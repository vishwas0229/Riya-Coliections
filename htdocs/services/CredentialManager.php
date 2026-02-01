<?php
/**
 * Credential Management Service
 * 
 * This service provides secure storage, retrieval, and management of sensitive
 * credentials including API keys, passwords, and other secrets.
 * 
 * Requirements: 14.2, 14.4
 */

require_once __DIR__ . '/../config/ConfigManager.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Credential Manager Class
 * 
 * Handles secure credential storage with encryption, rotation, and access control.
 */
class CredentialManager {
    private static $instance = null;
    private $encryptionKey;
    private $credentialStore;
    private $storePath;
    private $rotationLog;
    private $accessLog = [];
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->storePath = __DIR__ . '/../storage/credentials';
        $this->initializeEncryption();
        $this->initializeStorage();
        $this->loadRotationLog();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize encryption system
     */
    private function initializeEncryption() {
        // Use multiple sources for key derivation
        $sources = [
            env('CREDENTIAL_MASTER_KEY'),
            php_uname('n'), // Machine name
            __DIR__,
            env('JWT_SECRET', 'fallback'),
            get_current_user()
        ];
        
        $keyMaterial = implode('|', array_filter($sources));
        $this->encryptionKey = hash('sha256', $keyMaterial, true);
        
        // Additional key stretching
        for ($i = 0; $i < 10000; $i++) {
            $this->encryptionKey = hash('sha256', $this->encryptionKey . $keyMaterial, true);
        }
    }
    
    /**
     * Initialize credential storage
     */
    private function initializeStorage() {
        if (!is_dir($this->storePath)) {
            mkdir($this->storePath, 0700, true);
        }
        
        // Secure the storage directory
        $htaccessPath = $this->storePath . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n");
        }
        
