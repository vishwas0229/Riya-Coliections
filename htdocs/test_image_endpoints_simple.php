<?php
/**
 * Simple test for image upload endpoints
 * Tests the basic functionality without database dependencies
 */

require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/services/ImageService.php';
require_once __DIR__ . '/utils/Response.php';

echo "Testing ProductController Image Endpoints\n";
echo "========================================\n\n";

// Test 1: Verify ProductController has new methods
echo "1. Testing ProductController methods exist:\n";

$controller = new ProductController();
$methods = get_class_methods($controller);

$requiredMethods = [
    'getImages',
    'deleteAllImages', 
    'deleteImage',
    'setPrimaryImage',
    'updateImage'
];

foreach ($requiredMethods as $method) {
    if (in_array($method, $methods)) {
        echo "   ✓ Method $method exists\n";
    } else {
        echo "   ✗ Method $method missing\n";
    }
}

echo "\n";

// Test 2: Verify method signatures
echo "2. Testing method signatures:\n";

$reflection = new ReflectionClass($controller);

try {
    $getImagesMethod = $reflection->getMethod('getImages');
    $params = $getImagesMethod->getParameters();
    if (count($params) === 1 && $params[0]->getName() === 'id') {
        echo "   ✓ getImages($id) signature correct\n";
    } else {
        echo "   ✗ getImages signature incorrect\n";
    }
} catch (Exception $e) {
    echo "   ✗ getImages method not found\n";
}

try {
    $deleteImageMethod = $reflection->getMethod('deleteImage');
    $params = $deleteImageMethod->getParameters();
    if (count($params) === 2 && $params[0]->getName() === 'id' && $params[1]->getName() === 'imageId') {
        echo "   ✓ deleteImage($id, $imageId) signature correct\n";
    } else {
        echo "   ✗ deleteImage signature incorrect\n";
    }
} catch (Exception $e) {
    echo "   ✗ deleteImage method not found\n";
}

try {
    $setPrimaryMethod = $reflection->getMethod('setPrimaryImage');
    $params = $setPrimaryMethod->getParameters();
    if (count($params) === 2 && $params[0]->getName() === 'id' && $params[1]->getName() === 'imageId') {
        echo "   ✓ setPrimaryImage($id, $imageId) signature correct\n";
    } else {
        echo "   ✗ setPrimaryImage signature incorrect\n";
    }
} catch (Exception $e) {
    echo "   ✗ setPrimaryImage method not found\n";
}

echo "\n";

// Test 3: Verify Product model has new methods
echo "3. Testing Product model methods:\n";

require_once __DIR__ . '/models/Product.php';

try {
    $productReflection = new ReflectionClass('Product');
    
    $requiredProductMethods = [
        'getProductImageById',
        'deleteAllProductImages',
        'deleteProductImage', 
        'setPrimaryProductImage',
        'updateProductImage'
    ];
    
    foreach ($requiredProductMethods as $method) {
        if ($productReflection->hasMethod($method)) {
            echo "   ✓ Product::$method exists\n";
        } else {
            echo "   ✗ Product::$method missing\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error checking Product methods: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Verify ImageService methods
echo "4. Testing ImageService methods:\n";

try {
    $imageService = new ImageService();
    $imageReflection = new ReflectionClass($imageService);
    
    if ($imageReflection->hasMethod('deleteProductImages')) {
        echo "   ✓ ImageService::deleteProductImages exists\n";
    } else {
        echo "   ✗ ImageService::deleteProductImages missing\n";
    }
    
    if ($imageReflection->hasMethod('uploadProductImage')) {
        echo "   ✓ ImageService::uploadProductImage exists\n";
    } else {
        echo "   ✗ ImageService::uploadProductImage missing\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error checking ImageService: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Test input validation (without database)
echo "5. Testing input validation:\n";

// Mock request for invalid product ID
$controller->setRequest(['query' => [], 'body' => null]);

// Test invalid product ID validation
ob_start();
try {
    $controller->getImages('invalid');
    $output = ob_get_clean();
    
    if (strpos($output, 'Invalid product ID') !== false) {
        echo "   ✓ Invalid product ID validation works\n";
    } else {
        echo "   ✗ Invalid product ID validation failed\n";
        echo "   Output: " . substr($output, 0, 100) . "...\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✓ Exception thrown for invalid input (expected): " . $e->getMessage() . "\n";
}

// Test negative product ID
ob_start();
try {
    $controller->getImages(-1);
    $output = ob_get_clean();
    
    if (strpos($output, 'Invalid product ID') !== false) {
        echo "   ✓ Negative product ID validation works\n";
    } else {
        echo "   ✗ Negative product ID validation failed\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✓ Exception thrown for negative ID (expected): " . $e->getMessage() . "\n";
}

echo "\n";

echo "Basic functionality tests completed!\n";
echo "Note: Full database tests require proper database setup.\n";