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
                    'documentation_url' => $this->getBaseUrl() . '/api/docs'
                ],
                'authentication' => [
                    'type' => 'Bearer Token (JWT)',
                    'header' => 'Authorization: Bearer <token>',
                    'login_endpoint' => '/api/auth/login',
                    'register_endpoint' => '/api/auth/register'
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
     * Get base URL for the API
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . ($path !== '/' ? $path : '');
    }
    
    /**
     * Get endpoint documentation
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
                        'password' => 'securepassword',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'phone' => '+919876543210'
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
                        'password' => 'securepassword'
                    ]
                ],
                'GET /api/auth/profile' => [
                    'description' => 'Get current user profile',
                    'auth_required' => true,
                    'parameters' => []
                ],
                'PUT /api/auth/profile' => [
                    'description' => 'Update user profile',
                    'auth_required' => true,
                    'parameters' => [
                        'first_name' => 'string (optional) - User first name',
                        'last_name' => 'string (optional) - User last name',
                        'phone' => 'string (optional) - User phone number'
                    ]
                ]
            ],
            'products' => [
                'GET /api/products' => [
                    'description' => 'Get list of products with filtering and pagination',
                    'auth_required' => false,
                    'parameters' => [
                        'search' => 'string (optional) - Search term',
                        'category_id' => 'integer (optional) - Filter by category',
                        'min_price' => 'number (optional) - Minimum price filter',
                        'max_price' => 'number (optional) - Maximum price filter',
                        'page' => 'integer (optional) - Page number (default: 1)',
                        'limit' => 'integer (optional) - Items per page (default: 20)'
                    ]
                ],
                'GET /api/products/{id}' => [
                    'description' => 'Get product details by ID',
                    'auth_required' => false,
                    'parameters' => [
                        'id' => 'integer (required) - Product ID'
                    ]
                ],
                'POST /api/products' => [
                    'description' => 'Create new product (admin only)',
                    'auth_required' => true,
                    'admin_required' => true,
                    'parameters' => [
                        'name' => 'string (required) - Product name',
                        'description' => 'string (optional) - Product description',
                        'price' => 'number (required) - Product price',
                        'stock_quantity' => 'integer (required) - Stock quantity',
                        'category_id' => 'integer (optional) - Category ID',
                        'brand' => 'string (optional) - Product brand',
                        'sku' => 'string (optional) - Product SKU'
                    ]
                ]
            ],
            'orders' => [
                'GET /api/orders' => [
                    'description' => 'Get user orders',
                    'auth_required' => true,
                    'parameters' => [
                        'page' => 'integer (optional) - Page number',
                        'limit' => 'integer (optional) - Items per page'
                    ]
                ],
                'POST /api/orders' => [
                    'description' => 'Create new order',
                    'auth_required' => true,
                    'parameters' => [
                        'items' => 'array (required) - Order items',
                        'shipping_address_id' => 'integer (required) - Shipping address ID',
                        'payment_method' => 'string (required) - Payment method (razorpay|cod)'
                    ]
                ]
            ],
            'payments' => [
                'POST /api/payments/razorpay/create' => [
                    'description' => 'Create Razorpay order',
                    'auth_required' => true,
                    'parameters' => [
                        'amount' => 'number (required) - Order amount',
                        'currency' => 'string (optional) - Currency code (default: INR)'
                    ]
                ],
                'POST /api/payments/razorpay/verify' => [
                    'description' => 'Verify Razorpay payment',
                    'auth_required' => true,
                    'parameters' => [
                        'razorpay_order_id' => 'string (required) - Razorpay order ID',
                        'razorpay_payment_id' => 'string (required) - Razorpay payment ID',
                        'razorpay_signature' => 'string (required) - Razorpay signature'
                    ]
                ]
            ],
            'admin' => [
                'POST /api/admin/login' => [
                    'description' => 'Admin login',
                    'auth_required' => false,
                    'parameters' => [
                        'email' => 'string (required) - Admin email',
                        'password' => 'string (required) - Admin password'
                    ]
                ],
                'GET /api/admin/dashboard' => [
                    'description' => 'Get admin dashboard data',
                    'auth_required' => true,
                    'admin_required' => true,
                    'parameters' => []
                ]
            ],
            'utility' => [
                'GET /api/health' => [
                    'description' => 'Basic health check',
                    'auth_required' => false,
                    'parameters' => []
                ],
                'GET /api/health/detailed' => [
                    'description' => 'Detailed health check (admin only)',
                    'auth_required' => true,
                    'admin_required' => true,
                    'parameters' => []
                ]
            ]
        ];
    }
}