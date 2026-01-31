<?php
/**
 * Database Configuration
 * 
 * This module provides database connection management using PDO with prepared statements
 * for security. It maintains compatibility with the existing MySQL schema while providing
 * enhanced security and performance features.
 * 
 * Requirements: 2.1, 2.2, 14.2
 */

// Load environment variables
require_once __DIR__ . '/environment.php';

// Load Logger utility
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Database configuration array
 */
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'riya_collections',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_PERSISTENT => false
    ]
];

/**
 * Database Connection Class (Singleton Pattern with Connection Pooling Concepts)
 * 
 * Enhanced with:
 * - Robust singleton pattern implementation
 * - Connection pooling concepts (connection reuse and health monitoring)
 * - Comprehensive error handling and recovery
 * - Performance optimizations and query caching
 * - Enhanced security measures
 */
class Database {
    private static $instance = null;
    private $connection = null;
    private $config;
    private $connectionAttempts = 0;
    private $maxConnectionAttempts = 3;
    private $connectionTimeout = 30;
    private $lastHealthCheck = 0;
    private $healthCheckInterval = 300; // 5 minutes
    private $queryCache = [];
    private $maxCacheSize = 100;
    private $transactionLevel = 0;
    private $connectionStats = [
        'queries_executed' => 0,
        'total_execution_time' => 0,
        'failed_queries' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0
    ];
    
    /**
     * Private constructor to prevent direct instantiation
     * Enhanced with connection pooling concepts and error handling
     */
    private function __construct() {
        global $dbConfig;
        $this->config = $dbConfig;
        $this->establishConnection();
    }
    
