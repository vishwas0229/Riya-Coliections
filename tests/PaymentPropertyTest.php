<?php
/**
 * Payment System Property-Based Tests
 * 
 * Property-based tests for payment processing system to verify universal
 * properties hold across all valid inputs.
 * 
 * **Validates: Requirements 7.1, 7.2**
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../config/razorpay.php';

class PaymentPropertyTest extends TestCase {
    
    /**
     * Property: Payment amount calculations are always consistent
     * **Validates: Requirements 7.1**
     * 
     * @test
     */
    public function testPaymentAmountConsistency() {
        $paymentService = new PaymentService();
        $reflection = new ReflectionClass($paymentService);
        $calculateMethod = $reflection->getMethod('calculateCODCharges');
        $calculateMethod->setAccessible(true);
        
        for ($i = 0; $i < 100; $i++) {
            // Generate random valid amounts
            $amount = mt_rand(100, 50000); // Between ₹100 and ₹50,000
            
            $charges = $calculateMethod->invoke($paymentService, $amount);
            
            // Property: COD charges should always be positive
            $this->assertGreaterThan(0, $charges, "COD charges must be positive for amount: {$amount}");
            
            // Property: COD charges should be reasonable (not more than 10% of order)
            $this->assertLessThanOrEqual($amount * 0.1, $charges, "COD charges should not exceed 10% of order amount: {$amount}");
            
            // Property: COD charges should have minimum and maximum bounds
            $minCharge = env('COD_CHARGE_MIN', 20);
            $maxCharge = env('COD_CHARGE_MAX', 100);
            
            $this->assertGreaterThanOrEqual($minCharge, $charges, "COD charges should meet minimum for amount: {$amount}");
            $this->assertLessThanOrEqual($maxCharge, $charges, "COD charges should not exceed maximum for amount: {$amount}");
        }
    }
    
    /**
     * Property: Payment validation is consistent and deterministic
     * **Validates: Requirements 7.1**
     * 
     * @test
     */
    public function testPaymentValidationConsistency() {
        $paymentService = new PaymentService();
        $reflection = new ReflectionClass($paymentService);
        $validateMethod = $reflection->getMethod('validateOrderData');
        $validateMethod->setAccessible(true);
        
        for ($i = 0; $i < 100; $i++) {
            // Generate random valid payment data
            $orderData = [
                'order_id' => mt_rand(1, 10000),
                'payment_method' => mt_rand(0, 1) ? PaymentService::METHOD_RAZORPAY : PaymentService::METHOD_COD,
                'amount' => mt_rand(100, 50000) / 100, // Random amount with decimals
                'currency' => 'INR'
            ];
            
            // Property: Valid data should always pass validation
            try {
                $result = $validateMethod->invoke($paymentService, $orderData);
                $this->assertTrue($result, "Valid payment data should pass validation");
            } catch (Exception $e) {
                $this->fail("Valid payment data failed validation: " . $e->getMessage() . " for data: " . json_encode($orderData));
            }
            
            // Property: Invalid amounts should always fail
            $invalidOrderData = $orderData;
            $invalidOrderData['amount'] = -mt_rand(1, 1000);
            
            $this->expectException(Exception::class);
            try {
                $validateMethod->invoke($paymentService, $invalidOrderData);
                $this->fail("Negative amount should have failed validation");
            } catch (Exception $e) {
                $this->assertStringContainsString('Invalid payment amount', $e->getMessage());
            }
        }
    }
    
    /**
     * Property: Receipt ID generation is always unique and well-formed
     * **Validates: Requirements 7.1**
     * 
     * @test
     */
    public function testReceiptIdUniqueness() {
        $paymentService = new PaymentService();
        $reflection = new ReflectionClass($paymentService);
        $generateMethod = $reflection->getMethod('generateReceiptId');
        $generateMethod->setAccessible(true);
        
        $generatedIds = [];
        
        for ($i = 0; $i < 100; $i++) {
            $orderId = mt_rand(1, 10000);
            $receiptId = $generateMethod->invoke($paymentService, $orderId);
            
            // Property: Receipt ID should always be a string
            $this->assertIsString($receiptId, "Receipt ID must be a string");
            
            // Property: Receipt ID should contain the order ID
            $this->assertStringContainsString((string)$orderId, $receiptId, "Receipt ID must contain order ID");
            
            // Property: Receipt ID should start with expected prefix
            $this->assertStringStartsWith('receipt_order_', $receiptId, "Receipt ID must have correct prefix");
            
            // Property: Receipt IDs should be unique
            $this->assertNotContains($receiptId, $generatedIds, "Receipt ID must be unique: {$receiptId}");
            
            $generatedIds[] = $receiptId;
        }
    }
    
    /**
     * Property: Payment status transitions are logically consistent
     * **Validates: Requirements 7.1, 7.2**
     * 
     * @test
     */
    public function testPaymentStatusLogic() {
        $allStatuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING,
            Payment::STATUS_COMPLETED,
            Payment::STATUS_FAILED,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_REFUNDED
        ];
        
        for ($i = 0; $i < 100; $i++) {
            $status = $allStatuses[array_rand($allStatuses)];
            
            // Property: Status classification should be mutually exclusive
            $isSuccessful = Payment::isSuccessful($status);
            $isPending = Payment::isPending($status);
            $hasFailed = Payment::hasFailed($status);
            
            // A payment cannot be both successful and failed
            $this->assertFalse($isSuccessful && $hasFailed, "Payment cannot be both successful and failed: {$status}");
            
            // A payment cannot be both successful and pending
            $this->assertFalse($isSuccessful && $isPending, "Payment cannot be both successful and pending: {$status}");
            
            // Property: Completed status should always be successful
            if ($status === Payment::STATUS_COMPLETED) {
                $this->assertTrue($isSuccessful, "Completed payment must be successful");
                $this->assertFalse($isPending, "Completed payment cannot be pending");
                $this->assertFalse($hasFailed, "Completed payment cannot be failed");
            }
            
            // Property: Failed/Cancelled status should always be failed
            if (in_array($status, [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED])) {
                $this->assertTrue($hasFailed, "Failed/Cancelled payment must be marked as failed");
                $this->assertFalse($isSuccessful, "Failed/Cancelled payment cannot be successful");
                $this->assertFalse($isPending, "Failed/Cancelled payment cannot be pending");
            }
            
            // Property: Pending/Processing status should always be pending
            if (in_array($status, [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])) {
                $this->assertTrue($isPending, "Pending/Processing payment must be marked as pending");
                $this->assertFalse($isSuccessful, "Pending/Processing payment cannot be successful");
                $this->assertFalse($hasFailed, "Pending/Processing payment cannot be failed");
            }
        }
    }
    
    /**
     * Property: Amount formatting is consistent and reversible
     * **Validates: Requirements 7.1**
     * 
     * @test
     */
    public function testAmountFormattingConsistency() {
        for ($i = 0; $i < 100; $i++) {
            // Generate random amounts
            $amount = mt_rand(1, 1000000) / 100; // Up to ₹10,000 with decimals
            
            $formatted = Payment::formatAmount($amount);
            
            // Property: Formatted amount should always be a string
            $this->assertIsString($formatted, "Formatted amount must be a string");
            
            // Property: Formatted amount should contain currency symbol
            $this->assertStringContainsString('₹', $formatted, "Formatted amount must contain currency symbol");
            
            // Property: Formatted amount should contain the original amount
            $amountStr = number_format($amount, 2);
            $this->assertStringContainsString($amountStr, $formatted, "Formatted amount must contain original amount: {$amount}");
            
            // Property: Different currencies should produce different symbols
            $usdFormatted = Payment::formatAmount($amount, 'USD');
            $eurFormatted = Payment::formatAmount($amount, 'EUR');
            
            $this->assertStringContainsString('$', $usdFormatted, "USD formatting must contain dollar symbol");
            $this->assertStringContainsString('€', $eurFormatted, "EUR formatting must contain euro symbol");
            
            // Property: Same amount should produce same formatting
            $formatted2 = Payment::formatAmount($amount);
            $this->assertEquals($formatted, $formatted2, "Same amount should produce identical formatting");
        }
    }
    
    /**
     * Property: COD eligibility validation is consistent
     * **Validates: Requirements 7.1**
     * 
     * @test
     */
    public function testCODEligibilityConsistency() {
        $paymentService = new PaymentService();
        $reflection = new ReflectionClass($paymentService);
        $validateMethod = $reflection->getMethod('validateCODEligibility');
        $validateMethod->setAccessible(true);
        
        $minAmount = env('COD_MIN_AMOUNT', 100);
        $maxAmount = env('COD_MAX_AMOUNT', 50000);
        
        for ($i = 0; $i < 100; $i++) {
            // Test amounts within valid range
            $validAmount = mt_rand($minAmount, $maxAmount);
            $validOrderData = ['amount' => $validAmount];
            
            try {
                $result = $validateMethod->invoke($paymentService, $validOrderData);
                $this->assertTrue($result, "Valid COD amount should pass validation: {$validAmount}");
            } catch (Exception $e) {
                $this->fail("Valid COD amount failed validation: {$validAmount} - " . $e->getMessage());
            }
            
            // Test amounts below minimum
            if ($minAmount > 1) {
                $lowAmount = mt_rand(1, $minAmount - 1);
                $lowOrderData = ['amount' => $lowAmount];
                
                try {
                    $validateMethod->invoke($paymentService, $lowOrderData);
                    $this->fail("Low COD amount should have failed validation: {$lowAmount}");
                } catch (Exception $e) {
                    $this->assertStringContainsString('COD not available for orders below', $e->getMessage());
                }
            }
            
            // Test amounts above maximum
            $highAmount = $maxAmount + mt_rand(1, 10000);
            $highOrderData = ['amount' => $highAmount];
            
            try {
                $validateMethod->invoke($paymentService, $highOrderData);
                $this->fail("High COD amount should have failed validation: {$highAmount}");
            } catch (Exception $e) {
                $this->assertStringContainsString('COD not available for orders above', $e->getMessage());
            }
        }
    }
    
    /**
     * Property: Razorpay payment data validation is strict and consistent
     * **Validates: Requirements 7.2**
     * 
     * @test
     */
    public function testRazorpayValidationConsistency() {
        $razorpayService = new RazorpayService();
        
        for ($i = 0; $i < 50; $i++) {
            // Generate valid Razorpay data
            $validData = [
                'razorpay_order_id' => 'order_' . bin2hex(random_bytes(8)),
                'razorpay_payment_id' => 'pay_' . bin2hex(random_bytes(8)),
                'razorpay_signature' => bin2hex(random_bytes(32)) // 64 character hex string
            ];
            
            try {
                $result = $razorpayService->validatePaymentData($validData);
                $this->assertTrue($result, "Valid Razorpay data should pass validation");
            } catch (Exception $e) {
                $this->fail("Valid Razorpay data failed validation: " . $e->getMessage());
            }
            
            // Property: Invalid order ID format should always fail
            $invalidOrderData = $validData;
            $invalidOrderData['razorpay_order_id'] = 'invalid_' . bin2hex(random_bytes(4));
            
            try {
                $razorpayService->validatePaymentData($invalidOrderData);
                $this->fail("Invalid order ID format should have failed validation");
            } catch (Exception $e) {
                $this->assertStringContainsString('Invalid Razorpay order ID format', $e->getMessage());
            }
            
            // Property: Invalid payment ID format should always fail
            $invalidPaymentData = $validData;
            $invalidPaymentData['razorpay_payment_id'] = 'invalid_' . bin2hex(random_bytes(4));
            
            try {
                $razorpayService->validatePaymentData($invalidPaymentData);
                $this->fail("Invalid payment ID format should have failed validation");
            } catch (Exception $e) {
                $this->assertStringContainsString('Invalid Razorpay payment ID format', $e->getMessage());
            }
            
            // Property: Invalid signature format should always fail
            $invalidSignatureData = $validData;
            $invalidSignatureData['razorpay_signature'] = 'short_sig';
            
            try {
                $razorpayService->validatePaymentData($invalidSignatureData);
                $this->fail("Invalid signature format should have failed validation");
            } catch (Exception $e) {
                $this->assertStringContainsString('Invalid Razorpay signature format', $e->getMessage());
            }
        }
    }
    
    /**
     * Property: Payment options generation is consistent and complete
     * **Validates: Requirements 7.1, 7.2**
     * 
     * @test
     */
    public function testPaymentOptionsConsistency() {
        $razorpayService = new RazorpayService();
        
        for ($i = 0; $i < 50; $i++) {
            $orderData = [
                'amount' => mt_rand(100, 10000),
                'currency' => 'INR',
                'razorpay_order_id' => 'order_' . bin2hex(random_bytes(8)),
                'order_number' => 'RC' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'customer_name' => 'Test Customer ' . $i,
                'customer_email' => "test{$i}@example.com",
                'customer_phone' => '98765' . str_pad($i, 5, '0', STR_PAD_LEFT)
            ];
            
            $options = $razorpayService->generatePaymentOptions($orderData);
            
            // Property: Options should always contain required fields
            $requiredFields = ['key', 'amount', 'currency', 'order_id', 'name', 'prefill', 'theme', 'method'];
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $options, "Payment options must contain {$field}");
            }
            
            // Property: Amount should be converted to paise
            $expectedAmount = $orderData['amount'] * 100;
            $this->assertEquals($expectedAmount, $options['amount'], "Amount should be converted to paise");
            
            // Property: Prefill data should match input
            $this->assertEquals($orderData['customer_name'], $options['prefill']['name'], "Prefill name should match input");
            $this->assertEquals($orderData['customer_email'], $options['prefill']['email'], "Prefill email should match input");
            $this->assertEquals($orderData['customer_phone'], $options['prefill']['contact'], "Prefill contact should match input");
            
            // Property: Order ID should match input
            $this->assertEquals($orderData['razorpay_order_id'], $options['order_id'], "Order ID should match input");
        }
    }
}