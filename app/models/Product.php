<?php
/**
 * Product Model Class
 * 
 * Comprehensive Product model that provides full CRUD operations for product management,
 * including search, filtering, pagination, and stock quantity management.
 * Maintains API compatibility with the existing Node.js backend.
 * 
 * Requirements: 5.1, 5.2
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class Product extends DatabaseModel {
    protected $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('products');
        $this->setPrimaryKey('id');
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new product
     * 
     * @param array $productData Product data
     * @return array Created product data
     * @throws Exception If creation fails
     */
    public function createProduct($productData) {
        try {
            $this->beginTransaction();
            
            // Validate product data
            $this->validateProductData($productData, true);
            
            // Check SKU uniqueness if provided
            if (!empty($productData['sku']) && $this->skuExists($productData['sku'])) {
                throw new Exception('Product with this SKU already exists', 409);
            }
            
            // Prepare product data for insertion
            $insertData = [
                'name' => trim($productData['name']),
                'description' => !empty($productData['description']) ? trim($productData['description']) : null,
                'price' => (float)$productData['price'],
                'stock_quantity' => (int)($productData['stock_quantity'] ?? 0),
                'category_id' => !empty($productData['category_id']) ? (int)$productData['category_id'] : null,
                'brand' => !empty($productData['brand']) ? trim($productData['brand']) : null,
                'sku' => !empty($productData['sku']) ? trim($productData['sku']) : $this->generateSKU($productData['name']),
                'is_active' => $productData['is_active'] ?? true
            ];
            
            // Insert product
            $productId = $this->insert($insertData);
            
            // Handle product images if provided
            if (!empty($productData['images'])) {
                $this->addProductImages($productId, $productData['images']);
            }
            
            // Get created product with full details
            $product = $this->getProductById($productId);
            
            $this->commit();
            
            Logger::info('Product created successfully', [
                'product_id' => $productId,
                'name' => $productData['name'],
                'sku' => $insertData['sku']
            ]);
            
            return $product;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Product creation failed', [
                'name' => $productData['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get product by ID with full details
     * 
     * @param int $productId Product ID
     * @return array|null Product data or null if not found
     */
    public function getProductById($productId) {
        try {
            $sql = "SELECT p.*, c.name as category_name, c.description as category_description
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.id = ? AND p.is_active = 1";
            
            $product = $this->db->fetchOne($sql, [$productId]);
            
            if (!$product) {
                return null;
            }
            
            // Get product images
            $product['images'] = $this->getProductImages($productId);
            
            return $this->sanitizeProductData($product);
            
        } catch (Exception $e) {
            Logger::error('Failed to get product by ID', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get all products with filtering, search, and pagination
     * 
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated product data
     */
    public function getProducts($filters = [], $page = 1, $perPage = 20) {
        try {
            // Build base query
            $sql = "SELECT p.*, c.name as category_name, 
                           pi.image_url as primary_image,
                           pi.alt_text as primary_image_alt
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                    WHERE p.is_active = 1";
            
            $params = [];
            $conditions = [];
            
            // Add search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ? OR p.sku LIKE ?)";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            // Add category filter
            if (!empty($filters['category_id'])) {
                $conditions[] = "p.category_id = ?";
                $params[] = (int)$filters['category_id'];
            }
            
            // Add brand filter
            if (!empty($filters['brand'])) {
                $conditions[] = "p.brand = ?";
                $params[] = $filters['brand'];
            }
            
            // Add price range filters
            if (!empty($filters['min_price'])) {
                $conditions[] = "p.price >= ?";
                $params[] = (float)$filters['min_price'];
            }
            
            if (!empty($filters['max_price'])) {
                $conditions[] = "p.price <= ?";
                $params[] = (float)$filters['max_price'];
            }
            
            // Add stock filter
            if (isset($filters['in_stock']) && $filters['in_stock']) {
                $conditions[] = "p.stock_quantity > 0";
            }
            
            // Add conditions to query
            if (!empty($conditions)) {
                $sql .= " AND " . implode(' AND ', $conditions);
            }
            
            // Add sorting
            $orderBy = $this->buildOrderBy($filters['sort'] ?? null);
            $sql .= " ORDER BY " . $orderBy;
            
            // Get total count for pagination
            $countSql = str_replace(
                "SELECT p.*, c.name as category_name, pi.image_url as primary_image, pi.alt_text as primary_image_alt",
                "SELECT COUNT(DISTINCT p.id)",
                $sql
            );
            $countSql = preg_replace('/ORDER BY.*$/', '', $countSql);
            
            $total = (int)$this->db->fetchColumn($countSql, $params);
            
            // Add pagination
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$perPage;
            $params[] = (int)$offset;
            
            // Execute query
            $products = $this->db->fetchAll($sql, $params);
            
            // Sanitize product data
            $sanitizedProducts = array_map([$this, 'sanitizeProductData'], $products);
            
            return [
                'products' => $sanitizedProducts,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'has_next' => $page < ceil($total / $perPage),
                    'has_prev' => $page > 1
                ],
                'filters_applied' => $filters
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get products', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update product
     * 
     * @param int $productId Product ID
     * @param array $updateData Data to update
     * @return array Updated product data
     * @throws Exception If update fails
     */
    public function updateProduct($productId, $updateData) {
        try {
            $this->beginTransaction();
            
            // Get existing product
            $existingProduct = $this->find($productId);
            if (!$existingProduct) {
                throw new Exception('Product not found', 404);
            }
            
            // Validate update data
            $this->validateProductData($updateData, false);
            
            // Check SKU uniqueness if SKU is being updated
            if (isset($updateData['sku']) && $updateData['sku'] !== $existingProduct['sku']) {
                if ($this->skuExists($updateData['sku'], $productId)) {
                    throw new Exception('SKU already exists', 409);
                }
            }
            
            // Prepare update data
            $allowedFields = ['name', 'description', 'price', 'stock_quantity', 'category_id', 'brand', 'sku', 'is_active'];
            $filteredData = [];
            
            foreach ($updateData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    switch ($field) {
                        case 'name':
                        case 'description':
                        case 'brand':
                        case 'sku':
                            $filteredData[$field] = !empty($value) ? trim($value) : null;
                            break;
                        case 'price':
                            $filteredData[$field] = (float)$value;
                            break;
                        case 'stock_quantity':
                        case 'category_id':
                            $filteredData[$field] = !empty($value) ? (int)$value : null;
                            break;
                        case 'is_active':
                            $filteredData[$field] = (bool)$value;
                            break;
                        default:
                            $filteredData[$field] = $value;
                    }
                }
            }
            
            if (empty($filteredData)) {
                throw new Exception('No valid fields to update', 400);
            }
            
            // Update product
            $updated = $this->updateById($productId, $filteredData);
            
            if (!$updated) {
                throw new Exception('Failed to update product', 500);
            }
            
            // Handle image updates if provided
            if (isset($updateData['images'])) {
                $this->updateProductImages($productId, $updateData['images']);
            }
            
            // Get updated product
            $product = $this->getProductById($productId);
            
            $this->commit();
            
            Logger::info('Product updated successfully', [
                'product_id' => $productId,
                'updated_fields' => array_keys($filteredData)
            ]);
            
            return $product;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Product update failed', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete product (soft delete)
     * 
     * @param int $productId Product ID
     * @return bool Success status
     * @throws Exception If deletion fails
     */
    public function deleteProduct($productId) {
        try {
            $this->beginTransaction();
            
            // Check if product exists
            $product = $this->find($productId);
            if (!$product) {
                throw new Exception('Product not found', 404);
            }
            
            // Soft delete by setting is_active to false
            $updated = $this->updateById($productId, [
                'is_active' => false,
                'sku' => $product['sku'] . '_deleted_' . time() // Prevent SKU conflicts
            ]);
            
            if (!$updated) {
                throw new Exception('Failed to delete product', 500);
            }
            
            $this->commit();
            
            Logger::info('Product deleted successfully', [
                'product_id' => $productId,
                'name' => $product['name']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Product deletion failed', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update stock quantity
     * 
     * @param int $productId Product ID
     * @param int $quantity New stock quantity
     * @param string $operation Operation type: 'set', 'add', 'subtract'
     * @return bool Success status
     * @throws Exception If update fails
     */
    public function updateStock($productId, $quantity, $operation = 'set') {
        try {
            $this->beginTransaction();
            
            // Get current product
            $product = $this->find($productId);
            if (!$product) {
                throw new Exception('Product not found', 404);
            }
            
            $newQuantity = 0;
            
            switch ($operation) {
                case 'set':
                    $newQuantity = (int)$quantity;
                    break;
                case 'add':
                    $newQuantity = (int)$product['stock_quantity'] + (int)$quantity;
                    break;
                case 'subtract':
                    $newQuantity = (int)$product['stock_quantity'] - (int)$quantity;
                    break;
                default:
                    throw new Exception('Invalid stock operation', 400);
            }
            
            // Ensure stock doesn't go negative
            if ($newQuantity < 0) {
                throw new Exception('Insufficient stock quantity', 400);
            }
            
            // Update stock
            $updated = $this->updateById($productId, ['stock_quantity' => $newQuantity]);
            
            if (!$updated) {
                throw new Exception('Failed to update stock', 500);
            }
            
            $this->commit();
            
            Logger::info('Product stock updated', [
                'product_id' => $productId,
                'operation' => $operation,
                'quantity_change' => $quantity,
                'old_quantity' => $product['stock_quantity'],
                'new_quantity' => $newQuantity
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Stock update failed', [
                'product_id' => $productId,
                'operation' => $operation,
                'quantity' => $quantity,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get products by category
     * 
     * @param int $categoryId Category ID
     * @param array $filters Additional filters
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated product data
     */
    public function getProductsByCategory($categoryId, $filters = [], $page = 1, $perPage = 20) {
        $filters['category_id'] = $categoryId;
        return $this->getProducts($filters, $page, $perPage);
    }
    
    /**
     * Search products by name, description, or SKU
     * 
     * @param string $searchTerm Search term
     * @param array $filters Additional filters
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated product data
     */
    public function searchProducts($searchTerm, $filters = [], $page = 1, $perPage = 20) {
        $filters['search'] = $searchTerm;
        return $this->getProducts($filters, $page, $perPage);
    }
    
    /**
     * Get featured products
     * 
     * @param int $limit Number of products to return
     * @return array Featured products
     */
    public function getFeaturedProducts($limit = 10) {
        try {
            $sql = "SELECT p.*, c.name as category_name, 
                           pi.image_url as primary_image,
                           pi.alt_text as primary_image_alt
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                    WHERE p.is_active = 1 AND p.stock_quantity > 0
                    ORDER BY p.created_at DESC
                    LIMIT ?";
            
            $products = $this->db->fetchAll($sql, [(int)$limit]);
            
            return array_map([$this, 'sanitizeProductData'], $products);
            
        } catch (Exception $e) {
            Logger::error('Failed to get featured products', [
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get low stock products
     * 
     * @param int $threshold Stock threshold
     * @param int $limit Number of products to return
     * @return array Low stock products
     */
    public function getLowStockProducts($threshold = 10, $limit = 50) {
        try {
            $sql = "SELECT p.*, c.name as category_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.is_active = 1 AND p.stock_quantity <= ? AND p.stock_quantity >= 0
                    ORDER BY p.stock_quantity ASC, p.name ASC
                    LIMIT ?";
            
            $products = $this->db->fetchAll($sql, [(int)$threshold, (int)$limit]);
            
            return array_map([$this, 'sanitizeProductData'], $products);
            
        } catch (Exception $e) {
            Logger::error('Failed to get low stock products', [
                'threshold' => $threshold,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get product statistics
     * 
     * @return array Product statistics
     */
    public function getProductStats() {
        try {
            $stats = [];
            
            // Total products
            $stats['total_products'] = $this->count(['is_active' => true]);
            
            // Products by category
            $sql = "SELECT c.name as category_name, COUNT(p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id, c.name
                    ORDER BY product_count DESC";
            
            $stats['products_by_category'] = $this->db->fetchAll($sql);
            
            // Stock statistics
            $sql = "SELECT 
                        COUNT(*) as total_products,
                        SUM(stock_quantity) as total_stock,
                        AVG(stock_quantity) as average_stock,
                        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
                        COUNT(CASE WHEN stock_quantity <= 10 THEN 1 END) as low_stock
                    FROM products 
                    WHERE is_active = 1";
            
            $stockStats = $this->db->fetchOne($sql);
            $stats['stock'] = [
                'total_products' => (int)$stockStats['total_products'],
                'total_stock' => (int)$stockStats['total_stock'],
                'average_stock' => round((float)$stockStats['average_stock'], 2),
                'out_of_stock' => (int)$stockStats['out_of_stock'],
                'low_stock' => (int)$stockStats['low_stock']
            ];
            
            // Price statistics
            $sql = "SELECT 
                        MIN(price) as min_price,
                        MAX(price) as max_price,
                        AVG(price) as average_price
                    FROM products 
                    WHERE is_active = 1";
            
            $priceStats = $this->db->fetchOne($sql);
            $stats['pricing'] = [
                'min_price' => (float)$priceStats['min_price'],
                'max_price' => (float)$priceStats['max_price'],
                'average_price' => round((float)$priceStats['average_price'], 2)
            ];
            
            // Recent products (last 30 days)
            $sql = "SELECT COUNT(*) FROM products WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stats['recent_products'] = (int)$this->db->fetchColumn($sql);
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Failed to get product statistics', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get product images
     * 
     * @param int $productId Product ID
     * @return array Product images
     */
    public function getProductImages($productId) {
        try {
            $sql = "SELECT * FROM product_images 
                    WHERE product_id = ? 
                    ORDER BY is_primary DESC, sort_order ASC";
            
            return $this->db->fetchAll($sql, [$productId]);
            
        } catch (Exception $e) {
            Logger::error('Failed to get product images', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Add product images
     * 
     * @param int $productId Product ID
     * @param array $images Image data
     * @return bool Success status
     */
    public function addProductImages($productId, $images) {
        try {
            foreach ($images as $index => $image) {
                $imageData = [
                    'product_id' => $productId,
                    'image_url' => $image['url'],
                    'alt_text' => $image['alt_text'] ?? null,
                    'is_primary' => $image['is_primary'] ?? ($index === 0),
                    'sort_order' => $image['sort_order'] ?? $index
                ];
                
                $sql = "INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
                        VALUES (?, ?, ?, ?, ?)";
                
                $this->db->executeQuery($sql, array_values($imageData));
            }
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to add product images', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update product images
     * 
     * @param int $productId Product ID
     * @param array $images New image data
     * @return bool Success status
     */
    public function updateProductImages($productId, $images) {
        try {
            // Delete existing images
            $sql = "DELETE FROM product_images WHERE product_id = ?";
            $this->db->executeQuery($sql, [$productId]);
            
            // Add new images
            if (!empty($images)) {
                $this->addProductImages($productId, $images);
            }
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to update product images', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get product image by ID
     * 
     * @param int $imageId Image ID
     * @param int $productId Product ID (for security)
     * @return array|null Image data or null if not found
     */
    public function getProductImageById($imageId, $productId) {
        try {
            $sql = "SELECT * FROM product_images 
                    WHERE id = ? AND product_id = ?";
            
            return $this->db->fetchOne($sql, [$imageId, $productId]);
            
        } catch (Exception $e) {
            Logger::error('Failed to get product image by ID', [
                'image_id' => $imageId,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Delete all product images
     * 
     * @param int $productId Product ID
     * @return bool Success status
     */
    public function deleteAllProductImages($productId) {
        try {
            $sql = "DELETE FROM product_images WHERE product_id = ?";
            $stmt = $this->db->executeQuery($sql, [$productId]);
            
            Logger::info('All product images deleted from database', [
                'product_id' => $productId,
                'deleted_count' => $stmt->rowCount()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to delete all product images', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete specific product image
     * 
     * @param int $imageId Image ID
     * @param int $productId Product ID (for security)
     * @return bool Success status
     */
    public function deleteProductImage($imageId, $productId) {
        try {
            $sql = "DELETE FROM product_images 
                    WHERE id = ? AND product_id = ?";
            
            $stmt = $this->db->executeQuery($sql, [$imageId, $productId]);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            Logger::info('Product image deleted from database', [
                'image_id' => $imageId,
                'product_id' => $productId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to delete product image', [
                'image_id' => $imageId,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Set primary product image
     * 
     * @param int $imageId Image ID to set as primary
     * @param int $productId Product ID (for security)
     * @return bool Success status
     */
    public function setPrimaryProductImage($imageId, $productId) {
        try {
            $this->db->beginTransaction();
            
            // First, unset all primary images for this product
            $sql1 = "UPDATE product_images 
                     SET is_primary = 0 
                     WHERE product_id = ?";
            $this->db->executeQuery($sql1, [$productId]);
            
            // Then set the specified image as primary
            $sql2 = "UPDATE product_images 
                     SET is_primary = 1 
                     WHERE id = ? AND product_id = ?";
            $stmt = $this->db->executeQuery($sql2, [$imageId, $productId]);
            
            if ($stmt->rowCount() === 0) {
                $this->db->rollback();
                return false;
            }
            
            $this->db->commit();
            
            Logger::info('Primary product image set', [
                'image_id' => $imageId,
                'product_id' => $productId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error('Failed to set primary product image', [
                'image_id' => $imageId,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update product image metadata
     * 
     * @param int $imageId Image ID
     * @param int $productId Product ID (for security)
     * @param array $updateData Data to update
     * @return bool Success status
     */
    public function updateProductImage($imageId, $productId, $updateData) {
        try {
            $fields = [];
            $params = [];
            
            // Build update query dynamically
            foreach ($updateData as $field => $value) {
                if (in_array($field, ['alt_text', 'sort_order'])) {
                    $fields[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $params[] = $imageId;
            $params[] = $productId;
            
            $sql = "UPDATE product_images 
                    SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ? AND product_id = ?";
            
            $stmt = $this->db->executeQuery($sql, $params);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            Logger::info('Product image updated', [
                'image_id' => $imageId,
                'product_id' => $productId,
                'updated_fields' => array_keys($updateData)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to update product image', [
                'image_id' => $imageId,
                'product_id' => $productId,
                'update_data' => $updateData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Check if SKU exists
     * 
     * @param string $sku SKU to check
     * @param int|null $excludeProductId Product ID to exclude from check
     * @return bool True if SKU exists
     */
    public function skuExists($sku, $excludeProductId = null) {
        try {
            $conditions = ['sku' => trim($sku), 'is_active' => true];
            
            if ($excludeProductId) {
                $sql = "SELECT COUNT(*) FROM products WHERE sku = ? AND is_active = 1 AND id != ?";
                $params = [$conditions['sku'], $excludeProductId];
                return (int)$this->db->fetchColumn($sql, $params) > 0;
            }
            
            return $this->exists($conditions);
            
        } catch (Exception $e) {
            Logger::error('SKU existence check failed', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Generate unique SKU
     * 
     * @param string $productName Product name
     * @return string Generated SKU
     */
    private function generateSKU($productName) {
        // Create base SKU from product name
        $baseSku = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($productName, 0, 6)));
        
        // Add timestamp suffix
        $sku = $baseSku . date('ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness
        $counter = 1;
        $originalSku = $sku;
        
        while ($this->skuExists($sku)) {
            $sku = $originalSku . sprintf('%02d', $counter);
            $counter++;
            
            if ($counter > 99) {
                // Fallback to timestamp-based SKU
                $sku = $baseSku . time();
                break;
            }
        }
        
        return $sku;
    }
    
    /**
     * Build ORDER BY clause
     * 
     * @param string|null $sort Sort parameter
     * @return string ORDER BY clause
     */
    private function buildOrderBy($sort) {
        $validSorts = [
            'name_asc' => 'p.name ASC',
            'name_desc' => 'p.name DESC',
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'created_asc' => 'p.created_at ASC',
            'created_desc' => 'p.created_at DESC',
            'stock_asc' => 'p.stock_quantity ASC',
            'stock_desc' => 'p.stock_quantity DESC',
            'brand_asc' => 'p.brand ASC',
            'brand_desc' => 'p.brand DESC'
        ];
        
        if (!empty($sort) && isset($validSorts[$sort])) {
            return $validSorts[$sort];
        }
        
        // Default sort
        return 'p.created_at DESC';
    }
    
    /**
     * Validate product data
     * 
     * @param array $productData Product data to validate
     * @param bool $isCreation Whether this is for product creation
     * @throws Exception If validation fails
     */
    private function validateProductData($productData, $isCreation = false) {
        $errors = [];
        
        // Name validation
        if ($isCreation && empty($productData['name'])) {
            $errors[] = 'Product name is required';
        } elseif (!empty($productData['name']) && strlen(trim($productData['name'])) > 255) {
            $errors[] = 'Product name is too long (maximum 255 characters)';
        } elseif (!empty($productData['name']) && strlen(trim($productData['name'])) < 2) {
            $errors[] = 'Product name is too short (minimum 2 characters)';
        }
        
        // Price validation
        if ($isCreation && !isset($productData['price'])) {
            $errors[] = 'Product price is required';
        } elseif (isset($productData['price'])) {
            $price = (float)$productData['price'];
            if ($price < 0) {
                $errors[] = 'Product price cannot be negative';
            } elseif ($price > 999999.99) {
                $errors[] = 'Product price is too high (maximum 999999.99)';
            }
        }
        
        // Stock quantity validation
        if (isset($productData['stock_quantity'])) {
            $stock = (int)$productData['stock_quantity'];
            if ($stock < 0) {
                $errors[] = 'Stock quantity cannot be negative';
            } elseif ($stock > 999999) {
                $errors[] = 'Stock quantity is too high (maximum 999999)';
            }
        }
        
        // Description validation
        if (!empty($productData['description']) && strlen($productData['description']) > 65535) {
            $errors[] = 'Product description is too long (maximum 65535 characters)';
        }
        
        // Brand validation
        if (!empty($productData['brand']) && strlen(trim($productData['brand'])) > 100) {
            $errors[] = 'Brand name is too long (maximum 100 characters)';
        }
        
        // SKU validation
        if (!empty($productData['sku'])) {
            $sku = trim($productData['sku']);
            if (strlen($sku) > 50) {
                $errors[] = 'SKU is too long (maximum 50 characters)';
            } elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
                $errors[] = 'SKU can only contain letters, numbers, hyphens, and underscores';
            }
        }
        
        // Category validation
        if (!empty($productData['category_id'])) {
            $categoryId = (int)$productData['category_id'];
            if (!$this->categoryExists($categoryId)) {
                $errors[] = 'Invalid category specified';
            }
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
        }
    }
    
    /**
     * Check if category exists
     * 
     * @param int $categoryId Category ID
     * @return bool True if category exists
     */
    private function categoryExists($categoryId) {
        try {
            $sql = "SELECT COUNT(*) FROM categories WHERE id = ? AND is_active = 1";
            return (int)$this->db->fetchColumn($sql, [$categoryId]) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Sanitize product data for API response
     * 
     * @param array $product Product data
     * @return array Sanitized product data
     */
    private function sanitizeProductData($product) {
        $sanitized = [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'stock_quantity' => (int)$product['stock_quantity'],
            'category_id' => $product['category_id'] ? (int)$product['category_id'] : null,
            'category_name' => $product['category_name'] ?? null,
            'category_description' => $product['category_description'] ?? null,
            'brand' => $product['brand'],
            'sku' => $product['sku'],
            'is_active' => (bool)$product['is_active'],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
        
        // Add primary image if available
        if (isset($product['primary_image'])) {
            $sanitized['primary_image'] = [
                'url' => $product['primary_image'],
                'alt_text' => $product['primary_image_alt'] ?? null
            ];
        }
        
        // Add all images if available
        if (isset($product['images'])) {
            $sanitized['images'] = $product['images'];
        }
        
        // Add computed fields
        $sanitized['in_stock'] = $sanitized['stock_quantity'] > 0;
        $sanitized['formatted_price'] = number_format($sanitized['price'], 2);
        
        return $sanitized;
    }
}