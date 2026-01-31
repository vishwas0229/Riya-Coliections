/**
 * Query Optimization Middleware
 * 
 * This module provides query optimization features including:
 * - Automatic query caching
 * - Query performance monitoring
 * - Slow query detection
 * - Query result optimization
 * 
 * Requirements: 10.3, 10.4, 11.4
 */

const { cacheManager, CacheKeys } = require('../utils/cache-manager');
const { appLogger } = require('../config/logging');
const { secureExecuteQuery } = require('./database-security');

/**
 * Query performance monitoring
 */
class QueryPerformanceMonitor {
  constructor() {
    this.queryStats = new Map();
    this.slowQueries = [];
    this.slowQueryThreshold = 1000; // 1 second
  }

  /**
   * Record query execution
   */
  recordQuery(query, duration, params = []) {
    const queryHash = this.hashQuery(query);
    
    if (!this.queryStats.has(queryHash)) {
      this.queryStats.set(queryHash, {
        query: query.substring(0, 200) + (query.length > 200 ? '...' : ''),
        count: 0,
        totalDuration: 0,
        avgDuration: 0,
        minDuration: Infinity,
        maxDuration: 0,
        lastExecuted: null
      });
    }

    const stats = this.queryStats.get(queryHash);
    stats.count++;
    stats.totalDuration += duration;
    stats.avgDuration = stats.totalDuration / stats.count;
    stats.minDuration = Math.min(stats.minDuration, duration);
    stats.maxDuration = Math.max(stats.maxDuration, duration);
    stats.lastExecuted = new Date();

    // Track slow queries
    if (duration > this.slowQueryThreshold) {
      this.slowQueries.push({
        query: query.substring(0, 500),
        duration,
        params: params.slice(0, 10), // Limit params for logging
        timestamp: new Date(),
        hash: queryHash
      });

      // Keep only last 100 slow queries
      if (this.slowQueries.length > 100) {
        this.slowQueries = this.slowQueries.slice(-100);
      }

      appLogger.warn('Slow query detected', {
        query: query.substring(0, 200),
        duration,
        params: params.slice(0, 5)
      });
    }
  }

  /**
   * Hash query for statistics
   */
  hashQuery(query) {
    // Normalize query by removing parameters and whitespace
    const normalized = query
      .replace(/\?/g, 'PARAM')
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase();
    
    // Simple hash function
    let hash = 0;
    for (let i = 0; i < normalized.length; i++) {
      const char = normalized.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return hash.toString();
  }

  /**
   * Get query statistics
   */
  getStats() {
    const stats = Array.from(this.queryStats.values())
      .sort((a, b) => b.avgDuration - a.avgDuration)
      .slice(0, 20); // Top 20 slowest queries

    return {
      totalQueries: Array.from(this.queryStats.values()).reduce((sum, stat) => sum + stat.count, 0),
      uniqueQueries: this.queryStats.size,
      slowQueries: this.slowQueries.slice(-10), // Last 10 slow queries
      topSlowQueries: stats,
      averageQueryTime: stats.length > 0 ? 
        stats.reduce((sum, stat) => sum + stat.avgDuration, 0) / stats.length : 0
    };
  }

  /**
   * Reset statistics
   */
  reset() {
    this.queryStats.clear();
    this.slowQueries = [];
  }
}

const queryMonitor = new QueryPerformanceMonitor();

/**
 * Optimized query executor with caching
 */
class OptimizedQueryExecutor {
  constructor() {
    this.cacheableQueries = new Set([
      'SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'
    ]);
  }

  /**
   * Execute query with optimization
   */
  async executeOptimized(query, params = [], options = {}) {
    const startTime = Date.now();
    const {
      cache = true,
      cacheTTL = 300,
      cacheKey = null,
      skipCache = false
    } = options;

    try {
      // Check if query is cacheable
      const isCacheable = cache && !skipCache && this.isCacheableQuery(query);
      const finalCacheKey = cacheKey || this.generateCacheKey(query, params);

      // Try cache first for cacheable queries
      if (isCacheable) {
        const cached = await cacheManager.get(finalCacheKey);
        if (cached !== null) {
          const duration = Date.now() - startTime;
          queryMonitor.recordQuery(query, duration, params);
          appLogger.debug('Query served from cache', { 
            cacheKey: finalCacheKey, 
            duration 
          });
          return cached;
        }
      }

      // Execute query
      const result = await secureExecuteQuery(query, params);
      const duration = Date.now() - startTime;

      // Record performance
      queryMonitor.recordQuery(query, duration, params);

      // Cache result if cacheable
      if (isCacheable && result) {
        await cacheManager.set(finalCacheKey, result, cacheTTL);
        appLogger.debug('Query result cached', { 
          cacheKey: finalCacheKey, 
          duration,
          resultSize: Array.isArray(result) ? result.length : 1
        });
      }

      return result;

    } catch (error) {
      const duration = Date.now() - startTime;
      queryMonitor.recordQuery(query, duration, params);
      
      appLogger.error('Optimized query execution failed', {
        query: query.substring(0, 200),
        params: params.slice(0, 5),
        duration,
        error: error.message
      });
      
      throw error;
    }
  }

  /**
   * Check if query is cacheable
   */
  isCacheableQuery(query) {
    const trimmedQuery = query.trim().toUpperCase();
    return Array.from(this.cacheableQueries).some(keyword => 
      trimmedQuery.startsWith(keyword)
    );
  }

  /**
   * Generate cache key for query
   */
  generateCacheKey(query, params) {
    const queryHash = queryMonitor.hashQuery(query);
    const paramsHash = params.length > 0 ? 
      JSON.stringify(params).substring(0, 100) : 'no-params';
    return `query:${queryHash}:${paramsHash}`;
  }

