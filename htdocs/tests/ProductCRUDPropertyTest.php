<?php
/**
 * Product CRUD Operations Property Test
 * 
 * Property-based tests for product CRUD operations to ensure universal correctness
 * properties hold across all valid inputs and operations.
 * 
 * Task: 7.4 Write property test for product CRUD operations
 * **Property 7: Product CRUD Operations**
 * **Validates: Requirements 5.1**
 */

// Set up test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

// Set test environment variables
putenv('APP_ENV=testing');
putenv('DB_HOST=localhost');
putenv('DB_NAME=riya_collections_test');
putenv('DB_USER=root');
putenv('DB_PASSWORD=');
putenv('JWT_SECRET=test_jwt_secret_for_testing_only_32_chars_minimum');

$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'riya_collections_test';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASSWORD'] = '';
$_ENV['JWT_SECRET'] = 'test_jwt_secret_for_testing_only_32_chars_minimum';

// Include dependencies
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';

class ProductCRUDPropertyTest {
    private $product;
    private $category;
    private $db;
    private $testProductIds = [];
    private $testCategoryIds = [];
    
    public function setUp() {
        $this->product = new Product();
        $this->category = new Category();
        $this->db = Database::getInstance();
        
        // Create required tables for testing
        $this->createTestTables();
        
        // Create test categories
        $this->createTestCategories();
        
        // Clean up any existing test data
        $this->cleanupTestData();
    }
    
    public function tearDown() {
        $this->cleanupTestData();
    }
    
