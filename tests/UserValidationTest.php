<?php
/**
 * User Model Validation Tests
 * 
 * Isolated tests for User model validation logic without database dependencies.
 */

// Test the validation methods directly by creating a minimal User class
class UserValidator {
    
    /**
     * Validate user data
     */
    public function validateUserData($userData, $isCreation = false) {
        $errors = [];
        
        // Email validation
        if ($isCreation && empty($userData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } elseif (!empty($userData['email']) && strlen($userData['email']) > 255) {
            $errors[] = 'Email is too long (maximum 255 characters)';
        }
        
        // Password validation (only for creation)
        if ($isCreation) {
            if (empty($userData['password'])) {
                $errors[] = 'Password is required';
            } else {
                try {
                    $this->validatePassword($userData['password']);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        
        // Name validation
        if ($isCreation && empty($userData['first_name'])) {
            $errors[] = 'First name is required';
        } elseif (!empty($userData['first_name']) && strlen(trim($userData['first_name'])) > 100) {
            $errors[] = 'First name is too long (maximum 100 characters)';
        }
        
        if ($isCreation && empty($userData['last_name'])) {
            $errors[] = 'Last name is required';
        } elseif (!empty($userData['last_name']) && strlen(trim($userData['last_name'])) > 100) {
            $errors[] = 'Last name is too long (maximum 100 characters)';
        }
        
        // Phone validation (if provided)
        if (!empty($userData['phone'])) {
            if (!preg_match('/^[+]?[0-9\s\-\(\)]{10,20}$/', $userData['phone'])) {
                $errors[] = 'Invalid phone number format';
            }
        }
        
        // Role validation
        if (!empty($userData['role']) && !in_array($userData['role'], ['customer', 'admin'])) {
            $errors[] = 'Invalid role specified';
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
        }
        
        return true;
    }
    
    /**
     * Validate password strength
     */
    public function validatePassword($password) {
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception('Password must contain at least one uppercase letter');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception('Password must contain at least one lowercase letter');
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception('Password must contain at least one number');
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new Exception('Password must contain at least one special character');
        }
        
        return true;
    }
    
    /**
     * Format phone number
     */
    public function formatPhone($phone) {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^+0-9]/', '', $phone);
        
        // Ensure it starts with + for international numbers
        if (!empty($cleaned) && $cleaned[0] !== '+' && strlen($cleaned) > 10) {
            $cleaned = '+' . $cleaned;
        }
        
        return $cleaned;
    }
    
    /**
     * Sanitize user data for API response
     */
    public function sanitizeUserData($user) {
        return [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'full_name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'phone' => $user['phone'],
            'role' => $user['role'],
            'is_active' => (bool)$user['is_active'],
            'email_verified_at' => $user['email_verified_at'] ?? null,
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'last_login_at' => $user['last_login_at'] ?? null
        ];
    }
}

class UserValidationTest {
    private $validator;
    
    public function setUp() {
        $this->validator = new UserValidator();
    }
    
    /**
     * Test email validation
     */
    public function testEmailValidation() {
        echo "Testing email validation...\n";
        
        // Valid emails
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org',
            'user123@test-domain.com',
            'a@b.co'
        ];
        
        foreach ($validEmails as $email) {
            $userData = [
                'email' => $email,
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            
            try {
                $result = $this->validator->validateUserData($userData, true);
                assert($result === true, "Valid email should pass validation: $email");
            } catch (Exception $e) {
                assert(false, "Valid email should not fail validation: $email - " . $e->getMessage());
            }
        }
        
        // Invalid emails
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user..name@domain.com',
            'user@domain',
            'user name@domain.com',
            '',
            'user@@domain.com'
        ];
        
        foreach ($invalidEmails as $email) {
            $userData = [
                'email' => $email,
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            
            try {
                $this->validator->validateUserData($userData, true);
                assert(false, "Invalid email should fail validation: $email");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Invalid email should return 400 error: $email");
                assert(strpos($e->getMessage(), 'email') !== false, "Error should mention email: $email");
            }
        }
        
        echo "✓ Email validation test passed\n";
    }
    
    /**
     * Test password validation
     */
    public function testPasswordValidation() {
        echo "Testing password validation...\n";
        
        // Weak passwords
        $weakPasswords = [
            'short',           // Too short
            'nouppercase123!', // No uppercase
            'NOLOWERCASE123!', // No lowercase
            'NoNumbers!',      // No numbers
            'NoSpecialChars123', // No special characters
            '12345678',        // Only numbers
            'abcdefgh',        // Only lowercase
            'ABCDEFGH',        // Only uppercase
        ];
        
        foreach ($weakPasswords as $password) {
            try {
                $this->validator->validatePassword($password);
                assert(false, "Weak password should fail validation: $password");
            } catch (Exception $e) {
                assert(strpos($e->getMessage(), 'Password must') !== false, "Error should mention password requirements: $password");
            }
        }
        
        // Strong passwords
        $strongPasswords = [
            'StrongPass123!',
            'MySecure@Pass456',
            'Complex#Password789',
            'Valid$Password2024',
            'Test123!@#'
        ];
        
        foreach ($strongPasswords as $password) {
            try {
                $result = $this->validator->validatePassword($password);
                assert($result === true, "Strong password should pass validation: $password");
            } catch (Exception $e) {
                assert(false, "Strong password should not fail validation: $password - " . $e->getMessage());
            }
        }
        
        echo "✓ Password validation test passed\n";
    }
    
    /**
     * Test required fields validation
     */
    public function testRequiredFieldsValidation() {
        echo "Testing required fields validation...\n";
        
        // Missing email
        try {
            $userData = [
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            $this->validator->validateUserData($userData, true);
            assert(false, "Missing email should fail validation");
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Missing email should return 400 error");
            assert(strpos($e->getMessage(), 'Email is required') !== false, "Error should mention email required");
        }
        
        // Missing password
        try {
            $userData = [
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            $this->validator->validateUserData($userData, true);
            assert(false, "Missing password should fail validation");
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Missing password should return 400 error");
            assert(strpos($e->getMessage(), 'Password is required') !== false, "Error should mention password required");
        }
        
        // Missing first name
        try {
            $userData = [
                'email' => 'test@example.com',
                'password' => 'TestPass123!',
                'last_name' => 'User'
            ];
            $this->validator->validateUserData($userData, true);
            assert(false, "Missing first name should fail validation");
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Missing first name should return 400 error");
            assert(strpos($e->getMessage(), 'First name is required') !== false, "Error should mention first name required");
        }
        
        // Missing last name
        try {
            $userData = [
                'email' => 'test@example.com',
                'password' => 'TestPass123!',
                'first_name' => 'Test'
            ];
            $this->validator->validateUserData($userData, true);
            assert(false, "Missing last name should fail validation");
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Missing last name should return 400 error");
            assert(strpos($e->getMessage(), 'Last name is required') !== false, "Error should mention last name required");
        }
        
        echo "✓ Required fields validation test passed\n";
    }
    
    /**
     * Test phone number formatting
     */
    public function testPhoneFormatting() {
        echo "Testing phone number formatting...\n";
        
        $phoneTests = [
            ['input' => '1234567890', 'expected' => '1234567890'],
            ['input' => '+1234567890', 'expected' => '+1234567890'],
            ['input' => '(123) 456-7890', 'expected' => '1234567890'],
            ['input' => '123-456-7890', 'expected' => '1234567890'],
            ['input' => '123.456.7890', 'expected' => '1234567890'],
            ['input' => '+1 (123) 456-7890', 'expected' => '+11234567890'],
        ];
        
        foreach ($phoneTests as $test) {
            $result = $this->validator->formatPhone($test['input']);
            assert($result === $test['expected'], "Phone formatting failed for '{$test['input']}': expected '{$test['expected']}', got '$result'");
        }
        
        echo "✓ Phone number formatting test passed\n";
    }
    
    /**
     * Test role validation
     */
    public function testRoleValidation() {
        echo "Testing role validation...\n";
        
        // Valid roles
        $validRoles = ['customer', 'admin'];
        
        foreach ($validRoles as $role) {
            $userData = [
                'email' => 'test@example.com',
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User',
                'role' => $role
            ];
            
            try {
                $result = $this->validator->validateUserData($userData, true);
                assert($result === true, "Valid role should pass validation: $role");
            } catch (Exception $e) {
                assert(false, "Valid role should not fail validation: $role - " . $e->getMessage());
            }
        }
        
        // Invalid roles
        $invalidRoles = ['invalid', 'superuser', 'moderator', ''];
        
        foreach ($invalidRoles as $role) {
            $userData = [
                'email' => 'test@example.com',
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User',
                'role' => $role
            ];
            
            try {
                $this->validator->validateUserData($userData, true);
                assert(false, "Invalid role should fail validation: $role");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Invalid role should return 400 error: $role");
                assert(strpos($e->getMessage(), 'Invalid role') !== false, "Error should mention invalid role: $role");
            }
        }
        
        echo "✓ Role validation test passed\n";
    }
    
    /**
     * Test data sanitization
     */
    public function testDataSanitization() {
        echo "Testing data sanitization...\n";
        
        $userData = [
            'id' => '123',
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'role' => 'customer',
            'is_active' => '1',
            'email_verified_at' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
            'last_login_at' => null
        ];
        
        $sanitized = $this->validator->sanitizeUserData($userData);
        
        // Check data types
        assert(is_int($sanitized['id']), 'ID should be integer');
        assert(is_bool($sanitized['is_active']), 'is_active should be boolean');
        assert($sanitized['full_name'] === 'John Doe', 'Full name should be concatenated');
        
        // Check all required fields are present
        $requiredFields = ['id', 'email', 'first_name', 'last_name', 'full_name', 'phone', 'role', 'is_active'];
        foreach ($requiredFields as $field) {
            assert(array_key_exists($field, $sanitized), "Sanitized data should contain field: $field");
        }
        
        echo "✓ Data sanitization test passed\n";
    }
    
    /**
     * Test field length validation
     */
    public function testFieldLengthValidation() {
        echo "Testing field length validation...\n";
        
        // Test email too long
        try {
            $userData = [
                'email' => str_repeat('a', 250) . '@example.com', // Too long
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            $this->validator->validateUserData($userData, true);
            assert(false, "Too long email should fail validation");
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Too long email should return 400 error");
            assert(strpos($e->getMessage(), 'too long') !== false, "Error should mention field too long");
        }
        
        // Test first name too long
        try {
            $userData = [
                'email' => 'test@example.com',
                'password' => 'TestPass123!',
                'first_name' => str_repeat('A', 101), // Too long
                'last_name' => 'User'
            ];
            $this->validator->validateUserData($userData, true);
            assert(false, "Too long first name should fail validation");
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Too long first name should return 400 error");
            assert(strpos($e->getMessage(), 'too long') !== false, "Error should mention field too long");
        }
        
        echo "✓ Field length validation test passed\n";
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running User Model Validation Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testEmailValidation();
            $this->testPasswordValidation();
            $this->testRequiredFieldsValidation();
            $this->testPhoneFormatting();
            $this->testRoleValidation();
            $this->testDataSanitization();
            $this->testFieldLengthValidation();
            
            echo "\n✅ All User Model validation tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserValidationTest();
    $test->runAllTests();
}