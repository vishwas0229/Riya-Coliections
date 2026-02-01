<?php
/**
 * Logger Utility Class
 * 
 * This utility provides comprehensive logging functionality for the PHP backend,
 * supporting multiple log levels, structured logging, and log rotation.
 * 
 * Requirements: 13.2, 13.5
 */

/**
 * Logger Class
 */
class Logger {
    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;
    
    private static $logLevels = [
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRITICAL => 'CRITICAL',
        self::ERROR => 'ERROR',
        self::WARNING => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG'
    ];
    
    private static $config = null;
    
    /**
     * Get logging configuration
     */
    private static function getConfig() {
        if (self::$config === null) {
            self::$config = [
                'level' => self::getLevelFromString(env('LOG_LEVEL', 'info')),
                'file' => env('LOG_FILE', 'logs/app.log'),
                'error_file' => 'logs/error.log',
                'security_file' => 'logs/security.log',
                'max_files' => (int) env('LOG_MAX_FILES', 10),
                'max_size' => self::parseSize(env('LOG_MAX_SIZE', '10MB')),
                'daily_rotation' => env('LOG_DAILY_ROTATION', 'true') === 'true',
                'format' => env('LOG_FORMAT', 'json'), // json or text
                'include_trace' => isDevelopment()
            ];
        }
        
        return self::$config;
    }
    
    /**
     * Convert log level string to integer
     */
    private static function getLevelFromString($level) {
        $levels = [
            'emergency' => self::EMERGENCY,
            'alert' => self::ALERT,
            'critical' => self::CRITICAL,
            'error' => self::ERROR,
            'warning' => self::WARNING,
            'notice' => self::NOTICE,
            'info' => self::INFO,
            'debug' => self::DEBUG
        ];
        
        return $levels[strtolower($level)] ?? self::INFO;
    }
    
    /**
     * Parse size string to bytes
     */
    private static function parseSize($size) {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824];
        
        if (preg_match('/^(\d+)\s*([KMGT]?B)$/i', $size, $matches)) {
            $value = (int) $matches[1];
            $unit = strtoupper($matches[2]);
            return $value * ($units[$unit] ?? 1);
        }
        
