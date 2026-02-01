<?php
/**
 * Rate Limiter Utility
 * 
 * Implements rate limiting functionality to prevent abuse and DDoS attacks.
 * Uses file-based storage for simplicity and compatibility with shared hosting.
 * 
 * Requirements: 10.3, 13.2
 */

require_once __DIR__ . '/../utils/Logger.php';

class RateLimiter {
    private $storageDir;
    private $limits;
    private $cleanupInterval;
    
    public function __construct() {
        $this->storageDir = __DIR__ . '/../storage/rate_limits/';
        $this->cleanupInterval = 3600; // 1 hour
        
        // Default rate limits (requests per time window)
        $this->limits = [
            'global' => ['requests' => 1000, 'window' => 3600], // 1000 requests per hour
            'api' => ['requests' => 100, 'window' => 300],       // 100 requests per 5 minutes
            'auth' => ['requests' => 10, 'window' => 300],       // 10 auth requests per 5 minutes
            'upload' => ['requests' => 20, 'window' => 3600],    // 20 uploads per hour
            'search' => ['requests' => 50, 'window' => 300],     // 50 searches per 5 minutes
        ];
        
        // Ensure storage directory exists
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        
        // Periodic cleanup
        $this->performCleanup();
    }
    
    /**
     * Check if request is within rate limit
     * 
     * @param string $identifier Client identifier (IP, user ID, etc.)
     * @param string $type Rate limit type
     * @return bool True if within limit
     */
    public function checkLimit($identifier = null, $type = 'global') {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $limit = $this->limits[$type] ?? $this->limits['global'];
        $key = $this->generateKey($identifier, $type);
        $data = $this->loadData($key);
        
        $currentTime = time();
        $windowStart = $currentTime - $limit['window'];
        
        // Remove old entries
        $data = array_filter($data, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Check if limit exceeded
        if (count($data) >= $limit['requests']) {
            Logger::warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'type' => $type,
                'requests' => count($data),
                'limit' => $limit['requests'],
                'window' => $limit['window']
            ]);
            
            return false;
        }
        
        // Add current request
        $data[] = $currentTime;
        $this->saveData($key, $data);
        
