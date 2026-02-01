<?php
/**
 * Image Service Class
 * 
 * Handles image upload, processing, and management for products.
 * Provides image resizing, optimization, and validation capabilities.
 * 
 * Requirements: 8.1, 8.2
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../config/environment.php';

class ImageService {
    private $uploadPath;
    private $allowedTypes;
    private $maxFileSize;
    private $imageQuality;
    private $thumbnailSizes;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->uploadPath = __DIR__ . '/../uploads/products/';
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
        $this->imageQuality = 85;
        $this->thumbnailSizes = [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 400, 'height' => 400],
            'large' => ['width' => 800, 'height' => 800]
        ];
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
        
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            Logger::warning('GD extension not available. Image processing will be limited.');
        }
    }
    
    /**
     * Upload and process product image
     * 
     * @param array $fileInfo File information from $_FILES
     * @param int $productId Product ID
     * @return array Upload result with image URLs
     * @throws Exception If upload fails
     */
    public function uploadProductImage($fileInfo, $productId) {
        try {
            // Validate file
            $this->validateFile($fileInfo);
            
            // Generate unique filename
            $originalName = $fileInfo['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $filename = $this->generateFilename($productId, $extension);
            
            // Create product directory
            $productDir = $this->uploadPath . $productId . '/';
            if (!is_dir($productDir)) {
                mkdir($productDir, 0755, true);
            }
            
            // Move uploaded file
            $originalPath = $productDir . $filename;
            if (!move_uploaded_file($fileInfo['tmp_name'], $originalPath)) {
                throw new Exception('Failed to move uploaded file', 500);
            }
            
            // Process image (resize and optimize)
            $processedImages = $this->processImage($originalPath, $productDir, $filename);
            
            // Generate URLs
            $baseUrl = $this->getBaseUrl();
            $imageUrls = [];
            
            foreach ($processedImages as $size => $path) {
                $relativePath = str_replace(__DIR__ . '/../', '', $path);
                $imageUrls[$size] = $baseUrl . '/' . $relativePath;
            }
            
            Logger::info('Product image uploaded successfully', [
                'product_id' => $productId,
                'original_name' => $originalName,
                'filename' => $filename,
                'sizes_created' => array_keys($processedImages)
            ]);
            
            return [
                'url' => $imageUrls['large'], // Main image URL
                'thumbnail_url' => $imageUrls['thumbnail'],
                'medium_url' => $imageUrls['medium'],
                'large_url' => $imageUrls['large'],
                'original_name' => $originalName,
                'filename' => $filename,
                'alt_text' => null,
                'is_primary' => false,
                'sort_order' => 0
            ];
            
        } catch (Exception $e) {
            Logger::error('Image upload failed', [
                'product_id' => $productId,
                'original_name' => $fileInfo['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $fileInfo File information
     * @throws Exception If validation fails
     */
    private function validateFile($fileInfo) {
        // Check for upload errors
        if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
                UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            
            $message = $errorMessages[$fileInfo['error']] ?? 'Unknown upload error';
            throw new Exception($message, 400);
        }
        
        // Check if tmp_name is valid
        if (empty($fileInfo['tmp_name']) || !file_exists($fileInfo['tmp_name'])) {
            throw new Exception('Uploaded file not found', 400);
        }
        
        // Check file size
        if ($fileInfo['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB', 400);
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileInfo['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Invalid file type. Allowed types: JPEG, PNG, WebP, GIF', 400);
        }
        
        // Check if file is actually an image
        $imageInfo = getimagesize($fileInfo['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('File is not a valid image', 400);
        }
        
        // Check image dimensions (minimum size)
        if ($imageInfo[0] < 100 || $imageInfo[1] < 100) {
            throw new Exception('Image dimensions too small. Minimum size: 100x100 pixels', 400);
        }
        
        // Check image dimensions (maximum size)
        if ($imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
            throw new Exception('Image dimensions too large. Maximum size: 5000x5000 pixels', 400);
        }
    }
    
    /**
     * Process image (resize and optimize)
     * 
     * @param string $originalPath Path to original image
     * @param string $outputDir Output directory
     * @param string $filename Base filename
     * @return array Processed image paths
     */
    private function processImage($originalPath, $outputDir, $filename) {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            // If GD is not available, just copy the original file as "large"
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $largeFilename = $nameWithoutExt . '_large.' . $extension;
            $largePath = $outputDir . $largeFilename;
            
            copy($originalPath, $largePath);
            unlink($originalPath);
            
            // Create placeholder entries for other sizes
            return [
                'thumbnail' => $largePath,
                'medium' => $largePath,
                'large' => $largePath
            ];
        }
        
        $processedImages = [];
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Get original image info
        $imageInfo = getimagesize($originalPath);
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Create image resource from original
        $originalImage = $this->createImageFromFile($originalPath, $mimeType);
        
        if (!$originalImage) {
            throw new Exception('Failed to create image resource', 500);
        }
        
        // Process each thumbnail size
        foreach ($this->thumbnailSizes as $sizeName => $dimensions) {
            $targetWidth = $dimensions['width'];
            $targetHeight = $dimensions['height'];
            
            // Calculate resize dimensions (maintain aspect ratio)
            $resizeDimensions = $this->calculateResizeDimensions(
                $originalWidth, 
                $originalHeight, 
                $targetWidth, 
                $targetHeight
            );
            
            // Create resized image
            $resizedImage = imagecreatetruecolor($resizeDimensions['width'], $resizeDimensions['height']);
            
            // Preserve transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }
            
            // Resize image
            imagecopyresampled(
                $resizedImage, $originalImage,
                0, 0, 0, 0,
                $resizeDimensions['width'], $resizeDimensions['height'],
                $originalWidth, $originalHeight
            );
            
            // Save resized image
            $outputFilename = $nameWithoutExt . '_' . $sizeName . '.' . $extension;
            $outputPath = $outputDir . $outputFilename;
            
            $this->saveImage($resizedImage, $outputPath, $mimeType);
            $processedImages[$sizeName] = $outputPath;
            
            // Clean up memory
            imagedestroy($resizedImage);
        }
        
        // Save optimized original (large size)
        $largeFilename = $nameWithoutExt . '_large.' . $extension;
        $largePath = $outputDir . $largeFilename;
        
        // If original is larger than large size, resize it
        if ($originalWidth > $this->thumbnailSizes['large']['width'] || 
            $originalHeight > $this->thumbnailSizes['large']['height']) {
            
            $largeDimensions = $this->calculateResizeDimensions(
                $originalWidth, 
                $originalHeight, 
                $this->thumbnailSizes['large']['width'], 
                $this->thumbnailSizes['large']['height']
            );
            
            $largeImage = imagecreatetruecolor($largeDimensions['width'], $largeDimensions['height']);
            
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($largeImage, false);
                imagesavealpha($largeImage, true);
                $transparent = imagecolorallocatealpha($largeImage, 255, 255, 255, 127);
                imagefill($largeImage, 0, 0, $transparent);
            }
            
            imagecopyresampled(
                $largeImage, $originalImage,
                0, 0, 0, 0,
                $largeDimensions['width'], $largeDimensions['height'],
                $originalWidth, $originalHeight
            );
            
            $this->saveImage($largeImage, $largePath, $mimeType);
            imagedestroy($largeImage);
        } else {
            // Just optimize the original
            $this->saveImage($originalImage, $largePath, $mimeType);
        }
        
        $processedImages['large'] = $largePath;
        
        // Clean up original image resource
        imagedestroy($originalImage);
        
        // Remove original file if it's different from large
        if ($originalPath !== $largePath) {
            unlink($originalPath);
        }
        
        return $processedImages;
    }
    
    /**
     * Create image resource from file
     * 
     * @param string $filePath File path
     * @param string $mimeType MIME type
     * @return resource|false Image resource or false on failure
     */
    private function createImageFromFile($filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            case 'image/webp':
                return imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }
    
    /**
     * Save image to file
     * 
     * @param resource $image Image resource
     * @param string $filePath Output file path
     * @param string $mimeType MIME type
     * @return bool Success status
     */
    private function saveImage($image, $filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($image, $filePath, $this->imageQuality);
            case 'image/png':
                // PNG quality is 0-9, convert from JPEG quality (0-100)
                $pngQuality = 9 - round(($this->imageQuality / 100) * 9);
                return imagepng($image, $filePath, $pngQuality);
            case 'image/gif':
                return imagegif($image, $filePath);
            case 'image/webp':
                return imagewebp($image, $filePath, $this->imageQuality);
            default:
                return false;
        }
    }
    
    /**
     * Calculate resize dimensions maintaining aspect ratio
     * 
     * @param int $originalWidth Original width
     * @param int $originalHeight Original height
     * @param int $targetWidth Target width
     * @param int $targetHeight Target height
     * @return array New dimensions
     */
    private function calculateResizeDimensions($originalWidth, $originalHeight, $targetWidth, $targetHeight) {
        $aspectRatio = $originalWidth / $originalHeight;
        $targetAspectRatio = $targetWidth / $targetHeight;
        
        if ($aspectRatio > $targetAspectRatio) {
            // Original is wider, fit to width
            $newWidth = $targetWidth;
            $newHeight = round($targetWidth / $aspectRatio);
        } else {
            // Original is taller, fit to height
            $newHeight = $targetHeight;
            $newWidth = round($targetHeight * $aspectRatio);
        }
        
        return [
            'width' => $newWidth,
            'height' => $newHeight
        ];
    }
    
    /**
     * Generate unique filename
     * 
     * @param int $productId Product ID
     * @param string $extension File extension
     * @return string Generated filename
     */
    private function generateFilename($productId, $extension) {
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        return "product_{$productId}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Get base URL for image URLs
     * 
     * @return string Base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $scriptName;
    }
    
    /**
     * Delete product images
     * 
     * @param int $productId Product ID
     * @param array $imageFilenames Specific filenames to delete (optional)
     * @return bool Success status
     */
    public function deleteProductImages($productId, $imageFilenames = null) {
        try {
            $productDir = $this->uploadPath . $productId . '/';
            
            if (!is_dir($productDir)) {
                return true; // No images to delete
            }
            
            if ($imageFilenames === null) {
                // Delete all images for the product
                $files = glob($productDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                
                // Remove directory if empty
                if (count(glob($productDir . '*')) === 0) {
                    rmdir($productDir);
                }
            } else {
                // Delete specific images
                foreach ($imageFilenames as $filename) {
                    $filePath = $productDir . $filename;
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    // Also delete thumbnails
                    $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    
                    foreach (array_keys($this->thumbnailSizes) as $size) {
                        $thumbnailPath = $productDir . $nameWithoutExt . '_' . $size . '.' . $extension;
                        if (file_exists($thumbnailPath)) {
                            unlink($thumbnailPath);
                        }
                    }
                }
            }
            
            Logger::info('Product images deleted', [
                'product_id' => $productId,
                'specific_files' => $imageFilenames
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to delete product images', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get image info
     * 
     * @param string $imagePath Image path
     * @return array|null Image information
     */
    public function getImageInfo($imagePath) {
        if (!file_exists($imagePath)) {
            return null;
        }
        
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            return null;
        }
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime'],
            'file_size' => filesize($imagePath),
            'file_name' => basename($imagePath)
        ];
    }
    
    /**
     * Optimize existing image
     * 
     * @param string $imagePath Path to image
     * @return bool Success status
     */
    public function optimizeImage($imagePath) {
        try {
            if (!file_exists($imagePath)) {
                return false;
            }
            
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                return false;
            }
            
            $mimeType = $imageInfo['mime'];
            $image = $this->createImageFromFile($imagePath, $mimeType);
            
            if (!$image) {
                return false;
            }
            
            // Save optimized version
            $result = $this->saveImage($image, $imagePath, $mimeType);
            imagedestroy($image);
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error('Image optimization failed', [
                'image_path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}