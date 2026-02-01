<?php
/**
 * User Model Unit Tests
 * 
 * Comprehensive unit tests for the User model CRUD operations,
 * validation, and integration with the database layer.
 * 
 * Requirements: 3.1, 3.2, 16.1
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/bootstrap.php';

class UserTest {
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
     * Test user creation with valid data
     */
    public function testCreateUserWithValidData() {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Assertions
        assert($createdUser['email'] === 'test@example.com', 'Email should match');
        assert($createdUser['first_name'] === 'John', 'First name should match');
        assert($createdUser['last_name'] === 'Doe', 'Last name should match');
        assert($createdUser['phone'] === '+1234567890', 'Phone should match');
        assert($createdUser['role'] === 'customer', 'Default role should be customer');
        assert($createdUser['is_active'] === true, 'User should be active by default');
        assert(isset($createdUser['id']), 'User ID should be set');
        assert(!isset($createdUser['password_hash']), 'Password hash should not be in response');
        
        echo "✓ User creation with valid data test passed\n";
    }
    
    /**
     * Test user creation with duplicate email
     */
    public function testCreateUserWithDuplicateEmail() {
        $userData = [
            'email' => 'duplicate@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];
        
        // Create first user
        $firstUser = $this->user->createUser($userData);
        $this->testUsers[] = $firstUser['id'];
        
        // Try to create second user with same email
        try {
            $this->user->createUser($userData);
            assert(false, 'Should have thrown exception for duplicate email');
        } catch (Exception $e) {
            assert($e->getCode() === 409, 'Should return 409 conflict status');
            assert(strpos($e->getMessage(), 'already exists') !== false, 'Error message should mention duplicate');
        }
        
        echo "✓ Duplicate email validation test passed\n";
    }
    
    /**
     * Test user creation with invalid email
     */
    public function testCreateUserWithInvalidEmail() {
        $userData = [
            'email' => 'invalid-email',
            'password' => 'TestPass123!',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];
        
        try {
            $this->user->createUser($userData);
            assert(false, 'Should have thrown exception for invalid email');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
            assert(strpos($e->getMessage(), 'Invalid email') !== false, 'Error message should mention invalid email');
        }
        
        echo "✓ Invalid email validation test passed\n";
    }
    
    /**
     * Test user creation with weak password
     */
    public function testCreateUserWithWeakPassword() {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'weak',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];
        
        try {
            $this->user->createUser($userData);
            assert(false, 'Should have thrown exception for weak password');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
            assert(strpos($e->getMessage(), 'Password must') !== false, 'Error message should mention password requirements');
        }
        
        echo "✓ Weak password validation test passed\n";
    }
    
    /**
     * Test user creation with missing required fields
     */
    public function testCreateUserWithMissingFields() {
        $userData = [
            'email' => 'test@example.com'
            // Missing password, first_name, last_name
        ];
        
        try {
            $this->user->createUser($userData);
            assert(false, 'Should have thrown exception for missing fields');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
            assert(strpos($e->getMessage(), 'required') !== false, 'Error message should mention required fields');
        }
        
        echo "✓ Missing required fields validation test passed\n";
    }
    
    /**
     * Test getting user by ID
     */
    public function testGetUserById() {
        // Create test user
        $userData = [
            'email' => 'getbyid@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Get user by ID
        $retrievedUser = $this->user->getUserById($createdUser['id']);
        
        // Assertions
        assert($retrievedUser !== null, 'User should be found');
        assert($retrievedUser['id'] === $createdUser['id'], 'User ID should match');
        assert($retrievedUser['email'] === 'getbyid@example.com', 'Email should match');
        assert($retrievedUser['first_name'] === 'Jane', 'First name should match');
        assert($retrievedUser['last_name'] === 'Smith', 'Last name should match');
        
        echo "✓ Get user by ID test passed\n";
    }
    
    /**
     * Test getting user by email
     */
    public function testGetUserByEmail() {
        // Create test user
        $userData = [
            'email' => 'getbyemail@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Bob',
            'last_name' => 'Johnson'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Get user by email
        $retrievedUser = $this->user->getUserByEmail('getbyemail@example.com');
        
        // Assertions
        assert($retrievedUser !== null, 'User should be found');
        assert($retrievedUser['id'] === $createdUser['id'], 'User ID should match');
        assert($retrievedUser['email'] === 'getbyemail@example.com', 'Email should match');
        
        echo "✓ Get user by email test passed\n";
    }
    
    /**
     * Test getting non-existent user
     */
    public function testGetNonExistentUser() {
        $user = $this->user->getUserById(99999);
        assert($user === null, 'Non-existent user should return null');
        
        $user = $this->user->getUserByEmail('nonexistent@example.com');
        assert($user === null, 'Non-existent email should return null');
        
        echo "✓ Get non-existent user test passed\n";
    }
    
    /**
     * Test updating user profile
     */
    public function testUpdateUser() {
        // Create test user
        $userData = [
            'email' => 'update@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Original',
            'last_name' => 'Name'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Update user
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'NewName',
            'phone' => '+9876543210'
        ];
        
        $updatedUser = $this->user->updateUser($createdUser['id'], $updateData);
        
        // Assertions
        assert($updatedUser['first_name'] === 'Updated', 'First name should be updated');
        assert($updatedUser['last_name'] === 'NewName', 'Last name should be updated');
        assert($updatedUser['phone'] === '+9876543210', 'Phone should be updated');
        assert($updatedUser['email'] === 'update@example.com', 'Email should remain unchanged');
        
        echo "✓ Update user test passed\n";
    }
    
    /**
     * Test updating user email
     */
    public function testUpdateUserEmail() {
        // Create test user
        $userData = [
            'email' => 'oldemail@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Test',
            'last_name' => 'User'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Update email
        $updateData = ['email' => 'newemail@example.com'];
        $updatedUser = $this->user->updateUser($createdUser['id'], $updateData);
        
        // Assertions
        assert($updatedUser['email'] === 'newemail@example.com', 'Email should be updated');
        
        echo "✓ Update user email test passed\n";
    }
    
    /**
     * Test updating user with duplicate email
     */
    public function testUpdateUserWithDuplicateEmail() {
        // Create two test users
        $user1Data = [
            'email' => 'user1@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'User',
            'last_name' => 'One'
        ];
        
        $user2Data = [
            'email' => 'user2@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'User',
            'last_name' => 'Two'
        ];
        
        $user1 = $this->user->createUser($user1Data);
        $user2 = $this->user->createUser($user2Data);
        $this->testUsers[] = $user1['id'];
        $this->testUsers[] = $user2['id'];
        
        // Try to update user2 with user1's email
        try {
            $this->user->updateUser($user2['id'], ['email' => 'user1@example.com']);
            assert(false, 'Should have thrown exception for duplicate email');
        } catch (Exception $e) {
            assert($e->getCode() === 409, 'Should return 409 conflict status');
            assert(strpos($e->getMessage(), 'already exists') !== false, 'Error message should mention duplicate');
        }
        
        echo "✓ Update user with duplicate email test passed\n";
    }
    
    /**
     * Test updating non-existent user
     */
    public function testUpdateNonExistentUser() {
        try {
            $this->user->updateUser(99999, ['first_name' => 'Test']);
            assert(false, 'Should have thrown exception for non-existent user');
        } catch (Exception $e) {
            assert($e->getCode() === 404, 'Should return 404 not found status');
        }
        
        echo "✓ Update non-existent user test passed\n";
    }
    
    /**
     * Test deleting user (soft delete)
     */
    public function testDeleteUser() {
        // Create test user
        $userData = [
            'email' => 'delete@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Delete',
            'last_name' => 'Me'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $userId = $createdUser['id'];
        
        // Delete user
        $result = $this->user->deleteUser($userId);
        assert($result === true, 'Delete should return true');
        
        // Verify user is soft deleted
        $deletedUser = $this->user->getUserById($userId);
        assert($deletedUser === null, 'Deleted user should not be retrievable');
        
        echo "✓ Delete user test passed\n";
    }
    
    /**
     * Test deleting non-existent user
     */
    public function testDeleteNonExistentUser() {
        try {
            $this->user->deleteUser(99999);
            assert(false, 'Should have thrown exception for non-existent user');
        } catch (Exception $e) {
            assert($e->getCode() === 404, 'Should return 404 not found status');
        }
        
        echo "✓ Delete non-existent user test passed\n";
    }
    
    /**
     * Test getting users with pagination
     */
    public function testGetUsersWithPagination() {
        // Create multiple test users
        for ($i = 1; $i <= 5; $i++) {
            $userData = [
                'email' => "pagination{$i}@example.com",
                'password' => 'TestPass123!',
                'first_name' => "User{$i}",
                'last_name' => 'Test'
            ];
            
            $user = $this->user->createUser($userData);
            $this->testUsers[] = $user['id'];
        }
        
        // Get users with pagination
        $result = $this->user->getUsers([], 1, 3);
        
        // Assertions
        assert(isset($result['users']), 'Result should have users array');
        assert(isset($result['pagination']), 'Result should have pagination info');
        assert(count($result['users']) <= 3, 'Should return at most 3 users per page');
        assert($result['pagination']['per_page'] === 3, 'Per page should be 3');
        assert($result['pagination']['current_page'] === 1, 'Current page should be 1');
        
        echo "✓ Get users with pagination test passed\n";
    }
    
    /**
     * Test getting users with search filter
     */
    public function testGetUsersWithSearch() {
        // Create test users with specific names
        $userData1 = [
            'email' => 'search1@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'SearchableFirst',
            'last_name' => 'User'
        ];
        
        $userData2 = [
            'email' => 'search2@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Another',
            'last_name' => 'SearchableLast'
        ];
        
        $user1 = $this->user->createUser($userData1);
        $user2 = $this->user->createUser($userData2);
        $this->testUsers[] = $user1['id'];
        $this->testUsers[] = $user2['id'];
        
        // Search for users
        $result = $this->user->getUsers(['search' => 'Searchable'], 1, 10);
        
        // Assertions
        assert(count($result['users']) >= 2, 'Should find at least 2 users with search term');
        
        echo "✓ Get users with search test passed\n";
    }
    
    /**
     * Test email existence check
     */
    public function testEmailExists() {
        // Create test user
        $userData = [
            'email' => 'exists@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Exists',
            'last_name' => 'Test'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Test email existence
        assert($this->user->emailExists('exists@example.com') === true, 'Existing email should return true');
        assert($this->user->emailExists('notexists@example.com') === false, 'Non-existing email should return false');
        
        // Test excluding user ID
        assert($this->user->emailExists('exists@example.com', $createdUser['id']) === false, 'Should return false when excluding the user');
        
        echo "✓ Email exists test passed\n";
    }
    
    /**
     * Test password update
     */
    public function testUpdatePassword() {
        // Create test user
        $userData = [
            'email' => 'password@example.com',
            'password' => 'OldPass123!',
            'first_name' => 'Password',
            'last_name' => 'Test'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Update password
        $result = $this->user->updatePassword($createdUser['id'], 'NewPass456@');
        assert($result === true, 'Password update should return true');
        
        echo "✓ Update password test passed\n";
    }
    
    /**
     * Test password update with weak password
     */
    public function testUpdatePasswordWithWeakPassword() {
        // Create test user
        $userData = [
            'email' => 'weakpass@example.com',
            'password' => 'StrongPass123!',
            'first_name' => 'Weak',
            'last_name' => 'Password'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Try to update with weak password
        try {
            $this->user->updatePassword($createdUser['id'], 'weak');
            assert(false, 'Should have thrown exception for weak password');
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'Password must') !== false, 'Error message should mention password requirements');
        }
        
        echo "✓ Update password with weak password test passed\n";
    }
    
    /**
     * Test user role update
     */
    public function testUpdateUserRole() {
        // Create test user
        $userData = [
            'email' => 'role@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Role',
            'last_name' => 'Test'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Update role to admin
        $result = $this->user->updateUserRole($createdUser['id'], 'admin');
        assert($result === true, 'Role update should return true');
        
        // Verify role was updated
        $updatedUser = $this->user->getUserById($createdUser['id']);
        assert($updatedUser['role'] === 'admin', 'Role should be updated to admin');
        
        echo "✓ Update user role test passed\n";
    }
    
    /**
     * Test user role update with invalid role
     */
    public function testUpdateUserRoleWithInvalidRole() {
        // Create test user
        $userData = [
            'email' => 'invalidrole@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Invalid',
            'last_name' => 'Role'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Try to update with invalid role
        try {
            $this->user->updateUserRole($createdUser['id'], 'invalid_role');
            assert(false, 'Should have thrown exception for invalid role');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
            assert(strpos($e->getMessage(), 'Invalid role') !== false, 'Error message should mention invalid role');
        }
        
        echo "✓ Update user role with invalid role test passed\n";
    }
    
    /**
     * Test user status update
     */
    public function testSetUserStatus() {
        // Create test user
        $userData = [
            'email' => 'status@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Status',
            'last_name' => 'Test'
        ];
        
        $createdUser = $this->user->createUser($userData);
        $this->testUsers[] = $createdUser['id'];
        
        // Deactivate user
        $result = $this->user->setUserStatus($createdUser['id'], false);
        assert($result === true, 'Status update should return true');
        
        // Verify user is deactivated (should not be retrievable)
        $deactivatedUser = $this->user->getUserById($createdUser['id']);
        assert($deactivatedUser === null, 'Deactivated user should not be retrievable');
        
        echo "✓ Set user status test passed\n";
    }
    
    /**
     * Test getting user statistics
     */
    public function testGetUserStats() {
        // Create test users with different roles
        $customerData = [
            'email' => 'customer@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Customer',
            'last_name' => 'User',
            'role' => 'customer'
        ];
        
        $adminData = [
            'email' => 'admin@example.com',
            'password' => 'TestPass123!',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'admin'
        ];
        
        $customer = $this->user->createUser($customerData);
        $admin = $this->user->createUser($adminData);
        $this->testUsers[] = $customer['id'];
        $this->testUsers[] = $admin['id'];
        
        // Get statistics
        $stats = $this->user->getUserStats();
        
        // Assertions
        assert(isset($stats['total_users']), 'Stats should include total users');
        assert(isset($stats['customers']), 'Stats should include customer count');
        assert(isset($stats['admins']), 'Stats should include admin count');
        assert(isset($stats['recent_registrations']), 'Stats should include recent registrations');
        assert(isset($stats['active_users']), 'Stats should include active users');
        assert($stats['total_users'] >= 2, 'Should have at least 2 users');
        
        echo "✓ Get user statistics test passed\n";
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
            $this->db->executeQuery("DELETE FROM users WHERE email LIKE '%@example.com'");
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        
        $this->testUsers = [];
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running User Model Unit Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testCreateUserWithValidData();
            $this->testCreateUserWithDuplicateEmail();
            $this->testCreateUserWithInvalidEmail();
            $this->testCreateUserWithWeakPassword();
            $this->testCreateUserWithMissingFields();
            $this->testGetUserById();
            $this->testGetUserByEmail();
            $this->testGetNonExistentUser();
            $this->testUpdateUser();
            $this->testUpdateUserEmail();
            $this->testUpdateUserWithDuplicateEmail();
            $this->testUpdateNonExistentUser();
            $this->testDeleteUser();
            $this->testDeleteNonExistentUser();
            $this->testGetUsersWithPagination();
            $this->testGetUsersWithSearch();
            $this->testEmailExists();
            $this->testUpdatePassword();
            $this->testUpdatePasswordWithWeakPassword();
            $this->testUpdateUserRole();
            $this->testUpdateUserRoleWithInvalidRole();
            $this->testSetUserStatus();
            $this->testGetUserStats();
            
            echo "\n✅ All User Model unit tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserTest();
    $test->runAllTests();
}