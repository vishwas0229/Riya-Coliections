<?php
/**
 * Response Utility Unit Tests
 * 
 * Tests the Response utility class functionality including JSON formatting,
 * HTTP status codes, error handling, and API compatibility.
 * 
 * Requirements: 4.2, 13.1
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../config/security.php';

/**
 * Response Unit Tests
 */
class ResponseTest {
    private $testResults = [];
    private $testCount = 0;
    private $passCount = 0;
    
    public function __construct() {
        echo "Running Response Utility Tests...\n";
        $this->runAllTests();
        $this->printResults();
    }
    
    private function runAllTests() {
        $this->testJsonResponse();
        $this->testSuccessResponse();
        $this->testErrorResponse();
        $this->testValidationErrorResponse();
        $this->testStatusCodeMethods();
        $this->testPaginatedResponse();
        $this->testCreatePagination();
        $this->testPaginationEdgeCases();
        $this->testFormatCurrency();
        $this->testFormatDate();
        $this->testSanitizeData();
        $this->testCrudResponses();
        $this->testJsonEncodingError();
        $this->testRateLimitResponse();
        $this->testCustomHeaders();
        $this->testSuccessWithPaginationData();
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
    
    private function assertNull($value, $message = '') {
        $fullMessage = $message ?: "Expected null, got " . json_encode($value);
        $this->assert($value === null, $fullMessage);
    }
    
    private function assertStringContains($needle, $haystack, $message = '') {
        $condition = strpos($haystack, $needle) !== false;
        $fullMessage = $message ?: "Expected '$haystack' to contain '$needle'";
        $this->assert($condition, $fullMessage);
    }
    
    private function assertIsInt($value, $message = '') {
        $fullMessage = $message ?: "Expected integer, got " . gettype($value);
        $this->assert(is_int($value), $fullMessage);
    }
    
    private function captureResponse($callback) {
        ob_start();
        try {
            $callback();
        } catch (Exception $e) {
            // Response methods call exit, so we catch it here
        }
        $output = ob_get_clean();
        return $output;
    }
    
    private function printResults() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Response Utility Test Results\n";
        echo str_repeat("=", 50) . "\n";
        
        foreach ($this->testResults as $result) {
            echo $result . "\n";
        }
        
        echo str_repeat("-", 50) . "\n";
        echo "Tests: {$this->testCount}, Passed: {$this->passCount}, Failed: " . ($this->testCount - $this->passCount) . "\n";
        
        if ($this->passCount === $this->testCount) {
            echo "✓ All tests passed!\n";
        } else {
            echo "✗ Some tests failed!\n";
        }
        echo str_repeat("=", 50) . "\n";
    }
    
    /**
     * Test basic JSON response formatting
     */
    public function testJsonResponse() {
        $data = ['test' => 'value'];
        
        $output = $this->captureResponse(function() use ($data) {
            Response::json($data, 200);
        });
        
        $decoded = json_decode($output, true);
        
        $this->assertEquals(['test' => 'value'], $decoded, 'JSON response data matches');
        $this->assertEquals(JSON_ERROR_NONE, json_last_error(), 'JSON is valid');
    }
    
    /**
     * Test success response format
     */
    public function testSuccessResponse() {
        $data = ['id' => 1, 'name' => 'Test Product'];
        $message = 'Operation successful';
        
        $output = $this->captureResponse(function() use ($message, $data) {
            Response::success($message, $data);
        });
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success'], 'Success response has success=true');
        $this->assertEquals($message, $response['message'], 'Success response message matches');
        $this->assertEquals($data, $response['data'], 'Success response data matches');
        $this->assertNull($response['errors'], 'Success response has null errors');
    }
    
    /**
     * Test error response format
     */
    public function testErrorResponse() {
        $message = 'Test error message';
        $errors = [['field' => 'email', 'message' => 'Invalid email']];
        
        $output = $this->captureResponse(function() use ($message, $errors) {
            Response::error($message, 400, $errors);
        });
        
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success'], 'Error response has success=false');
        $this->assertEquals($message, $response['message'], 'Error response message matches');
        $this->assertNull($response['data'], 'Error response has null data');
        $this->assertEquals($errors, $response['errors'], 'Error response errors match');
    }
    
