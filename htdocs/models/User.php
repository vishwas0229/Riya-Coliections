<?php
/**
 * User Model Class
 * 
 * Comprehensive User model that provides CRUD operations for user management,
 * including registration, profile updates, and data retrieval.
 * Maintains API compatibility with the existing Node.js backend.
 * 
 * Requirements: 3.1, 3.2, 16.1
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class User extends DatabaseModel {
    protected $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('users');
        $this->setPrimaryKey('id');
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new user
     * 
     * @param array $userData User data
     * @return array Created user data
     * @throws Exception If creation fails
     */
    public function createUser($userData) {
        try {
            $this->beginTransaction();
            
            // Validate user data
            $this->validateUserData($userData, true);
            
            // Check email uniqueness
            if ($this->emailExists($userData['email'])) {
                throw new Exception('User with this email already exists', 409);
            }
            
            // Prepare user data for insertion
            $insertData = [
                'email' => strtolower(trim($userData['email'])),
                'password_hash' => $this->hashPassword($userData['password']),
                'first_name' => trim($userData['first_name']),
                'last_name' => trim($userData['last_name']),
                'phone' => !empty($userData['phone']) ? $this->formatPhone($userData['phone']) : null,
                'role' => $userData['role'] ?? 'customer',
                'is_active' => $userData['is_active'] ?? true
            ];
            
            // Insert user
            $userId = $this->insert($insertData);
            
            // Get created user
            $user = $this->getUserById($userId);
            
            $this->commit();
            
            Logger::info('User created successfully', [
                'user_id' => $userId,
                'email' => $userData['email']
            ]);
            
            return $this->sanitizeUserData($user);
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('User creation failed', [
                'email' => $userData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|null User data or null if not found
     */
    public function getUserById($userId) {
        try {
            $user = $this->find($userId);
            
            if (!$user || !$user['is_active']) {
                return null;
            }
            
            return $this->sanitizeUserData($user);
            
        } catch (Exception $e) {
            Logger::error('Failed to get user by ID', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get user by email
     * 
     * @param string $email User email
     * @return array|null User data or null if not found
     */
    public function getUserByEmail($email) {
        try {
            $user = $this->first(['email' => strtolower(trim($email)), 'is_active' => true]);
            
            if (!$user) {
                return null;
            }
            
            return $this->sanitizeUserData($user);
            
        } catch (Exception $e) {
            Logger::error('Failed to get user by email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Update user profile
     * 
     * @param int $userId User ID
     * @param array $updateData Data to update
     * @return array Updated user data
     * @throws Exception If update fails
     */
    public function updateUser($userId, $updateData) {
        try {
            $this->beginTransaction();
            
            // Get existing user
            $existingUser = $this->find($userId);
            if (!$existingUser) {
                throw new Exception('User not found', 404);
            }
            
            // Validate update data
            $this->validateUserData($updateData, false);
            
            // Check email uniqueness if email is being updated
            if (isset($updateData['email']) && $updateData['email'] !== $existingUser['email']) {
                if ($this->emailExists($updateData['email'], $userId)) {
                    throw new Exception('Email already exists', 409);
                }
            }
            
            // Prepare update data
            $allowedFields = ['first_name', 'last_name', 'phone', 'email'];
            $filteredData = [];
            
            foreach ($updateData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    switch ($field) {
                        case 'email':
                            $filteredData[$field] = strtolower(trim($value));
                            break;
                        case 'first_name':
                        case 'last_name':
                            $filteredData[$field] = trim($value);
                            break;
                        case 'phone':
                            $filteredData[$field] = !empty($value) ? $this->formatPhone($value) : null;
                            break;
                        default:
                            $filteredData[$field] = $value;
                    }
                }
            }
            
            if (empty($filteredData)) {
                throw new Exception('No valid fields to update', 400);
            }
            
            // Update user
            $updated = $this->updateById($userId, $filteredData);
            
            if (!$updated) {
                throw new Exception('Failed to update user', 500);
            }
            
            // Get updated user
            $user = $this->getUserById($userId);
            
            $this->commit();
            
            Logger::info('User updated successfully', [
                'user_id' => $userId,
                'updated_fields' => array_keys($filteredData)
            ]);
            
            return $user;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('User update failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete user (soft delete)
     * 
     * @param int $userId User ID
     * @return bool Success status
     * @throws Exception If deletion fails
     */
    public function deleteUser($userId) {
        try {
            $this->beginTransaction();
            
            // Check if user exists
            $user = $this->find($userId);
            if (!$user) {
                throw new Exception('User not found', 404);
            }
            
            // Soft delete by setting is_active to false
            $updated = $this->updateById($userId, [
                'is_active' => false,
                'email' => $user['email'] . '_deleted_' . time() // Prevent email conflicts
            ]);
            
            if (!$updated) {
                throw new Exception('Failed to delete user', 500);
            }
            
            $this->commit();
            
            Logger::info('User deleted successfully', [
                'user_id' => $userId,
                'email' => $user['email']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('User deletion failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all users with pagination and filtering
     * 
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated user data
     */
    public function getUsers($filters = [], $page = 1, $perPage = 20) {
        try {
            // Build conditions
            $conditions = ['is_active' => true];
            
            // Add search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $sql = "SELECT * FROM users WHERE is_active = 1 AND (
                    first_name LIKE ? OR 
                    last_name LIKE ? OR 
                    email LIKE ? OR 
                    CONCAT(first_name, ' ', last_name) LIKE ?
                )";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
                
                // Add role filter if specified
                if (!empty($filters['role'])) {
                    $sql .= " AND role = ?";
                    $params[] = $filters['role'];
                }
                
                // Add ordering and pagination
                $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $params[] = (int)$perPage;
                $params[] = (int)(($page - 1) * $perPage);
                
                $users = $this->db->fetchAll($sql, $params);
                
                // Get total count for pagination
                $countSql = "SELECT COUNT(*) FROM users WHERE is_active = 1 AND (
                    first_name LIKE ? OR 
                    last_name LIKE ? OR 
                    email LIKE ? OR 
                    CONCAT(first_name, ' ', last_name) LIKE ?
                )";
                $countParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
                
                if (!empty($filters['role'])) {
                    $countSql .= " AND role = ?";
                    $countParams[] = $filters['role'];
                }
                
                $total = (int)$this->db->fetchColumn($countSql, $countParams);
                
            } else {
                // Add role filter if specified
                if (!empty($filters['role'])) {
                    $conditions['role'] = $filters['role'];
                }
                
                $result = $this->paginate($page, $perPage, $conditions, 'created_at DESC');
                $users = $result['data'];
                $total = $result['pagination']['total'];
            }
            
            // Sanitize user data
            $sanitizedUsers = array_map([$this, 'sanitizeUserData'], $users);
            
            return [
                'users' => $sanitizedUsers,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'has_next' => $page < ceil($total / $perPage),
                    'has_prev' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get users', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return bool Success status
     * @throws Exception If update fails
     */
    public function updatePassword($userId, $newPassword) {
        try {
            // Validate password
            $this->validatePassword($newPassword);
            
            // Hash password
            $hashedPassword = $this->hashPassword($newPassword);
            
            // Update password
            $updated = $this->updateById($userId, ['password_hash' => $hashedPassword]);
            
            if (!$updated) {
                throw new Exception('Failed to update password', 500);
            }
            
            Logger::info('User password updated', [
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Password update failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update user role (admin only)
     * 
     * @param int $userId User ID
     * @param string $role New role
     * @return bool Success status
     * @throws Exception If update fails
     */
    public function updateUserRole($userId, $role) {
        try {
            // Validate role
            if (!in_array($role, ['customer', 'admin'])) {
                throw new Exception('Invalid role specified', 400);
            }
            
            // Update role
            $updated = $this->updateById($userId, ['role' => $role]);
            
            if (!$updated) {
                throw new Exception('Failed to update user role', 500);
            }
            
            Logger::info('User role updated', [
                'user_id' => $userId,
                'new_role' => $role
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Role update failed', [
                'user_id' => $userId,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Activate/deactivate user account
     * 
     * @param int $userId User ID
     * @param bool $isActive Active status
     * @return bool Success status
     * @throws Exception If update fails
     */
    public function setUserStatus($userId, $isActive) {
        try {
            $updated = $this->updateById($userId, ['is_active' => $isActive]);
            
            if (!$updated) {
                throw new Exception('Failed to update user status', 500);
            }
            
            Logger::info('User status updated', [
                'user_id' => $userId,
                'is_active' => $isActive
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Status update failed', [
                'user_id' => $userId,
                'is_active' => $isActive,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get user statistics
     * 
     * @return array User statistics
     */
    public function getUserStats() {
        try {
            $stats = [];
            
            // Total users
            $stats['total_users'] = $this->count(['is_active' => true]);
            
            // Users by role
            $stats['customers'] = $this->count(['role' => 'customer', 'is_active' => true]);
            $stats['admins'] = $this->count(['role' => 'admin', 'is_active' => true]);
            
            // Recent registrations (last 30 days)
            $sql = "SELECT COUNT(*) FROM users WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stats['recent_registrations'] = (int)$this->db->fetchColumn($sql);
            
            // Active users (logged in within last 30 days)
            $sql = "SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stats['active_users'] = (int)$this->db->fetchColumn($sql);
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Failed to get user statistics', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Check if email exists
     * 
     * @param string $email Email to check
     * @param int|null $excludeUserId User ID to exclude from check
     * @return bool True if email exists
     */
    public function emailExists($email, $excludeUserId = null) {
        try {
            $conditions = ['email' => strtolower(trim($email))];
            
            if ($excludeUserId) {
                $sql = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
                $params = [$conditions['email'], $excludeUserId];
                return (int)$this->db->fetchColumn($sql, $params) > 0;
            }
            
            return $this->exists($conditions);
            
        } catch (Exception $e) {
            Logger::error('Email existence check failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Validate user data
     * 
     * @param array $userData User data to validate
     * @param bool $isCreation Whether this is for user creation
     * @throws Exception If validation fails
     */
    private function validateUserData($userData, $isCreation = false) {
        $errors = [];
        
        // Email validation
        if ($isCreation && empty($userData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } elseif (!empty($userData['email']) && strlen($userData['email']) > 255) {
            $errors[] = 'Email is too long (maximum 255 characters)';
        }
        
        // Password validation (only for creation)
        if ($isCreation) {
            if (empty($userData['password'])) {
                $errors[] = 'Password is required';
            } else {
                try {
                    $this->validatePassword($userData['password']);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        
        // Name validation
        if ($isCreation && empty($userData['first_name'])) {
            $errors[] = 'First name is required';
        } elseif (!empty($userData['first_name']) && strlen(trim($userData['first_name'])) > 100) {
            $errors[] = 'First name is too long (maximum 100 characters)';
        }
        
        if ($isCreation && empty($userData['last_name'])) {
            $errors[] = 'Last name is required';
        } elseif (!empty($userData['last_name']) && strlen(trim($userData['last_name'])) > 100) {
            $errors[] = 'Last name is too long (maximum 100 characters)';
        }
        
        // Phone validation (if provided)
        if (!empty($userData['phone'])) {
            if (!preg_match('/^[+]?[0-9\s\-\(\)]{10,20}$/', $userData['phone'])) {
                $errors[] = 'Invalid phone number format';
            }
        }
        
        // Role validation
        if (!empty($userData['role']) && !in_array($userData['role'], ['customer', 'admin'])) {
            $errors[] = 'Invalid role specified';
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
            throw new Exception('Password must be at least 8 characters long');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception('Password must contain at least one uppercase letter');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception('Password must contain at least one lowercase letter');
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception('Password must contain at least one number');
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new Exception('Password must contain at least one special character');
        }
    }
    
    /**
     * Hash password using bcrypt
     * 
     * @param string $password Plain password
     * @return string Hashed password
     */
    private function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Format phone number
     * 
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    private function formatPhone($phone) {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^+0-9]/', '', $phone);
        
        // Ensure it starts with + for international numbers
        if (!empty($cleaned) && $cleaned[0] !== '+' && strlen($cleaned) > 10) {
            $cleaned = '+' . $cleaned;
        }
        
        return $cleaned;
    }
    
    /**
     * Sanitize user data for API response
     * 
     * @param array $user User data
     * @return array Sanitized user data
     */
    private function sanitizeUserData($user) {
        return [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'full_name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'phone' => $user['phone'],
            'role' => $user['role'],
            'is_active' => (bool)$user['is_active'],
            'email_verified_at' => $user['email_verified_at'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'last_login_at' => $user['last_login_at']
        ];
    }
}