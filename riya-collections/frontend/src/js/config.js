/**
 * Configuration file for Riya Collections Frontend
 * Contains API endpoints, constants, and application settings
 */

// API Configuration
const API_CONFIG = {
  // Base URL for the backend API
  BASE_URL: 'http://localhost:5000/api',
  
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
  }
};

// Application Constants
const APP_CONFIG = {
  // Application name
  NAME: 'Riya Collections',
  
  // Version
  VERSION: '1.0.0',
  
  // Default pagination
  PAGINATION: {
    DEFAULT_LIMIT: 20,
    MAX_LIMIT: 100
  },
  
  // Image settings
  IMAGES: {
    PLACEHOLDER: 'assets/placeholder.jpg',
    LAZY_LOAD_THRESHOLD: 100, // pixels
    SUPPORTED_FORMATS: ['jpg', 'jpeg', 'png', 'webp']
  },
  
  // Animation settings
  ANIMATIONS: {
    DURATION: {
      FAST: 200,
      NORMAL: 300,
      SLOW: 500
    },
    EASING: 'ease-in-out'
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

// UI Constants
const UI_CONFIG = {
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
  }
};

// Feature Flags
const FEATURES = {
  // Enable/disable features
  WISHLIST: true,
  PRODUCT_COMPARISON: true,
  REVIEWS: true,
  SOCIAL_LOGIN: false,
  GUEST_CHECKOUT: true,
  LIVE_CHAT: false,
  PWA: false,
  
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

// Utility Functions
const CONFIG_UTILS = {
  /**
   * Get API endpoint URL
   * @param {string} endpoint - Endpoint path
   * @param {Object} params - URL parameters
   * @returns {string} Complete URL
   */
  getApiUrl(endpoint, params = {}) {
    let url = API_CONFIG.BASE_URL + endpoint;
    
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
    const width = window.innerWidth;
    
    if (width >= UI_CONFIG.BREAKPOINTS.LARGE) return 'large';
    if (width >= UI_CONFIG.BREAKPOINTS.DESKTOP) return 'desktop';
    if (width >= UI_CONFIG.BREAKPOINTS.TABLET) return 'tablet';
    return 'mobile';
  },
  
  /**
   * Check if feature is enabled
   * @param {string} feature - Feature name
   * @returns {boolean} Feature status
   */
  isFeatureEnabled(feature) {
    return FEATURES[feature] === true;
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
  }
};

// Export configuration objects
window.API_CONFIG = API_CONFIG;
window.APP_CONFIG = APP_CONFIG;
window.UI_CONFIG = UI_CONFIG;
window.FEATURES = FEATURES;
window.ERROR_MESSAGES = ERROR_MESSAGES;
window.SUCCESS_MESSAGES = SUCCESS_MESSAGES;
window.CONFIG_UTILS = CONFIG_UTILS;

// Development mode detection
window.IS_DEVELOPMENT = window.location.hostname === 'localhost' || 
                       window.location.hostname === '127.0.0.1' ||
                       window.location.hostname === '';

// Console welcome message
if (window.IS_DEVELOPMENT) {
  console.log(`%c${APP_CONFIG.NAME} v${APP_CONFIG.VERSION}`, 
    'color: #E91E63; font-size: 16px; font-weight: bold;');
  console.log('Development mode enabled');
}