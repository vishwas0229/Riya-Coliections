<?php
/**
 * User Authentication Flows Unit Tests
 * 
 * Comprehensive unit tests for complete user authentication workflows including
 * registration, login, token refresh, password management, and session handling.
 * Tests the integration between AuthController, AuthService, and User models.
 * 
 * Task: 6.3 Write unit tests for user authentication flows
 * Requirements: 3.1, 3.2
 */

// Set up test environment first
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

// Set test environment variables
putenv('APP_ENV=testing');
putenv('DB_HOST=localhost');
putenv('DB_NAME=riya_collections_test');
putenv('DB_USER=root');
putenv('DB_PASSWORD=');
putenv('JWT_SECRET=test_jwt_secret_for_testing_only_32_chars_minimum');

$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'riya_collections_test';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASSWORD'] = '';
$_ENV['JWT_SECRET'] = 'test_jwt_secret_for_testing_only_32_chars_minimum';

// Include dependencies in correct order
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../controllers/AuthController.php';

class UserAuthenticationFlowsTest {
    private $authController;
    private $authService;
    private $userModel;
    private $db;
    private $testUsers = [];
    
    public function setUp() {
        // Set up test environment
        $this->setupTestEnvironment();
        
        $this->authController = new AuthController();
        $this->authService = new AuthService();
        $this->userModel = new User();
        $this->db = Database::getInstance();
        
        // Create required tables for testing
        $this->createTestTables();
        
        // Clean up any existing test data
        $this->cleanupTestData();
    }
    
    public function tearDown() {
        // Clean up test data after tests
        $this->cleanupTestData();
    }
    
    /**
     * Test complete user registration flow
     * Tests: Registration validation, password hashing, token generation, database storage
     */
    public function testCompleteUserRegistrationFlow() {
        echo "Testing complete user registration flow...\n";
        
        $userData = [
            'email' => 'registration@example.com',
            'password' => 'SecurePass123!',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890'
        ];
        
        // Mock JSON input for controller
        $this->mockJsonInput($userData);
        
        // Capture controller output
        ob_start();
        $this->authController->register();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Verify response structure
        $this->assert($response['success'] === true, 'Registration should succeed');
        $this->assert($response['message'] === 'User registered successfully', 'Success message should match');
        $this->assert(isset($response['data']['user']), 'Response should contain user data');
        $this->assert(isset($response['data']['tokens']), 'Response should contain tokens');
        
        // Verify user data
        $user = $response['data']['user'];
        $this->assert($user['email'] === $userData['email'], 'Email should match');
        $this->assert($user['first_name'] === $userData['first_name'], 'First name should match');
        $this->assert($user['last_name'] === $userData['last_name'], 'Last name should match');
        $this->assert($user['phone'] === $userData['phone'], 'Phone should match');
        $this->assert($user['role'] === 'customer', 'Default role should be customer');
        $this->assert(!isset($user['password_hash']), 'Password hash should not be exposed');
        
        // Verify tokens
        $tokens = $response['data']['tokens'];
        $this->assert(isset($tokens['access_token']), 'Access token should be provided');
        $this->assert(isset($tokens['refresh_token']), 'Refresh token should be provided');
        $this->assert($tokens['token_type'] === 'Bearer', 'Token type should be Bearer');
        $this->assert(is_numeric($tokens['expires_in']), 'Expires in should be numeric');
        
        // Verify user was stored in database
        $dbUser = $this->userModel->getUserByEmail($userData['email']);
        $this->assert($dbUser !== null, 'User should be stored in database');
        $this->assert($dbUser['id'] === $user['id'], 'Database user ID should match response');
        
        // Verify password was hashed properly
        $sql = "SELECT password_hash FROM users WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$user['id']]);
        $result = $stmt->fetch();
        $this->assert(password_verify($userData['password'], $result['password_hash']), 'Password should be hashed correctly');
        
        // Verify refresh token was stored
        $sql = "SELECT COUNT(*) as count FROM refresh_tokens WHERE user_id = ?";
        $stmt = $this->db->executeQuery($sql, [$user['id']]);
        $tokenCount = $stmt->fetch();
        $this->assert($tokenCount['count'] > 0, 'Refresh token should be stored');
        
        $this->testUsers[] = $user['id'];
        echo "✓ Complete user registration flow test passed\n";
    }
    
