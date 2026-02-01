<?php
/**
 * Configuration Initialization Script
 * 
 * This script initializes the configuration management system, validates settings,
 * and ensures all required configurations are properly loaded and validated.
 * 
 * Requirements: 14.2, 14.4
 */

// Prevent direct access
if (!defined('CONFIG_INIT_ALLOWED')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Load required components
require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/../services/ConfigValidationService.php';
require_once __DIR__ . '/../services/CredentialManager.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Configuration Initialization Class
 */
class ConfigInitializer {
    private $configManager;
    private $validator;
    private $credentialManager;
    private $initializationLog = [];
    
    public function __construct() {
        $this->configManager = ConfigManager::getInstance();
        $this->validator = new ConfigValidationService();
        $this->credentialManager = CredentialManager::getInstance();
    }
    
    /**
     * Initialize configuration system
     */
    public function initialize() {
        try {
            $this->log('Starting configuration initialization');
            
            // Step 1: Load environment variables
            $this->loadEnvironmentVariables();
            
            // Step 2: Initialize credential management
            $this->initializeCredentials();
            
            // Step 3: Validate configuration
            $this->validateConfiguration();
            
            // Step 4: Setup environment-specific settings
            $this->setupEnvironmentSettings();
            
            // Step 5: Initialize logging
            $this->initializeLogging();
            
            // Step 6: Setup security headers
            $this->setupSecurityHeaders();
            
            // Step 7: Initialize caching
            $this->initializeCaching();
            
            // Step 8: Cleanup and optimization
            $this->performCleanup();
            
            $this->log('Configuration initialization completed successfully');
            
            return [
                'success' => true,
                'environment' => $this->configManager->get('app.env'),
                'debug_mode' => $this->configManager->get('app.debug'),
                'initialization_log' => $this->initializationLog
            ];
            
        } catch (Exception $e) {
            $this->log('Configuration initialization failed: ' . $e->getMessage(), 'error');
            
            Logger::error('Configuration initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'initialization_log' => $this->initializationLog
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Load environment variables
     */
    private function loadEnvironmentVariables() {
        $this->log('Loading environment variables');
        
        // Check if .env file exists
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            $this->log('No .env file found, using system environment variables', 'warning');
            return;
        }
        
        // Load .env file
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $loaded = 0;
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');
                
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    $loaded++;
                }
            }
        }
        
        $this->log("Loaded {$loaded} environment variables from .env file");
    }
    
    /**
     * Initialize credential management
     */
    private function initializeCredentials() {
        $this->log('Initializing credential management');
        
        try {
            // Import sensitive environment variables to credential store
            $imported = $this->credentialManager->importFromEnvironment('', false);
            
            $this->log("Imported {$imported['imported']} credentials, skipped {$imported['skipped']} existing");
            
            // Clean up expired credentials
            $cleaned = $this->credentialManager->cleanupExpired();
            if ($cleaned > 0) {
                $this->log("Cleaned up {$cleaned} expired credentials");
            }
            
            // Check for credentials needing rotation
            $rotationCandidates = $this->credentialManager->getRotationCandidates();
            if (!empty($rotationCandidates)) {
                $this->log(count($rotationCandidates) . ' credentials need rotation', 'warning');
                
                foreach ($rotationCandidates as $candidate) {
                    Logger::warning('Credential needs rotation', $candidate);
                }
            }
            
        } catch (Exception $e) {
            $this->log('Credential initialization failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Validate configuration
     */
    private function validateConfiguration() {
        $this->log('Validating configuration');
        
        try {
            $isValid = $this->validator->validateAll();
            
            if (!$isValid) {
                $errors = $this->validator->getErrors();
                $this->log('Configuration validation failed with ' . count($errors) . ' errors', 'error');
                
                foreach ($errors as $error) {
                    $this->log("Validation error: {$error['message']}", 'error');
                }
                
                throw new Exception('Configuration validation failed');
            }
            
            $warnings = $this->validator->getWarnings();
            if (!empty($warnings)) {
                $this->log('Configuration validation completed with ' . count($warnings) . ' warnings', 'warning');
                
                foreach ($warnings as $warning) {
                    $this->log("Validation warning: {$warning['message']}", 'warning');
                }
            } else {
                $this->log('Configuration validation passed successfully');
            }
            
        } catch (Exception $e) {
            $this->log('Configuration validation error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Setup environment-specific settings
     */
    private function setupEnvironmentSettings() {
        $env = $this->configManager->get('app.env');
        $this->log("Setting up environment-specific settings for: {$env}");
        
        // Configure PHP settings based on environment
        $this->configurePHPSettings($env);
        
        // Setup error reporting
        $this->setupErrorReporting($env);
        
        // Configure session settings
        $this->configureSessionSettings($env);
        
        // Setup timezone
        $timezone = $this->configManager->get('app.timezone', 'UTC');
        date_default_timezone_set($timezone);
        $this->log("Timezone set to: {$timezone}");
    }
    
    /**
     * Configure PHP settings
     */
    private function configurePHPSettings($env) {
        // Memory and execution limits
        $memoryLimit = $this->configManager->get('php.memory_limit', '256M');
        $executionTime = $this->configManager->get('php.max_execution_time', 30);
        
        ini_set('memory_limit', $memoryLimit);
        ini_set('max_execution_time', $executionTime);
        
        // Security settings
        ini_set('expose_php', 0);
        ini_set('allow_url_fopen', 0);
        ini_set('allow_url_include', 0);
        
        // File upload settings
        $maxFileSize = $this->configManager->get('upload.limits.max_file_size', 5242880);
        ini_set('upload_max_filesize', $maxFileSize);
        ini_set('post_max_size', $maxFileSize * 2);
        
        $this->log('PHP settings configured');
    }
    
    /**
     * Setup error reporting
     */
    private function setupErrorReporting($env) {
        if ($env === 'production') {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        }
        
        // Set error log file
        $errorLogFile = $this->configManager->get('logging.channels.error.path', __DIR__ . '/../logs/php_errors.log');
        ini_set('error_log', $errorLogFile);
        
        $this->log('Error reporting configured');
    }
    
    /**
     * Configure session settings
     */
    private function configureSessionSettings($env) {
        $sessionConfig = $this->configManager->get('security.session', []);
        
        ini_set('session.cookie_httponly', $sessionConfig['httponly'] ?? 1);
        ini_set('session.cookie_secure', $sessionConfig['secure'] ?? ($env === 'production' ? 1 : 0));
        ini_set('session.cookie_samesite', $sessionConfig['samesite'] ?? 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        
        $this->log('Session settings configured');
    }
    
    /**
     * Initialize logging
     */
    private function initializeLogging() {
        $this->log('Initializing logging system');
        
        try {
            // Ensure log directory exists
            $logPath = dirname($this->configManager->get('logging.channels.file.path'));
            if (!is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }
            
            // Set up log rotation if enabled
            if ($this->configManager->get('logging.channels.file.daily', false)) {
                $this->setupLogRotation();
            }
            
            $this->log('Logging system initialized');
            
        } catch (Exception $e) {
            $this->log('Logging initialization failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Setup log rotation
     */
    private function setupLogRotation() {
        $logFile = $this->configManager->get('logging.channels.file.path');
        $maxFiles = $this->configManager->get('logging.channels.file.max_files', 10);
        
        if (file_exists($logFile)) {
            $fileSize = filesize($logFile);
            $maxSize = $this->parseSize($this->configManager->get('logging.channels.file.max_size', '10MB'));
            
            if ($fileSize > $maxSize) {
                $this->rotateLogFile($logFile, $maxFiles);
            }
        }
    }
    
    /**
     * Rotate log file
     */
    private function rotateLogFile($logFile, $maxFiles) {
        $pathInfo = pathinfo($logFile);
        $baseName = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'log';
        
        // Rotate existing files
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $oldFile = "{$baseName}.{$i}.{$extension}";
            $newFile = "{$baseName}." . ($i + 1) . ".{$extension}";
            
            if (file_exists($oldFile)) {
                if ($i === $maxFiles - 1) {
                    unlink($oldFile); // Delete oldest file
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current log to .1
        if (file_exists($logFile)) {
            rename($logFile, "{$baseName}.1.{$extension}");
        }
        
        $this->log('Log file rotated');
    }
    
    /**
     * Setup security headers
     */
    private function setupSecurityHeaders() {
        $this->log('Setting up security headers');
        
        $headers = $this->configManager->get('security.headers', []);
        
        foreach ($headers as $header => $value) {
            if (!empty($value)) {
                header("{$header}: {$value}");
            }
        }
        
        // CORS headers if configured
        $corsConfig = $this->configManager->get('security.cors', []);
        if (!empty($corsConfig['allowed_origins'])) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            if (in_array('*', $corsConfig['allowed_origins']) || in_array($origin, $corsConfig['allowed_origins'])) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Access-Control-Allow-Credentials: ' . ($corsConfig['supports_credentials'] ? 'true' : 'false'));
                
                if (!empty($corsConfig['allowed_methods'])) {
                    header('Access-Control-Allow-Methods: ' . implode(', ', $corsConfig['allowed_methods']));
                }
                
                if (!empty($corsConfig['allowed_headers'])) {
                    header('Access-Control-Allow-Headers: ' . implode(', ', $corsConfig['allowed_headers']));
                }
                
                if (!empty($corsConfig['max_age'])) {
                    header('Access-Control-Max-Age: ' . $corsConfig['max_age']);
                }
            }
        }
        
        $this->log('Security headers configured');
    }
    
    /**
     * Initialize caching
     */
    private function initializeCaching() {
        $this->log('Initializing caching system');
        
        try {
            $cacheEnabled = $this->configManager->get('cache.enabled', false);
            
            if ($cacheEnabled) {
                $cacheDir = __DIR__ . '/../cache';
                if (!is_dir($cacheDir)) {
                    mkdir($cacheDir, 0755, true);
                }
                
                // Create cache index if it doesn't exist
                $cacheIndex = $cacheDir . '/index.json';
                if (!file_exists($cacheIndex)) {
                    file_put_contents($cacheIndex, json_encode([]));
                }
                
                $this->log('Caching system enabled and initialized');
            } else {
                $this->log('Caching system disabled');
            }
            
        } catch (Exception $e) {
            $this->log('Caching initialization failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Perform cleanup and optimization
     */
    private function performCleanup() {
        $this->log('Performing cleanup and optimization');
        
        try {
            // Clean up old log files
            $this->cleanupOldLogs();
            
            // Clean up temporary files
            $this->cleanupTempFiles();
            
            // Optimize configuration cache
            $this->optimizeConfigCache();
            
            $this->log('Cleanup and optimization completed');
            
        } catch (Exception $e) {
            $this->log('Cleanup failed: ' . $e->getMessage(), 'warning');
            // Don't throw exception for cleanup failures
        }
    }
    
    /**
     * Clean up old log files
     */
    private function cleanupOldLogs() {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            return;
        }
        
        $maxAge = 30 * 24 * 3600; // 30 days
        $now = time();
        $cleaned = 0;
        
        $files = glob($logDir . '/*.log*');
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->log("Cleaned up {$cleaned} old log files");
        }
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles() {
        $tempDir = sys_get_temp_dir() . '/riya_collections';
        if (!is_dir($tempDir)) {
            return;
        }
        
        $maxAge = 24 * 3600; // 24 hours
        $now = time();
        $cleaned = 0;
        
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->log("Cleaned up {$cleaned} temporary files");
        }
    }
    
    /**
     * Optimize configuration cache
     */
    private function optimizeConfigCache() {
        // Force cache regeneration if it's old
        $cacheFile = __DIR__ . '/../cache/config.cache';
        if (file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            $maxAge = 24 * 3600; // 24 hours
            
            if ($cacheAge > $maxAge) {
                $this->configManager->clearCache();
                $this->log('Configuration cache cleared and regenerated');
            }
        }
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
     * Log initialization step
     */
    private function log($message, $level = 'info') {
        $this->initializationLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message
        ];
        
        // Also log to system if Logger is available
        if (class_exists('Logger')) {
            switch ($level) {
                case 'error':
                    Logger::error($message);
                    break;
                case 'warning':
                    Logger::warning($message);
                    break;
                default:
                    Logger::info($message);
                    break;
            }
        }
    }
    
    /**
     * Get initialization summary
     */
    public function getSummary() {
        return [
            'environment' => $this->configManager->get('app.env'),
            'debug_mode' => $this->configManager->get('app.debug'),
            'config_valid' => $this->validator->isValid(),
            'credential_count' => count($this->credentialManager->listKeys()),
            'initialization_steps' => count($this->initializationLog),
            'last_initialized' => date('Y-m-d H:i:s')
        ];
    }
}

// Initialize configuration if this file is included
if (!defined('CONFIG_SKIP_AUTO_INIT')) {
    define('CONFIG_INIT_ALLOWED', true);
    
    try {
        $initializer = new ConfigInitializer();
        $result = $initializer->initialize();
        
        // Store initialization result for debugging
        if (!isProduction()) {
            $_SERVER['CONFIG_INIT_RESULT'] = $result;
        }
        
    } catch (Exception $e) {
        // Log error and continue (don't break the application)
        error_log('Configuration initialization failed: ' . $e->getMessage());
        
        if (!isProduction()) {
            $_SERVER['CONFIG_INIT_ERROR'] = $e->getMessage();
        }
    }
}

// Helper function to get initialization status
function getConfigInitStatus() {
    return [
        'success' => isset($_SERVER['CONFIG_INIT_RESULT']),
        'error' => $_SERVER['CONFIG_INIT_ERROR'] ?? null,
        'result' => $_SERVER['CONFIG_INIT_RESULT'] ?? null
    ];
}