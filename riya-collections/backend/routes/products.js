const express = require('express');
const { body } = require('express-validator');
const router = express.Router();
const { secureExecuteQuery, secureExecuteTransaction, queryBuilders } = require('../middleware/database-security');
const { authenticateAdmin, optionalAuth } = require('../middleware/auth');
const { validationMiddleware, validationRules, createValidationMiddleware } = require('../middleware/validation');
const { 
  uploadSingleImage, 
  uploadMultipleImages, 
  cleanupOnError, 
  deleteImageFiles, 
  getImageUrl 
} = require('../middleware/image-upload');

/**
 * Product CRUD Operations for Riya Collections
 * 
 * This module implements:
 * 1. Product creation, reading, updating, and deletion
 * 2. Category management functionality
 * 3. Stock quantity management with validation
 * 
 * Requirements: 7.1, 7.3, 7.4
 */

// ==================== PUBLIC PRODUCT ENDPOINTS ====================

/**
 * GET /api/products
 * Get all products with filtering, sorting, and pagination
 * Public endpoint - no authentication required
 */
router.get('/', validationMiddleware.productListing, async (req, res) => {
  try {
    const {
      search = '',
      category,
      minPrice,
      maxPrice,
      sortBy = 'created_at',
      sortOrder = 'desc',
      page = 1,
      limit = 20
    } = req.query;

    // Calculate offset for pagination
    const offset = (page - 1) * limit;

    // Build base query
    let whereConditions = ['p.is_active = ?'];
    let queryParams = [true];

    // Add search condition
    if (search) {
      whereConditions.push('(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)');
      const searchTerm = `%${search}%`;
      queryParams.push(searchTerm, searchTerm, searchTerm);
    }

    // Add category filter
    if (category) {
      // Validate that the category exists
      const categoryExists = await secureExecuteQuery(
        'SELECT id FROM categories WHERE id = ? AND is_active = true',
        [category]
      );

      if (categoryExists.length === 0) {
        return res.status(400).json({
          success: false,
          message: 'Invalid category ID'
        });
      }

      whereConditions.push('p.category_id = ?');
      queryParams.push(category);
    }

    // Add price range filters
    if (minPrice !== undefined) {
      whereConditions.push('p.price >= ?');
      queryParams.push(minPrice);
    }

    if (maxPrice !== undefined) {
      whereConditions.push('p.price <= ?');
      queryParams.push(maxPrice);
    }

    // Validate sort column
    const allowedSortColumns = ['name', 'price', 'created_at', 'stock_quantity', 'brand'];
    const sortColumn = allowedSortColumns.includes(sortBy) ? sortBy : 'created_at';
    const sortDirection = sortOrder.toLowerCase() === 'asc' ? 'ASC' : 'DESC';

    // Build the main query
    const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';
    
    const query = `
      SELECT 
        p.id,
        p.name,
        p.description,
        p.price,
        p.stock_quantity,
        p.brand,
        p.sku,
        p.created_at,
        p.updated_at,
        c.id as category_id,
        c.name as category_name,
        pi.image_url as primary_image
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
      ${whereClause}
      ORDER BY p.${sortColumn} ${sortDirection}
      LIMIT ? OFFSET ?
    `;

    queryParams.push(limit, offset);

    // Execute main query
    const products = await secureExecuteQuery(query, queryParams);

    // Get total count for pagination
    const countQuery = `
      SELECT COUNT(*) as total
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      ${whereClause}
    `;

    const countParams = queryParams.slice(0, -2); // Remove limit and offset
    const [countResult] = await secureExecuteQuery(countQuery, countParams);
    const total = countResult.total;

    // Calculate pagination info
    const totalPages = Math.ceil(total / limit);
    const hasNextPage = page < totalPages;
    const hasPrevPage = page > 1;

    res.json({
      success: true,
      message: 'Products retrieved successfully',
      data: {
        products: products.map(product => ({
          id: product.id,
          name: product.name,
          description: product.description,
          price: parseFloat(product.price),
          stockQuantity: product.stock_quantity,
          brand: product.brand,
          sku: product.sku,
          category: product.category_id ? {
            id: product.category_id,
            name: product.category_name
          } : null,
          primaryImage: product.primary_image,
          createdAt: product.created_at,
          updatedAt: product.updated_at
        })),
        pagination: {
          currentPage: page,
          totalPages,
          totalItems: total,
          itemsPerPage: limit,
          hasNextPage,
          hasPrevPage
        },
        filters: {
          search,
          category,
          minPrice,
          maxPrice,
          sortBy: sortColumn,
          sortOrder: sortDirection.toLowerCase()
        }
      }
    });

  } catch (error) {
    console.error('Error fetching products:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch products'
    });
  }
});