    /**
     * Test complete user login flow
     * Tests: Credential validation, password verification, token generation, session creation
     */
    public function testCompleteUserLoginFlow() {
        echo "Testing complete user login flow...\n";
        
        // First register a user
        $userData = [
            'email' => 'login@example.com',
            'password' => 'LoginPass123!',
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ];
        
        $registeredUser = $this->authService->register($userData);
        $this->testUsers[] = $registeredUser['user']['id'];
        
        // Now test login
        $loginData = [
            'email' => $userData['email'],
            'password' => $userData['password']
        ];
        
        $this->mockJsonInput($loginData);
        
        // Capture controller output
        ob_start();
        $this->authController->login();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Verify response structure
        $this->assert($response['success'] === true, 'Login should succeed');
        $this->assert($response['message'] === 'Login successful', 'Success message should match');
        $this->assert(isset($response['data']['user']), 'Response should contain user data');
        $this->assert(isset($response['data']['tokens']), 'Response should contain tokens');
        
        // Verify user data
        $user = $response['data']['user'];
        $this->assert($user['email'] === $userData['email'], 'Email should match');
        $this->assert($user['id'] === $registeredUser['user']['id'], 'User ID should match registered user');
        
        // Verify tokens are different from registration tokens
        $tokens = $response['data']['tokens'];
        $this->assert($tokens['access_token'] !== $registeredUser['tokens']['access_token'], 'New access token should be generated');
        
        // Verify last login was updated
        $dbUser = $this->userModel->getUserById($user['id']);
        $this->assert($dbUser['last_login_at'] !== null, 'Last login should be updated');
        
        // Verify new refresh token was stored
        $sql = "SELECT COUNT(*) as count FROM refresh_tokens WHERE user_id = ? AND revoked_at IS NULL";
        $stmt = $this->db->executeQuery($sql, [$user['id']]);
        $tokenCount = $stmt->fetch();
        $this->assert($tokenCount['count'] >= 1, 'Active refresh token should exist');
        
        echo "✓ Complete user login flow test passed\n";
    }
    
    /**
     * Test token refresh flow
     * Tests: Refresh token validation, new token generation, token rotation
     */
    public function testTokenRefreshFlow() {
        echo "Testing token refresh flow...\n";
        
        // Register and login a user
        $userData = [
            'email' => 'refresh@example.com',
            'password' => 'RefreshPass123!',
            'first_name' => 'Token',
            'last_name' => 'Refresh'
        ];
        
        $loginResult = $this->authService->register($userData);
        $this->testUsers[] = $loginResult['user']['id'];
        
        $originalTokens = $loginResult['tokens'];
        
        // Test token refresh
        $refreshData = [
            'refresh_token' => $originalTokens['refresh_token']
        ];
        
        $this->mockJsonInput($refreshData);
        
        // Capture controller output
        ob_start();
        $this->authController->refresh();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Verify response structure
        $this->assert($response['success'] === true, 'Token refresh should succeed');
        $this->assert($response['message'] === 'Token refreshed successfully', 'Success message should match');
        $this->assert(isset($response['data']['tokens']), 'Response should contain new tokens');
        
        // Verify new tokens are different
        $newTokens = $response['data']['tokens'];
        $this->assert($newTokens['access_token'] !== $originalTokens['access_token'], 'New access token should be different');
        $this->assert($newTokens['refresh_token'] !== $originalTokens['refresh_token'], 'New refresh token should be different');
        
        // Verify old refresh token was revoked
        $sql = "SELECT revoked_at FROM refresh_tokens WHERE token = ?";
        $stmt = $this->db->executeQuery($sql, [$originalTokens['refresh_token']]);
        $oldToken = $stmt->fetch();
        $this->assert($oldToken['revoked_at'] !== null, 'Old refresh token should be revoked');
        
        // Verify new refresh token was stored
        $sql = "SELECT COUNT(*) as count FROM refresh_tokens WHERE token = ? AND revoked_at IS NULL";
        $stmt = $this->db->executeQuery($sql, [$newTokens['refresh_token']]);
        $newTokenCount = $stmt->fetch();
        $this->assert($newTokenCount['count'] === 1, 'New refresh token should be stored');
        
        echo "✓ Token refresh flow test passed\n";
    }
    
