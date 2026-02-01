/**
 * Configuration file for Riya Collections Frontend
 * Contains API endpoints, constants, and application settings
 * 
 * This file now integrates with the FrontendConfigManager for dynamic configuration
 * based on environment detection and server-side configuration management.
 */

// Configuration Loading State
let configLoaded = false;
let configLoadPromise = null;

// Default/Fallback API Configuration
const DEFAULT_API_CONFIG = {
  // Base URL for the backend API (PHP Backend) - Updated for integrated structure
  BASE_URL: '/api',
  
  // API Endpoints
  ENDPOINTS: {
    // Products
    PRODUCTS: '/products',
    PRODUCT_DETAIL: '/products/:id',
    PRODUCT_IMAGES: '/products/:id/images',
    CATEGORIES: '/products/categories/all',
    
    // Authentication
    AUTH: {
      LOGIN: '/auth/login',
      REGISTER: '/auth/register',
      LOGOUT: '/auth/logout',
      PROFILE: '/auth/profile',
      ADMIN_LOGIN: '/auth/admin/login',
      ADMIN_REGISTER: '/auth/admin/register'
    },
    
    // Admin
    ADMIN: {
      DASHBOARD: '/admin/dashboard',
      STATS_ORDERS: '/admin/stats/orders',
      STATS_PRODUCTS: '/admin/stats/products',
      PRODUCTS: '/products',
      PRODUCT_DETAIL: '/products/:id',
      PRODUCT_CREATE: '/products',
      PRODUCT_UPDATE: '/products/:id',
      PRODUCT_DELETE: '/products/:id',
      PRODUCT_IMAGES: '/products/:id/images',
      PRODUCT_IMAGE_UPDATE: '/products/:productId/images/:imageId',
      PRODUCT_IMAGE_DELETE: '/products/:productId/images/:imageId',
      CATEGORIES: '/products/categories/all',
      CATEGORY_CREATE: '/products/categories',
      CATEGORY_UPDATE: '/products/categories/:id',
      CATEGORY_DELETE: '/products/categories/:id'
    },
    
    // Cart
    CART: {
      GET: '/cart',
      ADD: '/cart/add',
      UPDATE: '/cart/update',
      REMOVE: '/cart/remove',
      CLEAR: '/cart/clear'
    },
    
    // Addresses
    ADDRESSES: {
      LIST: '/addresses',
      CREATE: '/addresses',
      UPDATE: '/addresses/:id',
      DELETE: '/addresses/:id'
    },
    
    // Orders
    ORDERS: {
      CREATE: '/orders',
      LIST: '/orders',
      DETAIL: '/orders/:id'
    },
    
    // Payments
    PAYMENTS: {
      RAZORPAY_CREATE: '/payments/razorpay/create',
      RAZORPAY_VERIFY: '/payments/razorpay/verify',
      COD: '/payments/cod'
    }
  },
  
  // Request timeout in milliseconds
  TIMEOUT: 10000,
  
  // Default headers
  HEADERS: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  
  // Retry configuration
  RETRY: {
    MAX_ATTEMPTS: 3,
    DELAY: 1000,
    BACKOFF_MULTIPLIER: 2
  }
};

// Default/Fallback Application Configuration
const DEFAULT_APP_CONFIG = {
  // Application name
  NAME: 'Riya Collections',
  
  // Version
  VERSION: '1.0.0',
  
  // Environment (will be updated by server configuration)
  ENVIRONMENT: 'production',
  DEBUG: false,
  
  // Default pagination
  PAGINATION: {
    DEFAULT_LIMIT: 20,
    MAX_LIMIT: 100
  },
  
  // Image settings
  IMAGES: {
    PLACEHOLDER: '/assets/placeholder.jpg', // Updated path for integrated structure
    LAZY_LOAD_THRESHOLD: 100, // pixels
    SUPPORTED_FORMATS: ['jpg', 'jpeg', 'png', 'webp'],
    MAX_SIZE: 5242880, // 5MB default
    QUALITY: 85
  },
  
  // Animation settings
  ANIMATIONS: {
    DURATION: {
      FAST: 200,
      NORMAL: 300,
      SLOW: 500
    },
    EASING: 'ease-in-out',
    ENABLED: true
  },
  
  // Local storage keys
  STORAGE_KEYS: {
    AUTH_TOKEN: 'riya_auth_token',
    USER_DATA: 'riya_user_data',
    CART_DATA: 'riya_cart_data',
    WISHLIST: 'riya_wishlist',
    RECENT_SEARCHES: 'riya_recent_searches',
    THEME_PREFERENCE: 'riya_theme'
  },
  
  // Cart settings
  CART: {
    MAX_QUANTITY: 10,
    MIN_QUANTITY: 1,
    AUTO_SAVE_DELAY: 1000 // milliseconds
  },
  
  // Search settings
  SEARCH: {
    MIN_QUERY_LENGTH: 2,
    DEBOUNCE_DELAY: 300, // milliseconds
    MAX_SUGGESTIONS: 5
  },
  
  // Notification settings
  NOTIFICATIONS: {
    DURATION: {
      SUCCESS: 3000,
      ERROR: 5000,
      WARNING: 4000,
      INFO: 3000
    },
    POSITION: 'top-right'
  }
};

