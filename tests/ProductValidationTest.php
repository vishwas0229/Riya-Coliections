<?php
/**
 * Product Validation Tests
 * 
 * Isolated tests for Product model validation logic without database dependencies.
 * These tests focus on the core validation and business logic.
 */

/**
 * Minimal Product class for testing validation logic
 */
class ProductValidator {
    
    /**
     * Validate product data
     */
    public function validateProductData($productData, $isCreation = false) {
        $errors = [];
        
        // Name validation
        if ($isCreation && empty($productData['name'])) {
            $errors[] = 'Product name is required';
        } elseif (!empty($productData['name']) && strlen(trim($productData['name'])) > 255) {
            $errors[] = 'Product name is too long (maximum 255 characters)';
        } elseif (!empty($productData['name']) && strlen(trim($productData['name'])) < 2) {
            $errors[] = 'Product name is too short (minimum 2 characters)';
        }
        
        // Price validation
        if ($isCreation && !isset($productData['price'])) {
            $errors[] = 'Product price is required';
        } elseif (isset($productData['price'])) {
            $price = (float)$productData['price'];
            if ($price < 0) {
                $errors[] = 'Product price cannot be negative';
            } elseif ($price > 999999.99) {
                $errors[] = 'Product price is too high (maximum 999999.99)';
            }
        }
        
        // Stock quantity validation
        if (isset($productData['stock_quantity'])) {
            $stock = (int)$productData['stock_quantity'];
            if ($stock < 0) {
                $errors[] = 'Stock quantity cannot be negative';
            } elseif ($stock > 999999) {
                $errors[] = 'Stock quantity is too high (maximum 999999)';
            }
        }
        
        // Description validation
        if (!empty($productData['description']) && strlen($productData['description']) > 65535) {
            $errors[] = 'Product description is too long (maximum 65535 characters)';
        }
        
        // Brand validation
        if (!empty($productData['brand']) && strlen(trim($productData['brand'])) > 100) {
            $errors[] = 'Brand name is too long (maximum 100 characters)';
        }
        
        // SKU validation
        if (!empty($productData['sku'])) {
            $sku = trim($productData['sku']);
            if (strlen($sku) > 50) {
                $errors[] = 'SKU is too long (maximum 50 characters)';
            } elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
                $errors[] = 'SKU can only contain letters, numbers, hyphens, and underscores';
            }
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Generate unique SKU
     */
    public function generateSKU($productName) {
        // Create base SKU from product name
        $baseSku = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($productName, 0, 6)));
        
        // Add timestamp suffix
        $sku = $baseSku . date('ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        return $sku;
    }
    
    /**
     * Sanitize product data for API response
     */
    public function sanitizeProductData($product) {
        $sanitized = [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'stock_quantity' => (int)$product['stock_quantity'],
            'category_id' => $product['category_id'] ? (int)$product['category_id'] : null,
            'brand' => $product['brand'],
            'sku' => $product['sku'],
            'is_active' => (bool)$product['is_active'],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
        
        // Add computed fields
        $sanitized['in_stock'] = $sanitized['stock_quantity'] > 0;
        $sanitized['formatted_price'] = number_format($sanitized['price'], 2);
        
        return $sanitized;
    }
    
    /**
     * Build ORDER BY clause
     */
    public function buildOrderBy($sort) {
        $validSorts = [
            'name_asc' => 'p.name ASC',
            'name_desc' => 'p.name DESC',
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'created_asc' => 'p.created_at ASC',
            'created_desc' => 'p.created_at DESC',
            'stock_asc' => 'p.stock_quantity ASC',
            'stock_desc' => 'p.stock_quantity DESC',
            'brand_asc' => 'p.brand ASC',
            'brand_desc' => 'p.brand DESC'
        ];
        
        if (!empty($sort) && isset($validSorts[$sort])) {
            return $validSorts[$sort];
        }
        
        // Default sort
        return 'p.created_at DESC';
    }
}

/**
 * Product Validation Tests
 */
class ProductValidationTest {
    private $validator;
    
    public function setUp() {
        $this->validator = new ProductValidator();
        echo "Product validation test setup completed\n";
    }
    
