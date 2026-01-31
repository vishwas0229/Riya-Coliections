<?php
/**
 * Razorpay Configuration and Service
 * 
 * This module provides Razorpay payment gateway integration for the PHP backend,
 * maintaining compatibility with the existing Node.js implementation while providing
 * enhanced security and error handling.
 * 
 * Requirements: 7.1, 7.2, 11.1
 */

require_once __DIR__ . '/environment.php';

/**
 * Razorpay Configuration Class
 */
class RazorpayConfig {
    private static $config = null;
    
    /**
     * Get Razorpay configuration
     */
    public static function getConfig() {
        if (self::$config === null) {
            self::$config = [
                'key_id' => env('RAZORPAY_KEY_ID'),
                'key_secret' => env('RAZORPAY_KEY_SECRET'),
                'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
                'currency' => env('RAZORPAY_CURRENCY', 'INR'),
                'api_url' => 'https://api.razorpay.com/v1/',
                'timeout' => 30,
                'supported_methods' => ['card', 'netbanking', 'wallet', 'upi', 'emi'],
                'supported_currencies' => ['INR']
            ];
        }
        
        return self::$config;
    }
    
    /**
     * Validate Razorpay configuration
     */
    public static function validate() {
        $config = self::getConfig();
        
        if (empty($config['key_id'])) {
            throw new Exception('Razorpay Key ID is not configured');
        }
        
        if (empty($config['key_secret'])) {
            throw new Exception('Razorpay Key Secret is not configured');
        }
        
        if (!preg_match('/^rzp_(test_|live_)?[a-zA-Z0-9]+$/', $config['key_id'])) {
            throw new Exception('Invalid Razorpay Key ID format');
        }
        
        return true;
    }
}

/**
 * Razorpay API Client
 * Simple HTTP client for Razorpay API without external dependencies
 */
class RazorpayClient {
    private $config;
    
    public function __construct() {
        $this->config = RazorpayConfig::getConfig();
        RazorpayConfig::validate();
    }
    
    /**
     * Make API request to Razorpay
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->config['api_url'] . ltrim($endpoint, '/');
        
        // Prepare authentication
        $auth = base64_encode($this->config['key_id'] . ':' . $this->config['key_secret']);
        
        // Prepare headers
        $headers = [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'User-Agent: RiyaCollections-PHP/1.0'
        ];
        
        // Initialize cURL
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ]);
        
        // Set method and data
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = 'Razorpay API error';
            
            if ($decodedResponse && isset($decodedResponse['error'])) {
                $errorMessage = $decodedResponse['error']['description'] ?? $decodedResponse['error']['code'] ?? $errorMessage;
            }
            
            throw new Exception("{$errorMessage} (HTTP {$httpCode})");
        }
        
        return $decodedResponse;
    }
    
    /**
     * Create Razorpay order
     */
    public function createOrder($amount, $currency = 'INR', $receipt = null, $notes = []) {
        $data = [
            'amount' => $this->convertToPaise($amount),
            'currency' => $currency,
            'receipt' => $receipt ?: $this->generateReceiptId(),
            'notes' => $notes
        ];
        
        Logger::info('Creating Razorpay order', [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => $data['receipt']
        ]);
        
        $response = $this->makeRequest('POST', 'orders', $data);
        
        Logger::info('Razorpay order created successfully', [
            'order_id' => $response['id'],
            'amount' => $response['amount'],
            'status' => $response['status']
        ]);
        
        return $response;
    }
    
    /**
     * Fetch order details
     */
    public function fetchOrder($orderId) {
        return $this->makeRequest('GET', "orders/{$orderId}");
    }
    
    /**
     * Fetch payment details
     */
    public function fetchPayment($paymentId) {
        return $this->makeRequest('GET', "payments/{$paymentId}");
    }
    
