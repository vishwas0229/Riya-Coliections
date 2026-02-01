<?php
/**
 * Environment Configuration
 * 
 * This module handles environment variable loading and configuration management
 * for different deployment environments (development, production, testing).
 * Enhanced with configuration management system integration.
 * 
 * Requirements: 14.2, 14.4
 */

// Load configuration management system
require_once __DIR__ . '/ConfigManager.php';

/**
 * Load environment variables from .env file
 */
function loadEnvironmentVariables() {
    $envFile = __DIR__ . '/../../.env';
    
    // Check if .env file exists
    if (!file_exists($envFile)) {
        // In production, environment variables should be set by the hosting provider
        if (getenv('APP_ENV') !== 'production') {
            error_log("Warning: .env file not found at {$envFile}");
        }
        return;
    }
    
    // Read and parse .env file
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes from value
            $value = trim($value, '"\'');
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

/**
 * Get environment variable with default value and type conversion
 */
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string representations of boolean values
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case 'empty':
        case '(empty)':
            return '';
    }
    
    return $value;
}

/**
 * Get configuration value using the ConfigManager
 */
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        static $configManager = null;
        
        if ($configManager === null) {
            try {
                $configManager = ConfigManager::getInstance();
            } catch (Exception $e) {
                // Fallback to environment variables if ConfigManager fails
                return $key ? env($key, $default) : null;
            }
        }
        
        if ($key === null) {
            return $configManager;
        }
        
        return $configManager->get($key, $default);
    }
}

/**
 * Check if application is in debug mode
 */
function isDebugMode() {
    return env('APP_DEBUG', false) === true || env('APP_ENV', 'production') === 'development';
}

/**
 * Check if application is in production
 */
function isProduction() {
    return env('APP_ENV', 'production') === 'production';
}

/**
 * Check if application is in development
 */
function isDevelopment() {
    return env('APP_ENV', 'production') === 'development';
}

/**
 * Check if application is in testing mode
 */
function isTesting() {
    return env('APP_ENV', 'production') === 'testing';
}

/**
 * Get application configuration with enhanced features
 */
function getAppConfig() {
    return [
        'name' => config('app.name', env('APP_NAME', 'Riya Collections')),
        'env' => config('app.env', env('APP_ENV', 'production')),
        'debug' => config('app.debug', env('APP_DEBUG', false)),
        'url' => config('app.url', env('APP_URL', 'https://riyacollections.com')),
        'timezone' => config('app.timezone', env('APP_TIMEZONE', 'UTC')),
        'locale' => config('app.locale', env('APP_LOCALE', 'en')),
        'version' => config('app.version', env('APP_VERSION', '1.0.0')),
        'maintenance_mode' => config('app.maintenance_mode', env('MAINTENANCE_MODE', false))
    ];
}

/**
 * Get database configuration with enhanced connection management
 */
function getDatabaseConfig() {
    return config('database.connections.mysql', [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_NAME', 'riya_collections'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'connection_limit' => (int) env('DB_CONNECTION_LIMIT', 10)
    ]);
}

/**
 * Get JWT configuration with enhanced security
 */
function getJWTConfig() {
    return config('security.jwt', [
        'secret' => env('JWT_SECRET', 'fallback_secret_change_in_production'),
        'expires_in' => env('JWT_EXPIRES_IN', '24h'),
        'refresh_secret' => env('JWT_REFRESH_SECRET', 'fallback_refresh_secret'),
        'refresh_expires_in' => env('JWT_REFRESH_EXPIRES_IN', '7d'),
        'issuer' => env('JWT_ISSUER', 'riya-collections'),
        'audience' => env('JWT_AUDIENCE', 'riya-collections-users')
    ]);
}

/**
 * Get email configuration with enhanced features
 */
function getEmailConfig() {
    return config('email', [
        'default' => env('MAIL_MAILER', 'smtp'),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', env('COMPANY_EMAIL', 'orders@riyacollections.com')),
            'name' => env('MAIL_FROM_NAME', env('COMPANY_NAME', 'Riya Collections'))
        ],
        'mailers' => [
            'smtp' => [
                'host' => env('SMTP_HOST', 'smtp.gmail.com'),
                'port' => (int) env('SMTP_PORT', 587),
                'secure' => env('SMTP_SECURE', 'false') === 'true',
                'username' => env('SMTP_USER'),
                'password' => env('SMTP_PASSWORD'),
                'timeout' => 30
            ]
        ]
    ]);
}

/**
 * Get Razorpay configuration with enhanced security
 */
function getRazorpayConfig() {
    return config('payment.gateways.razorpay', [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'currency' => env('RAZORPAY_CURRENCY', 'INR'),
        'test_mode' => env('RAZORPAY_TEST_MODE', !isProduction())
    ]);
}

/**
 * Get file upload configuration with enhanced security
 */
function getUploadConfig() {
    return config('upload', [
        'limits' => [
            'max_file_size' => (int) env('MAX_FILE_SIZE', 5242880), // 5MB
            'allowed_extensions' => explode(',', env('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,webp')),
            'max_files' => (int) env('UPLOAD_MAX_FILES', 10)
        ],
        'image' => [
            'quality' => (int) env('IMAGE_QUALITY', 85),
            'max_width' => (int) env('MAX_IMAGE_WIDTH', 1920),
            'max_height' => (int) env('MAX_IMAGE_HEIGHT', 1080)
        ],
        'security' => [
            'scan_uploads' => env('UPLOAD_SCAN_SECURITY', true) === true,
            'virus_scan' => env('UPLOAD_VIRUS_SCAN', false) === true
        ]
    ]);
}

