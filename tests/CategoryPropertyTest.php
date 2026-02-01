<?php
/**
 * Category Model Property-Based Tests
 * 
 * Property-based tests for the Category model that verify universal properties
 * hold across all valid inputs using random data generation.
 * 
 * Requirements: 5.1
 */

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/bootstrap.php';

/**
 * Category Property-Based Tests
 */
class CategoryPropertyTest {
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
     * Property: Category CRUD Operations Consistency
     * **Validates: Requirements 5.1**
     * 
     * For any valid category data, create-read-update-delete operations
     * should maintain data consistency and referential integrity.
     */
    public function testCategoryDataConsistency() {
        echo "Testing Category CRUD Operations Consistency...\n";
        
        for ($i = 0; $i < 50; $i++) {
            $categoryData = $this->generateRandomCategoryData();
            
            try {
                // Create category
                $created = $this->category->createCategory($categoryData);
                $this->testCategories[] = $created['id'];
                
                // Verify creation consistency
                assert($created['name'] === trim($categoryData['name']), 
                    "Created category name should match input (iteration {$i})");
                assert($created['is_active'] === ($categoryData['is_active'] ?? true), 
                    "Created category active status should match input (iteration {$i})");
                
                // Read category
                $read = $this->category->getCategoryById($created['id']);
                assert($read !== null, "Category should be readable after creation (iteration {$i})");
                assert($read['id'] === $created['id'], "Read category should have same ID (iteration {$i})");
                assert($read['name'] === $created['name'], "Read category should have same name (iteration {$i})");
                
                // Update category
                $updateData = $this->generateRandomUpdateData();
                $updated = $this->category->updateCategory($created['id'], $updateData);
                
                foreach ($updateData as $field => $value) {
                    if ($field === 'name' || $field === 'description' || $field === 'image_url') {
                        $expectedValue = !empty($value) ? trim($value) : null;
                    } else {
                        $expectedValue = $value;
                    }
                    assert($updated[$field] === $expectedValue, 
                        "Updated field {$field} should match input (iteration {$i})");
                }
                
                // Delete category
                $deleted = $this->category->deleteCategory($created['id']);
                assert($deleted === true, "Category deletion should succeed (iteration {$i})");
                
                // Verify deletion
                $afterDelete = $this->category->getCategoryById($created['id']);
                assert($afterDelete === null, "Deleted category should not be found (iteration {$i})");
                
            } catch (Exception $e) {
                // Skip invalid data that should fail validation
                if ($e->getCode() === 400) {
                    continue;
                }
                throw $e;
            }
        }
        
        echo "✓ Category CRUD operations maintain consistency across all valid inputs\n";
    }
    
    /**
     * Property: Category Name Uniqueness
     * **Validates: Requirements 5.1**
     * 
     * For any category name, the system should enforce uniqueness constraints
     * and prevent duplicate names from being created.
     */
    public function testCategoryNameUniqueness() {
        echo "Testing Category Name Uniqueness...\n";
        
        for ($i = 0; $i < 30; $i++) {
            $baseName = $this->generateRandomString(20);
            
            // Create first category
            $categoryData1 = [
                'name' => $baseName,
                'description' => $this->generateRandomString(100)
            ];
            
            try {
                $created1 = $this->category->createCategory($categoryData1);
                $this->testCategories[] = $created1['id'];
                
                // Try to create second category with same name
                $categoryData2 = [
                    'name' => $baseName,
                    'description' => $this->generateRandomString(50)
                ];
                
                $duplicateCreated = false;
                try {
                    $created2 = $this->category->createCategory($categoryData2);
                    $this->testCategories[] = $created2['id'];
                    $duplicateCreated = true;
                } catch (Exception $e) {
                    assert($e->getCode() === 409, 
                        "Duplicate name should return 409 conflict error (iteration {$i})");
                }
                
                assert(!$duplicateCreated, 
                    "Duplicate category names should not be allowed (iteration {$i})");
                
                // Verify name exists check
                $exists = $this->category->nameExists($baseName);
                assert($exists === true, 
                    "Name existence check should return true for existing name (iteration {$i})");
                
                // Verify name exists check with exclusion
                $existsWithExclusion = $this->category->nameExists($baseName, $created1['id']);
                assert($existsWithExclusion === false, 
                    "Name existence check should return false when excluding same category (iteration {$i})");
                
            } catch (Exception $e) {
                // Skip invalid data that should fail validation
                if ($e->getCode() === 400) {
                    continue;
                }
                throw $e;
            }
        }
        
        echo "✓ Category name uniqueness is enforced across all inputs\n";
    }
    
