<?php
/**
 * Product Model Property-Based Tests
 * 
 * Property-based tests for the Product model that verify universal properties
 * hold across all valid inputs using random data generation.
 * 
 * Requirements: 5.1, 5.2
 */

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/bootstrap.php';

/**
 * Product Property-Based Tests
 */
class ProductPropertyTest {
    private $product;
    private $db;
    private $testProductIds = [];
    private $testCategoryId;
    private $iterations = 100;
    
    /**
     * Set up test environment
     */
    public function setUp() {
        $this->product = new Product();
        $this->db = Database::getInstance();
        
        // Create test category
        $this->createTestCategory();
        
        echo "Product property test setup completed\n";
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
        
        echo "Product property test cleanup completed\n";
    }
    
    /**
     * Property 7: Product CRUD Operations
     * **Validates: Requirements 5.1**
     * 
     * For any valid product data, all CRUD operations (create, read, update, delete) 
     * should work identically between both systems
     */
    public function testProductCRUDOperationsProperty() {
        echo "Testing Property 7: Product CRUD Operations...\n";
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $productData = $this->generateValidProductData();
            
            try {
                // CREATE: Create product
                $created = $this->product->createProduct($productData);
                $this->testProductIds[] = $created['id'];
                
                // Verify creation properties
                assert($created['id'] > 0, "Created product should have positive ID");
                assert($created['name'] === $productData['name'], "Created product name should match input");
                assert(abs($created['price'] - $productData['price']) < 0.01, "Created product price should match input");
                assert($created['is_active'] === true, "Created product should be active by default");
                
                // READ: Retrieve product
                $retrieved = $this->product->getProductById($created['id']);
                assert($retrieved !== null, "Created product should be retrievable");
                assert($retrieved['id'] === $created['id'], "Retrieved product ID should match");
                assert($retrieved['name'] === $created['name'], "Retrieved product data should match created data");
                
                // UPDATE: Update product
                $updateData = $this->generateValidUpdateData();
                $updated = $this->product->updateProduct($created['id'], $updateData);
                
                // Verify update properties
                foreach ($updateData as $field => $value) {
                    if ($field === 'price') {
                        assert(abs($updated[$field] - $value) < 0.01, "Updated field '{$field}' should match new value");
                    } else {
                        assert($updated[$field] === $value, "Updated field '{$field}' should match new value");
                    }
                }
                
                // DELETE: Delete product (soft delete)
                $deleted = $this->product->deleteProduct($created['id']);
                assert($deleted === true, "Delete operation should return true");
                
                // Verify deletion properties
                $afterDelete = $this->product->getProductById($created['id']);
                assert($afterDelete === null, "Deleted product should not be retrievable");
                
            } catch (Exception $e) {
                echo "CRUD operation failed on iteration {$i}: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Property 7: Product CRUD Operations test passed ({$this->iterations} iterations)\n";
    }
    
    /**
     * Property 8: Product Query Consistency
     * **Validates: Requirements 5.2**
     * 
     * For any combination of search, filter, sort, and pagination parameters, 
     * both systems should return equivalent product results
     */
    public function testProductQueryConsistencyProperty() {
        echo "Testing Property 8: Product Query Consistency...\n";
        
        // Create a set of test products with varied data
        $testProducts = [];
        for ($i = 0; $i < 20; $i++) {
            $productData = $this->generateValidProductData();
            $created = $this->product->createProduct($productData);
            $this->testProductIds[] = $created['id'];
            $testProducts[] = $created;
        }
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $filters = $this->generateRandomFilters();
            $page = mt_rand(1, 3);
            $perPage = mt_rand(5, 10);
            
            try {
                $results = $this->product->getProducts($filters, $page, $perPage);
                
                // Verify query consistency properties
                assert(is_array($results), "Query results should be an array");
                assert(isset($results['products']), "Results should contain products array");
                assert(isset($results['pagination']), "Results should contain pagination info");
                
                // Verify pagination properties
                $pagination = $results['pagination'];
                assert($pagination['current_page'] === $page, "Current page should match requested page");
                assert($pagination['per_page'] === $perPage, "Per page should match requested per page");
                assert($pagination['total'] >= 0, "Total count should be non-negative");
                assert(count($results['products']) <= $perPage, "Returned products should not exceed per page limit");
                
                // Verify filter application properties
                foreach ($results['products'] as $product) {
                    $this->verifyProductMatchesFilters($product, $filters);
                }
                
                // Verify sorting consistency
                if (!empty($filters['sort'])) {
                    $this->verifySortingConsistency($results['products'], $filters['sort']);
                }
                
            } catch (Exception $e) {
                echo "Query consistency failed on iteration {$i} with filters: " . json_encode($filters) . "\n";
                echo "Error: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Property 8: Product Query Consistency test passed ({$this->iterations} iterations)\n";
    }
    
    /**
     * Property: Stock Management Consistency
     * 
     * For any stock operation, the resulting stock quantity should be mathematically correct
     * and stock should never go negative
     */
    public function testStockManagementConsistencyProperty() {
        echo "Testing Property: Stock Management Consistency...\n";
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $productData = $this->generateValidProductData();
            $productData['stock_quantity'] = mt_rand(50, 200); // Start with reasonable stock
            
            try {
                $created = $this->product->createProduct($productData);
                $this->testProductIds[] = $created['id'];
                
                $initialStock = $created['stock_quantity'];
                $operations = $this->generateStockOperations($initialStock);
                
                $expectedStock = $initialStock;
                
                foreach ($operations as $operation) {
                    $operationType = $operation['type'];
                    $quantity = $operation['quantity'];
                    
                    // Calculate expected stock
                    switch ($operationType) {
                        case 'set':
                            $expectedStock = $quantity;
                            break;
                        case 'add':
                            $expectedStock += $quantity;
                            break;
                        case 'subtract':
                            $expectedStock = max(0, $expectedStock - $quantity); // Should not go negative
                            break;
                    }
                    
                    // Perform operation
                    if ($operationType === 'subtract' && ($expectedStock < 0 || $quantity > $expectedStock + $quantity)) {
                        // This should fail
                        $exceptionThrown = false;
                        try {
                            $this->product->updateStock($created['id'], $quantity, $operationType);
                        } catch (Exception $e) {
                            $exceptionThrown = true;
                            assert(strpos($e->getMessage(), 'Insufficient stock') !== false, 
                                   "Should throw insufficient stock error");
                        }
                        assert($exceptionThrown, "Should throw exception for insufficient stock");
                        
                        // Reset expected stock since operation failed
                        $expectedStock += $quantity; // Undo the subtraction
                    } else {
                        // This should succeed
                        $result = $this->product->updateStock($created['id'], $quantity, $operationType);
                        assert($result === true, "Stock operation should succeed");
                        
                        // Verify stock quantity
                        $updated = $this->product->getProductById($created['id']);
                        assert($updated['stock_quantity'] === $expectedStock, 
                               "Stock quantity should match expected value. Expected: {$expectedStock}, Got: {$updated['stock_quantity']}");
                        assert($updated['stock_quantity'] >= 0, "Stock quantity should never be negative");
                    }
                }
                
            } catch (Exception $e) {
                echo "Stock management consistency failed on iteration {$i}: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Property: Stock Management Consistency test passed ({$this->iterations} iterations)\n";
    }
    
    /**
     * Property: SKU Uniqueness
     * 
     * For any set of products, all SKUs should be unique within active products
     */
    public function testSKUUniquenessProperty() {
        echo "Testing Property: SKU Uniqueness...\n";
        
        $skus = [];
        
        for ($i = 0; $i < min($this->iterations, 50); $i++) { // Limit to avoid too many products
            $productData = $this->generateValidProductData();
            
            // Sometimes provide SKU, sometimes let it auto-generate
            if (mt_rand(0, 1)) {
                $productData['sku'] = $this->generateRandomSKU();
            }
            
            try {
                $created = $this->product->createProduct($productData);
                $this->testProductIds[] = $created['id'];
                
                // Verify SKU uniqueness
                assert(!in_array($created['sku'], $skus), "SKU should be unique: {$created['sku']}");
                $skus[] = $created['sku'];
                
                // Verify SKU format
                assert(!empty($created['sku']), "SKU should not be empty");
                assert(strlen($created['sku']) <= 50, "SKU should not exceed 50 characters");
                assert(preg_match('/^[A-Za-z0-9\-_]+$/', $created['sku']), 
                       "SKU should only contain alphanumeric characters, hyphens, and underscores");
                
            } catch (Exception $e) {
                // If it's a duplicate SKU error, that's expected behavior
                if (strpos($e->getMessage(), 'SKU already exists') !== false) {
                    // This is correct behavior - SKU uniqueness is enforced
                    continue;
                } else {
                    echo "SKU uniqueness test failed on iteration {$i}: " . $e->getMessage() . "\n";
                    throw $e;
                }
            }
        }
        
        echo "✓ Property: SKU Uniqueness test passed\n";
    }
    
    /**
     * Property: Price Validation Consistency
     * 
     * For any price value, validation should be consistent and prices should be non-negative
     */
    public function testPriceValidationConsistencyProperty() {
        echo "Testing Property: Price Validation Consistency...\n";
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $price = $this->generateRandomPrice();
            
            $productData = [
                'name' => 'Price Test Product ' . $i,
                'price' => $price
            ];
            
            try {
                if ($price < 0) {
                    // Negative prices should be rejected
                    $exceptionThrown = false;
                    try {
                        $this->product->createProduct($productData);
                    } catch (Exception $e) {
                        $exceptionThrown = true;
                        assert(strpos($e->getMessage(), 'cannot be negative') !== false, 
                               "Should reject negative prices");
                    }
                    assert($exceptionThrown, "Should throw exception for negative price");
                    
                } elseif ($price > 999999.99) {
                    // Extremely high prices should be rejected
                    $exceptionThrown = false;
                    try {
                        $this->product->createProduct($productData);
                    } catch (Exception $e) {
                        $exceptionThrown = true;
                        assert(strpos($e->getMessage(), 'too high') !== false, 
                               "Should reject extremely high prices");
                    }
                    assert($exceptionThrown, "Should throw exception for extremely high price");
                    
                } else {
                    // Valid prices should be accepted
                    $created = $this->product->createProduct($productData);
                    $this->testProductIds[] = $created['id'];
                    
                    assert(abs($created['price'] - $price) < 0.01, 
                           "Created product price should match input price");
                    assert($created['price'] >= 0, "Product price should be non-negative");
                }
                
            } catch (Exception $e) {
                echo "Price validation consistency failed on iteration {$i} with price {$price}: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "✓ Property: Price Validation Consistency test passed ({$this->iterations} iterations)\n";
    }
    
    /**
     * Generate valid product data for testing
     */
    private function generateValidProductData() {
        $names = [
            'Lipstick', 'Foundation', 'Mascara', 'Eyeshadow', 'Blush', 'Concealer',
            'Primer', 'Bronzer', 'Highlighter', 'Eyeliner', 'Lip Gloss', 'Setting Spray'
        ];
        
        $brands = ['Beauty Co', 'Glamour Brand', 'Cosmetic Plus', 'Style Beauty', 'Luxury Makeup'];
        $colors = ['Red', 'Pink', 'Brown', 'Black', 'Blue', 'Purple', 'Gold', 'Silver'];
        
        $name = $names[array_rand($names)] . ' ' . $colors[array_rand($colors)];
        
        return [
            'name' => $name . ' ' . mt_rand(1, 1000),
            'description' => 'High-quality ' . strtolower($name) . ' for professional makeup application.',
            'price' => round(mt_rand(500, 5000) / 100, 2), // $5.00 to $50.00
            'stock_quantity' => mt_rand(0, 200),
            'category_id' => mt_rand(0, 1) ? $this->testCategoryId : null,
            'brand' => $brands[array_rand($brands)],
            'is_active' => true
        ];
    }
    
    /**
     * Generate valid update data for testing
     */
    private function generateValidUpdateData() {
        $fields = ['name', 'description', 'price', 'stock_quantity', 'brand'];
        $updateData = [];
        
        // Randomly select 1-3 fields to update
        $fieldsToUpdate = array_rand(array_flip($fields), mt_rand(1, 3));
        if (!is_array($fieldsToUpdate)) {
            $fieldsToUpdate = [$fieldsToUpdate];
        }
        
        foreach ($fieldsToUpdate as $field) {
            switch ($field) {
                case 'name':
                    $updateData[$field] = 'Updated Product ' . mt_rand(1, 1000);
                    break;
                case 'description':
                    $updateData[$field] = 'Updated description ' . mt_rand(1, 1000);
                    break;
                case 'price':
                    $updateData[$field] = round(mt_rand(500, 5000) / 100, 2);
                    break;
                case 'stock_quantity':
                    $updateData[$field] = mt_rand(0, 200);
                    break;
                case 'brand':
                    $brands = ['Updated Brand A', 'Updated Brand B', 'Updated Brand C'];
                    $updateData[$field] = $brands[array_rand($brands)];
                    break;
            }
        }
        
        return $updateData;
    }
    
    /**
     * Generate random filters for testing
     */
    private function generateRandomFilters() {
        $filters = [];
        
        // Randomly add different filter types
        if (mt_rand(0, 1)) {
            $searchTerms = ['Lipstick', 'Foundation', 'Beauty', 'Red', 'Pink'];
            $filters['search'] = $searchTerms[array_rand($searchTerms)];
        }
        
        if (mt_rand(0, 1)) {
            $filters['category_id'] = $this->testCategoryId;
        }
        
        if (mt_rand(0, 1)) {
            $brands = ['Beauty Co', 'Glamour Brand', 'Cosmetic Plus'];
            $filters['brand'] = $brands[array_rand($brands)];
        }
        
        if (mt_rand(0, 1)) {
            $filters['min_price'] = mt_rand(5, 20);
        }
        
        if (mt_rand(0, 1)) {
            $filters['max_price'] = mt_rand(30, 50);
        }
        
        if (mt_rand(0, 1)) {
            $filters['in_stock'] = true;
        }
        
        if (mt_rand(0, 1)) {
            $sorts = ['name_asc', 'name_desc', 'price_asc', 'price_desc', 'created_asc', 'created_desc'];
            $filters['sort'] = $sorts[array_rand($sorts)];
        }
        
        return $filters;
    }
    
    /**
     * Generate stock operations for testing
     */
    private function generateStockOperations($initialStock) {
        $operations = [];
        $numOperations = mt_rand(3, 8);
        
        for ($i = 0; $i < $numOperations; $i++) {
            $types = ['set', 'add', 'subtract'];
            $type = $types[array_rand($types)];
            
            switch ($type) {
                case 'set':
                    $quantity = mt_rand(0, 100);
                    break;
                case 'add':
                    $quantity = mt_rand(1, 50);
                    break;
                case 'subtract':
                    $quantity = mt_rand(1, $initialStock + 20); // Sometimes more than available
                    break;
            }
            
            $operations[] = [
                'type' => $type,
                'quantity' => $quantity
            ];
        }
        
        return $operations;
    }
    
    /**
     * Generate random SKU for testing
     */
    private function generateRandomSKU() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $sku = '';
        
        for ($i = 0; $i < mt_rand(6, 12); $i++) {
            $sku .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        return $sku;
    }
    
    /**
     * Generate random price for testing (including edge cases)
     */
    private function generateRandomPrice() {
        $priceTypes = [
            'normal' => 0.7,    // 70% normal prices
            'negative' => 0.1,  // 10% negative prices
            'zero' => 0.05,     // 5% zero prices
            'high' => 0.1,      // 10% very high prices
            'extreme' => 0.05   // 5% extreme prices
        ];
        
        $rand = mt_rand(1, 100) / 100;
        $cumulative = 0;
        
        foreach ($priceTypes as $type => $probability) {
            $cumulative += $probability;
            if ($rand <= $cumulative) {
                switch ($type) {
                    case 'normal':
                        return round(mt_rand(100, 10000) / 100, 2); // $1.00 to $100.00
                    case 'negative':
                        return -round(mt_rand(100, 5000) / 100, 2); // Negative prices
                    case 'zero':
                        return 0.0;
                    case 'high':
                        return round(mt_rand(100000, 999999) / 100, 2); // $1000 to $9999
                    case 'extreme':
                        return round(mt_rand(1000000, 10000000) / 100, 2); // Very high prices
                }
            }
        }
        
        return 29.99; // Fallback
    }
    
    /**
     * Verify product matches filters
     */
    private function verifyProductMatchesFilters($product, $filters) {
        if (isset($filters['search'])) {
            $searchTerm = strtolower($filters['search']);
            $searchableText = strtolower($product['name'] . ' ' . $product['description'] . ' ' . $product['brand'] . ' ' . $product['sku']);
            assert(strpos($searchableText, $searchTerm) !== false, 
                   "Product should match search term: {$filters['search']}");
        }
        
        if (isset($filters['category_id'])) {
            assert($product['category_id'] == $filters['category_id'], 
                   "Product should match category filter");
        }
        
        if (isset($filters['brand'])) {
            assert($product['brand'] === $filters['brand'], 
                   "Product should match brand filter");
        }
        
        if (isset($filters['min_price'])) {
            assert($product['price'] >= $filters['min_price'], 
                   "Product price should be >= min_price filter");
        }
        
        if (isset($filters['max_price'])) {
            assert($product['price'] <= $filters['max_price'], 
                   "Product price should be <= max_price filter");
        }
        
        if (isset($filters['in_stock']) && $filters['in_stock']) {
            assert($product['stock_quantity'] > 0, 
                   "Product should be in stock when in_stock filter is applied");
        }
    }
    
    /**
     * Verify sorting consistency
     */
    private function verifySortingConsistency($products, $sort) {
        if (count($products) < 2) {
            return; // Can't verify sorting with less than 2 products
        }
        
        for ($i = 0; $i < count($products) - 1; $i++) {
            $current = $products[$i];
            $next = $products[$i + 1];
            
            switch ($sort) {
                case 'name_asc':
                    assert(strcasecmp($current['name'], $next['name']) <= 0, 
                           "Products should be sorted by name ascending");
                    break;
                case 'name_desc':
                    assert(strcasecmp($current['name'], $next['name']) >= 0, 
                           "Products should be sorted by name descending");
                    break;
                case 'price_asc':
                    assert($current['price'] <= $next['price'], 
                           "Products should be sorted by price ascending");
                    break;
                case 'price_desc':
                    assert($current['price'] >= $next['price'], 
                           "Products should be sorted by price descending");
                    break;
            }
        }
    }
    
    /**
     * Create test category
     */
    private function createTestCategory() {
        $sql = "INSERT INTO categories (name, description, is_active) VALUES (?, ?, ?)";
        $params = ['Property Test Category', 'Category for property testing', true];
        
        $this->db->executeQuery($sql, $params);
        $this->testCategoryId = $this->db->getLastInsertId();
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "Starting Product Model Property-Based Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testProductCRUDOperationsProperty();
            $this->testProductQueryConsistencyProperty();
            $this->testStockManagementConsistencyProperty();
            $this->testSKUUniquenessProperty();
            $this->testPriceValidationConsistencyProperty();
            
            echo "\n✅ All Product Model property-based tests passed!\n";
            
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
    $test = new ProductPropertyTest();
    $test->runAllTests();
}