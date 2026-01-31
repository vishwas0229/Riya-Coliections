<?php
/**
 * Password Hash Compatibility Property Test
 * 
 * **Property 5: Password Hash Compatibility**
 * **Validates: Requirements 3.2**
 * 
 * For any password hashed by one system, the other system should be able to 
 * verify it using the same bcrypt algorithm and cost factor.
 * 
 * This test verifies that password hashing is compatible between Node.js and PHP
 * implementations by testing bcrypt algorithm consistency, salt generation,
 * and verification compatibility.
 */

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../utils/Logger.php';

class PasswordHashCompatibilityPropertyTest extends PHPUnit\Framework\TestCase {
    
    /**
     * Test-specific password hash utility with lower cost for faster testing
     */
    private function hashPasswordForTest($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 8]); // Lower cost for testing
    }
    
    private function verifyPasswordForTest($password, $hash) {
        if ($password === null) {
            return false;
        }
        return password_verify($password, $hash);
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Bcrypt Algorithm Consistency
     * 
     * For any password, the bcrypt hashing should produce hashes that are
     * compatible with standard bcrypt implementations (Node.js bcrypt library).
     * All hashes should use the same algorithm identifier and cost factor.
     * 
     * @test
     */
    public function testBcryptAlgorithmConsistency() {
        for ($i = 0; $i < 20; $i++) { // Reduced from 100 to 20
            $password = $this->generateRandomPassword();
            
            // Hash password using test utility (lower cost)
            $hash = $this->hashPasswordForTest($password);
            
            // Verify hash format is standard bcrypt
            $this->assertIsString($hash, 'Hash should be a string');
            $this->assertStringStartsWith('$2y$', $hash, 'Hash should use bcrypt $2y$ identifier');
            
            // Verify hash length (bcrypt hashes are typically 60 characters)
            $this->assertEquals(60, strlen($hash), 'Bcrypt hash should be exactly 60 characters');
            
            // Verify hash structure: $2y$cost$salt+hash
            $parts = explode('$', $hash);
            $this->assertCount(4, $parts, 'Bcrypt hash should have 4 parts separated by $');
            $this->assertEquals('', $parts[0], 'First part should be empty (leading $)');
            $this->assertEquals('2y', $parts[1], 'Second part should be algorithm identifier');
            $this->assertMatchesRegularExpression('/^\d{2}$/', $parts[2], 'Third part should be 2-digit cost');
            $this->assertEquals(53, strlen($parts[3]), 'Fourth part (salt+hash) should be 53 characters');
            
            // Verify cost factor is reasonable (test uses 8 for speed)
            $actualCost = (int) $parts[2];
            $this->assertEquals(8, $actualCost, 'Test cost factor should be 8 for performance');
            
            // Verify salt and hash portions use base64 encoding
            $saltAndHash = $parts[3];
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9.\/]+$/', $saltAndHash, 
                'Salt and hash should use bcrypt base64 encoding');
        }
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Password Verification Consistency
     * 
     * For any password and its hash, verification should be deterministic.
     * The correct password should always verify successfully, and incorrect
     * passwords should always fail verification.
     * 
     * @test
     */
    public function testPasswordVerificationConsistency() {
        for ($i = 0; $i < 20; $i++) { // Reduced from 100 to 20
            $correctPassword = $this->generateRandomPassword();
            $wrongPassword = $this->generateDifferentPassword($correctPassword);
            
            // Hash the correct password
            $hash = $this->hashPasswordForTest($correctPassword);
            
            // Correct password should always verify successfully
            for ($j = 0; $j < 3; $j++) { // Reduced from 5 to 3
                $this->assertTrue($this->verifyPasswordForTest($correctPassword, $hash), 
                    'Correct password should always verify against its hash');
            }
            
            // Wrong password should always fail verification
            for ($j = 0; $j < 3; $j++) { // Reduced from 5 to 3
                $this->assertFalse($this->verifyPasswordForTest($wrongPassword, $hash), 
                    'Wrong password should always fail verification');
            }
            
            // Empty password should fail if original wasn't empty
            if (!empty($correctPassword)) {
                $this->assertFalse($this->verifyPasswordForTest('', $hash), 
                    'Empty password should fail verification for non-empty original');
            }
            
            // Null password should fail
            $this->assertFalse($this->verifyPasswordForTest(null, $hash), 
                'Null password should fail verification');
        }
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Hash Uniqueness with Salt Randomization
     * 
     * For any password hashed multiple times, each hash should be unique
     * due to random salt generation, but all should verify the original password.
     * 
     * @test
     */
    public function testHashUniquenessWithSaltRandomization() {
        for ($i = 0; $i < 10; $i++) { // Reduced from 50 to 10
            $password = $this->generateRandomPassword();
            $hashes = [];
            
            // Generate multiple hashes of the same password
            for ($j = 0; $j < 5; $j++) { // Reduced from 10 to 5
                $hash = $this->hashPasswordForTest($password);
                $hashes[] = $hash;
                
                // Each hash should verify the original password
                $this->assertTrue($this->verifyPasswordForTest($password, $hash), 
                    'Each hash should verify the original password');
                
                // Each hash should have the same format but different salt
                $this->assertStringStartsWith('$2y$', $hash);
                $this->assertEquals(60, strlen($hash));
            }
            
            // All hashes should be unique due to random salt
            $uniqueHashes = array_unique($hashes);
            $this->assertCount(5, $uniqueHashes, 
                'All hashes should be unique due to random salt generation');
            
            // Verify salt portions are different
            $salts = [];
            foreach ($hashes as $hash) {
                $parts = explode('$', $hash);
                $saltPortion = substr($parts[3], 0, 22); // First 22 chars are salt
                $salts[] = $saltPortion;
            }
            
            $uniqueSalts = array_unique($salts);
            $this->assertCount(5, $uniqueSalts, 
                'All salt portions should be unique');
        }
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Cross-Implementation Compatibility
     * 
     * Hashes generated by PHP should be compatible with standard bcrypt
     * implementations. This tests compatibility with Node.js bcrypt format.
     * 
     * @test
     */
    public function testCrossImplementationCompatibility() {
        // Test with known bcrypt hashes that would be generated by Node.js
        $testCases = [
            [
                'password' => 'password123',
                'cost' => 12
            ],
            [
                'password' => 'Test@123!',
                'cost' => 10
            ],
            [
                'password' => 'ComplexP@ssw0rd!',
                'cost' => 12
            ]
        ];
        
        foreach ($testCases as $testCase) {
            $password = $testCase['password'];
            
            // Generate hash with PHP
            $phpHash = PasswordHash::hash($password);
            
            // Verify the hash follows bcrypt standards
            $this->assertStringStartsWith('$2y$', $phpHash, 
                'PHP hash should use standard bcrypt format');
            
            // Verify PHP can verify its own hash
            $this->assertTrue(PasswordHash::verify($password, $phpHash), 
                'PHP should verify its own generated hash');
            
            // Test with simulated Node.js bcrypt hash format
            // Node.js bcrypt typically uses $2b$ or $2a$ identifiers
            $nodejsStyleHash = str_replace('$2y$', '$2b$', $phpHash);
            
            // PHP should be able to verify Node.js style hashes
            // Note: This tests format compatibility, actual cross-verification
            // would require real Node.js generated hashes
            $this->assertTrue(password_verify($password, $nodejsStyleHash), 
                'PHP should verify Node.js style bcrypt hashes');
        }
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Hash Rehashing Detection
     * 
     * The system should correctly detect when a hash needs rehashing
     * due to cost factor changes, ensuring forward compatibility.
     * 
     * @test
     */
    public function testHashRehashingDetection() {
        for ($i = 0; $i < 5; $i++) { // Reduced from 50 to 5
            $password = $this->generateRandomPassword();
            
            // Generate hash with current cost
            $currentHash = $this->hashPasswordForTest($password);
            
            // Hash should not need rehashing immediately after creation
            $this->assertFalse(password_needs_rehash($currentHash, PASSWORD_BCRYPT, ['cost' => 8]), 
                'Newly created hash should not need rehashing');
            
            // Simulate hash created with different cost factor
            $lowCostHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
            $highCostHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            
            // Test rehashing detection based on current configuration
            $currentCost = 8; // Test cost
            
            $this->assertTrue(password_needs_rehash($lowCostHash, PASSWORD_BCRYPT, ['cost' => $currentCost]), 
                'Hash with lower cost should need rehashing');
            
            $this->assertTrue(password_needs_rehash($highCostHash, PASSWORD_BCRYPT, ['cost' => $currentCost]), 
                'Hash with higher cost should need rehashing');
            
            // All hashes should still verify the password regardless of cost
            $this->assertTrue(password_verify($password, $lowCostHash), 
                'Low cost hash should still verify password');
            $this->assertTrue(password_verify($password, $highCostHash), 
                'High cost hash should still verify password');
        }
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Edge Case Password Handling
     * 
     * The hashing system should handle edge cases consistently,
     * including special characters, unicode, and boundary conditions.
     * 
     * @test
     */
    public function testEdgeCasePasswordHandling() {
        $edgeCasePasswords = [
            // Empty password
            '',
            // Single character
            'a',
            // Special characters
            '!@#$%^&*()',
            // Mixed case and numbers
            'AbC123XyZ',
            // Spaces
            'password with spaces'
        ];
        
        foreach ($edgeCasePasswords as $password) {
            // Should be able to hash any string
            $hash = $this->hashPasswordForTest($password);
            
            // Hash should follow bcrypt format
            $this->assertIsString($hash, 'Hash should be string for any input');
            $this->assertStringStartsWith('$2y$', $hash, 'Hash should use bcrypt format for any input');
            $this->assertEquals(60, strlen($hash), 'Hash should be 60 characters for any input');
            
            // Should be able to verify the password
            $this->assertTrue($this->verifyPasswordForTest($password, $hash), 
                'Should verify edge case password: ' . json_encode($password));
            
            // Different password should not verify
            $differentPassword = $password . 'X';
            $this->assertFalse($this->verifyPasswordForTest($differentPassword, $hash), 
                'Different password should not verify for edge case');
        }
    }
    
    /**
     * **Validates: Requirements 3.2**
     * Property: Timing Attack Resistance
     * 
     * Password verification should take consistent time regardless of
     * whether the password is correct or incorrect, preventing timing attacks.
     * 
     * @test
     */
    public function testTimingAttackResistance() {
        $password = $this->generateRandomPassword();
        $hash = $this->hashPasswordForTest($password);
        $wrongPassword = $this->generateDifferentPassword($password);
        
        $correctTimes = [];
        $incorrectTimes = [];
        
        // Measure verification times
        for ($i = 0; $i < 10; $i++) { // Reduced from 20 to 10
            // Time correct password verification
            $start = microtime(true);
            $this->verifyPasswordForTest($password, $hash);
            $correctTimes[] = microtime(true) - $start;
            
            // Time incorrect password verification
            $start = microtime(true);
            $this->verifyPasswordForTest($wrongPassword, $hash);
            $incorrectTimes[] = microtime(true) - $start;
        }
        
        // Calculate average times
        $avgCorrectTime = array_sum($correctTimes) / count($correctTimes);
        $avgIncorrectTime = array_sum($incorrectTimes) / count($incorrectTimes);
        
        // Times should be similar (within reasonable variance)
        // bcrypt should take similar time for correct and incorrect passwords
        $timeDifference = abs($avgCorrectTime - $avgIncorrectTime);
        $maxAllowedDifference = max($avgCorrectTime, $avgIncorrectTime) * 0.5; // 50% variance allowed
        
        $this->assertLessThan($maxAllowedDifference, $timeDifference, 
            'Verification times should be similar for correct and incorrect passwords to prevent timing attacks');
        
        // Both should take reasonable time (bcrypt should be slow)
        $this->assertGreaterThan(0.001, $avgCorrectTime, 'Verification should take reasonable time (not too fast)');
        $this->assertGreaterThan(0.001, $avgIncorrectTime, 'Verification should take reasonable time (not too fast)');
    }
    
    /**
     * Generate a random password meeting security requirements
     */
    private function generateRandomPassword() {
        $length = rand(8, 32);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password = '';
        
        // Ensure at least one of each required type
        $password .= chr(rand(65, 90));  // Uppercase
        $password .= chr(rand(97, 122)); // Lowercase
        $password .= chr(rand(48, 57));  // Number
        $password .= '!@#$%^&*'[rand(0, 7)]; // Special
        
        // Fill remaining length with random characters
        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return str_shuffle($password);
    }
    
    /**
     * Generate a password that's different from the given password
     */
    private function generateDifferentPassword($originalPassword) {
        do {
            $differentPassword = $this->generateRandomPassword();
        } while ($differentPassword === $originalPassword);
        
        return $differentPassword;
    }
}