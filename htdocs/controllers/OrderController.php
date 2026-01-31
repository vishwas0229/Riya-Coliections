<?php
/**
 * Order Controller Class
 * 
 * Comprehensive OrderController that handles all order-related API endpoints
 * including order creation, retrieval, status management, and admin operations.
 * Maintains API compatibility with the existing Node.js backend and provides
 * complete order workflow functionality.
 * 
 * Requirements: 6.1, 6.2, 11.1
 */

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Address.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class OrderController {
    private $orderModel;
    private $addressModel;
    private $productModel;
    private $request;
    private $params;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->orderModel = new Order();
        $this->addressModel = new Address();
        $this->productModel = new Product();
    }
    
    /**
     * Set request data
     */
    public function setRequest($request) {
        $this->request = $request;
    }
    
    /**
     * Set route parameters
     */
    public function setParams($params) {
        $this->params = $params;
    }
    
    // ==================== PUBLIC ORDER ENDPOINTS ====================
    
    /**
     * POST /api/orders - Create new order
     * Authenticated endpoint for order creation
     */
    public function create() {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON data', 400);
                return;
            }
            
            // Validate required fields
            $this->validateOrderCreationData($input);
            
            // Prepare order data
            $orderData = [
                'user_id' => $user['id'],
                'payment_method' => $input['payment_method'],
                'currency' => $input['currency'] ?? 'INR',
                'shipping_address_id' => $input['shipping_address_id'] ?? null,
                'billing_address_id' => $input['billing_address_id'] ?? null,
                'notes' => $input['notes'] ?? null,
                'expected_delivery_date' => $input['expected_delivery_date'] ?? null,
                'items' => $input['items']
            ];
            
            // Validate and prepare order items
            $orderData['items'] = $this->validateAndPrepareOrderItems($input['items']);
            
            // Validate addresses if provided
            if ($orderData['shipping_address_id']) {
                $this->validateUserAddress($orderData['shipping_address_id'], $user['id']);
            }
            
            if ($orderData['billing_address_id']) {
                $this->validateUserAddress($orderData['billing_address_id'], $user['id']);
            }
            
            // Create order
            $order = $this->orderModel->createOrder($orderData);
            
            Logger::info('Order created successfully', [
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'user_id' => $user['id'],
                'total_amount' => $order['total_amount'],
                'payment_method' => $order['payment_method']
            ]);
            
            Response::success('Order created successfully', $order, 201);
            
        } catch (Exception $e) {
            Logger::error('Order creation failed', [
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'input' => $input ?? []
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * GET /api/orders - Get user's orders with pagination
     * Authenticated endpoint for order history
     */
    public function getUserOrders() {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Extract query parameters
            $filters = $this->extractOrderFilters();
            $page = (int)($this->request['query']['page'] ?? 1);
            $perPage = min((int)($this->request['query']['per_page'] ?? 20), 100); // Max 100 per page
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1) $perPage = 20;
            
            // Get user orders
            $result = $this->orderModel->getOrdersByUser($user['id'], $filters, $page, $perPage);
            
            Logger::info('User orders retrieved successfully', [
                'user_id' => $user['id'],
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
                'total_orders' => $result['pagination']['total']
            ]);
            
            Response::success('Orders retrieved successfully', [
                'orders' => $result['orders'],
                'pagination' => $result['pagination']
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve user orders', [
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'filters' => $filters ?? []
            ]);
            
            Response::error('Failed to retrieve orders: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/orders/{id} - Get order details by ID
     * Authenticated endpoint for order details
     */
    public function getById($id) {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Validate order ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid order ID', 400);
                return;
            }
            
            // Get order
            $order = $this->orderModel->getOrderById($id);
            
            if (!$order) {
                Response::error('Order not found', 404);
                return;
            }
            
            // Check if user owns the order (unless admin)
            if (!AuthMiddleware::hasRole(AuthMiddleware::ROLE_ADMIN) && 
                $order['user_id'] != $user['id']) {
                Response::error('Access denied', 403);
                return;
            }
            
            Logger::info('Order details retrieved', [
                'order_id' => $id,
                'user_id' => $user['id'],
                'order_number' => $order['order_number']
            ]);
            
            Response::success('Order details retrieved successfully', $order);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve order details', [
                'order_id' => $id,
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve order details: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/orders/number/{orderNumber} - Get order by order number
     * Authenticated endpoint for order lookup by number
     */
    public function getByNumber($orderNumber) {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Validate order number format
            if (!preg_match('/^RC\d{8}\d{4}$/', $orderNumber)) {
                Response::error('Invalid order number format', 400);
                return;
            }
            
            // Get order
            $order = $this->orderModel->getOrderByNumber($orderNumber);
            
            if (!$order) {
                Response::error('Order not found', 404);
                return;
            }
            
            // Check if user owns the order (unless admin)
            if (!AuthMiddleware::hasRole(AuthMiddleware::ROLE_ADMIN) && 
                $order['user_id'] != $user['id']) {
                Response::error('Access denied', 403);
                return;
            }
            
            Logger::info('Order retrieved by number', [
                'order_number' => $orderNumber,
                'user_id' => $user['id'],
                'order_id' => $order['id']
            ]);
            
            Response::success('Order retrieved successfully', $order);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve order by number', [
                'order_number' => $orderNumber,
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/orders/{id}/cancel - Cancel order
     * Authenticated endpoint for order cancellation
     */
    public function cancel($id) {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Validate order ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid order ID', 400);
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            $reason = $input['reason'] ?? 'Cancelled by customer';
            
            // Get order to check ownership
            $order = $this->orderModel->getOrderById($id);
            
            if (!$order) {
                Response::error('Order not found', 404);
                return;
            }
            
            // Check if user owns the order (unless admin)
            if (!AuthMiddleware::hasRole(AuthMiddleware::ROLE_ADMIN) && 
                $order['user_id'] != $user['id']) {
                Response::error('Access denied', 403);
                return;
            }
            
            // Cancel order
            $success = $this->orderModel->cancelOrder($id, $reason);
            
            if (!$success) {
                Response::error('Failed to cancel order', 500);
                return;
            }
            
            Logger::info('Order cancelled', [
                'order_id' => $id,
                'user_id' => $user['id'],
                'reason' => $reason
            ]);
            
            Response::success('Order cancelled successfully');
            
        } catch (Exception $e) {
            Logger::error('Order cancellation failed', [
                'order_id' => $id,
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    // ==================== ADMIN ORDER ENDPOINTS ====================
    
    /**
     * GET /api/admin/orders - Get all orders with filtering (Admin only)
     * Admin endpoint for order management
     */
    public function getAllOrders() {
        try {
            // Authenticate admin
            $user = AuthMiddleware::authenticate();
            if (!$user || !AuthMiddleware::hasRole(AuthMiddleware::ROLE_ADMIN)) {
                Response::unauthorized('Admin access required');
                return;
            }
            
            // Extract query parameters
            $filters = $this->extractOrderFilters();
            $page = (int)($this->request['query']['page'] ?? 1);
            $perPage = min((int)($this->request['query']['per_page'] ?? 20), 100); // Max 100 per page
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1) $perPage = 20;
            
            // Get all orders
            $result = $this->orderModel->getAllOrders($filters, $page, $perPage);
            
            Logger::info('All orders retrieved by admin', [
                'admin_id' => $user['id'],
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
                'total_orders' => $result['pagination']['total']
            ]);
            
            Response::success('Orders retrieved successfully', [
                'orders' => $result['orders'],
                'pagination' => $result['pagination']
            ]);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve all orders', [
                'admin_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'filters' => $filters ?? []
            ]);
            
            Response::error('Failed to retrieve orders: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/admin/orders/{id}/status - Update order status (Admin only)
     * Admin endpoint for order status management
     */
    public function updateStatus($id) {
        try {
            // Authenticate admin
            $user = AuthMiddleware::authenticate();
            if (!$user || !AuthMiddleware::hasRole(AuthMiddleware::ROLE_ADMIN)) {
                Response::unauthorized('Admin access required');
                return;
            }
            
            // Validate order ID
            if (!is_numeric($id) || $id <= 0) {
                Response::error('Invalid order ID', 400);
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['status'])) {
                Response::error('Status is required', 400);
                return;
            }
            
            $status = $input['status'];
            $notes = $input['notes'] ?? null;
            
            // Update order status
            $success = $this->orderModel->updateOrderStatus($id, $status, $notes);
            
            if (!$success) {
                Response::error('Failed to update order status', 500);
                return;
            }
            
            Logger::info('Order status updated by admin', [
                'order_id' => $id,
                'admin_id' => $user['id'],
                'new_status' => $status,
                'notes' => $notes
            ]);
            
            Response::success('Order status updated successfully');
            
        } catch (Exception $e) {
            Logger::error('Order status update failed', [
                'order_id' => $id,
                'admin_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * GET /api/admin/orders/stats - Get order statistics (Admin only)
     * Admin endpoint for order analytics
     */
    public function getOrderStats() {
        try {
            // Authenticate admin
            $user = AuthMiddleware::authenticate();
            if (!$user || !AuthMiddleware::hasRole(AuthMiddleware::ROLE_ADMIN)) {
                Response::unauthorized('Admin access required');
                return;
            }
            
            // Get order statistics
            $stats = $this->orderModel->getOrderStats();
            
            Logger::info('Order statistics retrieved by admin', [
                'admin_id' => $user['id'],
                'total_orders' => $stats['total_orders']
            ]);
            
            Response::success('Order statistics retrieved successfully', $stats);
            
        } catch (Exception $e) {
            Logger::error('Failed to retrieve order statistics', [
                'admin_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            Response::error('Failed to retrieve order statistics: ' . $e->getMessage(), 500);
        }
    }
    
    // ==================== PRIVATE HELPER METHODS ====================
    
    /**
     * Validate order creation data
     * 
     * @param array $input Input data
     * @throws Exception If validation fails
     */
    private function validateOrderCreationData($input) {
        $errors = [];
        
        // Payment method validation
        if (empty($input['payment_method'])) {
            $errors[] = 'Payment method is required';
        } elseif (!in_array($input['payment_method'], ['cod', 'online', 'razorpay'])) {
            $errors[] = 'Invalid payment method';
        }
        
        // Items validation
        if (empty($input['items']) || !is_array($input['items'])) {
            $errors[] = 'Order items are required';
        } elseif (count($input['items']) === 0) {
            $errors[] = 'At least one item is required';
        }
        
        // Currency validation
        if (!empty($input['currency']) && !in_array($input['currency'], ['INR', 'USD', 'EUR'])) {
            $errors[] = 'Invalid currency';
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
        }
    }
    
    /**
     * Validate and prepare order items
     * 
     * @param array $items Raw order items
     * @return array Prepared order items
     * @throws Exception If validation fails
     */
    private function validateAndPrepareOrderItems($items) {
        $preparedItems = [];
        $errors = [];
        
        foreach ($items as $index => $item) {
            // Validate required fields
            if (empty($item['product_id'])) {
                $errors[] = "Product ID is required for item {$index}";
                continue;
            }
            
            if (empty($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                $errors[] = "Valid quantity is required for item {$index}";
                continue;
            }
            
            // Get product details
            $product = $this->productModel->getProductById($item['product_id']);
            
            if (!$product) {
                $errors[] = "Product not found for item {$index}";
                continue;
            }
            
            // Check stock availability
            if ($product['stock_quantity'] < $item['quantity']) {
                $errors[] = "Insufficient stock for product '{$product['name']}'. Available: {$product['stock_quantity']}, Requested: {$item['quantity']}";
                continue;
            }
            
            // Prepare item data
            $preparedItems[] = [
                'product_id' => $product['id'],
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$product['price'],
                'product_name' => $product['name'],
                'product_sku' => $product['sku']
            ];
        }
        
        if (!empty($errors)) {
            throw new Exception('Item validation failed: ' . implode(', ', $errors), 400);
        }
        
        if (empty($preparedItems)) {
            throw new Exception('No valid items found', 400);
        }
        
        return $preparedItems;
    }
    
    /**
     * Validate user address ownership
     * 
     * @param int $addressId Address ID
     * @param int $userId User ID
     * @throws Exception If validation fails
     */
    private function validateUserAddress($addressId, $userId) {
        $address = $this->addressModel->getAddressById($addressId);
        
        if (!$address) {
            throw new Exception('Address not found', 404);
        }
        
        if ($address['user_id'] != $userId) {
            throw new Exception('Access denied to address', 403);
        }
    }
    
    /**
     * Extract order filters from query parameters
     * 
     * @return array Filters
     */
    private function extractOrderFilters() {
        $filters = [];
        
        // Status filter
        if (!empty($this->request['query']['status'])) {
            $filters['status'] = $this->request['query']['status'];
        }
        
        // Payment method filter
        if (!empty($this->request['query']['payment_method'])) {
            $filters['payment_method'] = $this->request['query']['payment_method'];
        }
        
        // Date range filters
        if (!empty($this->request['query']['date_from'])) {
            $filters['date_from'] = $this->request['query']['date_from'];
        }
        
        if (!empty($this->request['query']['date_to'])) {
            $filters['date_to'] = $this->request['query']['date_to'];
        }
        
        // Search filter (for admin)
        if (!empty($this->request['query']['search'])) {
            $filters['search'] = $this->request['query']['search'];
        }
        
        return $filters;
    }
}