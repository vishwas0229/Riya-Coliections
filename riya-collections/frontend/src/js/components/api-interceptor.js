/**
 * API Interceptor for Riya Collections Frontend
 * Handles request/response interception, authentication, and middleware
 */

class ApiInterceptor {
  constructor() {
    this.requestInterceptors = [];
    this.responseInterceptors = [];
    this.errorInterceptors = [];
    this.init();
  }

  /**
   * Initialize interceptor
   */
  init() {
    this.setupDefaultInterceptors();
  }

  /**
   * Setup default interceptors
   */
  setupDefaultInterceptors() {
    // Authentication interceptor
    this.addRequestInterceptor(this.authenticationInterceptor.bind(this));
    
    // Request logging interceptor
    this.addRequestInterceptor(this.requestLoggingInterceptor.bind(this));
    
    // Response logging interceptor
    this.addResponseInterceptor(this.responseLoggingInterceptor.bind(this));
    
    // Authentication error interceptor
    this.addErrorInterceptor(this.authErrorInterceptor.bind(this));
    
    // Rate limiting interceptor
    this.addErrorInterceptor(this.rateLimitInterceptor.bind(this));
  }

  /**
   * Add request interceptor
   * @param {Function} interceptor - Interceptor function
   * @returns {number} Interceptor ID
   */
  addRequestInterceptor(interceptor) {
    const id = Date.now() + Math.random();
    this.requestInterceptors.push({ id, interceptor });
    return id;
  }

  /**
   * Add response interceptor
   * @param {Function} interceptor - Interceptor function
   * @returns {number} Interceptor ID
   */
  addResponseInterceptor(interceptor) {
    const id = Date.now() + Math.random();
    this.responseInterceptors.push({ id, interceptor });
    return id;
  }

  /**
   * Add error interceptor
   * @param {Function} interceptor - Interceptor function
   * @returns {number} Interceptor ID
   */
  addErrorInterceptor(interceptor) {
    const id = Date.now() + Math.random();
    this.errorInterceptors.push({ id, interceptor });
    return id;
  }

  /**
   * Remove interceptor
   * @param {string} type - Interceptor type (request, response, error)
   * @param {number} id - Interceptor ID
   */
  removeInterceptor(type, id) {
    const interceptors = this[`${type}Interceptors`];
    if (interceptors) {
      const index = interceptors.findIndex(item => item.id === id);
      if (index > -1) {
        interceptors.splice(index, 1);
      }
    }
  }

  /**
   * Process request through interceptors
   * @param {string} url - Request URL
   * @param {Object} config - Request config
   * @returns {Promise<Object>} Modified config
   */
  async processRequest(url, config) {
    let modifiedConfig = { ...config };
    
    for (const { interceptor } of this.requestInterceptors) {
      try {
        const result = await interceptor(url, modifiedConfig);
        if (result) {
          modifiedConfig = result;
        }
      } catch (error) {
        console.error('Request interceptor error:', error);
      }
    }
    
    return modifiedConfig;
  }

  /**
   * Process response through interceptors
   * @param {Response} response - Fetch response
   * @param {Object} config - Request config
   * @returns {Promise<Response>} Modified response
   */
  async processResponse(response, config) {
    let modifiedResponse = response;
    
    for (const { interceptor } of this.responseInterceptors) {
      try {
        const result = await interceptor(modifiedResponse, config);
        if (result) {
          modifiedResponse = result;
        }
      } catch (error) {
        console.error('Response interceptor error:', error);
      }
    }
    
    return modifiedResponse;
  }

  /**
   * Process error through interceptors
   * @param {Error} error - Request error
   * @param {Object} config - Request config
   * @returns {Promise<Error>} Modified error
   */
  async processError(error, config) {
    let modifiedError = error;
    
    for (const { interceptor } of this.errorInterceptors) {
      try {
        const result = await interceptor(modifiedError, config);
        if (result) {
          modifiedError = result;
        }
      } catch (interceptorError) {
        console.error('Error interceptor error:', interceptorError);
      }
    }
    
    return modifiedError;
  }

