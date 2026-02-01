<?php
/**
 * Error Handler Utility
 * 
 * Provides comprehensive error handling with consistent responses, error classification,
 * user-friendly messages, and proper logging. Implements global exception handling
 * for the PHP backend system.
 * 
 * Requirements: 13.1, 13.3, 16.2
 */

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Response.php';

class ErrorHandler {
    // Error types
    const TYPE_VALIDATION = 'validation';
    const TYPE_AUTHENTICATION = 'authentication';
    const TYPE_AUTHORIZATION = 'authorization';
    const TYPE_NOT_FOUND = 'not_found';
    const TYPE_CONFLICT = 'conflict';
    const TYPE_RATE_LIMIT = 'rate_limit';
    const TYPE_SERVER = 'server';
    const TYPE_DATABASE = 'database';
    const TYPE_EXTERNAL = 'external';
    const TYPE_BUSINESS = 'business';
    
    // Error severity levels
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';
    
    private static $errorMappings = [];
    private static $isInitialized = false;
    
    /**
     * Initialize error handler
     */
    public static function initialize() {
        if (self::$isInitialized) {
            return;
        }
        
        // Set error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // Initialize error mappings
        self::initializeErrorMappings();
        
        self::$isInitialized = true;
        
        Logger::info('Error handler initialized');
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $severity Error severity
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number where error occurred
     * @return bool True to prevent default PHP error handler
     */
    public static function handleError($severity, $message, $file, $line) {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorTypeFromSeverity($severity);
        $context = [
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'error_type' => $errorType
        ];
        
        // Log based on severity
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            Logger::error("PHP Error: {$message}", $context);
        } elseif ($severity & (E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING)) {
            Logger::warning("PHP Warning: {$message}", $context);
        } else {
            Logger::notice("PHP Notice: {$message}", $context);
        }
        
        // In development, show detailed errors
        if (isDevelopment()) {
            return false; // Let PHP handle it
        }
        
        // In production, handle gracefully
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            self::sendErrorResponse(500, 'Internal server error occurred');
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     * 
     * @param Throwable $exception The uncaught exception
     */
    public static function handleException($exception) {
        try {
            $errorInfo = self::classifyException($exception);
            
            // Log the exception
            Logger::logException($exception, [
                'error_type' => $errorInfo['type'],
                'severity' => $errorInfo['severity'],
                'http_code' => $errorInfo['http_code']
            ]);
            
            // Send appropriate response
            self::sendErrorResponse(
                $errorInfo['http_code'],
                $errorInfo['user_message'],
                $errorInfo['type'],
                isDevelopment() ? $errorInfo['debug_info'] : null
            );
            
        } catch (Exception $e) {
            // Fallback error handling
            Logger::critical('Error in exception handler', [
                'original_exception' => $exception->getMessage(),
                'handler_exception' => $e->getMessage()
            ]);
            
            self::sendErrorResponse(500, 'A critical error occurred');
        }
    }
    
    /**
     * Handle fatal errors during shutdown
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $context = [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ];
            
            Logger::critical('Fatal error during shutdown', $context);
            
            // Only send response if headers haven't been sent
            if (!headers_sent()) {
                self::sendErrorResponse(500, 'A fatal error occurred');
            }
        }
    }
    
    /**
     * Handle application exceptions with context
     * 
     * @param Exception $exception The exception to handle
     * @param array $context Additional context
     */
    public static function handleApplicationException($exception, $context = []) {
        $errorInfo = self::classifyException($exception);
        
        // Add context to logging
        $logContext = array_merge($context, [
            'error_type' => $errorInfo['type'],
            'severity' => $errorInfo['severity'],
            'http_code' => $errorInfo['http_code']
        ]);
        
        Logger::logException($exception, $logContext);
        
        // Send response
        self::sendErrorResponse(
            $errorInfo['http_code'],
            $errorInfo['user_message'],
            $errorInfo['type'],
            isDevelopment() ? $errorInfo['debug_info'] : null
        );
    }
    
    /**
     * Handle validation errors
     * 
     * @param array $errors Validation errors
     * @param string $message Optional custom message
     */
    public static function handleValidationErrors($errors, $message = null) {
        $message = $message ?: 'Validation failed';
        
        Logger::info('Validation errors occurred', [
            'errors' => $errors,
            'error_count' => count($errors)
        ]);
        
        Response::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'error_type' => self::TYPE_VALIDATION
        ], 400);
    }
    
    /**
     * Handle business logic errors
     * 
     * @param string $message Error message
     * @param string $code Error code
     * @param array $context Additional context
     */
    public static function handleBusinessError($message, $code = null, $context = []) {
        Logger::warning('Business logic error', array_merge($context, [
            'message' => $message,
            'code' => $code
        ]));
        
        Response::json([
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'error_type' => self::TYPE_BUSINESS
        ], 400);
    }
    
    /**
     * Handle database errors
     * 
     * @param Exception $exception Database exception
     * @param array $context Query context
     */
    public static function handleDatabaseError($exception, $context = []) {
        Logger::error('Database error occurred', array_merge($context, [
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode()
        ]));
        
        // Don't expose database details in production
        $message = isDevelopment() 
            ? 'Database error: ' . $exception->getMessage()
            : 'A database error occurred';
        
        self::sendErrorResponse(500, $message, self::TYPE_DATABASE);
    }
    
    /**
     * Handle external service errors
     * 
     * @param string $service Service name
     * @param Exception $exception Service exception
     * @param array $context Request context
     */
    public static function handleExternalServiceError($service, $exception, $context = []) {
        Logger::error("External service error: {$service}", array_merge($context, [
            'service' => $service,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode()
        ]));
        
        $message = "External service temporarily unavailable: {$service}";
        self::sendErrorResponse(503, $message, self::TYPE_EXTERNAL);
    }
    
    /**
     * Classify exception and determine response
     * 
     * @param Throwable $exception The exception to classify
     * @return array Classification information
     */
    private static function classifyException($exception) {
        $message = $exception->getMessage();
        $code = $exception->getCode();
        $class = get_class($exception);
        
        // Check custom error mappings first
        foreach (self::$errorMappings as $pattern => $mapping) {
            if (preg_match($pattern, $message) || $class === $pattern) {
                return array_merge($mapping, [
                    'debug_info' => [
                        'exception_class' => $class,
                        'message' => $message,
                        'code' => $code,
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine()
                    ]
                ]);
            }
        }
        
        // Default classification based on exception type
        if ($exception instanceof InvalidArgumentException) {
            return [
                'type' => self::TYPE_VALIDATION,
                'severity' => self::SEVERITY_LOW,
                'http_code' => 400,
                'user_message' => 'Invalid input provided',
                'debug_info' => ['original_message' => $message]
            ];
        }
        
        if ($exception instanceof UnauthorizedHttpException || strpos($message, 'Unauthorized') !== false) {
            return [
                'type' => self::TYPE_AUTHENTICATION,
                'severity' => self::SEVERITY_MEDIUM,
                'http_code' => 401,
                'user_message' => 'Authentication required',
                'debug_info' => ['original_message' => $message]
            ];
        }
        
        if ($exception instanceof ForbiddenHttpException || strpos($message, 'Forbidden') !== false) {
            return [
                'type' => self::TYPE_AUTHORIZATION,
                'severity' => self::SEVERITY_MEDIUM,
                'http_code' => 403,
                'user_message' => 'Access denied',
                'debug_info' => ['original_message' => $message]
            ];
        }
        
        if ($exception instanceof NotFoundHttpException || strpos($message, 'not found') !== false) {
            return [
                'type' => self::TYPE_NOT_FOUND,
                'severity' => self::SEVERITY_LOW,
                'http_code' => 404,
                'user_message' => 'Resource not found',
                'debug_info' => ['original_message' => $message]
            ];
        }
        
        if ($exception instanceof PDOException || strpos($class, 'Database') !== false) {
            return [
                'type' => self::TYPE_DATABASE,
                'severity' => self::SEVERITY_HIGH,
                'http_code' => 500,
                'user_message' => 'Database operation failed',
                'debug_info' => ['original_message' => $message]
            ];
        }
        
        // Default server error
        return [
            'type' => self::TYPE_SERVER,
            'severity' => self::SEVERITY_HIGH,
            'http_code' => 500,
            'user_message' => 'An internal server error occurred',
            'debug_info' => [
                'exception_class' => $class,
                'message' => $message,
                'code' => $code
            ]
        ];
    }
    
    /**
     * Send error response
     * 
     * @param int $httpCode HTTP status code
     * @param string $message Error message
     * @param string $type Error type
     * @param array $debugInfo Debug information (development only)
     */
    private static function sendErrorResponse($httpCode, $message, $type = null, $debugInfo = null) {
        // Prevent multiple responses
        if (headers_sent()) {
            return;
        }
        
        $response = [
            'success' => false,
            'message' => $message,
            'error_type' => $type,
            'timestamp' => date('c'),
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_')
        ];
        
        // Add debug info in development
        if ($debugInfo && isDevelopment()) {
            $response['debug'] = $debugInfo;
        }
        
        Response::json($response, $httpCode);
    }
    
    /**
     * Get error type from PHP error severity
     * 
     * @param int $severity PHP error severity
     * @return string Error type
     */
    private static function getErrorTypeFromSeverity($severity) {
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            return 'fatal';
        } elseif ($severity & (E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING)) {
            return 'warning';
        } elseif ($severity & (E_NOTICE | E_USER_NOTICE)) {
            return 'notice';
        } elseif ($severity & (E_STRICT | E_DEPRECATED | E_USER_DEPRECATED)) {
            return 'deprecated';
        }
        
        return 'unknown';
    }
    
    /**
     * Initialize error mappings
     */
    private static function initializeErrorMappings() {
        self::$errorMappings = [
            // Database errors
            '/Connection refused/' => [
                'type' => self::TYPE_DATABASE,
                'severity' => self::SEVERITY_CRITICAL,
                'http_code' => 503,
                'user_message' => 'Database service unavailable'
            ],
            '/Duplicate entry/' => [
                'type' => self::TYPE_CONFLICT,
                'severity' => self::SEVERITY_LOW,
                'http_code' => 409,
                'user_message' => 'Resource already exists'
            ],
            
            // Authentication errors
            '/Invalid token/' => [
                'type' => self::TYPE_AUTHENTICATION,
                'severity' => self::SEVERITY_MEDIUM,
                'http_code' => 401,
                'user_message' => 'Invalid or expired authentication token'
            ],
            '/Token expired/' => [
                'type' => self::TYPE_AUTHENTICATION,
                'severity' => self::SEVERITY_MEDIUM,
                'http_code' => 401,
                'user_message' => 'Authentication token has expired'
            ],
            
            // Rate limiting
            '/Rate limit exceeded/' => [
                'type' => self::TYPE_RATE_LIMIT,
                'severity' => self::SEVERITY_MEDIUM,
                'http_code' => 429,
                'user_message' => 'Too many requests. Please try again later.'
            ],
            
            // File upload errors
            '/File too large/' => [
                'type' => self::TYPE_VALIDATION,
                'severity' => self::SEVERITY_LOW,
                'http_code' => 413,
                'user_message' => 'File size exceeds maximum allowed limit'
            ],
            '/Invalid file type/' => [
                'type' => self::TYPE_VALIDATION,
                'severity' => self::SEVERITY_LOW,
                'http_code' => 400,
                'user_message' => 'File type not supported'
            ],
            
            // Payment errors
            '/Payment failed/' => [
                'type' => self::TYPE_EXTERNAL,
                'severity' => self::SEVERITY_MEDIUM,
                'http_code' => 402,
                'user_message' => 'Payment processing failed'
            ],
            '/Insufficient funds/' => [
                'type' => self::TYPE_BUSINESS,
                'severity' => self::SEVERITY_LOW,
                'http_code' => 402,
                'user_message' => 'Insufficient funds for this transaction'
            ]
        ];
    }
    
    /**
     * Add custom error mapping
     * 
     * @param string $pattern Regex pattern or exception class
     * @param array $mapping Error mapping configuration
     */
    public static function addErrorMapping($pattern, $mapping) {
        self::$errorMappings[$pattern] = $mapping;
    }
    
    /**
     * Get error statistics
     * 
     * @return array Error statistics
     */
    public static function getErrorStatistics() {
        // This would typically read from logs or a database
        // For now, return basic info
        return [
            'handlers_registered' => self::$isInitialized,
            'error_mappings_count' => count(self::$errorMappings),
            'php_error_reporting' => error_reporting(),
            'display_errors' => ini_get('display_errors'),
            'log_errors' => ini_get('log_errors')
        ];
    }
    
    /**
     * Test error handling
     * 
     * @param string $type Type of error to test
     */
    public static function testErrorHandling($type = 'exception') {
        switch ($type) {
            case 'exception':
                throw new Exception('Test exception for error handling');
                
            case 'error':
                trigger_error('Test PHP error', E_USER_ERROR);
                break;
                
            case 'warning':
                trigger_error('Test PHP warning', E_USER_WARNING);
                break;
                
            case 'notice':
                trigger_error('Test PHP notice', E_USER_NOTICE);
                break;
                
            case 'fatal':
                // This will cause a fatal error
                call_undefined_function();
                break;
                
            default:
                throw new InvalidArgumentException('Invalid test type');
        }
    }
}

// Custom exception classes
class BusinessLogicException extends Exception {
    private $errorCode;
    
    public function __construct($message, $errorCode = null, $code = 400) {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
    }
    
    public function getErrorCode() {
        return $this->errorCode;
    }
}

class UnauthorizedHttpException extends Exception {
    public function __construct($message = 'Unauthorized', $code = 401) {
        parent::__construct($message, $code);
    }
}

class ForbiddenHttpException extends Exception {
    public function __construct($message = 'Forbidden', $code = 403) {
        parent::__construct($message, $code);
    }
}

class NotFoundHttpException extends Exception {
    public function __construct($message = 'Not Found', $code = 404) {
        parent::__construct($message, $code);
    }
}

class RateLimitException extends Exception {
    public function __construct($message = 'Rate limit exceeded', $code = 429) {
        parent::__construct($message, $code);
    }
}

// Global helper functions
function handleError($exception, $context = []) {
    ErrorHandler::handleApplicationException($exception, $context);
}

function handleValidationErrors($errors, $message = null) {
    ErrorHandler::handleValidationErrors($errors, $message);
}

function handleBusinessError($message, $code = null, $context = []) {
    ErrorHandler::handleBusinessError($message, $code, $context);
}