/**
 * GET /api/products/:id
 * Get a single product by ID with all images and related products
 * Public endpoint - no authentication required
 * Requirements: 2.6
 */
router.get('/:id', validationMiddleware.validateId, async (req, res) => {
  try {
    const productId = req.params.id;
    const { includeRelated = 'true', relatedLimit = 4 } = req.query;

    // Get product details
    const productQuery = `
      SELECT 
        p.id,
        p.name,
        p.description,
        p.price,
        p.stock_quantity,
        p.brand,
        p.sku,
        p.is_active,
        p.created_at,
        p.updated_at,
        c.id as category_id,
        c.name as category_name,
        c.description as category_description
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      WHERE p.id = ? AND p.is_active = true
    `;

    const products = await secureExecuteQuery(productQuery, [productId]);

    if (products.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Product not found'
      });
    }

    const product = products[0];

    // Get all product images
    const imagesQuery = `
      SELECT id, image_url, alt_text, is_primary, sort_order
      FROM product_images
      WHERE product_id = ?
      ORDER BY is_primary DESC, sort_order ASC
    `;

    const images = await secureExecuteQuery(imagesQuery, [productId]);

    // Get related products if requested
    let relatedProducts = [];
    if (includeRelated === 'true' && product.category_id) {
      const relatedQuery = `
        SELECT 
          p.id,
          p.name,
          p.price,
          p.stock_quantity,
          p.brand,
          pi.image_url as primary_image
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
        WHERE p.category_id = ? 
          AND p.id != ? 
          AND p.is_active = true
          AND p.stock_quantity > 0
        ORDER BY p.created_at DESC
        LIMIT ?
      `;

      const related = await secureExecuteQuery(relatedQuery, [
        product.category_id, 
        productId, 
        parseInt(relatedLimit)
      ]);

      relatedProducts = related.map(item => ({
        id: item.id,
        name: item.name,
        price: parseFloat(item.price),
        stockQuantity: item.stock_quantity,
        brand: item.brand,
        primaryImage: item.primary_image
      }));
    }

    // Determine stock status
    const stockStatus = product.stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
    const stockLevel = product.stock_quantity > 10 ? 'high' : 
                      product.stock_quantity > 5 ? 'medium' : 
                      product.stock_quantity > 0 ? 'low' : 'out';

    res.json({
      success: true,
      message: 'Product retrieved successfully',
      data: {
        product: {
          id: product.id,
          name: product.name,
          description: product.description,
          price: parseFloat(product.price),
          stockQuantity: product.stock_quantity,
          stockStatus,
          stockLevel,
          brand: product.brand,
          sku: product.sku,
          category: product.category_id ? {
            id: product.category_id,
            name: product.category_name,
            description: product.category_description
          } : null,
          images: images.map(img => ({
            id: img.id,
            url: img.image_url,
            altText: img.alt_text,
            isPrimary: img.is_primary,
            sortOrder: img.sort_order
          })),
          relatedProducts,
          createdAt: product.created_at,
          updatedAt: product.updated_at
        }
      }
    });

  } catch (error) {
    console.error('Error fetching product:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch product'
    });
  }
});

// ==================== ADMIN PRODUCT ENDPOINTS ====================

/**
 * POST /api/products
 * Create a new product (Admin only)
 */
