<?php
/**
 * Simple OrderController Test Script
 * 
 * Basic test script to verify OrderController functionality and structure.
 * Tests class instantiation, method existence, and basic functionality.
 */

// Load dependencies in correct order
require_once __DIR__ . '/utils/Logger.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/models/Order.php';
require_once __DIR__ . '/models/Address.php';
require_once __DIR__ . '/models/Product.php';

echo "=== OrderController Simple Test ===\n\n";

try {
    // Test 1: Class instantiation
    echo "1. Testing OrderController instantiation...\n";
    $controller = new OrderController();
    assert($controller instanceof OrderController, 'OrderController should be instantiable');
    echo "✓ OrderController instantiated successfully\n\n";
    
    // Test 2: Check required methods exist
    echo "2. Testing required methods exist...\n";
    $requiredMethods = [
        'create',
        'getUserOrders', 
        'getById',
        'getByNumber',
        'cancel',
        'getAllOrders',
        'updateStatus',
        'getOrderStats',
        'setRequest',
        'setParams'
    ];
    
    foreach ($requiredMethods as $method) {
        assert(method_exists($controller, $method), "Method {$method} should exist");
        echo "✓ Method {$method} exists\n";
    }
    echo "\n";
    
    // Test 3: Check model dependencies
    echo "3. Testing model dependencies...\n";
    
    // Test Order model
    $orderModel = new Order();
    assert($orderModel instanceof Order, 'Order model should be instantiable');
    echo "✓ Order model instantiated successfully\n";
    
    // Test Address model
    $addressModel = new Address();
    assert($addressModel instanceof Address, 'Address model should be instantiable');
    echo "✓ Address model instantiated successfully\n";
    
    // Test Product model
    $productModel = new Product();
    assert($productModel instanceof Product, 'Product model should be instantiable');
    echo "✓ Product model instantiated successfully\n";
    echo "\n";
    
    // Test 4: Test request/params setters
    echo "4. Testing request and params setters...\n";
    
    $testRequest = [
        'method' => 'POST',
        'query' => ['page' => 1],
        'body' => ['test' => 'data']
    ];
    
    $testParams = ['id' => 123];
    
    $controller->setRequest($testRequest);
    $controller->setParams($testParams);
    echo "✓ Request and params set successfully\n\n";
    
    // Test 5: Check Order model methods
    echo "5. Testing Order model required methods...\n";
    $orderMethods = [
        'createOrder',
        'getOrderById',
        'getOrderByNumber',
        'getOrdersByUser',
        'getAllOrders',
        'updateOrderStatus',
        'cancelOrder',
        'getOrderStats'
    ];
    
    foreach ($orderMethods as $method) {
        assert(method_exists($orderModel, $method), "Order model method {$method} should exist");
        echo "✓ Order model method {$method} exists\n";
    }
    echo "\n";
    
    // Test 6: Check Address model methods
    echo "6. Testing Address model required methods...\n";
    $addressMethods = [
        'getAddressById',
        'createAddress',
        'getAddressesByUser'
    ];
    
    foreach ($addressMethods as $method) {
        assert(method_exists($addressModel, $method), "Address model method {$method} should exist");
        echo "✓ Address model method {$method} exists\n";
    }
    echo "\n";
    
    // Test 7: Check Product model methods
    echo "7. Testing Product model required methods...\n";
    $productMethods = [
        'getProductById'
    ];
    
    foreach ($productMethods as $method) {
        assert(method_exists($productModel, $method), "Product model method {$method} should exist");
        echo "✓ Product model method {$method} exists\n";
    }
    echo "\n";
    
    // Test 8: Test order status constants
    echo "8. Testing Order model constants...\n";
    $statusConstants = [
        'STATUS_PENDING',
        'STATUS_CONFIRMED', 
        'STATUS_PROCESSING',
        'STATUS_SHIPPED',
        'STATUS_DELIVERED',
        'STATUS_CANCELLED',
        'STATUS_REFUNDED'
    ];
    
    foreach ($statusConstants as $constant) {
        assert(defined("Order::{$constant}"), "Order constant {$constant} should be defined");
        echo "✓ Order constant {$constant} is defined\n";
    }
    echo "\n";
    
    // Test 9: Test payment method constants
    echo "9. Testing Order payment method constants...\n";
    $paymentConstants = [
        'PAYMENT_COD',
        'PAYMENT_ONLINE',
        'PAYMENT_RAZORPAY'
    ];
    
    foreach ($paymentConstants as $constant) {
        assert(defined("Order::{$constant}"), "Order constant {$constant} should be defined");
        echo "✓ Order constant {$constant} is defined\n";
    }
    echo "\n";
    
    // Test 10: Test middleware dependencies
    echo "10. Testing middleware dependencies...\n";
    
    assert(class_exists('AuthMiddleware'), 'AuthMiddleware class should exist');
    echo "✓ AuthMiddleware class exists\n";
    
    assert(class_exists('Response'), 'Response class should exist');
    echo "✓ Response class exists\n";
    
    assert(class_exists('Logger'), 'Logger class should exist');
    echo "✓ Logger class exists\n";
    echo "\n";
    
    echo "🎉 All OrderController simple tests passed!\n";
    echo "OrderController is properly structured and ready for use.\n\n";
    
    // Summary
    echo "=== Test Summary ===\n";
    echo "✓ OrderController class instantiation\n";
    echo "✓ All required methods present\n";
    echo "✓ Model dependencies working\n";
    echo "✓ Request/params handling\n";
    echo "✓ Order model methods available\n";
    echo "✓ Address model methods available\n";
    echo "✓ Product model methods available\n";
    echo "✓ Order constants defined\n";
    echo "✓ Payment constants defined\n";
    echo "✓ Middleware dependencies available\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Error $e) {
    echo "❌ Error occurred: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}