    /**
     * Property: Category Search Consistency
     * **Validates: Requirements 5.1**
     * 
     * For any search term, the search results should be consistent and
     * include all categories that match the search criteria.
     */
    public function testCategorySearchConsistency() {
        echo "Testing Category Search Consistency...\n";
        
        // Create categories with known searchable terms
        $searchableCategories = [];
        $searchTerms = ['Electronics', 'Fashion', 'Beauty', 'Sports', 'Books'];
        
        foreach ($searchTerms as $term) {
            $categoryData = [
                'name' => $term . ' Category ' . time() . rand(1000, 9999),
                'description' => 'Description containing ' . $term
            ];
            
            try {
                $created = $this->category->createCategory($categoryData);
                $searchableCategories[] = $created;
                $this->testCategories[] = $created['id'];
            } catch (Exception $e) {
                // Skip if creation fails
                continue;
            }
        }
        
        // Test search consistency
        for ($i = 0; $i < 20; $i++) {
            $searchTerm = $searchTerms[array_rand($searchTerms)];
            
            // Search categories
            $searchResults = $this->category->searchCategories($searchTerm);
            
            assert(isset($searchResults['categories']), 
                "Search should return categories array (iteration {$i})");
            assert(is_array($searchResults['categories']), 
                "Categories should be an array (iteration {$i})");
            
            // Verify search results contain expected categories
            $foundExpected = false;
            foreach ($searchResults['categories'] as $result) {
                if (stripos($result['name'], $searchTerm) !== false || 
                    stripos($result['description'], $searchTerm) !== false) {
                    $foundExpected = true;
                    break;
                }
            }
            
            if (!empty($searchableCategories)) {
                // We should find at least one matching category if we created them
                foreach ($searchableCategories as $searchableCategory) {
                    if (stripos($searchableCategory['name'], $searchTerm) !== false) {
                        assert($foundExpected, 
                            "Search should find categories containing the search term (iteration {$i})");
                        break;
                    }
                }
            }
            
            // Verify pagination structure
            assert(isset($searchResults['pagination']), 
                "Search should return pagination info (iteration {$i})");
            $pagination = $searchResults['pagination'];
            assert($pagination['current_page'] >= 1, 
                "Current page should be at least 1 (iteration {$i})");
            assert($pagination['per_page'] > 0, 
                "Per page should be positive (iteration {$i})");
            assert($pagination['total'] >= 0, 
                "Total should be non-negative (iteration {$i})");
        }
        
        echo "✓ Category search returns consistent results across all search terms\n";
    }
    
    /**
     * Property: Category Validation Consistency
     * **Validates: Requirements 5.1**
     * 
     * For any input data, validation should consistently accept valid data
     * and reject invalid data with appropriate error messages.
     */
    public function testCategoryValidationConsistency() {
        echo "Testing Category Validation Consistency...\n";
        
        for ($i = 0; $i < 50; $i++) {
            // Generate random data that might be valid or invalid
            $categoryData = $this->generateRandomCategoryDataWithInvalid();
            
            $isValid = $this->isValidCategoryData($categoryData);
            $creationSucceeded = false;
            $validationError = null;
            
            try {
                $created = $this->category->createCategory($categoryData);
                $this->testCategories[] = $created['id'];
                $creationSucceeded = true;
            } catch (Exception $e) {
                $validationError = $e;
            }
            
            if ($isValid) {
                assert($creationSucceeded, 
                    "Valid data should succeed in creation (iteration {$i}): " . 
                    json_encode($categoryData));
            } else {
                assert(!$creationSucceeded, 
                    "Invalid data should fail validation (iteration {$i}): " . 
                    json_encode($categoryData));
                
                if ($validationError) {
                    assert($validationError->getCode() === 400 || $validationError->getCode() === 409, 
                        "Invalid data should return 400 or 409 error code (iteration {$i})");
                }
            }
        }
        
        echo "✓ Category validation consistently handles valid and invalid data\n";
    }
    
