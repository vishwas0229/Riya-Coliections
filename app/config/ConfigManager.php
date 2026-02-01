<?php
/**
 * Configuration Management System
 * 
 * This module provides comprehensive configuration management including:
 * - Environment-specific configuration loading
 * - Configuration validation and error handling
 * - Secure credential management
 * - Configuration caching and optimization
 * - Runtime configuration updates
 * 
 * Requirements: 14.2, 14.4
 */

require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Configuration Manager Class
 * 
 * Handles all configuration management operations including loading,
 * validation, caching, and secure credential handling.
 */
class ConfigManager {
    private static $instance = null;
    private $config = [];
    private $cache = [];
    private $validators = [];
    private $encryptionKey = null;
    private $configPath;
    private $cacheFile;
    private $lastModified = [];
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->configPath = __DIR__;
        $this->cacheFile = __DIR__ . '/../cache/config.cache';
        $this->initializeEncryption();
        $this->loadConfiguration();
        $this->registerValidators();
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
     * Initialize encryption for sensitive data
     */
    private function initializeEncryption() {
        $key = env('CONFIG_ENCRYPTION_KEY');
        if (!$key) {
            // Generate a key based on system-specific data
            $systemData = php_uname() . __DIR__ . (env('JWT_SECRET') ?: 'fallback');
            $this->encryptionKey = hash('sha256', $systemData, true);
        } else {
            $this->encryptionKey = hash('sha256', $key, true);
        }
    }
    
    /**
     * Safe logging method that works even if Logger class is not available
     */
    private function safeLog($message, $context = [], $level = 'info') {
        if (class_exists('Logger')) {
            switch ($level) {
                case 'error':
                    Logger::error($message, $context);
                    break;
                case 'warning':
                    Logger::warning($message, $context);
                    break;
                default:
                    Logger::info($message, $context);
                    break;
            }
        } else {
            // Fallback to error_log if Logger is not available
            $logMessage = $message;
            if (!empty($context)) {
                $logMessage .= ' ' . json_encode($context);
            }
            error_log("[CONFIG {$level}] {$logMessage}");
        }
    }
    
