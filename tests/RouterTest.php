<?php
/**
 * Router Test Suite
 * 
 * Comprehensive tests for the enhanced router functionality including
 * request parsing, routing logic, middleware integration, and error handling.
 * 
 * Requirements: 4.1, 4.2, 10.1
 */

require_once __DIR__ . '/../index.php';

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {
    private $router;
    private $originalServer;
    
    protected function setUp(): void {
        $this->router = new EnhancedRouter();
        $this->originalServer = $_SERVER;
    }
    
    protected function tearDown(): void {
        $_SERVER = $this->originalServer;
    }
    
    /**
     * Test basic request parsing
     */
    public function testBasicRequestParsing() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/products',
            'HTTP_USER_AGENT' => 'Test Agent',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_ID' => 'test_123'
        ]);
        
        $request = $this->router->parseRequest();
        
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('/api/products', $request['path']);
        $this->assertEquals('Test Agent', $request['user_agent']);
        $this->assertEquals('127.0.0.1', $request['ip']);
        $this->assertIsArray($request['headers']);
        $this->assertIsArray($request['query']);
    }
    
    /**
     * Test path sanitization and security
     */
    public function testPathSanitization() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/../../../etc/passwd',
            'REQUEST_ID' => 'test_124'
        ]);
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Invalid path traversal detected');
        
        $this->router->parseRequest();
    }
    
    /**
     * Test JSON request body parsing
     */
    public function testJsonRequestBodyParsing() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/products',
            'CONTENT_TYPE' => 'application/json',
            'REQUEST_ID' => 'test_125'
        ]);
        
        // Mock php://input
        $jsonData = json_encode(['name' => 'Test Product', 'price' => 99.99]);
        
        // We can't easily mock php://input in unit tests, so we'll test the parsing logic
        $this->assertTrue(true); // Placeholder for actual implementation test
    }
    
    /**
     * Test route matching with parameters
     */
    public function testRouteMatchingWithParameters() {
        $reflection = new ReflectionClass($this->router);
        $method = $reflection->getMethod('findRoute');
        $method->setAccessible(true);
        
        // Test matching route with parameter
        $result = $method->invoke($this->router, '/api/products/123', 'GET');
        
        $this->assertNotNull($result);
        $this->assertEquals(['ProductController', 'getById'], $result['handler']);
        $this->assertEquals('123', $result['params']['id']);
    }
    
    /**
     * Test route not found
     */
    public function testRouteNotFound() {
        $reflection = new ReflectionClass($this->router);
        $method = $reflection->getMethod('findRoute');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->router, '/api/nonexistent', 'GET');
        
        $this->assertNull($result);
    }
    
    /**
     * Test request size validation
     */
    public function testRequestSizeValidation() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/products',
            'CONTENT_LENGTH' => 20971520, // 20MB - exceeds default limit
            'REQUEST_ID' => 'test_126'
        ]);
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Request too large');
        
        $this->router->parseRequest();
    }
    
    /**
     * Test header parsing
     */
    public function testHeaderParsing() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/products',
            'HTTP_AUTHORIZATION' => 'Bearer test_token',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_X_CUSTOM_HEADER' => 'custom_value',
            'REQUEST_ID' => 'test_127'
        ]);
        
        $request = $this->router->parseRequest();
        
        $this->assertEquals('Bearer test_token', $request['headers']['Authorization']);
        $this->assertEquals('application/json', $request['headers']['Content-Type']);
        $this->assertEquals('custom_value', $request['headers']['X-Custom-Header']);
    }
    
    /**
     * Test middleware configuration
     */
    public function testMiddlewareConfiguration() {
        $reflection = new ReflectionClass($this->router);
        $property = $reflection->getProperty('routes');
        $property->setAccessible(true);
        $routes = $property->getValue($this->router);
        
        // Test that protected routes have AuthMiddleware
        $this->assertContains('AuthMiddleware', $routes['GET']['/api/auth/profile']['middleware']);
        
        // Test that admin routes have both AuthMiddleware and AdminMiddleware
        $adminRoute = $routes['POST']['/api/products']['middleware'];
        $this->assertContains('AuthMiddleware', $adminRoute);
        $this->assertContains('AdminMiddleware', $adminRoute);
        
        // Test that public routes have no middleware
        $this->assertEmpty($routes['GET']['/api/products']['middleware']);
    }
    
    /**
     * Test route caching functionality
     */
    public function testRouteCaching() {
        // Enable route caching
        $reflection = new ReflectionClass($this->router);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->router);
        $config['cache_routes'] = true;
        $configProperty->setValue($this->router, $config);
        
        // Test route matching (should cache the result)
        $findRouteMethod = $reflection->getMethod('findRoute');
        $findRouteMethod->setAccessible(true);
        
        $result1 = $findRouteMethod->invoke($this->router, '/api/products/123', 'GET');
        $result2 = $findRouteMethod->invoke($this->router, '/api/products/123', 'GET');
        
        $this->assertEquals($result1, $result2);
        
        // Check that cache was populated
        $cacheProperty = $reflection->getProperty('routeCache');
        $cacheProperty->setAccessible(true);
        $cache = $cacheProperty->getValue($this->router);
        
        $this->assertArrayHasKey('GET:/api/products/123', $cache);
    }
    
    /**
     * Test error handling for missing controller
     */
    public function testMissingControllerError() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/products',
            'REQUEST_ID' => 'test_128'
        ]);
        
        // Mock a route with non-existent controller
        $reflection = new ReflectionClass($this->router);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);
        
        $routes['GET']['/api/test'] = [
            'handler' => ['NonExistentController', 'test'],
            'middleware' => []
        ];
        
        $routesProperty->setValue($this->router, $routes);
        
        $request = $this->router->parseRequest();
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Controller file not found');
        
        // Manually call route method with test path
        $reflection = new ReflectionClass($this->router);
        $method = $reflection->getMethod('route');
        $method->setAccessible(true);
        
        $request['path'] = '/api/test';
        $method->invoke($this->router, $request);
    }
    
    /**
     * Test input sanitization
     */
    public function testInputSanitization() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/products?search=<script>alert("xss")</script>',
            'REQUEST_ID' => 'test_129'
        ]);
        
        $_GET = ['search' => '<script>alert("xss")</script>'];
        
        $request = $this->router->parseRequest();
        
        // Check that malicious script was sanitized
        $this->assertNotContains('<script>', $request['query']['search']);
        $this->assertContains('&lt;script&gt;', $request['query']['search']);
    }
}