// Default/Fallback UI Configuration
const DEFAULT_UI_CONFIG = {
  // Breakpoints (should match CSS)
  BREAKPOINTS: {
    MOBILE: 568,
    TABLET: 768,
    DESKTOP: 1024,
    LARGE: 1200
  },
  
  // Header height
  HEADER_HEIGHT: 56, // pixels
  
  // Scroll settings
  SCROLL: {
    SMOOTH_OFFSET: 80, // pixels above target
    BACK_TO_TOP_THRESHOLD: 300 // pixels scrolled before showing button
  },
  
  // Carousel settings
  CAROUSEL: {
    AUTO_PLAY_DELAY: 5000, // milliseconds
    TRANSITION_DURATION: 500, // milliseconds
    ITEMS_PER_VIEW: {
      MOBILE: 1,
      TABLET: 2,
      DESKTOP: 3,
      LARGE: 4
    }
  },
  
  // Modal settings
  MODAL: {
    BACKDROP_CLOSE: true,
    ESCAPE_CLOSE: true,
    FOCUS_TRAP: true
  },
  
  // Theme settings
  THEME: {
    DEFAULT: 'light',
    AVAILABLE: ['light', 'dark'],
    AUTO_DETECT: true
  }
};

// Default/Fallback Feature Flags
const DEFAULT_FEATURES = {
  // Enable/disable features
  WISHLIST: true,
  PRODUCT_COMPARISON: true,
  REVIEWS: true,
  SOCIAL_LOGIN: false,
  GUEST_CHECKOUT: true,
  LIVE_CHAT: false,
  PWA: false,
  ANALYTICS: true,
  
  // Payment methods
  PAYMENT_METHODS: {
    RAZORPAY: true,
    COD: true,
    WALLET: false
  },
  
  // Social sharing
  SOCIAL_SHARING: {
    FACEBOOK: true,
    TWITTER: true,
    WHATSAPP: true,
    INSTAGRAM: false
  }
};

// Environment Detection and Configuration
const ENVIRONMENT_CONFIG = {
  // Environment detection
  detectEnvironment() {
    const hostname = window.location.hostname;
    const protocol = window.location.protocol;
    
    // Development environment detection
    if (hostname === 'localhost' || 
        hostname === '127.0.0.1' || 
        hostname === '' ||
        hostname.includes('.local') ||
        hostname.includes('.dev')) {
      return 'development';
    }
    
    // Staging environment detection
    if (hostname.includes('staging') || 
        hostname.includes('test') ||
        hostname.includes('dev.')) {
      return 'staging';
    }
    
    // Production environment (default)
    return 'production';
  },
  
  // Get environment-specific API base URL
  getApiBaseUrl(environment = null) {
    const env = environment || this.detectEnvironment();
    
    // For integrated structure, API is always at /api regardless of environment
    return '/api';
  },
  
  // Check if HTTPS is required
  requiresHttps(environment = null) {
    const env = environment || this.detectEnvironment();
    return env === 'production';
  },
  
  // Get debug mode status
  isDebugMode(environment = null) {
    const env = environment || this.detectEnvironment();
    return env === 'development' || env === 'staging';
  }
};