    /**
     * Load all configuration files
     */
    private function loadConfiguration() {
        try {
            // Load from cache if available and valid
            if ($this->loadFromCache()) {
                $this->safeLog('Configuration loaded from cache');
                return;
            }
            
            // Load environment-specific configurations
            $this->loadEnvironmentConfig();
            $this->loadDatabaseConfig();
            $this->loadSecurityConfig();
            $this->loadEmailConfig();
            $this->loadPaymentConfig();
            $this->loadApplicationConfig();
            $this->loadLoggingConfig();
            $this->loadFileUploadConfig();
            
            // Save to cache
            $this->saveToCache();
            
            $this->safeLog('Configuration loaded successfully', [
                'environment' => $this->get('app.env'),
                'config_sections' => array_keys($this->config)
            ]);
            
        } catch (Exception $e) {
            $this->safeLog('Failed to load configuration: ' . $e->getMessage(), [], 'error');
            throw new Exception('Configuration loading failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Load environment-specific configuration
     */
    private function loadEnvironmentConfig() {
        $env = env('APP_ENV', 'production');
        
        $this->config['app'] = [
            'name' => env('APP_NAME', 'Riya Collections'),
            'env' => $env,
            'debug' => env('APP_DEBUG', false) === true || $env === 'development',
            'url' => env('APP_URL', 'https://riyacollections.com'),
            'timezone' => env('APP_TIMEZONE', 'UTC'),
            'locale' => env('APP_LOCALE', 'en'),
            'version' => env('APP_VERSION', '1.0.0'),
            'maintenance_mode' => env('MAINTENANCE_MODE', false) === true
        ];
        
        // Environment-specific overrides
        $envConfigFile = $this->configPath . "/environments/{$env}.php";
        if (file_exists($envConfigFile)) {
            $envConfig = require $envConfigFile;
            $this->config['app'] = array_merge($this->config['app'], $envConfig);
            $this->lastModified['app'] = filemtime($envConfigFile);
        }
    }
    
    /**
     * Load database configuration with connection pooling
     */
    private function loadDatabaseConfig() {
        $this->config['database'] = [
            'default' => env('DB_CONNECTION', 'mysql'),
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', 'localhost'),
                    'port' => (int) env('DB_PORT', 3306),
                    'database' => env('DB_NAME', 'riya_collections'),
                    'username' => env('DB_USER', 'root'),
                    'password' => $this->encryptSensitiveData(env('DB_PASSWORD', '')),
                    'charset' => env('DB_CHARSET', 'utf8mb4'),
                    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
                    'prefix' => env('DB_PREFIX', ''),
                    'strict' => env('DB_STRICT_MODE', true) === true,
                    'engine' => env('DB_ENGINE', 'InnoDB')
                ],
                'read' => [
                    'host' => env('DB_READ_HOST', env('DB_HOST', 'localhost')),
                    'port' => (int) env('DB_READ_PORT', env('DB_PORT', 3306)),
                    'database' => env('DB_READ_NAME', env('DB_NAME', 'riya_collections')),
                    'username' => env('DB_READ_USER', env('DB_USER', 'root')),
                    'password' => $this->encryptSensitiveData(env('DB_READ_PASSWORD', env('DB_PASSWORD', '')))
                ]
            ],
            'pool' => [
                'max_connections' => (int) env('DB_MAX_CONNECTIONS', 10),
                'min_connections' => (int) env('DB_MIN_CONNECTIONS', 2),
                'connection_timeout' => (int) env('DB_CONNECTION_TIMEOUT', 30),
                'idle_timeout' => (int) env('DB_IDLE_TIMEOUT', 300),
                'retry_attempts' => (int) env('DB_RETRY_ATTEMPTS', 3)
            ],
            'options' => [
                'slow_query_threshold' => (int) env('DB_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
                'enable_query_log' => env('DB_ENABLE_QUERY_LOG', false) === true,
                'log_slow_queries' => env('DB_LOG_SLOW_QUERIES', true) === true
            ]
        ];
    }
    
    /**
     * Load security configuration
     */
    private function loadSecurityConfig() {
        $this->config['security'] = [
            'encryption' => [
                'cipher' => env('ENCRYPTION_CIPHER', 'AES-256-CBC'),
                'key' => $this->encryptSensitiveData(env('ENCRYPTION_KEY', $this->generateSecureKey())),
                'derive_keys' => env('DERIVE_ENCRYPTION_KEYS', true) === true
            ],
            'hashing' => [
                'driver' => env('HASH_DRIVER', 'bcrypt'),
                'bcrypt_rounds' => (int) env('BCRYPT_ROUNDS', 12),
                'argon2_memory' => (int) env('ARGON2_MEMORY', 1024),
                'argon2_threads' => (int) env('ARGON2_THREADS', 2),
                'argon2_time' => (int) env('ARGON2_TIME', 2)
            ],
            'rate_limiting' => [
                'enabled' => env('RATE_LIMITING_ENABLED', true) === true,
                'driver' => env('RATE_LIMIT_DRIVER', 'file'),
                'window' => (int) env('RATE_LIMIT_WINDOW', 900), // seconds
                'max_attempts' => (int) env('RATE_LIMIT_MAX_ATTEMPTS', 100),
                'decay_minutes' => (int) env('RATE_LIMIT_DECAY_MINUTES', 15)
            ],
            'cors' => [
                'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),
                'allowed_methods' => array_filter(explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS'))),
                'allowed_headers' => array_filter(explode(',', env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With'))),
                'exposed_headers' => array_filter(explode(',', env('CORS_EXPOSED_HEADERS', ''))),
                'max_age' => (int) env('CORS_MAX_AGE', 86400),
                'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true) === true
            ],
            'headers' => [
                'csp' => env('SECURITY_CSP', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"),
                'hsts' => env('SECURITY_HSTS', 'max-age=31536000; includeSubDomains'),
                'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),
                'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),
                'xss_protection' => env('SECURITY_XSS_PROTECTION', '1; mode=block')
            ]
        ];
    }
    
    /**
     * Load email configuration
     */
    private function loadEmailConfig() {
        $this->config['email'] = [
            'default' => env('MAIL_MAILER', 'smtp'),
            'mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => env('MAIL_HOST', 'smtp.gmail.com'),
                    'port' => (int) env('MAIL_PORT', 587),
                    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
                    'username' => env('MAIL_USERNAME'),
                    'password' => $this->encryptSensitiveData(env('MAIL_PASSWORD')),
                    'timeout' => (int) env('MAIL_TIMEOUT', 30),
                    'local_domain' => env('MAIL_LOCAL_DOMAIN', env('APP_URL'))
                ],
                'sendmail' => [
                    'transport' => 'sendmail',
                    'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs')
                ]
            ],
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'orders@riyacollections.com'),
                'name' => env('MAIL_FROM_NAME', 'Riya Collections')
            ],
            'reply_to' => [
                'address' => env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')),
                'name' => env('MAIL_REPLY_TO_NAME', env('MAIL_FROM_NAME'))
            ],
            'templates' => [
                'path' => env('MAIL_TEMPLATE_PATH', __DIR__ . '/../templates/email'),
                'cache' => env('MAIL_TEMPLATE_CACHE', true) === true,
                'default_layout' => env('MAIL_DEFAULT_LAYOUT', 'default')
            ],
            'queue' => [
                'enabled' => env('MAIL_QUEUE_ENABLED', false) === true,
                'connection' => env('MAIL_QUEUE_CONNECTION', 'database'),
                'queue' => env('MAIL_QUEUE_NAME', 'emails'),
                'retry_after' => (int) env('MAIL_RETRY_AFTER', 300)
            ]
        ];
    }
    
    /**
     * Load payment configuration
     */
    private function loadPaymentConfig() {
        $this->config['payment'] = [
            'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'razorpay'),
            'gateways' => [
                'razorpay' => [
                    'key_id' => env('RAZORPAY_KEY_ID'),
                    'key_secret' => $this->encryptSensitiveData(env('RAZORPAY_KEY_SECRET')),
                    'webhook_secret' => $this->encryptSensitiveData(env('RAZORPAY_WEBHOOK_SECRET')),
                    'currency' => env('RAZORPAY_CURRENCY', 'INR'),
                    'test_mode' => env('RAZORPAY_TEST_MODE', !isProduction()) === true,
                    'auto_capture' => env('RAZORPAY_AUTO_CAPTURE', true) === true,
                    'timeout' => (int) env('RAZORPAY_TIMEOUT', 30)
                ],
                'cod' => [
                    'enabled' => env('COD_ENABLED', true) === true,
                    'min_amount' => (float) env('COD_MIN_AMOUNT', 0),
                    'max_amount' => (float) env('COD_MAX_AMOUNT', 10000),
                    'service_charge' => (float) env('COD_SERVICE_CHARGE', 0)
                ]
            ],
            'currencies' => [
                'default' => env('PAYMENT_DEFAULT_CURRENCY', 'INR'),
                'supported' => array_filter(explode(',', env('PAYMENT_SUPPORTED_CURRENCIES', 'INR,USD')))
            ],
            'limits' => [
                'min_amount' => (float) env('PAYMENT_MIN_AMOUNT', 1),
                'max_amount' => (float) env('PAYMENT_MAX_AMOUNT', 100000),
                'daily_limit' => (float) env('PAYMENT_DAILY_LIMIT', 500000)
            ]
        ];
    }
    
    /**
     * Load application-specific configuration
     */
    private function loadApplicationConfig() {
        $this->config['features'] = [
            'user_registration' => env('FEATURE_USER_REGISTRATION', true) === true,
            'guest_checkout' => env('FEATURE_GUEST_CHECKOUT', true) === true,
            'product_reviews' => env('FEATURE_PRODUCT_REVIEWS', true) === true,
            'wishlist' => env('FEATURE_WISHLIST', true) === true,
            'inventory_tracking' => env('FEATURE_INVENTORY_TRACKING', true) === true,
            'multi_currency' => env('FEATURE_MULTI_CURRENCY', false) === true,
            'analytics' => env('FEATURE_ANALYTICS', true) === true
        ];
        
        $this->config['business'] = [
            'company_name' => env('COMPANY_NAME', 'Riya Collections'),
            'company_email' => env('COMPANY_EMAIL', 'orders@riyacollections.com'),
            'support_email' => env('SUPPORT_EMAIL', 'support@riyacollections.com'),
            'phone' => env('COMPANY_PHONE', ''),
            'address' => env('COMPANY_ADDRESS', ''),
            'tax_number' => env('COMPANY_TAX_NUMBER', ''),
            'currency' => env('BUSINESS_CURRENCY', 'INR'),
            'timezone' => env('BUSINESS_TIMEZONE', 'Asia/Kolkata')
        ];
        
        $this->config['api'] = [
            'version' => env('API_VERSION', 'v1'),
            'rate_limit' => (int) env('API_RATE_LIMIT', 1000),
            'pagination' => [
                'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 20),
                'max_per_page' => (int) env('API_MAX_PER_PAGE', 100)
            ],
            'cache' => [
                'enabled' => env('API_CACHE_ENABLED', true) === true,
                'ttl' => (int) env('API_CACHE_TTL', 300) // 5 minutes
            ]
        ];
    }
    
    /**
     * Load logging configuration
     */
    private function loadLoggingConfig() {
        $this->config['logging'] = [
            'default' => env('LOG_CHANNEL', 'file'),
            'channels' => [
                'file' => [
                    'driver' => 'file',
                    'path' => env('LOG_FILE_PATH', __DIR__ . '/../logs/app.log'),
                    'level' => env('LOG_LEVEL', 'info'),
                    'max_files' => (int) env('LOG_MAX_FILES', 10),
                    'max_size' => env('LOG_MAX_SIZE', '10MB'),
                    'daily' => env('LOG_DAILY_ROTATION', true) === true
                ],
                'error' => [
                    'driver' => 'file',
                    'path' => env('LOG_ERROR_PATH', __DIR__ . '/../logs/error.log'),
                    'level' => 'error'
                ],
                'security' => [
                    'driver' => 'file',
                    'path' => env('LOG_SECURITY_PATH', __DIR__ . '/../logs/security.log'),
                    'level' => 'info'
                ]
            ],
            'format' => env('LOG_FORMAT', 'json'),
            'include_context' => env('LOG_INCLUDE_CONTEXT', true) === true,
            'include_extra' => env('LOG_INCLUDE_EXTRA', true) === true
        ];
    }
    
    /**
     * Load file upload configuration
     */
    private function loadFileUploadConfig() {
        $this->config['upload'] = [
            'disk' => env('UPLOAD_DISK', 'local'),
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => env('UPLOAD_PATH', __DIR__ . '/../uploads'),
                    'url' => env('UPLOAD_URL', '/uploads'),
                    'visibility' => 'public'
                ]
            ],
            'limits' => [
                'max_file_size' => $this->parseSize(env('UPLOAD_MAX_FILE_SIZE', '5MB')),
                'max_files' => (int) env('UPLOAD_MAX_FILES', 10),
                'allowed_extensions' => array_filter(explode(',', env('UPLOAD_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,webp'))),
                'allowed_mime_types' => array_filter(explode(',', env('UPLOAD_ALLOWED_MIME_TYPES', 'image/jpeg,image/png,image/webp')))
            ],
            'image' => [
                'max_width' => (int) env('IMAGE_MAX_WIDTH', 1920),
                'max_height' => (int) env('IMAGE_MAX_HEIGHT', 1080),
                'quality' => (int) env('IMAGE_QUALITY', 85),
                'auto_orient' => env('IMAGE_AUTO_ORIENT', true) === true,
                'strip_metadata' => env('IMAGE_STRIP_METADATA', true) === true
            ],
            'security' => [
                'scan_uploads' => env('UPLOAD_SCAN_SECURITY', true) === true,
                'quarantine_suspicious' => env('UPLOAD_QUARANTINE_SUSPICIOUS', true) === true,
                'virus_scan' => env('UPLOAD_VIRUS_SCAN', false) === true
            ]
        ];
    }
    
    /**
     * Register configuration validators
     */
    private function registerValidators() {
        // Database validators
        $this->validators['database.connections.mysql.host'] = function($value) {
            return !empty($value) && (filter_var($value, FILTER_VALIDATE_IP) || filter_var($value, FILTER_VALIDATE_DOMAIN));
        };
        
        $this->validators['database.connections.mysql.port'] = function($value) {
            return is_int($value) && $value > 0 && $value <= 65535;
        };
        
        $this->validators['database.connections.mysql.database'] = function($value) {
            return !empty($value) && preg_match('/^[a-zA-Z0-9_]+$/', $value);
        };
        
        // Email validators
        $this->validators['email.from.address'] = function($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        };
        
        $this->validators['email.mailers.smtp.host'] = function($value) {
            return !empty($value);
        };
        
        $this->validators['email.mailers.smtp.port'] = function($value) {
            return is_int($value) && in_array($value, [25, 465, 587, 2525]);
        };
        
        // Security validators
        $this->validators['security.encryption.key'] = function($value) {
            $decrypted = $this->decryptSensitiveData($value);
            return strlen($decrypted) >= 32;
        };
        
        $this->validators['security.hashing.bcrypt_rounds'] = function($value) {
            return is_int($value) && $value >= 10 && $value <= 15;
        };
        
        // Payment validators
        $this->validators['payment.gateways.razorpay.key_id'] = function($value) {
            return !empty($value) && preg_match('/^rzp_(test_|live_)?[a-zA-Z0-9]+$/', $value);
        };
        
        // Upload validators
        $this->validators['upload.limits.max_file_size'] = function($value) {
            return is_int($value) && $value > 0 && $value <= 50 * 1024 * 1024; // Max 50MB
        };
    }
    
    /**
     * Validate configuration
     */
    public function validate() {
        $errors = [];
        
        foreach ($this->validators as $path => $validator) {
            $value = $this->get($path);
            
            try {
                if (!$validator($value)) {
                    $errors[] = "Invalid configuration value for '{$path}': " . json_encode($value);
                }
            } catch (Exception $e) {
                $errors[] = "Validation error for '{$path}': " . $e->getMessage();
            }
        }
        
        // Additional business logic validations
        $this->validateBusinessLogic($errors);
        
        if (!empty($errors)) {
            $this->safeLog('Configuration validation failed', ['errors' => $errors], 'error');
            throw new Exception('Configuration validation failed: ' . implode(', ', $errors));
        }
        
        $this->safeLog('Configuration validation passed');
        return true;
    }
    
    /**
     * Validate business logic rules
     */
    private function validateBusinessLogic(&$errors) {
        // Ensure production environment has secure settings
        if ($this->get('app.env') === 'production') {
            if ($this->get('app.debug') === true) {
                $errors[] = 'Debug mode must be disabled in production';
            }
            
            if (strpos($this->get('app.url'), 'http://') === 0) {
                $errors[] = 'HTTPS must be used in production';
            }
            
            $jwtSecret = env('JWT_SECRET');
            if (empty($jwtSecret) || strlen($jwtSecret) < 32) {
                $errors[] = 'JWT secret must be at least 32 characters in production';
            }
        }
        
        // Validate payment configuration
        if ($this->get('payment.gateways.razorpay.key_id')) {
            $keySecret = $this->get('payment.gateways.razorpay.key_secret');
            if (empty($this->decryptSensitiveData($keySecret))) {
                $errors[] = 'Razorpay key secret is required when key ID is provided';
            }
        }
        
        // Validate email configuration
        if ($this->get('email.default') === 'smtp') {
            $host = $this->get('email.mailers.smtp.host');
            $username = $this->get('email.mailers.smtp.username');
            
            if (empty($host)) {
                $errors[] = 'SMTP host is required when using SMTP mailer';
            }
            
            if (empty($username)) {
                $errors[] = 'SMTP username is required when using SMTP mailer';
            }
        }
    }
    
    /**
     * Get configuration value using dot notation
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        // Decrypt sensitive data if needed
        if (is_string($value) && $this->isSensitiveKey($key)) {
            return $this->decryptSensitiveData($value);
        }
        
        return $value;
    }
    
    /**
     * Set configuration value using dot notation
     */
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        // Encrypt sensitive data if needed
        if ($this->isSensitiveKey($key)) {
            $value = $this->encryptSensitiveData($value);
        }
        
        $config = $value;
        
        // Clear cache
        $this->clearCache();
        
        $this->safeLog('Configuration value updated', ['key' => $key]);
    }
    
    /**
     * Check if configuration key contains sensitive data
     */
    private function isSensitiveKey($key) {
        $sensitivePatterns = [
            'password',
            'secret',
            'key',
            'token',
            'credential',
            'private'
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
     * Encrypt sensitive data
     */
    private function encryptSensitiveData($data) {
        if (empty($data)) {
            return $data;
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    private function decryptSensitiveData($encryptedData) {
        if (empty($encryptedData)) {
            return $encryptedData;
        }
        
        try {
            $data = base64_decode($encryptedData);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        } catch (Exception $e) {
            // If decryption fails, assume it's plain text (for backward compatibility)
            return $encryptedData;
        }
    }
    
    /**
     * Generate secure encryption key
     */
    private function generateSecureKey() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Parse size string to bytes
     */
    private function parseSize($size) {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824];
        
        if (is_numeric($size)) {
            return (int) $size;
        }
        
        $matches = [];
        if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMGT]?B)$/i', $size, $matches)) {
            $value = (float) $matches[1];
            $unit = strtoupper($matches[2]);
            
            return (int) ($value * ($units[$unit] ?? 1));
        }
        
        return 0;
    }
    
    /**
     * Load configuration from cache
     */
    private function loadFromCache() {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        
        $cacheData = file_get_contents($this->cacheFile);
        $cache = json_decode($cacheData, true);
        
        if (!$cache || !isset($cache['config'], $cache['timestamp'])) {
            return false;
        }
        
        // Check if cache is still valid (check file modification times)
        $configFiles = [
            $this->configPath . '/environment.php',
            __DIR__ . '/../.env'
        ];
        
        foreach ($configFiles as $file) {
            if (file_exists($file) && filemtime($file) > $cache['timestamp']) {
                return false; // Cache is outdated
            }
        }
        
        $this->config = $cache['config'];
        $this->lastModified = $cache['last_modified'] ?? [];
        
        return true;
    }
    
    /**
     * Save configuration to cache
     */
    private function saveToCache() {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheData = [
            'config' => $this->config,
            'timestamp' => time(),
            'last_modified' => $this->lastModified
        ];
        
        file_put_contents($this->cacheFile, json_encode($cacheData), LOCK_EX);
    }
    
    /**
     * Clear configuration cache
     */
    public function clearCache() {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        
        $this->safeLog('Configuration cache cleared');
    }
    
    /**
     * Reload configuration
     */
    public function reload() {
        $this->clearCache();
        $this->config = [];
        $this->cache = [];
        $this->lastModified = [];
        $this->loadConfiguration();
        
        $this->safeLog('Configuration reloaded');
    }
    
    /**
     * Get all configuration
     */
    public function all() {
        return $this->config;
    }
    
    /**
     * Check if configuration key exists
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Get configuration for specific section
     */
    public function section($section) {
        return $this->get($section, []);
    }
    
    /**
     * Export configuration (without sensitive data)
     */
    public function export($includeSensitive = false) {
        $config = $this->config;
        
        if (!$includeSensitive) {
            $config = $this->removeSensitiveData($config);
        }
        
        return $config;
    }
    
    /**
     * Remove sensitive data from configuration array
     */
    private function removeSensitiveData($config, $path = '') {
        if (!is_array($config)) {
            return $this->isSensitiveKey($path) ? '[REDACTED]' : $config;
        }
        
        $result = [];
        
        foreach ($config as $key => $value) {
            $currentPath = $path ? $path . '.' . $key : $key;
            $result[$key] = $this->removeSensitiveData($value, $currentPath);
        }
        
        return $result;
    }
    
    /**
     * Get configuration summary for monitoring
     */
    public function getSummary() {
        return [
            'environment' => $this->get('app.env'),
            'debug_mode' => $this->get('app.debug'),
            'database_host' => $this->get('database.connections.mysql.host'),
            'email_driver' => $this->get('email.default'),
            'payment_gateway' => $this->get('payment.default_gateway'),
            'cache_enabled' => file_exists($this->cacheFile),
            'last_loaded' => date('Y-m-d H:i:s'),
            'config_sections' => array_keys($this->config)
        ];
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize ConfigManager");
    }
}

// Global helper functions
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        $manager = ConfigManager::getInstance();
        
        if ($key === null) {
            return $manager;
        }
        
        return $manager->get($key, $default);
    }
}

if (!function_exists('config_set')) {
    function config_set($key, $value) {
        return ConfigManager::getInstance()->set($key, $value);
    }
}

if (!function_exists('config_has')) {
    function config_has($key) {
        return ConfigManager::getInstance()->has($key);
    }
}

if (!function_exists('config_section')) {
    function config_section($section) {
        return ConfigManager::getInstance()->section($section);
    }
}

if (!function_exists('config_reload')) {
    function config_reload() {
        return ConfigManager::getInstance()->reload();
    }
}

if (!function_exists('config_validate')) {
    function config_validate() {
        return ConfigManager::getInstance()->validate();
    }
}