router.post('/', authenticateAdmin, validationMiddleware.productCreation, async (req, res) => {
  try {
    const {
      name,
      description,
      price,
      stock_quantity,
      category_id,
      brand,
      sku
    } = req.body;

    // Check if category exists (if provided)
    if (category_id) {
      const categoryExists = await secureExecuteQuery(
        'SELECT id FROM categories WHERE id = ? AND is_active = true',
        [category_id]
      );

      if (categoryExists.length === 0) {
        return res.status(400).json({
          success: false,
          message: 'Invalid category ID'
        });
      }
    }

    // Check if SKU already exists (if provided)
    if (sku) {
      const skuExists = await secureExecuteQuery(
        'SELECT id FROM products WHERE sku = ?',
        [sku]
      );

      if (skuExists.length > 0) {
        return res.status(400).json({
          success: false,
          message: 'SKU already exists'
        });
      }
    }

    // Insert the product
    const insertQuery = `
      INSERT INTO products (name, description, price, stock_quantity, category_id, brand, sku)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `;

    const result = await secureExecuteQuery(insertQuery, [
      name,
      description || null,
      price,
      stock_quantity,
      category_id || null,
      brand || null,
      sku || null
    ]);

    const productId = result.insertId;

    // Get the created product with category info
    const createdProduct = await secureExecuteQuery(`
      SELECT 
        p.id,
        p.name,
        p.description,
        p.price,
        p.stock_quantity,
        p.brand,
        p.sku,
        p.is_active,
        p.created_at,
        p.updated_at,
        c.id as category_id,
        c.name as category_name
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      WHERE p.id = ?
    `, [productId]);

    const product = createdProduct[0];

    res.status(201).json({
      success: true,
      message: 'Product created successfully',
      data: {
        product: {
          id: product.id,
          name: product.name,
          description: product.description,
          price: parseFloat(product.price),
          stockQuantity: product.stock_quantity,
          brand: product.brand,
          sku: product.sku,
          isActive: product.is_active,
          category: product.category_id ? {
            id: product.category_id,
            name: product.category_name
          } : null,
          createdAt: product.created_at,
          updatedAt: product.updated_at
        }
      }
    });

  } catch (error) {
    console.error('Error creating product:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to create product'
    });
  }
});

/**
 * PUT /api/products/:id
 * Update a product (Admin only)
 */