    /**
     * Test validation error formatting
     */
    public function testValidationErrorResponse() {
        $errors = [
            'email' => 'Email is required',
            'password' => ['Password too short', 'Password must contain numbers']
        ];
        
        $output = $this->captureResponse(function() use ($errors) {
            Response::validationError($errors);
        });
        
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success'], 'Validation error has success=false');
        $this->assertEquals('Validation failed', $response['message'], 'Validation error default message');
        $this->assertNull($response['data'], 'Validation error has null data');
        
        // Check formatted errors
        $expectedErrors = [
            ['field' => 'email', 'message' => 'Email is required'],
            ['field' => 'password', 'message' => 'Password too short'],
            ['field' => 'password', 'message' => 'Password must contain numbers']
        ];
        
        $this->assertEquals($expectedErrors, $response['errors'], 'Validation errors formatted correctly');
    }
    
    /**
     * Test HTTP status code methods
     */
    public function testStatusCodeMethods() {
        $testCases = [
            ['unauthorized', 'Unauthorized access'],
            ['forbidden', 'Access forbidden'],
            ['notFound', 'Resource not found'],
            ['methodNotAllowed', 'Method not allowed'],
            ['serverError', 'Internal server error']
        ];
        
        foreach ($testCases as [$method, $expectedMessage]) {
            $output = $this->captureResponse(function() use ($method) {
                Response::$method();
            });
            
            $response = json_decode($output, true);
            
            $this->assertFalse($response['success'], "$method response has success=false");
            $this->assertEquals($expectedMessage, $response['message'], "$method response message correct");
            $this->assertNull($response['data'], "$method response has null data");
        }
    }
    
    /**
     * Test pagination response
     */
    public function testPaginatedResponse() {
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2']
        ];
        
        $pagination = [
            'current_page' => 1,
            'per_page' => 10,
            'total_items' => 25,
            'total_pages' => 3,
            'has_next_page' => true,
            'has_prev_page' => false
        ];
        
