<?php
/**
 * OrderController Unit Tests
 * 
 * Comprehensive unit tests for the OrderController class covering all endpoints
 * including order creation, retrieval, cancellation, and admin operations.
 * Tests both success and error scenarios to ensure robust functionality.
 * 
 * Requirements: 6.1, 6.2, 11.1
 */

require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Address.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/bootstrap.php';

class OrderControllerTest {
    private $controller;
    private $orderModel;
    private $addressModel;
    private $productModel;
    private $userModel;
    private $categoryModel;
    private $testUser;
    private $testAdmin;
    private $testProduct;
    private $testAddress;
    
    public function __construct() {
        $this->controller = new OrderController();
        $this->orderModel = new Order();
        $this->addressModel = new Address();
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->categoryModel = new Category();
    }
    
    /**
     * Set up test data
     */
    public function setUp() {
        // Create test category
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'Test category for order tests'
        ];
        $category = $this->categoryModel->createCategory($categoryData);
        
        // Create test product
        $productData = [
            'name' => 'Test Product',
            'description' => 'Test product for order tests',
            'price' => 100.00,
            'stock_quantity' => 50,
            'category_id' => $category['id'],
            'sku' => 'TEST-PRODUCT-001'
        ];
        $this->testProduct = $this->productModel->createProduct($productData);
        
        // Create test user
        $userData = [
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '9876543210'
        ];
        $this->testUser = $this->userModel->createUser($userData);
        
        // Create test admin
        $adminData = [
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'phone' => '9876543211',
            'role' => 'admin'
        ];
        $this->testAdmin = $this->userModel->createUser($adminData);
        
