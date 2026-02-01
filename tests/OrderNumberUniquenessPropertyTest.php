<?php
/**
 * Property Test for Order Number Uniqueness
 * 
 * **Validates: Requirements 6.2**
 * 
 * This test verifies Property 10: Order Number Uniqueness
 * For any sequence of order creation requests, all generated order numbers 
 * should be unique and follow the same format pattern.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Address.php';

use PHPUnit\Framework\TestCase;

class OrderNumberUniquenessPropertyTest extends TestCase {
    private $order;
    private $user;
    private $product;
    private $address;
    private $testUserId;
    private $testProductId;
    private $testAddressId;
    
    protected function setUp(): void {
        $this->order = new Order();
        $this->user = new User();
        $this->product = new Product();
        $this->address = new Address();
        
        // Create test user
        $userData = [
            'email' => 'test_order_' . uniqid() . '@example.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User'
        ];
        $this->testUserId = $this->user->create($userData);
        
        // Create test product
        $productData = [
            'name' => 'Test Product ' . uniqid(),
            'price' => 100.00,
            'stock_quantity' => 1000,
            'description' => 'Test product for order testing'
        ];
        $this->testProductId = $this->product->create($productData);
        
        // Create test address
        $addressData = [
            'user_id' => $this->testUserId,
            'address_line1' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'India'
        ];
        $this->testAddressId = $this->address->create($addressData);
    }
    
    protected function tearDown(): void {
        // Clean up test data
        if ($this->testUserId) {
            $this->user->deleteById($this->testUserId);
        }
        if ($this->testProductId) {
            $this->product->deleteById($this->testProductId);
        }
        if ($this->testAddressId) {
            $this->address->deleteById($this->testAddressId);
        }
        
        // Clean up any test orders
        $db = Database::getInstance();
        $db->executeQuery("DELETE FROM orders WHERE user_id = ?", [$this->testUserId]);
    }
    
    /**
     * Property Test: Order Number Uniqueness
     * 
     * **Validates: Requirements 6.2**
     * 
     * Tests that all generated order numbers are unique across multiple
     * concurrent order creation attempts and follow the correct format pattern.
     * 
     * @test
     */
    public function testOrderNumberUniquenessProperty() {
        $generatedNumbers = [];
        $iterations = 100; // Test with 100 order creations
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Create order data with random variations
                $orderData = $this->generateRandomOrderData();
                
                // Create order
                $order = $this->order->createOrder($orderData);
                
                // Verify order number format
                $this->assertOrderNumberFormat($order['order_number']);
                
                // Check uniqueness
                $this->assertNotContains(
                    $order['order_number'], 
                    $generatedNumbers,
                    "Order number {$order['order_number']} is not unique (iteration $i)"
                );
                
                // Store for uniqueness checking
                $generatedNumbers[] = $order['order_number'];
                
                // Verify order number is stored correctly in database
                $retrievedOrder = $this->order->getOrderByNumber($order['order_number']);
                $this->assertNotNull($retrievedOrder, "Order with number {$order['order_number']} not found in database");
                $this->assertEquals($order['id'], $retrievedOrder['id']);
                
            } catch (Exception $e) {
                $this->fail("Order creation failed at iteration $i: " . $e->getMessage());
            }
        }
        
        // Final verification: all numbers should be unique
        $uniqueNumbers = array_unique($generatedNumbers);
        $this->assertCount(
            $iterations, 
            $uniqueNumbers,
            "Expected $iterations unique order numbers, got " . count($uniqueNumbers)
        );
    }
    
    /**
     * Property Test: Order Number Format Consistency
     * 
     * **Validates: Requirements 6.2**
     * 
     * Tests that all order numbers follow the expected format pattern
     * regardless of when they are generated.
     * 
     * @test
     */
    public function testOrderNumberFormatConsistencyProperty() {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $orderData = $this->generateRandomOrderData();
            $order = $this->order->createOrder($orderData);
            
            // Test format consistency
            $this->assertOrderNumberFormat($order['order_number']);
            
            // Test that format remains consistent across different dates
            // (This tests the date component of the order number)
            $expectedDatePrefix = 'RC' . date('Ymd');
            $this->assertStringStartsWith(
                $expectedDatePrefix,
                $order['order_number'],
                "Order number should start with current date prefix: $expectedDatePrefix"
            );
        }
    }
    
    /**
     * Property Test: Concurrent Order Number Generation
     * 
     * **Validates: Requirements 6.2**
     * 
     * Tests that order numbers remain unique even when generated
     * in rapid succession (simulating concurrent requests).
     * 
     * @test
     */
    public function testConcurrentOrderNumberGenerationProperty() {
        $generatedNumbers = [];
        $batchSize = 20;
        $batches = 5;
        
        for ($batch = 0; $batch < $batches; $batch++) {
            $batchNumbers = [];
            
            // Generate multiple orders rapidly
            for ($i = 0; $i < $batchSize; $i++) {
                $orderData = $this->generateRandomOrderData();
                $order = $this->order->createOrder($orderData);
                
                $orderNumber = $order['order_number'];
                
                // Check format
                $this->assertOrderNumberFormat($orderNumber);
                
                // Check uniqueness within batch
                $this->assertNotContains(
                    $orderNumber,
                    $batchNumbers,
                    "Duplicate order number in batch $batch: $orderNumber"
                );
                
                // Check uniqueness across all batches
                $this->assertNotContains(
                    $orderNumber,
                    $generatedNumbers,
                    "Duplicate order number across batches: $orderNumber"
                );
                
                $batchNumbers[] = $orderNumber;
                $generatedNumbers[] = $orderNumber;
            }
            
            // Small delay between batches to test time-based uniqueness
            usleep(100000); // 0.1 second
        }
        
        // Verify total uniqueness
        $totalExpected = $batchSize * $batches;
        $this->assertCount($totalExpected, array_unique($generatedNumbers));
    }
    
    /**
     * Property Test: Order Number Database Uniqueness Constraint
     * 
     * **Validates: Requirements 6.2**
     * 
     * Tests that the database enforces uniqueness constraints on order numbers.
     * 
     * @test
     */
    public function testOrderNumberDatabaseUniquenessProperty() {
        // Create first order
        $orderData1 = $this->generateRandomOrderData();
        $order1 = $this->order->createOrder($orderData1);
        
        // Attempt to manually insert duplicate order number (should fail)
        $db = Database::getInstance();
        
        try {
            $duplicateOrderData = [
                'user_id' => $this->testUserId,
                'order_number' => $order1['order_number'], // Same order number
                'status' => 'pending',
                'payment_method' => 'cod',
                'subtotal' => 100.00,
                'tax_amount' => 18.00,
                'shipping_amount' => 0.00,
                'discount_amount' => 0.00,
                'total_amount' => 118.00,
                'currency' => 'INR'
            ];
            
            $sql = "INSERT INTO orders (user_id, order_number, status, payment_method, subtotal, tax_amount, shipping_amount, discount_amount, total_amount, currency, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $db->executeQuery($sql, [
                $duplicateOrderData['user_id'],
                $duplicateOrderData['order_number'],
                $duplicateOrderData['status'],
                $duplicateOrderData['payment_method'],
                $duplicateOrderData['subtotal'],
                $duplicateOrderData['tax_amount'],
                $duplicateOrderData['shipping_amount'],
                $duplicateOrderData['discount_amount'],
                $duplicateOrderData['total_amount'],
                $duplicateOrderData['currency']
            ]);
            
            $this->fail('Database should have prevented duplicate order number insertion');
            
        } catch (Exception $e) {
            // This is expected - database should reject duplicate order numbers
            $this->assertStringContains('Duplicate entry', $e->getMessage());
        }
    }
    
    /**
     * Generate random order data for testing
     * 
     * @return array Random order data
     */
    private function generateRandomOrderData() {
        $quantities = [1, 2, 3, 5, 10];
        $paymentMethods = ['cod', 'online', 'razorpay'];
        
        return [
            'user_id' => $this->testUserId,
            'payment_method' => $paymentMethods[array_rand($paymentMethods)],
            'shipping_address_id' => $this->testAddressId,
            'items' => [
                [
                    'product_id' => $this->testProductId,
                    'quantity' => $quantities[array_rand($quantities)],
                    'unit_price' => 100.00,
                    'product_name' => 'Test Product',
                    'product_sku' => 'TEST-SKU-' . uniqid()
                ]
            ],
            'currency' => 'INR',
            'notes' => 'Test order ' . uniqid()
        ];
    }
    
    /**
     * Assert that order number follows the expected format
     * 
     * @param string $orderNumber Order number to validate
     */
    private function assertOrderNumberFormat($orderNumber) {
        // Expected format: RC + YYYYMMDD + 4-digit number
        // Example: RC202312151234
        
        $this->assertIsString($orderNumber, 'Order number should be a string');
        $this->assertNotEmpty($orderNumber, 'Order number should not be empty');
        
        // Check minimum length (RC + 8 digits for date + 4 digits for sequence)
        $this->assertGreaterThanOrEqual(14, strlen($orderNumber), 'Order number should be at least 14 characters');
        
        // Check prefix
        $this->assertStringStartsWith('RC', $orderNumber, 'Order number should start with "RC"');
        
        // Check date format (YYYYMMDD after RC)
        $datepart = substr($orderNumber, 2, 8);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $datepart, 'Date part should be 8 digits');
        
        // Validate date is reasonable (not in future, not too old)
        $orderDate = DateTime::createFromFormat('Ymd', $datepart);
        $this->assertNotFalse($orderDate, 'Date part should be a valid date');
        
        $today = new DateTime();
        $this->assertLessThanOrEqual($today, $orderDate, 'Order date should not be in the future');
        
        // Check that remaining part is numeric
        $remainingPart = substr($orderNumber, 10);
        $this->assertMatchesRegularExpression('/^\d+$/', $remainingPart, 'Remaining part should be numeric');
    }
}