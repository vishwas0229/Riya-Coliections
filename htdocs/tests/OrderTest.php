<?php
/**
 * Order Model Unit Tests
 * 
 * Comprehensive unit tests for the Order model functionality including
 * order creation, retrieval, status updates, and transaction handling.
 * 
 * Requirements: 6.1, 6.2
 */

// Include environment first to avoid conflicts
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Database.php';

class OrderTest {
    private $order;
    private $testOrderId;
    private $testUserId = 1;
    
    public function __construct() {
        $this->order = new Order();
        $this->setupTestData();
    }
    
    /**
     * Run all tests
     */
    public function runTests() {
        echo "Running Order Model Unit Tests...\n\n";
        
        $tests = [
            'testOrderCreation',
            'testOrderRetrieval',
            'testOrderStatusUpdate',
            'testOrderCancellation',
            'testOrderValidation',
            'testOrderNumberGeneration',
            'testOrderTotalsCalculation',
            'testOrdersByUser',
            'testOrderStatistics',
            'testTransactionRollback'
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                echo "Running {$test}... ";
                $this->$test();
                echo "PASSED\n";
                $passed++;
            } catch (Exception $e) {
                echo "FAILED: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\n=== Test Results ===\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "Total: " . ($passed + $failed) . "\n";
        
        $this->cleanup();
        
        return $failed === 0;
    }
    
    /**
     * Test order creation with transaction support
     */
    public function testOrderCreation() {
        $orderData = [
            'user_id' => $this->testUserId,
            'payment_method' => Order::PAYMENT_COD,
            'currency' => 'INR',
            'notes' => 'Test order creation',
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'product_name' => 'Test Product 1',
                    'product_sku' => 'TEST001'
                ],
                [
                    'product_id' => 2,
                    'quantity' => 1,
                    'unit_price' => 200.00,
                    'product_name' => 'Test Product 2',
                    'product_sku' => 'TEST002'
                ]
            ]
        ];
        
        $createdOrder = $this->order->createOrder($orderData);
        
        // Assertions
        if (!$createdOrder || !isset($createdOrder['id'])) {
            throw new Exception('Order creation failed');
        }
        
        if ($createdOrder['user_id'] !== $this->testUserId) {
            throw new Exception('User ID mismatch');
        }
        
        if ($createdOrder['status'] !== Order::STATUS_PENDING) {
            throw new Exception('Initial status should be pending');
        }
        
        if ($createdOrder['payment_method'] !== Order::PAYMENT_COD) {
            throw new Exception('Payment method mismatch');
        }
        
        if (!preg_match('/^RC\d{8}\d{4}$/', $createdOrder['order_number'])) {
            throw new Exception('Invalid order number format');
        }
        
        // Check calculated totals
        $expectedSubtotal = 400.00; // (2 * 100) + (1 * 200)
        if (abs($createdOrder['subtotal'] - $expectedSubtotal) > 0.01) {
            throw new Exception('Subtotal calculation error');
        }
        