    /**
     * Test password change flow
     * Tests: Current password verification, new password validation, hash update, token invalidation
     */
    public function testPasswordChangeFlow() {
        echo "Testing password change flow...\n";
        
        // Register a user
        $userData = [
            'email' => 'passwordchange@example.com',
            'password' => 'OldPass123!',
            'first_name' => 'Password',
            'last_name' => 'Change'
        ];
        
        $loginResult = $this->authService->register($userData);
        $this->testUsers[] = $loginResult['user']['id'];
        
        // Mock authentication for password change
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $loginResult['tokens']['access_token'];
        
        // Test password change
        $changeData = [
            'current_password' => $userData['password'],
            'new_password' => 'NewPass456@'
        ];
        
        $this->mockJsonInput($changeData);
        
        // Capture controller output
        ob_start();
        $this->authController->changePassword();
        $output = ob_get_clean();
        
        // Parse response
        $response = json_decode($output, true);
        
        // Verify response
        $this->assert($response['success'] === true, 'Password change should succeed');
        $this->assert($response['message'] === 'Password changed successfully', 'Success message should match');
        
        // Verify password was updated in database
        $sql = "SELECT password_hash FROM users WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$loginResult['user']['id']]);
        $result = $stmt->fetch();
        $this->assert(password_verify($changeData['new_password'], $result['password_hash']), 'New password should be hashed correctly');
        $this->assert(!password_verify($userData['password'], $result['password_hash']), 'Old password should no longer work');
        
        // Verify all refresh tokens were invalidated
        $sql = "SELECT COUNT(*) as count FROM refresh_tokens WHERE user_id = ? AND revoked_at IS NULL";
        $stmt = $this->db->executeQuery($sql, [$loginResult['user']['id']]);
        $activeTokens = $stmt->fetch();
        $this->assert($activeTokens['count'] === 0, 'All refresh tokens should be invalidated after password change');
        
        echo "✓ Password change flow test passed\n";
    }
    
    /**
     * Test password reset flow
     * Tests: Reset token generation, token validation, password update, security measures
     */
    public function testPasswordResetFlow() {
        echo "Testing password reset flow...\n";
        
        // Register a user
        $userData = [
            'email' => 'passwordreset@example.com',
            'password' => 'OriginalPass123!',
            'first_name' => 'Password',
            'last_name' => 'Reset'
        ];
        
        $loginResult = $this->authService->register($userData);
        $this->testUsers[] = $loginResult['user']['id'];
        
        // Initiate password reset
        $resetInitData = ['email' => $userData['email']];
        $this->mockJsonInput($resetInitData);
        
        ob_start();
        $this->authController->forgotPassword();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === true, 'Password reset initiation should succeed');
        
        // Get reset token from database (in real app, this would be sent via email)
        $sql = "SELECT token FROM password_resets WHERE user_id = ? AND used_at IS NULL ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->executeQuery($sql, [$loginResult['user']['id']]);
        $resetRecord = $stmt->fetch();
        $this->assert($resetRecord !== false, 'Reset token should be stored');
        
        $resetToken = $resetRecord['token'];
        
        // Complete password reset
        $resetCompleteData = [
            'token' => $resetToken,
            'new_password' => 'NewResetPass789#'
        ];
        
        $this->mockJsonInput($resetCompleteData);
        