router.put('/:id', authenticateAdmin, validationMiddleware.productUpdate, async (req, res) => {
  try {
    const productId = req.params.id;
    const {
      name,
      description,
      price,
      stock_quantity,
      category_id,
      brand,
      sku,
      is_active
    } = req.body;

    // Check if product exists
    const existingProduct = await secureExecuteQuery(
      'SELECT id, sku FROM products WHERE id = ?',
      [productId]
    );

    if (existingProduct.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Product not found'
      });
    }

    // Check if category exists (if provided)
    if (category_id) {
      const categoryExists = await secureExecuteQuery(
        'SELECT id FROM categories WHERE id = ? AND is_active = true',
        [category_id]
      );

      if (categoryExists.length === 0) {
        return res.status(400).json({
          success: false,
          message: 'Invalid category ID'
        });
      }
    }

    // Check if SKU already exists for another product (if provided and changed)
    if (sku && sku !== existingProduct[0].sku) {
      const skuExists = await secureExecuteQuery(
        'SELECT id FROM products WHERE sku = ? AND id != ?',
        [sku, productId]
      );

      if (skuExists.length > 0) {
        return res.status(400).json({
          success: false,
          message: 'SKU already exists'
        });
      }
    }

    // Build update query dynamically based on provided fields
    const updateFields = [];
    const updateParams = [];

    if (name !== undefined) {
      updateFields.push('name = ?');
      updateParams.push(name);
    }

    if (description !== undefined) {
      updateFields.push('description = ?');
      updateParams.push(description);
    }

    if (price !== undefined) {
      updateFields.push('price = ?');
      updateParams.push(price);
    }

    if (stock_quantity !== undefined) {
      updateFields.push('stock_quantity = ?');
      updateParams.push(stock_quantity);
    }

    if (category_id !== undefined) {
      updateFields.push('category_id = ?');
      updateParams.push(category_id);
    }

    if (brand !== undefined) {
      updateFields.push('brand = ?');
      updateParams.push(brand);
    }

    if (sku !== undefined) {
      updateFields.push('sku = ?');
      updateParams.push(sku);
    }

    if (is_active !== undefined) {
      updateFields.push('is_active = ?');
      updateParams.push(is_active);
    }

    if (updateFields.length === 0) {
      return res.status(400).json({
        success: false,
        message: 'No fields to update'
      });
    }

    // Add updated_at timestamp
    updateFields.push('updated_at = CURRENT_TIMESTAMP');
    updateParams.push(productId);

    const updateQuery = `
      UPDATE products 
      SET ${updateFields.join(', ')}
      WHERE id = ?
    `;

    await secureExecuteQuery(updateQuery, updateParams);

    // Get the updated product with category info
    const updatedProduct = await secureExecuteQuery(`
      SELECT 
        p.id,
        p.name,
        p.description,
        p.price,
        p.stock_quantity,
        p.brand,
        p.sku,
        p.is_active,
        p.created_at,
        p.updated_at,
        c.id as category_id,
        c.name as category_name
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      WHERE p.id = ?
    `, [productId]);

    const product = updatedProduct[0];

    res.json({
      success: true,
      message: 'Product updated successfully',
      data: {
        product: {
          id: product.id,
          name: product.name,
          description: product.description,
          price: parseFloat(product.price),
          stockQuantity: product.stock_quantity,
          brand: product.brand,
          sku: product.sku,
          isActive: product.is_active,
          category: product.category_id ? {
            id: product.category_id,
            name: product.category_name
          } : null,
          createdAt: product.created_at,
          updatedAt: product.updated_at
        }
      }
    });

  } catch (error) {
    console.error('Error updating product:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to update product'
    });
  }
});

/**
 * DELETE /api/products/:id
 * Delete a product (Admin only)
 * Soft delete - sets is_active to false
 */
router.delete('/:id', authenticateAdmin, validationMiddleware.validateId, async (req, res) => {
  try {
    const productId = req.params.id;

    // Check if product exists
    const existingProduct = await secureExecuteQuery(
      'SELECT id, name, is_active FROM products WHERE id = ?',
      [productId]
    );

    if (existingProduct.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Product not found'
      });
    }

    if (!existingProduct[0].is_active) {
      return res.status(400).json({
        success: false,
        message: 'Product is already deleted'
      });
    }

    // Soft delete the product
    await secureExecuteQuery(
      'UPDATE products SET is_active = false, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
      [productId]
    );

    res.json({
      success: true,
      message: 'Product deleted successfully',
      data: {
        productId: parseInt(productId),
        productName: existingProduct[0].name
      }
    });

  } catch (error) {
    console.error('Error deleting product:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to delete product'
    });
  }
});

/**
 * PATCH /api/products/:id/stock
 * Update product stock quantity (Admin only)
 */
router.patch('/:id/stock', authenticateAdmin, validationMiddleware.validateId, validationRules.stockQuantity(), (req, res, next) => {
  const errors = require('express-validator').validationResult(req);
  if (!errors.isEmpty()) {
    return res.status(400).json({
      success: false,
      message: 'Validation failed',
      errors: errors.array()
    });
  }
  next();
}, async (req, res) => {
  try {
    const productId = req.params.id;
    const { stock_quantity } = req.body;

    // Check if product exists and is active
    const existingProduct = await secureExecuteQuery(
      'SELECT id, name, stock_quantity FROM products WHERE id = ? AND is_active = true',
      [productId]
    );

    if (existingProduct.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Product not found'
      });
    }

    const oldStock = existingProduct[0].stock_quantity;

    // Update stock quantity
    await secureExecuteQuery(
      'UPDATE products SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
      [stock_quantity, productId]
    );

    res.json({
      success: true,
      message: 'Stock quantity updated successfully',
      data: {
        productId: parseInt(productId),
        productName: existingProduct[0].name,
        oldStock,
        newStock: stock_quantity,
        stockChange: stock_quantity - oldStock
      }
    });

  } catch (error) {
    console.error('Error updating stock:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to update stock quantity'
    });
  }
});

