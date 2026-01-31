# Product Model Implementation

## Overview

The Product model provides comprehensive CRUD operations for product management in the Riya Collections PHP backend. It maintains API compatibility with the existing Node.js backend while providing enhanced functionality for search, filtering, pagination, and stock management.

## Features Implemented

### Core CRUD Operations
- ✅ **Create Product**: Full product creation with validation and image support
- ✅ **Read Product**: Retrieve products by ID with category and image information
- ✅ **Update Product**: Partial updates with validation and conflict checking
- ✅ **Delete Product**: Soft delete to maintain data integrity

### Advanced Features
- ✅ **Search & Filtering**: Multi-field search with category, brand, price range filters
- ✅ **Pagination**: Efficient pagination with metadata
- ✅ **Stock Management**: Set, add, subtract operations with negative prevention
- ✅ **SKU Management**: Auto-generation and uniqueness validation
- ✅ **Image Management**: Multiple images per product with primary image support
- ✅ **Data Validation**: Comprehensive validation for all fields
- ✅ **Data Sanitization**: Type conversion and computed fields

## API Compatibility

The Product model maintains full compatibility with the existing Node.js backend:

### Response Format
```json
{
  "id": 123,
  "name": "Product Name",
  "description": "Product description",
  "price": 29.99,
  "stock_quantity": 100,
  "category_id": 5,
  "category_name": "Category Name",
  "brand": "Brand Name",
  "sku": "PROD123",
  "is_active": true,
  "in_stock": true,
  "formatted_price": "29.99",
  "primary_image": {
    "url": "/uploads/products/image.jpg",
    "alt_text": "Product image"
  },
  "images": [...],
  "created_at": "2023-01-01 12:00:00",
  "updated_at": "2023-01-01 12:00:00"
}
```

### Filtering Options
- `search`: Search in name, description, brand, SKU
- `category_id`: Filter by category
- `brand`: Filter by brand
- `min_price`, `max_price`: Price range filtering
- `in_stock`: Filter products with stock > 0
- `sort`: Various sorting options (name, price, created date, stock, brand)

## Database Schema

### Products Table
```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    category_id INT,
    brand VARCHAR(100),
    sku VARCHAR(50) UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_products_name (name),
    INDEX idx_products_price (price),
    INDEX idx_products_category_id (category_id),
    INDEX idx_products_brand (brand),
    INDEX idx_products_sku (sku),
    INDEX idx_products_is_active (is_active)
);
```

### Product Images Table
```sql
CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_images_product_id (product_id),
    INDEX idx_product_images_is_primary (is_primary)
);
```

## Key Methods

### Product Creation
```php
public function createProduct($productData)
```
- Validates all input data
- Checks SKU uniqueness
- Auto-generates SKU if not provided
- Handles product images
- Returns sanitized product data

### Product Retrieval
```php
public function getProductById($productId)
public function getProducts($filters = [], $page = 1, $perPage = 20)
public function searchProducts($searchTerm, $filters = [], $page = 1, $perPage = 20)
```
- Efficient queries with JOINs
- Includes category and image information
- Supports complex filtering and sorting
- Paginated results with metadata

### Stock Management
```php
public function updateStock($productId, $quantity, $operation = 'set')
```
- Operations: 'set', 'add', 'subtract'
- Prevents negative stock
- Transaction-safe updates
- Detailed logging

### Specialized Queries
```php
public function getFeaturedProducts($limit = 10)
public function getLowStockProducts($threshold = 10, $limit = 50)
public function getProductStats()
```

## Validation Rules

### Required Fields (Creation)
- `name`: 2-255 characters
- `price`: Non-negative, max 999999.99

### Optional Fields
- `description`: Max 65535 characters
- `stock_quantity`: Non-negative integer, max 999999
- `category_id`: Must exist in categories table
- `brand`: Max 100 characters
- `sku`: Max 50 characters, alphanumeric + hyphens/underscores

