<?php
/**
 * AuthController Unit Tests
 * 
 * Tests for the AuthController authentication endpoints including
 * user registration, login, profile management, and admin authentication.
 * 
 * Requirements: 3.1, 3.2, 11.1
 */

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/bootstrap.php';

class AuthControllerTest extends PHPUnit\Framework\TestCase {
    private $authController;
    private $testUser;
    private $testAdmin;
    
    protected function setUp(): void {
        $this->authController = new AuthController();
        
        // Create test user data
        $this->testUser = [
            'email' => 'test@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+1234567890'
        ];
        
        // Create test admin data
        $this->testAdmin = [
            'email' => 'admin@example.com',
            'password' => 'AdminPass123!',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'admin'
        ];
        
        // Clean up any existing test data
        $this->cleanupTestData();
    }
    
    protected function tearDown(): void {
        $this->cleanupTestData();
    }
    
    /**
     * Test user registration endpoint
     */
    public function testUserRegistration() {
        // Mock request input
        $this->mockJsonInput($this->testUser);
        
        // Capture output
        ob_start();
        $this->authController->register();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('User registered successfully', $response['message']);
        $this->assertArrayHasKey('user', $response['data']);
        $this->assertArrayHasKey('tokens', $response['data']);
        $this->assertEquals($this->testUser['email'], $response['data']['user']['email']);
        
        echo "✓ User registration test passed\n";
    }
    
    /**
     * Test user login endpoint
     */
    public function testUserLogin() {
        // First register a user
        $this->registerTestUser();
        
        // Mock login request
        $loginData = [
            'email' => $this->testUser['email'],
            'password' => $this->testUser['password']
        ];
        $this->mockJsonInput($loginData);
        
        // Capture output
        ob_start();
        $this->authController->login();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('Login successful', $response['message']);
        $this->assertArrayHasKey('user', $response['data']);
        $this->assertArrayHasKey('tokens', $response['data']);
        
        echo "✓ User login test passed\n";
    }
    
    /**
     * Test admin login endpoint
     */
    public function testAdminLogin() {
        // First register an admin user
        $this->registerTestAdmin();
        
        // Mock admin login request
        $loginData = [
            'email' => $this->testAdmin['email'],
            'password' => $this->testAdmin['password']
        ];
        $this->mockJsonInput($loginData);
        
        // Capture output
        ob_start();
        $this->authController->adminLogin();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('Admin login successful', $response['message']);
        $this->assertArrayHasKey('user', $response['data']);
        $this->assertArrayHasKey('tokens', $response['data']);
        $this->assertArrayHasKey('permissions', $response['data']);
        $this->assertEquals('admin', $response['data']['user']['role']);
        
        echo "✓ Admin login test passed\n";
    }
    
    /**
     * Test invalid login credentials
     */
    public function testInvalidLogin() {
        // Mock invalid login request
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ];
        $this->mockJsonInput($loginData);
        
        // Capture output
        ob_start();
        $this->authController->login();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertContains('Invalid email or password', $response['message']);
        
