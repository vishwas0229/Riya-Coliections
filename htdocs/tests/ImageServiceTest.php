<?php
/**
 * ImageService Unit Tests
 * 
 * Tests for image upload, processing, validation, and management functionality.
 * Covers file handling, image resizing, optimization, and security validation.
 * 
 * Requirements: 8.1, 8.2
 */

require_once __DIR__ . '/../services/ImageService.php';
require_once __DIR__ . '/../utils/Logger.php';

class ImageServiceTest extends PHPUnit\Framework\TestCase {
    private $imageService;
    private $testUploadPath;
    private $testImages = [];
    
    protected function setUp(): void {
        $this->imageService = new ImageService();
        $this->testUploadPath = __DIR__ . '/../uploads/products/test/';
        
        // Create test directory
        if (!is_dir($this->testUploadPath)) {
            mkdir($this->testUploadPath, 0755, true);
        }
        
        // Create test images
        $this->createTestImages();
    }
    
    protected function tearDown(): void {
        // Clean up test files and directories
        $this->cleanupTestFiles();
    }
    
    /**
     * Test valid image upload
     */
    public function testValidImageUpload() {
        $fileInfo = $this->createMockFileInfo('test_image.jpg', 'image/jpeg', 1024 * 1024); // 1MB
        $productId = 123;
        
        $result = $this->imageService->uploadProductImage($fileInfo, $productId);
        
        // Verify result structure
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('thumbnail_url', $result);
        $this->assertArrayHasKey('medium_url', $result);
        $this->assertArrayHasKey('large_url', $result);
        $this->assertArrayHasKey('original_name', $result);
        $this->assertArrayHasKey('filename', $result);
        
        // Verify URLs are strings
        $this->assertIsString($result['url']);
        $this->assertIsString($result['thumbnail_url']);
        $this->assertIsString($result['medium_url']);
        $this->assertIsString($result['large_url']);
        
        // Verify filename format
        $this->assertStringContains("product_{$productId}_", $result['filename']);
        $this->assertStringEndsWith('.jpg', $result['filename']);
        
        // Verify original name is preserved
        $this->assertEquals('test_image.jpg', $result['original_name']);
    }
    
    /**
     * Test file size validation
     */
    public function testFileSizeValidation() {
        // Test file too large (6MB > 5MB limit)
        $fileInfo = $this->createMockFileInfo('large_image.jpg', 'image/jpeg', 6 * 1024 * 1024);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size');
        $this->imageService->uploadProductImage($fileInfo, 123);
    }
    
    /**
     * Test file type validation
     */
    public function testFileTypeValidation() {
        // Test invalid file type
        $fileInfo = $this->createMockFileInfo('document.pdf', 'application/pdf', 1024);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid file type');
        $this->imageService->uploadProductImage($fileInfo, 123);
    }
    
    /**
     * Test image dimension validation
     */
    public function testImageDimensionValidation() {
        // Test image too small (50x50 < 100x100 minimum)
        $fileInfo = $this->createMockFileInfo('small_image.jpg', 'image/jpeg', 1024, 50, 50);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Image dimensions too small');
        $this->imageService->uploadProductImage($fileInfo, 123);
        
        // Test image too large (6000x6000 > 5000x5000 maximum)
        $fileInfo = $this->createMockFileInfo('huge_image.jpg', 'image/jpeg', 1024, 6000, 6000);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Image dimensions too large');
        $this->imageService->uploadProductImage($fileInfo, 123);
    }
    
    /**
     * Test upload error handling
     */
    public function testUploadErrorHandling() {
        $errorCodes = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        foreach ($errorCodes as $errorCode => $expectedMessage) {
            $fileInfo = $this->createMockFileInfo('test.jpg', 'image/jpeg', 1024);
            $fileInfo['error'] = $errorCode;
            
            try {
                $this->imageService->uploadProductImage($fileInfo, 123);
                $this->fail("Expected exception for error code: $errorCode");
            } catch (Exception $e) {
                $this->assertStringContains($expectedMessage, $e->getMessage());
            }
        }
    }
    
