# Category Model Implementation

## Overview

The Category model provides comprehensive CRUD operations for category management in the Riya Collections PHP backend. It maintains API compatibility with the existing Node.js backend while implementing robust validation, error handling, and business logic.

## Requirements Fulfilled

- **Requirement 5.1**: Product Management System - Category CRUD operations
- Full category lifecycle management (Create, Read, Update, Delete)
- Category-product relationship handling
- Hierarchical category support (ready for future expansion)
- Comprehensive validation and error handling

## Features Implemented

### Core CRUD Operations

1. **Create Category** (`createCategory`)
   - Validates all input data
   - Enforces name uniqueness
   - Handles optional fields (description, image_url)
   - Supports transaction rollback on failure

2. **Read Operations**
   - `getCategoryById` - Get single category with product counts
   - `getCategories` - Get paginated list with filtering and search
   - `searchCategories` - Search by name or description
   - `getCategoriesForSelect` - Simplified list for dropdowns

3. **Update Category** (`updateCategory`)
   - Partial updates supported
   - Validates changed data
   - Maintains referential integrity
   - Prevents duplicate names

4. **Delete Category** (`deleteCategory`)
   - Soft delete implementation
   - Handles products in category
   - Force delete option available
   - Prevents accidental data loss

### Advanced Features

1. **Product Relationship Management**
   - `getCategoryProducts` - Get products in category
   - `moveProducts` - Move products between categories
   - `getProductCount` - Count products in category
   - Automatic product handling on category deletion

2. **Statistics and Analytics**
   - `getCategoryStats` - Comprehensive category statistics
   - `getPopularCategories` - Categories by product count
   - Distribution analysis
   - Trend tracking

3. **Search and Filtering**
   - Full-text search in name and description
   - Filter by product availability
   - Sorting by multiple criteria
   - Pagination support

## Data Model

