<?php
/**
 * Category Model Class
 * 
 * Comprehensive Category model that provides full CRUD operations for category management,
 * including hierarchical structure, search, filtering, and product relationships.
 * Maintains API compatibility with the existing Node.js backend.
 * 
 * Requirements: 5.1
 */

require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class Category extends DatabaseModel {
    protected $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('categories');
        $this->setPrimaryKey('id');
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new category
     * 
     * @param array $categoryData Category data
     * @return array Created category data
     * @throws Exception If creation fails
     */
    public function createCategory($categoryData) {
        try {
            $this->beginTransaction();
            
            // Validate category data
            $this->validateCategoryData($categoryData, true);
            
            // Check name uniqueness
            if ($this->nameExists($categoryData['name'])) {
                throw new Exception('Category with this name already exists', 409);
            }
            
            // Prepare category data for insertion
            $insertData = [
                'name' => trim($categoryData['name']),
                'description' => !empty($categoryData['description']) ? trim($categoryData['description']) : null,
                'image_url' => !empty($categoryData['image_url']) ? trim($categoryData['image_url']) : null,
                'is_active' => $categoryData['is_active'] ?? true
            ];
            
            // Insert category
            $categoryId = $this->insert($insertData);
            
            // Get created category with full details
            $category = $this->getCategoryById($categoryId);
            
            $this->commit();
            
            Logger::info('Category created successfully', [
                'category_id' => $categoryId,
                'name' => $categoryData['name']
            ]);
            
            return $category;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Category creation failed', [
                'name' => $categoryData['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get category by ID with full details
     * 
     * @param int $categoryId Category ID
     * @return array|null Category data or null if not found
     */
    public function getCategoryById($categoryId) {
        try {
            $sql = "SELECT c.*, 
                           COUNT(p.id) as product_count,
                           COUNT(CASE WHEN p.stock_quantity > 0 THEN 1 END) as in_stock_products
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.id = ? AND c.is_active = 1
                    GROUP BY c.id";
            
            $category = $this->db->fetchOne($sql, [$categoryId]);
            
            if (!$category) {
                return null;
            }
            
            return $this->sanitizeCategoryData($category);
            
        } catch (Exception $e) {
            Logger::error('Failed to get category by ID', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get all categories with filtering, search, and pagination
     * 
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated category data
     */
    public function getCategories($filters = [], $page = 1, $perPage = 20) {
        try {
            // Build base query
            $sql = "SELECT c.*, 
                           COUNT(p.id) as product_count,
                           COUNT(CASE WHEN p.stock_quantity > 0 THEN 1 END) as in_stock_products
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1";
            
            $params = [];
            $conditions = [];
            
            // Add search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
                $params = array_merge($params, [$searchTerm, $searchTerm]);
            }
            
            // Add name filter
            if (!empty($filters['name'])) {
                $conditions[] = "c.name = ?";
                $params[] = $filters['name'];
            }
            
            // Add has_products filter
            if (isset($filters['has_products']) && $filters['has_products']) {
                // This will be handled in HAVING clause after GROUP BY
            }
            
            // Add conditions to query
            if (!empty($conditions)) {
                $sql .= " AND " . implode(' AND ', $conditions);
            }
            
            // Group by category
            $sql .= " GROUP BY c.id";
            
            // Add having clause for product filters
            if (isset($filters['has_products']) && $filters['has_products']) {
                $sql .= " HAVING product_count > 0";
            }
            
            // Add sorting
            $orderBy = $this->buildOrderBy($filters['sort'] ?? null);
            $sql .= " ORDER BY " . $orderBy;
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(DISTINCT c.id) 
                        FROM categories c
                        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                        WHERE c.is_active = 1";
            
            if (!empty($conditions)) {
                $countSql .= " AND " . implode(' AND ', $conditions);
            }
            
            if (isset($filters['has_products']) && $filters['has_products']) {
                $countSql = "SELECT COUNT(*) FROM (
                    SELECT c.id 
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1";
                
                if (!empty($conditions)) {
                    $countSql .= " AND " . implode(' AND ', $conditions);
                }
                
                $countSql .= " GROUP BY c.id HAVING COUNT(p.id) > 0
                ) as filtered_categories";
            }
            
            $total = (int)$this->db->fetchColumn($countSql, $params);
            
            // Add pagination
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$perPage;
            $params[] = (int)$offset;
            
            // Execute query
            $categories = $this->db->fetchAll($sql, $params);
            
            // Sanitize category data
            $sanitizedCategories = array_map([$this, 'sanitizeCategoryData'], $categories);
            
            return [
                'categories' => $sanitizedCategories,
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
            Logger::error('Failed to get categories', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update category
     * 
     * @param int $categoryId Category ID
     * @param array $updateData Data to update
     * @return array Updated category data
     * @throws Exception If update fails
     */
    public function updateCategory($categoryId, $updateData) {
        try {
            $this->beginTransaction();
            
            // Get existing category
            $existingCategory = $this->find($categoryId);
            if (!$existingCategory) {
                throw new Exception('Category not found', 404);
            }
            
            // Validate update data
            $this->validateCategoryData($updateData, false);
            
            // Check name uniqueness if name is being updated
            if (isset($updateData['name']) && $updateData['name'] !== $existingCategory['name']) {
                if ($this->nameExists($updateData['name'], $categoryId)) {
                    throw new Exception('Category name already exists', 409);
                }
            }
            
            // Prepare update data
            $allowedFields = ['name', 'description', 'image_url', 'is_active'];
            $filteredData = [];
            
            foreach ($updateData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    switch ($field) {
                        case 'name':
                        case 'description':
                        case 'image_url':
                            $filteredData[$field] = !empty($value) ? trim($value) : null;
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
            
            // Update category
            $updated = $this->updateById($categoryId, $filteredData);
            
            if (!$updated) {
                throw new Exception('Failed to update category', 500);
            }
            
            // Get updated category
            $category = $this->getCategoryById($categoryId);
            
            $this->commit();
            
            Logger::info('Category updated successfully', [
                'category_id' => $categoryId,
                'updated_fields' => array_keys($filteredData)
            ]);
            
            return $category;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Category update failed', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete category (soft delete with product handling)
     * 
     * @param int $categoryId Category ID
     * @param bool $forceDelete Whether to force delete even with products
     * @return bool Success status
     * @throws Exception If deletion fails
     */
    public function deleteCategory($categoryId, $forceDelete = false) {
        try {
            $this->beginTransaction();
            
            // Check if category exists
            $category = $this->find($categoryId);
            if (!$category) {
                throw new Exception('Category not found', 404);
            }
            
            // Check for products in this category
            $productCount = $this->getProductCount($categoryId);
            
            if ($productCount > 0 && !$forceDelete) {
                throw new Exception(
                    "Cannot delete category with {$productCount} products. Use force delete or move products first.", 
                    409
                );
            }
            
            // If force delete, set products' category_id to null
            if ($productCount > 0 && $forceDelete) {
                $this->removeProductsFromCategory($categoryId);
            }
            
            // Soft delete by setting is_active to false
            $updated = $this->updateById($categoryId, [
                'is_active' => false,
                'name' => $category['name'] . '_deleted_' . time() // Prevent name conflicts
            ]);
            
            if (!$updated) {
                throw new Exception('Failed to delete category', 500);
            }
            
            $this->commit();
            
            Logger::info('Category deleted successfully', [
                'category_id' => $categoryId,
                'name' => $category['name'],
                'products_affected' => $productCount,
                'force_delete' => $forceDelete
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Category deletion failed', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get products in a category
     * 
     * @param int $categoryId Category ID
     * @param array $filters Additional filters
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated product data
     */
    public function getCategoryProducts($categoryId, $filters = [], $page = 1, $perPage = 20) {
        try {
            // Verify category exists
            if (!$this->find($categoryId)) {
                throw new Exception('Category not found', 404);
            }
            
            // Use Product model to get products by category
            require_once __DIR__ . '/Product.php';
            $productModel = new Product();
            
            $filters['category_id'] = $categoryId;
            return $productModel->getProducts($filters, $page, $perPage);
            
        } catch (Exception $e) {
            Logger::error('Failed to get category products', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Move products from one category to another
     * 
     * @param int $fromCategoryId Source category ID
     * @param int $toCategoryId Target category ID
     * @return int Number of products moved
     * @throws Exception If move fails
     */
    public function moveProducts($fromCategoryId, $toCategoryId) {
        try {
            $this->beginTransaction();
            
            // Verify both categories exist
            if (!$this->find($fromCategoryId)) {
                throw new Exception('Source category not found', 404);
            }
            
            if ($toCategoryId !== null && !$this->find($toCategoryId)) {
                throw new Exception('Target category not found', 404);
            }
            
            // Move products
            $sql = "UPDATE products SET category_id = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE category_id = ? AND is_active = 1";
            
            $stmt = $this->db->executeQuery($sql, [$toCategoryId, $fromCategoryId]);
            $movedCount = $stmt->rowCount();
            
            $this->commit();
            
            Logger::info('Products moved between categories', [
                'from_category_id' => $fromCategoryId,
                'to_category_id' => $toCategoryId,
                'products_moved' => $movedCount
            ]);
            
            return $movedCount;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Failed to move products between categories', [
                'from_category_id' => $fromCategoryId,
                'to_category_id' => $toCategoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get category statistics
     * 
     * @return array Category statistics
     */
    public function getCategoryStats() {
        try {
            $stats = [];
            
            // Total categories
            $stats['total_categories'] = $this->count(['is_active' => true]);
            
            // Categories with products
            $sql = "SELECT COUNT(DISTINCT c.id) 
                    FROM categories c
                    INNER JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1";
            
            $stats['categories_with_products'] = (int)$this->db->fetchColumn($sql);
            
            // Empty categories
            $stats['empty_categories'] = $stats['total_categories'] - $stats['categories_with_products'];
            
            // Category product distribution
            $sql = "SELECT c.name, c.id, COUNT(p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id, c.name
                    ORDER BY product_count DESC, c.name ASC";
            
            $stats['category_distribution'] = $this->db->fetchAll($sql);
            
            // Recent categories (last 30 days)
            $sql = "SELECT COUNT(*) FROM categories 
                    WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stats['recent_categories'] = (int)$this->db->fetchColumn($sql);
            
            // Categories by creation date (last 12 months)
            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as count
                    FROM categories 
                    WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month ASC";
            
            $stats['monthly_creation'] = $this->db->fetchAll($sql);
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Failed to get category statistics', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Search categories by name or description
     * 
     * @param string $searchTerm Search term
     * @param array $filters Additional filters
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated category data
     */
    public function searchCategories($searchTerm, $filters = [], $page = 1, $perPage = 20) {
        $filters['search'] = $searchTerm;
        return $this->getCategories($filters, $page, $perPage);
    }
    
    /**
     * Get popular categories (by product count)
     * 
     * @param int $limit Number of categories to return
     * @return array Popular categories
     */
    public function getPopularCategories($limit = 10) {
        try {
            $sql = "SELECT c.*, COUNT(p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id
                    HAVING product_count > 0
                    ORDER BY product_count DESC, c.name ASC
                    LIMIT ?";
            
            $categories = $this->db->fetchAll($sql, [(int)$limit]);
            
            return array_map([$this, 'sanitizeCategoryData'], $categories);
            
        } catch (Exception $e) {
            Logger::error('Failed to get popular categories', [
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get categories for dropdown/select options
     * 
     * @param bool $includeEmpty Whether to include categories without products
     * @return array Simple category list for dropdowns
     */
    public function getCategoriesForSelect($includeEmpty = true) {
        try {
            $sql = "SELECT c.id, c.name, COUNT(p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id, c.name";
            
            if (!$includeEmpty) {
                $sql .= " HAVING product_count > 0";
            }
            
            $sql .= " ORDER BY c.name ASC";
            
            $categories = $this->db->fetchAll($sql);
            
            return array_map(function($category) {
                return [
                    'id' => (int)$category['id'],
                    'name' => $category['name'],
                    'product_count' => (int)$category['product_count']
                ];
            }, $categories);
            
        } catch (Exception $e) {
            Logger::error('Failed to get categories for select', [
                'include_empty' => $includeEmpty,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Check if category name exists
     * 
     * @param string $name Category name to check
     * @param int|null $excludeCategoryId Category ID to exclude from check
     * @return bool True if name exists
     */
    public function nameExists($name, $excludeCategoryId = null) {
        try {
            $conditions = ['name' => trim($name), 'is_active' => true];
            
            if ($excludeCategoryId) {
                $sql = "SELECT COUNT(*) FROM categories WHERE name = ? AND is_active = 1 AND id != ?";
                $params = [$conditions['name'], $excludeCategoryId];
                return (int)$this->db->fetchColumn($sql, $params) > 0;
            }
            
            return $this->exists($conditions);
            
        } catch (Exception $e) {
            Logger::error('Category name existence check failed', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get product count for a category
     * 
     * @param int $categoryId Category ID
     * @return int Product count
     */
    public function getProductCount($categoryId) {
        try {
            $sql = "SELECT COUNT(*) FROM products WHERE category_id = ? AND is_active = 1";
            return (int)$this->db->fetchColumn($sql, [$categoryId]);
        } catch (Exception $e) {
            Logger::error('Failed to get product count for category', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Remove products from category (set category_id to null)
     * 
     * @param int $categoryId Category ID
     * @return int Number of products affected
     */
    private function removeProductsFromCategory($categoryId) {
        try {
            $sql = "UPDATE products SET category_id = NULL, updated_at = CURRENT_TIMESTAMP 
                    WHERE category_id = ? AND is_active = 1";
            
            $stmt = $this->db->executeQuery($sql, [$categoryId]);
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            Logger::error('Failed to remove products from category', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Build ORDER BY clause
     * 
     * @param string|null $sort Sort parameter
     * @return string ORDER BY clause
     */
    private function buildOrderBy($sort) {
        $validSorts = [
            'name_asc' => 'c.name ASC',
            'name_desc' => 'c.name DESC',
            'created_asc' => 'c.created_at ASC',
            'created_desc' => 'c.created_at DESC',
            'products_asc' => 'product_count ASC',
            'products_desc' => 'product_count DESC'
        ];
        
        if (!empty($sort) && isset($validSorts[$sort])) {
            return $validSorts[$sort];
        }
        
        // Default sort
        return 'c.name ASC';
    }
    
    /**
     * Validate category data
     * 
     * @param array $categoryData Category data to validate
     * @param bool $isCreation Whether this is for category creation
     * @throws Exception If validation fails
     */
    private function validateCategoryData($categoryData, $isCreation = false) {
        $errors = [];
        
        // Name validation
        if ($isCreation && empty($categoryData['name'])) {
            $errors[] = 'Category name is required';
        } elseif (!empty($categoryData['name'])) {
            $name = trim($categoryData['name']);
            if (strlen($name) > 100) {
                $errors[] = 'Category name is too long (maximum 100 characters)';
            } elseif (strlen($name) < 2) {
                $errors[] = 'Category name is too short (minimum 2 characters)';
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-_&()]+$/', $name)) {
                $errors[] = 'Category name contains invalid characters';
            }
        }
        
        // Description validation
        if (!empty($categoryData['description']) && strlen($categoryData['description']) > 65535) {
            $errors[] = 'Category description is too long (maximum 65535 characters)';
        }
        
        // Image URL validation
        if (!empty($categoryData['image_url'])) {
            $imageUrl = trim($categoryData['image_url']);
            if (strlen($imageUrl) > 255) {
                $errors[] = 'Image URL is too long (maximum 255 characters)';
            } elseif (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid image URL format';
            }
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
        }
    }
    
    /**
     * Sanitize category data for API response
     * 
     * @param array $category Category data
     * @return array Sanitized category data
     */
    private function sanitizeCategoryData($category) {
        $sanitized = [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'image_url' => $category['image_url'],
            'is_active' => (bool)$category['is_active'],
            'created_at' => $category['created_at'],
            'updated_at' => $category['updated_at'] ?? null
        ];
        
        // Add product counts if available
        if (isset($category['product_count'])) {
            $sanitized['product_count'] = (int)$category['product_count'];
        }
        
        if (isset($category['in_stock_products'])) {
            $sanitized['in_stock_products'] = (int)$category['in_stock_products'];
        }
        
        // Add computed fields
        $sanitized['has_products'] = isset($category['product_count']) ? $category['product_count'] > 0 : false;
        
        return $sanitized;
    }
}