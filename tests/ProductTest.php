<?php
/**
 * Product Model Unit Tests
 * 
 * Comprehensive unit tests for the Product model covering CRUD operations,
 * search functionality, filtering, pagination, and stock management.
 * 
 * Requirements: 5.1, 5.2
 */

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/bootstrap.php';

/**
 * Product Unit Tests
 */
class ProductTest {
    private $product;
    private $db;
    private $testProductIds = [];
    private $testCategoryId;
    
    /**
     * Set up test environment
     */
    public function setUp() {
        $this->product = new Product();
        $this->db = Database::getInstance();
        
        // Create test category
        $this->createTestCategory();
        
        echo "Product test setup completed\n";
    }
    
    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Clean up test products
        foreach ($this->testProductIds as $productId) {
            try {
                $this->db->executeQuery("DELETE FROM product_images WHERE product_id = ?", [$productId]);
                $this->db->executeQuery("DELETE FROM products WHERE id = ?", [$productId]);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up test category
        if ($this->testCategoryId) {
            try {
                $this->db->executeQuery("DELETE FROM categories WHERE id = ?", [$this->testCategoryId]);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        echo "Product test cleanup completed\n";
    }
    
    /**
     * Test product creation with valid data
     */
    public function testCreateProductWithValidData() {
        $productData = [
            'name' => 'Test Product',
            'description' => 'This is a test product description',
            'price' => 29.99,
            'stock_quantity' => 100,
            'category_id' => $this->testCategoryId,
            'brand' => 'Test Brand',
            'sku' => 'TEST001'
        ];
        
        $result = $this->product->createProduct($productData);
        $this->testProductIds[] = $result['id'];
        
        // Assertions
        assert($result['id'] > 0, 'Product ID should be positive');
        assert($result['name'] === $productData['name'], 'Product name should match');
        assert($result['price'] === $productData['price'], 'Product price should match');
        assert($result['stock_quantity'] === $productData['stock_quantity'], 'Stock quantity should match');
        assert($result['sku'] === $productData['sku'], 'SKU should match');
        assert($result['is_active'] === true, 'Product should be active by default');
        
        echo "✓ Product creation with valid data test passed\n";
    }
    
    /**
     * Test product creation with minimal data
     */
    public function testCreateProductWithMinimalData() {
        $productData = [
            'name' => 'Minimal Product',
            'price' => 19.99
        ];
        
        $result = $this->product->createProduct($productData);
        $this->testProductIds[] = $result['id'];
        
        // Assertions
        assert($result['id'] > 0, 'Product ID should be positive');
        assert($result['name'] === $productData['name'], 'Product name should match');
        assert($result['price'] === $productData['price'], 'Product price should match');
        assert($result['stock_quantity'] === 0, 'Stock quantity should default to 0');
        assert(!empty($result['sku']), 'SKU should be auto-generated');
        assert($result['is_active'] === true, 'Product should be active by default');
        
        echo "✓ Product creation with minimal data test passed\n";
    }
    
    /**
     * Test product creation with invalid data
     */
    public function testCreateProductWithInvalidData() {
        $testCases = [
            [
                'data' => ['price' => 29.99], // Missing name
                'expected_error' => 'Product name is required'
            ],
            [
                'data' => ['name' => 'Test', 'price' => -10], // Negative price
                'expected_error' => 'Product price cannot be negative'
            ],
            [
                'data' => ['name' => 'Test', 'price' => 29.99, 'stock_quantity' => -5], // Negative stock
                'expected_error' => 'Stock quantity cannot be negative'
            ],
            [
                'data' => ['name' => str_repeat('A', 300), 'price' => 29.99], // Name too long
                'expected_error' => 'Product name is too long'
            ]
        ];
        
        foreach ($testCases as $testCase) {
            $exceptionThrown = false;
            try {
                $this->product->createProduct($testCase['data']);
            } catch (Exception $e) {
                $exceptionThrown = true;
                assert(strpos($e->getMessage(), $testCase['expected_error']) !== false, 
                       "Expected error message containing '{$testCase['expected_error']}', got: {$e->getMessage()}");
            }
            assert($exceptionThrown, 'Exception should be thrown for invalid data');
        }
        
        echo "✓ Product creation with invalid data test passed\n";
    }
    
    /**
     * Test duplicate SKU handling
     */
    public function testDuplicateSKUHandling() {
        // Create first product
        $productData1 = [
            'name' => 'First Product',
            'price' => 29.99,
            'sku' => 'DUPLICATE001'
        ];
        
        $result1 = $this->product->createProduct($productData1);
        $this->testProductIds[] = $result1['id'];
        
        // Try to create second product with same SKU
        $productData2 = [
            'name' => 'Second Product',
            'price' => 39.99,
            'sku' => 'DUPLICATE001'
        ];
        
        $exceptionThrown = false;
        try {
            $this->product->createProduct($productData2);
        } catch (Exception $e) {
            $exceptionThrown = true;
            assert($e->getCode() === 409, 'Should return 409 conflict status');
            assert(strpos($e->getMessage(), 'SKU already exists') !== false, 'Should mention SKU conflict');
        }
        
        assert($exceptionThrown, 'Exception should be thrown for duplicate SKU');
        
        echo "✓ Duplicate SKU handling test passed\n";
    }
    
    /**
     * Test product retrieval by ID
     */
    public function testGetProductById() {
        // Create test product
        $productData = [
            'name' => 'Retrievable Product',
            'description' => 'Product for retrieval testing',
            'price' => 49.99,
            'stock_quantity' => 50,
            'category_id' => $this->testCategoryId,
            'brand' => 'Retrieve Brand'
        ];
        
        $created = $this->product->createProduct($productData);
        $this->testProductIds[] = $created['id'];
        
        // Retrieve product
        $retrieved = $this->product->getProductById($created['id']);
        
        // Assertions
        assert($retrieved !== null, 'Product should be found');
        assert($retrieved['id'] === $created['id'], 'Product ID should match');
        assert($retrieved['name'] === $productData['name'], 'Product name should match');
        assert($retrieved['category_name'] !== null, 'Category name should be included');
        
        // Test non-existent product
        $nonExistent = $this->product->getProductById(999999);
        assert($nonExistent === null, 'Non-existent product should return null');
        
        echo "✓ Product retrieval by ID test passed\n";
    }
    
    /**
     * Test product update
     */
    public function testUpdateProduct() {
        // Create test product
        $productData = [
            'name' => 'Updatable Product',
            'price' => 29.99,
            'stock_quantity' => 100
        ];
        
        $created = $this->product->createProduct($productData);
        $this->testProductIds[] = $created['id'];
        
        // Update product
        $updateData = [
            'name' => 'Updated Product Name',
            'price' => 39.99,
            'description' => 'Updated description'
        ];
        
        $updated = $this->product->updateProduct($created['id'], $updateData);
        
        // Assertions
        assert($updated['name'] === $updateData['name'], 'Product name should be updated');
        assert($updated['price'] === $updateData['price'], 'Product price should be updated');
        assert($updated['description'] === $updateData['description'], 'Product description should be updated');
        assert($updated['stock_quantity'] === $created['stock_quantity'], 'Unchanged fields should remain the same');
        
        echo "✓ Product update test passed\n";
    }
    
    /**
     * Test product deletion (soft delete)
     */
    public function testDeleteProduct() {
        // Create test product
        $productData = [
            'name' => 'Deletable Product',
            'price' => 19.99
        ];
        
        $created = $this->product->createProduct($productData);
        $this->testProductIds[] = $created['id'];
        
        // Delete product
        $result = $this->product->deleteProduct($created['id']);
        assert($result === true, 'Delete operation should return true');
        
        // Verify product is not retrievable
        $retrieved = $this->product->getProductById($created['id']);
        assert($retrieved === null, 'Deleted product should not be retrievable');
        
        // Test deleting non-existent product
        $exceptionThrown = false;
        try {
            $this->product->deleteProduct(999999);
        } catch (Exception $e) {
            $exceptionThrown = true;
            assert($e->getCode() === 404, 'Should return 404 not found status');
        }
        
        assert($exceptionThrown, 'Exception should be thrown for non-existent product');
        
        echo "✓ Product deletion test passed\n";
    }
    
    /**
     * Test stock management
     */
    public function testStockManagement() {
        // Create test product
        $productData = [
            'name' => 'Stock Test Product',
            'price' => 29.99,
            'stock_quantity' => 100
        ];
        
        $created = $this->product->createProduct($productData);
        $this->testProductIds[] = $created['id'];
        
        // Test set operation
        $result = $this->product->updateStock($created['id'], 50, 'set');
        assert($result === true, 'Set stock operation should succeed');
        
        $updated = $this->product->getProductById($created['id']);
        assert($updated['stock_quantity'] === 50, 'Stock should be set to 50');
        
        // Test add operation
        $this->product->updateStock($created['id'], 25, 'add');
        $updated = $this->product->getProductById($created['id']);
        assert($updated['stock_quantity'] === 75, 'Stock should be 75 after adding 25');
        
        // Test subtract operation
        $this->product->updateStock($created['id'], 15, 'subtract');
        $updated = $this->product->getProductById($created['id']);
        assert($updated['stock_quantity'] === 60, 'Stock should be 60 after subtracting 15');
        
        // Test negative stock prevention
        $exceptionThrown = false;
        try {
            $this->product->updateStock($created['id'], 100, 'subtract');
        } catch (Exception $e) {
            $exceptionThrown = true;
            assert(strpos($e->getMessage(), 'Insufficient stock') !== false, 'Should prevent negative stock');
        }
        
        assert($exceptionThrown, 'Exception should be thrown for insufficient stock');
        
        echo "✓ Stock management test passed\n";
    }
    
    /**
     * Test product search and filtering
     */
    public function testProductSearchAndFiltering() {
        // Create test products
        $products = [
            [
                'name' => 'Red Lipstick',
                'price' => 25.99,
                'brand' => 'Beauty Brand',
                'category_id' => $this->testCategoryId,
                'stock_quantity' => 50
            ],
            [
                'name' => 'Blue Eyeshadow',
                'price' => 15.99,
                'brand' => 'Color Brand',
                'category_id' => $this->testCategoryId,
                'stock_quantity' => 30
            ],
            [
                'name' => 'Foundation Cream',
                'price' => 35.99,
                'brand' => 'Beauty Brand',
                'category_id' => $this->testCategoryId,
                'stock_quantity' => 0
            ]
        ];
        
        foreach ($products as $productData) {
            $created = $this->product->createProduct($productData);
            $this->testProductIds[] = $created['id'];
        }
        
        // Test search by name
        $searchResults = $this->product->searchProducts('Lipstick');
        assert(count($searchResults['products']) >= 1, 'Should find products matching search term');
        assert(strpos($searchResults['products'][0]['name'], 'Lipstick') !== false, 'Found product should contain search term');
        
        // Test filter by brand
        $brandResults = $this->product->getProducts(['brand' => 'Beauty Brand']);
        assert(count($brandResults['products']) >= 2, 'Should find products by brand');
        
        // Test filter by price range
        $priceResults = $this->product->getProducts(['min_price' => 20, 'max_price' => 30]);
        assert(count($priceResults['products']) >= 1, 'Should find products in price range');
        
        // Test filter by stock availability
        $stockResults = $this->product->getProducts(['in_stock' => true]);
        foreach ($stockResults['products'] as $product) {
            assert($product['stock_quantity'] > 0, 'All products should be in stock');
        }
        
        echo "✓ Product search and filtering test passed\n";
    }
    
    /**
     * Test pagination
     */
    public function testPagination() {
        // Create multiple test products
        for ($i = 1; $i <= 15; $i++) {
            $productData = [
                'name' => "Pagination Test Product {$i}",
                'price' => 10.00 + $i,
                'stock_quantity' => $i * 5
            ];
            
            $created = $this->product->createProduct($productData);
            $this->testProductIds[] = $created['id'];
        }
        
        // Test first page
        $page1 = $this->product->getProducts([], 1, 5);
        assert(count($page1['products']) === 5, 'First page should have 5 products');
        assert($page1['pagination']['current_page'] === 1, 'Current page should be 1');
        assert($page1['pagination']['per_page'] === 5, 'Per page should be 5');
        assert($page1['pagination']['total'] >= 15, 'Total should be at least 15');
        assert($page1['pagination']['has_next'] === true, 'Should have next page');
        assert($page1['pagination']['has_prev'] === false, 'Should not have previous page');
        
        // Test second page
        $page2 = $this->product->getProducts([], 2, 5);
        assert(count($page2['products']) === 5, 'Second page should have 5 products');
        assert($page2['pagination']['current_page'] === 2, 'Current page should be 2');
        assert($page2['pagination']['has_prev'] === true, 'Should have previous page');
        
        echo "✓ Pagination test passed\n";
    }
    
    /**
     * Test product statistics
     */
    public function testProductStatistics() {
        // Create test products with different categories and stock levels
        $testProducts = [
            ['name' => 'Stats Product 1', 'price' => 10.00, 'stock_quantity' => 0],
            ['name' => 'Stats Product 2', 'price' => 20.00, 'stock_quantity' => 5],
            ['name' => 'Stats Product 3', 'price' => 30.00, 'stock_quantity' => 50]
        ];
        
        foreach ($testProducts as $productData) {
            $created = $this->product->createProduct($productData);
            $this->testProductIds[] = $created['id'];
        }
        
        $stats = $this->product->getProductStats();
        
        // Assertions
        assert(isset($stats['total_products']), 'Should include total products count');
        assert(isset($stats['stock']), 'Should include stock statistics');
        assert(isset($stats['pricing']), 'Should include pricing statistics');
        assert(isset($stats['products_by_category']), 'Should include products by category');
        
        assert($stats['stock']['out_of_stock'] >= 1, 'Should count out of stock products');
        assert($stats['stock']['low_stock'] >= 1, 'Should count low stock products');
        assert($stats['pricing']['min_price'] > 0, 'Should have minimum price');
        assert($stats['pricing']['max_price'] >= $stats['pricing']['min_price'], 'Max price should be >= min price');
        
        echo "✓ Product statistics test passed\n";
    }
    
    /**
     * Test featured products
     */
    public function testFeaturedProducts() {
        // Create test products
        for ($i = 1; $i <= 5; $i++) {
            $productData = [
                'name' => "Featured Product {$i}",
                'price' => 20.00 + $i,
                'stock_quantity' => 10
            ];
            
            $created = $this->product->createProduct($productData);
            $this->testProductIds[] = $created['id'];
        }
        
        $featured = $this->product->getFeaturedProducts(3);
        
        // Assertions
        assert(count($featured) <= 3, 'Should return at most 3 products');
        foreach ($featured as $product) {
            assert($product['stock_quantity'] > 0, 'Featured products should be in stock');
            assert($product['is_active'] === true, 'Featured products should be active');
        }
        
        echo "✓ Featured products test passed\n";
    }
    
    /**
     * Test low stock products
     */
    public function testLowStockProducts() {
        // Create products with different stock levels
        $stockLevels = [0, 5, 15, 25];
        
        foreach ($stockLevels as $stock) {
            $productData = [
                'name' => "Stock Level {$stock} Product",
                'price' => 25.00,
                'stock_quantity' => $stock
            ];
            
            $created = $this->product->createProduct($productData);
            $this->testProductIds[] = $created['id'];
        }
        
        $lowStock = $this->product->getLowStockProducts(10);
        
        // Assertions
        foreach ($lowStock as $product) {
            assert($product['stock_quantity'] <= 10, 'All products should have low stock');
            assert($product['stock_quantity'] >= 0, 'Stock should not be negative');
        }
        
        echo "✓ Low stock products test passed\n";
    }
    
    /**
     * Test SKU generation
     */
    public function testSKUGeneration() {
        $productData = [
            'name' => 'SKU Generation Test Product',
            'price' => 29.99
            // No SKU provided - should be auto-generated
        ];
        
        $created = $this->product->createProduct($productData);
        $this->testProductIds[] = $created['id'];
        
        // Assertions
        assert(!empty($created['sku']), 'SKU should be auto-generated');
        assert(strlen($created['sku']) >= 6, 'SKU should have reasonable length');
        assert(preg_match('/^[A-Z0-9]+$/', $created['sku']), 'SKU should contain only uppercase letters and numbers');
        
        echo "✓ SKU generation test passed\n";
    }
    
    /**
     * Create test category
     */
    private function createTestCategory() {
        $sql = "INSERT INTO categories (name, description, is_active) VALUES (?, ?, ?)";
        $params = ['Test Category', 'Category for testing', true];
        
        $this->db->executeQuery($sql, $params);
        $this->testCategoryId = $this->db->getLastInsertId();
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Starting Product Model Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testCreateProductWithValidData();
            $this->testCreateProductWithMinimalData();
            $this->testCreateProductWithInvalidData();
            $this->testDuplicateSKUHandling();
            $this->testGetProductById();
            $this->testUpdateProduct();
            $this->testDeleteProduct();
            $this->testStockManagement();
            $this->testProductSearchAndFiltering();
            $this->testPagination();
            $this->testProductStatistics();
            $this->testFeaturedProducts();
            $this->testLowStockProducts();
            $this->testSKUGeneration();
            
            echo "\n✅ All Product Model tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ProductTest();
    $test->runAllTests();
}