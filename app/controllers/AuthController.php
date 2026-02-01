<?php
/**
 * Authentication Controller
 * 
 * Handles all authentication-related API endpoints including registration,
 * login, token refresh, and user profile management.
 * 
 * Requirements: 3.1, 3.2, 17.1
 */

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class AuthController {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    /**
     * Register a new user
     * POST /api/auth/register
     */
    public function register() {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON data', 400);
                return;
            }
            
            // Register user
            $result = $this->authService->register($input);
            
            Response::success('User registered successfully', $result);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * User login
     * POST /api/auth/login
     */
    public function login() {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['email']) || empty($input['password'])) {
                Response::error('Email and password are required', 400);
                return;
            }
            
            // Authenticate user
            $result = $this->authService->login($input['email'], $input['password']);
            
            Response::success('Login successful', $result);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Refresh access token
     * POST /api/auth/refresh
     */
    public function refresh() {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['refresh_token'])) {
                Response::error('Refresh token is required', 400);
                return;
            }
            
            // Refresh token
            $result = $this->authService->refreshToken($input['refresh_token']);
            
            Response::success('Token refreshed successfully', $result);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Get user profile
     * GET /api/auth/profile
     */
    public function getProfile() {
        try {
            // Require authentication
            $user = AuthMiddleware::requireAuth();
            
            // Get full user data from database
            $db = Database::getInstance();
            $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
            $stmt = $db->executeQuery($sql, [$user['user_id']]);
            $userData = $stmt->fetch();
            
            if (!$userData) {
                Response::error('User not found', 404);
                return;
            }
            
            Response::success('Profile retrieved successfully', [
                'user' => $this->sanitizeUserData($userData)
            ]);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Update user profile
     * PUT /api/auth/profile
     */
    public function updateProfile() {
        try {
            // Require authentication
            $user = AuthMiddleware::requireAuth();
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON data', 400);
                return;
            }
            
            // Update profile
            $result = $this->updateUserProfile($user['user_id'], $input);
            
            Response::success('Profile updated successfully', $result);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Change password
     * POST /api/auth/change-password
     */
    public function changePassword() {
        try {
            // Require authentication
            $user = AuthMiddleware::requireAuth();
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['current_password']) || empty($input['new_password'])) {
                Response::error('Current password and new password are required', 400);
                return;
            }
            
            // Change password
            $this->authService->changePassword(
                $user['user_id'],
                $input['current_password'],
                $input['new_password']
            );
            
            Response::success('Password changed successfully');
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Initiate password reset
     * POST /api/auth/forgot-password
     */
    public function forgotPassword() {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['email'])) {
                Response::error('Email is required', 400);
                return;
            }
            
            // Initiate password reset
            $resetToken = $this->authService->initiatePasswordReset($input['email']);
            
            // In a real application, you would send this token via email
            // For now, we'll return it in the response (not recommended for production)
            Response::success('Password reset initiated. Check your email for instructions.', [
                'reset_token' => $resetToken // Remove this in production
            ]);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Reset password
     * POST /api/auth/reset-password
     */
    public function resetPassword() {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['token']) || empty($input['new_password'])) {
                Response::error('Reset token and new password are required', 400);
                return;
            }
            
            // Reset password
            $this->authService->completePasswordReset($input['token'], $input['new_password']);
            
            Response::success('Password reset successfully');
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Logout user
     * POST /api/auth/logout
     */
    public function logout() {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            $refreshToken = $input['refresh_token'] ?? null;
            
            // Logout user
            $this->authService->logout($refreshToken);
            
            Response::success('Logged out successfully');
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Get user sessions
     * GET /api/auth/sessions
     */
    public function getSessions() {
        try {
            // Require authentication
            $user = AuthMiddleware::requireAuth();
            
            // Get user sessions
            $sessions = $this->authService->getUserSessions($user['user_id']);
            
            Response::success('Sessions retrieved successfully', [
                'sessions' => $sessions
            ]);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Verify token (for middleware/frontend use)
     * GET /api/auth/verify
     */
    public function verifyToken() {
        try {
            // Get token from header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $token = null;
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
            
            if (!$token) {
                Response::error('No token provided', 401);
                return;
            }
            
            // Verify token
            $payload = $this->authService->verifyAccessToken($token);
            
            if (!$payload) {
                Response::error('Invalid token', 401);
                return;
            }
            
            Response::success('Token is valid', [
                'user' => $payload
            ]);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Admin login with enhanced security
     * POST /api/admin/login
     */
    public function adminLogin() {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['email']) || empty($input['password'])) {
                Logger::security('Admin login attempt with missing credentials', [
                    'ip' => $this->getClientIP(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);
                Response::error('Email and password are required', 400);
                return;
            }
            
            // Rate limiting for admin login attempts
            $this->checkAdminLoginRateLimit($input['email']);
            
            // Authenticate admin user
            $result = $this->authService->adminLogin($input['email'], $input['password']);
            
            // Log successful admin login
            Logger::security('Admin login successful', [
                'admin_id' => $result['user']['id'],
                'admin_email' => $result['user']['email'],
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            Response::success('Admin login successful', $result);
            
        } catch (Exception $e) {
            // Log failed admin login attempt
            Logger::security('Admin login failed', [
                'email' => $input['email'] ?? 'unknown',
                'error' => $e->getMessage(),
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Admin password change with additional security
     * POST /api/admin/change-password
     */
    public function adminChangePassword() {
        try {
            // Require admin authentication
            $admin = AuthMiddleware::requireAuth();
            
            if ($admin['role'] !== 'admin') {
                Response::error('Admin access required', 403);
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['current_password']) || empty($input['new_password'])) {
                Response::error('Current password and new password are required', 400);
                return;
            }
            
            // Additional confirmation for admin password change
            if (empty($input['confirmation_password']) || $input['new_password'] !== $input['confirmation_password']) {
                Response::error('Password confirmation does not match', 400);
                return;
            }
            
            // Change admin password
            $this->authService->changePassword(
                $admin['user_id'],
                $input['current_password'],
                $input['new_password']
            );
            
            // Log admin password change
            Logger::security('Admin password changed', [
                'admin_id' => $admin['user_id'],
                'admin_email' => $admin['email'],
                'ip' => $this->getClientIP()
            ]);
            
            Response::success('Admin password changed successfully');
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Get admin profile with additional security information
     * GET /api/admin/profile
     */
    public function getAdminProfile() {
        try {
            // Require admin authentication
            $admin = AuthMiddleware::requireAuth();
            
            if ($admin['role'] !== 'admin') {
                Response::error('Admin access required', 403);
                return;
            }
            
            // Get full admin data from database
            $db = Database::getInstance();
            $sql = "SELECT u.*, 
                           (SELECT COUNT(*) FROM refresh_tokens rt WHERE rt.user_id = u.id AND rt.revoked_at IS NULL AND rt.expires_at > NOW()) as active_sessions,
                           (SELECT MAX(created_at) FROM refresh_tokens rt WHERE rt.user_id = u.id) as last_session_created
                    FROM users u 
                    WHERE u.id = ? AND u.is_active = 1 AND u.role = 'admin'";
            
            $stmt = $db->executeQuery($sql, [$admin['user_id']]);
            $adminData = $stmt->fetch();
            
            if (!$adminData) {
                Response::error('Admin user not found', 404);
                return;
            }
            
            // Get admin permissions and additional security info
            $adminProfile = $this->sanitizeUserData($adminData);
            $adminProfile['security_info'] = [
                'active_sessions' => (int)$adminData['active_sessions'],
                'last_session_created' => $adminData['last_session_created'],
                'password_last_changed' => $adminData['updated_at'], // Assuming password changes update this field
                'login_attempts_today' => $this->getAdminLoginAttempts($admin['user_id'])
            ];
            
            Response::success('Admin profile retrieved successfully', [
                'admin' => $adminProfile
            ]);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Update admin profile with audit logging
     * PUT /api/admin/profile
     */
    public function updateAdminProfile() {
        try {
            // Require admin authentication
            $admin = AuthMiddleware::requireAuth();
            
            if ($admin['role'] !== 'admin') {
                Response::error('Admin access required', 403);
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON data', 400);
                return;
            }
            
            // Update admin profile
            $result = $this->updateUserProfile($admin['user_id'], $input);
            
            // Log admin profile update
            Logger::security('Admin profile updated', [
                'admin_id' => $admin['user_id'],
                'admin_email' => $admin['email'],
                'updated_fields' => array_keys($input),
                'ip' => $this->getClientIP()
            ]);
            
            Response::success('Admin profile updated successfully', $result);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Admin logout with session cleanup
     * POST /api/admin/logout
     */
    public function adminLogout() {
        try {
            // Get current admin
            $admin = AuthMiddleware::requireAuth();
            
            if ($admin['role'] !== 'admin') {
                Response::error('Admin access required', 403);
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            $refreshToken = $input['refresh_token'] ?? null;
            $logoutAll = $input['logout_all'] ?? false;
            
            // Logout admin
            if ($logoutAll) {
                $this->authService->logout(); // This will invalidate all tokens
            } else {
                $this->authService->logout($refreshToken);
            }
            
            // Log admin logout
            Logger::security('Admin logout', [
                'admin_id' => $admin['user_id'],
                'admin_email' => $admin['email'],
                'logout_all' => $logoutAll,
                'ip' => $this->getClientIP()
            ]);
            
            Response::success('Admin logged out successfully');
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Get admin security audit log
     * GET /api/admin/security-log
     */
    public function getAdminSecurityLog() {
        try {
            // Require admin authentication
            $admin = AuthMiddleware::requireAuth();
            
            if ($admin['role'] !== 'admin') {
                Response::error('Admin access required', 403);
                return;
            }
            
            // Get query parameters
            $page = (int)($_GET['page'] ?? 1);
            $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 records per page
            $offset = ($page - 1) * $limit;
            
            // Get security logs (this would require a security_logs table)
            $db = Database::getInstance();
            
            // For now, get recent login activities from refresh_tokens table
            $sql = "SELECT rt.created_at as timestamp, 
                           'login' as event_type,
                           u.email as admin_email,
                           u.id as admin_id,
                           'Admin login' as description
                    FROM refresh_tokens rt
                    JOIN users u ON rt.user_id = u.id
                    WHERE u.role = 'admin'
                    ORDER BY rt.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $db->executeQuery($sql, [$limit, $offset]);
            $logs = $stmt->fetchAll();
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM refresh_tokens rt
                        JOIN users u ON rt.user_id = u.id
                        WHERE u.role = 'admin'";
            $total = (int)$db->executeQuery($countSql)->fetchColumn();
            
            Response::success('Security log retrieved successfully', [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Update user profile data
     * 
     * @param int $userId User ID
     * @param array $data Profile data to update
     * @return array Updated user data
     */
    private function updateUserProfile($userId, $data) {
        $db = Database::getInstance();
        
        // Allowed fields for update
        $allowedFields = ['first_name', 'last_name', 'phone'];
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('No valid fields to update', 400);
        }
        
        // Add user ID to params
        $params[] = $userId;
        
        // Update user
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $db->executeQuery($sql, $params);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update profile', 500);
        }
        
        // Get updated user data
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $db->executeQuery($sql, [$userId]);
        $userData = $stmt->fetch();
        
        Logger::info('User profile updated', [
            'user_id' => $userId,
            'updated_fields' => array_keys(array_intersect_key($data, array_flip($allowedFields)))
        ]);
        
        return [
            'user' => $this->sanitizeUserData($userData)
        ];
    }
    
    /**
     * Sanitize user data for API response
     * 
     * @param array $user User data
     * @return array Sanitized user data
     */
    private function sanitizeUserData($user) {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'last_login_at' => $user['last_login_at'] ?? null
        ];
    }
    
    /**
     * Check admin login rate limiting
     * 
     * @param string $email Admin email
     * @throws Exception If rate limit exceeded
     */
    private function checkAdminLoginRateLimit($email) {
        $db = Database::getInstance();
        
        // Check failed login attempts in the last 15 minutes
        $sql = "SELECT COUNT(*) FROM login_attempts 
                WHERE email = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        
        try {
            $stmt = $db->executeQuery($sql, [$email]);
            $attempts = (int)$stmt->fetchColumn();
            
            if ($attempts >= 5) {
                Logger::security('Admin login rate limit exceeded', [
                    'email' => $email,
                    'attempts' => $attempts,
                    'ip' => $this->getClientIP()
                ]);
                
                throw new Exception('Too many failed login attempts. Please try again later.', 429);
            }
        } catch (Exception $e) {
            // If login_attempts table doesn't exist, continue without rate limiting
            // This is acceptable for the initial implementation
            if (strpos($e->getMessage(), "doesn't exist") === false) {
                throw $e;
            }
        }
    }
    
    /**
     * Get admin login attempts for today
     * 
     * @param int $adminId Admin user ID
     * @return int Number of login attempts today
     */
    private function getAdminLoginAttempts($adminId) {
        $db = Database::getInstance();
        
        try {
            $sql = "SELECT COUNT(*) FROM login_attempts 
                    WHERE user_id = ? AND DATE(created_at) = CURDATE()";
            
            $stmt = $db->executeQuery($sql, [$adminId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            // If login_attempts table doesn't exist, return 0
            return 0;
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}