        return (int) $size;
    }
    
    /**
     * Log emergency message
     */
    public static function emergency($message, $context = []) {
        self::log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Log alert message
     */
    public static function alert($message, $context = []) {
        self::log(self::ALERT, $message, $context);
    }
    
    /**
     * Log critical message
     */
    public static function critical($message, $context = []) {
        self::log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Log notice message
     */
    public static function notice($message, $context = []) {
        self::log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::log(self::INFO, $message, $context);
    }
    
    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log security event
     */
    public static function security($message, $context = []) {
        $config = self::getConfig();
        $context['security_event'] = true;
        $context['ip_address'] = self::getClientIP();
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        self::writeLog(self::WARNING, $message, $context, $config['security_file']);
    }
    
    /**
     * Main logging method
     */
    public static function log($level, $message, $context = []) {
        $config = self::getConfig();
        
        // Check if log level is enabled
        if ($level > $config['level']) {
            return;
        }
        
        // Determine log file
        $logFile = $config['file'];
        if ($level <= self::ERROR) {
            $logFile = $config['error_file'];
        }
        
        self::writeLog($level, $message, $context, $logFile);
    }
    
    /**
     * Write log entry to file
     */
    private static function writeLog($level, $message, $context, $logFile) {
        $config = self::getConfig();
        
        // Create log directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Handle daily rotation
        if ($config['daily_rotation']) {
            $logFile = self::getDailyLogFile($logFile);
        }
        
        // Check file size and rotate if necessary
        if (file_exists($logFile) && filesize($logFile) > $config['max_size']) {
            self::rotateLogFile($logFile);
        }
        
        // Prepare log entry
        $logEntry = self::formatLogEntry($level, $message, $context);
        
        // Write to file
        file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Also write to PHP error log for critical errors
        if ($level <= self::ERROR) {
            error_log($message);
        }
    }
    
    /**
     * Format log entry
     */
    private static function formatLogEntry($level, $message, $context) {
        $config = self::getConfig();
        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::$logLevels[$level];
        
        // Add request context
        $requestContext = [
            'request_id' => self::getRequestId(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'ip' => self::getClientIP(),
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];
        
        $context = array_merge($requestContext, $context);
        
        // Add stack trace for errors
        if ($level <= self::ERROR && $config['include_trace']) {
            $context['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        }
        
        if ($config['format'] === 'json') {
            return json_encode([
                'timestamp' => $timestamp,
                'level' => $levelName,
                'message' => $message,
                'context' => $context
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
            return "[{$timestamp}] {$levelName}: {$message}{$contextStr}";
        }
    }
    
    /**
     * Get daily log file name
     */
    private static function getDailyLogFile($logFile) {
        $pathInfo = pathinfo($logFile);
        $date = date('Y-m-d');
        
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-' . $date . '.' . $pathInfo['extension'];
    }
    
    /**
     * Rotate log file
     */
    private static function rotateLogFile($logFile) {
        $config = self::getConfig();
        $pathInfo = pathinfo($logFile);
        
        // Move existing numbered files
        for ($i = $config['max_files'] - 1; $i >= 1; $i--) {
            $oldFile = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $i . '.' . $pathInfo['extension'];
            $newFile = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . ($i + 1) . '.' . $pathInfo['extension'];
            
            if (file_exists($oldFile)) {
                if ($i + 1 > $config['max_files']) {
                    unlink($oldFile); // Delete oldest file
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current file to .1
        $rotatedFile = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.1.' . $pathInfo['extension'];
        rename($logFile, $rotatedFile);
    }
    
    /**
     * Get unique request ID
     */
    private static function getRequestId() {
        static $requestId = null;
        
        if ($requestId === null) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_', true);
        }
        
        return $requestId;
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
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log API request
     */
    public static function logRequest($method, $uri, $statusCode, $responseTime = null) {
        $context = [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'response_time' => $responseTime,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null
        ];
        
        if ($statusCode >= 400) {
            self::warning("API request failed: {$method} {$uri}", $context);
        } else {
            self::info("API request: {$method} {$uri}", $context);
        }
    }
    
    /**
     * Log database query
     */
    public static function logQuery($sql, $params = [], $executionTime = null) {
        if (!isDevelopment()) {
            return; // Only log queries in development
        }
        
        $context = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => $executionTime
        ];
        
        self::debug('Database query executed', $context);
    }
    
    /**
     * Log performance metrics
     */
    public static function logPerformance($operation, $duration, $context = []) {
        $context['operation'] = $operation;
        $context['duration'] = $duration;
        $context['memory_peak'] = memory_get_peak_usage(true);
        
        if ($duration > 1.0) { // Log slow operations
            self::warning("Slow operation detected: {$operation}", $context);
        } else {
            self::debug("Performance: {$operation}", $context);
        }
    }
    
    /**
     * Get log statistics
     */
    public static function getLogStats($logFile = null) {
        $config = self::getConfig();
        $logFile = $logFile ?: $config['file'];
        
        if (!file_exists($logFile)) {
            return null;
        }
        
        return [
            'file' => $logFile,
            'size' => filesize($logFile),
            'size_formatted' => self::formatBytes(filesize($logFile)),
            'modified' => filemtime($logFile),
            'modified_formatted' => date('Y-m-d H:i:s', filemtime($logFile)),
            'lines' => self::countLines($logFile)
        ];
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Count lines in file
     */
    private static function countLines($file) {
        $lines = 0;
        $handle = fopen($file, 'r');
        
        if ($handle) {
            while (!feof($handle)) {
                $lines += substr_count(fread($handle, 8192), "\n");
            }
            fclose($handle);
        }
        
        return $lines;
    }
    
    /**
     * Log exception with full context
     */
    public static function logException(Exception $exception, $context = []) {
        $exceptionContext = [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        $context = array_merge($exceptionContext, $context);
        
        self::error('Exception occurred: ' . $exception->getMessage(), $context);
    }
    
    /**
     * Log user action for audit trail
     */
    public static function logUserAction($userId, $action, $resource = null, $context = []) {
        $auditContext = [
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'timestamp' => time()
        ];
        
        $context = array_merge($auditContext, $context);
        
        self::info("User action: {$action}", $context);
    }
    
    /**
     * Clear log files
     */
    public static function clearLogs($logFile = null) {
        $config = self::getConfig();
        
        if ($logFile) {
            $files = [$logFile];
        } else {
            $files = [
                $config['file'],
                $config['error_file'],
                $config['security_file']
            ];
        }
        
        $cleared = 0;
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                file_put_contents($file, '');
                $cleared++;
            }
        }
        
        self::info("Log files cleared", ['files_cleared' => $cleared]);
        
        return $cleared;
    }
}