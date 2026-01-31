# User Model Implementation

## Overview

This document describes the implementation of the User model with comprehensive CRUD operations for the Riya Collections PHP backend. The implementation maintains API compatibility with the existing Node.js backend while providing enhanced security, validation, and error handling.

## Requirements Fulfilled

- **Requirement 3.1**: User registration and authentication system
- **Requirement 3.2**: Password hashing and validation
- **Requirement 16.1**: Input validation and sanitization

## Implementation Details

### Core Features

1. **Complete CRUD Operations**
   - Create users with validation
   - Read users by ID or email
   - Update user profiles
   - Soft delete users (maintains data integrity)

2. **Email Uniqueness Validation**
   - Database-level uniqueness constraints
   - Application-level validation
   - Proper error handling for conflicts

3. **Password Security**
   - Strong password requirements (8+ chars, uppercase, lowercase, numbers, special chars)
   - bcrypt hashing with cost factor 12
   - Password update functionality

4. **Data Validation and Sanitization**
   - Email format validation
   - Phone number formatting
   - Input length validation
   - XSS prevention through data sanitization

5. **Role-Based Access Control**
   - Customer and admin roles
   - Role validation and updates

### File Structure

```
htdocs/
├── models/
│   └── User.php                    # Main User model class
├── tests/
│   ├── UserTest.php               # Comprehensive unit tests
│   ├── UserPropertyTest.php       # Property-based tests (full DB)
│   ├── UserSimpleTest.php         # Simple validation tests
│   ├── UserValidationTest.php     # Validation logic tests
│   └── UserValidationPropertyTest.php # Property-based validation tests
└── docs/
    └── User_Model_Implementation.md # This documentation
```

## User Model Class

### Constructor and Setup

```php
class User extends DatabaseModel {
    public function __construct() {
        parent::__construct('users');
        $this->setPrimaryKey('id');
        $this->db = Database::getInstance();
    }
}
```

### Core Methods

#### Create User
```php
public function createUser($userData)
```
- Validates all input data
- Checks email uniqueness
- Hashes password securely
- Returns sanitized user data

#### Read Operations
```php
public function getUserById($userId)
public function getUserByEmail($email)
public function getUsers($filters = [], $page = 1, $perPage = 20)
```
- Retrieves users with proper filtering
- Supports pagination
- Returns sanitized data only

#### Update Operations
```php
public function updateUser($userId, $updateData)
public function updatePassword($userId, $newPassword)
public function updateUserRole($userId, $role)
public function setUserStatus($userId, $isActive)
```
- Validates update data
- Maintains data integrity
- Proper error handling

#### Delete Operations
```php
public function deleteUser($userId)
```
- Soft delete implementation
- Prevents accidental data loss
- Maintains referential integrity

### Validation Methods

#### Email Validation
- Format validation using `filter_var()`
- Length validation (max 255 characters)
- Uniqueness checking with optional exclusion

#### Password Validation
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

#### Data Sanitization
- Email normalization (lowercase, trimmed)
- Phone number formatting
- Name trimming
- Type casting for API responses

## Database Schema

The User model works with the following database table structure:

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('customer', 'admin') DEFAULT 'customer',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);
```

## API Response Format

All User model methods return data in a consistent format:

```json
{
    "id": 123,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "phone": "+1234567890",
    "role": "customer",
    "is_active": true,
    "email_verified_at": null,
    "created_at": "2024-01-01 00:00:00",
    "updated_at": "2024-01-01 00:00:00",
    "last_login_at": null
}
```

**Note**: Sensitive fields like `password_hash` are never included in API responses.

## Error Handling

The User model implements comprehensive error handling:

- **400 Bad Request**: Validation errors, invalid input
- **404 Not Found**: User not found
- **409 Conflict**: Email already exists
- **500 Internal Server Error**: Database or system errors

All errors include descriptive messages for debugging while maintaining security.

## Testing Strategy

### Unit Tests (`UserTest.php`)
- Tests all CRUD operations
- Validates error conditions
- Checks data integrity
- Covers edge cases

### Property-Based Tests (`UserValidationPropertyTest.php`)
- Tests validation logic across random inputs
- Verifies universal properties
- Ensures robustness against edge cases
- Runs 200+ test iterations

### Test Coverage
- ✅ Email validation (valid/invalid formats)
- ✅ Password strength requirements
- ✅ Required field validation
- ✅ Data sanitization
- ✅ Phone number formatting
- ✅ Role validation
- ✅ Field length limits
- ✅ CRUD operation consistency
- ✅ Error handling scenarios

## Integration with Existing Systems

### Database Integration
- Extends `DatabaseModel` class
- Uses existing `Database` singleton
- Supports transactions
- Proper connection handling

### Authentication Service Integration
- Compatible with existing `AuthService`
- Supports JWT token generation
- Password hashing compatibility
- Session management support

### Logging Integration
- Uses existing `Logger` class
- Logs security events
- Performance monitoring
- Error tracking

## Security Features

1. **SQL Injection Prevention**
   - Prepared statements for all queries
   - Parameter binding with type detection
   - Query validation

2. **Password Security**
   - bcrypt hashing with high cost factor
   - Strong password requirements
   - Secure password updates

3. **Data Sanitization**
   - Input validation and cleaning
   - XSS prevention
   - Type casting for outputs

4. **Access Control**
   - Role-based permissions
   - Soft delete for data protection
   - Audit logging

## Performance Considerations

1. **Database Optimization**
   - Proper indexing on frequently queried fields
   - Pagination for large datasets
   - Efficient query patterns

2. **Caching Strategy**
   - Leverages existing database query cache
   - Minimal database calls
   - Optimized data retrieval

3. **Memory Management**
   - Efficient data structures
   - Proper resource cleanup
   - Minimal memory footprint

## Usage Examples

### Creating a User
```php
$user = new User();
$userData = [
    'email' => 'john@example.com',
    'password' => 'SecurePass123!',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'phone' => '+1234567890'
];

try {
    $newUser = $user->createUser($userData);
    echo "User created with ID: " . $newUser['id'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Updating a User
```php
$user = new User();
$updateData = [
    'first_name' => 'Jane',
    'phone' => '+9876543210'
];

try {
    $updatedUser = $user->updateUser(123, $updateData);
    echo "User updated successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Getting Users with Pagination
```php
$user = new User();
$filters = ['search' => 'john', 'role' => 'customer'];

try {
    $result = $user->getUsers($filters, 1, 20);
    echo "Found " . count($result['users']) . " users";
    echo "Total pages: " . $result['pagination']['total_pages'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Future Enhancements

1. **Email Verification**
   - Email verification tokens
   - Verification workflow
   - Resend verification emails

2. **Advanced Search**
   - Full-text search capabilities
   - Advanced filtering options
   - Search result ranking

3. **User Preferences**
   - User settings management
   - Preference storage
   - Customization options

4. **Audit Trail**
   - User activity logging
   - Change history tracking
   - Compliance reporting

## Conclusion

The User model implementation provides a robust, secure, and scalable foundation for user management in the Riya Collections PHP backend. It maintains compatibility with the existing Node.js system while providing enhanced security and validation features. The comprehensive testing ensures reliability and correctness across various scenarios and edge cases.