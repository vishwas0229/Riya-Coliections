/**
 * Advanced Caching System for Riya Collections
 * 
 * This module provides comprehensive caching with:
 * - Redis support with in-memory fallback
 * - Multi-level caching strategy
 * - Cache invalidation patterns
 * - Performance monitoring
 * 
 * Requirements: 10.3, 10.4, 11.4
 */

const { appLogger } = require('../config/logging');
const { getEnvironmentConfig } = require('../config/environment');

class CacheManager {
  constructor() {
    this.config = getEnvironmentConfig();
    this.redisClient = null;
    this.memoryCache = new Map();
    this.cacheStats = {
      hits: 0,
      misses: 0,
      sets: 0,
      deletes: 0,
      errors: 0
    };
    
    this.initializeCache();
  }

  /**
   * Initialize cache system
   */
  async initializeCache() {
    if (this.config.cache?.enabled && this.config.cache.redis) {
      try {
        const redis = require('redis');
        this.redisClient = redis.createClient({
          host: this.config.cache.redis.host,
          port: this.config.cache.redis.port,
          password: this.config.cache.redis.password,
          retry_strategy: (options) => {
            if (options.error && options.error.code === 'ECONNREFUSED') {
              appLogger.warn('Redis connection refused, falling back to memory cache');
              return undefined; // Stop retrying
            }
            if (options.total_retry_time > 1000 * 60 * 60) {
              return new Error('Retry time exhausted');
            }
            if (options.attempt > 10) {
              return undefined;
            }
            return Math.min(options.attempt * 100, 3000);
          }
        });

        this.redisClient.on('connect', () => {
          appLogger.info('Redis cache connected successfully');
        });

        this.redisClient.on('error', (err) => {
          appLogger.warn('Redis cache error, falling back to memory cache', { error: err.message });
          this.redisClient = null;
        });

        await this.redisClient.connect();
        
      } catch (error) {
        appLogger.warn('Failed to initialize Redis cache, using memory cache', { error: error.message });
        this.redisClient = null;
      }
    }

    // Initialize memory cache cleanup
    this.startMemoryCacheCleanup();
    
    appLogger.info('Cache system initialized', {
      redis: !!this.redisClient,
      memoryCache: true,
      ttl: this.config.cache?.ttl || 300
    });
  }

  /**
   * Get value from cache
   */
  async get(key) {
    try {
      let value = null;
      
      // Try Redis first
      if (this.redisClient) {
        try {
          const redisValue = await this.redisClient.get(key);
          if (redisValue !== null) {
            value = JSON.parse(redisValue);
            this.cacheStats.hits++;
            return value;
          }
        } catch (error) {
          appLogger.warn('Redis get error', { key, error: error.message });
          this.cacheStats.errors++;
        }
      }

      // Fallback to memory cache
      const memoryItem = this.memoryCache.get(key);
      if (memoryItem) {
        if (memoryItem.expiry > Date.now()) {
          this.cacheStats.hits++;
          return memoryItem.value;
        } else {
          this.memoryCache.delete(key);
        }
      }

      this.cacheStats.misses++;
      return null;
      
    } catch (error) {
      appLogger.error('Cache get error', { key, error: error.message });
      this.cacheStats.errors++;
      return null;
    }
  }

  /**
   * Set value in cache
   */
  async set(key, value, ttl = null) {
    try {
      const cacheValue = JSON.stringify(value);
      const cacheTTL = ttl || this.config.cache?.ttl || 300;
      
      // Set in Redis
      if (this.redisClient) {
        try {
          await this.redisClient.setEx(key, cacheTTL, cacheValue);
        } catch (error) {
          appLogger.warn('Redis set error', { key, error: error.message });
          this.cacheStats.errors++;
        }
      }

      // Set in memory cache
      this.memoryCache.set(key, {
        value,
        expiry: Date.now() + (cacheTTL * 1000)
      });

      this.cacheStats.sets++;
      return true;
      
    } catch (error) {
      appLogger.error('Cache set error', { key, error: error.message });
      this.cacheStats.errors++;
      return false;
    }
  }

  /**
   * Delete value from cache
   */
  async delete(key) {
    try {
      // Delete from Redis
      if (this.redisClient) {
        try {
          await this.redisClient.del(key);
        } catch (error) {
          appLogger.warn('Redis delete error', { key, error: error.message });
          this.cacheStats.errors++;
        }
      }

      // Delete from memory cache
      this.memoryCache.delete(key);
      
      this.cacheStats.deletes++;
      return true;
      
    } catch (error) {
      appLogger.error('Cache delete error', { key, error: error.message });
      this.cacheStats.errors++;
      return false;
    }
  }

  /**
   * Clear cache by pattern
   */
  async clearPattern(pattern) {
    try {
      // Clear from Redis
      if (this.redisClient) {
        try {
          const keys = await this.redisClient.keys(pattern);
          if (keys.length > 0) {
            await this.redisClient.del(keys);
          }
        } catch (error) {
          appLogger.warn('Redis clear pattern error', { pattern, error: error.message });
          this.cacheStats.errors++;
        }
      }

      // Clear from memory cache
      const regex = new RegExp(pattern.replace(/\*/g, '.*'));
      for (const key of this.memoryCache.keys()) {
        if (regex.test(key)) {
          this.memoryCache.delete(key);
        }
      }

      appLogger.info('Cache pattern cleared', { pattern });
      return true;
      
    } catch (error) {
      appLogger.error('Cache clear pattern error', { pattern, error: error.message });
      this.cacheStats.errors++;
      return false;
    }
  }

