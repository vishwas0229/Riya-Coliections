<?php
/**
 * Authentication Middleware
 * 
 * Handles authentication checks for protected routes and provides
 * utilities for role-based access control. Enhanced to fully support
 * token extraction from headers, role-based access control, token
 * expiration handling, and security measures.
 * 
 * Requirements: 3.1, 11.1, 17.1
 */

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class AuthMiddleware {
    private static $jwtService = null;
    private static $authService = null;
    
    // Supported user roles
    const ROLE_ADMIN = 'admin';
    const ROLE_CUSTOMER = 'customer';
    const ROLE_MODERATOR = 'moderator';
    
    // Token types
    const TOKEN_TYPE_ACCESS = 'access';
    const TOKEN_TYPE_REFRESH = 'refresh';
    
    /**
     * Get JWT service instance
     */
    private static function getJWTService() {
        if (self::$jwtService === null) {
            self::$jwtService = new JWTService();
        }
        return self::$jwtService;
    }
    
    /**
     * Get Auth service instance
     */
    private static function getAuthService() {
        if (self::$authService === null) {
            self::$authService = new AuthService();
        }
        return self::$authService;
    }
    
    /**
     * Extract token from various header formats
     * Supports: Authorization: Bearer <token>, Authorization: <token>, X-Auth-Token: <token>
     * 
     * @return string|null Token or null if not found
     */
    public static function extractToken() {
        $token = null;
        
        // Try Authorization header with Bearer prefix
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader)) {
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = trim($matches[1]);
            } elseif (preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $authHeader)) {
                // Direct JWT token without Bearer prefix
                $token = $authHeader;
            }
        }
        
        // Try X-Auth-Token header
        if (!$token) {
            $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
        }
        
        // Try X-Access-Token header
        if (!$token) {
            $token = $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? null;
        }
        
        // Try query parameter (for WebSocket or special cases)
        if (!$token && isset($_GET['token'])) {
            $token = $_GET['token'];
        }
        
        // Validate token format
        if ($token && !self::isValidTokenFormat($token)) {
            Logger::security('Invalid token format detected', [
                'token_prefix' => substr($token, 0, 10) . '...',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return null;
        }
        
        return $token;
    }
    
    /**
     * Validate JWT token format
     * 
     * @param string $token Token to validate
     * @return bool True if valid format
     */
    public static function isValidTokenFormat($token) {
        if (empty($token) || !is_string($token)) {
            return false;
        }
        
        // JWT should have 3 parts separated by dots
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        // Each part should be base64url encoded
        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $part)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Require authentication for the current request
     * Enhanced with better token extraction and expiration handling
     * 
     * @return array User data from token
     * @throws Exception If authentication fails
     */
    public static function requireAuth() {
        $user = self::getAuthenticatedUser();
        
        if (!$user) {
            Logger::security('Unauthorized access attempt', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            Response::error('Authentication required', 401);
            exit;
        }
        
        // Check if token is about to expire (within 5 minutes)
        if (isset($user['exp']) && ($user['exp'] - time()) < 300) {
            Logger::info('Token expiring soon', [
                'user_id' => $user['user_id'],
                'expires_in' => $user['exp'] - time()
            ]);
            
            // Add header to suggest token refresh
            header('X-Token-Refresh-Suggested: true');
        }
        
        return $user;
    }
    
    /**
     * Require specific role for the current request
     * Enhanced with hierarchical role support and detailed logging
     * 
     * @param string|array $requiredRoles Required role(s)
     * @param bool $allowHigherRoles Allow higher privilege roles (default: true)
     * @return array User data from token
     * @throws Exception If authorization fails
     */
    public static function requireRole($requiredRoles, $allowHigherRoles = true) {
        $user = self::requireAuth();
        
        if (is_string($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }
        
        $userRole = $user['role'] ?? 'guest';
        $hasAccess = false;
        
        // Check direct role match
        if (in_array($userRole, $requiredRoles)) {
            $hasAccess = true;
        }
        
        // Check hierarchical roles if enabled
        if (!$hasAccess && $allowHigherRoles) {
            $hasAccess = self::hasHigherPrivilege($userRole, $requiredRoles);
        }
        
        if (!$hasAccess) {
            Logger::security('Insufficient permissions', [
                'user_id' => $user['user_id'],
                'user_role' => $userRole,
                'required_roles' => $requiredRoles,
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            Response::error('Insufficient permissions', 403);
            exit;
        }
        
        return $user;
    }
    
    /**
     * Check if user role has higher privilege than required roles
     * 
     * @param string $userRole User's current role
     * @param array $requiredRoles Required roles
     * @return bool True if user has higher privilege
     */
    private static function hasHigherPrivilege($userRole, $requiredRoles) {
        // Role hierarchy (higher index = higher privilege)
        $roleHierarchy = [
            'guest' => 0,
            'customer' => 1,
            'moderator' => 2,
            'admin' => 3
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        
        foreach ($requiredRoles as $role) {
            $requiredLevel = $roleHierarchy[$role] ?? 0;
            if ($userLevel >= $requiredLevel) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Require admin role for the current request
     * 
     * @return array User data from token
     */
    public static function requireAdmin() {
        return self::requireRole('admin');
    }
    
    /**
     * Get authenticated user without requiring authentication
     * Enhanced with improved token extraction and validation
     * 
     * @return array|null User data or null if not authenticated
     */
    public static function getAuthenticatedUser() {
        try {
            // Extract token using enhanced method
            $token = self::extractToken();
            
            if (!$token) {
                return null;
            }
            
            // Verify token using JWT service
            $jwtService = self::getJWTService();
            $payload = $jwtService->verifyAccessToken($token);
            
            // Additional validation
            if (!isset($payload['user_id']) || !isset($payload['email'])) {
                Logger::warning('Invalid token payload structure', [
                    'missing_fields' => array_diff(['user_id', 'email'], array_keys($payload))
                ]);
                return null;
            }
            
            // Check token type (should be access token)
            if (isset($payload['type']) && $payload['type'] !== self::TOKEN_TYPE_ACCESS) {
                Logger::warning('Wrong token type used for authentication', [
                    'token_type' => $payload['type'],
                    'expected' => self::TOKEN_TYPE_ACCESS
                ]);
                return null;
            }
            
            return $payload;
            
        } catch (Exception $e) {
            Logger::warning('Authentication check failed', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return null;
        }
    }
    
    /**
     * Check if current user has specific role
     * Enhanced with hierarchical role support
     * 
     * @param string|array $roles Role(s) to check
     * @param bool $allowHigherRoles Allow higher privilege roles (default: true)
     * @return bool True if user has required role
     */
    public static function hasRole($roles, $allowHigherRoles = true) {
        $user = self::getAuthenticatedUser();
        
        if (!$user) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        $userRole = $user['role'] ?? 'guest';
        
        // Check direct role match
        if (in_array($userRole, $roles)) {
            return true;
        }
        
        // Check hierarchical roles if enabled
        if ($allowHigherRoles) {
            return self::hasHigherPrivilege($userRole, $roles);
        }
        
        return false;
    }
    
    /**
     * Check if current user is admin
     * 
     * @return bool True if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Check if current user owns a resource
     * 
     * @param int $resourceUserId User ID associated with the resource
     * @return bool True if user owns the resource or is admin
     */
    public static function canAccessResource($resourceUserId) {
        $user = self::getAuthenticatedUser();
        
        if (!$user) {
            return false;
        }
        
        // Admin can access any resource
        if ($user['role'] === 'admin') {
            return true;
        }
        
        // User can access their own resources
        return $user['user_id'] == $resourceUserId;
    }
    
    /**
     * Handle token refresh request
     * 
     * @param string $refreshToken Refresh token
     * @return array New token pair or error
     */
    public static function handleTokenRefresh($refreshToken) {
        try {
            if (empty($refreshToken)) {
                throw new Exception('Refresh token is required', 400);
            }
            
            $authService = self::getAuthService();
            $result = $authService->refreshToken($refreshToken);
            
            Logger::info('Token refreshed successfully', [
                'user_id' => $result['user']['id']
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            Logger::security('Token refresh failed', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Check if token needs refresh (within 5 minutes of expiry)
     * 
     * @return bool True if token should be refreshed
     */
    public static function shouldRefreshToken() {
        $user = self::getAuthenticatedUser();
        
        if (!$user || !isset($user['exp'])) {
            return false;
        }
        
        // Suggest refresh if token expires within 5 minutes
        return ($user['exp'] - time()) < 300;
    }
    
    /**
     * Validate token expiration and handle refresh
     * 
     * @param bool $autoRefresh Automatically attempt refresh if needed
     * @return array|null User data or null if expired
     */
    public static function validateTokenExpiration($autoRefresh = false) {
        $user = self::getAuthenticatedUser();
        
        if (!$user) {
            return null;
        }
        
        // Check if token is expired
        if (isset($user['exp']) && $user['exp'] < time()) {
            Logger::info('Token expired', [
                'user_id' => $user['user_id'],
                'expired_at' => date('Y-m-d H:i:s', $user['exp'])
            ]);
            
            if ($autoRefresh) {
                // Try to refresh token automatically (would need refresh token from client)
                header('X-Token-Expired: true');
                header('X-Token-Refresh-Required: true');
            }
            
            return null;
        }
        
        return $user;
    }
    
    /**
     * Middleware handler for protected routes
     * 
     * @param callable $next Next middleware or route handler
     * @param array $options Middleware options (roles, etc.)
     */
    public static function handle($next = null, $options = []) {
        try {
            // Apply rate limiting if specified
            if (isset($options['rate_limit'])) {
                $rateLimit = $options['rate_limit'];
                $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                // Use user ID for authenticated requests
                $user = self::getAuthenticatedUser();
                if ($user) {
                    $identifier = 'user_' . $user['user_id'];
                }
                
                self::applyRateLimit(
                    $rateLimit['max'] ?? 100,
                    $rateLimit['window'] ?? 3600,
                    $identifier
                );
            }
            
            // Check if authentication is required
            if (isset($options['auth']) && $options['auth']) {
                $user = self::requireAuth();
                
                // Check role requirements
                if (isset($options['roles'])) {
                    $allowHigher = $options['allow_higher_roles'] ?? true;
                    self::requireRole($options['roles'], $allowHigher);
                }
                
                // Check resource ownership if specified
                if (isset($options['resource_owner']) && isset($options['resource_user_id'])) {
                    if (!self::canAccessResource($options['resource_user_id'])) {
                        Logger::security('Resource access denied', [
                            'user_id' => $user['user_id'],
                            'resource_user_id' => $options['resource_user_id'],
                            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                        ]);
                        
                        Response::error('Access denied to this resource', 403);
                        exit;
                    }
                }
                
                // Store user in global context for easy access
                $GLOBALS['current_user'] = $user;
            }
            
            // Apply CSRF protection if specified
            if (isset($options['csrf']) && $options['csrf']) {
                if (!self::validateCSRF()) {
                    Logger::security('CSRF token validation failed', [
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                    ]);
                    
                    Response::error('CSRF token validation failed', 403);
                    exit;
                }
            }
            
            // Call next middleware or handler
            if ($next && is_callable($next)) {
                return $next();
            }
            
        } catch (Exception $e) {
            Logger::error('Authentication middleware error', [
                'error' => $e->getMessage(),
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'options' => $options
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
            exit;
        }
    }
    
    /**
     * Extract and validate API key (for API-only access)
     * 
     * @return bool True if valid API key
     */
    public static function validateApiKey() {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        
        if (empty($apiKey)) {
            return false;
        }
        
        // Get valid API keys from environment or config
        $validApiKeys = explode(',', env('VALID_API_KEYS', ''));
        
        if (in_array($apiKey, $validApiKeys)) {
            Logger::info('API key authentication successful', [
                'key_prefix' => substr($apiKey, 0, 8) . '...'
            ]);
            return true;
        }
        
        Logger::security('Invalid API key used', [
            'key_prefix' => substr($apiKey, 0, 8) . '...',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        return false;
    }
    
    /**
     * Rate limiting check
     * 
     * @param string $identifier Rate limit identifier (IP, user ID, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if within rate limit
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        try {
            $db = Database::getInstance();
            
            // Clean up old rate limit entries
            $sql = "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $db->executeQuery($sql, [$timeWindow]);
            
            // Count current requests
            $sql = "SELECT COUNT(*) as count FROM rate_limits 
                    WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $stmt = $db->executeQuery($sql, [$identifier, $timeWindow]);
            $result = $stmt->fetch();
            
            $currentRequests = $result['count'];
            
            if ($currentRequests >= $maxRequests) {
                Logger::security('Rate limit exceeded', [
                    'identifier' => $identifier,
                    'current_requests' => $currentRequests,
                    'max_requests' => $maxRequests,
                    'time_window' => $timeWindow
                ]);
                return false;
            }
            
            // Record this request
            $sql = "INSERT INTO rate_limits (identifier, created_at) VALUES (?, NOW())";
            $db->executeQuery($sql, [$identifier]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Rate limit check failed', [
                'error' => $e->getMessage(),
                'identifier' => $identifier
            ]);
            
            // Allow request if rate limiting fails (fail open)
            return true;
        }
    }
    
    /**
     * Apply rate limiting to current request
     * 
     * @param int $maxRequests Maximum requests allowed
     * @param int $timeWindow Time window in seconds
     * @param string $identifier Custom identifier (defaults to IP)
     */
    public static function applyRateLimit($maxRequests = 100, $timeWindow = 3600, $identifier = null) {
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        if (!self::checkRateLimit($identifier, $maxRequests, $timeWindow)) {
            Response::error('Rate limit exceeded. Please try again later.', 429);
            exit;
        }
    }
    
    /**
     * Validate CSRF token
     * 
     * @return bool True if CSRF token is valid
     */
    public static function validateCSRF() {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token) || empty($sessionToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }
    
    /**
     * Security headers middleware
     */
    public static function setSecurityHeaders() {
        // Prevent XSS attacks
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // HTTPS enforcement
        if (env('FORCE_HTTPS', false)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';";
        header("Content-Security-Policy: $csp");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Log authentication event
     * 
     * @param string $event Event type
     * @param array $data Additional data
     */
    public static function logAuthEvent($event, $data = []) {
        $logData = array_merge([
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ], $data);
        
        Logger::security('Authentication event', $logData);
    }
    
    /**
     * Validate request origin for additional security
     * 
     * @return bool True if origin is valid
     */
    public static function validateOrigin() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($origin) && empty($referer)) {
            // Allow requests without origin/referer (e.g., mobile apps, Postman)
            return true;
        }
        
        $allowedOrigins = explode(',', env('ALLOWED_ORIGINS', ''));
        $allowedOrigins = array_map('trim', $allowedOrigins);
        
        // Check origin
        if (!empty($origin)) {
            foreach ($allowedOrigins as $allowedOrigin) {
                if ($origin === $allowedOrigin || strpos($origin, $allowedOrigin) !== false) {
                    return true;
                }
            }
        }
        
        // Check referer as fallback
        if (!empty($referer)) {
            foreach ($allowedOrigins as $allowedOrigin) {
                if (strpos($referer, $allowedOrigin) === 0) {
                    return true;
                }
            }
        }
        
        Logger::security('Invalid request origin', [
            'origin' => $origin,
            'referer' => $referer,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        return false;
    }
    
    /**
     * Check for suspicious activity patterns
     * 
     * @return bool True if request seems suspicious
     */
    public static function detectSuspiciousActivity() {
        $suspicious = false;
        $reasons = [];
        
        // Check for common attack patterns in headers
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (preg_match('/(<script|javascript:|data:)/i', $value)) {
                $suspicious = true;
                $reasons[] = 'XSS attempt in header: ' . $name;
            }
            
            if (preg_match('/(union|select|insert|update|delete|drop)/i', $value)) {
                $suspicious = true;
                $reasons[] = 'SQL injection attempt in header: ' . $name;
            }
        }
        
        // Check user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent) || preg_match('/(bot|crawler|spider|scraper)/i', $userAgent)) {
            $suspicious = true;
            $reasons[] = 'Suspicious user agent';
        }
        
        // Check for rapid requests from same IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (self::isRapidRequests($ip)) {
            $suspicious = true;
            $reasons[] = 'Rapid requests detected';
        }
        
        if ($suspicious) {
            Logger::security('Suspicious activity detected', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'reasons' => $reasons,
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
        }
        
        return $suspicious;
    }
    
    /**
     * Check if IP is making rapid requests
     * 
     * @param string $ip IP address
     * @return bool True if rapid requests detected
     */
    private static function isRapidRequests($ip) {
        try {
            $db = Database::getInstance();
            
            // Count requests in last minute
            $sql = "SELECT COUNT(*) as count FROM rate_limits 
                    WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
            $stmt = $db->executeQuery($sql, [$ip]);
            $result = $stmt->fetch();
            
            return $result['count'] > 60; // More than 1 request per second
            
        } catch (Exception $e) {
            Logger::error('Failed to check rapid requests', [
                'error' => $e->getMessage(),
                'ip' => $ip
            ]);
            return false;
        }
    }
    
    /**
     * Block suspicious IP addresses
     * 
     * @param string $ip IP address to block
     * @param string $reason Reason for blocking
     * @param int $duration Block duration in seconds (default: 1 hour)
     */
    public static function blockIP($ip, $reason, $duration = 3600) {
        try {
            $db = Database::getInstance();
            
            $expiresAt = date('Y-m-d H:i:s', time() + $duration);
            
            $sql = "INSERT INTO blocked_ips (ip_address, reason, expires_at, created_at) 
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    reason = VALUES(reason), 
                    expires_at = VALUES(expires_at)";
            
            $db->executeQuery($sql, [$ip, $reason, $expiresAt]);
            
            Logger::security('IP address blocked', [
                'ip' => $ip,
                'reason' => $reason,
                'duration' => $duration
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to block IP', [
                'error' => $e->getMessage(),
                'ip' => $ip
            ]);
        }
    }
    
    /**
     * Check if IP is blocked
     * 
     * @param string $ip IP address to check
     * @return bool True if IP is blocked
     */
    public static function isIPBlocked($ip) {
        try {
            $db = Database::getInstance();
            
            $sql = "SELECT COUNT(*) as count FROM blocked_ips 
                    WHERE ip_address = ? AND expires_at > NOW()";
            $stmt = $db->executeQuery($sql, [$ip]);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            Logger::error('Failed to check blocked IP', [
                'error' => $e->getMessage(),
                'ip' => $ip
            ]);
            return false;
        }
    }
    
    /**
     * Apply comprehensive security checks
     * 
     * @return bool True if all checks pass
     */
    public static function applySecurityChecks() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check if IP is blocked
        if (self::isIPBlocked($ip)) {
            Logger::security('Blocked IP attempted access', ['ip' => $ip]);
            Response::error('Access denied', 403);
            exit;
        }
        
        // Validate origin
        if (!self::validateOrigin()) {
            Response::error('Invalid request origin', 403);
            exit;
        }
        
        // Detect suspicious activity
        if (self::detectSuspiciousActivity()) {
            // Block IP for repeated suspicious activity
            self::blockIP($ip, 'Suspicious activity detected', 1800); // 30 minutes
            Response::error('Suspicious activity detected', 403);
            exit;
        }
        
        return true;
    }
}

/**
 * Helper function to get current authenticated user
 * 
 * @return array|null User data or null if not authenticated
 */
function getCurrentUser() {
    return AuthMiddleware::getAuthenticatedUser();
}

/**
 * Helper function to check if user is authenticated
 * 
 * @return bool True if authenticated
 */
function isAuthenticated() {
    return AuthMiddleware::getAuthenticatedUser() !== null;
}

/**
 * Helper function to check if user is admin
 * 
 * @return bool True if user is admin
 */
function isAdmin() {
    return AuthMiddleware::isAdmin();
}

/**
 * Helper function to check if user has specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool True if user has required role
 */
function hasRole($roles) {
    return AuthMiddleware::hasRole($roles);
}