        return true;
    }
    
    /**
     * Get remaining requests for identifier
     * 
     * @param string $identifier Client identifier
     * @param string $type Rate limit type
     * @return array Remaining requests info
     */
    public function getRemainingRequests($identifier = null, $type = 'global') {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $limit = $this->limits[$type] ?? $this->limits['global'];
        $key = $this->generateKey($identifier, $type);
        $data = $this->loadData($key);
        
        $currentTime = time();
        $windowStart = $currentTime - $limit['window'];
        
        // Count requests in current window
        $requestsInWindow = count(array_filter($data, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        }));
        
        $remaining = max(0, $limit['requests'] - $requestsInWindow);
        $resetTime = $currentTime + $limit['window'];
        
        return [
            'limit' => $limit['requests'],
            'remaining' => $remaining,
            'reset_time' => $resetTime,
            'window' => $limit['window']
        ];
    }
    
    /**
     * Reset rate limit for identifier
     * 
     * @param string $identifier Client identifier
     * @param string $type Rate limit type
     * @return bool Success status
     */
    public function resetLimit($identifier, $type = 'global') {
        $key = $this->generateKey($identifier, $type);
        $file = $this->storageDir . $key . '.json';
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Block identifier for specified duration
     * 
     * @param string $identifier Client identifier
     * @param int $duration Block duration in seconds
     * @param string $reason Block reason
     * @return bool Success status
     */
    public function blockIdentifier($identifier, $duration = 3600, $reason = 'Rate limit violation') {
        $blockData = [
            'identifier' => $identifier,
            'blocked_at' => time(),
            'expires_at' => time() + $duration,
            'reason' => $reason
        ];
        
        $key = 'block_' . md5($identifier);
        $file = $this->storageDir . $key . '.json';
        
        $success = file_put_contents($file, json_encode($blockData)) !== false;
        
        if ($success) {
            Logger::warning('Identifier blocked', $blockData);
        }
        
        return $success;
    }
    
    /**
     * Check if identifier is blocked
     * 
     * @param string $identifier Client identifier
     * @return bool True if blocked
     */
    public function isBlocked($identifier) {
        $key = 'block_' . md5($identifier);
        $file = $this->storageDir . $key . '.json';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $blockData = json_decode(file_get_contents($file), true);
        
        if (!$blockData || !isset($blockData['expires_at'])) {
            return false;
        }
        
        // Check if block has expired
        if (time() > $blockData['expires_at']) {
            unlink($file);
            return false;
        }
        
        return true;
    }
    
    /**
     * Unblock identifier
     * 
     * @param string $identifier Client identifier
     * @return bool Success status
     */
    public function unblockIdentifier($identifier) {
        $key = 'block_' . md5($identifier);
        $file = $this->storageDir . $key . '.json';
        
        if (file_exists($file)) {
            $success = unlink($file);
            
            if ($success) {
                Logger::info('Identifier unblocked', ['identifier' => $identifier]);
            }
            
            return $success;
        }
        
        return true;
    }
    
    /**
     * Get rate limit statistics
     * 
     * @return array Statistics
     */
    public function getStatistics() {
        $stats = [
            'total_files' => 0,
            'blocked_identifiers' => 0,
            'rate_limit_files' => 0,
            'oldest_entry' => null,
            'newest_entry' => null
        ];
        
        if (!is_dir($this->storageDir)) {
            return $stats;
        }
        
        $files = scandir($this->storageDir);
        $currentTime = time();
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $stats['total_files']++;
            
            if (strpos($file, 'block_') === 0) {
                $stats['blocked_identifiers']++;
            } else {
                $stats['rate_limit_files']++;
            }
            
            $filePath = $this->storageDir . $file;
            $mtime = filemtime($filePath);
            
            if ($stats['oldest_entry'] === null || $mtime < $stats['oldest_entry']) {
                $stats['oldest_entry'] = $mtime;
            }
            
            if ($stats['newest_entry'] === null || $mtime > $stats['newest_entry']) {
                $stats['newest_entry'] = $mtime;
            }
        }
        
        return $stats;
    }
    
    /**
     * Configure rate limits
     * 
     * @param array $limits Rate limit configuration
     */
    public function configureLimits($limits) {
        $this->limits = array_merge($this->limits, $limits);
    }
    
    /**
     * Get client identifier
     * 
     * @return string Client identifier
     */
    private function getClientIdentifier() {
        // Try to get the most accurate client IP
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
     * Generate storage key
     * 
     * @param string $identifier Client identifier
     * @param string $type Rate limit type
     * @return string Storage key
     */
    private function generateKey($identifier, $type) {
        return md5($identifier . '_' . $type);
    }
    
    /**
     * Load rate limit data
     * 
     * @param string $key Storage key
     * @return array Rate limit data
     */
    private function loadData($key) {
        $file = $this->storageDir . $key . '.json';
        
        if (!file_exists($file)) {
            return [];
        }
        
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    
    /**
     * Save rate limit data
     * 
     * @param string $key Storage key
     * @param array $data Rate limit data
     * @return bool Success status
     */
    private function saveData($key, $data) {
        $file = $this->storageDir . $key . '.json';
        return file_put_contents($file, json_encode($data)) !== false;
    }
    
    /**
     * Perform cleanup of old files
     */
    private function performCleanup() {
        $lastCleanup = $this->getLastCleanupTime();
        
        if (time() - $lastCleanup < $this->cleanupInterval) {
            return;
        }
        
        $this->cleanupOldFiles();
        $this->setLastCleanupTime(time());
    }
    
    /**
     * Clean up old rate limit files
     */
    private function cleanupOldFiles() {
        if (!is_dir($this->storageDir)) {
            return;
        }
        
        $files = scandir($this->storageDir);
        $currentTime = time();
        $maxAge = 86400; // 24 hours
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.cleanup') {
                continue;
            }
            
            $filePath = $this->storageDir . $file;
            
            // Remove files older than max age
            if (filemtime($filePath) < ($currentTime - $maxAge)) {
                unlink($filePath);
            }
        }
        
        Logger::debug('Rate limiter cleanup completed');
    }
    
    /**
     * Get last cleanup time
     * 
     * @return int Last cleanup timestamp
     */
    private function getLastCleanupTime() {
        $file = $this->storageDir . '.cleanup';
        
        if (file_exists($file)) {
            return (int) file_get_contents($file);
        }
        
        return 0;
    }
    
    /**
     * Set last cleanup time
     * 
     * @param int $time Cleanup timestamp
     */
    private function setLastCleanupTime($time) {
        $file = $this->storageDir . '.cleanup';
        file_put_contents($file, $time);
    }
}

// Global helper functions
if (!function_exists('getRateLimiter')) {
    function getRateLimiter() {
        static $limiter = null;
        if ($limiter === null) {
            $limiter = new RateLimiter();
        }
        return $limiter;
    }
}

function isBlocked($identifier) {
    return getRateLimiter()->isBlocked($identifier);
}