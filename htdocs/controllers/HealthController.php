<?php
/**
 * Health Controller
 * 
 * This controller provides health check endpoints for monitoring the API status,
 * database connectivity, and system information.
 * 
 * Requirements: 20.1, 20.2
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PerformanceService.php';
require_once __DIR__ . '/../services/MonitoringService.php';

/**
 * Health Controller Class
 */
class HealthController {
    /**
     * Welcome message for root endpoint
     */
    public function welcome() {
        $appConfig = getAppConfig();
        
        Response::success('Welcome to Riya Collections API', [
            'name' => $appConfig['name'],
            'version' => $appConfig['version'],
            'environment' => $appConfig['env'],
            'timestamp' => date('c'),
            'endpoints' => [
                'health' => '/api/health',
                'auth' => '/api/auth/*',
                'products' => '/api/products',
                'orders' => '/api/orders',
                'payments' => '/api/payments/*'
            ]
        ]);
    }
    
    /**
     * Basic health check
     */
    public function check() {
        $startTime = microtime(true);
        
        try {
            // Check database connectivity
            $db = Database::getInstance();
            $dbStatus = $db->testConnection();
            $dbInfo = $db->getDatabaseInfo();
            
            // Check file system permissions
            $uploadsWritable = is_writable(__DIR__ . '/../uploads');
            $logsWritable = is_writable(__DIR__ . '/../logs');
            
            // Get system information
            $systemInfo = [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ];
            
            // Calculate response time
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Determine overall status
            $status = 'healthy';
            $issues = [];
            
            if (!$dbStatus) {
                $status = 'unhealthy';
                $issues[] = 'Database connection failed';
            }
            
            if (!$uploadsWritable) {
                $status = 'degraded';
                $issues[] = 'Uploads directory not writable';
            }
            
            if (!$logsWritable) {
                $status = 'degraded';
                $issues[] = 'Logs directory not writable';
            }
            
            $healthData = [
                'status' => $status,
                'timestamp' => date('c'),
                'response_time_ms' => $responseTime,
                'version' => getAppConfig()['version'],
                'environment' => getAppConfig()['env'],
                'services' => [
                    'database' => [
                        'status' => $dbStatus ? 'healthy' : 'unhealthy',
                        'info' => $dbInfo
                    ],
                    'file_system' => [
                        'uploads_writable' => $uploadsWritable,
                        'logs_writable' => $logsWritable
                    ]
                ],
                'system' => $systemInfo,
                'issues' => $issues
            ];
            
            $statusCode = $status === 'healthy' ? 200 : ($status === 'degraded' ? 200 : 503);
            
            Response::json([
                'success' => $status !== 'unhealthy',
                'message' => $status === 'healthy' ? 'System is healthy' : 'System has issues',
                'data' => $healthData,
                'errors' => null
            ], $statusCode);
            
        } catch (Exception $e) {
            Logger::error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Response::json([
                'success' => false,
                'message' => 'Health check failed',
                'data' => [
                    'status' => 'unhealthy',
                    'timestamp' => date('c'),
                    'error' => $e->getMessage()
                ],
                'errors' => null
            ], 503);
        }
    }
    
    /**
     * Detailed system information (admin only)
     */
    public function detailedCheck() {
        try {
            $startTime = microtime(true);
            
            // Get comprehensive system information
            $info = [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'uptime' => $this->getSystemUptime(),
                'php' => [
                    'version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'extensions' => get_loaded_extensions(),
                    'ini_settings' => [
                        'memory_limit' => ini_get('memory_limit'),
                        'max_execution_time' => ini_get('max_execution_time'),
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size'),
                        'max_file_uploads' => ini_get('max_file_uploads'),
                        'error_reporting' => ini_get('error_reporting'),
                        'display_errors' => ini_get('display_errors'),
                        'log_errors' => ini_get('log_errors')
                    ]
                ],
                'server' => [
                    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                    'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                    'request_time' => $_SERVER['REQUEST_TIME'] ?? time(),
                    'load_average' => $this->getLoadAverage()
                ],
                'environment' => [
                    'app_env' => getAppConfig()['env'],
                    'debug_mode' => getAppConfig()['debug'],
                    'timezone' => date_default_timezone_get(),
                    'locale' => getAppConfig()['locale']
                ],
                'database' => $this->getDatabaseHealth(),
                'file_system' => $this->getFileSystemHealth(),
                'memory' => [
                    'current_usage' => memory_get_usage(true),
                    'peak_usage' => memory_get_peak_usage(true),
                    'limit' => ini_get('memory_limit'),
                    'usage_percentage' => $this->getMemoryUsagePercentage()
                ],
                'logs' => [
                    'app_log' => Logger::getLogStats(),
                    'error_log' => Logger::getLogStats('logs/error.log'),
                    'security_log' => Logger::getLogStats('logs/security.log')
                ],
                'security' => $this->getSecurityStatus(),
                'performance' => [
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'opcache_enabled' => function_exists('opcache_get_status'),
                    'opcache_status' => function_exists('opcache_get_status') ? opcache_get_status() : null
                ]
            ];
            
            // Determine overall health status
            $issues = [];
            $status = 'healthy';
            
            // Check database health
            if (!$info['database']['connection_status']) {
                $status = 'unhealthy';
                $issues[] = 'Database connection failed';
            }
            
            // Check file system health
            if (!$info['file_system']['uploads_writable'] || !$info['file_system']['logs_writable']) {
                $status = 'degraded';
                $issues[] = 'File system permissions issue';
            }
            
            // Check memory usage
            if ($info['memory']['usage_percentage'] > 90) {
                $status = 'degraded';
                $issues[] = 'High memory usage';
            }
            
            $info['status'] = $status;
            $info['issues'] = $issues;
            
            $statusCode = $status === 'healthy' ? 200 : ($status === 'degraded' ? 200 : 503);
            
            Response::json([
                'success' => $status !== 'unhealthy',
                'message' => $status === 'healthy' ? 'System is healthy' : 'System has issues',
                'data' => $info,
                'errors' => null
            ], $statusCode);
            
        } catch (Exception $e) {
            Logger::error('Detailed health check failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to retrieve detailed system information');
        }
    }
    
