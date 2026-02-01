/**
 * API utility functions for Riya Collections Frontend
 * Handles all HTTP requests to the backend API
 */

class ApiClient {
  constructor() {
    this.baseURL = API_CONFIG.BASE_URL;
    this.timeout = API_CONFIG.TIMEOUT;
    this.defaultHeaders = { ...API_CONFIG.HEADERS };
    this.requestQueue = new Map();
    this.retryAttempts = new Map();
    this.maxRetries = 3;
    this.retryDelay = 1000;
  }

  /**
   * Get authentication token from storage
   * @returns {string|null} Auth token
   */
  getAuthToken() {
    return CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
  }

  /**
   * Set authentication token
   * @param {string} token - JWT token
   */
  setAuthToken(token) {
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, token);
  }

  /**
   * Remove authentication token
   */
  removeAuthToken() {
    CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
  }

  /**
   * Get request headers with authentication
   * @param {Object} additionalHeaders - Additional headers
   * @returns {Object} Headers object
   */
  getHeaders(additionalHeaders = {}) {
    const headers = { ...this.defaultHeaders, ...additionalHeaders };
    
    const token = this.getAuthToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
    
    return headers;
  }

  /**
   * Handle API response
   * @param {Response} response - Fetch response
   * @returns {Promise<Object>} Parsed response data
   */
  async handleResponse(response) {
    const contentType = response.headers.get('content-type');
    
    let data;
    if (contentType && contentType.includes('application/json')) {
      data = await response.json();
    } else {
      data = { message: await response.text() };
    }

    if (!response.ok) {
      // Handle different error status codes
      switch (response.status) {
        case 401:
          this.removeAuthToken();
          throw new Error(ERROR_MESSAGES.AUTHENTICATION);
        case 403:
          throw new Error(ERROR_MESSAGES.AUTHORIZATION);
        case 404:
          throw new Error(ERROR_MESSAGES.NOT_FOUND);
        case 408:
          throw new Error(ERROR_MESSAGES.TIMEOUT);
        case 500:
          throw new Error(ERROR_MESSAGES.SERVER);
        default:
          throw new Error(data.message || ERROR_MESSAGES.GENERIC);
      }
    }

    return data;
  }

  /**
   * Make HTTP request
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async request(endpoint, options = {}) {
    const requestId = this.generateRequestId();
    const url = `${this.baseURL}${endpoint}`;
    
    const config = {
      method: 'GET',
      headers: this.getHeaders(options.headers),
      ...options
    };

    // Track request for loading management
    if (window.LoadingManager && !options.skipLoading) {
      LoadingManager.trackRequest(requestId);
    }

    // Add timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);
    config.signal = controller.signal;

    try {
      const response = await fetch(url, config);
      clearTimeout(timeoutId);
      
      const result = await this.handleResponse(response);
      
      // Untrack request
      if (window.LoadingManager && !options.skipLoading) {
        LoadingManager.untrackRequest(requestId);
      }
      
      return result;
      
    } catch (error) {
      clearTimeout(timeoutId);
      
      // Untrack request
      if (window.LoadingManager && !options.skipLoading) {
        LoadingManager.untrackRequest(requestId);
      }
      
      if (error.name === 'AbortError') {
        throw new Error(ERROR_MESSAGES.TIMEOUT);
      }
      
      if (error.message === 'Failed to fetch') {
        throw new Error(ERROR_MESSAGES.NETWORK);
      }
      
      // Handle retry logic
      if (this.shouldRetry(error, options)) {
        return this.retryRequest(endpoint, options, requestId);
      }
      
      // Handle error with error handler
      if (window.ErrorHandler && !options.skipErrorHandling) {
        ErrorHandler.handleApiError(error, {
          context: {
            endpoint,
            method: config.method,
            requestId
          }
        });
      }
      
      throw error;
    }
  }

  /**
   * Generate unique request ID
   * @returns {string} Request ID
   */
  generateRequestId() {
    return `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Check if request should be retried
   * @param {Error} error - Request error
   * @param {Object} options - Request options
   * @returns {boolean} Should retry
   */
  shouldRetry(error, options) {
    if (options.noRetry) return false;
    
    // Don't retry authentication errors
    if (error.status === 401 || error.status === 403) return false;
    
    // Don't retry client errors (4xx except 408, 429)
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
   * Retry failed request
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @param {string} requestId - Original request ID
   * @returns {Promise<Object>} Response data
   */
  async retryRequest(endpoint, options, requestId) {
    const retryKey = `${endpoint}_${JSON.stringify(options)}`;
    const currentAttempts = this.retryAttempts.get(retryKey) || 0;
    
    if (currentAttempts >= this.maxRetries) {
      this.retryAttempts.delete(retryKey);
      throw new Error('Maximum retry attempts exceeded');
    }
    
    // Increment retry count
    this.retryAttempts.set(retryKey, currentAttempts + 1);
    
    // Calculate delay with exponential backoff
    const delay = this.retryDelay * Math.pow(2, currentAttempts);
    
    // Wait before retry
    await new Promise(resolve => setTimeout(resolve, delay));
    
    try {
      const result = await this.request(endpoint, { ...options, skipLoading: true });
      this.retryAttempts.delete(retryKey);
      return result;
    } catch (error) {
      // If this was the last retry, clean up
      if (currentAttempts + 1 >= this.maxRetries) {
        this.retryAttempts.delete(retryKey);
      }
      throw error;
    }
  }

  /**
   * GET request
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Query parameters
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async get(endpoint, params = {}, options = {}) {
    const url = new URL(`${this.baseURL}${endpoint}`);
    
    // Add query parameters
    Object.keys(params).forEach(key => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    return this.request(url.pathname + url.search, {
      method: 'GET',
      ...options
    });
  }

  /**
   * POST request
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request body data
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async post(endpoint, data = {}, options = {}) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
      ...options
    });
  }

  /**
   * PUT request
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request body data
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async put(endpoint, data = {}, options = {}) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
      ...options
    });
  }

  /**
   * PATCH request
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request body data
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async patch(endpoint, data = {}, options = {}) {
    return this.request(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data),
      ...options
    });
  }

  /**
   * DELETE request
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async delete(endpoint, options = {}) {
    return this.request(endpoint, {
      method: 'DELETE',
      ...options
    });
  }

  /**
   * Upload file
   * @param {string} endpoint - API endpoint
   * @param {FormData} formData - Form data with file
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async upload(endpoint, formData, options = {}) {
    const headers = this.getHeaders();
    // Remove Content-Type header to let browser set it with boundary
    delete headers['Content-Type'];

    return this.request(endpoint, {
      method: 'POST',
      headers,
      body: formData,
      ...options
    });
  }
}

// Create API client instance
const api = new ApiClient();

// API Service Functions
const ApiService = {
  // Products API
  products: {
    /**
     * Get all products with filters
     * @param {Object} filters - Filter parameters
     * @returns {Promise<Object>} Products data
     */
    async getAll(filters = {}) {
      return api.get(API_CONFIG.ENDPOINTS.PRODUCTS, filters);
    },

    /**
     * Get product by ID
     * @param {number} id - Product ID
     * @param {Object} options - Additional options
     * @returns {Promise<Object>} Product data
     */
    async getById(id, options = {}) {
      const endpoint = API_CONFIG.ENDPOINTS.PRODUCT_DETAIL.replace(':id', id);
      return api.get(endpoint, options);
    },

    /**
     * Get product images
     * @param {number} id - Product ID
     * @returns {Promise<Object>} Product images
     */
    async getImages(id) {
      const endpoint = API_CONFIG.ENDPOINTS.PRODUCT_IMAGES.replace(':id', id);
      return api.get(endpoint);
    },

    /**
     * Search products
     * @param {string} query - Search query
     * @param {Object} filters - Additional filters
     * @returns {Promise<Object>} Search results
     */
    async search(query, filters = {}) {
      return api.get(API_CONFIG.ENDPOINTS.PRODUCTS, {
        search: query,
        ...filters
      });
    }
  },

  // Categories API
  categories: {
    /**
     * Get all categories
     * @returns {Promise<Object>} Categories data
     */
    async getAll() {
      return api.get(API_CONFIG.ENDPOINTS.CATEGORIES);
    }
  },

  // Authentication API
  auth: {
    /**
     * User login
     * @param {Object} credentials - Login credentials
     * @returns {Promise<Object>} Auth response
     */
    async login(credentials) {
      const response = await api.post(API_CONFIG.ENDPOINTS.AUTH.LOGIN, credentials);
      
      if (response.success && response.data.token) {
        api.setAuthToken(response.data.token);
        CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, response.data.user);
      }
      
      return response;
    },

    /**
     * Admin login
     * @param {Object} credentials - Admin login credentials
     * @returns {Promise<Object>} Auth response
     */
    async adminLogin(credentials) {
      const response = await api.post(API_CONFIG.ENDPOINTS.AUTH.ADMIN_LOGIN, credentials);
      
      if (response.success && response.data.tokens) {
        api.setAuthToken(response.data.tokens.accessToken);
        CONFIG_UTILS.setStorageItem('admin_user_data', response.data.admin);
      }
      
      return response;
    },

    /**
     * User registration
     * @param {Object} userData - Registration data
     * @returns {Promise<Object>} Auth response
     */
    async register(userData) {
      const response = await api.post(API_CONFIG.ENDPOINTS.AUTH.REGISTER, userData);
      
      if (response.success && response.data.token) {
        api.setAuthToken(response.data.token);
        CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, response.data.user);
      }
      
      return response;
    },

    /**
     * User logout
     * @returns {Promise<Object>} Logout response
     */
    async logout() {
      try {
        const response = await api.post(API_CONFIG.ENDPOINTS.AUTH.LOGOUT);
        return response;
      } finally {
        // Clear local data regardless of API response
        api.removeAuthToken();
        CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
        CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA);
      }
    },

    /**
     * Get user profile
     * @returns {Promise<Object>} Profile data
     */
    async getProfile() {
      return api.get(API_CONFIG.ENDPOINTS.AUTH.PROFILE);
    },

    /**
     * Update user profile
     * @param {Object} profileData - Profile update data
     * @returns {Promise<Object>} Update response
     */
    async updateProfile(profileData) {
      const response = await api.put(API_CONFIG.ENDPOINTS.AUTH.PROFILE, profileData);
      
      if (response.success && response.data.user) {
        CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, response.data.user);
      }
      
      return response;
    }
  },

  // Admin API
  admin: {
    /**
     * Get admin dashboard data
     * @returns {Promise<Object>} Dashboard data
     */
    async getDashboard() {
      return api.get(API_CONFIG.ENDPOINTS.ADMIN.DASHBOARD);
    },

    /**
     * Get order statistics
     * @param {Object} params - Query parameters
     * @returns {Promise<Object>} Order stats
     */
    async getOrderStats(params = {}) {
      return api.get(API_CONFIG.ENDPOINTS.ADMIN.STATS_ORDERS, params);
    },

    /**
     * Get product statistics
     * @returns {Promise<Object>} Product stats
     */
    async getProductStats() {
      return api.get(API_CONFIG.ENDPOINTS.ADMIN.STATS_PRODUCTS);
    },

    // Product Management
    products: {
      /**
       * Get all products (admin view)
       * @param {Object} filters - Filter parameters
       * @returns {Promise<Object>} Products data
       */
      async getAll(filters = {}) {
        return api.get(API_CONFIG.ENDPOINTS.ADMIN.PRODUCTS, filters);
      },

      /**
       * Get product by ID (admin view)
       * @param {number} id - Product ID
       * @returns {Promise<Object>} Product data
       */
      async getById(id) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_DETAIL.replace(':id', id);
        return api.get(endpoint);
      },

      /**
       * Create new product
       * @param {Object} productData - Product data
       * @returns {Promise<Object>} Create response
       */
      async create(productData) {
        return api.post(API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_CREATE, productData);
      },

      /**
       * Update product
       * @param {number} id - Product ID
       * @param {Object} productData - Updated product data
       * @returns {Promise<Object>} Update response
       */
      async update(id, productData) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_UPDATE.replace(':id', id);
        return api.put(endpoint, productData);
      },

      /**
       * Delete product
       * @param {number} id - Product ID
       * @returns {Promise<Object>} Delete response
       */
      async delete(id) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_DELETE.replace(':id', id);
        return api.delete(endpoint);
      },

      /**
       * Upload product images
       * @param {number} id - Product ID
       * @param {FormData} formData - Image form data
       * @returns {Promise<Object>} Upload response
       */
      async uploadImages(id, formData) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_IMAGES.replace(':id', id);
        return api.upload(endpoint, formData);
      },

      /**
       * Get product images
       * @param {number} id - Product ID
       * @returns {Promise<Object>} Images data
       */
      async getImages(id) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_IMAGES.replace(':id', id);
        return api.get(endpoint);
      },

      /**
       * Update product image
       * @param {number} productId - Product ID
       * @param {number} imageId - Image ID
       * @param {Object} imageData - Image update data
       * @returns {Promise<Object>} Update response
       */
      async updateImage(productId, imageId, imageData) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_IMAGE_UPDATE
          .replace(':productId', productId)
          .replace(':imageId', imageId);
        return api.put(endpoint, imageData);
      },

      /**
       * Delete product image
       * @param {number} productId - Product ID
       * @param {number} imageId - Image ID
       * @returns {Promise<Object>} Delete response
       */
      async deleteImage(productId, imageId) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.PRODUCT_IMAGE_DELETE
          .replace(':productId', productId)
          .replace(':imageId', imageId);
        return api.delete(endpoint);
      }
    },

    // Category Management
    categories: {
      /**
       * Get all categories (admin view)
       * @returns {Promise<Object>} Categories data
       */
      async getAll() {
        return api.get(API_CONFIG.ENDPOINTS.ADMIN.CATEGORIES);
      },

      /**
       * Create new category
       * @param {Object} categoryData - Category data
       * @returns {Promise<Object>} Create response
       */
      async create(categoryData) {
        return api.post(API_CONFIG.ENDPOINTS.ADMIN.CATEGORY_CREATE, categoryData);
      },

      /**
       * Update category
       * @param {number} id - Category ID
       * @param {Object} categoryData - Updated category data
       * @returns {Promise<Object>} Update response
       */
      async update(id, categoryData) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.CATEGORY_UPDATE.replace(':id', id);
        return api.put(endpoint, categoryData);
      },

      /**
       * Delete category
       * @param {number} id - Category ID
       * @returns {Promise<Object>} Delete response
       */
      async delete(id) {
        const endpoint = API_CONFIG.ENDPOINTS.ADMIN.CATEGORY_DELETE.replace(':id', id);
        return api.delete(endpoint);
      }
    }
  },

  // Cart API
  cart: {
    /**
     * Get cart contents
     * @returns {Promise<Object>} Cart data
     */
    async get() {
      return api.get(API_CONFIG.ENDPOINTS.CART.GET);
    },

    /**
     * Add item to cart
     * @param {Object} item - Cart item data
     * @returns {Promise<Object>} Add response
     */
    async add(item) {
      return api.post(API_CONFIG.ENDPOINTS.CART.ADD, item);
    },

    /**
     * Update cart item
     * @param {Object} item - Updated item data
     * @returns {Promise<Object>} Update response
     */
    async update(item) {
      return api.put(API_CONFIG.ENDPOINTS.CART.UPDATE, item);
    },

    /**
     * Remove item from cart
     * @param {number} itemId - Item ID to remove
     * @returns {Promise<Object>} Remove response
     */
    async remove(itemId) {
      return api.delete(`${API_CONFIG.ENDPOINTS.CART.REMOVE}/${itemId}`);
    },

    /**
     * Clear entire cart
     * @returns {Promise<Object>} Clear response
     */
    async clear() {
      return api.delete(API_CONFIG.ENDPOINTS.CART.CLEAR);
    }
  },

  // Orders API
  orders: {
    /**
     * Create new order
     * @param {Object} orderData - Order data
     * @returns {Promise<Object>} Order response
     */
    async create(orderData) {
      return api.post(API_CONFIG.ENDPOINTS.ORDERS.CREATE, orderData);
    },

    /**
     * Get user orders
     * @param {Object} filters - Filter parameters
     * @returns {Promise<Object>} Orders data
     */
    async getAll(filters = {}) {
      return api.get(API_CONFIG.ENDPOINTS.ORDERS.LIST, filters);
    },

    /**
     * Get order by ID
     * @param {number} id - Order ID
     * @returns {Promise<Object>} Order data
     */
    async getById(id) {
      const endpoint = API_CONFIG.ENDPOINTS.ORDERS.DETAIL.replace(':id', id);
      return api.get(endpoint);
    }
  },

  // Addresses API
  addresses: {
    /**
     * Get user addresses
     * @returns {Promise<Object>} Addresses data
     */
    async getAll() {
      return api.get(API_CONFIG.ENDPOINTS.ADDRESSES.LIST);
    },

    /**
     * Create new address
     * @param {Object} addressData - Address data
     * @returns {Promise<Object>} Address response
     */
    async create(addressData) {
      return api.post(API_CONFIG.ENDPOINTS.ADDRESSES.CREATE, addressData);
    },

    /**
     * Update address
     * @param {number} id - Address ID
     * @param {Object} addressData - Updated address data
     * @returns {Promise<Object>} Update response
     */
    async update(id, addressData) {
      const endpoint = API_CONFIG.ENDPOINTS.ADDRESSES.UPDATE.replace(':id', id);
      return api.put(endpoint, addressData);
    },

    /**
     * Delete address
     * @param {number} id - Address ID
     * @returns {Promise<Object>} Delete response
     */
    async delete(id) {
      const endpoint = API_CONFIG.ENDPOINTS.ADDRESSES.DELETE.replace(':id', id);
      return api.delete(endpoint);
    }
  },

  // Payments API
  payments: {
    /**
     * Create Razorpay payment
     * @param {Object} paymentData - Payment data
     * @returns {Promise<Object>} Payment response
     */
    async createRazorpay(paymentData) {
      return api.post(API_CONFIG.ENDPOINTS.PAYMENTS.RAZORPAY_CREATE, paymentData);
    },

    /**
     * Verify Razorpay payment
     * @param {Object} verificationData - Verification data
     * @returns {Promise<Object>} Verification response
     */
    async verifyRazorpay(verificationData) {
      return api.post(API_CONFIG.ENDPOINTS.PAYMENTS.RAZORPAY_VERIFY, verificationData);
    },

    /**
     * Process COD payment
     * @param {Object} orderData - Order data
     * @returns {Promise<Object>} COD response
     */
    async processCOD(orderData) {
      return api.post(API_CONFIG.ENDPOINTS.PAYMENTS.COD, orderData);
    }
  }
};

// Export API service
window.ApiService = ApiService;
window.api = api;

// Helper function to check if user is authenticated
window.isAuthenticated = () => {
  return !!api.getAuthToken();
};

// Helper function to get current user data
window.getCurrentUser = () => {
  return CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
};