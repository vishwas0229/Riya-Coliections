<?php
/**
 * Demonstration of FrontendConfigManager functionality
 * 
 * This script shows how the FrontendConfigManager generates environment-specific
 * configuration for the frontend application.
 */

require_once __DIR__ . '/app/services/FrontendConfigManager.php';

echo "=== Frontend Configuration Manager Demo ===\n\n";

try {
    $configManager = new FrontendConfigManager();
    
    echo "1. Testing API Base URL Generation:\n";
    echo "   Development: " . $configManager->getApiBaseUrl('development') . "\n";
    echo "   Production:  " . $configManager->getApiBaseUrl('production') . "\n";
    echo "   Testing:     " . $configManager->getApiBaseUrl('testing') . "\n\n";
    
    echo "2. Testing Feature Flags for Different Environments:\n";
    
    $devFlags = $configManager->getFeatureFlags('development');
    $prodFlags = $configManager->getFeatureFlags('production');
    
    echo "   Development Features:\n";
    echo "     - Debug Tools: " . ($devFlags['DEBUG_TOOLS'] ? 'Enabled' : 'Disabled') . "\n";
    echo "     - Mock Payments: " . ($devFlags['MOCK_PAYMENTS'] ? 'Enabled' : 'Disabled') . "\n";
    echo "     - Wishlist: " . ($devFlags['WISHLIST'] ? 'Enabled' : 'Disabled') . "\n";
    
    echo "   Production Features:\n";
    echo "     - Debug Tools: " . ($prodFlags['DEBUG_TOOLS'] ? 'Enabled' : 'Disabled') . "\n";
    echo "     - Mock Payments: " . ($prodFlags['MOCK_PAYMENTS'] ? 'Enabled' : 'Disabled') . "\n";
    echo "     - Wishlist: " . ($prodFlags['WISHLIST'] ? 'Enabled' : 'Disabled') . "\n\n";
    
    echo "3. Testing Configuration Generation:\n";
    
    $devConfig = $configManager->generateConfig('development');
    $prodConfig = $configManager->generateConfig('production');
    
    echo "   Development Configuration:\n";
    echo "     - Environment: " . $devConfig['environment']['NAME'] . "\n";
    echo "     - Debug Mode: " . ($devConfig['environment']['DEBUG_MODE'] ? 'Yes' : 'No') . "\n";
    echo "     - HTTPS Only: " . ($devConfig['security']['HTTPS_ONLY'] ? 'Yes' : 'No') . "\n";
    echo "     - Caching: " . ($devConfig['performance']['CACHING']['ENABLED'] ? 'Enabled' : 'Disabled') . "\n";
    
    echo "   Production Configuration:\n";
    echo "     - Environment: " . $prodConfig['environment']['NAME'] . "\n";
    echo "     - Debug Mode: " . ($prodConfig['environment']['DEBUG_MODE'] ? 'Yes' : 'No') . "\n";
    echo "     - HTTPS Only: " . ($prodConfig['security']['HTTPS_ONLY'] ? 'Yes' : 'No') . "\n";
    echo "     - Caching: " . ($prodConfig['performance']['CACHING']['ENABLED'] ? 'Enabled' : 'Disabled') . "\n\n";
    
    echo "4. Testing Configuration Validation:\n";
    
    try {
        $configManager->validateConfig($devConfig);
        echo "   Development config validation: PASSED\n";
    } catch (Exception $e) {
        echo "   Development config validation: FAILED - " . $e->getMessage() . "\n";
    }
    
    try {
        $configManager->validateConfig($prodConfig);
        echo "   Production config validation: PASSED\n";
    } catch (Exception $e) {
        echo "   Production config validation: FAILED - " . $e->getMessage() . "\n";
    }
    
    echo "\n5. Testing Configuration Summary:\n";
    $summary = $configManager->getConfigSummary();
    echo "   Current Environment: " . $summary['environment'] . "\n";
    echo "   API Base URL: " . $summary['api_base_url'] . "\n";
    echo "   Cache Size: " . $summary['cache_size'] . " configurations\n";
    echo "   Feature Flags Count: " . $summary['feature_flags_count'] . "\n";
    
    echo "\n6. Testing JavaScript Configuration Generation:\n";
    echo "   Generating JavaScript configuration for development environment...\n";
    
    // Capture the JavaScript output
    ob_start();
    $configManager->serveConfigEndpoint('development', false);
    $jsConfig = ob_get_clean();
    
    $lines = explode("\n", $jsConfig);
    $totalLines = count($lines);
    $size = strlen($jsConfig);
    
    echo "   Generated JavaScript configuration:\n";
    echo "     - Size: " . number_format($size) . " bytes\n";
    echo "     - Lines: " . number_format($totalLines) . "\n";
    echo "     - Contains API_CONFIG: " . (strpos($jsConfig, 'window.API_CONFIG') !== false ? 'Yes' : 'No') . "\n";
    echo "     - Contains APP_CONFIG: " . (strpos($jsConfig, 'window.APP_CONFIG') !== false ? 'Yes' : 'No') . "\n";
    echo "     - Contains FEATURES: " . (strpos($jsConfig, 'window.FEATURES') !== false ? 'Yes' : 'No') . "\n";
    echo "     - Contains Utility Functions: " . (strpos($jsConfig, 'CONFIG_UTILS') !== false ? 'Yes' : 'No') . "\n";
    
    echo "\n7. Sample JavaScript Configuration (first 10 lines):\n";
    for ($i = 0; $i < min(10, $totalLines); $i++) {
        echo "   " . ($i + 1) . ": " . $lines[$i] . "\n";
    }
    
    if ($totalLines > 10) {
        echo "   ... (and " . ($totalLines - 10) . " more lines)\n";
    }
    
    echo "\n=== Demo Complete ===\n";
    echo "The FrontendConfigManager successfully:\n";
    echo "✓ Generated environment-specific configurations\n";
    echo "✓ Provided consistent API base URLs\n";
    echo "✓ Managed feature flags per environment\n";
    echo "✓ Validated configuration structures\n";
    echo "✓ Generated JavaScript configuration code\n";
    echo "✓ Provided configuration summaries\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}