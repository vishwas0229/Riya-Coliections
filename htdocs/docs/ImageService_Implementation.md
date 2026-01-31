# ImageService Implementation Documentation

## Overview

The ImageService class provides comprehensive image upload, processing, validation, and management functionality for the Riya Collections PHP backend. It handles secure file uploads, image resizing, optimization, and maintains compatibility with the existing Node.js backend.

## Features

### Core Functionality
- **Secure File Upload**: Validates file types, sizes, and content
- **Image Processing**: Automatic resizing and optimization
- **Multiple Formats**: Supports JPEG, PNG, WebP, and GIF
- **Security Validation**: Prevents malicious file uploads
- **Directory Management**: Automatic directory creation and cleanup

### Image Processing
- **Thumbnail Generation**: Creates 150x150px thumbnails
- **Medium Size**: Creates 400x400px medium images
- **Large Size**: Creates 800x800px large images
- **Aspect Ratio Preservation**: Maintains original proportions
- **Quality Optimization**: Configurable compression settings

## Requirements Fulfilled

### Requirement 8.1: File Upload and Image Processing
- ✅ Handles file uploads for product images with size and format restrictions
- ✅ Implements secure file upload validation
- ✅ Organizes uploaded files in structured directory layout
- ✅ Implements image validation and security checks

### Requirement 8.2: Image Resizing and Optimization
- ✅ Resizes and optimizes images for web display
- ✅ Supports multiple image formats (JPEG, PNG, WebP, GIF)
- ✅ Creates multiple size variants (thumbnail, medium, large)
- ✅ Maintains image quality while reducing file size

## API Reference

### Constructor
```php
public function __construct()
```
Initializes the ImageService with default configuration:
- Upload path: `uploads/products/`
- Max file size: 5MB
- Supported formats: JPEG, PNG, WebP, GIF
- Image quality: 85%

### Main Methods

#### uploadProductImage()
```php
public function uploadProductImage(array $fileInfo, int $productId): array
```
Uploads and processes a product image.

**Parameters:**
- `$fileInfo`: File information from `$_FILES`
- `$productId`: Product ID for organization

**Returns:**
```php
[
    'url' => 'https://example.com/uploads/products/123/product_123_1234567890_1234_large.jpg',
    'thumbnail_url' => 'https://example.com/uploads/products/123/product_123_1234567890_1234_thumbnail.jpg',
    'medium_url' => 'https://example.com/uploads/products/123/product_123_1234567890_1234_medium.jpg',
    'large_url' => 'https://example.com/uploads/products/123/product_123_1234567890_1234_large.jpg',
    'original_name' => 'product_image.jpg',
    'filename' => 'product_123_1234567890_1234.jpg',
    'alt_text' => null,
    'is_primary' => false,
    'sort_order' => 0
]
```

**Throws:**
- `Exception`: On validation failure or processing error

#### deleteProductImages()
```php
public function deleteProductImages(int $productId, array $imageFilenames = null): bool
```
Deletes product images.

**Parameters:**
- `$productId`: Product ID
- `$imageFilenames`: Specific filenames to delete (optional)

**Returns:** `bool` - Success status

#### getImageInfo()
```php
public function getImageInfo(string $imagePath): array|null
```
Retrieves image information.

**Returns:**
```php
[
    'width' => 800,
    'height' => 600,
    'mime_type' => 'image/jpeg',
    'file_size' => 102400,
    'file_name' => 'image.jpg'
]
```

#### optimizeImage()
```php
public function optimizeImage(string $imagePath): bool
```
Optimizes an existing image file.

## Validation Rules

### File Size
- **Maximum**: 5MB (5,242,880 bytes)
- **Minimum**: No minimum (but must be valid image)

### Image Dimensions
- **Minimum**: 100x100 pixels
- **Maximum**: 5000x5000 pixels

### Supported MIME Types
- `image/jpeg` - JPEG images
- `image/png` - PNG images with transparency support
- `image/webp` - WebP images (if supported by server)
- `image/gif` - GIF images with transparency support

### Security Checks
- File content validation using `finfo_file()`
- Image header validation using `getimagesize()`
- Path traversal prevention in filenames
- Upload error handling for all PHP upload error codes

## Directory Structure

```
uploads/products/
├── {product_id}/
│   ├── product_{id}_{timestamp}_{random}_thumbnail.{ext}
│   ├── product_{id}_{timestamp}_{random}_medium.{ext}
│   ├── product_{id}_{timestamp}_{random}_large.{ext}
│   └── ...
└── ...
```

## Error Handling

