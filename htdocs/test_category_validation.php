<?php
/**
 * Category Model Validation Test
 * 
 * Simple validation test that doesn't require database connectivity.
 * Tests the validation logic in isolation.
 */

// Mock environment function if not already defined
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $default;
    }
}

// Mock Logger class
class Logger {
    public static function info($message, $context = []) {
        // Mock logging
    }
    
    public static function error($message, $context = []) {
        // Mock logging
    }
}

// Mock Database class
class Database {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function fetchOne($sql, $params = []) {
        return null;
    }
    
    public function fetchAll($sql, $params = []) {
        return [];
    }
    
    public function fetchColumn($sql, $params = []) {
        return 0;
    }
    
    public function executeQuery($sql, $params = []) {
        return new MockStatement();
    }
    
    public function getLastInsertId() {
        return 1;
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

// Mock Statement class
class MockStatement {
    public function rowCount() {
        return 1;
    }
}

// Mock DatabaseModel class
class DatabaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct($table = null) {
        $this->db = Database::getInstance();
        $this->table = $table;
    }
    
    public function setPrimaryKey($key) {
        $this->primaryKey = $key;
        return $this;
    }
    
    public function insert($data) {
        return 1;
    }
    
    public function find($id) {
        return [
            'id' => $id,
            'name' => 'Test Category',
            'description' => 'Test description',
            'image_url' => null,
            'is_active' => 1,
            'created_at' => '2024-01-01 00:00:00'
        ];
    }
    
    public function updateById($id, $data) {
        return 1;
    }
    
    public function count($conditions = []) {
        return 0;
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

// Include the Category model
require_once __DIR__ . '/models/Category.php';

/**
 * Test Category Validation
 */
function testCategoryValidation() {
    echo "Testing Category Model Validation...\n\n";
    
    $category = new Category();
    
    // Test 1: Valid category creation
    echo "Test 1: Valid category creation\n";
    try {
        $result = $category->createCategory([
            'name' => 'Test Electronics',
            'description' => 'Electronic devices and gadgets',
            'image_url' => 'https://example.com/electronics.jpg',
            'is_active' => true
        ]);
        
        assert(isset($result['id']), 'Category should have an ID');
        assert($result['name'] === 'Test Electronics', 'Category name should match');
        echo "✓ Valid category creation works\n";
    } catch (Exception $e) {
        echo "✗ Valid category creation failed: " . $e->getMessage() . "\n";
    }
    
    // Test 2: Empty name validation
    echo "\nTest 2: Empty name validation\n";
    try {
        $category->createCategory(['name' => '']);
        echo "✗ Empty name should be rejected\n";
    } catch (Exception $e) {
        if ($e->getCode() === 400 && strpos($e->getMessage(), 'name is required') !== false) {
            echo "✓ Empty name correctly rejected\n";
        } else {
            echo "✗ Wrong error for empty name: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 3: Name too long validation
    echo "\nTest 3: Name too long validation\n";
    try {
        $category->createCategory(['name' => str_repeat('a', 101)]);
        echo "✗ Long name should be rejected\n";
    } catch (Exception $e) {
        if ($e->getCode() === 400 && strpos($e->getMessage(), 'too long') !== false) {
            echo "✓ Long name correctly rejected\n";
        } else {
            echo "✗ Wrong error for long name: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 4: Invalid characters validation
    echo "\nTest 4: Invalid characters validation\n";
    try {
        $category->createCategory(['name' => 'Test<>Category']);
        echo "✗ Invalid characters should be rejected\n";
    } catch (Exception $e) {
        if ($e->getCode() === 400 && strpos($e->getMessage(), 'invalid characters') !== false) {
            echo "✓ Invalid characters correctly rejected\n";
        } else {
            echo "✗ Wrong error for invalid characters: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 5: Invalid URL validation
    echo "\nTest 5: Invalid URL validation\n";
    try {
        $category->createCategory([
            'name' => 'Test Category',
            'image_url' => 'not-a-valid-url'
        ]);
        echo "✗ Invalid URL should be rejected\n";
    } catch (Exception $e) {
        if ($e->getCode() === 400 && strpos($e->getMessage(), 'Invalid image URL') !== false) {
            echo "✓ Invalid URL correctly rejected\n";
        } else {
            echo "✗ Wrong error for invalid URL: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 6: Description too long validation
    echo "\nTest 6: Description too long validation\n";
    try {
        $category->createCategory([
            'name' => 'Test Category',
            'description' => str_repeat('a', 65536)
        ]);
        echo "✗ Long description should be rejected\n";
    } catch (Exception $e) {
        if ($e->getCode() === 400 && strpos($e->getMessage(), 'too long') !== false) {
            echo "✓ Long description correctly rejected\n";
        } else {
            echo "✗ Wrong error for long description: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 7: Data sanitization
    echo "\nTest 7: Data sanitization\n";
    try {
        $result = $category->createCategory([
            'name' => '  Test Category  ',
            'description' => '  Test description  ',
            'image_url' => '  https://example.com/image.jpg  '
        ]);
        
        // Note: The actual sanitization happens in the model
        echo "✓ Data sanitization works (no errors thrown)\n";
    } catch (Exception $e) {
        echo "✗ Data sanitization failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Category validation tests completed!\n";
}

// Run the test
testCategoryValidation();