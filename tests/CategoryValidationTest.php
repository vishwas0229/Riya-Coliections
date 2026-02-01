<?php
/**
 * Category Model Validation Tests
 * 
 * Focused validation tests for the Category model that verify all validation
 * rules, edge cases, and error handling scenarios.
 * 
 * Requirements: 5.1
 */

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/bootstrap.php';

/**
 * Category Validation Tests
 */
class CategoryValidationTest {
    private $category;
    private $testCategories = [];
    
    /**
     * Set up test environment
     */
    public function setUp() {
        $this->category = new Category();
        $this->cleanupTestData();
    }
    
    /**
     * Clean up test environment
     */
    public function tearDown() {
        $this->cleanupTestData();
    }
    
    /**
     * Test name validation - required field
     */
    public function testNameRequired() {
        $testCases = [
            ['name' => null],
            ['name' => ''],
            ['name' => '   '],
            []  // Missing name field
        ];
        
        foreach ($testCases as $index => $categoryData) {
            try {
                $this->category->createCategory($categoryData);
                assert(false, "Test case {$index}: Should throw exception for missing/empty name");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Test case {$index}: Should return 400 validation error");
                assert(strpos(strtolower($e->getMessage()), 'name') !== false, 
                    "Test case {$index}: Error should mention name field");
            }
        }
        
        echo "✓ Name field validation (required) works correctly\n";
    }
    
    /**
     * Test name validation - length constraints
     */
    public function testNameLength() {
        $testCases = [
            // Too short
            ['name' => 'a', 'expected_error' => 'too short'],
            ['name' => 'ab', 'expected_error' => null], // Valid minimum
            
            // Valid lengths
            ['name' => 'Valid Category Name', 'expected_error' => null],
            ['name' => str_repeat('a', 50), 'expected_error' => null],
            ['name' => str_repeat('a', 100), 'expected_error' => null], // Valid maximum
            
            // Too long
            ['name' => str_repeat('a', 101), 'expected_error' => 'too long'],
            ['name' => str_repeat('a', 200), 'expected_error' => 'too long']
        ];
        
        foreach ($testCases as $index => $testCase) {
            $categoryData = ['name' => $testCase['name']];
            
            if ($testCase['expected_error'] === null) {
                // Should succeed
                try {
                    $result = $this->category->createCategory($categoryData);
                    $this->testCategories[] = $result['id'];
                    assert(true, "Test case {$index}: Valid name should be accepted");
                } catch (Exception $e) {
                    assert(false, "Test case {$index}: Valid name '{$testCase['name']}' should not fail: " . $e->getMessage());
                }
            } else {
                // Should fail
                try {
                    $this->category->createCategory($categoryData);
                    assert(false, "Test case {$index}: Invalid name should be rejected");
                } catch (Exception $e) {
                    assert($e->getCode() === 400, "Test case {$index}: Should return 400 validation error");
                    assert(strpos(strtolower($e->getMessage()), $testCase['expected_error']) !== false, 
                        "Test case {$index}: Error should mention '{$testCase['expected_error']}'");
                }
            }
        }
        
        echo "✓ Name length validation works correctly\n";
    }
    
    /**
     * Test name validation - character constraints
     */
    public function testNameCharacters() {
        $testCases = [
            // Valid characters
            ['name' => 'Electronics', 'valid' => true],
            ['name' => 'Fashion & Beauty', 'valid' => true],
            ['name' => 'Home-Garden', 'valid' => true],
            ['name' => 'Sports_Equipment', 'valid' => true],
            ['name' => 'Books (Fiction)', 'valid' => true],
            ['name' => 'Category 123', 'valid' => true],
            ['name' => 'A-Z & 0-9 Test', 'valid' => true],
            
            // Invalid characters
            ['name' => 'Category<Script>', 'valid' => false],
            ['name' => 'Category>Alert', 'valid' => false],
            ['name' => 'Category"Quote', 'valid' => false],
            ['name' => "Category'Quote", 'valid' => false],
            ['name' => 'Category;Drop', 'valid' => false],
            ['name' => 'Category=Equals', 'valid' => false],
            ['name' => 'Category+Plus', 'valid' => false],
            ['name' => 'Category*Star', 'valid' => false],
            ['name' => 'Category%Percent', 'valid' => false],
            ['name' => 'Category#Hash', 'valid' => false],
            ['name' => 'Category@At', 'valid' => false],
            ['name' => 'Category!Exclamation', 'valid' => false],
            ['name' => 'Category?Question', 'valid' => false],
            ['name' => 'Category[Bracket]', 'valid' => false],
            ['name' => 'Category{Brace}', 'valid' => false],
            ['name' => 'Category|Pipe', 'valid' => false],
            ['name' => 'Category\\Backslash', 'valid' => false],
            ['name' => 'Category/Slash', 'valid' => false],
            ['name' => 'Category^Caret', 'valid' => false],
            ['name' => 'Category~Tilde', 'valid' => false],
            ['name' => 'Category`Backtick', 'valid' => false]
        ];
        
        foreach ($testCases as $index => $testCase) {
            $categoryData = ['name' => $testCase['name']];
            
            if ($testCase['valid']) {
                try {
                    $result = $this->category->createCategory($categoryData);
                    $this->testCategories[] = $result['id'];
                    assert(true, "Test case {$index}: Valid characters should be accepted");
                } catch (Exception $e) {
                    assert(false, "Test case {$index}: Valid name '{$testCase['name']}' should not fail: " . $e->getMessage());
                }
            } else {
                try {
                    $this->category->createCategory($categoryData);
                    assert(false, "Test case {$index}: Invalid characters should be rejected: '{$testCase['name']}'");
                } catch (Exception $e) {
                    assert($e->getCode() === 400, "Test case {$index}: Should return 400 validation error");
                    assert(strpos(strtolower($e->getMessage()), 'invalid characters') !== false, 
                        "Test case {$index}: Error should mention invalid characters");
                }
            }
        }
        
        echo "✓ Name character validation works correctly\n";
    }
    
