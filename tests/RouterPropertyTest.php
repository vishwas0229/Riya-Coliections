<?php
/**
 * Router Property-Based Test Suite
 * 
 * Property-based tests for the enhanced router to verify correctness
 * properties across many different inputs and scenarios.
 * 
 * Requirements: 4.1, 4.2, 10.1
 */

require_once __DIR__ . '/../index.php';

use PHPUnit\Framework\TestCase;

class RouterPropertyTest extends TestCase {
    private $router;
    
    protected function setUp(): void {
        $this->router = new EnhancedRouter();
    }
    
    /**
     * Property: Path sanitization should always prevent directory traversal
     * **Validates: Requirements 4.1, 10.1**
     * @test
     */
    public function testPathSanitizationProperty() {
        for ($i = 0; $i < 100; $i++) {
            $maliciousPath = $this->generateMaliciousPath();
            
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $maliciousPath,
                'REQUEST_ID' => 'prop_test_' . $i,
                'REMOTE_ADDR' => '127.0.0.1'
            ];
            
            try {
                $request = $this->router->parseRequest();
                
                // If parsing succeeds, path should not contain traversal patterns
                $this->assertNotContains('..', $request['path']);
                $this->assertNotContains('\\', $request['path']);
                
            } catch (RouterException $e) {
                // Exception is acceptable for malicious paths
                $this->assertContains('traversal', $e->getMessage());
            }
        }
    }
    
    /**
     * Property: Valid routes should always be found when they exist
     * **Validates: Requirements 4.1**
     * @test
     */
    public function testValidRouteMatchingProperty() {
        $validRoutes = [
            ['GET', '/api/products'],
            ['GET', '/api/products/123'],
            ['POST', '/api/auth/login'],
            ['GET', '/api/health'],
            ['POST', '/api/orders']
        ];
        
        $reflection = new ReflectionClass($this->router);
        $method = $reflection->getMethod('findRoute');
        $method->setAccessible(true);
        
        for ($i = 0; $i < 50; $i++) {
            $route = $validRoutes[array_rand($validRoutes)];
            $httpMethod = $route[0];
            $path = $route[1];
            
            $result = $method->invoke($this->router, $path, $httpMethod);
            
            // Valid routes should always be found
            $this->assertNotNull($result, "Route {$httpMethod} {$path} should be found");
            $this->assertArrayHasKey('handler', $result);
            $this->assertArrayHasKey('params', $result);
            $this->assertIsArray($result['handler']);
            $this->assertCount(2, $result['handler']);
        }
    }
    
    /**
     * Property: Invalid routes should never be matched
     * **Validates: Requirements 4.1**
     * @test
     */
    public function testInvalidRouteRejectionProperty() {
        $reflection = new ReflectionClass($this->router);
        $method = $reflection->getMethod('findRoute');
        $method->setAccessible(true);
        
        for ($i = 0; $i < 100; $i++) {
            $invalidPath = $this->generateInvalidPath();
            $httpMethod = $this->generateRandomHttpMethod();
            
            $result = $method->invoke($this->router, $invalidPath, $httpMethod);
            
            // Invalid routes should never be found
            $this->assertNull($result, "Invalid route {$httpMethod} {$invalidPath} should not be found");
        }
    }
    
    /**
     * Property: Request parsing should handle all valid HTTP methods
     * **Validates: Requirements 4.1, 4.2**
     * @test
     */
    public function testHttpMethodHandlingProperty() {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD'];
        
        for ($i = 0; $i < 50; $i++) {
            $method = $validMethods[array_rand($validMethods)];
            
            $_SERVER = [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => '/api/products',
                'REQUEST_ID' => 'method_test_' . $i,
                'REMOTE_ADDR' => '127.0.0.1'
            ];
            
            $request = $this->router->parseRequest();
            
            // Method should be preserved correctly
            $this->assertEquals($method, $request['method']);
            $this->assertIsString($request['path']);
            $this->assertIsArray($request['headers']);
        }
    }
    
    /**
     * Property: Header parsing should preserve all valid headers
     * **Validates: Requirements 4.2**
     * @test
     */
    public function testHeaderParsingProperty() {
        for ($i = 0; $i < 100; $i++) {
            $headers = $this->generateRandomHeaders();
            
            $_SERVER = array_merge([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/products',
                'REQUEST_ID' => 'header_test_' . $i,
                'REMOTE_ADDR' => '127.0.0.1'
            ], $headers);
            
            $request = $this->router->parseRequest();
            
            // All HTTP_ headers should be parsed and formatted correctly
            foreach ($headers as $serverKey => $value) {
                if (strpos($serverKey, 'HTTP_') === 0) {
                    $headerName = str_replace('_', '-', substr($serverKey, 5));
                    $headerName = ucwords(strtolower($headerName), '-');
                    
                    $this->assertArrayHasKey($headerName, $request['headers']);
                    $this->assertEquals($value, $request['headers'][$headerName]);
                }
            }
        }
    }
    
    /**
     * Property: Input sanitization should remove all malicious content
     * **Validates: Requirements 10.1**
     * @test
     */
    public function testInputSanitizationProperty() {
        for ($i = 0; $i < 100; $i++) {
            $maliciousInput = $this->generateMaliciousInput();
            
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/products?search=' . urlencode($maliciousInput),
                'REQUEST_ID' => 'sanitize_test_' . $i,
                'REMOTE_ADDR' => '127.0.0.1'
            ];
            
            $_GET = ['search' => $maliciousInput];
            
            $request = $this->router->parseRequest();
            
            // Malicious content should be sanitized
            $sanitizedValue = $request['query']['search'];
            
            $this->assertNotContains('<script>', $sanitizedValue);
            $this->assertNotContains('javascript:', $sanitizedValue);
            $this->assertNotContains('onload=', $sanitizedValue);
            $this->assertNotContains('SELECT * FROM', $sanitizedValue);
            $this->assertNotContains('DROP TABLE', $sanitizedValue);
        }
    }
    
    /**
     * Property: Route parameters should be correctly extracted
     * **Validates: Requirements 4.1**
     * @test
     */
    public function testParameterExtractionProperty() {
        $reflection = new ReflectionClass($this->router);
        $method = $reflection->getMethod('findRoute');
        $method->setAccessible(true);
        
        for ($i = 0; $i < 100; $i++) {
            $productId = $this->generateRandomId();
            $path = "/api/products/{$productId}";
            
            $result = $method->invoke($this->router, $path, 'GET');
            
            $this->assertNotNull($result);
            $this->assertArrayHasKey('params', $result);
            $this->assertArrayHasKey('id', $result['params']);
            $this->assertEquals($productId, $result['params']['id']);
        }
    }
    
    /**
     * Property: Request size limits should be enforced
     * **Validates: Requirements 10.1**
     * @test
     */
    public function testRequestSizeLimitProperty() {
        for ($i = 0; $i < 50; $i++) {
            $contentLength = rand(10485760, 20971520); // 10MB to 20MB
            
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/products',
                'CONTENT_LENGTH' => $contentLength,
                'REQUEST_ID' => 'size_test_' . $i,
                'REMOTE_ADDR' => '127.0.0.1'
            ];
            
            $this->expectException(RouterException::class);
            $this->expectExceptionMessage('Request too large');
            
            $this->router->parseRequest();
        }
    }
    
    /**
     * Generate malicious path for testing
     */
    private function generateMaliciousPath() {
        $patterns = [
            '/api/../../../etc/passwd',
            '/api/..\\..\\..\\windows\\system32',
            '/api/products/../../../config/database.php',
            '/api/orders/..%2F..%2F..%2Fetc%2Fpasswd',
            '/api/users/....//....//....//etc/passwd'
        ];
        
        return $patterns[array_rand($patterns)];
    }
    
    /**
     * Generate invalid path for testing
     */
    private function generateInvalidPath() {
        $invalidPaths = [
            '/api/nonexistent',
            '/api/invalid/endpoint',
            '/api/products/invalid/action',
            '/invalid/api/path',
            '/api/orders/999999/invalid'
        ];
        
        return $invalidPaths[array_rand($invalidPaths)];
    }
    
    /**
     * Generate random HTTP method
     */
    private function generateRandomHttpMethod() {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        return $methods[array_rand($methods)];
    }
    
    /**
     * Generate random headers for testing
     */
    private function generateRandomHeaders() {
        $headers = [];
        $headerNames = ['AUTHORIZATION', 'CONTENT_TYPE', 'USER_AGENT', 'ACCEPT', 'ACCEPT_LANGUAGE'];
        
        $numHeaders = rand(1, count($headerNames));
        $selectedHeaders = array_rand($headerNames, $numHeaders);
        
        if (!is_array($selectedHeaders)) {
            $selectedHeaders = [$selectedHeaders];
        }
        
        foreach ($selectedHeaders as $index) {
            $headerName = 'HTTP_' . $headerNames[$index];
            $headers[$headerName] = $this->generateRandomHeaderValue();
        }
        
        return $headers;
    }
    
    /**
     * Generate random header value
     */
    private function generateRandomHeaderValue() {
        $values = [
            'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9',
            'application/json',
            'Mozilla/5.0 (compatible; Test/1.0)',
            'application/json, text/plain, */*',
            'en-US,en;q=0.9'
        ];
        
        return $values[array_rand($values)];
    }
    
    /**
     * Generate malicious input for testing
     */
    private function generateMaliciousInput() {
        $maliciousInputs = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src="x" onload="alert(1)">',
            "'; DROP TABLE users; --",
            'SELECT * FROM users WHERE id = 1',
            '<iframe src="javascript:alert(1)"></iframe>',
            'onload="alert(1)"',
            '<?php system($_GET["cmd"]); ?>'
        ];
        
        return $maliciousInputs[array_rand($maliciousInputs)];
    }
    
    /**
     * Generate random ID for testing
     */
    private function generateRandomId() {
        return rand(1, 999999);
    }
}