// Configuration Loading and Management
const CONFIG_LOADER = {
  // Load configuration from server
  async loadServerConfig() {
    if (configLoaded) {
      return Promise.resolve();
    }
    
    if (configLoadPromise) {
      return configLoadPromise;
    }
    
    configLoadPromise = this._fetchServerConfig();
    return configLoadPromise;
  },
  
  // Internal method to fetch configuration from server
  async _fetchServerConfig() {
    try {
      const response = await fetch('/api/config', {
        method: 'GET',
        headers: {
          'Accept': 'application/javascript, application/json',
          'Cache-Control': 'no-cache'
        },
        credentials: 'same-origin'
      });
      
      if (response.ok) {
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/javascript')) {
          // Server returned JavaScript configuration
          const configScript = await response.text();
          this._executeConfigScript(configScript);
        } else if (contentType && contentType.includes('application/json')) {
          // Server returned JSON configuration
          const configData = await response.json();
          this._applyJsonConfig(configData);
        }
        
        configLoaded = true;
        this._dispatchConfigLoadedEvent();
        
        console.log('Server configuration loaded successfully');
      } else {
        console.warn('Failed to load server configuration, using defaults');
        this._initializeDefaultConfig();
      }
    } catch (error) {
      console.warn('Error loading server configuration:', error);
      this._initializeDefaultConfig();
    }
  },
  
  // Execute JavaScript configuration from server
  _executeConfigScript(script) {
    try {
      // Create a script element and execute it
      const scriptElement = document.createElement('script');
      scriptElement.textContent = script;
      document.head.appendChild(scriptElement);
      document.head.removeChild(scriptElement);
    } catch (error) {
      console.error('Error executing server configuration script:', error);
      this._initializeDefaultConfig();
    }
  },
  
  // Apply JSON configuration from server
  _applyJsonConfig(config) {
    try {
      // Merge server configuration with defaults
      if (config.api) {
        Object.assign(window.API_CONFIG || {}, DEFAULT_API_CONFIG, config.api);
      }
      if (config.app) {
        Object.assign(window.APP_CONFIG || {}, DEFAULT_APP_CONFIG, config.app);
      }
      if (config.ui) {
        Object.assign(window.UI_CONFIG || {}, DEFAULT_UI_CONFIG, config.ui);
      }
      if (config.features) {
        Object.assign(window.FEATURES || {}, DEFAULT_FEATURES, config.features);
      }
      
      // Set additional configuration objects
      if (config.environment) {
        window.ENVIRONMENT = config.environment;
      }
      if (config.security) {
        window.SECURITY_CONFIG = config.security;
      }
      if (config.performance) {
        window.PERFORMANCE_CONFIG = config.performance;
      }
    } catch (error) {
      console.error('Error applying JSON configuration:', error);
      this._initializeDefaultConfig();
    }
  },
  
  // Initialize default configuration
  _initializeDefaultConfig() {
    const environment = ENVIRONMENT_CONFIG.detectEnvironment();
    
    // Set up default configuration objects
    window.API_CONFIG = { ...DEFAULT_API_CONFIG };
    window.APP_CONFIG = { 
      ...DEFAULT_APP_CONFIG, 
      ENVIRONMENT: environment,
      DEBUG: ENVIRONMENT_CONFIG.isDebugMode(environment)
    };
    window.UI_CONFIG = { ...DEFAULT_UI_CONFIG };
    window.FEATURES = { ...DEFAULT_FEATURES };
    
    // Set environment configuration
    window.ENVIRONMENT = {
      NAME: environment,
      IS_DEVELOPMENT: environment === 'development',
      IS_PRODUCTION: environment === 'production',
      IS_TESTING: environment === 'testing',
      DEBUG_MODE: ENVIRONMENT_CONFIG.isDebugMode(environment)
    };
    
    // Set basic security configuration
    window.SECURITY_CONFIG = {
      HTTPS_ONLY: ENVIRONMENT_CONFIG.requiresHttps(environment),
      XSS_PROTECTION: true,
      CSRF_PROTECTION: environment === 'production'
    };
    
    // Set basic performance configuration
    window.PERFORMANCE_CONFIG = {
      LAZY_LOADING: true,
      CACHING: {
        ENABLED: environment === 'production'
      }
    };
    
    configLoaded = true;
    this._dispatchConfigLoadedEvent();
  },
  
  // Dispatch configuration loaded event
  _dispatchConfigLoadedEvent() {
    if (typeof window.dispatchEvent === 'function') {
      window.dispatchEvent(new CustomEvent('configLoaded', {
        detail: {
          environment: window.ENVIRONMENT?.NAME || 'unknown',
          timestamp: new Date().toISOString(),
          source: configLoaded ? 'server' : 'default'
        }
      }));
    }
  }
};