        ob_start();
        $this->authController->resetPassword();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === true, 'Password reset completion should succeed');
        
        // Verify password was updated
        $sql = "SELECT password_hash FROM users WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$loginResult['user']['id']]);
        $result = $stmt->fetch();
        $this->assert(password_verify($resetCompleteData['new_password'], $result['password_hash']), 'Reset password should be hashed correctly');
        
        // Verify reset token was marked as used
        $sql = "SELECT used_at FROM password_resets WHERE token = ?";
        $stmt = $this->db->executeQuery($sql, [$resetToken]);
        $usedToken = $stmt->fetch();
        $this->assert($usedToken['used_at'] !== null, 'Reset token should be marked as used');
        
        // Verify all refresh tokens were invalidated
        $sql = "SELECT COUNT(*) as count FROM refresh_tokens WHERE user_id = ? AND revoked_at IS NULL";
        $stmt = $this->db->executeQuery($sql, [$loginResult['user']['id']]);
        $activeTokens = $stmt->fetch();
        $this->assert($activeTokens['count'] === 0, 'All refresh tokens should be invalidated after password reset');
        
        echo "✓ Password reset flow test passed\n";
    }
    
    /**
     * Test admin authentication flow
     * Tests: Admin role validation, enhanced security, admin-specific tokens
     */
    public function testAdminAuthenticationFlow() {
        echo "Testing admin authentication flow...\n";
        
        // Register an admin user
        $adminData = [
            'email' => 'admin@example.com',
            'password' => 'AdminPass123!',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'admin'
        ];
        
        $registeredAdmin = $this->authService->register($adminData);
        $this->testUsers[] = $registeredAdmin['user']['id'];
        
        // Test admin login
        $loginData = [
            'email' => $adminData['email'],
            'password' => $adminData['password']
        ];
        
        $this->mockJsonInput($loginData);
        
        ob_start();
        $this->authController->adminLogin();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Verify admin login response
        $this->assert($response['success'] === true, 'Admin login should succeed');
        $this->assert($response['message'] === 'Admin login successful', 'Admin success message should match');
        $this->assert(isset($response['data']['permissions']), 'Admin response should include permissions');
        
        // Verify admin permissions
        $permissions = $response['data']['permissions'];
        $this->assert(is_array($permissions), 'Permissions should be an array');
        $this->assert(in_array('users.view', $permissions), 'Admin should have user view permission');
        $this->assert(in_array('products.create', $permissions), 'Admin should have product create permission');
        
        // Verify user role
        $user = $response['data']['user'];
        $this->assert($user['role'] === 'admin', 'User role should be admin');
        
        echo "✓ Admin authentication flow test passed\n";
    }
    
    /**
     * Test logout flow
     * Tests: Token invalidation, session cleanup
     */
    public function testLogoutFlow() {
        echo "Testing logout flow...\n";
        
        // Register and login a user
        $userData = [
            'email' => 'logout@example.com',
            'password' => 'LogoutPass123!',
            'first_name' => 'Logout',
            'last_name' => 'Test'
        ];
        
        $loginResult = $this->authService->register($userData);
        $this->testUsers[] = $loginResult['user']['id'];
        
        // Mock authentication for logout
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $loginResult['tokens']['access_token'];
        
        // Test logout
        $logoutData = [
            'refresh_token' => $loginResult['tokens']['refresh_token']
        ];
        
        $this->mockJsonInput($logoutData);
        
        ob_start();
        $this->authController->logout();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Verify logout response
        $this->assert($response['success'] === true, 'Logout should succeed');
        $this->assert($response['message'] === 'Logged out successfully', 'Logout message should match');
        
        // Verify refresh token was revoked
        $sql = "SELECT revoked_at FROM refresh_tokens WHERE token = ?";
        $stmt = $this->db->executeQuery($sql, [$loginResult['tokens']['refresh_token']]);
        $revokedToken = $stmt->fetch();
        $this->assert($revokedToken['revoked_at'] !== null, 'Refresh token should be revoked');
        
        echo "✓ Logout flow test passed\n";
    }
    
    /**
     * Test authentication error scenarios
     * Tests: Invalid credentials, expired tokens, unauthorized access
     */
    public function testAuthenticationErrorScenarios() {
        echo "Testing authentication error scenarios...\n";
        
        // Test invalid login credentials
        $invalidLoginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'WrongPassword123!'
        ];
        
        $this->mockJsonInput($invalidLoginData);
        
        ob_start();
        $this->authController->login();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === false, 'Invalid login should fail');
        $this->assert(strpos($response['message'], 'Invalid email or password') !== false, 'Error message should indicate invalid credentials');
        
        // Test registration with invalid data
        $invalidRegData = [
            'email' => 'invalid-email',
            'password' => 'weak',
            'first_name' => '',
            'last_name' => ''
        ];
        
        $this->mockJsonInput($invalidRegData);
        
        ob_start();
        $this->authController->register();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === false, 'Invalid registration should fail');
        $this->assert(strpos($response['message'], 'Validation failed') !== false, 'Error message should indicate validation failure');
        
        // Test token verification with invalid token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_token_here';
        
        ob_start();
        $this->authController->verifyToken();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === false, 'Invalid token verification should fail');
        $this->assert(strpos($response['message'], 'Invalid token') !== false, 'Error message should indicate invalid token');
        
        // Test refresh with invalid refresh token
        $invalidRefreshData = ['refresh_token' => 'invalid_refresh_token'];
        $this->mockJsonInput($invalidRefreshData);
        
        ob_start();
        $this->authController->refresh();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === false, 'Invalid refresh should fail');
        
        echo "✓ Authentication error scenarios test passed\n";
    }
    
    /**
     * Test profile management flow
     * Tests: Profile retrieval, profile updates, authentication requirements
     */
    public function testProfileManagementFlow() {
        echo "Testing profile management flow...\n";
        
        // Register a user
        $userData = [
            'email' => 'profile@example.com',
            'password' => 'ProfilePass123!',
            'first_name' => 'Profile',
            'last_name' => 'User',
            'phone' => '+1234567890'
        ];
        
        $loginResult = $this->authService->register($userData);
        $this->testUsers[] = $loginResult['user']['id'];
        
        // Mock authentication
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $loginResult['tokens']['access_token'];
        
        // Test profile retrieval
        ob_start();
        $this->authController->getProfile();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === true, 'Profile retrieval should succeed');
        $this->assert(isset($response['data']['user']), 'Response should contain user data');
        
        $profile = $response['data']['user'];
        $this->assert($profile['email'] === $userData['email'], 'Profile email should match');
        $this->assert($profile['first_name'] === $userData['first_name'], 'Profile first name should match');
        
        // Test profile update
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '+9876543210'
        ];
        
        $this->mockJsonInput($updateData);
        
        ob_start();
        $this->authController->updateProfile();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === true, 'Profile update should succeed');
        
        $updatedProfile = $response['data']['user'];
        $this->assert($updatedProfile['first_name'] === 'Updated', 'First name should be updated');
        $this->assert($updatedProfile['last_name'] === 'Name', 'Last name should be updated');
        $this->assert($updatedProfile['phone'] === '+9876543210', 'Phone should be updated');
        
        echo "✓ Profile management flow test passed\n";
    }
    
    /**
     * Test session management
     * Tests: Multiple sessions, session tracking, session cleanup
     */
    public function testSessionManagement() {
        echo "Testing session management...\n";
        
        // Register a user
        $userData = [
            'email' => 'sessions@example.com',
            'password' => 'SessionPass123!',
            'first_name' => 'Session',
            'last_name' => 'User'
        ];
        
        $loginResult = $this->authService->register($userData);
        $this->testUsers[] = $loginResult['user']['id'];
        
        // Create multiple sessions by logging in multiple times
        $session1 = $this->authService->login($userData['email'], $userData['password']);
        $session2 = $this->authService->login($userData['email'], $userData['password']);
        
        // Verify multiple refresh tokens exist
        $sql = "SELECT COUNT(*) as count FROM refresh_tokens WHERE user_id = ? AND revoked_at IS NULL";
        $stmt = $this->db->executeQuery($sql, [$loginResult['user']['id']]);
        $tokenCount = $stmt->fetch();
        $this->assert($tokenCount['count'] >= 2, 'Multiple active sessions should exist');
        
        // Test getting user sessions
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $session1['tokens']['access_token'];
        
        ob_start();
        $this->authController->getSessions();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assert($response['success'] === true, 'Get sessions should succeed');
        $this->assert(isset($response['data']['sessions']), 'Response should contain sessions');
        $this->assert(count($response['data']['sessions']) >= 2, 'Should return multiple sessions');
        
        echo "✓ Session management test passed\n";
    }
    
    /**
     * Set up test environment
     */
    private function setupTestEnvironment() {
        // Create logs directory if it doesn't exist
        $logsDir = __DIR__ . '/../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }
    
    /**
     * Create required database tables for testing
     */
    private function createTestTables() {
        try {
            // Create users table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    phone VARCHAR(20),
                    role ENUM('customer', 'admin') DEFAULT 'customer',
                    is_active BOOLEAN DEFAULT TRUE,
                    email_verified_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    last_login_at TIMESTAMP NULL
                )
            ");
            
            // Create refresh_tokens table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS refresh_tokens (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    token TEXT NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    revoked_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Create password_resets table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    token VARCHAR(255) UNIQUE NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    used_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
        } catch (Exception $e) {
            echo "Warning: Could not create test tables: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Helper method to mock JSON input for controllers
     */
    private function mockJsonInput($data) {
        // Store original input
        if (!isset($GLOBALS['original_php_input'])) {
            $GLOBALS['original_php_input'] = file_get_contents('php://input');
        }
        
        // Create temporary file with JSON data
        $tempFile = tempnam(sys_get_temp_dir(), 'php_input_');
        file_put_contents($tempFile, json_encode($data));
        
        // Mock php://input by overriding file_get_contents for php://input
        $GLOBALS['mock_php_input'] = json_encode($data);
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
     * Clean up test data
     */
    private function cleanupTestData() {
        try {
            // Clean up test users and related data
            foreach ($this->testUsers as $userId) {
                // Delete refresh tokens
                $this->db->executeQuery("DELETE FROM refresh_tokens WHERE user_id = ?", [$userId]);
                
                // Delete password resets
                $this->db->executeQuery("DELETE FROM password_resets WHERE user_id = ?", [$userId]);
                
                // Delete user
                $this->db->executeQuery("DELETE FROM users WHERE id = ?", [$userId]);
            }
            
            // Clean up any test users by email pattern
            $testEmails = [
                'registration@example.com',
                'login@example.com',
                'refresh@example.com',
                'passwordchange@example.com',
                'passwordreset@example.com',
                'admin@example.com',
                'logout@example.com',
                'profile@example.com',
                'sessions@example.com'
            ];
            
            foreach ($testEmails as $email) {
                $this->db->executeQuery("DELETE FROM users WHERE email = ?", [$email]);
            }
            
            // Clean up orphaned tokens
            $this->db->executeQuery("DELETE FROM refresh_tokens WHERE user_id NOT IN (SELECT id FROM users)");
            $this->db->executeQuery("DELETE FROM password_resets WHERE user_id NOT IN (SELECT id FROM users)");
            
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        
        $this->testUsers = [];
        
        // Clean up global mocks
        unset($GLOBALS['mock_php_input']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
    
    /**
     * Run all authentication flow tests
     */
    public function runAllTests() {
        echo "Running User Authentication Flows Unit Tests...\n";
        echo "=================================================\n\n";
        
        $this->setUp();
        
        try {
            $this->testCompleteUserRegistrationFlow();
            $this->testCompleteUserLoginFlow();
            $this->testTokenRefreshFlow();
            $this->testPasswordChangeFlow();
            $this->testPasswordResetFlow();
            $this->testAdminAuthenticationFlow();
            $this->testLogoutFlow();
            $this->testAuthenticationErrorScenarios();
            $this->testProfileManagementFlow();
            $this->testSessionManagement();
            
            echo "\n✅ All User Authentication Flows unit tests passed!\n";
            echo "   - Complete registration workflow ✓\n";
            echo "   - Complete login workflow ✓\n";
            echo "   - Token refresh mechanism ✓\n";
            echo "   - Password change security ✓\n";
            echo "   - Password reset process ✓\n";
            echo "   - Admin authentication ✓\n";
            echo "   - Logout and session cleanup ✓\n";
            echo "   - Error handling scenarios ✓\n";
            echo "   - Profile management ✓\n";
            echo "   - Session management ✓\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Override file_get_contents for php://input mocking
if (!function_exists('original_file_get_contents')) {
    function original_file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $maxlen = null) {
        return file_get_contents($filename, $use_include_path, $context, $offset, $maxlen);
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserAuthenticationFlowsTest();
    $test->runAllTests();
}