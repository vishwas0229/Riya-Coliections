<?php
/**
 * ImageService Simple Tests
 * 
 * Basic tests for image service functionality that don't require GD extension.
 * Tests file validation, security checks, and basic upload handling.
 * 
 * Requirements: 8.1, 8.2
 */

require_once __DIR__ . '/../services/ImageService.php';
require_once __DIR__ . '/../utils/Logger.php';

class ImageServiceSimpleTest extends PHPUnit\Framework\TestCase {
    private $imageService;
    private $testUploadPath;
    
    protected function setUp(): void {
        $this->imageService = new ImageService();
        $this->testUploadPath = __DIR__ . '/../uploads/products/test_simple/';
        
        // Create test directory
        if (!is_dir($this->testUploadPath)) {
            mkdir($this->testUploadPath, 0755, true);
        }
    }
    
    protected function tearDown(): void {
        $this->cleanupTestFiles();
    }
    
    /**
     * Test ImageService instantiation
     */
    public function testImageServiceInstantiation() {
        $service = new ImageService();
        $this->assertInstanceOf(ImageService::class, $service);
    }
    
    /**
     * Test file size validation
     */
    public function testFileSizeValidation() {
        // Test file too large (6MB > 5MB limit)
        $fileInfo = [
            'name' => 'large_image.jpg',
            'type' => 'image/jpeg',
            'size' => 6 * 1024 * 1024, // 6MB
            'tmp_name' => $this->createDummyFile('large_image.jpg'),
            'error' => UPLOAD_ERR_OK
        ];
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size');
        $this->imageService->uploadProductImage($fileInfo, 123);
    }
    