  /**
   * Get cache statistics
   */
  getStats() {
    const total = this.cacheStats.hits + this.cacheStats.misses;
    const hitRate = total > 0 ? (this.cacheStats.hits / total * 100).toFixed(2) : 0;
    
    return {
      ...this.cacheStats,
      hitRate: `${hitRate}%`,
      memorySize: this.memoryCache.size,
      redisConnected: !!this.redisClient
    };
  }

  /**
   * Reset cache statistics
   */
  resetStats() {
    this.cacheStats = {
      hits: 0,
      misses: 0,
      sets: 0,
      deletes: 0,
      errors: 0
    };
  }

  /**
   * Start memory cache cleanup process
   */
  startMemoryCacheCleanup() {
    setInterval(() => {
      const now = Date.now();
      let cleaned = 0;
      
      for (const [key, item] of this.memoryCache.entries()) {
        if (item.expiry <= now) {
          this.memoryCache.delete(key);
          cleaned++;
        }
      }
      
      if (cleaned > 0) {
        appLogger.debug('Memory cache cleanup completed', { cleaned, remaining: this.memoryCache.size });
      }
    }, 60000); // Clean every minute
  }

  /**
   * Cache wrapper for database queries
   */
  async cacheQuery(key, queryFunction, ttl = null) {
    // Try to get from cache first
    const cached = await this.get(key);
    if (cached !== null) {
      return cached;
    }

    // Execute query and cache result
    try {
      const result = await queryFunction();
      await this.set(key, result, ttl);
      return result;
    } catch (error) {
      appLogger.error('Cache query error', { key, error: error.message });
      throw error;
    }
  }

  /**
   * Invalidate cache for specific entities
   */
  async invalidateEntity(entityType, entityId = null) {
    const patterns = {
      product: [
        `products:*`,
        `product:${entityId}:*`,
        `categories:*`,
        `search:*`
      ],
      order: [
        `orders:user:*`,
        `order:${entityId}:*`,
        `admin:orders:*`
      ],
      user: [
        `user:${entityId}:*`,
        `orders:user:${entityId}:*`
      ],
      category: [
        `categories:*`,
        `products:category:*`,
        `category:${entityId}:*`
      ],
      cart: [
        `cart:${entityId}:*`
      ]
    };

    const entityPatterns = patterns[entityType] || [];
    
    for (const pattern of entityPatterns) {
      await this.clearPattern(pattern);
    }

    appLogger.info('Cache invalidated for entity', { entityType, entityId });
  }

  /**
   * Warm up cache with frequently accessed data
   */
  async warmupCache() {
    try {
      appLogger.info('Starting cache warmup');

      // Warm up categories
      const { secureExecuteQuery } = require('../middleware/database-security');
      
      const categories = await secureExecuteQuery(`
        SELECT id, name, description, image_url, is_active
        FROM categories 
        WHERE is_active = true
        ORDER BY name
      `);
      await this.set('categories:active', categories, 3600); // 1 hour

      // Warm up featured products
      const featuredProducts = await secureExecuteQuery(`
        SELECT p.id, p.name, p.price, p.stock_quantity, p.brand,
               c.name as category_name, pi.image_url
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
        WHERE p.is_active = true AND p.stock_quantity > 0
        ORDER BY p.created_at DESC
        LIMIT 20
      `);
      await this.set('products:featured', featuredProducts, 1800); // 30 minutes

      // Warm up popular products (based on order frequency)
      const popularProducts = await secureExecuteQuery(`
        SELECT p.id, p.name, p.price, p.stock_quantity, p.brand,
               c.name as category_name, pi.image_url,
               COUNT(oi.id) as order_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.is_active = true AND p.stock_quantity > 0
        GROUP BY p.id
        ORDER BY order_count DESC, p.created_at DESC
        LIMIT 20
      `);
      await this.set('products:popular', popularProducts, 1800); // 30 minutes

      appLogger.info('Cache warmup completed');
      
    } catch (error) {
      appLogger.error('Cache warmup failed', { error: error.message });
    }
  }

  /**
   * Close cache connections
   */
  async close() {
    if (this.redisClient) {
      try {
        await this.redisClient.quit();
        appLogger.info('Redis cache connection closed');
      } catch (error) {
        appLogger.warn('Error closing Redis connection', { error: error.message });
      }
    }
    
    this.memoryCache.clear();
    appLogger.info('Cache manager closed');
  }
}

// Create singleton instance
const cacheManager = new CacheManager();

// Cache key generators
const CacheKeys = {
  product: (id) => `product:${id}`,
  productList: (filters) => `products:list:${JSON.stringify(filters)}`,
  productsByCategory: (categoryId, page = 1) => `products:category:${categoryId}:page:${page}`,
  categories: () => 'categories:active',
  user: (id) => `user:${id}`,
  userOrders: (userId, page = 1) => `orders:user:${userId}:page:${page}`,
  order: (id) => `order:${id}`,
  cart: (userId) => `cart:${userId}`,
  search: (query, filters) => `search:${query}:${JSON.stringify(filters)}`,
  adminOrders: (filters, page) => `admin:orders:${JSON.stringify(filters)}:page:${page}`,
  stats: (type) => `stats:${type}`,
  coupon: (code) => `coupon:${code}`
};

module.exports = {
  cacheManager,
  CacheKeys
};