        $output = $this->captureResponse(function() use ($items, $pagination) {
            Response::paginated($items, $pagination);
        });
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success'], 'Paginated response has success=true');
        $this->assertEquals($items, $response['data'], 'Paginated response data matches');
        $this->assertEquals($pagination, $response['pagination'], 'Paginated response pagination matches');
    }
    
    /**
     * Test pagination metadata creation
     */
    public function testCreatePagination() {
        $pagination = Response::createPagination(2, 10, 25);
        
        $expected = [
            'current_page' => 2,
            'per_page' => 10,
            'total_items' => 25,
            'total_pages' => 3,
            'has_next_page' => true,
            'has_prev_page' => true,
            'next_page' => 3,
            'prev_page' => 1
        ];
        
        $this->assertEquals($expected, $pagination, 'Pagination metadata created correctly');
    }
    
    /**
     * Test pagination edge cases
     */
    public function testPaginationEdgeCases() {
        // First page
        $firstPage = Response::createPagination(1, 10, 25);
        $this->assertFalse($firstPage['has_prev_page'], 'First page has no previous');
        $this->assertNull($firstPage['prev_page'], 'First page prev_page is null');
        
        // Last page
        $lastPage = Response::createPagination(3, 10, 25);
        $this->assertFalse($lastPage['has_next_page'], 'Last page has no next');
        $this->assertNull($lastPage['next_page'], 'Last page next_page is null');
        
        // Single page
        $singlePage = Response::createPagination(1, 10, 5);
        $this->assertEquals(1, $singlePage['total_pages'], 'Single page total_pages is 1');
        $this->assertFalse($singlePage['has_next_page'], 'Single page has no next');
        $this->assertFalse($singlePage['has_prev_page'], 'Single page has no previous');
    }
    
    /**
     * Test currency formatting
     */
    public function testFormatCurrency() {
        $formatted = Response::formatCurrency(1234.56);
        
        $expected = [
            'amount' => 1234.56,
            'formatted' => '₹1,234.56',
            'currency' => 'INR'
        ];
        
        $this->assertEquals($expected, $formatted, 'Currency formatted correctly');
    }
    
    /**
     * Test date formatting
     */
    public function testFormatDate() {
        $date = '2024-01-15 14:30:00';
        $formatted = Response::formatDate($date);
        
        $this->assertEquals('2024-01-15 14:30:00', $formatted['date'], 'Date format correct');
        $this->assertIsInt($formatted['timestamp'], 'Timestamp is integer');
        $this->assertStringContains('2024-01-15T14:30:00', $formatted['iso'], 'ISO format contains expected date');
        $this->assertStringContains('January 15, 2024', $formatted['human'], 'Human format contains expected date');
    }
    
    /**
     * Test data sanitization
     */
    public function testSanitizeData() {
        $data = [
            'username' => 'testuser',
            'password' => 'secret123',
            'api_key' => 'abc123',
            'normal_field' => 'normal_value'
        ];
        
        $sanitized = Response::sanitizeData($data);
        
        $this->assertEquals('testuser', $sanitized['username'], 'Username not sanitized');
        $this->assertEquals('[REDACTED]', $sanitized['password'], 'Password sanitized');
        $this->assertEquals('[REDACTED]', $sanitized['api_key'], 'API key sanitized');
        $this->assertEquals('normal_value', $sanitized['normal_field'], 'Normal field not sanitized');
    }
    
    /**
     * Test CRUD operation responses
     */
    public function testCrudResponses() {
        $data = ['id' => 1, 'name' => 'Test'];
        
        // Test created response
        $output = $this->captureResponse(function() use ($data) {
            Response::created($data);
        });
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success'], 'Created response has success=true');
        $this->assertEquals('Resource created successfully', $response['message'], 'Created response message correct');
        $this->assertEquals($data, $response['data'], 'Created response data matches');
        
        // Test updated response
        $output = $this->captureResponse(function() use ($data) {
            Response::updated($data);
        });
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success'], 'Updated response has success=true');
        $this->assertEquals('Resource updated successfully', $response['message'], 'Updated response message correct');
        
        // Test deleted response
        $output = $this->captureResponse(function() {
            Response::deleted();
        });
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success'], 'Deleted response has success=true');
        $this->assertEquals('Resource deleted successfully', $response['message'], 'Deleted response message correct');
        $this->assertNull($response['data'], 'Deleted response has null data');
    }
    
    /**
     * Test JSON encoding error handling
     */
    public function testJsonEncodingError() {
        // Create data that will cause JSON encoding to fail
        $invalidData = [
            'invalid' => "\xB1\x31" // Invalid UTF-8 sequence
        ];
        
        $output = $this->captureResponse(function() use ($invalidData) {
            Response::json($invalidData);
        });
        
        $response = json_decode($output, true);
        
        // Should return fallback error response
        $this->assertFalse($response['success'], 'JSON encoding error returns success=false');
        $this->assertStringContains('JSON encoding failed', $response['message'], 'JSON encoding error message correct');
    }
    
    /**
     * Test rate limit response
     */
    public function testRateLimitResponse() {
        $output = $this->captureResponse(function() {
            Response::rateLimitExceeded();
        });
        
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success'], 'Rate limit response has success=false');
        $this->assertEquals('Rate limit exceeded', $response['message'], 'Rate limit response message correct');
    }
    
    /**
     * Test response with custom headers
     */
    public function testCustomHeaders() {
        $data = ['test' => 'value'];
        $headers = ['X-Custom-Header' => 'custom-value'];
        
        $output = $this->captureResponse(function() use ($data, $headers) {
            Response::json($data, 200, $headers);
        });
        
        // Verify JSON output
        $decoded = json_decode($output, true);
        $this->assertEquals($data, $decoded, 'Custom headers response data correct');
    }
    
    /**
     * Test success response with pagination data
     */
    public function testSuccessWithPaginationData() {
        $paginatedData = [
            'items' => [['id' => 1], ['id' => 2]],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 5
            ]
        ];
        
        $output = $this->captureResponse(function() use ($paginatedData) {
            Response::success('Data retrieved', $paginatedData);
        });
        
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success'], 'Paginated success response has success=true');
        $this->assertEquals($paginatedData['items'], $response['data'], 'Paginated success response data correct');
        $this->assertEquals($paginatedData['pagination'], $response['pagination'], 'Paginated success response pagination correct');
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    new ResponseTest();
}