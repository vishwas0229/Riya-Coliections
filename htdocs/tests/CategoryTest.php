<?php
/**
 * Category Model Unit Tests
 * 
 * Comprehensive unit tests for the Category model covering all CRUD operations,
 * validation, error handling, and business logic.
 * 
 * Requirements: 5.1
 */

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/bootstrap.php';

/**
 * Category Unit Tests
 */
class CategoryTest {
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
     * Test category creation with valid data
     */
    public function testCreateCategorySuccess() {
        $categoryData = [
            'name' => 'Test Category ' . time(),
            'description' => 'Test category description',
            'image_url' => 'https://example.com/image.jpg',
            'is_active' => true
        ];
        
        $result = $this->category->createCategory($categoryData);
        $this->testCategories[] = $result['id'];
        
        assert($result['id'] > 0, 'Category should have valid ID');
        assert($result['name'] === $categoryData['name'], 'Category name should match input');
        assert($result['description'] === $categoryData['description'], 'Category description should match input');
        assert($result['image_url'] === $categoryData['image_url'], 'Category image URL should match input');
        assert($result['is_active'] === true, 'Category should be active');
        assert(isset($result['created_at']), 'Category should have created_at timestamp');
        
        echo "✓ Category creation with valid data works correctly\n";
    }
    
    /**
     * Test category creation with minimal data
     */
    public function testCreateCategoryMinimalData() {
        $categoryData = [
            'name' => 'Minimal Category ' . time()
        ];
        
        $result = $this->category->createCategory($categoryData);
        $this->testCategories[] = $result['id'];
        
        assert($result['id'] > 0, 'Category should have valid ID');
        assert($result['name'] === $categoryData['name'], 'Category name should match input');
        assert($result['description'] === null, 'Category description should be null');
        assert($result['image_url'] === null, 'Category image URL should be null');
        assert($result['is_active'] === true, 'Category should be active by default');
        
        echo "✓ Category creation with minimal data works correctly\n";
    }
    
    /**
     * Test category creation with duplicate name
     */
    public function testCreateCategoryDuplicateName() {
        $categoryName = 'Duplicate Category ' . time();
        
        // Create first category
        $categoryData1 = ['name' => $categoryName];
        $result1 = $this->category->createCategory($categoryData1);
        $this->testCategories[] = $result1['id'];
        
        // Try to create second category with same name
        $categoryData2 = ['name' => $categoryName];
        
        try {
            $this->category->createCategory($categoryData2);
            assert(false, 'Should throw exception for duplicate name');
        } catch (Exception $e) {
            assert($e->getCode() === 409, 'Should return 409 conflict error');
            assert(strpos($e->getMessage(), 'already exists') !== false, 'Error message should mention duplicate');
        }
        
        echo "✓ Category creation prevents duplicate names\n";
    }
    
    /**
     * Test category creation with invalid data
     */
    public function testCreateCategoryInvalidData() {
        // Test empty name
        try {
            $this->category->createCategory(['name' => '']);
            assert(false, 'Should throw exception for empty name');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
        }
        
        // Test name too long
        try {
            $this->category->createCategory(['name' => str_repeat('a', 101)]);
            assert(false, 'Should throw exception for name too long');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
        }
        
        // Test invalid image URL
        try {
            $this->category->createCategory([
                'name' => 'Test Category',
                'image_url' => 'invalid-url'
            ]);
            assert(false, 'Should throw exception for invalid URL');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
        }
        
        echo "✓ Category creation validates input data correctly\n";
    }
    
    /**
     * Test getting category by ID
     */
    public function testGetCategoryById() {
        // Create test category
        $categoryData = [
            'name' => 'Get Test Category ' . time(),
            'description' => 'Test description'
        ];
        
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Get category by ID
        $result = $this->category->getCategoryById($created['id']);
        
        assert($result !== null, 'Should return category data');
        assert($result['id'] === $created['id'], 'Should return correct category');
        assert($result['name'] === $categoryData['name'], 'Should return correct name');
        assert(isset($result['product_count']), 'Should include product count');
        assert($result['product_count'] === 0, 'New category should have 0 products');
        
        echo "✓ Getting category by ID works correctly\n";
    }
    
    /**
     * Test getting non-existent category
     */
    public function testGetCategoryByIdNotFound() {
        $result = $this->category->getCategoryById(999999);
        assert($result === null, 'Should return null for non-existent category');
        
        echo "✓ Getting non-existent category returns null\n";
    }
    
