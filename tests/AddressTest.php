<?php
/**
 * Address Model Unit Tests
 * 
 * Comprehensive unit tests for the Address model covering CRUD operations,
 * validation, formatting, and default address management.
 */

require_once __DIR__ . '/../models/Address.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase {
    private $address;
    private $db;
    private $testUserId = 1;
    
    protected function setUp(): void {
        $this->address = new Address();
        $this->db = Database::getInstance();
        
        // Clean up test data
        $this->cleanupTestData();
        
        // Create test user if not exists
        $this->createTestUser();
    }
    
    protected function tearDown(): void {
        $this->cleanupTestData();
    }
    
    /**
     * Test address creation with valid data
     */
    public function testCreateAddressWithValidData() {
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'home',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main Street',
            'address_line2' => 'Apt 4B',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'country' => 'India',
            'phone' => '9876543210',
            'is_default' => true
        ];
        
        $result = $this->address->createAddress($addressData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($this->testUserId, $result['user_id']);
        $this->assertEquals('home', $result['type']);
        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Doe', $result['last_name']);
        $this->assertEquals('123 Main Street', $result['address_line1']);
        $this->assertEquals('Mumbai', $result['city']);
        $this->assertEquals('400001', $result['postal_code']);
        $this->assertTrue($result['is_default']);
        $this->assertArrayHasKey('formatted_address', $result);
    }
    
    /**
     * Test address creation with minimal required data
     */
    public function testCreateAddressWithMinimalData() {
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'work',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'address_line1' => '456 Business Ave',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'postal_code' => '110001'
        ];
        
        $result = $this->address->createAddress($addressData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('India', $result['country']); // Default country
        $this->assertFalse($result['is_default']); // Default is_default
    }
    
    /**
     * Test address creation validation failures
     */
    public function testCreateAddressValidationFailures() {
        // Missing required fields
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Validation failed');
        
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'home'
            // Missing required fields
        ];
        
        $this->address->createAddress($addressData);
    }
    
    /**
     * Test invalid address type
     */
    public function testCreateAddressWithInvalidType() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Type must be one of: home,work,other');
        
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'invalid_type',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001'
        ];
        
        $this->address->createAddress($addressData);
    }
    
    /**
     * Test invalid postal code
     */
    public function testCreateAddressWithInvalidPostalCode() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid postal code format');
        
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'home',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => 'invalid',
            'country' => 'India'
        ];
        
        $this->address->createAddress($addressData);
    }
    
    /**
     * Test invalid phone number
     */
    public function testCreateAddressWithInvalidPhone() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid phone number format');
        
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'home',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'phone' => '123' // Too short
        ];
        
        $this->address->createAddress($addressData);
    }
    
    /**
     * Test getting address by ID
     */
    public function testGetAddressById() {
        // Create test address
        $addressData = $this->getValidAddressData();
        $created = $this->address->createAddress($addressData);
        
        // Get address by ID
        $result = $this->address->getAddressById($created['id']);
        
        $this->assertIsArray($result);
        $this->assertEquals($created['id'], $result['id']);
        $this->assertEquals($addressData['first_name'], $result['first_name']);
        $this->assertEquals($addressData['city'], $result['city']);
    }
    
    /**
     * Test getting non-existent address
     */
    public function testGetNonExistentAddress() {
        $result = $this->address->getAddressById(99999);
        $this->assertNull($result);
    }
    
    /**
     * Test getting addresses by user
     */
    public function testGetAddressesByUser() {
        // Create multiple test addresses
        $address1 = $this->getValidAddressData(['type' => 'home', 'is_default' => true]);
        $address2 = $this->getValidAddressData(['type' => 'work', 'is_default' => false]);
        
        $this->address->createAddress($address1);
        $this->address->createAddress($address2);
        
        // Get all addresses for user
        $result = $this->address->getAddressesByUser($this->testUserId);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Check ordering (default first)
        $this->assertTrue($result[0]['is_default']);
        $this->assertFalse($result[1]['is_default']);
    }
    
    /**
     * Test getting addresses with type filter
     */
    public function testGetAddressesByUserWithTypeFilter() {
        // Create addresses of different types
        $homeAddress = $this->getValidAddressData(['type' => 'home']);
        $workAddress = $this->getValidAddressData(['type' => 'work']);
        
        $this->address->createAddress($homeAddress);
        $this->address->createAddress($workAddress);
        
        // Get only home addresses
        $result = $this->address->getAddressesByUser($this->testUserId, ['type' => 'home']);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('home', $result[0]['type']);
    }
    
    /**
     * Test getting default address
     */
    public function testGetDefaultAddress() {
        // Create non-default address
        $address1 = $this->getValidAddressData(['is_default' => false]);
        $this->address->createAddress($address1);
        
        // Create default address
        $address2 = $this->getValidAddressData(['is_default' => true, 'type' => 'work']);
        $created = $this->address->createAddress($address2);
        
        // Get default address
        $result = $this->address->getDefaultAddress($this->testUserId);
        
        $this->assertIsArray($result);
        $this->assertEquals($created['id'], $result['id']);
        $this->assertTrue($result['is_default']);
        $this->assertEquals('work', $result['type']);
    }
    
    /**
     * Test getting default address when none exists
     */
    public function testGetDefaultAddressWhenNoneExists() {
        $result = $this->address->getDefaultAddress($this->testUserId);
        $this->assertNull($result);
    }
    
    /**
     * Test updating address
     */
    public function testUpdateAddress() {
        // Create test address
        $addressData = $this->getValidAddressData();
        $created = $this->address->createAddress($addressData);
        
        // Update address
        $updateData = [
            'first_name' => 'Updated',
            'city' => 'Updated City',
            'postal_code' => '400002'
        ];
        
        $result = $this->address->updateAddress($created['id'], $updateData);
        
        $this->assertIsArray($result);
        $this->assertEquals('Updated', $result['first_name']);
        $this->assertEquals('Updated City', $result['city']);
        $this->assertEquals('400002', $result['postal_code']);
        $this->assertEquals($addressData['last_name'], $result['last_name']); // Unchanged
    }
    
    /**
     * Test updating non-existent address
     */
    public function testUpdateNonExistentAddress() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Address not found');
        
        $this->address->updateAddress(99999, ['city' => 'New City']);
    }
    
    /**
     * Test deleting address
     */
    public function testDeleteAddress() {
        // Create test address
        $addressData = $this->getValidAddressData();
        $created = $this->address->createAddress($addressData);
        
        // Delete address
        $result = $this->address->deleteAddress($created['id'], $this->testUserId);
        
        $this->assertTrue($result);
        
        // Verify address is deleted
        $deleted = $this->address->getAddressById($created['id']);
        $this->assertNull($deleted);
    }
    
    /**
     * Test deleting address with wrong user ID
     */
    public function testDeleteAddressUnauthorized() {
        // Create test address
        $addressData = $this->getValidAddressData();
        $created = $this->address->createAddress($addressData);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unauthorized to delete this address');
        
        // Try to delete with wrong user ID
        $this->address->deleteAddress($created['id'], 999);
    }
    
    /**
     * Test deleting non-existent address
     */
    public function testDeleteNonExistentAddress() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Address not found');
        
        $this->address->deleteAddress(99999, $this->testUserId);
    }
    
    /**
     * Test setting default address
     */
    public function testSetDefaultAddress() {
        // Create two addresses
        $address1 = $this->getValidAddressData(['is_default' => true]);
        $address2 = $this->getValidAddressData(['type' => 'work', 'is_default' => false]);
        
        $created1 = $this->address->createAddress($address1);
        $created2 = $this->address->createAddress($address2);
        
        // Set second address as default
        $result = $this->address->setDefaultAddress($created2['id'], $this->testUserId);
        
        $this->assertTrue($result['is_default']);
        
        // Verify first address is no longer default
        $updated1 = $this->address->getAddressById($created1['id']);
        $this->assertFalse($updated1['is_default']);
    }
    
    /**
     * Test default address management during creation
     */
    public function testDefaultAddressManagementDuringCreation() {
        // Create first default address
        $address1 = $this->getValidAddressData(['is_default' => true]);
        $created1 = $this->address->createAddress($address1);
        $this->assertTrue($created1['is_default']);
        
        // Create second default address
        $address2 = $this->getValidAddressData(['type' => 'work', 'is_default' => true]);
        $created2 = $this->address->createAddress($address2);
        $this->assertTrue($created2['is_default']);
        
        // Verify first address is no longer default
        $updated1 = $this->address->getAddressById($created1['id']);
        $this->assertFalse($updated1['is_default']);
    }
    
    /**
     * Test formatted address generation
     */
    public function testFormattedAddress() {
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'home',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main Street',
            'address_line2' => 'Apt 4B',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'country' => 'India',
            'phone' => '9876543210'
        ];
        
        $created = $this->address->createAddress($addressData);
        
        $this->assertArrayHasKey('formatted_address', $created);
        $this->assertStringContainsString('John Doe', $created['formatted_address']);
        $this->assertStringContainsString('123 Main Street', $created['formatted_address']);
        $this->assertStringContainsString('Mumbai, Maharashtra, 400001', $created['formatted_address']);
        $this->assertStringContainsString('Phone: +919876543210', $created['formatted_address']);
    }
    
    /**
     * Test postal code validation for different countries
     */
    public function testPostalCodeValidation() {
        // Test Indian postal code
        $this->assertTrue($this->address->validatePostalCode('400001', 'India'));
        $this->assertFalse($this->address->validatePostalCode('40001', 'India')); // Too short
        
        // Test US ZIP code
        $this->assertTrue($this->address->validatePostalCode('12345', 'USA'));
        $this->assertTrue($this->address->validatePostalCode('12345-6789', 'USA'));
        $this->assertFalse($this->address->validatePostalCode('1234', 'USA')); // Too short
        
        // Test UK postal code
        $this->assertTrue($this->address->validatePostalCode('SW1A 1AA', 'UK'));
        $this->assertTrue($this->address->validatePostalCode('M1 1AA', 'UK'));
        $this->assertFalse($this->address->validatePostalCode('123456', 'UK')); // Wrong format
    }
    
    /**
     * Test phone number validation
     */
    public function testPhoneNumberValidation() {
        // Valid Indian mobile numbers
        $this->assertTrue($this->address->validatePhoneNumber('9876543210'));
        $this->assertTrue($this->address->validatePhoneNumber('8123456789'));
        $this->assertTrue($this->address->validatePhoneNumber('+919876543210'));
        
        // Valid landline numbers
        $this->assertTrue($this->address->validatePhoneNumber('02212345678'));
        $this->assertTrue($this->address->validatePhoneNumber('01123456789'));
        
        // Invalid numbers
        $this->assertFalse($this->address->validatePhoneNumber('123456')); // Too short
        $this->assertFalse($this->address->validatePhoneNumber('5123456789')); // Invalid starting digit
        $this->assertFalse($this->address->validatePhoneNumber('12345678901234567')); // Too long
    }
    
    /**
     * Test data formatting
     */
    public function testDataFormatting() {
        $addressData = [
            'user_id' => $this->testUserId,
            'type' => 'home',
            'first_name' => 'john',
            'last_name' => 'DOE',
            'address_line1' => '123 Main Street',
            'city' => 'mumbai',
            'state' => 'MAHARASHTRA',
            'postal_code' => '400001',
            'country' => 'india',
            'phone' => '9876543210'
        ];
        
        $created = $this->address->createAddress($addressData);
        
        // Check name formatting
        $this->assertEquals('John', $created['first_name']);
        $this->assertEquals('Doe', $created['last_name']);
        
        // Check city/state formatting
        $this->assertEquals('Mumbai', $created['city']);
        $this->assertEquals('Maharashtra', $created['state']);
        
        // Check country formatting
        $this->assertEquals('India', $created['country']);
        
        // Check phone formatting
        $this->assertEquals('+919876543210', $created['phone']);
    }
    
    /**
     * Helper method to get valid address data
     */
    private function getValidAddressData($overrides = []) {
        $defaults = [
            'user_id' => $this->testUserId,
            'type' => 'home',
            'first_name' => 'Test',
            'last_name' => 'User',
            'address_line1' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '400001',
            'country' => 'India',
            'phone' => '9876543210',
            'is_default' => false
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create test user
     */
    private function createTestUser() {
        try {
            $sql = "INSERT IGNORE INTO users (id, email, password_hash, first_name, last_name, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            
            $this->db->executeQuery($sql, [
                $this->testUserId,
                'test@example.com',
                password_hash('password', PASSWORD_BCRYPT),
                'Test',
                'User'
            ]);
        } catch (Exception $e) {
            // User might already exist, ignore
        }
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        try {
            $this->db->executeQuery("DELETE FROM addresses WHERE user_id = ?", [$this->testUserId]);
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}