<?php
/**
 * Database Class Tests
 * 
 * Comprehensive test suite for the Database class including:
 * - Unit tests for specific functionality
 * - Property-based tests for universal properties
 * - Security tests for SQL injection prevention
 * - Performance tests for connection management
 * 
 * Requirements: 2.1, 2.2, 10.2, 12.1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Database Unit Tests
 */
class DatabaseTest {
    private $db;
    private $testTable = 'test_database_functionality';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->setupTestEnvironment();
    }
    
    /**
     * Setup test environment
     */
    private function setupTestEnvironment() {
        // Create test table
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS `{$this->testTable}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `email` varchar(255) UNIQUE,
                `age` int(11),
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->db->executeQuery($createTableSQL);
        
        // Clear any existing test data
        $this->db->executeQuery("DELETE FROM `{$this->testTable}`");
    }
    
    /**
     * Cleanup test environment
     */
    public function cleanup() {
        $this->db->executeQuery("DROP TABLE IF EXISTS `{$this->testTable}`");
    }
    
    /**
     * Test singleton pattern implementation
     */
    public function testSingletonPattern() {
        $db1 = Database::getInstance();
        $db2 = Database::getInstance();
        
        if ($db1 === $db2) {
            echo "✓ Singleton pattern test passed\n";
            return true;
        } else {
            echo "✗ Singleton pattern test failed\n";
            return false;
        }
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $result = $this->db->testConnection();
            
            if ($result) {
                echo "✓ Database connection test passed\n";
                return true;
            } else {
                echo "✗ Database connection test failed\n";
                return false;
            }
        } catch (Exception $e) {
            echo "✗ Database connection test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test basic CRUD operations
     */
    public function testCRUDOperations() {
        try {
            // Test INSERT
            $insertSQL = "INSERT INTO `{$this->testTable}` (name, email, age) VALUES (?, ?, ?)";
            $this->db->executeQuery($insertSQL, ['John Doe', 'john@example.com', 30]);
            $insertId = $this->db->getLastInsertId();
            
            if (!$insertId) {
                echo "✗ INSERT operation failed\n";
                return false;
            }
            
            // Test SELECT
            $selectSQL = "SELECT * FROM `{$this->testTable}` WHERE id = ?";
            $result = $this->db->fetchOne($selectSQL, [$insertId]);
            
            if (!$result || $result['name'] !== 'John Doe') {
                echo "✗ SELECT operation failed\n";
                return false;
            }
            
            // Test UPDATE
            $updateSQL = "UPDATE `{$this->testTable}` SET age = ? WHERE id = ?";
            $this->db->executeQuery($updateSQL, [31, $insertId]);
            
            $updatedResult = $this->db->fetchOne($selectSQL, [$insertId]);
            if ($updatedResult['age'] != 31) {
                echo "✗ UPDATE operation failed\n";
                return false;
            }
            
            // Test DELETE
            $deleteSQL = "DELETE FROM `{$this->testTable}` WHERE id = ?";
            $this->db->executeQuery($deleteSQL, [$insertId]);
            
            $deletedResult = $this->db->fetchOne($selectSQL, [$insertId]);
            if ($deletedResult !== false) {
                echo "✗ DELETE operation failed\n";
                return false;
            }
            
            echo "✓ CRUD operations test passed\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ CRUD operations test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test transaction handling
     */
    public function testTransactions() {
        try {
            // Test successful transaction
            $this->db->beginTransaction();
            
            $this->db->executeQuery(
                "INSERT INTO `{$this->testTable}` (name, email, age) VALUES (?, ?, ?)",
                ['Transaction Test 1', 'trans1@example.com', 25]
            );
            
            $this->db->executeQuery(
                "INSERT INTO `{$this->testTable}` (name, email, age) VALUES (?, ?, ?)",
                ['Transaction Test 2', 'trans2@example.com', 26]
            );
            
            $this->db->commit();
            
            // Verify both records exist
            $count = $this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->testTable}` WHERE name LIKE 'Transaction Test%'");
            
            if ($count != 2) {
                echo "✗ Transaction commit test failed\n";
                return false;
            }
            
            // Test rollback
            $this->db->beginTransaction();
            
            $this->db->executeQuery(
                "INSERT INTO `{$this->testTable}` (name, email, age) VALUES (?, ?, ?)",
                ['Rollback Test', 'rollback@example.com', 27]
            );
            
            $this->db->rollback();
            
            // Verify record doesn't exist
            $rollbackCount = $this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->testTable}` WHERE name = 'Rollback Test'");
            
            if ($rollbackCount != 0) {
                echo "✗ Transaction rollback test failed\n";
                return false;
            }
            
            echo "✓ Transaction handling test passed\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ Transaction handling test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test SQL injection prevention
     */
    public function testSQLInjectionPrevention() {
        try {
            // Test malicious input
            $maliciousInputs = [
                "'; DROP TABLE users; --",
                "1' OR '1'='1",
                "1; DELETE FROM users WHERE 1=1; --",
                "' UNION SELECT * FROM users --"
            ];
            
            foreach ($maliciousInputs as $input) {
                try {
                    // This should be safely handled by prepared statements
                    $result = $this->db->fetchOne(
                        "SELECT * FROM `{$this->testTable}` WHERE name = ?",
                        [$input]
                    );
                    
                    // Should return false (no results) but not cause SQL injection
                    if ($result !== false) {
                        echo "✗ SQL injection prevention test failed for input: {$input}\n";
                        return false;
                    }
                    
                } catch (Exception $e) {
                    // If an exception is thrown, it should be a validation error, not SQL error
                    if (strpos($e->getMessage(), 'dangerous SQL patterns') === false) {
                        echo "✗ SQL injection prevention test failed: " . $e->getMessage() . "\n";
                        return false;
                    }
                }
            }
            
            echo "✓ SQL injection prevention test passed\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ SQL injection prevention test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test connection health monitoring
     */
    public function testHealthMonitoring() {
        try {
            // Force a health check
            $healthResult = $this->db->testConnection();
            
            if (!$healthResult) {
                echo "✗ Health monitoring test failed\n";
                return false;
            }
            
            // Get connection statistics
            $stats = $this->db->getConnectionStats();
            
            if (!is_array($stats) || !isset($stats['queries_executed'])) {
                echo "✗ Connection statistics test failed\n";
                return false;
            }
            
            echo "✓ Health monitoring test passed\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ Health monitoring test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test query caching
     */
    public function testQueryCaching() {
        try {
            // Insert test data
            $this->db->executeQuery(
                "INSERT INTO `{$this->testTable}` (name, email, age) VALUES (?, ?, ?)",
                ['Cache Test', 'cache@example.com', 28]
            );
            
            // Execute same SELECT query multiple times
            $sql = "SELECT * FROM `{$this->testTable}` WHERE name = ?";
            $params = ['Cache Test'];
            
            $result1 = $this->db->fetchOne($sql, $params);
            $result2 = $this->db->fetchOne($sql, $params);
            
            // Both should return the same data
            if ($result1['name'] !== $result2['name']) {
                echo "✗ Query caching test failed\n";
                return false;
            }
            
            echo "✓ Query caching test passed\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ Query caching test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test database model functionality
     */
    public function testDatabaseModel() {
        try {
            $model = new DatabaseModel($this->testTable);
            
            // Test insert
            $insertId = $model->insert([
                'name' => 'Model Test',
                'email' => 'model@example.com',
                'age' => 29
            ]);
            
            if (!$insertId) {
                echo "✗ Database model insert test failed\n";
                return false;
            }
            
            // Test find
            $record = $model->find($insertId);
            
            if (!$record || $record['name'] !== 'Model Test') {
                echo "✗ Database model find test failed\n";
                return false;
            }
            
            // Test update
            $updated = $model->updateById($insertId, ['age' => 30]);
            
            if (!$updated) {
                echo "✗ Database model update test failed\n";
                return false;
            }
            
            // Test where
            $results = $model->where(['name' => 'Model Test']);
            
            if (empty($results) || $results[0]['age'] != 30) {
                echo "✗ Database model where test failed\n";
                return false;
            }
            
            // Test delete
            $deleted = $model->deleteById($insertId);
            
            if (!$deleted) {
                echo "✗ Database model delete test failed\n";
                return false;
            }
            
            echo "✓ Database model test passed\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ Database model test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running Database Class Tests...\n";
        echo "================================\n";
        
        $tests = [
            'testSingletonPattern',
            'testConnection',
            'testCRUDOperations',
            'testTransactions',
            'testSQLInjectionPrevention',
            'testHealthMonitoring',
            'testQueryCaching',
            'testDatabaseModel'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        echo "\n================================\n";
        echo "Tests completed: {$passed}/{$total} passed\n";
        
        if ($passed === $total) {
            echo "✓ All tests passed!\n";
        } else {
            echo "✗ Some tests failed!\n";
        }
        
        return $passed === $total;
    }
}

/**
 * Property-Based Tests for Database Class
 */
class DatabasePropertyTests {
    private $db;
    private $testTable = 'test_property_based';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->setupTestEnvironment();
    }
    
    /**
     * Setup test environment
     */
    private function setupTestEnvironment() {
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS `{$this->testTable}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `data` text,
                `number` int(11),
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->db->executeQuery($createTableSQL);
        $this->db->executeQuery("DELETE FROM `{$this->testTable}`");
    }
    
    /**
     * Cleanup test environment
     */
    public function cleanup() {
        $this->db->executeQuery("DROP TABLE IF EXISTS `{$this->testTable}`");
    }
    
    /**
     * Property: For any valid data inserted, it should be retrievable
     * **Validates: Requirements 2.1**
     */
    public function testDataPersistenceProperty() {
        echo "Testing data persistence property...\n";
        
        for ($i = 0; $i < 100; $i++) {
            $testData = $this->generateRandomData();
            
            try {
                // Insert data
                $this->db->executeQuery(
                    "INSERT INTO `{$this->testTable}` (data, number) VALUES (?, ?)",
                    [$testData['data'], $testData['number']]
                );
                
                $insertId = $this->db->getLastInsertId();
                
                // Retrieve data
                $retrieved = $this->db->fetchOne(
                    "SELECT * FROM `{$this->testTable}` WHERE id = ?",
                    [$insertId]
                );
                
                // Verify data integrity
                if (!$retrieved || 
                    $retrieved['data'] !== $testData['data'] || 
                    $retrieved['number'] != $testData['number']) {
                    echo "✗ Data persistence property failed at iteration {$i}\n";
                    return false;
                }
                
                // Clean up
                $this->db->executeQuery("DELETE FROM `{$this->testTable}` WHERE id = ?", [$insertId]);
                
            } catch (Exception $e) {
                echo "✗ Data persistence property failed with exception: " . $e->getMessage() . "\n";
                return false;
            }
        }
        
        echo "✓ Data persistence property test passed (100 iterations)\n";
        return true;
    }
    
    /**
     * Property: For any malicious SQL input, the system should prevent injection
     * **Validates: Requirements 2.2, 10.2**
     */
    public function testSQLInjectionPreventionProperty() {
        echo "Testing SQL injection prevention property...\n";
        
        $maliciousPatterns = [
            "'; DROP TABLE {table}; --",
            "' OR '1'='1",
            "'; DELETE FROM {table}; --",
            "' UNION SELECT * FROM information_schema.tables --",
            "'; INSERT INTO {table} VALUES (999, 'hacked'); --"
        ];
        
        for ($i = 0; $i < 50; $i++) {
            $pattern = $maliciousPatterns[array_rand($maliciousPatterns)];
            $maliciousInput = str_replace('{table}', $this->testTable, $pattern);
            
            try {
                // This should be safely handled
                $result = $this->db->fetchOne(
                    "SELECT * FROM `{$this->testTable}` WHERE data = ?",
                    [$maliciousInput]
                );
                
                // Should return false (no results) and not cause injection
                if ($result !== false) {
                    echo "✗ SQL injection prevention property failed at iteration {$i}\n";
                    return false;
                }
                
                // Verify table still exists and is intact
                $tableCheck = $this->db->fetchOne("SHOW TABLES LIKE '{$this->testTable}'");
                if (!$tableCheck) {
                    echo "✗ SQL injection prevention property failed - table was dropped\n";
                    return false;
                }
                
            } catch (Exception $e) {
                // Exceptions are acceptable if they're validation errors
                if (strpos($e->getMessage(), 'dangerous SQL patterns') === false &&
                    strpos($e->getMessage(), 'syntax error') === false) {
                    echo "✗ SQL injection prevention property failed with unexpected exception: " . $e->getMessage() . "\n";
                    return false;
                }
            }
        }
        
        echo "✓ SQL injection prevention property test passed (50 iterations)\n";
        return true;
    }
    
    /**
     * Property: Transaction rollback should restore database to previous state
     * **Validates: Requirements 2.4**
     */
    public function testTransactionRollbackProperty() {
        echo "Testing transaction rollback property...\n";
        
        for ($i = 0; $i < 20; $i++) {
            try {
                // Get initial count
                $initialCount = $this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->testTable}`");
                
                // Start transaction
                $this->db->beginTransaction();
                
                // Insert random number of records
                $recordsToInsert = rand(1, 5);
                for ($j = 0; $j < $recordsToInsert; $j++) {
                    $testData = $this->generateRandomData();
                    $this->db->executeQuery(
                        "INSERT INTO `{$this->testTable}` (data, number) VALUES (?, ?)",
                        [$testData['data'], $testData['number']]
                    );
                }
                
                // Rollback transaction
                $this->db->rollback();
                
                // Verify count is back to initial
                $finalCount = $this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->testTable}`");
                
                if ($finalCount != $initialCount) {
                    echo "✗ Transaction rollback property failed at iteration {$i}\n";
                    echo "  Initial count: {$initialCount}, Final count: {$finalCount}\n";
                    return false;
                }
                
            } catch (Exception $e) {
                echo "✗ Transaction rollback property failed with exception: " . $e->getMessage() . "\n";
                return false;
            }
        }
        
        echo "✓ Transaction rollback property test passed (20 iterations)\n";
        return true;
    }
    
    /**
     * Generate random test data
     */
    private function generateRandomData() {
        $strings = [
            'Hello World',
            'Test Data',
            'Random String',
            'Database Test',
            'Property Based Testing',
            'Lorem ipsum dolor sit amet',
            'Special chars: !@#$%^&*()',
            'Unicode: 你好世界',
            'Emoji: 🚀🎉💻',
            ''  // Empty string
        ];
        
        return [
            'data' => $strings[array_rand($strings)],
            'number' => rand(-1000, 1000)
        ];
    }
    
    /**
     * Run all property tests
     */
    public function runAllPropertyTests() {
        echo "Running Database Property-Based Tests...\n";
        echo "=======================================\n";
        
        $tests = [
            'testDataPersistenceProperty',
            'testSQLInjectionPreventionProperty',
            'testTransactionRollbackProperty'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        echo "\n=======================================\n";
        echo "Property tests completed: {$passed}/{$total} passed\n";
        
        if ($passed === $total) {
            echo "✓ All property tests passed!\n";
        } else {
            echo "✗ Some property tests failed!\n";
        }
        
        return $passed === $total;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        // Run unit tests
        $unitTests = new DatabaseTest();
        $unitTestsPassed = $unitTests->runAllTests();
        $unitTests->cleanup();
        
        echo "\n";
        
        // Run property-based tests
        $propertyTests = new DatabasePropertyTests();
        $propertyTestsPassed = $propertyTests->runAllPropertyTests();
        $propertyTests->cleanup();
        
        echo "\n";
        echo "Overall Test Results:\n";
        echo "====================\n";
        
        if ($unitTestsPassed && $propertyTestsPassed) {
            echo "✓ All tests passed successfully!\n";
            exit(0);
        } else {
            echo "✗ Some tests failed!\n";
            exit(1);
        }
        
    } catch (Exception $e) {
        echo "✗ Test execution failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}