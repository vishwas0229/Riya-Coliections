<?php
/**
 * Security Middleware
 * 
 * This middleware implements various security measures including rate limiting,
 * security headers, input validation, and attack prevention.
 * 
 * Requirements: 10.1, 10.3, 10.4
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/../services/ValidationService.php';

/**
 * Security Middleware Class
 */
class SecurityMiddleware {
    /**
     * Handle security for the request
     */
    public static function handle() {
        // Set security headers
        self::setSecurityHeaders();
        
        // Check rate limiting
        if (!self::checkRateLimit()) {
            Response::rateLimitExceeded();
            return;
        }
        
        // Validate request
        self::validateRequest();
        
        // Check for common attacks
        self::checkForAttacks();
        
        // Log security event if suspicious
        self::logSecurityEvent();
    }
    
    /**
     * Set security headers
     */
    private static function setSecurityHeaders() {
        $config = SecurityConfig::getConfig()['headers'];
        
        foreach ($config as $header => $value) {
            header("{$header}: {$value}");
        }
        
        // Additional security headers
        header('Server: Riya Collections API');
        header('X-Powered-By: PHP/' . PHP_VERSION);
        
        // HTTPS enforcement in production
        if (isProduction() && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: {$redirectURL}", true, 301);
            exit;
        }
    }
    
    /**
     * Check rate limiting
     */
    private static function checkRateLimit() {
        try {
            $rateLimiter = getRateLimiter();
            $clientIP = self::getClientIP();
            
            // Check if client is blocked
            if ($rateLimiter->isBlocked($clientIP)) {
                Logger::warning('Blocked client attempted access', ['ip' => $clientIP]);
                Response::error('Access denied', 429);
                return false;
            }
            
            // Determine rate limit type based on request
            $type = self::getRateLimitType();
            
            // Check rate limit
            if (!$rateLimiter->checkLimit($clientIP, $type)) {
                // Get remaining requests info
                $info = $rateLimiter->getRemainingRequests($clientIP, $type);
                
                // Set rate limit headers
                header('X-RateLimit-Limit: ' . $info['limit']);
                header('X-RateLimit-Remaining: ' . $info['remaining']);
                header('X-RateLimit-Reset: ' . $info['reset_time']);
                
                Response::error('Rate limit exceeded', 429);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            Logger::error('Rate limiting check failed', ['error' => $e->getMessage()]);
            return true; // Allow request if rate limiting fails
        }
    }
    
    /**
     * Validate request
     */
    private static function validateRequest() {
        // Check request method
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            Response::methodNotAllowed();
            return;
        }
        
        // Check content length
        $maxContentLength = 10 * 1024 * 1024; // 10MB
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        
        if ($contentLength > $maxContentLength) {
            Response::error('Request too large', 413);
            return;
        }
        
        // Validate User-Agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent) && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
            Logger::security('Request without User-Agent', [
                'ip' => self::getClientIP(),
                'uri' => $_SERVER['REQUEST_URI']
            ]);
        }
        
