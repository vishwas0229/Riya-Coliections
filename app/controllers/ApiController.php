<?php
/**
 * API Controller
 * 
 * This controller handles API documentation and utility endpoints
 * for the Riya Collections PHP backend.
 * 
 * Requirements: 15.1, 15.3
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * API Controller Class
 */
class ApiController {
    
    /**
     * Get API documentation
     */
    public function documentation() {
        try {
            $documentation = [
                'api' => [
                    'name' => 'Riya Collections API',
                    'version' => '1.0.0',
                    'description' => 'PHP backend API for Riya Collections e-commerce platform',
                    'base_url' => $this->getBaseUrl(),
                    'documentation_url' => $this->getBaseUrl() . '/api/docs',
                    'testing_url' => $this->getBaseUrl() . '/api/test'
                ],
                'authentication' => [
                    'type' => 'Bearer Token (JWT)',
                    'header' => 'Authorization: Bearer <token>',
                    'login_endpoint' => '/api/auth/login',
                    'register_endpoint' => '/api/auth/register',
                    'token_expiry' => '24 hours',
                    'refresh_endpoint' => '/api/auth/refresh'
                ],
                'endpoints' => $this->getEndpointDocumentation(),
                'response_format' => [
                    'success' => [
                        'success' => true,
                        'message' => 'string',
                        'data' => 'object|array|null',
                        'errors' => null
                    ],
                    'error' => [
                        'success' => false,
                        'message' => 'string',
                        'data' => null,
                        'errors' => 'array|null'
                    ],
                    'paginated' => [
                        'success' => true,
                        'message' => 'string',
                        'data' => 'array',
                        'pagination' => [
                            'current_page' => 'integer',
                            'per_page' => 'integer',
                            'total_items' => 'integer',
                            'total_pages' => 'integer',
                            'has_next_page' => 'boolean',
                            'has_prev_page' => 'boolean',
                            'next_page' => 'integer|null',
                            'prev_page' => 'integer|null'
                        ],
                        'errors' => null
                    ]
                ],
                'status_codes' => [
                    200 => 'OK - Request successful',
                    201 => 'Created - Resource created successfully',
                    400 => 'Bad Request - Invalid request data',
                    401 => 'Unauthorized - Authentication required',
                    403 => 'Forbidden - Access denied',
                    404 => 'Not Found - Resource not found',
                    422 => 'Unprocessable Entity - Validation failed',
                    429 => 'Too Many Requests - Rate limit exceeded',
                    500 => 'Internal Server Error - Server error'
                ],
                'rate_limiting' => [
                    'window' => '15 minutes',
                    'max_requests' => 100,
                    'headers' => [
                        'X-RateLimit-Limit' => 'Maximum requests per window',
                        'X-RateLimit-Remaining' => 'Remaining requests in window',
                        'X-RateLimit-Reset' => 'Window reset time'
                    ]
                ],
                'testing' => [
                    'interactive_tester' => $this->getBaseUrl() . '/api/test',
                    'validation_tools' => $this->getBaseUrl() . '/api/validate',
                    'example_collections' => $this->getTestingCollections()
                ],
                'error_handling' => [
                    'validation_errors' => [
                        'format' => [
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => [
                                [
                                    'field' => 'field_name',
                                    'message' => 'Error description'
                                ]
                            ]
                        ]
                    ],
                    'common_errors' => [
                        'INVALID_TOKEN' => 'JWT token is invalid or expired',
                        'MISSING_FIELD' => 'Required field is missing',
                        'INVALID_FORMAT' => 'Field format is invalid',
                        'RESOURCE_NOT_FOUND' => 'Requested resource does not exist',
                        'PERMISSION_DENIED' => 'Insufficient permissions for this action'
                    ]
                ]
            ];
            
            Response::success('API documentation retrieved', $documentation);
            
        } catch (Exception $e) {
            Logger::error('Failed to generate API documentation', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to generate documentation');
        }
    }
    
    /**
     * Interactive API testing interface
     */
    public function testInterface() {
        try {
            $testInterface = [
                'title' => 'Riya Collections API Testing Interface',
                'description' => 'Interactive tool for testing API endpoints',
                'base_url' => $this->getBaseUrl(),
                'endpoints' => $this->getTestableEndpoints(),
                'authentication' => [
                    'test_user' => [
                        'email' => 'test@example.com',
                        'password' => 'testpassword123',
                        'note' => 'Use these credentials for testing user endpoints'
                    ],
                    'test_admin' => [
                        'email' => 'admin@test.com',
                        'password' => 'adminpassword123',
                        'note' => 'Use these credentials for testing admin endpoints'
                    ]
                ],
                'test_data' => $this->getTestData(),
                'validation_tools' => $this->getValidationTools(),
                'example_requests' => $this->getExampleRequests()
            ];
            
            Response::success('API testing interface loaded', $testInterface);
            
        } catch (Exception $e) {
            Logger::error('Failed to load API testing interface', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to load testing interface');
        }
    }
    
    /**
     * Validate API request format
     */
    public function validateRequest() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON input', 400);
                return;
            }
            
            $endpoint = $input['endpoint'] ?? '';
            $method = $input['method'] ?? '';
            $headers = $input['headers'] ?? [];
            $body = $input['body'] ?? null;
            
            $validation = $this->performRequestValidation($endpoint, $method, $headers, $body);
            
            Response::success('Request validation completed', $validation);
            
        } catch (Exception $e) {
            Logger::error('Request validation failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Validation failed');
        }
    }
    
    /**
     * Execute test request
     */
    public function executeTest() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON input', 400);
                return;
            }
            
            $endpoint = $input['endpoint'] ?? '';
            $method = $input['method'] ?? 'GET';
            $headers = $input['headers'] ?? [];
            $body = $input['body'] ?? null;
            
            $result = $this->executeTestRequest($endpoint, $method, $headers, $body);
            
            Response::success('Test request executed', $result);
            
        } catch (Exception $e) {
            Logger::error('Test execution failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Test execution failed');
        }
    }
    
    /**
     * Get base URL for the API
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . ($path !== '/' ? $path : '');
    }
    
    /**
     * Get comprehensive endpoint documentation
     */
    private function getEndpointDocumentation() {
        return [
            'authentication' => [
                'POST /api/auth/register' => [
                    'description' => 'Register a new user account',
                    'auth_required' => false,
                    'parameters' => [
                        'email' => 'string (required) - User email address',
                        'password' => 'string (required) - User password (min 8 characters)',
                        'first_name' => 'string (required) - User first name',
                        'last_name' => 'string (required) - User last name',
                        'phone' => 'string (optional) - User phone number'
                    ],
                    'example_request' => [
                        'email' => 'user@example.com',
                        'password' => 'securepassword123',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'phone' => '+919876543210'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'User registered successfully',
                        'data' => [
                            'user' => [
                                'id' => 1,
                                'email' => 'user@example.com',
                                'first_name' => 'John',
                                'last_name' => 'Doe',
                                'phone' => '+919876543210'
                            ],
                            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'
                        ],
                        'errors' => null
                    ],
                    'validation_rules' => [
                        'email' => 'Valid email format, unique in system',
                        'password' => 'Minimum 8 characters, at least 1 uppercase, 1 lowercase, 1 number',
                        'first_name' => 'Maximum 50 characters, letters only',
                        'last_name' => 'Maximum 50 characters, letters only',
                        'phone' => 'Valid phone number format with country code'
                    ]
                ],
                'POST /api/auth/login' => [
                    'description' => 'Login with email and password',
                    'auth_required' => false,
                    'parameters' => [
                        'email' => 'string (required) - User email address',
                        'password' => 'string (required) - User password'
                    ],
                    'example_request' => [
                        'email' => 'user@example.com',
                        'password' => 'securepassword123'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Login successful',
                        'data' => [
                            'user' => [
                                'id' => 1,
                                'email' => 'user@example.com',
                                'first_name' => 'John',
                                'last_name' => 'Doe'
                            ],
                            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
                            'expires_at' => '2024-02-01T12:00:00Z'
                        ],
                        'errors' => null
                    ]
                ],
                'GET /api/auth/profile' => [
                    'description' => 'Get current user profile',
                    'auth_required' => true,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Profile retrieved successfully',
                        'data' => [
                            'id' => 1,
                            'email' => 'user@example.com',
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'phone' => '+919876543210',
                            'created_at' => '2024-01-01T10:00:00Z',
                            'updated_at' => '2024-01-15T14:30:00Z'
                        ],
                        'errors' => null
                    ]
                ],
                'PUT /api/auth/profile' => [
                    'description' => 'Update user profile',
                    'auth_required' => true,
                    'parameters' => [
                        'first_name' => 'string (optional) - User first name',
                        'last_name' => 'string (optional) - User last name',
                        'phone' => 'string (optional) - User phone number'
                    ],
                    'example_request' => [
                        'first_name' => 'Jane',
                        'phone' => '+919876543211'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Profile updated successfully',
                        'data' => [
                            'id' => 1,
                            'email' => 'user@example.com',
                            'first_name' => 'Jane',
                            'last_name' => 'Doe',
                            'phone' => '+919876543211'
                        ],
                        'errors' => null
                    ]
                ],
                'POST /api/auth/refresh' => [
                    'description' => 'Refresh JWT token',
                    'auth_required' => true,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Token refreshed successfully',
                        'data' => [
                            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
                            'expires_at' => '2024-02-01T12:00:00Z'
                        ],
                        'errors' => null
                    ]
                ],
                'POST /api/auth/logout' => [
                    'description' => 'Logout and invalidate token',
                    'auth_required' => true,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Logged out successfully',
                        'data' => null,
                        'errors' => null
                    ]
                ]
            ],
            'products' => [
                'GET /api/products' => [
                    'description' => 'Get list of products with filtering and pagination',
                    'auth_required' => false,
                    'parameters' => [
                        'search' => 'string (optional) - Search term for name/description',
                        'category_id' => 'integer (optional) - Filter by category ID',
                        'min_price' => 'number (optional) - Minimum price filter',
                        'max_price' => 'number (optional) - Maximum price filter',
                        'page' => 'integer (optional) - Page number (default: 1)',
                        'limit' => 'integer (optional) - Items per page (default: 20, max: 100)',
                        'sort' => 'string (optional) - Sort field (name, price, created_at)',
                        'order' => 'string (optional) - Sort order (asc, desc)'
                    ],
                    'example_request' => '?search=shirt&category_id=1&min_price=500&max_price=2000&page=1&limit=10',
                    'example_response' => [
                        'success' => true,
                        'message' => 'Products retrieved successfully',
                        'data' => [
                            [
                                'id' => 1,
                                'name' => 'Cotton Shirt',
                                'description' => 'Comfortable cotton shirt',
                                'price' => 1299.99,
                                'stock_quantity' => 50,
                                'category_id' => 1,
                                'category_name' => 'Clothing',
                                'brand' => 'Riya Collections',
                                'sku' => 'RC-SHIRT-001',
                                'primary_image' => '/uploads/products/shirt1.jpg',
                                'created_at' => '2024-01-01T10:00:00Z'
                            ]
                        ],
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => 10,
                            'total_items' => 25,
                            'total_pages' => 3,
                            'has_next_page' => true,
                            'has_prev_page' => false,
                            'next_page' => 2,
                            'prev_page' => null
                        ],
                        'errors' => null
                    ]
                ],
                'GET /api/products/{id}' => [
                    'description' => 'Get product details by ID',
                    'auth_required' => false,
                    'parameters' => [
                        'id' => 'integer (required) - Product ID'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Product retrieved successfully',
                        'data' => [
                            'id' => 1,
                            'name' => 'Cotton Shirt',
                            'description' => 'Comfortable cotton shirt perfect for casual wear',
                            'price' => 1299.99,
                            'stock_quantity' => 50,
                            'category_id' => 1,
                            'category_name' => 'Clothing',
                            'brand' => 'Riya Collections',
                            'sku' => 'RC-SHIRT-001',
                            'images' => [
                                [
                                    'id' => 1,
                                    'image_url' => '/uploads/products/shirt1.jpg',
                                    'is_primary' => true,
                                    'sort_order' => 1
                                ]
                            ],
                            'created_at' => '2024-01-01T10:00:00Z',
                            'updated_at' => '2024-01-15T14:30:00Z'
                        ],
                        'errors' => null
                    ]
                ],
                'POST /api/admin/products' => [
                    'description' => 'Create new product (admin only)',
                    'auth_required' => true,
                    'admin_required' => true,
                    'parameters' => [
                        'name' => 'string (required) - Product name (max 255 chars)',
                        'description' => 'string (optional) - Product description',
                        'price' => 'number (required) - Product price (positive)',
                        'stock_quantity' => 'integer (required) - Stock quantity (non-negative)',
                        'category_id' => 'integer (optional) - Category ID',
                        'brand' => 'string (optional) - Product brand (max 100 chars)',
                        'sku' => 'string (optional) - Product SKU (unique, max 50 chars)'
                    ],
                    'example_request' => [
                        'name' => 'Cotton Shirt',
                        'description' => 'Comfortable cotton shirt',
                        'price' => 1299.99,
                        'stock_quantity' => 50,
                        'category_id' => 1,
                        'brand' => 'Riya Collections',
                        'sku' => 'RC-SHIRT-001'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Product created successfully',
                        'data' => [
                            'id' => 1,
                            'name' => 'Cotton Shirt',
                            'price' => 1299.99,
                            'stock_quantity' => 50
                        ],
                        'errors' => null
                    ]
                ]
            ],
            'categories' => [
                'GET /api/categories' => [
                    'description' => 'Get list of all categories',
                    'auth_required' => false,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Categories retrieved successfully',
                        'data' => [
                            [
                                'id' => 1,
                                'name' => 'Clothing',
                                'description' => 'Fashion and clothing items',
                                'parent_id' => null,
                                'is_active' => true,
                                'product_count' => 25
                            ]
                        ],
                        'errors' => null
                    ]
                ]
            ],
            'orders' => [
                'GET /api/orders' => [
                    'description' => 'Get user orders with pagination',
                    'auth_required' => true,
                    'parameters' => [
                        'page' => 'integer (optional) - Page number (default: 1)',
                        'limit' => 'integer (optional) - Items per page (default: 20)',
                        'status' => 'string (optional) - Filter by order status'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Orders retrieved successfully',
                        'data' => [
                            [
                                'id' => 1,
                                'order_number' => 'RC20240101001',
                                'total_amount' => 2599.98,
                                'status' => 'confirmed',
                                'payment_method' => 'razorpay',
                                'created_at' => '2024-01-01T10:00:00Z',
                                'items_count' => 2
                            ]
                        ],
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => 20,
                            'total_items' => 5,
                            'total_pages' => 1
                        ],
                        'errors' => null
                    ]
                ],
                'POST /api/orders' => [
                    'description' => 'Create new order',
                    'auth_required' => true,
                    'parameters' => [
                        'items' => 'array (required) - Order items with product_id, quantity, unit_price',
                        'shipping_address_id' => 'integer (required) - Shipping address ID',
                        'payment_method' => 'string (required) - Payment method (razorpay|cod)'
                    ],
                    'example_request' => [
                        'items' => [
                            [
                                'product_id' => 1,
                                'quantity' => 2,
                                'unit_price' => 1299.99
                            ]
                        ],
                        'shipping_address_id' => 1,
                        'payment_method' => 'razorpay'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Order created successfully',
                        'data' => [
                            'id' => 1,
                            'order_number' => 'RC20240101001',
                            'total_amount' => 2599.98,
                            'status' => 'pending',
                            'payment_method' => 'razorpay'
                        ],
                        'errors' => null
                    ]
                ]
            ],
            'payments' => [
                'POST /api/payments/razorpay/create' => [
                    'description' => 'Create Razorpay order for payment',
                    'auth_required' => true,
                    'parameters' => [
                        'amount' => 'number (required) - Order amount in INR',
                        'currency' => 'string (optional) - Currency code (default: INR)',
                        'receipt' => 'string (optional) - Receipt identifier'
                    ],
                    'example_request' => [
                        'amount' => 2599.98,
                        'currency' => 'INR'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Razorpay order created successfully',
                        'data' => [
                            'razorpay_order_id' => 'order_123456789',
                            'amount' => 259998,
                            'currency' => 'INR',
                            'key_id' => 'rzp_test_123456'
                        ],
                        'errors' => null
                    ]
                ],
                'POST /api/payments/razorpay/verify' => [
                    'description' => 'Verify Razorpay payment signature',
                    'auth_required' => true,
                    'parameters' => [
                        'razorpay_order_id' => 'string (required) - Razorpay order ID',
                        'razorpay_payment_id' => 'string (required) - Razorpay payment ID',
                        'razorpay_signature' => 'string (required) - Razorpay signature'
                    ],
                    'example_request' => [
                        'razorpay_order_id' => 'order_123456789',
                        'razorpay_payment_id' => 'pay_987654321',
                        'razorpay_signature' => 'signature_hash_here'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Payment verified successfully',
                        'data' => [
                            'payment_id' => 1,
                            'status' => 'completed',
                            'amount' => 2599.98
                        ],
                        'errors' => null
                    ]
                ]
            ],
            'addresses' => [
                'GET /api/addresses' => [
                    'description' => 'Get user addresses',
                    'auth_required' => true,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Addresses retrieved successfully',
                        'data' => [
                            [
                                'id' => 1,
                                'address_line1' => '123 Main Street',
                                'address_line2' => 'Apartment 4B',
                                'city' => 'Mumbai',
                                'state' => 'Maharashtra',
                                'postal_code' => '400001',
                                'country' => 'India',
                                'is_default' => true
                            ]
                        ],
                        'errors' => null
                    ]
                ]
            ],
            'admin' => [
                'POST /api/admin/login' => [
                    'description' => 'Admin login with enhanced security',
                    'auth_required' => false,
                    'parameters' => [
                        'email' => 'string (required) - Admin email',
                        'password' => 'string (required) - Admin password'
                    ],
                    'example_request' => [
                        'email' => 'admin@riyacollections.com',
                        'password' => 'admin_secure_password'
                    ],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Admin login successful',
                        'data' => [
                            'admin' => [
                                'id' => 1,
                                'email' => 'admin@riyacollections.com',
                                'role' => 'super_admin'
                            ],
                            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
                            'permissions' => ['manage_products', 'manage_orders', 'manage_users']
                        ],
                        'errors' => null
                    ]
                ],
                'GET /api/admin/dashboard' => [
                    'description' => 'Get admin dashboard statistics',
                    'auth_required' => true,
                    'admin_required' => true,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Dashboard data retrieved successfully',
                        'data' => [
                            'stats' => [
                                'total_users' => 1250,
                                'total_products' => 450,
                                'total_orders' => 2340,
                                'total_revenue' => 1250000.50
                            ],
                            'recent_orders' => [],
                            'low_stock_products' => []
                        ],
                        'errors' => null
                    ]
                ]
            ],
            'utility' => [
                'GET /api/health' => [
                    'description' => 'Basic health check endpoint',
                    'auth_required' => false,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'API is healthy',
                        'data' => [
                            'status' => 'healthy',
                            'timestamp' => '2024-01-31T15:30:00Z',
                            'version' => '1.0.0',
                            'uptime' => 86400
                        ],
                        'errors' => null
                    ]
                ],
                'GET /api/health/detailed' => [
                    'description' => 'Detailed health check with system metrics (admin only)',
                    'auth_required' => true,
                    'admin_required' => true,
                    'parameters' => [],
                    'example_response' => [
                        'success' => true,
                        'message' => 'Detailed health check completed',
                        'data' => [
                            'database' => ['status' => 'connected', 'response_time' => 5.2],
                            'memory' => ['usage' => '45%', 'available' => '2.1GB'],
                            'disk' => ['usage' => '60%', 'available' => '40GB'],
                            'services' => ['email' => 'operational', 'payment' => 'operational']
                        ],
                        'errors' => null
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get testing collections for common use cases
     */
    private function getTestingCollections() {
        return [
            'authentication_flow' => [
                'name' => 'Complete Authentication Flow',
                'description' => 'Test user registration, login, profile management',
                'requests' => [
                    [
                        'name' => 'Register User',
                        'method' => 'POST',
                        'endpoint' => '/api/auth/register',
                        'body' => [
                            'email' => 'testuser@example.com',
                            'password' => 'TestPassword123',
                            'first_name' => 'Test',
                            'last_name' => 'User',
                            'phone' => '+919876543210'
                        ]
                    ],
                    [
                        'name' => 'Login User',
                        'method' => 'POST',
                        'endpoint' => '/api/auth/login',
                        'body' => [
                            'email' => 'testuser@example.com',
                            'password' => 'TestPassword123'
                        ]
                    ],
                    [
                        'name' => 'Get Profile',
                        'method' => 'GET',
                        'endpoint' => '/api/auth/profile',
                        'headers' => ['Authorization' => 'Bearer {token_from_login}']
                    ]
                ]
            ],
            'product_management' => [
                'name' => 'Product Management Flow',
                'description' => 'Test product CRUD operations',
                'requests' => [
                    [
                        'name' => 'Get All Products',
                        'method' => 'GET',
                        'endpoint' => '/api/products'
                    ],
                    [
                        'name' => 'Search Products',
                        'method' => 'GET',
                        'endpoint' => '/api/products?search=shirt&min_price=500'
                    ],
                    [
                        'name' => 'Get Product Details',
                        'method' => 'GET',
                        'endpoint' => '/api/products/1'
                    ]
                ]
            ],
            'order_workflow' => [
                'name' => 'Complete Order Workflow',
                'description' => 'Test order creation and payment',
                'requests' => [
                    [
                        'name' => 'Create Order',
                        'method' => 'POST',
                        'endpoint' => '/api/orders',
                        'headers' => ['Authorization' => 'Bearer {user_token}'],
                        'body' => [
                            'items' => [
                                [
                                    'product_id' => 1,
                                    'quantity' => 2,
                                    'unit_price' => 1299.99
                                ]
                            ],
                            'shipping_address_id' => 1,
                            'payment_method' => 'razorpay'
                        ]
                    ],
                    [
                        'name' => 'Create Payment',
                        'method' => 'POST',
                        'endpoint' => '/api/payments/razorpay/create',
                        'headers' => ['Authorization' => 'Bearer {user_token}'],
                        'body' => [
                            'amount' => 2599.98,
                            'currency' => 'INR'
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get testable endpoints with metadata
     */
    private function getTestableEndpoints() {
        return [
            'public' => [
                'GET /api/health' => [
                    'description' => 'Health check endpoint',
                    'auth_required' => false,
                    'test_data' => []
                ],
                'GET /api/products' => [
                    'description' => 'Get products list',
                    'auth_required' => false,
                    'test_data' => [
                        'query_params' => ['search', 'category_id', 'min_price', 'max_price', 'page', 'limit']
                    ]
                ],
                'POST /api/auth/login' => [
                    'description' => 'User login',
                    'auth_required' => false,
                    'test_data' => [
                        'body' => ['email', 'password']
                    ]
                ]
            ],
            'authenticated' => [
                'GET /api/auth/profile' => [
                    'description' => 'Get user profile',
                    'auth_required' => true,
                    'test_data' => []
                ],
                'GET /api/orders' => [
                    'description' => 'Get user orders',
                    'auth_required' => true,
                    'test_data' => [
                        'query_params' => ['page', 'limit', 'status']
                    ]
                ]
            ],
            'admin' => [
                'GET /api/admin/dashboard' => [
                    'description' => 'Admin dashboard',
                    'auth_required' => true,
                    'admin_required' => true,
                    'test_data' => []
                ],
                'POST /api/admin/products' => [
                    'description' => 'Create product',
                    'auth_required' => true,
                    'admin_required' => true,
                    'test_data' => [
                        'body' => ['name', 'description', 'price', 'stock_quantity']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get test data templates
     */
    private function getTestData() {
        return [
            'users' => [
                'valid_user' => [
                    'email' => 'testuser@example.com',
                    'password' => 'TestPassword123',
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'phone' => '+919876543210'
                ],
                'invalid_user' => [
                    'email' => 'invalid-email',
                    'password' => '123',
                    'first_name' => '',
                    'last_name' => ''
                ]
            ],
            'products' => [
                'valid_product' => [
                    'name' => 'Test Product',
                    'description' => 'A test product for API testing',
                    'price' => 999.99,
                    'stock_quantity' => 100,
                    'category_id' => 1,
                    'brand' => 'Test Brand',
                    'sku' => 'TEST-001'
                ],
                'invalid_product' => [
                    'name' => '',
                    'price' => -100,
                    'stock_quantity' => -5
                ]
            ],
            'orders' => [
                'valid_order' => [
                    'items' => [
                        [
                            'product_id' => 1,
                            'quantity' => 2,
                            'unit_price' => 999.99
                        ]
                    ],
                    'shipping_address_id' => 1,
                    'payment_method' => 'razorpay'
                ]
            ],
            'addresses' => [
                'valid_address' => [
                    'address_line1' => '123 Test Street',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'postal_code' => '400001',
                    'country' => 'India'
                ]
            ]
        ];
    }
    
    /**
     * Get validation tools
     */
    private function getValidationTools() {
        return [
            'request_validator' => [
                'endpoint' => '/api/validate',
                'description' => 'Validate request format before sending',
                'supported_validations' => [
                    'json_format',
                    'required_fields',
                    'field_types',
                    'authentication_headers',
                    'parameter_formats'
                ]
            ],
            'response_validator' => [
                'description' => 'Validate API response format',
                'checks' => [
                    'json_structure',
                    'required_fields',
                    'data_types',
                    'status_codes',
                    'error_formats'
                ]
            ],
            'schema_validator' => [
                'description' => 'Validate against API schema',
                'features' => [
                    'endpoint_existence',
                    'parameter_validation',
                    'response_schema_check',
                    'authentication_requirements'
                ]
            ]
        ];
    }
    
    /**
     * Get example requests for common scenarios
     */
    private function getExampleRequests() {
        return [
            'curl_examples' => [
                'login' => [
                    'description' => 'Login with cURL',
                    'command' => 'curl -X POST ' . $this->getBaseUrl() . '/api/auth/login -H "Content-Type: application/json" -d \'{"email":"test@example.com","password":"password123"}\''
                ],
                'get_products' => [
                    'description' => 'Get products with cURL',
                    'command' => 'curl -X GET "' . $this->getBaseUrl() . '/api/products?page=1&limit=10"'
                ],
                'authenticated_request' => [
                    'description' => 'Authenticated request with cURL',
                    'command' => 'curl -X GET ' . $this->getBaseUrl() . '/api/auth/profile -H "Authorization: Bearer YOUR_TOKEN_HERE"'
                ]
            ],
            'javascript_examples' => [
                'fetch_login' => [
                    'description' => 'Login with JavaScript fetch',
                    'code' => 'fetch("' . $this->getBaseUrl() . '/api/auth/login", {
  method: "POST",
  headers: {"Content-Type": "application/json"},
  body: JSON.stringify({email: "test@example.com", password: "password123"})
}).then(response => response.json()).then(data => console.log(data));'
                ],
                'authenticated_fetch' => [
                    'description' => 'Authenticated request with JavaScript',
                    'code' => 'fetch("' . $this->getBaseUrl() . '/api/auth/profile", {
  headers: {"Authorization": "Bearer " + token}
}).then(response => response.json()).then(data => console.log(data));'
                ]
            ],
            'postman_collection' => [
                'description' => 'Import into Postman for testing',
                'download_url' => $this->getBaseUrl() . '/api/postman-collection',
                'variables' => [
                    'base_url' => $this->getBaseUrl(),
                    'user_token' => '{{user_token}}',
                    'admin_token' => '{{admin_token}}'
                ]
            ]
        ];
    }
    
    /**
     * Perform request validation
     */
    private function performRequestValidation($endpoint, $method, $headers, $body) {
        $validation = [
            'endpoint' => $endpoint,
            'method' => $method,
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'suggestions' => []
        ];
        
        // Validate endpoint format
        if (empty($endpoint) || !str_starts_with($endpoint, '/api/')) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Endpoint must start with /api/';
        }
        
        // Validate HTTP method
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        if (!in_array(strtoupper($method), $validMethods)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Invalid HTTP method. Allowed: ' . implode(', ', $validMethods);
        }
        
        // Validate headers
        if (!empty($headers)) {
            if (isset($headers['Authorization'])) {
                if (!str_starts_with($headers['Authorization'], 'Bearer ')) {
                    $validation['warnings'][] = 'Authorization header should start with "Bearer "';
                }
            }
            
            if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !isset($headers['Content-Type'])) {
                $validation['warnings'][] = 'Content-Type header recommended for ' . $method . ' requests';
            }
        }
        
        // Validate body for POST/PUT requests
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            if (empty($body)) {
                $validation['warnings'][] = 'Request body is empty for ' . $method . ' request';
            } else {
                if (is_string($body)) {
                    $decoded = json_decode($body, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $validation['errors'][] = 'Invalid JSON in request body: ' . json_last_error_msg();
                        $validation['valid'] = false;
                    }
                }
            }
        }
        
        // Add suggestions
        if ($validation['valid']) {
            $validation['suggestions'][] = 'Request format is valid';
            
            if (str_contains($endpoint, '/admin/')) {
                $validation['suggestions'][] = 'This is an admin endpoint - ensure you have admin token';
            }
            
            if (!str_contains($endpoint, '/auth/') && !str_contains($endpoint, '/health') && !str_contains($endpoint, '/products')) {
                $validation['suggestions'][] = 'This endpoint likely requires authentication';
            }
        }
        
        return $validation;
    }
    
    /**
     * Execute test request (simulation)
     */
    private function executeTestRequest($endpoint, $method, $headers, $body) {
        // This is a simulation - in a real implementation, you might make actual HTTP requests
        // or use the router to process the request internally
        
        $result = [
            'request' => [
                'endpoint' => $endpoint,
                'method' => $method,
                'headers' => $headers,
                'body' => $body,
                'timestamp' => date('c')
            ],
            'response' => [
                'status_code' => 200,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Request-ID' => uniqid('test_', true)
                ],
                'body' => [
                    'success' => true,
                    'message' => 'Test request simulation',
                    'data' => [
                        'note' => 'This is a simulated response for testing purposes',
                        'endpoint_tested' => $endpoint,
                        'method_used' => $method
                    ]
                ],
                'response_time' => mt_rand(50, 500) . 'ms'
            ],
            'validation' => $this->performRequestValidation($endpoint, $method, $headers, $body),
            'suggestions' => [
                'Try the actual endpoint for real results',
                'Use the provided test data for valid requests',
                'Check authentication requirements for protected endpoints'
            ]
        ];
        
        return $result;
    }
    
    /**
     * Generate and serve Postman collection
     */
    public function postmanCollection() {
        try {
            require_once __DIR__ . '/../generate_postman_collection.php';
            
            $generator = new PostmanCollectionGenerator();
            $generator->outputAsResponse();
            
        } catch (Exception $e) {
            Logger::error('Failed to generate Postman collection', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('Failed to generate Postman collection');
        }
    }
}