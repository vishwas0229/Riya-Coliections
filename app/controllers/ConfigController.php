<?php
/**
 * Configuration Controller
 * 
 * Handles frontend configuration requests and serves environment-specific
 * configuration through the FrontendConfigManager.
 * 
 * Requirements: 3.1, 3.2, 5.4
 */

require_once __DIR__ . '/../services/FrontendConfigManager.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Configuration Controller Class
 * 
 * Provides endpoints for frontend configuration management
 */
class ConfigController {
    private $configManager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->configManager = new FrontendConfigManager();
    }
    
    /**
     * Get frontend configuration
     * 
     * Serves JavaScript configuration for the frontend application
     * based on the current environment and server settings.
     * 
     * @return void Outputs JavaScript configuration directly
     */
    public function getFrontendConfig() {
        try {
            // Get environment parameter if provided
            $environment = $_GET['env'] ?? null;
            
            // Validate environment parameter
            if ($environment && !in_array($environment, ['development', 'staging', 'production'])) {
                Logger::warning('Invalid environment parameter in config request', [
                    'environment' => $environment,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                $environment = null; // Use default environment
            }
            
            // Log configuration request
            Logger::info('Frontend configuration requested', [
                'environment' => $environment,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'referer' => $_SERVER['HTTP_REFERER'] ?? null
            ]);
            
            // Serve configuration through FrontendConfigManager
            $this->configManager->serveConfigEndpoint($environment, true);
            
        } catch (Exception $e) {
            Logger::error('Failed to serve frontend configuration', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'environment' => $environment ?? 'default'
            ]);
            
            // Set appropriate headers for error response
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            
            // Serve minimal fallback configuration
            echo $this->getFallbackConfigScript();
        }
    }
    
    /**
     * Get configuration summary (JSON endpoint for debugging)
     * 
     * @return void Outputs JSON response
     */
    public function getConfigSummary() {
        try {
            $summary = $this->configManager->getConfigSummary();
            
            Logger::info('Configuration summary requested', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            Response::success($summary, 'Configuration summary retrieved successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to get configuration summary', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to retrieve configuration summary');
        }
    }
    
    /**
     * Reload configuration cache
     * 
     * @return void Outputs JSON response
     */
    public function reloadConfig() {
        try {
            $this->configManager->clearCache();
            
            Logger::info('Configuration cache cleared', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            Response::success(null, 'Configuration cache cleared successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to clear configuration cache', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to clear configuration cache');
        }
    }
    
    /**
     * Get fallback configuration script for error cases
     * 
     * @return string JavaScript configuration
     */
    private function getFallbackConfigScript() {
        return <<<'JS'
/**
 * Fallback Frontend Configuration
 * This configuration is used when the server configuration cannot be loaded.
 */

// Minimal API Configuration
window.API_CONFIG = {
    BASE_URL: '/api',
    ENDPOINTS: {},
    TIMEOUT: 10000,
    HEADERS: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
};

// Minimal Application Configuration
window.APP_CONFIG = {
    NAME: 'Riya Collections',
    VERSION: '1.0.0',
    ENVIRONMENT: 'production',
    DEBUG: false
};

// Minimal UI Configuration
window.UI_CONFIG = {
    BREAKPOINTS: {
        MOBILE: 568,
        TABLET: 768,
        DESKTOP: 1024,
        LARGE: 1200
    }
};

// Minimal Feature Flags
window.FEATURES = {
    WISHLIST: true,
    GUEST_CHECKOUT: true
};

// Environment Configuration
window.ENVIRONMENT = {
    NAME: 'production',
    IS_DEVELOPMENT: false,
    IS_PRODUCTION: true,
    IS_TESTING: false,
    DEBUG_MODE: false
};

// Basic utility functions
window.CONFIG_UTILS = {
    getApiUrl: function(endpoint, params = {}) {
        let url = window.API_CONFIG.BASE_URL + endpoint;
        Object.keys(params).forEach(key => {
            url = url.replace(':' + key, params[key]);
        });
        return url;
    },
    
    isFeatureEnabled: function(feature) {
        return window.FEATURES[feature] === true;
    },
    
    getConfig: function(path, defaultValue = null) {
        return defaultValue;
    }
};

// Legacy compatibility
window.IS_DEVELOPMENT = false;

// Log fallback usage
console.warn('Using fallback configuration - server configuration could not be loaded');

// Dispatch configuration loaded event
if (typeof window.dispatchEvent === 'function') {
    window.dispatchEvent(new CustomEvent('configLoaded', {
        detail: {
            environment: 'production',
            timestamp: new Date().toISOString(),
            source: 'fallback'
        }
    }));
}
JS;
    }
}