    /**
     * Test valid product data validation
     */
    public function testValidProductData() {
        $validData = [
            'name' => 'Test Product',
            'price' => 29.99,
            'stock_quantity' => 100,
            'description' => 'This is a test product',
            'brand' => 'Test Brand',
            'sku' => 'TEST001'
        ];
        
        try {
            $result = $this->validator->validateProductData($validData, true);
            assert($result === true, 'Valid product data should pass validation');
            echo "✓ Valid product data validation test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Valid product data validation test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test invalid product data validation
     */
    public function testInvalidProductData() {
        $testCases = [
            [
                'data' => ['price' => 29.99], // Missing name
                'expected_error' => 'Product name is required'
            ],
            [
                'data' => ['name' => 'Test', 'price' => -10], // Negative price
                'expected_error' => 'Product price cannot be negative'
            ],
            [
                'data' => ['name' => 'Test', 'price' => 29.99, 'stock_quantity' => -5], // Negative stock
                'expected_error' => 'Stock quantity cannot be negative'
            ],
            [
                'data' => ['name' => str_repeat('A', 300), 'price' => 29.99], // Name too long
                'expected_error' => 'Product name is too long'
            ],
            [
                'data' => ['name' => 'A', 'price' => 29.99], // Name too short
                'expected_error' => 'Product name is too short'
            ],
            [
                'data' => ['name' => 'Test', 'price' => 1000000], // Price too high
                'expected_error' => 'Product price is too high'
            ],
            [
                'data' => ['name' => 'Test', 'price' => 29.99, 'sku' => 'INVALID SKU!'], // Invalid SKU
                'expected_error' => 'SKU can only contain letters, numbers, hyphens, and underscores'
            ]
        ];
        
        $passed = 0;
        foreach ($testCases as $i => $testCase) {
            try {
                $this->validator->validateProductData($testCase['data'], true);
                echo "✗ Test case {$i} should have failed but passed\n";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), $testCase['expected_error']) !== false) {
                    $passed++;
                } else {
                    echo "✗ Test case {$i} failed with wrong error: " . $e->getMessage() . "\n";
                }
            }
        }
        
        if ($passed === count($testCases)) {
            echo "✓ Invalid product data validation test passed ({$passed}/" . count($testCases) . ")\n";
            return true;
        } else {
            echo "✗ Invalid product data validation test failed ({$passed}/" . count($testCases) . ")\n";
            return false;
        }
    }
    
    /**
     * Test SKU generation
     */
    public function testSKUGeneration() {
        $testNames = [
            'Test Product',
            'Another Product Name',
            'Special Characters!@#',
            'Very Long Product Name That Exceeds Normal Length',
            'Short'
        ];
        
        $generatedSKUs = [];
        
        foreach ($testNames as $name) {
            $sku = $this->validator->generateSKU($name);
            
            // Check SKU properties
            assert(!empty($sku), 'SKU should not be empty');
            assert(strlen($sku) >= 6, 'SKU should have reasonable length');
            assert(preg_match('/^[A-Z0-9]+$/', $sku), 'SKU should contain only uppercase letters and numbers');
            assert(!in_array($sku, $generatedSKUs), 'SKU should be unique');
            
            $generatedSKUs[] = $sku;
        }
        
        echo "✓ SKU generation test passed (" . count($testNames) . " unique SKUs generated)\n";
        return true;
    }
    
    /**
     * Test product data sanitization
     */
    public function testProductDataSanitization() {
        $rawData = [
            'id' => '123',
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => '29.99',
            'stock_quantity' => '100',
            'category_id' => '5',
            'brand' => 'Test Brand',
            'sku' => 'TEST001',
            'is_active' => '1',
            'created_at' => '2023-01-01 12:00:00',
            'updated_at' => '2023-01-01 12:00:00'
        ];
        
        $sanitized = $this->validator->sanitizeProductData($rawData);
        
        // Check data types
        assert(is_int($sanitized['id']), 'ID should be integer');
        assert(is_float($sanitized['price']), 'Price should be float');
        assert(is_int($sanitized['stock_quantity']), 'Stock quantity should be integer');
        assert(is_int($sanitized['category_id']), 'Category ID should be integer');
        assert(is_bool($sanitized['is_active']), 'Is active should be boolean');
        
        // Check computed fields
        assert(isset($sanitized['in_stock']), 'Should include in_stock field');
        assert(isset($sanitized['formatted_price']), 'Should include formatted_price field');
        assert(is_bool($sanitized['in_stock']), 'In stock should be boolean');
        assert($sanitized['in_stock'] === true, 'Should be in stock when stock > 0');
        assert($sanitized['formatted_price'] === '29.99', 'Formatted price should be correct');
        
        // Test with zero stock
        $rawData['stock_quantity'] = '0';
        $sanitized = $this->validator->sanitizeProductData($rawData);
        assert($sanitized['in_stock'] === false, 'Should not be in stock when stock = 0');
        
        echo "✓ Product data sanitization test passed\n";
        return true;
    }
    
