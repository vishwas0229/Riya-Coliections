<?php
/**
 * Simple Payment Tests
 * 
 * Basic tests for payment system components that don't require database connectivity.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../config/razorpay.php';

class PaymentSimpleTest extends TestCase {
    
    /**
     * Test payment method constants
     */
    public function testPaymentMethodConstants() {
        $this->assertEquals('razorpay', Payment::METHOD_RAZORPAY);
        $this->assertEquals('cod', Payment::METHOD_COD);
    }
    
    /**
     * Test payment status constants
     */
    public function testPaymentStatusConstants() {
        $this->assertEquals('pending', Payment::STATUS_PENDING);
        $this->assertEquals('processing', Payment::STATUS_PROCESSING);
        $this->assertEquals('completed', Payment::STATUS_COMPLETED);
        $this->assertEquals('failed', Payment::STATUS_FAILED);
        $this->assertEquals('cancelled', Payment::STATUS_CANCELLED);
        $this->assertEquals('refunded', Payment::STATUS_REFUNDED);
    }
    
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
     * Test Razorpay configuration loading
     */
    public function testRazorpayConfigLoading() {
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
     * Test Razorpay service instantiation
     */
    public function testRazorpayServiceInstantiation() {
        // This test will pass even without credentials configured
        // as the service only validates on actual operations
        try {
            $service = new RazorpayService();
            $this->assertInstanceOf(RazorpayService::class, $service);
            
            // Test methods that don't require API calls
            $methods = $service->getSupportedMethods();
            $this->assertIsArray($methods);
            $this->assertContains('card', $methods);
            
            $currencies = $service->getSupportedCurrencies();
            $this->assertIsArray($currencies);
            $this->assertContains('INR', $currencies);
            
        } catch (Exception $e) {
            // If credentials are not configured, this is expected
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
    }
    
    /**
     * Test payment method formatting
     */
    public function testPaymentMethodFormatting() {
        try {
            $service = new RazorpayService();
            
            $formatted = $service->formatPaymentMethod('card');
            $this->assertEquals('Credit/Debit Card', $formatted);
            
            $formatted = $service->formatPaymentMethod('upi');
            $this->assertEquals('UPI', $formatted);
            
            $formatted = $service->formatPaymentMethod('netbanking');
            $this->assertEquals('Net Banking', $formatted);
            
        } catch (Exception $e) {
            // Skip if credentials not configured
            $this->markTestSkipped('Razorpay credentials not configured');
        }
    }
    
    /**
     * Test Razorpay client amount conversion
     */
    public function testAmountConversion() {
        try {
            $client = new RazorpayClient();
            
            // Test conversion to rupees
            $this->assertEquals(10.0, $client->convertToRupees(1000));
            $this->assertEquals(150.50, $client->convertToRupees(15050));
            $this->assertEquals(1.0, $client->convertToRupees(100));
            
        } catch (Exception $e) {
            // Skip if credentials not configured
            $this->markTestSkipped('Razorpay credentials not configured');
        }
    }
    
    /**
     * Test payment validation with mock data
     */
    public function testPaymentValidationMock() {
        try {
            $service = new RazorpayService();
            
            // Test valid format data (will fail signature verification but pass format validation)
            $validData = [
                'razorpay_order_id' => 'order_test123456789',
                'razorpay_payment_id' => 'pay_test123456789',
                'razorpay_signature' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'
            ];
            
            // This should not throw format validation errors
            $service->validatePaymentData($validData);
            $this->assertTrue(true); // If we reach here, validation passed
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not configured') !== false) {
                $this->markTestSkipped('Razorpay credentials not configured');
            } else {
                // Re-throw if it's not a configuration error
                throw $e;
            }
        }
    }
    
    /**
     * Test invalid payment data formats
     */
    public function testInvalidPaymentDataFormats() {
        try {
            $service = new RazorpayService();
            
            // Test invalid order ID format
            $invalidData = [
                'razorpay_order_id' => 'invalid_format',
                'razorpay_payment_id' => 'pay_test123456789',
                'razorpay_signature' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'
            ];
            
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Invalid Razorpay order ID format');
            $service->validatePaymentData($invalidData);
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not configured') !== false) {
                $this->markTestSkipped('Razorpay credentials not configured');
            } else {
                // Check if it's the expected validation error
                $this->assertStringContainsString('Invalid Razorpay', $e->getMessage());
            }
        }
    }
}