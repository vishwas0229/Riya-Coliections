<?php
/**
 * Payment Service
 * 
 * Comprehensive payment processing service that handles Razorpay integration,
 * COD processing, and payment transaction management for the PHP backend.
 * 
 * Requirements: 7.1, 7.2
 */

require_once __DIR__ . '/../config/razorpay.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class PaymentService {
    private $db;
    private $razorpayService;
    
    // Payment methods
    const METHOD_RAZORPAY = 'razorpay';
    const METHOD_COD = 'cod';
    
    // Payment statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->razorpayService = getRazorpayService();
    }
    
    /**
     * Create payment for an order
     */
    public function createPayment($orderData) {
        try {
            $this->db->beginTransaction();
            
            // Validate order data
            $this->validateOrderData($orderData);
            
            $paymentMethod = $orderData['payment_method'];
            $paymentData = null;
            
            switch ($paymentMethod) {
                case self::METHOD_RAZORPAY:
                    $paymentData = $this->createRazorpayPayment($orderData);
                    break;
                    
                case self::METHOD_COD:
                    $paymentData = $this->createCODPayment($orderData);
                    break;
                    
                default:
                    throw new Exception("Unsupported payment method: {$paymentMethod}");
            }
            
            // Store payment record in database
            $paymentId = $this->storePaymentRecord($paymentData);
            $paymentData['id'] = $paymentId;
            
            $this->db->commit();
            
            Logger::info('Payment created successfully', [
                'payment_id' => $paymentId,
                'order_id' => $orderData['order_id'],
                'method' => $paymentMethod,
                'amount' => $orderData['amount']
            ]);
            
            return $paymentData;
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            Logger::error('Failed to create payment', [
                'error' => $e->getMessage(),
                'order_data' => $orderData
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Create Razorpay payment
     */
    private function createRazorpayPayment($orderData) {
        try {
            // Create Razorpay order
            $razorpayOrderData = [
                'amount' => $orderData['amount'],
                'currency' => $orderData['currency'] ?? 'INR',
                'receipt' => $this->generateReceiptId($orderData['order_id']),
                'order_number' => $orderData['order_number'] ?? '',
                'customer_id' => $orderData['customer_id'] ?? ''
            ];
            
            $razorpayOrder = $this->razorpayService->createOrder($razorpayOrderData);
            
            return [
                'order_id' => $orderData['order_id'],
                'payment_method' => self::METHOD_RAZORPAY,
                'amount' => $orderData['amount'],
                'currency' => $orderData['currency'] ?? 'INR',
                'status' => self::STATUS_PENDING,
                'razorpay_order_id' => $razorpayOrder['id'],
                'razorpay_order_data' => json_encode($razorpayOrder),
                'payment_options' => $this->generateRazorpayOptions($orderData, $razorpayOrder),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to create Razorpay payment', [
                'error' => $e->getMessage(),
                'order_data' => $orderData
            ]);
            throw new Exception("Failed to create Razorpay payment: " . $e->getMessage());
        }
    }
    
    /**
     * Create COD payment
     */
    private function createCODPayment($orderData) {
        // Validate COD eligibility
        $this->validateCODEligibility($orderData);
        
        return [
            'order_id' => $orderData['order_id'],
            'payment_method' => self::METHOD_COD,
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'] ?? 'INR',
            'status' => self::STATUS_PENDING,
            'cod_charges' => $this->calculateCODCharges($orderData['amount']),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Verify Razorpay payment
     */
    public function verifyRazorpayPayment($paymentData) {
        try {
            // Validate payment data
            $this->razorpayService->validatePaymentData($paymentData);
            
            // Verify signature
            $isValid = $this->razorpayService->verifyPaymentSignature(
                $paymentData['razorpay_order_id'],
                $paymentData['razorpay_payment_id'],
                $paymentData['razorpay_signature']
            );
            
            if (!$isValid) {
                throw new Exception('Invalid payment signature');
            }
            
            // Get payment details from Razorpay
            $razorpayPayment = $this->razorpayService->getPaymentDetails($paymentData['razorpay_payment_id']);
            
            // Update payment record
            $updateData = [
                'status' => self::STATUS_COMPLETED,
                'razorpay_payment_id' => $paymentData['razorpay_payment_id'],
                'razorpay_signature' => $paymentData['razorpay_signature'],
                'razorpay_payment_data' => json_encode($razorpayPayment),
                'verified_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->updatePaymentByRazorpayOrderId($paymentData['razorpay_order_id'], $updateData);
            
            Logger::info('Razorpay payment verified successfully', [
                'razorpay_order_id' => $paymentData['razorpay_order_id'],
                'razorpay_payment_id' => $paymentData['razorpay_payment_id'],
                'amount' => $razorpayPayment['amount']
            ]);
            
            return [
                'success' => true,
                'payment_id' => $razorpayPayment['id'],
                'amount' => $razorpayPayment['amount'],
                'status' => $razorpayPayment['status']
            ];
            
        } catch (Exception $e) {
            Logger::error('Razorpay payment verification failed', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);
            
            // Update payment status to failed
            if (isset($paymentData['razorpay_order_id'])) {
                $this->updatePaymentByRazorpayOrderId($paymentData['razorpay_order_id'], [
                    'status' => self::STATUS_FAILED,
                    'failure_reason' => $e->getMessage(),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Process COD payment confirmation
     */
    public function confirmCODPayment($orderId, $confirmationData = []) {
        try {
            $payment = $this->getPaymentByOrderId($orderId);
            
            if (!$payment) {
                throw new Exception('Payment not found for order');
            }
            
            if ($payment['payment_method'] !== self::METHOD_COD) {
                throw new Exception('Invalid payment method for COD confirmation');
            }
            
            if ($payment['status'] !== self::STATUS_PENDING) {
                throw new Exception('Payment is not in pending status');
            }
            
            // Update payment status
            $updateData = [
                'status' => self::STATUS_COMPLETED,
                'confirmed_at' => date('Y-m-d H:i:s'),
                'confirmation_data' => json_encode($confirmationData),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->updatePayment($payment['id'], $updateData);
            
            Logger::info('COD payment confirmed', [
                'payment_id' => $payment['id'],
                'order_id' => $orderId,
                'amount' => $payment['amount']
            ]);
            
            return [
                'success' => true,
                'payment_id' => $payment['id'],
                'status' => self::STATUS_COMPLETED
            ];
            
        } catch (Exception $e) {
            Logger::error('COD payment confirmation failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            throw $e;
        }
    }
    
    /**
     * Process webhook from Razorpay
     */
    public function processWebhook($payload, $signature) {
        try {
            $event = $this->razorpayService->processWebhook($payload, $signature);
            
            $eventType = $event['event'];
            $paymentEntity = $event['payload']['payment']['entity'] ?? null;
            
            if (!$paymentEntity) {
                throw new Exception('Invalid webhook payload: missing payment entity');
            }
            
            Logger::info('Processing Razorpay webhook', [
                'event_type' => $eventType,
                'payment_id' => $paymentEntity['id'],
                'order_id' => $paymentEntity['order_id']
            ]);
            
            switch ($eventType) {
                case 'payment.captured':
                    $this->handlePaymentCaptured($paymentEntity);
                    break;
                    
                case 'payment.failed':
                    $this->handlePaymentFailed($paymentEntity);
                    break;
                    
                case 'payment.authorized':
                    $this->handlePaymentAuthorized($paymentEntity);
                    break;
                    
                default:
                    Logger::info('Unhandled webhook event type', ['event_type' => $eventType]);
            }
            
            return ['success' => true, 'event_type' => $eventType];
            
        } catch (Exception $e) {
            Logger::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload_length' => strlen($payload)
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle payment captured webhook
     */
    private function handlePaymentCaptured($paymentEntity) {
        $updateData = [
            'status' => self::STATUS_COMPLETED,
            'razorpay_payment_id' => $paymentEntity['id'],
            'razorpay_payment_data' => json_encode($paymentEntity),
            'captured_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->updatePaymentByRazorpayOrderId($paymentEntity['order_id'], $updateData);
        
        Logger::info('Payment captured via webhook', [
            'payment_id' => $paymentEntity['id'],
            'order_id' => $paymentEntity['order_id'],
            'amount' => $paymentEntity['amount']
        ]);
    }
    
    /**
     * Handle payment failed webhook
     */
    private function handlePaymentFailed($paymentEntity) {
        $updateData = [
            'status' => self::STATUS_FAILED,
            'razorpay_payment_id' => $paymentEntity['id'],
            'razorpay_payment_data' => json_encode($paymentEntity),
            'failure_reason' => $paymentEntity['error_description'] ?? 'Payment failed',
            'failed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->updatePaymentByRazorpayOrderId($paymentEntity['order_id'], $updateData);
        
        Logger::warning('Payment failed via webhook', [
            'payment_id' => $paymentEntity['id'],
            'order_id' => $paymentEntity['order_id'],
            'error' => $paymentEntity['error_description'] ?? 'Unknown error'
        ]);
    }
    
    /**
     * Handle payment authorized webhook
     */
    private function handlePaymentAuthorized($paymentEntity) {
        $updateData = [
            'status' => self::STATUS_PROCESSING,
            'razorpay_payment_id' => $paymentEntity['id'],
            'razorpay_payment_data' => json_encode($paymentEntity),
            'authorized_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->updatePaymentByRazorpayOrderId($paymentEntity['order_id'], $updateData);
        
        Logger::info('Payment authorized via webhook', [
            'payment_id' => $paymentEntity['id'],
            'order_id' => $paymentEntity['order_id'],
            'amount' => $paymentEntity['amount']
        ]);
    }
    
    /**
     * Get payment by order ID
     */
    public function getPaymentByOrderId($orderId) {
        $sql = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->executeQuery($sql, [$orderId]);
        return $stmt->fetch();
    }
    
    /**
     * Get payment by ID
     */
    public function getPaymentById($paymentId) {
        $sql = "SELECT * FROM payments WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$paymentId]);
        return $stmt->fetch();
    }
    
    /**
     * Get payment by Razorpay order ID
     */
    public function getPaymentByRazorpayOrderId($razorpayOrderId) {
        $sql = "SELECT * FROM payments WHERE razorpay_order_id = ?";
        $stmt = $this->db->executeQuery($sql, [$razorpayOrderId]);
        return $stmt->fetch();
    }
    
    /**
     * Store payment record in database
     */
    private function storePaymentRecord($paymentData) {
        $sql = "INSERT INTO payments (
            order_id, payment_method, amount, currency, status,
            razorpay_order_id, razorpay_order_data, cod_charges,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $paymentData['order_id'],
            $paymentData['payment_method'],
            $paymentData['amount'],
            $paymentData['currency'],
            $paymentData['status'],
            $paymentData['razorpay_order_id'] ?? null,
            $paymentData['razorpay_order_data'] ?? null,
            $paymentData['cod_charges'] ?? null,
            $paymentData['created_at']
        ];
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Update payment record
     */
    private function updatePayment($paymentId, $updateData) {
        $fields = [];
        $params = [];
        
        foreach ($updateData as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $paymentId;
        
        $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, $params);
        
        // Create polling notification for payment status update
        if (isset($updateData['status'])) {
            $this->createPaymentNotification($paymentId, $updateData['status']);
        }
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Update payment by Razorpay order ID
     */
    private function updatePaymentByRazorpayOrderId($razorpayOrderId, $updateData) {
        $fields = [];
        $params = [];
        
        foreach ($updateData as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $razorpayOrderId;
        
        $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE razorpay_order_id = ?";
        $stmt = $this->db->executeQuery($sql, $params);
        
        // Create polling notification for payment status update
        if (isset($updateData['status'])) {
            $this->createPaymentNotificationByRazorpayOrderId($razorpayOrderId, $updateData['status']);
        }
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Validate order data for payment
     */
    private function validateOrderData($orderData) {
        $required = ['order_id', 'payment_method', 'amount'];
        
        foreach ($required as $field) {
            if (!isset($orderData[$field]) || empty($orderData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate amount
        if (!is_numeric($orderData['amount']) || $orderData['amount'] <= 0) {
            throw new Exception('Invalid payment amount');
        }
        
        // Validate payment method
        $validMethods = [self::METHOD_RAZORPAY, self::METHOD_COD];
        if (!in_array($orderData['payment_method'], $validMethods)) {
            throw new Exception('Invalid payment method');
        }
        
        return true;
    }
    
    /**
     * Validate COD eligibility
     */
    private function validateCODEligibility($orderData) {
        // Check minimum order amount for COD
        $minCODAmount = env('COD_MIN_AMOUNT', 100);
        if ($orderData['amount'] < $minCODAmount) {
            throw new Exception("COD not available for orders below ₹{$minCODAmount}");
        }
        
        // Check maximum order amount for COD
        $maxCODAmount = env('COD_MAX_AMOUNT', 50000);
        if ($orderData['amount'] > $maxCODAmount) {
            throw new Exception("COD not available for orders above ₹{$maxCODAmount}");
        }
        
        return true;
    }
    
    /**
     * Calculate COD charges
     */
    private function calculateCODCharges($amount) {
        $codChargePercent = env('COD_CHARGE_PERCENT', 2); // 2% default
        $codChargeMin = env('COD_CHARGE_MIN', 20); // ₹20 minimum
        $codChargeMax = env('COD_CHARGE_MAX', 100); // ₹100 maximum
        
        $charges = ($amount * $codChargePercent) / 100;
        $charges = max($charges, $codChargeMin);
        $charges = min($charges, $codChargeMax);
        
        return round($charges, 2);
    }
    
    /**
     * Generate receipt ID
     */
    private function generateReceiptId($orderId) {
        return "receipt_order_{$orderId}_" . time();
    }
    
    /**
     * Generate Razorpay payment options for frontend
     */
    private function generateRazorpayOptions($orderData, $razorpayOrder) {
        return $this->razorpayService->generatePaymentOptions([
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'] ?? 'INR',
            'razorpay_order_id' => $razorpayOrder['id'],
            'order_number' => $orderData['order_number'] ?? '',
            'customer_name' => $orderData['customer_name'] ?? '',
            'customer_email' => $orderData['customer_email'] ?? '',
            'customer_phone' => $orderData['customer_phone'] ?? ''
        ]);
    }
    
    /**
     * Get payment statistics
     */
    public function getPaymentStatistics($filters = []) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $whereClause .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $whereClause .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['payment_method'])) {
            $whereClause .= " AND payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        $sql = "SELECT 
            payment_method,
            status,
            COUNT(*) as count,
            SUM(amount) as total_amount,
            AVG(amount) as avg_amount
        FROM payments 
        {$whereClause}
        GROUP BY payment_method, status
        ORDER BY payment_method, status";
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get supported payment methods
     */
    public function getSupportedPaymentMethods() {
        return [
            self::METHOD_RAZORPAY => [
                'name' => 'Online Payment',
                'description' => 'Pay securely using Credit/Debit Card, Net Banking, UPI, or Wallet',
                'enabled' => true,
                'methods' => $this->razorpayService->getSupportedMethods()
            ],
            self::METHOD_COD => [
                'name' => 'Cash on Delivery',
                'description' => 'Pay when your order is delivered',
                'enabled' => env('COD_ENABLED', true),
                'charges' => [
                    'percent' => env('COD_CHARGE_PERCENT', 2),
                    'min' => env('COD_CHARGE_MIN', 20),
                    'max' => env('COD_CHARGE_MAX', 100)
                ]
            ]
        ];
    }
    
    /**
     * Create payment notification
     */
    private function createPaymentNotification($paymentId, $status) {
        try {
            // Get payment and order details
            $payment = $this->getPaymentById($paymentId);
            if (!$payment) {
                return;
            }
            
            $order = $this->db->fetchOne(
                "SELECT id, user_id, order_number, total_amount FROM orders WHERE id = ?",
                [$payment['order_id']]
            );
            
            if (!$order) {
                return;
            }
            
            // Load PollingService
            require_once __DIR__ . '/PollingService.php';
            $pollingService = new PollingService();
            
            // Create notification based on status
            $title = '';
            $message = '';
            
            switch ($status) {
                case self::STATUS_COMPLETED:
                    $title = 'Payment Successful';
                    $message = "Payment for order {$order['order_number']} has been completed successfully.";
                    break;
                case self::STATUS_FAILED:
                    $title = 'Payment Failed';
                    $message = "Payment for order {$order['order_number']} has failed. Please try again.";
                    break;
                case self::STATUS_REFUNDED:
                    $title = 'Payment Refunded';
                    $message = "Payment for order {$order['order_number']} has been refunded.";
                    break;
                default:
                    return; // Don't create notifications for other statuses
            }
            
            $pollingService->createNotification(
                $order['user_id'],
                'payment_status',
                $title,
                $message,
                [
                    'payment_id' => $paymentId,
                    'order_id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'payment_status' => $status,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount']
                ]
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to create payment notification', [
                'payment_id' => $paymentId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create payment notification by Razorpay order ID
     */
    private function createPaymentNotificationByRazorpayOrderId($razorpayOrderId, $status) {
        try {
            // Get payment by Razorpay order ID
            $payment = $this->db->fetchOne(
                "SELECT id FROM payments WHERE razorpay_order_id = ?",
                [$razorpayOrderId]
            );
            
            if ($payment) {
                $this->createPaymentNotification($payment['id'], $status);
            }
            
        } catch (Exception $e) {
            Logger::error('Failed to create payment notification by Razorpay order ID', [
                'razorpay_order_id' => $razorpayOrderId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
}