  /**
   * Authentication interceptor
   * @param {string} url - Request URL
   * @param {Object} config - Request config
   * @returns {Object} Modified config
   */
  authenticationInterceptor(url, config) {
    // Skip auth for login/register endpoints
    if (url.includes('/auth/login') || url.includes('/auth/register')) {
      return config;
    }

    // Add authentication header if available
    if (window.AuthManager && window.AuthManager.isAuthenticated) {
      const authHeader = window.AuthManager.getAuthHeader();
      config.headers = {
        ...config.headers,
        ...authHeader
      };
    }

    return config;
  }

  /**
   * Request logging interceptor
   * @param {string} url - Request URL
   * @param {Object} config - Request config
   * @returns {Object} Modified config
   */
  requestLoggingInterceptor(url, config) {
    if (window.IS_DEVELOPMENT) {
      console.group(`🚀 API Request: ${config.method || 'GET'} ${url}`);
      console.log('Config:', config);
      console.log('Headers:', config.headers);
      if (config.body) {
        console.log('Body:', config.body);
      }
      console.groupEnd();
    }

    // Add request timestamp
    config._requestStartTime = Date.now();
    
    return config;
  }

  /**
   * Response logging interceptor
   * @param {Response} response - Fetch response
   * @param {Object} config - Request config
   * @returns {Response} Response
   */
  responseLoggingInterceptor(response, config) {
    if (window.IS_DEVELOPMENT) {
      const duration = Date.now() - (config._requestStartTime || 0);
      
      console.group(`📥 API Response: ${response.status} ${response.statusText} (${duration}ms)`);
      console.log('Response:', response);
      console.log('Headers:', Object.fromEntries(response.headers.entries()));
      console.groupEnd();
    }

    return response;
  }

  /**
   * Authentication error interceptor
   * @param {Error} error - Request error
   * @param {Object} config - Request config
   * @returns {Promise<Error>} Error
   */
  async authErrorInterceptor(error, config) {
    if (error.status === 401) {
      // Token expired or invalid
      if (window.AuthManager) {
        // Try to refresh token
        const refreshed = await window.AuthManager.refreshToken();
        
        if (refreshed) {
          // Retry the original request with new token
          const newConfig = {
            ...config,
            headers: {
              ...config.headers,
              ...window.AuthManager.getAuthHeader()
            }
          };
          
          try {
            const response = await fetch(config._originalUrl, newConfig);
            return response;
          } catch (retryError) {
            // Refresh worked but retry failed, logout user
            window.AuthManager.logout();
            return retryError;
          }
        } else {
          // Refresh failed, logout user
          window.AuthManager.logout();
        }
      }
    }

    return error;
  }

  /**
   * Rate limiting interceptor
   * @param {Error} error - Request error
   * @param {Object} config - Request config
   * @returns {Promise<Error>} Error
   */
  async rateLimitInterceptor(error, config) {
    if (error.status === 429) {
      // Rate limited
      const retryAfter = error.headers?.get('Retry-After');
      
      if (retryAfter && window.NotificationManager) {
        const seconds = parseInt(retryAfter);
        NotificationManager.show(
          `Rate limit exceeded. Please wait ${seconds} seconds before trying again.`,
          'warning',
          { duration: seconds * 1000 }
        );
      }
    }

    return error;
  }

  /**
   * Cache interceptor
   * @param {string} url - Request URL
   * @param {Object} config - Request config
   * @returns {Promise<Object|null>} Cached response or null
   */
  async cacheInterceptor(url, config) {
    // Only cache GET requests
    if (config.method !== 'GET' && !config.method) {
      return null;
    }

    // Skip cache for certain endpoints
    const skipCachePatterns = ['/auth/', '/cart/', '/orders/'];
    if (skipCachePatterns.some(pattern => url.includes(pattern))) {
      return null;
    }

    // Check if response is cached
    const cacheKey = `api_cache_${url}_${JSON.stringify(config.headers || {})}`;
    const cached = CONFIG_UTILS.getStorageItem(cacheKey);
    
    if (cached && cached.expiry > Date.now()) {
      if (window.IS_DEVELOPMENT) {
        console.log('📦 Cache hit:', url);
      }
      
      // Return cached response
      return {
        ok: true,
        status: 200,
        json: () => Promise.resolve(cached.data),
        headers: new Headers(cached.headers || {})
      };
    }

    return null;
  }

