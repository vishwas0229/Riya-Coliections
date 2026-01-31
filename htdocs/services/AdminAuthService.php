<?php
/**
 * Admin Authentication Service
 * 
 * Handles admin-specific authentication including enhanced security measures,
 * session management, and role-based access control for administrative functions.
 * 
 * Requirements: 11.1, 11.4
 */

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

class AdminAuthService extends AuthService {
    private $db;
    
    // Admin roles and permissions
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_MODERATOR = 'moderator';
    
    // Admin permissions
    const PERMISSION_USER_MANAGEMENT = 'user_management';
    const PERMISSION_ORDER_MANAGEMENT = 'order_management';
    const PERMISSION_PRODUCT_MANAGEMENT = 'product_management';
    const PERMISSION_ANALYTICS_VIEW = 'analytics_view';
    const PERMISSION_SYSTEM_SETTINGS = 'system_settings';
    const PERMISSION_SECURITY_LOGS = 'security_logs';
    
    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
    }
    
    /**
     * Authenticate admin user
     * 
     * @param string $email Admin email
     * @param string $password Admin password
     * @param string $ipAddress Client IP address
     * @return array|false Authentication result or false on failure
     */
    public function authenticateAdmin($email, $password, $ipAddress = null) {
        try {
            // Get admin user
            $admin = $this->getAdminByEmail($email);
            
            if (!$admin) {
                Logger::security('Admin login attempt with invalid email', [
                    'email' => $email,
                    'ip' => $ipAddress
                ]);
                return false;
            }
            
            // Check if admin account is active
            if ($admin['status'] !== 'active') {
                Logger::security('Login attempt with inactive admin account', [
                    'admin_id' => $admin['id'],
                    'email' => $email,
                    'status' => $admin['status'],
                    'ip' => $ipAddress
                ]);
                return false;
            }
            
            // Verify password
            if (!$this->verifyPassword($password, $admin['password_hash'])) {
                // Log failed attempt
                $this->logFailedLoginAttempt($admin['id'], $ipAddress);
                
                Logger::security('Admin login failed - invalid password', [
                    'admin_id' => $admin['id'],
                    'email' => $email,
                    'ip' => $ipAddress
                ]);
                return false;
            }
            
            // Check for account lockout
            if ($this->isAccountLocked($admin['id'])) {
                Logger::security('Login attempt on locked admin account', [
                    'admin_id' => $admin['id'],
                    'email' => $email,
                    'ip' => $ipAddress
                ]);
                return false;
            }
            
            // Check IP whitelist if configured
            if (!$this->isIPAllowed($ipAddress, $admin['allowed_ips'])) {
                Logger::security('Admin login from unauthorized IP', [
                    'admin_id' => $admin['id'],
                    'email' => $email,
                    'ip' => $ipAddress,
                    'allowed_ips' => $admin['allowed_ips']
                ]);
                return false;
            }
            
            // Generate admin token with enhanced payload
            $tokenPayload = [
                'user_id' => $admin['id'],
                'email' => $admin['email'],
                'role' => $admin['role'],
                'permissions' => $this->getAdminPermissions($admin['role']),
                'session_id' => $this->generateSessionId(),
                'ip_address' => $ipAddress,
                'login_time' => time()
            ];
            
            $token = $this->generateToken($tokenPayload);
            
            // Create admin session
            $sessionId = $this->createAdminSession($admin['id'], $token, $ipAddress);
            
            // Clear failed login attempts
            $this->clearFailedLoginAttempts($admin['id']);
            
            // Update last login
            $this->updateLastLogin($admin['id'], $ipAddress);
            
            Logger::info('Admin login successful', [
                'admin_id' => $admin['id'],
                'email' => $email,
                'role' => $admin['role'],
                'ip' => $ipAddress,
                'session_id' => $sessionId
            ]);
            
            return [
                'token' => $token,
                'admin' => [
                    'id' => $admin['id'],
                    'email' => $admin['email'],
                    'first_name' => $admin['first_name'],
                    'last_name' => $admin['last_name'],
                    'role' => $admin['role'],
                    'permissions' => $tokenPayload['permissions']
                ],
                'session_id' => $sessionId
            ];
            
        } catch (Exception $e) {
            Logger::error('Admin authentication error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'ip' => $ipAddress
            ]);
            return false;
        }
    }
    
    /**
     * Logout admin user
     * 
     * @param string $token Admin token
     * @return bool Success status
     */
    public function logoutAdmin($token) {
        try {
            $payload = $this->validateToken($token);
            
            if (!$payload) {
                return false;
            }
            
            // Invalidate session
            $this->invalidateAdminSession($payload->user_id, $payload->session_id ?? null);
            
            Logger::info('Admin logout', [
                'admin_id' => $payload->user_id,
                'session_id' => $payload->session_id ?? 'unknown'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Admin logout error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Validate admin token with enhanced security
     * 
     * @param string $token Admin token
     * @return object|false Token payload or false on failure
     */
    public function validateAdminToken($token) {
        try {
            $payload = $this->validateToken($token);
            
            if (!$payload) {
                return false;
            }
            
            // Check if session is still valid
            if (!$this->isSessionValid($payload->user_id, $payload->session_id ?? null)) {
                Logger::security('Invalid admin session detected', [
                    'admin_id' => $payload->user_id,
                    'session_id' => $payload->session_id ?? 'unknown'
                ]);
                return false;
            }
            
            // Check if admin is still active
            $admin = $this->getAdminById($payload->user_id);
            if (!$admin || $admin['status'] !== 'active') {
                Logger::security('Token validation failed - admin inactive', [
                    'admin_id' => $payload->user_id
                ]);
                return false;
            }
            
            return $payload;
            
        } catch (Exception $e) {
            Logger::error('Admin token validation error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Create admin user
     * 
     * @param array $adminData Admin data
     * @return int|false Admin ID or false on failure
     */
    public function createAdmin($adminData) {
        try {
            // Validate admin data
            $this->validateAdminData($adminData);
            
            // Check if email already exists
            if ($this->getAdminByEmail($adminData['email'])) {
                throw new Exception('Admin email already exists');
            }
            
            // Hash password
            $passwordHash = $this->hashPassword($adminData['password']);
            
            // Insert admin
            $sql = "INSERT INTO admin_users (
                email, password_hash, first_name, last_name, role, 
                status, allowed_ips, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $params = [
                $adminData['email'],
                $passwordHash,
                $adminData['first_name'],
                $adminData['last_name'],
                $adminData['role'] ?? self::ROLE_ADMIN,
                $adminData['status'] ?? 'active',
                $adminData['allowed_ips'] ?? null
            ];
            
            $stmt = $this->db->executeQuery($sql, $params);
            $adminId = $this->db->getConnection()->lastInsertId();
            
            Logger::info('Admin user created', [
                'admin_id' => $adminId,
                'email' => $adminData['email'],
                'role' => $adminData['role'] ?? self::ROLE_ADMIN
            ]);
            
            return $adminId;
            
        } catch (Exception $e) {
            Logger::error('Failed to create admin user', [
                'email' => $adminData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get admin permissions based on role
     * 
     * @param string $role Admin role
     * @return array Permissions
     */
    public function getAdminPermissions($role) {
        $permissions = [
            self::ROLE_SUPER_ADMIN => [
                self::PERMISSION_USER_MANAGEMENT,
                self::PERMISSION_ORDER_MANAGEMENT,
                self::PERMISSION_PRODUCT_MANAGEMENT,
                self::PERMISSION_ANALYTICS_VIEW,
                self::PERMISSION_SYSTEM_SETTINGS,
                self::PERMISSION_SECURITY_LOGS
            ],
            self::ROLE_ADMIN => [
                self::PERMISSION_USER_MANAGEMENT,
                self::PERMISSION_ORDER_MANAGEMENT,
                self::PERMISSION_PRODUCT_MANAGEMENT,
                self::PERMISSION_ANALYTICS_VIEW
            ],
            self::ROLE_MODERATOR => [
                self::PERMISSION_ORDER_MANAGEMENT,
                self::PERMISSION_ANALYTICS_VIEW
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Get admin by email
     * 
     * @param string $email Admin email
     * @return array|null Admin data
     */
    private function getAdminByEmail($email) {
        $sql = "SELECT * FROM admin_users WHERE email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }
    
    /**
     * Get admin by ID
     * 
     * @param int $adminId Admin ID
     * @return array|null Admin data
     */
    private function getAdminById($adminId) {
        $sql = "SELECT * FROM admin_users WHERE id = ?";
        return $this->db->fetchOne($sql, [$adminId]);
    }
    
    /**
     * Create admin session
     * 
     * @param int $adminId Admin ID
     * @param string $token Session token
     * @param string $ipAddress IP address
     * @return string Session ID
     */
    private function createAdminSession($adminId, $token, $ipAddress) {
        $sessionId = $this->generateSessionId();
        
        $sql = "INSERT INTO admin_sessions (
            id, admin_id, token_hash, ip_address, user_agent, 
            created_at, expires_at, last_activity
        ) VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 8 HOUR), NOW())";
        
        $params = [
            $sessionId,
            $adminId,
            hash('sha256', $token),
            $ipAddress,
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        $this->db->executeQuery($sql, $params);
        
        return $sessionId;
    }
    
    /**
     * Check if session is valid
     * 
     * @param int $adminId Admin ID
     * @param string $sessionId Session ID
     * @return bool True if valid
     */
    private function isSessionValid($adminId, $sessionId) {
        if (!$sessionId) {
            return false;
        }
        
        $sql = "SELECT COUNT(*) FROM admin_sessions 
                WHERE id = ? AND admin_id = ? AND expires_at > NOW()";
        
        $count = $this->db->fetchColumn($sql, [$sessionId, $adminId]);
        return $count > 0;
    }
    
    /**
     * Invalidate admin session
     * 
     * @param int $adminId Admin ID
     * @param string $sessionId Session ID
     */
    private function invalidateAdminSession($adminId, $sessionId = null) {
        if ($sessionId) {
            $sql = "DELETE FROM admin_sessions WHERE id = ? AND admin_id = ?";
            $this->db->executeQuery($sql, [$sessionId, $adminId]);
        } else {
            // Invalidate all sessions for admin
            $sql = "DELETE FROM admin_sessions WHERE admin_id = ?";
            $this->db->executeQuery($sql, [$adminId]);
        }
    }
    
    /**
     * Log failed login attempt
     * 
     * @param int $adminId Admin ID
     * @param string $ipAddress IP address
     */
    private function logFailedLoginAttempt($adminId, $ipAddress) {
        $sql = "INSERT INTO admin_login_attempts (
            admin_id, ip_address, success, attempted_at
        ) VALUES (?, ?, 0, NOW())";
        
        $this->db->executeQuery($sql, [$adminId, $ipAddress]);
    }
    
    /**
     * Clear failed login attempts
     * 
     * @param int $adminId Admin ID
     */
    private function clearFailedLoginAttempts($adminId) {
        $sql = "DELETE FROM admin_login_attempts 
                WHERE admin_id = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $this->db->executeQuery($sql, [$adminId]);
    }
    
    /**
     * Check if account is locked
     * 
     * @param int $adminId Admin ID
     * @return bool True if locked
     */
    private function isAccountLocked($adminId) {
        $sql = "SELECT COUNT(*) FROM admin_login_attempts 
                WHERE admin_id = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        
        $failedAttempts = $this->db->fetchColumn($sql, [$adminId]);
        
        return $failedAttempts >= 5; // Lock after 5 failed attempts in 15 minutes
    }
    
    /**
     * Check if IP is allowed
     * 
     * @param string $ipAddress IP address
     * @param string $allowedIps Comma-separated allowed IPs
     * @return bool True if allowed
     */
    private function isIPAllowed($ipAddress, $allowedIps) {
        if (empty($allowedIps)) {
            return true; // No IP restriction
        }
        
        $allowedList = array_map('trim', explode(',', $allowedIps));
        return in_array($ipAddress, $allowedList);
    }
    
    /**
     * Update last login
     * 
     * @param int $adminId Admin ID
     * @param string $ipAddress IP address
     */
    private function updateLastLogin($adminId, $ipAddress) {
        $sql = "UPDATE admin_users 
                SET last_login_at = NOW(), last_login_ip = ? 
                WHERE id = ?";
        
        $this->db->executeQuery($sql, [$ipAddress, $adminId]);
    }
    
    /**
     * Generate session ID
     * 
     * @return string Session ID
     */
    private function generateSessionId() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Validate admin data
     * 
     * @param array $adminData Admin data
     * @throws Exception If validation fails
     */
    private function validateAdminData($adminData) {
        $required = ['email', 'password', 'first_name', 'last_name'];
        
        foreach ($required as $field) {
            if (empty($adminData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        if (!filter_var($adminData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        if (strlen($adminData['password']) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
        
        $validRoles = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_MODERATOR];
        if (isset($adminData['role']) && !in_array($adminData['role'], $validRoles)) {
            throw new Exception('Invalid admin role');
        }
    }
}

// Global helper functions
function getAdminAuthService() {
    static $service = null;
    if ($service === null) {
        $service = new AdminAuthService();
    }
    return $service;
}

function authenticateAdmin($email, $password, $ipAddress = null) {
    return getAdminAuthService()->authenticateAdmin($email, $password, $ipAddress);
}

function validateAdminToken($token) {
    return getAdminAuthService()->validateAdminToken($token);
}