// ==================== CATEGORY MANAGEMENT ENDPOINTS ====================

/**
 * GET /api/products/categories
 * Get all active categories
 * Public endpoint - no authentication required
 */
router.get('/categories/all', async (req, res) => {
  try {
    const categories = await secureExecuteQuery(`
      SELECT 
        c.id,
        c.name,
        c.description,
        c.image_url,
        c.created_at,
        COUNT(p.id) as product_count
      FROM categories c
      LEFT JOIN products p ON c.id = p.category_id AND p.is_active = true
      WHERE c.is_active = true
      GROUP BY c.id, c.name, c.description, c.image_url, c.created_at
      ORDER BY c.name ASC
    `);

    res.json({
      success: true,
      message: 'Categories retrieved successfully',
      data: {
        categories: categories.map(category => ({
          id: category.id,
          name: category.name,
          description: category.description,
          imageUrl: category.image_url,
          productCount: category.product_count,
          createdAt: category.created_at
        }))
      }
    });

  } catch (error) {
    console.error('Error fetching categories:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch categories'
    });
  }
});

/**
 * POST /api/products/categories
 * Create a new category (Admin only)
 */
router.post('/categories', authenticateAdmin, validationRules.name('name'), validationRules.description(), (req, res, next) => {
  const errors = require('express-validator').validationResult(req);
  if (!errors.isEmpty()) {
    return res.status(400).json({
      success: false,
      message: 'Validation failed',
      errors: errors.array()
    });
  }
  next();
}, async (req, res) => {
  try {
    const { name, description, image_url } = req.body;

    // Check if category name already exists
    const existingCategory = await secureExecuteQuery(
      'SELECT id FROM categories WHERE name = ?',
      [name]
    );

    if (existingCategory.length > 0) {
      return res.status(400).json({
        success: false,
        message: 'Category name already exists'
      });
    }

    // Insert the category
    const result = await secureExecuteQuery(
      'INSERT INTO categories (name, description, image_url) VALUES (?, ?, ?)',
      [name, description || null, image_url || null]
    );

    const categoryId = result.insertId;

    // Get the created category
    const createdCategory = await secureExecuteQuery(
      'SELECT id, name, description, image_url, is_active, created_at FROM categories WHERE id = ?',
      [categoryId]
    );

    const category = createdCategory[0];

    res.status(201).json({
      success: true,
      message: 'Category created successfully',
      data: {
        category: {
          id: category.id,
          name: category.name,
          description: category.description,
          imageUrl: category.image_url,
          isActive: category.is_active,
          createdAt: category.created_at
        }
      }
    });

  } catch (error) {
    console.error('Error creating category:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to create category'
    });
  }
});

/**
 * PUT /api/products/categories/:id
 * Update a category (Admin only)
 */
