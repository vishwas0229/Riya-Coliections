/**
 * API State Manager for Riya Collections Frontend
 * Manages API request states, caching, and data synchronization
 */

class ApiStateManager {
  constructor() {
    this.state = {
      loading: new Set(),
      errors: new Map(),
      cache: new Map(),
      lastFetch: new Map(),
      retryCount: new Map()
    };
    
    this.subscribers = new Map();
    this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
    this.maxRetries = 3;
    this.retryDelay = 1000;
    
    this.init();
  }

  /**
   * Initialize state manager
   */
  init() {
    this.setupPeriodicCleanup();
    this.setupStorageSync();
  }

  /**
   * Setup periodic cache cleanup
   */
  setupPeriodicCleanup() {
    // Clean up expired cache entries every 5 minutes
    setInterval(() => {
      this.cleanupExpiredCache();
    }, 5 * 60 * 1000);
  }

  /**
   * Setup storage synchronization
   */
  setupStorageSync() {
    // Listen for storage changes from other tabs
    window.addEventListener('storage', (event) => {
      if (event.key && event.key.startsWith('api_state_')) {
        this.handleStorageChange(event);
      }
    });
  }

  /**
   * Handle storage change from other tabs
   * @param {StorageEvent} event - Storage event
   */
  handleStorageChange(event) {
    const key = event.key.replace('api_state_', '');
    
    if (event.newValue) {
      try {
        const data = JSON.parse(event.newValue);
        this.updateStateFromStorage(key, data);
      } catch (error) {
        console.warn('Failed to parse storage data:', error);
      }
    }
  }

  /**
   * Update state from storage data
   * @param {string} key - State key
   * @param {*} data - State data
   */
  updateStateFromStorage(key, data) {
    // Update cache if newer
    if (this.state.cache.has(key)) {
      const existing = this.state.cache.get(key);
      if (data.timestamp > existing.timestamp) {
        this.state.cache.set(key, data);
        this.notifySubscribers(key, data.data);
      }
    }
  }

  /**
   * Generate cache key
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Request parameters
   * @returns {string} Cache key
   */
  generateCacheKey(endpoint, params = {}) {
    const sortedParams = Object.keys(params)
      .sort()
      .reduce((result, key) => {
        result[key] = params[key];
        return result;
      }, {});
    
    return `${endpoint}_${JSON.stringify(sortedParams)}`;
  }

  /**
   * Set loading state
   * @param {string} key - Request key
   * @param {boolean} loading - Loading state
   */
  setLoading(key, loading) {
    if (loading) {
      this.state.loading.add(key);
    } else {
      this.state.loading.delete(key);
    }
    
    this.notifyLoadingChange(key, loading);
  }

  /**
   * Check if request is loading
   * @param {string} key - Request key
   * @returns {boolean} Is loading
   */
  isLoading(key) {
    return this.state.loading.has(key);
  }

  /**
   * Get all loading states
   * @returns {Array} Loading keys
   */
  getAllLoading() {
    return Array.from(this.state.loading);
  }

  /**
   * Set error state
   * @param {string} key - Request key
   * @param {Error} error - Error object
   */
  setError(key, error) {
    this.state.errors.set(key, {
      error,
      timestamp: Date.now(),
      retryCount: this.state.retryCount.get(key) || 0
    });
    
    this.notifyErrorChange(key, error);
  }

  /**
   * Clear error state
   * @param {string} key - Request key
   */
  clearError(key) {
    this.state.errors.delete(key);
    this.notifyErrorChange(key, null);
  }

  /**
   * Get error for key
   * @param {string} key - Request key
   * @returns {Error|null} Error object
   */
  getError(key) {
    const errorData = this.state.errors.get(key);
    return errorData ? errorData.error : null;
  }

  /**
   * Set cached data
   * @param {string} key - Cache key
   * @param {*} data - Data to cache
   * @param {number} ttl - Time to live in milliseconds
   */
  setCache(key, data, ttl = this.cacheTimeout) {
    const cacheEntry = {
      data,
      timestamp: Date.now(),
      expiry: Date.now() + ttl
    };
    
    this.state.cache.set(key, cacheEntry);
    this.state.lastFetch.set(key, Date.now());
    
    // Sync to storage for cross-tab sharing
    this.syncToStorage(key, cacheEntry);
    
    this.notifySubscribers(key, data);
  }