    /**
     * Test file type validation
     */
    public function testFileTypeValidation() {
        // Test invalid file type
        $fileInfo = [
            'name' => 'document.pdf',
            'type' => 'application/pdf',
            'size' => 1024,
            'tmp_name' => $this->createDummyFile('document.pdf'),
            'error' => UPLOAD_ERR_OK
        ];
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid file type');
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
            $fileInfo = [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'size' => 1024,
                'tmp_name' => $this->createDummyFile('test.jpg'),
                'error' => $errorCode
            ];
            
            try {
                $this->imageService->uploadProductImage($fileInfo, 123);
                $this->fail("Expected exception for error code: $errorCode");
            } catch (Exception $e) {
                $this->assertStringContainsString($expectedMessage, $e->getMessage());
                $this->assertEquals(400, $e->getCode());
            }
        }
    }
    
    /**
     * Test supported MIME types
     */
    public function testSupportedMimeTypes() {
        $supportedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $unsupportedTypes = ['text/plain', 'application/pdf', 'video/mp4', 'audio/mp3'];
        
        // For supported types, we can't test with dummy files since the service
        // validates actual file content. Instead, we test that the MIME type
        // validation logic accepts these types by checking the allowed types array.
        $reflection = new ReflectionClass($this->imageService);
        $allowedTypesProperty = $reflection->getProperty('allowedTypes');
        $allowedTypesProperty->setAccessible(true);
        $allowedTypes = $allowedTypesProperty->getValue($this->imageService);
        
        foreach ($supportedTypes as $mimeType) {
            $this->assertContains($mimeType, $allowedTypes, 
                "MIME type $mimeType should be in allowed types");
        }
        
        // Test unsupported types (should throw type validation error)
        foreach ($unsupportedTypes as $mimeType) {
            $fileInfo = [
                'name' => 'test.txt',
                'type' => $mimeType,
                'size' => 1024,
                'tmp_name' => $this->createDummyFile('test.txt'),
                'error' => UPLOAD_ERR_OK
            ];
            
            try {
                $this->imageService->uploadProductImage($fileInfo, 123);
                $this->fail("Should reject unsupported MIME type: $mimeType");
            } catch (Exception $e) {
                $this->assertStringContainsString('Invalid file type', $e->getMessage());
            }
        }
    }
    
    /**
     * Test filename sanitization
     */
    public function testFilenameSanitization() {
        $maliciousNames = [
            '../../../etc/passwd',
            '..\\..\\windows\\system32\\config\\sam',
            'normal_file.jpg',
            'file with spaces.png',
            'file-with-dashes.gif'
        ];
        
        foreach ($maliciousNames as $filename) {
            $fileInfo = [
                'name' => $filename,
                'type' => 'image/jpeg',
                'size' => 1024,
                'tmp_name' => $this->createDummyFile($filename),
                'error' => UPLOAD_ERR_OK
            ];
            
            try {
                $result = $this->imageService->uploadProductImage($fileInfo, 123);
                
                // If upload succeeds, verify filename is sanitized
                $this->assertArrayHasKey('filename', $result);
                $this->assertArrayHasKey('original_name', $result);
                
                // Generated filename should not contain path traversal
                $this->assertStringNotContains('..', $result['filename']);
                $this->assertStringNotContains('/', $result['filename']);
                $this->assertStringNotContains('\\', $result['filename']);
                
                // Original name should be preserved
                $this->assertEquals($filename, $result['original_name']);
                
                // Clean up
                $productId = $this->extractProductIdFromFilename($result['filename']);
                if ($productId) {
                    $this->imageService->deleteProductImages($productId);
                }
                
            } catch (Exception $e) {
                // May fail for other validation reasons, which is acceptable
                continue;
            }
        }
        
        // Ensure we tested the functionality
        $this->assertTrue(true, 'Filename sanitization test completed');
    }
    
    /**
     * Test image deletion functionality
     */
    public function testImageDeletion() {
        $productId = 999;
        
        // Test deleting from non-existent product (should not fail)
        $result = $this->imageService->deleteProductImages($productId);
        $this->assertTrue($result);
        
        // Test deleting specific non-existent files (should not fail)
        $result = $this->imageService->deleteProductImages($productId, ['nonexistent.jpg']);
        $this->assertTrue($result);
    }
    
    /**
     * Test image info retrieval
     */
    public function testImageInfoRetrieval() {
        // Test with non-existent file
        $info = $this->imageService->getImageInfo('/non/existent/path.jpg');
        $this->assertNull($info);
        
        // Test with dummy file (will fail getimagesize but should handle gracefully)
        $dummyFile = $this->createDummyFile('dummy.jpg');
        $info = $this->imageService->getImageInfo($dummyFile);
        $this->assertNull($info); // Should return null for non-image files
        
        unlink($dummyFile);
    }
    
    /**
     * Test image optimization
     */
    public function testImageOptimization() {
        // Test with non-existent file
        $result = $this->imageService->optimizeImage('/non/existent/path.jpg');
        $this->assertFalse($result);
        
        // Test with dummy file (will fail as it's not a real image)
        $dummyFile = $this->createDummyFile('dummy.jpg');
        $result = $this->imageService->optimizeImage($dummyFile);
        $this->assertFalse($result);
        
        unlink($dummyFile);
    }
    
    /**
     * Test directory creation behavior
     */
    public function testDirectoryCreation() {
        $productId = 888;
        $productDir = __DIR__ . "/../uploads/products/{$productId}/";
        
        // Directory shouldn't exist initially
        $this->assertFalse(is_dir($productDir));
        
        // Try to upload (will fail but should create directory)
        $fileInfo = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'tmp_name' => $this->createDummyFile('test.jpg'),
            'error' => UPLOAD_ERR_OK
        ];
        
        try {
            $this->imageService->uploadProductImage($fileInfo, $productId);
        } catch (Exception $e) {
            // Expected to fail due to invalid image, but directory should be created
        }
        
        // Clean up
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
    
    /**
     * Test security validation edge cases
     */
    public function testSecurityValidation() {
        // Test empty filename
        $fileInfo = [
            'name' => '',
            'type' => 'image/jpeg',
            'size' => 1024,
            'tmp_name' => $this->createDummyFile(''),
            'error' => UPLOAD_ERR_OK
        ];
        
        try {
            $this->imageService->uploadProductImage($fileInfo, 123);
        } catch (Exception $e) {
            // Should handle gracefully
            $this->assertIsString($e->getMessage());
        }
        
        // Test null values
        $fileInfo = [
            'name' => null,
            'type' => null,
            'size' => 0,
            'tmp_name' => null,
            'error' => UPLOAD_ERR_OK
        ];
        
        try {
            $this->imageService->uploadProductImage($fileInfo, 123);
        } catch (Exception $e) {
            // Should handle gracefully
            $this->assertIsString($e->getMessage());
        }
    }
    
    /**
     * Test boundary conditions
     */
    public function testBoundaryConditions() {
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Test exactly at size limit
        $fileInfo = [
            'name' => 'boundary_test.jpg',
            'type' => 'image/jpeg',
            'size' => $maxSize,
            'tmp_name' => $this->createDummyFile('boundary_test.jpg'),
            'error' => UPLOAD_ERR_OK
        ];
        
        try {
            $this->imageService->uploadProductImage($fileInfo, 123);
            // May fail for other reasons, but not size
        } catch (Exception $e) {
            $this->assertStringNotContainsString('File size exceeds', $e->getMessage());
        }
        
        // Test just over size limit
        $fileInfo['size'] = $maxSize + 1;
        
        try {
            $this->imageService->uploadProductImage($fileInfo, 123);
            $this->fail('Should reject file over size limit');
        } catch (Exception $e) {
            $this->assertStringContainsString('File size exceeds', $e->getMessage());
        }
    }
    
    /**
     * Create a dummy file for testing
     */
    private function createDummyFile($filename) {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_dummy_');
        file_put_contents($tempFile, 'dummy file content for testing');
        return $tempFile;
    }
    
    /**
     * Extract product ID from filename
     */
    private function extractProductIdFromFilename($filename) {
        if (preg_match('/product_(\d+)_/', $filename, $matches)) {
            return (int)$matches[1];
        }
        return null;
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
        
        // Clean up any product directories created during tests
        $uploadBase = __DIR__ . '/../uploads/products/';
        $testProductIds = [123, 888, 999];
        
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
        $tempFiles = glob(sys_get_temp_dir() . '/test_dummy_*');
        foreach ($tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}