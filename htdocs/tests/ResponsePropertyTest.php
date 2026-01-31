<?php
/**
 * Response Utility Property-Based Tests
 * 
 * Property-based tests for the Response utility class to verify universal
 * properties hold across all valid inputs and maintain API compatibility.
 * 
 * Requirements: 4.2, 13.1
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../config/security.php';

/**
 * Response Property-Based Tests
 */
class ResponsePropertyTest {
    private $testResults = [];
    private $testCount = 0;
    private $passCount = 0;
    
    public function __construct() {
        echo "Running Response Property-Based Tests...\n";
        $this->runAllTests();
        $this->printResults();
    }
    
    private function runAllTests() {
        $this->testAllResponsesProduceValidJson();
        $this->testSuccessResponsesAlwaysHaveSuccessTrue();
        $this->testErrorResponsesAlwaysHaveSuccessFalse();
        $this->testValidationErrorsAreProperlyFormatted();
        $this->testPaginationMetadataIsConsistent();
        $this->testCurrencyFormattingIsConsistent();
        $this->testDataSanitizationRemovesSensitiveInfo();
        $this->testDateFormattingProducesConsistentStructure();
        $this->testResponseStructureConsistency();
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
    
    private function assertStringContains($needle, $haystack, $message = '') {
        $condition = strpos($haystack, $needle) !== false;
        $fullMessage = $message ?: "Expected '$haystack' to contain '$needle'";
        $this->assert($condition, $fullMessage);
    }
    
    private function assertIsInt($value, $message = '') {
        $fullMessage = $message ?: "Expected integer, got " . gettype($value);
        $this->assert(is_int($value), $fullMessage);
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
    
    private function assertNotEmpty($value, $message = '') {
        $fullMessage = $message ?: "Expected non-empty value";
        $this->assert(!empty($value), $fullMessage);
    }
    
    private function assertArrayHasKey($key, $array, $message = '') {
        $fullMessage = $message ?: "Expected array to have key '$key'";
        $this->assert(array_key_exists($key, $array), $fullMessage);
    }
    
    private function assertGreaterThanOrEqual($expected, $actual, $message = '') {
        $fullMessage = $message ?: "Expected $actual to be >= $expected";
        $this->assert($actual >= $expected, $fullMessage);
    }
    
    private function assertMatchesRegularExpression($pattern, $string, $message = '') {
        $fullMessage = $message ?: "Expected '$string' to match pattern '$pattern'";
        $this->assert(preg_match($pattern, $string), $fullMessage);
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
        echo "Response Property-Based Test Results\n";
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
     * Generate random valid JSON data
     */
    private function generateRandomJsonData() {
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
                for ($i = 0; $i < mt_rand(1, 5); $i++) {
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
    
    /**
     * Generate random string
     */
    private function generateRandomString($length = null) {
        $length = $length ?: mt_rand(5, 20);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Generate random HTTP status code
     */
    private function generateRandomStatusCode() {
        $codes = [200, 201, 400, 401, 403, 404, 405, 422, 429, 500, 503];
        return $codes[array_rand($codes)];
    }
    
    /**
     * Generate random validation errors
     */
    private function generateRandomValidationErrors() {
        $fields = ['email', 'password', 'name', 'phone', 'address'];
        $messages = ['is required', 'is invalid', 'is too short', 'is too long', 'contains invalid characters'];
        
        $errors = [];
        $numErrors = mt_rand(1, 3);
        
        for ($i = 0; $i < $numErrors; $i++) {
            $field = $fields[array_rand($fields)];
            $message = $field . ' ' . $messages[array_rand($messages)];
            
            if (mt_rand(0, 1)) {
                // Single error for field
                $errors[$field] = $message;
            } else {
                // Multiple errors for field
                if (!isset($errors[$field])) {
                    $errors[$field] = [];
                }
                if (is_array($errors[$field])) {
                    $errors[$field][] = $message;
                } else {
                    $errors[$field] = [$errors[$field], $message];
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Property: All JSON responses must be valid JSON
     * **Validates: Requirements 4.2**
     */
    public function testAllResponsesProduceValidJson() {
        for ($i = 0; $i < 50; $i++) {
            $data = $this->generateRandomJsonData();
            $statusCode = $this->generateRandomStatusCode();
            
            $output = $this->captureResponse(function() use ($data, $statusCode) {
                Response::json($data, $statusCode);
            });
            
            // Property: Output must be valid JSON
            $decoded = json_decode($output, true);
            $this->assertEquals(JSON_ERROR_NONE, json_last_error(), 
                "Failed to decode JSON for iteration $i: " . json_last_error_msg());
            $this->assertNotNull($decoded, "JSON decode returned null for iteration $i");
        }
    }
    
    /**
     * Property: Success responses always have success=true
     * **Validates: Requirements 4.2**
     */
    public function testSuccessResponsesAlwaysHaveSuccessTrue() {
        for ($i = 0; $i < 50; $i++) {
            $data = $this->generateRandomJsonData();
            $message = $this->generateRandomString();
            
            $output = $this->captureResponse(function() use ($message, $data) {
                Response::success($message, $data);
            });
            
            $response = json_decode($output, true);
            
            // Property: Success responses must have success=true
            $this->assertTrue($response['success'], "Success response had success=false for iteration $i");
            $this->assertEquals($message, $response['message'], "Message mismatch for iteration $i");
            $this->assertEquals($data, $response['data'], "Data mismatch for iteration $i");
            $this->assertNull($response['errors'], "Errors should be null for success response in iteration $i");
        }
    }
    
    /**
     * Property: Error responses always have success=false
     * **Validates: Requirements 13.1**
     */
    public function testErrorResponsesAlwaysHaveSuccessFalse() {
        for ($i = 0; $i < 50; $i++) {
            $message = $this->generateRandomString();
            $statusCode = $this->generateRandomStatusCode();
            $errors = mt_rand(0, 1) ? $this->generateRandomValidationErrors() : null;
            
            $output = $this->captureResponse(function() use ($message, $statusCode, $errors) {
                Response::error($message, $statusCode, $errors);
            });
            
            $response = json_decode($output, true);
            
            // Property: Error responses must have success=false
            $this->assertFalse($response['success'], "Error response had success=true for iteration $i");
            $this->assertEquals($message, $response['message'], "Message mismatch for iteration $i");
            $this->assertNull($response['data'], "Data should be null for error response in iteration $i");
            
            if ($errors !== null) {
                $this->assertEquals($errors, $response['errors'], "Errors mismatch for iteration $i");
            }
        }
    }
    
    /**
     * Property: Validation errors are properly formatted
     * **Validates: Requirements 4.2, 13.1**
     */
    public function testValidationErrorsAreProperlyFormatted() {
        for ($i = 0; $i < 30; $i++) {
            $errors = $this->generateRandomValidationErrors();
            
            $output = $this->captureResponse(function() use ($errors) {
                Response::validationError($errors);
            });
            
            $response = json_decode($output, true);
            
            // Property: Validation errors must be properly formatted
            $this->assertFalse($response['success'], "Validation error response had success=true for iteration $i");
            $this->assertEquals('Validation failed', $response['message'], "Default validation message incorrect for iteration $i");
            $this->assertNull($response['data'], "Data should be null for validation error in iteration $i");
            $this->assertIsArray($response['errors'], "Errors should be array for iteration $i");
            
            // Check that all errors have field and message
            foreach ($response['errors'] as $error) {
                $this->assertArrayHasKey('field', $error, "Error missing field key in iteration $i");
                $this->assertArrayHasKey('message', $error, "Error missing message key in iteration $i");
                $this->assertIsString($error['field'], "Error field should be string in iteration $i");
                $this->assertIsString($error['message'], "Error message should be string in iteration $i");
            }
        }
    }
    
    /**
     * Property: Pagination metadata is mathematically consistent
     * **Validates: Requirements 4.2**
     */
    public function testPaginationMetadataIsConsistent() {
        for ($i = 0; $i < 30; $i++) {
            $currentPage = mt_rand(1, 10);
            $perPage = mt_rand(5, 50);
            $totalItems = mt_rand(0, 500);
            
            $pagination = Response::createPagination($currentPage, $perPage, $totalItems);
            
            // Property: Mathematical consistency
            $expectedTotalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 1;
            $this->assertEquals($expectedTotalPages, $pagination['total_pages'], 
                "Total pages calculation incorrect for iteration $i (totalItems: $totalItems, perPage: $perPage)");
            
            // Property: Current page bounds
            $this->assertGreaterThanOrEqual(1, $pagination['current_page'], 
                "Current page should be >= 1 for iteration $i");
            
            // Property: Has next page logic
            $expectedHasNext = $currentPage < $expectedTotalPages;
            $this->assertEquals($expectedHasNext, $pagination['has_next_page'], 
                "Has next page logic incorrect for iteration $i");
            
            // Property: Has previous page logic
            $expectedHasPrev = $currentPage > 1;
            $this->assertEquals($expectedHasPrev, $pagination['has_prev_page'], 
                "Has previous page logic incorrect for iteration $i");
        }
    }
    
    /**
     * Property: Currency formatting is consistent
     * **Validates: Requirements 4.2**
     */
    public function testCurrencyFormattingIsConsistent() {
        for ($i = 0; $i < 30; $i++) {
            $amount = mt_rand(0, 100000) + (mt_rand(0, 99) / 100);
            
            $formatted = Response::formatCurrency($amount);
            
            // Property: Amount preservation
            $this->assertEquals((float) $amount, $formatted['amount'], 
                "Amount not preserved for iteration $i");
            
            // Property: Currency code
            $this->assertEquals('INR', $formatted['currency'], 
                "Currency code incorrect for iteration $i");
            
            // Property: Formatted string contains rupee symbol
            $this->assertStringContains('₹', $formatted['formatted'], 
                "Formatted currency missing rupee symbol for iteration $i");
        }
    }
    
    /**
     * Property: Data sanitization removes sensitive information
     * **Validates: Requirements 4.2, 13.1**
     */
    public function testDataSanitizationRemovesSensitiveInfo() {
        $sensitiveFields = ['password', 'secret', 'token', 'key', 'api_key', 'private_key'];
        
        for ($i = 0; $i < 20; $i++) {
            $data = [];
            $numFields = mt_rand(3, 8);
            
            for ($j = 0; $j < $numFields; $j++) {
                if (mt_rand(0, 1) && !empty($sensitiveFields)) {
                    // Add sensitive field
                    $field = $sensitiveFields[array_rand($sensitiveFields)];
                    $data[$field] = $this->generateRandomString();
                } else {
                    // Add normal field
                    $field = 'field_' . $j;
                    $data[$field] = $this->generateRandomString();
                }
            }
            
            $sanitized = Response::sanitizeData($data);
            
            // Property: Sensitive fields are redacted
            foreach ($data as $field => $value) {
                if (preg_match('/password|secret|token|key/i', $field)) {
                    $this->assertEquals('[REDACTED]', $sanitized[$field], 
                        "Sensitive field '$field' not redacted in iteration $i");
                } else {
                    $this->assertEquals($value, $sanitized[$field], 
                        "Non-sensitive field '$field' was modified in iteration $i");
                }
            }
        }
    }
    
    /**
     * Property: Date formatting produces consistent structure
     * **Validates: Requirements 4.2**
     */
    public function testDateFormattingProducesConsistentStructure() {
        for ($i = 0; $i < 20; $i++) {
            // Generate random date within reasonable range
            $timestamp = mt_rand(strtotime('2020-01-01'), strtotime('2030-12-31'));
            $date = date('Y-m-d H:i:s', $timestamp);
            
            $formatted = Response::formatDate($date);
            
            // Property: Required fields are present
            $requiredFields = ['date', 'timestamp', 'iso', 'human'];
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $formatted, 
                    "Missing required field '$field' in iteration $i");
            }
            
            // Property: Timestamp is integer
            $this->assertIsInt($formatted['timestamp'], 
                "Timestamp should be integer for iteration $i");
            
            // Property: Date format consistency
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', 
                $formatted['date'], "Date format incorrect for iteration $i");
        }
    }
    
    /**
     * Property: Response structure is consistent across all methods
     * **Validates: Requirements 4.2**
     */
    public function testResponseStructureConsistency() {
        $methods = [
            ['success', ['Test message', ['data' => 'value']]],
            ['error', ['Test error', 400]],
            ['unauthorized', []],
            ['forbidden', []],
            ['notFound', []],
            ['serverError', []]
        ];
        
        for ($i = 0; $i < 10; $i++) {
            foreach ($methods as [$method, $args]) {
                $output = $this->captureResponse(function() use ($method, $args) {
                    call_user_func_array([Response::class, $method], $args);
                });
                
                $response = json_decode($output, true);
                
                // Property: All responses have required structure
                $requiredFields = ['success', 'message', 'data', 'errors'];
                foreach ($requiredFields as $field) {
                    $this->assertArrayHasKey($field, $response, 
                        "Missing required field '$field' in $method response for iteration $i");
                }
                
                // Property: Success field is boolean
                $this->assertIsBool($response['success'], 
                    "Success field should be boolean in $method response for iteration $i");
                
                // Property: Message field is string
                $this->assertIsString($response['message'], 
                    "Message field should be string in $method response for iteration $i");
            }
        }
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    new ResponsePropertyTest();
}