        // Check for suspicious headers
        self::checkSuspiciousHeaders();
    }
    
    /**
     * Check for common attacks
     */
    private static function checkForAttacks() {
        $validator = getValidationService();
        
        // Check query string
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if (!empty($queryString)) {
            if ($validator->detectSQLInjection($queryString)) {
                Logger::security('SQL injection attempt detected in query string', [
                    'query_string' => $queryString,
                    'ip' => self::getClientIP()
                ]);
                Response::forbidden('Malicious request detected');
                return;
            }
            
            if ($validator->detectXSS($queryString)) {
                Logger::security('XSS attempt detected in query string', [
                    'query_string' => $queryString,
                    'ip' => self::getClientIP()
                ]);
                Response::forbidden('Malicious request detected');
                return;
            }
        }
        
        // Check POST data
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postData = file_get_contents('php://input');
            
            if (!empty($postData)) {
                if ($validator->detectSQLInjection($postData)) {
                    Logger::security('SQL injection attempt detected in POST data', [
                        'ip' => self::getClientIP(),
                        'data_length' => strlen($postData)
                    ]);
                    Response::forbidden('Malicious request detected');
                    return;
                }
                
                if ($validator->detectXSS($postData)) {
                    Logger::security('XSS attempt detected in POST data', [
                        'ip' => self::getClientIP(),
                        'data_length' => strlen($postData)
                    ]);
                    Response::forbidden('Malicious request detected');
                    return;
                }
            }
        }
        
        // Check for path traversal
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('/\.\.(\/|\\\\)/', $uri)) {
            Logger::security('Path traversal attempt detected', [
                'uri' => $uri,
                'ip' => self::getClientIP()
            ]);
            Response::forbidden('Malicious request detected');
            return;
        }
        
        // Check for common exploit patterns
        self::checkExploitPatterns($uri);
    }
    
    /**
     * Check for suspicious headers
     */
    private static function checkSuspiciousHeaders() {
        $suspiciousHeaders = [
            'HTTP_X_FORWARDED_HOST',
            'HTTP_X_ORIGINAL_URL',
            'HTTP_X_REWRITE_URL'
        ];
        
        foreach ($suspiciousHeaders as $header) {
            if (isset($_SERVER[$header])) {
                Logger::security('Suspicious header detected', [
                    'header' => $header,
                    'value' => $_SERVER[$header],
                    'ip' => self::getClientIP()
                ]);
            }
        }
    }
    
    /**
     * Check for exploit patterns
     */
    private static function checkExploitPatterns($uri) {
        $exploitPatterns = [
            '/wp-admin/',
            '/wp-login/',
            '/phpmyadmin/',
            '/admin/',
            '/administrator/',
            '/xmlrpc.php',
            '/wp-config.php',
            '/.env',
            '/config.php',
            '/shell.php',
            '/cmd.php',
            '/eval.php'
        ];
        
        foreach ($exploitPatterns as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                Logger::security('Exploit pattern detected', [
                    'pattern' => $pattern,
                    'uri' => $uri,
                    'ip' => self::getClientIP()
                ]);
                
                // Return 404 to not reveal the actual structure
                Response::notFound();
                return;
            }
        }
    }
    
    /**
     * Log security event
     */
    private static function logSecurityEvent() {
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        
        // Check for suspicious patterns
        $suspicious = false;
        
        // Check for bot patterns
        $botPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i'
        ];
        
        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $suspicious = true;
                break;
            }
        }
        
        // Check for suspicious IPs (basic check)
        if (self::isSuspiciousIP($ip)) {
            $suspicious = true;
        }
        
        if ($suspicious) {
            Logger::security('Suspicious request detected', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'uri' => $uri,
                'method' => $method
            ]);
        }
    }
    
    /**
     * Check if IP is suspicious
     */
    private static function isSuspiciousIP($ip) {
        // Check for private/local IPs in production
        if (isProduction()) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }
        
        // Add more sophisticated IP checking here
        // Could integrate with threat intelligence feeds
        
        return false;
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
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        $validator = getValidationService();
        return $validator->sanitize($data);
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file) {
        $validator = getValidationService();
        return $validator->validateFileUpload($file);
    }
    
    /**
     * Get rate limit type based on request
     * 
     * @return string Rate limit type
     */
    private static function getRateLimitType() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (strpos($uri, '/api/auth/') !== false) {
            return 'auth';
        } elseif (strpos($uri, '/api/upload') !== false) {
            return 'upload';
        } elseif (strpos($uri, '/api/search') !== false) {
            return 'search';
        } elseif (strpos($uri, '/api/') !== false) {
            return 'api';
        }
        
        return 'global';
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check if request is from allowed referer
     */
    public static function checkReferer($allowedDomains = []) {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($referer)) {
            return false;
        }
        
        $refererHost = parse_url($referer, PHP_URL_HOST);
        
        if (empty($allowedDomains)) {
            $allowedDomains = SecurityConfig::getConfig()['cors']['allowed_origins'];
        }
        
        foreach ($allowedDomains as $domain) {
            $domainHost = parse_url($domain, PHP_URL_HOST);
            if ($refererHost === $domainHost) {
                return true;
            }
        }
        
        return false;
    }
}