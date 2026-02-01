<?php
/**
 * Comprehensive API Testing Utility
 * 
 * This utility provides comprehensive testing capabilities for the Riya Collections API,
 * including endpoint validation, request/response testing, and interactive testing tools.
 * 
 * Requirements: 15.1, 15.3, 15.4
 */

require_once __DIR__ . '/config/environment.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Logger.php';

class ApiTestUtility {
    private $baseUrl;
    private $testResults = [];
    private $authTokens = [];
    
    public function __construct() {
        $this->baseUrl = $this->getBaseUrl();
        echo "Riya Collections API Testing Utility\n";
        echo str_repeat("=", 50) . "\n";
    }
    
    /**
     * Run comprehensive API tests
     */
    public function runComprehensiveTests() {
        echo "Running comprehensive API tests...\n\n";
        
        $this->testHealthEndpoints();
        $this->testAuthenticationFlow();
        $this->testProductEndpoints();
        $this->testValidationRules();
        $this->testErrorHandling();
        $this->testRateLimiting();
        $this->testSecurityFeatures();
        
        $this->printTestSummary();
    }
    
    /**
     * Test health check endpoints
     */
    private function testHealthEndpoints() {
        echo "Testing Health Check Endpoints\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test basic health check
        $response = $this->makeRequest('GET', '/api/health');
        $this->assertResponse($response, 'Basic health check', [
            'status_code' => 200,
            'has_success_field' => true,
            'has_data_field' => true
        ]);
        
        // Test health endpoint response structure
        if ($response && isset($response['body'])) {
            $body = json_decode($response['body'], true);
            $this->assertTrue(isset($body['data']['status']), 'Health check should have status field');
            $this->assertTrue(isset($body['data']['timestamp']), 'Health check should have timestamp field');
        }
        
        echo "\n";
    }
    
    /**
     * Test authentication flow
     */
    private function testAuthenticationFlow() {
        echo "Testing Authentication Flow\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test user registration
        $userData = [
            'email' => 'test_' . time() . '@example.com',
            'password' => 'TestPassword123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+919876543210'
        ];
        
        $response = $this->makeRequest('POST', '/api/auth/register', $userData);
        $this->assertResponse($response, 'User registration', [
            'status_code' => 201,
            'has_success_field' => true,
            'success_value' => true
        ]);
        
        // Extract token if registration successful
        if ($response && $response['status_code'] === 201) {
            $body = json_decode($response['body'], true);
            if (isset($body['data']['token'])) {
                $this->authTokens['user'] = $body['data']['token'];
            }
        }
        
        // Test user login
        $loginData = [
            'email' => $userData['email'],
            'password' => $userData['password']
        ];
        
        $response = $this->makeRequest('POST', '/api/auth/login', $loginData);
        $this->assertResponse($response, 'User login', [
            'status_code' => 200,
            'has_success_field' => true,
            'success_value' => true
        ]);
        
        // Test profile access with token
        if (isset($this->authTokens['user'])) {
            $headers = ['Authorization' => 'Bearer ' . $this->authTokens['user']];
            $response = $this->makeRequest('GET', '/api/auth/profile', null, $headers);
            $this->assertResponse($response, 'Profile access with token', [
                'status_code' => 200,
                'has_success_field' => true
            ]);
        }
        
        // Test profile access without token (should fail)
        $response = $this->makeRequest('GET', '/api/auth/profile');
        $this->assertResponse($response, 'Profile access without token', [
            'status_code' => 401,
            'has_success_field' => true,
            'success_value' => false
        ]);
        
        echo "\n";
    }
    
