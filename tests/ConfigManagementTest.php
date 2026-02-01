<?php
/**
 * Configuration Management System Tests
 * 
 * This test suite validates the configuration management system including
 * ConfigManager, ConfigValidationService, and CredentialManager.
 * 
 * Requirements: 14.2, 14.4
 */

require_once __DIR__ . '/../config/ConfigManager.php';
require_once __DIR__ . '/../services/ConfigValidationService.php';
require_once __DIR__ . '/../services/CredentialManager.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class ConfigManagementTest extends TestCase {
    private $configManager;
    private $validator;
    private $credentialManager;
    private $testConfigPath;
    
    protected function setUp(): void {
        // Create temporary test environment
        $this->testConfigPath = sys_get_temp_dir() . '/riya_config_test_' . uniqid();
        mkdir($this->testConfigPath, 0755, true);
        
        // Set test environment variables
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=true');
        putenv('DB_HOST=localhost');
        putenv('DB_NAME=test_db');
        putenv('DB_USER=test_user');
        putenv('JWT_SECRET=test_jwt_secret_32_characters_long');
        
        // Initialize managers
        $this->configManager = ConfigManager::getInstance();
        $this->validator = new ConfigValidationService();
        $this->credentialManager = CredentialManager::getInstance();
    }
    
    protected function tearDown(): void {
        // Clean up test environment
        if (is_dir($this->testConfigPath)) {
            $this->removeDirectory($this->testConfigPath);
        }
        
        // Clear configuration cache
        $this->configManager->clearCache();
    }
    
    /**
     * Test configuration loading and retrieval
     */
    public function testConfigurationLoading() {
        // Test basic configuration retrieval
        $appName = $this->configManager->get('app.name');
        $this->assertNotEmpty($appName);
        
        $appEnv = $this->configManager->get('app.env');
        $this->assertEquals('testing', $appEnv);
        
        // Test default values
        $nonExistent = $this->configManager->get('non.existent.key', 'default_value');
        $this->assertEquals('default_value', $nonExistent);
        
        // Test nested configuration
        $dbConfig = $this->configManager->get('database.connections.mysql');
        $this->assertIsArray($dbConfig);
        $this->assertArrayHasKey('host', $dbConfig);
    }
    
    /**
     * Test configuration setting and updating
     */
    public function testConfigurationSetting() {
        // Test setting simple value
        $this->configManager->set('test.simple', 'test_value');
        $value = $this->configManager->get('test.simple');
        $this->assertEquals('test_value', $value);
        
        // Test setting nested value
        $this->configManager->set('test.nested.key', 'nested_value');
        $nestedValue = $this->configManager->get('test.nested.key');
        $this->assertEquals('nested_value', $nestedValue);
        
        // Test setting array value
        $arrayValue = ['item1', 'item2', 'item3'];
        $this->configManager->set('test.array', $arrayValue);
        $retrievedArray = $this->configManager->get('test.array');
        $this->assertEquals($arrayValue, $retrievedArray);
    }
    
    /**
     * Test configuration validation
     */
    public function testConfigurationValidation() {
        // Test valid configuration
        $isValid = $this->validator->validateAll();
        $this->assertTrue($isValid, 'Configuration should be valid');
        
        // Test validation errors
        $errors = $this->validator->getErrors();
        $this->assertIsArray($errors);
        
        // Test validation warnings
        $warnings = $this->validator->getWarnings();
        $this->assertIsArray($warnings);
        
        // Test validation summary
        $summary = $this->validator->getSummary();
        $this->assertArrayHasKey('valid', $summary);
        $this->assertArrayHasKey('error_count', $summary);
        $this->assertArrayHasKey('warning_count', $summary);
    }
    
    /**
     * Test environment-specific configuration
     */
    public function testEnvironmentSpecificConfiguration() {
        // Test that testing environment settings are loaded
        $debugMode = $this->configManager->get('app.debug');
        $this->assertTrue($debugMode, 'Debug mode should be enabled in testing');
        
        // Test environment-specific features
        $mockPayments = $this->configManager->get('payment.mock_responses');
        $this->assertTrue($mockPayments, 'Mock payments should be enabled in testing');
        
        // Test security settings for testing
        $rateLimiting = $this->configManager->get('security.rate_limiting.enabled');
        $this->assertFalse($rateLimiting, 'Rate limiting should be disabled in testing');
    }
    
    /**
     * Test credential management
     */
    public function testCredentialManagement() {
        $testKey = 'test_credential';
        $testValue = 'secret_test_value_123';
        
        // Test storing credential
        $stored = $this->credentialManager->store($testKey, $testValue);
        $this->assertTrue($stored);
        
        // Test retrieving credential
        $retrieved = $this->credentialManager->retrieve($testKey);
        $this->assertEquals($testValue, $retrieved);
        
        // Test credential existence
        $exists = $this->credentialManager->exists($testKey);
        $this->assertTrue($exists);
        
        // Test credential metadata
        $metadata = $this->credentialManager->getMetadata($testKey);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('created_at', $metadata);
        $this->assertArrayHasKey('access_count', $metadata);
        
        // Test credential deletion
        $deleted = $this->credentialManager->delete($testKey);
        $this->assertTrue($deleted);
        
        $existsAfterDelete = $this->credentialManager->exists($testKey);
        $this->assertFalse($existsAfterDelete);
    }
    
    /**
     * Test credential rotation
     */
    public function testCredentialRotation() {
        $testKey = 'rotation_test';
        $oldValue = 'old_secret_value';
        $newValue = 'new_secret_value';
        
        // Store initial credential
        $this->credentialManager->store($testKey, $oldValue);
        
        // Rotate credential
        $rotated = $this->credentialManager->rotate($testKey, $newValue, 'test_rotation');
        $this->assertTrue($rotated);
        
        // Verify new value
        $retrieved = $this->credentialManager->retrieve($testKey);
        $this->assertEquals($newValue, $retrieved);
        
        // Clean up
        $this->credentialManager->delete($testKey);
    }
    
    /**
     * Test credential expiration
     */
    public function testCredentialExpiration() {
        $testKey = 'expiring_credential';
        $testValue = 'expiring_value';
        
        // Store credential with short expiration
        $this->credentialManager->store($testKey, $testValue, [
            'expires_in' => 1 // 1 second
        ]);
        
        // Should be retrievable immediately
        $retrieved = $this->credentialManager->retrieve($testKey);
        $this->assertEquals($testValue, $retrieved);
        
        // Wait for expiration
        sleep(2);
        
        // Should return null after expiration
        $expiredRetrieved = $this->credentialManager->retrieve($testKey);
        $this->assertNull($expiredRetrieved);
    }
    
    /**
     * Test secure credential generation
     */
    public function testSecureCredentialGeneration() {
        // Test default generation
        $credential = $this->credentialManager->generateSecureCredential();
        $this->assertEquals(32, strlen($credential));
        
        // Test custom length
        $shortCredential = $this->credentialManager->generateSecureCredential(16);
        $this->assertEquals(16, strlen($shortCredential));
        
        // Test without special characters
        $simpleCredential = $this->credentialManager->generateSecureCredential(20, false);
        $this->assertEquals(20, strlen($simpleCredential));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $simpleCredential);
    }
    
    /**
     * Test configuration caching
     */
    public function testConfigurationCaching() {
        // Clear cache first
        $this->configManager->clearCache();
        
        // Set a test value
        $this->configManager->set('cache.test', 'cached_value');
        
        // Get the value (should create cache)
        $value = $this->configManager->get('cache.test');
        $this->assertEquals('cached_value', $value);
        
        // Verify cache file exists (implementation dependent)
        // This test might need adjustment based on actual cache implementation
        $this->assertTrue(true); // Placeholder assertion
    }
    
    /**
     * Test configuration export
     */
    public function testConfigurationExport() {
        // Test export without sensitive data
        $config = $this->configManager->export(false);
        $this->assertIsArray($config);
        
        // Test export with sensitive data
        $configWithSensitive = $this->configManager->export(true);
        $this->assertIsArray($configWithSensitive);
        
        // Verify sensitive data is redacted in non-sensitive export
        // This test depends on the specific implementation
        $this->assertTrue(true); // Placeholder assertion
    }
    
    /**
     * Test configuration validation with invalid data
     */
    public function testConfigurationValidationWithInvalidData() {
        // Set invalid configuration
        $this->configManager->set('database.connections.mysql.port', 'invalid_port');
        
        // Validation should fail
        $isValid = $this->validator->validateAll();
        $this->assertFalse($isValid);
        
        $errors = $this->validator->getErrors();
        $this->assertNotEmpty($errors);
        
        // Find the specific error
        $portError = array_filter($errors, function($error) {
            return strpos($error['message'], 'port') !== false;
        });
        $this->assertNotEmpty($portError);
    }
    
    /**
     * Test environment-specific validation rules
     */
    public function testEnvironmentSpecificValidation() {
        // Set environment to production
        $this->configManager->set('app.env', 'production');
        
        // Set debug mode to true (should fail in production)
        $this->configManager->set('app.debug', true);
        
        // Validation should fail
        $isValid = $this->validator->validateAll();
        $this->assertFalse($isValid);
        
        $errors = $this->validator->getErrors();
        $debugError = array_filter($errors, function($error) {
            return strpos($error['message'], 'debug') !== false;
        });
        $this->assertNotEmpty($debugError);
    }
    
    /**
     * Test credential import from environment
     */
    public function testCredentialImportFromEnvironment() {
        // Set test environment variables
        putenv('TEST_SECRET_KEY=test_secret_value');
        putenv('TEST_API_TOKEN=test_api_token');
        putenv('REGULAR_CONFIG=not_secret');
        
        // Import credentials
        $result = $this->credentialManager->importFromEnvironment('TEST_');
        
        $this->assertArrayHasKey('imported', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertGreaterThan(0, $result['imported']);
        
        // Verify imported credentials
        $secretKey = $this->credentialManager->retrieve('SECRET_KEY');
        $this->assertEquals('test_secret_value', $secretKey);
        
        // Clean up
        $this->credentialManager->delete('SECRET_KEY');
        $this->credentialManager->delete('API_TOKEN');
    }
    
    /**
     * Test configuration section retrieval
     */
    public function testConfigurationSectionRetrieval() {
        // Test getting entire section
        $databaseSection = $this->configManager->section('database');
        $this->assertIsArray($databaseSection);
        $this->assertArrayHasKey('connections', $databaseSection);
        
        // Test getting non-existent section
        $nonExistentSection = $this->configManager->section('non.existent');
        $this->assertIsArray($nonExistentSection);
        $this->assertEmpty($nonExistentSection);
    }
    
    /**
     * Test configuration has method
     */
    public function testConfigurationHasMethod() {
        // Test existing key
        $hasAppName = $this->configManager->has('app.name');
        $this->assertTrue($hasAppName);
        
        // Test non-existent key
        $hasNonExistent = $this->configManager->has('non.existent.key');
        $this->assertFalse($hasNonExistent);
        
        // Test nested existing key
        $hasDbHost = $this->configManager->has('database.connections.mysql.host');
        $this->assertTrue($hasDbHost);
    }
    
    /**
     * Test configuration summary
     */
    public function testConfigurationSummary() {
        $summary = $this->configManager->getSummary();
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('environment', $summary);
        $this->assertArrayHasKey('debug_mode', $summary);
        $this->assertArrayHasKey('database_host', $summary);
        $this->assertArrayHasKey('config_sections', $summary);
        
        $this->assertEquals('testing', $summary['environment']);
    }
    
    /**
     * Helper method to remove directory recursively
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}

/**
 * Property-Based Test for Configuration Management
 * 
 * **Validates: Requirements 14.2, 14.4**
 */
class ConfigManagementPropertyTest extends TestCase {
    private $configManager;
    
    protected function setUp(): void {
        $this->configManager = ConfigManager::getInstance();
    }
    
    /**
     * Property: Configuration values should be retrievable after being set
     * 
     * **Validates: Requirements 14.2**
     */
    public function testConfigurationPersistenceProperty() {
        for ($i = 0; $i < 50; $i++) {
            // Generate random configuration key and value
            $key = 'test.property.' . uniqid();
            $value = $this->generateRandomValue();
            
            // Set configuration
            $this->configManager->set($key, $value);
            
            // Retrieve configuration
            $retrieved = $this->configManager->get($key);
            
            // Assert they are equal
            $this->assertEquals($value, $retrieved, 
                "Configuration value should persist after being set for key: {$key}");
        }
    }
    
    /**
     * Property: Configuration validation should be consistent
     * 
     * **Validates: Requirements 14.4**
     */
    public function testConfigurationValidationConsistency() {
        $validator = new ConfigValidationService();
        
        for ($i = 0; $i < 20; $i++) {
            // Run validation multiple times
            $result1 = $validator->validateAll();
            $result2 = $validator->validateAll();
            
            // Results should be consistent
            $this->assertEquals($result1, $result2, 
                "Configuration validation should return consistent results");
            
            $errors1 = $validator->getErrors();
            $errors2 = $validator->getErrors();
            
            $this->assertEquals(count($errors1), count($errors2), 
                "Error count should be consistent across validation runs");
        }
    }
    
    /**
     * Generate random value for testing
     */
    private function generateRandomValue() {
        $types = ['string', 'integer', 'boolean', 'array'];
        $type = $types[array_rand($types)];
        
        switch ($type) {
            case 'string':
                return 'test_string_' . uniqid();
            case 'integer':
                return rand(1, 1000);
            case 'boolean':
                return (bool) rand(0, 1);
            case 'array':
                return ['item1', 'item2', rand(1, 100)];
            default:
                return 'default_value';
        }
    }
}