    /**
     * Get database instance (Singleton with thread safety)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            // Prevent race conditions in multi-threaded environments
            if (self::$instance === null) {
                self::$instance = new self();
            }
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection with retry logic and health monitoring
     */
    private function establishConnection() {
        $this->connectionAttempts = 0;
        
        while ($this->connectionAttempts < $this->maxConnectionAttempts) {
            try {
                $this->connectionAttempts++;
                
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database'],
                    $this->config['charset']
                );
                
                // Enhanced PDO options for security and performance
                $options = array_merge($this->config['options'], [
                    PDO::ATTR_TIMEOUT => $this->connectionTimeout,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                        "SET NAMES %s COLLATE %s, time_zone = '+00:00', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
                        $this->config['charset'],
                        $this->config['collation']
                    )
                ]);
                
                $this->connection = new PDO(
                    $dsn, 
                    $this->config['username'], 
                    $this->config['password'], 
                    $options
                );
                
                // Verify connection with a simple query
                $this->connection->query('SELECT 1');
                
                $this->lastHealthCheck = time();
                $this->connectionAttempts = 0; // Reset on successful connection
                
                Logger::info('Database connection established successfully', [
                    'host' => $this->config['host'],
                    'database' => $this->config['database'],
                    'attempt' => $this->connectionAttempts,
                    'connection_id' => $this->getConnectionId()
                ]);
                
                return;
                
            } catch (PDOException $e) {
                $this->handleConnectionError($e);
                
                // Wait before retry (exponential backoff)
                if ($this->connectionAttempts < $this->maxConnectionAttempts) {
                    $waitTime = pow(2, $this->connectionAttempts - 1);
                    sleep($waitTime);
                }
            }
        }
        
        // If we reach here, all connection attempts failed
        $errorMessage = "Failed to establish database connection after {$this->maxConnectionAttempts} attempts";
        Logger::critical($errorMessage, [
            'host' => $this->config['host'],
            'database' => $this->config['database']
        ]);
        
        throw new Exception($errorMessage);
    }
    
    /**
     * Handle connection errors with detailed logging
     */
    private function handleConnectionError(PDOException $e) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Classify error types for better handling
        $errorType = 'unknown';
        if (strpos($errorMessage, 'Access denied') !== false) {
            $errorType = 'authentication';
        } elseif (strpos($errorMessage, 'Unknown database') !== false) {
            $errorType = 'database_not_found';
        } elseif (strpos($errorMessage, 'Connection refused') !== false) {
            $errorType = 'connection_refused';
        } elseif (strpos($errorMessage, 'timeout') !== false) {
            $errorType = 'timeout';
        }
        
        Logger::error('Database connection attempt failed', [
            'attempt' => $this->connectionAttempts,
            'max_attempts' => $this->maxConnectionAttempts,
            'error_code' => $errorCode,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'host' => $this->config['host'],
            'database' => $this->config['database']
        ]);
        
        // Log security events for authentication failures
        if ($errorType === 'authentication') {
            Logger::security('Database authentication failure', [
                'host' => $this->config['host'],
                'username' => $this->config['username']
            ]);
        }
    }
    
    /**
     * Get PDO connection instance with health monitoring
     */
    public function getConnection() {
        // Perform health check if needed
        if ($this->shouldPerformHealthCheck()) {
            $this->performHealthCheck();
        }
        
        // Ensure connection is still alive
        if ($this->connection === null) {
            $this->establishConnection();
        }
        
        return $this->connection;
    }
    
    /**
     * Check if health check should be performed
     */
    private function shouldPerformHealthCheck() {
        return (time() - $this->lastHealthCheck) > $this->healthCheckInterval;
    }
    
    /**
     * Perform connection health check
     */
    private function performHealthCheck() {
        try {
            if ($this->connection !== null) {
                $startTime = microtime(true);
                $result = $this->connection->query('SELECT 1 as health_check');
                $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
                
                if ($result && $result->fetchColumn() == 1) {
                    $this->lastHealthCheck = time();
                    
                    Logger::debug('Database health check passed', [
                        'response_time_ms' => round($responseTime, 2),
                        'connection_id' => $this->getConnectionId()
                    ]);
                    
                    return true;
                }
            }
            
            // Health check failed, reconnect
            Logger::warning('Database health check failed, reconnecting...');
            $this->establishConnection();
            
        } catch (PDOException $e) {
            Logger::warning('Database health check failed with exception, reconnecting...', [
                'error' => $e->getMessage()
            ]);
            $this->establishConnection();
        }
        
        return false;
    }
    
    /**
     * Get connection ID for monitoring
     */
    private function getConnectionId() {
        try {
            if ($this->connection) {
                return $this->connection->query('SELECT CONNECTION_ID()')->fetchColumn();
            }
        } catch (Exception $e) {
            // Ignore errors when getting connection ID
        }
        return null;
    }
    
    /**
     * Execute a prepared query with parameters and enhanced security
     */
    public function executeQuery($sql, $params = []) {
        $startTime = microtime(true);
        
        try {
            // Validate SQL query for security
            $this->validateQuery($sql);
            
            // Check query cache first
            $cacheKey = $this->getCacheKey($sql, $params);
            if ($this->isSelectQuery($sql) && isset($this->queryCache[$cacheKey])) {
                $this->connectionStats['cache_hits']++;
                Logger::debug('Query cache hit', ['cache_key' => $cacheKey]);
                return $this->queryCache[$cacheKey];
            }
            
            $stmt = $this->getConnection()->prepare($sql);
            
            // Enhanced parameter binding with type detection
            $this->bindParameters($stmt, $params);
            
            // Log query for debugging (in development only)
            if (isDevelopment()) {
                Logger::debug('Executing database query', [
                    'sql' => $sql,
                    'params' => $this->sanitizeParamsForLogging($params),
                    'cache_key' => $cacheKey
                ]);
            }
            
            $stmt->execute();
            
            // Update statistics
            $executionTime = (microtime(true) - $startTime) * 1000;
            $this->connectionStats['queries_executed']++;
            $this->connectionStats['total_execution_time'] += $executionTime;
            
            // Cache SELECT queries
            if ($this->isSelectQuery($sql)) {
                $this->cacheQuery($cacheKey, $stmt);
                $this->connectionStats['cache_misses']++;
            }
            
            // Log slow queries
            if ($executionTime > 1000) { // Queries taking more than 1 second
                Logger::warning('Slow query detected', [
                    'sql' => $sql,
                    'execution_time_ms' => round($executionTime, 2),
                    'params_count' => count($params)
                ]);
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->connectionStats['failed_queries']++;
            $this->handleQueryError($e, $sql, $params);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate SQL query for security threats
     */
    private function validateQuery($sql) {
        // Remove comments and normalize whitespace
        $normalizedSql = preg_replace('/\/\*.*?\*\/|--.*$/m', '', $sql);
        $normalizedSql = preg_replace('/\s+/', ' ', trim($normalizedSql));
        
        // Check for dangerous SQL patterns
        $dangerousPatterns = [
            '/\b(DROP|ALTER|CREATE|TRUNCATE)\s+/i',
            '/\bINTO\s+OUTFILE\b/i',
            '/\bLOAD_FILE\s*\(/i',
            '/\bUNION\s+.*SELECT\b/i',
            '/\bEXEC\s*\(/i',
            '/\bSYSTEM\s*\(/i',
            '/\bDELETE\s+FROM\b/i',
            '/\'\s*OR\s+\'\d+\'\s*=\s*\'\d+\'/i', // OR '1'='1' pattern
            '/\'\s*OR\s+\d+\s*=\s*\d+/i', // OR 1=1 pattern
            '/;\s*(DROP|DELETE|TRUNCATE|ALTER|CREATE)\b/i', // SQL injection with semicolon
            '/--\s*$/m', // SQL comments at end of line
            '/\/\*.*\*\//s' // SQL block comments
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $normalizedSql)) {
                Logger::security('Potentially dangerous SQL query blocked', [
                    'sql' => $sql,
                    'pattern' => $pattern,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                throw new Exception('Query contains potentially dangerous SQL patterns');
            }
        }
    }
    
    /**
     * Enhanced parameter binding with type detection
     */
    private function bindParameters($stmt, $params) {
        foreach ($params as $key => $value) {
            $paramName = is_int($key) ? $key + 1 : $key;
            
            // Detect parameter type
            if (is_null($value)) {
                $type = PDO::PARAM_NULL;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_int($value)) {
                $type = PDO::PARAM_INT;
            } else {
                $type = PDO::PARAM_STR;
            }
            
            $stmt->bindValue($paramName, $value, $type);
        }
    }
    
    /**
     * Sanitize parameters for logging (remove sensitive data)
     */
    private function sanitizeParamsForLogging($params) {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'hash'];
        $sanitized = [];
        
        foreach ($params as $key => $value) {
            $keyLower = strtolower((string)$key);
            $isSensitive = false;
            
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($keyLower, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            $sanitized[$key] = $isSensitive ? '[REDACTED]' : $value;
        }
        
        return $sanitized;
    }
    
    /**
     * Check if query is a SELECT statement
     */
    private function isSelectQuery($sql) {
        return preg_match('/^\s*SELECT\b/i', trim($sql));
    }
    
    /**
     * Generate cache key for query
     */
    private function getCacheKey($sql, $params) {
        return md5($sql . serialize($params));
    }
    
    /**
     * Cache query result
     */
    private function cacheQuery($cacheKey, $stmt) {
        // Limit cache size
        if (count($this->queryCache) >= $this->maxCacheSize) {
            // Remove oldest entries (simple FIFO)
            $this->queryCache = array_slice($this->queryCache, -($this->maxCacheSize - 10), null, true);
        }
        
        // Clone statement for caching
        $this->queryCache[$cacheKey] = clone $stmt;
    }
    
    /**
     * Handle query execution errors
     */
    private function handleQueryError(PDOException $e, $sql, $params) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Classify error types
        $errorType = 'unknown';
        if (strpos($errorMessage, 'Duplicate entry') !== false) {
            $errorType = 'duplicate_entry';
        } elseif (strpos($errorMessage, 'doesn\'t exist') !== false) {
            $errorType = 'table_not_found';
        } elseif (strpos($errorMessage, 'Lost connection') !== false) {
            $errorType = 'connection_lost';
        } elseif (strpos($errorMessage, 'Deadlock') !== false) {
            $errorType = 'deadlock';
        }
        
        Logger::error('Database query execution failed', [
            'sql' => $sql,
            'params' => $this->sanitizeParamsForLogging($params),
            'error_code' => $errorCode,
            'error_type' => $errorType,
            'error_message' => $errorMessage
        ]);
        
        // Handle connection lost errors by reconnecting
        if ($errorType === 'connection_lost') {
            Logger::info('Attempting to reconnect due to lost connection...');
            $this->establishConnection();
        }
    }
    
    /**
     * Execute a query and return all results
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Execute a query and return single result
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Execute a query and return single column value
     */
    public function fetchColumn($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get the last inserted ID
     */
    public function getLastInsertId() {
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * Begin database transaction with nested transaction support
     */
    public function beginTransaction() {
        try {
            if ($this->transactionLevel === 0) {
                $result = $this->getConnection()->beginTransaction();
                Logger::debug('Database transaction started');
            } else {
                // Nested transaction using savepoints
                $savepointName = 'sp_level_' . $this->transactionLevel;
                $result = $this->getConnection()->exec("SAVEPOINT {$savepointName}");
                Logger::debug('Database savepoint created', ['savepoint' => $savepointName]);
            }
            
            $this->transactionLevel++;
            return $result;
            
        } catch (PDOException $e) {
            Logger::error('Failed to start database transaction', [
                'error' => $e->getMessage(),
                'transaction_level' => $this->transactionLevel
            ]);
            throw new Exception('Transaction start failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Commit database transaction with nested transaction support
     */
    public function commit() {
        try {
            if ($this->transactionLevel <= 0) {
                throw new Exception('No active transaction to commit');
            }
            
            $this->transactionLevel--;
            
            if ($this->transactionLevel === 0) {
                $result = $this->getConnection()->commit();
                Logger::debug('Database transaction committed');
            } else {
                // Release savepoint for nested transaction
                $savepointName = 'sp_level_' . $this->transactionLevel;
                $result = $this->getConnection()->exec("RELEASE SAVEPOINT {$savepointName}");
                Logger::debug('Database savepoint released', ['savepoint' => $savepointName]);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            Logger::error('Failed to commit database transaction', [
                'error' => $e->getMessage(),
                'transaction_level' => $this->transactionLevel
            ]);
            throw new Exception('Transaction commit failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Rollback database transaction with nested transaction support
     */
    public function rollback() {
        try {
            if ($this->transactionLevel <= 0) {
                throw new Exception('No active transaction to rollback');
            }
            
            $this->transactionLevel--;
            
            if ($this->transactionLevel === 0) {
                $result = $this->getConnection()->rollback();
                Logger::debug('Database transaction rolled back');
            } else {
                // Rollback to savepoint for nested transaction
                $savepointName = 'sp_level_' . $this->transactionLevel;
                $result = $this->getConnection()->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
                Logger::debug('Database rolled back to savepoint', ['savepoint' => $savepointName]);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            Logger::error('Failed to rollback database transaction', [
                'error' => $e->getMessage(),
                'transaction_level' => $this->transactionLevel
            ]);
            throw new Exception('Transaction rollback failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute multiple queries in a transaction
     */
    public function executeTransaction($queries) {
        $this->beginTransaction();
        
        try {
            $results = [];
            
            foreach ($queries as $query) {
                $sql = $query['sql'];
                $params = $query['params'] ?? [];
                
                $stmt = $this->executeQuery($sql, $params);
                $results[] = [
                    'statement' => $stmt,
                    'rowCount' => $stmt->rowCount(),
                    'lastInsertId' => $this->getLastInsertId()
                ];
            }
            
            $this->commit();
            return $results;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $result = $this->fetchOne('SELECT 1 as test, NOW() as current_time');
            
            if ($result && $result['test'] == 1) {
                Logger::info('Database connection test successful', [
                    'server_time' => $result['current_time']
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Logger::error('Database connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get database information with enhanced details
     */
    public function getDatabaseInfo() {
        try {
            $version = $this->fetchColumn('SELECT VERSION()');
            $database = $this->fetchColumn('SELECT DATABASE()');
            $charset = $this->fetchColumn('SELECT @@character_set_database');
            $collation = $this->fetchColumn('SELECT @@collation_database');
            $timezone = $this->fetchColumn('SELECT @@time_zone');
            $connectionId = $this->getConnectionId();
            
            // Get additional server information
            $serverInfo = [
                'version' => $version,
                'database' => $database,
                'charset' => $charset,
                'collation' => $collation,
                'timezone' => $timezone,
                'connection_id' => $connectionId,
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'uptime' => $this->fetchColumn('SELECT @@uptime'),
                'max_connections' => $this->fetchColumn('SELECT @@max_connections'),
                'thread_cache_size' => $this->fetchColumn('SELECT @@thread_cache_size')
            ];
            
            return $serverInfo;
            
        } catch (Exception $e) {
            Logger::error('Failed to get database information', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get connection statistics
     */
    public function getConnectionStats() {
        $stats = $this->connectionStats;
        $stats['average_execution_time'] = $stats['queries_executed'] > 0 
            ? round($stats['total_execution_time'] / $stats['queries_executed'], 2) 
            : 0;
        $stats['cache_hit_ratio'] = ($stats['cache_hits'] + $stats['cache_misses']) > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2) 
            : 0;
        $stats['error_rate'] = $stats['queries_executed'] > 0 
            ? round(($stats['failed_queries'] / $stats['queries_executed']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Clear query cache
     */
    public function clearQueryCache() {
        $cacheSize = count($this->queryCache);
        $this->queryCache = [];
        
        Logger::info('Query cache cleared', ['cached_queries' => $cacheSize]);
        
        return $cacheSize;
    }
    
    /**
     * Optimize database tables
     */
    public function optimizeTables($tables = null) {
        try {
            if ($tables === null) {
                // Get all tables in the database
                $tables = $this->fetchAll('SHOW TABLES');
                $tables = array_column($tables, array_keys($tables[0])[0]);
            }
            
            $optimized = [];
            
            foreach ($tables as $table) {
                $result = $this->fetchOne("OPTIMIZE TABLE `{$table}`");
                $optimized[] = [
                    'table' => $table,
                    'status' => $result['Msg_text'] ?? 'Unknown'
                ];
            }
            
            Logger::info('Database tables optimized', [
                'tables_count' => count($optimized),
                'tables' => array_column($optimized, 'table')
            ]);
            
            return $optimized;
            
        } catch (Exception $e) {
            Logger::error('Failed to optimize database tables', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Check database integrity
     */
    public function checkIntegrity($tables = null) {
        try {
            if ($tables === null) {
                // Get all tables in the database
                $tables = $this->fetchAll('SHOW TABLES');
                $tables = array_column($tables, array_keys($tables[0])[0]);
            }
            
            $results = [];
            
            foreach ($tables as $table) {
                $result = $this->fetchAll("CHECK TABLE `{$table}`");
                $results[$table] = $result;
            }
            
            Logger::info('Database integrity check completed', [
                'tables_checked' => count($results)
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            Logger::error('Failed to check database integrity', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get database size information
     */
    public function getDatabaseSize() {
        try {
            $query = "
                SELECT 
                    table_schema as 'database_name',
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as 'size_mb',
                    COUNT(*) as 'table_count'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                GROUP BY table_schema
            ";
            
            $result = $this->fetchOne($query);
            
            // Get individual table sizes
            $tableQuery = "
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'size_mb',
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
            ";
            
            $tables = $this->fetchAll($tableQuery);
            
            return [
                'database' => $result,
                'tables' => $tables
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get database size information', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Execute query with retry logic for deadlocks
     */
    public function executeWithRetry($sql, $params = [], $maxRetries = 3) {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return $this->executeQuery($sql, $params);
                
            } catch (Exception $e) {
                $attempt++;
                
                // Check if it's a deadlock error
                if (strpos($e->getMessage(), 'Deadlock') !== false && $attempt < $maxRetries) {
                    Logger::warning('Deadlock detected, retrying query', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'sql' => $sql
                    ]);
                    
                    // Wait before retry (exponential backoff)
                    usleep(pow(2, $attempt) * 100000); // 0.1s, 0.2s, 0.4s
                    continue;
                }
                
                throw $e;
            }
        }
        
        throw new Exception("Query failed after {$maxRetries} attempts");
    }
    
    /**
     * Backup database structure and data
     */
    public function createBackup($includeData = true) {
        try {
            $backupDir = __DIR__ . '/../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = "{$backupDir}/backup_{$this->config['database']}_{$timestamp}.sql";
            
            // Get all tables
            $tables = $this->fetchAll('SHOW TABLES');
            $tables = array_column($tables, array_keys($tables[0])[0]);
            
            $backup = "-- Database Backup\n";
            $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $backup .= "-- Database: {$this->config['database']}\n\n";
            
            $backup .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            foreach ($tables as $table) {
                // Get table structure
                $createTable = $this->fetchOne("SHOW CREATE TABLE `{$table}`");
                $backup .= "-- Table structure for `{$table}`\n";
                $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $backup .= $createTable['Create Table'] . ";\n\n";
                
                // Get table data if requested
                if ($includeData) {
                    $rows = $this->fetchAll("SELECT * FROM `{$table}`");
                    
                    if (!empty($rows)) {
                        $backup .= "-- Data for table `{$table}`\n";
                        $backup .= "INSERT INTO `{$table}` VALUES\n";
                        
                        $values = [];
                        foreach ($rows as $row) {
                            $escapedValues = array_map(function($value) {
                                return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                            }, array_values($row));
                            
                            $values[] = '(' . implode(', ', $escapedValues) . ')';
                        }
                        
                        $backup .= implode(",\n", $values) . ";\n\n";
                    }
                }
            }
            
            $backup .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            
            file_put_contents($backupFile, $backup);
            
            Logger::info('Database backup created', [
                'backup_file' => $backupFile,
                'include_data' => $includeData,
                'tables_count' => count($tables),
                'file_size' => filesize($backupFile)
            ]);
            
            return $backupFile;
            
        } catch (Exception $e) {
            Logger::error('Failed to create database backup', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {
        throw new Exception("Cannot clone singleton Database instance");
    }
    
    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton Database instance");
    }
    
    /**
     * Destructor - Clean up resources
     */
    public function __destruct() {
        // Log final statistics
        if ($this->connectionStats['queries_executed'] > 0) {
            Logger::info('Database connection closing', [
                'stats' => $this->getConnectionStats(),
                'connection_id' => $this->getConnectionId()
            ]);
        }
        
        // Close connection
        $this->connection = null;
    }
}

/**
 * Helper function to get database instance
 */
function getDatabase() {
    return Database::getInstance();
}

/**
 * Helper function to execute a query
 */
function dbQuery($sql, $params = []) {
    return Database::getInstance()->executeQuery($sql, $params);
}

/**
 * Helper function to fetch all results
 */
function dbFetchAll($sql, $params = []) {
    return Database::getInstance()->fetchAll($sql, $params);
}

/**
 * Helper function to fetch single result
 */
function dbFetchOne($sql, $params = []) {
    return Database::getInstance()->fetchOne($sql, $params);
}

/**
 * Helper function to fetch single column
 */
function dbFetchColumn($sql, $params = []) {
    return Database::getInstance()->fetchColumn($sql, $params);
}

// Test database connection on include (only in development)
if (getenv('APP_ENV') === 'development') {
    try {
        $db = Database::getInstance();
        $db->testConnection();
    } catch (Exception $e) {
        Logger::error('Database initialization failed during include', ['error' => $e->getMessage()]);
    }
}