    /**
     * Test product endpoints
     */
    private function testProductEndpoints() {
        echo "Testing Product Endpoints\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test get all products
        $response = $this->makeRequest('GET', '/api/products');
        $this->assertResponse($response, 'Get all products', [
            'status_code' => 200,
            'has_success_field' => true,
            'has_data_field' => true
        ]);
        
        // Test products with pagination
        $response = $this->makeRequest('GET', '/api/products?page=1&limit=5');
        $this->assertResponse($response, 'Products with pagination', [
            'status_code' => 200,
            'has_pagination' => true
        ]);
        
        // Test product search
        $response = $this->makeRequest('GET', '/api/products?search=test');
        $this->assertResponse($response, 'Product search', [
            'status_code' => 200,
            'has_success_field' => true
        ]);
        
        // Test get specific product (assuming product ID 1 exists)
        $response = $this->makeRequest('GET', '/api/products/1');
        $this->assertResponse($response, 'Get specific product', [
            'status_code' => [200, 404], // Either exists or not found
            'has_success_field' => true
        ]);
        
        // Test get non-existent product
        $response = $this->makeRequest('GET', '/api/products/99999');
        $this->assertResponse($response, 'Get non-existent product', [
            'status_code' => 404,
            'has_success_field' => true,
            'success_value' => false
        ]);
        
        echo "\n";
    }
    
    /**
     * Test validation rules
     */
    private function testValidationRules() {
        echo "Testing Validation Rules\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test registration with invalid email
        $invalidData = [
            'email' => 'invalid-email',
            'password' => 'short',
            'first_name' => '',
            'last_name' => ''
        ];
        
        $response = $this->makeRequest('POST', '/api/auth/register', $invalidData);
        $this->assertResponse($response, 'Registration with invalid data', [
            'status_code' => 422,
            'has_success_field' => true,
            'success_value' => false,
            'has_errors_field' => true
        ]);
        
        // Test login with missing fields
        $response = $this->makeRequest('POST', '/api/auth/login', []);
        $this->assertResponse($response, 'Login with missing fields', [
            'status_code' => 422,
            'has_success_field' => true,
            'success_value' => false
        ]);
        
        echo "\n";
    }
    
    /**
     * Test error handling
     */
    private function testErrorHandling() {
        echo "Testing Error Handling\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test non-existent endpoint
        $response = $this->makeRequest('GET', '/api/nonexistent');
        $this->assertResponse($response, 'Non-existent endpoint', [
            'status_code' => 404
        ]);
        
        // Test invalid HTTP method
        $response = $this->makeRequest('PATCH', '/api/health');
        $this->assertResponse($response, 'Invalid HTTP method', [
            'status_code' => [404, 405] // Method not allowed or not found
        ]);
        
        // Test malformed JSON
        $response = $this->makeRequest('POST', '/api/auth/login', 'invalid-json', [], true);
        $this->assertResponse($response, 'Malformed JSON', [
            'status_code' => 400
        ]);
        
        echo "\n";
    }
    
    /**
     * Test rate limiting (if implemented)
     */
    private function testRateLimiting() {
        echo "Testing Rate Limiting\n";
        echo str_repeat("-", 30) . "\n";
        
        // Make multiple rapid requests to test rate limiting
        $rateLimitHit = false;
        for ($i = 0; $i < 10; $i++) {
            $response = $this->makeRequest('GET', '/api/health');
            if ($response && $response['status_code'] === 429) {
                $rateLimitHit = true;
                break;
            }
            usleep(100000); // 100ms delay
        }
        
        if ($rateLimitHit) {
            echo "✓ Rate limiting is active\n";
        } else {
            echo "ℹ Rate limiting not detected (may not be enabled in test environment)\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test security features
     */
    private function testSecurityFeatures() {
        echo "Testing Security Features\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test SQL injection attempt
        $sqlInjection = "'; DROP TABLE users; --";
        $response = $this->makeRequest('GET', '/api/products?search=' . urlencode($sqlInjection));
        $this->assertResponse($response, 'SQL injection attempt', [
            'status_code' => 200, // Should handle gracefully
            'no_sql_error' => true
        ]);
        
        // Test XSS attempt
        $xssPayload = '<script>alert("xss")</script>';
        $response = $this->makeRequest('GET', '/api/products?search=' . urlencode($xssPayload));
        $this->assertResponse($response, 'XSS attempt', [
            'status_code' => 200, // Should handle gracefully
            'no_script_tags' => true
        ]);
        
        // Test invalid token format
        $headers = ['Authorization' => 'Bearer invalid-token-format'];
        $response = $this->makeRequest('GET', '/api/auth/profile', null, $headers);
        $this->assertResponse($response, 'Invalid token format', [
            'status_code' => 401,
            'has_success_field' => true,
            'success_value' => false
        ]);
        
        echo "\n";
    }
    
    /**
     * Make HTTP request to API
     */
    private function makeRequest($method, $endpoint, $data = null, $headers = [], $rawData = false) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, // For testing only
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false
        ]);
        
