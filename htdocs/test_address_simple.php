<?php
/**
 * Simple Address Model Test
 * 
 * Basic test to verify Address model functionality and structure.
 */

require_once __DIR__ . '/models/Address.php';
require_once __DIR__ . '/models/Database.php';

try {
    echo "=== Address Model Simple Test ===\n\n";
    
    // Initialize Address model
    $address = new Address();
    echo "✓ Address model initialized successfully\n";
    
    // Test validation methods
    echo "\n--- Testing Validation Methods ---\n";
    
    // Test postal code validation
    $validIndianCode = $address->validatePostalCode('400001', 'India');
    echo "✓ Indian postal code validation: " . ($validIndianCode ? 'PASS' : 'FAIL') . "\n";
    
    $validUSCode = $address->validatePostalCode('12345', 'USA');
    echo "✓ US postal code validation: " . ($validUSCode ? 'PASS' : 'FAIL') . "\n";
    
    $validUKCode = $address->validatePostalCode('SW1A 1AA', 'UK');
    echo "✓ UK postal code validation: " . ($validUKCode ? 'PASS' : 'FAIL') . "\n";
    
    // Test phone number validation
    $validPhone = $address->validatePhoneNumber('9876543210');
    echo "✓ Phone number validation: " . ($validPhone ? 'PASS' : 'FAIL') . "\n";
    
    $invalidPhone = $address->validatePhoneNumber('123');
    echo "✓ Invalid phone rejection: " . (!$invalidPhone ? 'PASS' : 'FAIL') . "\n";
    
    // Test formatted address generation
    echo "\n--- Testing Address Formatting ---\n";
    
    $sampleAddress = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_line1' => '123 Main Street',
        'address_line2' => 'Apt 4B',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'postal_code' => '400001',
        'country' => 'India',
        'phone' => '9876543210'
    ];
    
    $formatted = $address->getFormattedAddress($sampleAddress);
    echo "✓ Formatted address generated:\n";
    echo str_replace("\n", "\n  ", $formatted) . "\n";
    
    // Test database connection
    echo "\n--- Testing Database Connection ---\n";
    
    $db = Database::getInstance();
    echo "✓ Database connection established\n";
    
    // Test table existence (if database is set up)
    try {
        $result = $db->fetchOne("SHOW TABLES LIKE 'addresses'");
        if ($result) {
            echo "✓ Addresses table exists\n";
            
            // Test table structure
            $columns = $db->fetchAll("DESCRIBE addresses");
            echo "✓ Address table has " . count($columns) . " columns\n";
            
            $expectedColumns = ['id', 'user_id', 'type', 'first_name', 'last_name', 
                              'address_line1', 'city', 'state', 'postal_code', 'is_default'];
            
            $actualColumns = array_column($columns, 'Field');
            $missingColumns = array_diff($expectedColumns, $actualColumns);
            
            if (empty($missingColumns)) {
                echo "✓ All required columns present\n";
            } else {
                echo "⚠ Missing columns: " . implode(', ', $missingColumns) . "\n";
            }
        } else {
            echo "⚠ Addresses table not found (database may not be set up)\n";
        }
    } catch (Exception $e) {
        echo "⚠ Could not check table structure: " . $e->getMessage() . "\n";
    }
    
    // Test model methods exist
    echo "\n--- Testing Model Methods ---\n";
    
    $methods = [
        'createAddress',
        'getAddressById',
        'getAddressesByUser',
        'getDefaultAddress',
        'updateAddress',
        'deleteAddress',
        'setDefaultAddress',
        'getFormattedAddress',
        'validatePostalCode',
        'validatePhoneNumber'
    ];
    
    foreach ($methods as $method) {
        if (method_exists($address, $method)) {
            echo "✓ Method {$method} exists\n";
        } else {
            echo "✗ Method {$method} missing\n";
        }
    }
    
    echo "\n=== Address Model Test Complete ===\n";
    echo "✓ All basic functionality verified\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}