  /**
   * Get cached data
   * @param {string} key - Cache key
   * @returns {*|null} Cached data or null
   */
  getCache(key) {
    const cacheEntry = this.state.cache.get(key);
    
    if (!cacheEntry) {
      return null;
    }
    
    // Check if expired
    if (Date.now() > cacheEntry.expiry) {
      this.state.cache.delete(key);
      this.removeFromStorage(key);
      return null;
    }
    
    return cacheEntry.data;
  }

  /**
   * Check if data is cached and fresh
   * @param {string} key - Cache key
   * @param {number} maxAge - Maximum age in milliseconds
   * @returns {boolean} Is fresh
   */
  isCacheFresh(key, maxAge = this.cacheTimeout) {
    const lastFetch = this.state.lastFetch.get(key);
    
    if (!lastFetch) {
      return false;
    }
    
    return (Date.now() - lastFetch) < maxAge;
  }

  /**
   * Invalidate cache
   * @param {string|RegExp} pattern - Key or pattern to invalidate
   */
  invalidateCache(pattern) {
    if (typeof pattern === 'string') {
      this.state.cache.delete(pattern);
      this.state.lastFetch.delete(pattern);
      this.removeFromStorage(pattern);
    } else if (pattern instanceof RegExp) {
      // Invalidate by pattern
      for (const key of this.state.cache.keys()) {
        if (pattern.test(key)) {
          this.state.cache.delete(key);
          this.state.lastFetch.delete(key);
          this.removeFromStorage(key);
        }
      }
    }
  }

  /**
   * Clear all cache
   */
  clearCache() {
    this.state.cache.clear();
    this.state.lastFetch.clear();
    
    // Clear from storage
    const keys = Object.keys(localStorage);
    keys.forEach(key => {
      if (key.startsWith('api_state_')) {
        localStorage.removeItem(key);
      }
    });
  }

  /**
   * Clean up expired cache entries
   */
  cleanupExpiredCache() {
    const now = Date.now();
    
    for (const [key, entry] of this.state.cache.entries()) {
      if (now > entry.expiry) {
        this.state.cache.delete(key);
        this.state.lastFetch.delete(key);
        this.removeFromStorage(key);
      }
    }
  }

  /**
   * Sync cache entry to storage
   * @param {string} key - Cache key
   * @param {Object} entry - Cache entry
   */
  syncToStorage(key, entry) {
    try {
      const storageKey = `api_state_${key}`;
      localStorage.setItem(storageKey, JSON.stringify(entry));
    } catch (error) {
      console.warn('Failed to sync to storage:', error);
    }
  }

  /**
   * Remove from storage
   * @param {string} key - Cache key
   */
  removeFromStorage(key) {
    try {
      const storageKey = `api_state_${key}`;
      localStorage.removeItem(storageKey);
    } catch (error) {
      console.warn('Failed to remove from storage:', error);
    }
  }

  /**
   * Subscribe to data changes
   * @param {string} key - Data key
   * @param {Function} callback - Callback function
   * @returns {Function} Unsubscribe function
   */
  subscribe(key, callback) {
    if (!this.subscribers.has(key)) {
      this.subscribers.set(key, new Set());
    }
    
    this.subscribers.get(key).add(callback);
    
    // Call immediately with current data
    const cached = this.getCache(key);
    if (cached !== null) {
      callback(cached);
    }
    
    // Return unsubscribe function
    return () => {
      const keySubscribers = this.subscribers.get(key);
      if (keySubscribers) {
        keySubscribers.delete(callback);
        if (keySubscribers.size === 0) {
          this.subscribers.delete(key);
        }
      }
    };
  }

  /**
   * Notify subscribers of data changes
   * @param {string} key - Data key
   * @param {*} data - New data
   */
  notifySubscribers(key, data) {
    const keySubscribers = this.subscribers.get(key);
    if (keySubscribers) {
      keySubscribers.forEach(callback => {
        try {
          callback(data);
        } catch (error) {
          console.error('Subscriber callback error:', error);
        }
      });
    }
  }