        // Set headers
        $curlHeaders = ['Content-Type: application/json'];
        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $curlHeaders[] = "$key: $value";
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        
        // Set request body
        if ($data !== null) {
            if ($rawData) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if (curl_error($ch)) {
            echo "cURL Error: " . curl_error($ch) . "\n";
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        return [
            'status_code' => $httpCode,
            'headers' => $headers,
            'body' => $body
        ];
    }
    
    /**
     * Assert response meets expectations
     */
    private function assertResponse($response, $testName, $expectations) {
        $passed = true;
        $errors = [];
        
        if (!$response) {
            $passed = false;
            $errors[] = 'No response received';
        } else {
            // Check status code
            if (isset($expectations['status_code'])) {
                $expectedCodes = is_array($expectations['status_code']) 
                    ? $expectations['status_code'] 
                    : [$expectations['status_code']];
                
                if (!in_array($response['status_code'], $expectedCodes)) {
                    $passed = false;
                    $errors[] = "Expected status code " . implode(' or ', $expectedCodes) . 
                               ", got {$response['status_code']}";
                }
            }
            
            // Parse response body
            $body = json_decode($response['body'], true);
            
            // Check response structure
            if (isset($expectations['has_success_field']) && $expectations['has_success_field']) {
                if (!isset($body['success'])) {
                    $passed = false;
                    $errors[] = 'Response missing success field';
                }
            }
            
            if (isset($expectations['success_value'])) {
                if (!isset($body['success']) || $body['success'] !== $expectations['success_value']) {
                    $passed = false;
                    $errors[] = "Expected success={$expectations['success_value']}, got " . 
                               (isset($body['success']) ? $body['success'] : 'null');
                }
            }
            
            if (isset($expectations['has_data_field']) && $expectations['has_data_field']) {
                if (!isset($body['data'])) {
                    $passed = false;
                    $errors[] = 'Response missing data field';
                }
            }
            
            if (isset($expectations['has_errors_field']) && $expectations['has_errors_field']) {
                if (!isset($body['errors'])) {
                    $passed = false;
                    $errors[] = 'Response missing errors field';
                }
            }
            
            if (isset($expectations['has_pagination']) && $expectations['has_pagination']) {
                if (!isset($body['pagination'])) {
                    $passed = false;
                    $errors[] = 'Response missing pagination field';
                }
            }
            
            // Security checks
            if (isset($expectations['no_sql_error']) && $expectations['no_sql_error']) {
                if (stripos($response['body'], 'mysql') !== false || 
                    stripos($response['body'], 'sql') !== false) {
                    $passed = false;
                    $errors[] = 'Response contains SQL error information';
                }
            }
            
            if (isset($expectations['no_script_tags']) && $expectations['no_script_tags']) {
                if (stripos($response['body'], '<script>') !== false) {
                    $passed = false;
                    $errors[] = 'Response contains unescaped script tags';
                }
            }
        }
        
        // Record test result
        $this->testResults[] = [
            'name' => $testName,
            'passed' => $passed,
            'errors' => $errors,
            'status_code' => $response ? $response['status_code'] : null
        ];
        
        // Print result
        $status = $passed ? '✓' : '✗';
        $statusCode = $response ? " ({$response['status_code']})" : '';
        echo "$status $testName$statusCode\n";
        
        if (!$passed && !empty($errors)) {
            foreach ($errors as $error) {
                echo "  - $error\n";
            }
        }
    }
    
    /**
     * Assert condition is true
     */
    private function assertTrue($condition, $message) {
        if ($condition) {
            echo "✓ $message\n";
        } else {
            echo "✗ $message\n";
        }
    }
    
    /**
     * Print test summary
     */
    private function printTestSummary() {
        echo str_repeat("=", 50) . "\n";
        echo "Test Summary\n";
        echo str_repeat("=", 50) . "\n";
        
        $total = count($this->testResults);
        $passed = array_filter($this->testResults, function($result) {
            return $result['passed'];
        });
        $passedCount = count($passed);
        $failedCount = $total - $passedCount;
        
        echo "Total Tests: $total\n";
        echo "Passed: $passedCount\n";
        echo "Failed: $failedCount\n";
        echo "Success Rate: " . round(($passedCount / $total) * 100, 2) . "%\n\n";
        
        if ($failedCount > 0) {
            echo "Failed Tests:\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "- {$result['name']}\n";
                    foreach ($result['errors'] as $error) {
                        echo "  * $error\n";
                    }
                }
            }
        }
        
