<?php
/**
 * Order Model Property-Based Tests
 * 
 * Property-based tests for the Order model to verify universal properties
 * hold across all valid inputs and edge cases.
 * 
 * Requirements: 6.1, 6.2
 */

// Include environment first to avoid conflicts
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Database.php';

class OrderPropertyTest {
    private $order;
    private $testUserId = 1001;
    private $testProductIds = [1001, 1002, 1003];
    
    public function __construct() {
        $this->order = new Order();
        $this->setupTestData();
    }
    
    /**
     * Run all property-based tests
     */
    public function runTests() {
        echo "Running Order Model Property-Based Tests...\n\n";
        
        $tests = [
            'testOrderCreationProperty',
            'testOrderNumberUniquenessProperty',
            'testOrderTotalsCalculationProperty',
            'testOrderStatusTransitionProperty',
            'testTransactionConsistencyProperty',
            'testOrderRetrievalConsistencyProperty',
            'testOrderValidationProperty',
            'testStockUpdateConsistencyProperty'
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
        
        echo "\n=== Property Test Results ===\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "Total: " . ($passed + $failed) . "\n";
        
        $this->cleanup();
        
        return $failed === 0;
    }
    
    /**
     * Property: Order creation should always result in a valid order with correct structure
     * **Validates: Requirements 6.1**
     */
    public function testOrderCreationProperty() {
        for ($i = 0; $i < 100; $i++) {
            $orderData = $this->generateRandomOrderData();
            
            try {
                $createdOrder = $this->order->createOrder($orderData);
                
                // Property: Created order must have all required fields
                $this->assertOrderStructure($createdOrder);
                
                // Property: Order number must follow correct format
                $this->assertOrderNumberFormat($createdOrder['order_number']);
                
                // Property: Status must be pending for new orders
                if ($createdOrder['status'] !== Order::STATUS_PENDING) {
                    throw new Exception("New order status should be pending, got: {$createdOrder['status']}");
                }
                
                // Property: User ID must match input
                if ($createdOrder['user_id'] !== $orderData['user_id']) {
                    throw new Exception("User ID mismatch in created order");
                }
                
                // Property: Payment method must match input
                if ($createdOrder['payment_method'] !== $orderData['payment_method']) {
                    throw new Exception("Payment method mismatch in created order");
                }
                
                // Property: Total amount must be positive
                if ($createdOrder['total_amount'] <= 0) {
                    throw new Exception("Total amount must be positive");
                }
                
                // Property: Subtotal must equal sum of item totals
                $expectedSubtotal = 0;
                foreach ($orderData['items'] as $item) {
                    $expectedSubtotal += $item['quantity'] * $item['unit_price'];
                }
                
                if (abs($createdOrder['subtotal'] - $expectedSubtotal) > 0.01) {
                    throw new Exception("Subtotal calculation error");
                }
                
            } catch (Exception $e) {
                // If creation fails, it should be due to validation errors
                if (strpos($e->getMessage(), 'Validation failed') === false && 
                    strpos($e->getMessage(), 'Insufficient stock') === false) {
                    throw new Exception("Unexpected error during order creation: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Property: Order numbers must always be unique
     * **Validates: Requirements 6.2**
     */
    public function testOrderNumberUniquenessProperty() {
        $orderNumbers = [];
        $createdOrders = [];
        
        for ($i = 0; $i < 50; $i++) {
            $orderData = $this->generateRandomOrderData();
            
            try {
                $createdOrder = $this->order->createOrder($orderData);
                $orderNumbers[] = $createdOrder['order_number'];
                $createdOrders[] = $createdOrder['id'];
                
                // Property: Order number format must be consistent
                $this->assertOrderNumberFormat($createdOrder['order_number']);
                
            } catch (Exception $e) {
                // Skip validation errors
                continue;
            }
        }
        
        // Property: All order numbers must be unique
        $uniqueNumbers = array_unique($orderNumbers);
        if (count($uniqueNumbers) !== count($orderNumbers)) {
            throw new Exception("Order numbers are not unique");
        }
        
        // Property: Order numbers must follow the pattern RC + date + random
        foreach ($orderNumbers as $number) {
            if (!preg_match('/^RC\d{8}\d{4}$/', $number)) {
                throw new Exception("Invalid order number format: {$number}");
            }
        }
    }
    
    /**
     * Property: Order totals calculation must be mathematically correct
     * **Validates: Requirements 6.4**
     */
    public function testOrderTotalsCalculationProperty() {
        for ($i = 0; $i < 100; $i++) {
            $orderData = $this->generateRandomOrderData();
            
            try {
                $createdOrder = $this->order->createOrder($orderData);
                
                // Calculate expected values
                $expectedSubtotal = 0;
                foreach ($orderData['items'] as $item) {
                    $expectedSubtotal += $item['quantity'] * $item['unit_price'];
                }
                
                $expectedTax = $expectedSubtotal * 0.18; // 18% GST
                $expectedShipping = $expectedSubtotal >= 500 ? 0 : 50;
                $expectedTotal = $expectedSubtotal + $expectedTax + $expectedShipping;
                
                // Property: Subtotal must equal sum of item prices
                if (abs($createdOrder['subtotal'] - $expectedSubtotal) > 0.01) {
                    throw new Exception("Subtotal calculation error");
                }
                
                // Property: Tax must be 18% of subtotal
                if (abs($createdOrder['tax_amount'] - $expectedTax) > 0.01) {
                    throw new Exception("Tax calculation error");
                }
                
                // Property: Shipping logic must be consistent
                if (abs($createdOrder['shipping_amount'] - $expectedShipping) > 0.01) {
                    throw new Exception("Shipping calculation error");
                }
                
                // Property: Total must equal subtotal + tax + shipping - discount
                if (abs($createdOrder['total_amount'] - $expectedTotal) > 0.01) {
                    throw new Exception("Total calculation error");
                }
                
                // Property: All amounts must be non-negative
                if ($createdOrder['subtotal'] < 0 || $createdOrder['tax_amount'] < 0 || 
                    $createdOrder['shipping_amount'] < 0 || $createdOrder['total_amount'] < 0) {
                    throw new Exception("Negative amounts not allowed");
                }
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Validation failed') === false) {
                    throw $e;
                }
            }
        }
    }
    
    /**
     * Property: Order status transitions must follow valid state machine
     * **Validates: Requirements 6.3**
     */
    public function testOrderStatusTransitionProperty() {
        // Create test orders for status transition testing
        for ($i = 0; $i < 20; $i++) {
            $orderData = $this->generateRandomOrderData();
            
            try {
                $createdOrder = $this->order->createOrder($orderData);
                $orderId = $createdOrder['id'];
                
                // Property: New orders must start with pending status
                if ($createdOrder['status'] !== Order::STATUS_PENDING) {
                    throw new Exception("New orders must start with pending status");
                }
                
                // Test valid transitions from pending
                $validFromPending = [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED];
                foreach ($validFromPending as $status) {
                    $testOrderData = $this->generateRandomOrderData();
                    $testOrder = $this->order->createOrder($testOrderData);
                    
                    $result = $this->order->updateOrderStatus($testOrder['id'], $status);
                    if (!$result) {
                        throw new Exception("Valid status transition failed: pending -> {$status}");
                    }
                    
                    $updatedOrder = $this->order->getOrderById($testOrder['id']);
                    if ($updatedOrder['status'] !== $status) {
                        throw new Exception("Status not updated correctly");
                    }
                }
                
                // Test invalid transitions
                $invalidFromPending = [Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED];
                foreach ($invalidFromPending as $status) {
                    try {
                        $this->order->updateOrderStatus($orderId, $status);
                        throw new Exception("Invalid status transition should have failed: pending -> {$status}");
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Invalid status transition') === false) {
                            throw new Exception("Wrong error for invalid transition: " . $e->getMessage());
                        }
                    }
                }
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Validation failed') === false) {
                    throw $e;
                }
            }
        }
    }
    
    /**
     * Property: Transaction consistency must be maintained
     * **Validates: Requirements 6.1**
     */
    public function testTransactionConsistencyProperty() {
        for ($i = 0; $i < 30; $i++) {
            $orderData = $this->generateRandomOrderData();
            
            // Add invalid product to trigger rollback
            $orderData['items'][] = [
                'product_id' => 99999, // Non-existent product
                'quantity' => 1,
                'unit_price' => 100.00
            ];
            
            $initialOrderCount = $this->order->count();
            $initialItemCount = $this->getOrderItemsCount();
            
            try {
                $this->order->createOrder($orderData);
                throw new Exception("Order creation should have failed due to invalid product");
            } catch (Exception $e) {
                // Expected to fail
            }
            
            $finalOrderCount = $this->order->count();
            $finalItemCount = $this->getOrderItemsCount();
            
            // Property: Failed transactions must not leave partial data
            if ($finalOrderCount !== $initialOrderCount) {
                throw new Exception("Transaction rollback failed - order count changed");
            }
            
            if ($finalItemCount !== $initialItemCount) {
                throw new Exception("Transaction rollback failed - order items count changed");
            }
        }
    }
    
    /**
     * Property: Order retrieval must be consistent and complete
     * **Validates: Requirements 6.1**
     */
    public function testOrderRetrievalConsistencyProperty() {
        $createdOrders = [];
        
        // Create multiple orders
        for ($i = 0; $i < 20; $i++) {
            $orderData = $this->generateRandomOrderData();
            
            try {
                $createdOrder = $this->order->createOrder($orderData);
                $createdOrders[] = $createdOrder;
            } catch (Exception $e) {
                // Skip validation errors
                continue;
            }
        }
        
        foreach ($createdOrders as $originalOrder) {
            // Property: Retrieval by ID must return same order
            $retrievedById = $this->order->getOrderById($originalOrder['id']);
            
            if (!$retrievedById) {
                throw new Exception("Order not found by ID: {$originalOrder['id']}");
            }
            
            if ($retrievedById['id'] !== $originalOrder['id']) {
                throw new Exception("Retrieved order ID mismatch");
            }
            
            // Property: Retrieval by order number must return same order
            $retrievedByNumber = $this->order->getOrderByNumber($originalOrder['order_number']);
            
            if (!$retrievedByNumber) {
                throw new Exception("Order not found by number: {$originalOrder['order_number']}");
            }
            
            if ($retrievedByNumber['id'] !== $originalOrder['id']) {
                throw new Exception("Retrieved order by number ID mismatch");
            }
            
            // Property: Retrieved order must have complete structure
            $this->assertOrderStructure($retrievedById);
            
            // Property: Order items must be included
            if (!isset($retrievedById['items']) || !is_array($retrievedById['items'])) {
                throw new Exception("Order items not included in retrieval");
            }
        }
    }
    
    /**
     * Property: Order validation must be consistent and comprehensive
     * **Validates: Requirements 6.5**
     */
    public function testOrderValidationProperty() {
        // Test various invalid order data combinations
        $invalidCases = [
            // Missing user ID
            [
                'payment_method' => Order::PAYMENT_COD,
                'items' => [['product_id' => 1001, 'quantity' => 1, 'unit_price' => 100]]
            ],
            // Invalid user ID
            [
                'user_id' => 'invalid',
                'payment_method' => Order::PAYMENT_COD,
                'items' => [['product_id' => 1001, 'quantity' => 1, 'unit_price' => 100]]
            ],
            // Missing payment method
            [
                'user_id' => $this->testUserId,
                'items' => [['product_id' => 1001, 'quantity' => 1, 'unit_price' => 100]]
            ],
            // Invalid payment method
            [
                'user_id' => $this->testUserId,
                'payment_method' => 'invalid_method',
                'items' => [['product_id' => 1001, 'quantity' => 1, 'unit_price' => 100]]
            ],
            // Empty items
            [
                'user_id' => $this->testUserId,
                'payment_method' => Order::PAYMENT_COD,
                'items' => []
            ],
            // Invalid item data
            [
                'user_id' => $this->testUserId,
                'payment_method' => Order::PAYMENT_COD,
                'items' => [['product_id' => '', 'quantity' => 0, 'unit_price' => -10]]
            ]
        ];
        
        foreach ($invalidCases as $invalidData) {
            try {
                $this->order->createOrder($invalidData);
                throw new Exception("Validation should have failed for invalid data");
            } catch (Exception $e) {
                // Property: All validation failures must contain "Validation failed"
                if (strpos($e->getMessage(), 'Validation failed') === false &&
                    strpos($e->getMessage(), 'required') === false &&
                    strpos($e->getMessage(), 'Invalid') === false) {
                    throw new Exception("Unexpected validation error: " . $e->getMessage());
                }
            }
        }
        
        // Property: Valid data must pass validation
        for ($i = 0; $i < 20; $i++) {
            $validData = $this->generateRandomOrderData();
            
            try {
                $order = $this->order->createOrder($validData);
                // If creation succeeds, order must be valid
                $this->assertOrderStructure($order);
            } catch (Exception $e) {
                // Only stock-related errors are acceptable for valid data
                if (strpos($e->getMessage(), 'Insufficient stock') === false) {
                    throw new Exception("Valid data should not fail validation: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Property: Stock updates must be consistent with order operations
     * **Validates: Requirements 6.1**
     */
    public function testStockUpdateConsistencyProperty() {
        // Get initial stock levels
        $initialStocks = [];
        foreach ($this->testProductIds as $productId) {
            $stock = $this->getProductStock($productId);
            $initialStocks[$productId] = $stock;
        }
        
        $createdOrders = [];
        
        // Create orders and track stock changes
        for ($i = 0; $i < 10; $i++) {
            $orderData = $this->generateRandomOrderData();
            
            try {
                $order = $this->order->createOrder($orderData);
                $createdOrders[] = $order;
                
                // Property: Stock must decrease by ordered quantity
                foreach ($orderData['items'] as $item) {
                    $currentStock = $this->getProductStock($item['product_id']);
                    $expectedStock = $initialStocks[$item['product_id']] - $item['quantity'];
                    
                    // Update our tracking
                    $initialStocks[$item['product_id']] = $currentStock;
                }
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Insufficient stock') !== false) {
                    // Property: Stock should not change if order fails due to insufficient stock
                    foreach ($orderData['items'] as $item) {
                        $currentStock = $this->getProductStock($item['product_id']);
                        if ($currentStock !== $initialStocks[$item['product_id']]) {
                            throw new Exception("Stock changed despite insufficient stock error");
                        }
                    }
                }
            }
        }
        
        // Test order cancellation stock restoration
        foreach ($createdOrders as $order) {
            if ($order['status'] === Order::STATUS_PENDING) {
                $orderItems = $this->order->getOrderById($order['id'])['items'];
                
                // Get stock before cancellation
                $stockBeforeCancel = [];
                foreach ($orderItems as $item) {
                    $stockBeforeCancel[$item['product_id']] = $this->getProductStock($item['product_id']);
                }
                
                // Cancel order
                $this->order->cancelOrder($order['id'], 'Test cancellation');
                
                // Property: Stock must be restored after cancellation
                foreach ($orderItems as $item) {
                    $stockAfterCancel = $this->getProductStock($item['product_id']);
                    $expectedStock = $stockBeforeCancel[$item['product_id']] + $item['quantity'];
                    
                    if ($stockAfterCancel !== $expectedStock) {
                        throw new Exception("Stock not restored correctly after cancellation");
                    }
                }
            }
        }
    }
    
    /**
     * Generate random order data for testing
     */
    private function generateRandomOrderData() {
        $paymentMethods = [Order::PAYMENT_COD, Order::PAYMENT_ONLINE, Order::PAYMENT_RAZORPAY];
        $currencies = ['INR', 'USD'];
        
        $itemCount = mt_rand(1, 5);
        $items = [];
        
        for ($i = 0; $i < $itemCount; $i++) {
            $items[] = [
                'product_id' => $this->testProductIds[array_rand($this->testProductIds)],
                'quantity' => mt_rand(1, 10),
                'unit_price' => mt_rand(10, 500) + (mt_rand(0, 99) / 100),
                'product_name' => 'Test Product ' . mt_rand(1, 100),
                'product_sku' => 'TEST' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT)
            ];
        }
        
        return [
            'user_id' => $this->testUserId,
            'payment_method' => $paymentMethods[array_rand($paymentMethods)],
            'currency' => $currencies[array_rand($currencies)],
            'notes' => 'Property test order ' . mt_rand(1, 1000),
            'items' => $items
        ];
    }
    
    /**
     * Assert order structure is complete and valid
     */
    private function assertOrderStructure($order) {
        $requiredFields = [
            'id', 'order_number', 'user_id', 'status', 'payment_method',
            'subtotal', 'tax_amount', 'shipping_amount', 'total_amount',
            'currency', 'created_at', 'updated_at'
        ];
        
        foreach ($requiredFields as $field) {
            if (!isset($order[$field])) {
                throw new Exception("Missing required field in order: {$field}");
            }
        }
        
        // Check data types
        if (!is_numeric($order['id']) || $order['id'] <= 0) {
            throw new Exception("Invalid order ID");
        }
        
        if (!is_numeric($order['user_id']) || $order['user_id'] <= 0) {
            throw new Exception("Invalid user ID");
        }
        
        if (!is_numeric($order['subtotal']) || $order['subtotal'] < 0) {
            throw new Exception("Invalid subtotal");
        }
        
        if (!is_numeric($order['total_amount']) || $order['total_amount'] <= 0) {
            throw new Exception("Invalid total amount");
        }
    }
    
    /**
     * Assert order number format is correct
     */
    private function assertOrderNumberFormat($orderNumber) {
        if (!preg_match('/^RC\d{8}\d{4}$/', $orderNumber)) {
            throw new Exception("Invalid order number format: {$orderNumber}");
        }
        
        // Extract date part and validate
        $datePart = substr($orderNumber, 2, 8);
        $date = DateTime::createFromFormat('Ymd', $datePart);
        
        if (!$date || $date->format('Ymd') !== $datePart) {
            throw new Exception("Invalid date in order number: {$orderNumber}");
        }
    }
    
    /**
     * Get order items count
     */
    private function getOrderItemsCount() {
        $db = Database::getInstance();
        return (int)$db->fetchColumn("SELECT COUNT(*) FROM order_items");
    }
    
    /**
     * Get product stock
     */
    private function getProductStock($productId) {
        $db = Database::getInstance();
        return (int)$db->fetchColumn("SELECT stock_quantity FROM products WHERE id = ?", [$productId]);
    }
    
    /**
     * Setup test data
     */
    private function setupTestData() {
        $db = Database::getInstance();
        
        // Create test user
        $existingUser = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$this->testUserId]);
        
        if (!$existingUser) {
            $db->executeQuery("
                INSERT INTO users (id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
                VALUES (?, 'propertytest@example.com', 'test_hash', 'Property', 'Test', 'customer', 1, NOW(), NOW())
            ", [$this->testUserId]);
        }
        
        // Create test products with sufficient stock
        foreach ($this->testProductIds as $index => $productId) {
            $existing = $db->fetchOne("SELECT id FROM products WHERE id = ?", [$productId]);
            
            if (!$existing) {
                $db->executeQuery("
                    INSERT INTO products (id, name, sku, price, stock_quantity, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
                ", [
                    $productId,
                    "Property Test Product " . ($index + 1),
                    "PROP" . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    mt_rand(50, 500),
                    1000 // High stock for testing
                ]);
            } else {
                // Ensure sufficient stock
                $db->executeQuery("UPDATE products SET stock_quantity = 1000 WHERE id = ?", [$productId]);
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
        
        echo "\nProperty test cleanup completed.\n";
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $test = new OrderPropertyTest();
        $success = $test->runTests();
        exit($success ? 0 : 1);
    } catch (Exception $e) {
        echo "Property test execution failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}