  /**
   * Invalidate query cache by pattern
   */
  async invalidateQueryCache(pattern) {
    await cacheManager.clearPattern(`query:${pattern}*`);
    appLogger.info('Query cache invalidated', { pattern });
  }
}

const queryExecutor = new OptimizedQueryExecutor();

/**
 * Middleware for automatic query optimization
 */
const queryOptimizationMiddleware = (req, res, next) => {
  // Add optimized query executor to request
  req.optimizedQuery = queryExecutor.executeOptimized.bind(queryExecutor);
  
  // Add cache invalidation helper
  req.invalidateCache = async (entityType, entityId) => {
    await cacheManager.invalidateEntity(entityType, entityId);
  };

  next();
};

/**
 * Product-specific optimized queries
 */
const ProductQueries = {
  /**
   * Get products with caching
   */
  async getProducts(filters = {}, options = {}) {
    const {
      search = '',
      category,
      minPrice,
      maxPrice,
      sortBy = 'created_at',
      sortOrder = 'desc',
      page = 1,
      limit = 20
    } = filters;

    const cacheKey = CacheKeys.productList({ 
      search, category, minPrice, maxPrice, 
      sortBy, sortOrder, page, limit 
    });

    return await queryExecutor.executeOptimized(`
      SELECT 
        p.id, p.name, p.description, p.price, p.stock_quantity,
        p.brand, p.sku, p.created_at, p.updated_at,
        c.id as category_id, c.name as category_name,
        pi.image_url as primary_image
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
      WHERE p.is_active = true
        ${search ? 'AND (p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)' : ''}
        ${category ? 'AND p.category_id = ?' : ''}
        ${minPrice ? 'AND p.price >= ?' : ''}
        ${maxPrice ? 'AND p.price <= ?' : ''}
      ORDER BY p.${['name', 'price', 'created_at', 'stock_quantity'].includes(sortBy) ? sortBy : 'created_at'} 
        ${sortOrder.toLowerCase() === 'asc' ? 'ASC' : 'DESC'}
      LIMIT ? OFFSET ?
    `, [
      ...(search ? [`%${search}%`, `%${search}%`, `%${search}%`] : []),
      ...(category ? [category] : []),
      ...(minPrice ? [minPrice] : []),
      ...(maxPrice ? [maxPrice] : []),
      limit,
      (page - 1) * limit
    ], {
      cacheKey,
      cacheTTL: 600 // 10 minutes
    });
  },

  /**
   * Get single product with caching
   */
  async getProduct(id) {
    const cacheKey = CacheKeys.product(id);
    
    return await queryExecutor.executeOptimized(`
      SELECT 
        p.id, p.name, p.description, p.price, p.stock_quantity,
        p.brand, p.sku, p.is_active, p.created_at, p.updated_at,
        c.id as category_id, c.name as category_name,
        c.description as category_description
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      WHERE p.id = ? AND p.is_active = true
    `, [id], {
      cacheKey,
      cacheTTL: 1800 // 30 minutes
    });
  },

  /**
   * Get categories with caching
   */
  async getCategories() {
    const cacheKey = CacheKeys.categories();
    
    return await queryExecutor.executeOptimized(`
      SELECT 
        c.id, c.name, c.description, c.image_url, c.created_at,
        COUNT(p.id) as product_count
      FROM categories c
      LEFT JOIN products p ON c.id = p.category_id AND p.is_active = true
      WHERE c.is_active = true
      GROUP BY c.id, c.name, c.description, c.image_url, c.created_at
      ORDER BY c.name ASC
    `, [], {
      cacheKey,
      cacheTTL: 3600 // 1 hour
    });
  }
};

/**
 * Order-specific optimized queries
 */
const OrderQueries = {
  /**
   * Get user orders with caching
   */
  async getUserOrders(userId, page = 1, limit = 10, status = null) {
    const cacheKey = CacheKeys.userOrders(userId, page);
    
    return await queryExecutor.executeOptimized(`
      SELECT o.id, o.order_number, o.status, o.total_amount, 
             o.discount_amount, o.payment_method, o.payment_status,
             o.created_at, o.updated_at,
             a.first_name, a.last_name, a.city, a.state
      FROM orders o
      JOIN addresses a ON o.shipping_address_id = a.id
      WHERE o.user_id = ? ${status ? 'AND o.status = ?' : ''}
      ORDER BY o.created_at DESC
      LIMIT ? OFFSET ?
    `, [
      userId,
      ...(status ? [status] : []),
      limit,
      (page - 1) * limit
    ], {
      cacheKey,
      cacheTTL: 300 // 5 minutes
    });
  },

  /**
   * Get order details with caching
   */
  async getOrder(id, userId = null) {
    const cacheKey = CacheKeys.order(id);
    
    return await queryExecutor.executeOptimized(`
      SELECT o.*, 
             a.first_name, a.last_name, a.address_line1, a.address_line2,
             a.city, a.state, a.postal_code, a.country, a.phone,
             p.payment_status, p.razorpay_payment_id, p.transaction_id
      FROM orders o
      JOIN addresses a ON o.shipping_address_id = a.id
      LEFT JOIN payments p ON o.id = p.order_id
      WHERE o.id = ? ${userId ? 'AND o.user_id = ?' : ''}
    `, [
      id,
      ...(userId ? [userId] : [])
    ], {
      cacheKey,
      cacheTTL: 600 // 10 minutes
    });
  }
};

module.exports = {
  queryOptimizationMiddleware,
  queryExecutor,
  queryMonitor,
  ProductQueries,
  OrderQueries,
  OptimizedQueryExecutor
};