router.put('/categories/:id', authenticateAdmin, validationMiddleware.validateId, validationRules.name('name').optional(), validationRules.description(), (req, res, next) => {
  const errors = require('express-validator').validationResult(req);
  if (!errors.isEmpty()) {
    return res.status(400).json({
      success: false,
      message: 'Validation failed',
      errors: errors.array()
    });
  }
  next();
}, async (req, res) => {
  try {
    const categoryId = req.params.id;
    const { name, description, image_url, is_active } = req.body;

    // Check if category exists
    const existingCategory = await secureExecuteQuery(
      'SELECT id, name FROM categories WHERE id = ?',
      [categoryId]
    );

    if (existingCategory.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Category not found'
      });
    }

    // Check if name already exists for another category (if name is being updated)
    if (name && name !== existingCategory[0].name) {
      const nameExists = await secureExecuteQuery(
        'SELECT id FROM categories WHERE name = ? AND id != ?',
        [name, categoryId]
      );

      if (nameExists.length > 0) {
        return res.status(400).json({
          success: false,
          message: 'Category name already exists'
        });
      }
    }

    // Build update query dynamically
    const updateFields = [];
    const updateParams = [];

    if (name !== undefined) {
      updateFields.push('name = ?');
      updateParams.push(name);
    }

    if (description !== undefined) {
      updateFields.push('description = ?');
      updateParams.push(description);
    }

    if (image_url !== undefined) {
      updateFields.push('image_url = ?');
      updateParams.push(image_url);
    }

    if (is_active !== undefined) {
      updateFields.push('is_active = ?');
      updateParams.push(is_active);
    }

    if (updateFields.length === 0) {
      return res.status(400).json({
        success: false,
        message: 'No fields to update'
      });
    }

    updateParams.push(categoryId);

    const updateQuery = `
      UPDATE categories 
      SET ${updateFields.join(', ')}
      WHERE id = ?
    `;

    await secureExecuteQuery(updateQuery, updateParams);

    // Get the updated category
    const updatedCategory = await secureExecuteQuery(
      'SELECT id, name, description, image_url, is_active, created_at FROM categories WHERE id = ?',
      [categoryId]
    );

    const category = updatedCategory[0];

    res.json({
      success: true,
      message: 'Category updated successfully',
      data: {
        category: {
          id: category.id,
          name: category.name,
          description: category.description,
          imageUrl: category.image_url,
          isActive: category.is_active,
          createdAt: category.created_at
        }
      }
    });

  } catch (error) {
    console.error('Error updating category:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to update category'
    });
  }
});

/**
 * DELETE /api/products/categories/:id
 * Delete a category (Admin only)
 * Soft delete - sets is_active to false
 */
router.delete('/categories/:id', authenticateAdmin, validationMiddleware.validateId, async (req, res) => {
  try {
    const categoryId = req.params.id;

    // Check if category exists
    const existingCategory = await secureExecuteQuery(
      'SELECT id, name, is_active FROM categories WHERE id = ?',
      [categoryId]
    );

    if (existingCategory.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Category not found'
      });
    }

    if (!existingCategory[0].is_active) {
      return res.status(400).json({
        success: false,
        message: 'Category is already deleted'
      });
    }

    // Check if category has active products
    const productsInCategory = await secureExecuteQuery(
      'SELECT COUNT(*) as count FROM products WHERE category_id = ? AND is_active = true',
      [categoryId]
    );

    if (productsInCategory[0].count > 0) {
      return res.status(400).json({
        success: false,
        message: `Cannot delete category. It has ${productsInCategory[0].count} active products. Please move or delete the products first.`
      });
    }

    // Soft delete the category
    await secureExecuteQuery(
      'UPDATE categories SET is_active = false WHERE id = ?',
      [categoryId]
    );

    res.json({
      success: true,
      message: 'Category deleted successfully',
      data: {
        categoryId: parseInt(categoryId),
        categoryName: existingCategory[0].name
      }
    });

  } catch (error) {
    console.error('Error deleting category:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to delete category'
    });
  }
});

// ==================== IMAGE UPLOAD ENDPOINTS ====================

/**
 * POST /api/products/:id/images
 * Upload images for a product (Admin only)
 * Supports both single and multiple image uploads
 */
