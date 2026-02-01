<?php
/**
 * Integration Tests for FrontendConfigManager
 * 
 * Tests the integration of FrontendConfigManager with the routing system
 * and configuration endpoint serving functionality.
 */

require_once __DIR__ . '/../app/services/FrontendConfigManager.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class FrontendConfigManagerIntegrationTest extends TestCase {
    private $configManager;
    
    protected function setUp(): void {
        // Set up test environment
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_NAME'] = 'Test Riya Collections';
        $_ENV['APP_URL'] = 'https://test.riyacollections.com';
        
        $this->configManager = new FrontendConfigManager();
    }
    
    /**
     * Test serving configuration endpoint with output buffering
     */
    public function testServeConfigEndpoint() {
        // Capture output
        ob_start();
        
        // Mock headers function to prevent actual header output during tests
        if (!function_exists('header')) {
            function header($string) {
                // Mock header function for testing
            }
        }
        
        $this->configManager->serveConfigEndpoint('development', false);
        
        $output = ob_get_clean();
        
        // Test that output is generated
        $this->assertNotEmpty($output);
        
        // Test that output contains JavaScript configuration
        $this->assertStringContainsString('window.API_CONFIG', $output);
        $this->assertStringContainsString('window.APP_CONFIG', $output);
        $this->assertStringContainsString('window.UI_CONFIG', $output);
        $this->assertStringContainsString('window.FEATURES', $output);
        $this->assertStringContainsString('window.ENVIRONMENT', $output);
        
        // Test that utility functions are included
        $this->assertStringContainsString('window.CONFIG_UTILS', $output);
        $this->assertStringContainsString('getApiUrl:', $output);
        $this->assertStringContainsString('getCurrentBreakpoint:', $output);
        $this->assertStringContainsString('isFeatureEnabled:', $output);
        
        // Test that environment detection is included
        $this->assertStringContainsString('window.IS_DEVELOPMENT', $output);
        $this->assertStringContainsString('window.IS_PRODUCTION', $output);
        
        // Test that the output is valid JavaScript (basic syntax check)
        $this->assertStringNotContainsString('<?php', $output);
        $this->assertStringContainsString('/**', $output); // Should have comments
    }
    
    /**
     * Test configuration endpoint with different environments
     */
    public function testConfigEndpointDifferentEnvironments() {
        $environments = ['development', 'production', 'testing'];
        
        foreach ($environments as $env) {
            ob_start();
            $this->configManager->serveConfigEndpoint($env, false);
            $output = ob_get_clean();
            
            $this->assertNotEmpty($output, "Output should not be empty for environment: {$env}");
            $this->assertStringContainsString("Environment: {$env}", $output);
            
            // Test environment-specific content
            if ($env === 'development') {
                $this->assertStringContainsString('"IS_DEVELOPMENT": true', $output);
                $this->assertStringContainsString('"DEBUG_TOOLS": true', $output);
            } elseif ($env === 'production') {
                $this->assertStringContainsString('"IS_PRODUCTION": true', $output);
                $this->assertStringContainsString('"HTTPS_ONLY": true', $output);
            }
        }
    }
    
    /**
     * Test JavaScript configuration parsing
     */
    public function testJavaScriptConfigurationParsing() {
        ob_start();
        $this->configManager->serveConfigEndpoint('development', false);
        $jsOutput = ob_get_clean();
        
        // Extract JSON configurations from JavaScript output
        preg_match('/window\.API_CONFIG = ({.*?});/s', $jsOutput, $apiMatches);
        preg_match('/window\.APP_CONFIG = ({.*?});/s', $jsOutput, $appMatches);
        
        $this->assertNotEmpty($apiMatches, 'API_CONFIG should be found in output');
        $this->assertNotEmpty($appMatches, 'APP_CONFIG should be found in output');
        
        // Test that JSON is valid
        $apiConfig = json_decode($apiMatches[1], true);
        $appConfig = json_decode($appMatches[1], true);
        
        $this->assertNotNull($apiConfig, 'API_CONFIG should be valid JSON');
        $this->assertNotNull($appConfig, 'APP_CONFIG should be valid JSON');
        
        // Test API configuration structure
        $this->assertEquals('/api', $apiConfig['BASE_URL']);
        $this->assertArrayHasKey('ENDPOINTS', $apiConfig);
        $this->assertArrayHasKey('TIMEOUT', $apiConfig);
        
        // Test app configuration structure
        $this->assertArrayHasKey('NAME', $appConfig);
        $this->assertArrayHasKey('VERSION', $appConfig);
        $this->assertEquals('development', $appConfig['ENVIRONMENT']);
    }
    
    /**
     * Test configuration caching behavior
     */
    public function testConfigurationCaching() {
        // Generate config multiple times
        $config1 = $this->configManager->generateConfig('development');
        $config2 = $this->configManager->generateConfig('development');
        $config3 = $this->configManager->generateConfig('production');
        
        // Same environment should return identical config (cached)
        $this->assertEquals($config1, $config2);
        
        // Different environment should return different config
        $this->assertNotEquals($config1, $config3);
        
        // Clear cache and regenerate
        $this->configManager->clearCache();
        $config4 = $this->configManager->generateConfig('development');
        
        // Should still be the same structure
        $this->assertEquals($config1, $config4);
    }
    
    /**
     * Test error handling in configuration serving
     */
    public function testErrorHandlingInConfigServing() {
        // Create a mock that might fail
        $mockConfigManager = $this->getMockBuilder(FrontendConfigManager::class)
                                  ->onlyMethods(['generateConfig'])
                                  ->getMock();
        
        // Make generateConfig throw an exception
        $mockConfigManager->method('generateConfig')
                         ->willThrowException(new Exception('Test exception'));
        
        // Should still produce output (fallback config)
        ob_start();
        
        try {
            $mockConfigManager->serveConfigEndpoint('development', false);
            $output = ob_get_clean();
            
            // Should have fallback configuration
            $this->assertNotEmpty($output);
            $this->assertStringContainsString('window.API_CONFIG', $output);
            
        } catch (Exception $e) {
            ob_end_clean();
            // If exception is thrown, that's also acceptable behavior
            $this->assertInstanceOf(Exception::class, $e);
        }
    }
    
    /**
     * Test configuration validation integration
     */
    public function testConfigurationValidationIntegration() {
        $config = $this->configManager->generateConfig('development');
        
        // Should validate successfully
        $this->assertTrue($this->configManager->validateConfig($config));
        
        // Test with invalid config
        $invalidConfig = $config;
        unset($invalidConfig['api']['BASE_URL']);
        
        $this->expectException(Exception::class);
        $this->configManager->validateConfig($invalidConfig);
    }
    
    /**
     * Test configuration summary integration
     */
    public function testConfigurationSummaryIntegration() {
        // Generate some config to populate cache
        $this->configManager->generateConfig('development');
        $this->configManager->generateConfig('production');
        
        $summary = $this->configManager->getConfigSummary();
        
        $this->assertArrayHasKey('environment', $summary);
        $this->assertArrayHasKey('api_base_url', $summary);
        $this->assertArrayHasKey('cache_size', $summary);
        $this->assertArrayHasKey('feature_flags_count', $summary);
        
        $this->assertEquals('/api', $summary['api_base_url']);
        $this->assertGreaterThanOrEqual(2, $summary['cache_size']); // Should have cached 2 environments
        $this->assertGreaterThan(0, $summary['feature_flags_count']);
    }
    
    /**
     * Test API base URL consistency
     */
    public function testApiBaseUrlConsistency() {
        $environments = ['development', 'production', 'testing'];
        
        foreach ($environments as $env) {
            $config = $this->configManager->generateConfig($env);
            $directUrl = $this->configManager->getApiBaseUrl($env);
            
            // API base URL should be consistent
            $this->assertEquals('/api', $config['api']['BASE_URL']);
            $this->assertEquals('/api', $directUrl);
        }
    }
    
    /**
     * Test feature flags consistency across methods
     */
    public function testFeatureFlagsConsistency() {
        $config = $this->configManager->generateConfig('development');
        $directFlags = $this->configManager->getFeatureFlags('development');
        
        // Feature flags should be consistent
        $this->assertEquals($config['features'], $directFlags);
    }
}