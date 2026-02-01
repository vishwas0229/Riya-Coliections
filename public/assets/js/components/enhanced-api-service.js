/**
 * Enhanced API Service for Riya Collections Frontend
 * Integrates all API communication components for comprehensive functionality
 */

class EnhancedApiService {
  constructor() {
    this.client = new ApiClient();
    this.stateManager = window.ApiStateManager;
    this.authManager = window.AuthManager;
    this.loadingManager = window.LoadingManager;
    this.errorHandler = window.ErrorHandler;
    this.interceptor = window.ApiInterceptor;
    
    this.init();
  }

  /**
   * Initialize enhanced API service
   */
  init() {
    this.setupInterceptors();
    this.setupEventListeners();
  }

  /**
   * Setup API interceptors
   */
  setupInterceptors() {
    // Setup caching and deduplication
    this.interceptor.setupCaching();
    this.interceptor.setupDeduplication();
    
    // Add custom interceptors
    this.interceptor.addRequestInterceptor(this.requestInterceptor.bind(this));
    this.interceptor.addResponseInterceptor(this.responseInterceptor.bind(this));
    this.interceptor.addErrorInterceptor(this.errorInterceptor.bind(this));
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Listen for retry events
    document.addEventListener('errorRetry', (event) => {
      this.handleRetryRequest(event.detail.errorId);
    });
    
    // Listen for auth state changes
    document.addEventListener('authStateChanged', (event) => {
      this.handleAuthStateChange(event.detail);
    });
  }

  /**
   * Request interceptor
   * @param {string} url - Request URL
   * @param {Object} config - Request config
   * @returns {Object} Modified config
   */
  async requestInterceptor(url, config) {
    // Store original URL for retry purposes
    config._originalUrl = url;
    
    // Generate request key for state management
    const requestKey = this.generateRequestKey(url, config);
    config._requestKey = requestKey;
    
    // Check cache first
    if (config.method === 'GET' || !config.method) {
      const cached = this.stateManager.getCache(requestKey);
      if (cached && this.stateManager.isCacheFresh(requestKey)) {
        // Return cached response
        return Promise.resolve({
          ok: true,
          status: 200,
          json: () => Promise.resolve({ success: true, data: cached }),
          headers: new Headers()
        });
      }
    }
    
    // Set loading state
    this.stateManager.setLoading(requestKey, true);
    
    return config;
  }

  /**
   * Response interceptor
   * @param {Response} response - Fetch response
   * @param {Object} config - Request config
   * @returns {Response} Response
   */
  async responseInterceptor(response, config) {
    const requestKey = config._requestKey;
    
    if (response.ok) {
      // Clear error state on success
      this.stateManager.clearError(requestKey);
      this.stateManager.resetRetryCount(requestKey);
      
      // Cache successful GET responses
      if (config.method === 'GET' || !config.method) {
        try {
          const responseClone = response.clone();
          const data = await responseClone.json();
          
          if (data.success) {
            this.stateManager.setCache(requestKey, data.data);
          }
        } catch (error) {
          console.warn('Failed to cache response:', error);
        }
      }
    }
    
    // Clear loading state
    this.stateManager.setLoading(requestKey, false);
    
    return response;
  }

  /**
   * Error interceptor
   * @param {Error} error - Request error
   * @param {Object} config - Request config
   * @returns {Error} Error
   */
  async errorInterceptor(error, config) {
    const requestKey = config._requestKey;
    
    // Clear loading state
    this.stateManager.setLoading(requestKey, false);
    
    // Set error state
    this.stateManager.setError(requestKey, error);
    
    // Handle retry logic
    if (this.shouldRetryRequest(error, config)) {
      const retryCount = this.stateManager.incrementRetryCount(requestKey);
      const delay = this.stateManager.getRetryDelay(requestKey);
      
      // Wait and retry
      await new Promise(resolve => setTimeout(resolve, delay));
      
      try {
        const retryResponse = await this.client.request(config._originalUrl, {
          ...config,
          skipLoading: true
        });
        
        // Success on retry
        this.stateManager.resetRetryCount(requestKey);
        return retryResponse;
        
      } catch (retryError) {
        // Retry failed
        if (!this.stateManager.shouldRetry(requestKey)) {
          this.stateManager.setError(requestKey, retryError);
        }
        return retryError;
      }
    }
    
    return error;
  }

  /**
   * Generate request key for state management
   * @param {string} url - Request URL
   * @param {Object} config - Request config
   * @returns {string} Request key
   */
  generateRequestKey(url, config) {
    const method = config.method || 'GET';
    const params = config.body ? JSON.parse(config.body) : {};
    return `${method}_${url}_${JSON.stringify(params)}`;
  }

  /**
   * Check if request should be retried
   * @param {Error} error - Request error
   * @param {Object} config - Request config
   * @returns {boolean} Should retry
   */
  shouldRetryRequest(error, config) {
    const requestKey = config._requestKey;
    
    // Don't retry if disabled
    if (config.noRetry) return false;
    
    // Don't retry if max attempts reached
    if (!this.stateManager.shouldRetry(requestKey)) return false;
    
    // Don't retry authentication errors
    if (error.status === 401 || error.status === 403) return false;
    
    // Don't retry client errors (except timeout and rate limit)
    if (error.status >= 400 && error.status < 500 && 
        error.status !== 408 && error.status !== 429) {
      return false;
    }
    
    // Retry network errors, timeouts, and server errors
    return error.message.includes('Network') || 
           error.message.includes('timeout') ||
           error.status >= 500 ||
           error.status === 408 ||
           error.status === 429;
  }

