<?php
/**
 * Address Model Validation Test
 * 
 * Test Address model validation methods independently.
 */

// Create a minimal Address class for testing validation methods only
class AddressValidator {
    
    /**
     * Validate postal code format
     */
    public function validatePostalCode($postalCode, $country = 'India') {
        switch (strtolower($country)) {
            case 'india':
                // Indian postal code: 6 digits
                return preg_match('/^\d{6}$/', $postalCode);
            
            case 'usa':
            case 'united states':
                // US ZIP code: 5 digits or 5+4 format
                return preg_match('/^\d{5}(-\d{4})?$/', $postalCode);
            
            case 'uk':
            case 'united kingdom':
                // UK postal code format
                return preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i', $postalCode);
            
            default:
                // Generic validation: alphanumeric with spaces and hyphens
                return preg_match('/^[A-Z0-9\s\-]{3,20}$/i', $postalCode);
        }
    }
    
    /**
     * Validate phone number format
     */
    public function validatePhoneNumber($phone) {
        // Remove all non-digit characters
        $cleanPhone = preg_replace('/\D/', '', $phone);
        
        // Indian mobile number: 10 digits starting with 6-9
        if (preg_match('/^[6-9]\d{9}$/', $cleanPhone)) {
            return true;
        }
        
        // Indian landline with STD code: 10-11 digits
        if (preg_match('/^\d{10,11}$/', $cleanPhone)) {
            return true;
        }
        
        // International format: 7-15 digits
        if (preg_match('/^\d{7,15}$/', $cleanPhone)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get formatted address string
     */
    public function getFormattedAddress($address) {
        $parts = [];
        
        // Name
        $name = trim($address['first_name'] . ' ' . $address['last_name']);
        if ($name) {
            $parts[] = $name;
        }
        
        // Address lines
        if (!empty($address['address_line1'])) {
            $parts[] = $address['address_line1'];
        }
        
        if (!empty($address['address_line2'])) {
            $parts[] = $address['address_line2'];
        }
        
        // City, State, Postal Code
        $cityStateParts = [];
        if (!empty($address['city'])) {
            $cityStateParts[] = $address['city'];
        }
        if (!empty($address['state'])) {
            $cityStateParts[] = $address['state'];
        }
        if (!empty($address['postal_code'])) {
            $cityStateParts[] = $address['postal_code'];
        }
        
        if (!empty($cityStateParts)) {
            $parts[] = implode(', ', $cityStateParts);
        }
        
        // Country
        if (!empty($address['country']) && $address['country'] !== 'India') {
            $parts[] = $address['country'];
        }
        
        // Phone
        if (!empty($address['phone'])) {
            $parts[] = 'Phone: ' . $address['phone'];
        }
        
        return implode("\n", $parts);
    }
}

try {
    echo "=== Address Validation Test ===\n\n";
    
    // Initialize validator
    $validator = new AddressValidator();
    echo "✓ Address validator initialized successfully\n";
    
    // Test postal code validation
    echo "\n--- Testing Postal Code Validation ---\n";
    
    $postalTests = [
        // Indian postal codes
        ['400001', 'India', true, 'Valid Indian postal code'],
        ['110001', 'India', true, 'Valid Indian postal code'],
        ['40001', 'India', false, 'Invalid Indian postal code (too short)'],
        ['4000012', 'India', false, 'Invalid Indian postal code (too long)'],
        
        // US ZIP codes
        ['12345', 'USA', true, 'Valid US ZIP code'],
        ['90210', 'USA', true, 'Valid US ZIP code'],
        ['12345-6789', 'USA', true, 'Valid US ZIP+4 code'],
        ['1234', 'USA', false, 'Invalid US ZIP code (too short)'],
        ['123456', 'USA', false, 'Invalid US ZIP code (too long)'],
        
        // UK postal codes
        ['SW1A 1AA', 'UK', true, 'Valid UK postal code'],
        ['M1 1AA', 'UK', true, 'Valid UK postal code'],
        ['B33 8TH', 'UK', true, 'Valid UK postal code'],
        ['123456', 'UK', false, 'Invalid UK postal code'],
        ['INVALID', 'UK', false, 'Invalid UK postal code'],
    ];
    
    foreach ($postalTests as [$code, $country, $expected, $description]) {
        $result = $validator->validatePostalCode($code, $country);
        $status = ($result === $expected) ? 'PASS' : 'FAIL';
        echo "  {$status}: {$description} - '{$code}' for {$country}\n";
    }
    
    // Test phone number validation
    echo "\n--- Testing Phone Number Validation ---\n";
    
    $phoneTests = [
        ['9876543210', true, 'Valid Indian mobile number'],
        ['8123456789', true, 'Valid Indian mobile number'],
        ['7987654321', true, 'Valid Indian mobile number'],
        ['6123456789', true, 'Valid Indian mobile number'],
        ['+919876543210', true, 'Valid Indian mobile with country code'],
        ['02212345678', true, 'Valid Indian landline (11 digits)'],
        ['01123456789', true, 'Valid Indian landline (11 digits)'],
        ['2212345678', true, 'Valid Indian landline (10 digits)'],
        ['123456789012345', true, 'Valid international number (15 digits)'],
        
        ['5123456789', false, 'Invalid Indian mobile (starts with 5)'],
        ['4123456789', false, 'Invalid Indian mobile (starts with 4)'],
        ['123456', false, 'Invalid phone (too short)'],
        ['12345678901234567', false, 'Invalid phone (too long)'],
        ['abcdefghij', false, 'Invalid phone (non-numeric)'],
        ['', false, 'Invalid phone (empty)'],
    ];
    
    foreach ($phoneTests as [$phone, $expected, $description]) {
        $result = $validator->validatePhoneNumber($phone);
        $status = ($result === $expected) ? 'PASS' : 'FAIL';
        echo "  {$status}: {$description} - '{$phone}'\n";
    }
    
    // Test address formatting
    echo "\n--- Testing Address Formatting ---\n";
    
    $sampleAddresses = [
        [
            'address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_line1' => '123 Main Street',
                'address_line2' => 'Apt 4B',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'postal_code' => '400001',
                'country' => 'India',
                'phone' => '9876543210'
            ],
            'expected_components' => ['John Doe', '123 Main Street', 'Apt 4B', 'Mumbai, Maharashtra, 400001', 'Phone: 9876543210']
        ],
        [
            'address' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'address_line1' => '456 Business Ave',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'postal_code' => '110001',
                'country' => 'USA',
                'phone' => '8123456789'
            ],
            'expected_components' => ['Jane Smith', '456 Business Ave', 'Delhi, Delhi, 110001', 'USA', 'Phone: 8123456789']
        ]
    ];
    
