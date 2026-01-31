# Address Model Implementation

## Overview

The Address model provides comprehensive address management functionality for the Riya Collections PHP backend. It handles shipping and billing addresses with proper validation, formatting, and default address management while maintaining API compatibility with the existing Node.js backend.

## Requirements Addressed

- **Requirement 6.1**: Order Processing System - Address management for shipping and billing

## Features

### Core Functionality

1. **CRUD Operations**
   - Create new addresses with validation
   - Read addresses by ID or user
   - Update existing addresses
   - Delete addresses (with usage checks)

2. **Address Validation**
   - Postal code validation by country
   - Phone number format validation
   - Required field validation
   - Data type validation

3. **Address Formatting**
   - Automatic name capitalization
   - Consistent address formatting
   - Phone number formatting with country codes
   - Formatted address string generation

4. **Default Address Management**
   - Only one default address per user
   - Automatic default address switching
   - Default address retrieval

## Database Schema

```sql
CREATE TABLE addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('home', 'work', 'other') DEFAULT 'home',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) DEFAULT 'India',
    phone VARCHAR(20),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_user_default (user_id, is_default)
);
```

## Class Structure

### Constants

```php
const TYPE_HOME = 'home';
const TYPE_WORK = 'work';
const TYPE_OTHER = 'other';
```

### Key Methods

#### Address CRUD Operations

```php
// Create new address
public function createAddress($addressData)

// Get address by ID
public function getAddressById($addressId)

// Get addresses by user
public function getAddressesByUser($userId, $filters = [])

// Get user's default address
public function getDefaultAddress($userId)

// Update address
public function updateAddress($addressId, $addressData)

// Delete address
public function deleteAddress($addressId, $userId)

// Set address as default
public function setDefaultAddress($addressId, $userId)
```

#### Validation Methods

```php
// Validate postal code by country
public function validatePostalCode($postalCode, $country = 'India')

// Validate phone number format
public function validatePhoneNumber($phone)
```

#### Formatting Methods

```php
// Get formatted address string
public function getFormattedAddress($address)
```

## Validation Rules

### Required Fields
- `user_id`: Integer, required
- `type`: String, must be 'home', 'work', or 'other'
- `first_name`: String, max 100 characters
- `last_name`: String, max 100 characters
- `address_line1`: String, max 255 characters
- `city`: String, max 100 characters
- `state`: String, max 100 characters
- `postal_code`: String, max 20 characters

### Optional Fields
- `address_line2`: String, max 255 characters
- `country`: String, max 100 characters (defaults to 'India')
- `phone`: String, max 20 characters
- `is_default`: Boolean (defaults to false)

### Postal Code Validation

#### India
- Format: 6 digits (e.g., 400001)
- Regex: `/^\d{6}$/`

#### USA
- Format: 5 digits or 5+4 format (e.g., 12345 or 12345-6789)
- Regex: `/^\d{5}(-\d{4})?$/`

#### UK
- Format: Standard UK postal code (e.g., SW1A 1AA)
- Regex: `/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i`

#### Generic
- Format: Alphanumeric with spaces and hyphens, 3-20 characters
- Regex: `/^[A-Z0-9\s\-]{3,20}$/i`

### Phone Number Validation

#### Indian Mobile Numbers
- Format: 10 digits starting with 6-9
- Regex: `/^[6-9]\d{9}$/`

#### Indian Landline Numbers
- Format: 10-11 digits
- Regex: `/^\d{10,11}$/`

#### International Numbers
- Format: 7-15 digits
- Regex: `/^\d{7,15}$/`

## Data Formatting

### Name Formatting
- Names are automatically capitalized using `ucwords(strtolower())`
- Example: "john doe" → "John Doe"

### Phone Number Formatting
- Non-digit characters are removed
- Indian mobile numbers get +91 country code if not present
- Example: "9876543210" → "+919876543210"

### Address Formatting
- City, state, and country names are capitalized
- Postal codes are converted to uppercase
- Example: "mumbai" → "Mumbai"

## Usage Examples

### Creating an Address

```php
$address = new Address();

$addressData = [
    'user_id' => 1,
    'type' => 'home',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'address_line1' => '123 Main Street',
    'address_line2' => 'Apt 4B',
    'city' => 'Mumbai',
    'state' => 'Maharashtra',
    'postal_code' => '400001',
    'country' => 'India',
    'phone' => '9876543210',
    'is_default' => true
];

$createdAddress = $address->createAddress($addressData);
```

### Getting User Addresses

```php
// Get all addresses for user
$addresses = $address->getAddressesByUser(1);

// Get only home addresses
$homeAddresses = $address->getAddressesByUser(1, ['type' => 'home']);

// Get default address
$defaultAddress = $address->getDefaultAddress(1);
```