        // Create test address
        $addressData = [
            'user_id' => $this->testUser['id'],
            'type' => 'home',
            'first_name' => 'Test',
            'last_name' => 'User',
            'address_line1' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '123456',
            'country' => 'India',
            'phone' => '9876543210',
            'is_default' => true
        ];
        $this->testAddress = $this->addressModel->createAddress($addressData);
    }
    
    /**
     * Clean up test data
     */
    public function tearDown() {
        // Clean up in reverse order of creation
        if ($this->testAddress) {
            $this->addressModel->deleteById($this->testAddress['id']);
        }
        if ($this->testAdmin) {
            $this->userModel->deleteById($this->testAdmin['id']);
        }
        if ($this->testUser) {
            $this->userModel->deleteById($this->testUser['id']);
        }
        if ($this->testProduct) {
            $this->productModel->deleteById($this->testProduct['id']);
        }
    }
    
    /**
     * Test order creation with valid data
     */
    public function testCreateOrderSuccess() {
        // Mock authentication
        $this->mockAuthentication($this->testUser);
        
        // Prepare order data
        $orderData = [
            'payment_method' => 'cod',
            'currency' => 'INR',
            'shipping_address_id' => $this->testAddress['id'],
            'items' => [
                [
                    'product_id' => $this->testProduct['id'],
                    'quantity' => 2
                ]
            ]
        ];
        
        // Mock request input
        $this->mockRequestInput($orderData);
        
        // Capture output
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === true, 'Order creation should succeed');
        assert(isset($response['data']['id']), 'Response should contain order ID');
        assert(isset($response['data']['order_number']), 'Response should contain order number');
        assert($response['data']['total_amount'] > 0, 'Order should have positive total amount');
        
        echo "✓ Order creation with valid data test passed\n";
    }
    
    /**
     * Test order creation without authentication
     */
    public function testCreateOrderUnauthorized() {
        // Clear authentication
        $this->clearAuthentication();
        
        // Prepare order data
        $orderData = [
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $this->testProduct['id'],
                    'quantity' => 1
                ]
            ]
        ];
        
        // Mock request input
        $this->mockRequestInput($orderData);
        
        // Capture output
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === false, 'Order creation should fail without authentication');
        assert(strpos($response['message'], 'Authentication') !== false, 'Error should mention authentication');
        
        echo "✓ Order creation without authentication test passed\n";
    }
    
    /**
     * Test order creation with invalid data
     */
    public function testCreateOrderInvalidData() {
        // Mock authentication
        $this->mockAuthentication($this->testUser);
        
        // Prepare invalid order data (missing payment method)
        $orderData = [
            'items' => [
                [
                    'product_id' => $this->testProduct['id'],
                    'quantity' => 1
                ]
            ]
        ];
        
        // Mock request input
        $this->mockRequestInput($orderData);
        
        // Capture output
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === false, 'Order creation should fail with invalid data');
        assert(strpos($response['message'], 'Payment method') !== false, 'Error should mention payment method');
        
        echo "✓ Order creation with invalid data test passed\n";
    }
    
    /**
     * Test order creation with insufficient stock
     */
    public function testCreateOrderInsufficientStock() {
        // Mock authentication
        $this->mockAuthentication($this->testUser);
        
        // Prepare order data with quantity exceeding stock
        $orderData = [
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $this->testProduct['id'],
                    'quantity' => 100 // Exceeds available stock of 50
                ]
            ]
        ];
        
        // Mock request input
        $this->mockRequestInput($orderData);
        
        // Capture output
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === false, 'Order creation should fail with insufficient stock');
        assert(strpos($response['message'], 'Insufficient stock') !== false, 'Error should mention insufficient stock');
        
        echo "✓ Order creation with insufficient stock test passed\n";
    }
    
    /**
     * Test getting user orders
     */
    public function testGetUserOrders() {
        // Create a test order first
        $this->createTestOrder();
        
        // Mock authentication
        $this->mockAuthentication($this->testUser);
        
        // Mock request with query parameters
        $this->mockRequest([
            'query' => [
                'page' => 1,
                'per_page' => 10
            ]
        ]);
        
        // Capture output
        ob_start();
        $this->controller->getUserOrders();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === true, 'Getting user orders should succeed');
        assert(isset($response['data']['orders']), 'Response should contain orders array');
        assert(isset($response['data']['pagination']), 'Response should contain pagination info');
        
        echo "✓ Get user orders test passed\n";
    }
    
    /**
     * Test getting order by ID
     */
    public function testGetOrderById() {
        // Create a test order first
        $testOrder = $this->createTestOrder();
        
        // Mock authentication
        $this->mockAuthentication($this->testUser);
        
        // Capture output
        ob_start();
        $this->controller->getById($testOrder['id']);
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === true, 'Getting order by ID should succeed');
        assert($response['data']['id'] === $testOrder['id'], 'Response should contain correct order ID');
        assert(isset($response['data']['items']), 'Response should contain order items');
        
        echo "✓ Get order by ID test passed\n";
    }
    
    /**
     * Test getting order by ID with invalid ID
     */
    public function testGetOrderByIdNotFound() {
        // Mock authentication
        $this->mockAuthentication($this->testUser);
        
        // Capture output
        ob_start();
        $this->controller->getById(999999);
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === false, 'Getting non-existent order should fail');
        assert(strpos($response['message'], 'not found') !== false, 'Error should mention not found');
        
        echo "✓ Get order by ID not found test passed\n";
    }
    
    /**
     * Test order cancellation
     */
    public function testCancelOrder() {
        // Create a test order first
        $testOrder = $this->createTestOrder();
        
        // Mock authentication
        $this->mockAuthentication($this->testUser);
        
        // Mock request input
        $this->mockRequestInput(['reason' => 'Changed mind']);
        
        // Capture output
        ob_start();
        $this->controller->cancel($testOrder['id']);
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === true, 'Order cancellation should succeed');
        assert(strpos($response['message'], 'cancelled') !== false, 'Message should mention cancellation');
        
        echo "✓ Order cancellation test passed\n";
    }
    
    /**
     * Test admin getting all orders
     */
    public function testAdminGetAllOrders() {
        // Create a test order first
        $this->createTestOrder();
        
        // Mock admin authentication
        $this->mockAuthentication($this->testAdmin);
        
        // Mock request with query parameters
        $this->mockRequest([
            'query' => [
                'page' => 1,
                'per_page' => 10
            ]
        ]);
        
        // Capture output
        ob_start();
        $this->controller->getAllOrders();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === true, 'Admin getting all orders should succeed');
        assert(isset($response['data']['orders']), 'Response should contain orders array');
        assert(isset($response['data']['pagination']), 'Response should contain pagination info');
        
        echo "✓ Admin get all orders test passed\n";
    }
    
    /**
     * Test admin updating order status
     */
    public function testAdminUpdateOrderStatus() {
        // Create a test order first
        $testOrder = $this->createTestOrder();
        
        // Mock admin authentication
        $this->mockAuthentication($this->testAdmin);
        
        // Mock request input
        $this->mockRequestInput([
            'status' => 'confirmed',
            'notes' => 'Order confirmed by admin'
        ]);
        
        // Capture output
        ob_start();
        $this->controller->updateStatus($testOrder['id']);
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === true, 'Admin updating order status should succeed');
        assert(strpos($response['message'], 'updated') !== false, 'Message should mention update');
        
        echo "✓ Admin update order status test passed\n";
    }
    
    /**
     * Test admin getting order statistics
     */
    public function testAdminGetOrderStats() {
        // Mock admin authentication
        $this->mockAuthentication($this->testAdmin);
        
        // Capture output
        ob_start();
        $this->controller->getOrderStats();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === true, 'Admin getting order stats should succeed');
        assert(isset($response['data']['total_orders']), 'Response should contain total orders');
        assert(isset($response['data']['by_status']), 'Response should contain status breakdown');
        
        echo "✓ Admin get order statistics test passed\n";
    }
    
    /**
     * Test non-admin accessing admin endpoints
     */
    public function testNonAdminAccessDenied() {
        // Mock regular user authentication
        $this->mockAuthentication($this->testUser);
        
        // Try to access admin endpoint
        ob_start();
        $this->controller->getAllOrders();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        assert($response !== null, 'Response should be valid JSON');
        assert($response['success'] === false, 'Non-admin access should be denied');
        assert(strpos($response['message'], 'Admin') !== false, 'Error should mention admin access');
        
        echo "✓ Non-admin access denied test passed\n";
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Create a test order
     */
    private function createTestOrder() {
        $orderData = [
            'user_id' => $this->testUser['id'],
            'payment_method' => 'cod',
            'currency' => 'INR',
            'shipping_address_id' => $this->testAddress['id'],
            'items' => [
                [
                    'product_id' => $this->testProduct['id'],
                    'quantity' => 1,
                    'unit_price' => $this->testProduct['price'],
                    'product_name' => $this->testProduct['name'],
                    'product_sku' => $this->testProduct['sku']
                ]
            ]
        ];
        
        return $this->orderModel->createOrder($orderData);
    }
    
    /**
     * Mock authentication
     */
    private function mockAuthentication($user) {
        // Store original authenticated user
        $_SESSION['test_authenticated_user'] = $user;
        
        // Mock AuthMiddleware::authenticate method
        if (!class_exists('MockAuthMiddleware')) {
            eval('
                class MockAuthMiddleware extends AuthMiddleware {
                    public static function authenticate() {
                        return $_SESSION["test_authenticated_user"] ?? null;
                    }
                    
                    public static function hasRole($role) {
                        $user = self::authenticate();
                        if (!$user) return false;
                        
                        $userRole = $user["role"] ?? "customer";
                        return $userRole === $role || $userRole === "admin";
                    }
                }
            ');
        }
    }
    
    /**
     * Clear authentication
     */
    private function clearAuthentication() {
        unset($_SESSION['test_authenticated_user']);
    }
    
    /**
     * Mock request input
     */
    private function mockRequestInput($data) {
        // Store original input
        if (!isset($GLOBALS['mock_input'])) {
            $GLOBALS['original_file_get_contents'] = 'file_get_contents';
        }
        
        $GLOBALS['mock_input'] = json_encode($data);
        
        // Override file_get_contents for php://input
        if (!function_exists('mock_file_get_contents')) {
            eval('
                function mock_file_get_contents($filename) {
                    if ($filename === "php://input") {
                        return $GLOBALS["mock_input"] ?? "";
                    }
                    return call_user_func($GLOBALS["original_file_get_contents"], $filename);
                }
            ');
        }
    }
    
    /**
     * Mock request data
     */
    private function mockRequest($request) {
        $this->controller->setRequest($request);
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running OrderController Tests...\n\n";
        
        try {
            $this->setUp();
            
            // Test order creation
            $this->testCreateOrderSuccess();
            $this->testCreateOrderUnauthorized();
            $this->testCreateOrderInvalidData();
            $this->testCreateOrderInsufficientStock();
            
            // Test order retrieval
            $this->testGetUserOrders();
            $this->testGetOrderById();
            $this->testGetOrderByIdNotFound();
            
            // Test order operations
            $this->testCancelOrder();
            
            // Test admin operations
            $this->testAdminGetAllOrders();
            $this->testAdminUpdateOrderStatus();
            $this->testAdminGetOrderStats();
            $this->testNonAdminAccessDenied();
            
            echo "\n✅ All OrderController tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new OrderControllerTest();
    $test->runAllTests();
}