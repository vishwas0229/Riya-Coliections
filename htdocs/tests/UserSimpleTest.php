<?php
/**
 * Simple User Model Tests
 * 
 * Basic tests for the User model that don't require full database setup.
 * These tests focus on validation logic and data sanitization.
 */

// Set up minimal environment
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $default;
    }
}

if (!function_exists('isDevelopment')) {
    function isDevelopment() {
        return true;
    }
}

// Mock Logger class
if (!class_exists('Logger')) {
    class Logger {
        public static function info($message, $context = []) {
            // Silent for tests
        }
        
        public static function error($message, $context = []) {
            // Silent for tests
        }
        
        public static function security($message, $context = []) {
            // Silent for tests
        }
    }
}

// Mock Database class for testing
class MockDatabase {
    private static $instance = null;
    private $data = [];
    private $lastInsertId = 0;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function executeQuery($sql, $params = []) {
        // Mock implementation
        return new MockStatement();
    }
    
    public function fetchAll($sql, $params = []) {
        return [];
    }
    
    public function fetchOne($sql, $params = []) {
        return null;
    }
    
    public function fetchColumn($sql, $params = []) {
        return 0;
    }
    
    public function getLastInsertId() {
        return ++$this->lastInsertId;
    }
    
    public function beginTransaction() {
        return true;
    }
    
    public function commit() {
        return true;
    }
    
    public function rollback() {
        return true;
    }
}

class MockStatement {
    public function rowCount() {
        return 1;
    }
    
    public function fetch() {
        return null;
    }
    
    public function fetchAll() {
        return [];
    }
}

// Mock DatabaseModel class
class DatabaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct($table = null) {
        $this->table = $table;
        $this->db = MockDatabase::getInstance();
    }
    
    public function setPrimaryKey($key) {
        $this->primaryKey = $key;
        return $this;
    }
    
    public function insert($data) {
        return $this->db->getLastInsertId();
    }
    
    public function find($id) {
        // Mock user data
        if ($id === 1) {
            return [
                'id' => 1,
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => '+1234567890',
                'role' => 'customer',
                'is_active' => true,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
                'last_login_at' => null
            ];
        }
        return null;
    }
    
    public function first($conditions = []) {
        if (isset($conditions['email']) && $conditions['email'] === 'test@example.com') {
            return $this->find(1);
        }
        return null;
    }
    
    public function updateById($id, $data) {
        return 1;
    }
    
    public function exists($conditions) {
        if (isset($conditions['email']) && $conditions['email'] === 'existing@example.com') {
            return true;
        }
        return false;
    }
    
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    public function commit() {
        return $this->db->commit();
    }
    
    public function rollback() {
        return $this->db->rollback();
    }
}

// Include the User model
require_once __DIR__ . '/../models/User.php';

class UserSimpleTest {
    private $user;
    
    public function setUp() {
        $this->user = new User();
    }
    
    /**
     * Test email validation
     */
    public function testEmailValidation() {
        // Test valid emails
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org',
            'user123@test-domain.com'
        ];
        
