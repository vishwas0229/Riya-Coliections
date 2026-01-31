<?php
/**
 * API Endpoint Completeness Property Test
 * 
 * Property-based test to verify that all API endpoints available in the Node backend
 * have equivalent endpoints with the same path and HTTP method in the PHP backend.
 * 
 * **Property 6: API Endpoint Completeness**
 * **Validates: Requirements 4.1**
 */

require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class ApiEndpointCompletenessPropertyTest {
    private $router;
    private $nodeEndpoints;
    private $testResults = [];
    private $testCount = 0;
    private $passCount = 0;
    
    public function __construct() {
        echo "Running API Endpoint Completeness Property Tests...\n";
        $this->router = new EnhancedRouter();
        $this->nodeEndpoints = $this->getNodeBackendEndpoints();
        $this->runAllTests();
        $this->printResults();
    }
    
    private function runAllTests() {
        $this->testApiEndpointCompleteness();
        $this->testAllPhpEndpointsHaveValidHandlers();
        $this->testEndpointMiddlewareConsistency();
        $this->testHttpMethodSupport();
    }
    
    private function assert($condition, $message) {
        $this->testCount++;
        if ($condition) {
            $this->passCount++;
            $this->testResults[] = "✓ $message";
        } else {
            $this->testResults[] = "✗ $message";
        }
    }
    
    private function assertEquals($expected, $actual, $message = '') {
        $condition = $expected === $actual;
        $fullMessage = $message ?: "Expected " . json_encode($expected) . ", got " . json_encode($actual);
        $this->assert($condition, $fullMessage);
    }
    
    private function assertTrue($condition, $message = '') {
        $fullMessage = $message ?: "Expected true, got false";
        $this->assert($condition, $fullMessage);
    }
    
    private function assertFalse($condition, $message = '') {
        $fullMessage = $message ?: "Expected false, got true";
        $this->assert(!$condition, $fullMessage);
    }
    
    private function assertEmpty($array, $message = '') {
        $fullMessage = $message ?: "Expected empty array, got " . json_encode($array);
        $this->assert(empty($array), $fullMessage);
    }
    
    private function assertIsArray($value, $message = '') {
        $fullMessage = $message ?: "Expected array, got " . gettype($value);
        $this->assert(is_array($value), $fullMessage);
    }
    
    private function assertCount($expectedCount, $array, $message = '') {
        $actualCount = count($array);
        $fullMessage = $message ?: "Expected count $expectedCount, got $actualCount";
        $this->assert($actualCount === $expectedCount, $fullMessage);
    }
    
    private function printResults() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "API Endpoint Completeness Property Test Results\n";
        echo str_repeat("=", 60) . "\n";
        
        foreach ($this->testResults as $result) {
            echo $result . "\n";
        }
        
        echo str_repeat("-", 60) . "\n";
        echo "Tests: {$this->testCount}, Passed: {$this->passCount}, Failed: " . ($this->testCount - $this->passCount) . "\n";
        
        if ($this->passCount === $this->testCount) {
            echo "✓ All property tests passed!\n";
        } else {
            echo "✗ Some property tests failed!\n";
        }
        echo str_repeat("=", 60) . "\n";
    }
    
    /**
     * Property 6: API Endpoint Completeness
     * For any API endpoint available in the Node backend, an equivalent endpoint 
     * with the same path and HTTP method should exist in the PHP backend
     * **Validates: Requirements 4.1**
     */
    public function testApiEndpointCompleteness() {
        $missingEndpoints = [];
        $testCount = 0;
        
        foreach ($this->nodeEndpoints as $endpoint) {
            $method = $endpoint['method'];
            $path = $endpoint['path'];
            $testCount++;
            
            // Test if endpoint exists in PHP backend
            $routeExists = $this->checkRouteExists($method, $path);
            
            if (!$routeExists) {
                $missingEndpoints[] = "{$method} {$path}";
            }
            
            $this->assertTrue($routeExists, 
                "Endpoint {$method} {$path} from Node backend should exist in PHP backend");
        }
        
        // Log test results
        Logger::info('API Endpoint Completeness Test Results', [
            'total_endpoints_tested' => $testCount,
            'missing_endpoints' => count($missingEndpoints),
            'missing_endpoint_list' => $missingEndpoints
        ]);
        
        // Property assertion: No endpoints should be missing
        $this->assertEmpty($missingEndpoints, 
            'Missing endpoints in PHP backend: ' . implode(', ', $missingEndpoints));
    }
    
    /**
     * Property: All PHP endpoints should have valid handlers
     * **Validates: Requirements 4.1**
     */
    public function testAllPhpEndpointsHaveValidHandlers() {
        $reflection = new ReflectionClass($this->router);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);
        
        $invalidHandlers = [];
        $testCount = 0;
        
        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $path => $config) {
                $testCount++;
                $handler = $config['handler'];
                
                // Validate handler structure
                $this->assertIsArray($handler, "Handler for {$method} {$path} should be array");
                $this->assertCount(2, $handler, "Handler for {$method} {$path} should have 2 elements");
                
                [$controllerName, $methodName] = $handler;
                
                // Check if controller file exists
                $controllerFile = __DIR__ . "/../controllers/{$controllerName}.php";
                if (!file_exists($controllerFile)) {
                    $invalidHandlers[] = "{$method} {$path} -> {$controllerName} (file not found)";
                    continue;
                }
                
                // Check if controller class exists
                require_once $controllerFile;
                if (!class_exists($controllerName)) {
                    $invalidHandlers[] = "{$method} {$path} -> {$controllerName} (class not found)";
                    continue;
                }
                
                // Check if method exists
                if (!method_exists($controllerName, $methodName)) {
                    $invalidHandlers[] = "{$method} {$path} -> {$controllerName}::{$methodName} (method not found)";
                }
            }
        }
        
        Logger::info('PHP Endpoint Handler Validation Results', [
            'total_endpoints_tested' => $testCount,
            'invalid_handlers' => count($invalidHandlers),
            'invalid_handler_list' => $invalidHandlers
        ]);
        
        $this->assertEmpty($invalidHandlers, 
            'Invalid handlers found: ' . implode(', ', $invalidHandlers));
    }
    
    /**
     * Property: Endpoint middleware configuration should be consistent
     * **Validates: Requirements 4.1**
     */
    public function testEndpointMiddlewareConsistency() {
        $reflection = new ReflectionClass($this->router);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);
        
        $middlewareInconsistencies = [];
        
        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $path => $config) {
                $middleware = $config['middleware'] ?? [];
                
                // Property: Admin endpoints should have both AuthMiddleware and AdminMiddleware
                if (strpos($path, '/api/admin/') === 0) {
                    if (!in_array('AuthMiddleware', $middleware)) {
                        $middlewareInconsistencies[] = "{$method} {$path} missing AuthMiddleware";
                    }
                    if (!in_array('AdminMiddleware', $middleware)) {
                        $middlewareInconsistencies[] = "{$method} {$path} missing AdminMiddleware";
                    }
                }
                
                // Property: Protected endpoints (except auth) should have AuthMiddleware
                $protectedPaths = ['/api/orders', '/api/addresses', '/api/auth/profile'];
                foreach ($protectedPaths as $protectedPath) {
                    if (strpos($path, $protectedPath) === 0 && 
                        !in_array($path, ['/api/auth/login', '/api/auth/register'])) {
                        if (!in_array('AuthMiddleware', $middleware)) {
                            $middlewareInconsistencies[] = "{$method} {$path} missing AuthMiddleware";
                        }
                    }
                }
                
                // Property: Public endpoints should not have AuthMiddleware
                $publicPaths = ['/api/products', '/api/categories', '/api/health'];
                foreach ($publicPaths as $publicPath) {
                    if (strpos($path, $publicPath) === 0 && 
                        !strpos($path, '/api/admin/')) {
                        if (in_array('AuthMiddleware', $middleware)) {
                            $middlewareInconsistencies[] = "{$method} {$path} should not have AuthMiddleware";
                        }
                    }
                }
            }
        }
        
        $this->assertEmpty($middlewareInconsistencies, 
            'Middleware inconsistencies found: ' . implode(', ', $middlewareInconsistencies));
    }
    
    /**
     * Property: All endpoints should support proper HTTP methods
     * **Validates: Requirements 4.1**
     */
    public function testHttpMethodSupport() {
        $methodValidation = [];
        
        // Test various endpoint patterns with expected methods
        $endpointPatterns = [
            '/api/products' => ['GET'],
            '/api/products/{id}' => ['GET'],
            '/api/auth/login' => ['POST'],
            '/api/auth/profile' => ['GET', 'PUT'],
            '/api/orders' => ['GET', 'POST'],
            '/api/orders/{id}' => ['GET']
        ];
        
        foreach ($endpointPatterns as $pathPattern => $expectedMethods) {
            foreach ($expectedMethods as $method) {
                $routeExists = $this->checkRouteExists($method, $pathPattern);
                
                if (!$routeExists) {
                    $methodValidation[] = "{$method} {$pathPattern}";
                }
                
                $this->assertTrue($routeExists, 
                    "Expected method {$method} should be supported for {$pathPattern}");
            }
        }
        
        Logger::info('HTTP Method Support Test Results', [
            'missing_method_support' => $methodValidation
        ]);
    }
    
    /**
     * Check if a route exists in the PHP backend
     */
    private function checkRouteExists($method, $path) {
        $reflection = new ReflectionClass($this->router);
        $findRouteMethod = $reflection->getMethod('findRoute');
        $findRouteMethod->setAccessible(true);
        
        try {
            $result = $findRouteMethod->invoke($this->router, $path, $method);
            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get the list of endpoints that should exist based on Node.js backend
     * This represents the complete API surface that needs to be maintained
     */
    private function getNodeBackendEndpoints() {
        return [
            // Authentication endpoints
            ['method' => 'POST', 'path' => '/api/auth/register'],
            ['method' => 'POST', 'path' => '/api/auth/login'],
            ['method' => 'POST', 'path' => '/api/auth/refresh'],
            ['method' => 'POST', 'path' => '/api/auth/forgot-password'],
            ['method' => 'POST', 'path' => '/api/auth/reset-password'],
            ['method' => 'GET', 'path' => '/api/auth/profile'],
            ['method' => 'PUT', 'path' => '/api/auth/profile'],
            ['method' => 'POST', 'path' => '/api/auth/change-password'],
            ['method' => 'POST', 'path' => '/api/auth/logout'],
            ['method' => 'GET', 'path' => '/api/auth/sessions'],
            ['method' => 'GET', 'path' => '/api/auth/verify'],
            
            // Public product endpoints
            ['method' => 'GET', 'path' => '/api/products'],
            ['method' => 'GET', 'path' => '/api/products/{id}'],
            ['method' => 'GET', 'path' => '/api/products/search'],
            ['method' => 'GET', 'path' => '/api/products/featured'],
            
            // Public category endpoints
            ['method' => 'GET', 'path' => '/api/categories'],
            ['method' => 'GET', 'path' => '/api/categories/{id}'],
            ['method' => 'GET', 'path' => '/api/categories/{id}/products'],
            
            // Admin product endpoints
            ['method' => 'POST', 'path' => '/api/admin/products'],
            ['method' => 'PUT', 'path' => '/api/admin/products/{id}'],
            ['method' => 'DELETE', 'path' => '/api/admin/products/{id}'],
            ['method' => 'POST', 'path' => '/api/admin/products/{id}/images'],
            ['method' => 'PUT', 'path' => '/api/admin/products/{id}/stock'],
            ['method' => 'GET', 'path' => '/api/admin/products/stats'],
            ['method' => 'GET', 'path' => '/api/admin/products/low-stock'],
            
            // Admin category endpoints
            ['method' => 'POST', 'path' => '/api/admin/categories'],
            ['method' => 'PUT', 'path' => '/api/admin/categories/{id}'],
            ['method' => 'DELETE', 'path' => '/api/admin/categories/{id}'],
            ['method' => 'GET', 'path' => '/api/admin/categories/stats'],
            
            // Order endpoints
            ['method' => 'GET', 'path' => '/api/orders'],
            ['method' => 'GET', 'path' => '/api/orders/{id}'],
            ['method' => 'POST', 'path' => '/api/orders'],
            ['method' => 'PUT', 'path' => '/api/orders/{id}/status'],
            
            // Payment endpoints
            ['method' => 'POST', 'path' => '/api/payments/razorpay/create'],
            ['method' => 'POST', 'path' => '/api/payments/razorpay/verify'],
            ['method' => 'POST', 'path' => '/api/payments/cod'],
            ['method' => 'POST', 'path' => '/api/payments/webhook'],
            
            // Address endpoints
            ['method' => 'GET', 'path' => '/api/addresses'],
            ['method' => 'POST', 'path' => '/api/addresses'],
            ['method' => 'PUT', 'path' => '/api/addresses/{id}'],
            ['method' => 'DELETE', 'path' => '/api/addresses/{id}'],
            
            // Admin authentication endpoints
            ['method' => 'POST', 'path' => '/api/admin/login'],
            ['method' => 'GET', 'path' => '/api/admin/profile'],
            ['method' => 'PUT', 'path' => '/api/admin/profile'],
            ['method' => 'POST', 'path' => '/api/admin/change-password'],
            ['method' => 'POST', 'path' => '/api/admin/logout'],
            ['method' => 'GET', 'path' => '/api/admin/security-log'],
            
            // Admin management endpoints
            ['method' => 'GET', 'path' => '/api/admin/dashboard'],
            ['method' => 'GET', 'path' => '/api/admin/orders'],
            ['method' => 'GET', 'path' => '/api/admin/users'],
            
            // Health check endpoints
            ['method' => 'GET', 'path' => '/api/health'],
            ['method' => 'GET', 'path' => '/api/health/detailed'],
            
            // Utility endpoints
            ['method' => 'GET', 'path' => '/api/docs'],
            ['method' => 'GET', 'path' => '/uploads/{path}']
        ];
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    new ApiEndpointCompletenessPropertyTest();
}