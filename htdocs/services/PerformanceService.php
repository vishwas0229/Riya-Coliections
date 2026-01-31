<?php
/**
 * Performance Service
 * 
 * This service provides database query optimization, performance monitoring,
 * and indexing validation for the PHP backend system.
 * 
 * Requirements: 12.1, 12.2, 20.1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Performance Service Class
 */
class PerformanceService {
    private $db;
    private $performanceThresholds;
    private $indexRecommendations = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->performanceThresholds = [
            'slow_query_time' => 1000, // milliseconds
            'max_execution_time' => 5000, // milliseconds
            'memory_usage_limit' => 128 * 1024 * 1024, // 128MB
            'cache_hit_ratio_min' => 80, // percentage
            'connection_timeout' => 30000 // milliseconds
        ];
    }
    
    /**
     * Analyze database performance and provide optimization recommendations
     */
    public function analyzePerformance() {
        $startTime = microtime(true);
        
        try {
            $analysis = [
                'timestamp' => date('c'),
                'database_info' => $this->db->getDatabaseInfo(),
                'connection_stats' => $this->db->getConnectionStats(),
                'table_analysis' => $this->analyzeTablePerformance(),
                'index_analysis' => $this->analyzeIndexUsage(),
                'query_performance' => $this->analyzeQueryPerformance(),
                'recommendations' => [],
                'overall_score' => 0
            ];
            
            // Generate recommendations based on analysis
            $analysis['recommendations'] = $this->generateRecommendations($analysis);
            $analysis['overall_score'] = $this->calculatePerformanceScore($analysis);
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            Logger::info('Performance analysis completed', [
                'execution_time_ms' => round($executionTime, 2),
                'overall_score' => $analysis['overall_score'],
                'recommendations_count' => count($analysis['recommendations'])
            ]);
            
            return $analysis;
            
        } catch (Exception $e) {
            Logger::error('Performance analysis failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Analyze table performance metrics
     */
    private function analyzeTablePerformance() {
        try {
            $query = "
                SELECT 
                    t.table_name,
                    t.table_rows,
                    ROUND(((t.data_length + t.index_length) / 1024 / 1024), 2) as size_mb,
                    ROUND((t.data_length / 1024 / 1024), 2) as data_mb,
                    ROUND((t.index_length / 1024 / 1024), 2) as index_mb,
                    t.avg_row_length,
                    t.auto_increment,
                    t.table_collation,
                    t.engine
                FROM information_schema.tables t
                WHERE t.table_schema = DATABASE()
                AND t.table_type = 'BASE TABLE'
                ORDER BY (t.data_length + t.index_length) DESC
            ";
            
            $tables = $this->db->fetchAll($query);
            
            // Analyze each table for performance issues
            foreach ($tables as &$table) {
                $table['performance_issues'] = [];
                
                // Check for large tables without proper indexing
                if ($table['size_mb'] > 100 && $table['index_mb'] < ($table['data_mb'] * 0.1)) {
                    $table['performance_issues'][] = 'Large table with insufficient indexing';
                }
                
                // Check for tables with very high row count
                if ($table['table_rows'] > 1000000) {
                    $table['performance_issues'][] = 'Very high row count - consider partitioning';
                }
                
                // Check for MyISAM engine (should use InnoDB)
                if ($table['engine'] === 'MyISAM') {
                    $table['performance_issues'][] = 'Using MyISAM engine - consider InnoDB for better performance';
                }
                
                // Get table-specific statistics
                $table['statistics'] = $this->getTableStatistics($table['table_name']);
            }
            
            return $tables;
            
        } catch (Exception $e) {
            Logger::error('Table performance analysis failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get detailed statistics for a specific table
     */
    private function getTableStatistics($tableName) {
        try {
            $stats = [];
            
            // Get index cardinality
            $indexQuery = "
                SELECT 
                    index_name,
                    column_name,
                    cardinality,
                    sub_part,
                    nullable
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
                ORDER BY index_name, seq_in_index
            ";
            
            $stats['indexes'] = $this->db->fetchAll($indexQuery, [$tableName]);
            
            // Get column information
            $columnQuery = "
                SELECT 
                    column_name,
                    data_type,
                    is_nullable,
                    column_default,
                    column_key,
                    extra
                FROM information_schema.columns 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
                ORDER BY ordinal_position
            ";
            
            $stats['columns'] = $this->db->fetchAll($columnQuery, [$tableName]);
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Failed to get table statistics', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Analyze index usage and effectiveness
     */
    private function analyzeIndexUsage() {
        try {
            // Get index usage statistics
            $query = "
                SELECT 
                    s.table_name,
                    s.index_name,
                    s.column_name,
                    s.cardinality,
                    s.sub_part,
                    s.packed,
                    s.nullable,
                    s.index_type
                FROM information_schema.statistics s
                WHERE s.table_schema = DATABASE()
                ORDER BY s.table_name, s.index_name, s.seq_in_index
            ";
            
            $indexes = $this->db->fetchAll($query);
            
            // Group indexes by table
            $indexAnalysis = [];
            foreach ($indexes as $index) {
                $tableName = $index['table_name'];
                $indexName = $index['index_name'];
                
                if (!isset($indexAnalysis[$tableName])) {
                    $indexAnalysis[$tableName] = [];
                }
                
                if (!isset($indexAnalysis[$tableName][$indexName])) {
                    $indexAnalysis[$tableName][$indexName] = [
                        'columns' => [],
                        'type' => $index['index_type'],
                        'issues' => []
                    ];
                }
                
                $indexAnalysis[$tableName][$indexName]['columns'][] = [
                    'column' => $index['column_name'],
                    'cardinality' => $index['cardinality'],
                    'nullable' => $index['nullable']
                ];
                
                // Analyze index effectiveness
                if ($index['cardinality'] < 10 && $index['index_name'] !== 'PRIMARY') {
                    $indexAnalysis[$tableName][$indexName]['issues'][] = 'Low cardinality - index may not be effective';
                }
                
                if ($index['nullable'] === 'YES' && $index['index_name'] !== 'PRIMARY') {
                    $indexAnalysis[$tableName][$indexName]['issues'][] = 'Index on nullable column - consider composite index';
                }
            }
            
            // Check for missing indexes on foreign keys
            $this->checkMissingForeignKeyIndexes($indexAnalysis);
            
            return $indexAnalysis;
            
        } catch (Exception $e) {
            Logger::error('Index analysis failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Check for missing indexes on foreign key columns
     */
    private function checkMissingForeignKeyIndexes(&$indexAnalysis) {
        try {
            $fkQuery = "
                SELECT 
                    kcu.table_name,
                    kcu.column_name,
                    kcu.referenced_table_name,
                    kcu.referenced_column_name
                FROM information_schema.key_column_usage kcu
                WHERE kcu.table_schema = DATABASE()
                AND kcu.referenced_table_name IS NOT NULL
            ";
            
            $foreignKeys = $this->db->fetchAll($fkQuery);
            
            foreach ($foreignKeys as $fk) {
                $tableName = $fk['table_name'];
                $columnName = $fk['column_name'];
                
                // Check if there's an index on this foreign key column
                $hasIndex = false;
                if (isset($indexAnalysis[$tableName])) {
                    foreach ($indexAnalysis[$tableName] as $indexName => $indexInfo) {
                        foreach ($indexInfo['columns'] as $indexColumn) {
                            if ($indexColumn['column'] === $columnName) {
                                $hasIndex = true;
                                break 2;
                            }
                        }
                    }
                }
                
                if (!$hasIndex) {
                    $this->indexRecommendations[] = [
                        'type' => 'missing_foreign_key_index',
                        'table' => $tableName,
                        'column' => $columnName,
                        'recommendation' => "CREATE INDEX idx_{$tableName}_{$columnName} ON {$tableName} ({$columnName})",
                        'reason' => 'Foreign key column without index can cause performance issues'
                    ];
                }
            }
            
        } catch (Exception $e) {
            Logger::error('Foreign key index check failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Analyze query performance patterns
     */
    private function analyzeQueryPerformance() {
        try {
            $stats = $this->db->getConnectionStats();
            
            $analysis = [
                'total_queries' => $stats['queries_executed'],
                'average_execution_time' => $stats['average_execution_time'],
                'cache_hit_ratio' => $stats['cache_hit_ratio'],
                'error_rate' => $stats['error_rate'],
                'slow_queries' => $this->getSlowQueryCount(),
                'performance_issues' => []
            ];
            
            // Identify performance issues
            if ($analysis['average_execution_time'] > $this->performanceThresholds['slow_query_time']) {
                $analysis['performance_issues'][] = 'High average query execution time';
            }
            
            if ($analysis['cache_hit_ratio'] < $this->performanceThresholds['cache_hit_ratio_min']) {
                $analysis['performance_issues'][] = 'Low cache hit ratio';
            }
            
            if ($analysis['error_rate'] > 5) {
                $analysis['performance_issues'][] = 'High query error rate';
            }
            
            return $analysis;
            
        } catch (Exception $e) {
            Logger::error('Query performance analysis failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get count of slow queries from logs
     */
    private function getSlowQueryCount() {
        try {
            // This would typically query the MySQL slow query log
            // For now, we'll return a placeholder value
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Generate optimization recommendations
     */
    private function generateRecommendations($analysis) {
        $recommendations = [];
        
        // Add index recommendations
        $recommendations = array_merge($recommendations, $this->indexRecommendations);
        
        // Query performance recommendations
        if (isset($analysis['query_performance']['cache_hit_ratio']) && 
            $analysis['query_performance']['cache_hit_ratio'] < 80) {
            $recommendations[] = [
                'type' => 'cache_optimization',
                'priority' => 'high',
                'recommendation' => 'Increase query cache size or optimize frequently executed queries',
                'reason' => 'Low cache hit ratio indicates inefficient query patterns'
            ];
        }
        
        // Table optimization recommendations
        if (isset($analysis['table_analysis'])) {
            foreach ($analysis['table_analysis'] as $table) {
                if (!empty($table['performance_issues'])) {
                    foreach ($table['performance_issues'] as $issue) {
                        $recommendations[] = [
                            'type' => 'table_optimization',
                            'table' => $table['table_name'],
                            'priority' => 'medium',
                            'recommendation' => $this->getTableOptimizationRecommendation($issue),
                            'reason' => $issue
                        ];
                    }
                }
            }
        }
        
        // Memory usage recommendations
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage > $this->performanceThresholds['memory_usage_limit']) {
            $recommendations[] = [
                'type' => 'memory_optimization',
                'priority' => 'high',
                'recommendation' => 'Optimize memory usage by reducing query result sets or implementing pagination',
                'reason' => 'High memory usage detected'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get specific recommendation for table optimization issue
     */
    private function getTableOptimizationRecommendation($issue) {
        switch ($issue) {
            case 'Large table with insufficient indexing':
                return 'Add appropriate indexes on frequently queried columns';
            case 'Very high row count - consider partitioning':
                return 'Consider table partitioning or archiving old data';
            case 'Using MyISAM engine - consider InnoDB for better performance':
                return 'ALTER TABLE to use InnoDB engine for better concurrency and reliability';
            default:
                return 'Review table structure and optimize as needed';
        }
    }
    
    /**
     * Calculate overall performance score (0-100)
     */
    private function calculatePerformanceScore($analysis) {
        $score = 100;
        
        // Deduct points for performance issues
        if (isset($analysis['query_performance']['cache_hit_ratio'])) {
            $cacheRatio = $analysis['query_performance']['cache_hit_ratio'];
            if ($cacheRatio < 50) {
                $score -= 30;
            } elseif ($cacheRatio < 80) {
                $score -= 15;
            }
        }
        
        if (isset($analysis['query_performance']['average_execution_time'])) {
            $avgTime = $analysis['query_performance']['average_execution_time'];
            if ($avgTime > 2000) {
                $score -= 25;
            } elseif ($avgTime > 1000) {
                $score -= 10;
            }
        }
        
        // Deduct points for each recommendation
        $recommendationCount = count($analysis['recommendations']);
        $score -= min($recommendationCount * 5, 30);
        
        return max($score, 0);
    }
    
    /**
     * Monitor query execution time and log slow queries
     */
    public function monitorQuery($sql, $params, $executionTime) {
        if ($executionTime > $this->performanceThresholds['slow_query_time']) {
            Logger::warning('Slow query detected', [
                'sql' => $sql,
                'params' => $this->sanitizeParams($params),
                'execution_time_ms' => $executionTime,
                'threshold_ms' => $this->performanceThresholds['slow_query_time']
            ]);
            
            // Analyze the slow query for optimization opportunities
            $this->analyzeSlowQuery($sql, $params, $executionTime);
        }
    }
    
    /**
     * Analyze a slow query for optimization opportunities
     */
    private function analyzeSlowQuery($sql, $params, $executionTime) {
        try {
            // Use EXPLAIN to analyze query execution plan
            $explainSql = "EXPLAIN " . $sql;
            $explainResult = $this->db->fetchAll($explainSql, $params);
            
            $issues = [];
            
            foreach ($explainResult as $row) {
                // Check for table scans
                if ($row['type'] === 'ALL') {
                    $issues[] = "Full table scan on table '{$row['table']}'";
                }
                
                // Check for missing indexes
                if ($row['key'] === null && $row['type'] !== 'system') {
                    $issues[] = "No index used for table '{$row['table']}'";
                }
                
                // Check for large row examinations
                if (isset($row['rows']) && $row['rows'] > 10000) {
                    $issues[] = "Large number of rows examined ({$row['rows']}) for table '{$row['table']}'";
                }
            }
            
            if (!empty($issues)) {
                Logger::warning('Query optimization opportunities found', [
                    'sql' => $sql,
                    'execution_time_ms' => $executionTime,
                    'issues' => $issues,
                    'explain_result' => $explainResult
                ]);
            }
            
        } catch (Exception $e) {
            Logger::error('Failed to analyze slow query', [
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Sanitize parameters for logging
     */
    private function sanitizeParams($params) {
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
     * Get performance metrics for monitoring
     */
    public function getPerformanceMetrics() {
        return [
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'database_stats' => $this->db->getConnectionStats(),
            'system_load' => sys_getloadavg(),
            'uptime' => $this->getSystemUptime()
        ];
    }
    
    /**
     * Get system uptime if available
     */
    private function getSystemUptime() {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            return floatval(explode(' ', $uptime)[0]);
        }
        return null;
    }
    
    /**
     * Optimize database tables
     */
    public function optimizeTables($tables = null) {
        return $this->db->optimizeTables($tables);
    }
    
    /**
     * Clear performance caches
     */
    public function clearCaches() {
        $cleared = $this->db->clearQueryCache();
        
        Logger::info('Performance caches cleared', [
            'query_cache_entries' => $cleared
        ]);
        
        return [
            'query_cache_cleared' => $cleared
        ];
    }
}