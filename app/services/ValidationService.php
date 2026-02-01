<?php
/**
 * Validation Service
 * 
 * Comprehensive validation service that provides input sanitization, data validation,
 * and security validation helpers for the PHP backend. Implements server-side
 * validation for all form submissions and API requests.
 * 
 * Requirements: 10.1, 16.1
 */

require_once __DIR__ . '/../utils/Logger.php';

class ValidationService {
    private $errors = [];
    private $rules = [];
    private $customMessages = [];
    
    // Common validation patterns
    const PATTERN_EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    const PATTERN_PHONE = '/^[\+]?[1-9][\d]{0,15}$/';
    const PATTERN_PHONE_INDIAN = '/^(\+91|91|0)?[6789]\d{9}$/';
    const PATTERN_POSTAL_CODE = '/^[1-9][0-9]{5}$/';
    const PATTERN_PASSWORD = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    const PATTERN_USERNAME = '/^[a-zA-Z0-9_]{3,20}$/';
    const PATTERN_SLUG = '/^[a-z0-9-]+$/';
    const PATTERN_ORDER_NUMBER = '/^RC\d{8}\d{4}$/';
    const PATTERN_SKU = '/^[A-Z0-9-]{3,20}$/';
    
    // XSS and injection patterns
    const XSS_PATTERNS = [
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload\s*=/i',
        '/onerror\s*=/i',
        '/onclick\s*=/i',
        '/onmouseover\s*=/i',
        '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
        '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi'
    ];
    
    // SQL injection patterns
    const SQL_INJECTION_PATTERNS = [
        '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
        '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
        '/(\b(OR|AND)\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?)/i',
        '/(\-\-|\#|\/\*|\*\/)/i',
        '/(;|\||&)/i'
    ];
    
    public function __construct() {
        $this->initializeDefaultMessages();
    }
    
    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @return bool True if valid
     */
    public function validate($data, $rules, $messages = []) {
        $this->errors = [];
        $this->rules = $rules;
        $this->customMessages = array_merge($this->customMessages, $messages);
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     * 
     * @return array Validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error for a field
     * 
     * @param string $field Field name
     * @return string|null First error message
     */
    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }
    