        echo "\nFor detailed API documentation, visit: {$this->baseUrl}/api/docs\n";
        echo "For interactive testing, visit: {$this->baseUrl}/api/test\n";
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = 'http'; // Default for CLI testing
        $host = 'localhost';
        $port = '';
        $path = '';
        
        // Try to detect from environment or use defaults
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $path = dirname($_SERVER['SCRIPT_NAME']);
        } else {
            // CLI mode - use configuration or defaults
            $host = env('TEST_HOST', 'localhost');
            $port = env('TEST_PORT', '');
            $path = env('TEST_PATH', '');
        }
        
        $url = $protocol . '://' . $host;
        if ($port) {
            $url .= ':' . $port;
        }
        if ($path && $path !== '/') {
            $url .= $path;
        }
        
        return $url;
    }
    
    /**
     * Interactive testing mode
     */
    public function interactiveMode() {
        echo "Interactive API Testing Mode\n";
        echo str_repeat("=", 30) . "\n";
        echo "Available commands:\n";
        echo "1. test [endpoint] - Test specific endpoint\n";
        echo "2. auth - Test authentication flow\n";
        echo "3. products - Test product endpoints\n";
        echo "4. health - Test health endpoints\n";
        echo "5. validate [json] - Validate JSON request\n";
        echo "6. help - Show this help\n";
        echo "7. exit - Exit interactive mode\n\n";
        
        while (true) {
            echo "api-test> ";
            $input = trim(fgets(STDIN));
            $parts = explode(' ', $input, 2);
            $command = $parts[0];
            $args = isset($parts[1]) ? $parts[1] : '';
            
            switch ($command) {
                case 'test':
                    if ($args) {
                        $response = $this->makeRequest('GET', $args);
                        if ($response) {
                            echo "Status: {$response['status_code']}\n";
                            echo "Response: " . $response['body'] . "\n\n";
                        }
                    } else {
                        echo "Usage: test [endpoint]\n";
                    }
                    break;
                    
                case 'auth':
                    $this->testAuthenticationFlow();
                    break;
                    
                case 'products':
                    $this->testProductEndpoints();
                    break;
                    
                case 'health':
                    $this->testHealthEndpoints();
                    break;
                    
                case 'validate':
                    if ($args) {
                        $decoded = json_decode($args, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            echo "✓ Valid JSON\n";
                            echo "Parsed: " . print_r($decoded, true) . "\n";
                        } else {
                            echo "✗ Invalid JSON: " . json_last_error_msg() . "\n";
                        }
                    } else {
                        echo "Usage: validate [json_string]\n";
                    }
                    break;
                    
                case 'help':
                    echo "Available commands:\n";
                    echo "1. test [endpoint] - Test specific endpoint\n";
                    echo "2. auth - Test authentication flow\n";
                    echo "3. products - Test product endpoints\n";
                    echo "4. health - Test health endpoints\n";
                    echo "5. validate [json] - Validate JSON request\n";
                    echo "6. help - Show this help\n";
                    echo "7. exit - Exit interactive mode\n\n";
                    break;
                    
                case 'exit':
                    echo "Goodbye!\n";
                    return;
                    
                default:
                    echo "Unknown command. Type 'help' for available commands.\n";
                    break;
            }
        }
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $tester = new ApiTestUtility();
    
    $mode = $argv[1] ?? 'comprehensive';
    
    switch ($mode) {
        case 'comprehensive':
        case 'all':
            $tester->runComprehensiveTests();
            break;
            
        case 'interactive':
        case 'i':
            $tester->interactiveMode();
            break;
            
        default:
            echo "Usage: php api_test_utility.php [comprehensive|interactive]\n";
            echo "  comprehensive - Run all tests (default)\n";
            echo "  interactive   - Interactive testing mode\n";
            break;
    }
}