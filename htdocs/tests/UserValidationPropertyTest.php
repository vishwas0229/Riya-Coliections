<?php
/**
 * User Model Validation Property-Based Tests
 * 
 * Property-based tests that verify universal properties of user validation
 * across many randomly generated inputs to ensure robustness.
 */

// Include the validator from the previous test
require_once __DIR__ . '/UserValidationTest.php';

class UserValidationPropertyTest {
    private $validator;
    
    public function setUp() {
        $this->validator = new UserValidator();
    }
    
    /**
     * Property: Email format validation consistency
     * **Validates: Requirements 16.1**
     * 
     * For any string that is not a valid email format, validation should fail.
     * For any string that is a valid email format, validation should pass.
     */
    public function testEmailFormatValidationProperty() {
        echo "Testing email format validation property...\n";
        
        // Test invalid email patterns
        $invalidPatterns = [
            'notanemail',
            '@domain.com',
            'user@',
            'user..name@domain.com',
            'user@domain',
            'user name@domain.com',
            'user@domain..com',
            '',
            'user@@domain.com',
            'user@domain.c',
        ];
        
        foreach ($invalidPatterns as $pattern) {
            $userData = [
                'email' => $pattern,
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            
            try {
                $this->validator->validateUserData($userData, true);
                assert(false, "Invalid email pattern should fail: $pattern");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Invalid email should return 400 error: $pattern");
                assert(strpos($e->getMessage(), 'email') !== false, "Error should mention email: $pattern");
            }
        }
        
        // Test valid email patterns with random variations
        for ($i = 0; $i < 50; $i++) {
            $validEmail = $this->generateValidEmail();
            $userData = [
                'email' => $validEmail,
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            
            try {
                $result = $this->validator->validateUserData($userData, true);
                assert($result === true, "Valid email should pass validation: $validEmail");
            } catch (Exception $e) {
                echo "Valid email failed validation: $validEmail - " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Email format validation property verified across 50+ test cases\n";
    }
    
    /**
     * Property: Password strength requirements
     * **Validates: Requirements 3.2, 16.1**
     * 
     * For any password that doesn't meet all security requirements, validation should fail.
     * For any password that meets all requirements, validation should pass.
     */
    public function testPasswordStrengthProperty() {
        echo "Testing password strength property...\n";
        
        // Test passwords missing each requirement
        $weakPasswordTypes = [
            ['type' => 'too_short', 'generator' => function() { return $this->generateShortPassword(); }],
            ['type' => 'no_uppercase', 'generator' => function() { return $this->generateNoUppercasePassword(); }],
            ['type' => 'no_lowercase', 'generator' => function() { return $this->generateNoLowercasePassword(); }],
            ['type' => 'no_numbers', 'generator' => function() { return $this->generateNoNumbersPassword(); }],
            ['type' => 'no_special', 'generator' => function() { return $this->generateNoSpecialPassword(); }],
        ];
        
        foreach ($weakPasswordTypes as $type) {
            for ($i = 0; $i < 10; $i++) {
                $weakPassword = $type['generator']();
                
                try {
                    $this->validator->validatePassword($weakPassword);
                    assert(false, "Weak password ({$type['type']}) should fail: $weakPassword");
                } catch (Exception $e) {
                    assert(strpos($e->getMessage(), 'Password must') !== false, 
                           "Error should mention password requirements for {$type['type']}: $weakPassword");
                }
            }
        }
        
        // Test strong passwords (should all pass)
        for ($i = 0; $i < 30; $i++) {
            $strongPassword = $this->generateStrongPassword();
            
            try {
                $result = $this->validator->validatePassword($strongPassword);
                assert($result === true, "Strong password should pass validation: $strongPassword");
            } catch (Exception $e) {
                echo "Strong password failed validation: $strongPassword - " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Password strength property verified across 80+ test cases\n";
    }
    
    /**
     * Property: Required fields validation
     * **Validates: Requirements 16.1**
     * 
     * For any user data missing required fields, validation should fail.
     * For any user data with all required fields, validation should pass (assuming valid values).
     */
    public function testRequiredFieldsProperty() {
        echo "Testing required fields property...\n";
        
        $requiredFields = ['email', 'password', 'first_name', 'last_name'];
        
        // Test missing each required field
        foreach ($requiredFields as $missingField) {
            for ($i = 0; $i < 10; $i++) {
                $userData = $this->generateValidUserData();
                unset($userData[$missingField]); // Remove the required field
                
                try {
                    $this->validator->validateUserData($userData, true);
                    assert(false, "Missing required field should fail: $missingField");
                } catch (Exception $e) {
                    assert($e->getCode() === 400, "Missing required field should return 400 error: $missingField");
                    assert(strpos($e->getMessage(), 'required') !== false, 
                           "Error should mention required field: $missingField");
                }
            }
        }
        
        // Test with all required fields present
        for ($i = 0; $i < 20; $i++) {
            $userData = $this->generateValidUserData();
            
            try {
                $result = $this->validator->validateUserData($userData, true);
                assert($result === true, "Complete user data should pass validation");
            } catch (Exception $e) {
                echo "Complete user data failed validation: " . json_encode($userData) . " - " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Required fields property verified across 60+ test cases\n";
    }
    
    /**
     * Property: Phone number formatting consistency
     * **Validates: Requirements 16.1**
     * 
     * For any phone number input, the formatting should be consistent and contain only digits and +.
     */
    public function testPhoneFormattingProperty() {
        echo "Testing phone number formatting property...\n";
        
        for ($i = 0; $i < 50; $i++) {
            $originalPhone = $this->generateRandomPhoneInput();
            $formattedPhone = $this->validator->formatPhone($originalPhone);
            
            // Property: Formatted phone should only contain digits and +
            if (!empty($formattedPhone)) {
                assert(preg_match('/^[+]?[0-9]+$/', $formattedPhone), 
                       "Formatted phone should only contain digits and +: '$originalPhone' -> '$formattedPhone'");
                
                // Property: Formatted phone should have reasonable length
                assert(strlen($formattedPhone) >= 10 && strlen($formattedPhone) <= 15, 
                       "Formatted phone should have reasonable length: '$originalPhone' -> '$formattedPhone'");
            }
        }
        
        echo "✓ Phone number formatting property verified across 50 test cases\n";
    }
    
    /**
     * Property: Data sanitization consistency
     * **Validates: Requirements 16.1**
     * 
     * For any user data, sanitization should produce consistent, safe output.
     */
    public function testDataSanitizationProperty() {
        echo "Testing data sanitization property...\n";
        
        for ($i = 0; $i < 30; $i++) {
            $userData = $this->generateUserDataWithVariations();
            $sanitized = $this->validator->sanitizeUserData($userData);
            
            // Property: Sanitized data should have correct types
            assert(is_int($sanitized['id']), "ID should be integer");
            assert(is_string($sanitized['email']), "Email should be string");
            assert(is_string($sanitized['first_name']), "First name should be string");
            assert(is_string($sanitized['last_name']), "Last name should be string");
            assert(is_string($sanitized['full_name']), "Full name should be string");
            assert(is_bool($sanitized['is_active']), "is_active should be boolean");
            
            // Property: Full name should be combination of first and last name
            $expectedFullName = trim($userData['first_name'] . ' ' . $userData['last_name']);
            assert($sanitized['full_name'] === $expectedFullName, 
                   "Full name should be combination of first and last name");
            
            // Property: Sensitive fields should not be present
            assert(!isset($sanitized['password']), "Password should not be in sanitized data");
            assert(!isset($sanitized['password_hash']), "Password hash should not be in sanitized data");
        }
        
        echo "✓ Data sanitization property verified across 30 test cases\n";
    }
    
    /**
     * Property: Field length validation
     * **Validates: Requirements 16.1**
     * 
     * For any field that exceeds maximum length, validation should fail.
     */
    public function testFieldLengthProperty() {
        echo "Testing field length property...\n";
        
        $fieldLimits = [
            'email' => 255,
            'first_name' => 100,
            'last_name' => 100
        ];
        
        foreach ($fieldLimits as $field => $limit) {
            for ($i = 0; $i < 10; $i++) {
                $userData = $this->generateValidUserData();
                
                // Generate string that exceeds limit
                $tooLongValue = str_repeat('A', $limit + rand(1, 50));
                if ($field === 'email') {
                    $tooLongValue = $tooLongValue . '@example.com';
                }
                $userData[$field] = $tooLongValue;
                
                try {
                    $this->validator->validateUserData($userData, true);
                    assert(false, "Too long $field should fail validation: length " . strlen($tooLongValue));
                } catch (Exception $e) {
                    assert($e->getCode() === 400, "Too long $field should return 400 error");
                    assert(strpos($e->getMessage(), 'too long') !== false, 
                           "Error should mention field too long for $field");
                }
            }
        }
        
        echo "✓ Field length property verified across multiple fields and lengths\n";
    }
    
    /**
     * Generate valid email
     */
    private function generateValidEmail() {
        $domains = ['example.com', 'test.org', 'demo.net', 'sample.co.uk'];
        $usernames = ['user', 'test', 'demo', 'sample', 'john', 'jane'];
        
        $username = $usernames[array_rand($usernames)] . rand(1, 999);
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
     * Generate short password (weak)
     */
    private function generateShortPassword() {
        return 'Abc1!'; // Only 5 characters
    }
    
    /**
     * Generate password without uppercase (weak)
     */
    private function generateNoUppercasePassword() {
        return 'lowercase123!';
    }
    
    /**
     * Generate password without lowercase (weak)
     */
    private function generateNoLowercasePassword() {
        return 'UPPERCASE123!';
    }
    
    /**
     * Generate password without numbers (weak)
     */
    private function generateNoNumbersPassword() {
        return 'NoNumbers!@#';
    }
    
    /**
     * Generate password without special characters (weak)
     */
    private function generateNoSpecialPassword() {
        return 'NoSpecialChars123';
    }
    
    /**
     * Generate valid user data
     */
    private function generateValidUserData() {
        return [
            'email' => $this->generateValidEmail(),
            'password' => $this->generateStrongPassword(),
            'first_name' => $this->generateRandomName(),
            'last_name' => $this->generateRandomName(),
            'phone' => rand(0, 1) ? $this->generateRandomPhone() : null,
            'role' => rand(0, 10) > 8 ? 'admin' : 'customer'
        ];
    }
    
    /**
     * Generate user data with type variations for sanitization testing
     */
    private function generateUserDataWithVariations() {
        return [
            'id' => (string)rand(1, 1000), // String that should become int
            'email' => $this->generateValidEmail(),
            'first_name' => $this->generateRandomName(),
            'last_name' => $this->generateRandomName(),
            'phone' => $this->generateRandomPhone(),
            'role' => 'customer',
            'is_active' => rand(0, 1) ? '1' : '0', // String that should become bool
            'email_verified_at' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
            'last_login_at' => null,
            'password' => 'should_not_appear', // Should be filtered out
            'password_hash' => 'should_not_appear' // Should be filtered out
        ];
    }
    
    /**
     * Generate random name
     */
    private function generateRandomName() {
        $names = [
            'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Jessica',
            'William', 'Ashley', 'James', 'Amanda', 'Christopher', 'Melissa', 'Daniel',
            'Test', 'Demo', 'Sample', 'User', 'Admin'
        ];
        
        return $names[array_rand($names)];
    }
    
    /**
     * Generate random phone number
     */
    private function generateRandomPhone() {
        return '+' . rand(1, 999) . rand(1000000000, 9999999999);
    }
    
    /**
     * Generate random phone input with various formats
     */
    private function generateRandomPhoneInput() {
        $formats = [
            '+1234567890',
            '1234567890',
            '(123) 456-7890',
            '123-456-7890',
            '123.456.7890',
            '+1 (123) 456-7890',
            '123 456 7890',
            '+44 20 7946 0958',
        ];
        
        return $formats[array_rand($formats)];
    }
    
    /**
     * Run all property-based tests
     */
    public function runAllTests() {
        echo "Running User Model Validation Property-Based Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testEmailFormatValidationProperty();
            $this->testPasswordStrengthProperty();
            $this->testRequiredFieldsProperty();
            $this->testPhoneFormattingProperty();
            $this->testDataSanitizationProperty();
            $this->testFieldLengthProperty();
            
            echo "\n✅ All User Model validation property-based tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Property test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserValidationPropertyTest();
    $test->runAllTests();
}