    /**
     * Get system uptime (if available)
     */
    private function getSystemUptime() {
        if (function_exists('sys_getloadavg') && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = floatval(explode(' ', $uptime)[0]);
            return [
                'seconds' => $uptime,
                'formatted' => $this->formatUptime($uptime)
            ];
        }
        
        return null;
    }
    
    /**
     * Format uptime in human readable format
     */
    private function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return "{$days}d {$hours}h {$minutes}m";
    }
    
    /**
     * Get system load average (if available)
     */
    private function getLoadAverage() {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        
        return null;
    }
    
    /**
     * Get database health information
     */
    private function getDatabaseHealth() {
        try {
            $db = Database::getInstance();
            $connectionStatus = $db->testConnection();
            $dbInfo = $db->getDatabaseInfo();
            
            // Get additional database metrics
            $metrics = [];
            if ($connectionStatus) {
                try {
                    $conn = $db->getConnection();
                    
                    // Get database size
                    $stmt = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = DATABASE()");
                    $size = $stmt->fetch();
                    $metrics['size_mb'] = $size['DB Size in MB'] ?? 0;
                    
                    // Get connection count
                    $stmt = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
                    $connections = $stmt->fetch();
                    $metrics['active_connections'] = $connections['Value'] ?? 0;
                    
                    // Get uptime
                    $stmt = $conn->query("SHOW STATUS LIKE 'Uptime'");
                    $uptime = $stmt->fetch();
                    $metrics['uptime_seconds'] = $uptime['Value'] ?? 0;
                    
                } catch (Exception $e) {
                    $metrics['error'] = 'Could not retrieve database metrics';
                }
            }
            
            return [
                'connection_status' => $connectionStatus,
                'info' => $dbInfo,
                'metrics' => $metrics
            ];
            
        } catch (Exception $e) {
            return [
                'connection_status' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get file system health information
     */
    private function getFileSystemHealth() {
        $uploadDir = __DIR__ . '/../uploads';
        $logDir = __DIR__ . '/../logs';
        $cacheDir = __DIR__ . '/../cache';
        
        return [
            'uploads_writable' => is_writable($uploadDir),
            'logs_writable' => is_writable($logDir),
            'cache_writable' => is_writable($cacheDir),
            'disk_space' => [
                'free_bytes' => disk_free_space(__DIR__),
                'total_bytes' => disk_total_space(__DIR__),
                'used_percentage' => $this->getDiskUsagePercentage()
            ],
            'directories' => [
                'uploads' => [
                    'exists' => is_dir($uploadDir),
                    'writable' => is_writable($uploadDir),
                    'size' => $this->getDirectorySize($uploadDir)
                ],
                'logs' => [
                    'exists' => is_dir($logDir),
                    'writable' => is_writable($logDir),
                    'size' => $this->getDirectorySize($logDir)
                ],
                'cache' => [
                    'exists' => is_dir($cacheDir),
                    'writable' => is_writable($cacheDir),
                    'size' => $this->getDirectorySize($cacheDir)
                ]
            ]
        ];
    }
    
    /**
     * Get memory usage percentage
     */
    private function getMemoryUsagePercentage() {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return 0; // No limit
        }
        
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $currentUsage = memory_get_usage(true);
        
        return round(($currentUsage / $memoryLimitBytes) * 100, 2);
    }
    
    /**
     * Get disk usage percentage
     */
    private function getDiskUsagePercentage() {
        $freeBytes = disk_free_space(__DIR__);
        $totalBytes = disk_total_space(__DIR__);
        
        if ($totalBytes === false || $freeBytes === false) {
            return null;
        }
        
        $usedBytes = $totalBytes - $freeBytes;
        return round(($usedBytes / $totalBytes) * 100, 2);
    }
    
    /**
     * Get directory size
     */
    private function getDirectorySize($directory) {
        if (!is_dir($directory)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($value) {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Get security status information
     */
    private function getSecurityStatus() {
        return [
            'https_enabled' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'security_headers' => [
                'x_frame_options' => !empty($_SERVER['HTTP_X_FRAME_OPTIONS']),
                'x_content_type_options' => !empty($_SERVER['HTTP_X_CONTENT_TYPE_OPTIONS']),
                'x_xss_protection' => !empty($_SERVER['HTTP_X_XSS_PROTECTION'])
            ],
            'php_security' => [
                'expose_php' => ini_get('expose_php') == '0',
                'allow_url_fopen' => ini_get('allow_url_fopen') == '0',
                'allow_url_include' => ini_get('allow_url_include') == '0',
                'session_cookie_httponly' => ini_get('session.cookie_httponly') == '1',
                'session_cookie_secure' => ini_get('session.cookie_secure') == '1'
            ],
            'file_permissions' => [
                'config_readable' => is_readable(__DIR__ . '/../config'),
                'uploads_writable_only' => is_writable(__DIR__ . '/../uploads') && !is_executable(__DIR__ . '/../uploads')
            ]
        ];
    }
    
    /**
     * Performance monitoring endpoint
     */
    public function performance() {
        try {
            $performanceService = new PerformanceService();
            $metrics = $performanceService->getPerformanceMetrics();
            
            Response::success('Performance metrics retrieved', $metrics);
            
        } catch (Exception $e) {
            Logger::error('Failed to get performance metrics', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to retrieve performance metrics');
        }
    }
    
    /**
     * Database performance analysis endpoint
     */
    public function analyzeDatabase() {
        try {
            $performanceService = new PerformanceService();
            $analysis = $performanceService->analyzePerformance();
            
            Response::success('Database performance analysis completed', $analysis);
            
        } catch (Exception $e) {
            Logger::error('Database performance analysis failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Database performance analysis failed');
        }
    }
    
    /**
     * Optimize database tables endpoint
     */
    public function optimizeDatabase() {
        try {
            $performanceService = new PerformanceService();
            $results = $performanceService->optimizeTables();
            
            Response::success('Database optimization completed', [
                'optimized_tables' => $results
            ]);
            
        } catch (Exception $e) {
            Logger::error('Database optimization failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Database optimization failed');
        }
    }
    
    /**
     * Clear performance caches endpoint
     */
    public function clearCaches() {
        try {
            $performanceService = new PerformanceService();
            $results = $performanceService->clearCaches();
            
            Response::success('Performance caches cleared', $results);
            
        } catch (Exception $e) {
            Logger::error('Failed to clear performance caches', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to clear performance caches');
        }
    }
    
    /**
     * Comprehensive monitoring endpoint
     */
    public function monitor() {
        try {
            $monitoringService = new MonitoringService();
            $healthData = $monitoringService->performHealthCheck();
            
            $statusCode = $healthData['overall_status'] === 'healthy' ? 200 : 
                         ($healthData['overall_status'] === 'degraded' ? 200 : 503);
            
            Response::json([
                'success' => $healthData['overall_status'] !== 'unhealthy',
                'message' => 'System monitoring completed',
                'data' => $healthData,
                'errors' => null
            ], $statusCode);
            
        } catch (Exception $e) {
            Logger::error('System monitoring failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('System monitoring failed');
        }
    }
    
    /**
     * Get health check history
     */
    public function history() {
        try {
            $hours = $_GET['hours'] ?? 24;
            $hours = max(1, min(168, (int)$hours)); // Limit between 1 hour and 1 week
            
            $monitoringService = new MonitoringService();
            $history = $monitoringService->getHealthHistory($hours);
            
            Response::success('Health check history retrieved', [
                'history' => $history,
                'hours' => $hours,
                'count' => count($history)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to get health history', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to retrieve health history');
        }
    }
    
    /**
     * Get system uptime
     */
    public function uptime() {
        try {
            $monitoringService = new MonitoringService();
            $uptime = $monitoringService->getUptime();
            
            Response::success('System uptime retrieved', [
                'uptime' => $uptime,
                'timestamp' => date('c')
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to get system uptime', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to retrieve system uptime');
        }
    }
}