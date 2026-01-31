# Design Document

## Overview

This design document outlines the comprehensive architecture for converting the Riya Collections e-commerce backend from Node.js/Express to pure PHP while maintaining all existing functionality, security standards, and performance characteristics. The design focuses on creating a robust, scalable PHP backend that can run on standard hosting environments like InfinityFree without requiring Node.js dependencies.

The conversion strategy emphasizes maintaining API compatibility, preserving data integrity, and implementing modern PHP security practices while ensuring the system remains maintainable and extensible.

## Architecture

### High-Level Architecture

The PHP backend follows a modular, layered architecture pattern:

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend Layer                           │
│              (HTML/CSS/JavaScript)                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     API Gateway                             │
│                  (index.php + .htaccess)                   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                  Controller Layer                           │
│           (Route handlers + Business logic)                 │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   Service Layer                             │
│        (Authentication, Payments, Email, etc.)             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   Data Access Layer                         │
│              (PDO + Database abstraction)                   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    MySQL Database                           │
│              (Existing schema preserved)                    │
└─────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
htdocs/
├── index.php                    # Main entry point
├── .htaccess                    # URL rewriting and security
├── config/
│   ├── database.php            # Database configuration
│   ├── jwt.php                 # JWT configuration
│   ├── email.php               # Email configuration
│   ├── razorpay.php            # Payment configuration
│   └── security.php            # Security settings
├── controllers/
│   ├── AuthController.php      # Authentication endpoints
│   ├── ProductController.php   # Product management
│   ├── OrderController.php     # Order processing
│   ├── PaymentController.php   # Payment handling
│   ├── AdminController.php     # Admin operations
│   └── BaseController.php      # Common functionality
├── services/
│   ├── AuthService.php         # Authentication logic
│   ├── PaymentService.php      # Payment processing
│   ├── EmailService.php        # Email operations
│   ├── ImageService.php        # Image processing
│   └── ValidationService.php   # Input validation
├── middleware/
│   ├── AuthMiddleware.php      # Authentication checks
│   ├── CorsMiddleware.php      # CORS handling
│   ├── SecurityMiddleware.php  # Security headers
│   └── ValidationMiddleware.php # Input validation
├── models/
│   ├── Database.php            # Database connection
│   ├── User.php                # User model
│   ├── Product.php             # Product model
│   ├── Order.php               # Order model
│   └── Payment.php             # Payment model
├── utils/
│   ├── Response.php            # API response formatting
│   ├── Logger.php              # Logging utility
│   ├── Validator.php           # Validation helpers
│   └── Security.php            # Security utilities
├── uploads/                    # File uploads
│   ├── products/               # Product images
│   └── temp/                   # Temporary files
└── logs/                       # Application logs
    ├── app.log                 # General logs
    ├── error.log               # Error logs
    └── security.log            # Security events
```

## Components and Interfaces

### Core Components

#### 1. API Gateway (index.php)

The main entry point that handles all incoming requests and routes them to appropriate controllers.

```php
<?php
// Main API router
require_once 'config/database.php';
require_once 'middleware/CorsMiddleware.php';
require_once 'middleware/SecurityMiddleware.php';
require_once 'utils/Response.php';

// Apply global middleware
CorsMiddleware::handle();
SecurityMiddleware::handle();

// Route parsing
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Route to appropriate controller
$router = new Router();
$router->route($path, $request_method);
```

#### 2. Database Layer (models/Database.php)

Provides secure database connectivity using PDO with prepared statements.

```php
<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $config = require 'config/database.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $this->connection = new PDO($dsn, $config['username'], $config['password'], $options);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function executeQuery($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}
```

#### 3. Authentication Service (services/AuthService.php)

Handles JWT token generation, validation, and user authentication.

```php
<?php
require_once 'vendor/firebase/php-jwt/src/JWT.php';
require_once 'vendor/firebase/php-jwt/src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService {
    private $jwtSecret;
    private $jwtExpiry;
    
    public function __construct() {
        $config = require 'config/jwt.php';
        $this->jwtSecret = $config['secret'];
        $this->jwtExpiry = $config['expiry'];
    }
    
    public function generateToken($payload) {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->jwtExpiry;
        
        $token = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'iss' => 'riya-collections',
            'data' => $payload
        ];
        
        return JWT::encode($token, $this->jwtSecret, 'HS256');
    }
    
    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return $decoded->data;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
