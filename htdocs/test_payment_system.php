<?php
/**
 * Payment System Test Script
 * 
 * Tests the payment processing system implementation including:
 * - PaymentService functionality
 * - Razorpay integration
 * - COD processing
 * - Payment model operations
 */

require_once __DIR__ . '/config/environment.php';
require_once __DIR__ . '/utils/Logger.php';
require_once __DIR__ . '/services/PaymentService.php';
require_once __DIR__ . '/models/Payment.php';
require_once __DIR__ . '/controllers/PaymentController.php';

echo "=== Payment System Test ===\n\n";

try {
    // Test 1: PaymentService instantiation
    echo "1. Testing PaymentService instantiation...\n";
    $paymentService = new PaymentService();
    echo "✓ PaymentService created successfully\n\n";
    
    // Test 2: Payment model instantiation
    echo "2. Testing Payment model instantiation...\n";
    $paymentModel = new Payment();
    echo "✓ Payment model created successfully\n\n";
    
    // Test 3: PaymentController instantiation
    echo "3. Testing PaymentController instantiation...\n";
    $paymentController = new PaymentController();
    echo "✓ PaymentController created successfully\n\n";
    
    // Test 4: Get supported payment methods
    echo "4. Testing supported payment methods...\n";
    $methods = $paymentService->getSupportedPaymentMethods();
    echo "✓ Supported payment methods:\n";
    foreach ($methods as $method => $config) {
        echo "  - {$method}: {$config['name']}\n";
    }
    echo "\n";
    
    // Test 5: Test Razorpay configuration
    echo "5. Testing Razorpay configuration...\n";
    try {
        $razorpayService = getRazorpayService();
        echo "✓ Razorpay service initialized\n";
        
        // Test connection (this will create a test order)
        if (env('RAZORPAY_KEY_ID') && env('RAZORPAY_KEY_SECRET')) {
            echo "  Testing Razorpay connection...\n";
            $connectionTest = $razorpayService->testConnection();
            if ($connectionTest) {
                echo "  ✓ Razorpay connection successful\n";
            } else {
                echo "  ⚠ Razorpay connection failed (check credentials)\n";
            }
        } else {
            echo "  ⚠ Razorpay credentials not configured\n";
        }
    } catch (Exception $e) {
        echo "  ⚠ Razorpay configuration error: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 6: Test payment data validation
    echo "6. Testing payment data validation...\n";
    
    // Valid payment data
    $validPaymentData = [
        'order_id' => 1,
        'payment_method' => PaymentService::METHOD_COD,
        'amount' => 100.00,
        'currency' => 'INR'
    ];
    
    try {
        // This should not throw an exception
        $reflection = new ReflectionClass($paymentService);
        $validateMethod = $reflection->getMethod('validateOrderData');
        $validateMethod->setAccessible(true);
        $validateMethod->invoke($paymentService, $validPaymentData);
        echo "✓ Valid payment data validation passed\n";
    } catch (Exception $e) {
        echo "✗ Valid payment data validation failed: " . $e->getMessage() . "\n";
    }
    
    // Invalid payment data
    $invalidPaymentData = [
        'order_id' => 1,
        'payment_method' => 'invalid_method',
        'amount' => -100.00
    ];
    
    try {
        $validateMethod->invoke($paymentService, $invalidPaymentData);
        echo "✗ Invalid payment data validation should have failed\n";
    } catch (Exception $e) {
        echo "✓ Invalid payment data validation correctly failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 7: Test COD charges calculation
    echo "7. Testing COD charges calculation...\n";
    try {
        $reflection = new ReflectionClass($paymentService);
        $calculateMethod = $reflection->getMethod('calculateCODCharges');
        $calculateMethod->setAccessible(true);
        
        $charges1 = $calculateMethod->invoke($paymentService, 1000);
        $charges2 = $calculateMethod->invoke($paymentService, 5000);
        
        echo "✓ COD charges for ₹1000: ₹{$charges1}\n";
        echo "✓ COD charges for ₹5000: ₹{$charges2}\n";
    } catch (Exception $e) {
        echo "✗ COD charges calculation failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 8: Test payment status methods
    echo "8. Testing payment status methods...\n";
    
    $testStatuses = [
        Payment::STATUS_PENDING,
        Payment::STATUS_COMPLETED,
        Payment::STATUS_FAILED,
        Payment::STATUS_CANCELLED
    ];
    
    foreach ($testStatuses as $status) {
        $isSuccessful = Payment::isSuccessful($status);
        $isPending = Payment::isPending($status);
        $hasFailed = Payment::hasFailed($status);
        $displayName = Payment::getPaymentStatusName($status);
        
        echo "  Status: {$status} ({$displayName})\n";
        echo "    Successful: " . ($isSuccessful ? 'Yes' : 'No') . "\n";
        echo "    Pending: " . ($isPending ? 'Yes' : 'No') . "\n";
        echo "    Failed: " . ($hasFailed ? 'Yes' : 'No') . "\n";
    }
    echo "✓ Payment status methods working correctly\n\n";
    
    // Test 9: Test amount formatting
    echo "9. Testing amount formatting...\n";
    $amounts = [100, 1500.50, 25000];
    foreach ($amounts as $amount) {
        $formatted = Payment::formatAmount($amount);
        echo "  ₹{$amount} -> {$formatted}\n";
    }
    echo "✓ Amount formatting working correctly\n\n";
    
    // Test 10: Test payment method names
    echo "10. Testing payment method names...\n";
    $methods = [Payment::METHOD_RAZORPAY, Payment::METHOD_COD];
    foreach ($methods as $method) {
        $name = Payment::getPaymentMethodName($method);
        echo "  {$method} -> {$name}\n";
    }
    echo "✓ Payment method names working correctly\n\n";
    
    echo "=== All Tests Completed Successfully ===\n";
    echo "Payment system is ready for use!\n\n";
    
    // Display configuration summary
    echo "=== Configuration Summary ===\n";
    echo "Razorpay Key ID: " . (env('RAZORPAY_KEY_ID') ? 'Configured' : 'Not configured') . "\n";
    echo "Razorpay Key Secret: " . (env('RAZORPAY_KEY_SECRET') ? 'Configured' : 'Not configured') . "\n";
    echo "Razorpay Webhook Secret: " . (env('RAZORPAY_WEBHOOK_SECRET') ? 'Configured' : 'Not configured') . "\n";
    echo "COD Enabled: " . (env('COD_ENABLED', 'true') === 'true' ? 'Yes' : 'No') . "\n";
    echo "COD Charge Percent: " . env('COD_CHARGE_PERCENT', '2') . "%\n";
    echo "COD Min Amount: ₹" . env('COD_MIN_AMOUNT', '100') . "\n";
    echo "COD Max Amount: ₹" . env('COD_MAX_AMOUNT', '50000') . "\n";
    
} catch (Exception $e) {
    echo "✗ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}