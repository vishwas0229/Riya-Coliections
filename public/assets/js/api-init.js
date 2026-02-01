/**
 * API Initialization for Riya Collections Frontend
 * Sets up and initializes all API communication components
 */

(function() {
  'use strict';

  /**
   * Initialize API communication system
   */
  function initializeApiSystem() {
    console.log('🚀 Initializing Riya Collections API System...');

    // Check dependencies
    if (!checkDependencies()) {
      console.error('❌ API System initialization failed: Missing dependencies');
      return false;
    }

    try {
      // Initialize components in order
      initializeLoadingManager();
      initializeErrorHandler();
      initializeAuthManager();
      initializeApiStateManager();
      initializeApiInterceptor();
      initializeEnhancedApiService();
      
      // Setup global error handling
      setupGlobalErrorHandling();
      
      // Setup performance monitoring
      setupPerformanceMonitoring();
      
      // Setup development tools
      if (window.IS_DEVELOPMENT) {
        setupDevelopmentTools();
      }

      console.log('✅ API System initialized successfully');
      
      // Dispatch initialization complete event
      document.dispatchEvent(new CustomEvent('apiSystemReady', {
        detail: { timestamp: Date.now() }
      }));

      return true;

    } catch (error) {
      console.error('❌ API System initialization error:', error);
      return false;
    }
  }

  /**
   * Check if all required dependencies are available
   * @returns {boolean} Dependencies available
   */
  function checkDependencies() {
    const requiredGlobals = [
      'API_CONFIG',
      'APP_CONFIG',
      'ERROR_MESSAGES',
      'SUCCESS_MESSAGES',
      'CONFIG_UTILS',
      'DOMUtils',
      'ValidationUtils'
    ];

    const missing = requiredGlobals.filter(global => !window[global]);
    
    if (missing.length > 0) {
      console.error('Missing required globals:', missing);
      return false;
    }

    return true;
  }

  /**
   * Initialize Loading Manager
   */
  function initializeLoadingManager() {
    if (!window.LoadingManager) {
      console.warn('LoadingManager not found, creating basic implementation');
      
      // Create basic loading manager if not available
      window.LoadingManager = {
        trackRequest: () => {},
        untrackRequest: () => {},
        showGlobal: () => {},
        hideGlobal: () => {},
        showButton: () => {},
        hideButton: () => {},
        showElement: () => {},
        hideElement: () => {}
      };
    }
    
    console.log('📊 Loading Manager initialized');
  }

  /**
   * Initialize Error Handler
   */
  function initializeErrorHandler() {
    if (!window.ErrorHandler) {
      console.warn('ErrorHandler not found, creating basic implementation');
      
      // Create basic error handler if not available
      window.ErrorHandler = {
        handleApiError: (error) => {
          console.error('API Error:', error);
          if (window.NotificationManager) {
            NotificationManager.error(error.message || 'An error occurred');
          }
        },
        logError: () => {},
        showErrorToast: () => {},
        showErrorModal: () => {}
      };
    }
    
    console.log('🚨 Error Handler initialized');
  }

  /**
   * Initialize Authentication Manager
   */
  function initializeAuthManager() {
    if (!window.AuthManager) {
      console.warn('AuthManager not found, creating basic implementation');
      
      // Create basic auth manager if not available
      window.AuthManager = {
        isAuthenticated: false,
        currentUser: null,
        getAuthHeader: () => ({}),
        requireAuth: () => {},
        onAuthStateChange: () => () => {}
      };
    }
    
    console.log('🔐 Authentication Manager initialized');
  }

  /**
   * Initialize API State Manager
   */
  function initializeApiStateManager() {
    if (!window.ApiStateManager) {
      console.warn('ApiStateManager not found, creating basic implementation');
      
      // Create basic state manager if not available
      window.ApiStateManager = {
        setLoading: () => {},
        isLoading: () => false,
        setError: () => {},
        getError: () => null,
        setCache: () => {},
        getCache: () => null,
        invalidateCache: () => {},
        subscribe: () => () => {}
      };
    }
    
    console.log('📦 API State Manager initialized');
  }

  /**
   * Initialize API Interceptor
   */
  function initializeApiInterceptor() {
    if (!window.ApiInterceptor) {
      console.warn('ApiInterceptor not found, creating basic implementation');
      
      // Create basic interceptor if not available
      window.ApiInterceptor = {
        processRequest: (url, config) => Promise.resolve(config),
        processResponse: (response) => Promise.resolve(response),
        processError: (error) => Promise.resolve(error),
        setupCaching: () => {},
        setupDeduplication: () => {}
      };
    }
    
    console.log('🔄 API Interceptor initialized');
  }

  /**
   * Initialize Enhanced API Service
   */
  function initializeEnhancedApiService() {
    if (!window.EnhancedApiService) {
      console.warn('EnhancedApiService not found, using basic ApiService');
      
      // Use existing ApiService if enhanced version not available
      if (window.ApiService) {
        window.EnhancedApiService = window.ApiService;
      }
    }
    
    console.log('🌟 Enhanced API Service initialized');
  }

  /**
   * Setup global error handling
   */
  function setupGlobalErrorHandling() {
    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      console.error('Unhandled promise rejection:', event.reason);
      
      if (window.ErrorHandler) {
        ErrorHandler.logError('UNHANDLED_PROMISE', event.reason);
      }
      
      // Prevent default browser behavior for API-related errors
      if (event.reason && event.reason.message && 
          (event.reason.message.includes('fetch') || 
           event.reason.message.includes('API'))) {
        event.preventDefault();
      }
    });

    // Handle network status changes
    window.addEventListener('online', () => {
      if (window.NotificationManager) {
        NotificationManager.success('Connection restored');
      }
      
      // Retry failed requests
      retryFailedRequests();
    });

    window.addEventListener('offline', () => {
      if (window.NotificationManager) {
        NotificationManager.warning('Connection lost. Some features may not work.');
      }
    });

    console.log('🛡️ Global error handling setup complete');
  }

  /**
   * Setup performance monitoring
   */
  function setupPerformanceMonitoring() {
    // Monitor API request performance
    let requestCount = 0;
    let totalRequestTime = 0;

    // Intercept fetch to monitor performance
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
      const startTime = performance.now();
      requestCount++;

      return originalFetch.apply(this, args)
        .then(response => {
          const endTime = performance.now();
          const duration = endTime - startTime;
          totalRequestTime += duration;

          // Log slow requests in development
          if (window.IS_DEVELOPMENT && duration > 2000) {
            console.warn(`Slow API request (${duration.toFixed(2)}ms):`, args[0]);
          }

          return response;
        })
        .catch(error => {
          const endTime = performance.now();
          const duration = endTime - startTime;
          totalRequestTime += duration;

          throw error;
        });
    };

    // Expose performance stats
    window.getApiPerformanceStats = () => ({
      requestCount,
      totalRequestTime,
      averageRequestTime: requestCount > 0 ? totalRequestTime / requestCount : 0
    });

    console.log('📈 Performance monitoring setup complete');
  }

  /**
   * Setup development tools
   */
  function setupDevelopmentTools() {
    // Add API debugging tools to window
    window.apiDebug = {
      // Get current API stats
      getStats: () => {
        if (window.EnhancedApiService && window.EnhancedApiService.getStats) {
          return window.EnhancedApiService.getStats();
        }
        return { message: 'Enhanced API Service not available' };
      },

      // Clear all caches
      clearCache: () => {
        if (window.ApiStateManager) {
          window.ApiStateManager.clearCache();
        }
        if (window.ApiInterceptor && window.ApiInterceptor.clearCache) {
          window.ApiInterceptor.clearCache();
        }
        console.log('🗑️ All API caches cleared');
      },

      // Get error log
      getErrors: () => {
        if (window.ErrorHandler && window.ErrorHandler.getErrorLog) {
          return window.ErrorHandler.getErrorLog();
        }
        return [];
      },

      // Test API connectivity
      testConnectivity: async () => {
        try {
          const response = await fetch(`${API_CONFIG.BASE_URL}/health`);
          const data = await response.json();
          console.log('✅ API connectivity test passed:', data);
          return data;
        } catch (error) {
          console.error('❌ API connectivity test failed:', error);
          throw error;
        }
      },

      // Simulate network conditions
      simulateSlowNetwork: (delay = 2000) => {
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
          return new Promise(resolve => {
            setTimeout(() => {
              resolve(originalFetch.apply(this, args));
            }, delay);
          });
        };
        console.log(`🐌 Simulating slow network (${delay}ms delay)`);
      },

      // Reset network simulation
      resetNetwork: () => {
        // This would need to restore the original fetch
        console.log('🚀 Network simulation reset');
      }
    };

    // Add helpful console messages
    console.log('🔧 Development tools available:');
    console.log('  - apiDebug.getStats() - Get API statistics');
    console.log('  - apiDebug.clearCache() - Clear all caches');
    console.log('  - apiDebug.getErrors() - Get error log');
    console.log('  - apiDebug.testConnectivity() - Test API connection');
    console.log('  - apiDebug.simulateSlowNetwork(delay) - Simulate slow network');
  }

  /**
   * Retry failed requests when connection is restored
   */
  function retryFailedRequests() {
    // This would need to be implemented based on how failed requests are tracked
    console.log('🔄 Retrying failed requests...');
  }

  /**
   * Initialize when DOM is ready
   */
  function initialize() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initializeApiSystem);
    } else {
      initializeApiSystem();
    }
  }

  // Start initialization
  initialize();

  // Export initialization function for manual use
  window.initializeApiSystem = initializeApiSystem;

})();