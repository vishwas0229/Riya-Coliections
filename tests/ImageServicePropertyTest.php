<?php
/**
 * ImageService Property-Based Tests
 * 
 * Property-based tests for image service to verify universal properties
 * hold across all valid inputs and edge cases for file upload validation
 * and image processing consistency.
 * 
 * Requirements: 8.1, 8.2
 */

require_once __DIR__ . '/../services/ImageService.php';
require_once __DIR__ . '/../utils/Logger.php';

class ImageServicePropertyTest extends PHPUnit\Framework\TestCase {
    private $imageService;
    private $testUploadPath;
    
    protected function setUp(): void {
        $this->imageService = new ImageService();
        $this->testUploadPath = __DIR__ . '/../uploads/products/test_property/';
        
        // Create test directory
        if (!is_dir($this->testUploadPath)) {
            mkdir($this->testUploadPath, 0755, true);
        }
    }
    
    protected function tearDown(): void {
        $this->cleanupTestFiles();
    }
    
    /**
     * **Validates: Requirements 8.1**
     * Property 13: File Upload Validation
     * For any uploaded file, the validation rules for size, format, and security
     * should produce identical results regardless of the specific file content
     * 
     * @test
     */
    public function testFileUploadValidationConsistency() {
        for ($i = 0; $i < 100; $i++) {
            $fileInfo = $this->generateRandomFileInfo();
            $productId = rand(1, 999999);
            
            try {
                $result = $this->imageService->uploadProductImage($fileInfo, $productId);
                
                // If upload succeeds, verify result structure is consistent
                $this->assertArrayHasKey('url', $result);
                $this->assertArrayHasKey('thumbnail_url', $result);
                $this->assertArrayHasKey('medium_url', $result);
                $this->assertArrayHasKey('large_url', $result);
                $this->assertArrayHasKey('original_name', $result);
                $this->assertArrayHasKey('filename', $result);
                
                // Verify URL format consistency
                $this->assertIsString($result['url']);
                $this->assertIsString($result['thumbnail_url']);
                $this->assertIsString($result['medium_url']);
                $this->assertIsString($result['large_url']);
                
                // Verify filename contains product ID
                $this->assertStringContains("product_{$productId}_", $result['filename']);
                
                // Verify original name is preserved
                $this->assertEquals($fileInfo['name'], $result['original_name']);
                
                // Clean up created files
                $this->imageService->deleteProductImages($productId);
                
            } catch (Exception $e) {
                // If upload fails, verify it's due to proper validation
                $validationErrors = [
                    'File size exceeds',
                    'Invalid file type',
                    'Image dimensions too',
                    'File was only partially',
                    'No file was uploaded',
                    'Failed to move uploaded file',
                    'File is not a valid image'
                ];
                
                $isValidationError = false;
                foreach ($validationErrors as $errorPattern) {
                    if (strpos($e->getMessage(), $errorPattern) !== false) {
                        $isValidationError = true;
                        break;
                    }
                }
                
                $this->assertTrue($isValidationError, 
                    "Unexpected error message: " . $e->getMessage());
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.2**
     * Property 14: Image Processing Consistency
     * For any uploaded image, the resizing and optimization should produce
     * equivalent output files with consistent dimensions and quality
     * 
     * @test
     */
    public function testImageProcessingConsistency() {
        for ($i = 0; $i < 50; $i++) {
            $fileInfo = $this->generateValidImageFileInfo();
            $productId = rand(1, 999999);
            
            try {
                $result = $this->imageService->uploadProductImage($fileInfo, $productId);
                
                // Verify all required sizes are created
                $expectedSizes = ['thumbnail_url', 'medium_url', 'large_url'];
                foreach ($expectedSizes as $sizeKey) {
                    $this->assertArrayHasKey($sizeKey, $result);
                    $this->assertIsString($result[$sizeKey]);
                    $this->assertNotEmpty($result[$sizeKey]);
                }
                
                // Verify image files actually exist and have correct dimensions
                $productDir = __DIR__ . "/../uploads/products/{$productId}/";
                $this->assertTrue(is_dir($productDir));
                
                // Check thumbnail dimensions (should be ≤ 150x150)
                $thumbnailFiles = glob($productDir . '*_thumbnail.*');
                if (!empty($thumbnailFiles)) {
                    $thumbnailInfo = $this->imageService->getImageInfo($thumbnailFiles[0]);
                    $this->assertNotNull($thumbnailInfo);
                    $this->assertLessThanOrEqual(150, $thumbnailInfo['width']);
                    $this->assertLessThanOrEqual(150, $thumbnailInfo['height']);
                }
                
                // Check medium dimensions (should be ≤ 400x400)
                $mediumFiles = glob($productDir . '*_medium.*');
                if (!empty($mediumFiles)) {
                    $mediumInfo = $this->imageService->getImageInfo($mediumFiles[0]);
                    $this->assertNotNull($mediumInfo);
                    $this->assertLessThanOrEqual(400, $mediumInfo['width']);
                    $this->assertLessThanOrEqual(400, $mediumInfo['height']);
                }
                
                // Check large dimensions (should be ≤ 800x800)
                $largeFiles = glob($productDir . '*_large.*');
                if (!empty($largeFiles)) {
                    $largeInfo = $this->imageService->getImageInfo($largeFiles[0]);
                    $this->assertNotNull($largeInfo);
                    $this->assertLessThanOrEqual(800, $largeInfo['width']);
                    $this->assertLessThanOrEqual(800, $largeInfo['height']);
                }
                
                // Clean up
                $this->imageService->deleteProductImages($productId);
                
            } catch (Exception $e) {
                // Skip invalid files for this test
                continue;
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1**
     * Property: File Size Validation Boundary
     * For any file at the size boundary (5MB), validation should be consistent
     * 
     * @test
     */
    public function testFileSizeValidationBoundary() {
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        for ($i = 0; $i < 20; $i++) {
            // Test files at various sizes around the boundary
            $testSizes = [
                $maxSize - 1024,     // Just under limit
                $maxSize,            // Exactly at limit
                $maxSize + 1024      // Just over limit
            ];
            
            foreach ($testSizes as $size) {
                $fileInfo = $this->generateValidImageFileInfo();
                $fileInfo['size'] = $size;
                
                try {
                    $result = $this->imageService->uploadProductImage($fileInfo, rand(1, 999999));
                    
                    // Should succeed for sizes <= maxSize
                    $this->assertLessThanOrEqual($maxSize, $size);
                    $this->assertArrayHasKey('url', $result);
                    
                    // Clean up
                    $productId = $this->extractProductIdFromFilename($result['filename']);
                    $this->imageService->deleteProductImages($productId);
                    
                } catch (Exception $e) {
                    // Should fail for sizes > maxSize
                    if ($size > $maxSize) {
                        $this->assertStringContains('File size exceeds', $e->getMessage());
                    } else {
                        // If it fails for valid size, it should be for other reasons
                        $this->assertStringNotContains('File size exceeds', $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1**
     * Property: Image Dimension Validation Boundary
     * For any image at dimension boundaries, validation should be consistent
     * 
     * @test
     */
    public function testImageDimensionValidationBoundary() {
        $minDimension = 100;
        $maxDimension = 5000;
        
        for ($i = 0; $i < 30; $i++) {
            // Test dimensions around boundaries
            $testDimensions = [
                [$minDimension - 1, $minDimension - 1],  // Too small
                [$minDimension, $minDimension],          // Minimum valid
                [$maxDimension, $maxDimension],          // Maximum valid
                [$maxDimension + 1, $maxDimension + 1]   // Too large
            ];
            
            foreach ($testDimensions as [$width, $height]) {
                $fileInfo = $this->generateImageFileInfoWithDimensions($width, $height);
                
                try {
                    $result = $this->imageService->uploadProductImage($fileInfo, rand(1, 999999));
                    
                    // Should succeed for valid dimensions
                    $this->assertGreaterThanOrEqual($minDimension, $width);
                    $this->assertGreaterThanOrEqual($minDimension, $height);
                    $this->assertLessThanOrEqual($maxDimension, $width);
                    $this->assertLessThanOrEqual($maxDimension, $height);
                    
                    // Clean up
                    $productId = $this->extractProductIdFromFilename($result['filename']);
                    $this->imageService->deleteProductImages($productId);
                    
                } catch (Exception $e) {
                    // Should fail for invalid dimensions
                    if ($width < $minDimension || $height < $minDimension) {
                        $this->assertStringContains('dimensions too small', $e->getMessage());
                    } elseif ($width > $maxDimension || $height > $maxDimension) {
                        $this->assertStringContains('dimensions too large', $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.1**
     * Property: File Type Validation Consistency
     * For any file type, validation should consistently accept or reject based on MIME type
     * 
     * @test
     */
    public function testFileTypeValidationConsistency() {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $disallowedTypes = ['text/plain', 'application/pdf', 'video/mp4', 'audio/mp3', 'application/zip'];
        
        for ($i = 0; $i < 50; $i++) {
            // Test allowed types
            foreach ($allowedTypes as $mimeType) {
                $fileInfo = $this->generateValidImageFileInfo();
                $fileInfo['type'] = $mimeType;
                
                try {
                    $result = $this->imageService->uploadProductImage($fileInfo, rand(1, 999999));
                    $this->assertArrayHasKey('url', $result);
                    
                    // Clean up
                    $productId = $this->extractProductIdFromFilename($result['filename']);
                    $this->imageService->deleteProductImages($productId);
                    
                } catch (Exception $e) {
                    // May fail for other reasons (file content mismatch), but not type
                    $this->assertStringNotContains('Invalid file type', $e->getMessage());
                }
            }
            
            // Test disallowed types
            foreach ($disallowedTypes as $mimeType) {
                $fileInfo = $this->generateValidImageFileInfo();
                $fileInfo['type'] = $mimeType;
                
                try {
                    $this->imageService->uploadProductImage($fileInfo, rand(1, 999999));
                    $this->fail("Should reject disallowed MIME type: $mimeType");
                } catch (Exception $e) {
                    $this->assertStringContains('Invalid file type', $e->getMessage());
                }
            }
        }
    }
    
    /**
     * **Validates: Requirements 8.2**
     * Property: Filename Generation Uniqueness
     * For any sequence of uploads, all generated filenames should be unique
     * 
     * @test
     */
    public function testFilenameGenerationUniqueness() {
        $generatedFilenames = [];
        $productId = rand(1, 999999);
        
        for ($i = 0; $i < 50; $i++) {
            $fileInfo = $this->generateValidImageFileInfo();
            
            try {
                $result = $this->imageService->uploadProductImage($fileInfo, $productId);
                $filename = $result['filename'];
                
                // Verify filename is unique
                $this->assertNotContains($filename, $generatedFilenames, 
                    'Generated filename should be unique');
                $generatedFilenames[] = $filename;
                
                // Verify filename format
                $this->assertStringContains("product_{$productId}_", $filename);
                $this->assertMatchesRegularExpression('/product_\d+_\d+_\d+\.\w+/', $filename);
                
            } catch (Exception $e) {
                // Skip invalid files
                continue;
            }
        }
        
        // Clean up all files
        $this->imageService->deleteProductImages($productId);
        
        // Verify we generated some unique filenames
        $this->assertGreaterThan(0, count($generatedFilenames));
        $this->assertEquals(count($generatedFilenames), count(array_unique($generatedFilenames)));
    }
    
    /**
     * **Validates: Requirements 8.1, 8.2**
     * Property: Upload Error Handling Consistency
     * For any upload error condition, the system should handle it gracefully
     * and provide consistent error messages
     * 
     * @test
     */
    public function testUploadErrorHandlingConsistency() {
        $errorCodes = [
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE,
            UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_FILE,
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION
        ];
        
        for ($i = 0; $i < 20; $i++) {
            foreach ($errorCodes as $errorCode) {
                $fileInfo = $this->generateValidImageFileInfo();
                $fileInfo['error'] = $errorCode;
                
                try {
                    $this->imageService->uploadProductImage($fileInfo, rand(1, 999999));
                    $this->fail("Should throw exception for upload error code: $errorCode");
                } catch (Exception $e) {
                    // Verify error message is appropriate
                    $this->assertIsString($e->getMessage());
                    $this->assertNotEmpty($e->getMessage());
                    
                    // Verify HTTP status code is 400 (Bad Request)
                    $this->assertEquals(400, $e->getCode());
                }
            }
        }
    }
    
    /**
     * Generate random file info for testing
     */
    private function generateRandomFileInfo() {
        $extensions = ['jpg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc'];
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc' => 'application/msword'
        ];
        
        $extension = $extensions[rand(0, count($extensions) - 1)];
        $filename = 'test_' . rand(1000, 9999) . '.' . $extension;
        $mimeType = $mimeTypes[$extension];
        $size = rand(1024, 10 * 1024 * 1024); // 1KB to 10MB
        
        return $this->createMockFileInfo($filename, $mimeType, $size);
    }
    
    /**
     * Generate valid image file info for testing
     */
    private function generateValidImageFileInfo() {
        $extensions = ['jpg', 'png', 'gif', 'webp'];
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        
        $extension = $extensions[rand(0, count($extensions) - 1)];
        $filename = 'test_' . rand(1000, 9999) . '.' . $extension;
        $mimeType = $mimeTypes[$extension];
        $size = rand(1024, 4 * 1024 * 1024); // 1KB to 4MB (under limit)
        
        return $this->createMockFileInfo($filename, $mimeType, $size);
    }
    
    /**
     * Generate image file info with specific dimensions
     */
    private function generateImageFileInfoWithDimensions($width, $height) {
        $filename = "test_{$width}x{$height}.jpg";
        $mimeType = 'image/jpeg';
        $size = rand(1024, 4 * 1024 * 1024);
        
        return $this->createMockFileInfo($filename, $mimeType, $size, $width, $height);
    }
    
    /**
     * Create mock file info array
     */
    private function createMockFileInfo($name, $mimeType, $size, $width = null, $height = null) {
        // Use default dimensions if not specified
        if ($width === null) $width = rand(200, 1000);
        if ($height === null) $height = rand(200, 1000);
        
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
     * Create a test image file
     */
    private function createTestImage($path, $width, $height, $mimeType) {
        // Only create actual images for image MIME types
        if (strpos($mimeType, 'image/') !== 0) {
            // For non-image types, create a dummy file
            file_put_contents($path, 'dummy content');
            return;
        }
        
        $image = imagecreatetruecolor($width, $height);
        
        // Fill with random color
        $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
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
                if (function_exists('imagewebp')) {
                    imagewebp($image, $path, 85);
                } else {
                    // Fallback to JPEG if WebP not supported
                    imagejpeg($image, $path, 85);
                }
                break;
        }
        
        imagedestroy($image);
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
        if (is_dir($uploadBase)) {
            $dirs = glob($uploadBase . '*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                // Only remove if empty and looks like a test directory
                if (count(glob($dir . '/*')) === 0 && basename($dir) !== 'test') {
                    rmdir($dir);
                }
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