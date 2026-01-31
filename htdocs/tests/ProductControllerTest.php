<?php
/**
 * ProductController Unit Tests
 * 
 * Comprehensive unit tests for the ProductController class covering all endpoints
 * and functionality including public product browsing, admin management, and
 * category operations.
 * 
 * Requirements: 5.1, 5.2, 11.1
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/bootstrap.php';

class ProductControllerTest extends TestCase {
    private $controller;
    private $productModel;
    private $categoryModel;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Initialize test database
        $this->initializeTestDatabase();
        
        // Create controller instance
        $this->controller = new ProductController();
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        
        // Create test data
        $this->createTestData();
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->cleanupTestData();
        parent::tearDown();
    }
    
    // ==================== PUBLIC ENDPOINT TESTS ====================
    
    /**
     * @test
     * Test GET /api/products - List products with pagination
     */
    public function testGetAllProducts() {
        // Set up request
        $request = [
            'query' => [
                'page' => 1,
                'per_page' => 10
            ]
        ];
        
        $this->controller->setRequest($request);
        
        // Capture output
        ob_start();
        $this->controller->getAll();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertIsArray($response['data']);
        $this->assertLessThanOrEqual(10, count($response['data']));
    }
    
    /**
     * @test
     * Test GET /api/products with filters
     */
    public function testGetAllProductsWithFilters() {
        // Set up request with filters
        $request = [
            'query' => [
                'search' => 'test',
                'category_id' => 1,
                'min_price' => 10.00,
                'max_price' => 100.00,
                'in_stock' => true,
                'sort' => 'price_asc'
            ]
        ];
        
        $this->controller->setRequest($request);
        
        // Capture output
        ob_start();
        $this->controller->getAll();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        // Verify filters are applied (products should match criteria)
        foreach ($response['data'] as $product) {
            $this->assertGreaterThanOrEqual(10.00, $product['price']);
            $this->assertLessThanOrEqual(100.00, $product['price']);
            $this->assertGreaterThan(0, $product['stock_quantity']);
        }
    }
    
    /**
     * @test
     * Test GET /api/products/{id} - Get single product
     */
    public function testGetProductById() {
        // Create test product
        $productData = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 29.99,
            'stock_quantity' => 10,
            'category_id' => 1
        ];
        
        $product = $this->productModel->createProduct($productData);
        $productId = $product['id'];
        
        // Test valid product ID
        ob_start();
        $this->controller->getById($productId);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals($productId, $response['data']['id']);
        $this->assertEquals('Test Product', $response['data']['name']);
        $this->assertEquals(29.99, $response['data']['price']);
    }
    
    /**
     * @test
     * Test GET /api/products/{id} with invalid ID
     */
    public function testGetProductByIdNotFound() {
        ob_start();
        $this->controller->getById(99999);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertEquals('Product not found', $response['message']);
    }
    
    /**
     * @test
     * Test GET /api/products/{id} with invalid ID format
     */
    public function testGetProductByIdInvalidFormat() {
        ob_start();
        $this->controller->getById('invalid');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid product ID', $response['message']);
    }
    
    /**
     * @test
     * Test GET /api/products/search
     */
    public function testSearchProducts() {
        // Create test products
        $this->productModel->createProduct([
            'name' => 'Searchable Product One',
            'description' => 'Description with keyword',
            'price' => 19.99,
            'stock_quantity' => 5
        ]);
        
        $this->productModel->createProduct([
            'name' => 'Another Product',
            'description' => 'Different description',
            'price' => 39.99,
            'stock_quantity' => 3
        ]);
        
        // Set up search request
        $request = [
            'query' => [
                'q' => 'Searchable',
                'page' => 1,
                'per_page' => 10
            ]
        ];
        
        $this->controller->setRequest($request);
        
        // Capture output
        ob_start();
        $this->controller->search();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertGreaterThan(0, count($response['data']));
        
        // Verify search results contain the search term
        $found = false;
        foreach ($response['data'] as $product) {
            if (stripos($product['name'], 'Searchable') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
    
    /**
     * @test
     * Test GET /api/products/search with empty query
     */
    public function testSearchProductsEmptyQuery() {
        $request = [
            'query' => [
                'q' => '',
                'page' => 1
            ]
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->search();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertEquals('Search term is required', $response['message']);
    }
    
    /**
     * @test
     * Test GET /api/products/featured
     */
    public function testGetFeaturedProducts() {
        // Create test products
        for ($i = 1; $i <= 5; $i++) {
            $this->productModel->createProduct([
                'name' => "Featured Product {$i}",
                'price' => 10.00 * $i,
                'stock_quantity' => 10
            ]);
        }
        
        $request = [
            'query' => [
                'limit' => 3
            ]
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->getFeatured();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertLessThanOrEqual(3, count($response['data']));
        
        // Verify all products have stock
        foreach ($response['data'] as $product) {
            $this->assertGreaterThan(0, $product['stock_quantity']);
        }
    }
    
    // ==================== CATEGORY ENDPOINT TESTS ====================
    
    /**
     * @test
     * Test GET /api/categories
     */
    public function testGetCategories() {
        $request = [
            'query' => [
                'page' => 1,
                'per_page' => 10
            ]
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->getCategories();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertIsArray($response['data']);
    }
    
    /**
     * @test
     * Test GET /api/categories/{id}
     */
    public function testGetCategoryById() {
        // Create test category
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'Test Description'
        ];
        
        $category = $this->categoryModel->createCategory($categoryData);
        $categoryId = $category['id'];
        
        ob_start();
        $this->controller->getCategoryById($categoryId);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals($categoryId, $response['data']['id']);
        $this->assertEquals('Test Category', $response['data']['name']);
    }
    
    /**
     * @test
     * Test GET /api/categories/{id}/products
     */
    public function testGetCategoryProducts() {
        // Create test category
        $category = $this->categoryModel->createCategory([
            'name' => 'Test Category'
        ]);
        
        // Create products in category
        for ($i = 1; $i <= 3; $i++) {
            $this->productModel->createProduct([
                'name' => "Product {$i}",
                'price' => 10.00 * $i,
                'stock_quantity' => 5,
                'category_id' => $category['id']
            ]);
        }
        
        $request = [
            'query' => [
                'page' => 1,
                'per_page' => 10
            ]
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->getCategoryProducts($category['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals(3, count($response['data']));
        
        // Verify all products belong to the category
        foreach ($response['data'] as $product) {
            $this->assertEquals($category['id'], $product['category_id']);
        }
    }
    
    // ==================== ADMIN ENDPOINT TESTS ====================
    
    /**
     * @test
     * Test POST /api/admin/products - Create product
     */
    public function testCreateProduct() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        $productData = [
            'name' => 'New Test Product',
            'description' => 'New product description',
            'price' => 49.99,
            'stock_quantity' => 20,
            'brand' => 'Test Brand',
            'sku' => 'TEST-001'
        ];
        
        $request = [
            'body' => $productData
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('New Test Product', $response['data']['name']);
        $this->assertEquals(49.99, $response['data']['price']);
        $this->assertEquals('TEST-001', $response['data']['sku']);
    }
    
    /**
     * @test
     * Test POST /api/admin/products with missing required fields
     */
    public function testCreateProductMissingFields() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        $productData = [
            'description' => 'Missing name and price'
        ];
        
        $request = [
            'body' => $productData
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('errors', $response);
    }
    
    /**
     * @test
     * Test PUT /api/admin/products/{id} - Update product
     */
    public function testUpdateProduct() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        // Create test product
        $product = $this->productModel->createProduct([
            'name' => 'Original Product',
            'price' => 29.99,
            'stock_quantity' => 10
        ]);
        
        $updateData = [
            'name' => 'Updated Product',
            'price' => 39.99,
            'stock_quantity' => 15
        ];
        
        $request = [
            'body' => $updateData
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->update($product['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('Updated Product', $response['data']['name']);
        $this->assertEquals(39.99, $response['data']['price']);
        $this->assertEquals(15, $response['data']['stock_quantity']);
    }
    
    /**
     * @test
     * Test DELETE /api/admin/products/{id} - Delete product
     */
    public function testDeleteProduct() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        // Create test product
        $product = $this->productModel->createProduct([
            'name' => 'Product to Delete',
            'price' => 19.99,
            'stock_quantity' => 5
        ]);
        
        ob_start();
        $this->controller->delete($product['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('Product deleted successfully', $response['message']);
        
        // Verify product is soft deleted
        $deletedProduct = $this->productModel->getProductById($product['id']);
        $this->assertNull($deletedProduct);
    }
    
    /**
     * @test
     * Test POST /api/admin/categories - Create category
     */
    public function testCreateCategory() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        $categoryData = [
            'name' => 'New Test Category',
            'description' => 'New category description'
        ];
        
        $request = [
            'body' => $categoryData
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->createCategory();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('New Test Category', $response['data']['name']);
    }
    
    /**
     * @test
     * Test PUT /api/admin/categories/{id} - Update category
     */
    public function testUpdateCategory() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        // Create test category
        $category = $this->categoryModel->createCategory([
            'name' => 'Original Category',
            'description' => 'Original description'
        ]);
        
        $updateData = [
            'name' => 'Updated Category',
            'description' => 'Updated description'
        ];
        
        $request = [
            'body' => $updateData
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->updateCategory($category['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('Updated Category', $response['data']['name']);
        $this->assertEquals('Updated description', $response['data']['description']);
    }
    
    /**
     * @test
     * Test DELETE /api/admin/categories/{id} - Delete category
     */
    public function testDeleteCategory() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        // Create test category
        $category = $this->categoryModel->createCategory([
            'name' => 'Category to Delete',
            'description' => 'Will be deleted'
        ]);
        
        ob_start();
        $this->controller->deleteCategory($category['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals('Category deleted successfully', $response['message']);
        
        // Verify category is soft deleted
        $deletedCategory = $this->categoryModel->getCategoryById($category['id']);
        $this->assertNull($deletedCategory);
    }
    
    /**
     * @test
     * Test GET /api/admin/products/stats - Product statistics
     */
    public function testGetProductStats() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        ob_start();
        $this->controller->getProductStats();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('total_products', $response['data']);
        $this->assertArrayHasKey('stock', $response['data']);
        $this->assertArrayHasKey('pricing', $response['data']);
    }
    
    /**
     * @test
     * Test GET /api/admin/categories/stats - Category statistics
     */
    public function testGetCategoryStats() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        ob_start();
        $this->controller->getCategoryStats();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('total_categories', $response['data']);
        $this->assertArrayHasKey('categories_with_products', $response['data']);
    }
    
    /**
     * @test
     * Test PUT /api/admin/products/{id}/stock - Update stock
     */
    public function testUpdateStock() {
        // Mock admin authentication
        $this->mockAdminAuth();
        
        // Create test product
        $product = $this->productModel->createProduct([
            'name' => 'Stock Test Product',
            'price' => 29.99,
            'stock_quantity' => 10
        ]);
        
        $stockData = [
            'quantity' => 5,
            'operation' => 'add'
        ];
        
        $request = [
            'body' => $stockData
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->updateStock($product['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Assertions
        $this->assertTrue($response['success']);
        $this->assertEquals(15, $response['data']['new_stock_quantity']);
        $this->assertEquals('add', $response['data']['operation']);
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Initialize test database
     */
    private function initializeTestDatabase() {
        // This would set up a test database connection
        // For now, we'll use the existing database setup
    }
    
    /**
     * Create test data
     */
    private function createTestData() {
        // Create test category
        try {
            $this->categoryModel->createCategory([
                'name' => 'Test Category',
                'description' => 'Category for testing'
            ]);
        } catch (Exception $e) {
            // Category might already exist
        }
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        // Clean up would be handled by database transactions in a real test environment
    }
    
    /**
     * Mock admin authentication
     */
    private function mockAdminAuth() {
        // Mock the AuthMiddleware::requireAdmin() method
        // In a real test environment, this would use proper mocking
        $GLOBALS['current_user'] = [
            'user_id' => 1,
            'email' => 'admin@test.com',
            'role' => 'admin'
        ];
    }
}