    /**
     * Test getting all categories
     */
    public function testGetCategories() {
        // Create test categories
        $categories = [];
        for ($i = 1; $i <= 3; $i++) {
            $categoryData = [
                'name' => "Test Category {$i} " . time(),
                'description' => "Description {$i}"
            ];
            $created = $this->category->createCategory($categoryData);
            $categories[] = $created;
            $this->testCategories[] = $created['id'];
        }
        
        // Get all categories
        $result = $this->category->getCategories();
        
        assert(isset($result['categories']), 'Should return categories array');
        assert(isset($result['pagination']), 'Should return pagination info');
        assert(count($result['categories']) >= 3, 'Should return at least 3 categories');
        
        // Check pagination structure
        $pagination = $result['pagination'];
        assert(isset($pagination['current_page']), 'Should have current_page');
        assert(isset($pagination['per_page']), 'Should have per_page');
        assert(isset($pagination['total']), 'Should have total');
        assert(isset($pagination['total_pages']), 'Should have total_pages');
        
        echo "✓ Getting all categories works correctly\n";
    }
    
    /**
     * Test category search
     */
    public function testSearchCategories() {
        // Create test category with unique name
        $uniqueName = 'Searchable Category ' . time();
        $categoryData = [
            'name' => $uniqueName,
            'description' => 'Searchable description'
        ];
        
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Search by name
        $result = $this->category->searchCategories('Searchable');
        
        assert(isset($result['categories']), 'Should return categories array');
        assert(count($result['categories']) >= 1, 'Should find at least one category');
        
        $found = false;
        foreach ($result['categories'] as $cat) {
            if ($cat['id'] === $created['id']) {
                $found = true;
                break;
            }
        }
        assert($found, 'Should find the created category');
        
        echo "✓ Category search works correctly\n";
    }
    
    /**
     * Test category update
     */
    public function testUpdateCategory() {
        // Create test category
        $categoryData = [
            'name' => 'Update Test Category ' . time(),
            'description' => 'Original description'
        ];
        
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Update category
        $updateData = [
            'name' => 'Updated Category Name ' . time(),
            'description' => 'Updated description',
            'image_url' => 'https://example.com/updated.jpg'
        ];
        
        $result = $this->category->updateCategory($created['id'], $updateData);
        
        assert($result['id'] === $created['id'], 'Should return same category ID');
        assert($result['name'] === $updateData['name'], 'Should update name');
        assert($result['description'] === $updateData['description'], 'Should update description');
        assert($result['image_url'] === $updateData['image_url'], 'Should update image URL');
        
        echo "✓ Category update works correctly\n";
    }
    
    /**
     * Test category update with invalid data
     */
    public function testUpdateCategoryInvalidData() {
        // Create test category
        $categoryData = ['name' => 'Update Invalid Test ' . time()];
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Try to update with invalid name
        try {
            $this->category->updateCategory($created['id'], ['name' => '']);
            assert(false, 'Should throw exception for empty name');
        } catch (Exception $e) {
            assert($e->getCode() === 400, 'Should return 400 validation error');
        }
        
        echo "✓ Category update validates input data correctly\n";
    }
    
    /**
     * Test category update non-existent
     */
    public function testUpdateCategoryNotFound() {
        try {
            $this->category->updateCategory(999999, ['name' => 'New Name']);
            assert(false, 'Should throw exception for non-existent category');
        } catch (Exception $e) {
            assert($e->getCode() === 404, 'Should return 404 not found error');
        }
        
        echo "✓ Category update handles non-existent category correctly\n";
    }
    
    /**
     * Test category deletion
     */
    public function testDeleteCategory() {
        // Create test category
        $categoryData = ['name' => 'Delete Test Category ' . time()];
        $created = $this->category->createCategory($categoryData);
        
        // Delete category
        $result = $this->category->deleteCategory($created['id']);
        
        assert($result === true, 'Should return true on successful deletion');
        
        // Verify category is soft deleted
        $deleted = $this->category->getCategoryById($created['id']);
        assert($deleted === null, 'Deleted category should not be found');
        
        echo "✓ Category deletion works correctly\n";
    }
    
    /**
     * Test category deletion non-existent
     */
    public function testDeleteCategoryNotFound() {
        try {
            $this->category->deleteCategory(999999);
            assert(false, 'Should throw exception for non-existent category');
        } catch (Exception $e) {
            assert($e->getCode() === 404, 'Should return 404 not found error');
        }
        
        echo "✓ Category deletion handles non-existent category correctly\n";
    }
    