```

#### 4. Payment Service (services/PaymentService.php)

Integrates with Razorpay for payment processing and webhook handling.

```php
<?php
require_once 'vendor/razorpay/razorpay/Razorpay.php';

use Razorpay\Api\Api;

class PaymentService {
    private $razorpay;
    private $keyId;
    private $keySecret;
    
    public function __construct() {
        $config = require 'config/razorpay.php';
        $this->keyId = $config['key_id'];
        $this->keySecret = $config['key_secret'];
        $this->razorpay = new Api($this->keyId, $this->keySecret);
    }
    
    public function createOrder($amount, $currency = 'INR', $receipt = null) {
        $orderData = [
            'amount' => $amount * 100, // Convert to paise
            'currency' => $currency,
            'receipt' => $receipt ?: 'order_' . time(),
        ];
        
        return $this->razorpay->order->create($orderData);
    }
    
    public function verifyPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature) {
        $attributes = [
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_signature' => $razorpaySignature
        ];
        
        try {
            $this->razorpay->utility->verifyPaymentSignature($attributes);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function verifyWebhookSignature($payload, $signature, $secret) {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}
```

### Interface Specifications

#### API Response Format

All API responses follow a consistent JSON structure:

```json
{
    "success": boolean,
    "message": string,
    "data": object|array|null,
    "errors": array|null,
    "pagination": object|null
}
```

#### Authentication Headers

```
Authorization: Bearer <jwt_token>
Content-Type: application/json
```

#### Error Response Format

```json
{
    "success": false,
    "message": "Error description",
    "errors": [
        {
            "field": "field_name",
            "message": "Validation error message"
        }
    ]
}
```

## Data Models

### User Model

```php
<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($userData) {
        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, phone) 
                VALUES (?, ?, ?, ?, ?)";
        
        $params = [
            $userData['email'],
            password_hash($userData['password'], PASSWORD_BCRYPT),
            $userData['first_name'],
            $userData['last_name'],
            $userData['phone'] ?? null
        ];
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->db->executeQuery($sql, [$email]);
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $sql = "SELECT id, email, first_name, last_name, phone, created_at 
                FROM users WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function update($id, $userData) {
        $fields = [];
        $params = [];
        
        foreach ($userData as $field => $value) {
            if (in_array($field, ['first_name', 'last_name', 'phone'])) {
                $fields[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    }
}
```

### Product Model

```php
<?php
class Product {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT p.*, c.name as category_name, pi.image_url as primary_image
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                WHERE p.is_active = 1";
        
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['min_price'])) {
            $sql .= " AND p.price >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND p.price <= ?";
            $params[] = $filters['max_price'];
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function findById($id) {
        $sql = "SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.is_active = 1";
        
        $stmt = $this->db->executeQuery($sql, [$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Get product images
            $imagesSql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC";
            $imagesStmt = $this->db->executeQuery($imagesSql, [$id]);
            $product['images'] = $imagesStmt->fetchAll();
        }
        
        return $product;
    }
    
    public function create($productData) {
        $sql = "INSERT INTO products (name, description, price, stock_quantity, category_id, brand, sku)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $productData['name'],
            $productData['description'] ?? null,
            $productData['price'],
            $productData['stock_quantity'],
            $productData['category_id'] ?? null,
            $productData['brand'] ?? null,
            $productData['sku'] ?? null
        ];
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    public function updateStock($id, $quantity) {
        $sql = "UPDATE products SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$quantity, $id]);
        return $stmt->rowCount() > 0;
    }
}
```

### Order Model

```php
<?php
class Order {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($orderData) {
        try {
            $this->db->beginTransaction();
            
            // Create order
            $orderSql = "INSERT INTO orders (user_id, order_number, total_amount, shipping_address_id, payment_method)
                         VALUES (?, ?, ?, ?, ?)";
            
            $orderParams = [
                $orderData['user_id'],
                $this->generateOrderNumber(),
                $orderData['total_amount'],
                $orderData['shipping_address_id'],
                $orderData['payment_method']
            ];
            
            $this->db->executeQuery($orderSql, $orderParams);
            $orderId = $this->db->getConnection()->lastInsertId();
            
            // Create order items
            foreach ($orderData['items'] as $item) {
                $itemSql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                           VALUES (?, ?, ?, ?, ?)";
                
                $itemParams = [
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ];
                
                $this->db->executeQuery($itemSql, $itemParams);
            }
            
            // Create payment record
            $paymentSql = "INSERT INTO payments (order_id, payment_method, amount)
                          VALUES (?, ?, ?)";
            
            $paymentParams = [
                $orderId,
                $orderData['payment_method'],
                $orderData['total_amount']
            ];
            
            $this->db->executeQuery($paymentSql, $paymentParams);
            
            $this->db->commit();
            return $orderId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function findByUser($userId, $limit = 20, $offset = 0) {
        $sql = "SELECT o.*, a.address_line1, a.city, a.state
                FROM orders o
                LEFT JOIN addresses a ON o.shipping_address_id = a.id
                WHERE o.user_id = ?
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->executeQuery($sql, [$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public function findById($id) {
        $sql = "SELECT o.*, a.*, u.first_name, u.last_name, u.email
                FROM orders o
                LEFT JOIN addresses a ON o.shipping_address_id = a.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ?";
        
        $stmt = $this->db->executeQuery($sql, [$id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Get order items
            $itemsSql = "SELECT oi.*, p.name as product_name, p.brand
                        FROM order_items oi
                        LEFT JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?";
            
            $itemsStmt = $this->db->executeQuery($itemsSql, [$id]);
            $order['items'] = $itemsStmt->fetchAll();
        }
        
        return $order;
    }
    
    public function updateStatus($id, $status) {
        $sql = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$status, $id]);
        return $stmt->rowCount() > 0;
    }
    
    private function generateOrderNumber() {
        return 'RC' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
```

Now I need to use the prework tool to analyze the acceptance criteria before writing the correctness properties.

<function_calls>
<invoke name="prework">
<parameter name="featureName">riya-collections-php-conversion

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Based on the prework analysis, the following correctness properties ensure the PHP backend maintains functional equivalence with the Node.js backend while meeting all security and performance requirements:

### Property 1: API Response Compatibility
*For any* valid API request that works on the Node backend, the PHP backend should return a response with identical structure, data types, and HTTP status codes
**Validates: Requirements 1.3, 4.2**

### Property 2: Database Schema Compatibility  
*For any* database operation that works with the existing schema, the PHP backend should execute successfully without requiring schema modifications
**Validates: Requirements 2.1, 2.3**

### Property 3: SQL Injection Prevention
*For any* user input containing malicious SQL code, the PHP backend should sanitize it through prepared statements and prevent database compromise
**Validates: Requirements 2.2, 10.2**

### Property 4: Authentication Token Compatibility
*For any* valid JWT token generated by either system, both the Node and PHP backends should be able to validate and extract the same user information
**Validates: Requirements 3.1**

### Property 5: Password Hash Compatibility
*For any* password hashed by one system, the other system should be able to verify it using the same bcrypt algorithm and cost factor
**Validates: Requirements 3.2**

### Property 6: API Endpoint Completeness
*For any* API endpoint available in the Node backend, an equivalent endpoint with the same path and HTTP method should exist in the PHP backend
**Validates: Requirements 4.1**

### Property 7: Product CRUD Operations
*For any* valid product data, all CRUD operations (create, read, update, delete) should work identically between both systems
**Validates: Requirements 5.1**

### Property 8: Product Query Consistency
*For any* combination of search, filter, sort, and pagination parameters, both systems should return equivalent product results
**Validates: Requirements 5.2**

### Property 9: Order Workflow Completeness
*For any* valid order data, the complete workflow from creation to delivery tracking should function identically in both systems
**Validates: Requirements 6.1**

### Property 10: Order Number Uniqueness
*For any* sequence of order creation requests, all generated order numbers should be unique and follow the same format pattern
**Validates: Requirements 6.2**

### Property 11: Payment Processing Compatibility
*For any* valid payment request, the Razorpay integration should process it identically regardless of whether it originates from Node or PHP backend
**Validates: Requirements 7.1**

### Property 12: Payment Signature Verification
*For any* payment signature received from Razorpay, the verification process should produce the same result in both systems
**Validates: Requirements 7.2**

### Property 13: File Upload Validation
*For any* uploaded file, the validation rules for size, format, and security should produce identical results in both systems
**Validates: Requirements 8.1**

### Property 14: Image Processing Consistency
*For any* uploaded image, the resizing and optimization should produce equivalent output files with the same dimensions and quality
**Validates: Requirements 8.2**

### Property 15: Email Delivery Reliability
*For any* email trigger event, the PHP backend should send emails with the same content, formatting, and delivery success rate as the Node backend
**Validates: Requirements 9.1**

### Property 16: Input Validation Consistency
*For any* user input that fails validation in one system, it should fail with equivalent error messages in the other system
**Validates: Requirements 10.1, 16.1**

### Property 17: Admin Operation Authorization
*For any* admin operation, the authorization checks should produce identical results regardless of which system processes the request
**Validates: Requirements 11.1**

### Property 18: Database Query Performance
*For any* database query, the execution time should be within acceptable performance thresholds when proper indexing is applied
**Validates: Requirements 12.1**

### Property 19: Error Response Consistency
*For any* error condition, both systems should return the same HTTP status code and error message structure
**Validates: Requirements 13.1**

### Property 20: Session Token Security
*For any* session token, the generation, validation, and expiration mechanisms should maintain the same security properties in both systems
**Validates: Requirements 17.1**

### Property 21: Real-time Update Equivalence
*For any* real-time feature requirement, the polling-based PHP implementation should provide equivalent functionality to the WebSocket-based Node implementation
**Validates: Requirements 18.1**

### Property 22: Backup Data Integrity
*For any* database backup created by the PHP system, restoring it should result in identical data to the original database state
**Validates: Requirements 19.1**

### Property 23: Health Check Accuracy
*For any* system component, the health check should accurately reflect its operational status and connectivity
**Validates: Requirements 20.1**

## Error Handling

### Error Classification

The PHP backend implements a comprehensive error handling system with the following classifications:

1. **Validation Errors (400)**: Input validation failures, malformed requests
2. **Authentication Errors (401)**: Invalid credentials, expired tokens
3. **Authorization Errors (403)**: Insufficient permissions, role-based access violations
4. **Not Found Errors (404)**: Resource not found, invalid endpoints
5. **Conflict Errors (409)**: Duplicate resources, constraint violations
6. **Server Errors (500)**: Database failures, external service errors
7. **Service Unavailable (503)**: Temporary service outages, maintenance mode

### Error Response Structure

```php
<?php
class ErrorHandler {
    public static function handleException($exception) {
        $statusCode = $exception->getCode() ?: 500;
        $message = $exception->getMessage();
        
        // Log error details
        Logger::error('Exception occurred', [
            'message' => $message,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Return user-friendly error response
        Response::error($message, $statusCode);
    }
    
    public static function handleValidationErrors($errors) {
        Response::json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ], 400);
    }
    
    public static function handleDatabaseError($error) {
        Logger::error('Database error', ['error' => $error]);
        
        // Don't expose database details in production
        $message = (ENV === 'development') ? $error : 'Database operation failed';
        Response::error($message, 500);
    }
}
```

### Logging Strategy

```php
<?php
class Logger {
    private static $logFile = 'logs/app.log';
    private static $errorFile = 'logs/error.log';
    private static $securityFile = 'logs/security.log';
    
    public static function info($message, $context = []) {
        self::writeLog('INFO', $message, $context, self::$logFile);
    }
    
    public static function error($message, $context = []) {
        self::writeLog('ERROR', $message, $context, self::$errorFile);
    }
    
    public static function security($message, $context = []) {
        self::writeLog('SECURITY', $message, $context, self::$securityFile);
    }
    
    private static function writeLog($level, $message, $context, $file) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logEntry = "[$timestamp] $level: $message $contextStr" . PHP_EOL;
        
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
```

## Testing Strategy

### Dual Testing Approach

The testing strategy employs both unit testing and property-based testing to ensure comprehensive coverage:

**Unit Testing**: Validates specific examples, edge cases, and error conditions using PHPUnit
**Property-Based Testing**: Verifies universal properties across all inputs using random data generation

### Property-Based Testing Configuration

Each property test runs a minimum of 100 iterations with randomly generated test data to ensure comprehensive coverage. Tests are tagged with references to their corresponding design properties:

```php
<?php
use PHPUnit\Framework\TestCase;

class APICompatibilityTest extends TestCase {
    /**
     * Feature: riya-collections-php-conversion, Property 1: API Response Compatibility
     * @test
     */
    public function testAPIResponseCompatibility() {
        for ($i = 0; $i < 100; $i++) {
            $endpoint = $this->generateRandomEndpoint();
            $params = $this->generateRandomParams();
            
            $nodeResponse = $this->callNodeAPI($endpoint, $params);
            $phpResponse = $this->callPHPAPI($endpoint, $params);
            
            $this->assertEquals($nodeResponse['structure'], $phpResponse['structure']);
            $this->assertEquals($nodeResponse['status'], $phpResponse['status']);
        }
    }
    
    /**
     * Feature: riya-collections-php-conversion, Property 3: SQL Injection Prevention
     * @test
     */
    public function testSQLInjectionPrevention() {
        for ($i = 0; $i < 100; $i++) {
            $maliciousInput = $this->generateSQLInjectionPayload();
            
            $response = $this->callPHPAPI('/api/products', ['search' => $maliciousInput]);
            
            // Should not return database error or expose schema
            $this->assertNotContains('mysql', strtolower($response['body']));
            $this->assertNotContains('select', strtolower($response['body']));
            $this->assertNotEquals(500, $response['status']);
        }
    }
}
```

### Unit Testing Focus Areas

Unit tests concentrate on:
- Specific business logic validation
- Edge cases and boundary conditions  
- Error handling scenarios
- Integration points between components
- Security validation for known attack vectors

### Testing Tools and Libraries

- **PHPUnit**: Primary testing framework for unit and integration tests
- **Faker**: Random data generation for property-based testing
- **Mockery**: Mocking external dependencies (Razorpay, email services)
- **Database Testing**: In-memory SQLite for fast test execution
- **API Testing**: HTTP client for endpoint validation

### Continuous Integration

```yaml
# .github/workflows/test.yml
name: PHP Backend Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: test
          MYSQL_DATABASE: riya_collections_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s
    
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: pdo, pdo_mysql, gd, curl
      
      - name: Install dependencies
        run: composer install
      
      - name: Run property tests
        run: vendor/bin/phpunit --group=property --testdox
      
      - name: Run unit tests  
        run: vendor/bin/phpunit --group=unit --coverage-html coverage
      
      - name: Security scan
        run: vendor/bin/psalm --show-info=false
```

This comprehensive design ensures the PHP backend maintains full compatibility with the existing Node.js system while providing enhanced security, performance, and maintainability for standard PHP hosting environments.