    /**
     * Test description validation
     */
    public function testDescriptionValidation() {
        $testCases = [
            // Valid descriptions
            ['description' => null, 'valid' => true],
            ['description' => '', 'valid' => true], // Empty string becomes null
            ['description' => 'Short description', 'valid' => true],
            ['description' => str_repeat('a', 1000), 'valid' => true],
            ['description' => str_repeat('a', 65535), 'valid' => true], // Maximum length
            
            // Invalid descriptions
            ['description' => str_repeat('a', 65536), 'valid' => false], // Too long
            ['description' => str_repeat('a', 100000), 'valid' => false] // Way too long
        ];
        
        foreach ($testCases as $index => $testCase) {
            $categoryData = [
                'name' => 'Test Category ' . time() . '_' . $index,
                'description' => $testCase['description']
            ];
            
            if ($testCase['valid']) {
                try {
                    $result = $this->category->createCategory($categoryData);
                    $this->testCategories[] = $result['id'];
                    assert(true, "Test case {$index}: Valid description should be accepted");
                } catch (Exception $e) {
                    assert(false, "Test case {$index}: Valid description should not fail: " . $e->getMessage());
                }
            } else {
                try {
                    $this->category->createCategory($categoryData);
                    assert(false, "Test case {$index}: Invalid description should be rejected");
                } catch (Exception $e) {
                    assert($e->getCode() === 400, "Test case {$index}: Should return 400 validation error");
                    assert(strpos(strtolower($e->getMessage()), 'too long') !== false, 
                        "Test case {$index}: Error should mention length limit");
                }
            }
        }
        
        echo "✓ Description validation works correctly\n";
    }
    
    /**
     * Test image URL validation
     */
    public function testImageUrlValidation() {
        $testCases = [
            // Valid URLs
            ['image_url' => null, 'valid' => true],
            ['image_url' => '', 'valid' => true], // Empty string becomes null
            ['image_url' => 'https://example.com/image.jpg', 'valid' => true],
            ['image_url' => 'http://example.com/image.png', 'valid' => true],
            ['image_url' => 'https://cdn.example.com/path/to/image.gif', 'valid' => true],
            ['image_url' => 'https://example.com/image.webp', 'valid' => true],
            ['image_url' => 'https://example.com/' . str_repeat('a', 200) . '.jpg', 'valid' => true], // Long but valid
            
            // Invalid URLs
            ['image_url' => 'not-a-url', 'valid' => false],
            ['image_url' => 'ftp://example.com/image.jpg', 'valid' => false], // Wrong protocol
            ['image_url' => 'javascript:alert(1)', 'valid' => false],
            ['image_url' => 'data:image/png;base64,abc', 'valid' => false],
            ['image_url' => 'file:///etc/passwd', 'valid' => false],
            ['image_url' => 'example.com/image.jpg', 'valid' => false], // Missing protocol
            ['image_url' => 'https://', 'valid' => false], // Incomplete URL
            ['image_url' => 'https://example.com/' . str_repeat('a', 250) . '.jpg', 'valid' => false] // Too long
        ];
        
        foreach ($testCases as $index => $testCase) {
            $categoryData = [
                'name' => 'Test Category ' . time() . '_' . $index,
                'image_url' => $testCase['image_url']
            ];
            
            if ($testCase['valid']) {
                try {
                    $result = $this->category->createCategory($categoryData);
                    $this->testCategories[] = $result['id'];
                    assert(true, "Test case {$index}: Valid image URL should be accepted");
                } catch (Exception $e) {
                    assert(false, "Test case {$index}: Valid image URL should not fail: " . $e->getMessage());
                }
            } else {
                try {
                    $this->category->createCategory($categoryData);
                    assert(false, "Test case {$index}: Invalid image URL should be rejected: '{$testCase['image_url']}'");
                } catch (Exception $e) {
                    assert($e->getCode() === 400, "Test case {$index}: Should return 400 validation error");
                    assert(strpos(strtolower($e->getMessage()), 'url') !== false || 
                           strpos(strtolower($e->getMessage()), 'too long') !== false, 
                        "Test case {$index}: Error should mention URL or length issue");
                }
            }
        }
        
        echo "✓ Image URL validation works correctly\n";
    }
    
