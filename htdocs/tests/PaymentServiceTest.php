<?php
/**
 * Payment Service Unit Tests
 * 
 * Tests for the PaymentService class functionality including Razorpay integration,
 * COD processing, and payment validation.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../config/razorpay.php';

class PaymentServiceTest extends TestCase {
    private $paymentService;
    private $mockDb;
    
    protected function setUp(): void {
        // Mock database for testing
        $this->mockDb = $this->createMock(Database::class);
        
        // Create PaymentService instance
        $this->paymentService = new PaymentService();
    }
    
    /**
     * Test payment method constants
     */
    public function testPaymentMethodConstants() {
        $this->assertEquals('razorpay', PaymentService::METHOD_RAZORPAY);
        $this->assertEquals('cod', PaymentService::METHOD_COD);
    }
    
    /**
     * Test payment status constants
     */
    public function testPaymentStatusConstants() {
        $this->assertEquals('pending', PaymentService::STATUS_PENDING);
        $this->assertEquals('processing', PaymentService::STATUS_PROCESSING);
        $this->assertEquals('completed', PaymentService::STATUS_COMPLETED);
        $this->assertEquals('failed', PaymentService::STATUS_FAILED);
        $this->assertEquals('cancelled', PaymentService::STATUS_CANCELLED);
        $this->assertEquals('refunded', PaymentService::STATUS_REFUNDED);
    }
    
    /**
     * Test supported payment methods
     */
    public function testGetSupportedPaymentMethods() {
        $methods = $this->paymentService->getSupportedPaymentMethods();
        
        $this->assertIsArray($methods);
        $this->assertArrayHasKey(PaymentService::METHOD_RAZORPAY, $methods);
        $this->assertArrayHasKey(PaymentService::METHOD_COD, $methods);
        
        // Check Razorpay method structure
        $razorpayMethod = $methods[PaymentService::METHOD_RAZORPAY];
        $this->assertArrayHasKey('name', $razorpayMethod);
        $this->assertArrayHasKey('description', $razorpayMethod);
        $this->assertArrayHasKey('enabled', $razorpayMethod);
        $this->assertArrayHasKey('methods', $razorpayMethod);
        
        // Check COD method structure
        $codMethod = $methods[PaymentService::METHOD_COD];
        $this->assertArrayHasKey('name', $codMethod);
        $this->assertArrayHasKey('description', $codMethod);
        $this->assertArrayHasKey('enabled', $codMethod);
        $this->assertArrayHasKey('charges', $codMethod);
    }
    
    /**
     * Test COD charges calculation
     */
    public function testCalculateCODCharges() {
        $reflection = new ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('calculateCODCharges');
        $method->setAccessible(true);
        
        // Test different amounts
        $charges1 = $method->invoke($this->paymentService, 1000);
        $charges2 = $method->invoke($this->paymentService, 5000);
        $charges3 = $method->invoke($this->paymentService, 500);
        
        $this->assertIsFloat($charges1);
        $this->assertIsFloat($charges2);
        $this->assertIsFloat($charges3);
        
        // Charges should be positive
        $this->assertGreaterThan(0, $charges1);
        $this->assertGreaterThan(0, $charges2);
        $this->assertGreaterThan(0, $charges3);
        
        // Higher amounts should generally have higher charges (within limits)
        $this->assertGreaterThanOrEqual($charges3, $charges1);
    }
    
    /**
     * Test COD eligibility validation
     */
    public function testValidateCODEligibility() {
        $reflection = new ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('validateCODEligibility');
        $method->setAccessible(true);
        
        // Test valid amount
        $validOrderData = ['amount' => 1000];
        $this->assertTrue($method->invoke($this->paymentService, $validOrderData));
        
        // Test amount too low
        $lowAmountData = ['amount' => 50];
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('COD not available for orders below');
        $method->invoke($this->paymentService, $lowAmountData);
    }
    
    /**
     * Test order data validation
     */
    public function testValidateOrderData() {
        $reflection = new ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('validateOrderData');
        $method->setAccessible(true);
        
        // Test valid data
        $validData = [
            'order_id' => 1,
            'payment_method' => PaymentService::METHOD_COD,
            'amount' => 100.00
        ];
        $this->assertTrue($method->invoke($this->paymentService, $validData));
        
        // Test missing required field
        $invalidData = [
            'order_id' => 1,
            'payment_method' => PaymentService::METHOD_COD
            // Missing amount
        ];
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required field: amount');
        $method->invoke($this->paymentService, $invalidData);
    }
    
    /**
     * Test invalid payment method
     */
    public function testInvalidPaymentMethod() {
        $reflection = new ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('validateOrderData');
        $method->setAccessible(true);
        
        $invalidData = [
            'order_id' => 1,
            'payment_method' => 'invalid_method',
            'amount' => 100.00
        ];
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid payment method');
        $method->invoke($this->paymentService, $invalidData);
    }
    
    /**
     * Test invalid amount
     */
    public function testInvalidAmount() {
        $reflection = new ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('validateOrderData');
        $method->setAccessible(true);
        
        // Test negative amount
        $negativeAmountData = [
            'order_id' => 1,
            'payment_method' => PaymentService::METHOD_COD,
            'amount' => -100.00
        ];
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid payment amount');
        $method->invoke($this->paymentService, $negativeAmountData);
    }
    
    /**
     * Test receipt ID generation
     */
    public function testGenerateReceiptId() {
        $reflection = new ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('generateReceiptId');
        $method->setAccessible(true);
        
        $receiptId1 = $method->invoke($this->paymentService, 123);
        $receiptId2 = $method->invoke($this->paymentService, 456);
        
        $this->assertIsString($receiptId1);
        $this->assertIsString($receiptId2);
        $this->assertNotEquals($receiptId1, $receiptId2);
        
        // Should contain order ID
        $this->assertStringContainsString('123', $receiptId1);
        $this->assertStringContainsString('456', $receiptId2);
        
        // Should start with receipt_order_
        $this->assertStringStartsWith('receipt_order_', $receiptId1);
        $this->assertStringStartsWith('receipt_order_', $receiptId2);
    }
}

