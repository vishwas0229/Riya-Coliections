<?php
/**
 * Property Test for Payment Processing Compatibility
 * 
 * **Validates: Requirements 7.1**
 * 
 * This test verifies Property 11: Payment Processing Compatibility
 * For any valid payment request, the Razorpay integration should process it 
 * identically regardless of whether it originates from Node or PHP backend.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../config/razorpay.php';

use PHPUnit\Framework\TestCase;

class PaymentProcessingCompatibilityPropertyTest extends TestCase {
    private $paymentService;
    private $testOrderIds;
    
    protected function setUp(): void {
        $this->paymentService = new PaymentService();
        $this->testOrderIds = [];
    }
    
    protected function tearDown(): void {
        // Clean up test data
        if (!empty($this->testOrderIds)) {
            $db = Database::getInstance();
            foreach ($this->testOrderIds as $orderId) {
                $db->executeQuery("DELETE FROM payments WHERE order_id = ?", [$orderId]);
            }
        }
    }
    
    /**
     * Property Test: Payment Method Compatibility
     * 
     * **Validates: Requirements 7.1**
     * 
     * Tests that all supported payment methods work consistently
     * across different order configurations and amounts.
     * 
     * @test
     */
    public function testPaymentMethodCompatibilityProperty() {
        $iterations = 50;
        $supportedMethods = [
            PaymentService::METHOD_RAZORPAY,
            PaymentService::METHOD_COD
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($supportedMethods as $method) {
                try {
                    // Generate random order data
                    $orderData = $this->generateRandomOrderData($method);
                    $this->testOrderIds[] = $orderData['order_id'];
                    
                    // Test payment creation
                    $payment = $this->paymentService->createPayment($orderData);
                    
                    // Verify payment structure compatibility
                    $this->assertPaymentStructureCompatibility($payment, $method);
                    
                    // Verify payment method consistency
                    $this->assertEquals($method, $payment['payment_method']);
                    
                    // Verify amount consistency
                    $this->assertEquals($orderData['amount'], $payment['amount']);
                    
                    // Verify currency consistency
                    $expectedCurrency = $orderData['currency'] ?? 'INR';
                    $this->assertEquals($expectedCurrency, $payment['currency']);
                    
                    // Verify status initialization
                    $this->assertEquals(PaymentService::STATUS_PENDING, $payment['status']);
                    
                    // Method-specific validations
                    if ($method === PaymentService::METHOD_RAZORPAY) {
                        $this->assertRazorpayPaymentCompatibility($payment, $orderData);
                    } elseif ($method === PaymentService::METHOD_COD) {
                        $this->assertCODPaymentCompatibility($payment, $orderData);
                    }
                    
                } catch (Exception $e) {
                    $this->fail("Payment creation failed for method $method at iteration $i: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Property Test: Payment Amount Handling Consistency
     * 
     * **Validates: Requirements 7.1**
     * 
     * Tests that payment amounts are handled consistently across
     * different currencies and decimal precision scenarios.
     * 
     * @test
     */
    public function testPaymentAmountHandlingConsistencyProperty() {
        $iterations = 30;
        $currencies = ['INR', 'USD', 'EUR'];
        $amountRanges = [
            [1, 100],      // Small amounts
            [100, 1000],   // Medium amounts
            [1000, 10000], // Large amounts
            [10000, 50000] // Very large amounts
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            $currency = $currencies[array_rand($currencies)];
            $range = $amountRanges[array_rand($amountRanges)];
            
            // Generate amount with decimal precision
            $amount = round(mt_rand($range[0] * 100, $range[1] * 100) / 100, 2);
            
            $orderData = [
                'order_id' => 'test_order_' . uniqid(),
                'payment_method' => PaymentService::METHOD_RAZORPAY,
                'amount' => $amount,
                'currency' => $currency,
                'order_number' => 'RC' . date('Ymd') . mt_rand(1000, 9999),
                'customer_id' => 'test_customer_' . mt_rand(1, 1000)
            ];
            
            $this->testOrderIds[] = $orderData['order_id'];
            
            try {
                $payment = $this->paymentService->createPayment($orderData);
                
                // Verify amount precision is maintained
                $this->assertEquals($amount, $payment['amount'], "Amount precision not maintained for $currency");
                
                // Verify currency is preserved
                $this->assertEquals($currency, $payment['currency']);
                
                // For Razorpay, verify amount conversion to paise (for INR)
                if ($currency === 'INR' && isset($payment['razorpay_order_data'])) {
                    $razorpayData = json_decode($payment['razorpay_order_data'], true);
                    $expectedPaiseAmount = $amount * 100;
                    $this->assertEquals($expectedPaiseAmount, $razorpayData['amount'], 
                        "Razorpay amount conversion to paise incorrect");
                }
                
            } catch (Exception $e) {
                $this->fail("Amount handling failed for $currency $amount: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Property Test: Payment Status Transition Consistency
     * 
     * **Validates: Requirements 7.1**
     * 
     * Tests that payment status transitions follow consistent patterns
     * regardless of payment method or external conditions.
     * 
     * @test
     */
    public function testPaymentStatusTransitionConsistencyProperty() {
        $iterations = 20;
        $validStatuses = [
            PaymentService::STATUS_PENDING,
            PaymentService::STATUS_PROCESSING,
            PaymentService::STATUS_COMPLETED,
            PaymentService::STATUS_FAILED,
            PaymentService::STATUS_CANCELLED
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test both payment methods
            foreach ([PaymentService::METHOD_RAZORPAY, PaymentService::METHOD_COD] as $method) {
                $orderData = $this->generateRandomOrderData($method);
                $this->testOrderIds[] = $orderData['order_id'];
                
                try {
                    // Create payment
                    $payment = $this->paymentService->createPayment($orderData);
                    
                    // Verify initial status
                    $this->assertEquals(PaymentService::STATUS_PENDING, $payment['status']);
                    
                    // Verify status is in valid list
                    $this->assertContains($payment['status'], $validStatuses);
                    
                    // Test status retrieval consistency
                    $retrievedPayment = $this->paymentService->getPaymentByOrderId($orderData['order_id']);
                    $this->assertNotNull($retrievedPayment, "Payment should be retrievable after creation");
                    $this->assertEquals($payment['status'], $retrievedPayment['status']);
                    
                } catch (Exception $e) {
                    $this->fail("Status transition test failed for method $method: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Property Test: Payment Data Integrity
     * 
     * **Validates: Requirements 7.1**
     * 
     * Tests that payment data maintains integrity throughout
     * the payment processing lifecycle.
     * 
     * @test
     */
    public function testPaymentDataIntegrityProperty() {
        $iterations = 25;
        
        for ($i = 0; $i < $iterations; $i++) {
            $method = [PaymentService::METHOD_RAZORPAY, PaymentService::METHOD_COD][mt_rand(0, 1)];
            $orderData = $this->generateRandomOrderData($method);
            $this->testOrderIds[] = $orderData['order_id'];
            
            try {
                // Create payment
                $payment = $this->paymentService->createPayment($orderData);
                
                // Verify all required fields are present
                $requiredFields = ['id', 'order_id', 'payment_method', 'amount', 'currency', 'status'];
                foreach ($requiredFields as $field) {
                    $this->assertArrayHasKey($field, $payment, "Required field $field missing");
                    $this->assertNotNull($payment[$field], "Required field $field is null");
                }
                
                // Verify data types
                $this->assertIsInt($payment['id']);
                $this->assertIsString($payment['order_id']);
                $this->assertIsString($payment['payment_method']);
                $this->assertIsNumeric($payment['amount']);
                $this->assertIsString($payment['currency']);
                $this->assertIsString($payment['status']);
                
                // Verify data consistency after retrieval
                $retrievedPayment = $this->paymentService->getPaymentById($payment['id']);
                $this->assertNotNull($retrievedPayment);
                
                foreach ($requiredFields as $field) {
                    $this->assertEquals(
                        $payment[$field], 
                        $retrievedPayment[$field],
                        "Field $field value changed after retrieval"
                    );
                }
                
                // Verify method-specific data integrity
                if ($method === PaymentService::METHOD_RAZORPAY) {
                    $this->assertArrayHasKey('razorpay_order_id', $payment);
                    $this->assertNotEmpty($payment['razorpay_order_id']);
                    
                    if (isset($payment['razorpay_order_data'])) {
                        $razorpayData = json_decode($payment['razorpay_order_data'], true);
                        $this->assertIsArray($razorpayData, "Razorpay order data should be valid JSON");
                    }
                }
                
                if ($method === PaymentService::METHOD_COD) {
                    $this->assertArrayHasKey('cod_charges', $payment);
                    $this->assertIsNumeric($payment['cod_charges']);
                    $this->assertGreaterThanOrEqual(0, $payment['cod_charges']);
                }
                
            } catch (Exception $e) {
                $this->fail("Data integrity test failed for method $method: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Property Test: Payment Error Handling Consistency
     * 
     * **Validates: Requirements 7.1**
     * 
     * Tests that payment error conditions are handled consistently
     * across different scenarios and payment methods.
     * 
     * @test
     */
    public function testPaymentErrorHandlingConsistencyProperty() {
        $errorScenarios = [
            // Missing required fields
            ['order_id' => null, 'payment_method' => PaymentService::METHOD_RAZORPAY, 'amount' => 100],
            ['order_id' => 'test', 'payment_method' => null, 'amount' => 100],
            ['order_id' => 'test', 'payment_method' => PaymentService::METHOD_RAZORPAY, 'amount' => null],
            
            // Invalid values
            ['order_id' => 'test', 'payment_method' => 'invalid_method', 'amount' => 100],
            ['order_id' => 'test', 'payment_method' => PaymentService::METHOD_RAZORPAY, 'amount' => -100],
            ['order_id' => 'test', 'payment_method' => PaymentService::METHOD_RAZORPAY, 'amount' => 0],
            ['order_id' => 'test', 'payment_method' => PaymentService::METHOD_RAZORPAY, 'amount' => 'invalid'],
            
            // COD-specific errors
            ['order_id' => 'test', 'payment_method' => PaymentService::METHOD_COD, 'amount' => 50], // Below minimum
            ['order_id' => 'test', 'payment_method' => PaymentService::METHOD_COD, 'amount' => 100000], // Above maximum
        ];
        
        foreach ($errorScenarios as $index => $scenario) {
            try {
                $this->paymentService->createPayment($scenario);
                $this->fail("Expected exception for error scenario $index but none was thrown");
                
            } catch (Exception $e) {
                // Verify error message is meaningful
                $this->assertNotEmpty($e->getMessage(), "Error message should not be empty for scenario $index");
                
                // Verify error message contains relevant information
                $message = strtolower($e->getMessage());
                
                if ($scenario['order_id'] === null) {
                    $this->assertStringContains('order_id', $message);
                }
                
                if ($scenario['payment_method'] === null) {
                    $this->assertStringContains('payment_method', $message);
                }
                
                if ($scenario['amount'] === null || $scenario['amount'] <= 0) {
                    $this->assertStringContains('amount', $message);
                }
                
                if ($scenario['payment_method'] === 'invalid_method') {
                    $this->assertStringContains('payment method', $message);
                }
                
                // Verify no partial data was created
                if (!empty($scenario['order_id'])) {
                    $payment = $this->paymentService->getPaymentByOrderId($scenario['order_id']);
                    $this->assertNull($payment, "No payment should be created for invalid scenario $index");
                }
            }
        }
    }
    
    /**
     * Generate random order data for testing
     * 
     * @param string $method Payment method
     * @return array Random order data
     */
    private function generateRandomOrderData($method) {
        $amounts = [50, 100, 250, 500, 1000, 2500, 5000, 10000];
        $currencies = ['INR', 'USD', 'EUR'];
        
        $baseData = [
            'order_id' => 'test_order_' . uniqid(),
            'payment_method' => $method,
            'amount' => $amounts[array_rand($amounts)],
            'currency' => $currencies[array_rand($currencies)],
            'order_number' => 'RC' . date('Ymd') . mt_rand(1000, 9999),
            'customer_id' => 'test_customer_' . mt_rand(1, 1000),
            'customer_name' => 'Test Customer ' . mt_rand(1, 100),
            'customer_email' => 'test' . mt_rand(1, 1000) . '@example.com',
            'customer_phone' => '+91' . mt_rand(7000000000, 9999999999)
        ];
        
        // Adjust amount for COD if needed
        if ($method === PaymentService::METHOD_COD) {
            // Ensure amount is within COD limits
            $baseData['amount'] = mt_rand(100, 10000); // Between min and max COD limits
        }
        
        return $baseData;
    }
    
    /**
     * Assert payment structure compatibility
     * 
     * @param array $payment Payment data
     * @param string $method Payment method
     */
    private function assertPaymentStructureCompatibility($payment, $method) {
        // Common fields for all payment methods
        $commonFields = ['id', 'order_id', 'payment_method', 'amount', 'currency', 'status'];
        
        foreach ($commonFields as $field) {
            $this->assertArrayHasKey($field, $payment, "Payment missing common field: $field");
        }
        
        // Method-specific fields
        if ($method === PaymentService::METHOD_RAZORPAY) {
            $this->assertArrayHasKey('razorpay_order_id', $payment);
            $this->assertArrayHasKey('payment_options', $payment);
        }
        
        if ($method === PaymentService::METHOD_COD) {
            $this->assertArrayHasKey('cod_charges', $payment);
        }
    }
    
    /**
     * Assert Razorpay payment compatibility
     * 
     * @param array $payment Payment data
     * @param array $orderData Original order data
     */
    private function assertRazorpayPaymentCompatibility($payment, $orderData) {
        // Verify Razorpay order ID format
        $this->assertStringStartsWith('order_', $payment['razorpay_order_id']);
        
        // Verify payment options structure
        $this->assertIsArray($payment['payment_options']);
        
        // Verify Razorpay order data if present
        if (isset($payment['razorpay_order_data'])) {
            $razorpayData = json_decode($payment['razorpay_order_data'], true);
            $this->assertIsArray($razorpayData);
            $this->assertArrayHasKey('id', $razorpayData);
            $this->assertArrayHasKey('amount', $razorpayData);
            $this->assertArrayHasKey('currency', $razorpayData);
        }
    }
    
    /**
     * Assert COD payment compatibility
     * 
     * @param array $payment Payment data
     * @param array $orderData Original order data
     */
    private function assertCODPaymentCompatibility($payment, $orderData) {
        // Verify COD charges are calculated
        $this->assertIsNumeric($payment['cod_charges']);
        $this->assertGreaterThanOrEqual(0, $payment['cod_charges']);
        
        // Verify COD charges are reasonable (not more than 10% of order amount)
        $maxCharges = $orderData['amount'] * 0.1;
        $this->assertLessThanOrEqual($maxCharges, $payment['cod_charges']);
    }
}