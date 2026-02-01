<?php
/**
 * Unit Tests for FrontendConfigManager
 * 
 * Tests the frontend configuration management functionality including
 * environment-specific configuration generation, API base URL handling,
 * feature flags management, and configuration endpoint serving.
 */

require_once __DIR__ . '/../app/services/FrontendConfigManager.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class FrontendConfigManagerTest extends TestCase {
    private $configManager;
    private $originalEnv;
    
    protected function setUp(): void {
        // Store original environment
        $this->originalEnv = $_ENV;
        
        // Set up test environment variables
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_NAME'] = 'Test Riya Collections';
        $_ENV['APP_URL'] = 'https://test.riyacollections.com';
        $_ENV['APP_DEBUG'] = 'true';
        
        $this->configManager = new FrontendConfigManager();
    }
    
    protected function tearDown(): void {
        // Restore original environment
        $_ENV = $this->originalEnv;
    }
    
    /**
     * Test basic configuration generation
     */
    public function testGenerateConfig() {
        $config = $this->configManager->generateConfig('development');
        
        // Test that all required sections are present
        $this->assertArrayHasKey('api', $config);
        $this->assertArrayHasKey('app', $config);
        $this->assertArrayHasKey('ui', $config);
        $this->assertArrayHasKey('features', $config);
        $this->assertArrayHasKey('environment', $config);
        $this->assertArrayHasKey('security', $config);
        $this->assertArrayHasKey('performance', $config);
        
        // Test API configuration
        $this->assertEquals('/api', $config['api']['BASE_URL']);
        $this->assertArrayHasKey('ENDPOINTS', $config['api']);
        $this->assertArrayHasKey('TIMEOUT', $config['api']);
        
        // Test app configuration
        $this->assertArrayHasKey('NAME', $config['app']);
        $this->assertArrayHasKey('VERSION', $config['app']);
        $this->assertEquals('development', $config['app']['ENVIRONMENT']);
        
        // Test environment configuration
        $this->assertEquals('development', $config['environment']['NAME']);
        $this->assertTrue($config['environment']['IS_DEVELOPMENT']);
        $this->assertFalse($config['environment']['IS_PRODUCTION']);
    }
    
    /**
     * Test production environment configuration
     */
    public function testProductionEnvironmentConfig() {
        $config = $this->configManager->generateConfig('production');
        
        // Test production-specific settings
        $this->assertEquals('production', $config['environment']['NAME']);
        $this->assertFalse($config['environment']['IS_DEVELOPMENT']);
        $this->assertTrue($config['environment']['IS_PRODUCTION']);
        $this->assertFalse($config['environment']['DEBUG_MODE']);
        
        // Test security settings for production
        $this->assertTrue($config['security']['HTTPS_ONLY']);
        $this->assertTrue($config['security']['SECURE_COOKIES']);
        $this->assertTrue($config['security']['CONTENT_SECURITY_POLICY']);
        
        // Test performance settings for production
        $this->assertTrue($config['performance']['IMAGE_OPTIMIZATION']);
        $this->assertTrue($config['performance']['COMPRESSION']);
        $this->assertTrue($config['performance']['CACHING']['ENABLED']);
    }
    
    /**
     * Test development environment configuration
     */
    public function testDevelopmentEnvironmentConfig() {
        $config = $this->configManager->generateConfig('development');
        
        // Test development-specific settings
        $this->assertEquals('development', $config['environment']['NAME']);
        $this->assertTrue($config['environment']['IS_DEVELOPMENT']);
        $this->assertFalse($config['environment']['IS_PRODUCTION']);
        
        // Test feature flags for development
        $this->assertTrue($config['features']['DEBUG_TOOLS']);
        $this->assertTrue($config['features']['MOCK_PAYMENTS']);
        
        // Test security settings for development
        $this->assertFalse($config['security']['HTTPS_ONLY']);
        $this->assertFalse($config['security']['SECURE_COOKIES']);
        
        // Test performance settings for development
        $this->assertFalse($config['performance']['IMAGE_OPTIMIZATION']);
        $this->assertFalse($config['performance']['COMPRESSION']);
        $this->assertFalse($config['performance']['CACHING']['ENABLED']);
    }
    
    /**
     * Test API base URL generation
     */
    public function testGetApiBaseUrl() {
        // Test that API base URL is always /api for integrated structure
        $this->assertEquals('/api', $this->configManager->getApiBaseUrl('development'));
        $this->assertEquals('/api', $this->configManager->getApiBaseUrl('production'));
        $this->assertEquals('/api', $this->configManager->getApiBaseUrl('testing'));
        $this->assertEquals('/api', $this->configManager->getApiBaseUrl());
    }
    
    /**
     * Test feature flags generation
     */
    public function testGetFeatureFlags() {
        $flags = $this->configManager->getFeatureFlags('production');
        
        // Test basic feature flags
        $this->assertArrayHasKey('WISHLIST', $flags);
        $this->assertArrayHasKey('GUEST_CHECKOUT', $flags);
        $this->assertArrayHasKey('PAYMENT_METHODS', $flags);
        $this->assertArrayHasKey('SOCIAL_SHARING', $flags);
        
        // Test payment methods
        $this->assertArrayHasKey('RAZORPAY', $flags['PAYMENT_METHODS']);
        $this->assertArrayHasKey('COD', $flags['PAYMENT_METHODS']);
        $this->assertArrayHasKey('WALLET', $flags['PAYMENT_METHODS']);
        
        // Test social sharing
        $this->assertArrayHasKey('FACEBOOK', $flags['SOCIAL_SHARING']);
        $this->assertArrayHasKey('TWITTER', $flags['SOCIAL_SHARING']);
        $this->assertArrayHasKey('WHATSAPP', $flags['SOCIAL_SHARING']);
    }
    
    /**
     * Test development-specific feature flags
     */
    public function testDevelopmentFeatureFlags() {
        $flags = $this->configManager->getFeatureFlags('development');
        
        // Test development-specific flags
        $this->assertTrue($flags['DEBUG_TOOLS']);
        $this->assertTrue($flags['MOCK_PAYMENTS']);
    }
    
    /**
     * Test production-specific feature flags
     */
    public function testProductionFeatureFlags() {
        $flags = $this->configManager->getFeatureFlags('production');
        
        // Test production-specific flags
        $this->assertFalse($flags['DEBUG_TOOLS']);
        $this->assertFalse($flags['MOCK_PAYMENTS']);
    }
    
    /**
     * Test API endpoints configuration
     */
    public function testApiEndpointsConfiguration() {
        $config = $this->configManager->generateConfig('development');
        $endpoints = $config['api']['ENDPOINTS'];
        
        // Test product endpoints
        $this->assertEquals('/products', $endpoints['PRODUCTS']);
        $this->assertEquals('/products/:id', $endpoints['PRODUCT_DETAIL']);
        $this->assertEquals('/products/:id/images', $endpoints['PRODUCT_IMAGES']);
        
        // Test auth endpoints
        $this->assertArrayHasKey('AUTH', $endpoints);
        $this->assertEquals('/auth/login', $endpoints['AUTH']['LOGIN']);
        $this->assertEquals('/auth/register', $endpoints['AUTH']['REGISTER']);
        $this->assertEquals('/auth/logout', $endpoints['AUTH']['LOGOUT']);
        
        // Test admin endpoints
        $this->assertArrayHasKey('ADMIN', $endpoints);
        $this->assertEquals('/admin/dashboard', $endpoints['ADMIN']['DASHBOARD']);
        
        // Test cart endpoints
        $this->assertArrayHasKey('CART', $endpoints);
        $this->assertEquals('/cart', $endpoints['CART']['GET']);
        $this->assertEquals('/cart/add', $endpoints['CART']['ADD']);
        
        // Test order endpoints
        $this->assertArrayHasKey('ORDERS', $endpoints);
        $this->assertEquals('/orders', $endpoints['ORDERS']['CREATE']);
        $this->assertEquals('/orders', $endpoints['ORDERS']['LIST']);
        
        // Test payment endpoints
        $this->assertArrayHasKey('PAYMENTS', $endpoints);
        $this->assertEquals('/payments/razorpay/create', $endpoints['PAYMENTS']['RAZORPAY_CREATE']);
        $this->assertEquals('/payments/cod', $endpoints['PAYMENTS']['COD']);
    }
    
    /**
     * Test configuration validation
     */
    public function testValidateConfig() {
        $validConfig = [
            'api' => ['BASE_URL' => '/api'],
            'app' => ['NAME' => 'Test App'],
            'ui' => [],
            'features' => [],
            'environment' => []
        ];
        
        $this->assertTrue($this->configManager->validateConfig($validConfig));
    }
    
    /**
     * Test configuration validation with missing sections
     */
    public function testValidateConfigMissingSections() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required configuration section: api');
        
        $invalidConfig = [
            'app' => ['NAME' => 'Test App'],
            'ui' => [],
            'features' => [],
            'environment' => []
        ];
        
        $this->configManager->validateConfig($invalidConfig);
    }
    
    /**
     * Test configuration validation with missing API base URL
     */
    public function testValidateConfigMissingApiBaseUrl() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API base URL is required');
        
        $invalidConfig = [
            'api' => [],
            'app' => ['NAME' => 'Test App'],
            'ui' => [],
            'features' => [],
            'environment' => []
        ];
        
        $this->configManager->validateConfig($invalidConfig);
    }
    
    /**
     * Test configuration validation with missing app name
     */
    public function testValidateConfigMissingAppName() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Application name is required');
        
        $invalidConfig = [
            'api' => ['BASE_URL' => '/api'],
            'app' => [],
            'ui' => [],
            'features' => [],
            'environment' => []
        ];
        
        $this->configManager->validateConfig($invalidConfig);
    }
    
    /**
     * Test configuration caching
     */
    public function testConfigurationCaching() {
        // Generate config twice for same environment
        $config1 = $this->configManager->generateConfig('development');
        $config2 = $this->configManager->generateConfig('development');
        
        // Should return identical configurations (from cache)
        $this->assertEquals($config1, $config2);
    }
    
    /**
     * Test cache clearing
     */
    public function testClearCache() {
        // Generate config to populate cache
        $this->configManager->generateConfig('development');
        
        // Clear cache
        $this->configManager->clearCache();
        
        // This should work without errors
        $this->assertTrue(true);
    }
    
    /**
     * Test configuration summary
     */
    public function testGetConfigSummary() {
        $summary = $this->configManager->getConfigSummary();
        
        $this->assertArrayHasKey('environment', $summary);
        $this->assertArrayHasKey('base_url', $summary);
        $this->assertArrayHasKey('api_base_url', $summary);
        $this->assertArrayHasKey('cache_size', $summary);
        $this->assertArrayHasKey('last_generated', $summary);
        $this->assertArrayHasKey('feature_flags_count', $summary);
        
        $this->assertEquals('/api', $summary['api_base_url']);
        $this->assertIsInt($summary['cache_size']);
        $this->assertIsInt($summary['feature_flags_count']);
    }
    
    /**
     * Test UI configuration
     */
    public function testUiConfiguration() {
        $config = $this->configManager->generateConfig('development');
        $ui = $config['ui'];
        
        // Test breakpoints
        $this->assertArrayHasKey('BREAKPOINTS', $ui);
        $this->assertEquals(568, $ui['BREAKPOINTS']['MOBILE']);
        $this->assertEquals(768, $ui['BREAKPOINTS']['TABLET']);
        $this->assertEquals(1024, $ui['BREAKPOINTS']['DESKTOP']);
        $this->assertEquals(1200, $ui['BREAKPOINTS']['LARGE']);
        
        // Test other UI settings
        $this->assertArrayHasKey('HEADER_HEIGHT', $ui);
        $this->assertArrayHasKey('SCROLL', $ui);
        $this->assertArrayHasKey('CAROUSEL', $ui);
        $this->assertArrayHasKey('MODAL', $ui);
        
        // Test carousel settings
        $this->assertArrayHasKey('ITEMS_PER_VIEW', $ui['CAROUSEL']);
        $this->assertEquals(1, $ui['CAROUSEL']['ITEMS_PER_VIEW']['MOBILE']);
        $this->assertEquals(2, $ui['CAROUSEL']['ITEMS_PER_VIEW']['TABLET']);
        $this->assertEquals(3, $ui['CAROUSEL']['ITEMS_PER_VIEW']['DESKTOP']);
        $this->assertEquals(4, $ui['CAROUSEL']['ITEMS_PER_VIEW']['LARGE']);
    }
    
    /**
     * Test security configuration
     */
    public function testSecurityConfiguration() {
        $prodConfig = $this->configManager->generateConfig('production');
        $devConfig = $this->configManager->generateConfig('development');
        
        // Test production security settings
        $this->assertTrue($prodConfig['security']['HTTPS_ONLY']);
        $this->assertTrue($prodConfig['security']['SECURE_COOKIES']);
        $this->assertTrue($prodConfig['security']['CONTENT_SECURITY_POLICY']);
        
        // Test development security settings
        $this->assertFalse($devConfig['security']['HTTPS_ONLY']);
        $this->assertFalse($devConfig['security']['SECURE_COOKIES']);
        $this->assertFalse($devConfig['security']['CONTENT_SECURITY_POLICY']);
        
        // Test common security settings
        $this->assertTrue($prodConfig['security']['XSS_PROTECTION']);
        $this->assertTrue($devConfig['security']['XSS_PROTECTION']);
        $this->assertEquals('DENY', $prodConfig['security']['FRAME_OPTIONS']);
        $this->assertEquals('DENY', $devConfig['security']['FRAME_OPTIONS']);
    }
    
    /**
     * Test performance configuration
     */
    public function testPerformanceConfiguration() {
        $prodConfig = $this->configManager->generateConfig('production');
        $devConfig = $this->configManager->generateConfig('development');
        
        // Test production performance settings
        $this->assertTrue($prodConfig['performance']['IMAGE_OPTIMIZATION']);
        $this->assertTrue($prodConfig['performance']['COMPRESSION']);
        $this->assertTrue($prodConfig['performance']['CACHING']['ENABLED']);
        $this->assertEquals(86400, $prodConfig['performance']['CACHING']['DURATION']);
        
        // Test development performance settings
        $this->assertFalse($devConfig['performance']['IMAGE_OPTIMIZATION']);
        $this->assertFalse($devConfig['performance']['COMPRESSION']);
        $this->assertFalse($devConfig['performance']['CACHING']['ENABLED']);
        $this->assertEquals(0, $devConfig['performance']['CACHING']['DURATION']);
        
        // Test common performance settings
        $this->assertTrue($prodConfig['performance']['LAZY_LOADING']);
        $this->assertTrue($devConfig['performance']['LAZY_LOADING']);
    }
    
    /**
     * Test fallback configuration
     */
    public function testFallbackConfiguration() {
        // Create a mock that throws an exception
        $mockConfigManager = $this->createMock(FrontendConfigManager::class);
        $mockConfigManager->method('generateConfig')
                         ->willThrowException(new Exception('Test exception'));
        
        // Test that we can still get a basic configuration structure
        $config = $this->configManager->generateConfig('production');
        
        // Should have basic structure even if some parts fail
        $this->assertArrayHasKey('api', $config);
        $this->assertArrayHasKey('app', $config);
        $this->assertEquals('/api', $config['api']['BASE_URL']);
    }
}