    /**
     * Capture payment
     */
    public function capturePayment($paymentId, $amount) {
        $data = [
            'amount' => $this->convertToPaise($amount)
        ];
        
        return $this->makeRequest('POST', "payments/{$paymentId}/capture", $data);
    }
    
    /**
     * Refund payment
     */
    public function refundPayment($paymentId, $amount = null, $notes = []) {
        $data = ['notes' => $notes];
        
        if ($amount !== null) {
            $data['amount'] = $this->convertToPaise($amount);
        }
        
        return $this->makeRequest('POST', "payments/{$paymentId}/refund", $data);
    }
    
    /**
     * Convert amount from rupees to paise
     */
    private function convertToPaise($amount) {
        return (int) round(floatval($amount) * 100);
    }
    
    /**
     * Convert amount from paise to rupees
     */
    public function convertToRupees($amount) {
        return floatval($amount) / 100;
    }
    
    /**
     * Generate receipt ID
     */
    private function generateReceiptId() {
        return 'receipt_' . time() . '_' . bin2hex(random_bytes(4));
    }
}

/**
 * Razorpay Service with signature verification and utilities
 */
class RazorpayService {
    private $client;
    private $config;
    
    public function __construct() {
        $this->client = new RazorpayClient();
        $this->config = RazorpayConfig::getConfig();
    }
    
