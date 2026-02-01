<?php
/**
 * Payment Controller
 * 
 * Handles all payment-related API endpoints including Razorpay integration,
 * COD processing, and webhook handling for the PHP backend.
 * 
 * Requirements: 7.1, 7.2
 */

require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class PaymentController {
    private $paymentService;
    
    public function __construct() {
        $this->paymentService = new PaymentService();
    }
    
    /**
     * Handle payment routes
     */
    public function handleRequest($method = null, $path = null, $params = []) {
        // Get method and path from server if not provided
        $method = $method ?? $_SERVER['REQUEST_METHOD'];
        $path = $path ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        try {
            switch ($method) {
                case 'POST':
                    return $this->handlePostRequest($path, $params);
                case 'GET':
                    return $this->handleGetRequest($path, $params);
                case 'PUT':
                    return $this->handlePutRequest($path, $params);
                default:
                    Response::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            Logger::error('PaymentController error', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle POST requests
     */
    private function handlePostRequest($path, $params) {
        switch ($path) {
            case '/api/payments/razorpay/create':
                return $this->createRazorpayPayment();
                
            case '/api/payments/razorpay/verify':
                return $this->verifyRazorpayPayment();
                
            case '/api/payments/cod':
                return $this->createCODPayment();
                
            case '/api/payments/webhook/razorpay':
                return $this->handleRazorpayWebhook();
                
            default:
                Response::error('Endpoint not found', 404);
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGetRequest($path, $params) {
        // Extract payment ID from params if present (from route parameter)
        if (isset($params['id'])) {
            return $this->getPaymentDetails($params['id']);
        }
        
        switch ($path) {
            case '/api/payments/methods':
                return $this->getPaymentMethods();
                
            case '/api/payments/statistics':
                return $this->getPaymentStatistics();
                
            case '/api/payments/test':
                return $this->testPaymentSystem();
                
            default:
                Response::error('Endpoint not found', 404);
        }
    }
    
    /**
     * Handle PUT requests
     */
    private function handlePutRequest($path, $params) {
        // Extract order ID from params for COD confirmation
        if (isset($params['id']) && strpos($path, '/api/payments/cod/confirm/') !== false) {
            return $this->confirmCODPayment($params['id']);
        }
        
        Response::error('Endpoint not found', 404);
    }
    
    /**
     * Create Razorpay payment
     * POST /api/payments/razorpay/create
     */
    public function createRazorpayPayment() {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        
        // Get request data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('Invalid JSON input', 400);
            return;
        }
        
        // Validate required fields
        $required = ['order_id', 'amount'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                Response::error("Missing required field: {$field}", 400);
                return;
            }
        }
        
        try {
            // Prepare payment data
            $paymentData = [
                'order_id' => $input['order_id'],
                'payment_method' => PaymentService::METHOD_RAZORPAY,
                'amount' => $input['amount'],
                'currency' => $input['currency'] ?? 'INR',
                'order_number' => $input['order_number'] ?? '',
                'customer_id' => $user['id'],
                'customer_name' => $user['first_name'] . ' ' . $user['last_name'],
                'customer_email' => $user['email'],
                'customer_phone' => $user['phone'] ?? ''
            ];
            
            // Create payment
            $payment = $this->paymentService->createPayment($paymentData);
            
            Response::success([
                'payment_id' => $payment['id'],
                'razorpay_order_id' => $payment['razorpay_order_id'],
                'payment_options' => $payment['payment_options'],
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
                'status' => $payment['status']
            ], 'Razorpay payment created successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to create Razorpay payment', [
                'user_id' => $user['id'],
                'order_id' => $input['order_id'],
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Verify Razorpay payment
     * POST /api/payments/razorpay/verify
     */
    public function verifyRazorpayPayment() {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        
        // Get request data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('Invalid JSON input', 400);
            return;
        }
        
        // Validate required fields
        $required = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                Response::error("Missing required field: {$field}", 400);
                return;
            }
        }
        
        try {
            // Verify payment
            $result = $this->paymentService->verifyRazorpayPayment($input);
            
            if ($result['success']) {
                Response::success([
                    'payment_id' => $result['payment_id'],
                    'amount' => $result['amount'],
                    'status' => $result['status'],
                    'verified' => true
                ], 'Payment verified successfully');
            } else {
                Response::error('Payment verification failed', 400);
            }
            
        } catch (Exception $e) {
            Logger::error('Payment verification failed', [
                'user_id' => $user['id'],
                'razorpay_order_id' => $input['razorpay_order_id'],
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Create COD payment
     * POST /api/payments/cod
     */
    public function createCODPayment() {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        
        // Get request data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('Invalid JSON input', 400);
            return;
        }
        
        // Validate required fields
        $required = ['order_id', 'amount'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                Response::error("Missing required field: {$field}", 400);
                return;
            }
        }
        
        try {
            // Prepare payment data
            $paymentData = [
                'order_id' => $input['order_id'],
                'payment_method' => PaymentService::METHOD_COD,
                'amount' => $input['amount'],
                'currency' => $input['currency'] ?? 'INR',
                'order_number' => $input['order_number'] ?? '',
                'customer_id' => $user['id']
            ];
            
            // Create COD payment
            $payment = $this->paymentService->createPayment($paymentData);
            
            Response::success([
                'payment_id' => $payment['id'],
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
                'cod_charges' => $payment['cod_charges'],
                'status' => $payment['status']
            ], 'COD payment created successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to create COD payment', [
                'user_id' => $user['id'],
                'order_id' => $input['order_id'],
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Confirm COD payment (admin only)
     * PUT /api/payments/cod/confirm/{order_id}
     */
    public function confirmCODPayment($orderId) {
        // Authenticate admin user
        $user = AuthMiddleware::authenticate();
        
        if (!isset($user['role']) || $user['role'] !== 'admin') {
            Response::error('Admin access required', 403);
            return;
        }
        
        // Get request data
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        try {
            // Confirm COD payment
            $result = $this->paymentService->confirmCODPayment($orderId, $input);
            
            if ($result['success']) {
                Response::success([
                    'payment_id' => $result['payment_id'],
                    'status' => $result['status'],
                    'confirmed' => true
                ], 'COD payment confirmed successfully');
            } else {
                Response::error('COD payment confirmation failed', 400);
            }
            
        } catch (Exception $e) {
            Logger::error('COD payment confirmation failed', [
                'admin_id' => $user['id'],
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Handle Razorpay webhook
     * POST /api/payments/webhook/razorpay
     */
    public function handleRazorpayWebhook() {
        // Get raw payload and signature
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        
        if (empty($payload)) {
            Response::error('Empty webhook payload', 400);
            return;
        }
        
        if (empty($signature)) {
            Response::error('Missing webhook signature', 400);
            return;
        }
        
        try {
            // Process webhook
            $result = $this->paymentService->processWebhook($payload, $signature);
            
            if ($result['success']) {
                Response::success([
                    'event_type' => $result['event_type'],
                    'processed' => true
                ], 'Webhook processed successfully');
            } else {
                Response::error('Webhook processing failed', 400);
            }
            
        } catch (Exception $e) {
            Logger::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload_length' => strlen($payload)
            ]);
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Get payment details
     * GET /api/payments/{payment_id}
     */
    public function getPaymentDetails($paymentId) {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        
        try {
            $payment = $this->paymentService->getPaymentById($paymentId);
            
            if (!$payment) {
                Response::error('Payment not found', 404);
                return;
            }
            
            // Check if user has access to this payment
            // Users can only see their own payments, admins can see all
            if ($user['role'] !== 'admin') {
                // Get order details to check ownership
                $orderSql = "SELECT user_id FROM orders WHERE id = ?";
                $db = Database::getInstance();
                $stmt = $db->executeQuery($orderSql, [$payment['order_id']]);
                $order = $stmt->fetch();
                
                if (!$order || $order['user_id'] != $user['id']) {
                    Response::error('Access denied', 403);
                    return;
                }
            }
            
            // Prepare response data
            $responseData = [
                'id' => $payment['id'],
                'order_id' => $payment['order_id'],
                'payment_method' => $payment['payment_method'],
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
                'status' => $payment['status'],
                'created_at' => $payment['created_at'],
                'updated_at' => $payment['updated_at']
            ];
            
            // Add method-specific data
            if ($payment['payment_method'] === PaymentService::METHOD_RAZORPAY) {
                $responseData['razorpay_order_id'] = $payment['razorpay_order_id'];
                $responseData['razorpay_payment_id'] = $payment['razorpay_payment_id'];
                
                if ($payment['verified_at']) {
                    $responseData['verified_at'] = $payment['verified_at'];
                }
            } elseif ($payment['payment_method'] === PaymentService::METHOD_COD) {
                $responseData['cod_charges'] = $payment['cod_charges'];
                
                if ($payment['confirmed_at']) {
                    $responseData['confirmed_at'] = $payment['confirmed_at'];
                }
            }
            
            Response::success($responseData, 'Payment details retrieved successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to get payment details', [
                'payment_id' => $paymentId,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get supported payment methods
     * GET /api/payments/methods
     */
    public function getPaymentMethods() {
        try {
            $methods = $this->paymentService->getSupportedPaymentMethods();
            
            Response::success($methods, 'Payment methods retrieved successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to get payment methods', [
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get payment statistics (admin only)
     * GET /api/payments/statistics
     */
    public function getPaymentStatistics() {
        // Authenticate admin user
        $user = AuthMiddleware::authenticate();
        
        if (!isset($user['role']) || $user['role'] !== 'admin') {
            Response::error('Admin access required', 403);
            return;
        }
        
        try {
            // Get query parameters for filtering
            $filters = [];
            
            if (isset($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            
            if (isset($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }
            
            if (isset($_GET['payment_method'])) {
                $filters['payment_method'] = $_GET['payment_method'];
            }
            
            $statistics = $this->paymentService->getPaymentStatistics($filters);
            
            Response::success($statistics, 'Payment statistics retrieved successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to get payment statistics', [
                'admin_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 500);
        }
    }
    
    /**
     * Test payment system connectivity (admin only)
     * GET /api/payments/test
     */
    public function testPaymentSystem() {
        // Authenticate admin user
        $user = AuthMiddleware::authenticate();
        
        if (!isset($user['role']) || $user['role'] !== 'admin') {
            Response::error('Admin access required', 403);
            return;
        }
        
        try {
            $razorpayService = getRazorpayService();
            $connectionTest = $razorpayService->testConnection();
            
            $testResults = [
                'razorpay_connection' => $connectionTest,
                'database_connection' => true, // If we reach here, DB is working
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            Response::success($testResults, 'Payment system test completed');
            
        } catch (Exception $e) {
            Logger::error('Payment system test failed', [
                'admin_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 500);
        }
    }
}

// Helper function to get PaymentController instance
function getPaymentController() {
    static $controller = null;
    if ($controller === null) {
        $controller = new PaymentController();
    }
    return $controller;
}