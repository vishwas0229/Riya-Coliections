<?php
/**
 * Product Controller Image Management Tests
 * 
 * Tests for image upload, deletion, and management endpoints in ProductController.
 * Validates all image-related functionality including primary image designation.
 * 
 * Requirements: 8.1, 8.2
 */

require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../services/ImageService.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class ProductControllerImageTest extends TestCase {
    private $controller;
    private $productModel;
    private $imageService;
    private $db;
    private $testProductIds = [];
    private $testImageIds = [];
    private $testFiles = [];
    
    protected function setUp(): void {
        $this->controller = new ProductController();
        $this->productModel = new Product();
        $this->imageService = new ImageService();
        $this->db = Database::getInstance();
        
        // Create test product
        $productData = [
            'name' => 'Test Product for Images',
            'description' => 'Test product for image management',
            'price' => 99.99,
            'stock_quantity' => 10,
            'category_id' => null,
            'brand' => 'Test Brand',
            'sku' => 'TEST-IMG-' . time()
        ];
        
        $productId = $this->productModel->createProduct($productData);
        $this->testProductIds[] = $productId;
        
        // Mock admin authentication
        $_SESSION['user'] = [
            'user_id' => 1,
            'email' => 'admin@test.com',
            'role' => 'admin'
        ];
    }
    
    protected function tearDown(): void {
        // Clean up test data
        foreach ($this->testImageIds as $imageId) {
            try {
                $this->db->executeQuery("DELETE FROM product_images WHERE id = ?", [$imageId]);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        foreach ($this->testProductIds as $productId) {
            try {
                $this->imageService->deleteProductImages($productId);
                $this->db->executeQuery("DELETE FROM product_images WHERE product_id = ?", [$productId]);
                $this->db->executeQuery("DELETE FROM products WHERE id = ?", [$productId]);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up test files
        foreach ($this->testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        unset($_SESSION['user']);
    }
    
    /**
     * Test getting product images
     */
    public function testGetProductImages() {
        $productId = $this->testProductIds[0];
        
        // Add test images to database
        $testImages = [
            [
                'url' => '/uploads/products/test1.jpg',
                'alt_text' => 'Test image 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => '/uploads/products/test2.jpg',
                'alt_text' => 'Test image 2',
                'is_primary' => false,
                'sort_order' => 1
            ]
        ];
        
        $this->productModel->addProductImages($productId, $testImages);
        
        // Mock request
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        // Capture output
        ob_start();
        $this->controller->getImages($productId);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Product images retrieved successfully', $response['message']);
        $this->assertEquals($productId, $response['data']['product_id']);
        $this->assertEquals(2, $response['data']['images_count']);
        $this->assertCount(2, $response['data']['images']);
        
        // Verify primary image is first
        $this->assertTrue($response['data']['images'][0]['is_primary']);
        $this->assertFalse($response['data']['images'][1]['is_primary']);
    }
    
    /**
     * Test getting images for non-existent product
     */
    public function testGetImagesNonExistentProduct() {
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        ob_start();
        $this->controller->getImages(99999);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Product not found', $response['message']);
    }
    
    /**
     * Test deleting all product images
     */
    public function testDeleteAllProductImages() {
        $productId = $this->testProductIds[0];
        
        // Add test images
        $testImages = [
            [
                'url' => '/uploads/products/test1.jpg',
                'alt_text' => 'Test image 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => '/uploads/products/test2.jpg',
                'alt_text' => 'Test image 2',
                'is_primary' => false,
                'sort_order' => 1
            ]
        ];
        
        $this->productModel->addProductImages($productId, $testImages);
        
        // Verify images exist
        $images = $this->productModel->getProductImages($productId);
        $this->assertCount(2, $images);
        
        // Mock request
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        // Delete all images
        ob_start();
        $this->controller->deleteAllImages($productId);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('All product images deleted successfully', $response['message']);
        $this->assertEquals($productId, $response['data']['product_id']);
        $this->assertEquals(2, $response['data']['deleted_images_count']);
        
        // Verify images are deleted from database
        $remainingImages = $this->productModel->getProductImages($productId);
        $this->assertCount(0, $remainingImages);
    }
    
    /**
     * Test deleting specific product image
     */
    public function testDeleteSpecificProductImage() {
        $productId = $this->testProductIds[0];
        
        // Add test images
        $testImages = [
            [
                'url' => '/uploads/products/test1.jpg',
                'alt_text' => 'Test image 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => '/uploads/products/test2.jpg',
                'alt_text' => 'Test image 2',
                'is_primary' => false,
                'sort_order' => 1
            ]
        ];
        
        $this->productModel->addProductImages($productId, $testImages);
        
        // Get image IDs
        $images = $this->productModel->getProductImages($productId);
        $this->assertCount(2, $images);
        $imageToDelete = $images[1]; // Delete the second image
        
        // Mock request
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        // Delete specific image
        ob_start();
        $this->controller->deleteImage($productId, $imageToDelete['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Product image deleted successfully', $response['message']);
        $this->assertEquals($productId, $response['data']['product_id']);
        $this->assertEquals($imageToDelete['id'], $response['data']['image_id']);
        
        // Verify only one image remains
        $remainingImages = $this->productModel->getProductImages($productId);
        $this->assertCount(1, $remainingImages);
        $this->assertEquals($images[0]['id'], $remainingImages[0]['id']);
    }
    
    /**
     * Test setting primary image
     */
    public function testSetPrimaryImage() {
        $productId = $this->testProductIds[0];
        
        // Add test images
        $testImages = [
            [
                'url' => '/uploads/products/test1.jpg',
                'alt_text' => 'Test image 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => '/uploads/products/test2.jpg',
                'alt_text' => 'Test image 2',
                'is_primary' => false,
                'sort_order' => 1
            ]
        ];
        
        $this->productModel->addProductImages($productId, $testImages);
        
        // Get image IDs
        $images = $this->productModel->getProductImages($productId);
        $this->assertCount(2, $images);
        
        $newPrimaryImage = $images[1]; // Make the second image primary
        
        // Mock request
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        // Set primary image
        ob_start();
        $this->controller->setPrimaryImage($productId, $newPrimaryImage['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Primary image set successfully', $response['message']);
        $this->assertEquals($productId, $response['data']['product_id']);
        $this->assertEquals($newPrimaryImage['id'], $response['data']['image_id']);
        
        // Verify primary image changed
        $updatedImages = $this->productModel->getProductImages($productId);
        $this->assertTrue($updatedImages[0]['is_primary']); // First in order should be primary
        $this->assertEquals($newPrimaryImage['id'], $updatedImages[0]['id']);
        $this->assertFalse($updatedImages[1]['is_primary']); // Second should not be primary
    }
    
    /**
     * Test updating image metadata
     */
    public function testUpdateImageMetadata() {
        $productId = $this->testProductIds[0];
        
        // Add test image
        $testImages = [
            [
                'url' => '/uploads/products/test1.jpg',
                'alt_text' => 'Original alt text',
                'is_primary' => true,
                'sort_order' => 0
            ]
        ];
        
        $this->productModel->addProductImages($productId, $testImages);
        
        // Get image ID
        $images = $this->productModel->getProductImages($productId);
        $image = $images[0];
        
        // Mock request with update data
        $updateData = [
            'alt_text' => 'Updated alt text',
            'sort_order' => 5
        ];
        
        $request = [
            'query' => [],
            'body' => $updateData
        ];
        
        $this->controller->setRequest($request);
        
        // Update image
        ob_start();
        $this->controller->updateImage($productId, $image['id']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Image updated successfully', $response['message']);
        $this->assertEquals($productId, $response['data']['product_id']);
        $this->assertEquals($image['id'], $response['data']['image_id']);
        
        // Verify updates
        $updatedImage = $response['data']['image'];
        $this->assertEquals('Updated alt text', $updatedImage['alt_text']);
        $this->assertEquals(5, $updatedImage['sort_order']);
    }
    
    /**
     * Test authentication requirement for admin endpoints
     */
    public function testAuthenticationRequired() {
        // Remove admin session
        unset($_SESSION['user']);
        
        $productId = $this->testProductIds[0];
        
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        // Test delete all images without auth
        ob_start();
        try {
            $this->controller->deleteAllImages($productId);
        } catch (Exception $e) {
            // Expected to throw exception for unauthorized access
            $this->assertStringContains('Unauthorized', $e->getMessage());
        }
        ob_end_clean();
        
        // Test delete specific image without auth
        ob_start();
        try {
            $this->controller->deleteImage($productId, 1);
        } catch (Exception $e) {
            // Expected to throw exception for unauthorized access
            $this->assertStringContains('Unauthorized', $e->getMessage());
        }
        ob_end_clean();
        
        // Test set primary image without auth
        ob_start();
        try {
            $this->controller->setPrimaryImage($productId, 1);
        } catch (Exception $e) {
            // Expected to throw exception for unauthorized access
            $this->assertStringContains('Unauthorized', $e->getMessage());
        }
        ob_end_clean();
    }
    
    /**
     * Test invalid input validation
     */
    public function testInvalidInputValidation() {
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        // Test invalid product ID
        ob_start();
        $this->controller->getImages('invalid');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid product ID', $response['message']);
        
        // Test invalid image ID
        ob_start();
        $this->controller->deleteImage($this->testProductIds[0], 'invalid');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid image ID', $response['message']);
        
        // Test negative IDs
        ob_start();
        $this->controller->getImages(-1);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid product ID', $response['message']);
    }
    
    /**
     * Test error handling for non-existent images
     */
    public function testNonExistentImageHandling() {
        $productId = $this->testProductIds[0];
        
        $request = [
            'query' => [],
            'body' => null
        ];
        
        $this->controller->setRequest($request);
        
        // Test deleting non-existent image
        ob_start();
        $this->controller->deleteImage($productId, 99999);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Image not found', $response['message']);
        
        // Test setting non-existent image as primary
        ob_start();
        $this->controller->setPrimaryImage($productId, 99999);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Image not found', $response['message']);
    }
}