// Error Messages
const ERROR_MESSAGES = {
  NETWORK: 'Network error. Please check your connection and try again.',
  SERVER: 'Server error. Please try again later.',
  VALIDATION: 'Please check your input and try again.',
  AUTHENTICATION: 'Please log in to continue.',
  AUTHORIZATION: 'You are not authorized to perform this action.',
  NOT_FOUND: 'The requested resource was not found.',
  TIMEOUT: 'Request timed out. Please try again.',
  GENERIC: 'Something went wrong. Please try again.',
  
  // Form validation
  REQUIRED_FIELD: 'This field is required.',
  INVALID_EMAIL: 'Please enter a valid email address.',
  INVALID_PHONE: 'Please enter a valid phone number.',
  PASSWORD_TOO_SHORT: 'Password must be at least 8 characters long.',
  PASSWORDS_DONT_MATCH: 'Passwords do not match.',
  
  // Cart errors
  OUT_OF_STOCK: 'This product is out of stock.',
  MAX_QUANTITY_EXCEEDED: 'Maximum quantity exceeded.',
  CART_EMPTY: 'Your cart is empty.',
  
  // Payment errors
  PAYMENT_FAILED: 'Payment failed. Please try again.',
  INVALID_PAYMENT_METHOD: 'Invalid payment method selected.'
};

// Success Messages
const SUCCESS_MESSAGES = {
  PRODUCT_ADDED_TO_CART: 'Product added to cart successfully!',
  PRODUCT_REMOVED_FROM_CART: 'Product removed from cart.',
  CART_UPDATED: 'Cart updated successfully.',
  ORDER_PLACED: 'Order placed successfully!',
  PROFILE_UPDATED: 'Profile updated successfully.',
  PASSWORD_CHANGED: 'Password changed successfully.',
  NEWSLETTER_SUBSCRIBED: 'Successfully subscribed to newsletter!',
  LOGOUT_SUCCESS: 'Logged out successfully.'
};

// Enhanced Utility Functions
const CONFIG_UTILS = {
  /**
   * Get API endpoint URL
   * @param {string} endpoint - Endpoint path
   * @param {Object} params - URL parameters
   * @returns {string} Complete URL
   */
  getApiUrl(endpoint, params = {}) {
    const apiConfig = window.API_CONFIG || DEFAULT_API_CONFIG;
    let url = apiConfig.BASE_URL + endpoint;
    
    // Replace URL parameters
    Object.keys(params).forEach(key => {
      url = url.replace(`:${key}`, params[key]);
    });
    
    return url;
  },
  
  /**
   * Get current breakpoint
   * @returns {string} Current breakpoint name
   */
  getCurrentBreakpoint() {
    const uiConfig = window.UI_CONFIG || DEFAULT_UI_CONFIG;
    const width = window.innerWidth;
    
    if (width >= uiConfig.BREAKPOINTS.LARGE) return 'large';
    if (width >= uiConfig.BREAKPOINTS.DESKTOP) return 'desktop';
    if (width >= uiConfig.BREAKPOINTS.TABLET) return 'tablet';
    return 'mobile';
  },
  
  /**
   * Check if feature is enabled (supports dot notation)
   * @param {string} feature - Feature name (e.g., 'PAYMENT_METHODS.RAZORPAY')
   * @returns {boolean} Feature status
   */
  isFeatureEnabled(feature) {
    const features = window.FEATURES || DEFAULT_FEATURES;
    const keys = feature.split('.');
    let value = features;
    
    for (const key of keys) {
      if (value && typeof value === 'object' && key in value) {
        value = value[key];
      } else {
        return false;
      }
    }
    
    return value === true;
  },
  
  /**
   * Get configuration value using dot notation
   * @param {string} path - Configuration path (e.g., 'app.NAME', 'api.TIMEOUT')
   * @param {*} defaultValue - Default value if not found
   * @returns {*} Configuration value
   */
  getConfig(path, defaultValue = null) {
    const keys = path.split('.');
    const configMap = {
      'api': window.API_CONFIG || DEFAULT_API_CONFIG,
      'app': window.APP_CONFIG || DEFAULT_APP_CONFIG,
      'ui': window.UI_CONFIG || DEFAULT_UI_CONFIG,
      'features': window.FEATURES || DEFAULT_FEATURES,
      'environment': window.ENVIRONMENT || {},
      'security': window.SECURITY_CONFIG || {},
      'performance': window.PERFORMANCE_CONFIG || {}
    };
    
    let value = configMap[keys[0]];
    
    for (let i = 1; i < keys.length; i++) {
      if (value && typeof value === 'object' && keys[i] in value) {
        value = value[keys[i]];
      } else {
        return defaultValue;
      }
    }
    
    return value !== undefined ? value : defaultValue;
  },
  
  /**
   * Get storage item with fallback
   * @param {string} key - Storage key
   * @param {*} defaultValue - Default value if not found
   * @returns {*} Stored value or default
   */
  getStorageItem(key, defaultValue = null) {
    try {
      const item = localStorage.getItem(key);
      return item ? JSON.parse(item) : defaultValue;
    } catch (error) {
      console.warn('Error reading from localStorage:', error);
      return defaultValue;
    }
  },
  
  /**
   * Set storage item
   * @param {string} key - Storage key
   * @param {*} value - Value to store
   */
  setStorageItem(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.warn('Error writing to localStorage:', error);
    }
  },
  
  /**
   * Remove storage item
   * @param {string} key - Storage key
   */
  removeStorageItem(key) {
    try {
      localStorage.removeItem(key);
    } catch (error) {
      console.warn('Error removing from localStorage:', error);
    }
  },
  
  /**
   * Wait for configuration to be loaded
   * @returns {Promise} Promise that resolves when configuration is loaded
   */
  waitForConfig() {
    if (configLoaded) {
      return Promise.resolve();
    }
    
    return new Promise((resolve) => {
      const handleConfigLoaded = () => {
        window.removeEventListener('configLoaded', handleConfigLoaded);
        resolve();
      };
      
      window.addEventListener('configLoaded', handleConfigLoaded);
      
      // Fallback timeout
      setTimeout(() => {
        window.removeEventListener('configLoaded', handleConfigLoaded);
        resolve();
      }, 5000);
    });
  },
  
  /**
   * Reload configuration from server
   * @returns {Promise} Promise that resolves when configuration is reloaded
   */
  async reloadConfig() {
    configLoaded = false;
    configLoadPromise = null;
    return CONFIG_LOADER.loadServerConfig();
  },
  
  /**
   * Get current environment
   * @returns {string} Current environment name
   */
  getEnvironment() {
    return window.ENVIRONMENT?.NAME || ENVIRONMENT_CONFIG.detectEnvironment();
  },
  
  /**
   * Check if in development mode
   * @returns {boolean} True if in development mode
   */
  isDevelopment() {
    return window.ENVIRONMENT?.IS_DEVELOPMENT || ENVIRONMENT_CONFIG.detectEnvironment() === 'development';
  },
  
  /**
   * Check if in production mode
   * @returns {boolean} True if in production mode
   */
  isProduction() {
    return window.ENVIRONMENT?.IS_PRODUCTION || ENVIRONMENT_CONFIG.detectEnvironment() === 'production';
  }
};

