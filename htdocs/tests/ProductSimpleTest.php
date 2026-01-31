<?php
/**
 * Simple Product Model Tests
 * 
 * Basic tests for the Product model that don't rely on complex database setup.
 * These tests focus on core functionality and validation logic.
 */

// Set up minimal test environment
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

// Mock Logger class to avoid dependency issues
class Logger {
    public static function info($message, $context = []) {
        // Silent for tests
    }
    
    public static function error($message, $context = []) {
        // Silent for tests
    }
    
    public static function warning($message, $context = []) {
        // Silent for tests
    }
    
    public static function debug($message, $context = []) {
        // Silent for tests
    }
}

// Mock Database class for testing
class Database {
    private static $instance = null;
    private $lastInsertId = 1;
    private $mockData = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function executeQuery($sql, $params = []) {
        // Mock implementation
        return new MockStatement();
    }
    
    public function fetchAll($sql, $params = []) {
        return [];
    }
    
    public function fetchOne($sql, $params = []) {
        return null;
    }
    
    public function fetchColumn($sql, $params = []) {
        return 0;
    }
    
    public function getLastInsertId() {
        return $this->lastInsertId++;
    }
    
    public function beginTransaction() {
        return true;
    }
    
    public function commit() {
        return true;
    }
    
    public function rollback() {
        return true;
    }
}

class MockStatement {
    public function rowCount() {
        return 1;
    }
    
    public function fetchAll() {
        return [];
    }
    
    public function fetch() {
        return null;
    }
}