        $this->testOrderId = $createdOrder['id'];
    }
    
    /**
     * Test order retrieval by ID and number
     */
    public function testOrderRetrieval() {
        if (!$this->testOrderId) {
            throw new Exception('No test order available');
        }
        
        // Test retrieval by ID
        $order = $this->order->getOrderById($this->testOrderId);
        
        if (!$order) {
            throw new Exception('Order not found by ID');
        }
        
        if ($order['id'] !== $this->testOrderId) {
            throw new Exception('Retrieved order ID mismatch');
        }
        
        // Test retrieval by order number
        $orderByNumber = $this->order->getOrderByNumber($order['order_number']);
        
        if (!$orderByNumber) {
            throw new Exception('Order not found by number');
        }
        
        if ($orderByNumber['id'] !== $this->testOrderId) {
            throw new Exception('Retrieved order by number ID mismatch');
        }
        
        // Check if items are included
        if (!isset($order['items']) || empty($order['items'])) {
            throw new Exception('Order items not retrieved');
        }
        
        if (count($order['items']) !== 2) {
            throw new Exception('Incorrect number of order items');
        }
    }
    
    /**
     * Test order status updates
     */
    public function testOrderStatusUpdate() {
        if (!$this->testOrderId) {
            throw new Exception('No test order available');
        }
        
        // Test valid status transition
        $result = $this->order->updateOrderStatus(
            $this->testOrderId, 
            Order::STATUS_CONFIRMED, 
            'Order confirmed by admin'
        );
        
        if (!$result) {
            throw new Exception('Status update failed');
        }
        
        // Verify status was updated
        $updatedOrder = $this->order->getOrderById($this->testOrderId);
        if ($updatedOrder['status'] !== Order::STATUS_CONFIRMED) {
            throw new Exception('Status not updated correctly');
        }
        
        // Test invalid status transition
        try {
            $this->order->updateOrderStatus($this->testOrderId, Order::STATUS_DELIVERED);
            throw new Exception('Invalid status transition should have failed');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Invalid status transition') === false) {
                throw new Exception('Wrong error for invalid status transition');
            }
        }
        
        // Test invalid status
        try {
            $this->order->updateOrderStatus($this->testOrderId, 'invalid_status');
            throw new Exception('Invalid status should have failed');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Invalid order status') === false) {
                throw new Exception('Wrong error for invalid status');
            }
        }
    }
    
    /**
     * Test order cancellation
     */
    public function testOrderCancellation() {
        // Create a new order for cancellation test
        $orderData = [
            'user_id' => $this->testUserId,
            'payment_method' => Order::PAYMENT_ONLINE,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                    'unit_price' => 50.00,
                    'product_name' => 'Cancellation Test Product'
                ]
            ]
        ];
        
        $cancelOrder = $this->order->createOrder($orderData);
        
        // Test cancellation
        $result = $this->order->cancelOrder($cancelOrder['id'], 'Customer requested cancellation');
        
        if (!$result) {
            throw new Exception('Order cancellation failed');
        }
        
        // Verify order was cancelled
        $cancelledOrder = $this->order->getOrderById($cancelOrder['id']);
        if ($cancelledOrder['status'] !== Order::STATUS_CANCELLED) {
            throw new Exception('Order status not updated to cancelled');
        }
        
        // Test cancellation of non-cancellable order
        $this->order->updateOrderStatus($this->testOrderId, Order::STATUS_PROCESSING);
        
        try {
            $this->order->cancelOrder($this->testOrderId, 'Should fail');
            throw new Exception('Cancellation of processing order should have failed');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'cannot be cancelled') === false) {
                throw new Exception('Wrong error for invalid cancellation');
            }
        }
    }
    
    /**
     * Test order data validation
     */
    public function testOrderValidation() {
        // Test missing user ID
        try {
            $this->order->createOrder([
                'payment_method' => Order::PAYMENT_COD,
                'items' => []
            ]);
            throw new Exception('Missing user ID should have failed');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'User ID is required') === false) {
                throw new Exception('Wrong error for missing user ID');
            }
        }
        
        // Test missing payment method
        try {
            $this->order->createOrder([
                'user_id' => 1,
                'items' => []
            ]);
            throw new Exception('Missing payment method should have failed');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Payment method is required') === false) {
                throw new Exception('Wrong error for missing payment method');
            }
        }
        
        // Test empty items
        try {
            $this->order->createOrder([
                'user_id' => 1,
                'payment_method' => Order::PAYMENT_COD,
                'items' => []
            ]);
            throw new Exception('Empty items should have failed');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Order items are required') === false) {
                throw new Exception('Wrong error for empty items');
            }
        }
        
        // Test invalid item data
        try {
            $this->order->createOrder([
                'user_id' => 1,
                'payment_method' => Order::PAYMENT_COD,
                'items' => [
                    [
                        'product_id' => '',
                        'quantity' => 0,
                        'unit_price' => -10
                    ]
                ]
            ]);
            throw new Exception('Invalid item data should have failed');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Validation failed') === false) {
                throw new Exception('Wrong error for invalid item data');
            }
        }
    }
    
    /**
     * Test order number generation uniqueness
     */
    public function testOrderNumberGeneration() {
        $orderNumbers = [];
        
        // Generate multiple order numbers
        for ($i = 0; $i < 10; $i++) {
            $orderData = [
                'user_id' => $this->testUserId,
                'payment_method' => Order::PAYMENT_COD,
                'items' => [
                    [
                        'product_id' => 1,
                        'quantity' => 1,
                        'unit_price' => 10.00
                    ]
                ]
            ];
            
            $order = $this->order->createOrder($orderData);
            $orderNumbers[] = $order['order_number'];
        }
        
        // Check uniqueness
        $uniqueNumbers = array_unique($orderNumbers);
        if (count($uniqueNumbers) !== count($orderNumbers)) {
            throw new Exception('Order numbers are not unique');
        }
        
        // Check format
        foreach ($orderNumbers as $number) {
            if (!preg_match('/^RC\d{8}\d{4}$/', $number)) {
                throw new Exception('Invalid order number format: ' . $number);
            }
        }
    }
    
    /**
     * Test order totals calculation
     */
    public function testOrderTotalsCalculation() {
        $orderData = [
            'user_id' => $this->testUserId,
            'payment_method' => Order::PAYMENT_COD,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 3,
                    'unit_price' => 100.00
                ],
                [
                    'product_id' => 2,
                    'quantity' => 2,
                    'unit_price' => 150.00
                ]
            ]
        ];
        
        $order = $this->order->createOrder($orderData);
        
        // Expected calculations
        $expectedSubtotal = 600.00; // (3 * 100) + (2 * 150)
        $expectedTax = 108.00; // 18% of 600
        $expectedShipping = 0.00; // Free shipping for orders > 500
        $expectedTotal = 708.00; // 600 + 108 + 0
        
        if (abs($order['subtotal'] - $expectedSubtotal) > 0.01) {
            throw new Exception('Subtotal calculation error');
        }
        
        if (abs($order['tax_amount'] - $expectedTax) > 0.01) {
            throw new Exception('Tax calculation error');
        }
        
        if (abs($order['shipping_amount'] - $expectedShipping) > 0.01) {
            throw new Exception('Shipping calculation error');
        }
        
        if (abs($order['total_amount'] - $expectedTotal) > 0.01) {
            throw new Exception('Total calculation error');
        }
        
        // Test with shipping charges (order < 500)
        $smallOrderData = [
            'user_id' => $this->testUserId,
            'payment_method' => Order::PAYMENT_COD,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                    'unit_price' => 100.00
                ]
            ]
        ];
        
        $smallOrder = $this->order->createOrder($smallOrderData);
        
        if ($smallOrder['shipping_amount'] != 50.00) {
            throw new Exception('Shipping charge not applied for small orders');
        }
    }
    
    /**
     * Test getting orders by user
     */
    public function testOrdersByUser() {
        $result = $this->order->getOrdersByUser($this->testUserId, [], 1, 10);
        
        if (!isset($result['orders']) || !isset($result['pagination'])) {
            throw new Exception('Invalid orders by user response structure');
        }
        
        if (!is_array($result['orders'])) {
            throw new Exception('Orders should be an array');
        }
        
        // Test with status filter
        $filteredResult = $this->order->getOrdersByUser(
            $this->testUserId, 
            ['status' => Order::STATUS_CONFIRMED], 
            1, 
            10
        );
        
        if (!isset($filteredResult['orders'])) {
            throw new Exception('Filtered orders response invalid');
        }
        
        // All returned orders should have confirmed status
        foreach ($filteredResult['orders'] as $order) {
            if ($order['status'] !== Order::STATUS_CONFIRMED) {
                throw new Exception('Status filter not working correctly');
            }
        }
    }
    
    /**
     * Test order statistics
     */
    public function testOrderStatistics() {
        $stats = $this->order->getOrderStats();
        
        $requiredFields = ['total_orders', 'by_status', 'revenue', 'recent_orders', 'by_payment_method'];
        
        foreach ($requiredFields as $field) {
            if (!isset($stats[$field])) {
                throw new Exception("Missing statistics field: {$field}");
            }
        }
        
        if (!is_numeric($stats['total_orders']) || $stats['total_orders'] < 0) {
            throw new Exception('Invalid total orders count');
        }
        
        if (!is_array($stats['by_status'])) {
            throw new Exception('Status statistics should be an array');
        }
        
        if (!isset($stats['revenue']['total_revenue']) || !is_numeric($stats['revenue']['total_revenue'])) {
            throw new Exception('Invalid revenue statistics');
        }
    }
    
    /**
     * Test transaction rollback on failure
     */
    public function testTransactionRollback() {
        // Create order data with invalid product ID to trigger rollback
        $orderData = [
            'user_id' => $this->testUserId,
            'payment_method' => Order::PAYMENT_COD,
            'items' => [
                [
                    'product_id' => 99999, // Non-existent product
                    'quantity' => 1,
                    'unit_price' => 100.00
                ]
            ]
        ];
        
        $initialOrderCount = $this->order->count();
        
        try {
            $this->order->createOrder($orderData);
            throw new Exception('Order creation should have failed');
        } catch (Exception $e) {
            // Expected to fail
        }
        
        $finalOrderCount = $this->order->count();
        
        if ($finalOrderCount !== $initialOrderCount) {
            throw new Exception('Transaction rollback failed - order count changed');
        }
    }
    
    /**
     * Setup test data
     */
    private function setupTestData() {
        // Create test user if not exists
        $db = Database::getInstance();
        
        $existingUser = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$this->testUserId]);
        
        if (!$existingUser) {
            $db->executeQuery("
                INSERT INTO users (id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
                VALUES (?, 'test@example.com', 'test_hash', 'Test', 'User', 'customer', 1, NOW(), NOW())
            ", [$this->testUserId]);
        }
        
        // Create test products if not exist
        $testProducts = [
            [1, 'Test Product 1', 'TEST001', 100.00, 50],
            [2, 'Test Product 2', 'TEST002', 200.00, 30]
        ];
        
        foreach ($testProducts as $product) {
            $existing = $db->fetchOne("SELECT id FROM products WHERE id = ?", [$product[0]]);
            
            if (!$existing) {
                $db->executeQuery("
                    INSERT INTO products (id, name, sku, price, stock_quantity, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
                ", $product);
            }
        }
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup() {
        $db = Database::getInstance();
        
        // Clean up test orders and related data
        $db->executeQuery("DELETE FROM order_status_history WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)", [$this->testUserId]);
        $db->executeQuery("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)", [$this->testUserId]);
        $db->executeQuery("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)", [$this->testUserId]);
        $db->executeQuery("DELETE FROM orders WHERE user_id = ?", [$this->testUserId]);
        
        echo "\nTest cleanup completed.\n";
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $test = new OrderTest();
        $success = $test->runTests();
        exit($success ? 0 : 1);
    } catch (Exception $e) {
        echo "Test execution failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}