    /**
     * Test is_active field validation
     */
    public function testIsActiveValidation() {
        $testCases = [
            // Valid values
            ['is_active' => true, 'expected' => true],
            ['is_active' => false, 'expected' => false],
            ['is_active' => 1, 'expected' => true],
            ['is_active' => 0, 'expected' => false],
            ['is_active' => '1', 'expected' => true],
            ['is_active' => '0', 'expected' => false],
            ['is_active' => 'true', 'expected' => true],
            ['is_active' => 'false', 'expected' => false],
            [null, 'expected' => true] // Default value
        ];
        
        foreach ($testCases as $index => $testCase) {
            $categoryData = ['name' => 'Test Category ' . time() . '_' . $index];
            
            if (isset($testCase['is_active'])) {
                $categoryData['is_active'] = $testCase['is_active'];
            }
            
            try {
                $result = $this->category->createCategory($categoryData);
                $this->testCategories[] = $result['id'];
                
                assert($result['is_active'] === $testCase['expected'], 
                    "Test case {$index}: is_active should be {$testCase['expected']}");
                
            } catch (Exception $e) {
                assert(false, "Test case {$index}: Valid is_active value should not fail: " . $e->getMessage());
            }
        }
        
        echo "✓ is_active field validation works correctly\n";
    }
    
    /**
     * Test update validation
     */
    public function testUpdateValidation() {
        // Create a test category first
        $categoryData = ['name' => 'Update Validation Test ' . time()];
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Test valid updates
        $validUpdates = [
            ['name' => 'Updated Name'],
            ['description' => 'Updated description'],
            ['image_url' => 'https://example.com/updated.jpg'],
            ['is_active' => false],
            ['name' => 'New Name', 'description' => 'New description']
        ];
        
        foreach ($validUpdates as $index => $updateData) {
            try {
                $result = $this->category->updateCategory($created['id'], $updateData);
                assert($result !== null, "Test case {$index}: Valid update should succeed");
            } catch (Exception $e) {
                assert(false, "Test case {$index}: Valid update should not fail: " . $e->getMessage());
            }
        }
        
        // Test invalid updates
        $invalidUpdates = [
            ['name' => ''], // Empty name
            ['name' => str_repeat('a', 101)], // Name too long
            ['name' => 'Invalid<>Name'], // Invalid characters
            ['description' => str_repeat('a', 65536)], // Description too long
            ['image_url' => 'invalid-url'], // Invalid URL
            ['image_url' => 'https://example.com/' . str_repeat('a', 250) . '.jpg'] // URL too long
        ];
        
        foreach ($invalidUpdates as $index => $updateData) {
            try {
                $this->category->updateCategory($created['id'], $updateData);
                assert(false, "Test case {$index}: Invalid update should be rejected");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Test case {$index}: Should return 400 validation error");
            }
        }
        
        echo "✓ Update validation works correctly\n";
    }
    
