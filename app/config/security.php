<?php
/**
 * Security Configuration
 * 
 * This module provides comprehensive security configuration including rate limiting,
 * input validation, CSRF protection, and other security measures for the PHP backend.
 * 
 * Requirements: 10.1, 10.3, 10.4, 16.1
 */

require_once __DIR__ . '/environment.php';

/**
 * Security Configuration Class
 */
class SecurityConfig {
    private static $config = null;
    
    /**
     * Get security configuration
     */
    public static function getConfig() {
        if (self::$config === null) {
            self::$config = [
                'rate_limiting' => [
                    'enabled' => true,
                    'window_ms' => (int) env('RATE_LIMIT_WINDOW_MS', 900000), // 15 minutes
                    'max_requests' => (int) env('RATE_LIMIT_MAX_REQUESTS', 100),
                    'storage_file' => __DIR__ . '/../logs/rate_limit.json'
                ],
                'cors' => [
                    'allowed_origins' => array_filter(explode(',', env('ALLOWED_ORIGINS', 'https://riyacollections.com'))),
                    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
                    'allow_credentials' => true,
                    'max_age' => 86400 // 24 hours
                ],
                'headers' => [
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'DENY',
                    'X-XSS-Protection' => '1; mode=block',
                    'Referrer-Policy' => 'strict-origin-when-cross-origin',
                    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https:; frame-ancestors 'none';",
                    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=()'
                ],
                'input_validation' => [
                    'max_input_length' => 10000,
                    'allowed_html_tags' => [],
                    'strip_tags' => true,
                    'trim_whitespace' => true
                ],
                'file_upload' => [
                    'max_size' => (int) env('MAX_FILE_SIZE', 5242880), // 5MB
                    'allowed_types' => explode(',', env('ALLOWED_FILE_TYPES', 'image/jpeg,image/png,image/webp')),
                    'scan_for_malware' => true,
                    'quarantine_suspicious' => true
                ],
                'session' => [
                    'secure' => isProduction(),
                    'httponly' => true,
                    'samesite' => 'Strict',
                    'regenerate_interval' => 1800, // 30 minutes
                    'timeout' => 7200 // 2 hours
                ]
            ];
        }
        
        return self::$config;
    }
}

/**
 * Rate Limiting Service
 */
class SecurityRateLimiter {
    private $config;
    private $storage;
    
    public function __construct() {
        $this->config = SecurityConfig::getConfig()['rate_limiting'];
        $this->loadStorage();
    }
    
    /**
     * Check if request is within rate limits
     */
    public function checkLimit($identifier = null) {
        if (!$this->config['enabled']) {
            return true;
        }
        
        $identifier = $identifier ?: $this->getClientIdentifier();
        $now = time() * 1000; // Convert to milliseconds
        $windowStart = $now - $this->config['window_ms'];
        
        // Clean old entries
        $this->cleanOldEntries($windowStart);
        
        // Count requests in current window
        $requestCount = $this->countRequests($identifier, $windowStart);
        
        if ($requestCount >= $this->config['max_requests']) {
            Logger::warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'requests' => $requestCount,
                'limit' => $this->config['max_requests']
            ]);
            return false;
        }
        
        // Record this request
        $this->recordRequest($identifier, $now);
        
        return true;
    }
    
    /**
     * Get client identifier for rate limiting
     */
    private function getClientIdentifier() {
        // Use IP address as primary identifier
        $ip = $this->getClientIP();
        
        // Add user agent hash for additional uniqueness
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $userAgentHash = substr(md5($userAgent), 0, 8);
        
        return $ip . '_' . $userAgentHash;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Load rate limit storage
     */
    private function loadStorage() {
        if (file_exists($this->config['storage_file'])) {
            $data = file_get_contents($this->config['storage_file']);
            $this->storage = json_decode($data, true) ?: [];
        } else {
            $this->storage = [];
        }
    }
    
    /**
     * Save rate limit storage
     */
    private function saveStorage() {
        $dir = dirname($this->config['storage_file']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($this->config['storage_file'], json_encode($this->storage), LOCK_EX);
    }
    
    /**
     * Clean old entries from storage
     */
    private function cleanOldEntries($windowStart) {
        foreach ($this->storage as $identifier => $requests) {
            $this->storage[$identifier] = array_filter($requests, function($timestamp) use ($windowStart) {
                return $timestamp >= $windowStart;
            });
            
            if (empty($this->storage[$identifier])) {
                unset($this->storage[$identifier]);
            }
        }
    }
    
    /**
     * Count requests for identifier in window
     */
    private function countRequests($identifier, $windowStart) {
        if (!isset($this->storage[$identifier])) {
            return 0;
        }
        
        return count(array_filter($this->storage[$identifier], function($timestamp) use ($windowStart) {
            return $timestamp >= $windowStart;
        }));
    }
    
    /**
     * Record a request
     */
    private function recordRequest($identifier, $timestamp) {
        if (!isset($this->storage[$identifier])) {
            $this->storage[$identifier] = [];
        }
        
        $this->storage[$identifier][] = $timestamp;
        $this->saveStorage();
    }
    
    /**
     * Check if identifier is blocked
     */
    public function isBlocked($identifier) {
        // For now, just return false as we don't have blocking logic in this implementation
        // This method exists for compatibility with the interface
        return false;
    }
}

/**
 * Input Validation and Sanitization
 * Use the InputValidator from utils
 */
require_once __DIR__ . '/../utils/InputValidator.php';

/**
 * File Upload Security
 */
class FileUploadSecurity {
    private $config;
    
    public function __construct() {
        $this->config = SecurityConfig::getConfig()['file_upload'];
    }
    
    /**
     * Validate uploaded file
     */
    public function validateFile($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        
        // Check file size
        if ($file['size'] > $this->config['max_size']) {
            throw new Exception('File too large');
        }
        
        // Check MIME type
        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, $this->config['allowed_types'])) {
            throw new Exception('File type not allowed');
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('File extension not allowed');
        }
        
        // Scan for malware (basic check)
        if ($this->config['scan_for_malware']) {
            $this->scanForMalware($file['tmp_name']);
        }
        
        return true;
    }
    
    /**
     * Basic malware scanning
     */
    private function scanForMalware($filePath) {
        $content = file_get_contents($filePath, false, null, 0, 1024); // Read first 1KB
        
        $malwarePatterns = [
            '/<\?php/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i'
        ];
        
        foreach ($malwarePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                Logger::security('Malware detected in uploaded file', [
                    'file_path' => $filePath,
                    'pattern' => $pattern
                ]);
                throw new Exception('Malicious file detected');
            }
        }
    }
    
    /**
     * Generate safe filename
     */
    public function generateSafeFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Add timestamp for uniqueness
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "{$basename}_{$timestamp}_{$random}.{$extension}";
    }
}

// Global helper functions
if (!function_exists('getRateLimiter')) {
    function getRateLimiter() {
        static $limiter = null;
        if ($limiter === null) {
            $limiter = new SecurityRateLimiter();
        }
        return $limiter;
    }
}

if (!function_exists('getInputValidator')) {
    function getInputValidator() {
        static $validator = null;
        if ($validator === null) {
            $validator = new InputValidator();
        }
        return $validator;
    }
}

function getFileUploadSecurity() {
    static $security = null;
    if ($security === null) {
        $security = new FileUploadSecurity();
    }
    return $security;
}

function checkRateLimit($identifier = null) {
    return getRateLimiter()->checkLimit($identifier);
}

function sanitizeInput($input, $options = []) {
    return getInputValidator()->sanitize($input, $options);
}

function validateFile($file) {
    return getFileUploadSecurity()->validateFile($file);
}