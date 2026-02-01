<?php
/**
 * Frontend Configuration Manager
 * 
 * This service manages environment-specific configuration for the frontend application.
 * It generates JavaScript configuration based on the current environment, handles API
 * base URL configuration, manages feature flags, and provides configuration endpoints.
 * 
 * Requirements: 3.1, 3.3, 5.1, 5.2
 */

require_once __DIR__ . '/../config/ConfigManager.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/AssetVersionHelper.php';

/**
 * Frontend Configuration Manager Class
 * 
 * Handles generation and serving of environment-specific frontend configuration
 */
class FrontendConfigManager {
    private $configManager;
    private $environment;
    private $baseUrl;
    private $configCache = [];
    private $cacheFile;
    private $assetVersionHelper;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->configManager = ConfigManager::getInstance();
        $this->environment = $this->configManager->get('app.env', 'production');
        $this->baseUrl = $this->configManager->get('app.url', 'https://riyacollections.com');
        $this->cacheFile = __DIR__ . '/../cache/frontend_config.cache';
        $this->assetVersionHelper = new AssetVersionHelper();
        
        Logger::info('FrontendConfigManager initialized', [
            'environment' => $this->environment,
            'base_url' => $this->baseUrl
        ]);
    }
    
    /**
     * Generate complete frontend configuration for the current environment
     * 
     * @param string|null $environment Override environment (optional)
     * @return array Complete frontend configuration
     */
    public function generateConfig($environment = null) {
        $env = $environment ?: $this->environment;
        
        // Check cache first
        $cacheKey = "config_{$env}";
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        try {
            $config = [
                'api' => $this->generateApiConfig($env),
                'app' => $this->generateAppConfig($env),
                'ui' => $this->generateUiConfig($env),
                'features' => $this->getFeatureFlags($env),
                'environment' => $this->generateEnvironmentConfig($env),
                'security' => $this->generateSecurityConfig($env),
                'performance' => $this->generatePerformanceConfig($env),
                'assets' => $this->generateAssetConfig($env)
            ];
            
            // Cache the configuration
            $this->configCache[$cacheKey] = $config;
            
            Logger::info('Frontend configuration generated', [
                'environment' => $env,
                'config_sections' => array_keys($config)
            ]);
            
            return $config;
            
        } catch (Exception $e) {
            Logger::error('Failed to generate frontend configuration', [
                'environment' => $env,
                'error' => $e->getMessage()
            ]);
            
            // Return minimal fallback configuration
            return $this->getFallbackConfig($env);
        }
    }
    
    /**
     * Generate API configuration section
     * 
     * @param string $environment
     * @return array API configuration
     */
    private function generateApiConfig($environment) {
        $baseUrl = $this->getApiBaseUrl($environment);
        
        return [
            'BASE_URL' => $baseUrl,
            'ENDPOINTS' => [
                // Products
                'PRODUCTS' => '/products',
                'PRODUCT_DETAIL' => '/products/:id',
                'PRODUCT_IMAGES' => '/products/:id/images',
                'CATEGORIES' => '/products/categories/all',
                
                // Authentication
                'AUTH' => [
                    'LOGIN' => '/auth/login',
                    'REGISTER' => '/auth/register',
                    'LOGOUT' => '/auth/logout',
                    'PROFILE' => '/auth/profile',
                    'ADMIN_LOGIN' => '/auth/admin/login',
                    'ADMIN_REGISTER' => '/auth/admin/register'
                ],
                
                // Admin
                'ADMIN' => [
                    'DASHBOARD' => '/admin/dashboard',
                    'STATS_ORDERS' => '/admin/stats/orders',
                    'STATS_PRODUCTS' => '/admin/stats/products',
                    'PRODUCTS' => '/products',
                    'PRODUCT_DETAIL' => '/products/:id',
                    'PRODUCT_CREATE' => '/products',
                    'PRODUCT_UPDATE' => '/products/:id',
                    'PRODUCT_DELETE' => '/products/:id',
                    'PRODUCT_IMAGES' => '/products/:id/images',
                    'PRODUCT_IMAGE_UPDATE' => '/products/:productId/images/:imageId',
                    'PRODUCT_IMAGE_DELETE' => '/products/:productId/images/:imageId',
                    'CATEGORIES' => '/products/categories/all',
                    'CATEGORY_CREATE' => '/products/categories',
                    'CATEGORY_UPDATE' => '/products/categories/:id',
                    'CATEGORY_DELETE' => '/products/categories/:id'
                ],
                
                // Cart
                'CART' => [
                    'GET' => '/cart',
                    'ADD' => '/cart/add',
                    'UPDATE' => '/cart/update',
                    'REMOVE' => '/cart/remove',
                    'CLEAR' => '/cart/clear'
                ],
                
                // Addresses
                'ADDRESSES' => [
                    'LIST' => '/addresses',
                    'CREATE' => '/addresses',
                    'UPDATE' => '/addresses/:id',
                    'DELETE' => '/addresses/:id'
                ],
                
                // Orders
                'ORDERS' => [
                    'CREATE' => '/orders',
                    'LIST' => '/orders',
                    'DETAIL' => '/orders/:id'
                ],
                
                // Payments
                'PAYMENTS' => [
                    'RAZORPAY_CREATE' => '/payments/razorpay/create',
                    'RAZORPAY_VERIFY' => '/payments/razorpay/verify',
                    'COD' => '/payments/cod'
                ]
            ],
            'TIMEOUT' => $this->configManager->get('api.timeout', 10000),
            'HEADERS' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'RETRY' => [
                'MAX_ATTEMPTS' => $environment === 'production' ? 3 : 1,
                'DELAY' => 1000,
                'BACKOFF_MULTIPLIER' => 2
            ]
        ];
    }
    
    /**
     * Generate application configuration section
     * 
     * @param string $environment
     * @return array Application configuration
     */
    private function generateAppConfig($environment) {
        return [
            'NAME' => $this->configManager->get('business.company_name', 'Riya Collections'),
            'VERSION' => $this->configManager->get('app.version', '1.0.0'),
            'ENVIRONMENT' => $environment,
            'DEBUG' => $this->configManager->get('app.debug', false) && $environment !== 'production',
            
            'PAGINATION' => [
                'DEFAULT_LIMIT' => $this->configManager->get('api.pagination.default_per_page', 20),
                'MAX_LIMIT' => $this->configManager->get('api.pagination.max_per_page', 100)
            ],
            
            'IMAGES' => [
                'PLACEHOLDER' => '/assets/placeholder.jpg',
                'LAZY_LOAD_THRESHOLD' => 100,
                'SUPPORTED_FORMATS' => $this->configManager->get('upload.limits.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp']),
                'MAX_SIZE' => $this->configManager->get('upload.limits.max_file_size', 5242880),
                'QUALITY' => $this->configManager->get('upload.image.quality', 85)
            ],
            
            'ANIMATIONS' => [
                'DURATION' => [
                    'FAST' => 200,
                    'NORMAL' => 300,
                    'SLOW' => 500
                ],
                'EASING' => 'ease-in-out',
                'ENABLED' => $environment !== 'production' || $this->configManager->get('features.animations', true)
            ],
            
            'STORAGE_KEYS' => [
                'AUTH_TOKEN' => 'riya_auth_token',
                'USER_DATA' => 'riya_user_data',
                'CART_DATA' => 'riya_cart_data',
                'WISHLIST' => 'riya_wishlist',
                'RECENT_SEARCHES' => 'riya_recent_searches',
                'THEME_PREFERENCE' => 'riya_theme'
            ],
            
            'CART' => [
                'MAX_QUANTITY' => 10,
                'MIN_QUANTITY' => 1,
                'AUTO_SAVE_DELAY' => 1000
            ],
            
            'SEARCH' => [
                'MIN_QUERY_LENGTH' => 2,
                'DEBOUNCE_DELAY' => 300,
                'MAX_SUGGESTIONS' => 5
            ],
            
            'NOTIFICATIONS' => [
                'DURATION' => [
                    'SUCCESS' => 3000,
                    'ERROR' => 5000,
                    'WARNING' => 4000,
                    'INFO' => 3000
                ],
                'POSITION' => 'top-right'
            ]
        ];
    }
    
    /**
     * Generate UI configuration section
     * 
     * @param string $environment
     * @return array UI configuration
     */
    private function generateUiConfig($environment) {
        return [
            'BREAKPOINTS' => [
                'MOBILE' => 568,
                'TABLET' => 768,
                'DESKTOP' => 1024,
                'LARGE' => 1200
            ],
            
            'HEADER_HEIGHT' => 56,
            
            'SCROLL' => [
                'SMOOTH_OFFSET' => 80,
                'BACK_TO_TOP_THRESHOLD' => 300
            ],
            
            'CAROUSEL' => [
                'AUTO_PLAY_DELAY' => 5000,
                'TRANSITION_DURATION' => 500,
                'ITEMS_PER_VIEW' => [
                    'MOBILE' => 1,
                    'TABLET' => 2,
                    'DESKTOP' => 3,
                    'LARGE' => 4
                ]
            ],
            
            'MODAL' => [
                'BACKDROP_CLOSE' => true,
                'ESCAPE_CLOSE' => true,
                'FOCUS_TRAP' => true
            ],
            
            'THEME' => [
                'DEFAULT' => 'light',
                'AVAILABLE' => ['light', 'dark'],
                'AUTO_DETECT' => true
            ]
        ];
    }
    
    /**
     * Get feature flags for the environment
     * 
     * @param string $environment
     * @return array Feature flags
     */
    public function getFeatureFlags($environment = null) {
        $env = $environment ?: $this->environment;
        
        $baseFeatures = [
            'WISHLIST' => $this->configManager->get('features.wishlist', true),
            'PRODUCT_COMPARISON' => $this->configManager->get('features.product_comparison', true),
            'REVIEWS' => $this->configManager->get('features.product_reviews', true),
            'SOCIAL_LOGIN' => $this->configManager->get('features.social_login', false),
            'GUEST_CHECKOUT' => $this->configManager->get('features.guest_checkout', true),
            'LIVE_CHAT' => $this->configManager->get('features.live_chat', false),
            'PWA' => $this->configManager->get('features.pwa', false),
            'ANALYTICS' => $this->configManager->get('features.analytics', true),
            
            'PAYMENT_METHODS' => [
                'RAZORPAY' => $this->configManager->get('payment.gateways.razorpay.key_id') !== null,
                'COD' => $this->configManager->get('payment.gateways.cod.enabled', true),
                'WALLET' => $this->configManager->get('features.wallet', false)
            ],
            
            'SOCIAL_SHARING' => [
                'FACEBOOK' => true,
                'TWITTER' => true,
                'WHATSAPP' => true,
                'INSTAGRAM' => false
            ]
        ];
        
        // Environment-specific feature overrides
        if ($env === 'development') {
            $baseFeatures['DEBUG_TOOLS'] = true;
            $baseFeatures['MOCK_PAYMENTS'] = true;
        } elseif ($env === 'production') {
            $baseFeatures['DEBUG_TOOLS'] = false;
            $baseFeatures['MOCK_PAYMENTS'] = false;
        }
        
        return $baseFeatures;
    }
    
    /**
     * Generate environment-specific configuration
     * 
     * @param string $environment
     * @return array Environment configuration
     */
    private function generateEnvironmentConfig($environment) {
        return [
            'NAME' => $environment,
            'IS_DEVELOPMENT' => $environment === 'development',
            'IS_PRODUCTION' => $environment === 'production',
            'IS_TESTING' => $environment === 'testing',
            'DEBUG_MODE' => $this->configManager->get('app.debug', false) && $environment !== 'production',
            'LOG_LEVEL' => $environment === 'production' ? 'error' : 'debug',
            'CACHE_ENABLED' => $environment === 'production',
            'MINIFICATION_ENABLED' => $environment === 'production'
        ];
    }
    
    /**
     * Generate security configuration for frontend
     * 
     * @param string $environment
     * @return array Security configuration
     */
    private function generateSecurityConfig($environment) {
        return [
            'CSRF_PROTECTION' => $environment === 'production',
            'HTTPS_ONLY' => $environment === 'production',
            'SECURE_COOKIES' => $environment === 'production',
            'CONTENT_SECURITY_POLICY' => $environment === 'production',
            'XSS_PROTECTION' => true,
            'FRAME_OPTIONS' => 'DENY',
            'RATE_LIMITING' => [
                'ENABLED' => $this->configManager->get('security.rate_limiting.enabled', true),
                'MAX_REQUESTS' => $this->configManager->get('security.rate_limiting.max_attempts', 100),
                'WINDOW_MS' => $this->configManager->get('security.rate_limiting.window', 900) * 1000
            ]
        ];
    }
    
    /**
     * Generate performance configuration
     * 
     * @param string $environment
     * @return array Performance configuration
     */
    private function generatePerformanceConfig($environment) {
        return [
            'LAZY_LOADING' => true,
            'IMAGE_OPTIMIZATION' => $environment === 'production',
            'COMPRESSION' => $environment === 'production',
            'CACHING' => [
                'ENABLED' => $environment === 'production',
                'DURATION' => $environment === 'production' ? 86400 : 0, // 24 hours in production
                'STRATEGY' => 'cache-first'
            ],
            'PREFETCH' => [
                'ENABLED' => $environment === 'production',
                'CRITICAL_RESOURCES' => ['/api/products', '/api/categories']
            ],
            'MONITORING' => [
                'PERFORMANCE_METRICS' => $environment === 'production',
                'ERROR_TRACKING' => true,
                'ANALYTICS' => $this->configManager->get('features.analytics', true)
            ]
        ];
    }
    
    /**
     * Generate asset configuration with versioning
     * 
     * @param string $environment
     * @return array Asset configuration
     */
    private function generateAssetConfig($environment) {
        // Common assets that need versioning
        $commonAssets = [
            'css/main.css',
            'css/home.css',
            'css/accessibility.css',
            'js/config.js',
            'js/api.js',
            'js/utils.js',
            'js/main.js',
            'js/home.js',
            'logo.svg'
        ];
        
        $assetConfig = [
            'VERSIONING_ENABLED' => $this->configManager->get('assets.versioning.enabled', true),
            'BASE_PATH' => '/assets',
            'MANIFEST' => []
        ];
        
        // Generate versioned URLs for common assets
        if ($assetConfig['VERSIONING_ENABLED']) {
            try {
                $assetConfig['MANIFEST'] = $this->assetVersionHelper->getManifest($commonAssets);
            } catch (Exception $e) {
                Logger::warning('Failed to generate asset manifest', [
                    'error' => $e->getMessage(),
                    'environment' => $environment
                ]);
                
                // Fallback to non-versioned URLs
                $assetConfig['MANIFEST'] = [];
                foreach ($commonAssets as $asset) {
                    $assetConfig['MANIFEST'][$asset] = '/assets/' . $asset;
                }
            }
        } else {
            // Non-versioned URLs
            foreach ($commonAssets as $asset) {
                $assetConfig['MANIFEST'][$asset] = '/assets/' . $asset;
            }
        }
        
        return $assetConfig;
    }
    
    /**
     * Get API base URL for the environment
     * 
     * @param string|null $environment
     * @return string API base URL
     */
    public function getApiBaseUrl($environment = null) {
        $env = $environment ?: $this->environment;
        
        // For integrated structure, API is always at /api
        return '/api';
    }
    
    /**
     * Serve configuration as JavaScript endpoint
     * 
     * @param string|null $environment Override environment
     * @param bool $sendHeaders Whether to send HTTP headers (default: true)
     * @return void
     */
    public function serveConfigEndpoint($environment = null, $sendHeaders = true) {
        try {
            $config = $this->generateConfig($environment);
            
            // Set appropriate headers only if requested
            if ($sendHeaders) {
                header('Content-Type: application/javascript; charset=utf-8');
                header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
                header('Vary: Accept-Encoding');
            }
            
            // Generate JavaScript configuration
            $jsConfig = $this->generateJavaScriptConfig($config);
            
            echo $jsConfig;
            
            Logger::info('Frontend configuration served', [
                'environment' => $environment ?: $this->environment,
                'size' => strlen($jsConfig)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to serve configuration endpoint', [
                'error' => $e->getMessage()
            ]);
            
            // Serve fallback configuration
            if ($sendHeaders) {
                header('Content-Type: application/javascript; charset=utf-8');
            }
            echo $this->generateJavaScriptConfig($this->getFallbackConfig($environment));
        }
    }
    
    /**
     * Generate JavaScript configuration code
     * 
     * @param array $config Configuration array
     * @return string JavaScript code
     */
    private function generateJavaScriptConfig($config) {
        $js = "/**\n";
        $js .= " * Auto-generated Frontend Configuration\n";
        $js .= " * Environment: " . ($config['environment']['NAME'] ?? 'unknown') . "\n";
        $js .= " * Generated: " . date('Y-m-d H:i:s T') . "\n";
        $js .= " */\n\n";
        
        // Generate configuration objects
        $js .= "// API Configuration\n";
        $js .= "window.API_CONFIG = " . json_encode($config['api'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        $js .= "// Application Configuration\n";
        $js .= "window.APP_CONFIG = " . json_encode($config['app'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        $js .= "// UI Configuration\n";
        $js .= "window.UI_CONFIG = " . json_encode($config['ui'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        $js .= "// Feature Flags\n";
        $js .= "window.FEATURES = " . json_encode($config['features'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        $js .= "// Environment Configuration\n";
        $js .= "window.ENVIRONMENT = " . json_encode($config['environment'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        $js .= "// Security Configuration\n";
        $js .= "window.SECURITY_CONFIG = " . json_encode($config['security'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        $js .= "// Performance Configuration\n";
        $js .= "window.PERFORMANCE_CONFIG = " . json_encode($config['performance'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        $js .= "// Asset Configuration\n";
        $js .= "window.ASSET_CONFIG = " . json_encode($config['assets'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";
        
        // Add utility functions
        $js .= $this->generateUtilityFunctions();
        
        // Add environment detection
        $js .= $this->generateEnvironmentDetection();
        
        return $js;
    }
    
    /**
     * Generate utility functions for JavaScript
     * 
     * @return string JavaScript utility functions
     */
    private function generateUtilityFunctions() {
        return <<<'JS'
// Configuration Utility Functions
window.CONFIG_UTILS = {
    /**
     * Get API endpoint URL
     * @param {string} endpoint - Endpoint path
     * @param {Object} params - URL parameters
     * @returns {string} Complete URL
     */
    getApiUrl: function(endpoint, params = {}) {
        let url = window.API_CONFIG.BASE_URL + endpoint;
        
        // Replace URL parameters
        Object.keys(params).forEach(key => {
            url = url.replace(':' + key, params[key]);
        });
        
        return url;
    },
    
    /**
     * Get current breakpoint
     * @returns {string} Current breakpoint name
     */
    getCurrentBreakpoint: function() {
        const width = window.innerWidth;
        
        if (width >= window.UI_CONFIG.BREAKPOINTS.LARGE) return 'large';
        if (width >= window.UI_CONFIG.BREAKPOINTS.DESKTOP) return 'desktop';
        if (width >= window.UI_CONFIG.BREAKPOINTS.TABLET) return 'tablet';
        return 'mobile';
    },
    
    /**
     * Check if feature is enabled
     * @param {string} feature - Feature name
     * @returns {boolean} Feature status
     */
    isFeatureEnabled: function(feature) {
        const keys = feature.split('.');
        let value = window.FEATURES;
        
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
     * Get storage item with fallback
     * @param {string} key - Storage key
     * @param {*} defaultValue - Default value if not found
     * @returns {*} Stored value or default
     */
    getStorageItem: function(key, defaultValue = null) {
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
    setStorageItem: function(key, value) {
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
    removeStorageItem: function(key) {
        try {
            localStorage.removeItem(key);
        } catch (error) {
            console.warn('Error removing from localStorage:', error);
        }
    },
    
    /**
     * Get configuration value using dot notation
     * @param {string} path - Configuration path (e.g., 'app.name')
     * @param {*} defaultValue - Default value if not found
     * @returns {*} Configuration value
     */
    getConfig: function(path, defaultValue = null) {
        const keys = path.split('.');
        const configMap = {
            'api': window.API_CONFIG,
            'app': window.APP_CONFIG,
            'ui': window.UI_CONFIG,
            'features': window.FEATURES,
            'environment': window.ENVIRONMENT,
            'security': window.SECURITY_CONFIG,
            'performance': window.PERFORMANCE_CONFIG,
            'assets': window.ASSET_CONFIG
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
     * Get versioned asset URL
     * @param {string} assetPath - Asset path (e.g., 'css/main.css')
     * @returns {string} Versioned asset URL
     */
    getAssetUrl: function(assetPath) {
        if (window.ASSET_CONFIG && window.ASSET_CONFIG.MANIFEST && window.ASSET_CONFIG.MANIFEST[assetPath]) {
            return window.ASSET_CONFIG.MANIFEST[assetPath];
        }
        
        // Fallback to non-versioned URL
        const basePath = window.ASSET_CONFIG ? window.ASSET_CONFIG.BASE_PATH : '/assets';
        return basePath + '/' + assetPath.replace(/^\/+/, '');
    },
    
    /**
     * Preload critical assets
     * @param {Array} assetPaths - Array of asset paths to preload
     */
    preloadAssets: function(assetPaths) {
        assetPaths.forEach(assetPath => {
            const url = this.getAssetUrl(assetPath);
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = url;
            
            // Determine asset type
            if (assetPath.endsWith('.css')) {
                link.as = 'style';
            } else if (assetPath.endsWith('.js')) {
                link.as = 'script';
            } else if (assetPath.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i)) {
                link.as = 'image';
            } else if (assetPath.match(/\.(woff|woff2|ttf|otf|eot)$/i)) {
                link.as = 'font';
                link.crossOrigin = 'anonymous';
            }
            
            document.head.appendChild(link);
        });
    }
};

JS;
    }
    
    /**
     * Generate environment detection code
     * 
     * @return string JavaScript environment detection
     */
    private function generateEnvironmentDetection() {
        return <<<'JS'
// Environment Detection
window.IS_DEVELOPMENT = window.ENVIRONMENT.IS_DEVELOPMENT;
window.IS_PRODUCTION = window.ENVIRONMENT.IS_PRODUCTION;
window.IS_TESTING = window.ENVIRONMENT.IS_TESTING;

// Console welcome message
if (window.ENVIRONMENT.DEBUG_MODE) {
    console.log('%c' + window.APP_CONFIG.NAME + ' v' + window.APP_CONFIG.VERSION, 
        'color: #E91E63; font-size: 16px; font-weight: bold;');
    console.log('Environment: ' + window.ENVIRONMENT.NAME);
    console.log('Debug mode enabled');
}

// Configuration loaded event
if (typeof window.dispatchEvent === 'function') {
    window.dispatchEvent(new CustomEvent('configLoaded', {
        detail: {
            environment: window.ENVIRONMENT.NAME,
            timestamp: new Date().toISOString()
        }
    }));
}

JS;
    }
    
    /**
     * Get fallback configuration for error cases
     * 
     * @param string|null $environment
     * @return array Minimal fallback configuration
     */
    private function getFallbackConfig($environment = null) {
        $env = $environment ?: 'production';
        
        return [
            'api' => [
                'BASE_URL' => '/api',
                'ENDPOINTS' => [],
                'TIMEOUT' => 10000,
                'HEADERS' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ],
            'app' => [
                'NAME' => 'Riya Collections',
                'VERSION' => '1.0.0',
                'ENVIRONMENT' => $env,
                'DEBUG' => false
            ],
            'ui' => [
                'BREAKPOINTS' => [
                    'MOBILE' => 568,
                    'TABLET' => 768,
                    'DESKTOP' => 1024,
                    'LARGE' => 1200
                ]
            ],
            'features' => [
                'WISHLIST' => true,
                'GUEST_CHECKOUT' => true
            ],
            'environment' => [
                'NAME' => $env,
                'IS_DEVELOPMENT' => $env === 'development',
                'IS_PRODUCTION' => $env === 'production',
                'IS_TESTING' => $env === 'testing',
                'DEBUG_MODE' => false
            ],
            'security' => [
                'HTTPS_ONLY' => true,
                'XSS_PROTECTION' => true
            ],
            'performance' => [
                'LAZY_LOADING' => true,
                'CACHING' => ['ENABLED' => false]
            ]
        ];
    }
    
    /**
     * Clear configuration cache
     * 
     * @return void
     */
    public function clearCache() {
        $this->configCache = [];
        
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        
        Logger::info('Frontend configuration cache cleared');
    }
    
    /**
     * Validate configuration
     * 
     * @param array $config Configuration to validate
     * @return bool True if valid
     * @throws Exception If validation fails
     */
    public function validateConfig($config) {
        $requiredSections = ['api', 'app', 'ui', 'features', 'environment'];
        
        foreach ($requiredSections as $section) {
            if (!isset($config[$section])) {
                throw new Exception("Missing required configuration section: {$section}");
            }
        }
        
        // Validate API configuration
        if (!isset($config['api']['BASE_URL']) || empty($config['api']['BASE_URL'])) {
            throw new Exception("API base URL is required");
        }
        
        // Validate app configuration
        if (!isset($config['app']['NAME']) || empty($config['app']['NAME'])) {
            throw new Exception("Application name is required");
        }
        
        return true;
    }
    
    /**
     * Get configuration summary for monitoring
     * 
     * @return array Configuration summary
     */
    public function getConfigSummary() {
        return [
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'api_base_url' => $this->getApiBaseUrl(),
            'cache_size' => count($this->configCache),
            'last_generated' => date('Y-m-d H:i:s'),
            'feature_flags_count' => count($this->getFeatureFlags()),
            'asset_versioning_enabled' => $this->configManager->get('assets.versioning.enabled', true)
        ];
    }
    
    /**
     * Get versioned asset URL
     * 
     * @param string $assetPath Asset path relative to assets directory
     * @return string Versioned asset URL
     */
    public function getVersionedAssetUrl($assetPath) {
        return $this->assetVersionHelper->asset($assetPath);
    }
    
    /**
     * Generate asset manifest for specific assets
     * 
     * @param array $assetPaths Array of asset paths
     * @return array Asset manifest with versioned URLs
     */
    public function generateAssetManifest($assetPaths) {
        return $this->assetVersionHelper->getManifest($assetPaths);
    }
    
    /**
     * Clear asset version cache
     * 
     * @param string|null $assetPath Optional specific asset to clear
     * @return void
     */
    public function clearAssetCache($assetPath = null) {
        $this->assetVersionHelper->clearCache($assetPath);
    }
    
    /**
     * Serve asset manifest endpoint
     * 
     * @param array $assetPaths Optional array of specific assets
     * @return void
     */
    public function serveAssetManifestEndpoint($assetPaths = []) {
        try {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
            
            $manifest = $this->generateAssetManifest($assetPaths);
            echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            Logger::info('Asset manifest served', [
                'asset_count' => count($manifest)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to serve asset manifest', [
                'error' => $e->getMessage()
            ]);
            
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate asset manifest']);
        }
    }
}