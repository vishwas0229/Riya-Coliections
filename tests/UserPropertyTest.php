<?php
/**
 * User Model Property-Based Tests
 * 
 * Property-based tests that verify universal properties of the User model
 * across many randomly generated inputs to ensure robustness and correctness.
 * 
 * Requirements: 3.1, 3.2, 16.1
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/bootstrap.php';

class UserPropertyTest {
    private $user;
    private $db;
    private $testUsers = [];
    
    public function setUp() {
        $this->user = new User();
        $this->db = Database::getInstance();
        
        // Clean up any existing test data
        $this->cleanupTestData();
    }
    
    public function tearDown() {
        // Clean up test data
        $this->cleanupTestData();
    }
    
    /**
     * Property: Email uniqueness constraint
     * **Validates: Requirements 3.1, 16.1**
     * 
     * For any valid email, only one user should be able to register with that email.
     * Subsequent attempts should fail with a conflict error.
     */
    public function testEmailUniquenessProperty() {
        echo "Testing email uniqueness property...\n";
        
        for ($i = 0; $i < 50; $i++) {
            $email = $this->generateRandomEmail();
            $userData1 = $this->generateValidUserData($email);
            $userData2 = $this->generateValidUserData($email); // Same email
            
            try {
                // First user should be created successfully
                $user1 = $this->user->createUser($userData1);
                $this->testUsers[] = $user1['id'];
                
                assert($user1['email'] === $email, "First user should have the correct email");
                
                // Second user with same email should fail
                try {
                    $user2 = $this->user->createUser($userData2);
                    $this->testUsers[] = $user2['id']; // In case it somehow succeeds
                    assert(false, "Second user with same email should not be created");
                } catch (Exception $e) {
                    assert($e->getCode() === 409, "Should return 409 conflict for duplicate email");
                    assert(strpos($e->getMessage(), 'already exists') !== false, "Error should mention email already exists");
                }
                
            } catch (Exception $e) {
                echo "Unexpected error in iteration $i: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Email uniqueness property verified across 50 iterations\n";
    }
    
    /**
     * Property: Password security validation
     * **Validates: Requirements 3.2, 16.1**
     * 
     * For any password that doesn't meet security requirements, user creation should fail.
     * For any password that meets requirements, user creation should succeed.
     */
    public function testPasswordSecurityProperty() {
        echo "Testing password security property...\n";
        
        // Test weak passwords (should all fail)
        $weakPasswords = [
            'short',           // Too short
            'nouppercase123!', // No uppercase
            'NOLOWERCASE123!', // No lowercase
            'NoNumbers!',      // No numbers
            'NoSpecialChars123', // No special characters
            '12345678',        // Only numbers
            'abcdefgh',        // Only lowercase
            'ABCDEFGH',        // Only uppercase
            '!@#$%^&*',        // Only special characters
        ];
        
        foreach ($weakPasswords as $weakPassword) {
            $userData = $this->generateValidUserData();
            $userData['password'] = $weakPassword;
            
            try {
                $user = $this->user->createUser($userData);
                $this->testUsers[] = $user['id']; // Clean up if somehow created
                assert(false, "Weak password '$weakPassword' should not be accepted");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Weak password should return 400 validation error");
                assert(strpos($e->getMessage(), 'Password must') !== false, "Error should mention password requirements");
            }
        }
        
        // Test strong passwords (should all succeed)
        for ($i = 0; $i < 20; $i++) {
            $strongPassword = $this->generateStrongPassword();
            $userData = $this->generateValidUserData();
            $userData['password'] = $strongPassword;
            
            try {
                $user = $this->user->createUser($userData);
                $this->testUsers[] = $user['id'];
                
                assert(isset($user['id']), "Strong password should allow user creation");
                assert(!isset($user['password_hash']), "Password hash should not be in response");
                
            } catch (Exception $e) {
                echo "Strong password '$strongPassword' failed: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Password security property verified across multiple weak and strong passwords\n";
    }
    
    /**
     * Property: Data sanitization and validation
     * **Validates: Requirements 16.1**
     * 
     * For any user input, the system should sanitize and validate it properly.
     * Malicious inputs should be rejected or sanitized.
     */
    public function testDataSanitizationProperty() {
        echo "Testing data sanitization property...\n";
        
        for ($i = 0; $i < 30; $i++) {
            $userData = $this->generateValidUserData();
            
            // Add potentially malicious data
            $maliciousInputs = [
                '<script>alert("xss")</script>',
                'DROP TABLE users;',
                "'; DELETE FROM users; --",
                '<img src=x onerror=alert(1)>',
                '../../etc/passwd',
                'javascript:alert(1)',
                '\x00\x01\x02',
                str_repeat('A', 1000), // Very long string
            ];
            
            $maliciousInput = $maliciousInputs[array_rand($maliciousInputs)];
            
            // Test malicious input in different fields
            $testFields = ['first_name', 'last_name', 'phone'];
            $testField = $testFields[array_rand($testFields)];
            $userData[$testField] = $maliciousInput;
            
            try {
                $user = $this->user->createUser($userData);
                $this->testUsers[] = $user['id'];
                
                // If creation succeeds, verify data is sanitized
                assert($user[$testField] !== $maliciousInput || strlen($user[$testField]) <= 100, 
                       "Malicious input should be sanitized or rejected");
                
                // Verify no script tags in response
                assert(strpos($user[$testField], '<script>') === false, "Script tags should be removed");
                assert(strpos($user[$testField], 'javascript:') === false, "JavaScript URLs should be removed");
                
            } catch (Exception $e) {
                // It's acceptable for malicious input to be rejected
                assert($e->getCode() === 400, "Malicious input should return 400 validation error if rejected");
            }
        }
        
        echo "✓ Data sanitization property verified across 30 malicious inputs\n";
    }
    
    /**
     * Property: CRUD operation consistency
     * **Validates: Requirements 3.1**
     * 
     * For any valid user data, Create -> Read -> Update -> Delete operations should be consistent.
     * What you create should be readable, what you update should be reflected, what you delete should be gone.
     */
    public function testCRUDConsistencyProperty() {
        echo "Testing CRUD consistency property...\n";
        
        for ($i = 0; $i < 25; $i++) {
            $originalData = $this->generateValidUserData();
            
            // CREATE
            $createdUser = $this->user->createUser($originalData);
            $userId = $createdUser['id'];
            
            // READ
            $readUser = $this->user->getUserById($userId);
            assert($readUser !== null, "Created user should be readable");
            assert($readUser['email'] === $originalData['email'], "Read data should match created data");
            assert($readUser['first_name'] === $originalData['first_name'], "First name should match");
            assert($readUser['last_name'] === $originalData['last_name'], "Last name should match");
            
            // UPDATE
            $updateData = [
                'first_name' => $this->generateRandomName(),
                'last_name' => $this->generateRandomName(),
                'phone' => $this->generateRandomPhone()
            ];
            
            $updatedUser = $this->user->updateUser($userId, $updateData);
            assert($updatedUser['first_name'] === $updateData['first_name'], "Updated first name should be reflected");
            assert($updatedUser['last_name'] === $updateData['last_name'], "Updated last name should be reflected");
            assert($updatedUser['phone'] === $updateData['phone'], "Updated phone should be reflected");
            assert($updatedUser['email'] === $originalData['email'], "Email should remain unchanged");
            
            // Verify update persistence
            $reReadUser = $this->user->getUserById($userId);
            assert($reReadUser['first_name'] === $updateData['first_name'], "Updates should persist after re-read");
            
            // DELETE
            $deleteResult = $this->user->deleteUser($userId);
            assert($deleteResult === true, "Delete should return true");
            
            $deletedUser = $this->user->getUserById($userId);
            assert($deletedUser === null, "Deleted user should not be readable");
        }
        
        echo "✓ CRUD consistency property verified across 25 iterations\n";
    }
    
    /**
     * Property: Email format validation
     * **Validates: Requirements 16.1**
     * 
     * For any string that is not a valid email format, user creation should fail.
     * For any string that is a valid email format, validation should pass.
     */
    public function testEmailFormatValidationProperty() {
        echo "Testing email format validation property...\n";
        
        // Test invalid email formats
        $invalidEmails = [
            'notanemail',
            '@domain.com',
            'user@',
            'user..name@domain.com',
            'user@domain',
            'user name@domain.com',
            'user@domain..com',
            '',
            'user@',
            '@',
            'user@@domain.com',
            'user@domain.c',
            str_repeat('a', 250) . '@domain.com', // Too long
        ];
        
        foreach ($invalidEmails as $invalidEmail) {
            $userData = $this->generateValidUserData($invalidEmail);
            
            try {
                $user = $this->user->createUser($userData);
                $this->testUsers[] = $user['id']; // Clean up if somehow created
                assert(false, "Invalid email '$invalidEmail' should not be accepted");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Invalid email should return 400 validation error");
                assert(strpos($e->getMessage(), 'email') !== false, "Error should mention email");
            }
        }
        
        // Test valid email formats
        for ($i = 0; $i < 20; $i++) {
            $validEmail = $this->generateRandomEmail();
            $userData = $this->generateValidUserData($validEmail);
            
            try {
                $user = $this->user->createUser($userData);
                $this->testUsers[] = $user['id'];
                
                assert($user['email'] === strtolower($validEmail), "Valid email should be accepted and normalized");
                
            } catch (Exception $e) {
                echo "Valid email '$validEmail' failed: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Email format validation property verified across invalid and valid emails\n";
    }
    
    /**
     * Property: Phone number formatting
     * **Validates: Requirements 16.1**
     * 
     * For any phone number input, the system should format it consistently.
     * Invalid phone numbers should be rejected.
     */
    public function testPhoneNumberFormattingProperty() {
        echo "Testing phone number formatting property...\n";
        
        for ($i = 0; $i < 30; $i++) {
            $userData = $this->generateValidUserData();
            
            // Test various phone number formats
            $phoneFormats = [
                '1234567890',
                '+1234567890',
                '(123) 456-7890',
                '123-456-7890',
                '123.456.7890',
                '+1 (123) 456-7890',
                '+91 98765 43210',
                '44 20 7946 0958',
            ];
            
            $originalPhone = $phoneFormats[array_rand($phoneFormats)];
            $userData['phone'] = $originalPhone;
            
            try {
                $user = $this->user->createUser($userData);
                $this->testUsers[] = $user['id'];
                
                // Verify phone is formatted (should contain only digits and +)
                if ($user['phone'] !== null) {
                    assert(preg_match('/^[+]?[0-9]+$/', $user['phone']), 
                           "Phone should be formatted to contain only digits and optional +");
                    assert(strlen($user['phone']) >= 10, "Formatted phone should have at least 10 digits");
                }
                
            } catch (Exception $e) {
                // Some phone formats might be rejected, which is acceptable
                if ($e->getCode() === 400 && strpos($e->getMessage(), 'phone') !== false) {
                    continue; // Invalid phone format rejected
                }
                throw $e;
            }
        }
        
        echo "✓ Phone number formatting property verified across 30 different formats\n";
    }
    
    /**
     * Property: User retrieval consistency
     * **Validates: Requirements 3.1**
     * 
     * For any user that exists, it should be retrievable by both ID and email.
     * The retrieved data should be identical regardless of retrieval method.
     */
    public function testUserRetrievalConsistencyProperty() {
        echo "Testing user retrieval consistency property...\n";
        
        for ($i = 0; $i < 20; $i++) {
            $userData = $this->generateValidUserData();
            
            // Create user
            $createdUser = $this->user->createUser($userData);
            $this->testUsers[] = $createdUser['id'];
            
            // Retrieve by ID
            $userById = $this->user->getUserById($createdUser['id']);
            
            // Retrieve by email
            $userByEmail = $this->user->getUserByEmail($createdUser['email']);
            
            // Both should return the same data
            assert($userById !== null, "User should be retrievable by ID");
            assert($userByEmail !== null, "User should be retrievable by email");
            assert($userById['id'] === $userByEmail['id'], "ID should match between retrieval methods");
            assert($userById['email'] === $userByEmail['email'], "Email should match between retrieval methods");
            assert($userById['first_name'] === $userByEmail['first_name'], "First name should match");
            assert($userById['last_name'] === $userByEmail['last_name'], "Last name should match");
            assert($userById['phone'] === $userByEmail['phone'], "Phone should match");
            assert($userById['role'] === $userByEmail['role'], "Role should match");
        }
        
        echo "✓ User retrieval consistency property verified across 20 users\n";
    }
    
    /**
     * Generate random valid user data
     */
    private function generateValidUserData($email = null) {
        return [
            'email' => $email ?: $this->generateRandomEmail(),
            'password' => $this->generateStrongPassword(),
            'first_name' => $this->generateRandomName(),
            'last_name' => $this->generateRandomName(),
            'phone' => rand(0, 1) ? $this->generateRandomPhone() : null,
            'role' => rand(0, 10) > 8 ? 'admin' : 'customer' // 20% chance of admin
        ];
    }
    
    /**
     * Generate random email
     */
    private function generateRandomEmail() {
        $domains = ['example.com', 'test.org', 'demo.net', 'sample.co'];
        $username = 'user' . rand(1000, 9999) . '_' . uniqid();
        $domain = $domains[array_rand($domains)];
        
        return $username . '@' . $domain;
    }
    
    /**
     * Generate strong password
     */
    private function generateStrongPassword() {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = '';
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)]; // At least one uppercase
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)]; // At least one lowercase
        $password .= $numbers[rand(0, strlen($numbers) - 1)];     // At least one number
        $password .= $special[rand(0, strlen($special) - 1)];     // At least one special
        
        // Add random characters to reach minimum length
        $allChars = $uppercase . $lowercase . $numbers . $special;
        while (strlen($password) < 12) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }
        
        return str_shuffle($password);
    }
    
    /**
     * Generate random name
     */
    private function generateRandomName() {
        $names = [
            'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Jessica',
            'William', 'Ashley', 'James', 'Amanda', 'Christopher', 'Melissa', 'Daniel',
            'Deborah', 'Matthew', 'Dorothy', 'Anthony', 'Lisa', 'Mark', 'Nancy',
            'Donald', 'Karen', 'Steven', 'Betty', 'Paul', 'Helen', 'Andrew', 'Sandra'
        ];
        
        return $names[array_rand($names)] . rand(1, 999);
    }
    
    /**
     * Generate random phone number
     */
    private function generateRandomPhone() {
        $formats = [
            '+1' . rand(1000000000, 9999999999),
            '+44' . rand(1000000000, 9999999999),
            '+91' . rand(1000000000, 9999999999),
            rand(1000000000, 9999999999),
        ];
        
        return $formats[array_rand($formats)];
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        foreach ($this->testUsers as $userId) {
            try {
                // Hard delete test users
                $this->db->executeQuery("DELETE FROM users WHERE id = ?", [$userId]);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up any test users by email pattern
        try {
            $this->db->executeQuery("DELETE FROM users WHERE email LIKE '%example.com' OR email LIKE '%test.org' OR email LIKE '%demo.net' OR email LIKE '%sample.co'");
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        
        $this->testUsers = [];
    }
    
    /**
     * Run all property-based tests
     */
    public function runAllTests() {
        echo "Running User Model Property-Based Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testEmailUniquenessProperty();
            $this->testPasswordSecurityProperty();
            $this->testDataSanitizationProperty();
            $this->testCRUDConsistencyProperty();
            $this->testEmailFormatValidationProperty();
            $this->testPhoneNumberFormattingProperty();
            $this->testUserRetrievalConsistencyProperty();
            
            echo "\n✅ All User Model property-based tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Property test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserPropertyTest();
    $test->runAllTests();
}