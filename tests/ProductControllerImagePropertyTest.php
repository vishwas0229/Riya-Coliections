<?php
/**
 * Product Controller Image Management Property Tests
 * 
 * Property-based tests for image upload, deletion, and management endpoints.
 * Tests universal properties across random inputs and edge cases.
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

class ProductControllerImagePropertyTest extends TestCase {
    private $controller;
    private $productModel;
    private $imageService;
    private $db;
    private $testProductIds = [];
    private $testImageIds = [];
    
    protected function setUp(): void {
        $this->controller = new ProductController();
        $this->productModel = new Product();
        $this->imageService = new ImageService();
        $this->db = Database::getInstance();
        
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
        
        unset($_SESSION['user']);
    }
    
    /**
     * **Validates: Requirements 8.1, 8.2**
     * Property: Image retrieval consistency
     * For any product with images, retrieving images should return consistent results
     * 
     * @test
     */
    public function testImageRetrievalConsistency() {
        for ($i = 0; $i < 20; $i++) {
            try {
                // Create test product
                $productId = $this->createRandomProduct();
                $this->testProductIds[] = $productId;
                
                // Add random number of images
                $imageCount = mt_rand(1, 5);
                $testImages = [];
                
                for ($j = 0; $j < $imageCount; $j++) {
                    $testImages[] = [
                        'url' => '/uploads/products/test_' . $productId . '_' . $j . '.jpg',
                        'alt_text' => 'Test image ' . $j,
                        'is_primary' => $j === 0, // First image is primary
                        'sort_order' => $j
                    ];
                }
                
                $this->productModel->addProductImages($productId, $testImages);
                
                // Test retrieval multiple times
                for ($k = 0; $k < 3; $k++) {
                    $request = ['query' => [], 'body' => null];
                    $this->controller->setRequest($request);
                    
                    ob_start();
                    $this->controller->getImages($productId);
                    $output = ob_get_clean();
                    
                    $response = json_decode($output, true);
                    
                    // Verify consistent response structure
                    $this->assertTrue($response['success']);
                    $this->assertEquals($productId, $response['data']['product_id']);
                    $this->assertEquals($imageCount, $response['data']['images_count']);
                    $this->assertCount($imageCount, $response['data']['images']);
                    
                    // Verify primary image is always first
                    if ($imageCount > 0) {
                        $this->assertTrue($response['data']['images'][0]['is_primary']);
                        
                        // Verify only one primary image
                        $primaryCount = 0;
                        foreach ($response['data']['images'] as $image) {
                            if ($image['is_primary']) {
                                $primaryCount++;
                            }
                        }
                        $this->assertEquals(1, $primaryCount, 'Exactly one image should be primary');
                    }
                }
                
            } catch (Exception $e) {
                $this->fail('Image retrieval consistency test failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1, 8.2**
     * Property: Image deletion atomicity
     * For any product with images, deleting all images should remove all records
     * 
     * @test
     */
    public function testImageDeletionAtomicity() {
        for ($i = 0; $i < 15; $i++) {
            try {
                // Create test product
                $productId = $this->createRandomProduct();
                $this->testProductIds[] = $productId;
                
                // Add random number of images
                $imageCount = mt_rand(1, 8);
                $testImages = [];
                
                for ($j = 0; $j < $imageCount; $j++) {
                    $testImages[] = [
                        'url' => '/uploads/products/test_' . $productId . '_' . $j . '.jpg',
                        'alt_text' => 'Test image ' . $j,
                        'is_primary' => $j === 0,
                        'sort_order' => $j
                    ];
                }
                
                $this->productModel->addProductImages($productId, $testImages);
                
                // Verify images exist
                $images = $this->productModel->getProductImages($productId);
                $this->assertCount($imageCount, $images);
                
                // Delete all images
                $request = ['query' => [], 'body' => null];
                $this->controller->setRequest($request);
                
                ob_start();
                $this->controller->deleteAllImages($productId);
                $output = ob_get_clean();
                
                $response = json_decode($output, true);
                
                // Verify successful deletion
                $this->assertTrue($response['success']);
                $this->assertEquals($imageCount, $response['data']['deleted_images_count']);
                
                // Verify no images remain
                $remainingImages = $this->productModel->getProductImages($productId);
                $this->assertCount(0, $remainingImages, 'All images should be deleted');
                
            } catch (Exception $e) {
                $this->fail('Image deletion atomicity test failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1, 8.2**
     * Property: Primary image uniqueness
     * For any product, only one image can be primary at any time
     * 
     * @test
     */
    public function testPrimaryImageUniqueness() {
        for ($i = 0; $i < 20; $i++) {
            try {
                // Create test product
                $productId = $this->createRandomProduct();
                $this->testProductIds[] = $productId;
                
                // Add multiple images
                $imageCount = mt_rand(2, 6);
                $testImages = [];
                
                for ($j = 0; $j < $imageCount; $j++) {
                    $testImages[] = [
                        'url' => '/uploads/products/test_' . $productId . '_' . $j . '.jpg',
                        'alt_text' => 'Test image ' . $j,
                        'is_primary' => $j === 0,
                        'sort_order' => $j
                    ];
                }
                
                $this->productModel->addProductImages($productId, $testImages);
                
                // Get all images
                $images = $this->productModel->getProductImages($productId);
                $this->assertCount($imageCount, $images);
                
                // Randomly select an image to make primary
                $randomIndex = mt_rand(0, $imageCount - 1);
                $newPrimaryImage = $images[$randomIndex];
                
                // Set as primary
                $request = ['query' => [], 'body' => null];
                $this->controller->setRequest($request);
                
                ob_start();
                $this->controller->setPrimaryImage($productId, $newPrimaryImage['id']);
                $output = ob_get_clean();
                
                $response = json_decode($output, true);
                $this->assertTrue($response['success']);
                
                // Verify only one primary image exists
                $updatedImages = $this->productModel->getProductImages($productId);
                $primaryCount = 0;
                $primaryImageId = null;
                
                foreach ($updatedImages as $image) {
                    if ($image['is_primary']) {
                        $primaryCount++;
                        $primaryImageId = $image['id'];
                    }
                }
                
                $this->assertEquals(1, $primaryCount, 'Exactly one image should be primary');
                $this->assertEquals($newPrimaryImage['id'], $primaryImageId, 'Correct image should be primary');
                
            } catch (Exception $e) {
                $this->fail('Primary image uniqueness test failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1, 8.2**
     * Property: Image metadata update consistency
     * For any image, updating metadata should preserve other fields
     * 
     * @test
     */
    public function testImageMetadataUpdateConsistency() {
        for ($i = 0; $i < 15; $i++) {
            try {
                // Create test product
                $productId = $this->createRandomProduct();
                $this->testProductIds[] = $productId;
                
                // Add test image
                $originalAltText = 'Original alt text ' . mt_rand(1, 1000);
                $originalSortOrder = mt_rand(0, 10);
                
                $testImages = [
                    [
                        'url' => '/uploads/products/test_' . $productId . '.jpg',
                        'alt_text' => $originalAltText,
                        'is_primary' => true,
                        'sort_order' => $originalSortOrder
                    ]
                ];
                
                $this->productModel->addProductImages($productId, $testImages);
                
                // Get image
                $images = $this->productModel->getProductImages($productId);
                $image = $images[0];
                
                // Generate random update data
                $updateFields = ['alt_text', 'sort_order'];
                $fieldsToUpdate = [];
                
                // Randomly select fields to update
                foreach ($updateFields as $field) {
                    if (mt_rand(0, 1)) {
                        $fieldsToUpdate[] = $field;
                    }
                }
                
                if (empty($fieldsToUpdate)) {
                    $fieldsToUpdate = ['alt_text']; // Always update at least one field
                }
                
                $updateData = [];
                foreach ($fieldsToUpdate as $field) {
                    if ($field === 'alt_text') {
                        $updateData[$field] = 'Updated alt text ' . mt_rand(1, 1000);
                    } elseif ($field === 'sort_order') {
                        $updateData[$field] = mt_rand(0, 20);
                    }
                }
                
                // Update image
                $request = ['query' => [], 'body' => $updateData];
                $this->controller->setRequest($request);
                
                ob_start();
                $this->controller->updateImage($productId, $image['id']);
                $output = ob_get_clean();
                
                $response = json_decode($output, true);
                $this->assertTrue($response['success']);
                
                // Verify updates
                $updatedImage = $this->productModel->getProductImageById($image['id'], $productId);
                
                foreach ($updateData as $field => $value) {
                    $this->assertEquals($value, $updatedImage[$field], "Field $field should be updated");
                }
                
                // Verify unchanged fields
                $this->assertEquals($image['image_url'], $updatedImage['image_url'], 'URL should not change');
                $this->assertEquals($image['is_primary'], $updatedImage['is_primary'], 'Primary status should not change');
                $this->assertEquals($image['product_id'], $updatedImage['product_id'], 'Product ID should not change');
                
            } catch (Exception $e) {
                $this->fail('Image metadata update consistency test failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1, 8.2**
     * Property: Individual image deletion precision
     * For any product with multiple images, deleting one image should not affect others
     * 
     * @test
     */
    public function testIndividualImageDeletionPrecision() {
        for ($i = 0; $i < 15; $i++) {
            try {
                // Create test product
                $productId = $this->createRandomProduct();
                $this->testProductIds[] = $productId;
                
                // Add multiple images
                $imageCount = mt_rand(3, 7);
                $testImages = [];
                
                for ($j = 0; $j < $imageCount; $j++) {
                    $testImages[] = [
                        'url' => '/uploads/products/test_' . $productId . '_' . $j . '.jpg',
                        'alt_text' => 'Test image ' . $j,
                        'is_primary' => $j === 0,
                        'sort_order' => $j
                    ];
                }
                
                $this->productModel->addProductImages($productId, $testImages);
                
                // Get all images
                $images = $this->productModel->getProductImages($productId);
                $this->assertCount($imageCount, $images);
                
                // Randomly select an image to delete (not the primary one)
                $nonPrimaryImages = array_filter($images, function($img) {
                    return !$img['is_primary'];
                });
                
                if (!empty($nonPrimaryImages)) {
                    $imageToDelete = $nonPrimaryImages[array_rand($nonPrimaryImages)];
                    
                    // Delete the selected image
                    $request = ['query' => [], 'body' => null];
                    $this->controller->setRequest($request);
                    
                    ob_start();
                    $this->controller->deleteImage($productId, $imageToDelete['id']);
                    $output = ob_get_clean();
                    
                    $response = json_decode($output, true);
                    $this->assertTrue($response['success']);
                    
                    // Verify correct image was deleted
                    $remainingImages = $this->productModel->getProductImages($productId);
                    $this->assertCount($imageCount - 1, $remainingImages);
                    
                    // Verify deleted image is not in remaining images
                    $remainingIds = array_column($remainingImages, 'id');
                    $this->assertNotContains($imageToDelete['id'], $remainingIds);
                    
                    // Verify other images are unchanged
                    foreach ($remainingImages as $remainingImage) {
                        $originalImage = null;
                        foreach ($images as $img) {
                            if ($img['id'] === $remainingImage['id']) {
                                $originalImage = $img;
                                break;
                            }
                        }
                        
                        $this->assertNotNull($originalImage, 'Remaining image should exist in original set');
                        $this->assertEquals($originalImage['image_url'], $remainingImage['image_url']);
                        $this->assertEquals($originalImage['alt_text'], $remainingImage['alt_text']);
                        $this->assertEquals($originalImage['is_primary'], $remainingImage['is_primary']);
                    }
                }
                
            } catch (Exception $e) {
                $this->fail('Individual image deletion precision test failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1, 8.2**
     * Property: Error handling robustness
     * For any invalid input, the system should handle errors gracefully
     * 
     * @test
     */
    public function testErrorHandlingRobustness() {
        for ($i = 0; $i < 10; $i++) {
            try {
                // Create test product
                $productId = $this->createRandomProduct();
                $this->testProductIds[] = $productId;
                
                $request = ['query' => [], 'body' => null];
                $this->controller->setRequest($request);
                
                // Test various invalid inputs
                $invalidInputs = [
                    ['productId' => 'invalid', 'imageId' => 1],
                    ['productId' => -1, 'imageId' => 1],
                    ['productId' => 0, 'imageId' => 1],
                    ['productId' => $productId, 'imageId' => 'invalid'],
                    ['productId' => $productId, 'imageId' => -1],
                    ['productId' => $productId, 'imageId' => 0],
                    ['productId' => 99999, 'imageId' => 1], // Non-existent product
                    ['productId' => $productId, 'imageId' => 99999], // Non-existent image
                ];
                
                foreach ($invalidInputs as $input) {
                    // Test getImages with invalid product ID
                    if (is_string($input['productId']) || $input['productId'] <= 0) {
                        ob_start();
                        $this->controller->getImages($input['productId']);
                        $output = ob_get_clean();
                        
                        $response = json_decode($output, true);
                        $this->assertFalse($response['success'], 'Should fail with invalid product ID');
                    }
                    
                    // Test deleteImage with invalid IDs
                    if (is_string($input['imageId']) || $input['imageId'] <= 0 || 
                        is_string($input['productId']) || $input['productId'] <= 0) {
                        ob_start();
                        $this->controller->deleteImage($input['productId'], $input['imageId']);
                        $output = ob_get_clean();
                        
                        $response = json_decode($output, true);
                        $this->assertFalse($response['success'], 'Should fail with invalid IDs');
                    }
                    
                    // Test setPrimaryImage with invalid IDs
                    if (is_string($input['imageId']) || $input['imageId'] <= 0 || 
                        is_string($input['productId']) || $input['productId'] <= 0) {
                        ob_start();
                        $this->controller->setPrimaryImage($input['productId'], $input['imageId']);
                        $output = ob_get_clean();
                        
                        $response = json_decode($output, true);
                        $this->assertFalse($response['success'], 'Should fail with invalid IDs');
                    }
                }
                
            } catch (Exception $e) {
                $this->fail('Error handling robustness test failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Helper method to create a random test product
     */
    private function createRandomProduct() {
        $productData = [
            'name' => 'Test Product ' . mt_rand(1, 10000),
            'description' => 'Test product description',
            'price' => mt_rand(10, 1000) + (mt_rand(0, 99) / 100),
            'stock_quantity' => mt_rand(0, 100),
            'category_id' => null,
            'brand' => 'Test Brand',
            'sku' => 'TEST-' . time() . '-' . mt_rand(1000, 9999)
        ];
        
        return $this->productModel->createProduct($productData);
    }
}