/**
 * Payment Model Unit Tests
 */
class PaymentModelTest extends TestCase {
    
    /**
     * Test payment method display names
     */
    public function testGetPaymentMethodName() {
        $this->assertEquals('Online Payment', Payment::getPaymentMethodName(Payment::METHOD_RAZORPAY));
        $this->assertEquals('Cash on Delivery', Payment::getPaymentMethodName(Payment::METHOD_COD));
        $this->assertEquals('Unknown', Payment::getPaymentMethodName('unknown'));
    }
    
    /**
     * Test payment status display names
     */
    public function testGetPaymentStatusName() {
        $this->assertEquals('Pending', Payment::getPaymentStatusName(Payment::STATUS_PENDING));
        $this->assertEquals('Completed', Payment::getPaymentStatusName(Payment::STATUS_COMPLETED));
        $this->assertEquals('Failed', Payment::getPaymentStatusName(Payment::STATUS_FAILED));
        $this->assertEquals('Unknown', Payment::getPaymentStatusName('unknown'));
    }
    
    /**
     * Test payment status checks
     */
    public function testPaymentStatusChecks() {
        // Test successful status
        $this->assertTrue(Payment::isSuccessful(Payment::STATUS_COMPLETED));
        $this->assertFalse(Payment::isSuccessful(Payment::STATUS_PENDING));
        $this->assertFalse(Payment::isSuccessful(Payment::STATUS_FAILED));
        
        // Test pending status
        $this->assertTrue(Payment::isPending(Payment::STATUS_PENDING));
        $this->assertTrue(Payment::isPending(Payment::STATUS_PROCESSING));
        $this->assertFalse(Payment::isPending(Payment::STATUS_COMPLETED));
        $this->assertFalse(Payment::isPending(Payment::STATUS_FAILED));
        
        // Test failed status
        $this->assertTrue(Payment::hasFailed(Payment::STATUS_FAILED));
        $this->assertTrue(Payment::hasFailed(Payment::STATUS_CANCELLED));
        $this->assertFalse(Payment::hasFailed(Payment::STATUS_COMPLETED));
        $this->assertFalse(Payment::hasFailed(Payment::STATUS_PENDING));
    }
    
    /**
     * Test amount formatting
     */
    public function testFormatAmount() {
        $this->assertEquals('₹100.00', Payment::formatAmount(100));
        $this->assertEquals('₹1,500.50', Payment::formatAmount(1500.50));
        $this->assertEquals('$100.00', Payment::formatAmount(100, 'USD'));
        $this->assertEquals('€100.00', Payment::formatAmount(100, 'EUR'));
    }
    
    /**
     * Test payment data validation
     */
    public function testValidatePaymentData() {
        $payment = new Payment();
        $reflection = new ReflectionClass($payment);
        $method = $reflection->getMethod('validatePaymentData');
        $method->setAccessible(true);
        
        // Test valid data
        $validData = [
            'order_id' => 1,
            'payment_method' => Payment::METHOD_RAZORPAY,
            'amount' => 100.00
        ];
        $this->assertTrue($method->invoke($payment, $validData));
        
        // Test invalid payment method
        $invalidMethodData = [
            'order_id' => 1,
            'payment_method' => 'invalid',
            'amount' => 100.00
        ];
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid payment method');
        $method->invoke($payment, $invalidMethodData);
    }
    
    /**
     * Test field validation
     */
    public function testIsValidField() {
        $payment = new Payment();
        $reflection = new ReflectionClass($payment);
        $method = $reflection->getMethod('isValidField');
        $method->setAccessible(true);
        
        // Test valid fields
        $this->assertTrue($method->invoke($payment, 'status'));
        $this->assertTrue($method->invoke($payment, 'razorpay_payment_id'));
        $this->assertTrue($method->invoke($payment, 'cod_charges'));
        
        // Test invalid fields
        $this->assertFalse($method->invoke($payment, 'invalid_field'));
        $this->assertFalse($method->invoke($payment, 'order_id')); // Not updatable
        $this->assertFalse($method->invoke($payment, 'amount')); // Not updatable
    }
}

/**
 * Razorpay Configuration Tests
 */
class RazorpayConfigTest extends TestCase {
    
    /**
     * Test configuration loading
     */
    public function testGetConfig() {
        $config = RazorpayConfig::getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('key_id', $config);
        $this->assertArrayHasKey('key_secret', $config);
        $this->assertArrayHasKey('webhook_secret', $config);
        $this->assertArrayHasKey('currency', $config);
        $this->assertArrayHasKey('api_url', $config);
        $this->assertArrayHasKey('supported_methods', $config);
        $this->assertArrayHasKey('supported_currencies', $config);
        
        // Test default values
        $this->assertEquals('INR', $config['currency']);
        $this->assertEquals('https://api.razorpay.com/v1/', $config['api_url']);
        $this->assertContains('card', $config['supported_methods']);
        $this->assertContains('upi', $config['supported_methods']);
        $this->assertContains('INR', $config['supported_currencies']);
    }
    
    /**
     * Test configuration validation with missing credentials
     */
    public function testValidationWithMissingCredentials() {
        // This will likely fail in test environment where credentials are not set
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Razorpay Key ID is not configured');
        RazorpayConfig::validate();
    }
}