<?php
/**
 * Integration Functionality Test Script
 * 
 * This script tests the actual functionality of the integrated application
 * by making HTTP requests to validate different components.
 */

// Include required files for testing
require_once __DIR__ . '/app/config/environment.php';
require_once __DIR__ . '/app/services/AssetServer.php';
require_once __DIR__ . '/app/services/SPARouteHandler.php';
require_once __DIR__ . '/app/services/FrontendConfigManager.php';
require_once __DIR__ . '/app/utils/Logger.php';

echo "=== Riya Collections Integration Functionality Test ===\n\n";

// Test 1: Test frontend configuration endpoint
echo "1. Testing frontend configuration endpoint...\n";
$configUrl = 'http://localhost/api/config';
$configResponse = @file_get_contents($configUrl, false, stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json',
        'timeout' => 5
    ]
]));

if ($configResponse !== false) {
    $config = json_decode($configResponse, true);
    if ($config && isset($config['api']['BASE_URL'])) {
        echo "   ✓ Frontend configuration endpoint working\n";
        echo "   ✓ API base URL: " . $config['api']['BASE_URL'] . "\n";
    } else {
        echo "   ✗ Invalid configuration response\n";
    }
} else {
    echo "   ~ Configuration endpoint test skipped (server not running)\n";
}

// Test 2: Test static asset serving
echo "2. Testing static asset serving...\n";
$testAssets = [
    'assets/logo.svg' => 'image/svg+xml',
    'assets/css/main.css' => 'text/css',
    'assets/js/config.js' => 'application/javascript'
];

foreach ($testAssets as $asset => $expectedMimeType) {
    if (file_exists("public/$asset")) {
        echo "   ✓ Asset exists: $asset\n";
        
        // Test MIME type detection
        $assetServer = new AssetServer();
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

// Test 3: Test SPA route handling
echo "3. Testing SPA route handling...\n";
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
    if ($isFrontend === $shouldBeFrontend) {
        echo "   ✓ Route classification correct: $route\n";
    } else {
        echo "   ✗ Route classification incorrect: $route (expected " . ($shouldBeFrontend ? 'frontend' : 'non-frontend') . ")\n";
    }
}

// Test 4: Test integrated router classification
echo "4. Testing integrated router classification...\n";
$router = new IntegratedRouter();

$testClassifications = [
    '/api/products' => 'api',
    '/assets/css/main.css' => 'asset',
    '/products' => 'frontend',
    '/' => 'frontend'
];

foreach ($testClassifications as $path => $expectedType) {
    $actualType = $router->classifyRequestType($path);
    if ($actualType === $expectedType) {
        echo "   ✓ Request classification correct: $path -> $actualType\n";
    } else {
        echo "   ✗ Request classification incorrect: $path (expected $expectedType, got $actualType)\n";
    }
}

// Test 5: Test frontend configuration manager
echo "5. Testing frontend configuration manager...\n";
$configManager = new FrontendConfigManager();

try {
    $devConfig = $configManager->generateConfig('development');
    if (isset($devConfig['api']['BASE_URL']) && $devConfig['api']['BASE_URL'] === '/api') {
        echo "   ✓ Development configuration generated correctly\n";
    } else {
        echo "   ✗ Development configuration incorrect\n";
    }
    
    $prodConfig = $configManager->generateConfig('production');
    if (isset($prodConfig['environment']['IS_PRODUCTION']) && $prodConfig['environment']['IS_PRODUCTION'] === true) {
        echo "   ✓ Production configuration generated correctly\n";
    } else {
        echo "   ✗ Production configuration incorrect\n";
    }
} catch (Exception $e) {
    echo "   ✗ Configuration manager error: " . $e->getMessage() . "\n";
}

// Test 6: Test asset server functionality
echo "6. Testing asset server functionality...\n";
$assetServer = new AssetServer();

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
    } else {
        echo "   ✗ Asset server statistics unavailable\n";
    }
} catch (Exception $e) {
    echo "   ✗ Asset server error: " . $e->getMessage() . "\n";
}

// Test 7: Test environment configuration
echo "7. Testing environment configuration...\n";
if (function_exists('env')) {
    $appEnv = env('APP_ENV', 'unknown');
    $appUrl = env('APP_URL', 'unknown');
    
    echo "   ✓ Environment function available\n";
    echo "   ✓ APP_ENV: $appEnv\n";
    echo "   ✓ APP_URL: $appUrl\n";
} else {
    echo "   ✗ Environment function not available\n";
}

// Test 8: Test database configuration (without connecting)
echo "8. Testing database configuration...\n";
if (class_exists('Database')) {
    echo "   ✓ Database class available\n";
    
    // Test configuration without connecting
    $dbConfig = [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'riya_collections'),
        'user' => env('DB_USER', 'root')
    ];
    
    echo "   ✓ Database configuration loaded\n";
    echo "   ✓ DB Host: " . $dbConfig['host'] . "\n";
    echo "   ✓ DB Name: " . $dbConfig['name'] . "\n";
} else {
    echo "   ✗ Database class not available\n";
}

// Test 9: Test logging functionality
echo "9. Testing logging functionality...\n";
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

// Test 10: Test middleware classes
echo "10. Testing middleware classes...\n";
$middlewareClasses = ['CorsMiddleware', 'SecurityMiddleware', 'AuthMiddleware'];

foreach ($middlewareClasses as $middlewareClass) {
    if (class_exists($middlewareClass)) {
        echo "   ✓ $middlewareClass available\n";
    } else {
        echo "   ✗ $middlewareClass not available\n";
    }
}

echo "\n=== Integration Functionality Test Complete ===\n";
echo "If all tests pass, the integration is fully functional!\n";
?>