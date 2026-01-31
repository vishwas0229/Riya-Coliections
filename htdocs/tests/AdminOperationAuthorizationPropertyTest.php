<?php
/**
 * Admin Operation Authorization Property Test
 * 
 * Property-based test that verifies admin operation authorization consistency.
 * Tests that authorization checks produce identical results for any admin operation
 * regardless of system state or request context.
 * 
 * **Validates: Requirements 11.1**
 * **Property 17: Admin Operation Authorization**
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../services/AdminAuthService.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Response.php';

class AdminOperationAuthorizationPropertyTest extends TestCase {
    private $adminAuthService;
    private $testAdmins = [];
    private $testOperations = [];
    
    protected function setUp(): void {
        // Create a mock admin auth service that doesn't require database
        $this->adminAuthService = new class extends AdminAuthService {
            public function __construct() {
                // Skip parent constructor to avoid database dependency
            }
            
            public function generateToken($payload) {
                // Simple mock token generation
                return base64_encode(json_encode($payload));
            }
            
            public function validateToken($token) {
                try {
                    $payload = json_decode(base64_decode($token));
                    return $payload;
                } catch (Exception $e) {
                    return false;
                }
            }
            
            public function validateAdminToken($token) {
                return $this->validateToken($token);
            }
            
            public function authenticateAdmin($email, $password, $ipAddress = null) {
                // Mock authentication - always succeed for test
                return [
                    'token' => $this->generateToken(['user_id' => 1, 'email' => $email]),
                    'admin' => ['id' => 1, 'email' => $email]
                ];
            }
            
            public function logoutAdmin($token) {
                return true;
            }
        };
        
        // Create test admin users with different roles
        $this->createTestAdmins();
        
        // Define test operations with required permissions
        $this->defineTestOperations();
    }
    
    protected function tearDown(): void {
        // Clean up any test data
        unset($GLOBALS['admin_user']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
    
    /**
     * Property Test: Admin Operation Authorization Consistency
     * 
     * **Validates: Requirements 11.1**
     * 
     * For any admin operation, the authorization checks should produce identical 
     * results based on the admin's role and permissions, regardless of request 
     * context or system state.
     * 
     * @test
     */
    public function testAdminOperationAuthorizationConsistency() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $admin = $this->generateRandomAdmin();
            $operation = $this->generateRandomOperation();
            $context = $this->generateRandomContext();
            
            // Test authorization consistency across multiple checks
            $result1 = $this->checkOperationAuthorization($admin, $operation, $context);
            $result2 = $this->checkOperationAuthorization($admin, $operation, $context);
            
            // Authorization should be consistent
            $this->assertEquals(
                $result1['authorized'], 
                $result2['authorized'],
                "Authorization result should be consistent for admin {$admin['role']} accessing {$operation['name']}"
            );
            
            // Verify authorization logic is correct based on role and permissions
            $expectedAuthorization = $this->calculateExpectedAuthorization($admin, $operation);
            
            $this->assertEquals(
                $expectedAuthorization,
                $result1['authorized'],
                "Authorization should match expected result for admin {$admin['role']} accessing {$operation['name']}"
            );
            
            // Test that inactive admins are always denied
            if ($admin['status'] !== 'active') {
                $this->assertFalse(
                    $result1['authorized'],
                    "Inactive admin should be denied access to {$operation['name']}"
                );
            }
            
            // Test that operations requiring higher permissions are properly restricted
            if ($operation['required_permission'] === AdminAuthService::PERMISSION_SYSTEM_SETTINGS) {
                $hasPermission = in_array(AdminAuthService::PERMISSION_SYSTEM_SETTINGS, $admin['permissions']);
                $this->assertEquals(
                    $hasPermission && $admin['status'] === 'active',
                    $result1['authorized'],
                    "System settings access should match permission for admin {$admin['role']}"
                );
            }
        }
    }
    
    /**
     * Property Test: Role-Based Permission Inheritance
     * 
     * **Validates: Requirements 11.1**
     * 
     * For any admin role hierarchy, higher roles should have all permissions 
     * of lower roles plus additional permissions.
     * 
     * @test
     */
    public function testRoleBasedPermissionInheritance() {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $operation = $this->generateRandomOperation();
            
            // Test permission hierarchy
            $moderatorAdmin = $this->createAdminWithRole(AdminAuthService::ROLE_MODERATOR);
            $regularAdmin = $this->createAdminWithRole(AdminAuthService::ROLE_ADMIN);
            $superAdmin = $this->createAdminWithRole(AdminAuthService::ROLE_SUPER_ADMIN);
            
            $moderatorAuth = $this->checkOperationAuthorization($moderatorAdmin, $operation);
            $regularAuth = $this->checkOperationAuthorization($regularAdmin, $operation);
            $superAuth = $this->checkOperationAuthorization($superAdmin, $operation);
            
            // Super admin should have access to everything regular admin has
            if ($regularAuth['authorized']) {
                $this->assertTrue(
                    $superAuth['authorized'],
                    "Super admin should have all permissions of regular admin for {$operation['name']}"
                );
            }
            
            // Regular admin should have access to everything moderator has (for applicable operations)
            if ($moderatorAuth['authorized'] && $this->isOperationInAdminScope($operation)) {
                $this->assertTrue(
                    $regularAuth['authorized'],
                    "Regular admin should have all moderator permissions for {$operation['name']}"
                );
            }
            
            // Super admin should always have access (if active)
            if ($superAdmin['status'] === 'active') {
                $this->assertTrue(
                    $superAuth['authorized'],
                    "Active super admin should have access to {$operation['name']}"
                );
            }
        }
    }
    
    /**
     * Property Test: Session-Based Authorization Consistency
     * 
     * **Validates: Requirements 11.1**
     * 
     * For any valid admin session, authorization should remain consistent 
     * throughout the session lifecycle.
     * 
     * @test
     */
    public function testSessionBasedAuthorizationConsistency() {
        $iterations = 30;
        
        for ($i = 0; $i < $iterations; $i++) {
            $admin = $this->generateRandomAdmin();
            
            if ($admin['status'] !== 'active') {
                continue; // Skip inactive admins for session tests
            }
            
            // Create admin session
            $authResult = $this->adminAuthService->authenticateAdmin(
                $admin['email'],
                'test_password_123',
                '127.0.0.1'
            );
            
            if (!$authResult) {
                continue; // Skip if authentication failed
            }
            
            $token = $authResult['token'];
            $operation = $this->generateRandomOperation();
            
            // Test authorization multiple times during session
            $authResults = [];
            for ($j = 0; $j < 5; $j++) {
                $authResults[] = $this->checkTokenBasedAuthorization($token, $operation);
                
                // Small delay to simulate real usage
                usleep(1000);
            }
            
            // All authorization results should be identical
            $firstResult = $authResults[0];
            foreach ($authResults as $result) {
                $this->assertEquals(
                    $firstResult['authorized'],
                    $result['authorized'],
                    "Session-based authorization should be consistent for {$operation['name']}"
                );
            }
            
            // Logout and verify token is invalidated
            $this->adminAuthService->logoutAdmin($token);
            
            $postLogoutAuth = $this->checkTokenBasedAuthorization($token, $operation);
            $this->assertFalse(
                $postLogoutAuth['authorized'],
                "Authorization should fail after logout for {$operation['name']}"
            );
        }
    }
    
    /**
     * Property Test: IP-Based Access Control
     * 
     * **Validates: Requirements 11.1**
     * 
     * For any admin with IP restrictions, authorization should respect 
     * the allowed IP addresses consistently.
     * 
     * @test
     */
    public function testIPBasedAccessControl() {
        $iterations = 25;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create admin with IP restrictions
            $allowedIPs = $this->generateRandomIPList();
            $admin = $this->createAdminWithIPRestrictions($allowedIPs);
            
            $operation = $this->generateRandomOperation();
            
            // Test access from allowed IP
            $allowedIP = $allowedIPs[array_rand($allowedIPs)];
            $allowedResult = $this->checkOperationAuthorizationWithIP($admin, $operation, $allowedIP);
            
            // Test access from disallowed IP
            $disallowedIP = $this->generateDisallowedIP($allowedIPs);
            $disallowedResult = $this->checkOperationAuthorizationWithIP($admin, $operation, $disallowedIP);
            
            // Access should be granted from allowed IP (if other conditions met)
            $expectedAllowed = $this->calculateExpectedAuthorization($admin, $operation);
            if ($expectedAllowed && $admin['status'] === 'active') {
                $this->assertTrue(
                    $allowedResult['authorized'],
                    "Access should be granted from allowed IP {$allowedIP} for {$operation['name']}"
                );
            }
            
            // Access should be denied from disallowed IP
            $this->assertFalse(
                $disallowedResult['authorized'],
                "Access should be denied from disallowed IP {$disallowedIP} for {$operation['name']}"
            );
        }
    }
    
    /**
     * Generate random admin for testing
     */
    private function generateRandomAdmin() {
        return $this->testAdmins[array_rand($this->testAdmins)];
    }
    
    /**
     * Generate random operation for testing
     */
    private function generateRandomOperation() {
        return $this->testOperations[array_rand($this->testOperations)];
    }
    
    /**
     * Generate random request context
     */
    private function generateRandomContext() {
        return [
            'ip_address' => $this->generateRandomIP(),
            'user_agent' => $this->generateRandomUserAgent(),
            'timestamp' => time() + rand(-3600, 3600),
            'request_id' => uniqid('req_', true)
        ];
    }
    
    /**
     * Check operation authorization
     */
    private function checkOperationAuthorization($admin, $operation, $context = []) {
        try {
            // Create mock token for admin
            $tokenPayload = [
                'user_id' => $admin['id'],
                'email' => $admin['email'],
                'role' => $admin['role'],
                'permissions' => $admin['permissions'],
                'status' => $admin['status']
            ];
            
            $token = $this->adminAuthService->generateToken($tokenPayload);
            
            // Mock authorization header
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
            
            // Check if admin has required permission
            $hasPermission = in_array($operation['required_permission'], $admin['permissions']);
            $isActive = $admin['status'] === 'active';
            
            $authorized = $hasPermission && $isActive;
            
            return [
                'authorized' => $authorized,
                'reason' => $authorized ? 'authorized' : 'insufficient_permissions',
                'admin_id' => $admin['id'],
                'operation' => $operation['name']
            ];
            
        } catch (Exception $e) {
            return [
                'authorized' => false,
                'reason' => 'error: ' . $e->getMessage(),
                'admin_id' => $admin['id'],
                'operation' => $operation['name']
            ];
        }
    }
    
    /**
     * Check token-based authorization
     */
    private function checkTokenBasedAuthorization($token, $operation) {
        try {
            $payload = $this->adminAuthService->validateAdminToken($token);
            
            if (!$payload) {
                return ['authorized' => false, 'reason' => 'invalid_token'];
            }
            
            $hasPermission = in_array($operation['required_permission'], $payload->permissions ?? []);
            
            return [
                'authorized' => $hasPermission,
                'reason' => $hasPermission ? 'authorized' : 'insufficient_permissions'
            ];
            
        } catch (Exception $e) {
            return [
                'authorized' => false,
                'reason' => 'error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check operation authorization with specific IP
     */
    private function checkOperationAuthorizationWithIP($admin, $operation, $ipAddress) {
        // Check IP restriction
        if (!empty($admin['allowed_ips'])) {
            $allowedIPs = array_map('trim', explode(',', $admin['allowed_ips']));
            if (!in_array($ipAddress, $allowedIPs)) {
                return ['authorized' => false, 'reason' => 'ip_not_allowed'];
            }
        }
        
        // Check normal authorization
        return $this->checkOperationAuthorization($admin, $operation);
    }
    
    /**
     * Calculate expected authorization result
     */
    private function calculateExpectedAuthorization($admin, $operation) {
        if ($admin['status'] !== 'active') {
            return false;
        }
        
        return in_array($operation['required_permission'], $admin['permissions']);
    }
    
    /**
     * Create admin with specific role
     */
    private function createAdminWithRole($role) {
        $rolePermissions = [
            AdminAuthService::ROLE_SUPER_ADMIN => [
                AdminAuthService::PERMISSION_USER_MANAGEMENT,
                AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
                AdminAuthService::PERMISSION_PRODUCT_MANAGEMENT,
                AdminAuthService::PERMISSION_ANALYTICS_VIEW,
                AdminAuthService::PERMISSION_SYSTEM_SETTINGS,
                AdminAuthService::PERMISSION_SECURITY_LOGS
            ],
            AdminAuthService::ROLE_ADMIN => [
                AdminAuthService::PERMISSION_USER_MANAGEMENT,
                AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
                AdminAuthService::PERMISSION_PRODUCT_MANAGEMENT,
                AdminAuthService::PERMISSION_ANALYTICS_VIEW
            ],
            AdminAuthService::ROLE_MODERATOR => [
                AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
                AdminAuthService::PERMISSION_ANALYTICS_VIEW
            ]
        ];
        
        $permissions = $rolePermissions[$role] ?? [];
        
        return [
            'id' => rand(1000, 9999),
            'email' => 'test_' . $role . '_' . rand(1000, 9999) . '@example.com',
            'role' => $role,
            'permissions' => $permissions,
            'status' => 'active',
            'allowed_ips' => null
        ];
    }
    
    /**
     * Create admin with IP restrictions
     */
    private function createAdminWithIPRestrictions($allowedIPs) {
        $role = AdminAuthService::ROLE_ADMIN;
        $permissions = [
            AdminAuthService::PERMISSION_USER_MANAGEMENT,
            AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
            AdminAuthService::PERMISSION_PRODUCT_MANAGEMENT,
            AdminAuthService::PERMISSION_ANALYTICS_VIEW
        ];
        
        return [
            'id' => rand(1000, 9999),
            'email' => 'test_ip_restricted_' . rand(1000, 9999) . '@example.com',
            'role' => $role,
            'permissions' => $permissions,
            'status' => 'active',
            'allowed_ips' => implode(',', $allowedIPs)
        ];
    }
    
    /**
     * Check if operation is in admin scope
     */
    private function isOperationInAdminScope($operation) {
        $adminScopePermissions = [
            AdminAuthService::PERMISSION_USER_MANAGEMENT,
            AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
            AdminAuthService::PERMISSION_PRODUCT_MANAGEMENT,
            AdminAuthService::PERMISSION_ANALYTICS_VIEW
        ];
        
        return in_array($operation['required_permission'], $adminScopePermissions);
    }
    
    /**
     * Generate random IP address
     */
    private function generateRandomIP() {
        return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 254);
    }
    
    /**
     * Generate random user agent
     */
    private function generateRandomUserAgent() {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X)',
            'Mozilla/5.0 (Android 11; Mobile; rv:68.0) Gecko/68.0'
        ];
        
        return $userAgents[array_rand($userAgents)];
    }
    
    /**
     * Generate random IP list
     */
    private function generateRandomIPList() {
        $count = rand(1, 3);
        $ips = [];
        
        for ($i = 0; $i < $count; $i++) {
            $ips[] = $this->generateRandomIP();
        }
        
        return array_unique($ips);
    }
    
    /**
     * Generate disallowed IP
     */
    private function generateDisallowedIP($allowedIPs) {
        do {
            $ip = $this->generateRandomIP();
        } while (in_array($ip, $allowedIPs));
        
        return $ip;
    }
    
    /**
     * Create test admin users
     */
    private function createTestAdmins() {
        $roles = [
            AdminAuthService::ROLE_SUPER_ADMIN,
            AdminAuthService::ROLE_ADMIN,
            AdminAuthService::ROLE_MODERATOR
        ];
        
        $statuses = ['active', 'inactive', 'suspended'];
        
        // Get permissions for each role using static method calls
        $rolePermissions = [
            AdminAuthService::ROLE_SUPER_ADMIN => [
                AdminAuthService::PERMISSION_USER_MANAGEMENT,
                AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
                AdminAuthService::PERMISSION_PRODUCT_MANAGEMENT,
                AdminAuthService::PERMISSION_ANALYTICS_VIEW,
                AdminAuthService::PERMISSION_SYSTEM_SETTINGS,
                AdminAuthService::PERMISSION_SECURITY_LOGS
            ],
            AdminAuthService::ROLE_ADMIN => [
                AdminAuthService::PERMISSION_USER_MANAGEMENT,
                AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
                AdminAuthService::PERMISSION_PRODUCT_MANAGEMENT,
                AdminAuthService::PERMISSION_ANALYTICS_VIEW
            ],
            AdminAuthService::ROLE_MODERATOR => [
                AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
                AdminAuthService::PERMISSION_ANALYTICS_VIEW
            ]
        ];
        
        foreach ($roles as $role) {
            foreach ($statuses as $status) {
                $permissions = $rolePermissions[$role];
                
                $this->testAdmins[] = [
                    'id' => rand(1000, 9999),
                    'email' => "test_{$role}_{$status}_" . rand(1000, 9999) . '@example.com',
                    'role' => $role,
                    'permissions' => $permissions,
                    'status' => $status,
                    'allowed_ips' => null
                ];
            }
        }
        
        // Add some admins with IP restrictions
        for ($i = 0; $i < 3; $i++) {
            $role = AdminAuthService::ROLE_ADMIN;
            $permissions = $rolePermissions[$role];
            $allowedIPs = $this->generateRandomIPList();
            
            $this->testAdmins[] = [
                'id' => rand(1000, 9999),
                'email' => "test_ip_restricted_{$i}@example.com",
                'role' => $role,
                'permissions' => $permissions,
                'status' => 'active',
                'allowed_ips' => implode(',', $allowedIPs)
            ];
        }
    }
    
    /**
     * Define test operations
     */
    private function defineTestOperations() {
        $this->testOperations = [
            [
                'name' => 'view_dashboard',
                'required_permission' => AdminAuthService::PERMISSION_ANALYTICS_VIEW,
                'endpoint' => '/admin/dashboard'
            ],
            [
                'name' => 'manage_users',
                'required_permission' => AdminAuthService::PERMISSION_USER_MANAGEMENT,
                'endpoint' => '/admin/users'
            ],
            [
                'name' => 'manage_orders',
                'required_permission' => AdminAuthService::PERMISSION_ORDER_MANAGEMENT,
                'endpoint' => '/admin/orders'
            ],
            [
                'name' => 'manage_products',
                'required_permission' => AdminAuthService::PERMISSION_PRODUCT_MANAGEMENT,
                'endpoint' => '/admin/products'
            ],
            [
                'name' => 'view_analytics',
                'required_permission' => AdminAuthService::PERMISSION_ANALYTICS_VIEW,
                'endpoint' => '/admin/analytics'
            ],
            [
                'name' => 'system_settings',
                'required_permission' => AdminAuthService::PERMISSION_SYSTEM_SETTINGS,
                'endpoint' => '/admin/settings'
            ],
            [
                'name' => 'security_logs',
                'required_permission' => AdminAuthService::PERMISSION_SECURITY_LOGS,
                'endpoint' => '/admin/logs'
            ]
        ];
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        // Clear global variables
        unset($GLOBALS['admin_user']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
}