  /**
   * Cache response interceptor
   * @param {Response} response - Fetch response
   * @param {Object} config - Request config
   * @returns {Response} Response
   */
  cacheResponseInterceptor(response, config) {
    // Only cache successful GET requests
    if (response.ok && (config.method === 'GET' || !config.method)) {
      const url = config._originalUrl;
      
      // Skip cache for certain endpoints
      const skipCachePatterns = ['/auth/', '/cart/', '/orders/'];
      if (!skipCachePatterns.some(pattern => url.includes(pattern))) {
        
        // Clone response to read body
        const responseClone = response.clone();
        
        responseClone.json().then(data => {
          const cacheKey = `api_cache_${url}_${JSON.stringify(config.headers || {})}`;
          const cacheData = {
            data,
            headers: Object.fromEntries(response.headers.entries()),
            expiry: Date.now() + (5 * 60 * 1000) // 5 minutes
          };
          
          CONFIG_UTILS.setStorageItem(cacheKey, cacheData);
          
          if (window.IS_DEVELOPMENT) {
            console.log('💾 Response cached:', url);
          }
        }).catch(error => {
          console.warn('Failed to cache response:', error);
        });
      }
    }

    return response;
  }

  /**
   * Request deduplication interceptor
   * @param {string} url - Request URL
   * @param {Object} config - Request config
   * @returns {Promise<Response|null>} Existing request promise or null
   */
  deduplicationInterceptor(url, config) {
    // Only deduplicate GET requests
    if (config.method !== 'GET' && config.method) {
      return null;
    }

    const requestKey = `${url}_${JSON.stringify(config)}`;
    
    // Check if same request is already in flight
    if (this.pendingRequests && this.pendingRequests.has(requestKey)) {
      if (window.IS_DEVELOPMENT) {
        console.log('🔄 Request deduplicated:', url);
      }
      
      return this.pendingRequests.get(requestKey);
    }

    return null;
  }

  /**
   * Setup request deduplication
   */
  setupDeduplication() {
    this.pendingRequests = new Map();
    
    this.addRequestInterceptor((url, config) => {
      const existing = this.deduplicationInterceptor(url, config);
      if (existing) {
        return existing;
      }

      // Store request promise
      if (config.method === 'GET' || !config.method) {
        const requestKey = `${url}_${JSON.stringify(config)}`;
        config._requestKey = requestKey;
      }

      return config;
    });

    this.addResponseInterceptor((response, config) => {
      // Remove from pending requests
      if (config._requestKey && this.pendingRequests) {
        this.pendingRequests.delete(config._requestKey);
      }
      
      return response;
    });

    this.addErrorInterceptor((error, config) => {
      // Remove from pending requests on error
      if (config._requestKey && this.pendingRequests) {
        this.pendingRequests.delete(config._requestKey);
      }
      
      return error;
    });
  }

  /**
   * Setup response caching
   */
  setupCaching() {
    this.addRequestInterceptor(this.cacheInterceptor.bind(this));
    this.addResponseInterceptor(this.cacheResponseInterceptor.bind(this));
  }

  /**
   * Clear cache
   * @param {string} pattern - URL pattern to clear (optional)
   */
  clearCache(pattern = null) {
    const keys = Object.keys(localStorage);
    
    keys.forEach(key => {
      if (key.startsWith('api_cache_')) {
        if (!pattern || key.includes(pattern)) {
          localStorage.removeItem(key);
        }
      }
    });
    
    if (window.IS_DEVELOPMENT) {
      console.log('🗑️ API cache cleared:', pattern || 'all');
    }
  }

  /**
   * Get cache statistics
   * @returns {Object} Cache stats
   */
  getCacheStats() {
    const keys = Object.keys(localStorage);
    const cacheKeys = keys.filter(key => key.startsWith('api_cache_'));
    
    let totalSize = 0;
    let expiredCount = 0;
    const now = Date.now();
    
    cacheKeys.forEach(key => {
      const data = CONFIG_UTILS.getStorageItem(key);
      if (data) {
        totalSize += JSON.stringify(data).length;
        if (data.expiry < now) {
          expiredCount++;
        }
      }
    });
    
    return {
      totalEntries: cacheKeys.length,
      expiredEntries: expiredCount,
      totalSize: totalSize,
      averageSize: cacheKeys.length > 0 ? Math.round(totalSize / cacheKeys.length) : 0
    };
  }
}

// Create global instance
window.ApiInterceptor = new ApiInterceptor();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ApiInterceptor;
}