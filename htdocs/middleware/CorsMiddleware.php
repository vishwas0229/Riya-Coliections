<?php
/**
 * CORS Middleware
 * 
 * This middleware handles Cross-Origin Resource Sharing (CORS) headers
 * to allow the frontend application to communicate with the PHP backend API.
 * 
 * Requirements: 4.1, 10.4
 */

require_once __DIR__ . '/../config/security.php';

/**
 * CORS Middleware Class
 */
class CorsMiddleware {
    /**
     * Handle CORS for the request
     */
    public static function handle() {
        $config = SecurityConfig::getConfig()['cors'];
        
        // Get the origin of the request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if (self::isOriginAllowed($origin, $config['allowed_origins'])) {
            header("Access-Control-Allow-Origin: {$origin}");
        }
        
        // Set other CORS headers
        header('Access-Control-Allow-Methods: ' . implode(', ', $config['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $config['allowed_headers']));
        
        if ($config['allow_credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }
        
        header('Access-Control-Max-Age: ' . $config['max_age']);
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Log CORS request for debugging
        if (isDevelopment()) {
            Logger::debug('CORS request processed', [
                'origin' => $origin,
                'method' => $_SERVER['REQUEST_METHOD'],
                'allowed' => self::isOriginAllowed($origin, $config['allowed_origins'])
            ]);
        }
    }
    
    /**
     * Check if origin is allowed
     */
    private static function isOriginAllowed($origin, $allowedOrigins) {
        if (empty($origin)) {
            return false;
        }
        
        // Check for wildcard
        if (in_array('*', $allowedOrigins)) {
            return true;
        }
        
        // Check exact match
        if (in_array($origin, $allowedOrigins)) {
            return true;
        }
        
        // Check pattern match for development
        if (isDevelopment()) {
            foreach ($allowedOrigins as $allowedOrigin) {
                if (strpos($allowedOrigin, 'localhost') !== false || 
                    strpos($allowedOrigin, '127.0.0.1') !== false) {
                    
                    // Allow any port for localhost in development
                    $pattern = preg_replace('/:\d+/', ':\d+', preg_quote($allowedOrigin, '/'));
                    if (preg_match("/^{$pattern}$/", $origin)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Set CORS headers for specific response
     */
    public static function setHeaders($origin = null) {
        $config = SecurityConfig::getConfig()['cors'];
        
        if ($origin === null) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        }
        
        if (self::isOriginAllowed($origin, $config['allowed_origins'])) {
            header("Access-Control-Allow-Origin: {$origin}");
            
            if ($config['allow_credentials']) {
                header('Access-Control-Allow-Credentials: true');
            }
        }
    }
}