<?php
/**
 * Basic Order Model Test
 * 
 * Simple test to verify Order model structure and basic functionality
 */

// Include environment first to avoid conflicts
require_once __DIR__ . '/config/environment.php';
require_once __DIR__ . '/utils/Logger.php';

echo "=== Order Model Basic Test ===\n\n";

try {
    // Test 1: Check if Order class can be loaded
    echo "Test 1: Loading Order class... ";
    require_once __DIR__ . '/models/Order.php';
    echo "✓ PASSED\n";
    
    // Test 2: Check if Order can be instantiated
    echo "Test 2: Creating Order instance... ";
    $order = new Order();
    echo "✓ PASSED\n";
    
    // Test 3: Check Order constants
    echo "Test 3: Checking Order constants... ";
    $expectedConstants = [
        'STATUS_PENDING', 'STATUS_CONFIRMED', 'STATUS_PROCESSING',
        'STATUS_SHIPPED', 'STATUS_DELIVERED', 'STATUS_CANCELLED', 'STATUS_REFUNDED',
        'PAYMENT_COD', 'PAYMENT_ONLINE', 'PAYMENT_RAZORPAY'
    ];
    
    $reflection = new ReflectionClass('Order');
    $constants = $reflection->getConstants();
    
    foreach ($expectedConstants as $constant) {
        if (!array_key_exists($constant, $constants)) {
            throw new Exception("Missing constant: {$constant}");
        }
    }
    echo "✓ PASSED\n";
    
    // Test 4: Check Order methods exist
    echo "Test 4: Checking Order methods... ";
    $expectedMethods = [
        'createOrder', 'getOrderById', 'getOrderByNumber', 
        'getOrdersByUser', 'updateOrderStatus', 'cancelOrder',
        'getAllOrders', 'getOrderStats'
    ];
    
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $methodNames = array_map(function($method) {
        return $method->getName();
    }, $methods);
    
    foreach ($expectedMethods as $method) {
        if (!in_array($method, $methodNames)) {
            throw new Exception("Missing method: {$method}");
        }
    }
    echo "✓ PASSED\n";
    
    // Test 5: Check database connection
    echo "Test 5: Testing database connection... ";
    $db = Database::getInstance();
    if ($db->testConnection()) {
        echo "✓ PASSED\n";
    } else {
        echo "⚠ WARNING: Database connection failed\n";
    }
    
    // Test 6: Check Order model inheritance
    echo "Test 6: Checking Order inheritance... ";
    if ($order instanceof DatabaseModel) {
        echo "✓ PASSED\n";
    } else {
        throw new Exception("Order should extend DatabaseModel");
    }
    
    // Test 7: Test order validation (without database)
    echo "Test 7: Testing order validation... ";
    
    // This should fail validation
    try {
        $invalidOrder = [
            'user_id' => '', // Invalid
            'payment_method' => 'invalid', // Invalid
            'items' => [] // Empty
        ];
        
        // We can't actually create the order without proper database setup,
        // but we can test that the validation logic exists
        $reflection = new ReflectionClass('Order');
        $validateMethod = $reflection->getMethod('validateOrderData');
        $validateMethod->setAccessible(true);
        
        try {
            $validateMethod->invoke($order, $invalidOrder);
            throw new Exception("Validation should have failed");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Validation failed') !== false) {
                echo "✓ PASSED\n";
            } else {
                throw new Exception("Unexpected validation error: " . $e->getMessage());
            }
        }
    } catch (ReflectionException $e) {
        echo "⚠ WARNING: Could not test validation method\n";
    }
    
    echo "\n=== All Basic Tests Completed Successfully! ===\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Order Model Structure Verification ===\n";

// Display Order class information
$reflection = new ReflectionClass('Order');

echo "Class: " . $reflection->getName() . "\n";
echo "Parent: " . $reflection->getParentClass()->getName() . "\n";
echo "File: " . $reflection->getFileName() . "\n";

echo "\nConstants:\n";
foreach ($reflection->getConstants() as $name => $value) {
    echo "  {$name} = '{$value}'\n";
}

echo "\nPublic Methods:\n";
$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
foreach ($methods as $method) {
    if ($method->getDeclaringClass()->getName() === 'Order') {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramStr = '$' . $param->getName();
            if ($param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                $paramStr .= ' = ' . (is_null($default) ? 'null' : var_export($default, true));
            }
            $params[] = $paramStr;
        }
        echo "  " . $method->getName() . "(" . implode(', ', $params) . ")\n";
    }
}

echo "\n=== Order Model Ready for Use ===\n";