    /**
     * Test order by clause building
     */
    public function testOrderByBuilding() {
        $testCases = [
            ['sort' => 'name_asc', 'expected' => 'p.name ASC'],
            ['sort' => 'name_desc', 'expected' => 'p.name DESC'],
            ['sort' => 'price_asc', 'expected' => 'p.price ASC'],
            ['sort' => 'price_desc', 'expected' => 'p.price DESC'],
            ['sort' => 'created_asc', 'expected' => 'p.created_at ASC'],
            ['sort' => 'created_desc', 'expected' => 'p.created_at DESC'],
            ['sort' => 'stock_asc', 'expected' => 'p.stock_quantity ASC'],
            ['sort' => 'stock_desc', 'expected' => 'p.stock_quantity DESC'],
            ['sort' => 'brand_asc', 'expected' => 'p.brand ASC'],
            ['sort' => 'brand_desc', 'expected' => 'p.brand DESC'],
            ['sort' => 'invalid_sort', 'expected' => 'p.created_at DESC'], // Default
            ['sort' => null, 'expected' => 'p.created_at DESC'], // Default
            ['sort' => '', 'expected' => 'p.created_at DESC'] // Default
        ];
        
        foreach ($testCases as $testCase) {
            $result = $this->validator->buildOrderBy($testCase['sort']);
            assert($result === $testCase['expected'], 
                   "Sort '{$testCase['sort']}' should return '{$testCase['expected']}', got '{$result}'");
        }
        
        echo "✓ Order by building test passed (" . count($testCases) . " cases)\n";
        return true;
    }
    
    /**
     * Test edge cases and boundary values
     */
    public function testEdgeCases() {
        // Test minimum valid values
        $minValidData = [
            'name' => 'AB', // Minimum length
            'price' => 0.01 // Minimum price
        ];
        
        try {
            $this->validator->validateProductData($minValidData, true);
            echo "✓ Minimum valid values test passed\n";
        } catch (Exception $e) {
            echo "✗ Minimum valid values test failed: " . $e->getMessage() . "\n";
            return false;
        }
        
        // Test maximum valid values
        $maxValidData = [
            'name' => str_repeat('A', 255), // Maximum length
            'price' => 999999.99, // Maximum price
            'stock_quantity' => 999999, // Maximum stock
            'description' => str_repeat('A', 65535), // Maximum description
            'brand' => str_repeat('B', 100), // Maximum brand length
            'sku' => str_repeat('S', 50) // Maximum SKU length
        ];
        
        try {
            $this->validator->validateProductData($maxValidData, true);
            echo "✓ Maximum valid values test passed\n";
        } catch (Exception $e) {
            echo "✗ Maximum valid values test failed: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    /**
     * Test update validation (different from creation)
     */
    public function testUpdateValidation() {
        // For updates, name and price are not required
        $updateData = [
            'description' => 'Updated description',
            'stock_quantity' => 50
        ];
        
        try {
            $this->validator->validateProductData($updateData, false);
            echo "✓ Update validation test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Update validation test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Starting Product Validation Tests...\n\n";
        
        $this->setUp();
        
        $tests = [
            'testValidProductData',
            'testInvalidProductData',
            'testSKUGeneration',
            'testProductDataSanitization',
            'testOrderByBuilding',
            'testEdgeCases',
            'testUpdateValidation'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        echo "\n";
        if ($passed === $total) {
            echo "✅ All Product Validation tests passed! ({$passed}/{$total})\n";
        } else {
            echo "❌ Some tests failed. Passed: {$passed}/{$total}\n";
        }
        
        return $passed === $total;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ProductValidationTest();
    $test->runAllTests();
}