    /**
     * Test getting categories for select dropdown
     */
    public function testGetCategoriesForSelect() {
        // Create test categories
        $categories = [];
        for ($i = 1; $i <= 2; $i++) {
            $categoryData = ['name' => "Select Category {$i} " . time()];
            $created = $this->category->createCategory($categoryData);
            $categories[] = $created;
            $this->testCategories[] = $created['id'];
        }
        
        // Get categories for select
        $result = $this->category->getCategoriesForSelect();
        
        assert(is_array($result), 'Should return array');
        assert(count($result) >= 2, 'Should return at least 2 categories');
        
        // Check structure of first category
        if (!empty($result)) {
            $firstCategory = $result[0];
            assert(isset($firstCategory['id']), 'Should have id field');
            assert(isset($firstCategory['name']), 'Should have name field');
            assert(isset($firstCategory['product_count']), 'Should have product_count field');
        }
        
        echo "✓ Getting categories for select works correctly\n";
    }
    
    /**
     * Test category statistics
     */
    public function testGetCategoryStats() {
        // Create test category
        $categoryData = ['name' => 'Stats Test Category ' . time()];
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Get statistics
        $stats = $this->category->getCategoryStats();
        
        assert(isset($stats['total_categories']), 'Should have total_categories');
        assert(isset($stats['categories_with_products']), 'Should have categories_with_products');
        assert(isset($stats['empty_categories']), 'Should have empty_categories');
        assert(isset($stats['category_distribution']), 'Should have category_distribution');
        assert(isset($stats['recent_categories']), 'Should have recent_categories');
        
        assert($stats['total_categories'] >= 1, 'Should have at least 1 category');
        assert(is_array($stats['category_distribution']), 'Category distribution should be array');
        
        echo "✓ Category statistics work correctly\n";
    }
    
    /**
     * Test name existence check
     */
    public function testNameExists() {
        // Create test category
        $categoryName = 'Existence Test Category ' . time();
        $categoryData = ['name' => $categoryName];
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Check if name exists
        $exists = $this->category->nameExists($categoryName);
        assert($exists === true, 'Should return true for existing name');
        
        // Check non-existent name
        $notExists = $this->category->nameExists('Non Existent Category Name ' . time());
        assert($notExists === false, 'Should return false for non-existent name');
        
        // Check with exclusion
        $existsWithExclusion = $this->category->nameExists($categoryName, $created['id']);
        assert($existsWithExclusion === false, 'Should return false when excluding the same category');
        
        echo "✓ Name existence check works correctly\n";
    }
    
    /**
     * Test getting popular categories
     */
    public function testGetPopularCategories() {
        // Create test category
        $categoryData = ['name' => 'Popular Test Category ' . time()];
        $created = $this->category->createCategory($categoryData);
        $this->testCategories[] = $created['id'];
        
        // Get popular categories
        $result = $this->category->getPopularCategories(5);
        
        assert(is_array($result), 'Should return array');
        // Note: Popular categories require products, so this might be empty
        
        echo "✓ Getting popular categories works correctly\n";
    }
    
    /**
     * Test data sanitization
     */
    public function testDataSanitization() {
        // Create category with data that needs sanitization
        $categoryData = [
            'name' => '  Sanitize Test Category  ' . time(),
            'description' => '  Test description with spaces  ',
            'image_url' => '  https://example.com/image.jpg  '
        ];
        
        $result = $this->category->createCategory($categoryData);
        $this->testCategories[] = $result['id'];
        
        // Check that data is properly sanitized
        assert($result['name'] === trim($categoryData['name']), 'Name should be trimmed');
        assert($result['description'] === trim($categoryData['description']), 'Description should be trimmed');
        assert($result['image_url'] === trim($categoryData['image_url']), 'Image URL should be trimmed');
        
        echo "✓ Data sanitization works correctly\n";
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
     * Run all tests
     */
    public function runAllTests() {
        echo "Running Category Model Unit Tests...\n\n";
        
        $this->setUp();
        
        try {
            $this->testCreateCategorySuccess();
            $this->testCreateCategoryMinimalData();
            $this->testCreateCategoryDuplicateName();
            $this->testCreateCategoryInvalidData();
            $this->testGetCategoryById();
            $this->testGetCategoryByIdNotFound();
            $this->testGetCategories();
            $this->testSearchCategories();
            $this->testUpdateCategory();
            $this->testUpdateCategoryInvalidData();
            $this->testUpdateCategoryNotFound();
            $this->testDeleteCategory();
            $this->testDeleteCategoryNotFound();
            $this->testGetCategoriesForSelect();
            $this->testGetCategoryStats();
            $this->testNameExists();
            $this->testGetPopularCategories();
            $this->testDataSanitization();
            
            echo "\n✅ All Category Model unit tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CategoryTest();
    $test->runAllTests();
}