<?php
/**
 * Simple Order Model Test Script
 * 
 * Basic functionality test for the Order model to verify
 * core operations work correctly.
 */

// Mock environment functions if not already defined
if (!function_exists('env')) {
    function env($key, $default = null) {
        $values = [
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'riya_collections',
            'DB_USER' => 'root',
            'DB_PASSWORD' => '',
            'LOG_LEVEL' => 'info',
            'APP_ENV' => 'development'
        ];
        return $values[$key] ?? $default;
    }
}

if (!function_exists('isDevelopment')) {
    function isDevelopment() {
        return env('APP_ENV', 'production') === 'development';
    }
}

require_once __DIR__ . '/models/Order.php';
require_once __DIR__ . '/models/Database.php';
require_once __DIR__ . '/utils/Logger.php';

echo "=== Order Model Simple Test ===\n\n";

try {
    // Initialize Order model
    $order = new Order();
    echo "✓ Order model initialized successfully\n";
    
    // Test database connection
    $db = Database::getInstance();
    $db->testConnection();
    echo "✓ Database connection established\n";
    
    // Setup test data
    setupTestData();
    echo "✓ Test data setup completed\n";
    
    // Test 1: Create a simple order
    echo "\n--- Test 1: Order Creation ---\n";
    
    $orderData = [
        'user_id' => 2001,
        'payment_method' => Order::PAYMENT_COD,
        'currency' => 'INR',
        'notes' => 'Simple test order',
        'items' => [
            [
                'product_id' => 2001,
                'quantity' => 2,
                'unit_price' => 150.00,
                'product_name' => 'Simple Test Product 1',
                'product_sku' => 'STP001'
            ],
            [
                'product_id' => 2002,
                'quantity' => 1,
                'unit_price' => 300.00,
                'product_name' => 'Simple Test Product 2',
                'product_sku' => 'STP002'
            ]
        ]
    ];
    
    $createdOrder = $order->createOrder($orderData);
    
    echo "Order created with ID: {$createdOrder['id']}\n";
    echo "Order number: {$createdOrder['order_number']}\n";
    echo "Status: {$createdOrder['status']}\n";
    echo "Total amount: {$createdOrder['total_amount']}\n";
    echo "✓ Order creation successful\n";
    
    // Test 2: Retrieve order
    echo "\n--- Test 2: Order Retrieval ---\n";
    
    $retrievedOrder = $order->getOrderById($createdOrder['id']);
    
    if ($retrievedOrder) {
        echo "Retrieved order ID: {$retrievedOrder['id']}\n";
        echo "Items count: " . count($retrievedOrder['items']) . "\n";
        echo "✓ Order retrieval successful\n";
    } else {
        throw new Exception("Failed to retrieve order");
    }
    
    // Test 3: Update order status
    echo "\n--- Test 3: Status Update ---\n";
    
    $statusUpdated = $order->updateOrderStatus(
        $createdOrder['id'], 
        Order::STATUS_CONFIRMED, 
        'Order confirmed for testing'
    );
    
    if ($statusUpdated) {
        $updatedOrder = $order->getOrderById($createdOrder['id']);
        echo "Status updated to: {$updatedOrder['status']}\n";
        echo "✓ Status update successful\n";
    } else {
        throw new Exception("Failed to update order status");
    }
    
    // Test 4: Get orders by user
    echo "\n--- Test 4: Orders by User ---\n";
    
    $userOrders = $order->getOrdersByUser(2001, [], 1, 10);
    
    echo "Found {$userOrders['pagination']['total']} orders for user\n";
    echo "Orders on current page: " . count($userOrders['orders']) . "\n";
    echo "✓ User orders retrieval successful\n";
    
    // Test 5: Order statistics
    echo "\n--- Test 5: Order Statistics ---\n";
    
    $stats = $order->getOrderStats();
    
    echo "Total orders: {$stats['total_orders']}\n";
    echo "Revenue statistics available: " . (isset($stats['revenue']) ? 'Yes' : 'No') . "\n";
    echo "Status breakdown available: " . (isset($stats['by_status']) ? 'Yes' : 'No') . "\n";
    echo "✓ Order statistics successful\n";
    
    // Test 6: Order number format validation
    echo "\n--- Test 6: Order Number Validation ---\n";
    
    $orderNumbers = [];
    for ($i = 0; $i < 5; $i++) {
        $testOrderData = [
            'user_id' => 2001,
            'payment_method' => Order::PAYMENT_ONLINE,
            'items' => [
                [
                    'product_id' => 2001,
                    'quantity' => 1,
                    'unit_price' => 100.00
                ]
            ]
        ];
        
        $testOrder = $order->createOrder($testOrderData);
        $orderNumbers[] = $testOrder['order_number'];
    }
    
    echo "Generated order numbers:\n";
    foreach ($orderNumbers as $number) {
        echo "  - {$number}\n";
        
        // Validate format
        if (!preg_match('/^RC\d{8}\d{4}$/', $number)) {
            throw new Exception("Invalid order number format: {$number}");
        }
    }
    
    // Check uniqueness
    $uniqueNumbers = array_unique($orderNumbers);
    if (count($uniqueNumbers) === count($orderNumbers)) {
        echo "✓ All order numbers are unique\n";
    } else {
        throw new Exception("Duplicate order numbers found");
    }
    
    echo "✓ Order number validation successful\n";
    
    // Test 7: Order validation
    echo "\n--- Test 7: Order Validation ---\n";
    
    // Test invalid order data
    $invalidOrderData = [
        'user_id' => '', // Invalid user ID
        'payment_method' => Order::PAYMENT_COD,
        'items' => []  // Empty items
    ];
    
    try {
        $order->createOrder($invalidOrderData);
        throw new Exception("Validation should have failed");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Validation failed') !== false) {
            echo "✓ Order validation working correctly\n";
        } else {
            throw new Exception("Unexpected validation error: " . $e->getMessage());
        }
    }
    
    // Cleanup
    cleanup();
    echo "\n✓ Test cleanup completed\n";
    
    echo "\n=== All Tests Passed Successfully! ===\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Attempt cleanup
    try {
        cleanup();
    } catch (Exception $cleanupError) {
        echo "Cleanup error: " . $cleanupError->getMessage() . "\n";
    }
    
    exit(1);
}