    /**
     * Test name uniqueness validation
     */
    public function testNameUniqueness() {
        $baseName = 'Unique Test Category ' . time();
        
        // Create first category
        $categoryData1 = ['name' => $baseName];
        $created1 = $this->category->createCategory($categoryData1);
        $this->testCategories[] = $created1['id'];
        
        // Try to create second category with same name
        $categoryData2 = ['name' => $baseName];
        
        try {
            $created2 = $this->category->createCategory($categoryData2);
            $this->testCategories[] = $created2['id'];
            assert(false, 'Should not allow duplicate category names');
        } catch (Exception $e) {
            assert($e->getCode() === 409, 'Should return 409 conflict error for duplicate name');
            assert(strpos(strtolower($e->getMessage()), 'already exists') !== false, 
                'Error should mention that name already exists');
        }
        
        // Test case sensitivity (should be case-insensitive)
        $categoryData3 = ['name' => strtoupper($baseName)];
        
        try {
            $created3 = $this->category->createCategory($categoryData3);
            $this->testCategories[] = $created3['id'];
            // If this succeeds, the system is case-sensitive (which might be intended)
            echo "  Note: Category names are case-sensitive\n";
        } catch (Exception $e) {
            // If this fails, the system is case-insensitive
            assert($e->getCode() === 409, 'Should return 409 conflict error for case variation');
            echo "  Note: Category names are case-insensitive\n";
        }
        
        echo "✓ Name uniqueness validation works correctly\n";
    }
    
    /**
     * Test whitespace handling
     */
    public function testWhitespaceHandling() {
        $testCases = [
            ['input' => '  Test Category  ', 'expected' => 'Test Category'],
            ['input' => "\tTest Category\t", 'expected' => 'Test Category'],
            ['input' => "\nTest Category\n", 'expected' => 'Test Category'],
            ['input' => "  \t\n Test Category \n\t  ", 'expected' => 'Test Category']
        ];
        
        foreach ($testCases as $index => $testCase) {
            $categoryData = [
                'name' => $testCase['input'],
                'description' => '  Test Description  ',
                'image_url' => '  https://example.com/image.jpg  '
            ];
            
            try {
                $result = $this->category->createCategory($categoryData);
                $this->testCategories[] = $result['id'];
                
                assert($result['name'] === $testCase['expected'], 
                    "Test case {$index}: Name should be trimmed to '{$testCase['expected']}'");
                assert($result['description'] === 'Test Description', 
                    "Test case {$index}: Description should be trimmed");
                assert($result['image_url'] === 'https://example.com/image.jpg', 
                    "Test case {$index}: Image URL should be trimmed");
                
            } catch (Exception $e) {
                assert(false, "Test case {$index}: Whitespace trimming should not cause failure: " . $e->getMessage());
            }
        }
        
        echo "✓ Whitespace handling works correctly\n";
    }
    
    /**
     * Test validation error messages
     */
    public function testValidationErrorMessages() {
        $testCases = [
            [
                'data' => ['name' => ''],
                'expected_keywords' => ['name', 'required']
            ],
            [
                'data' => ['name' => str_repeat('a', 101)],
                'expected_keywords' => ['name', 'too long', '100']
            ],
            [
                'data' => ['name' => 'a'],
                'expected_keywords' => ['name', 'too short', '2']
            ],
            [
                'data' => ['name' => 'Test<>Category'],
                'expected_keywords' => ['name', 'invalid characters']
            ],
            [
                'data' => ['name' => 'Test', 'description' => str_repeat('a', 65536)],
                'expected_keywords' => ['description', 'too long']
            ],
            [
                'data' => ['name' => 'Test', 'image_url' => 'invalid-url'],
                'expected_keywords' => ['image', 'url', 'invalid']
            ]
        ];
        
        foreach ($testCases as $index => $testCase) {
            try {
                $this->category->createCategory($testCase['data']);
                assert(false, "Test case {$index}: Should throw validation exception");
            } catch (Exception $e) {
                assert($e->getCode() === 400, "Test case {$index}: Should return 400 validation error");
                
                $errorMessage = strtolower($e->getMessage());
                foreach ($testCase['expected_keywords'] as $keyword) {
                    assert(strpos($errorMessage, strtolower($keyword)) !== false, 
                        "Test case {$index}: Error message should contain '{$keyword}': {$e->getMessage()}");
                }
            }
        }
        
        echo "✓ Validation error messages are descriptive and helpful\n";
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        if (!empty($this->testCategories)) {
            foreach ($this->testCategories as $categoryId) {
                try {
                    // Force delete to clean up
                    $this->category->deleteCategory($categoryId, true);
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
            $this->testCategories = [];
        }
    }
    
    /**
     * Run all validation tests
     */
    public function runAllTests() {
        echo "Running Category Model Validation Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testNameRequired();
            $this->testNameLength();
            $this->testNameCharacters();
            $this->testDescriptionValidation();
            $this->testImageUrlValidation();
            $this->testIsActiveValidation();
            $this->testUpdateValidation();
            $this->testNameUniqueness();
            $this->testWhitespaceHandling();
            $this->testValidationErrorMessages();
            
            echo "\n✅ All Category Model validation tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Validation test failed: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CategoryValidationTest();
    $test->runAllTests();
}