        echo "✓ Invalid login test passed\n";
    }
    
    /**
     * Test registration with invalid data
     */
    public function testInvalidRegistration() {
        // Mock invalid registration request (missing required fields)
        $invalidData = [
            'email' => 'invalid-email',
            'password' => '123' // Too short
        ];
        $this->mockJsonInput($invalidData);
        
        // Capture output
        ob_start();
        $this->authController->register();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertContains('Validation failed', $response['message']);
        
        echo "✓ Invalid registration test passed\n";
    }
    
    /**
     * Test non-admin user attempting admin login
     */
    public function testNonAdminUserAdminLogin() {
        // Register a regular user
        $this->registerTestUser();
        
        // Try to login as admin
        $loginData = [
            'email' => $this->testUser['email'],
            'password' => $this->testUser['password']
        ];
        $this->mockJsonInput($loginData);
        
        // Capture output
        ob_start();
        $this->authController->adminLogin();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertContains('Admin access required', $response['message']);
        
        echo "✓ Non-admin user admin login test passed\n";
    }
    
    /**
     * Test token verification
     */
    public function testTokenVerification() {
        // Register and login a user to get a token
        $this->registerTestUser();
        $token = $this->loginAndGetToken($this->testUser['email'], $this->testUser['password']);
        
        // Mock authorization header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        
        // Capture output
        ob_start();
        $this->authController->verifyToken();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('Token is valid', $response['message']);
        $this->assertArrayHasKey('user', $response['data']);
        
        echo "✓ Token verification test passed\n";
    }
    
    /**
     * Test invalid token verification
     */
    public function testInvalidTokenVerification() {
        // Mock invalid authorization header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_token_here';
        
        // Capture output
        ob_start();
        $this->authController->verifyToken();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertContains('Invalid token', $response['message']);
        
        echo "✓ Invalid token verification test passed\n";
    }
    
    /**
     * Helper method to mock JSON input
     */
    private function mockJsonInput($data) {
        // Create a temporary file with JSON data
        $tempFile = tempnam(sys_get_temp_dir(), 'php_input');
        file_put_contents($tempFile, json_encode($data));
        
        // Mock php://input
        $GLOBALS['mock_php_input'] = $tempFile;
    }
    
    /**
     * Helper method to register test user
     */
    private function registerTestUser() {
        $db = Database::getInstance();
        $hashedPassword = password_hash($this->testUser['password'], PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, phone, role, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'customer', 1, NOW(), NOW())";
        
        $params = [
            $this->testUser['email'],
            $hashedPassword,
            $this->testUser['first_name'],
            $this->testUser['last_name'],
            $this->testUser['phone']
        ];
        
        $db->executeQuery($sql, $params);
    }
    
    /**
     * Helper method to register test admin
     */
    private function registerTestAdmin() {
        $db = Database::getInstance();
        $hashedPassword = password_hash($this->testAdmin['password'], PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'admin', 1, NOW(), NOW())";
        
        $params = [
            $this->testAdmin['email'],
            $hashedPassword,
            $this->testAdmin['first_name'],
            $this->testAdmin['last_name']
        ];
        
        $db->executeQuery($sql, $params);
    }
    
    /**
     * Helper method to login and get token
     */
    private function loginAndGetToken($email, $password) {
        $authService = new AuthService();
        $result = $authService->login($email, $password);
        return $result['tokens']['access_token'];
    }
    
    /**
     * Helper method to clean up test data
     */
    private function cleanupTestData() {
        try {
            $db = Database::getInstance();
            
            // Delete test users
            $emails = [$this->testUser['email'], $this->testAdmin['email']];
            $placeholders = str_repeat('?,', count($emails) - 1) . '?';
            
            $sql = "DELETE FROM users WHERE email IN ($placeholders)";
            $db->executeQuery($sql, $emails);
            
            // Clean up any refresh tokens
            $sql = "DELETE FROM refresh_tokens WHERE user_id NOT IN (SELECT id FROM users)";
            $db->executeQuery($sql);
            
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "Running AuthController Tests...\n\n";
    
    $test = new AuthControllerTest();
    
    try {
        $test->setUp();
        
        echo "1. Testing user registration...\n";
        $test->testUserRegistration();
        
        echo "2. Testing user login...\n";
        $test->testUserLogin();
        
        echo "3. Testing admin login...\n";
        $test->testAdminLogin();
        
        echo "4. Testing invalid login...\n";
        $test->testInvalidLogin();
        
        echo "5. Testing invalid registration...\n";
        $test->testInvalidRegistration();
        
        echo "6. Testing non-admin user admin login...\n";
        $test->testNonAdminUserAdminLogin();
        
        echo "7. Testing token verification...\n";
        $test->testTokenVerification();
        
        echo "8. Testing invalid token verification...\n";
        $test->testInvalidTokenVerification();
        
        $test->tearDown();
        
        echo "\n✅ All AuthController tests passed!\n";
        
    } catch (Exception $e) {
        echo "\n❌ Test failed: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
}