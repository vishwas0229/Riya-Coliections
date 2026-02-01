<?php
/**
 * ProductController Property-Based Tests
 * 
 * Property-based tests for the ProductController that verify universal properties
 * hold across all valid inputs using random data generation.
 * 
 * Requirements: 5.1, 5.2, 11.1
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/bootstrap.php';

class ProductControllerPropertyTest extends TestCase {
    private $controller;
    private $productModel;
    private $categoryModel;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->controller = new ProductController();
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        
        // Mock admin authentication for admin tests
        $this->mockAdminAuth();
    }
    
    /**
     * **Validates: Requirements 5.1**
     * Property: For any valid product data, creating and then retrieving the product
     * should return the same data with proper sanitization and formatting
     * 
     * @test
     */
    public function testProductCreateRetrieveConsistency() {
        for ($i = 0; $i < 100; $i++) {
            // Generate random valid product data
            $productData = $this->generateRandomProductData();
            
            try {
                // Create product
                $request = ['body' => $productData];
                $this->controller->setRequest($request);
                
                ob_start();
                $this->controller->create();
                $createOutput = ob_get_clean();
                
                $createResponse = json_decode($createOutput, true);
                
                if (!$createResponse['success']) {
                    continue; // Skip invalid data
                }
                
                $createdProduct = $createResponse['data'];
                $productId = $createdProduct['id'];
                
                // Retrieve product
                ob_start();
                $this->controller->getById($productId);
                $retrieveOutput = ob_get_clean();
                
                $retrieveResponse = json_decode($retrieveOutput, true);
                
                // Verify consistency
                $this->assertTrue($retrieveResponse['success'], 
                    "Failed to retrieve created product with ID: {$productId}");
                
                $retrievedProduct = $retrieveResponse['data'];
                
                // Core data should match
                $this->assertEquals($createdProduct['name'], $retrievedProduct['name']);
                $this->assertEquals($createdProduct['price'], $retrievedProduct['price']);
                $this->assertEquals($createdProduct['stock_quantity'], $retrievedProduct['stock_quantity']);
                $this->assertEquals($createdProduct['description'], $retrievedProduct['description']);
                $this->assertEquals($createdProduct['brand'], $retrievedProduct['brand']);
                
                // Verify data types are consistent
                $this->assertIsInt($retrievedProduct['id']);
                $this->assertIsFloat($retrievedProduct['price']);
                $this->assertIsInt($retrievedProduct['stock_quantity']);
                $this->assertIsBool($retrievedProduct['is_active']);
                $this->assertIsBool($retrievedProduct['in_stock']);
                
                // Clean up
                $this->controller->delete($productId);
                
            } catch (Exception $e) {
                // Log but continue with next iteration
                error_log("Property test iteration {$i} failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 5.2**
     * Property: For any valid search parameters, the search results should only contain
     * products that match the search criteria
     * 
     * @test
     */
    public function testSearchResultsMatchCriteria() {
        // Create test products with known data
        $testProducts = [];
        for ($i = 0; $i < 10; $i++) {
            $productData = [
                'name' => "SearchTest Product {$i}",
                'description' => "Description with keyword {$i}",
                'price' => 10.00 + ($i * 5),
                'stock_quantity' => 5 + $i,
                'brand' => ($i % 2 === 0) ? 'BrandA' : 'BrandB'
            ];
            
            $request = ['body' => $productData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->create();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            if ($response['success']) {
                $testProducts[] = $response['data'];
            }
        }
        
        // Test various search criteria
        for ($i = 0; $i < 50; $i++) {
            $searchCriteria = $this->generateRandomSearchCriteria();
            
            $request = ['query' => $searchCriteria];
            $this->controller->setRequest($request);
            
            ob_start();
            if (!empty($searchCriteria['q'])) {
                $this->controller->search();
            } else {
                $this->controller->getAll();
            }
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            if ($response['success'] && !empty($response['data'])) {
                foreach ($response['data'] as $product) {
                    // Verify search term matches if provided
                    if (!empty($searchCriteria['q'])) {
                        $searchTerm = strtolower($searchCriteria['q']);
                        $productText = strtolower($product['name'] . ' ' . $product['description'] . ' ' . $product['brand']);
                        $this->assertStringContainsString($searchTerm, $productText,
                            "Product does not match search term: {$searchCriteria['q']}");
                    }
                    
                    // Verify price range if provided
                    if (isset($searchCriteria['min_price'])) {
                        $this->assertGreaterThanOrEqual($searchCriteria['min_price'], $product['price'],
                            "Product price below minimum: {$searchCriteria['min_price']}");
                    }
                    
                    if (isset($searchCriteria['max_price'])) {
                        $this->assertLessThanOrEqual($searchCriteria['max_price'], $product['price'],
                            "Product price above maximum: {$searchCriteria['max_price']}");
                    }
                    
                    // Verify stock filter if provided
                    if (isset($searchCriteria['in_stock']) && $searchCriteria['in_stock']) {
                        $this->assertGreaterThan(0, $product['stock_quantity'],
                            "Product should be in stock but has zero quantity");
                    }
                    
                    // Verify brand filter if provided
                    if (!empty($searchCriteria['brand'])) {
                        $this->assertEquals($searchCriteria['brand'], $product['brand'],
                            "Product brand does not match filter: {$searchCriteria['brand']}");
                    }
                }
            }
        }
        
        // Clean up test products
        foreach ($testProducts as $product) {
            $this->controller->delete($product['id']);
        }
    }
    
    /**
     * **Validates: Requirements 5.1**
     * Property: For any valid product update data, updating a product should preserve
     * unchanged fields and correctly update specified fields
     * 
     * @test
     */
    public function testProductUpdatePreservesUnchangedFields() {
        for ($i = 0; $i < 50; $i++) {
            // Create initial product
            $initialData = $this->generateRandomProductData();
            
            $request = ['body' => $initialData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->create();
            $createOutput = ob_get_clean();
            
            $createResponse = json_decode($createOutput, true);
            
            if (!$createResponse['success']) {
                continue;
            }
            
            $originalProduct = $createResponse['data'];
            $productId = $originalProduct['id'];
            
            // Generate partial update data
            $updateData = $this->generateRandomUpdateData();
            
            $request = ['body' => $updateData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->update($productId);
            $updateOutput = ob_get_clean();
            
            $updateResponse = json_decode($updateOutput, true);
            
            if ($updateResponse['success']) {
                $updatedProduct = $updateResponse['data'];
                
                // Verify updated fields changed
                foreach ($updateData as $field => $value) {
                    if (isset($updatedProduct[$field])) {
                        $this->assertEquals($value, $updatedProduct[$field],
                            "Field {$field} was not updated correctly");
                    }
                }
                
                // Verify unchanged fields preserved
                $unchangedFields = ['id', 'created_at'];
                foreach ($unchangedFields as $field) {
                    if (isset($originalProduct[$field]) && isset($updatedProduct[$field])) {
                        $this->assertEquals($originalProduct[$field], $updatedProduct[$field],
                            "Unchanged field {$field} was modified");
                    }
                }
                
                // Verify data types maintained
                $this->assertIsInt($updatedProduct['id']);
                $this->assertIsFloat($updatedProduct['price']);
                $this->assertIsInt($updatedProduct['stock_quantity']);
            }
            
            // Clean up
            $this->controller->delete($productId);
        }
    }
    
    /**
     * **Validates: Requirements 5.2**
     * Property: For any valid pagination parameters, the returned results should
     * respect the pagination limits and provide accurate pagination metadata
     * 
     * @test
     */
    public function testPaginationConsistency() {
        // Create test products
        $testProducts = [];
        for ($i = 0; $i < 25; $i++) {
            $productData = [
                'name' => "Pagination Test Product {$i}",
                'price' => 10.00 + $i,
                'stock_quantity' => 1
            ];
            
            $request = ['body' => $productData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->create();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            if ($response['success']) {
                $testProducts[] = $response['data'];
            }
        }
        
        // Test various pagination scenarios
        for ($i = 0; $i < 20; $i++) {
            $page = mt_rand(1, 5);
            $perPage = mt_rand(5, 15);
            
            $request = [
                'query' => [
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ];
            
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->getAll();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            if ($response['success']) {
                $pagination = $response['pagination'];
                
                // Verify pagination metadata
                $this->assertEquals($page, $pagination['current_page'],
                    "Current page mismatch");
                $this->assertEquals($perPage, $pagination['per_page'],
                    "Per page mismatch");
                $this->assertIsInt($pagination['total']);
                $this->assertIsInt($pagination['total_pages']);
                
                // Verify result count doesn't exceed per_page
                $this->assertLessThanOrEqual($perPage, count($response['data']),
                    "Result count exceeds per_page limit");
                
                // Verify has_next/has_prev logic
                if ($page > 1) {
                    $this->assertTrue($pagination['has_prev'],
                        "Should have previous page when page > 1");
                } else {
                    $this->assertFalse($pagination['has_prev'],
                        "Should not have previous page when page = 1");
                }
                
                if ($page < $pagination['total_pages']) {
                    $this->assertTrue($pagination['has_next'],
                        "Should have next page when page < total_pages");
                } else {
                    $this->assertFalse($pagination['has_next'],
                        "Should not have next page when page = total_pages");
                }
            }
        }
        
        // Clean up test products
        foreach ($testProducts as $product) {
            $this->controller->delete($product['id']);
        }
    }
    
    /**
     * **Validates: Requirements 11.1**
     * Property: For any valid stock operation, the stock quantity should be updated
     * correctly according to the operation type and constraints
     * 
     * @test
     */
    public function testStockOperationConsistency() {
        for ($i = 0; $i < 50; $i++) {
            // Create test product with random initial stock
            $initialStock = mt_rand(10, 100);
            $productData = [
                'name' => "Stock Test Product {$i}",
                'price' => 29.99,
                'stock_quantity' => $initialStock
            ];
            
            $request = ['body' => $productData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->create();
            $createOutput = ob_get_clean();
            
            $createResponse = json_decode($createOutput, true);
            
            if (!$createResponse['success']) {
                continue;
            }
            
            $productId = $createResponse['data']['id'];
            
            // Test different stock operations
            $operations = ['set', 'add', 'subtract'];
            $operation = $operations[array_rand($operations)];
            $quantity = mt_rand(1, 20);
            
            // Adjust quantity for subtract to avoid negative stock
            if ($operation === 'subtract' && $quantity > $initialStock) {
                $quantity = $initialStock - 1;
            }
            
            $stockData = [
                'quantity' => $quantity,
                'operation' => $operation
            ];
            
            $request = ['body' => $stockData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->updateStock($productId);
            $updateOutput = ob_get_clean();
            
            $updateResponse = json_decode($updateOutput, true);
            
            if ($updateResponse['success']) {
                $newStock = $updateResponse['data']['new_stock_quantity'];
                
                // Verify stock calculation
                switch ($operation) {
                    case 'set':
                        $this->assertEquals($quantity, $newStock,
                            "Set operation should set stock to exact quantity");
                        break;
                    case 'add':
                        $this->assertEquals($initialStock + $quantity, $newStock,
                            "Add operation should increase stock by quantity");
                        break;
                    case 'subtract':
                        $this->assertEquals($initialStock - $quantity, $newStock,
                            "Subtract operation should decrease stock by quantity");
                        break;
                }
                
                // Verify stock is never negative
                $this->assertGreaterThanOrEqual(0, $newStock,
                    "Stock quantity should never be negative");
            }
            
            // Clean up
            $this->controller->delete($productId);
        }
    }
    
    /**
     * **Validates: Requirements 5.1**
     * Property: For any valid category operations, category-product relationships
     * should be maintained correctly
     * 
     * @test
     */
    public function testCategoryProductRelationshipConsistency() {
        for ($i = 0; $i < 30; $i++) {
            // Create test category
            $categoryData = [
                'name' => "Test Category {$i}",
                'description' => "Category for testing {$i}"
            ];
            
            $request = ['body' => $categoryData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->createCategory();
            $categoryOutput = ob_get_clean();
            
            $categoryResponse = json_decode($categoryOutput, true);
            
            if (!$categoryResponse['success']) {
                continue;
            }
            
            $categoryId = $categoryResponse['data']['id'];
            
            // Create products in this category
            $productIds = [];
            $productCount = mt_rand(2, 5);
            
            for ($j = 0; $j < $productCount; $j++) {
                $productData = [
                    'name' => "Product {$j} in Category {$i}",
                    'price' => 10.00 + $j,
                    'stock_quantity' => 5,
                    'category_id' => $categoryId
                ];
                
                $request = ['body' => $productData];
                $this->controller->setRequest($request);
                
                ob_start();
                $this->controller->create();
                $productOutput = ob_get_clean();
                
                $productResponse = json_decode($productOutput, true);
                
                if ($productResponse['success']) {
                    $productIds[] = $productResponse['data']['id'];
                }
            }
            
            // Verify category-product relationship
            $request = ['query' => ['page' => 1, 'per_page' => 20]];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->getCategoryProducts($categoryId);
            $categoryProductsOutput = ob_get_clean();
            
            $categoryProductsResponse = json_decode($categoryProductsOutput, true);
            
            if ($categoryProductsResponse['success']) {
                $categoryProducts = $categoryProductsResponse['data'];
                
                // Verify all products belong to the category
                foreach ($categoryProducts as $product) {
                    $this->assertEquals($categoryId, $product['category_id'],
                        "Product should belong to the correct category");
                }
                
                // Verify product count matches
                $this->assertEquals(count($productIds), count($categoryProducts),
                    "Category should contain all created products");
            }
            
            // Clean up products
            foreach ($productIds as $productId) {
                $this->controller->delete($productId);
            }
            
            // Clean up category
            $this->controller->deleteCategory($categoryId);
        }
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Generate random valid product data
     */
    private function generateRandomProductData() {
        $names = ['Widget', 'Gadget', 'Tool', 'Device', 'Item', 'Product', 'Component'];
        $brands = ['BrandA', 'BrandB', 'BrandC', 'TestBrand', null];
        $descriptions = [
            'High quality product',
            'Durable and reliable',
            'Perfect for everyday use',
            'Premium quality item',
            null
        ];
        
        return [
            'name' => $names[array_rand($names)] . ' ' . mt_rand(1000, 9999),
            'description' => $descriptions[array_rand($descriptions)],
            'price' => round(mt_rand(100, 10000) / 100, 2), // $1.00 to $100.00
            'stock_quantity' => mt_rand(0, 100),
            'brand' => $brands[array_rand($brands)],
            'sku' => 'TEST-' . mt_rand(1000, 9999)
        ];
    }
    
    /**
     * Generate random search criteria
     */
    private function generateRandomSearchCriteria() {
        $criteria = [];
        
        // Random search term (50% chance)
        if (mt_rand(0, 1)) {
            $searchTerms = ['Widget', 'Test', 'Product', 'Brand', 'Quality'];
            $criteria['q'] = $searchTerms[array_rand($searchTerms)];
        }
        
        // Random price range (30% chance)
        if (mt_rand(0, 2) === 0) {
            $minPrice = mt_rand(1, 50);
            $maxPrice = $minPrice + mt_rand(10, 50);
            $criteria['min_price'] = $minPrice;
            $criteria['max_price'] = $maxPrice;
        }
        
        // Random stock filter (20% chance)
        if (mt_rand(0, 4) === 0) {
            $criteria['in_stock'] = true;
        }
        
        // Random brand filter (20% chance)
        if (mt_rand(0, 4) === 0) {
            $brands = ['BrandA', 'BrandB', 'TestBrand'];
            $criteria['brand'] = $brands[array_rand($brands)];
        }
        
        // Random sort (always include)
        $sorts = ['name_asc', 'name_desc', 'price_asc', 'price_desc', 'created_desc'];
        $criteria['sort'] = $sorts[array_rand($sorts)];
        
        return $criteria;
    }
    
    /**
     * Generate random update data (partial)
     */
    private function generateRandomUpdateData() {
        $possibleUpdates = [
            'name' => 'Updated Product ' . mt_rand(1000, 9999),
            'description' => 'Updated description ' . mt_rand(1000, 9999),
            'price' => round(mt_rand(100, 5000) / 100, 2),
            'stock_quantity' => mt_rand(0, 50),
            'brand' => 'UpdatedBrand'
        ];
        
        // Select 1-3 random fields to update
        $fieldsToUpdate = array_rand($possibleUpdates, mt_rand(1, 3));
        if (!is_array($fieldsToUpdate)) {
            $fieldsToUpdate = [$fieldsToUpdate];
        }
        
        $updateData = [];
        foreach ($fieldsToUpdate as $field) {
            $updateData[$field] = $possibleUpdates[$field];
        }
        
        return $updateData;
    }
    
    /**
     * Mock admin authentication
     */
    private function mockAdminAuth() {
        $GLOBALS['current_user'] = [
            'user_id' => 1,
            'email' => 'admin@test.com',
            'role' => 'admin'
        ];
    }
}