<?php
/**
 * AuthService Property-Based Tests
 * 
 * Property-based tests for authentication service to verify universal
 * properties hold across all valid inputs and edge cases.
 * 
 * Requirements: 3.1, 3.2, 17.1
 */

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../models/Database.php';

class AuthServicePropertyTest extends PHPUnit\Framework\TestCase {
    private $authService;
    private $db;
    
    protected function setUp(): void {
        $this->authService = new AuthService();
        $this->db = Database::getInstance();
        $this->cleanupTestData();
    }
    
    protected function tearDown(): void {
        $this->cleanupTestData();
    }
    
    /**
     * **Validates: Requirements 3.1**
     * Property: JWT Token Roundtrip Consistency
     * For any valid user payload, generating a JWT token and then verifying it
     * should return the same payload data
     * 
     * @test
     */
    public function testJWTTokenRoundtripConsistency() {
        $jwtService = new JWTService();
        
        for ($i = 0; $i < 100; $i++) {
            // Generate random but valid user payload
            $originalPayload = $this->generateRandomUserPayload();
            
            // Generate token
            $token = $jwtService->generateAccessToken($originalPayload);
            
            // Verify token and extract payload
            $decodedPayload = $jwtService->verifyAccessToken($token);
            
            // Core user data should match
            $this->assertEquals($originalPayload['user_id'], $decodedPayload['user_id']);
            $this->assertEquals($originalPayload['email'], $decodedPayload['email']);
            $this->assertEquals($originalPayload['role'], $decodedPayload['role']);
            
            // Token should have proper structure
            $this->assertTrue($jwtService->isValidTokenFormat($token));
            $this->assertIsString($token);
            $this->assertCount(3, explode('.', $token));
        }
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Password Hash Verification Consistency
     * For any password, hashing it and then verifying should always return true,
     * while verifying with a different password should return false
     * 
     * @test
     */
    public function testPasswordHashVerificationConsistency() {
        for ($i = 0; $i < 100; $i++) {
            // Generate random password
            $password = $this->generateRandomPassword();
            $wrongPassword = $this->generateRandomPassword();
            
            // Ensure passwords are different
            if ($password === $wrongPassword) {
                $wrongPassword .= 'X';
            }
            
            // Hash password
            $hash = PasswordHash::hash($password);
            
            // Verify correct password always works
            $this->assertTrue(PasswordHash::verify($password, $hash));
            
            // Verify wrong password always fails
            $this->assertFalse(PasswordHash::verify($wrongPassword, $hash));
            
            // Hash should be bcrypt format
            $this->assertStringStartsWith('$2y$', $hash);
            $this->assertGreaterThan(50, strlen($hash)); // Bcrypt hashes are typically 60 chars
        }
    }
    
    /**
     * **Validates: Requirements 17.1**
     * Property: Token Pair Generation Consistency
     * For any user payload, generating a token pair should always produce
     * valid access and refresh tokens with consistent user data
     * 
     * @test
     */
    public function testTokenPairGenerationConsistency() {
        $jwtService = new JWTService();
        
        for ($i = 0; $i < 100; $i++) {
            $userPayload = $this->generateRandomUserPayload();
            
            // Generate token pair
            $tokens = $jwtService->generateTokenPair($userPayload);
            
            // Verify structure
            $this->assertArrayHasKey('access_token', $tokens);
            $this->assertArrayHasKey('refresh_token', $tokens);
            $this->assertArrayHasKey('token_type', $tokens);
            $this->assertArrayHasKey('expires_in', $tokens);
            $this->assertEquals('Bearer', $tokens['token_type']);
            
            // Verify both tokens contain same user data
            $accessPayload = $jwtService->verifyAccessToken($tokens['access_token']);
            $refreshPayload = $jwtService->verifyRefreshToken($tokens['refresh_token']);
            
            $this->assertEquals($userPayload['user_id'], $accessPayload['user_id']);
            $this->assertEquals($userPayload['user_id'], $refreshPayload['user_id']);
            $this->assertEquals($userPayload['email'], $accessPayload['email']);
            $this->assertEquals($userPayload['email'], $refreshPayload['email']);
            
            // Refresh token should have type marker
            $this->assertEquals('refresh', $refreshPayload['type']);
        }
    }
    
    /**
     * **Validates: Requirements 3.1, 3.2**
     * Property: Registration Data Sanitization
     * For any valid registration data, the system should sanitize and store
     * it correctly without exposing sensitive information
     * 
     * @test
     */
    public function testRegistrationDataSanitization() {
        for ($i = 0; $i < 50; $i++) {
            $userData = $this->generateRandomRegistrationData();
            
            try {
                $result = $this->authService->register($userData);
                
                // Verify sanitized user data doesn't contain password
                $user = $result['user'];
                $this->assertArrayNotHasKey('password', $user);
                $this->assertArrayNotHasKey('password_hash', $user);
                
                // Verify required fields are present
                $this->assertArrayHasKey('id', $user);
                $this->assertArrayHasKey('email', $user);
                $this->assertArrayHasKey('first_name', $user);
                $this->assertArrayHasKey('last_name', $user);
                $this->assertArrayHasKey('role', $user);
                
                // Verify email is properly formatted
                $this->assertIsString($user['email']);
                $this->assertTrue(filter_var($user['email'], FILTER_VALIDATE_EMAIL) !== false);
                
                // Verify role is valid
                $this->assertContains($user['role'], ['customer', 'admin']);
                
            } catch (Exception $e) {
                // If registration fails, it should be due to validation
                $this->assertStringContains('Validation failed', $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 3.1**
     * Property: Token Signature Integrity
     * For any token, modifying any part of it should make verification fail
     * 
     * @test
     */
    public function testTokenSignatureIntegrity() {
        $jwtService = new JWTService();
        
        for ($i = 0; $i < 50; $i++) {
            $userPayload = $this->generateRandomUserPayload();
            $token = $jwtService->generateAccessToken($userPayload);
            
            // Split token into parts
            $parts = explode('.', $token);
            $this->assertCount(3, $parts);
            
            // Modify each part and verify it fails
            foreach ([0, 1, 2] as $partIndex) {
                $modifiedParts = $parts;
                $modifiedParts[$partIndex] = $this->modifyBase64String($parts[$partIndex]);
                $modifiedToken = implode('.', $modifiedParts);
                
                // Modified token should fail verification
                try {
                    $jwtService->verifyAccessToken($modifiedToken);
                    $this->fail('Modified token should not verify successfully');
                } catch (Exception $e) {
                    $this->assertStringContains('Invalid', $e->getMessage());
                }
            }
        }
    }
    
    /**
     * **Validates: Requirements 17.1**
     * Property: Session Token Uniqueness
     * For any sequence of token generations, all tokens should be unique
     * 
     * @test
     */
    public function testSessionTokenUniqueness() {
        $jwtService = new JWTService();
        $generatedTokens = [];
        
        for ($i = 0; $i < 100; $i++) {
            $userPayload = $this->generateRandomUserPayload();
            $tokens = $jwtService->generateTokenPair($userPayload);
            
            // Check access token uniqueness
            $accessToken = $tokens['access_token'];
            $this->assertNotContains($accessToken, $generatedTokens, 'Access token should be unique');
            $generatedTokens[] = $accessToken;
            
            // Check refresh token uniqueness
            $refreshToken = $tokens['refresh_token'];
            $this->assertNotContains($refreshToken, $generatedTokens, 'Refresh token should be unique');
            $generatedTokens[] = $refreshToken;
        }
        
        // Verify we generated the expected number of unique tokens
        $this->assertCount(200, $generatedTokens); // 100 access + 100 refresh tokens
        $this->assertCount(200, array_unique($generatedTokens)); // All should be unique
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Password Hash Uniqueness
     * For the same password hashed multiple times, each hash should be unique
     * (due to salt), but all should verify correctly
     * 
     * @test
     */
    public function testPasswordHashUniqueness() {
        for ($i = 0; $i < 20; $i++) {
            $password = $this->generateRandomPassword();
            $hashes = [];
            
            // Generate multiple hashes of the same password
            for ($j = 0; $j < 10; $j++) {
                $hash = PasswordHash::hash($password);
                $hashes[] = $hash;
                
                // Each hash should verify the original password
                $this->assertTrue(PasswordHash::verify($password, $hash));
            }
            
            // All hashes should be unique (due to random salt)
            $this->assertCount(10, array_unique($hashes), 'Password hashes should be unique due to salt');
        }
    }
    
    /**
     * Generate random user payload for testing
     */
    private function generateRandomUserPayload() {
        return [
            'user_id' => rand(1, 999999),
            'email' => 'user' . rand(1000, 9999) . '@example.com',
            'role' => rand(0, 1) ? 'customer' : 'admin'
        ];
    }
    
    /**
     * Generate random password meeting requirements
     */
    private function generateRandomPassword() {
        $uppercase = chr(rand(65, 90));  // A-Z
        $lowercase = chr(rand(97, 122)); // a-z
        $number = chr(rand(48, 57));     // 0-9
        $special = ['!', '@', '#', '$', '%', '^', '&', '*'][rand(0, 7)];
        
        // Add random additional characters
        $additional = '';
        for ($i = 0; $i < rand(4, 12); $i++) {
            $type = rand(0, 3);
            switch ($type) {
                case 0: $additional .= chr(rand(65, 90)); break;  // Uppercase
                case 1: $additional .= chr(rand(97, 122)); break; // Lowercase
                case 2: $additional .= chr(rand(48, 57)); break;  // Number
                case 3: $additional .= ['!', '@', '#', '$', '%', '^', '&', '*'][rand(0, 7)]; break; // Special
            }
        }
        
        // Combine and shuffle
        $password = $uppercase . $lowercase . $number . $special . $additional;
        return str_shuffle($password);
    }
    
    /**
     * Generate random registration data
     */
    private function generateRandomRegistrationData() {
        $firstNames = ['John', 'Jane', 'Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
        
        return [
            'email' => 'test' . rand(10000, 99999) . '@example.com',
            'password' => $this->generateRandomPassword(),
            'first_name' => $firstNames[rand(0, count($firstNames) - 1)],
            'last_name' => $lastNames[rand(0, count($lastNames) - 1)],
            'phone' => rand(0, 1) ? '+1' . rand(1000000000, 9999999999) : null,
            'role' => rand(0, 9) === 0 ? 'admin' : 'customer' // 10% chance of admin
        ];
    }
    
    /**
     * Modify a base64 string slightly
     */
    private function modifyBase64String($base64String) {
        if (empty($base64String)) {
            return 'modified';
        }
        
        // Change one character
        $pos = rand(0, strlen($base64String) - 1);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $newChar = $chars[rand(0, strlen($chars) - 1)];
        
        return substr_replace($base64String, $newChar, $pos, 1);
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        try {
            // Delete test users (those with test emails)
            $sql = "DELETE FROM users WHERE email LIKE 'test%@example.com' OR email LIKE 'user%@example.com'";
            $this->db->executeQuery($sql);
            
            // Clean up orphaned tokens
            $sql = "DELETE FROM refresh_tokens WHERE user_id NOT IN (SELECT id FROM users)";
            $this->db->executeQuery($sql);
            
            $sql = "DELETE FROM password_resets WHERE user_id NOT IN (SELECT id FROM users)";
            $this->db->executeQuery($sql);
            
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}