<?php
/**
 * Monitoring Service
 * 
 * This service provides comprehensive system monitoring, alerting,
 * and health check capabilities for the PHP backend system.
 * 
 * Requirements: 20.1, 20.2, 20.3
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/PerformanceService.php';

/**
 * Monitoring Service Class
 */
class MonitoringService {
    private $db;
    private $performanceService;
    private $alertThresholds;
    private $monitoringConfig;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->performanceService = new PerformanceService();
        
        $this->alertThresholds = [
            'cpu_usage' => 80, // percentage
            'memory_usage' => 85, // percentage
            'disk_usage' => 90, // percentage
            'response_time' => 2000, // milliseconds
            'error_rate' => 5, // percentage
            'database_connections' => 80, // percentage of max
            'cache_hit_ratio' => 70 // percentage
        ];
        
        $this->monitoringConfig = [
            'check_interval' => 300, // 5 minutes
            'retention_days' => 30,
            'alert_cooldown' => 900, // 15 minutes
            'enable_alerts' => true
        ];
    }
    
    /**
     * Perform comprehensive system health check
     */
    public function performHealthCheck() {
        $startTime = microtime(true);
        
        try {
            $healthData = [
                'timestamp' => date('c'),
                'overall_status' => 'healthy',
                'checks' => [
                    'database' => $this->checkDatabaseHealth(),
                    'application' => $this->checkApplicationHealth(),
                    'system' => $this->checkSystemHealth(),
                    'performance' => $this->checkPerformanceHealth(),
                    'security' => $this->checkSecurityHealth()
                ],
                'metrics' => $this->collectMetrics(),
                'alerts' => [],
                'response_time_ms' => 0
            ];
            
            // Determine overall status
            $healthData['overall_status'] = $this->determineOverallStatus($healthData['checks']);
            
            // Generate alerts if needed
            $healthData['alerts'] = $this->generateAlerts($healthData);
            
            // Calculate response time
            $healthData['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
            // Store health check result
            $this->storeHealthCheckResult($healthData);
            
            Logger::info('Health check completed', [
                'status' => $healthData['overall_status'],
                'response_time_ms' => $healthData['response_time_ms'],
                'alerts_count' => count($healthData['alerts'])
            ]);
            
            return $healthData;
            
        } catch (Exception $e) {
            Logger::error('Health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'timestamp' => date('c'),
                'overall_status' => 'unhealthy',
                'error' => $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth() {
        try {
            $startTime = microtime(true);
            
            // Test basic connectivity
            $connectionTest = $this->db->testConnection();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if (!$connectionTest) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Database connection failed',
                    'response_time_ms' => $responseTime
                ];
            }
            
            // Get database information
            $dbInfo = $this->db->getDatabaseInfo();
            $dbStats = $this->db->getConnectionStats();
            
            // Check for issues
            $issues = [];
            
            if ($responseTime > 1000) {
                $issues[] = 'Slow database response time';
            }
            
            if ($dbStats['error_rate'] > 5) {
                $issues[] = 'High database error rate';
            }
            
            if ($dbStats['cache_hit_ratio'] < 70) {
                $issues[] = 'Low cache hit ratio';
            }
            
            $status = empty($issues) ? 'healthy' : 'degraded';
            
            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
                'info' => $dbInfo,
                'stats' => $dbStats,
                'issues' => $issues
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check application health
     */
    private function checkApplicationHealth() {
        try {
            $issues = [];
            
            // Check critical directories
            $directories = [
                'uploads' => __DIR__ . '/../uploads',
                'logs' => __DIR__ . '/../logs',
                'cache' => __DIR__ . '/../cache'
            ];
            
            foreach ($directories as $name => $path) {
                if (!is_dir($path)) {
                    $issues[] = "Missing {$name} directory";
                } elseif (!is_writable($path)) {
                    $issues[] = "{$name} directory not writable";
                }
            }
            
            // Check configuration files
            $configFiles = [
                'database' => __DIR__ . '/../config/database.php',
                'jwt' => __DIR__ . '/../config/jwt.php',
                'email' => __DIR__ . '/../config/email.php'
            ];
            
            foreach ($configFiles as $name => $path) {
                if (!file_exists($path)) {
                    $issues[] = "Missing {$name} configuration file";
                } elseif (!is_readable($path)) {
                    $issues[] = "{$name} configuration file not readable";
                }
            }
            
            // Check PHP extensions
            $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
            foreach ($requiredExtensions as $extension) {
                if (!extension_loaded($extension)) {
                    $issues[] = "Missing PHP extension: {$extension}";
                }
            }
            
            $status = empty($issues) ? 'healthy' : 'degraded';
            
            return [
                'status' => $status,
                'php_version' => PHP_VERSION,
                'extensions' => get_loaded_extensions(),
                'issues' => $issues
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check system health
     */
    private function checkSystemHealth() {
        try {
            $issues = [];
            
            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
            
            if ($memoryPercent > $this->alertThresholds['memory_usage']) {
                $issues[] = 'High memory usage';
            }
            
            // Disk space
            $diskFree = disk_free_space(__DIR__);
            $diskTotal = disk_total_space(__DIR__);
            $diskUsagePercent = $diskTotal > 0 ? (($diskTotal - $diskFree) / $diskTotal) * 100 : 0;
            
            if ($diskUsagePercent > $this->alertThresholds['disk_usage']) {
                $issues[] = 'Low disk space';
            }
            
            // Load average (if available)
            $loadAverage = null;
            if (function_exists('sys_getloadavg')) {
                $loadAverage = sys_getloadavg();
                if ($loadAverage[0] > 5.0) {
                    $issues[] = 'High system load';
                }
            }
            
            $status = empty($issues) ? 'healthy' : 'degraded';
            
            return [
                'status' => $status,
                'memory' => [
                    'usage_bytes' => $memoryUsage,
                    'limit_bytes' => $memoryLimit,
                    'usage_percent' => round($memoryPercent, 2)
                ],
                'disk' => [
                    'free_bytes' => $diskFree,
                    'total_bytes' => $diskTotal,
                    'usage_percent' => round($diskUsagePercent, 2)
                ],
                'load_average' => $loadAverage,
                'issues' => $issues
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check performance health
     */
    private function checkPerformanceHealth() {
        try {
            $metrics = $this->performanceService->getPerformanceMetrics();
            $issues = [];
            
            // Check database performance
            if (isset($metrics['database_stats'])) {
                $dbStats = $metrics['database_stats'];
                
                if ($dbStats['average_execution_time'] > $this->alertThresholds['response_time']) {
                    $issues[] = 'Slow database queries';
                }
                
                if ($dbStats['cache_hit_ratio'] < $this->alertThresholds['cache_hit_ratio']) {
                    $issues[] = 'Low cache hit ratio';
                }
                
                if ($dbStats['error_rate'] > $this->alertThresholds['error_rate']) {
                    $issues[] = 'High database error rate';
                }
            }
            
            $status = empty($issues) ? 'healthy' : 'degraded';
            
            return [
                'status' => $status,
                'metrics' => $metrics,
                'issues' => $issues
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check security health
     */
    private function checkSecurityHealth() {
        try {
            $issues = [];
            
            // Check HTTPS
            if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                $issues[] = 'HTTPS not enabled';
            }
            
            // Check PHP security settings
            $securitySettings = [
                'expose_php' => '0',
                'allow_url_fopen' => '0',
                'allow_url_include' => '0',
                'session.cookie_httponly' => '1',
                'session.cookie_secure' => '1'
            ];
            
            foreach ($securitySettings as $setting => $expectedValue) {
                if (ini_get($setting) != $expectedValue) {
                    $issues[] = "Insecure PHP setting: {$setting}";
                }
            }
            
            // Check file permissions
            $sensitiveFiles = [
                __DIR__ . '/../config',
                __DIR__ . '/../.env'
            ];
            
            foreach ($sensitiveFiles as $file) {
                if (file_exists($file) && is_readable($file)) {
                    $perms = fileperms($file);
                    if ($perms & 0x0004) { // World readable
                        $issues[] = "Sensitive file world-readable: " . basename($file);
                    }
                }
            }
            
            $status = empty($issues) ? 'healthy' : 'degraded';
            
            return [
                'status' => $status,
                'https_enabled' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'security_headers' => $this->checkSecurityHeaders(),
                'issues' => $issues
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check security headers
     */
    private function checkSecurityHeaders() {
        $headers = [
            'X-Frame-Options' => !empty($_SERVER['HTTP_X_FRAME_OPTIONS']),
            'X-Content-Type-Options' => !empty($_SERVER['HTTP_X_CONTENT_TYPE_OPTIONS']),
            'X-XSS-Protection' => !empty($_SERVER['HTTP_X_XSS_PROTECTION']),
            'Strict-Transport-Security' => !empty($_SERVER['HTTP_STRICT_TRANSPORT_SECURITY']),
            'Content-Security-Policy' => !empty($_SERVER['HTTP_CONTENT_SECURITY_POLICY'])
        ];
        
        return $headers;
    }
    
    /**
     * Collect system metrics
     */
    private function collectMetrics() {
        return [
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_usage' => $this->getCpuUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'network_stats' => $this->getNetworkStats(),
            'process_count' => $this->getProcessCount()
        ];
    }
    
    /**
     * Get CPU usage (if available)
     */
    private function getCpuUsage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0]; // 1-minute load average
        }
        return null;
    }
    
    /**
     * Get disk usage information
     */
    private function getDiskUsage() {
        $free = disk_free_space(__DIR__);
        $total = disk_total_space(__DIR__);
        
        return [
            'free_bytes' => $free,
            'total_bytes' => $total,
            'used_bytes' => $total - $free,
            'usage_percent' => $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0
        ];
    }
    
    /**
     * Get network statistics (placeholder)
     */
    private function getNetworkStats() {
        // This would typically read from /proc/net/dev on Linux
        return [
            'connections' => $this->getActiveConnections(),
            'bandwidth_usage' => null // Placeholder
        ];
    }
    
    /**
     * Get active connection count (placeholder)
     */
    private function getActiveConnections() {
        // This would typically use netstat or ss command
        return null;
    }
    
    /**
     * Get process count (if available)
     */
    private function getProcessCount() {
        if (function_exists('shell_exec')) {
            $output = shell_exec('ps aux | wc -l');
            return $output ? (int)trim($output) : null;
        }
        return null;
    }
    
    /**
     * Determine overall system status
     */
    private function determineOverallStatus($checks) {
        $statuses = array_column($checks, 'status');
        
        if (in_array('unhealthy', $statuses)) {
            return 'unhealthy';
        }
        
        if (in_array('degraded', $statuses)) {
            return 'degraded';
        }
        
        return 'healthy';
    }
    
    /**
     * Generate alerts based on health check results
     */
    private function generateAlerts($healthData) {
        $alerts = [];
        
        if (!$this->monitoringConfig['enable_alerts']) {
            return $alerts;
        }
        
        // Check each component for issues
        foreach ($healthData['checks'] as $component => $check) {
            if (isset($check['issues']) && !empty($check['issues'])) {
                foreach ($check['issues'] as $issue) {
                    $alertKey = md5($component . $issue);
                    
                    // Check if alert is in cooldown period
                    if (!$this->isAlertInCooldown($alertKey)) {
                        $alerts[] = [
                            'id' => $alertKey,
                            'component' => $component,
                            'severity' => $check['status'] === 'unhealthy' ? 'critical' : 'warning',
                            'message' => $issue,
                            'timestamp' => time()
                        ];
                        
                        // Record alert to prevent spam
                        $this->recordAlert($alertKey);
                    }
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Check if alert is in cooldown period
     */
    private function isAlertInCooldown($alertKey) {
        $alertFile = __DIR__ . '/../logs/alerts.json';
        
        if (!file_exists($alertFile)) {
            return false;
        }
        
        $alerts = json_decode(file_get_contents($alertFile), true);
        
        if (isset($alerts[$alertKey])) {
            $lastAlert = $alerts[$alertKey];
            return (time() - $lastAlert) < $this->monitoringConfig['alert_cooldown'];
        }
        
        return false;
    }
    
    /**
     * Record alert timestamp
     */
    private function recordAlert($alertKey) {
        $alertFile = __DIR__ . '/../logs/alerts.json';
        
        $alerts = [];
        if (file_exists($alertFile)) {
            $alerts = json_decode(file_get_contents($alertFile), true) ?: [];
        }
        
        $alerts[$alertKey] = time();
        
        // Clean up old alerts
        $cutoff = time() - ($this->monitoringConfig['retention_days'] * 86400);
        $alerts = array_filter($alerts, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        file_put_contents($alertFile, json_encode($alerts));
    }
    
    /**
     * Store health check result for historical tracking
     */
    private function storeHealthCheckResult($healthData) {
        try {
            $logFile = __DIR__ . '/../logs/health_checks.log';
            
            $logEntry = [
                'timestamp' => $healthData['timestamp'],
                'status' => $healthData['overall_status'],
                'response_time_ms' => $healthData['response_time_ms'],
                'alerts_count' => count($healthData['alerts']),
                'summary' => $this->createHealthSummary($healthData)
            ];
            
            file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            Logger::error('Failed to store health check result', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create health summary for logging
     */
    private function createHealthSummary($healthData) {
        $summary = [];
        
        foreach ($healthData['checks'] as $component => $check) {
            $summary[$component] = $check['status'];
        }
        
        return $summary;
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($limit) {
        if ($limit === '-1') {
            return 0; // No limit
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
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
     * Get health check history
     */
    public function getHealthHistory($hours = 24) {
        try {
            $logFile = __DIR__ . '/../logs/health_checks.log';
            
            if (!file_exists($logFile)) {
                return [];
            }
            
            $cutoff = time() - ($hours * 3600);
            $history = [];
            
            $handle = fopen($logFile, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $entry = json_decode(trim($line), true);
                    if ($entry && strtotime($entry['timestamp']) > $cutoff) {
                        $history[] = $entry;
                    }
                }
                fclose($handle);
            }
            
            return array_reverse($history); // Most recent first
            
        } catch (Exception $e) {
            Logger::error('Failed to get health history', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get system uptime
     */
    public function getUptime() {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $seconds = floatval(explode(' ', $uptime)[0]);
            
            return [
                'seconds' => $seconds,
                'formatted' => $this->formatUptime($seconds)
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
}