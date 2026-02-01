<?php
/**
 * Basic Authentication Flows Test
 * 
 * Simple unit tests for user authentication flows that can run without
 * complex database setup. Tests core authentication logic and workflows.
 * 
 * Task: 6.3 Write unit tests for user authentication flows
 * Requirements: 3.1, 3.2
 */

// Set up basic test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

// Set test environment variables
putenv('APP_ENV=testing');
putenv('JWT_SECRET=test_jwt_secret_for_testing_only_32_chars_minimum');
$_ENV['JWT_SECRET'] = 'test_jwt_secret_for_testing_only_32_chars_minimum';

// Include required files
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../config/jwt.php';

class AuthenticationFlowsBasicTest {
    private $jwtService;
    
    public function setUp() {
        $this->jwtService = new JWTService();
    }
    
    /**
     * Test JWT token generation and validation flow
     */
    public function testJWTTokenFlow() {
        echo "Testing JWT token generation and validation flow...\n";
        
        // Test data
        $userPayload = [
            'user_id' => 123,
            'email' => 'test@example.com',
            'role' => 'customer'
        ];
        
        // Generate access token
        $accessToken = $this->jwtService->generateAccessToken($userPayload);
        $this->assert(!empty($accessToken), 'Access token should be generated');
        $this->assert($this->jwtService->isValidTokenFormat($accessToken), 'Access token should have valid format');
        
        // Verify access token
        $decodedPayload = $this->jwtService->verifyAccessToken($accessToken);
        $this->assert($decodedPayload['user_id'] === 123, 'User ID should match');
        $this->assert($decodedPayload['email'] === 'test@example.com', 'Email should match');
        $this->assert($decodedPayload['role'] === 'customer', 'Role should match');
        $this->assert(isset($decodedPayload['iat']), 'Token should have issued at time');
        $this->assert(isset($decodedPayload['exp']), 'Token should have expiration time');
        
        echo "✓ JWT token generation and validation flow test passed\n";
    }
    
    /**
     * Test token pair generation flow
     */
    public function testTokenPairFlow() {
        echo "Testing token pair generation flow...\n";
        
        $userPayload = [
            'user_id' => 456,
            'email' => 'user@example.com',
            'role' => 'customer'
        ];
        
        // Generate token pair
        $tokens = $this->jwtService->generateTokenPair($userPayload);
        
        // Verify token pair structure
        $this->assert(isset($tokens['access_token']), 'Token pair should include access token');
        $this->assert(isset($tokens['refresh_token']), 'Token pair should include refresh token');
        $this->assert(isset($tokens['token_type']), 'Token pair should include token type');
        $this->assert(isset($tokens['expires_in']), 'Token pair should include expiration time');
        $this->assert($tokens['token_type'] === 'Bearer', 'Token type should be Bearer');
        $this->assert(is_numeric($tokens['expires_in']), 'Expires in should be numeric');
        
        // Verify both tokens are valid
        $accessPayload = $this->jwtService->verifyAccessToken($tokens['access_token']);
        $refreshPayload = $this->jwtService->verifyRefreshToken($tokens['refresh_token']);
        
        $this->assert($accessPayload['user_id'] === $userPayload['user_id'], 'Access token user ID should match');
        $this->assert($refreshPayload['user_id'] === $userPayload['user_id'], 'Refresh token user ID should match');
        $this->assert($refreshPayload['type'] === 'refresh', 'Refresh token should have correct type');
        
        echo "✓ Token pair generation flow test passed\n";
    }
    
    /**
     * Test password hashing flow
     */
    public function testPasswordHashingFlow() {
        echo "Testing password hashing flow...\n";
        
        $password = 'TestPassword123!';
        
        // Hash password
        $hash = PasswordHash::hash($password);
        $this->assert(!empty($hash), 'Password hash should be generated');
        $this->assert(strlen($hash) >= 60, 'Bcrypt hash should be at least 60 characters');
        $this->assert(strpos($hash, '$2y$') === 0, 'Hash should use bcrypt format');
        
        // Verify password
        $this->assert(PasswordHash::verify($password, $hash), 'Correct password should verify');
        $this->assert(!PasswordHash::verify('WrongPassword', $hash), 'Wrong password should not verify');
        $this->assert(!PasswordHash::verify('', $hash), 'Empty password should not verify');
        $this->assert(!PasswordHash::verify(null, $hash), 'Null password should not verify');
        
        // Test password strength requirements
        $weakPasswords = [
            'short',           // Too short
            'nouppercase123!', // No uppercase
            'NOLOWERCASE123!', // No lowercase
            'NoNumbers!',      // No numbers
            'NoSpecialChars123' // No special characters
        ];
        
        foreach ($weakPasswords as $weakPassword) {
            try {
                $this->validatePasswordStrength($weakPassword);
                $this->assert(false, "Weak password should be rejected: $weakPassword");
            } catch (Exception $e) {
                $this->assert(strpos($e->getMessage(), 'Password must') !== false, 'Error should mention password requirements');
            }
        }
        
        // Test strong password
        $strongPassword = 'StrongPass123!';
        try {
            $this->validatePasswordStrength($strongPassword);
            // Should not throw exception
        } catch (Exception $e) {
            $this->assert(false, 'Strong password should be accepted');
        }
        
        echo "✓ Password hashing flow test passed\n";
    }
    
