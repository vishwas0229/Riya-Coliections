<?php
/**
 * Postman Collection Generator
 * 
 * Generates a Postman collection file for the Riya Collections API
 * that can be imported into Postman for interactive testing.
 * 
 * Requirements: 15.3, 15.4
 */

require_once __DIR__ . '/config/environment.php';

class PostmanCollectionGenerator {
    private $baseUrl;
    private $collection;
    
    public function __construct() {
        $this->baseUrl = $this->getBaseUrl();
        $this->initializeCollection();
    }
    
    /**
     * Generate complete Postman collection
     */
    public function generateCollection() {
        $this->addAuthenticationRequests();
        $this->addProductRequests();
        $this->addOrderRequests();
        $this->addPaymentRequests();
        $this->addAddressRequests();
        $this->addAdminRequests();
        $this->addUtilityRequests();
        
        return $this->collection;
    }
    
    /**
     * Initialize collection structure
     */
    private function initializeCollection() {
        $this->collection = [
            'info' => [
                'name' => 'Riya Collections API',
                'description' => 'Complete API collection for Riya Collections e-commerce platform',
                'version' => '1.0.0',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $this->baseUrl,
                    'type' => 'string'
                ],
                [
                    'key' => 'user_token',
                    'value' => '',
                    'type' => 'string'
                ],
                [
                    'key' => 'admin_token',
                    'value' => '',
                    'type' => 'string'
                ]
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{user_token}}',
                        'type' => 'string'
                    ]
                ]
            ],
            'event' => [
                [
                    'listen' => 'prerequest',
                    'script' => [
                        'type' => 'text/javascript',
                        'exec' => [
                            '// Auto-extract tokens from login responses',
                            'if (pm.response && pm.response.json()) {',
                            '    const response = pm.response.json();',
                            '    if (response.success && response.data && response.data.token) {',
                            '        pm.collectionVariables.set("user_token", response.data.token);',
                            '    }',
                            '}'
                        ]
                    ]
                ]
            ],
            'item' => []
        ];
    }
    
    /**
     * Add authentication requests
     */
    private function addAuthenticationRequests() {
        $authFolder = [
            'name' => 'Authentication',
            'description' => 'User authentication and profile management',
            'item' => [
                $this->createRequest(
                    'Register User',
                    'POST',
                    '/api/auth/register',
                    [
                        'email' => 'user@example.com',
                        'password' => 'SecurePassword123',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'phone' => '+919876543210'
                    ],
                    [],
                    'Register a new user account'
                ),
                $this->createRequest(
                    'Login User',
                    'POST',
                    '/api/auth/login',
                    [
                        'email' => 'user@example.com',
                        'password' => 'SecurePassword123'
                    ],
                    [],
                    'Login with email and password',
                    [
                        'pm.test("Status code is 200", function () {',
                        '    pm.response.to.have.status(200);',
                        '});',
                        '',
                        'pm.test("Response has token", function () {',
                        '    const response = pm.response.json();',
                        '    pm.expect(response.success).to.be.true;',
                        '    pm.expect(response.data.token).to.exist;',
                        '    pm.collectionVariables.set("user_token", response.data.token);',
                        '});'
                    ]
                ),
                $this->createRequest(
                    'Get Profile',
                    'GET',
                    '/api/auth/profile',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Get current user profile'
                ),
                $this->createRequest(
                    'Update Profile',
                    'PUT',
                    '/api/auth/profile',
                    [
                        'first_name' => 'Jane',
                        'last_name' => 'Smith',
                        'phone' => '+919876543211'
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Update user profile information'
                ),
                $this->createRequest(
                    'Change Password',
                    'POST',
                    '/api/auth/change-password',
                    [
                        'current_password' => 'SecurePassword123',
                        'new_password' => 'NewSecurePassword123'
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Change user password'
                ),
                $this->createRequest(
                    'Refresh Token',
                    'POST',
                    '/api/auth/refresh',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Refresh JWT token'
                ),
                $this->createRequest(
                    'Logout',
                    'POST',
                    '/api/auth/logout',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Logout and invalidate token'
                )
            ]
        ];
        
        $this->collection['item'][] = $authFolder;
    }
    
    /**
     * Add product requests
     */
    private function addProductRequests() {
        $productFolder = [
            'name' => 'Products',
            'description' => 'Product catalog and management',
            'item' => [
                $this->createRequest(
                    'Get All Products',
                    'GET',
                    '/api/products',
                    null,
                    [],
                    'Get list of all products'
                ),
                $this->createRequest(
                    'Get Products with Pagination',
                    'GET',
                    '/api/products?page=1&limit=10',
                    null,
                    [],
                    'Get products with pagination'
                ),
                $this->createRequest(
                    'Search Products',
                    'GET',
                    '/api/products?search=shirt&min_price=500&max_price=2000',
                    null,
                    [],
                    'Search products with filters'
                ),
                $this->createRequest(
                    'Get Product by ID',
                    'GET',
                    '/api/products/1',
                    null,
                    [],
                    'Get specific product details'
                ),
                $this->createRequest(
                    'Get Categories',
                    'GET',
                    '/api/categories',
                    null,
                    [],
                    'Get all product categories'
                ),
                $this->createRequest(
                    'Get Category Products',
                    'GET',
                    '/api/categories/1/products',
                    null,
                    [],
                    'Get products in specific category'
                )
            ]
        ];
        
        $this->collection['item'][] = $productFolder;
    }
    
    /**
     * Add order requests
     */
    private function addOrderRequests() {
        $orderFolder = [
            'name' => 'Orders',
            'description' => 'Order management and tracking',
            'item' => [
                $this->createRequest(
                    'Get User Orders',
                    'GET',
                    '/api/orders',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Get current user orders'
                ),
                $this->createRequest(
                    'Get Order by ID',
                    'GET',
                    '/api/orders/1',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Get specific order details'
                ),
                $this->createRequest(
                    'Create Order',
                    'POST',
                    '/api/orders',
                    [
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
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Create new order'
                )
            ]
        ];
        
        $this->collection['item'][] = $orderFolder;
    }
    
    /**
     * Add payment requests
     */
    private function addPaymentRequests() {
        $paymentFolder = [
            'name' => 'Payments',
            'description' => 'Payment processing and verification',
            'item' => [
                $this->createRequest(
                    'Create Razorpay Order',
                    'POST',
                    '/api/payments/razorpay/create',
                    [
                        'amount' => 2599.98,
                        'currency' => 'INR'
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Create Razorpay payment order'
                ),
                $this->createRequest(
                    'Verify Razorpay Payment',
                    'POST',
                    '/api/payments/razorpay/verify',
                    [
                        'razorpay_order_id' => 'order_123456789',
                        'razorpay_payment_id' => 'pay_987654321',
                        'razorpay_signature' => 'signature_hash_here'
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Verify Razorpay payment signature'
                ),
                $this->createRequest(
                    'Create COD Order',
                    'POST',
                    '/api/payments/cod',
                    [
                        'order_id' => 1,
                        'amount' => 2599.98
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Create Cash on Delivery order'
                ),
                $this->createRequest(
                    'Get Payment Methods',
                    'GET',
                    '/api/payments/methods',
                    null,
                    [],
                    'Get available payment methods'
                )
            ]
        ];
        
        $this->collection['item'][] = $paymentFolder;
    }
    
    /**
     * Add address requests
     */
    private function addAddressRequests() {
        $addressFolder = [
            'name' => 'Addresses',
            'description' => 'User address management',
            'item' => [
                $this->createRequest(
                    'Get User Addresses',
                    'GET',
                    '/api/addresses',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Get current user addresses'
                ),
                $this->createRequest(
                    'Create Address',
                    'POST',
                    '/api/addresses',
                    [
                        'address_line1' => '123 Main Street',
                        'address_line2' => 'Apartment 4B',
                        'city' => 'Mumbai',
                        'state' => 'Maharashtra',
                        'postal_code' => '400001',
                        'country' => 'India',
                        'is_default' => true
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Create new address'
                ),
                $this->createRequest(
                    'Update Address',
                    'PUT',
                    '/api/addresses/1',
                    [
                        'address_line1' => '456 Updated Street',
                        'city' => 'Delhi'
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Update existing address'
                ),
                $this->createRequest(
                    'Delete Address',
                    'DELETE',
                    '/api/addresses/1',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{user_token}}']],
                    'Delete address'
                )
            ]
        ];
        
        $this->collection['item'][] = $addressFolder;
    }
    
    /**
     * Add admin requests
     */
    private function addAdminRequests() {
        $adminFolder = [
            'name' => 'Admin',
            'description' => 'Administrative functions',
            'item' => [
                $this->createRequest(
                    'Admin Login',
                    'POST',
                    '/api/admin/login',
                    [
                        'email' => 'admin@riyacollections.com',
                        'password' => 'admin_password'
                    ],
                    [],
                    'Admin authentication',
                    [
                        'pm.test("Admin login successful", function () {',
                        '    const response = pm.response.json();',
                        '    if (response.success && response.data.token) {',
                        '        pm.collectionVariables.set("admin_token", response.data.token);',
                        '    }',
                        '});'
                    ]
                ),
                $this->createRequest(
                    'Admin Dashboard',
                    'GET',
                    '/api/admin/dashboard',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}']],
                    'Get admin dashboard data'
                ),
                $this->createRequest(
                    'Create Product',
                    'POST',
                    '/api/admin/products',
                    [
                        'name' => 'New Product',
                        'description' => 'Product description',
                        'price' => 999.99,
                        'stock_quantity' => 100,
                        'category_id' => 1,
                        'brand' => 'Riya Collections',
                        'sku' => 'RC-NEW-001'
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}']],
                    'Create new product (admin only)'
                ),
                $this->createRequest(
                    'Update Product',
                    'PUT',
                    '/api/admin/products/1',
                    [
                        'name' => 'Updated Product Name',
                        'price' => 1199.99
                    ],
                    [['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}']],
                    'Update existing product'
                ),
                $this->createRequest(
                    'Get All Orders (Admin)',
                    'GET',
                    '/api/admin/orders',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}']],
                    'Get all orders (admin view)'
                ),
                $this->createRequest(
                    'Get All Users (Admin)',
                    'GET',
                    '/api/admin/users',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}']],
                    'Get all users (admin view)'
                )
            ]
        ];
        
        $this->collection['item'][] = $adminFolder;
    }
    
    /**
     * Add utility requests
     */
    private function addUtilityRequests() {
        $utilityFolder = [
            'name' => 'Utilities',
            'description' => 'Health checks and system utilities',
            'item' => [
                $this->createRequest(
                    'Health Check',
                    'GET',
                    '/api/health',
                    null,
                    [],
                    'Basic health check'
                ),
                $this->createRequest(
                    'Detailed Health Check',
                    'GET',
                    '/api/health/detailed',
                    null,
                    [['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}']],
                    'Detailed health check (admin only)'
                ),
                $this->createRequest(
                    'API Documentation',
                    'GET',
                    '/api/docs',
                    null,
                    [],
                    'Get API documentation'
                ),
                $this->createRequest(
                    'API Test Interface',
                    'GET',
                    '/api/test',
                    null,
                    [],
                    'Get API testing interface'
                )
            ]
        ];
        
        $this->collection['item'][] = $utilityFolder;
    }
    
    /**
     * Create individual request
     */
    private function createRequest($name, $method, $endpoint, $body = null, $headers = [], $description = '', $tests = []) {
        $request = [
            'name' => $name,
            'request' => [
                'method' => $method,
                'header' => array_merge(
                    [['key' => 'Content-Type', 'value' => 'application/json']],
                    $headers
                ),
                'url' => [
                    'raw' => '{{base_url}}' . $endpoint,
                    'host' => ['{{base_url}}'],
                    'path' => array_filter(explode('/', $endpoint))
                ]
            ]
        ];
        
        if ($body !== null) {
            $request['request']['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($body, JSON_PRETTY_PRINT),
                'options' => [
                    'raw' => [
                        'language' => 'json'
                    ]
                ]
            ];
        }
        
        if ($description) {
            $request['request']['description'] = $description;
        }
        
        if (!empty($tests)) {
            $request['event'] = [
                [
                    'listen' => 'test',
                    'script' => [
                        'type' => 'text/javascript',
                        'exec' => $tests
                    ]
                ]
            ];
        }
        
        return $request;
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . ($path !== '/' ? $path : '');
    }
    
    /**
     * Save collection to file
     */
    public function saveToFile($filename = 'riya_collections_api.postman_collection.json') {
        $collection = $this->generateCollection();
        $json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        file_put_contents($filename, $json);
        
        return $filename;
    }
    
    /**
     * Output collection as JSON response
     */
    public function outputAsResponse() {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="riya_collections_api.postman_collection.json"');
        
        $collection = $this->generateCollection();
        echo json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

// Handle direct access
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $generator = new PostmanCollectionGenerator();
    
    if (php_sapi_name() === 'cli') {
        // CLI mode - save to file
        $filename = $generator->saveToFile();
        echo "Postman collection saved to: $filename\n";
    } else {
        // Web mode - output as download
        $generator->outputAsResponse();
    }
}