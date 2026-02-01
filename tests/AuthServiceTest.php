<?php
/**
 * AuthService Unit Tests
 * 
 * Tests for the authentication service including JWT token handling,
 * password hashing, and user authentication functionality.
 * 
 * Requirements: 3.1, 3.2, 17.1
 */

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../models/Database.php';

class AuthServiceTest extends PHPUnit\Framework\TestCase {
    private $authService;
    private $db;
    
    protected function setUp(): void {
        $this->authService = new AuthService();
        $this->db = Database::getInstance();
        
        // Clean up test data
        $this->cleanupTestData();
    }
    
    protected function tearDown(): void {
        // Clean up test data after each test
        $this->cleanupTestData();
    }
    
    /**
     * Test JWT token generation and validation
     */
    public function testJWTTokenGeneration() {
        $jwtService = new JWTService();
        
        $payload = [
            'user_id' => 123,
            'email' => 'test@example.com',
            'role' => 'customer'
        ];
        
        // Generate token
        $token = $jwtService->generateAccessToken($payload);
        
        // Verify token format
        $this->assertIsString($token);
        $this->assertTrue($jwtService->isValidTokenFormat($token));
        
        // Verify token content
        $decoded = $jwtService->verifyAccessToken($token);
        $this->assertEquals(123, $decoded['user_id']);
        $this->assertEquals('test@example.com', $decoded['email']);
        $this->assertEquals('customer', $decoded['role']);
    }
    
    /**
     * Test password hashing compatibility
     */
    public function testPasswordHashing() {
        $password = 'TestPassword123!';
        
        // Hash password
        $hash = PasswordHash::hash($password);
        
        // Verify hash format (bcrypt)
        $this->assertIsString($hash);
        $this->assertStringStartsWith('$2y$', $hash);
        
        // Verify password
        $this->assertTrue(PasswordHash::verify($password, $hash));
        $this->assertFalse(PasswordHash::verify('WrongPassword', $hash));
    }
    
    /**
     * Test token refresh mechanism
     */
    public function testTokenRefresh() {
        $jwtService = new JWTService();
        
        $payload = [
            'user_id' => 123,
            'email' => 'test@example.com',
            'role' => 'customer'
        ];
        
        // Generate token pair
        $tokens = $jwtService->generateTokenPair($payload);
        
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
        
        // Verify both tokens
        $accessPayload = $jwtService->verifyAccessToken($tokens['access_token']);
        $refreshPayload = $jwtService->verifyRefreshToken($tokens['refresh_token']);
        
        $this->assertEquals($payload['user_id'], $accessPayload['user_id']);
        $this->assertEquals($payload['user_id'], $refreshPayload['user_id']);
        $this->assertEquals('refresh', $refreshPayload['type']);
    }
    
    /**
     * Test user registration
     */
    public function testUserRegistration() {
        $userData = [
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890'
        ];
        
        $result = $this->authService->register($userData);
        
        // Verify result structure
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('tokens', $result);
        
        // Verify user data
        $user = $result['user'];
        $this->assertEquals($userData['email'], $user['email']);
        $this->assertEquals($userData['first_name'], $user['first_name']);
        $this->assertEquals($userData['last_name'], $user['last_name']);
        $this->assertEquals($userData['phone'], $user['phone']);
        $this->assertEquals('customer', $user['role']);
        
        // Verify tokens
        $tokens = $result['tokens'];
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
    }
    
    /**
     * Test user login
     */
    public function testUserLogin() {
        // First register a user
        $userData = [
            'email' => 'logintest@example.com',
            'password' => 'LoginPass123!',
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ];
        
        $this->authService->register($userData);
        
        // Now test login
        $result = $this->authService->login($userData['email'], $userData['password']);
        
        // Verify result structure
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('tokens', $result);
        
        // Verify user data
        $user = $result['user'];
        $this->assertEquals($userData['email'], $user['email']);
        $this->assertEquals($userData['first_name'], $user['first_name']);
        
        // Test invalid login
        $this->expectException(Exception::class);
        $this->authService->login($userData['email'], 'WrongPassword');
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
                    'email' => 'test@example.com',
                    'password' => $password,
                    'first_name' => 'Test',
                    'last_name' => 'User'
                ];
                