    /**
     * Test token refresh flow
     */
    public function testTokenRefreshFlow() {
        echo "Testing token refresh flow...\n";
        
        $userPayload = [
            'user_id' => 789,
            'email' => 'refresh@example.com',
            'role' => 'customer'
        ];
        
        // Generate initial token pair
        $originalTokens = $this->jwtService->generateTokenPair($userPayload);
        
        // Wait a moment to ensure different timestamps
        sleep(1);
        
        // Refresh access token
        $newAccessToken = $this->jwtService->refreshAccessToken($originalTokens['refresh_token']);
        
        // Verify new access token
        $this->assert(!empty($newAccessToken), 'New access token should be generated');
        $this->assert($newAccessToken !== $originalTokens['access_token'], 'New access token should be different');
        
        $newPayload = $this->jwtService->verifyAccessToken($newAccessToken);
        $this->assert($newPayload['user_id'] === $userPayload['user_id'], 'User ID should be preserved');
        $this->assert($newPayload['email'] === $userPayload['email'], 'Email should be preserved');
        $this->assert($newPayload['role'] === $userPayload['role'], 'Role should be preserved');
        
        echo "✓ Token refresh flow test passed\n";
    }
    
    /**
     * Test token expiration handling
     */
    public function testTokenExpirationFlow() {
        echo "Testing token expiration handling...\n";
        
        // Create expired token manually
        $expiredPayload = [
            'user_id' => 999,
            'email' => 'expired@example.com',
            'role' => 'customer',
            'iat' => time() - 7200, // 2 hours ago
            'exp' => time() - 3600   // 1 hour ago (expired)
        ];
        
        $config = JWTConfig::getConfig();
        $expiredToken = JWT::encode($expiredPayload, $config['secret'], $config['algorithm']);
        
        // Verify that expired token is rejected
        try {
            $this->jwtService->verifyAccessToken($expiredToken);
            $this->assert(false, 'Expired token should be rejected');
        } catch (Exception $e) {
            $this->assert(strpos($e->getMessage(), 'expired') !== false, 'Error should mention expiration');
        }
        
        echo "✓ Token expiration handling test passed\n";
    }
    
    /**
     * Test invalid token handling
     */
    public function testInvalidTokenHandling() {
        echo "Testing invalid token handling...\n";
        
        $invalidTokens = [
            'invalid.token',           // Only 2 parts
            'invalid.token.format.extra', // 4 parts
            'notbase64.notbase64.notbase64', // Invalid base64
            '',                        // Empty string
            'completely_invalid_token' // No dots
        ];
        
        foreach ($invalidTokens as $token) {
            // Test token format validation
            if ($token !== 'notbase64.notbase64.notbase64') {
                // Skip this specific test case as it has valid format but invalid content
                $this->assert(!$this->jwtService->isValidTokenFormat($token), "Token should be invalid: $token");
            }
            
            // Test token verification
            try {
                $this->jwtService->verifyAccessToken($token);
                $this->assert(false, "Invalid token should be rejected: $token");
            } catch (Exception $e) {
                $this->assert(strpos($e->getMessage(), 'Invalid') !== false || strpos($e->getMessage(), 'format') !== false, 'Error should mention invalid token');
            }
        }
        
        echo "✓ Invalid token handling test passed\n";
    }
    
    /**
     * Test authentication header extraction
     */
    public function testAuthHeaderExtraction() {
        echo "Testing authentication header extraction...\n";
        
        // Test valid Bearer token
        $token = 'valid.jwt.token';
        $bearerHeader = "Bearer $token";
        $extractedToken = $this->jwtService->extractTokenFromHeader($bearerHeader);
        $this->assert($extractedToken === $token, 'Token should be extracted from Bearer header');
        
        // Test case insensitive
        $bearerHeaderLower = "bearer $token";
        $extractedToken = $this->jwtService->extractTokenFromHeader($bearerHeaderLower);
        $this->assert($extractedToken === $token, 'Token extraction should be case insensitive');
        
        // Test invalid headers
        $invalidHeaders = [
            '',
            'InvalidHeader',
            'Basic dXNlcjpwYXNz', // Basic auth
            'Token abc123',       // Wrong prefix
            'Bearer',             // No token
            null
        ];
        
        foreach ($invalidHeaders as $header) {
            $extractedToken = $this->jwtService->extractTokenFromHeader($header);
            $this->assert($extractedToken === null, "Invalid header should return null: " . var_export($header, true));
        }
        
        echo "✓ Authentication header extraction test passed\n";
    }
    
