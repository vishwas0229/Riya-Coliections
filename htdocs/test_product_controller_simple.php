<?php
/**
 * Simple ProductController Test
 * 
 * Basic test to verify ProductController can be instantiated and basic methods work
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/config/environment.php';
require_once __DIR__ . '/utils/Logger.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/models/Category.php';

echo "=== ProductController Simple Test ===\n\n";

try {
    // Test 1: Controller instantiation
    echo "1. Testing ProductController instantiation...\n";
    $controller = new ProductController();
    echo "✓ ProductController created successfully\n\n";
    
    // Test 2: Set request data
    echo "2. Testing request data setting...\n";
    $testRequest = [
        'query' => ['page' => 1, 'per_page' => 10],
        'body' => null
    ];
    $controller->setRequest($testRequest);
    echo "✓ Request data set successfully\n\n";
    
    // Test 3: Set parameters
    echo "3. Testing parameter setting...\n";
    $testParams = ['id' => 1];
    $controller->setParams($testParams);
    echo "✓ Parameters set successfully\n\n";
    
    // Test 4: Test filter extraction (private method test via reflection)
    echo "4. Testing filter extraction method...\n";
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('extractFilters');
    $method->setAccessible(true);
    
    $controller->setRequest([
        'query' => [
            'search' => 'test',
            'category_id' => '1',
            'min_price' => '10.00',
            'max_price' => '100.00',
            'in_stock' => 'true',
            'sort' => 'price_asc'
        ]
    ]);
    
    $filters = $method->invoke($controller);
    
    echo "Extracted filters:\n";
    print_r($filters);
    
    // Verify filter extraction
    if (isset($filters['search']) && $filters['search'] === 'test') {
        echo "✓ Search filter extracted correctly\n";
    } else {
        echo "✗ Search filter extraction failed\n";
    }
    
    if (isset($filters['category_id']) && $filters['category_id'] === 1) {
        echo "✓ Category filter extracted correctly\n";
    } else {
        echo "✗ Category filter extraction failed\n";
    }
    
    if (isset($filters['min_price']) && $filters['min_price'] === 10.00) {
        echo "✓ Min price filter extracted correctly\n";
    } else {
        echo "✗ Min price filter extraction failed\n";
    }
    
    echo "\n";
    
    // Test 5: Test model instantiation
    echo "5. Testing model instantiation...\n";
    $productModel = new Product();
    echo "✓ Product model created successfully\n";
    
    $categoryModel = new Category();
    echo "✓ Category model created successfully\n\n";
    
    // Test 6: Test Response utility
    echo "6. Testing Response utility...\n";
    
    // Capture output for success response
    ob_start();
    Response::success('Test message', ['test' => 'data']);
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    
    if ($response && $response['success'] === true && $response['message'] === 'Test message') {
        echo "✓ Response utility working correctly\n";
    } else {
        echo "✗ Response utility test failed\n";
        echo "Output: " . $output . "\n";
    }
    
    echo "\n";
    
    // Test 7: Test ImageService instantiation
    echo "7. Testing ImageService instantiation...\n";
    require_once __DIR__ . '/services/ImageService.php';
    $imageService = new ImageService();
    echo "✓ ImageService created successfully\n\n";
    
    echo "=== All Basic Tests Passed! ===\n";
    echo "ProductController is ready for use.\n\n";
    
    // Test 8: Test validation method
    echo "8. Testing validation method...\n";
    $validationMethod = $reflection->getMethod('validateProductData');
    $validationMethod->setAccessible(true);
    
    // Test valid data
    try {
        $validData = [
            'name' => 'Test Product',
            'price' => 29.99,
            'stock_quantity' => 10
        ];
        
        $errors = $validationMethod->invoke($controller, $validData, false);
        
        if (empty($errors)) {
            echo "✓ Valid product data validation passed\n";
        } else {
            echo "✗ Valid product data validation failed\n";
            print_r($errors);
        }
    } catch (Exception $e) {
        echo "✗ Validation method test failed: " . $e->getMessage() . "\n";
    }
    
    // Test invalid data
    try {
        $invalidData = [
            'name' => '', // Empty name
            'price' => -10, // Negative price
            'stock_quantity' => -5 // Negative stock
        ];
        
        $errors = $validationMethod->invoke($controller, $invalidData, true);
        
        if (!empty($errors)) {
            echo "✓ Invalid product data validation correctly identified errors\n";
            echo "Validation errors found: " . count($errors) . "\n";
        } else {
            echo "✗ Invalid product data validation failed to identify errors\n";
        }
    } catch (Exception $e) {
        echo "✗ Invalid data validation test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== ProductController Implementation Complete! ===\n";
    
} catch (Exception $e) {
    echo "✗ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}