### Updating an Address

```php
$updateData = [
    'city' => 'New Mumbai',
    'postal_code' => '400002'
];

$updatedAddress = $address->updateAddress(1, $updateData);
```

### Validation Examples

```php
// Validate postal codes
$isValid = $address->validatePostalCode('400001', 'India'); // true
$isValid = $address->validatePostalCode('12345', 'USA');    // true
$isValid = $address->validatePostalCode('SW1A 1AA', 'UK');  // true

// Validate phone numbers
$isValid = $address->validatePhoneNumber('9876543210');     // true
$isValid = $address->validatePhoneNumber('+919876543210'); // true
$isValid = $address->validatePhoneNumber('123456');        // false
```

### Formatted Address

```php
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
/*
Output:
John Doe
123 Main Street
Apt 4B
Mumbai, Maharashtra, 400001
Phone: 9876543210
*/
```

## Error Handling

### Validation Errors
- Thrown as `Exception` with HTTP status code 400
- Contains detailed validation error messages
- Example: "Validation failed: First name is required, Invalid postal code format for India"

### Authorization Errors
- Thrown as `Exception` with HTTP status code 403
- Prevents users from modifying other users' addresses
- Example: "Unauthorized to delete this address"

### Not Found Errors
- Thrown as `Exception` with HTTP status code 404
- When trying to access non-existent addresses
- Example: "Address not found"

### Business Logic Errors
- Thrown as `Exception` with HTTP status code 400
- Prevents deletion of addresses in use by orders
- Example: "Cannot delete address that is used in orders"

## Security Features

### User Isolation
- Users can only access their own addresses
- Authorization checks on all modification operations
- User ID validation on all operations

### Input Sanitization
- All input data is validated and sanitized
- SQL injection prevention through prepared statements
- XSS prevention through proper data handling

### Data Integrity
- Transaction support for multi-step operations
- Foreign key constraints ensure data consistency
- Default address management prevents multiple defaults

## Integration

### Order Model Integration
- Addresses are linked to orders via foreign keys
- `shipping_address_id` and `billing_address_id` in orders table
- Address deletion checks for existing order references

### User Model Integration
- Addresses belong to users via `user_id` foreign key
- Cascade deletion when user is deleted
- User authentication required for address operations

## Testing

### Unit Tests
- Comprehensive test coverage for all methods
- Validation testing for all input scenarios
- Error condition testing
- Edge case handling

### Property-Based Tests
- Random data generation for validation testing
- Cross-validation of CRUD operations
- Default address management consistency
- User isolation verification

## Performance Considerations

### Database Indexes
- Index on `user_id` for efficient user address queries
- Composite index on `(user_id, is_default)` for default address queries
- Primary key index for direct address access

### Query Optimization
- Efficient queries with proper WHERE clauses
- Minimal data transfer with selective field retrieval
- Pagination support for large address lists

### Caching Opportunities
- Default address caching per user
- Address validation result caching
- Formatted address string caching

## API Response Format

### Single Address Response
```json
{
    "id": 1,
    "user_id": 1,
    "type": "home",
    "first_name": "John",
    "last_name": "Doe",
    "address_line1": "123 Main Street",
    "address_line2": "Apt 4B",
    "city": "Mumbai",
    "state": "Maharashtra",
    "postal_code": "400001",
    "country": "India",
    "phone": "+919876543210",
    "is_default": true,
    "formatted_address": "John Doe\n123 Main Street\nApt 4B\nMumbai, Maharashtra, 400001\nPhone: +919876543210",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-01-15 10:30:00"
}
```

### Multiple Addresses Response
```json
[
    {
        "id": 1,
        "user_id": 1,
        "type": "home",
        "is_default": true,
        // ... other fields
    },
    {
        "id": 2,
        "user_id": 1,
        "type": "work",
        "is_default": false,
        // ... other fields
    }
]
```

## Future Enhancements

### Planned Features
1. **Address Verification**: Integration with postal service APIs
2. **Geocoding**: Latitude/longitude coordinates for addresses
3. **Address Suggestions**: Auto-complete functionality
4. **International Support**: Extended country-specific validation
5. **Address History**: Track address changes over time

### Scalability Considerations
1. **Caching Layer**: Redis caching for frequently accessed addresses
2. **Database Sharding**: Partition addresses by user ID ranges
3. **API Rate Limiting**: Prevent abuse of address operations
4. **Audit Logging**: Track all address modifications

## Conclusion

The Address model provides a robust, secure, and scalable solution for address management in the Riya Collections e-commerce platform. It maintains compatibility with the existing Node.js backend while providing enhanced validation, formatting, and security features suitable for a PHP hosting environment.