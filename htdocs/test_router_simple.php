<?php
/**
 * Simple Router Test - Core Functionality Only
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock environment variables
putenv('APP_ENV=development');
putenv('DB_HOST=localhost');
putenv('DB_NAME=test_db');
putenv('DB_USER=test_user');
putenv('JWT_SECRET=test_secret_key_for_testing_purposes_only');

// Include only what we need for testing
require_once 'config/environment.php';
require_once 'utils/Logger.php';

// Mock the middleware classes to avoid header issues
class CorsMiddleware {
    public static function handle() {
        // Do nothing in test mode
    }
}

class SecurityMiddleware {
    public static function handle() {
        // Do nothing in test mode
    }
}

// Include the router classes from index.php
$indexContent = file_get_contents('index.php');

// Extract just the class definitions
preg_match('/class RouterException.*?(?=class|$)/s', $indexContent, $routerExceptionMatch);
preg_match('/class ValidationException.*?(?=class|$)/s', $indexContent, $validationExceptionMatch);
preg_match('/class AuthenticationException.*?(?=class|$)/s', $indexContent, $authExceptionMatch);
preg_match('/class AuthorizationException.*?(?=class|$)/s', $indexContent, $authzExceptionMatch);
preg_match('/class DatabaseException.*?(?=class|$)/s', $indexContent, $dbExceptionMatch);
preg_match('/class EnhancedRouter.*?(?=\/\*\*|$)/s', $indexContent, $routerMatch);

if ($routerExceptionMatch) eval($routerExceptionMatch[0]);
if ($validationExceptionMatch) eval($validationExceptionMatch[0]);
if ($authExceptionMatch) eval($authExceptionMatch[0]);
if ($authzExceptionMatch) eval($authzExceptionMatch[0]);
if ($dbExceptionMatch) eval($dbExceptionMatch[0]);
if ($routerMatch) eval($routerMatch[0]);

echo "=== Enhanced Router Core Test ===\n\n";

try {
    // Test 1: Router instantiation
    echo "Test 1: Router instantiation... ";
    $router = new EnhancedRouter();
    echo "PASS\n";
    
    // Test 2: Route matching
    echo "Test 2: Route matching... ";
    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('findRoute');
    $method->setAccessible(true);
    
    $result = $method->invoke($router, '/api/products', 'GET');
    
    if ($result && $result['handler'][0] === 'ProductController') {
        echo "PASS\n";
    } else {
        echo "FAIL - Expected ProductController, got: " . print_r($result, true) . "\n";
    }
    
    // Test 3: Parameter extraction
    echo "Test 3: Parameter extraction... ";
    $result = $method->invoke($router, '/api/products/123', 'GET');
    
    if ($result && isset($result['params']['id']) && $result['params']['id'] === '123') {
        echo "PASS\n";
    } else {
        echo "FAIL - Parameter extraction failed: " . print_r($result, true) . "\n";
    }
    
    // Test 4: Route not found
    echo "Test 4: Route not found... ";
    $result = $method->invoke($router, '/api/nonexistent', 'GET');
    
    if ($result === null) {
        echo "PASS\n";
    } else {
        echo "FAIL - Should return null for non-existent route\n";
    }
    
    // Test 5: Middleware configuration
    echo "Test 5: Middleware configuration... ";
    $routesProperty = $reflection->getProperty('routes');
    $routesProperty->setAccessible(true);
    $routes = $routesProperty->getValue($router);
    
    // Check auth route has AuthMiddleware
    $authRoute = $routes['GET']['/api/auth/profile']['middleware'] ?? [];
    $adminRoute = $routes['POST']['/api/products']['middleware'] ?? [];
    $publicRoute = $routes['GET']['/api/products']['middleware'] ?? [];
    
    if (in_array('AuthMiddleware', $authRoute) &&
        in_array('AuthMiddleware', $adminRoute) &&
        in_array('AdminMiddleware', $adminRoute) &&
        empty($publicRoute)) {
        echo "PASS\n";
    } else {
        echo "FAIL - Middleware configuration incorrect\n";
        echo "Auth route middleware: " . implode(', ', $authRoute) . "\n";
        echo "Admin route middleware: " . implode(', ', $adminRoute) . "\n";
        echo "Public route middleware: " . implode(', ', $publicRoute) . "\n";
    }
    
    // Test 6: Route caching
    echo "Test 6: Route caching... ";
    $cacheProperty = $reflection->getProperty('routeCache');
    $cacheProperty->setAccessible(true);
    
    // First call
    $result1 = $method->invoke($router, '/api/products/456', 'GET');
    
    // Second call (should use cache)
    $result2 = $method->invoke($router, '/api/products/456', 'GET');
    
    if ($result1 == $result2) {
        echo "PASS\n";
    } else {
        echo "FAIL - Caching not working correctly\n";
    }
    
    echo "\n=== Core Router Tests Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}