/**
 * Setup test data
 */
function setupTestData() {
    $db = Database::getInstance();
    
    // Create test user
    $existingUser = $db->fetchOne("SELECT id FROM users WHERE id = ?", [2001]);
    
    if (!$existingUser) {
        $db->executeQuery("
            INSERT INTO users (id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
            VALUES (?, 'simpletest@example.com', 'test_hash', 'Simple', 'Test', 'customer', 1, NOW(), NOW())
        ", [2001]);
    }
    
    // Create test products
    $testProducts = [
        [2001, 'Simple Test Product 1', 'STP001', 150.00, 100],
        [2002, 'Simple Test Product 2', 'STP002', 300.00, 50]
    ];
    
    foreach ($testProducts as $product) {
        $existing = $db->fetchOne("SELECT id FROM products WHERE id = ?", [$product[0]]);
        
        if (!$existing) {
            $db->executeQuery("
                INSERT INTO products (id, name, sku, price, stock_quantity, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ", $product);
        } else {
            // Update stock to ensure sufficient quantity
            $db->executeQuery("UPDATE products SET stock_quantity = ? WHERE id = ?", [$product[4], $product[0]]);
        }
    }
}

/**
 * Cleanup test data
 */
function cleanup() {
    $db = Database::getInstance();
    
    // Clean up test orders and related data
    $db->executeQuery("DELETE FROM order_status_history WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)", [2001]);
    $db->executeQuery("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)", [2001]);
    $db->executeQuery("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)", [2001]);
    $db->executeQuery("DELETE FROM orders WHERE user_id = ?", [2001]);
}