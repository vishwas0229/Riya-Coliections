<?php
/**
 * Product Controller Class
 * 
 * Comprehensive ProductController that handles all product-related API endpoints
 * including public product browsing, admin product management, and category operations.
 * Maintains API compatibility with the existing Node.js backend.
 * 
 * Requirements: 5.1, 5.2, 11.1
 */

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../services/ImageService.php';

class ProductController {
    private $productModel;
    private $categoryModel;
    private $request;
    private $params;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->productModel = new Product();
        $this->categoryModel = new Category();
    }
    
    /**
     * Set request data
     */
    public function setRequest($request) {
        $this->request = $request;
    }
    
    /**
     * Set route parameters
     */
    public function setParams($params) {
        $this->params = $params;
    }
    
    // ==================== PUBLIC PRODUCT ENDPOINTS ====================
    
    /**
     * GET /api/products - List products with filtering/pagination
     * Public endpoint for browsing products
     */
    public function getAll() {
        try {
            // Extract query parameters
            $filters = $this->extractFilters();
            $page = (int)($this->request['query']['page'] ?? 1);
            $perPage = min((int)($this->request['query']['per_page'] ?? 20), 100); // Max 100 per page
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1) $perPage = 20;
            
            // Get products with pagination
            $result = $this->productModel->getProducts($filters, $page, $perPage);
            
            Logger::info('Products retrieved successfully', [
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
                'total_products' => $result['pagination']['total']
            ]);
            
            Response::paginated(
                $result['products'],
                $result['pagination'],
                'Products retrieved successfully'
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve products', [
                'error' => $e->getMessage(),
                'filters' => $filters ?? [],
                'page' => $page ?? 1
            ]);
            
            Response::error('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/products/{id} - Get single product details
     * Public endpoint for product details
     */
    public function getById($id) {
        try {
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Get product by ID
            $product = $this->productModel->getProductById((int)$id);
            
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            Logger::info('Product retrieved successfully', [
                'product_id' => $id,
                'product_name' => $product['name']
            ]);
            
            Response::success('Product retrieved successfully', $product);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve product', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/products/search - Search products
     * Public endpoint for product search
     */
    public function search() {
        try {
            $searchTerm = trim($this->request['query']['q'] ?? '');
            
            if (empty($searchTerm)) {
                Response::error('Search term is required', 400);
                return;
            }
            
            if (strlen($searchTerm) < 2) {
                Response::error('Search term must be at least 2 characters', 400);
                return;
            }
            
            // Extract additional filters
            $filters = $this->extractFilters();
            $page = (int)($this->request['query']['page'] ?? 1);
            $perPage = min((int)($this->request['query']['per_page'] ?? 20), 100);
            
            // Perform search
            $result = $this->productModel->searchProducts($searchTerm, $filters, $page, $perPage);
            
            Logger::info('Product search completed', [
                'search_term' => $searchTerm,
                'filters' => $filters,
                'results_count' => count($result['products'])
            ]);
            
            Response::paginated(
                $result['products'],
                $result['pagination'],
                "Search results for '{$searchTerm}'"
            );
            
        } catch (Exception $e) {
            Logger::error('Product search failed', [
                'search_term' => $searchTerm ?? '',
                'error' => $e->getMessage()
            ]);
            
            Response::error('Search failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/products/featured - Get featured products
     * Public endpoint for featured products
     */
    public function getFeatured() {
        try {
            $limit = min((int)($this->request['query']['limit'] ?? 10), 50); // Max 50 featured products
            
            $products = $this->productModel->getFeaturedProducts($limit);
            
            Logger::info('Featured products retrieved', [
                'limit' => $limit,
                'count' => count($products)
            ]);
            
            Response::success('Featured products retrieved successfully', $products);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve featured products', [
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve featured products: ' . $e->getMessage(), 500);
        }
    }
    
    // ==================== PUBLIC CATEGORY ENDPOINTS ====================
    
    /**
     * GET /api/categories - List categories
     * Public endpoint for browsing categories
     */
    public function getCategories() {
        try {
            // Extract query parameters
            $filters = [];
            if (!empty($this->request['query']['search'])) {
                $filters['search'] = trim($this->request['query']['search']);
            }
            if (isset($this->request['query']['has_products'])) {
                $filters['has_products'] = filter_var($this->request['query']['has_products'], FILTER_VALIDATE_BOOLEAN);
            }
            
            $page = (int)($this->request['query']['page'] ?? 1);
            $perPage = min((int)($this->request['query']['per_page'] ?? 20), 100);
            $sort = $this->request['query']['sort'] ?? 'name_asc';
            
            if ($page < 1) $page = 1;
            if ($perPage < 1) $perPage = 20;
            
            $filters['sort'] = $sort;
            
            // Get categories with pagination
            $result = $this->categoryModel->getCategories($filters, $page, $perPage);
            
            Logger::info('Categories retrieved successfully', [
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
                'total_categories' => $result['pagination']['total']
            ]);
            
            Response::paginated(
                $result['categories'],
                $result['pagination'],
                'Categories retrieved successfully'
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve categories', [
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/categories/{id} - Get category details
     * Public endpoint for category details
     */
    public function getCategoryById($id) {
        try {
            // Validate category ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid category ID', 400);
                return;
            }
            
            // Get category by ID
            $category = $this->categoryModel->getCategoryById((int)$id);
            
            if (!$category) {
                Response::notFound('Category not found');
                return;
            }
            
            Logger::info('Category retrieved successfully', [
                'category_id' => $id,
                'category_name' => $category['name']
            ]);
            
            Response::success('Category retrieved successfully', $category);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve category', [
                'category_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve category: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/categories/{id}/products - Get products in category
     * Public endpoint for category products
     */
    public function getCategoryProducts($id) {
        try {
            // Validate category ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid category ID', 400);
                return;
            }
            
            // Extract filters and pagination
            $filters = $this->extractFilters();
            $page = (int)($this->request['query']['page'] ?? 1);
            $perPage = min((int)($this->request['query']['per_page'] ?? 20), 100);
            
            if ($page < 1) $page = 1;
            if ($perPage < 1) $perPage = 20;
            
            // Get products in category
            $result = $this->categoryModel->getCategoryProducts((int)$id, $filters, $page, $perPage);
            
            Logger::info('Category products retrieved successfully', [
                'category_id' => $id,
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
                'total_products' => $result['pagination']['total']
            ]);
            
            Response::paginated(
                $result['products'],
                $result['pagination'],
                'Category products retrieved successfully'
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve category products', [
                'category_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve category products: ' . $e->getMessage(), 500);
        }
    }
    
    // ==================== ADMIN PRODUCT ENDPOINTS ====================
    
    /**
     * POST /api/admin/products - Create product
     * Admin-only endpoint for creating products
     */
    public function create() {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate request body
            if (empty($this->request['body'])) {
                Response::error('Request body is required', 400);
                return;
            }
            
            $productData = $this->request['body'];
            
            // Validate required fields
            $requiredFields = ['name', 'price'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (empty($productData[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                Response::validationError([
                    'missing_fields' => $missingFields
                ], 'Required fields are missing');
                return;
            }
            
            // Create product
            $product = $this->productModel->createProduct($productData);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Product created by admin', [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::created($product, 'Product created successfully');
            
        } catch (Exception $e) {
            Logger::error('Product creation failed', [
                'product_data' => $productData ?? [],
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error('Failed to create product: ' . $e->getMessage(), $statusCode);
        }
    }
    
    /**
     * PUT /api/admin/products/{id} - Update product
     * Admin-only endpoint for updating products
     */
    public function update($id) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Validate request body
            if (empty($this->request['body'])) {
                Response::error('Request body is required', 400);
                return;
            }
            
            $updateData = $this->request['body'];
            
            // Update product
            $product = $this->productModel->updateProduct((int)$id, $updateData);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Product updated by admin', [
                'product_id' => $id,
                'product_name' => $product['name'],
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email'],
                'updated_fields' => array_keys($updateData)
            ]);
            
            Response::updated($product, 'Product updated successfully');
            
        } catch (Exception $e) {
            Logger::error('Product update failed', [
                'product_id' => $id,
                'update_data' => $updateData ?? [],
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error('Failed to update product: ' . $e->getMessage(), $statusCode);
        }
    }
    
    /**
     * DELETE /api/admin/products/{id} - Delete product
     * Admin-only endpoint for deleting products
     */
    public function delete($id) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Get product details before deletion for logging
            $product = $this->productModel->getProductById((int)$id);
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            // Delete product
            $deleted = $this->productModel->deleteProduct((int)$id);
            
            if (!$deleted) {
                Response::error('Failed to delete product', 500);
                return;
            }
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Product deleted by admin', [
                'product_id' => $id,
                'product_name' => $product['name'],
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::deleted('Product deleted successfully');
            
        } catch (Exception $e) {
            Logger::error('Product deletion failed', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error('Failed to delete product: ' . $e->getMessage(), $statusCode);
        }
    }
    
    /**
     * POST /api/admin/products/{id}/images - Upload product images
     * Admin-only endpoint for uploading product images
     */
    public function uploadImages($id) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Check if product exists
            $product = $this->productModel->getProductById((int)$id);
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            // Check if files were uploaded
            if (empty($_FILES['images'])) {
                Response::error('No images uploaded', 400);
                return;
            }
            
            // Process uploaded images
            $imageService = new ImageService();
            $uploadedImages = [];
            
            $files = $_FILES['images'];
            
            // Handle multiple file upload
            if (is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $fileInfo = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'size' => $files['size'][$i]
                        ];
                        
                        $uploadResult = $imageService->uploadProductImage($fileInfo, (int)$id);
                        $uploadedImages[] = $uploadResult;
                    }
                }
            } else {
                // Single file upload
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = $imageService->uploadProductImage($files, (int)$id);
                    $uploadedImages[] = $uploadResult;
                }
            }
            
            if (empty($uploadedImages)) {
                Response::error('No images were successfully uploaded', 400);
                return;
            }
            
            // Update product images in database
            $this->productModel->addProductImages((int)$id, $uploadedImages);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Product images uploaded by admin', [
                'product_id' => $id,
                'images_count' => count($uploadedImages),
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::success('Images uploaded successfully', [
                'product_id' => (int)$id,
                'uploaded_images' => $uploadedImages,
                'images_count' => count($uploadedImages)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Product image upload failed', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to upload images: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/products/{id}/images - Get product images
     * Public endpoint for retrieving product images
     */
    public function getImages($id) {
        try {
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Check if product exists
            $product = $this->productModel->getProductById((int)$id);
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            // Get product images
            $images = $this->productModel->getProductImages((int)$id);
            
            Logger::info('Product images retrieved', [
                'product_id' => $id,
                'images_count' => count($images)
            ]);
            
            Response::success('Product images retrieved successfully', [
                'product_id' => (int)$id,
                'images' => $images,
                'images_count' => count($images)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve product images', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve product images: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/admin/products/{id}/images - Delete all product images
     * Admin-only endpoint for deleting all product images
     */
    public function deleteAllImages($id) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Check if product exists
            $product = $this->productModel->getProductById((int)$id);
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            // Get existing images for logging
            $existingImages = $this->productModel->getProductImages((int)$id);
            
            // Delete images from filesystem
            $imageService = new ImageService();
            $filesDeleted = $imageService->deleteProductImages((int)$id);
            
            // Delete images from database
            $dbDeleted = $this->productModel->deleteAllProductImages((int)$id);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('All product images deleted by admin', [
                'product_id' => $id,
                'images_count' => count($existingImages),
                'files_deleted' => $filesDeleted,
                'db_deleted' => $dbDeleted,
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::success('All product images deleted successfully', [
                'product_id' => (int)$id,
                'deleted_images_count' => count($existingImages)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to delete all product images', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to delete images: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/admin/products/{id}/images/{imageId} - Delete specific product image
     * Admin-only endpoint for deleting a specific product image
     */
    public function deleteImage($id, $imageId) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Validate image ID
            if (!is_numeric($imageId) || $imageId <= 0) {
                Response::error('Invalid image ID', 400);
                return;
            }
            
            // Check if product exists
            $product = $this->productModel->getProductById((int)$id);
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            // Get image details before deletion
            $image = $this->productModel->getProductImageById((int)$imageId, (int)$id);
            if (!$image) {
                Response::notFound('Image not found');
                return;
            }
            
            // Extract filename from URL for filesystem deletion
            $filename = basename(parse_url($image['image_url'], PHP_URL_PATH));
            
            // Delete image from filesystem
            $imageService = new ImageService();
            $filesDeleted = $imageService->deleteProductImages((int)$id, [$filename]);
            
            // Delete image from database
            $dbDeleted = $this->productModel->deleteProductImage((int)$imageId, (int)$id);
            
            if (!$dbDeleted) {
                Response::error('Failed to delete image from database', 500);
                return;
            }
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Product image deleted by admin', [
                'product_id' => $id,
                'image_id' => $imageId,
                'filename' => $filename,
                'was_primary' => $image['is_primary'],
                'files_deleted' => $filesDeleted,
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::success('Product image deleted successfully', [
                'product_id' => (int)$id,
                'image_id' => (int)$imageId,
                'was_primary' => (bool)$image['is_primary']
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to delete product image', [
                'product_id' => $id,
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to delete image: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/admin/products/{id}/images/{imageId}/primary - Set image as primary
     * Admin-only endpoint for setting an image as the primary product image
     */
    public function setPrimaryImage($id, $imageId) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Validate image ID
            if (!is_numeric($imageId) || $imageId <= 0) {
                Response::error('Invalid image ID', 400);
                return;
            }
            
            // Check if product exists
            $product = $this->productModel->getProductById((int)$id);
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            // Check if image exists and belongs to this product
            $image = $this->productModel->getProductImageById((int)$imageId, (int)$id);
            if (!$image) {
                Response::notFound('Image not found');
                return;
            }
            
            // Set image as primary
            $updated = $this->productModel->setPrimaryProductImage((int)$imageId, (int)$id);
            
            if (!$updated) {
                Response::error('Failed to set primary image', 500);
                return;
            }
            
            // Get updated image details
            $updatedImage = $this->productModel->getProductImageById((int)$imageId, (int)$id);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Primary product image set by admin', [
                'product_id' => $id,
                'image_id' => $imageId,
                'image_url' => $updatedImage['image_url'],
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::success('Primary image set successfully', [
                'product_id' => (int)$id,
                'image_id' => (int)$imageId,
                'image' => $updatedImage
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to set primary image', [
                'product_id' => $id,
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to set primary image: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/admin/products/{id}/images/{imageId} - Update image details
     * Admin-only endpoint for updating image metadata (alt text, sort order)
     */
    public function updateImage($id, $imageId) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Validate image ID
            if (!is_numeric($imageId) || $imageId <= 0) {
                Response::error('Invalid image ID', 400);
                return;
            }
            
            // Validate request body
            if (empty($this->request['body'])) {
                Response::error('Request body is required', 400);
                return;
            }
            
            $updateData = $this->request['body'];
            
            // Check if product exists
            $product = $this->productModel->getProductById((int)$id);
            if (!$product) {
                Response::notFound('Product not found');
                return;
            }
            
            // Check if image exists and belongs to this product
            $image = $this->productModel->getProductImageById((int)$imageId, (int)$id);
            if (!$image) {
                Response::notFound('Image not found');
                return;
            }
            
            // Validate update data
            $allowedFields = ['alt_text', 'sort_order'];
            $validatedData = [];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $updateData)) {
                    if ($field === 'sort_order') {
                        $validatedData[$field] = max(0, (int)$updateData[$field]);
                    } else {
                        $validatedData[$field] = $updateData[$field];
                    }
                }
            }
            
            if (empty($validatedData)) {
                Response::error('No valid fields to update', 400);
                return;
            }
            
            // Update image
            $updated = $this->productModel->updateProductImage((int)$imageId, (int)$id, $validatedData);
            
            if (!$updated) {
                Response::error('Failed to update image', 500);
                return;
            }
            
            // Get updated image details
            $updatedImage = $this->productModel->getProductImageById((int)$imageId, (int)$id);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Product image updated by admin', [
                'product_id' => $id,
                'image_id' => $imageId,
                'updated_fields' => array_keys($validatedData),
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::success('Image updated successfully', [
                'product_id' => (int)$id,
                'image_id' => (int)$imageId,
                'image' => $updatedImage
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to update product image', [
                'product_id' => $id,
                'image_id' => $imageId,
                'update_data' => $updateData ?? [],
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to update image: ' . $e->getMessage(), 500);
        }
    }
    
    // ==================== ADMIN CATEGORY ENDPOINTS ====================
    
    /**
     * POST /api/admin/categories - Create category
     * Admin-only endpoint for creating categories
     */
    public function createCategory() {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate request body
            if (empty($this->request['body'])) {
                Response::error('Request body is required', 400);
                return;
            }
            
            $categoryData = $this->request['body'];
            
            // Validate required fields
            if (empty($categoryData['name'])) {
                Response::validationError([
                    'name' => 'Category name is required'
                ], 'Validation failed');
                return;
            }
            
            // Create category
            $category = $this->categoryModel->createCategory($categoryData);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Category created by admin', [
                'category_id' => $category['id'],
                'category_name' => $category['name'],
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::created($category, 'Category created successfully');
            
        } catch (Exception $e) {
            Logger::error('Category creation failed', [
                'category_data' => $categoryData ?? [],
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error('Failed to create category: ' . $e->getMessage(), $statusCode);
        }
    }
    
    /**
     * PUT /api/admin/categories/{id} - Update category
     * Admin-only endpoint for updating categories
     */
    public function updateCategory($id) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate category ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid category ID', 400);
                return;
            }
            
            // Validate request body
            if (empty($this->request['body'])) {
                Response::error('Request body is required', 400);
                return;
            }
            
            $updateData = $this->request['body'];
            
            // Update category
            $category = $this->categoryModel->updateCategory((int)$id, $updateData);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Category updated by admin', [
                'category_id' => $id,
                'category_name' => $category['name'],
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email'],
                'updated_fields' => array_keys($updateData)
            ]);
            
            Response::updated($category, 'Category updated successfully');
            
        } catch (Exception $e) {
            Logger::error('Category update failed', [
                'category_id' => $id,
                'update_data' => $updateData ?? [],
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error('Failed to update category: ' . $e->getMessage(), $statusCode);
        }
    }
    
    /**
     * DELETE /api/admin/categories/{id} - Delete category
     * Admin-only endpoint for deleting categories
     */
    public function deleteCategory($id) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate category ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid category ID', 400);
                return;
            }
            
            // Check force delete parameter
            $forceDelete = filter_var($this->request['query']['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // Get category details before deletion for logging
            $category = $this->categoryModel->getCategoryById((int)$id);
            if (!$category) {
                Response::notFound('Category not found');
                return;
            }
            
            // Delete category
            $deleted = $this->categoryModel->deleteCategory((int)$id, $forceDelete);
            
            if (!$deleted) {
                Response::error('Failed to delete category', 500);
                return;
            }
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Category deleted by admin', [
                'category_id' => $id,
                'category_name' => $category['name'],
                'force_delete' => $forceDelete,
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::deleted('Category deleted successfully');
            
        } catch (Exception $e) {
            Logger::error('Category deletion failed', [
                'category_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error('Failed to delete category: ' . $e->getMessage(), $statusCode);
        }
    }
    
    // ==================== ADMIN STATISTICS ENDPOINTS ====================
    
    /**
     * GET /api/admin/products/stats - Product statistics
     * Admin-only endpoint for product statistics
     */
    public function getProductStats() {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Get product statistics
            $stats = $this->productModel->getProductStats();
            
            Logger::info('Product statistics retrieved by admin', [
                'admin_id' => AuthMiddleware::getAuthenticatedUser()['user_id']
            ]);
            
            Response::success('Product statistics retrieved successfully', $stats);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve product statistics', [
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve product statistics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/admin/categories/stats - Category statistics
     * Admin-only endpoint for category statistics
     */
    public function getCategoryStats() {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Get category statistics
            $stats = $this->categoryModel->getCategoryStats();
            
            Logger::info('Category statistics retrieved by admin', [
                'admin_id' => AuthMiddleware::getAuthenticatedUser()['user_id']
            ]);
            
            Response::success('Category statistics retrieved successfully', $stats);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve category statistics', [
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve category statistics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/admin/products/low-stock - Get low stock products
     * Admin-only endpoint for low stock alerts
     */
    public function getLowStockProducts() {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            $threshold = (int)($this->request['query']['threshold'] ?? 10);
            $limit = min((int)($this->request['query']['limit'] ?? 50), 100);
            
            // Get low stock products
            $products = $this->productModel->getLowStockProducts($threshold, $limit);
            
            Logger::info('Low stock products retrieved by admin', [
                'threshold' => $threshold,
                'count' => count($products),
                'admin_id' => AuthMiddleware::getAuthenticatedUser()['user_id']
            ]);
            
            Response::success('Low stock products retrieved successfully', [
                'products' => $products,
                'threshold' => $threshold,
                'count' => count($products)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve low stock products', [
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve low stock products: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/admin/products/{id}/stock - Update product stock
     * Admin-only endpoint for stock management
     */
    public function updateStock($id) {
        try {
            // Ensure admin authentication
            AuthMiddleware::requireAdmin();
            
            // Validate product ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid product ID', 400);
                return;
            }
            
            // Validate request body
            if (empty($this->request['body'])) {
                Response::error('Request body is required', 400);
                return;
            }
            
            $stockData = $this->request['body'];
            
            // Validate required fields
            if (!isset($stockData['quantity'])) {
                Response::validationError([
                    'quantity' => 'Stock quantity is required'
                ], 'Validation failed');
                return;
            }
            
            $quantity = (int)$stockData['quantity'];
            $operation = $stockData['operation'] ?? 'set'; // 'set', 'add', 'subtract'
            
            // Update stock
            $updated = $this->productModel->updateStock((int)$id, $quantity, $operation);
            
            if (!$updated) {
                Response::error('Failed to update stock', 500);
                return;
            }
            
            // Get updated product
            $product = $this->productModel->getProductById((int)$id);
            
            $adminUser = AuthMiddleware::getAuthenticatedUser();
            Logger::info('Product stock updated by admin', [
                'product_id' => $id,
                'operation' => $operation,
                'quantity_change' => $quantity,
                'new_stock' => $product['stock_quantity'],
                'admin_id' => $adminUser['user_id'],
                'admin_email' => $adminUser['email']
            ]);
            
            Response::success('Stock updated successfully', [
                'product_id' => (int)$id,
                'operation' => $operation,
                'quantity_change' => $quantity,
                'new_stock_quantity' => $product['stock_quantity']
            ]);
            
        } catch (Exception $e) {
            Logger::error('Stock update failed', [
                'product_id' => $id,
                'stock_data' => $stockData ?? [],
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error('Failed to update stock: ' . $e->getMessage(), $statusCode);
        }
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Extract and validate filters from query parameters
     */
    private function extractFilters() {
        $filters = [];
        $query = $this->request['query'] ?? [];
        
        // Search filter
        if (!empty($query['search'])) {
            $filters['search'] = trim($query['search']);
        }
        
        // Category filter
        if (!empty($query['category_id']) && is_numeric($query['category_id'])) {
            $filters['category_id'] = (int)$query['category_id'];
        }
        
        // Brand filter
        if (!empty($query['brand'])) {
            $filters['brand'] = trim($query['brand']);
        }
        
        // Price range filters
        if (!empty($query['min_price']) && is_numeric($query['min_price'])) {
            $filters['min_price'] = (float)$query['min_price'];
        }
        
        if (!empty($query['max_price']) && is_numeric($query['max_price'])) {
            $filters['max_price'] = (float)$query['max_price'];
        }
        
        // Stock filter
        if (isset($query['in_stock'])) {
            $filters['in_stock'] = filter_var($query['in_stock'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Sort filter
        if (!empty($query['sort'])) {
            $validSorts = [
                'name_asc', 'name_desc',
                'price_asc', 'price_desc',
                'created_asc', 'created_desc',
                'stock_asc', 'stock_desc',
                'brand_asc', 'brand_desc'
            ];
            
            if (in_array($query['sort'], $validSorts)) {
                $filters['sort'] = $query['sort'];
            }
        }
        
        return $filters;
    }
    
    /**
     * Validate product data
     */
    private function validateProductData($data, $isUpdate = false) {
        $errors = [];
        
        // Name validation
        if (!$isUpdate && empty($data['name'])) {
            $errors['name'] = 'Product name is required';
        } elseif (!empty($data['name']) && strlen(trim($data['name'])) < 2) {
            $errors['name'] = 'Product name must be at least 2 characters';
        } elseif (!empty($data['name']) && strlen(trim($data['name'])) > 255) {
            $errors['name'] = 'Product name is too long (maximum 255 characters)';
        }
        
        // Price validation
        if (!$isUpdate && !isset($data['price'])) {
            $errors['price'] = 'Product price is required';
        } elseif (isset($data['price'])) {
            $price = (float)$data['price'];
            if ($price < 0) {
                $errors['price'] = 'Product price cannot be negative';
            } elseif ($price > 999999.99) {
                $errors['price'] = 'Product price is too high (maximum 999999.99)';
            }
        }
        
        // Stock validation
        if (isset($data['stock_quantity'])) {
            $stock = (int)$data['stock_quantity'];
            if ($stock < 0) {
                $errors['stock_quantity'] = 'Stock quantity cannot be negative';
            } elseif ($stock > 999999) {
                $errors['stock_quantity'] = 'Stock quantity is too high (maximum 999999)';
            }
        }
        
        // Category validation
        if (!empty($data['category_id'])) {
            $categoryId = (int)$data['category_id'];
            if (!$this->categoryModel->getCategoryById($categoryId)) {
                $errors['category_id'] = 'Invalid category specified';
            }
        }
        
        // SKU validation
        if (!empty($data['sku'])) {
            $sku = trim($data['sku']);
            if (strlen($sku) > 50) {
                $errors['sku'] = 'SKU is too long (maximum 50 characters)';
            } elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
                $errors['sku'] = 'SKU can only contain letters, numbers, hyphens, and underscores';
            }
        }
        
        return $errors;
    }
}