### Upload Errors
- `UPLOAD_ERR_INI_SIZE`: File size exceeds server limit
- `UPLOAD_ERR_FORM_SIZE`: File size exceeds form limit
- `UPLOAD_ERR_PARTIAL`: File was only partially uploaded
- `UPLOAD_ERR_NO_FILE`: No file was uploaded
- `UPLOAD_ERR_NO_TMP_DIR`: Missing temporary folder
- `UPLOAD_ERR_CANT_WRITE`: Failed to write file to disk
- `UPLOAD_ERR_EXTENSION`: File upload stopped by extension

### Validation Errors
- File size exceeds maximum allowed size
- Invalid file type (unsupported MIME type)
- File is not a valid image
- Image dimensions too small/large
- Failed to move uploaded file

### Processing Errors
- Failed to create image resource
- GD extension not available (fallback mode)
- Failed to create directory
- Insufficient disk space

## Configuration

### Default Settings
```php
private $uploadPath = 'uploads/products/';
private $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
private $maxFileSize = 5 * 1024 * 1024; // 5MB
private $imageQuality = 85;
private $thumbnailSizes = [
    'thumbnail' => ['width' => 150, 'height' => 150],
    'medium' => ['width' => 400, 'height' => 400],
    'large' => ['width' => 800, 'height' => 800]
];
```

### Environment Considerations
- **GD Extension**: Required for image processing (fallback available)
- **File Permissions**: Upload directory must be writable (755)
- **Memory Limit**: Large images may require increased PHP memory limit
- **Execution Time**: Processing multiple images may require increased max_execution_time

## Usage Examples

### Basic Image Upload
```php
$imageService = new ImageService();

// Handle file upload from form
if (isset($_FILES['product_image'])) {
    try {
        $result = $imageService->uploadProductImage($_FILES['product_image'], $productId);
        
        // Save image URLs to database
        $imageUrl = $result['url'];
        $thumbnailUrl = $result['thumbnail_url'];
        
        echo "Image uploaded successfully: " . $imageUrl;
    } catch (Exception $e) {
        echo "Upload failed: " . $e->getMessage();
    }
}
```

### Image Management
```php
// Get image information
$info = $imageService->getImageInfo('/path/to/image.jpg');
if ($info) {
    echo "Image size: {$info['width']}x{$info['height']}";
}

// Optimize existing image
$success = $imageService->optimizeImage('/path/to/image.jpg');

// Delete product images
$success = $imageService->deleteProductImages($productId);

// Delete specific images
$success = $imageService->deleteProductImages($productId, ['image1.jpg', 'image2.jpg']);
```

## Testing

### Unit Tests
- File validation logic
- Error handling scenarios
- Security validation
- Directory management
- Image information retrieval

### Property-Based Tests
- File upload validation consistency
- Image processing consistency
- Filename generation uniqueness
- Boundary condition testing
- Error handling consistency

### Test Coverage
- ✅ File size validation
- ✅ MIME type validation
- ✅ Image dimension validation
- ✅ Upload error handling
- ✅ Security validation
- ✅ Filename sanitization
- ✅ Directory creation
- ✅ Image deletion
- ✅ Boundary conditions

## Security Considerations

### Input Validation
- All file uploads are validated for type, size, and content
- MIME type validation uses actual file content, not just headers
- Image validation ensures files are actually images
- Path traversal prevention in filenames

### File Storage
- Images stored outside web root when possible
- Unique filename generation prevents conflicts
- Directory structure prevents direct access
- File permissions set appropriately

### Error Handling
- Detailed errors logged but not exposed to users
- Graceful handling of all error conditions
- Cleanup of temporary files on failure
- Resource cleanup to prevent memory leaks

## Performance Considerations

### Memory Usage
- Image processing can be memory-intensive
- Large images may require increased PHP memory limit
- Resources are properly cleaned up after processing

### File I/O
- Efficient file operations with proper error handling
- Batch operations for multiple images
- Directory structure optimized for file system performance

### Caching
- Generated thumbnails are cached on disk
- No regeneration unless source image changes
- URL generation includes cache-busting when needed

## Compatibility

### PHP Version
- Requires PHP 7.4 or higher
- Compatible with PHP 8.x

### Extensions
- **Required**: `fileinfo` for MIME type detection
- **Required**: `gd` for image processing (fallback available)
- **Optional**: `imagick` for advanced image processing

### Node.js Backend Compatibility
- API response format matches Node.js backend
- File organization structure identical
- Image processing results equivalent
- Error handling consistent

## Deployment Notes

### Server Requirements
- PHP with GD extension enabled
- Writable upload directory
- Sufficient disk space for image storage
- Appropriate file permissions

### Configuration
- Set appropriate upload limits in php.ini
- Configure memory limits for image processing
- Set execution time limits for batch operations
- Configure error logging for debugging

### Monitoring
- Monitor disk space usage
- Track upload success/failure rates
- Log security violations
- Monitor image processing performance