                $this->authService->register($userData);
                $this->fail("Expected exception for weak password: $password");
            } catch (Exception $e) {
                $this->assertStringContains('Password must', $e->getMessage());
            }
        }
        
        // Test strong password
        $strongPassword = 'StrongPass123!';
        $userData = [
            'email' => 'strongpass@example.com',
            'password' => $strongPassword,
            'first_name' => 'Strong',
            'last_name' => 'User'
        ];
        
        $result = $this->authService->register($userData);
        $this->assertArrayHasKey('user', $result);
    }
    
    /**
     * Test duplicate email registration
     */
    public function testDuplicateEmailRegistration() {
        $userData = [
            'email' => 'duplicate@example.com',
            'password' => 'Password123!',
            'first_name' => 'First',
            'last_name' => 'User'
        ];
        
        // Register first user
        $this->authService->register($userData);
        
        // Try to register with same email
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User with this email already exists');
        $this->authService->register($userData);
    }
    
    /**
     * Test token expiration
     */
    public function testTokenExpiration() {
        $jwtService = new JWTService();
        
        // Create expired token manually
        $payload = [
            'user_id' => 123,
            'email' => 'test@example.com',
            'role' => 'customer',
            'iat' => time() - 7200, // 2 hours ago
            'exp' => time() - 3600   // 1 hour ago (expired)
        ];
        
        $config = JWTConfig::getConfig();
        $expiredToken = JWT::encode($payload, $config['secret'], $config['algorithm']);
        
        // Verify that expired token is rejected
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Token has expired');
        $jwtService->verifyAccessToken($expiredToken);
    }
    
    /**
     * Test invalid token format
     */
    public function testInvalidTokenFormat() {
        $jwtService = new JWTService();
        
        $invalidTokens = [
            'invalid.token',           // Only 2 parts
            'invalid.token.format.extra', // 4 parts
            'notbase64.notbase64.notbase64', // Invalid base64
            '',                        // Empty string
            null                       // Null value
        ];
        
        foreach ($invalidTokens as $token) {
            $this->assertFalse($jwtService->isValidTokenFormat($token));
            
            try {
                $jwtService->verifyAccessToken($token);
                $this->fail("Expected exception for invalid token: " . var_export($token, true));
            } catch (Exception $e) {
                $this->assertStringContains('Invalid', $e->getMessage());
            }
        }
    }
    
    /**
     * Test security measures
     */
    public function testSecurityMeasures() {
        // Test SQL injection prevention in email
        $maliciousEmail = "test@example.com'; DROP TABLE users; --";
        
        try {
            $userData = [
                'email' => $maliciousEmail,
                'password' => 'Password123!',
                'first_name' => 'Malicious',
                'last_name' => 'User'
            ];
            
            $this->authService->register($userData);
            $this->fail('Expected validation error for malicious email');
        } catch (Exception $e) {
            $this->assertStringContains('Invalid email format', $e->getMessage());
        }
        
        // Verify users table still exists
        $sql = "SELECT COUNT(*) as count FROM users";
        $stmt = $this->db->executeQuery($sql);
        $result = $stmt->fetch();
        $this->assertIsArray($result);
    }
    
    /**
     * Test bcrypt compatibility with Node.js
     */
    public function testBcryptCompatibility() {
        // Test with a known bcrypt hash from Node.js
        $password = 'TestPassword123';
        $nodeJsHash = '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/VcQjLSeHu';
        
        // PHP should be able to verify Node.js bcrypt hashes
        $this->assertTrue(PasswordHash::verify($password, $nodeJsHash));
        
        // Generate PHP hash and verify it works
        $phpHash = PasswordHash::hash($password);
        $this->assertTrue(PasswordHash::verify($password, $phpHash));
        
        // Verify different passwords don't match
        $this->assertFalse(PasswordHash::verify('WrongPassword', $phpHash));
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        try {
            // Delete test users
            $testEmails = [
                'newuser@example.com',
                'logintest@example.com',
                'strongpass@example.com',
                'duplicate@example.com',
                'test@example.com'
            ];
            
            foreach ($testEmails as $email) {
                $sql = "DELETE FROM users WHERE email = ?";
                $this->db->executeQuery($sql, [$email]);
            }
            
            // Clean up related data
            $sql = "DELETE FROM refresh_tokens WHERE user_id NOT IN (SELECT id FROM users)";
            $this->db->executeQuery($sql);
            
            $sql = "DELETE FROM password_resets WHERE user_id NOT IN (SELECT id FROM users)";
            $this->db->executeQuery($sql);
            
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}