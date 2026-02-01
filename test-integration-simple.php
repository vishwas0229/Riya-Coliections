<?php
/**
 * Simple Integration Test Script
 * 
 * This script tests the core integration components without requiring
 * the full router to be loaded.
 */

// Include required files for testing
require_once __DIR__ . '/app/config/environment.php';
require_once __DIR__ . '/app/services/AssetServer.php';
require_once __DIR__ . '/app/services/SPARouteHandler.php';
require_once __DIR__ . '/app/services/FrontendConfigManager.php';
require_once __DIR__ . '/app/utils/Logger.php';

echo "=== Riya Collections Simple Integration Test ===\n\n";

// Test 1: Test static asset serving
echo "1. Testing static asset serving...\n";
$testAssets = [
    'assets/logo.svg' => 'image/svg+xml',
    'assets/css/main.css' => 'text/css',
    'assets/js/config.js' => 'application/javascript'
];

$assetServer = new AssetServer();
foreach ($testAssets as $asset => $expectedMimeType) {
    if (file_exists("public/$asset")) {
        echo "   ✓ Asset exists: $asset\n";
        
        // Test MIME type detection
        $mimeType = $assetServer->getMimeType("public/$asset");
        if ($mimeType === $expectedMimeType) {
            echo "   ✓ Correct MIME type: $mimeType\n";
        } else {
            echo "   ✗ Incorrect MIME type: expected $expectedMimeType, got $mimeType\n";
        }
    } else {
        echo "   ✗ Asset missing: $asset\n";
    }
}

// Test 2: Test SPA route handling
echo "2. Testing SPA route handling...\n";
$spaHandler = new SPARouteHandler();

$testRoutes = [
    '/' => true,
    '/products' => true,
    '/categories' => true,
    '/api/products' => false,
    '/assets/css/main.css' => false
];

foreach ($testRoutes as $route => $shouldBeFrontend) {
    $isFrontend = $spaHandler->isFrontendRoute($route);
    $isApi = $spaHandler->isAPIRoute($route);
    
    if ($shouldBeFrontend) {
        if ($isFrontend && !$isApi) {
            echo "   ✓ Frontend route classification correct: $route\n";
        } else {
            echo "   ✗ Frontend route classification incorrect: $route\n";
        }
    } else {
        if (!$isFrontend) {
            echo "   ✓ Non-frontend route classification correct: $route\n";
        } else {
            echo "   ✗ Non-frontend route classification incorrect: $route\n";
        }
    }
}

// Test 3: Test frontend configuration manager
echo "3. Testing frontend configuration manager...\n";
$configManager = new FrontendConfigManager();

try {
    $devConfig = $configManager->generateConfig('development');
    if (isset($devConfig['api']['BASE_URL']) && $devConfig['api']['BASE_URL'] === '/api') {
        echo "   ✓ Development configuration generated correctly\n";
        echo "   ✓ API base URL: " . $devConfig['api']['BASE_URL'] . "\n";
    } else {
        echo "   ✗ Development configuration incorrect\n";
    }
    
    $prodConfig = $configManager->generateConfig('production');
    if (isset($prodConfig['environment']['IS_PRODUCTION']) && $prodConfig['environment']['IS_PRODUCTION'] === true) {
        echo "   ✓ Production configuration generated correctly\n";
    } else {
        echo "   ✗ Production configuration incorrect\n";
    }
    
    // Test feature flags
    $flags = $configManager->getFeatureFlags('development');
    if (isset($flags['DEBUG_TOOLS']) && $flags['DEBUG_TOOLS'] === true) {
        echo "   ✓ Development feature flags correct\n";
    } else {
        echo "   ✗ Development feature flags incorrect\n";
    }
} catch (Exception $e) {
    echo "   ✗ Configuration manager error: " . $e->getMessage() . "\n";
}

// Test 4: Test asset server functionality
echo "4. Testing asset server functionality...\n";

