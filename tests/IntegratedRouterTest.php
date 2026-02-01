<?php
/**
 * Integrated Router Test Suite
 * 
 * Tests for the enhanced IntegratedRouter functionality including
 * request classification, SPA routing, and static asset handling.
 * 
 * Requirements: 2.5, 4.1, 4.2, 4.3
 */

require_once __DIR__ . '/../public/index.php';

use PHPUnit\Framework\TestCase;

class IntegratedRouterTest extends TestCase {
    private $router;
    private $originalServer;
    
    protected function setUp(): void {
        $this->router = new IntegratedRouter();
        $this->originalServer = $_SERVER;
    }
    
    protected function tearDown(): void {
        $_SERVER = $this->originalServer;
    }
    
    /**
     * Test request type classification for API requests
     */
    public function testClassifyApiRequest() {
        $this->assertEquals('api', $this->router->classifyRequestType('/api/products'));
        $this->assertEquals('api', $this->router->classifyRequestType('/api/auth/login'));
        $this->assertEquals('api', $this->router->classifyRequestType('/api/orders/123'));
    }
    
    /**
     * Test request type classification for static assets
     */
    public function testClassifyAssetRequest() {
        $this->assertEquals('asset', $this->router->classifyRequestType('/assets/css/main.css'));
        $this->assertEquals('asset', $this->router->classifyRequestType('/uploads/image.jpg'));
        $this->assertEquals('asset', $this->router->classifyRequestType('/src/js/main.js'));
        $this->assertEquals('asset', $this->router->classifyRequestType('/images/logo.png'));
    }
    
    /**
     * Test request type classification for frontend routes
     */
    public function testClassifyFrontendRequest() {
        $this->assertEquals('frontend', $this->router->classifyRequestType('/'));
        $this->assertEquals('frontend', $this->router->classifyRequestType('/products'));
        $this->assertEquals('frontend', $this->router->classifyRequestType('/categories'));
        $this->assertEquals('frontend', $this->router->classifyRequestType('/products/123'));
        $this->assertEquals('frontend', $this->router->classifyRequestType('/pages/about'));
    }
    
    /**
     * Test API request detection
     */
    public function testIsApiRequest() {
        $this->assertTrue($this->router->isApiRequest('/api/products'));
        $this->assertTrue($this->router->isApiRequest('/api/auth/login'));
        $this->assertFalse($this->router->isApiRequest('/products'));
        $this->assertFalse($this->router->isApiRequest('/assets/css/main.css'));
    }
    
    /**
     * Test static asset detection
     */
    public function testIsStaticAsset() {
        $this->assertTrue($this->router->isStaticAsset('/assets/css/main.css'));
        $this->assertTrue($this->router->isStaticAsset('/uploads/image.jpg'));
        $this->assertTrue($this->router->isStaticAsset('/src/js/main.js'));
        $this->assertFalse($this->router->isStaticAsset('/api/products'));
        $this->assertFalse($this->router->isStaticAsset('/products'));
        
        // Test file extensions
        $this->assertTrue($this->router->isStaticAsset('/test.css'));
        $this->assertTrue($this->router->isStaticAsset('/test.js'));
        $this->assertTrue($this->router->isStaticAsset('/test.png'));
        $this->assertFalse($this->router->isStaticAsset('/test')); // No extension
    }
    
    /**
     * Test frontend route detection
     */
    public function testIsFrontendRoute() {
        $this->assertTrue($this->router->isFrontendRoute('/'));
        $this->assertTrue($this->router->isFrontendRoute('/products'));
        $this->assertTrue($this->router->isFrontendRoute('/products/123'));
        $this->assertTrue($this->router->isFrontendRoute('/categories/1'));
        $this->assertTrue($this->router->isFrontendRoute('/pages/about'));
        
        // Should exclude API and asset paths
        $this->assertFalse($this->router->isFrontendRoute('/api/products'));
        $this->assertFalse($this->router->isFrontendRoute('/assets/css/main.css'));
    }
    
    /**
     * Test enhanced static asset path validation
     */
    public function testEnhancedStaticAssetDetection() {
        // Test new asset directories
        $this->assertTrue($this->router->isStaticAsset('/src/css/main.css'));
        $this->assertTrue($this->router->isStaticAsset('/src/js/components/navigation.js'));
        
        // Test that paths without extensions are not considered assets
        $this->assertFalse($this->router->isStaticAsset('/products'));
        $this->assertFalse($this->router->isStaticAsset('/categories'));
    }
    
    /**
     * Test request parsing with enhanced context
     */
    public function testRequestParsingWithContext() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/products/123',
            'HTTP_USER_AGENT' => 'Test Browser',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_ID' => 'test_request_123'
        ]);
        
        $request = $this->router->parseRequest();
        
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('/products/123', $request['path']);
        $this->assertEquals('Test Browser', $request['user_agent']);
        $this->assertEquals('127.0.0.1', $request['ip']);
        $this->assertEquals('test_request_123', $request['id']);
        $this->assertIsArray($request['headers']);
    }
    
    /**
     * Test path traversal security
     */
    public function testPathTraversalSecurity() {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/products/../../../etc/passwd',
            'REQUEST_ID' => 'test_security_123'
        ]);
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Invalid path traversal detected');
        
        $this->router->parseRequest();
    }
    
    /**
     * Test MIME type initialization
     */
    public function testMimeTypeInitialization() {
        $reflection = new ReflectionClass($this->router);
        $property = $reflection->getProperty('mimeTypes');
        $property->setAccessible(true);
        $mimeTypes = $property->getValue($this->router);
        
        // Test common MIME types
        $this->assertEquals('text/css', $mimeTypes['css']);
        $this->assertEquals('application/javascript', $mimeTypes['js']);
        $this->assertEquals('image/jpeg', $mimeTypes['jpg']);
        $this->assertEquals('image/png', $mimeTypes['png']);
        $this->assertEquals('font/woff2', $mimeTypes['woff2']);
    }
}