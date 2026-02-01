<?php
/**
 * File Controller
 * 
 * This controller handles file serving, uploads, and management
 * for the Riya Collections PHP backend.
 * 
 * Requirements: 8.1, 8.4
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../config/security.php';

/**
 * File Controller Class
 */
class FileController {
    private $uploadPath;
    private $allowedTypes;
    private $maxFileSize;
    
    public function __construct() {
        $config = getUploadConfig();
        $this->uploadPath = __DIR__ . '/../' . $config['path'];
        $this->allowedTypes = $config['allowed_types'];
        $this->maxFileSize = $config['max_file_size'];
    }
    
    /**
     * Serve uploaded files with security checks
     */
    public function serve($path = null) {
        try {
            if (!$path) {
                Response::notFound('File path required');
                return;
            }
            
            // Decode and sanitize path
            $path = urldecode($path);
            $path = $this->sanitizePath($path);
            
            // Construct full file path
            $filePath = $this->uploadPath . '/' . $path;
            
            // Security checks
            if (!$this->isPathSafe($filePath)) {
                Logger::security('Unsafe file path access attempt', [
                    'requested_path' => $path,
                    'resolved_path' => $filePath,
                    'ip' => $this->getClientIP()
                ]);
                
                Response::forbidden('Access denied');
                return;
            }
            
            // Check if file exists
            if (!file_exists($filePath) || !is_file($filePath)) {
                Response::notFound('File not found');
                return;
            }
            
            // Check file type
            $mimeType = mime_content_type($filePath);
            if (!$this->isAllowedType($mimeType)) {
                Logger::security('Attempt to access disallowed file type', [
                    'file_path' => $path,
                    'mime_type' => $mimeType,
                    'ip' => $this->getClientIP()
                ]);
                
                Response::forbidden('File type not allowed');
                return;
            }
            
            // Serve file with appropriate headers
            $this->serveFile($filePath, $mimeType);
            
        } catch (Exception $e) {
            Logger::error('File serving error', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('File serving failed');
        }
    }
    
    /**
     * Upload file with validation and processing
     */
    public function upload() {
        try {
            // Check if file was uploaded
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Response::validationError(['file' => 'No file uploaded or upload error']);
                return;
            }
            
            $file = $_FILES['file'];
            
            // Validate file
            $this->validateUploadedFile($file);
            
            // Generate safe filename
            $filename = $this->generateSafeFilename($file['name']);
            
            // Determine upload directory
            $uploadDir = $this->getUploadDirectory();
            $uploadPath = $uploadDir . '/' . $filename;
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Process image if it's an image file
            if (strpos($file['type'], 'image/') === 0) {
                $this->processImage($uploadPath);
            }
            
            // Generate file URL
            $fileUrl = $this->generateFileUrl($filename);
            
            Logger::info('File uploaded successfully', [
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type']
            ]);
            
            Response::created([
                'filename' => $filename,
                'url' => $fileUrl,
                'size' => $file['size'],
                'type' => $file['type']
            ], 'File uploaded successfully');
            
        } catch (Exception $e) {
            Logger::error('File upload error', [
                'error' => $e->getMessage(),
                'file_info' => $_FILES['file'] ?? null
            ]);
            
            Response::serverError('File upload failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete uploaded file
     */
    public function delete($filename = null) {
        try {
            if (!$filename) {
                Response::validationError(['filename' => 'Filename required']);
                return;
            }
            
            // Sanitize filename
            $filename = $this->sanitizePath($filename);
            $filePath = $this->uploadPath . '/' . $filename;
            
            // Security checks
            if (!$this->isPathSafe($filePath)) {
                Response::forbidden('Access denied');
                return;
            }
            
            // Check if file exists
            if (!file_exists($filePath)) {
                Response::notFound('File not found');
                return;
            }
            
            // Delete file
            if (!unlink($filePath)) {
                throw new Exception('Failed to delete file');
            }
            
            Logger::info('File deleted successfully', [
                'filename' => $filename
            ]);
            
            Response::success('File deleted successfully');
            
        } catch (Exception $e) {
            Logger::error('File deletion error', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('File deletion failed');
        }
    }
    
    /**
     * Sanitize file path to prevent directory traversal
     */
    private function sanitizePath($path) {
        // Remove any directory traversal attempts
        $path = str_replace(['../', '..\\', '../', '..\\'], '', $path);
        
        // Remove leading slashes
        $path = ltrim($path, '/\\');
        
        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);
        
        return $path;
    }
    
    /**
     * Check if path is safe (within upload directory)
     */
    private function isPathSafe($filePath) {
        $realUploadPath = realpath($this->uploadPath);
        $realFilePath = realpath(dirname($filePath)) . '/' . basename($filePath);
        
        // Check if file is within upload directory
        return strpos($realFilePath, $realUploadPath) === 0;
    }
    
    /**
     * Check if file type is allowed
     */
    private function isAllowedType($mimeType) {
        return in_array($mimeType, $this->allowedTypes);
    }
    
    /**
     * Validate uploaded file
     */
    private function validateUploadedFile($file) {
        $security = getFileUploadSecurity();
        
        // Use security class validation
        $security->validateFile($file);
        
        // Additional custom validations can be added here
    }
    
    /**
     * Generate safe filename
     */
    private function generateSafeFilename($originalName) {
        $security = getFileUploadSecurity();
        return $security->generateSafeFilename($originalName);
    }
    
    /**
     * Get upload directory based on file type
     */
    private function getUploadDirectory() {
        $baseDir = $this->uploadPath;
        
        // Organize by date
        $dateDir = date('Y/m');
        
        return $baseDir . '/' . $dateDir;
    }
    
    /**
     * Process uploaded image (resize, optimize)
     */
    private function processImage($imagePath) {
        $config = getUploadConfig();
        
        // Get image info
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return; // Not a valid image
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Check if resizing is needed
        $maxWidth = $config['max_width'];
        $maxHeight = $config['max_height'];
        
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return; // No resizing needed
        }
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Create image resource
        $sourceImage = $this->createImageResource($imagePath, $type);
        if (!$sourceImage) {
            return;
        }
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save resized image
        $this->saveImageResource($newImage, $imagePath, $type, $config['image_quality']);
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($newImage);
    }
    
    /**
     * Create image resource from file
     */
    private function createImageResource($imagePath, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($imagePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($imagePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($imagePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($imagePath);
            default:
                return false;
        }
    }
    
    /**
     * Save image resource to file
     */
    private function saveImageResource($imageResource, $imagePath, $type, $quality) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($imageResource, $imagePath, $quality);
            case IMAGETYPE_PNG:
                return imagepng($imageResource, $imagePath, (int)(9 - ($quality / 10)));
            case IMAGETYPE_GIF:
                return imagegif($imageResource, $imagePath);
            case IMAGETYPE_WEBP:
                return imagewebp($imageResource, $imagePath, $quality);
            default:
                return false;
        }
    }
    
    /**
     * Generate file URL
     */
    private function generateFileUrl($filename) {
        $baseUrl = $this->getBaseUrl();
        $dateDir = date('Y/m');
        
        return $baseUrl . '/uploads/' . $dateDir . '/' . $filename;
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . ($path !== '/' ? $path : '');
    }
    
    /**
     * Serve file with appropriate headers
     */
    private function serveFile($filePath, $mimeType) {
        $fileSize = filesize($filePath);
        $lastModified = filemtime($filePath);
        $etag = md5_file($filePath);
        
        // Check if client has cached version
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        
        if (($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified) ||
            ($ifNoneMatch && $ifNoneMatch === $etag)) {
            http_response_code(304);
            exit;
        }
        
        // Set headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=86400'); // 24 hours
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
        
        // Output file
        readfile($filePath);
        exit;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}