  /**
   * Handle retry request
   * @param {string} errorId - Error ID
   */
  async handleRetryRequest(errorId) {
    // Find the request associated with this error
    // This would need to be implemented based on how errors are tracked
    console.log('Retry requested for error:', errorId);
  }

  /**
   * Handle authentication state change
   * @param {Object} authState - Authentication state
   */
  handleAuthStateChange(authState) {
    if (!authState.authenticated) {
      // Clear cache on logout
      this.stateManager.clearCache();
      
      // Clear any pending requests
      this.stateManager.state.loading.clear();
    }
  }

  /**
   * Make API request with enhanced features
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async request(endpoint, options = {}) {
    const config = await this.interceptor.processRequest(endpoint, options);
    
    try {
      const response = await this.client.request(endpoint, config);
      
      const processedResponse = await this.interceptor.processResponse(response, config);
      return processedResponse;
      
    } catch (error) {
      const processedError = await this.interceptor.processError(error, config);
      throw processedError;
    }
  }

  /**
   * GET request with caching
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Query parameters
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async get(endpoint, params = {}, options = {}) {
    const requestKey = this.stateManager.generateCacheKey(endpoint, params);
    
    // Check cache first
    if (!options.skipCache) {
      const cached = this.stateManager.getCache(requestKey);
      if (cached && this.stateManager.isCacheFresh(requestKey, options.maxAge)) {
        return { success: true, data: cached };
      }
    }
    
    return this.client.get(endpoint, params, options);
  }

  /**
   * POST request
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request body data
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async post(endpoint, data = {}, options = {}) {
    const response = await this.client.post(endpoint, data, options);
    
    // Invalidate related cache entries
    this.invalidateRelatedCache(endpoint, 'POST');
    
    return response;
  }

  /**
   * PUT request
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request body data
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async put(endpoint, data = {}, options = {}) {
    const response = await this.client.put(endpoint, data, options);
    
    // Invalidate related cache entries
    this.invalidateRelatedCache(endpoint, 'PUT');
    
    return response;
  }

  /**
   * DELETE request
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async delete(endpoint, options = {}) {
    const response = await this.client.delete(endpoint, options);
    
    // Invalidate related cache entries
    this.invalidateRelatedCache(endpoint, 'DELETE');
    
    return response;
  }

  /**
   * Invalidate related cache entries
   * @param {string} endpoint - API endpoint
   * @param {string} method - HTTP method
   */
  invalidateRelatedCache(endpoint, method) {
    // Determine which cache entries to invalidate based on endpoint
    const patterns = this.getCacheInvalidationPatterns(endpoint, method);
    
    patterns.forEach(pattern => {
      this.stateManager.invalidateCache(pattern);
    });
  }

  /**
   * Get cache invalidation patterns
   * @param {string} endpoint - API endpoint
   * @param {string} method - HTTP method
   * @returns {Array} Invalidation patterns
   */
  getCacheInvalidationPatterns(endpoint, method) {
    const patterns = [];
    
    // Product-related invalidations
    if (endpoint.includes('/products')) {
      patterns.push(/GET_\/products/);
      patterns.push(/GET_\/products\/categories/);
    }
    
    // Cart-related invalidations
    if (endpoint.includes('/cart')) {
      patterns.push(/GET_\/cart/);
    }
    
    // Order-related invalidations
    if (endpoint.includes('/orders')) {
      patterns.push(/GET_\/orders/);
      patterns.push(/GET_\/cart/); // Orders affect cart
    }
    
    // User-related invalidations
    if (endpoint.includes('/auth/profile')) {
      patterns.push(/GET_\/auth\/profile/);
    }
    
    return patterns;
  }

  /**
   * Prefetch data for performance
   * @param {Array} endpoints - Endpoints to prefetch
   * @returns {Promise<Array>} Prefetch results
   */
  async prefetch(endpoints) {
    const requests = endpoints.map(({ endpoint, params, options }) => ({
      endpoint,
      params: params || {},
      options: { ...options, skipLoading: true }
    }));
    
    return this.stateManager.batchPrefetch(requests);
  }

  /**
   * Subscribe to data changes
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Request parameters
   * @param {Function} callback - Callback function
   * @returns {Function} Unsubscribe function
   */
  subscribe(endpoint, params, callback) {
    const key = this.stateManager.generateCacheKey(endpoint, params);
    return this.stateManager.subscribe(key, callback);
  }

  /**
   * Get loading state
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Request parameters
   * @returns {boolean} Is loading
   */
  isLoading(endpoint, params = {}) {
    const key = this.stateManager.generateCacheKey(endpoint, params);
    return this.stateManager.isLoading(key);
  }

  /**
   * Get error state
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Request parameters
   * @returns {Error|null} Error object
   */
  getError(endpoint, params = {}) {
    const key = this.stateManager.generateCacheKey(endpoint, params);
    return this.stateManager.getError(key);
  }

  /**
   * Clear cache
   * @param {string|RegExp} pattern - Pattern to clear
   */
  clearCache(pattern) {
    this.stateManager.invalidateCache(pattern);
  }

  /**
   * Get service statistics
   * @returns {Object} Service statistics
   */
  getStats() {
    return {
      cache: this.stateManager.getCacheStats(),
      interceptor: this.interceptor.getCacheStats(),
      auth: {
        isAuthenticated: this.authManager.isAuthenticated,
        sessionValid: this.authManager.isSessionValid(),
        timeUntilExpiration: this.authManager.getTimeUntilExpiration()
      }
    };
  }
}

// Create enhanced API service instance
window.EnhancedApiService = new EnhancedApiService();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = EnhancedApiService;
}