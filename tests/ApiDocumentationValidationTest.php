<?php
/**
 * API Documentation Validation Test
 * 
 * Comprehensive test suite to validate API documentation accuracy,
 * endpoint functionality, and testing utilities.
 * 
 * **Validates: Requirements 15.1, 15.3, 15.4**
 */

require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../controllers/ApiController.php';

use PHPUnit\Framework\TestCase;

class ApiDocumentationValidationTest extends TestCase {
    private $apiController;
    private $baseUrl;
    
    protected function setUp(): void {
        $this->apiController = new ApiController();
        $this->baseUrl = $this->getBaseUrl();
    }
    
    /**
     * Test API documentation endpoint accessibility
     * **Validates: Requirements 15.1**
     */
    public function testApiDocumentationEndpoint() {
        // Capture documentation output
        ob_start();
        try {
            $this->apiController->documentation();
        } catch (Exception $e) {
            // Response methods call exit, so we catch it here
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertIsArray($response, 'Documentation should return valid JSON');
        $this->assertTrue($response['success'], 'Documentation request should be successful');
        $this->assertArrayHasKey('data', $response, 'Documentation should have data field');
        
        $data = $response['data'];
        
        // Validate documentation structure
        $this->assertArrayHasKey('api', $data, 'Documentation should have API info');
        $this->assertArrayHasKey('authentication', $data, 'Documentation should have auth info');
        $this->assertArrayHasKey('endpoints', $data, 'Documentation should have endpoints');
        $this->assertArrayHasKey('response_format', $data, 'Documentation should have response format');
        $this->assertArrayHasKey('status_codes', $data, 'Documentation should have status codes');
        
        // Validate API info
        $apiInfo = $data['api'];
        $this->assertEquals('Riya Collections API', $apiInfo['name']);
        $this->assertEquals('1.0.0', $apiInfo['version']);
        $this->assertNotEmpty($apiInfo['base_url']);
        
        // Validate authentication info
        $authInfo = $data['authentication'];
        $this->assertEquals('Bearer Token (JWT)', $authInfo['type']);
        $this->assertStringContains('Bearer', $authInfo['header']);
        
        // Validate endpoints structure
        $endpoints = $data['endpoints'];
        $this->assertArrayHasKey('authentication', $endpoints);
        $this->assertArrayHasKey('products', $endpoints);
        $this->assertArrayHasKey('orders', $endpoints);
        $this->assertArrayHasKey('payments', $endpoints);
        $this->assertArrayHasKey('utility', $endpoints);
    }
    
    /**
     * Test endpoint documentation completeness
     * **Validates: Requirements 15.1**
     */
    public function testEndpointDocumentationCompleteness() {
        ob_start();
        try {
            $this->apiController->documentation();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $endpoints = $response['data']['endpoints'];
        
        // Test authentication endpoints
        $authEndpoints = $endpoints['authentication'];
        $this->assertArrayHasKey('POST /api/auth/register', $authEndpoints);
        $this->assertArrayHasKey('POST /api/auth/login', $authEndpoints);
        $this->assertArrayHasKey('GET /api/auth/profile', $authEndpoints);
        
        // Validate endpoint structure
        $registerEndpoint = $authEndpoints['POST /api/auth/register'];
        $this->assertArrayHasKey('description', $registerEndpoint);
        $this->assertArrayHasKey('auth_required', $registerEndpoint);
        $this->assertArrayHasKey('parameters', $registerEndpoint);
        $this->assertArrayHasKey('example_request', $registerEndpoint);
        $this->assertArrayHasKey('example_response', $registerEndpoint);
        $this->assertArrayHasKey('validation_rules', $registerEndpoint);
        
        // Validate parameter documentation
        $parameters = $registerEndpoint['parameters'];
        $this->assertArrayHasKey('email', $parameters);
        $this->assertArrayHasKey('password', $parameters);
        $this->assertArrayHasKey('first_name', $parameters);
        $this->assertArrayHasKey('last_name', $parameters);
        
        // Validate example request structure
        $exampleRequest = $registerEndpoint['example_request'];
        $this->assertArrayHasKey('email', $exampleRequest);
        $this->assertArrayHasKey('password', $exampleRequest);
        $this->assertArrayHasKey('first_name', $exampleRequest);
        $this->assertArrayHasKey('last_name', $exampleRequest);
        
        // Validate example response structure
        $exampleResponse = $registerEndpoint['example_response'];
        $this->assertArrayHasKey('success', $exampleResponse);
        $this->assertArrayHasKey('message', $exampleResponse);
        $this->assertArrayHasKey('data', $exampleResponse);
        $this->assertArrayHasKey('errors', $exampleResponse);
    }
    
    /**
     * Test API testing interface endpoint
     * **Validates: Requirements 15.3**
     */
    public function testApiTestingInterface() {
        ob_start();
        try {
            $this->apiController->testInterface();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertIsArray($response, 'Test interface should return valid JSON');
        $this->assertTrue($response['success'], 'Test interface request should be successful');
        $this->assertArrayHasKey('data', $response, 'Test interface should have data field');
        
        $data = $response['data'];
        
        // Validate testing interface structure
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('base_url', $data);
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertArrayHasKey('authentication', $data);
        $this->assertArrayHasKey('test_data', $data);
        $this->assertArrayHasKey('validation_tools', $data);
        $this->assertArrayHasKey('example_requests', $data);
        
        // Validate test credentials
        $auth = $data['authentication'];
        $this->assertArrayHasKey('test_user', $auth);
        $this->assertArrayHasKey('test_admin', $auth);
        
        $testUser = $auth['test_user'];
        $this->assertArrayHasKey('email', $testUser);
        $this->assertArrayHasKey('password', $testUser);
        $this->assertArrayHasKey('note', $testUser);
        
        // Validate test data
        $testData = $data['test_data'];
        $this->assertArrayHasKey('users', $testData);
        $this->assertArrayHasKey('products', $testData);
        $this->assertArrayHasKey('orders', $testData);
        $this->assertArrayHasKey('addresses', $testData);
        
        // Validate validation tools
        $validationTools = $data['validation_tools'];
        $this->assertArrayHasKey('request_validator', $validationTools);
        $this->assertArrayHasKey('response_validator', $validationTools);
        $this->assertArrayHasKey('schema_validator', $validationTools);
    }
    
    /**
     * Test request validation functionality
     * **Validates: Requirements 15.4**
     */
    public function testRequestValidation() {
        // Test valid request validation
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        $validRequest = [
            'endpoint' => '/api/auth/login',
            'method' => 'POST',
            'headers' => ['Authorization' => 'Bearer token123'],
            'body' => ['email' => 'test@example.com', 'password' => 'password123']
        ];
        
        // Mock input
        $this->mockInput(json_encode($validRequest));
        
        ob_start();
        try {
            $this->apiController->validateRequest();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertIsArray($response, 'Validation should return valid JSON');
        $this->assertTrue($response['success'], 'Valid request validation should be successful');
        $this->assertArrayHasKey('data', $response, 'Validation should have data field');
        
        $validation = $response['data'];
        $this->assertArrayHasKey('endpoint', $validation);
        $this->assertArrayHasKey('method', $validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
        $this->assertArrayHasKey('suggestions', $validation);
        
        $this->assertEquals('/api/auth/login', $validation['endpoint']);
        $this->assertEquals('POST', $validation['method']);
        $this->assertTrue($validation['valid']);
    }
    
    /**
     * Test invalid request validation
     * **Validates: Requirements 15.4**
     */
    public function testInvalidRequestValidation() {
        $invalidRequest = [
            'endpoint' => 'invalid-endpoint',
            'method' => 'INVALID',
            'headers' => [],
            'body' => 'invalid-json'
        ];
        
        $this->mockInput(json_encode($invalidRequest));
        
        ob_start();
        try {
            $this->apiController->validateRequest();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $validation = $response['data'];
        
        $this->assertFalse($validation['valid'], 'Invalid request should fail validation');
        $this->assertNotEmpty($validation['errors'], 'Invalid request should have errors');
        
        // Check for specific validation errors
        $errors = $validation['errors'];
        $this->assertContains('Endpoint must start with /api/', $errors);
        $this->assertContains('Invalid HTTP method. Allowed: GET, POST, PUT, DELETE, PATCH', $errors);
    }
    
    /**
     * Test example requests functionality
     * **Validates: Requirements 15.3**
     */
    public function testExampleRequests() {
        ob_start();
        try {
            $this->apiController->testInterface();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $exampleRequests = $response['data']['example_requests'];
        
        // Validate cURL examples
        $this->assertArrayHasKey('curl_examples', $exampleRequests);
        $curlExamples = $exampleRequests['curl_examples'];
        
        $this->assertArrayHasKey('login', $curlExamples);
        $this->assertArrayHasKey('get_products', $curlExamples);
        $this->assertArrayHasKey('authenticated_request', $curlExamples);
        
        // Validate cURL command structure
        $loginExample = $curlExamples['login'];
        $this->assertArrayHasKey('description', $loginExample);
        $this->assertArrayHasKey('command', $loginExample);
        $this->assertStringContains('curl', $loginExample['command']);
        $this->assertStringContains('/api/auth/login', $loginExample['command']);
        
        // Validate JavaScript examples
        $this->assertArrayHasKey('javascript_examples', $exampleRequests);
        $jsExamples = $exampleRequests['javascript_examples'];
        
        $this->assertArrayHasKey('fetch_login', $jsExamples);
        $this->assertArrayHasKey('authenticated_fetch', $jsExamples);
        
        // Validate JavaScript code structure
        $fetchLogin = $jsExamples['fetch_login'];
        $this->assertArrayHasKey('description', $fetchLogin);
        $this->assertArrayHasKey('code', $fetchLogin);
        $this->assertStringContains('fetch(', $fetchLogin['code']);
        $this->assertStringContains('/api/auth/login', $fetchLogin['code']);
        
        // Validate Postman collection info
        $this->assertArrayHasKey('postman_collection', $exampleRequests);
        $postmanInfo = $exampleRequests['postman_collection'];
        
        $this->assertArrayHasKey('description', $postmanInfo);
        $this->assertArrayHasKey('download_url', $postmanInfo);
        $this->assertArrayHasKey('variables', $postmanInfo);
        
        $variables = $postmanInfo['variables'];
        $this->assertArrayHasKey('base_url', $variables);
        $this->assertArrayHasKey('user_token', $variables);
        $this->assertArrayHasKey('admin_token', $variables);
    }
    
    /**
     * Test test execution simulation
     * **Validates: Requirements 15.4**
     */
    public function testTestExecution() {
        $testRequest = [
            'endpoint' => '/api/health',
            'method' => 'GET',
            'headers' => [],
            'body' => null
        ];
        
        $this->mockInput(json_encode($testRequest));
        
        ob_start();
        try {
            $this->apiController->executeTest();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertIsArray($response, 'Test execution should return valid JSON');
        $this->assertTrue($response['success'], 'Test execution should be successful');
        $this->assertArrayHasKey('data', $response, 'Test execution should have data field');
        
        $result = $response['data'];
        
        // Validate test execution result structure
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('validation', $result);
        $this->assertArrayHasKey('suggestions', $result);
        
        // Validate request info
        $request = $result['request'];
        $this->assertArrayHasKey('endpoint', $request);
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayHasKey('headers', $request);
        $this->assertArrayHasKey('body', $request);
        $this->assertArrayHasKey('timestamp', $request);
        
        // Validate response simulation
        $responseData = $result['response'];
        $this->assertArrayHasKey('status_code', $responseData);
        $this->assertArrayHasKey('headers', $responseData);
        $this->assertArrayHasKey('body', $responseData);
        $this->assertArrayHasKey('response_time', $responseData);
        
        // Validate response body structure
        $body = $responseData['body'];
        $this->assertArrayHasKey('success', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('data', $body);
        
        // Validate validation results
        $validation = $result['validation'];
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
        $this->assertArrayHasKey('suggestions', $validation);
    }
    
    /**
     * Test response format consistency
     * **Validates: Requirements 15.1**
     */
    public function testResponseFormatConsistency() {
        ob_start();
        try {
            $this->apiController->documentation();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $responseFormats = $response['data']['response_format'];
        
        // Validate success response format
        $this->assertArrayHasKey('success', $responseFormats);
        $successFormat = $responseFormats['success'];
        
        $this->assertTrue($successFormat['success']);
        $this->assertEquals('string', $successFormat['message']);
        $this->assertEquals('object|array|null', $successFormat['data']);
        $this->assertNull($successFormat['errors']);
        
        // Validate error response format
        $this->assertArrayHasKey('error', $responseFormats);
        $errorFormat = $responseFormats['error'];
        
        $this->assertFalse($errorFormat['success']);
        $this->assertEquals('string', $errorFormat['message']);
        $this->assertNull($errorFormat['data']);
        $this->assertEquals('array|null', $errorFormat['errors']);
        
        // Validate paginated response format
        $this->assertArrayHasKey('paginated', $responseFormats);
        $paginatedFormat = $responseFormats['paginated'];
        
        $this->assertTrue($paginatedFormat['success']);
        $this->assertEquals('string', $paginatedFormat['message']);
        $this->assertEquals('array', $paginatedFormat['data']);
        $this->assertArrayHasKey('pagination', $paginatedFormat);
        $this->assertNull($paginatedFormat['errors']);
        
        // Validate pagination structure
        $pagination = $paginatedFormat['pagination'];
        $expectedPaginationFields = [
            'current_page', 'per_page', 'total_items', 'total_pages',
            'has_next_page', 'has_prev_page', 'next_page', 'prev_page'
        ];
        
        foreach ($expectedPaginationFields as $field) {
            $this->assertArrayHasKey($field, $pagination);
        }
    }
    
    /**
     * Test status codes documentation
     * **Validates: Requirements 15.1**
     */
    public function testStatusCodesDocumentation() {
        ob_start();
        try {
            $this->apiController->documentation();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $statusCodes = $response['data']['status_codes'];
        
        // Validate common HTTP status codes
        $expectedCodes = [200, 201, 400, 401, 403, 404, 422, 429, 500];
        
        foreach ($expectedCodes as $code) {
            $this->assertArrayHasKey($code, $statusCodes, "Status code $code should be documented");
            $this->assertIsString($statusCodes[$code], "Status code $code should have description");
            $this->assertNotEmpty($statusCodes[$code], "Status code $code description should not be empty");
        }
        
        // Validate specific status code descriptions
        $this->assertStringContains('OK', $statusCodes[200]);
        $this->assertStringContains('Created', $statusCodes[201]);
        $this->assertStringContains('Bad Request', $statusCodes[400]);
        $this->assertStringContains('Unauthorized', $statusCodes[401]);
        $this->assertStringContains('Forbidden', $statusCodes[403]);
        $this->assertStringContains('Not Found', $statusCodes[404]);
        $this->assertStringContains('Validation', $statusCodes[422]);
        $this->assertStringContains('Rate limit', $statusCodes[429]);
        $this->assertStringContains('Server error', $statusCodes[500]);
    }
    
    /**
     * Test error handling documentation
     * **Validates: Requirements 15.1**
     */
    public function testErrorHandlingDocumentation() {
        ob_start();
        try {
            $this->apiController->documentation();
        } catch (Exception $e) {
            // Response methods call exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $errorHandling = $response['data']['error_handling'];
        
        // Validate validation errors documentation
        $this->assertArrayHasKey('validation_errors', $errorHandling);
        $validationErrors = $errorHandling['validation_errors'];
        
        $this->assertArrayHasKey('format', $validationErrors);
        $format = $validationErrors['format'];
        
        $this->assertFalse($format['success']);
        $this->assertEquals('Validation failed', $format['message']);
        $this->assertArrayHasKey('errors', $format);
        $this->assertIsArray($format['errors']);
        
        // Validate error format structure
        $errorExample = $format['errors'][0];
        $this->assertArrayHasKey('field', $errorExample);
        $this->assertArrayHasKey('message', $errorExample);
        
        // Validate common errors documentation
        $this->assertArrayHasKey('common_errors', $errorHandling);
        $commonErrors = $errorHandling['common_errors'];
        
        $expectedErrors = [
            'INVALID_TOKEN', 'MISSING_FIELD', 'INVALID_FORMAT',
            'RESOURCE_NOT_FOUND', 'PERMISSION_DENIED'
        ];
        
        foreach ($expectedErrors as $errorCode) {
            $this->assertArrayHasKey($errorCode, $commonErrors);
            $this->assertIsString($commonErrors[$errorCode]);
            $this->assertNotEmpty($commonErrors[$errorCode]);
        }
    }
    
    /**
     * Mock input for testing
     */
    private function mockInput($data) {
        // Create a temporary file with the test data
        $tempFile = tempnam(sys_get_temp_dir(), 'api_test_input');
        file_put_contents($tempFile, $data);
        
        // Override php://input stream
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', 'MockPhpInputStream');
        MockPhpInputStream::$data = $data;
    }
    
    /**
     * Get base URL for testing
     */
    private function getBaseUrl() {
        return 'http://localhost';
    }
}

/**
 * Mock class for php://input stream
 */
class MockPhpInputStream {
    public static $data = '';
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }
    
    public function stream_stat() {
        return [];
    }
}