### Auto-Generated Fields
- `sku`: Generated from product name + timestamp if not provided
- `created_at`, `updated_at`: Automatic timestamps

## Error Handling

### Validation Errors (400)
- Missing required fields
- Invalid data types or formats
- Field length violations
- Invalid characters in SKU

### Conflict Errors (409)
- Duplicate SKU
- Category doesn't exist

### Not Found Errors (404)
- Product doesn't exist
- Attempting to update/delete non-existent product

### Business Logic Errors (400)
- Insufficient stock for subtraction
- Invalid stock operation type

## Security Features

### SQL Injection Prevention
- All queries use prepared statements
- Parameter binding with type detection
- Input sanitization and validation

### Data Sanitization
- Type conversion for all numeric fields
- Boolean conversion for flags
- Computed fields for frontend convenience

### Access Control
- Soft delete preserves data integrity
- Active/inactive status filtering
- Transaction-based operations

## Performance Optimizations

### Database Indexes
- Optimized indexes for common queries
- Composite indexes for filtered searches
- Foreign key indexes for JOINs

### Query Efficiency
- Single query for product with category/images
- Efficient pagination with LIMIT/OFFSET
- Optimized COUNT queries for totals

### Caching Ready
- Structured for easy caching integration
- Consistent data format
- Minimal database calls

## Testing

### Unit Tests
- ✅ Product validation logic
- ✅ SKU generation and uniqueness
- ✅ Data sanitization
- ✅ Order by clause building
- ✅ Edge cases and boundary values

### Property-Based Tests
- Product CRUD operations consistency
- Query result consistency
- Stock management mathematical correctness
- Price validation consistency

### Test Coverage
- All validation rules tested
- All CRUD operations verified
- Error conditions handled
- Edge cases covered

## Usage Examples

### Create Product
```php
$product = new Product();
$productData = [
    'name' => 'Red Lipstick',
    'description' => 'Long-lasting red lipstick',
    'price' => 25.99,
    'stock_quantity' => 50,
    'category_id' => 1,
    'brand' => 'Beauty Brand',
    'images' => [
        ['url' => '/uploads/lipstick1.jpg', 'is_primary' => true],
        ['url' => '/uploads/lipstick2.jpg', 'is_primary' => false]
    ]
];

$result = $product->createProduct($productData);
```

### Search Products
```php
$filters = [
    'search' => 'lipstick',
    'category_id' => 1,
    'min_price' => 20,
    'max_price' => 50,
    'in_stock' => true,
    'sort' => 'price_asc'
];

$results = $product->getProducts($filters, 1, 20);
```

### Update Stock
```php
// Set stock to specific amount
$product->updateStock(123, 100, 'set');

// Add to existing stock
$product->updateStock(123, 25, 'add');

// Subtract from stock (prevents negative)
$product->updateStock(123, 10, 'subtract');
```

## Integration Points

### Category Model
- Foreign key relationship
- Category name included in product data
- Category validation during creation/update

### Image Management
- Multiple images per product
- Primary image designation
- Sort order support
- Cascade delete on product removal

### Order System
- Stock quantity management
- Product availability checking
- Price consistency

## Future Enhancements

### Planned Features
- Product variants (size, color)
- Bulk operations
- Product reviews integration
- Inventory tracking history
- Price history tracking

### Performance Improvements
- Redis caching layer
- Elasticsearch integration
- Image optimization
- CDN integration

## Requirements Validation

### Requirement 5.1: Product CRUD Operations
- ✅ Complete CRUD functionality implemented
- ✅ Category relationships maintained
- ✅ Stock quantity management
- ✅ Image handling capabilities

### Requirement 5.2: Search and Filtering
- ✅ Multi-field search functionality
- ✅ Category, brand, price filtering
- ✅ Pagination with metadata
- ✅ Multiple sorting options
- ✅ Stock availability filtering

The Product model successfully implements all required functionality while maintaining API compatibility with the existing Node.js backend and providing enhanced features for the PHP implementation.