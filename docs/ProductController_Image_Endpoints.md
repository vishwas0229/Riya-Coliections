# ProductController Image Upload Endpoints Implementation

## Overview

This document describes the implementation of image upload endpoints for the ProductController in the Riya Collections PHP backend. These endpoints provide comprehensive image management functionality including upload, deletion, and primary image designation.

## Requirements Fulfilled

### Requirement 8.1: File Upload and Image Processing
- ✅ Implements POST /api/products/:id/images endpoint
- ✅ Handles secure file upload validation through ImageService
- ✅ Supports multiple image formats (JPEG, PNG, WebP, GIF)
- ✅ Implements image validation and security checks

### Requirement 8.2: Image Management and Primary Image Designation
- ✅ Implements image deletion endpoints
- ✅ Handles primary image designation
- ✅ Provides image metadata management
- ✅ Maintains database consistency for image operations

## API Endpoints

### 1. Upload Product Images
**POST /api/admin/products/{id}/images**

Uploads one or more images for a product. Admin authentication required.

**Request:**
- Method: POST
- Content-Type: multipart/form-data
- Authentication: Admin JWT token required
- Body: Form data with 'images' field containing file(s)

**Response:**
```json
{
    "success": true,
    "message": "Images uploaded successfully",
    "data": {
        "product_id": 123,
        "uploaded_images": [
            {
                "url": "/uploads/products/123/product_123_1640995200_1234.jpg",
                "thumbnail_url": "/uploads/products/123/product_123_1640995200_1234_thumbnail.jpg",
                "medium_url": "/uploads/products/123/product_123_1640995200_1234_medium.jpg",
                "large_url": "/uploads/products/123/product_123_1640995200_1234_large.jpg",
                "original_name": "product-image.jpg",
                "filename": "product_123_1640995200_1234.jpg",
                "alt_text": null,
                "is_primary": false,
                "sort_order": 0
            }
        ],
        "images_count": 1
    }
}
```

### 2. Get Product Images
**GET /api/products/{id}/images**

Retrieves all images for a product. Public endpoint.

**Request:**
- Method: GET
- Authentication: None required

**Response:**
```json
{
    "success": true,
    "message": "Product images retrieved successfully",
    "data": {
        "product_id": 123,
        "images": [
            {
                "id": 1,
                "product_id": 123,
                "image_url": "/uploads/products/123/image.jpg",
                "alt_text": "Product image",
                "is_primary": true,
                "sort_order": 0,
                "created_at": "2024-01-01 12:00:00",
                "updated_at": "2024-01-01 12:00:00"
            }
        ],
        "images_count": 1
    }
}
```

### 3. Delete All Product Images
**DELETE /api/admin/products/{id}/images**

Deletes all images for a product. Admin authentication required.

**Request:**
- Method: DELETE
- Authentication: Admin JWT token required

**Response:**
```json
{
    "success": true,
    "message": "All product images deleted successfully",
    "data": {
        "product_id": 123,
        "deleted_images_count": 3
    }
}
```

### 4. Delete Specific Product Image
**DELETE /api/admin/products/{id}/images/{imageId}**

Deletes a specific image for a product. Admin authentication required.

**Request:**
- Method: DELETE
- Authentication: Admin JWT token required

**Response:**
```json
{
    "success": true,
    "message": "Product image deleted successfully",
    "data": {
        "product_id": 123,
        "image_id": 456,
        "was_primary": false
    }
}
```

### 5. Set Primary Image
**PUT /api/admin/products/{id}/images/{imageId}/primary**

Sets a specific image as the primary product image. Admin authentication required.

**Request:**
- Method: PUT
- Authentication: Admin JWT token required

**Response:**
```json
{
    "success": true,
    "message": "Primary image set successfully",
    "data": {
        "product_id": 123,
        "image_id": 456,
        "image": {
            "id": 456,
            "product_id": 123,
            "image_url": "/uploads/products/123/image.jpg",
            "alt_text": "Product image",
            "is_primary": true,
            "sort_order": 0,
            "created_at": "2024-01-01 12:00:00",
            "updated_at": "2024-01-01 12:00:00"
        }
    }
}
```

### 6. Update Image Metadata
**PUT /api/admin/products/{id}/images/{imageId}**

Updates image metadata (alt text, sort order). Admin authentication required.

**Request:**
- Method: PUT
- Content-Type: application/json
- Authentication: Admin JWT token required
- Body:
```json
{
    "alt_text": "Updated alt text",
    "sort_order": 5
}
```

**Response:**
```json
{
    "success": true,
    "message": "Image updated successfully",
    "data": {
        "product_id": 123,
        "image_id": 456,
        "image": {
            "id": 456,
            "product_id": 123,
            "image_url": "/uploads/products/123/image.jpg",
            "alt_text": "Updated alt text",
            "is_primary": false,
            "sort_order": 5,
            "created_at": "2024-01-01 12:00:00",
            "updated_at": "2024-01-01 12:05:00"
        }
    }
}
```

## Implementation Details

### Controller Methods

#### ProductController::getImages($id)
- Validates product ID
- Checks product existence
- Retrieves all images for the product
- Returns images ordered by primary status and sort order

#### ProductController::deleteAllImages($id)
- Requires admin authentication
- Validates product ID and existence
- Deletes images from filesystem via ImageService
- Removes all image records from database
- Maintains transaction consistency