    /**
     * Test supported image formats
     */
    public function testSupportedImageFormats() {
        $supportedFormats = [
            'test.jpg' => 'image/jpeg',
            'test.png' => 'image/png',
            'test.webp' => 'image/webp',
            'test.gif' => 'image/gif'
        ];
        
        foreach ($supportedFormats as $filename => $mimeType) {
            $fileInfo = $this->createMockFileInfo($filename, $mimeType, 1024);
            
            try {
                $result = $this->imageService->uploadProductImage($fileInfo, 123);
                $this->assertArrayHasKey('url', $result);
                $this->assertEquals($filename, $result['original_name']);
            } catch (Exception $e) {
                $this->fail("Should support format $mimeType: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test image deletion
     */
    public function testImageDeletion() {
        // First upload an image
        $fileInfo = $this->createMockFileInfo('delete_test.jpg', 'image/jpeg', 1024);
        $productId = 456;
        
        $result = $this->imageService->uploadProductImage($fileInfo, $productId);
        $filename = $result['filename'];
        
        // Verify files exist
        $productDir = __DIR__ . "/../uploads/products/{$productId}/";
        $this->assertTrue(is_dir($productDir));
        
        // Delete specific image
        $success = $this->imageService->deleteProductImages($productId, [$filename]);
        $this->assertTrue($success);
        
        // Delete all images for product
        $success = $this->imageService->deleteProductImages($productId);
        $this->assertTrue($success);
    }
    
    /**
     * Test image info retrieval
     */
    public function testImageInfoRetrieval() {
        // Test with existing image
        $imagePath = $this->testImages['valid_jpeg'];
        $info = $this->imageService->getImageInfo($imagePath);
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('width', $info);
        $this->assertArrayHasKey('height', $info);
        $this->assertArrayHasKey('mime_type', $info);
        $this->assertArrayHasKey('file_size', $info);
        $this->assertArrayHasKey('file_name', $info);
        
        $this->assertEquals(200, $info['width']);
        $this->assertEquals(200, $info['height']);
        $this->assertEquals('image/jpeg', $info['mime_type']);
        
        // Test with non-existent image
        $info = $this->imageService->getImageInfo('/non/existent/path.jpg');
        $this->assertNull($info);
    }
    
    /**
     * Test image optimization
     */
    public function testImageOptimization() {
        $imagePath = $this->testImages['valid_jpeg'];
        $originalSize = filesize($imagePath);
        
        $success = $this->imageService->optimizeImage($imagePath);
        $this->assertTrue($success);
        
        // File should still exist and be valid
        $this->assertTrue(file_exists($imagePath));
        $info = $this->imageService->getImageInfo($imagePath);
        $this->assertIsArray($info);
        $this->assertEquals('image/jpeg', $info['mime_type']);
        
        // Test with non-existent file
        $success = $this->imageService->optimizeImage('/non/existent/path.jpg');
        $this->assertFalse($success);
    }
    
    /**
     * Test filename generation
     */
    public function testFilenameGeneration() {
        $fileInfo1 = $this->createMockFileInfo('test.jpg', 'image/jpeg', 1024);
        $fileInfo2 = $this->createMockFileInfo('test.jpg', 'image/jpeg', 1024);
        
        $result1 = $this->imageService->uploadProductImage($fileInfo1, 123);
        $result2 = $this->imageService->uploadProductImage($fileInfo2, 123);
        
        // Filenames should be different even for same original name
        $this->assertNotEquals($result1['filename'], $result2['filename']);
        
        // Both should contain product ID
        $this->assertStringContains('product_123_', $result1['filename']);
        $this->assertStringContains('product_123_', $result2['filename']);
    }
    
    /**
     * Test directory creation
     */
    public function testDirectoryCreation() {
        $productId = 999;
        $fileInfo = $this->createMockFileInfo('dir_test.jpg', 'image/jpeg', 1024);
        
        // Product directory shouldn't exist initially
        $productDir = __DIR__ . "/../uploads/products/{$productId}/";
        $this->assertFalse(is_dir($productDir));
        
        // Upload should create directory
        $result = $this->imageService->uploadProductImage($fileInfo, $productId);
        $this->assertTrue(is_dir($productDir));
        
        // Clean up
        $this->imageService->deleteProductImages($productId);
    }
    
    /**
     * Test security validation
     */
    public function testSecurityValidation() {
        // Test file with malicious name
        $fileInfo = $this->createMockFileInfo('../../../etc/passwd', 'image/jpeg', 1024);
        
        try {
            $result = $this->imageService->uploadProductImage($fileInfo, 123);
            // Should still work but filename should be sanitized
            $this->assertStringNotContains('..', $result['filename']);
            $this->assertStringNotContains('/', $result['filename']);
        } catch (Exception $e) {
            // Or it might reject the file entirely, which is also acceptable
            $this->assertStringContains('Invalid', $e->getMessage());
        }
    }
    
    /**
     * Create mock file info array
     */
    private function createMockFileInfo($name, $mimeType, $size, $width = 200, $height = 200) {
        // Create a temporary test image file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
        $this->createTestImage($tempFile, $width, $height, $mimeType);
        
        return [
            'name' => $name,
            'type' => $mimeType,
            'size' => $size,
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK
        ];
    }
    
    /**
     * Create test images for testing
     */
    private function createTestImages() {
        // Create valid JPEG
        $jpegPath = $this->testUploadPath . 'test_valid.jpg';
        $this->createTestImage($jpegPath, 200, 200, 'image/jpeg');
        $this->testImages['valid_jpeg'] = $jpegPath;
        
        // Create valid PNG
        $pngPath = $this->testUploadPath . 'test_valid.png';
        $this->createTestImage($pngPath, 200, 200, 'image/png');
        $this->testImages['valid_png'] = $pngPath;
        
        // Create small image
        $smallPath = $this->testUploadPath . 'test_small.jpg';
        $this->createTestImage($smallPath, 50, 50, 'image/jpeg');
        $this->testImages['small_jpeg'] = $smallPath;
    }
    
    /**
     * Create a test image file
     */
    private function createTestImage($path, $width, $height, $mimeType) {
        $image = imagecreatetruecolor($width, $height);
        
        // Fill with a color
        $color = imagecolorallocate($image, 255, 0, 0); // Red
        imagefill($image, 0, 0, $color);
        
        // Save based on mime type
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($image, $path, 85);
                break;
            case 'image/png':
                imagepng($image, $path);
                break;
            case 'image/gif':
                imagegif($image, $path);
                break;
            case 'image/webp':
                imagewebp($image, $path, 85);
                break;
        }
        
        imagedestroy($image);
    }
    
    /**
     * Clean up test files and directories
     */
    private function cleanupTestFiles() {
        // Remove test upload directory
        if (is_dir($this->testUploadPath)) {
            $files = glob($this->testUploadPath . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testUploadPath);
        }
        
        // Clean up product directories created during tests
        $uploadBase = __DIR__ . '/../uploads/products/';
        $testProductIds = [123, 456, 999];
        
        foreach ($testProductIds as $productId) {
            $productDir = $uploadBase . $productId . '/';
            if (is_dir($productDir)) {
                $files = glob($productDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($productDir);
            }
        }
        
        // Clean up temporary files
        $tempFiles = glob(sys_get_temp_dir() . '/test_image_*');
        foreach ($tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}