        $this->credentialStore = $this->storePath . '/credentials.enc';
        $this->rotationLog = $this->storePath . '/rotation.log';
    }
    
    /**
     * Load rotation log
     */
    private function loadRotationLog() {
        if (file_exists($this->rotationLog)) {
            $content = file_get_contents($this->rotationLog);
            $this->rotationLog = json_decode($content, true) ?: [];
        } else {
            $this->rotationLog = [];
        }
    }
    
    /**
     * Store credential securely
     */
    public function store($key, $value, $metadata = []) {
        try {
            $credentials = $this->loadCredentials();
            
            // Prepare credential entry
            $entry = [
                'value' => $value,
                'created_at' => time(),
                'updated_at' => time(),
                'metadata' => array_merge($metadata, [
                    'created_by' => $this->getCurrentUser(),
                    'ip_address' => $this->getClientIP(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
                ]),
                'access_count' => 0,
                'last_accessed' => null,
                'rotation_schedule' => $metadata['rotation_schedule'] ?? null,
                'expires_at' => isset($metadata['expires_in']) ? time() + $metadata['expires_in'] : null
            ];
            
            // Update existing entry
            if (isset($credentials[$key])) {
                $entry['created_at'] = $credentials[$key]['created_at'];
                $entry['access_count'] = $credentials[$key]['access_count'];
                $entry['last_accessed'] = $credentials[$key]['last_accessed'];
                
                // Log the update
                $this->logCredentialUpdate($key, $credentials[$key], $entry);
            }
            
            $credentials[$key] = $entry;
            $this->saveCredentials($credentials);
            
            Logger::info('Credential stored', [
                'key' => $key,
                'has_expiration' => !empty($entry['expires_at']),
                'has_rotation' => !empty($entry['rotation_schedule'])
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to store credential', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Retrieve credential securely
     */
    public function retrieve($key, $updateAccessLog = true) {
        try {
            $credentials = $this->loadCredentials();
            
            if (!isset($credentials[$key])) {
                Logger::warning('Credential not found', ['key' => $key]);
                return null;
            }
            
            $entry = $credentials[$key];
            
            // Check expiration
            if (!empty($entry['expires_at']) && $entry['expires_at'] < time()) {
                Logger::warning('Credential has expired', [
                    'key' => $key,
                    'expired_at' => date('Y-m-d H:i:s', $entry['expires_at'])
                ]);
                return null;
            }
            
            // Update access log
            if ($updateAccessLog) {
                $entry['access_count']++;
                $entry['last_accessed'] = time();
                $credentials[$key] = $entry;
                $this->saveCredentials($credentials);
                
                $this->logAccess($key);
            }
            
            // Check if rotation is needed
            $this->checkRotationNeeded($key, $entry);
            
            return $entry['value'];
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve credential', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete credential
     */
    public function delete($key) {
        try {
            $credentials = $this->loadCredentials();
            
            if (!isset($credentials[$key])) {
                return false;
            }
            
            // Log deletion
            Logger::info('Credential deleted', [
                'key' => $key,
                'deleted_by' => $this->getCurrentUser()
            ]);
            
            unset($credentials[$key]);
            $this->saveCredentials($credentials);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to delete credential', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * List all credential keys (without values)
     */
    public function listKeys() {
        try {
            $credentials = $this->loadCredentials();
            $keys = [];
            
            foreach ($credentials as $key => $entry) {
                $keys[] = [
                    'key' => $key,
                    'created_at' => date('Y-m-d H:i:s', $entry['created_at']),
                    'updated_at' => date('Y-m-d H:i:s', $entry['updated_at']),
                    'access_count' => $entry['access_count'],
                    'last_accessed' => $entry['last_accessed'] ? date('Y-m-d H:i:s', $entry['last_accessed']) : null,
                    'expires_at' => $entry['expires_at'] ? date('Y-m-d H:i:s', $entry['expires_at']) : null,
                    'has_rotation' => !empty($entry['rotation_schedule']),
                    'metadata' => $entry['metadata'] ?? []
                ];
            }
            
            return $keys;
            
        } catch (Exception $e) {
            Logger::error('Failed to list credential keys', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Rotate credential
     */
    public function rotate($key, $newValue, $reason = 'manual') {
        try {
            $credentials = $this->loadCredentials();
            
            if (!isset($credentials[$key])) {
                throw new Exception("Credential '{$key}' not found");
            }
            
            $oldEntry = $credentials[$key];
            $oldValue = $oldEntry['value'];
            
            // Create new entry
            $newEntry = $oldEntry;
            $newEntry['value'] = $newValue;
            $newEntry['updated_at'] = time();
            $newEntry['metadata']['rotated_by'] = $this->getCurrentUser();
            $newEntry['metadata']['rotation_reason'] = $reason;
            
            $credentials[$key] = $newEntry;
            $this->saveCredentials($credentials);
            
            // Log rotation
            $this->logRotation($key, $reason, $oldValue, $newValue);
            
            Logger::info('Credential rotated', [
                'key' => $key,
                'reason' => $reason,
                'rotated_by' => $this->getCurrentUser()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to rotate credential', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Check if credential exists
     */
    public function exists($key) {
        try {
            $credentials = $this->loadCredentials();
            return isset($credentials[$key]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get credential metadata
     */
    public function getMetadata($key) {
        try {
            $credentials = $this->loadCredentials();
            
            if (!isset($credentials[$key])) {
                return null;
            }
            
            $entry = $credentials[$key];
            
            return [
                'created_at' => date('Y-m-d H:i:s', $entry['created_at']),
                'updated_at' => date('Y-m-d H:i:s', $entry['updated_at']),
                'access_count' => $entry['access_count'],
                'last_accessed' => $entry['last_accessed'] ? date('Y-m-d H:i:s', $entry['last_accessed']) : null,
                'expires_at' => $entry['expires_at'] ? date('Y-m-d H:i:s', $entry['expires_at']) : null,
                'rotation_schedule' => $entry['rotation_schedule'] ?? null,
                'metadata' => $entry['metadata'] ?? []
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get credential metadata', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Clean up expired credentials
     */
    public function cleanupExpired() {
        try {
            $credentials = $this->loadCredentials();
            $cleaned = 0;
            $now = time();
            
            foreach ($credentials as $key => $entry) {
                if (!empty($entry['expires_at']) && $entry['expires_at'] < $now) {
                    unset($credentials[$key]);
                    $cleaned++;
                    
                    Logger::info('Expired credential cleaned up', [
                        'key' => $key,
                        'expired_at' => date('Y-m-d H:i:s', $entry['expires_at'])
                    ]);
                }
            }
            
            if ($cleaned > 0) {
                $this->saveCredentials($credentials);
            }
            
            return $cleaned;
            
        } catch (Exception $e) {
            Logger::error('Failed to cleanup expired credentials', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Get credentials that need rotation
     */
    public function getRotationCandidates() {
        try {
            $credentials = $this->loadCredentials();
            $candidates = [];
            $now = time();
            
            foreach ($credentials as $key => $entry) {
                if (empty($entry['rotation_schedule'])) {
                    continue;
                }
                
                $schedule = $entry['rotation_schedule'];
                $lastRotation = $entry['updated_at'];
                
                // Calculate next rotation time
                $nextRotation = $this->calculateNextRotation($lastRotation, $schedule);
                
                if ($nextRotation <= $now) {
                    $candidates[] = [
                        'key' => $key,
                        'last_rotation' => date('Y-m-d H:i:s', $lastRotation),
                        'next_rotation' => date('Y-m-d H:i:s', $nextRotation),
                        'overdue_by' => $now - $nextRotation,
                        'schedule' => $schedule
                    ];
                }
            }
            
            return $candidates;
            
        } catch (Exception $e) {
            Logger::error('Failed to get rotation candidates', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Generate secure random credential
     */
    public function generateSecureCredential($length = 32, $includeSpecialChars = true) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        
        if ($includeSpecialChars) {
            $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }
        
        $credential = '';
        $maxIndex = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $credential .= $chars[random_int(0, $maxIndex)];
        }
        
        return $credential;
    }
    
    /**
     * Import credentials from environment variables
     */
    public function importFromEnvironment($prefix = '', $overwrite = false) {
        $imported = 0;
        $skipped = 0;
        
        foreach ($_ENV as $key => $value) {
            if ($prefix && strpos($key, $prefix) !== 0) {
                continue;
            }
            
            // Skip non-sensitive looking variables
            if (!$this->isSensitiveKey($key)) {
                continue;
            }
            
            $credentialKey = $prefix ? substr($key, strlen($prefix)) : $key;
            
            if (!$overwrite && $this->exists($credentialKey)) {
                $skipped++;
                continue;
            }
            
            $this->store($credentialKey, $value, [
                'source' => 'environment',
                'original_key' => $key,
                'imported_at' => time()
            ]);
            
            $imported++;
        }
        
        Logger::info('Credentials imported from environment', [
            'imported' => $imported,
            'skipped' => $skipped,
            'prefix' => $prefix
        ]);
        
        return ['imported' => $imported, 'skipped' => $skipped];
    }
    
    /**
     * Export credentials to environment format
     */
    public function exportToEnvironment($keys = null, $prefix = '') {
        try {
            $credentials = $this->loadCredentials();
            $exported = [];
            
            foreach ($credentials as $key => $entry) {
                if ($keys && !in_array($key, $keys)) {
                    continue;
                }
                
                $envKey = $prefix . $key;
                $exported[$envKey] = $entry['value'];
            }
            
            return $exported;
            
        } catch (Exception $e) {
            Logger::error('Failed to export credentials', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Load credentials from encrypted storage
     */
    private function loadCredentials() {
        if (!file_exists($this->credentialStore)) {
            return [];
        }
        
        $encryptedData = file_get_contents($this->credentialStore);
        if (empty($encryptedData)) {
            return [];
        }
        
        $decryptedData = $this->decrypt($encryptedData);
        if ($decryptedData === false) {
            throw new Exception('Failed to decrypt credential store');
        }
        
        $credentials = json_decode($decryptedData, true);
        if ($credentials === null) {
            throw new Exception('Invalid credential store format');
        }
        
        return $credentials;
    }
    
    /**
     * Save credentials to encrypted storage
     */
    private function saveCredentials($credentials) {
        $jsonData = json_encode($credentials, JSON_PRETTY_PRINT);
        $encryptedData = $this->encrypt($jsonData);
        
        if ($encryptedData === false) {
            throw new Exception('Failed to encrypt credential data');
        }
        
        $result = file_put_contents($this->credentialStore, $encryptedData, LOCK_EX);
        if ($result === false) {
            throw new Exception('Failed to save credential store');
        }
        
        // Set secure permissions
        chmod($this->credentialStore, 0600);
    }
    
    /**
     * Encrypt data
     */
    private function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        if ($encrypted === false) {
            return false;
        }
        
        // Add integrity check
        $hmac = hash_hmac('sha256', $iv . $encrypted, $this->encryptionKey, true);
        
        return base64_encode($hmac . $iv . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    private function decrypt($encryptedData) {
        $data = base64_decode($encryptedData);
        if ($data === false) {
            return false;
        }
        
        // Extract components
        $hmac = substr($data, 0, 32);
        $iv = substr($data, 32, 16);
        $encrypted = substr($data, 48);
        
        // Verify integrity
        $expectedHmac = hash_hmac('sha256', $iv . $encrypted, $this->encryptionKey, true);
        if (!hash_equals($hmac, $expectedHmac)) {
            return false;
        }
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
    
    /**
     * Check if key contains sensitive data
     */
    private function isSensitiveKey($key) {
        $sensitivePatterns = [
            'password', 'secret', 'key', 'token', 'credential',
            'private', 'auth', 'api', 'webhook', 'smtp'
        ];
        
        $keyLower = strtolower($key);
        
        foreach ($sensitivePatterns as $pattern) {
            if (strpos($keyLower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log credential access
     */
    private function logAccess($key) {
        $this->accessLog[] = [
            'key' => $key,
            'timestamp' => time(),
            'user' => $this->getCurrentUser(),
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ];
        
        // Keep only last 1000 access logs
        if (count($this->accessLog) > 1000) {
            $this->accessLog = array_slice($this->accessLog, -1000);
        }
    }
    
    /**
     * Log credential rotation
     */
    private function logRotation($key, $reason, $oldValue, $newValue) {
        $rotationEntry = [
            'key' => $key,
            'timestamp' => time(),
            'reason' => $reason,
            'rotated_by' => $this->getCurrentUser(),
            'old_value_hash' => hash('sha256', $oldValue),
            'new_value_hash' => hash('sha256', $newValue)
        ];
        
        $this->rotationLog[] = $rotationEntry;
        
        // Save rotation log
        file_put_contents($this->rotationLog, json_encode($this->rotationLog, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Log credential update
     */
    private function logCredentialUpdate($key, $oldEntry, $newEntry) {
        Logger::info('Credential updated', [
            'key' => $key,
            'old_updated_at' => date('Y-m-d H:i:s', $oldEntry['updated_at']),
            'new_updated_at' => date('Y-m-d H:i:s', $newEntry['updated_at']),
            'updated_by' => $this->getCurrentUser()
        ]);
    }
    
    /**
     * Check if rotation is needed
     */
    private function checkRotationNeeded($key, $entry) {
        if (empty($entry['rotation_schedule'])) {
            return;
        }
        
        $nextRotation = $this->calculateNextRotation($entry['updated_at'], $entry['rotation_schedule']);
        
        if ($nextRotation <= time()) {
            Logger::warning('Credential rotation needed', [
                'key' => $key,
                'last_rotation' => date('Y-m-d H:i:s', $entry['updated_at']),
                'next_rotation' => date('Y-m-d H:i:s', $nextRotation)
            ]);
        }
    }
    
    /**
     * Calculate next rotation time
     */
    private function calculateNextRotation($lastRotation, $schedule) {
        // Parse schedule (e.g., "30d", "1w", "6h")
        $matches = [];
        if (preg_match('/^(\d+)([dwh])$/', $schedule, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            $multipliers = ['h' => 3600, 'd' => 86400, 'w' => 604800];
            $interval = $value * ($multipliers[$unit] ?? 86400);
            
            return $lastRotation + $interval;
        }
        
        // Default to 30 days
        return $lastRotation + (30 * 86400);
    }
    
    /**
     * Get current user
     */
    private function getCurrentUser() {
        return $_SERVER['PHP_AUTH_USER'] ?? get_current_user() ?? 'system';
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        return $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize CredentialManager");
    }
}

// Global helper functions
if (!function_exists('credentials')) {
    function credentials($key = null, $value = null) {
        $manager = CredentialManager::getInstance();
        
        if ($key === null) {
            return $manager;
        }
        
        if ($value !== null) {
            return $manager->store($key, $value);
        }
        
        return $manager->retrieve($key);
    }
}

if (!function_exists('credential_store')) {
    function credential_store($key, $value, $metadata = []) {
        return CredentialManager::getInstance()->store($key, $value, $metadata);
    }
}

if (!function_exists('credential_get')) {
    function credential_get($key) {
        return CredentialManager::getInstance()->retrieve($key);
    }
}

if (!function_exists('credential_exists')) {
    function credential_exists($key) {
        return CredentialManager::getInstance()->exists($key);
    }
}

if (!function_exists('credential_delete')) {
    function credential_delete($key) {
        return CredentialManager::getInstance()->delete($key);
    }
}

if (!function_exists('credential_rotate')) {
    function credential_rotate($key, $newValue, $reason = 'manual') {
        return CredentialManager::getInstance()->rotate($key, $newValue, $reason);
    }
}