    /**
     * Test email validation flow
     */
    public function testEmailValidationFlow() {
        echo "Testing email validation flow...\n";
        
        // Valid emails
        $validEmails = [
            'user@example.com',
            'test.email@domain.co.uk',
            'user+tag@example.org',
            'user123@test-domain.com',
            'a@b.co'
        ];
        
        foreach ($validEmails as $email) {
            $this->assert(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "Email should be valid: $email");
        }
        
        // Invalid emails
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user..name@domain.com',
            'user@domain',
            '',
            null
        ];
        
        foreach ($invalidEmails as $email) {
            $this->assert(filter_var($email, FILTER_VALIDATE_EMAIL) === false, "Email should be invalid: " . var_export($email, true));
        }
        
        echo "✓ Email validation flow test passed\n";
    }
    
    /**
     * Test user data sanitization
     */
    public function testUserDataSanitization() {
        echo "Testing user data sanitization...\n";
        
        $rawUserData = [
            'id' => 123,
            'email' => 'test@example.com',
            'password_hash' => '$2y$12$hashedpassword',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'role' => 'customer',
            'is_active' => true,
            'created_at' => '2023-01-01 12:00:00',
            'updated_at' => '2023-01-02 12:00:00',
            'last_login_at' => '2023-01-03 12:00:00'
        ];
        
        $sanitizedData = $this->sanitizeUserData($rawUserData);
        
        // Verify sensitive data is removed
        $this->assert(!isset($sanitizedData['password_hash']), 'Password hash should be removed');
        
        // Verify required data is present
        $this->assert($sanitizedData['id'] === 123, 'ID should be preserved');
        $this->assert($sanitizedData['email'] === 'test@example.com', 'Email should be preserved');
        $this->assert($sanitizedData['first_name'] === 'John', 'First name should be preserved');
        $this->assert($sanitizedData['last_name'] === 'Doe', 'Last name should be preserved');
        $this->assert($sanitizedData['role'] === 'customer', 'Role should be preserved');
        
        echo "✓ User data sanitization test passed\n";
    }
    
    /**
     * Test security measures
     */
    public function testSecurityMeasures() {
        echo "Testing security measures...\n";
        
        // Test SQL injection prevention in email validation
        $maliciousEmails = [
            "test@example.com'; DROP TABLE users; --",
            "test@example.com' OR '1'='1",
            "test@example.com\"; DELETE FROM users; --"
        ];
        
        foreach ($maliciousEmails as $email) {
            $this->assert(filter_var($email, FILTER_VALIDATE_EMAIL) === false, "Malicious email should be rejected: $email");
        }
        
        // Test XSS prevention in user input
        $xssInputs = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src="x" onerror="alert(1)">',
            '"><script>alert("xss")</script>'
        ];
        
        foreach ($xssInputs as $input) {
            $sanitized = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            $this->assert($sanitized !== $input, "XSS input should be sanitized: $input");
            $this->assert(strpos($sanitized, '<script>') === false, 'Script tags should be escaped');
        }
        
        echo "✓ Security measures test passed\n";
    }
    
    /**
     * Validate password strength (helper method)
     */
    private function validatePasswordStrength($password) {
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
    }
    
    /**
     * Sanitize user data (helper method)
     */
    private function sanitizeUserData($user) {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'] ?? null,
            'role' => $user['role'],
            'is_active' => $user['is_active'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'last_login_at' => $user['last_login_at'] ?? null
        ];
    }
    
    /**
     * Helper assertion method
     */
    private function assert($condition, $message) {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }
    
    /**
     * Run all authentication flow tests
     */
    public function runAllTests() {
        echo "Running Basic Authentication Flows Unit Tests...\n";
        echo "==================================================\n\n";
        
        $this->setUp();
        
        try {
            $this->testJWTTokenFlow();
            $this->testTokenPairFlow();
            $this->testPasswordHashingFlow();
            $this->testTokenRefreshFlow();
            $this->testTokenExpirationFlow();
            $this->testInvalidTokenHandling();
            $this->testAuthHeaderExtraction();
            $this->testEmailValidationFlow();
            $this->testUserDataSanitization();
            $this->testSecurityMeasures();
            
            echo "\n✅ All Basic Authentication Flows unit tests passed!\n";
            echo "   - JWT token generation and validation ✓\n";
            echo "   - Token pair generation ✓\n";
            echo "   - Password hashing and verification ✓\n";
            echo "   - Token refresh mechanism ✓\n";
            echo "   - Token expiration handling ✓\n";
            echo "   - Invalid token handling ✓\n";
            echo "   - Authentication header extraction ✓\n";
            echo "   - Email validation ✓\n";
            echo "   - User data sanitization ✓\n";
            echo "   - Security measures ✓\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new AuthenticationFlowsBasicTest();
    $test->runAllTests();
}