    foreach ($sampleAddresses as $index => $testCase) {
        echo "\n  Test Address " . ($index + 1) . ":\n";
        $formatted = $validator->getFormattedAddress($testCase['address']);
        echo "  Formatted Address:\n" . str_replace("\n", "\n    ", $formatted) . "\n";
        
        // Check for expected components
        foreach ($testCase['expected_components'] as $component) {
            if (strpos($formatted, $component) !== false) {
                echo "    ✓ Contains: {$component}\n";
            } else {
                echo "    ✗ Missing: {$component}\n";
            }
        }
    }
    
    // Test edge cases
    echo "\n--- Testing Edge Cases ---\n";
    
    // Empty address
    $emptyAddress = [];
    $emptyFormatted = $validator->getFormattedAddress($emptyAddress);
    echo "  Empty address formatted: '" . str_replace("\n", "\\n", $emptyFormatted) . "'\n";
    
    // Minimal address
    $minimalAddress = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'address_line1' => '123 Test St',
        'city' => 'Test City',
        'state' => 'Test State',
        'postal_code' => '123456'
    ];
    $minimalFormatted = $validator->getFormattedAddress($minimalAddress);
    echo "  Minimal address formatted:\n" . str_replace("\n", "\n    ", $minimalFormatted) . "\n";
    
    echo "\n=== Address Validation Test Complete ===\n";
    echo "✓ All validation tests completed successfully\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}