// Mock DatabaseModel class
class DatabaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct($table = null) {
        $this->table = $table;
        $this->db = Database::getInstance();
    }
    
    public function setTable($table) {
        $this->table = $table;
        return $this;
    }
    
    public function setPrimaryKey($key) {
        $this->primaryKey = $key;
        return $this;
    }
    
    public function insert($data) {
        return $this->db->getLastInsertId();
    }
    
    public function find($id) {
        return [
            'id' => $id,
            'name' => 'Test Product',
            'price' => 29.99,
            'stock_quantity' => 100,
            'sku' => 'TEST001',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function updateById($id, $data) {
        return true;
    }
    
    public function exists($conditions) {
        return false;
    }
    
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    public function commit() {
        return $this->db->commit();
    }
    
    public function rollback() {
        return $this->db->rollback();
    }
}

// Include the Product model
require_once __DIR__ . '/../models/Product.php';

/**
 * Simple Product Tests
 */
class ProductSimpleTest {
    private $product;
    
    public function setUp() {
        $this->product = new Product();
        echo "Product simple test setup completed\n";
    }
    
    /**
     * Test product data validation
     */
    public function testProductValidation() {
        // Test valid product data
        $validData = [
            'name' => 'Test Product',
            'price' => 29.99,
            'stock_quantity' => 100
        ];
        
        try {
            // This should not throw an exception
            $reflection = new ReflectionClass($this->product);
            $method = $reflection->getMethod('validateProductData');
            $method->setAccessible(true);
            $method->invoke($this->product, $validData, true);
            echo "✓ Valid product data validation passed\n";
        } catch (Exception $e) {
            echo "✗ Valid product data validation failed: " . $e->getMessage() . "\n";
            return false;
        }
        
        // Test invalid product data
        $invalidData = [
            'price' => 29.99 // Missing required name
        ];
        
        try {
            $reflection = new ReflectionClass($this->product);
            $method = $reflection->getMethod('validateProductData');
            $method->setAccessible(true);
            $method->invoke($this->product, $invalidData, true);
            echo "✗ Invalid product data validation should have failed\n";
            return false;
        } catch (Exception $e) {
            echo "✓ Invalid product data validation correctly failed\n";
        }
        
        return true;
    }
    
    /**
     * Test SKU generation
     */
    public function testSKUGeneration() {
        try {
            $reflection = new ReflectionClass($this->product);
            $method = $reflection->getMethod('generateSKU');
            $method->setAccessible(true);
            
            $sku1 = $method->invoke($this->product, 'Test Product');
            $sku2 = $method->invoke($this->product, 'Another Product');
            
            // SKUs should be different
            assert($sku1 !== $sku2, 'Generated SKUs should be unique');
            
            // SKUs should have reasonable length
            assert(strlen($sku1) >= 6, 'SKU should have reasonable length');
            assert(strlen($sku2) >= 6, 'SKU should have reasonable length');
            
            // SKUs should contain only valid characters
            assert(preg_match('/^[A-Z0-9]+$/', $sku1), 'SKU should contain only uppercase letters and numbers');
            assert(preg_match('/^[A-Z0-9]+$/', $sku2), 'SKU should contain only uppercase letters and numbers');
            
            echo "✓ SKU generation test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ SKU generation test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test price validation
     */
    public function testPriceValidation() {
        $testCases = [
            ['price' => 0.01, 'should_pass' => true],
            ['price' => 29.99, 'should_pass' => true],
            ['price' => 999999.99, 'should_pass' => true],
            ['price' => -10.00, 'should_pass' => false],
            ['price' => 1000000.00, 'should_pass' => false]
        ];
        
        $reflection = new ReflectionClass($this->product);
        $method = $reflection->getMethod('validateProductData');
        $method->setAccessible(true);
        
        foreach ($testCases as $testCase) {
            $productData = [
                'name' => 'Test Product',
                'price' => $testCase['price']
            ];
            
            try {
                $method->invoke($this->product, $productData, true);
                
                if (!$testCase['should_pass']) {
                    echo "✗ Price validation should have failed for price: {$testCase['price']}\n";
                    return false;
                }
            } catch (Exception $e) {
                if ($testCase['should_pass']) {
                    echo "✗ Price validation should have passed for price: {$testCase['price']}\n";
                    return false;
                }
            }
        }
        
        echo "✓ Price validation test passed\n";
        return true;
    }
    
    /**
     * Test product data sanitization
     */
    public function testProductDataSanitization() {
        $rawProductData = [
            'id' => '123',
            'name' => 'Test Product',
            'price' => '29.99',
            'stock_quantity' => '100',
            'category_id' => '5',
            'is_active' => '1',
            'created_at' => '2023-01-01 12:00:00',
            'updated_at' => '2023-01-01 12:00:00'
        ];
        
        try {
            $reflection = new ReflectionClass($this->product);
            $method = $reflection->getMethod('sanitizeProductData');
            $method->setAccessible(true);
            
            $sanitized = $method->invoke($this->product, $rawProductData);
            
            // Check data types
            assert(is_int($sanitized['id']), 'ID should be integer');
            assert(is_float($sanitized['price']), 'Price should be float');
            assert(is_int($sanitized['stock_quantity']), 'Stock quantity should be integer');
            assert(is_int($sanitized['category_id']), 'Category ID should be integer');
            assert(is_bool($sanitized['is_active']), 'Is active should be boolean');
            assert(is_bool($sanitized['in_stock']), 'In stock should be boolean');
            
            // Check computed fields
            assert(isset($sanitized['formatted_price']), 'Should include formatted price');
            assert($sanitized['in_stock'] === ($sanitized['stock_quantity'] > 0), 'In stock should match stock quantity');
            
            echo "✓ Product data sanitization test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Product data sanitization test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test order by clause building
     */
    public function testOrderByBuilding() {
        try {
            $reflection = new ReflectionClass($this->product);
            $method = $reflection->getMethod('buildOrderBy');
            $method->setAccessible(true);
            
            // Test valid sort options
            $validSorts = [
                'name_asc' => 'p.name ASC',
                'name_desc' => 'p.name DESC',
                'price_asc' => 'p.price ASC',
                'price_desc' => 'p.price DESC',
                'created_asc' => 'p.created_at ASC',
                'created_desc' => 'p.created_at DESC'
            ];
            
            foreach ($validSorts as $sort => $expected) {
                $result = $method->invoke($this->product, $sort);
                assert($result === $expected, "Sort '{$sort}' should return '{$expected}', got '{$result}'");
            }
            
            // Test invalid sort (should return default)
            $result = $method->invoke($this->product, 'invalid_sort');
            assert($result === 'p.created_at DESC', 'Invalid sort should return default');
            
            // Test null sort (should return default)
            $result = $method->invoke($this->product, null);
            assert($result === 'p.created_at DESC', 'Null sort should return default');
            
            echo "✓ Order by building test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Order by building test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test product creation with mocked database
     */
    public function testProductCreationMocked() {
        try {
            $productData = [
                'name' => 'Test Product',
                'description' => 'Test description',
                'price' => 29.99,
                'stock_quantity' => 100,
                'brand' => 'Test Brand'
            ];
            
            // This will use the mocked database
            $result = $this->product->createProduct($productData);
            
            // Basic assertions
            assert(isset($result['id']), 'Result should have ID');
            assert($result['id'] > 0, 'ID should be positive');
            
            echo "✓ Product creation (mocked) test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Product creation (mocked) test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Starting Product Simple Tests...\n\n";
        
        $this->setUp();
        
        $tests = [
            'testProductValidation',
            'testSKUGeneration',
            'testPriceValidation',
            'testProductDataSanitization',
            'testOrderByBuilding',
            'testProductCreationMocked'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        echo "\n";
        if ($passed === $total) {
            echo "✅ All Product Simple tests passed! ({$passed}/{$total})\n";
        } else {
            echo "❌ Some tests failed. Passed: {$passed}/{$total}\n";
        }
        
        return $passed === $total;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ProductSimpleTest();
    $test->runAllTests();
}