    /**
     * Create order for payment
     */
    public function createOrder($orderData) {
        try {
            $amount = $orderData['amount'];
            $currency = $orderData['currency'] ?? $this->config['currency'];
            $receipt = $orderData['receipt'] ?? null;
            $notes = $orderData['notes'] ?? [];
            
            // Add order metadata to notes
            $notes['order_number'] = $orderData['order_number'] ?? '';
            $notes['customer_id'] = $orderData['customer_id'] ?? '';
            
            $razorpayOrder = $this->client->createOrder($amount, $currency, $receipt, $notes);
            
            return [
                'id' => $razorpayOrder['id'],
                'amount' => $this->client->convertToRupees($razorpayOrder['amount']),
                'currency' => $razorpayOrder['currency'],
                'receipt' => $razorpayOrder['receipt'],
                'status' => $razorpayOrder['status'],
                'created_at' => $razorpayOrder['created_at']
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to create Razorpay order', [
                'error' => $e->getMessage(),
                'order_data' => $orderData
            ]);
            throw $e;
        }
    }
    
    /**
     * Verify payment signature
     */
    public function verifyPaymentSignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature) {
        try {
            $payload = $razorpayOrderId . '|' . $razorpayPaymentId;
            $expectedSignature = hash_hmac('sha256', $payload, $this->config['key_secret']);
            
            $isValid = hash_equals($expectedSignature, $razorpaySignature);
            
            Logger::info('Payment signature verification', [
                'order_id' => $razorpayOrderId,
                'payment_id' => $razorpayPaymentId,
                'is_valid' => $isValid
            ]);
            
            return $isValid;
            
        } catch (Exception $e) {
            Logger::error('Payment signature verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $razorpayOrderId,
                'payment_id' => $razorpayPaymentId
            ]);
            return false;
        }
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($payload, $signature) {
        try {
            if (empty($this->config['webhook_secret'])) {
                Logger::warning('Webhook secret not configured, skipping signature verification');
                return true; // Allow webhook processing without signature verification
            }
            
            $expectedSignature = hash_hmac('sha256', $payload, $this->config['webhook_secret']);
            
            $isValid = hash_equals($expectedSignature, $signature);
            
            Logger::info('Webhook signature verification', [
                'is_valid' => $isValid,
                'payload_length' => strlen($payload)
            ]);
            
            return $isValid;
            
        } catch (Exception $e) {
            Logger::error('Webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Process webhook event
     */
    public function processWebhook($payload, $signature) {
        if (!$this->verifyWebhookSignature($payload, $signature)) {
            throw new Exception('Invalid webhook signature');
        }
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            throw new Exception('Invalid webhook payload');
        }
        
        Logger::info('Processing Razorpay webhook', [
            'event' => $event['event'] ?? 'unknown',
            'entity' => $event['payload']['payment']['entity'] ?? 'unknown'
        ]);
        
        return $event;
    }
    
    /**
     * Get payment details
     */
    public function getPaymentDetails($paymentId) {
        try {
            $payment = $this->client->fetchPayment($paymentId);
            
            return [
                'id' => $payment['id'],
                'amount' => $this->client->convertToRupees($payment['amount']),
                'currency' => $payment['currency'],
                'status' => $payment['status'],
                'method' => $payment['method'],
                'order_id' => $payment['order_id'],
                'created_at' => $payment['created_at'],
                'captured' => $payment['captured'] ?? false,
                'email' => $payment['email'] ?? null,
                'contact' => $payment['contact'] ?? null
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to fetch payment details', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Validate payment data
     */
    public function validatePaymentData($data) {
        $required = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate ID formats
        if (!preg_match('/^order_[a-zA-Z0-9]+$/', $data['razorpay_order_id'])) {
            throw new Exception('Invalid Razorpay order ID format');
        }
        
        if (!preg_match('/^pay_[a-zA-Z0-9]+$/', $data['razorpay_payment_id'])) {
            throw new Exception('Invalid Razorpay payment ID format');
        }
        
        if (!preg_match('/^[a-f0-9]{64}$/', $data['razorpay_signature'])) {
            throw new Exception('Invalid Razorpay signature format');
        }
        
        return true;
    }
    
    /**
     * Test Razorpay connection
     */
    public function testConnection() {
        try {
            // Create a test order with minimal amount
            $testOrder = $this->createOrder([
                'amount' => 1, // ₹1
                'currency' => 'INR',
                'receipt' => 'test_' . time(),
                'notes' => ['test' => 'connection_test']
            ]);
            
            Logger::info('Razorpay connection test successful', [
                'test_order_id' => $testOrder['id']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Razorpay connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get supported payment methods
     */
    public function getSupportedMethods() {
        return $this->config['supported_methods'];
    }
    
    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies() {
        return $this->config['supported_currencies'];
    }
    
    /**
     * Format payment method for display
     */
    public function formatPaymentMethod($method) {
        $methodNames = [
            'card' => 'Credit/Debit Card',
            'netbanking' => 'Net Banking',
            'wallet' => 'Digital Wallet',
            'upi' => 'UPI',
            'emi' => 'EMI'
        ];
        
        return $methodNames[$method] ?? ucfirst($method);
    }
    
    /**
     * Generate payment options for frontend
     */
    public function generatePaymentOptions($orderData) {
        return [
            'key' => $this->config['key_id'],
            'amount' => $this->client->convertToPaise($orderData['amount']),
            'currency' => $orderData['currency'] ?? $this->config['currency'],
            'order_id' => $orderData['razorpay_order_id'],
            'name' => 'Riya Collections',
            'description' => 'Order #' . ($orderData['order_number'] ?? ''),
            'image' => env('LOGO_URL', 'https://riyacollections.com/assets/logo.png'),
            'prefill' => [
                'name' => $orderData['customer_name'] ?? '',
                'email' => $orderData['customer_email'] ?? '',
                'contact' => $orderData['customer_phone'] ?? ''
            ],
            'theme' => [
                'color' => '#E91E63'
            ],
            'method' => $this->getSupportedMethods()
        ];
    }
}

// Global helper functions
function getRazorpayService() {
    static $service = null;
    if ($service === null) {
        $service = new RazorpayService();
    }
    return $service;
}

function createRazorpayOrder($orderData) {
    return getRazorpayService()->createOrder($orderData);
}

function verifyRazorpayPayment($orderId, $paymentId, $signature) {
    return getRazorpayService()->verifyPaymentSignature($orderId, $paymentId, $signature);
}