// Configuration Initialization and Export
(function initializeConfiguration() {
  // Initialize default configuration immediately
  CONFIG_LOADER._initializeDefaultConfig();
  
  // Export configuration objects to window
  window.API_CONFIG = window.API_CONFIG || DEFAULT_API_CONFIG;
  window.APP_CONFIG = window.APP_CONFIG || DEFAULT_APP_CONFIG;
  window.UI_CONFIG = window.UI_CONFIG || DEFAULT_UI_CONFIG;
  window.FEATURES = window.FEATURES || DEFAULT_FEATURES;
  window.ERROR_MESSAGES = ERROR_MESSAGES;
  window.SUCCESS_MESSAGES = SUCCESS_MESSAGES;
  window.CONFIG_UTILS = CONFIG_UTILS;
  window.ENVIRONMENT_CONFIG = ENVIRONMENT_CONFIG;
  window.CONFIG_LOADER = CONFIG_LOADER;
  
  // Legacy compatibility
  window.IS_DEVELOPMENT = CONFIG_UTILS.isDevelopment();
  
  // Load server configuration asynchronously
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      CONFIG_LOADER.loadServerConfig();
    });
  } else {
    CONFIG_LOADER.loadServerConfig();
  }
  
  // Console welcome message
  if (CONFIG_UTILS.isDevelopment() || window.ENVIRONMENT?.DEBUG_MODE) {
    const appConfig = window.APP_CONFIG || DEFAULT_APP_CONFIG;
    console.log(`%c${appConfig.NAME} v${appConfig.VERSION}`, 
      'color: #E91E63; font-size: 16px; font-weight: bold;');
    console.log(`Environment: ${CONFIG_UTILS.getEnvironment()}`);
    console.log('Configuration system initialized');
    
    if (CONFIG_UTILS.isDevelopment()) {
      console.log('Development mode enabled');
    }
  }
})();

// Configuration ready promise for external scripts
window.configReady = CONFIG_UTILS.waitForConfig();