    /**
     * Property: Category Statistics Accuracy
     * **Validates: Requirements 5.1**
     * 
     * For any set of categories, statistics should accurately reflect
     * the current state of the category data.
     */
    public function testCategoryStatisticsAccuracy() {
        echo "Testing Category Statistics Accuracy...\n";
        
        // Create known number of categories
        $createdCategories = [];
        $numCategories = rand(3, 8);
        
        for ($i = 0; $i < $numCategories; $i++) {
            $categoryData = [
                'name' => 'Stats Test Category ' . time() . '_' . $i,
                'description' => 'Test description ' . $i
            ];
            
            try {
                $created = $this->category->createCategory($categoryData);
                $createdCategories[] = $created;
                $this->testCategories[] = $created['id'];
            } catch (Exception $e) {
                // Skip if creation fails
                continue;
            }
        }
        
        // Get statistics
        $stats = $this->category->getCategoryStats();
        
        // Verify statistics structure
        assert(isset($stats['total_categories']), 'Stats should include total_categories');
        assert(isset($stats['categories_with_products']), 'Stats should include categories_with_products');
        assert(isset($stats['empty_categories']), 'Stats should include empty_categories');
        assert(isset($stats['category_distribution']), 'Stats should include category_distribution');
        
        // Verify statistics accuracy
        assert($stats['total_categories'] >= count($createdCategories), 
            'Total categories should include our created categories');
        
        assert($stats['categories_with_products'] + $stats['empty_categories'] === $stats['total_categories'], 
            'Categories with products + empty categories should equal total');
        
        assert(is_array($stats['category_distribution']), 
            'Category distribution should be an array');
        
        // Verify our created categories appear in distribution
        $distributionIds = array_column($stats['category_distribution'], 'id');
        foreach ($createdCategories as $created) {
            assert(in_array($created['id'], $distributionIds), 
                'Created category should appear in distribution');
        }
        
        echo "✓ Category statistics accurately reflect the current data state\n";
    }
    
    /**
     * Property: Category Pagination Consistency
     * **Validates: Requirements 5.1**
     * 
     * For any pagination parameters, the system should return consistent
     * results and proper pagination metadata.
     */
    public function testCategoryPaginationConsistency() {
        echo "Testing Category Pagination Consistency...\n";
        
        // Create enough categories for pagination testing
        $createdCategories = [];
        for ($i = 0; $i < 15; $i++) {
            $categoryData = [
                'name' => 'Pagination Test Category ' . time() . '_' . $i,
                'description' => 'Test description ' . $i
            ];
            
            try {
                $created = $this->category->createCategory($categoryData);
                $createdCategories[] = $created;
                $this->testCategories[] = $created['id'];
            } catch (Exception $e) {
                // Skip if creation fails
                continue;
            }
        }
        
        for ($i = 0; $i < 20; $i++) {
            $page = rand(1, 5);
            $perPage = rand(2, 10);
            
            $result = $this->category->getCategories([], $page, $perPage);
            
            // Verify pagination structure
            assert(isset($result['categories']), 
                "Result should contain categories array (iteration {$i})");
            assert(isset($result['pagination']), 
                "Result should contain pagination info (iteration {$i})");
            
            $pagination = $result['pagination'];
            
            // Verify pagination metadata
            assert($pagination['current_page'] === $page, 
                "Current page should match requested page (iteration {$i})");
            assert($pagination['per_page'] === $perPage, 
                "Per page should match requested per page (iteration {$i})");
            assert($pagination['total'] >= 0, 
                "Total should be non-negative (iteration {$i})");
            assert($pagination['total_pages'] >= 0, 
                "Total pages should be non-negative (iteration {$i})");
            
            // Verify categories count doesn't exceed per_page
            assert(count($result['categories']) <= $perPage, 
                "Categories count should not exceed per_page limit (iteration {$i})");
            
            // Verify has_next and has_prev logic
            $expectedHasNext = $page < $pagination['total_pages'];
            $expectedHasPrev = $page > 1;
            
            assert($pagination['has_next'] === $expectedHasNext, 
                "Has next should be correct (iteration {$i})");
            assert($pagination['has_prev'] === $expectedHasPrev, 
                "Has prev should be correct (iteration {$i})");
        }
        
        echo "✓ Category pagination returns consistent results across all parameters\n";
    }
    
    /**
     * Generate random valid category data
     */
    private function generateRandomCategoryData() {
        $names = [
            'Electronics', 'Fashion', 'Beauty', 'Sports', 'Books', 'Home', 'Garden',
            'Automotive', 'Health', 'Toys', 'Music', 'Movies', 'Games', 'Food'
        ];
        
        return [
            'name' => $names[array_rand($names)] . ' ' . time() . rand(1000, 9999),
            'description' => rand(0, 1) ? $this->generateRandomString(rand(10, 200)) : null,
            'image_url' => rand(0, 1) ? 'https://example.com/image' . rand(1, 100) . '.jpg' : null,
            'is_active' => rand(0, 1) ? true : false
        ];
    }
    
