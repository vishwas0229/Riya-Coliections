<?php
/**
 * ProductController Integration Tests
 * 
 * Integration tests that verify the ProductController works correctly with
 * the routing system, middleware, and database operations.
 * 
 * Requirements: 5.1, 5.2, 11.1
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';

class ProductControllerIntegrationTest extends TestCase {
    private $controller;
    private $testProductIds = [];
    private $testCategoryIds = [];
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->controller = new ProductController();
        
        // Mock admin authentication for admin tests
        $GLOBALS['current_user'] = [
            'user_id' => 1,
            'email' => 'admin@test.com',
            'role' => 'admin'
        ];
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->cleanupTestData();
        parent::tearDown();
    }
    
    /**
     * @test
     * Test complete product lifecycle: create, read, update, delete
     */
    public function testProductLifecycle() {
        // 1. Create product
        $productData = [
            'name' => 'Integration Test Product',
            'description' => 'Product for integration testing',
            'price' => 49.99,
            'stock_quantity' => 25,
            'brand' => 'TestBrand',
            'sku' => 'INT-TEST-001'
        ];
        
        $request = ['body' => $productData];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->create();
        $createOutput = ob_get_clean();
        
        $createResponse = json_decode($createOutput, true);
        
        $this->assertTrue($createResponse['success'], 'Product creation should succeed');
        $this->assertArrayHasKey('data', $createResponse);
        
        $createdProduct = $createResponse['data'];
        $productId = $createdProduct['id'];
        $this->testProductIds[] = $productId;
        
        // Verify created product data
        $this->assertEquals('Integration Test Product', $createdProduct['name']);
        $this->assertEquals(49.99, $createdProduct['price']);
        $this->assertEquals(25, $createdProduct['stock_quantity']);
        $this->assertEquals('INT-TEST-001', $createdProduct['sku']);
        
        // 2. Read product
        ob_start();
        $this->controller->getById($productId);
        $readOutput = ob_get_clean();
        
        $readResponse = json_decode($readOutput, true);
        
        $this->assertTrue($readResponse['success'], 'Product retrieval should succeed');
        $retrievedProduct = $readResponse['data'];
        
        $this->assertEquals($productId, $retrievedProduct['id']);
        $this->assertEquals('Integration Test Product', $retrievedProduct['name']);
        
        // 3. Update product
        $updateData = [
            'name' => 'Updated Integration Test Product',
            'price' => 59.99,
            'stock_quantity' => 30
        ];
        
        $request = ['body' => $updateData];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->update($productId);
        $updateOutput = ob_get_clean();
        
        $updateResponse = json_decode($updateOutput, true);
        
        $this->assertTrue($updateResponse['success'], 'Product update should succeed');
        $updatedProduct = $updateResponse['data'];
        
        $this->assertEquals('Updated Integration Test Product', $updatedProduct['name']);
        $this->assertEquals(59.99, $updatedProduct['price']);
        $this->assertEquals(30, $updatedProduct['stock_quantity']);
        
        // 4. Delete product
        ob_start();
        $this->controller->delete($productId);
        $deleteOutput = ob_get_clean();
        
        $deleteResponse = json_decode($deleteOutput, true);
        
        $this->assertTrue($deleteResponse['success'], 'Product deletion should succeed');
        
        // 5. Verify product is deleted (should return 404)
        ob_start();
        $this->controller->getById($productId);
        $notFoundOutput = ob_get_clean();
        
        $notFoundResponse = json_decode($notFoundOutput, true);
        
        $this->assertFalse($notFoundResponse['success'], 'Deleted product should not be found');
        $this->assertEquals('Product not found', $notFoundResponse['message']);
        
        // Remove from cleanup list since it's already deleted
        $this->testProductIds = array_diff($this->testProductIds, [$productId]);
    }
    
    /**
     * @test
     * Test category and product relationship integration
     */
    public function testCategoryProductIntegration() {
        // 1. Create category
        $categoryData = [
            'name' => 'Integration Test Category',
            'description' => 'Category for integration testing'
        ];
        
        $request = ['body' => $categoryData];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->createCategory();
        $categoryOutput = ob_get_clean();
        
        $categoryResponse = json_decode($categoryOutput, true);
        
        $this->assertTrue($categoryResponse['success'], 'Category creation should succeed');
        $category = $categoryResponse['data'];
        $categoryId = $category['id'];
        $this->testCategoryIds[] = $categoryId;
        
        // 2. Create products in category
        $productIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $productData = [
                'name' => "Category Product {$i}",
                'price' => 10.00 * $i,
                'stock_quantity' => 5 * $i,
                'category_id' => $categoryId
            ];
            
            $request = ['body' => $productData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->create();
            $productOutput = ob_get_clean();
            
            $productResponse = json_decode($productOutput, true);
            
            $this->assertTrue($productResponse['success'], "Product {$i} creation should succeed");
            $productIds[] = $productResponse['data']['id'];
            $this->testProductIds[] = $productResponse['data']['id'];
        }
        
        // 3. Get category products
        $request = ['query' => ['page' => 1, 'per_page' => 10]];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->getCategoryProducts($categoryId);
        $categoryProductsOutput = ob_get_clean();
        
        $categoryProductsResponse = json_decode($categoryProductsOutput, true);
        
        $this->assertTrue($categoryProductsResponse['success'], 'Category products retrieval should succeed');
        $categoryProducts = $categoryProductsResponse['data'];
        
        // Verify all products are in the category
        $this->assertEquals(3, count($categoryProducts));
        
        foreach ($categoryProducts as $product) {
            $this->assertEquals($categoryId, $product['category_id']);
            $this->assertEquals('Integration Test Category', $product['category_name']);
        }
        
        // 4. Test category filtering in product list
        $request = ['query' => ['category_id' => $categoryId]];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->getAll();
        $filteredOutput = ob_get_clean();
        
        $filteredResponse = json_decode($filteredOutput, true);
        
        $this->assertTrue($filteredResponse['success'], 'Filtered products retrieval should succeed');
        $filteredProducts = $filteredResponse['data'];
        
        // All returned products should belong to the category
        foreach ($filteredProducts as $product) {
            $this->assertEquals($categoryId, $product['category_id']);
        }
    }
    
    /**
     * @test
     * Test search functionality integration
     */
    public function testSearchIntegration() {
        // Create test products with specific searchable content
        $testProducts = [
            [
                'name' => 'Searchable Widget Alpha',
                'description' => 'High quality widget for testing',
                'price' => 25.99,
                'stock_quantity' => 10,
                'brand' => 'SearchBrand'
            ],
            [
                'name' => 'Another Product Beta',
                'description' => 'Different product with widget keyword',
                'price' => 35.99,
                'stock_quantity' => 15,
                'brand' => 'OtherBrand'
            ],
            [
                'name' => 'Unrelated Item Gamma',
                'description' => 'Completely different item',
                'price' => 45.99,
                'stock_quantity' => 5,
                'brand' => 'ThirdBrand'
            ]
        ];
        
        $createdProductIds = [];
        
        foreach ($testProducts as $productData) {
            $request = ['body' => $productData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->create();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            if ($response['success']) {
                $createdProductIds[] = $response['data']['id'];
                $this->testProductIds[] = $response['data']['id'];
            }
        }
        
        // Test search by name
        $request = ['query' => ['q' => 'widget']];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->search();
        $searchOutput = ob_get_clean();
        
        $searchResponse = json_decode($searchOutput, true);
        
        $this->assertTrue($searchResponse['success'], 'Search should succeed');
        $searchResults = $searchResponse['data'];
        
        // Should find products containing 'widget'
        $this->assertGreaterThan(0, count($searchResults));
        
        foreach ($searchResults as $product) {
            $productText = strtolower($product['name'] . ' ' . $product['description']);
            $this->assertStringContainsString('widget', $productText);
        }
        
        // Test search with filters
        $request = [
            'query' => [
                'q' => 'product',
                'min_price' => 30.00,
                'max_price' => 50.00
            ]
        ];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->search();
        $filteredSearchOutput = ob_get_clean();
        
        $filteredSearchResponse = json_decode($filteredSearchOutput, true);
        
        $this->assertTrue($filteredSearchResponse['success'], 'Filtered search should succeed');
        $filteredResults = $filteredSearchResponse['data'];
        
        foreach ($filteredResults as $product) {
            $this->assertGreaterThanOrEqual(30.00, $product['price']);
            $this->assertLessThanOrEqual(50.00, $product['price']);
        }
    }
    
    /**
     * @test
     * Test stock management integration
     */
    public function testStockManagementIntegration() {
        // Create test product
        $productData = [
            'name' => 'Stock Test Product',
            'price' => 29.99,
            'stock_quantity' => 50
        ];
        
        $request = ['body' => $productData];
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->create();
        $createOutput = ob_get_clean();
        
        $createResponse = json_decode($createOutput, true);
        
        $this->assertTrue($createResponse['success']);
        $productId = $createResponse['data']['id'];
        $this->testProductIds[] = $productId;
        
        // Test stock operations
        $stockOperations = [
            ['operation' => 'add', 'quantity' => 10, 'expected' => 60],
            ['operation' => 'subtract', 'quantity' => 15, 'expected' => 45],
            ['operation' => 'set', 'quantity' => 25, 'expected' => 25]
        ];
        
        foreach ($stockOperations as $operation) {
            $stockData = [
                'quantity' => $operation['quantity'],
                'operation' => $operation['operation']
            ];
            
            $request = ['body' => $stockData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->updateStock($productId);
            $stockOutput = ob_get_clean();
            
            $stockResponse = json_decode($stockOutput, true);
            
            $this->assertTrue($stockResponse['success'], 
                "Stock {$operation['operation']} operation should succeed");
            
            $this->assertEquals($operation['expected'], 
                $stockResponse['data']['new_stock_quantity'],
                "Stock quantity should match expected value for {$operation['operation']} operation");
        }
        
        // Verify final stock by retrieving product
        ob_start();
        $this->controller->getById($productId);
        $finalOutput = ob_get_clean();
        
        $finalResponse = json_decode($finalOutput, true);
        
        $this->assertTrue($finalResponse['success']);
        $this->assertEquals(25, $finalResponse['data']['stock_quantity']);
    }
    
    /**
     * @test
     * Test pagination integration
     */
    public function testPaginationIntegration() {
        // Create multiple test products
        $productCount = 15;
        $createdIds = [];
        
        for ($i = 1; $i <= $productCount; $i++) {
            $productData = [
                'name' => "Pagination Test Product {$i}",
                'price' => 10.00 + $i,
                'stock_quantity' => $i
            ];
            
            $request = ['body' => $productData];
            $this->controller->setRequest($request);
            
            ob_start();
            $this->controller->create();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            if ($response['success']) {
                $createdIds[] = $response['data']['id'];
                $this->testProductIds[] = $response['data']['id'];
            }
        }
        
        // Test pagination
        $perPage = 5;
        $totalPages = ceil(count($createdIds) / $perPage);
        
        for ($page = 1; $page <= $totalPages; $page++) {
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
            
            $this->assertTrue($response['success'], "Page {$page} should load successfully");
            
            $pagination = $response['pagination'];
            
            $this->assertEquals($page, $pagination['current_page']);
            $this->assertEquals($perPage, $pagination['per_page']);
            $this->assertLessThanOrEqual($perPage, count($response['data']));
            
            // Verify pagination flags
            if ($page > 1) {
                $this->assertTrue($pagination['has_prev']);
            } else {
                $this->assertFalse($pagination['has_prev']);
            }
            
            if ($page < $totalPages) {
                $this->assertTrue($pagination['has_next']);
            } else {
                $this->assertFalse($pagination['has_next']);
            }
        }
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        // Delete test products
        foreach ($this->testProductIds as $productId) {
            try {
                $this->controller->delete($productId);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete test categories
        foreach ($this->testCategoryIds as $categoryId) {
            try {
                $this->controller->deleteCategory($categoryId);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->testProductIds = [];
        $this->testCategoryIds = [];
    }
}