<?php
/**
 * Database Property-Based Tests
 * 
 * Property-based tests for Database class functionality that can run
 * without requiring an actual database connection.
 * 
 * Requirements: 2.1, 2.2, 10.2
 */

require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Database.php';

/**
 * Database Property Tests
 */
class DatabasePropertyTest {
    
    /**
     * Property: SQL validation should consistently reject dangerous patterns
     * **Validates: Requirements 2.2, 10.2**
     */
    public function testSQLValidationProperty() {
        echo "Testing SQL validation property...\n";
        
        // Get access to the private validateQuery method
        $reflection = new ReflectionClass('Database');
        $validateMethod = $reflection->getMethod('validateQuery');
        $validateMethod->setAccessible(true);
        
        // Create a mock database instance for testing
        $db = $this->createMockDatabase();
        
        // Dangerous SQL patterns that should always be rejected
        $dangerousPatterns = [
            "SELECT * FROM users; DROP TABLE users; --",
            "SELECT * FROM users WHERE id = 1; DELETE FROM users; --",
            "SELECT * FROM users UNION SELECT * FROM passwords",
            "SELECT * FROM users WHERE name = 'test' OR '1'='1'",
            "INSERT INTO users VALUES (1, 'test'); DROP TABLE users; --",
            "UPDATE users SET name = 'test'; TRUNCATE TABLE users; --",
            "SELECT * FROM users WHERE id IN (SELECT id FROM users); ALTER TABLE users ADD COLUMN hacked INT; --"
        ];
        
        $rejectedCount = 0;
        $totalTests = 0;
        
        // Test each dangerous pattern multiple times with variations
        foreach ($dangerousPatterns as $basePattern) {
            for ($i = 0; $i < 10; $i++) {
                $totalTests++;
                
                // Create variations of the pattern
                $variations = [
                    $basePattern,
                    strtoupper($basePattern),
                    strtolower($basePattern),
                    str_replace(' ', '  ', $basePattern), // Double spaces
                    str_replace(';', ' ; ', $basePattern), // Spaces around semicolon
                ];
                
                foreach ($variations as $pattern) {
                    $totalTests++;
                    
                    try {
                        $validateMethod->invoke($db, $pattern);
                        // If no exception is thrown, the validation failed
                        echo "✗ SQL validation property failed - dangerous pattern not rejected: " . substr($pattern, 0, 50) . "...\n";
                        return false;
                    } catch (Exception $e) {
                        // Exception should be thrown for dangerous patterns
                        if (strpos($e->getMessage(), 'dangerous SQL patterns') !== false) {
                            $rejectedCount++;
                        } else {
                            echo "✗ SQL validation property failed - wrong exception type: " . $e->getMessage() . "\n";
                            return false;
                        }
                    }
                }
            }
        }
        
        echo "✓ SQL validation property test passed ({$rejectedCount}/{$totalTests} dangerous patterns rejected)\n";
        return true;
    }
    
    /**
     * Property: Parameter sanitization should consistently remove sensitive data
     * **Validates: Requirements 10.1**
     */
    public function testParameterSanitizationProperty() {
        echo "Testing parameter sanitization property...\n";
        
        // Get access to the private sanitizeParamsForLogging method
        $reflection = new ReflectionClass('Database');
        $sanitizeMethod = $reflection->getMethod('sanitizeParamsForLogging');
        $sanitizeMethod->setAccessible(true);
        
        $db = $this->createMockDatabase();
        
        // Test data with sensitive information
        $sensitiveTestCases = [
            ['password' => 'secret123', 'name' => 'John'],
            ['user_password' => 'mypass', 'email' => 'test@example.com'],
            ['token' => 'abc123token', 'id' => 1],
            ['api_key' => 'key123', 'status' => 'active'],
            ['secret_key' => 'topsecret', 'data' => 'public'],
            ['hash' => 'hash123', 'value' => 'normal'],
            ['PASSWORD' => 'CAPS_PASSWORD', 'info' => 'data'],
            ['jwt_token' => 'eyJ0eXAi...', 'user_id' => 42]
        ];
        
        foreach ($sensitiveTestCases as $testCase) {
            for ($i = 0; $i < 5; $i++) { // Test each case multiple times
                $sanitized = $sanitizeMethod->invoke($db, $testCase);
                
                // Check that sensitive fields are redacted
                foreach ($testCase as $key => $value) {
                    $keyLower = strtolower($key);
                    $isSensitive = false;
                    
                    $sensitiveKeys = ['password', 'token', 'secret', 'key', 'hash'];
                    foreach ($sensitiveKeys as $sensitiveKey) {
                        if (strpos($keyLower, $sensitiveKey) !== false) {
                            $isSensitive = true;
                            break;
                        }
                    }
                    
                    if ($isSensitive) {
                        if ($sanitized[$key] !== '[REDACTED]') {
                            echo "✗ Parameter sanitization property failed - sensitive field not redacted: {$key}\n";
                            return false;
                        }
                    } else {
                        if ($sanitized[$key] !== $value) {
                            echo "✗ Parameter sanitization property failed - non-sensitive field modified: {$key}\n";
                            return false;
                        }
                    }
                }
            }
        }
        
        echo "✓ Parameter sanitization property test passed\n";
        return true;
    }
    