    /**
     * Check if validation has errors
     * 
     * @return bool True if has errors
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Sanitize input data
     * 
     * @param mixed $data Data to sanitize
     * @param string $type Sanitization type
     * @return mixed Sanitized data
     */
    public function sanitize($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'string':
                return $this->sanitizeString($data);
                
            case 'html':
                return $this->sanitizeHtml($data);
                
            case 'email':
                return $this->sanitizeEmail($data);
                
            case 'phone':
                return $this->sanitizePhone($data);
                
            case 'number':
                return $this->sanitizeNumber($data);
                
            case 'float':
                return $this->sanitizeFloat($data);
                
            case 'boolean':
                return $this->sanitizeBoolean($data);
                
            case 'url':
                return $this->sanitizeUrl($data);
                
            case 'filename':
                return $this->sanitizeFilename($data);
                
            default:
                return $this->sanitizeString($data);
        }
    }
    
    /**
     * Validate user registration data
     * 
     * @param array $data User data
     * @return bool True if valid
     */
    public function validateUserRegistration($data) {
        $rules = [
            'first_name' => 'required|string|min:2|max:50|alpha_spaces',
            'last_name' => 'required|string|min:2|max:50|alpha_spaces',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|password|min:8|max:128',
            'phone' => 'nullable|phone_indian',
            'terms_accepted' => 'required|boolean|accepted'
        ];
        
        return $this->validate($data, $rules);
    }
    
    /**
     * Validate user login data
     * 
     * @param array $data Login data
     * @return bool True if valid
     */
    public function validateUserLogin($data) {
        $rules = [
            'email' => 'required|email',
            'password' => 'required|string|min:1'
        ];
        
        return $this->validate($data, $rules);
    }
    
    /**
     * Validate product data
     * 
     * @param array $data Product data
     * @return bool True if valid
     */
    public function validateProduct($data) {
        $rules = [
            'name' => 'required|string|min:3|max:200',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0.01|max:999999.99',
            'stock_quantity' => 'required|integer|min:0|max:999999',
            'category_id' => 'nullable|integer|exists:categories,id',
            'brand' => 'nullable|string|max:100',
            'sku' => 'nullable|string|sku|unique:products,sku',
            'weight' => 'nullable|numeric|min:0|max:999999',
            'dimensions' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean'
        ];
        
        return $this->validate($data, $rules);
    }
    
    /**
     * Validate order data
     * 
     * @param array $data Order data
     * @return bool True if valid
     */
    public function validateOrder($data) {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
            'payment_method' => 'required|in:cod,razorpay,online',
            'shipping_address_id' => 'required|integer|exists:addresses,id',
            'billing_address_id' => 'nullable|integer|exists:addresses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'items.*.unit_price' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
            'currency' => 'nullable|in:INR,USD,EUR'
        ];
        
        return $this->validate($data, $rules);
    }
    
    /**
     * Validate address data
     * 
     * @param array $data Address data
     * @return bool True if valid
     */
    public function validateAddress($data) {
        $rules = [
            'address_line1' => 'required|string|min:5|max:200',
            'address_line2' => 'nullable|string|max:200',
            'city' => 'required|string|min:2|max:100|alpha_spaces',
            'state' => 'required|string|min:2|max:100|alpha_spaces',
            'postal_code' => 'required|postal_code',
            'country' => 'required|string|min:2|max:100|alpha_spaces',
            'phone' => 'nullable|phone_indian',
            'is_default' => 'nullable|boolean'
        ];
        
        return $this->validate($data, $rules);
    }
    
    /**
     * Validate payment data
     * 
     * @param array $data Payment data
     * @return bool True if valid
     */
    public function validatePayment($data) {
        $rules = [
            'order_id' => 'required|integer|exists:orders,id',
            'payment_method' => 'required|in:cod,razorpay,online',
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'currency' => 'nullable|in:INR,USD,EUR'
        ];
        
        // Add Razorpay-specific validation
        if (($data['payment_method'] ?? '') === 'razorpay') {
            $rules['razorpay_order_id'] = 'required|string';
            $rules['razorpay_payment_id'] = 'required|string';
            $rules['razorpay_signature'] = 'required|string';
        }
        
        return $this->validate($data, $rules);
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file File data from $_FILES
     * @param array $options Upload options
     * @return bool True if valid
     */
    public function validateFileUpload($file, $options = []) {
        $maxSize = $options['max_size'] ?? 5242880; // 5MB default
        $allowedTypes = $options['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExtensions = $options['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->addError('file', 'No file was uploaded');
            return false;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->addError('file', $this->getUploadErrorMessage($file['error']));
            return false;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $this->addError('file', 'File size exceeds maximum allowed size of ' . $this->formatBytes($maxSize));
            return false;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $this->addError('file', 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
            return false;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $this->addError('file', 'File extension not allowed. Allowed extensions: ' . implode(', ', $allowedExtensions));
            return false;
        }
        
        // Additional security checks for images
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $this->addError('file', 'Invalid image file');
                return false;
            }
            
            // Check image dimensions if specified
            if (isset($options['max_width']) && $imageInfo[0] > $options['max_width']) {
                $this->addError('file', 'Image width exceeds maximum allowed width of ' . $options['max_width'] . 'px');
                return false;
            }
            
            if (isset($options['max_height']) && $imageInfo[1] > $options['max_height']) {
                $this->addError('file', 'Image height exceeds maximum allowed height of ' . $options['max_height'] . 'px');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check for XSS attempts
     * 
     * @param string $input Input string
     * @return bool True if XSS detected
     */
    public function detectXSS($input) {
        if (!is_string($input)) {
            return false;
        }
        
        foreach (self::XSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                Logger::security('XSS attempt detected', [
                    'input' => substr($input, 0, 200),
                    'pattern' => $pattern,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for SQL injection attempts
     * 
     * @param string $input Input string
     * @return bool True if SQL injection detected
     */
    public function detectSQLInjection($input) {
        if (!is_string($input)) {
            return false;
        }
        
        foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                Logger::security('SQL injection attempt detected', [
                    'input' => substr($input, 0, 200),
                    'pattern' => $pattern,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate field against rules
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rules Validation rules
     */
    private function validateField($field, $value, $rules) {
        $ruleList = explode('|', $rules);
        
        foreach ($ruleList as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }
    
    /**
     * Apply validation rule
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     */
    private function applyRule($field, $value, $rule) {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, $this->getMessage($field, 'required'));
                }
                break;
                
            case 'nullable':
                // Skip other validations if value is null/empty
                if (empty($value)) {
                    return;
                }
                break;
                
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, $this->getMessage($field, 'string'));
                }
                break;
                
            case 'integer':
                if (!filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, $this->getMessage($field, 'integer'));
                }
                break;
                
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, $this->getMessage($field, 'numeric'));
                }
                break;
                
            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    $this->addError($field, $this->getMessage($field, 'boolean'));
                }
                break;
                
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $this->getMessage($field, 'email'));
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, $this->getMessage($field, 'url'));
                }
                break;
                
            case 'min':
                if (is_string($value) && strlen($value) < (int)$parameter) {
                    $this->addError($field, $this->getMessage($field, 'min', ['min' => $parameter]));
                } elseif (is_numeric($value) && $value < (float)$parameter) {
                    $this->addError($field, $this->getMessage($field, 'min', ['min' => $parameter]));
                }
                break;
                
            case 'max':
                if (is_string($value) && strlen($value) > (int)$parameter) {
                    $this->addError($field, $this->getMessage($field, 'max', ['max' => $parameter]));
                } elseif (is_numeric($value) && $value > (float)$parameter) {
                    $this->addError($field, $this->getMessage($field, 'max', ['max' => $parameter]));
                }
                break;
                
            case 'in':
                $options = explode(',', $parameter);
                if (!in_array($value, $options)) {
                    $this->addError($field, $this->getMessage($field, 'in', ['values' => implode(', ', $options)]));
                }
                break;
                
            case 'alpha':
                if (!ctype_alpha($value)) {
                    $this->addError($field, $this->getMessage($field, 'alpha'));
                }
                break;
                
            case 'alpha_spaces':
                if (!preg_match('/^[a-zA-Z\s]+$/', $value)) {
                    $this->addError($field, $this->getMessage($field, 'alpha_spaces'));
                }
                break;
                
            case 'alphanumeric':
                if (!ctype_alnum($value)) {
                    $this->addError($field, $this->getMessage($field, 'alphanumeric'));
                }
                break;
                
            case 'phone_indian':
                if (!preg_match(self::PATTERN_PHONE_INDIAN, $value)) {
                    $this->addError($field, $this->getMessage($field, 'phone_indian'));
                }
                break;
                
            case 'postal_code':
                if (!preg_match(self::PATTERN_POSTAL_CODE, $value)) {
                    $this->addError($field, $this->getMessage($field, 'postal_code'));
                }
                break;
                
            case 'password':
                if (!preg_match(self::PATTERN_PASSWORD, $value)) {
                    $this->addError($field, $this->getMessage($field, 'password'));
                }
                break;
                
            case 'sku':
                if (!preg_match(self::PATTERN_SKU, $value)) {
                    $this->addError($field, $this->getMessage($field, 'sku'));
                }
                break;
                
            case 'accepted':
                if (!in_array($value, [true, 1, '1', 'yes', 'on'])) {
                    $this->addError($field, $this->getMessage($field, 'accepted'));
                }
                break;
                
            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, $this->getMessage($field, 'array'));
                }
                break;
                
            case 'exists':
                // Format: exists:table,column
                $parts = explode(',', $parameter);
                $table = $parts[0];
                $column = $parts[1] ?? 'id';
                
                if (!$this->checkExists($table, $column, $value)) {
                    $this->addError($field, $this->getMessage($field, 'exists'));
                }
                break;
                
            case 'unique':
                // Format: unique:table,column,except_id
                $parts = explode(',', $parameter);
                $table = $parts[0];
                $column = $parts[1] ?? $field;
                $exceptId = $parts[2] ?? null;
                
                if (!$this->checkUnique($table, $column, $value, $exceptId)) {
                    $this->addError($field, $this->getMessage($field, 'unique'));
                }
                break;
        }
        
        // Security checks
        if (is_string($value)) {
            if ($this->detectXSS($value)) {
                $this->addError($field, 'Input contains potentially dangerous content');
            }
            
            if ($this->detectSQLInjection($value)) {
                $this->addError($field, 'Input contains potentially dangerous SQL content');
            }
        }
    }
    
    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get validation message
     * 
     * @param string $field Field name
     * @param string $rule Rule name
     * @param array $parameters Message parameters
     * @return string Error message
     */
    private function getMessage($field, $rule, $parameters = []) {
        $key = "{$field}.{$rule}";
        
        if (isset($this->customMessages[$key])) {
            return $this->customMessages[$key];
        }
        
        if (isset($this->customMessages[$rule])) {
            $message = $this->customMessages[$rule];
        } else {
            $message = $this->getDefaultMessage($rule);
        }
        
        // Replace placeholders
        $message = str_replace(':field', ucfirst(str_replace('_', ' ', $field)), $message);
        
        foreach ($parameters as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Get default error message
     * 
     * @param string $rule Rule name
     * @return string Default message
     */
    private function getDefaultMessage($rule) {
        $messages = [
            'required' => ':field is required',
            'string' => ':field must be a string',
            'integer' => ':field must be an integer',
            'numeric' => ':field must be a number',
            'boolean' => ':field must be true or false',
            'email' => ':field must be a valid email address',
            'url' => ':field must be a valid URL',
            'min' => ':field must be at least :min characters',
            'max' => ':field must not exceed :max characters',
            'in' => ':field must be one of: :values',
            'alpha' => ':field must contain only letters',
            'alpha_spaces' => ':field must contain only letters and spaces',
            'alphanumeric' => ':field must contain only letters and numbers',
            'phone_indian' => ':field must be a valid Indian phone number',
            'postal_code' => ':field must be a valid postal code',
            'password' => ':field must contain at least 8 characters with uppercase, lowercase, number and special character',
            'sku' => ':field must be a valid SKU format',
            'accepted' => ':field must be accepted',
            'array' => ':field must be an array',
            'exists' => ':field does not exist',
            'unique' => ':field already exists'
        ];
        
        return $messages[$rule] ?? ':field is invalid';
    }
    
    /**
     * Initialize default error messages
     */
    private function initializeDefaultMessages() {
        $this->customMessages = [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'password.required' => 'Password is required',
            'password.password' => 'Password must be at least 8 characters with uppercase, lowercase, number and special character',
            'terms_accepted.accepted' => 'You must accept the terms and conditions'
        ];
    }
    
    /**
     * Sanitize string input
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    private function sanitizeString($input) {
        if (!is_string($input)) {
            return $input;
        }
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Sanitize HTML input
     * 
     * @param string $input HTML input
     * @return string Sanitized HTML
     */
    private function sanitizeHtml($input) {
        if (!is_string($input)) {
            return $input;
        }
        
        // Remove dangerous tags and attributes
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6>';
        $input = strip_tags($input, $allowedTags);
        
        // Remove dangerous attributes
        $input = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $input);
        $input = preg_replace('/\s*javascript\s*:/i', '', $input);
        
        return $input;
    }
    
    /**
     * Sanitize email input
     * 
     * @param string $input Email input
     * @return string Sanitized email
     */
    private function sanitizeEmail($input) {
        return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Sanitize phone input
     * 
     * @param string $input Phone input
     * @return string Sanitized phone
     */
    private function sanitizePhone($input) {
        return preg_replace('/[^0-9+\-\s\(\)]/', '', $input);
    }
    
    /**
     * Sanitize number input
     * 
     * @param mixed $input Number input
     * @return int Sanitized number
     */
    private function sanitizeNumber($input) {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize float input
     * 
     * @param mixed $input Float input
     * @return float Sanitized float
     */
    private function sanitizeFloat($input) {
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    /**
     * Sanitize boolean input
     * 
     * @param mixed $input Boolean input
     * @return bool Sanitized boolean
     */
    private function sanitizeBoolean($input) {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Sanitize URL input
     * 
     * @param string $input URL input
     * @return string Sanitized URL
     */
    private function sanitizeUrl($input) {
        return filter_var(trim($input), FILTER_SANITIZE_URL);
    }
    
    /**
     * Sanitize filename input
     * 
     * @param string $input Filename input
     * @return string Sanitized filename
     */
    private function sanitizeFilename($input) {
        // Remove path traversal attempts
        $input = basename($input);
        
        // Remove dangerous characters
        $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
        
        // Limit length
        $input = substr($input, 0, 255);
        
        return $input;
    }
    
    /**
     * Check if value exists in database
     * 
     * @param string $table Table name
     * @param string $column Column name
     * @param mixed $value Value to check
     * @return bool True if exists
     */
    private function checkExists($table, $column, $value) {
        try {
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
            $count = $db->fetchColumn($sql, [$value]);
            return $count > 0;
        } catch (Exception $e) {
            Logger::error('Database exists check failed', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Check if value is unique in database
     * 
     * @param string $table Table name
     * @param string $column Column name
     * @param mixed $value Value to check
     * @param mixed $exceptId ID to exclude from check
     * @return bool True if unique
     */
    private function checkUnique($table, $column, $value, $exceptId = null) {
        try {
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
            $params = [$value];
            
            if ($exceptId !== null) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }
            
            $count = $db->fetchColumn($sql, $params);
            return $count == 0;
        } catch (Exception $e) {
            Logger::error('Database unique check failed', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get upload error message
     * 
     * @param int $errorCode Upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $messages[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Bytes
     * @return string Formatted size
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Global helper functions
function getValidationService() {
    static $service = null;
    if ($service === null) {
        $service = new ValidationService();
    }
    return $service;
}

function validateData($data, $rules, $messages = []) {
    return getValidationService()->validate($data, $rules, $messages);
}