#### ProductController::deleteImage($id, $imageId)
- Requires admin authentication
- Validates both product and image IDs
- Ensures image belongs to the specified product
- Deletes specific image from filesystem and database
- Logs deletion details for audit trail

#### ProductController::setPrimaryImage($id, $imageId)
- Requires admin authentication
- Validates product and image existence
- Uses database transaction to ensure atomicity
- Unsets all existing primary images for the product
- Sets the specified image as primary
- Maintains data consistency

#### ProductController::updateImage($id, $imageId)
- Requires admin authentication
- Validates input data (alt_text, sort_order)
- Updates only allowed fields
- Preserves other image properties
- Returns updated image data

### Database Methods

#### Product::getProductImageById($imageId, $productId)
- Retrieves specific image with security check
- Ensures image belongs to the specified product
- Returns null if not found

#### Product::deleteAllProductImages($productId)
- Removes all image records for a product
- Returns success status
- Logs deletion count

#### Product::deleteProductImage($imageId, $productId)
- Removes specific image record
- Includes security check for product ownership
- Returns false if image not found

#### Product::setPrimaryProductImage($imageId, $productId)
- Uses database transaction for atomicity
- Unsets all existing primary images
- Sets specified image as primary
- Ensures only one primary image exists

#### Product::updateProductImage($imageId, $productId, $updateData)
- Updates allowed fields dynamically
- Validates field names for security
- Updates timestamp automatically
- Returns success status

## Security Features

### Authentication & Authorization
- All admin endpoints require JWT authentication
- Role-based access control via AuthMiddleware
- Public endpoints (getImages) accessible without authentication

### Input Validation
- Product ID validation (numeric, positive)
- Image ID validation (numeric, positive)
- Ownership verification (image belongs to product)
- Field validation for updates (whitelist approach)

### Error Handling
- Comprehensive input validation
- Graceful handling of non-existent resources
- Detailed error messages for debugging
- Consistent error response format

### Data Integrity
- Database transactions for multi-step operations
- Foreign key constraints enforcement
- Cascade deletion handling
- Primary image uniqueness enforcement

## File System Integration

### ImageService Integration
- Seamless integration with existing ImageService
- Automatic file cleanup on deletion
- Support for multiple image sizes (thumbnail, medium, large)
- Proper error handling for file operations

### Directory Structure
```
uploads/
└── products/
    └── {product_id}/
        ├── product_{id}_{timestamp}_{random}.jpg
        ├── product_{id}_{timestamp}_{random}_thumbnail.jpg
        ├── product_{id}_{timestamp}_{random}_medium.jpg
        └── product_{id}_{timestamp}_{random}_large.jpg
```

## Testing

### Unit Tests (ProductControllerImageTest.php)
- Tests all endpoint functionality
- Validates input/output formats
- Checks authentication requirements
- Tests error handling scenarios
- Verifies database operations

### Property-Based Tests (ProductControllerImagePropertyTest.php)
- Tests universal properties across random inputs
- Validates consistency across multiple operations
- Tests atomicity of transactions
- Verifies primary image uniqueness
- Tests error handling robustness

### Test Coverage
- **Image retrieval consistency**: Ensures consistent results across multiple calls
- **Image deletion atomicity**: Verifies complete deletion of all images
- **Primary image uniqueness**: Ensures only one primary image exists
- **Metadata update consistency**: Validates field updates preserve other data
- **Individual deletion precision**: Tests selective image deletion
- **Error handling robustness**: Validates graceful error handling

## Usage Examples

### Upload Multiple Images
```bash
curl -X POST \
  -H "Authorization: Bearer {admin_jwt_token}" \
  -F "images[]=@image1.jpg" \
  -F "images[]=@image2.jpg" \
  http://localhost/api/admin/products/123/images
```

### Get Product Images
```bash
curl -X GET \
  http://localhost/api/products/123/images
```

### Set Primary Image
```bash
curl -X PUT \
  -H "Authorization: Bearer {admin_jwt_token}" \
  http://localhost/api/admin/products/123/images/456/primary
```

### Update Image Alt Text
```bash
curl -X PUT \
  -H "Authorization: Bearer {admin_jwt_token}" \
  -H "Content-Type: application/json" \
  -d '{"alt_text": "New alt text", "sort_order": 1}' \
  http://localhost/api/admin/products/123/images/456
```

### Delete Specific Image
```bash
curl -X DELETE \
  -H "Authorization: Bearer {admin_jwt_token}" \
  http://localhost/api/admin/products/123/images/456
```

## Performance Considerations

### Database Optimization
- Indexed queries on product_id and is_primary fields
- Efficient ordering by primary status and sort order
- Minimal database queries per operation

### File System Optimization
- Organized directory structure by product ID
- Efficient file deletion with glob patterns
- Proper cleanup of all image sizes

### Memory Management
- Streaming file operations where possible
- Proper resource cleanup in ImageService
- Transaction rollback on errors

## Maintenance

### Logging
- Comprehensive audit logging for all operations
- Admin action tracking with user identification
- Error logging with context information
- Performance metrics logging

### Monitoring
- File system usage monitoring
- Database operation performance
- Error rate tracking
- Admin activity monitoring

This implementation provides a complete, secure, and robust image management system for the Riya Collections e-commerce platform, maintaining full compatibility with the existing Node.js backend while leveraging PHP's strengths for file handling and database operations.