### Category Table Structure
```sql
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Validation Rules

#### Name Field
- **Required**: Yes
- **Type**: String
- **Length**: 2-100 characters
- **Characters**: Letters, numbers, spaces, hyphens, underscores, ampersands, parentheses
- **Uniqueness**: Must be unique among active categories

#### Description Field
- **Required**: No
- **Type**: String or null
- **Length**: 0-65535 characters
- **Sanitization**: Trimmed whitespace

#### Image URL Field
- **Required**: No
- **Type**: Valid URL or null
- **Length**: 0-255 characters
- **Validation**: Must be valid URL format

#### Is Active Field
- **Required**: No (defaults to true)
- **Type**: Boolean
- **Values**: true/false, 1/0, 'true'/'false'

## API Response Format

### Single Category Response
```json
{
    "id": 1,
    "name": "Electronics",
    "description": "Electronic devices and gadgets",
    "image_url": "https://example.com/electronics.jpg",
    "is_active": true,
    "created_at": "2024-01-01 00:00:00",
    "updated_at": "2024-01-01 00:00:00",
    "product_count": 15,
    "in_stock_products": 12,
    "has_products": true
}
```

### Category List Response
```json
{
    "categories": [
        {
            "id": 1,
            "name": "Electronics",
            "description": "Electronic devices",
            "image_url": null,
            "is_active": true,
            "created_at": "2024-01-01 00:00:00",
            "updated_at": "2024-01-01 00:00:00",
            "product_count": 15,
            "in_stock_products": 12,
            "has_products": true
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 20,
        "total": 50,
        "total_pages": 3,
        "has_next": true,
        "has_prev": false
    },
    "filters_applied": {
        "search": "electronics",
        "has_products": true
    }
}
```

## Error Handling

### Validation Errors (400)
- Empty or missing name
- Name too short/long
- Invalid characters in name
- Description too long
- Invalid image URL format
- URL too long

### Conflict Errors (409)
- Duplicate category name
- Category has products (on delete without force)

### Not Found Errors (404)
- Category doesn't exist (on update/delete)

### Server Errors (500)
- Database connection issues
- Transaction failures
- Unexpected system errors

## Usage Examples

### Creating a Category
```php
$category = new Category();

$categoryData = [
    'name' => 'Electronics',
    'description' => 'Electronic devices and gadgets',
    'image_url' => 'https://example.com/electronics.jpg',
    'is_active' => true
];

try {
    $result = $category->createCategory($categoryData);
    echo "Category created with ID: " . $result['id'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Getting Categories with Filters
```php
$filters = [
    'search' => 'electronics',
    'has_products' => true,
    'sort' => 'name_asc'
];

$result = $category->getCategories($filters, $page = 1, $perPage = 20);

foreach ($result['categories'] as $cat) {
    echo $cat['name'] . " (" . $cat['product_count'] . " products)\n";
}
```

### Updating a Category
```php
$updateData = [
    'name' => 'Consumer Electronics',
    'description' => 'Updated description'
];

try {
    $result = $category->updateCategory($categoryId, $updateData);
    echo "Category updated successfully";
} catch (Exception $e) {
    echo "Update failed: " . $e->getMessage();
}
```

### Safe Category Deletion
```php
try {
    // Check if category has products
    $productCount = $category->getProductCount($categoryId);
    
    if ($productCount > 0) {
        // Move products to another category first
        $category->moveProducts($categoryId, $newCategoryId);
    }
    
    // Now delete the category
    $category->deleteCategory($categoryId);
    echo "Category deleted successfully";
} catch (Exception $e) {
    echo "Deletion failed: " . $e->getMessage();
}
```

## Security Features

1. **SQL Injection Prevention**
   - All queries use prepared statements
   - Parameter binding for all user inputs
   - No dynamic SQL construction

2. **Input Validation**
   - Comprehensive validation rules
   - Data type checking
   - Length constraints
   - Character restrictions

3. **Data Sanitization**
   - Automatic whitespace trimming
   - HTML entity encoding (when needed)
   - URL validation

4. **Access Control Ready**
   - Model designed for role-based access
   - Audit logging implemented
   - Transaction support for data integrity

## Performance Optimizations

1. **Database Indexes**
   - Primary key on id
   - Index on name for searches
   - Index on is_active for filtering
   - Index on created_at for sorting

2. **Query Optimization**
   - Efficient JOIN operations
   - Proper LIMIT/OFFSET usage
   - COUNT queries optimized
   - Minimal data transfer

3. **Caching Ready**
   - Stateless design
   - Cacheable query results
   - Efficient pagination

## Testing Coverage

### Unit Tests (`CategoryTest.php`)
- All CRUD operations
- Validation scenarios
- Error handling
- Edge cases
- Data sanitization

### Property-Based Tests (`CategoryPropertyTest.php`)
- Data consistency across operations
- Name uniqueness enforcement
- Search result consistency
- Validation consistency
- Statistics accuracy
- Pagination consistency

### Validation Tests (`CategoryValidationTest.php`)
- Comprehensive field validation
- Error message accuracy
- Boundary condition testing
- Character set validation
- URL format validation

### Simple Tests (`test_category_simple.php`)
- Isolated validation logic
- No database dependencies
- Quick verification

## Integration Points

### Product Model Integration
- Foreign key relationship (products.category_id)
- Automatic product count calculation
- Product movement between categories
- Cascade handling on category changes

### Future Hierarchy Support
The model is designed to easily support hierarchical categories by adding:
- `parent_id` field to categories table
- Recursive query methods
- Tree traversal functions
- Breadcrumb generation

### API Controller Integration
Ready for REST API implementation with:
- Consistent response formats
- Proper HTTP status codes
- Error message standardization
- Pagination metadata

## Maintenance and Monitoring

### Logging
- All operations logged with context
- Error tracking with stack traces
- Performance metrics collection
- Security event logging

### Health Checks
- Database connectivity verification
- Data integrity checks
- Performance monitoring
- Error rate tracking

### Backup Considerations
- Soft delete preserves data
- Transaction logs for recovery
- Referential integrity maintained
- Export/import capabilities

## Conclusion

The Category model implementation provides a robust, secure, and scalable foundation for category management in the Riya Collections PHP backend. It maintains full compatibility with the existing Node.js API while adding enhanced validation, error handling, and performance optimizations suitable for production deployment on standard PHP hosting environments.