    /**
     * Property: Query cache key generation should be consistent and unique
     * **Validates: Requirements 12.1**
     */
    public function testQueryCacheKeyProperty() {
        echo "Testing query cache key property...\n";
        
        // Get access to the private getCacheKey method
        $reflection = new ReflectionClass('Database');
        $cacheKeyMethod = $reflection->getMethod('getCacheKey');
        $cacheKeyMethod->setAccessible(true);
        
        $db = $this->createMockDatabase();
        
        // Test that identical queries produce identical cache keys
        $testQueries = [
            ['sql' => 'SELECT * FROM users', 'params' => []],
            ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1]],
            ['sql' => 'SELECT name, email FROM users WHERE age > ?', 'params' => [18]],
            ['sql' => 'INSERT INTO users (name, email) VALUES (?, ?)', 'params' => ['John', 'john@example.com']],
        ];
        
        foreach ($testQueries as $query) {
            $keys = [];
            
            // Generate cache key multiple times for the same query
            for ($i = 0; $i < 10; $i++) {
                $key = $cacheKeyMethod->invoke($db, $query['sql'], $query['params']);
                $keys[] = $key;
                
                // Verify key is a valid MD5 hash
                if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
                    echo "✗ Query cache key property failed - invalid key format: {$key}\n";
                    return false;
                }
            }
            
            // All keys should be identical
            $uniqueKeys = array_unique($keys);
            if (count($uniqueKeys) !== 1) {
                echo "✗ Query cache key property failed - inconsistent keys for same query\n";
                return false;
            }
        }
        
        // Test that different queries produce different cache keys
        $key1 = $cacheKeyMethod->invoke($db, 'SELECT * FROM users', []);
        $key2 = $cacheKeyMethod->invoke($db, 'SELECT * FROM products', []);
        $key3 = $cacheKeyMethod->invoke($db, 'SELECT * FROM users WHERE id = ?', [1]);
        
        if ($key1 === $key2 || $key1 === $key3 || $key2 === $key3) {
            echo "✗ Query cache key property failed - different queries produced same key\n";
            return false;
        }
        
        echo "✓ Query cache key property test passed\n";
        return true;
    }
    
    /**
     * Property: Query builder should produce valid SQL for any valid input combination
     * **Validates: Requirements 2.1**
     */
    public function testQueryBuilderProperty() {
        echo "Testing query builder property...\n";
        
        $testCases = [
            // Basic SELECT queries
            ['select' => '*', 'from' => 'users', 'expected_pattern' => '/^SELECT \* FROM users$/'],
            ['select' => 'id, name', 'from' => 'products', 'expected_pattern' => '/^SELECT id, name FROM products$/'],
            
            // SELECT with WHERE
            ['select' => '*', 'from' => 'users', 'where' => ['name', '=', 'John'], 'expected_pattern' => '/^SELECT \* FROM users WHERE name = \?$/'],
            
            // SELECT with ORDER BY
            ['select' => '*', 'from' => 'users', 'orderBy' => ['name', 'ASC'], 'expected_pattern' => '/^SELECT \* FROM users ORDER BY name ASC$/'],
            
            // SELECT with LIMIT
            ['select' => '*', 'from' => 'users', 'limit' => 10, 'expected_pattern' => '/^SELECT \* FROM users LIMIT 10$/'],
            
            // Complex queries
            ['select' => 'u.name, p.title', 'from' => 'users u', 'join' => ['posts p', 'u.id = p.user_id'], 'where' => ['u.active', '=', 1], 'orderBy' => ['u.name', 'ASC'], 'limit' => 5, 'expected_pattern' => '/^SELECT u\.name, p\.title FROM users u INNER JOIN posts p ON u\.id = p\.user_id WHERE u\.active = \? ORDER BY u\.name ASC LIMIT 5$/']
        ];
        
        foreach ($testCases as $testCase) {
            for ($i = 0; $i < 3; $i++) { // Test each case multiple times
                $qb = new QueryBuilder();
                
                // Build query based on test case
                if (isset($testCase['select'])) {
                    $qb->select($testCase['select']);
                }
                if (isset($testCase['from'])) {
                    $qb->from($testCase['from']);
                }
                if (isset($testCase['join'])) {
                    $qb->join($testCase['join'][0], $testCase['join'][1]);
                }
                if (isset($testCase['where'])) {
                    $qb->where($testCase['where'][0], $testCase['where'][1], $testCase['where'][2]);
                }
                if (isset($testCase['orderBy'])) {
                    $qb->orderBy($testCase['orderBy'][0], $testCase['orderBy'][1]);
                }
                if (isset($testCase['limit'])) {
                    $qb->limit($testCase['limit']);
                }
                
                $sql = $qb->build();
                
                // Verify SQL matches expected pattern
                if (!preg_match($testCase['expected_pattern'], $sql)) {
                    echo "✗ Query builder property failed - SQL doesn't match pattern\n";
                    echo "  Expected pattern: {$testCase['expected_pattern']}\n";
                    echo "  Generated SQL: {$sql}\n";
                    return false;
                }
            }
        }
        
        echo "✓ Query builder property test passed\n";
        return true;
    }
    
    /**
     * Create a mock Database instance for testing
     */
    private function createMockDatabase() {
        // Use reflection to create instance without calling constructor
        $reflection = new ReflectionClass('Database');
        $instance = $reflection->newInstanceWithoutConstructor();
        
        return $instance;
    }
    
    /**
     * Run all property tests
     */
    public function runAllPropertyTests() {
        echo "Running Database Property-Based Tests...\n";
        echo "=======================================\n";
        
        $tests = [
            'testSQLValidationProperty',
            'testParameterSanitizationProperty',
            'testQueryCacheKeyProperty',
            'testQueryBuilderProperty'
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
            return true;
        } else {
            echo "✗ Some property tests failed!\n";
            return false;
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $tests = new DatabasePropertyTest();
        $result = $tests->runAllPropertyTests();
        
        if ($result) {
            echo "\n✓ Database class passes all property-based tests!\n";
            exit(0);
        } else {
            echo "\n✗ Database class failed some property-based tests!\n";
            exit(1);
        }
        
    } catch (Exception $e) {
        echo "✗ Test execution failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}