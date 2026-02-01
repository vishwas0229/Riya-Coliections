<?php
/**
 * Configuration Validation Service
 * 
 * This service provides comprehensive validation for configuration settings,
 * ensuring that all required values are present and valid before the application starts.
 * 
 * Requirements: 14.2, 14.4
 */

require_once __DIR__ . '/../config/ConfigManager.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Configuration Validation Service
 */
class ConfigValidationService {
    private $configManager;
    private $validationRules;
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        $this->configManager = ConfigManager::getInstance();
        $this->initializeValidationRules();
    }
    
    /**
     * Initialize validation rules
     */
    private function initializeValidationRules() {
        $this->validationRules = [
            // Application configuration
            'app.name' => [
                'required' => true,
                'type' => 'string',
                'min_length' => 1,
                'max_length' => 100
            ],
            'app.env' => [
                'required' => true,
                'type' => 'string',
                'allowed_values' => ['development', 'testing', 'staging', 'production']
            ],
            'app.url' => [
                'required' => true,
                'type' => 'url',
                'schemes' => ['http', 'https']
            ],
            'app.timezone' => [
                'required' => true,
                'type' => 'timezone'
            ],
            
            // Database configuration
            'database.connections.mysql.host' => [
                'required' => true,
                'type' => 'string',
                'validation' => 'host'
            ],
            'database.connections.mysql.port' => [
                'required' => true,
                'type' => 'integer',
                'min' => 1,
                'max' => 65535
            ],
            'database.connections.mysql.database' => [
                'required' => true,
                'type' => 'string',
                'pattern' => '/^[a-zA-Z0-9_]+$/'
            ],
            'database.connections.mysql.username' => [
                'required' => true,
                'type' => 'string',
                'min_length' => 1
            ],
            
            // Security configuration
            'security.encryption.key' => [
                'required' => true,
                'type' => 'string',
                'min_length' => 32,
                'validation' => 'encryption_key'
            ],
            'security.hashing.bcrypt_rounds' => [
                'required' => true,
                'type' => 'integer',
                'min' => 10,
                'max' => 15
            ],
            
            // Email configuration
            'email.from.address' => [
                'required' => true,
                'type' => 'email'
            ],
            'email.mailers.smtp.host' => [
                'required_if' => 'email.default=smtp',
                'type' => 'string',
                'min_length' => 1
            ],
            'email.mailers.smtp.port' => [
                'required_if' => 'email.default=smtp',
                'type' => 'integer',
                'allowed_values' => [25, 465, 587, 2525]
            ],
            'email.mailers.smtp.username' => [
                'required_if' => 'email.default=smtp',
                'type' => 'string'
            ],
            
            // Payment configuration
            'payment.gateways.razorpay.key_id' => [
                'required_if' => 'payment.default_gateway=razorpay',
                'type' => 'string',
                'pattern' => '/^rzp_(test_|live_)?[a-zA-Z0-9]+$/'
            ],
            'payment.gateways.razorpay.key_secret' => [
                'required_if' => 'payment.default_gateway=razorpay',
                'type' => 'string',
                'min_length' => 1
            ],
            
            // Upload configuration
            'upload.limits.max_file_size' => [
                'required' => true,
                'type' => 'integer',
                'min' => 1,
                'max' => 52428800 // 50MB
            ],
            'upload.limits.allowed_extensions' => [
                'required' => true,
                'type' => 'array',
                'min_items' => 1
            ],
            
            // Business configuration
            'business.company_name' => [
                'required' => true,
                'type' => 'string',
                'min_length' => 1
            ],
            'business.company_email' => [
                'required' => true,
                'type' => 'email'
            ]
        ];
    }
    
    /**
     * Validate all configuration
     */
    public function validateAll() {
        $this->errors = [];
        $this->warnings = [];
        
        Logger::info('Starting configuration validation');
        
        // Validate individual rules
        foreach ($this->validationRules as $key => $rules) {
            $this->validateConfigKey($key, $rules);
        }
        
        // Validate business logic
        $this->validateBusinessLogic();
        
        // Validate environment-specific requirements
        $this->validateEnvironmentRequirements();
        
        // Validate security requirements
        $this->validateSecurityRequirements();
        
        // Log results
        if (!empty($this->errors)) {
            Logger::error('Configuration validation failed', [
                'errors' => $this->errors,
                'warnings' => $this->warnings
            ]);
            return false;
        }
        
        if (!empty($this->warnings)) {
            Logger::warning('Configuration validation completed with warnings', [
                'warnings' => $this->warnings
            ]);
        } else {
            Logger::info('Configuration validation passed successfully');
        }
        
        return true;
    }
    
    /**
     * Validate individual configuration key
     */
    private function validateConfigKey($key, $rules) {
        $value = $this->configManager->get($key);
        
        // Check required fields
        if (isset($rules['required']) && $rules['required'] && $this->isEmpty($value)) {
            $this->addError($key, "Required configuration '{$key}' is missing or empty");
            return;
        }
        
        // Check conditional requirements
        if (isset($rules['required_if']) && $this->checkConditionalRequirement($rules['required_if'])) {
            if ($this->isEmpty($value)) {
                $this->addError($key, "Required configuration '{$key}' is missing (required by condition: {$rules['required_if']})");
                return;
            }
        }
        
        // Skip further validation if value is empty and not required
        if ($this->isEmpty($value)) {
            return;
        }
        
        // Validate type
        if (isset($rules['type'])) {
            if (!$this->validateType($value, $rules['type'])) {
                $this->addError($key, "Configuration '{$key}' must be of type {$rules['type']}");
                return;
            }
        }
        
        // Validate string length
        if (is_string($value)) {
            if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                $this->addError($key, "Configuration '{$key}' must be at least {$rules['min_length']} characters long");
            }
            
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $this->addError($key, "Configuration '{$key}' must be no more than {$rules['max_length']} characters long");
            }
        }
        
        // Validate numeric ranges
        if (is_numeric($value)) {
            if (isset($rules['min']) && $value < $rules['min']) {
                $this->addError($key, "Configuration '{$key}' must be at least {$rules['min']}");
            }
            
            if (isset($rules['max']) && $value > $rules['max']) {
                $this->addError($key, "Configuration '{$key}' must be no more than {$rules['max']}");
            }
        }
        
        // Validate array items
        if (is_array($value)) {
            if (isset($rules['min_items']) && count($value) < $rules['min_items']) {
                $this->addError($key, "Configuration '{$key}' must have at least {$rules['min_items']} items");
            }
            
            if (isset($rules['max_items']) && count($value) > $rules['max_items']) {
                $this->addError($key, "Configuration '{$key}' must have no more than {$rules['max_items']} items");
            }
        }
        
        // Validate allowed values
        if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'])) {
            $allowed = implode(', ', $rules['allowed_values']);
            $this->addError($key, "Configuration '{$key}' must be one of: {$allowed}");
        }
        
        // Validate pattern
        if (isset($rules['pattern']) && is_string($value)) {
            if (!preg_match($rules['pattern'], $value)) {
                $this->addError($key, "Configuration '{$key}' does not match required pattern");
            }
        }
        
        // Custom validation
        if (isset($rules['validation'])) {
            $this->performCustomValidation($key, $value, $rules['validation']);
        }
    }
    
    /**
     * Validate data type
     */
    private function validateType($value, $type) {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'float':
                return is_float($value) || is_numeric($value);
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'email':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'timezone':
                return is_string($value) && in_array($value, timezone_identifiers_list());
            default:
                return true;
        }
    }
    
    /**
     * Perform custom validation
     */
    private function performCustomValidation($key, $value, $validation) {
        switch ($validation) {
            case 'host':
                if (!filter_var($value, FILTER_VALIDATE_IP) && !filter_var($value, FILTER_VALIDATE_DOMAIN)) {
                    $this->addError($key, "Configuration '{$key}' must be a valid IP address or domain name");
                }
                break;
                
            case 'encryption_key':
                // Check if it's a valid encryption key (after decryption if encrypted)
                $decryptedKey = $this->configManager->get($key); // This will decrypt if needed
                if (strlen($decryptedKey) < 32) {
                    $this->addError($key, "Encryption key must be at least 32 characters long");
                }
                break;
        }
    }
    
    /**
     * Check conditional requirement
     */
    private function checkConditionalRequirement($condition) {
        if (strpos($condition, '=') === false) {
            return false;
        }
        
        list($conditionKey, $conditionValue) = explode('=', $condition, 2);
        $actualValue = $this->configManager->get($conditionKey);
        
        return $actualValue === $conditionValue;
    }
    
    /**
     * Validate business logic
     */
    private function validateBusinessLogic() {
        // Validate payment gateway configuration
        $defaultGateway = $this->configManager->get('payment.default_gateway');
        if ($defaultGateway === 'razorpay') {
            $keyId = $this->configManager->get('payment.gateways.razorpay.key_id');
            $keySecret = $this->configManager->get('payment.gateways.razorpay.key_secret');
            
            if (empty($keyId) || empty($keySecret)) {
                $this->addError('payment.razorpay', 'Razorpay key ID and secret are required when Razorpay is the default gateway');
            }
        }
        
        // Validate email configuration
        $emailDriver = $this->configManager->get('email.default');
        if ($emailDriver === 'smtp') {
            $smtpHost = $this->configManager->get('email.mailers.smtp.host');
            $smtpUsername = $this->configManager->get('email.mailers.smtp.username');
            
            if (empty($smtpHost) || empty($smtpUsername)) {
                $this->addError('email.smtp', 'SMTP host and username are required when SMTP is the default mailer');
            }
        }
        
        // Validate database configuration
        $dbHost = $this->configManager->get('database.connections.mysql.host');
        $dbName = $this->configManager->get('database.connections.mysql.database');
        $dbUser = $this->configManager->get('database.connections.mysql.username');
        
        if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
            $this->addError('database.mysql', 'Database host, name, and username are required');
        }
        
        // Validate URL consistency
        $appUrl = $this->configManager->get('app.url');
        $env = $this->configManager->get('app.env');
        
        if ($env === 'production' && strpos($appUrl, 'http://') === 0) {
            $this->addWarning('app.url', 'HTTPS should be used in production environment');
        }
        
        if ($env === 'development' && strpos($appUrl, 'https://') === 0 && strpos($appUrl, 'localhost') === false) {
            $this->addWarning('app.url', 'HTTP might be more appropriate for local development');
        }
    }
    
    /**
     * Validate environment-specific requirements
     */
    private function validateEnvironmentRequirements() {
        $env = $this->configManager->get('app.env');
        
        switch ($env) {
            case 'production':
                $this->validateProductionRequirements();
                break;
            case 'staging':
                $this->validateStagingRequirements();
                break;
            case 'development':
                $this->validateDevelopmentRequirements();
                break;
            case 'testing':
                $this->validateTestingRequirements();
                break;
        }
    }
    
    /**
     * Validate production environment requirements
     */
    private function validateProductionRequirements() {
        // Debug mode should be disabled
        if ($this->configManager->get('app.debug') === true) {
            $this->addError('app.debug', 'Debug mode must be disabled in production');
        }
        
        // HTTPS should be enforced
        $appUrl = $this->configManager->get('app.url');
        if (strpos($appUrl, 'http://') === 0) {
            $this->addError('app.url', 'HTTPS must be used in production');
        }
        
        // Strong JWT secret required
        $jwtSecret = env('JWT_SECRET');
        if (empty($jwtSecret) || strlen($jwtSecret) < 32) {
            $this->addError('jwt.secret', 'JWT secret must be at least 32 characters in production');
        }
        
        // Payment test mode should be disabled
        if ($this->configManager->get('payment.gateways.razorpay.test_mode') === true) {
            $this->addError('payment.test_mode', 'Payment test mode must be disabled in production');
        }
        
        // Email test mode should be disabled
        if ($this->configManager->get('email.disable_delivery') === true) {
            $this->addError('email.disable_delivery', 'Email delivery must be enabled in production');
        }
    }
    
    /**
     * Validate staging environment requirements
     */
    private function validateStagingRequirements() {
        // Payment should be in test mode
        if ($this->configManager->get('payment.gateways.razorpay.test_mode') !== true) {
            $this->addWarning('payment.test_mode', 'Payment test mode should be enabled in staging');
        }
        
        // Email catch-all should be configured
        $catchAllEmail = $this->configManager->get('email.catch_all_email');
        if (empty($catchAllEmail)) {
            $this->addWarning('email.catch_all', 'Email catch-all should be configured in staging');
        }
    }
    
    /**
     * Validate development environment requirements
     */
    private function validateDevelopmentRequirements() {
        // Debug mode should be enabled
        if ($this->configManager->get('app.debug') !== true) {
            $this->addWarning('app.debug', 'Debug mode should be enabled in development');
        }
        
        // Payment should be in test mode
        if ($this->configManager->get('payment.gateways.razorpay.test_mode') !== true) {
            $this->addWarning('payment.test_mode', 'Payment test mode should be enabled in development');
        }
    }
    
    /**
     * Validate testing environment requirements
     */
    private function validateTestingRequirements() {
        // All external services should be mocked
        if ($this->configManager->get('payment.mock_responses') !== true) {
            $this->addWarning('payment.mock', 'Payment responses should be mocked in testing');
        }
        
        if ($this->configManager->get('email.disable_delivery') !== true) {
            $this->addWarning('email.mock', 'Email delivery should be disabled in testing');
        }
    }
    
    /**
     * Validate security requirements
     */
    private function validateSecurityRequirements() {
        $env = $this->configManager->get('app.env');
        
        // Check encryption key strength
        $encryptionKey = $this->configManager->get('security.encryption.key');
        if (strlen($encryptionKey) < 32) {
            $this->addError('security.encryption.key', 'Encryption key must be at least 32 characters');
        }
        
        // Check bcrypt rounds
        $bcryptRounds = $this->configManager->get('security.hashing.bcrypt_rounds');
        if ($bcryptRounds < 10) {
            $this->addError('security.bcrypt_rounds', 'BCrypt rounds should be at least 10 for security');
        }
        
        if ($env === 'production' && $bcryptRounds < 12) {
            $this->addWarning('security.bcrypt_rounds', 'BCrypt rounds should be at least 12 in production');
        }
        
        // Check CORS configuration
        $corsOrigins = $this->configManager->get('security.cors.allowed_origins');
        if (in_array('*', $corsOrigins) && $env === 'production') {
            $this->addError('security.cors', 'CORS should not allow all origins in production');
        }
        
        // Check rate limiting
        if ($this->configManager->get('security.rate_limiting.enabled') !== true && $env === 'production') {
            $this->addWarning('security.rate_limiting', 'Rate limiting should be enabled in production');
        }
    }
    
    /**
     * Check if value is empty
     */
    private function isEmpty($value) {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }
    
    /**
     * Add validation error
     */
    private function addError($key, $message) {
        $this->errors[] = [
            'key' => $key,
            'message' => $message,
            'severity' => 'error'
        ];
    }
    
    /**
     * Add validation warning
     */
    private function addWarning($key, $message) {
        $this->warnings[] = [
            'key' => $key,
            'message' => $message,
            'severity' => 'warning'
        ];
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get validation warnings
     */
    public function getWarnings() {
        return $this->warnings;
    }
    
    /**
     * Get all validation issues
     */
    public function getAllIssues() {
        return array_merge($this->errors, $this->warnings);
    }
    
    /**
     * Check if validation passed
     */
    public function isValid() {
        return empty($this->errors);
    }
    
    /**
     * Get validation summary
     */
    public function getSummary() {
        return [
            'valid' => $this->isValid(),
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'total_issues' => count($this->errors) + count($this->warnings),
            'environment' => $this->configManager->get('app.env'),
            'validated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Generate validation report
     */
    public function generateReport() {
        $report = [
            'summary' => $this->getSummary(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'configuration' => [
                'environment' => $this->configManager->get('app.env'),
                'debug_mode' => $this->configManager->get('app.debug'),
                'database_host' => $this->configManager->get('database.connections.mysql.host'),
                'email_driver' => $this->configManager->get('email.default'),
                'payment_gateway' => $this->configManager->get('payment.default_gateway'),
                'cache_enabled' => $this->configManager->get('cache.enabled', false)
            ]
        ];
        
        return $report;
    }
}

// Global helper functions
if (!function_exists('validateConfig')) {
    function validateConfig() {
        $validator = new ConfigValidationService();
        return $validator->validateAll();
    }
}

if (!function_exists('getConfigValidationReport')) {
    function getConfigValidationReport() {
        $validator = new ConfigValidationService();
        $validator->validateAll();
        return $validator->generateReport();
    }
}