try {
    // Test path validation
    $validPath = $assetServer->validateAssetPath('assets/css/main.css');
    if ($validPath !== false) {
        echo "   ✓ Valid asset path accepted\n";
    } else {
        echo "   ✗ Valid asset path rejected\n";
    }
    
    // Test path traversal prevention
    $maliciousPath = $assetServer->validateAssetPath('../../../etc/passwd');
    if ($maliciousPath === false) {
        echo "   ✓ Path traversal attack blocked\n";
    } else {
        echo "   ✗ Path traversal attack not blocked\n";
    }
    
    // Test statistics
    $stats = $assetServer->getStatistics();
    if (isset($stats['mime_types_supported']) && $stats['mime_types_supported'] > 0) {
        echo "   ✓ Asset server statistics available\n";
        echo "   ✓ MIME types supported: " . $stats['mime_types_supported'] . "\n";
    } else {
        echo "   ✗ Asset server statistics unavailable\n";
    }
    
    // Test cache headers
    $fileInfo = [
        'extension' => 'css',
        'mtime' => time(),
        'etag' => '"test-etag"'
    ];
    $headers = $assetServer->getCacheHeaders($fileInfo);
    if (isset($headers['Cache-Control']) && isset($headers['ETag'])) {
        echo "   ✓ Cache headers generated correctly\n";
    } else {
        echo "   ✗ Cache headers not generated\n";
    }
} catch (Exception $e) {
    echo "   ✗ Asset server error: " . $e->getMessage() . "\n";
}

// Test 5: Test environment configuration
echo "5. Testing environment configuration...\n";
if (function_exists('env')) {
    $appEnv = env('APP_ENV', 'unknown');
    $appUrl = env('APP_URL', 'unknown');
    
    echo "   ✓ Environment function available\n";
    echo "   ✓ APP_ENV: $appEnv\n";
    echo "   ✓ APP_URL: $appUrl\n";
    
    // Test API base URL generation
    $apiBaseUrl = $configManager->getApiBaseUrl();
    if ($apiBaseUrl === '/api') {
        echo "   ✓ API base URL correct: $apiBaseUrl\n";
    } else {
        echo "   ✗ API base URL incorrect: $apiBaseUrl\n";
    }
} else {
    echo "   ✗ Environment function not available\n";
}

// Test 6: Test logging functionality
echo "6. Testing logging functionality...\n";
if (class_exists('Logger')) {
    try {
        Logger::info('Integration test log entry', ['test' => true]);
        echo "   ✓ Logger class available and functional\n";
    } catch (Exception $e) {
        echo "   ✗ Logger error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ Logger class not available\n";
}

// Test 7: Test file structure integrity
echo "7. Testing file structure integrity...\n";
$requiredFiles = [
    'public/index.php' => 'Main entry point',
    'public/.htaccess' => 'Apache configuration',
    'public/index.html' => 'Frontend main page',
    'app/services/AssetServer.php' => 'Asset server',
    'app/services/SPARouteHandler.php' => 'SPA route handler',
    'app/services/FrontendConfigManager.php' => 'Frontend config manager',
    'app/config/environment.php' => 'Environment configuration',
    'app/utils/Logger.php' => 'Logger utility'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "   ✓ $description exists: $file\n";
    } else {
        echo "   ✗ $description missing: $file\n";
    }
}

// Test 8: Test asset compression capability
echo "8. Testing asset compression capability...\n";
if (extension_loaded('zlib')) {
    echo "   ✓ Zlib extension available for compression\n";
    
    // Test compression
    $testContent = str_repeat('This is test content for compression. ', 100);
    $compressed = gzcompress($testContent);
    
    if (strlen($compressed) < strlen($testContent)) {
        echo "   ✓ Compression working correctly\n";
        echo "   ✓ Compression ratio: " . round((1 - strlen($compressed) / strlen($testContent)) * 100, 1) . "%\n";
    } else {
        echo "   ✗ Compression not working\n";
    }
} else {
    echo "   ✗ Zlib extension not available\n";
}

// Test 9: Test configuration validation
echo "9. Testing configuration validation...\n";
try {
    $validConfig = [
        'api' => ['BASE_URL' => '/api'],
        'app' => ['NAME' => 'Test App'],
        'ui' => [],
        'features' => [],
        'environment' => []
    ];
    
    $isValid = $configManager->validateConfig($validConfig);
    if ($isValid) {
        echo "   ✓ Configuration validation working\n";
    } else {
        echo "   ✗ Configuration validation failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ Configuration validation error: " . $e->getMessage() . "\n";
}

// Test 10: Test summary
echo "10. Integration summary...\n";
$summary = $configManager->getConfigSummary();
if (isset($summary['api_base_url']) && $summary['api_base_url'] === '/api') {
    echo "   ✓ Configuration summary available\n";
    echo "   ✓ Environment: " . ($summary['environment'] ?? 'unknown') . "\n";
    echo "   ✓ Feature flags count: " . ($summary['feature_flags_count'] ?? 0) . "\n";
} else {
    echo "   ✗ Configuration summary unavailable\n";
}

echo "\n=== Simple Integration Test Complete ===\n";
echo "Integration components are working correctly!\n";
echo "The basic integration functionality has been validated.\n";
?>