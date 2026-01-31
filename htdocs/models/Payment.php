<?php
/**
 * Payment Model
 * 
 * Handles payment transaction records, providing CRUD operations and
 * business logic for payment management in the PHP backend.
 * 
 * Requirements: 7.1, 7.2
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

class Payment {
    private $db;
    
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
    }
    
    /**
     * Create a new payment record
     */
    public function create($paymentData) {
        try {
            $this->validatePaymentData($paymentData);
            
            $sql = "INSERT INTO payments (
                order_id, payment_method, amount, currency, status,
                razorpay_order_id, razorpay_payment_id, razorpay_signature,
                razorpay_order_data, razorpay_payment_data,
                cod_charges, failure_reason, notes,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $paymentData['order_id'],
                $paymentData['payment_method'],
                $paymentData['amount'],
                $paymentData['currency'] ?? 'INR',
                $paymentData['status'] ?? self::STATUS_PENDING,
                $paymentData['razorpay_order_id'] ?? null,
                $paymentData['razorpay_payment_id'] ?? null,
                $paymentData['razorpay_signature'] ?? null,
                $paymentData['razorpay_order_data'] ?? null,
                $paymentData['razorpay_payment_data'] ?? null,
                $paymentData['cod_charges'] ?? null,
                $paymentData['failure_reason'] ?? null,
                $paymentData['notes'] ?? null,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ];
            
            $stmt = $this->db->executeQuery($sql, $params);
            $paymentId = $this->db->getConnection()->lastInsertId();
            
            Logger::info('Payment record created', [
                'payment_id' => $paymentId,
                'order_id' => $paymentData['order_id'],
                'method' => $paymentData['payment_method'],
                'amount' => $paymentData['amount']
            ]);
            
            return $paymentId;
            
        } catch (Exception $e) {
            Logger::error('Failed to create payment record', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);
            throw $e;
        }
    }
    
    /**
     * Find payment by ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM payments WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Find payment by order ID
     */
    public function findByOrderId($orderId) {
        $sql = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->executeQuery($sql, [$orderId]);
        return $stmt->fetch();
    }
    
    /**
     * Find all payments by order ID
     */
    public function findAllByOrderId($orderId) {
        $sql = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->executeQuery($sql, [$orderId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Find payment by Razorpay order ID
     */
    public function findByRazorpayOrderId($razorpayOrderId) {
        $sql = "SELECT * FROM payments WHERE razorpay_order_id = ?";
        $stmt = $this->db->executeQuery($sql, [$razorpayOrderId]);
        return $stmt->fetch();
    }
    
    /**
     * Find payment by Razorpay payment ID
     */
    public function findByRazorpayPaymentId($razorpayPaymentId) {
        $sql = "SELECT * FROM payments WHERE razorpay_payment_id = ?";
        $stmt = $this->db->executeQuery($sql, [$razorpayPaymentId]);
        return $stmt->fetch();
    }
    
    /**
     * Update payment record
     */
    public function update($id, $updateData) {
        try {
            $fields = [];
            $params = [];
            
            // Build dynamic update query
            foreach ($updateData as $field => $value) {
                if ($this->isValidField($field)) {
                    $fields[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($fields)) {
                throw new Exception('No valid fields to update');
            }
            
            // Always update the updated_at timestamp
            $fields[] = "updated_at = ?";
            $params[] = date('Y-m-d H:i:s');
            $params[] = $id;
            
            $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->executeQuery($sql, $params);
            
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected > 0) {
                Logger::info('Payment record updated', [
                    'payment_id' => $id,
                    'updated_fields' => array_keys($updateData)
                ]);
            }
            
            return $rowsAffected > 0;
            
        } catch (Exception $e) {
            Logger::error('Failed to update payment record', [
                'payment_id' => $id,
                'error' => $e->getMessage(),
                'update_data' => $updateData
            ]);
            throw $e;
        }
    }
    
    /**
     * Update payment by Razorpay order ID
     */
    public function updateByRazorpayOrderId($razorpayOrderId, $updateData) {
        try {
            $fields = [];
            $params = [];
            
            // Build dynamic update query
            foreach ($updateData as $field => $value) {
                if ($this->isValidField($field)) {
                    $fields[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($fields)) {
                throw new Exception('No valid fields to update');
            }
            
            // Always update the updated_at timestamp
            $fields[] = "updated_at = ?";
            $params[] = date('Y-m-d H:i:s');
            $params[] = $razorpayOrderId;
            
            $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE razorpay_order_id = ?";
            $stmt = $this->db->executeQuery($sql, $params);
            
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected > 0) {
                Logger::info('Payment record updated by Razorpay order ID', [
                    'razorpay_order_id' => $razorpayOrderId,
                    'updated_fields' => array_keys($updateData)
                ]);
            }
            
            return $rowsAffected > 0;
            
        } catch (Exception $e) {
            Logger::error('Failed to update payment by Razorpay order ID', [
                'razorpay_order_id' => $razorpayOrderId,
                'error' => $e->getMessage(),
                'update_data' => $updateData
            ]);
            throw $e;
        }
    }
    
    /**
     * Get payments with filters and pagination
     */
    public function getPayments($filters = [], $limit = 20, $offset = 0) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['order_id'])) {
            $whereClause .= " AND order_id = ?";
            $params[] = $filters['order_id'];
        }
        
        if (!empty($filters['payment_method'])) {
            $whereClause .= " AND payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['status'])) {
            $whereClause .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['start_date'])) {
            $whereClause .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $whereClause .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['min_amount'])) {
            $whereClause .= " AND amount >= ?";
            $params[] = $filters['min_amount'];
        }
        
        if (!empty($filters['max_amount'])) {
            $whereClause .= " AND amount <= ?";
            $params[] = $filters['max_amount'];
        }
        
        // Add pagination
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $sql = "SELECT * FROM payments 
                {$whereClause} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get payment count with filters
     */
    public function getPaymentCount($filters = []) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Apply same filters as getPayments
        if (!empty($filters['order_id'])) {
            $whereClause .= " AND order_id = ?";
            $params[] = $filters['order_id'];
        }
        
        if (!empty($filters['payment_method'])) {
            $whereClause .= " AND payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['status'])) {
            $whereClause .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['start_date'])) {
            $whereClause .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $whereClause .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['min_amount'])) {
            $whereClause .= " AND amount >= ?";
            $params[] = $filters['min_amount'];
        }
        
        if (!empty($filters['max_amount'])) {
            $whereClause .= " AND amount <= ?";
            $params[] = $filters['max_amount'];
        }
        
        $sql = "SELECT COUNT(*) as count FROM payments {$whereClause}";
        $stmt = $this->db->executeQuery($sql, $params);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    }
    
    /**
     * Get payment statistics
     */
    public function getStatistics($filters = []) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Apply filters
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
            AVG(amount) as avg_amount,
            MIN(amount) as min_amount,
            MAX(amount) as max_amount
        FROM payments 
        {$whereClause}
        GROUP BY payment_method, status
        ORDER BY payment_method, status";
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get daily payment summary
     */
    public function getDailySummary($startDate, $endDate) {
        $sql = "SELECT 
            DATE(created_at) as payment_date,
            payment_method,
            status,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM payments 
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY DATE(created_at), payment_method, status
        ORDER BY payment_date DESC, payment_method, status";
        
        $stmt = $this->db->executeQuery($sql, [$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get payments by user (through orders)
     */
    public function getPaymentsByUser($userId, $limit = 20, $offset = 0) {
        $sql = "SELECT p.* 
        FROM payments p
        INNER JOIN orders o ON p.order_id = o.id
        WHERE o.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";
        
        $stmt = $this->db->executeQuery($sql, [$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get failed payments for retry
     */
    public function getFailedPayments($limit = 50) {
        $sql = "SELECT * FROM payments 
        WHERE status = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT ?";
        
        $stmt = $this->db->executeQuery($sql, [self::STATUS_FAILED, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get pending payments older than specified minutes
     */
    public function getPendingPayments($olderThanMinutes = 30) {
        $sql = "SELECT * FROM payments 
        WHERE status = ? 
        AND created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY created_at ASC";
        
        $stmt = $this->db->executeQuery($sql, [self::STATUS_PENDING, $olderThanMinutes]);
        return $stmt->fetchAll();
    }
    
    /**
     * Delete payment record (soft delete by updating status)
     */
    public function delete($id) {
        try {
            $updateData = [
                'status' => self::STATUS_CANCELLED,
                'cancelled_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->update($id, $updateData);
            
            if ($result) {
                Logger::info('Payment record deleted (soft delete)', [
                    'payment_id' => $id
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error('Failed to delete payment record', [
                'payment_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Validate payment data
     */
    private function validatePaymentData($data) {
        $required = ['order_id', 'payment_method', 'amount'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate payment method
        $validMethods = [self::METHOD_RAZORPAY, self::METHOD_COD];
        if (!in_array($data['payment_method'], $validMethods)) {
            throw new Exception('Invalid payment method');
        }
        
        // Validate amount
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new Exception('Invalid payment amount');
        }
        
        // Validate status if provided
        if (isset($data['status'])) {
            $validStatuses = [
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                self::STATUS_COMPLETED,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED,
                self::STATUS_REFUNDED
            ];
            
            if (!in_array($data['status'], $validStatuses)) {
                throw new Exception('Invalid payment status');
            }
        }
        
        return true;
    }
    
    /**
     * Check if field is valid for updates
     */
    private function isValidField($field) {
        $validFields = [
            'status', 'razorpay_payment_id', 'razorpay_signature',
            'razorpay_payment_data', 'cod_charges', 'failure_reason',
            'notes', 'verified_at', 'confirmed_at', 'captured_at',
            'authorized_at', 'failed_at', 'cancelled_at', 'refunded_at',
            'confirmation_data'
        ];
        
        return in_array($field, $validFields);
    }
    
    /**
     * Get payment method display name
     */
    public static function getPaymentMethodName($method) {
        $methodNames = [
            self::METHOD_RAZORPAY => 'Online Payment',
            self::METHOD_COD => 'Cash on Delivery'
        ];
        
        return $methodNames[$method] ?? ucfirst($method);
    }
    
    /**
     * Get payment status display name
     */
    public static function getPaymentStatusName($status) {
        $statusNames = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded'
        ];
        
        return $statusNames[$status] ?? ucfirst($status);
    }
    
    /**
     * Check if payment is successful
     */
    public static function isSuccessful($status) {
        return $status === self::STATUS_COMPLETED;
    }
    
    /**
     * Check if payment is pending
     */
    public static function isPending($status) {
        return in_array($status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }
    
    /**
     * Check if payment has failed
     */
    public static function hasFailed($status) {
        return in_array($status, [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }
    
    /**
     * Format payment amount for display
     */
    public static function formatAmount($amount, $currency = 'INR') {
        $symbols = [
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format($amount, 2);
    }
    
    /**
     * Get payment summary for order
     */
    public function getOrderPaymentSummary($orderId) {
        $sql = "SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_payments,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_payments,
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as total_paid,
            MAX(created_at) as last_payment_attempt
        FROM payments 
        WHERE order_id = ?";
        
        $stmt = $this->db->executeQuery($sql, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_COMPLETED,
            $orderId
        ]);
        
        return $stmt->fetch();
    }
}