    /**
     * Generate random update data
     */
    private function generateRandomUpdateData() {
        $fields = ['name', 'description', 'image_url', 'is_active'];
        $updateData = [];
        
        // Randomly select 1-3 fields to update
        $numFields = rand(1, 3);
        $selectedFields = array_rand(array_flip($fields), $numFields);
        if (!is_array($selectedFields)) {
            $selectedFields = [$selectedFields];
        }
        
        foreach ($selectedFields as $field) {
            switch ($field) {
                case 'name':
                    $updateData[$field] = 'Updated ' . $this->generateRandomString(20);
                    break;
                case 'description':
                    $updateData[$field] = 'Updated ' . $this->generateRandomString(100);
                    break;
                case 'image_url':
                    $updateData[$field] = 'https://example.com/updated' . rand(1, 100) . '.jpg';
                    break;
                case 'is_active':
                    $updateData[$field] = rand(0, 1) ? true : false;
                    break;
            }
        }
        
        return $updateData;
    }
    
    /**
     * Generate random category data that might be invalid
     */
    private function generateRandomCategoryDataWithInvalid() {
        $data = [];
        
        // Name field - sometimes invalid
        $nameType = rand(1, 10);
        if ($nameType <= 6) {
            // Valid name
            $data['name'] = 'Test Category ' . time() . rand(1000, 9999);
        } elseif ($nameType <= 7) {
            // Empty name
            $data['name'] = '';
        } elseif ($nameType <= 8) {
            // Too long name
            $data['name'] = str_repeat('a', 101);
        } elseif ($nameType <= 9) {
            // Too short name
            $data['name'] = 'a';
        } else {
            // Invalid characters
            $data['name'] = 'Test<>Category';
        }
        
        // Description field - sometimes invalid
        if (rand(0, 1)) {
            $descType = rand(1, 10);
            if ($descType <= 8) {
                // Valid description
                $data['description'] = $this->generateRandomString(rand(10, 200));
            } else {
                // Too long description
                $data['description'] = str_repeat('a', 65536);
            }
        }
        
        // Image URL field - sometimes invalid
        if (rand(0, 1)) {
            $urlType = rand(1, 10);
            if ($urlType <= 7) {
                // Valid URL
                $data['image_url'] = 'https://example.com/image' . rand(1, 100) . '.jpg';
            } elseif ($urlType <= 8) {
                // Invalid URL
                $data['image_url'] = 'not-a-url';
            } else {
                // Too long URL
                $data['image_url'] = 'https://example.com/' . str_repeat('a', 250) . '.jpg';
            }
        }
        
        // is_active field
        if (rand(0, 1)) {
            $data['is_active'] = rand(0, 1) ? true : false;
        }
        
        return $data;
    }
    
    /**
     * Check if category data is valid according to business rules
     */
    private function isValidCategoryData($data) {
        // Name validation
        if (empty($data['name']) || !is_string($data['name'])) {
            return false;
        }
        
        $name = trim($data['name']);
        if (strlen($name) < 2 || strlen($name) > 100) {
            return false;
        }
        
        if (!preg_match('/^[a-zA-Z0-9\s\-_&()]+$/', $name)) {
            return false;
        }
        
        // Description validation
        if (isset($data['description']) && $data['description'] !== null) {
            if (!is_string($data['description']) || strlen($data['description']) > 65535) {
                return false;
            }
        }
        
        // Image URL validation
        if (isset($data['image_url']) && $data['image_url'] !== null) {
            if (!is_string($data['image_url']) || 
                strlen(trim($data['image_url'])) > 255 || 
                !filter_var(trim($data['image_url']), FILTER_VALIDATE_URL)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate random string
     */
    private function generateRandomString($length) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ';
        $string = '';
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return trim($string);
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
     * Run all property-based tests
     */
    public function runAllTests() {
        echo "Running Category Model Property-Based Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testCategoryDataConsistency();
            $this->testCategoryNameUniqueness();
            $this->testCategorySearchConsistency();
            $this->testCategoryValidationConsistency();
            $this->testCategoryStatisticsAccuracy();
            $this->testCategoryPaginationConsistency();
            
            echo "\n✅ All Category Model property-based tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Property test failed: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CategoryPropertyTest();
    $test->runAllTests();
}