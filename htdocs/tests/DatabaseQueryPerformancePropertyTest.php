<?php
/**
 * Database Query Performance Property Test
 * 
 * This test validates Property 18: Database Query Performance
 * For any database query, the execution time should be within acceptable 
 * performance thresholds when proper indexing is applied.
 * 
 * **Validates: Requirements 12.1**
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PerformanceService.php';

use PHPUnit\Framework\TestCase;

class DatabaseQueryPerformancePropertyTest extends TestCase {
    private $db;
    private $performanceService;
    private $testTables = [];
    private $performanceThresholds;
    
    protected function setUp(): void {
        $this->db = Database::getInstance();
        $this->performanceService = new PerformanceService();
        
        // Performance thresholds for testing
        $this->performanceThresholds = [
            'simple_select' => 100, // milliseconds
            'indexed_select' => 200, // milliseconds
            'join_query' => 500, // milliseconds
            'complex_query' => 1000, // milliseconds
            'bulk_insert' => 2000 // milliseconds
        ];
        
        $this->createTestTables();
        $this->populateTestData();
    }
    
    protected function tearDown(): void {
        $this->cleanupTestTables();
    }
    
    /**
     * Property Test: Simple SELECT Query Performance
     * **Validates: Requirements 12.1**
     * 
     * For any simple SELECT query on an indexed column, 
     * execution time should be under the threshold.
     */
    public function testSimpleSelectQueryPerformance() {
        $iterations = 50;
        $slowQueries = 0;
        $totalTime = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random query parameters
            $id = rand(1, 1000);
            $status = $this->generateRandomStatus();
            
            // Test indexed column query
            $startTime = microtime(true);
            $result = $this->db->fetchAll(
                "SELECT * FROM test_performance WHERE id = ?", 
                [$id]
            );
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            $totalTime += $executionTime;
            
            if ($executionTime > $this->performanceThresholds['simple_select']) {
                $slowQueries++;
            }
            
            // Verify query returns expected structure
            $this->assertIsArray($result);
            if (!empty($result)) {
                $this->assertArrayHasKey('id', $result[0]);
                $this->assertArrayHasKey('name', $result[0]);
                $this->assertArrayHasKey('status', $result[0]);
            }
        }
        
        $averageTime = $totalTime / $iterations;
        $slowQueryPercentage = ($slowQueries / $iterations) * 100;
        
        // Log performance metrics
        Logger::info('Simple SELECT performance test completed', [
            'iterations' => $iterations,
            'average_time_ms' => round($averageTime, 2),
            'slow_queries' => $slowQueries,
            'slow_query_percentage' => round($slowQueryPercentage, 2),
            'threshold_ms' => $this->performanceThresholds['simple_select']
        ]);
        
        // Property assertion: Most queries should be fast
        $this->assertLessThan(10, $slowQueryPercentage, 
            "More than 10% of simple SELECT queries exceeded performance threshold");
        
        $this->assertLessThan($this->performanceThresholds['simple_select'], $averageTime,
            "Average execution time exceeds threshold for simple SELECT queries");
    }
    
    /**
     * Property Test: Indexed Column Query Performance
     * **Validates: Requirements 12.1**
     * 
     * For any query using indexed columns, performance should be 
     * significantly better than non-indexed queries.
     */
    public function testIndexedVsNonIndexedPerformance() {
        $iterations = 30;
        $indexedTimes = [];
        $nonIndexedTimes = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $searchValue = $this->generateRandomString(10);
            
            // Test indexed column query (status has index)
            $startTime = microtime(true);
            $indexedResult = $this->db->fetchAll(
                "SELECT * FROM test_performance WHERE status = ?", 
                [$searchValue]
            );
            $indexedTime = (microtime(true) - $startTime) * 1000;
            $indexedTimes[] = $indexedTime;
            
            // Test non-indexed column query (description has no index)
            $startTime = microtime(true);
            $nonIndexedResult = $this->db->fetchAll(
                "SELECT * FROM test_performance WHERE description LIKE ?", 
                ["%{$searchValue}%"]
            );
            $nonIndexedTime = (microtime(true) - $startTime) * 1000;
            $nonIndexedTimes[] = $nonIndexedTime;
            
            // Verify both queries return valid results
            $this->assertIsArray($indexedResult);
            $this->assertIsArray($nonIndexedResult);
        }
        
        $avgIndexedTime = array_sum($indexedTimes) / count($indexedTimes);
        $avgNonIndexedTime = array_sum($nonIndexedTimes) / count($nonIndexedTimes);
        $performanceRatio = $avgNonIndexedTime / $avgIndexedTime;
        
        Logger::info('Indexed vs non-indexed performance comparison', [
            'iterations' => $iterations,
            'avg_indexed_time_ms' => round($avgIndexedTime, 2),
            'avg_non_indexed_time_ms' => round($avgNonIndexedTime, 2),
            'performance_ratio' => round($performanceRatio, 2)
        ]);
        
        // Property assertion: Indexed queries should be faster
        $this->assertGreaterThan(1.5, $performanceRatio,
            "Indexed queries should be at least 50% faster than non-indexed queries");
        
        $this->assertLessThan($this->performanceThresholds['indexed_select'], $avgIndexedTime,
            "Average indexed query time exceeds threshold");
    }
    
    /**
     * Property Test: JOIN Query Performance
     * **Validates: Requirements 12.1**
     * 
     * For any JOIN query with proper foreign key indexes, 
     * performance should be within acceptable limits.
     */
    public function testJoinQueryPerformance() {
        $iterations = 25;
        $slowQueries = 0;
        $totalTime = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            $categoryId = rand(1, 10);
            
            // Test JOIN query with indexed foreign key
            $startTime = microtime(true);
            $result = $this->db->fetchAll(
                "SELECT p.*, c.name as category_name 
                 FROM test_performance p 
                 JOIN test_categories c ON p.category_id = c.id 
                 WHERE c.id = ?", 
                [$categoryId]
            );
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            $totalTime += $executionTime;
            
            if ($executionTime > $this->performanceThresholds['join_query']) {
                $slowQueries++;
            }
            
            // Verify JOIN result structure
            $this->assertIsArray($result);
            if (!empty($result)) {
                $this->assertArrayHasKey('id', $result[0]);
                $this->assertArrayHasKey('category_name', $result[0]);
            }
        }
        
        $averageTime = $totalTime / $iterations;
        $slowQueryPercentage = ($slowQueries / $iterations) * 100;
        
        Logger::info('JOIN query performance test completed', [
            'iterations' => $iterations,
            'average_time_ms' => round($averageTime, 2),
            'slow_queries' => $slowQueries,
            'slow_query_percentage' => round($slowQueryPercentage, 2),
            'threshold_ms' => $this->performanceThresholds['join_query']
        ]);
        
        // Property assertion: JOIN queries should perform well
        $this->assertLessThan(15, $slowQueryPercentage,
            "More than 15% of JOIN queries exceeded performance threshold");
        
        $this->assertLessThan($this->performanceThresholds['join_query'], $averageTime,
            "Average JOIN query execution time exceeds threshold");
    }
    
    /**
     * Property Test: Complex Query Performance
     * **Validates: Requirements 12.1**
     * 
     * For any complex query with multiple conditions and sorting,
     * performance should remain within acceptable bounds.
     */
    public function testComplexQueryPerformance() {
        $iterations = 20;
        $slowQueries = 0;
        $totalTime = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            $status = $this->generateRandomStatus();
            $minId = rand(1, 500);
            $maxId = $minId + rand(100, 500);
            
            // Test complex query with multiple conditions
            $startTime = microtime(true);
            $result = $this->db->fetchAll(
                "SELECT p.*, c.name as category_name, 
                        COUNT(*) OVER() as total_count
                 FROM test_performance p 
                 JOIN test_categories c ON p.category_id = c.id 
                 WHERE p.status = ? 
                   AND p.id BETWEEN ? AND ?
                   AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 ORDER BY p.created_at DESC, p.id ASC
                 LIMIT 50", 
                [$status, $minId, $maxId]
            );
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            $totalTime += $executionTime;
            
            if ($executionTime > $this->performanceThresholds['complex_query']) {
                $slowQueries++;
            }
            
            // Verify complex query result
            $this->assertIsArray($result);
            $this->assertLessThanOrEqual(50, count($result));
        }
        
        $averageTime = $totalTime / $iterations;
        $slowQueryPercentage = ($slowQueries / $iterations) * 100;
        
        Logger::info('Complex query performance test completed', [
            'iterations' => $iterations,
            'average_time_ms' => round($averageTime, 2),
            'slow_queries' => $slowQueries,
            'slow_query_percentage' => round($slowQueryPercentage, 2),
            'threshold_ms' => $this->performanceThresholds['complex_query']
        ]);
        
        // Property assertion: Complex queries should still perform reasonably
        $this->assertLessThan(20, $slowQueryPercentage,
            "More than 20% of complex queries exceeded performance threshold");
        
        $this->assertLessThan($this->performanceThresholds['complex_query'], $averageTime,
            "Average complex query execution time exceeds threshold");
    }
    
    /**
     * Property Test: Bulk Insert Performance
     * **Validates: Requirements 12.1**
     * 
     * For any bulk insert operation, performance should scale
     * reasonably with the number of records.
     */
    public function testBulkInsertPerformance() {
        $batchSizes = [10, 50, 100, 200];
        $performanceData = [];
        
        foreach ($batchSizes as $batchSize) {
            $records = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $records[] = [
                    'name' => $this->generateRandomString(20),
                    'status' => $this->generateRandomStatus(),
                    'category_id' => rand(1, 10),
                    'description' => $this->generateRandomString(100),
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Test bulk insert performance
            $startTime = microtime(true);
            
            $this->db->beginTransaction();
            try {
                foreach ($records as $record) {
                    $this->db->executeQuery(
                        "INSERT INTO test_performance_bulk (name, status, category_id, description, created_at) 
                         VALUES (?, ?, ?, ?, ?)",
                        array_values($record)
                    );
                }
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            $timePerRecord = $executionTime / $batchSize;
            
            $performanceData[] = [
                'batch_size' => $batchSize,
                'total_time_ms' => $executionTime,
                'time_per_record_ms' => $timePerRecord
            ];
            
            // Verify all records were inserted
            $count = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM test_performance_bulk WHERE created_at >= ?",
                [date('Y-m-d H:i:s', time() - 60)]
            );
            $this->assertGreaterThanOrEqual($batchSize, $count);
        }
        
        Logger::info('Bulk insert performance test completed', [
            'performance_data' => $performanceData
        ]);
        
        // Property assertion: Bulk inserts should scale reasonably
        foreach ($performanceData as $data) {
            $this->assertLessThan($this->performanceThresholds['bulk_insert'], 
                $data['total_time_ms'],
                "Bulk insert of {$data['batch_size']} records exceeded threshold");
            
            // Time per record should not increase dramatically with batch size
            $this->assertLessThan(50, $data['time_per_record_ms'],
                "Time per record in bulk insert is too high");
        }
    }
    
    /**
     * Property Test: Query Cache Performance
     * **Validates: Requirements 12.1**
     * 
     * For any repeated query, cache hits should provide
     * significant performance improvement.
     */
    public function testQueryCachePerformance() {
        $iterations = 20;
        $query = "SELECT * FROM test_performance WHERE status = ? ORDER BY id LIMIT 10";
        $params = ['active'];
        
        $firstRunTimes = [];
        $cachedRunTimes = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Clear cache before first run
            $this->db->clearQueryCache();
            
            // First run (cache miss)
            $startTime = microtime(true);
            $result1 = $this->db->fetchAll($query, $params);
            $firstRunTime = (microtime(true) - $startTime) * 1000;
            $firstRunTimes[] = $firstRunTime;
            
            // Second run (should be cache hit)
            $startTime = microtime(true);
            $result2 = $this->db->fetchAll($query, $params);
            $cachedRunTime = (microtime(true) - $startTime) * 1000;
            $cachedRunTimes[] = $cachedRunTime;
            
            // Verify results are identical
            $this->assertEquals($result1, $result2);
        }
        
        $avgFirstRun = array_sum($firstRunTimes) / count($firstRunTimes);
        $avgCachedRun = array_sum($cachedRunTimes) / count($cachedRunTimes);
        $cacheSpeedup = $avgFirstRun / $avgCachedRun;
        
        Logger::info('Query cache performance test completed', [
            'iterations' => $iterations,
            'avg_first_run_ms' => round($avgFirstRun, 2),
            'avg_cached_run_ms' => round($avgCachedRun, 2),
            'cache_speedup' => round($cacheSpeedup, 2)
        ]);
        
        // Property assertion: Cache should provide performance benefit
        $this->assertGreaterThan(1.2, $cacheSpeedup,
            "Query cache should provide at least 20% performance improvement");
        
        // Get cache statistics
        $stats = $this->db->getConnectionStats();
        $this->assertGreaterThan(0, $stats['cache_hits'],
            "Cache hits should be recorded");
    }
    
    /**
     * Create test tables for performance testing
     */
    private function createTestTables() {
        // Main performance test table
        $this->db->executeQuery("
            CREATE TABLE IF NOT EXISTS test_performance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                category_id INT,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_category_id (category_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB
        ");
        
        // Categories table for JOIN tests
        $this->db->executeQuery("
            CREATE TABLE IF NOT EXISTS test_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");
        
        // Bulk insert test table
        $this->db->executeQuery("
            CREATE TABLE IF NOT EXISTS test_performance_bulk (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                category_id INT,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");
        
        $this->testTables = ['test_performance', 'test_categories', 'test_performance_bulk'];
    }
    
    /**
     * Populate test data
     */
    private function populateTestData() {
        // Insert categories
        $categories = ['Electronics', 'Clothing', 'Books', 'Home', 'Sports', 
                      'Toys', 'Beauty', 'Automotive', 'Garden', 'Health'];
        
        foreach ($categories as $category) {
            $this->db->executeQuery(
                "INSERT IGNORE INTO test_categories (name) VALUES (?)",
                [$category]
            );
        }
        
        // Check if test data already exists
        $count = $this->db->fetchColumn("SELECT COUNT(*) FROM test_performance");
        if ($count > 0) {
            return; // Data already exists
        }
        
        // Insert test performance data
        $statuses = ['active', 'inactive', 'pending', 'archived'];
        
        for ($i = 1; $i <= 1000; $i++) {
            $this->db->executeQuery(
                "INSERT INTO test_performance (name, status, category_id, description) 
                 VALUES (?, ?, ?, ?)",
                [
                    "Test Item {$i}",
                    $statuses[array_rand($statuses)],
                    rand(1, 10),
                    "Description for test item {$i} with some random content " . 
                    $this->generateRandomString(50)
                ]
            );
        }
    }
    
    /**
     * Clean up test tables
     */
    private function cleanupTestTables() {
        foreach ($this->testTables as $table) {
            try {
                $this->db->executeQuery("DROP TABLE IF EXISTS {$table}");
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
    
    /**
     * Generate random string
     */
    private function generateRandomString($length) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ';
        $string = '';
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $string;
    }
    
    /**
     * Generate random status
     */
    private function generateRandomStatus() {
        $statuses = ['active', 'inactive', 'pending', 'archived'];
        return $statuses[array_rand($statuses)];
    }
}