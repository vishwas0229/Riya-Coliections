# AuthService Implementation Documentation

## Overview

The AuthService provides comprehensive authentication functionality for the Riya Collections PHP backend, including JWT token handling, password hashing, user authentication, and session management. It maintains full compatibility with the existing Node.js implementation while providing enhanced security features.

## Requirements Fulfilled

- **Requirement 3.1**: JWT token-based authentication compatible with existing token format
- **Requirement 3.2**: Bcrypt password hashing compatibility with Node.js implementation  
- **Requirement 17.1**: Secure session management with token expiration and refresh mechanisms

## Core Components

### 1. JWT Service (`JWTService`)

Handles JWT token generation, validation, and management:

```php
$jwtService = new JWTService();

// Generate token pair
$tokens = $jwtService->generateTokenPair([
    'user_id' => 123,
    'email' => 'user@example.com',
    'role' => 'customer'
]);

// Verify tokens
$payload = $jwtService->verifyAccessToken($tokens['access_token']);
$refreshPayload = $jwtService->verifyRefreshToken($tokens['refresh_token']);
```

**Features:**
- Access token generation with configurable expiration
- Refresh token generation for secure token renewal
- Token validation with signature verification
- Automatic expiration checking
- Compatible with Node.js jsonwebtoken library

### 2. Password Hashing (`PasswordHash`)

Provides bcrypt password hashing compatible with Node.js:

```php
// Hash password
$hash = PasswordHash::hash('MyPassword123!');

// Verify password
$isValid = PasswordHash::verify('MyPassword123!', $hash);

// Check if rehashing needed
$needsRehash = PasswordHash::needsRehash($hash);
```

**Features:**
- Bcrypt hashing with configurable cost factor
- Compatible with Node.js bcrypt hashes ($2a$, $2b$, $2y$ variants)
- Automatic salt generation for unique hashes
- Rehashing detection for security updates

### 3. AuthService (`AuthService`)

Main authentication service providing complete user management:

```php
$authService = new AuthService();

// User registration
$result = $authService->register([
    'email' => 'user@example.com',
    'password' => 'SecurePass123!',
    'first_name' => 'John',
    'last_name' => 'Doe'
]);

// User login
$result = $authService->login('user@example.com', 'SecurePass123!');

// Token refresh
$newTokens = $authService->refreshToken($refreshToken);
```

**Features:**
- User registration with validation
- Secure login with password verification
- Token refresh mechanism
- Password change functionality
- Password reset workflow
- Session management
- Security logging

### 4. AuthMiddleware (`AuthMiddleware`)

Provides authentication and authorization middleware:

```php
// Require authentication
$user = AuthMiddleware::requireAuth();

// Require specific role
$admin = AuthMiddleware::requireRole('admin');

// Check permissions
$canAccess = AuthMiddleware::canAccessResource($resourceUserId);
```

**Features:**
- Route protection
- Role-based access control
- Rate limiting
- CSRF protection
- Security headers
- Authentication logging

## Security Features

### 1. Token Security

- **Signature Verification**: All tokens are cryptographically signed
- **Expiration Checking**: Automatic token expiration validation
- **Unique JTI**: Each token includes a unique identifier
- **Secure Storage**: Refresh tokens stored in database with expiration

### 2. Password Security

- **Strong Hashing**: Bcrypt with configurable cost factor (default: 12)
- **Salt Generation**: Automatic unique salt for each password
- **Validation Rules**: Enforced password complexity requirements
- **Rehashing**: Automatic rehashing when cost factor changes

### 3. Session Security

- **Token Rotation**: Refresh tokens are rotated on use
- **Session Cleanup**: Automatic cleanup of expired tokens
- **Multiple Sessions**: Support for multiple active sessions per user
- **Logout Protection**: Secure token invalidation

### 4. Input Validation

- **Email Validation**: RFC-compliant email format checking
- **Password Strength**: Enforced complexity requirements
- **SQL Injection Prevention**: Prepared statements for all queries
- **XSS Prevention**: Input sanitization and output encoding

## Database Schema