        foreach ($validEmails as $email) {
            try {
                $userData = [
                    'email' => $email,
                    'password' => 'TestPass123!',
                    'first_name' => 'Test',
                    'last_name' => 'User'
                ];
                
                // This should not throw an exception for valid emails
                $result = $this->user->createUser($userData);
                assert(isset($result['email']), "Valid email should be accepted: $email");
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Invalid email') !== false) {
                    assert(false, "Valid email should not be rejected: $email");
                }
                // Other exceptions (like duplicate email) are acceptable
            }
        }
        
        // Test invalid emails
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user..name@domain.com',
            'user@domain',
            'user name@domain.com'
        ];
        
        foreach ($invalidEmails as $email) {
            try {
                $userData = [
                    'email' => $email,
                    'password' => 'TestPass123!',
                    'first_name' => 'Test',
                    'last_name' => 'User'
                ];
                
                $this->user->createUser($userData);
                assert(false, "Invalid email should be rejected: $email");
                
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
        // Test weak passwords
        $weakPasswords = [
            'short',           // Too short
            'nouppercase123!', // No uppercase
            'NOLOWERCASE123!', // No lowercase
            'NoNumbers!',      // No numbers
            'NoSpecialChars123' // No special characters
        ];
        
        foreach ($weakPasswords as $password) {
            try {
                $userData = [
                    'email' => 'test' . rand(1000, 9999) . '@example.com',
                    'password' => $password,
                    'first_name' => 'Test',
                    'last_name' => 'User'
                ];
                
                $this->user->createUser($userData);
                assert(false, "Weak password should be rejected: $password");
                
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Weak password should return 400 error: $password");
                assert(strpos($e->getMessage(), 'Password must') !== false, "Error should mention password requirements: $password");
            }
        }
        
        // Test strong passwords
        $strongPasswords = [
            'StrongPass123!',
            'MySecure@Pass456',
            'Complex#Password789',
            'Valid$Password2024'
        ];
        
        foreach ($strongPasswords as $password) {
            try {
                $userData = [
                    'email' => 'test' . rand(1000, 9999) . '@example.com',
                    'password' => $password,
                    'first_name' => 'Test',
                    'last_name' => 'User'
                ];
                
                $result = $this->user->createUser($userData);
                assert(isset($result['email']), "Strong password should be accepted: $password");
                assert(!isset($result['password_hash']), "Password hash should not be in response");
                
            } catch (Exception $e) {
                // Only fail if it's a password validation error
                if (strpos($e->getMessage(), 'Password must') !== false) {
                    assert(false, "Strong password should not be rejected: $password - " . $e->getMessage());
                }
                // Other exceptions (like duplicate email) are acceptable
            }
        }
        
        echo "✓ Password validation test passed\n";
    }
    
    /**
     * Test required fields validation
     */
    public function testRequiredFieldsValidation() {
        // Test missing email
        try {
            $userData = [
                'password' => 'TestPass123!',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            
            $this->user->createUser($userData);
            assert(false, "Missing email should be rejected");
            
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Missing email should return 400 error");
            assert(strpos($e->getMessage(), 'required') !== false, "Error should mention required field");
        }
        
        // Test missing password
        try {
            $userData = [
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            
            $this->user->createUser($userData);
            assert(false, "Missing password should be rejected");
            
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Missing password should return 400 error");
            assert(strpos($e->getMessage(), 'required') !== false, "Error should mention required field");
        }
        
        // Test missing first name
        try {
            $userData = [
                'email' => 'test@example.com',
                'password' => 'TestPass123!',
                'last_name' => 'User'
            ];
            
            $this->user->createUser($userData);
            assert(false, "Missing first name should be rejected");
            
        } catch (Exception $e) {
            assert($e->getCode() === 400, "Missing first name should return 400 error");
            assert(strpos($e->getMessage(), 'required') !== false, "Error should mention required field");
        }
        
        echo "✓ Required fields validation test passed\n";
    }
    
    /**
     * Test data sanitization
     */
    public function testDataSanitization() {
        $userData = [
            'email' => '  TEST@EXAMPLE.COM  ',  // Should be trimmed and lowercased
            'password' => 'TestPass123!',
            'first_name' => '  John  ',          // Should be trimmed
            'last_name' => '  Doe  ',            // Should be trimmed
            'phone' => '(123) 456-7890'          // Should be formatted
        ];
        
        try {
            $result = $this->user->createUser($userData);
            
            assert($result['email'] === 'test@example.com', 'Email should be normalized');
            assert($result['first_name'] === 'John', 'First name should be trimmed');
            assert($result['last_name'] === 'Doe', 'Last name should be trimmed');
            
            // Phone formatting test (should contain only digits and +)
            if ($result['phone'] !== null) {
                assert(preg_match('/^[+]?[0-9]+$/', $result['phone']), 'Phone should be formatted');
            }
            
        } catch (Exception $e) {
            // If creation fails due to duplicate email (from mock), that's acceptable
            if ($e->getCode() !== 409) {
                throw $e;
            }
        }
        
        echo "✓ Data sanitization test passed\n";
    }
    
    /**
     * Test user data structure
     */
    public function testUserDataStructure() {
        $userData = [
            'email' => 'structure@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Structure',
            'last_name' => 'Test'
        ];
        
        try {
            $result = $this->user->createUser($userData);
            
            // Check required fields are present
            $requiredFields = ['id', 'email', 'first_name', 'last_name', 'role', 'is_active'];
            foreach ($requiredFields as $field) {
                assert(isset($result[$field]), "Result should contain field: $field");
            }
            
            // Check sensitive fields are not present
            $sensitiveFields = ['password', 'password_hash'];
            foreach ($sensitiveFields as $field) {
                assert(!isset($result[$field]), "Result should not contain sensitive field: $field");
            }
            
            // Check data types
            assert(is_int($result['id']), 'ID should be integer');
            assert(is_string($result['email']), 'Email should be string');
            assert(is_bool($result['is_active']), 'is_active should be boolean');
            
        } catch (Exception $e) {
            // If creation fails due to duplicate email (from mock), that's acceptable
            if ($e->getCode() !== 409) {
                throw $e;
            }
        }
        
        echo "✓ User data structure test passed\n";
    }
    
    /**
     * Test email existence check
     */
    public function testEmailExistenceCheck() {
        // Test existing email (mocked)
        $exists = $this->user->emailExists('existing@example.com');
        assert($exists === true, 'Should return true for existing email');
        
        // Test non-existing email
        $exists = $this->user->emailExists('nonexistent@example.com');
        assert($exists === false, 'Should return false for non-existing email');
        
        echo "✓ Email existence check test passed\n";
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running User Model Simple Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testEmailValidation();
            $this->testPasswordValidation();
            $this->testRequiredFieldsValidation();
            $this->testDataSanitization();
            $this->testUserDataStructure();
            $this->testEmailExistenceCheck();
            
            echo "\n✅ All User Model simple tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserSimpleTest();
    $test->runAllTests();
}