router.post('/:id/images', 
  authenticateAdmin, 
  validationMiddleware.validateId,
  cleanupOnError,
  uploadMultipleImages('images', 10),
  async (req, res) => {
    try {
      const productId = req.params.id;
      const { alt_text, is_primary } = req.body;

      // Check if product exists
      const existingProduct = await secureExecuteQuery(
        'SELECT id, name FROM products WHERE id = ? AND is_active = true',
        [productId]
      );

      if (existingProduct.length === 0) {
        return res.status(404).json({
          success: false,
          message: 'Product not found'
        });
      }

      // Check if any images were uploaded
      if (!req.processedImages || req.processedImages.length === 0) {
        return res.status(400).json({
          success: false,
          message: 'No images uploaded'
        });
      }

      const uploadedImages = [];

      // Use transaction to ensure data consistency
      await secureExecuteTransaction(async (connection) => {
        // If this is set as primary, unset other primary images
        if (is_primary === 'true' || is_primary === true) {
          await connection.execute(
            'UPDATE product_images SET is_primary = false WHERE product_id = ?',
            [productId]
          );
        }

        // Insert each uploaded image
        for (let i = 0; i < req.processedImages.length; i++) {
          const image = req.processedImages[i];
          const isPrimary = (is_primary === 'true' || is_primary === true) && i === 0;
          const imageAltText = Array.isArray(alt_text) ? alt_text[i] : alt_text;

          const insertResult = await connection.execute(
            `INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
             VALUES (?, ?, ?, ?, ?)`,
            [
              productId,
              image.url,
              imageAltText || `${existingProduct[0].name} image`,
              isPrimary,
              i
            ]
          );

          uploadedImages.push({
            id: insertResult[0].insertId,
            url: image.url,
            thumbnails: image.thumbnails,
            altText: imageAltText || `${existingProduct[0].name} image`,
            isPrimary: isPrimary,
            sortOrder: i,
            filename: image.filename,
            originalName: image.originalName,
            size: image.size
          });
        }
      });

      res.status(201).json({
        success: true,
        message: `${uploadedImages.length} image(s) uploaded successfully`,
        data: {
          productId: parseInt(productId),
          productName: existingProduct[0].name,
          images: uploadedImages
        }
      });

    } catch (error) {
      console.error('Error uploading product images:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to upload product images'
      });
    }
  }
);

/**
 * PUT /api/products/:productId/images/:imageId
 * Update product image metadata (Admin only)
 */
router.put('/:productId/images/:imageId',
  authenticateAdmin,
  validationMiddleware.validateId,
  createValidationMiddleware([
    validationRules.idParam('imageId'),
    body('alt_text').optional().trim().isLength({ max: 255 }).withMessage('Alt text must not exceed 255 characters'),
    body('is_primary').optional().isBoolean().withMessage('is_primary must be a boolean'),
    body('sort_order').optional().isInt({ min: 0 }).withMessage('Sort order must be a non-negative integer')
  ]),
  async (req, res) => {
    try {
      const productId = req.params.productId;
      const imageId = req.params.imageId;
      const { alt_text, is_primary, sort_order } = req.body;

      // Check if product and image exist
      const existingImage = await secureExecuteQuery(
        `SELECT pi.id, pi.image_url, pi.alt_text, pi.is_primary, pi.sort_order, p.name as product_name
         FROM product_images pi
         JOIN products p ON pi.product_id = p.id
         WHERE pi.id = ? AND pi.product_id = ? AND p.is_active = true`,
        [imageId, productId]
      );

      if (existingImage.length === 0) {
        return res.status(404).json({
          success: false,
          message: 'Product image not found'
        });
      }

      // Use transaction for consistency
      await secureExecuteTransaction(async (connection) => {
        // If setting as primary, unset other primary images
        if (is_primary === true) {
          await connection.execute(
            'UPDATE product_images SET is_primary = false WHERE product_id = ? AND id != ?',
            [productId, imageId]
          );
        }

        // Build update query dynamically
        const updateFields = [];
        const updateParams = [];

        if (alt_text !== undefined) {
          updateFields.push('alt_text = ?');
          updateParams.push(alt_text);
        }

        if (is_primary !== undefined) {
          updateFields.push('is_primary = ?');
          updateParams.push(is_primary);
        }

        if (sort_order !== undefined) {
          updateFields.push('sort_order = ?');
          updateParams.push(sort_order);
        }

        if (updateFields.length === 0) {
          throw new Error('No fields to update');
        }

        updateParams.push(imageId);

        const updateQuery = `
          UPDATE product_images 
          SET ${updateFields.join(', ')}
          WHERE id = ?
        `;

        await connection.execute(updateQuery, updateParams);
      });

      // Get updated image data
      const updatedImage = await secureExecuteQuery(
        `SELECT pi.id, pi.image_url, pi.alt_text, pi.is_primary, pi.sort_order, p.name as product_name
         FROM product_images pi
         JOIN products p ON pi.product_id = p.id
         WHERE pi.id = ?`,
        [imageId]
      );

      const image = updatedImage[0];

      res.json({
        success: true,
        message: 'Product image updated successfully',
        data: {
          image: {
            id: image.id,
            url: image.image_url,
            altText: image.alt_text,
            isPrimary: image.is_primary,
            sortOrder: image.sort_order,
            productName: image.product_name
          }
        }
      });

    } catch (error) {
      console.error('Error updating product image:', error);
      res.status(500).json({
        success: false,
        message: error.message === 'No fields to update' ? error.message : 'Failed to update product image'
      });
    }
  }
);

