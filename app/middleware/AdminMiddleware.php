<?php
/**
 * Admin Middleware
 * 
 * This middleware ensures that only authenticated admin users can access
 * admin-only endpoints. It checks for valid authentication and admin role.
 * 
 * Requirements: 11.2, 17.1
 */

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Admin Middleware Class
 */
class AdminMiddleware {
    /**
     * Handle admin authorization
     */
    public static function handle($request = null) {
        // Get authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader)) {
            Logger::security('Admin access attempt without authorization header', [
                'ip' => self::getClientIP(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            Response::unauthorized('Authorization header required');
            return;
        }
        
        // Extract token from Bearer header
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Logger::security('Admin access attempt with invalid authorization format', [
                'ip' => self::getClientIP(),
                'auth_header' => $authHeader
            ]);
            
            Response::unauthorized('Invalid authorization format');
            return;
        }
        
        $token = $matches[1];
        
        // Validate token
        $authService = new AuthService();
        $payload = $authService->validateToken($token);
        
        if (!$payload) {
            Logger::security('Admin access attempt with invalid token', [
                'ip' => self::getClientIP(),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            
            Response::unauthorized('Invalid or expired token');
            return;
        }
        
        // Check if user is admin
        if (!isset($payload->role) || $payload->role !== 'admin') {
            Logger::security('Non-admin user attempted admin access', [
                'user_id' => $payload->user_id ?? 'unknown',
                'role' => $payload->role ?? 'none',
                'ip' => self::getClientIP(),
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            
            Response::forbidden('Admin access required');
            return;
        }
        
        // Check if admin account is active
        if (isset($payload->status) && $payload->status !== 'active') {
            Logger::security('Inactive admin account attempted access', [
                'user_id' => $payload->user_id,
                'status' => $payload->status,
                'ip' => self::getClientIP()
            ]);
            
            Response::forbidden('Admin account is not active');
            return;
        }
        
        // Store admin info in global variable for controllers
        $GLOBALS['admin_user'] = $payload;
        
        // Log successful admin access
        Logger::info('Admin access granted', [
            'admin_id' => $payload->user_id,
            'admin_email' => $payload->email ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => self::getClientIP()
        ]);
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
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
    
    /**
     * Check if current user is admin
     */
    public static function isAdmin() {
        return isset($GLOBALS['admin_user']) && 
               isset($GLOBALS['admin_user']->role) && 
               $GLOBALS['admin_user']->role === 'admin';
    }
    
    /**
     * Get current admin user
     */
    public static function getAdminUser() {
        return $GLOBALS['admin_user'] ?? null;
    }
    
    /**
     * Check admin permissions for specific action
     */
    public static function checkPermission($permission) {
        $adminUser = self::getAdminUser();
        
        if (!$adminUser) {
            return false;
        }
        
        // Check if admin has specific permission
        $permissions = $adminUser->permissions ?? [];
        
        // Super admin has all permissions
        if (in_array('super_admin', $permissions)) {
            return true;
        }
        
        // Check specific permission
        return in_array($permission, $permissions);
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission($permission) {
        if (!self::checkPermission($permission)) {
            Logger::security('Admin permission denied', [
                'admin_id' => self::getAdminUser()->user_id ?? 'unknown',
                'required_permission' => $permission,
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            
            Response::forbidden('Insufficient permissions');
            return;
        }
    }
}