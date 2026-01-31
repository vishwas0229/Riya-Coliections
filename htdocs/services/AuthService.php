<?php
/**
 * Authentication Service
 * 
 * Comprehensive authentication service that provides JWT token handling,
 * password hashing, user authentication, and session management.
 * Compatible with the existing Node.js implementation.
 * 
 * Requirements: 3.1, 3.2, 17.1
 */

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthService {
    private $db;
    private $jwtService;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->jwtService = new JWTService();
    }
    
    /**
     * Register a new user
     * 
     * @param array $userData User registration data
     * @return array Registration result with tokens
     * @throws Exception If registration fails
     */
    public function register($userData) {
        try {
            $this->db->beginTransaction();
            
            // Validate required fields
            $this->validateRegistrationData($userData);
            
            // Check if user already exists
            if ($this->userExists($userData['email'])) {
                throw new Exception('User with this email already exists', 409);
            }
            
            // Hash password
            $hashedPassword = PasswordHash::hash($userData['password']);
            
            // Create user record
            $sql = "INSERT INTO users (email, password_hash, first_name, last_name, phone, role, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $params = [
                $userData['email'],
                $hashedPassword,
                $userData['first_name'],
                $userData['last_name'],
                $userData['phone'] ?? null,
                $userData['role'] ?? 'customer'
            ];
            
            $stmt = $this->db->executeQuery($sql, $params);
            $userId = $this->db->getConnection()->lastInsertId();
            
            // Get created user
            $user = $this->getUserById($userId);
            
            // Generate token pair
            $tokenPayload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            $tokens = $this->jwtService->generateTokenPair($tokenPayload);
            
            // Store refresh token in database
            $this->storeRefreshToken($userId, $tokens['refresh_token']);
            
            $this->db->commit();
            
            Logger::info('User registered successfully', [
                'user_id' => $userId,
                'email' => $userData['email']
            ]);
            
            return [
                'user' => $this->sanitizeUserData($user),
                'tokens' => $tokens
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error('User registration failed', [
                'email' => $userData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Authenticate user login
     * 
     * @param string $email User email
     * @param string $password User password
     * @return array Login result with tokens
     * @throws Exception If authentication fails
     */
    public function login($email, $password) {
        try {
            // Find user by email
            $user = $this->getUserByEmail($email);
            
            if (!$user) {
                throw new Exception('Invalid email or password', 401);
            }
            
            // Verify password
            if (!PasswordHash::verify($password, $user['password_hash'])) {
                Logger::security('Failed login attempt', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                throw new Exception('Invalid email or password', 401);
            }
            
            // Check if password needs rehashing
            if (PasswordHash::needsRehash($user['password_hash'])) {
                $this->updatePasswordHash($user['id'], $password);
            }
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Generate token pair
            $tokenPayload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            $tokens = $this->jwtService->generateTokenPair($tokenPayload);
            
            // Store refresh token
            $this->storeRefreshToken($user['id'], $tokens['refresh_token']);
            
            Logger::info('User logged in successfully', [
                'user_id' => $user['id'],
                'email' => $email
            ]);
            
            return [
                'user' => $this->sanitizeUserData($user),
                'tokens' => $tokens
            ];
            
        } catch (Exception $e) {
            Logger::error('User login failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Refresh access token using refresh token
     * 
     * @param string $refreshToken Refresh token
     * @return array New token pair
     * @throws Exception If refresh fails
     */
    public function refreshToken($refreshToken) {
        try {
            // Verify refresh token
            $payload = $this->jwtService->verifyRefreshToken($refreshToken);
            
            // Check if refresh token exists in database
            if (!$this->isValidRefreshToken($payload['user_id'], $refreshToken)) {
                throw new Exception('Invalid refresh token', 401);
            }
            
            // Get current user data
            $user = $this->getUserById($payload['user_id']);
            
            if (!$user) {
                throw new Exception('User not found', 404);
            }
            
            // Generate new token pair
            $tokenPayload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            $tokens = $this->jwtService->generateTokenPair($tokenPayload);
            
            // Replace old refresh token with new one
            $this->replaceRefreshToken($user['id'], $refreshToken, $tokens['refresh_token']);
            
            Logger::info('Token refreshed successfully', [
                'user_id' => $user['id']
            ]);
            
            return [
                'user' => $this->sanitizeUserData($user),
                'tokens' => $tokens
            ];
            
        } catch (Exception $e) {
            Logger::error('Token refresh failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Logout user by invalidating refresh token
     * 
     * @param string $refreshToken Refresh token to invalidate
     * @return bool Success status
     */
    public function logout($refreshToken = null) {
        try {
            $user = $this->getCurrentUser();
            
            if ($user && $refreshToken) {
                $this->invalidateRefreshToken($user['user_id'], $refreshToken);
            } elseif ($user) {
                // Invalidate all refresh tokens for user
                $this->invalidateAllRefreshTokens($user['user_id']);
            }
            
            Logger::info('User logged out', [
                'user_id' => $user['user_id'] ?? 'unknown'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Logout failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get current authenticated user
     * 
     * @return array|null User data or null if not authenticated
     */
    public function getCurrentUser() {
        return $this->jwtService->getCurrentUser();
    }
    
    /**
     * Verify access token
     * 
     * @param string $token Access token
     * @return array|false Token payload or false if invalid
     */
    public function verifyAccessToken($token) {
        try {
            return $this->jwtService->verifyAccessToken($token);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool Success status
     * @throws Exception If password change fails
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->getUserById($userId);
            
            if (!$user) {
                throw new Exception('User not found', 404);
            }
            
            // Verify current password
            if (!PasswordHash::verify($currentPassword, $user['password_hash'])) {
                throw new Exception('Current password is incorrect', 400);
            }
            
            // Validate new password
            $this->validatePassword($newPassword);
            
            // Hash new password
            $hashedPassword = PasswordHash::hash($newPassword);
            
            // Update password
            $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->executeQuery($sql, [$hashedPassword, $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Failed to update password', 500);
            }
            
            // Invalidate all refresh tokens to force re-login
            $this->invalidateAllRefreshTokens($userId);
            
            Logger::info('Password changed successfully', [
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Password change failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Reset password (for forgot password functionality)
     * 
     * @param string $email User email
     * @return string Reset token
     * @throws Exception If reset fails
     */
    public function initiatePasswordReset($email) {
        try {
            $user = $this->getUserByEmail($email);
            
            if (!$user) {
                // Don't reveal if email exists or not
                Logger::security('Password reset attempted for non-existent email', [
                    'email' => $email
                ]);
                return 'reset_token_placeholder';
            }
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Store reset token
            $sql = "INSERT INTO password_resets (user_id, token, expires_at, created_at) 
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    token = VALUES(token), 
                    expires_at = VALUES(expires_at), 
                    created_at = VALUES(created_at)";
            
            $this->db->executeQuery($sql, [$user['id'], $resetToken, $expiresAt]);
            
            Logger::info('Password reset initiated', [
                'user_id' => $user['id'],
                'email' => $email
            ]);
            
            return $resetToken;
            
        } catch (Exception $e) {
            Logger::error('Password reset initiation failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Complete password reset
     * 
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return bool Success status
     * @throws Exception If reset fails
     */
    public function completePasswordReset($token, $newPassword) {
        try {
            // Find valid reset token
            $sql = "SELECT pr.*, u.id as user_id, u.email 
                    FROM password_resets pr 
                    JOIN users u ON pr.user_id = u.id 
                    WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL";
            
            $stmt = $this->db->executeQuery($sql, [$token]);
            $reset = $stmt->fetch();
            
            if (!$reset) {
                throw new Exception('Invalid or expired reset token', 400);
            }
            
            // Validate new password
            $this->validatePassword($newPassword);
            
            // Hash new password
            $hashedPassword = PasswordHash::hash($newPassword);
            
            $this->db->beginTransaction();
            
            // Update password
            $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
            $this->db->executeQuery($sql, [$hashedPassword, $reset['user_id']]);
            
            // Mark reset token as used
            $sql = "UPDATE password_resets SET used_at = NOW() WHERE token = ?";
            $this->db->executeQuery($sql, [$token]);
            
            // Invalidate all refresh tokens
            $this->invalidateAllRefreshTokens($reset['user_id']);
            
            $this->db->commit();
            
            Logger::info('Password reset completed', [
                'user_id' => $reset['user_id'],
                'email' => $reset['email']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error('Password reset completion failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Validate user registration data
     * 
     * @param array $userData User data to validate
     * @throws Exception If validation fails
     */
    private function validateRegistrationData($userData) {
        $errors = [];
        
        // Email validation
        if (empty($userData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        // Password validation
        if (empty($userData['password'])) {
            $errors[] = 'Password is required';
        } else {
            $this->validatePassword($userData['password']);
        }
        
        // Name validation
        if (empty($userData['first_name'])) {
            $errors[] = 'First name is required';
        }
        
        if (empty($userData['last_name'])) {
            $errors[] = 'Last name is required';
        }
        
        // Phone validation (if provided)
        if (!empty($userData['phone']) && !preg_match('/^[+]?[0-9\s\-\(\)]{10,15}$/', $userData['phone'])) {
            $errors[] = 'Invalid phone number format';
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
        }
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password Password to validate
     * @throws Exception If password is invalid
     */
    private function validatePassword($password) {
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long', 400);
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception('Password must contain at least one uppercase letter', 400);
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception('Password must contain at least one lowercase letter', 400);
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception('Password must contain at least one number', 400);
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new Exception('Password must contain at least one special character', 400);
        }
    }
    
    /**
     * Check if user exists by email
     * 
     * @param string $email User email
     * @return bool True if user exists
     */
    private function userExists($email) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $stmt = $this->db->executeQuery($sql, [$email]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Get user by email
     * 
     * @param string $email User email
     * @return array|false User data or false if not found
     */
    private function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        $stmt = $this->db->executeQuery($sql, [$email]);
        return $stmt->fetch();
    }
    
    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|false User data or false if not found
     */
    private function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
        $stmt = $this->db->executeQuery($sql, [$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Update user's last login timestamp
     * 
     * @param int $userId User ID
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login_at = NOW() WHERE id = ?";
        $this->db->executeQuery($sql, [$userId]);
    }
    
    /**
     * Update password hash (for rehashing)
     * 
     * @param int $userId User ID
     * @param string $password Plain password
     */
    private function updatePasswordHash($userId, $password) {
        $hashedPassword = PasswordHash::hash($password);
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $this->db->executeQuery($sql, [$hashedPassword, $userId]);
    }
    
    /**
     * Store refresh token in database
     * 
     * @param int $userId User ID
     * @param string $refreshToken Refresh token
     */
    private function storeRefreshToken($userId, $refreshToken) {
        $expiresAt = date('Y-m-d H:i:s', time() + JWTConfig::parseTimeString('7d'));
        
        $sql = "INSERT INTO refresh_tokens (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $this->db->executeQuery($sql, [$userId, $refreshToken, $expiresAt]);
    }
    
    /**
     * Check if refresh token is valid
     * 
     * @param int $userId User ID
     * @param string $refreshToken Refresh token
     * @return bool True if valid
     */
    private function isValidRefreshToken($userId, $refreshToken) {
        $sql = "SELECT COUNT(*) as count FROM refresh_tokens 
                WHERE user_id = ? AND token = ? AND expires_at > NOW() AND revoked_at IS NULL";
        
        $stmt = $this->db->executeQuery($sql, [$userId, $refreshToken]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Replace old refresh token with new one
     * 
     * @param int $userId User ID
     * @param string $oldToken Old refresh token
     * @param string $newToken New refresh token
     */
    private function replaceRefreshToken($userId, $oldToken, $newToken) {
        $this->db->beginTransaction();
        
        try {
            // Revoke old token
            $sql = "UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = ? AND token = ?";
            $this->db->executeQuery($sql, [$userId, $oldToken]);
            
            // Store new token
            $this->storeRefreshToken($userId, $newToken);
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Invalidate specific refresh token
     * 
     * @param int $userId User ID
     * @param string $refreshToken Refresh token to invalidate
     */
    private function invalidateRefreshToken($userId, $refreshToken) {
        $sql = "UPDATE refresh_tokens SET revoked_at = NOW() 
                WHERE user_id = ? AND token = ? AND revoked_at IS NULL";
        
        $this->db->executeQuery($sql, [$userId, $refreshToken]);
    }
    
    /**
     * Invalidate all refresh tokens for user
     * 
     * @param int $userId User ID
     */
    private function invalidateAllRefreshTokens($userId) {
        $sql = "UPDATE refresh_tokens SET revoked_at = NOW() 
                WHERE user_id = ? AND revoked_at IS NULL";
        
        $this->db->executeQuery($sql, [$userId]);
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
            'last_login_at' => $user['last_login_at'] ?? null
        ];
    }
    
    /**
     * Authenticate admin login with enhanced security
     * 
     * @param string $email Admin email
     * @param string $password Admin password
     * @return array Login result with tokens
     * @throws Exception If authentication fails
     */
    public function adminLogin($email, $password) {
        try {
            // Find user by email
            $user = $this->getUserByEmail($email);
            
            if (!$user) {
                throw new Exception('Invalid email or password', 401);
            }
            
            // Check if user is admin
            if ($user['role'] !== 'admin') {
                Logger::security('Non-admin user attempted admin login', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'role' => $user['role'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                throw new Exception('Admin access required', 403);
            }
            
            // Verify password
            if (!PasswordHash::verify($password, $user['password_hash'])) {
                Logger::security('Failed admin login attempt', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                throw new Exception('Invalid email or password', 401);
            }
            
            // Check if admin account is active
            if (!$user['is_active']) {
                Logger::security('Inactive admin account login attempt', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                throw new Exception('Admin account is not active', 403);
            }
            
            // Check if password needs rehashing
            if (PasswordHash::needsRehash($user['password_hash'])) {
                $this->updatePasswordHash($user['id'], $password);
            }
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Generate token pair with admin-specific payload
            $tokenPayload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'permissions' => $this->getAdminPermissions($user['id']),
                'login_type' => 'admin'
            ];
            
            $tokens = $this->jwtService->generateTokenPair($tokenPayload);
            
            // Store refresh token
            $this->storeRefreshToken($user['id'], $tokens['refresh_token']);
            
            Logger::info('Admin logged in successfully', [
                'admin_id' => $user['id'],
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return [
                'user' => $this->sanitizeUserData($user),
                'tokens' => $tokens,
                'permissions' => $tokenPayload['permissions']
            ];
            
        } catch (Exception $e) {
            Logger::error('Admin login failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get admin permissions
     * 
     * @param int $adminId Admin user ID
     * @return array Admin permissions
     */
    private function getAdminPermissions($adminId) {
        // For now, return default admin permissions
        // In a more complex system, this would query a permissions table
        return [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'orders.view',
            'orders.update',
            'orders.delete',
            'dashboard.view',
            'reports.view',
            'settings.update'
        ];
    }
    
    /**
     * Clean up expired tokens (should be called periodically)
     */
    public function cleanupExpiredTokens() {
        try {
            // Clean up expired refresh tokens
            $sql = "DELETE FROM refresh_tokens WHERE expires_at < NOW()";
            $stmt = $this->db->executeQuery($sql);
            $deletedTokens = $stmt->rowCount();
            
            // Clean up expired password reset tokens
            $sql = "DELETE FROM password_resets WHERE expires_at < NOW()";
            $stmt = $this->db->executeQuery($sql);
            $deletedResets = $stmt->rowCount();
            
            Logger::info('Token cleanup completed', [
                'deleted_tokens' => $deletedTokens,
                'deleted_resets' => $deletedResets
            ]);
            
            return [
                'deleted_tokens' => $deletedTokens,
                'deleted_resets' => $deletedResets
            ];
            
        } catch (Exception $e) {
            Logger::error('Token cleanup failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get user session information
     * 
     * @param int $userId User ID
     * @return array Session information
     */
    public function getUserSessions($userId) {
        try {
            $sql = "SELECT token, created_at, expires_at, 
                           CASE WHEN expires_at > NOW() AND revoked_at IS NULL THEN 'active' ELSE 'inactive' END as status
                    FROM refresh_tokens 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC";
            
            $stmt = $this->db->executeQuery($sql, [$userId]);
            $sessions = $stmt->fetchAll();
            
            // Don't return actual tokens for security
            foreach ($sessions as &$session) {
                $session['token'] = substr($session['token'], 0, 8) . '...';
            }
            
            return $sessions;
            
        } catch (Exception $e) {
            Logger::error('Failed to get user sessions', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}