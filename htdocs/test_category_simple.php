<?php
/**
 * Simple Category Validation Test
 * 
 * Tests the validation logic without loading the full Category model.
 */

/**
 * Test category name validation
 */
function testCategoryNameValidation($name) {
    $errors = [];
    
    // Name validation
    if (empty($name)) {
        $errors[] = 'Category name is required';
    } elseif (!is_string($name)) {
        $errors[] = 'Category name must be a string';
    } else {
        $trimmedName = trim($name);
        if (strlen($trimmedName) > 100) {
            $errors[] = 'Category name is too long (maximum 100 characters)';
        } elseif (strlen($trimmedName) < 2) {
            $errors[] = 'Category name is too short (minimum 2 characters)';
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-_&()]+$/', $trimmedName)) {
            $errors[] = 'Category name contains invalid characters';
        }
    }
    
    return $errors;
}

/**
 * Test category description validation
 */
function testCategoryDescriptionValidation($description) {
    $errors = [];
    
    if ($description !== null && !empty($description)) {
        if (!is_string($description)) {
            $errors[] = 'Description must be a string';
        } elseif (strlen($description) > 65535) {
            $errors[] = 'Category description is too long (maximum 65535 characters)';
        }
    }
    
    return $errors;
}

/**
 * Test category image URL validation
 */
function testCategoryImageUrlValidation($imageUrl) {
    $errors = [];
    
    if ($imageUrl !== null && !empty($imageUrl)) {
        if (!is_string($imageUrl)) {
            $errors[] = 'Image URL must be a string';
        } else {
            $trimmedUrl = trim($imageUrl);
            if (strlen($trimmedUrl) > 255) {
                $errors[] = 'Image URL is too long (maximum 255 characters)';
            } elseif (!filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid image URL format';
            }
        }
    }
    
    return $errors;
}

/**
 * Run validation tests
 */
function runValidationTests() {
    echo "Running Category Validation Logic Tests...\n\n";
    
    $testsPassed = 0;
    $totalTests = 0;
    
    // Test 1: Valid name
    $totalTests++;
    $errors = testCategoryNameValidation('Electronics');
    if (empty($errors)) {
        echo "✓ Test 1: Valid name accepted\n";
        $testsPassed++;
    } else {
        echo "✗ Test 1: Valid name rejected: " . implode(', ', $errors) . "\n";
    }
    
    // Test 2: Empty name
    $totalTests++;
    $errors = testCategoryNameValidation('');
    if (!empty($errors) && strpos($errors[0], 'required') !== false) {
        echo "✓ Test 2: Empty name correctly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ Test 2: Empty name should be rejected\n";
    }
    
    // Test 3: Name too long
    $totalTests++;
    $errors = testCategoryNameValidation(str_repeat('a', 101));
    if (!empty($errors) && strpos($errors[0], 'too long') !== false) {
        echo "✓ Test 3: Long name correctly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ Test 3: Long name should be rejected\n";
    }
    
    // Test 4: Name too short
    $totalTests++;
    $errors = testCategoryNameValidation('a');
    if (!empty($errors) && strpos($errors[0], 'too short') !== false) {
        echo "✓ Test 4: Short name correctly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ Test 4: Short name should be rejected\n";
    }
    
    // Test 5: Invalid characters
    $totalTests++;
    $errors = testCategoryNameValidation('Test<>Category');
    if (!empty($errors) && strpos($errors[0], 'invalid characters') !== false) {
        echo "✓ Test 5: Invalid characters correctly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ Test 5: Invalid characters should be rejected\n";
    }
    
    // Test 6: Valid characters
    $totalTests++;
    $errors = testCategoryNameValidation('Fashion & Beauty');
    if (empty($errors)) {
        echo "✓ Test 6: Valid characters with ampersand accepted\n";
        $testsPassed++;
    } else {
        echo "✗ Test 6: Valid characters should be accepted: " . implode(', ', $errors) . "\n";
    }
    
    // Test 7: Valid description
    $totalTests++;
    $errors = testCategoryDescriptionValidation('This is a valid description');
    if (empty($errors)) {
        echo "✓ Test 7: Valid description accepted\n";
        $testsPassed++;
    } else {
        echo "✗ Test 7: Valid description rejected: " . implode(', ', $errors) . "\n";
    }
    
    // Test 8: Description too long
    $totalTests++;
    $errors = testCategoryDescriptionValidation(str_repeat('a', 65536));
    if (!empty($errors) && strpos($errors[0], 'too long') !== false) {
        echo "✓ Test 8: Long description correctly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ Test 8: Long description should be rejected\n";
    }
    
    // Test 9: Valid URL
    $totalTests++;
    $errors = testCategoryImageUrlValidation('https://example.com/image.jpg');
    if (empty($errors)) {
        echo "✓ Test 9: Valid URL accepted\n";
        $testsPassed++;
    } else {
        echo "✗ Test 9: Valid URL rejected: " . implode(', ', $errors) . "\n";
    }
    
    // Test 10: Invalid URL
    $totalTests++;
    $errors = testCategoryImageUrlValidation('not-a-valid-url');
    if (!empty($errors) && strpos($errors[0], 'Invalid image URL') !== false) {
        echo "✓ Test 10: Invalid URL correctly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ Test 10: Invalid URL should be rejected\n";
    }
    
    // Test 11: URL too long
    $totalTests++;
    $errors = testCategoryImageUrlValidation('https://example.com/' . str_repeat('a', 250) . '.jpg');
    if (!empty($errors) && strpos($errors[0], 'too long') !== false) {
        echo "✓ Test 11: Long URL correctly rejected\n";
        $testsPassed++;
    } else {
        echo "✗ Test 11: Long URL should be rejected\n";
    }
    
    // Test 12: Null values (should be accepted)
    $totalTests++;
    $nameErrors = testCategoryNameValidation(null);
    $descErrors = testCategoryDescriptionValidation(null);
    $urlErrors = testCategoryImageUrlValidation(null);
    
    if (!empty($nameErrors) && empty($descErrors) && empty($urlErrors)) {
        echo "✓ Test 12: Null handling works correctly (name required, others optional)\n";
        $testsPassed++;
    } else {
        echo "✗ Test 12: Null handling incorrect\n";
    }
    
    echo "\n";
    echo "Tests passed: {$testsPassed}/{$totalTests}\n";
    
    if ($testsPassed === $totalTests) {
        echo "✅ All validation tests passed!\n";
        return true;
    } else {
        echo "❌ Some validation tests failed!\n";
        return false;
    }
}

// Run the tests
runValidationTests();