/**
 * DELETE /api/products/:productId/images/:imageId
 * Delete a product image (Admin only)
 */
router.delete('/:productId/images/:imageId',
  authenticateAdmin,
  validationMiddleware.validateId,
  createValidationMiddleware([
    validationRules.idParam('imageId')
  ]),
  async (req, res) => {
    try {
      const productId = req.params.productId;
      const imageId = req.params.imageId;

      // Check if product and image exist
      const existingImage = await secureExecuteQuery(
        `SELECT pi.id, pi.image_url, pi.alt_text, pi.is_primary, p.name as product_name
         FROM product_images pi
         JOIN products p ON pi.product_id = p.id
         WHERE pi.id = ? AND pi.product_id = ? AND p.is_active = true`,
        [imageId, productId]
      );

      if (existingImage.length === 0) {
        return res.status(404).json({
          success: false,
          message: 'Product image not found'
        });
      }

      const image = existingImage[0];

      // Delete from database
      await secureExecuteQuery(
        'DELETE FROM product_images WHERE id = ?',
        [imageId]
      );

      // Extract filename from URL and delete physical files
      const filename = image.image_url.split('/').pop();
      if (filename) {
        await deleteImageFiles(filename);
      }

      res.json({
        success: true,
        message: 'Product image deleted successfully',
        data: {
          imageId: parseInt(imageId),
          productId: parseInt(productId),
          productName: image.product_name,
          deletedImageUrl: image.image_url
        }
      });

    } catch (error) {
      console.error('Error deleting product image:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to delete product image'
      });
    }
  }
);

/**
 * GET /api/products/:id/images
 * Get all images for a product
 * Public endpoint - no authentication required
 */
router.get('/:id/images', validationMiddleware.validateId, async (req, res) => {
  try {
    const productId = req.params.id;

    // Check if product exists
    const existingProduct = await secureExecuteQuery(
      'SELECT id, name FROM products WHERE id = ? AND is_active = true',
      [productId]
    );

    if (existingProduct.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Product not found'
      });
    }

    // Get all images for the product
    const images = await secureExecuteQuery(
      `SELECT id, image_url, alt_text, is_primary, sort_order
       FROM product_images
       WHERE product_id = ?
       ORDER BY is_primary DESC, sort_order ASC`,
      [productId]
    );

    // Format images with thumbnail URLs
    const formattedImages = images.map(image => {
      const filename = image.image_url.split('/').pop();
      return {
        id: image.id,
        url: image.image_url,
        altText: image.alt_text,
        isPrimary: image.is_primary,
        sortOrder: image.sort_order,
        thumbnails: {
          small: getImageUrl(filename, 'small'),
          medium: getImageUrl(filename, 'medium'),
          large: getImageUrl(filename, 'large')
        }
      };
    });

    res.json({
      success: true,
      message: 'Product images retrieved successfully',
      data: {
        productId: parseInt(productId),
        productName: existingProduct[0].name,
        images: formattedImages,
        totalImages: formattedImages.length
      }
    });

  } catch (error) {
    console.error('Error fetching product images:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch product images'
    });
  }
});

module.exports = router;