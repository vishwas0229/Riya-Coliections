<?php
/**
 * Simple Router Test Runner
 * 
 * Basic test to verify the enhanced router functionality
 */

// Set up test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock environment variables for testing
putenv('APP_ENV=development');
putenv('DB_HOST=localhost');
putenv('DB_NAME=test_db');
putenv('DB_USER=test_user');
putenv('JWT_SECRET=test_secret_key_for_testing_purposes_only');

// Include the router
require_once 'index.php';

echo "=== Enhanced Router Test ===\n\n";

try {
    // Test 1: Basic router instantiation
    echo "Test 1: Router instantiation... ";
    $router = new EnhancedRouter();
    echo "PASS\n";
    
    // Test 2: Request parsing with basic data
    echo "Test 2: Basic request parsing... ";
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/api/products',
        'HTTP_USER_AGENT' => 'Test Agent',
        'REMOTE_ADDR' => '127.0.0.1',
        'REQUEST_ID' => 'test_001',
        'SCRIPT_NAME' => '/index.php'
    ];
    
    $request = $router->parseRequest();
    
    if ($request['method'] === 'GET' && $request['path'] === '/api/products') {
        echo "PASS\n";
    } else {
        echo "FAIL - Expected GET /api/products, got {$request['method']} {$request['path']}\n";
    }
    
    // Test 3: Route matching
    echo "Test 3: Route matching... ";
    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('findRoute');
    $method->setAccessible(true);
    
    $result = $method->invoke($router, '/api/products', 'GET');
    
    if ($result && $result['handler'][0] === 'ProductController') {
        echo "PASS\n";
    } else {
        echo "FAIL - Route not matched correctly\n";
    }
    
    // Test 4: Parameter extraction
    echo "Test 4: Parameter extraction... ";
    $result = $method->invoke($router, '/api/products/123', 'GET');
    
    if ($result && isset($result['params']['id']) && $result['params']['id'] === '123') {
        echo "PASS\n";
    } else {
        echo "FAIL - Parameter not extracted correctly\n";
    }
    
    // Test 5: Path traversal protection
    echo "Test 5: Path traversal protection... ";
    $_SERVER['REQUEST_URI'] = '/api/../../../etc/passwd';
    
    try {
        $router->parseRequest();
        echo "FAIL - Path traversal not blocked\n";
    } catch (RouterException $e) {
        if (strpos($e->getMessage(), 'traversal') !== false) {
            echo "PASS\n";
        } else {
            echo "FAIL - Wrong exception: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 6: Header parsing
    echo "Test 6: Header parsing... ";
    $_SERVER = array_merge($_SERVER, [
        'REQUEST_URI' => '/api/products',
        'HTTP_AUTHORIZATION' => 'Bearer test_token',
        'HTTP_CONTENT_TYPE' => 'application/json'
    ]);
    
    $request = $router->parseRequest();
    
    if (isset($request['headers']['Authorization']) && 
        $request['headers']['Authorization'] === 'Bearer test_token') {
        echo "PASS\n";
    } else {
        echo "FAIL - Headers not parsed correctly\n";
    }
    
    // Test 7: Middleware configuration
    echo "Test 7: Middleware configuration... ";
    $routesProperty = $reflection->getProperty('routes');
    $routesProperty->setAccessible(true);
    $routes = $routesProperty->getValue($router);
    
    $authProfileRoute = $routes['GET']['/api/auth/profile'];
    $adminRoute = $routes['POST']['/api/products'];
    
    if (in_array('AuthMiddleware', $authProfileRoute['middleware']) &&
        in_array('AuthMiddleware', $adminRoute['middleware']) &&
        in_array('AdminMiddleware', $adminRoute['middleware'])) {
        echo "PASS\n";
    } else {
        echo "FAIL - Middleware not configured correctly\n";
    }
    
    echo "\n=== All Tests Completed ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}