  /**
   * Notify loading state change
   * @param {string} key - Request key
   * @param {boolean} loading - Loading state
   */
  notifyLoadingChange(key, loading) {
    const event = new CustomEvent('apiLoadingChange', {
      detail: { key, loading }
    });
    document.dispatchEvent(event);
  }

  /**
   * Notify error state change
   * @param {string} key - Request key
   * @param {Error|null} error - Error object
   */
  notifyErrorChange(key, error) {
    const event = new CustomEvent('apiErrorChange', {
      detail: { key, error }
    });
    document.dispatchEvent(event);
  }

  /**
   * Increment retry count
   * @param {string} key - Request key
   * @returns {number} New retry count
   */
  incrementRetryCount(key) {
    const current = this.state.retryCount.get(key) || 0;
    const newCount = current + 1;
    this.state.retryCount.set(key, newCount);
    return newCount;
  }

  /**
   * Reset retry count
   * @param {string} key - Request key
   */
  resetRetryCount(key) {
    this.state.retryCount.delete(key);
  }

  /**
   * Check if should retry request
   * @param {string} key - Request key
   * @returns {boolean} Should retry
   */
  shouldRetry(key) {
    const retryCount = this.state.retryCount.get(key) || 0;
    return retryCount < this.maxRetries;
  }

  /**
   * Get retry delay
   * @param {string} key - Request key
   * @returns {number} Delay in milliseconds
   */
  getRetryDelay(key) {
    const retryCount = this.state.retryCount.get(key) || 0;
    return this.retryDelay * Math.pow(2, retryCount); // Exponential backoff
  }

  /**
   * Prefetch data
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Request parameters
   * @param {Object} options - Request options
   * @returns {Promise<*>} Prefetch promise
   */
  async prefetch(endpoint, params = {}, options = {}) {
    const key = this.generateCacheKey(endpoint, params);
    
    // Don't prefetch if already cached and fresh
    if (this.isCacheFresh(key)) {
      return this.getCache(key);
    }
    
    // Don't prefetch if already loading
    if (this.isLoading(key)) {
      return null;
    }
    
    try {
      this.setLoading(key, true);
      
      const response = await ApiService.request(endpoint, {
        ...options,
        skipLoading: true,
        skipErrorHandling: true
      });
      
      if (response.success) {
        this.setCache(key, response.data);
        return response.data;
      }
      
      return null;
      
    } catch (error) {
      console.warn('Prefetch failed:', error);
      return null;
    } finally {
      this.setLoading(key, false);
    }
  }

  /**
   * Batch prefetch multiple endpoints
   * @param {Array} requests - Array of request objects
   * @returns {Promise<Array>} Batch results
   */
  async batchPrefetch(requests) {
    const promises = requests.map(({ endpoint, params, options }) => 
      this.prefetch(endpoint, params, options)
    );
    
    return Promise.allSettled(promises);
  }

  /**
   * Get cache statistics
   * @returns {Object} Cache statistics
   */
  getCacheStats() {
    const now = Date.now();
    let totalSize = 0;
    let expiredCount = 0;
    
    for (const [key, entry] of this.state.cache.entries()) {
      totalSize += JSON.stringify(entry).length;
      if (now > entry.expiry) {
        expiredCount++;
      }
    }
    
    return {
      totalEntries: this.state.cache.size,
      expiredEntries: expiredCount,
      totalSize,
      averageSize: this.state.cache.size > 0 ? Math.round(totalSize / this.state.cache.size) : 0,
      loadingRequests: this.state.loading.size,
      errorCount: this.state.errors.size
    };
  }

  /**
   * Export state for debugging
   * @returns {Object} Current state
   */
  exportState() {
    return {
      loading: Array.from(this.state.loading),
      errors: Object.fromEntries(this.state.errors),
      cache: Object.fromEntries(this.state.cache),
      lastFetch: Object.fromEntries(this.state.lastFetch),
      retryCount: Object.fromEntries(this.state.retryCount),
      subscribers: Object.fromEntries(
        Array.from(this.subscribers.entries()).map(([key, set]) => [key, set.size])
      )
    };
  }
}

// Create global instance
window.ApiStateManager = new ApiStateManager();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ApiStateManager;
}