    /**
     * Property Test: Product CRUD Operations Consistency
     * 
     * **Property 7: Product CRUD Operations**
     * For any valid product data, all CRUD operations (create, read, update, delete) 
     * should work identically and maintain data consistency.
     * **Validates: Requirements 5.1**
     */
    public function testProductCRUDOperationsConsistency() {
        echo "Testing Product CRUD Operations Consistency (Property 7)...\n";
        
        $iterations = 100;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random valid product data
                $productData = $this->generateRandomProductData();
                
                // Test CREATE operation
                $createdProduct = $this->product->createProduct($productData);
                $this->testProductIds[] = $createdProduct['id'];
                
                // Verify CREATE consistency
                $this->assert($createdProduct['name'] === $productData['name'], 'Created product name should match input');
                $this->assert($createdProduct['price'] == $productData['price'], 'Created product price should match input');
                $this->assert($createdProduct['stock_quantity'] == ($productData['stock_quantity'] ?? 0), 'Created product stock should match input');
                $this->assert(!empty($createdProduct['id']), 'Created product should have an ID');
                $this->assert(!empty($createdProduct['sku']), 'Created product should have a SKU');
                
                // Test READ operation
                $readProduct = $this->product->getProductById($createdProduct['id']);
                
                // Verify READ consistency
                $this->assert($readProduct !== null, 'Product should be readable after creation');
                $this->assert($readProduct['id'] === $createdProduct['id'], 'Read product ID should match created product');
                $this->assert($readProduct['name'] === $createdProduct['name'], 'Read product name should match created product');
                $this->assert($readProduct['price'] == $createdProduct['price'], 'Read product price should match created product');
                $this->assert($readProduct['sku'] === $createdProduct['sku'], 'Read product SKU should match created product');
                
                // Test UPDATE operation
                $updateData = $this->generateRandomUpdateData();
                $updatedProduct = $this->product->updateProduct($createdProduct['id'], $updateData);
                
                // Verify UPDATE consistency
                $this->assert($updatedProduct['id'] === $createdProduct['id'], 'Updated product ID should remain the same');
                if (isset($updateData['name'])) {
                    $this->assert($updatedProduct['name'] === $updateData['name'], 'Updated product name should match update data');
                }
                if (isset($updateData['price'])) {
                    $this->assert($updatedProduct['price'] == $updateData['price'], 'Updated product price should match update data');
                }
                
                // Verify UPDATE persistence
                $reReadProduct = $this->product->getProductById($createdProduct['id']);
                $this->assert($reReadProduct['name'] === $updatedProduct['name'], 'Updated data should persist after re-reading');
                $this->assert($reReadProduct['price'] == $updatedProduct['price'], 'Updated price should persist after re-reading');
                
                // Test stock operations
                $originalStock = $updatedProduct['stock_quantity'];
                $stockChange = rand(1, 50);
                
                // Test stock addition
                $this->product->updateStock($createdProduct['id'], $stockChange, 'add');
                $stockAfterAdd = $this->product->getProductById($createdProduct['id'])['stock_quantity'];
                $this->assert($stockAfterAdd == ($originalStock + $stockChange), 'Stock addition should be accurate');
                
                // Test stock subtraction
                $this->product->updateStock($createdProduct['id'], $stockChange, 'subtract');
                $stockAfterSubtract = $this->product->getProductById($createdProduct['id'])['stock_quantity'];
                $this->assert($stockAfterSubtract == $originalStock, 'Stock subtraction should restore original value');
                
                // Test DELETE operation (soft delete)
                $deleteResult = $this->product->deleteProduct($createdProduct['id']);
                
                // Verify DELETE consistency
                $this->assert($deleteResult === true, 'Delete operation should return true');
                
                $deletedProduct = $this->product->getProductById($createdProduct['id']);
                $this->assert($deletedProduct === null, 'Deleted product should not be readable');
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
                // Continue with next iteration
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 95, 'At least 95% of CRUD operations should succeed');
        echo "✓ Product CRUD Operations Consistency test passed\n";
    }
    
    /**
     * Property Test: Product Data Integrity
     * 
     * For any product operation, data integrity constraints should be maintained
     * (unique SKUs, valid prices, non-negative stock, etc.)
     */
    public function testProductDataIntegrity() {
        echo "Testing Product Data Integrity...\n";
        
        $iterations = 50;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Test unique SKU constraint
                $productData1 = $this->generateRandomProductData();
                $productData2 = $this->generateRandomProductData();
                $productData2['sku'] = $productData1['sku']; // Same SKU
                
                $product1 = $this->product->createProduct($productData1);
                $this->testProductIds[] = $product1['id'];
                
                // Second product with same SKU should fail
                try {
                    $product2 = $this->product->createProduct($productData2);
                    $this->testProductIds[] = $product2['id'];
                    $this->assert(false, 'Duplicate SKU should be rejected');
                } catch (Exception $e) {
                    $this->assert($e->getCode() === 409, 'Duplicate SKU should return 409 conflict');
                }
                
                // Test price validation
                try {
                    $invalidPriceData = $this->generateRandomProductData();
                    $invalidPriceData['price'] = -10.50; // Negative price
                    $this->product->createProduct($invalidPriceData);
                    $this->assert(false, 'Negative price should be rejected');
                } catch (Exception $e) {
                    $this->assert($e->getCode() === 400, 'Invalid price should return 400 validation error');
                }
                
                // Test stock quantity constraints
                $stockProduct = $this->product->createProduct($this->generateRandomProductData());
                $this->testProductIds[] = $stockProduct['id'];
                
                // Stock should not go negative
                try {
                    $this->product->updateStock($stockProduct['id'], 1000, 'subtract');
                    $this->assert(false, 'Stock should not go negative');
                } catch (Exception $e) {
                    $this->assert($e->getCode() === 400, 'Negative stock should return 400 error');
                }
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 90, 'At least 90% of data integrity tests should pass');
        echo "✓ Product Data Integrity test passed\n";
    }
    
    /**
     * Property Test: Product Search and Filter Consistency
     * 
     * For any search/filter criteria, results should be consistent and accurate
     */
    public function testProductSearchFilterConsistency() {
        echo "Testing Product Search and Filter Consistency...\n";
        
        // Create test products with known data
        $testProducts = [];
        for ($i = 0; $i < 20; $i++) {
            $productData = $this->generateRandomProductData();
            $productData['name'] = "TestProduct" . $i;
            $productData['brand'] = ($i % 3 === 0) ? 'TestBrand' : 'OtherBrand';
            $productData['price'] = 100 + ($i * 10); // Prices from 100 to 290
            
            $product = $this->product->createProduct($productData);
            $testProducts[] = $product;
            $this->testProductIds[] = $product['id'];
        }
        
        $iterations = 30;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Test search functionality
                $searchTerm = 'TestProduct';
                $searchResults = $this->product->searchProducts($searchTerm);
                
                // All results should contain the search term
                foreach ($searchResults['products'] as $product) {
                    $this->assert(
                        strpos($product['name'], $searchTerm) !== false ||
                        strpos($product['description'] ?? '', $searchTerm) !== false ||
                        strpos($product['brand'] ?? '', $searchTerm) !== false ||
                        strpos($product['sku'] ?? '', $searchTerm) !== false,
                        'Search results should contain the search term'
                    );
                }
                
                // Test brand filter
                $brandFilter = ['brand' => 'TestBrand'];
                $brandResults = $this->product->getProducts($brandFilter);
                
                foreach ($brandResults['products'] as $product) {
                    $this->assert($product['brand'] === 'TestBrand', 'Brand filter should return only matching brands');
                }
                
                // Test price range filter
                $priceFilter = ['min_price' => 150, 'max_price' => 250];
                $priceResults = $this->product->getProducts($priceFilter);
                
                foreach ($priceResults['products'] as $product) {
                    $this->assert(
                        $product['price'] >= 150 && $product['price'] <= 250,
                        'Price filter should return products within range'
                    );
                }
                
                // Test pagination consistency
                $page1 = $this->product->getProducts([], 1, 5);
                $page2 = $this->product->getProducts([], 2, 5);
                
                $this->assert($page1['pagination']['current_page'] === 1, 'Page 1 should have correct page number');
                $this->assert($page2['pagination']['current_page'] === 2, 'Page 2 should have correct page number');
                $this->assert(count($page1['products']) <= 5, 'Page 1 should have at most 5 products');
                $this->assert(count($page2['products']) <= 5, 'Page 2 should have at most 5 products');
                
                // Ensure no overlap between pages
                $page1Ids = array_column($page1['products'], 'id');
                $page2Ids = array_column($page2['products'], 'id');
                $overlap = array_intersect($page1Ids, $page2Ids);
                $this->assert(empty($overlap), 'Different pages should not have overlapping products');
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 90, 'At least 90% of search/filter tests should pass');
        echo "✓ Product Search and Filter Consistency test passed\n";
    }
    
    /**
     * Property Test: Product Validation Rules
     * 
     * For any product data, validation rules should be consistently applied
     */
    public function testProductValidationRules() {
        echo "Testing Product Validation Rules...\n";
        
        $iterations = 50;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Test required field validation
                $invalidData = [
                    [], // Empty data
                    ['name' => ''], // Empty name
                    ['name' => 'Test', 'price' => ''], // Empty price
                    ['name' => 'Test', 'price' => 'invalid'], // Invalid price format
                    ['name' => str_repeat('a', 300), 'price' => 100], // Name too long
                ];
                
                foreach ($invalidData as $data) {
                    try {
                        $this->product->createProduct($data);
                        $this->assert(false, 'Invalid product data should be rejected');
                    } catch (Exception $e) {
                        $this->assert($e->getCode() === 400, 'Invalid data should return 400 validation error');
                    }
                }
                
                // Test valid data acceptance
                $validData = $this->generateRandomProductData();
                $product = $this->product->createProduct($validData);
                $this->testProductIds[] = $product['id'];
                
                $this->assert(!empty($product['id']), 'Valid product data should be accepted');
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 95, 'At least 95% of validation tests should pass');
        echo "✓ Product Validation Rules test passed\n";
    }
    
    /**
     * Property Test: Product Stock Management
     * 
     * For any stock operations, quantities should be accurately tracked and constraints enforced
     */
    public function testProductStockManagement() {
        echo "Testing Product Stock Management...\n";
        
        $iterations = 30;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Create product with initial stock
                $productData = $this->generateRandomProductData();
                $initialStock = rand(10, 100);
                $productData['stock_quantity'] = $initialStock;
                
                $product = $this->product->createProduct($productData);
                $this->testProductIds[] = $product['id'];
                
                // Test stock operations
                $operations = [
                    ['operation' => 'add', 'quantity' => rand(1, 20)],
                    ['operation' => 'subtract', 'quantity' => rand(1, 10)],
                    ['operation' => 'set', 'quantity' => rand(50, 150)]
                ];
                
                $currentStock = $initialStock;
                
                foreach ($operations as $op) {
                    $this->product->updateStock($product['id'], $op['quantity'], $op['operation']);
                    
                    switch ($op['operation']) {
                        case 'add':
                            $currentStock += $op['quantity'];
                            break;
                        case 'subtract':
                            $currentStock -= $op['quantity'];
                            break;
                        case 'set':
                            $currentStock = $op['quantity'];
                            break;
                    }
                    
                    $updatedProduct = $this->product->getProductById($product['id']);
                    $this->assert(
                        $updatedProduct['stock_quantity'] == $currentStock,
                        "Stock quantity should match expected value after {$op['operation']} operation"
                    );
                }
                
                // Test stock constraints
                try {
                    $this->product->updateStock($product['id'], $currentStock + 1, 'subtract');
                    $this->assert(false, 'Stock should not go negative');
                } catch (Exception $e) {
                    $this->assert($e->getCode() === 400, 'Negative stock should return 400 error');
                }
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 90, 'At least 90% of stock management tests should pass');
        echo "✓ Product Stock Management test passed\n";
    }
    
    /**
     * Generate random valid product data for testing
     */
    private function generateRandomProductData() {
        $names = ['Laptop', 'Phone', 'Tablet', 'Watch', 'Camera', 'Headphones', 'Speaker', 'Monitor'];
        $brands = ['Apple', 'Samsung', 'Sony', 'Dell', 'HP', 'Lenovo', 'Canon', 'Nikon'];
        $descriptions = [
            'High-quality product with excellent features',
            'Premium device for professional use',
            'Affordable option with great value',
            'Latest technology with innovative design',
            'Durable and reliable product'
        ];
        
        return [
            'name' => $names[array_rand($names)] . ' ' . rand(1000, 9999),
            'description' => $descriptions[array_rand($descriptions)],
            'price' => round(rand(1000, 50000) / 100, 2), // Price between 10.00 and 500.00
            'stock_quantity' => rand(0, 100),
            'category_id' => !empty($this->testCategoryIds) ? $this->testCategoryIds[array_rand($this->testCategoryIds)] : null,
            'brand' => $brands[array_rand($brands)],
            'sku' => 'SKU' . rand(100000, 999999),
            'is_active' => true
        ];
    }
    
    /**
     * Generate random update data for testing
     */
    private function generateRandomUpdateData() {
        $updateFields = ['name', 'description', 'price', 'stock_quantity', 'brand'];
        $selectedFields = array_rand(array_flip($updateFields), rand(1, 3));
        
        if (!is_array($selectedFields)) {
            $selectedFields = [$selectedFields];
        }
        
        $updateData = [];
        
        foreach ($selectedFields as $field) {
            switch ($field) {
                case 'name':
                    $updateData['name'] = 'Updated Product ' . rand(1000, 9999);
                    break;
                case 'description':
                    $updateData['description'] = 'Updated description ' . rand(1000, 9999);
                    break;
                case 'price':
                    $updateData['price'] = round(rand(1000, 30000) / 100, 2);
                    break;
                case 'stock_quantity':
                    $updateData['stock_quantity'] = rand(0, 50);
                    break;
                case 'brand':
                    $brands = ['UpdatedBrand1', 'UpdatedBrand2', 'UpdatedBrand3'];
                    $updateData['brand'] = $brands[array_rand($brands)];
                    break;
            }
        }
        
        return $updateData;
    }
    
    /**
     * Create required database tables for testing
     */
    private function createTestTables() {
        try {
            // Create categories table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS categories (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            // Create products table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS products (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    price DECIMAL(10,2) NOT NULL,
                    stock_quantity INT DEFAULT 0,
                    category_id INT,
                    brand VARCHAR(100),
                    sku VARCHAR(100) UNIQUE,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            // Create product_images table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS product_images (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    product_id INT NOT NULL,
                    image_url VARCHAR(255) NOT NULL,
                    alt_text VARCHAR(255),
                    is_primary BOOLEAN DEFAULT FALSE,
                    sort_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
        } catch (Exception $e) {
            echo "Warning: Could not create test tables: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Create test categories
     */
    private function createTestCategories() {
        try {
            $categories = [
                ['name' => 'Electronics', 'description' => 'Electronic devices and gadgets'],
                ['name' => 'Clothing', 'description' => 'Apparel and fashion items'],
                ['name' => 'Books', 'description' => 'Books and publications'],
                ['name' => 'Home & Garden', 'description' => 'Home and garden products']
            ];
            
            foreach ($categories as $categoryData) {
                $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
                $this->db->executeQuery($sql, [$categoryData['name'], $categoryData['description']]);
                $this->testCategoryIds[] = $this->db->getConnection()->lastInsertId();
            }
            
        } catch (Exception $e) {
            // Categories might already exist, continue
        }
    }
    
    /**
     * Helper assertion method
     */
    private function assert($condition, $message) {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        try {
            // Clean up test products
            foreach ($this->testProductIds as $productId) {
                $this->db->executeQuery("DELETE FROM product_images WHERE product_id = ?", [$productId]);
                $this->db->executeQuery("DELETE FROM products WHERE id = ?", [$productId]);
            }
            
            // Clean up test categories
            foreach ($this->testCategoryIds as $categoryId) {
                $this->db->executeQuery("DELETE FROM categories WHERE id = ?", [$categoryId]);
            }
            
            // Clean up any test products by name pattern
            $this->db->executeQuery("DELETE FROM products WHERE name LIKE 'TestProduct%' OR name LIKE 'Updated Product%' OR sku LIKE 'SKU%'");
            
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        
        $this->testProductIds = [];
        $this->testCategoryIds = [];
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "Running Product CRUD Operations Property Tests...\n";
        echo "================================================\n\n";
        
        $this->setUp();
        
        try {
            $this->testProductCRUDOperationsConsistency();
            $this->testProductDataIntegrity();
            $this->testProductSearchFilterConsistency();
            $this->testProductValidationRules();
            $this->testProductStockManagement();
            
            echo "\n✅ All Product CRUD Operations property tests passed!\n";
            echo "   - CRUD Operations Consistency (Property 7) ✓\n";
            echo "   - Data Integrity Constraints ✓\n";
            echo "   - Search and Filter Consistency ✓\n";
            echo "   - Validation Rules ✓\n";
            echo "   - Stock Management ✓\n";
            
        } catch (Exception $e) {
            echo "\n❌ Property test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ProductCRUDPropertyTest();
    $test->runAllTests();
}