### Users Table
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL
);
```

### Refresh Tokens Table
```sql
CREATE TABLE refresh_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token TEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Password Resets Table
```sql
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## API Endpoints

### Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | User registration |
| POST | `/api/auth/login` | User login |
| POST | `/api/auth/refresh` | Token refresh |
| GET | `/api/auth/profile` | Get user profile |
| PUT | `/api/auth/profile` | Update user profile |
| POST | `/api/auth/change-password` | Change password |
| POST | `/api/auth/forgot-password` | Initiate password reset |
| POST | `/api/auth/reset-password` | Complete password reset |
| POST | `/api/auth/logout` | User logout |
| GET | `/api/auth/sessions` | Get user sessions |
| GET | `/api/auth/verify` | Verify token |

### Request/Response Examples

#### Registration
```json
POST /api/auth/register
{
    "email": "user@example.com",
    "password": "SecurePass123!",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+1234567890"
}

Response:
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 123,
            "email": "user@example.com",
            "first_name": "John",
            "last_name": "Doe",
            "role": "customer"
        },
        "tokens": {
            "access_token": "eyJ0eXAiOiJKV1Q...",
            "refresh_token": "eyJ0eXAiOiJKV1Q...",
            "token_type": "Bearer",
            "expires_in": 86400
        }
    }
}
```

#### Login
```json
POST /api/auth/login
{
    "email": "user@example.com",
    "password": "SecurePass123!"
}

Response:
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": { ... },
        "tokens": { ... }
    }
}
```

## Configuration

### Environment Variables

```env
# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key-change-in-production
JWT_EXPIRES_IN=24h
JWT_REFRESH_SECRET=your-super-secret-refresh-key-change-in-production
JWT_REFRESH_EXPIRES_IN=7d
JWT_ISSUER=riya-collections
JWT_AUDIENCE=riya-collections-users

# Password Hashing
BCRYPT_SALT_ROUNDS=12

# Database
DB_HOST=localhost
DB_NAME=riya_collections
DB_USER=root
DB_PASSWORD=
```

### Security Recommendations

1. **JWT Secrets**: Use cryptographically secure random strings (minimum 32 characters)
2. **HTTPS**: Always use HTTPS in production
3. **Token Expiration**: Keep access token expiration short (15-60 minutes)
4. **Refresh Tokens**: Implement secure refresh token rotation
5. **Rate Limiting**: Apply rate limiting to authentication endpoints
6. **Logging**: Monitor authentication events for security analysis

## Testing

### Unit Tests

The implementation includes comprehensive unit tests covering:

- JWT token generation and validation
- Password hashing and verification
- User registration and login
- Token refresh mechanism
- Security validation
- Error handling

### Property-Based Tests

Property-based tests verify universal properties:

- Token roundtrip consistency
- Password hash verification consistency
- Token pair generation consistency
- Registration data sanitization
- Token signature integrity
- Session token uniqueness

### Running Tests

```bash
# Run unit tests
php vendor/bin/phpunit tests/AuthServiceTest.php

# Run property-based tests
php vendor/bin/phpunit tests/AuthServicePropertyTest.php

# Run all authentication tests
php vendor/bin/phpunit tests/Auth*Test.php
```

## Performance Considerations

### Optimization Strategies

1. **Database Indexing**: Proper indexes on email, user_id, and token fields
2. **Token Caching**: Consider Redis for token blacklisting in high-traffic scenarios
3. **Connection Pooling**: Use persistent database connections
4. **Cleanup Jobs**: Regular cleanup of expired tokens and reset requests

### Monitoring

- Track authentication success/failure rates
- Monitor token generation and validation performance
- Log security events for analysis
- Set up alerts for suspicious activity

## Compatibility

### Node.js Compatibility

The PHP implementation maintains full compatibility with the Node.js backend:

- **JWT Tokens**: Same format and validation logic
- **Password Hashes**: Compatible with Node.js bcrypt library
- **API Responses**: Identical JSON structure and status codes
- **Database Schema**: Same table structure and relationships

### Migration Path

1. Deploy PHP backend alongside Node.js backend
2. Gradually migrate endpoints to PHP
3. Existing tokens and password hashes remain valid
4. No user re-authentication required

## Troubleshooting

### Common Issues

1. **Token Verification Fails**: Check JWT secret configuration
2. **Password Verification Fails**: Verify bcrypt cost factor settings
3. **Database Connection Issues**: Check database credentials and connectivity
4. **Permission Errors**: Verify file permissions for logs and uploads

### Debug Mode

Enable debug mode in development:

```env
APP_ENV=development
APP_DEBUG=true
```

This provides detailed error messages and stack traces for troubleshooting.