/**
 * Get asset serving configuration with compression support
 */
function getAssetConfig() {
    return config('assets', [
        'compression' => [
            'enabled' => env('ENABLE_ASSET_COMPRESSION', true) === true,
            'level' => (int) env('COMPRESSION_LEVEL', 6),
            'min_size' => (int) env('COMPRESSION_MIN_SIZE', 1024), // Only compress files > 1KB
            'types' => explode(',', env('COMPRESSIBLE_TYPES', 'text/css,application/javascript,text/html,application/json,image/svg+xml'))
        ],
        'caching' => [
            'enabled' => env('ENABLE_ASSET_CACHING', true) === true,
            'duration' => (int) env('ASSET_CACHE_DURATION', 86400), // 24 hours
            'etag_enabled' => env('ENABLE_ETAG', true) === true,
            'last_modified_enabled' => env('ENABLE_LAST_MODIFIED', true) === true
        ],
        'security' => [
            'max_file_size' => (int) env('MAX_ASSET_SIZE', 52428800), // 50MB
            'allowed_directories' => explode(',', env('ASSET_ALLOWED_DIRS', 'public/assets,public/uploads,public/pages')),
            'blocked_extensions' => explode(',', env('ASSET_BLOCKED_EXTENSIONS', 'php,exe,bat,sh,py'))
        ],
        'performance' => [
            'log_requests' => env('LOG_ASSET_REQUESTS', false) === true,
            'enable_range_requests' => env('ENABLE_RANGE_REQUESTS', true) === true
        ]
    ]);
}

/**
 * Get security configuration with enhanced features
 */
function getSecurityConfig() {
    return config('security', [
        'rate_limiting' => [
            'enabled' => env('RATE_LIMITING_ENABLED', true) === true,
            'window' => (int) env('RATE_LIMIT_WINDOW_MS', 900000), // 15 minutes
            'max_requests' => (int) env('RATE_LIMIT_MAX_REQUESTS', 100)
        ],
        'cors' => [
            'allowed_origins' => explode(',', env('ALLOWED_ORIGINS', 'https://riyacollections.com')),
            'supports_credentials' => true
        ],
        'encryption' => [
            'cipher' => 'AES-256-CBC',
            'key' => env('ENCRYPTION_KEY', 'fallback_key_change_in_production')
        ],
        'hashing' => [
            'bcrypt_rounds' => (int) env('BCRYPT_SALT_ROUNDS', 12)
        ]
    ]);
}

/**
 * Get logging configuration with enhanced features
 */
function getLoggingConfig() {
    return config('logging', [
        'default' => env('LOG_CHANNEL', 'file'),
        'channels' => [
            'file' => [
                'driver' => 'file',
                'path' => env('LOG_FILE', 'logs/app.log'),
                'level' => env('LOG_LEVEL', 'info'),
                'max_files' => (int) env('LOG_MAX_FILES', 10),
                'max_size' => env('LOG_MAX_SIZE', '10MB'),
                'daily' => env('LOG_DAILY_ROTATION', 'true') === 'true'
            ]
        ]
    ]);
}

/**
 * Validate required environment variables
 */
function validateEnvironment() {
    $required = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'JWT_SECRET'
    ];
    
    $missing = [];
    
    foreach ($required as $var) {
        if (!env($var)) {
            $missing[] = $var;
        }
    }
    
    if (!empty($missing)) {
        $message = 'Missing required environment variables: ' . implode(', ', $missing);
        error_log($message);
        
        if (!isProduction()) {
            throw new Exception($message);
        }
    }
    
    // Validate JWT secret strength
    $jwtSecret = env('JWT_SECRET');
    if ($jwtSecret && strlen($jwtSecret) < 32) {
        $message = 'JWT_SECRET must be at least 32 characters long for security';
        error_log($message);
        
        if (isProduction()) {
            throw new Exception($message);
        }
    }
    
    return empty($missing);
}

/**
 * Set PHP configuration based on environment
 */
function configurePHP() {
    // Set timezone
    date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));
    
    // Configure error reporting
    if (isProduction()) {
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
    }
    
    // Set error log file
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
    
    // Security settings
    ini_set('expose_php', 0);
    ini_set('allow_url_fopen', 0);
    ini_set('allow_url_include', 0);
    
    // Session security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isProduction() ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    
    // File upload limits
    $uploadConfig = getUploadConfig();
    if (isset($uploadConfig['limits']['max_file_size'])) {
        ini_set('upload_max_filesize', $uploadConfig['limits']['max_file_size']);
        ini_set('post_max_size', $uploadConfig['limits']['max_file_size'] * 2);
    }
    ini_set('max_file_uploads', 10);
    
    // Memory and execution limits
    ini_set('memory_limit', env('PHP_MEMORY_LIMIT', '256M'));
    ini_set('max_execution_time', env('PHP_MAX_EXECUTION_TIME', 30));
    ini_set('max_input_time', env('PHP_MAX_INPUT_TIME', 30));
}

// Load environment variables
loadEnvironmentVariables();

// Configure PHP settings
configurePHP();

// Validate environment (only in non-production for now)
if (!isProduction()) {
    validateEnvironment();
}