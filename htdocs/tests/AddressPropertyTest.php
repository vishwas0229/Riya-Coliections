<?php
/**
 * Address Model Property-Based Tests
 * 
 * Property-based tests for the Address model that verify universal properties
 * hold across all valid inputs using random data generation.
 */

require_once __DIR__ . '/../models/Address.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class AddressPropertyTest extends TestCase {
    private $address;
    private $db;
    private $testUserIds = [];
    
    protected function setUp(): void {
        $this->address = new Address();
        $this->db = Database::getInstance();
        
        // Clean up test data
        $this->cleanupTestData();
        
        // Create test users
        $this->createTestUsers();
    }
    
    protected function tearDown(): void {
        $this->cleanupTestData();
    }
    
    /**
     * Property 1: Address CRUD Operations Consistency
     * **Validates: Requirements 6.1**
     * 
     * For any valid address data, CRUD operations should work consistently:
     * - Created address should be retrievable with same data
     * - Updated address should reflect changes
     * - Deleted address should not be retrievable
     * 
     * @test
     */
    public function testAddressCRUDOperationsConsistency() {
        for ($i = 0; $i < 50; $i++) {
            $addressData = $this->generateRandomAddressData();
            
            // CREATE: Create address
            $created = $this->address->createAddress($addressData);
            $this->assertIsArray($created);
            $this->assertArrayHasKey('id', $created);
            
            // READ: Retrieve created address
            $retrieved = $this->address->getAddressById($created['id']);
            $this->assertIsArray($retrieved);
            $this->assertEquals($created['id'], $retrieved['id']);
            $this->assertEquals($addressData['first_name'], $retrieved['first_name']);
            $this->assertEquals($addressData['city'], $retrieved['city']);
            
            // UPDATE: Update address
            $updateData = [
                'city' => $this->generateRandomCity(),
                'postal_code' => $this->generateRandomPostalCode()
            ];
            
            $updated = $this->address->updateAddress($created['id'], $updateData);
            $this->assertIsArray($updated);
            $this->assertEquals($updateData['city'], $updated['city']);
            $this->assertEquals($updateData['postal_code'], $updated['postal_code']);
            
            // DELETE: Delete address
            $deleted = $this->address->deleteAddress($created['id'], $addressData['user_id']);
            $this->assertTrue($deleted);
            
            // Verify deletion
            $notFound = $this->address->getAddressById($created['id']);
            $this->assertNull($notFound);
        }
    }
    
    /**
     * Property 2: Address Validation Consistency
     * **Validates: Requirements 6.1**
     * 
     * For any address data, validation should be consistent:
     * - Valid data should always pass validation
     * - Invalid data should always fail validation with appropriate errors
     * 
     * @test
     */
    public function testAddressValidationConsistency() {
        for ($i = 0; $i < 50; $i++) {
            // Test valid data
            $validData = $this->generateRandomAddressData();
            
            try {
                $result = $this->address->createAddress($validData);
                $this->assertIsArray($result);
                $this->assertArrayHasKey('id', $result);
                
                // Clean up
                $this->address->deleteAddress($result['id'], $validData['user_id']);
            } catch (Exception $e) {
                $this->fail("Valid address data should not throw exception: " . $e->getMessage());
            }
            
            // Test invalid data (missing required fields)
            $invalidData = $validData;
            unset($invalidData['first_name']); // Remove required field
            
            $this->expectException(Exception::class);
            try {
                $this->address->createAddress($invalidData);
                $this->fail("Invalid address data should throw exception");
            } catch (Exception $e) {
                $this->assertStringContainsString('Validation failed', $e->getMessage());
            }
        }
    }
    
    /**
     * Property 3: Default Address Management Consistency
     * **Validates: Requirements 6.1**
     * 
     * For any user, default address management should be consistent:
     * - Only one address per user can be default
     * - Setting a new default should clear the previous default
     * 
     * @test
     */
    public function testDefaultAddressManagementConsistency() {
        for ($i = 0; $i < 30; $i++) {
            $userId = $this->getRandomTestUserId();
            
            // Create multiple addresses for the user
            $addresses = [];
            $addressCount = rand(2, 5);
            
            for ($j = 0; $j < $addressCount; $j++) {
                $addressData = $this->generateRandomAddressData(['user_id' => $userId]);
                $addressData['is_default'] = ($j === 0); // First one is default
                
                $created = $this->address->createAddress($addressData);
                $addresses[] = $created;
            }
            
            // Verify only one default exists
            $defaultAddresses = $this->address->getAddressesByUser($userId, ['is_default' => true]);
            $this->assertCount(1, $defaultAddresses, "User should have exactly one default address");
            
            // Set a different address as default
            $newDefaultIndex = rand(1, count($addresses) - 1);
            $newDefault = $this->address->setDefaultAddress($addresses[$newDefaultIndex]['id'], $userId);
            $this->assertTrue($newDefault['is_default']);
            
            // Verify only the new address is default
            $allAddresses = $this->address->getAddressesByUser($userId);
            $defaultCount = 0;
            foreach ($allAddresses as $addr) {
                if ($addr['is_default']) {
                    $defaultCount++;
                    $this->assertEquals($addresses[$newDefaultIndex]['id'], $addr['id']);
                }
            }
            $this->assertEquals(1, $defaultCount, "User should have exactly one default address after update");
            
            // Clean up
            foreach ($addresses as $addr) {
                try {
                    $this->address->deleteAddress($addr['id'], $userId);
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }
    }
    
    /**
     * Property 4: Address Formatting Consistency
     * **Validates: Requirements 6.1**
     * 
     * For any address data, formatting should be consistent:
     * - Names should be properly capitalized
     * - Postal codes should be validated for country
     * - Phone numbers should be formatted consistently
     * 
     * @test
     */
    public function testAddressFormattingConsistency() {
        for ($i = 0; $i < 50; $i++) {
            $addressData = $this->generateRandomAddressData();
            
            // Use various case formats for names
            $addressData['first_name'] = $this->randomizeCase($addressData['first_name']);
            $addressData['last_name'] = $this->randomizeCase($addressData['last_name']);
            $addressData['city'] = $this->randomizeCase($addressData['city']);
            $addressData['state'] = $this->randomizeCase($addressData['state']);
            
            $created = $this->address->createAddress($addressData);
            
            // Verify proper capitalization
            $this->assertEquals(ucwords(strtolower($addressData['first_name'])), $created['first_name']);
            $this->assertEquals(ucwords(strtolower($addressData['last_name'])), $created['last_name']);
            $this->assertEquals(ucwords(strtolower($addressData['city'])), $created['city']);
            $this->assertEquals(ucwords(strtolower($addressData['state'])), $created['state']);
            
            // Verify formatted address contains all components
            $this->assertStringContainsString($created['first_name'], $created['formatted_address']);
            $this->assertStringContainsString($created['address_line1'], $created['formatted_address']);
            $this->assertStringContainsString($created['city'], $created['formatted_address']);
            
            // Clean up
            $this->address->deleteAddress($created['id'], $addressData['user_id']);
        }
    }
    
    /**
     * Property 5: Postal Code Validation by Country
     * **Validates: Requirements 6.1**
     * 
     * For any postal code and country combination, validation should be consistent
     * with the country's postal code format rules.
     * 
     * @test
     */
    public function testPostalCodeValidationByCountry() {
        $testCases = [
            'India' => ['400001', '110001', '560001'], // Valid Indian postal codes
            'USA' => ['12345', '90210', '12345-6789'], // Valid US ZIP codes
            'UK' => ['SW1A 1AA', 'M1 1AA', 'B33 8TH'], // Valid UK postal codes
        ];
        
        foreach ($testCases as $country => $validCodes) {
            foreach ($validCodes as $code) {
                $this->assertTrue(
                    $this->address->validatePostalCode($code, $country),
                    "Postal code {$code} should be valid for {$country}"
                );
                
                // Test with address creation
                $addressData = $this->generateRandomAddressData([
                    'country' => $country,
                    'postal_code' => $code
                ]);
                
                try {
                    $created = $this->address->createAddress($addressData);
                    $this->assertEquals($code, $created['postal_code']);
                    
                    // Clean up
                    $this->address->deleteAddress($created['id'], $addressData['user_id']);
                } catch (Exception $e) {
                    $this->fail("Valid postal code {$code} for {$country} should not fail: " . $e->getMessage());
                }
            }
        }
        
        // Test invalid postal codes
        $invalidCases = [
            'India' => ['12345', 'ABC123', '40001'], // Invalid for India
            'USA' => ['400001', 'ABCDE', '1234'], // Invalid for USA
            'UK' => ['400001', '12345', 'INVALID'], // Invalid for UK
        ];
        
        foreach ($invalidCases as $country => $invalidCodes) {
            foreach ($invalidCodes as $code) {
                $this->assertFalse(
                    $this->address->validatePostalCode($code, $country),
                    "Postal code {$code} should be invalid for {$country}"
                );
            }
        }
    }
    
    /**
     * Property 6: Phone Number Validation Consistency
     * **Validates: Requirements 6.1**
     * 
     * For any phone number format, validation should be consistent
     * and formatting should produce valid results.
     * 
     * @test
     */
    public function testPhoneNumberValidationConsistency() {
        $validPhoneNumbers = [
            '9876543210',
            '8123456789',
            '+919876543210',
            '02212345678',
            '01123456789'
        ];
        
        foreach ($validPhoneNumbers as $phone) {
            $this->assertTrue(
                $this->address->validatePhoneNumber($phone),
                "Phone number {$phone} should be valid"
            );
            
            // Test with address creation
            $addressData = $this->generateRandomAddressData(['phone' => $phone]);
            
            try {
                $created = $this->address->createAddress($addressData);
                $this->assertNotEmpty($created['phone']);
                
                // Clean up
                $this->address->deleteAddress($created['id'], $addressData['user_id']);
            } catch (Exception $e) {
                $this->fail("Valid phone number {$phone} should not fail: " . $e->getMessage());
            }
        }
        
        $invalidPhoneNumbers = [
            '123456',      // Too short
            '5123456789',  // Invalid starting digit
            '12345678901234567', // Too long
            'abcdefghij'   // Non-numeric
        ];
        
        foreach ($invalidPhoneNumbers as $phone) {
            $this->assertFalse(
                $this->address->validatePhoneNumber($phone),
                "Phone number {$phone} should be invalid"
            );
        }
    }
    
    /**
     * Property 7: User Address Isolation
     * **Validates: Requirements 6.1**
     * 
     * For any user, their addresses should be isolated from other users:
     * - Users can only see their own addresses
     * - Users cannot modify other users' addresses
     * 
     * @test
     */
    public function testUserAddressIsolation() {
        for ($i = 0; $i < 20; $i++) {
            $user1Id = $this->getRandomTestUserId();
            $user2Id = $this->getRandomTestUserId();
            
            // Ensure different users
            while ($user1Id === $user2Id) {
                $user2Id = $this->getRandomTestUserId();
            }
            
            // Create addresses for both users
            $user1Address = $this->address->createAddress($this->generateRandomAddressData(['user_id' => $user1Id]));
            $user2Address = $this->address->createAddress($this->generateRandomAddressData(['user_id' => $user2Id]));
            
            // User 1 should only see their own addresses
            $user1Addresses = $this->address->getAddressesByUser($user1Id);
            $user1AddressIds = array_column($user1Addresses, 'id');
            $this->assertContains($user1Address['id'], $user1AddressIds);
            $this->assertNotContains($user2Address['id'], $user1AddressIds);
            
            // User 2 should only see their own addresses
            $user2Addresses = $this->address->getAddressesByUser($user2Id);
            $user2AddressIds = array_column($user2Addresses, 'id');
            $this->assertContains($user2Address['id'], $user2AddressIds);
            $this->assertNotContains($user1Address['id'], $user2AddressIds);
            
            // User 1 should not be able to delete User 2's address
            try {
                $this->address->deleteAddress($user2Address['id'], $user1Id);
                $this->fail("User should not be able to delete another user's address");
            } catch (Exception $e) {
                $this->assertStringContainsString('Unauthorized', $e->getMessage());
            }
            
            // Clean up
            $this->address->deleteAddress($user1Address['id'], $user1Id);
            $this->address->deleteAddress($user2Address['id'], $user2Id);
        }
    }
    
    /**
     * Generate random address data
     */
    private function generateRandomAddressData($overrides = []) {
        $types = ['home', 'work', 'other'];
        $firstNames = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Lisa', 'Tom', 'Anna'];
        $lastNames = ['Smith', 'Johnson', 'Brown', 'Davis', 'Wilson', 'Miller', 'Taylor', 'Anderson'];
        $cities = ['Mumbai', 'Delhi', 'Bangalore', 'Chennai', 'Kolkata', 'Hyderabad', 'Pune', 'Ahmedabad'];
        $states = ['Maharashtra', 'Delhi', 'Karnataka', 'Tamil Nadu', 'West Bengal', 'Telangana', 'Gujarat', 'Rajasthan'];
        
        $defaults = [
            'user_id' => $this->getRandomTestUserId(),
            'type' => $types[array_rand($types)],
            'first_name' => $firstNames[array_rand($firstNames)],
            'last_name' => $lastNames[array_rand($lastNames)],
            'address_line1' => rand(1, 999) . ' ' . ['Main St', 'Oak Ave', 'Park Rd', 'First St', 'Second Ave'][array_rand(['Main St', 'Oak Ave', 'Park Rd', 'First St', 'Second Ave'])],
            'address_line2' => rand(1, 10) > 7 ? 'Apt ' . rand(1, 50) : null,
            'city' => $cities[array_rand($cities)],
            'state' => $states[array_rand($states)],
            'postal_code' => $this->generateRandomPostalCode(),
            'country' => 'India',
            'phone' => $this->generateRandomPhoneNumber(),
            'is_default' => rand(1, 10) > 8 // 20% chance of being default
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Generate random postal code
     */
    private function generateRandomPostalCode() {
        return str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate random phone number
     */
    private function generateRandomPhoneNumber() {
        $startDigits = [6, 7, 8, 9];
        return $startDigits[array_rand($startDigits)] . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate random city
     */
    private function generateRandomCity() {
        $cities = ['Mumbai', 'Delhi', 'Bangalore', 'Chennai', 'Kolkata', 'Hyderabad', 'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow'];
        return $cities[array_rand($cities)];
    }
    
    /**
     * Randomize case of string
     */
    private function randomizeCase($string) {
        $cases = [
            strtolower($string),
            strtoupper($string),
            ucfirst(strtolower($string)),
            ucwords(strtolower($string))
        ];
        
        return $cases[array_rand($cases)];
    }
    
    /**
     * Get random test user ID
     */
    private function getRandomTestUserId() {
        return $this->testUserIds[array_rand($this->testUserIds)];
    }
    
    /**
     * Create test users
     */
    private function createTestUsers() {
        for ($i = 1; $i <= 5; $i++) {
            $userId = 1000 + $i;
            $this->testUserIds[] = $userId;
            
            try {
                $sql = "INSERT IGNORE INTO users (id, email, password_hash, first_name, last_name, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                
                $this->db->executeQuery($sql, [
                    $userId,
                    "test{$i}@example.com",
                    password_hash('password', PASSWORD_BCRYPT),
                    "Test{$i}",
                    'User'
                ]);
            } catch (Exception $e) {
                // User might already exist, ignore
            }
        }
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        try {
            if (!empty($this->testUserIds)) {
                $placeholders = str_repeat('?,', count($this->testUserIds) - 1) . '?';
                $this->db->executeQuery("DELETE FROM addresses WHERE user_id IN ({$placeholders})", $this->testUserIds);
            }
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}