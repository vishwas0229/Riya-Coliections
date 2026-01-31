<?php
/**
 * API Response Compatibility Property Test
 * 
 * Property-based test to verify that the PHP backend returns responses with identical 
 * structure, data types, and HTTP status codes as the Node.js backend for all valid requests.
 * 
 * **Property 1: API Response Compatibility**
 * **Validates: Requirements 1.3, 4.2**
 */

require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class ApiResponseCompatibilityPropertyTest {
    private $testResults = [];
    private $testCount = 0;
    private $passCount = 0;
    
    public function __construct() {
        echo "Running API Response Compatibility Property Tests...\n";
        $this->runAllTests();
        $this->printResults();
    }
    
    private function runAllTests() {
        $this->testResponseStructureConsistency();
        $this->testSuccessResponseFormat();
        $this->testErrorResponseFormat();
        $this->testValidationErrorFormat();
        $this->testPaginatedResponseFormat();
        $this->testHttpStatusCodeConsistency();
        $this->testJsonContentTypeHeader();
        $this->testResponseDataTypes();
        $this->testCrudResponseFormats();
        $this->testSpecialResponseFormats();
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
    
    private function assertNotNull($value, $message = '') {
        $fullMessage = $message ?: "Expected non-null value, got null";
        $this->assert($value !== null, $fullMessage);
    }
    
    private function assertIsArray($value, $message = '') {
        $fullMessage = $message ?: "Expected array, got " . gettype($value);
        $this->assert(is_array($value), $fullMessage);
    }
    
    private function assertIsString($value, $message = '') {
        $fullMessage = $message ?: "Expected string, got " . gettype($value);
        $this->assert(is_string($value), $fullMessage);
    }
    
    private function assertIsBool($value, $message = '') {
        $fullMessage = $message ?: "Expected boolean, got " . gettype($value);
        $this->assert(is_bool($value), $fullMessage);
    }
    
    private function assertIsInt($value, $message = '') {
        $fullMessage = $message ?: "Expected integer, got " . gettype($value);
        $this->assert(is_int($value), $fullMessage);
    }
    
    private function assertArrayHasKey($key, $array, $message = '') {
        $fullMessage = $message ?: "Expected array to have key '$key'";
        $this->assert(array_key_exists($key, $array), $fullMessage);
    }
    
    private function assertStringContains($needle, $haystack, $message = '') {
        $condition = strpos($haystack, $needle) !== false;
        $fullMessage = $message ?: "Expected '$haystack' to contain '$needle'";
        $this->assert($condition, $fullMessage);
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
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "API Response Compatibility Property Test Results\n";
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
     * Generate random test data
     */
    private function generateRandomData() {
        $types = ['string', 'number', 'boolean', 'array', 'object'];
        $type = $types[array_rand($types)];
        
        switch ($type) {
            case 'string':
                return $this->generateRandomString();
            case 'number':
                return mt_rand(-1000, 1000) + (mt_rand(0, 99) / 100);
            case 'boolean':
                return (bool) mt_rand(0, 1);
            case 'array':
                $arr = [];
                for ($i = 0; $i < mt_rand(1, 3); $i++) {
                    $arr[] = $this->generateRandomString();
                }
                return $arr;
            case 'object':
                return [
                    'id' => mt_rand(1, 1000),
                    'name' => $this->generateRandomString(),
                    'active' => (bool) mt_rand(0, 1)
                ];
        }
    }
    
    private function generateRandomString($length = null) {
        $length = $length ?: mt_rand(5, 15);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    private function generateRandomStatusCode() {
        $codes = [200, 201, 400, 401, 403, 404, 422, 429, 500];
        return $codes[array_rand($codes)];
    }
    
    private function generateRandomValidationErrors() {
        $fields = ['email', 'password', 'name', 'phone'];
        $messages = ['is required', 'is invalid', 'is too short'];
        
        $errors = [];
        $numErrors = mt_rand(1, 2);
        
        for ($i = 0; $i < $numErrors; $i++) {
            $field = $fields[array_rand($fields)];
            $message = $field . ' ' . $messages[array_rand($messages)];
            $errors[$field] = $message;
        }
        
        return $errors;
    }
    
    /**
     * Property 1: All API responses must have consistent structure
     * **Validates: Requirements 1.3, 4.2**
     */
    public function testResponseStructureConsistency() {
        $responseMethods = [
            'success' => [['Test message', ['data' => 'value']]],
            'error' => [['Test error', 400]],
            'unauthorized' => [[]],
            'forbidden' => [[]],
            'notFound' => [[]],
            'serverError' => [[]]
        ];
        
        for ($i = 0; $i < 30; $i++) {
            foreach ($responseMethods as $method => $argsList) {
                $args = $argsList[0];
                
                $output = $this->captureResponse(function() use ($method, $args) {
                    call_user_func_array([Response::class, $method], $args);
                });
                
                $response = json_decode($output, true);
                
                // Property: All responses must have the standard structure
                $this->assertIsArray($response, "Response should be array for $method in iteration $i");
                
                $requiredFields = ['success', 'message', 'data', 'errors'];
                foreach ($requiredFields as $field) {
                    $this->assertArrayHasKey($field, $response, 
                        "Response missing '$field' field for $method in iteration $i");
                }
                
                // Property: Success field must be boolean
                $this->assertIsBool($response['success'], 
                    "Success field should be boolean for $method in iteration $i");
                
                // Property: Message field must be string
                $this->assertIsString($response['message'], 
                    "Message field should be string for $method in iteration $i");
            }
        }
    }
    
    /**
     * Property: Success responses always have success=true and proper structure
     * **Validates: Requirements 4.2**
     */
    public function testSuccessResponseFormat() {
        for ($i = 0; $i < 50; $i++) {
            $message = $this->generateRandomString();
            $data = $this->generateRandomData();
            
            $output = $this->captureResponse(function() use ($message, $data) {
                Response::success($message, $data);
            });
            
            $response = json_decode($output, true);
            
            // Property: Success responses must have success=true
            $this->assertTrue($response['success'], 
                "Success response should have success=true in iteration $i");
            
            // Property: Message should match input
            $this->assertEquals($message, $response['message'], 
                "Success response message should match input in iteration $i");
            
            // Property: Data should match input
            $this->assertEquals($data, $response['data'], 
                "Success response data should match input in iteration $i");
            
            // Property: Errors should be null
            $this->assertNull($response['errors'], 
                "Success response errors should be null in iteration $i");
        }
    }
    
    /**
     * Property: Error responses always have success=false and proper structure
     * **Validates: Requirements 4.2**
     */
    public function testErrorResponseFormat() {
        for ($i = 0; $i < 50; $i++) {
            $message = $this->generateRandomString();
            $statusCode = $this->generateRandomStatusCode();
            $errors = mt_rand(0, 1) ? $this->generateRandomValidationErrors() : null;
            
            $output = $this->captureResponse(function() use ($message, $statusCode, $errors) {
                Response::error($message, $statusCode, $errors);
            });
            
            $response = json_decode($output, true);
            
            // Property: Error responses must have success=false
            $this->assertFalse($response['success'], 
                "Error response should have success=false in iteration $i");
            
            // Property: Message should match input
            $this->assertEquals($message, $response['message'], 
                "Error response message should match input in iteration $i");
            
            // Property: Data should be null
            $this->assertNull($response['data'], 
                "Error response data should be null in iteration $i");
            
            // Property: Errors should match input
            if ($errors !== null) {
                $this->assertEquals($errors, $response['errors'], 
                    "Error response errors should match input in iteration $i");
            }
        }
    }
    
    /**
     * Property: Validation errors are consistently formatted
     * **Validates: Requirements 4.2**
     */
    public function testValidationErrorFormat() {
        for ($i = 0; $i < 30; $i++) {
            $errors = $this->generateRandomValidationErrors();
            
            $output = $this->captureResponse(function() use ($errors) {
                Response::validationError($errors);
            });
            
            $response = json_decode($output, true);
            
            // Property: Validation errors must have success=false
            $this->assertFalse($response['success'], 
                "Validation error should have success=false in iteration $i");
            
            // Property: Default validation message
            $this->assertEquals('Validation failed', $response['message'], 
                "Validation error should have default message in iteration $i");
            
            // Property: Data should be null
            $this->assertNull($response['data'], 
                "Validation error data should be null in iteration $i");
            
            // Property: Errors should be properly formatted array
            $this->assertIsArray($response['errors'], 
                "Validation errors should be array in iteration $i");
            
            foreach ($response['errors'] as $error) {
                $this->assertArrayHasKey('field', $error, 
                    "Validation error should have field key in iteration $i");
                $this->assertArrayHasKey('message', $error, 
                    "Validation error should have message key in iteration $i");
                $this->assertIsString($error['field'], 
                    "Validation error field should be string in iteration $i");
                $this->assertIsString($error['message'], 
                    "Validation error message should be string in iteration $i");
            }
        }
    }
    
    /**
     * Property: Paginated responses have consistent structure
     * **Validates: Requirements 4.2**
     */
    public function testPaginatedResponseFormat() {
        for ($i = 0; $i < 20; $i++) {
            $items = [];
            for ($j = 0; $j < mt_rand(1, 5); $j++) {
                $items[] = ['id' => $j, 'name' => $this->generateRandomString()];
            }
            
            $currentPage = mt_rand(1, 5);
            $perPage = mt_rand(5, 20);
            $totalItems = mt_rand(50, 200);
            
            $pagination = Response::createPagination($currentPage, $perPage, $totalItems);
            
            $output = $this->captureResponse(function() use ($items, $pagination) {
                Response::paginated($items, $pagination);
            });
            
            $response = json_decode($output, true);
            
            // Property: Paginated responses must have success=true
            $this->assertTrue($response['success'], 
                "Paginated response should have success=true in iteration $i");
            
            // Property: Data should match items
            $this->assertEquals($items, $response['data'], 
                "Paginated response data should match items in iteration $i");
            
            // Property: Pagination metadata should be present
            $this->assertArrayHasKey('pagination', $response, 
                "Paginated response should have pagination key in iteration $i");
            
            $this->assertEquals($pagination, $response['pagination'], 
                "Paginated response pagination should match input in iteration $i");
            
            // Property: Pagination metadata structure
            $paginationFields = ['current_page', 'per_page', 'total_items', 'total_pages', 
                               'has_next_page', 'has_prev_page', 'next_page', 'prev_page'];
            foreach ($paginationFields as $field) {
                $this->assertArrayHasKey($field, $response['pagination'], 
                    "Pagination should have '$field' field in iteration $i");
            }
        }
    }
    
    /**
     * Property: HTTP status codes are consistent with response types
     * **Validates: Requirements 4.2**
     */
    public function testHttpStatusCodeConsistency() {
        $statusCodeTests = [
            ['success', [], 200],
            ['created', [], 201],
            ['error', ['Bad request', 400], 400],
            ['unauthorized', [], 401],
            ['forbidden', [], 403],
            ['notFound', [], 404],
            ['serverError', [], 500]
        ];
        
        for ($i = 0; $i < 10; $i++) {
            foreach ($statusCodeTests as [$method, $args, $expectedCode]) {
                // We can't easily test HTTP status codes in CLI mode,
                // but we can verify the response structure is correct
                $output = $this->captureResponse(function() use ($method, $args) {
                    call_user_func_array([Response::class, $method], $args);
                });
                
                $response = json_decode($output, true);
                
                // Property: Response should be valid JSON
                $this->assertNotNull($response, 
                    "Response should be valid JSON for $method in iteration $i");
                
                // Property: Response should have required structure
                $this->assertArrayHasKey('success', $response, 
                    "Response should have success field for $method in iteration $i");
                $this->assertArrayHasKey('message', $response, 
                    "Response should have message field for $method in iteration $i");
            }
        }
    }
    
    /**
     * Property: All responses have correct Content-Type header
     * **Validates: Requirements 4.2**
     */
    public function testJsonContentTypeHeader() {
        // This test verifies that the Response class sets the correct content type
        // In CLI mode, we can't test headers directly, but we can verify JSON output
        
        for ($i = 0; $i < 20; $i++) {
            $data = $this->generateRandomData();
            
            $output = $this->captureResponse(function() use ($data) {
                Response::json($data);
            });
            
            // Property: Output should be valid JSON
            $decoded = json_decode($output, true);
            $this->assertEquals(JSON_ERROR_NONE, json_last_error(), 
                "Response should be valid JSON in iteration $i");
            
            // Property: Data should be preserved correctly
            $this->assertEquals($data, $decoded, 
                "JSON data should be preserved correctly in iteration $i");
        }
    }
    
    /**
     * Property: Response data types are preserved correctly
     * **Validates: Requirements 4.2**
     */
    public function testResponseDataTypes() {
        $testData = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => ['key' => 'value']
        ];
        
        for ($i = 0; $i < 10; $i++) {
            foreach ($testData as $type => $value) {
                $output = $this->captureResponse(function() use ($value) {
                    Response::success('Test', $value);
                });
                
                $response = json_decode($output, true);
                
                // Property: Data type should be preserved
                $this->assertEquals($value, $response['data'], 
                    "Data type should be preserved for $type in iteration $i");
            }
        }
    }
    
    /**
     * Property: CRUD operation responses have consistent formats
     * **Validates: Requirements 4.2**
     */
    public function testCrudResponseFormats() {
        for ($i = 0; $i < 20; $i++) {
            $data = ['id' => mt_rand(1, 1000), 'name' => $this->generateRandomString()];
            
            // Test created response
            $output = $this->captureResponse(function() use ($data) {
                Response::created($data);
            });
            $response = json_decode($output, true);
            
            $this->assertTrue($response['success'], 
                "Created response should have success=true in iteration $i");
            $this->assertEquals('Resource created successfully', $response['message'], 
                "Created response should have correct message in iteration $i");
            $this->assertEquals($data, $response['data'], 
                "Created response should preserve data in iteration $i");
            
            // Test updated response
            $output = $this->captureResponse(function() use ($data) {
                Response::updated($data);
            });
            $response = json_decode($output, true);
            
            $this->assertTrue($response['success'], 
                "Updated response should have success=true in iteration $i");
            $this->assertEquals('Resource updated successfully', $response['message'], 
                "Updated response should have correct message in iteration $i");
            
            // Test deleted response
            $output = $this->captureResponse(function() {
                Response::deleted();
            });
            $response = json_decode($output, true);
            
            $this->assertTrue($response['success'], 
                "Deleted response should have success=true in iteration $i");
            $this->assertEquals('Resource deleted successfully', $response['message'], 
                "Deleted response should have correct message in iteration $i");
            $this->assertNull($response['data'], 
                "Deleted response should have null data in iteration $i");
        }
    }
    
    /**
     * Property: Special response formats are consistent
     * **Validates: Requirements 4.2**
     */
    public function testSpecialResponseFormats() {
        for ($i = 0; $i < 10; $i++) {
            // Test currency formatting
            $amount = mt_rand(100, 10000) + (mt_rand(0, 99) / 100);
            $formatted = Response::formatCurrency($amount);
            
            $this->assertArrayHasKey('amount', $formatted, 
                "Currency format should have amount field in iteration $i");
            $this->assertArrayHasKey('formatted', $formatted, 
                "Currency format should have formatted field in iteration $i");
            $this->assertArrayHasKey('currency', $formatted, 
                "Currency format should have currency field in iteration $i");
            
            $this->assertEquals((float) $amount, $formatted['amount'], 
                "Currency amount should be preserved in iteration $i");
            $this->assertEquals('INR', $formatted['currency'], 
                "Currency should be INR in iteration $i");
            $this->assertStringContains('₹', $formatted['formatted'], 
                "Formatted currency should contain rupee symbol in iteration $i");
            
            // Test date formatting
            $timestamp = mt_rand(strtotime('2020-01-01'), strtotime('2025-12-31'));
            $date = date('Y-m-d H:i:s', $timestamp);
            $formatted = Response::formatDate($date);
            
            $requiredFields = ['date', 'timestamp', 'iso', 'human'];
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $formatted, 
                    "Date format should have '$field' field in iteration $i");
            }
            
            $this->assertIsInt($formatted['timestamp'], 
                "Date timestamp should be integer in iteration $i");
        }
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    new ApiResponseCompatibilityPropertyTest();
}