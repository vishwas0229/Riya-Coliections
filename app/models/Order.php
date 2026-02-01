<?php
/**
 * Order Model Class
 * 
 * Comprehensive Order model that provides complete order processing functionality
 * with transaction support to ensure data consistency. Handles order creation,
 * order items management, status tracking, and order number generation.
 * Maintains API compatibility with the existing Node.js backend.
 * 
 * Requirements: 6.1, 6.2
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class Order extends DatabaseModel {
    protected $db;
    
    // Order status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    
    // Payment method constants
    const PAYMENT_COD = 'cod';
    const PAYMENT_ONLINE = 'online';
    const PAYMENT_RAZORPAY = 'razorpay';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('orders');
        $this->setPrimaryKey('id');
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new order with order items
     * 
     * @param array $orderData Order data including items
     * @return array Created order data
     * @throws Exception If creation fails
     */
    public function createOrder($orderData) {
        try {
            $this->beginTransaction();
            
            // Validate order data
            $this->validateOrderData($orderData);
            
            // Generate unique order number
            $orderNumber = $this->generateOrderNumber();
            
            // Calculate order totals
            $totals = $this->calculateOrderTotals($orderData['items']);
            
            // Prepare order data for insertion
            $insertData = [
                'user_id' => $orderData['user_id'],
                'order_number' => $orderNumber,
                'status' => self::STATUS_PENDING,
                'payment_method' => $orderData['payment_method'],
                'payment_status' => 'pending',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_amount' => $totals['shipping_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total_amount' => $totals['total_amount'],
                'currency' => $orderData['currency'] ?? 'INR',
                'shipping_address_id' => $orderData['shipping_address_id'] ?? null,
                'billing_address_id' => $orderData['billing_address_id'] ?? null,
                'notes' => $orderData['notes'] ?? null,
                'expected_delivery_date' => $orderData['expected_delivery_date'] ?? null
            ];
            
            // Insert order
            $orderId = $this->insert($insertData);
            
            // Create order items
            $this->createOrderItems($orderId, $orderData['items']);
            
            // Update product stock quantities
            $this->updateProductStock($orderData['items']);
            
            // Create order status history
            $this->createOrderStatusHistory($orderId, self::STATUS_PENDING, 'Order created');
            
            // Get created order with items
            $order = $this->getOrderById($orderId);
            
            $this->commit();
            
            Logger::info('Order created successfully', [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'user_id' => $orderData['user_id'],
                'total_amount' => $totals['total_amount'],
                'items_count' => count($orderData['items'])
            ]);
            
            return $order;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Order creation failed', [
                'user_id' => $orderData['user_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get order by ID with items and details
     * 
     * @param int $orderId Order ID
     * @return array|null Order data or null if not found
     */
    public function getOrderById($orderId) {
        try {
            // Get order details
            $sql = "SELECT o.*, 
                           sa.address_line1 as shipping_address_line1,
                           sa.address_line2 as shipping_address_line2,
                           sa.city as shipping_city,
                           sa.state as shipping_state,
                           sa.postal_code as shipping_postal_code,
                           sa.country as shipping_country,
                           ba.address_line1 as billing_address_line1,
                           ba.address_line2 as billing_address_line2,
                           ba.city as billing_city,
                           ba.state as billing_state,
                           ba.postal_code as billing_postal_code,
                           ba.country as billing_country,
                           u.first_name, u.last_name, u.email, u.phone
                    FROM orders o
                    LEFT JOIN addresses sa ON o.shipping_address_id = sa.id
                    LEFT JOIN addresses ba ON o.billing_address_id = ba.id
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE o.id = ?";
            
            $order = $this->db->fetchOne($sql, [$orderId]);
            
            if (!$order) {
                return null;
            }
            
            // Get order items
            $order['items'] = $this->getOrderItems($orderId);
            
            // Get order status history
            $order['status_history'] = $this->getOrderStatusHistory($orderId);
            
            // Get payment details
            $order['payment'] = $this->getOrderPayment($orderId);
            
            return $this->sanitizeOrderData($order);
            
        } catch (Exception $e) {
            Logger::error('Failed to get order by ID', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get order by order number
     * 
     * @param string $orderNumber Order number
     * @return array|null Order data or null if not found
     */
    public function getOrderByNumber($orderNumber) {
        try {
            $order = $this->first(['order_number' => $orderNumber]);
            
            if (!$order) {
                return null;
            }
            
            return $this->getOrderById($order['id']);
            
        } catch (Exception $e) {
            Logger::error('Failed to get order by number', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get orders by user ID with pagination and filtering
     * 
     * @param int $userId User ID
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated order data
     */
    public function getOrdersByUser($userId, $filters = [], $page = 1, $perPage = 20) {
        try {
            $conditions = ['user_id' => $userId];
            
            // Add status filter
            if (!empty($filters['status'])) {
                $conditions['status'] = $filters['status'];
            }
            
            // Add date range filter
            $sql = "SELECT o.*, COUNT(oi.id) as items_count
                    FROM orders o
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE o.user_id = ?";
            $params = [$userId];
            
            if (!empty($filters['status'])) {
                $sql .= " AND o.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND o.created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND o.created_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
            $params[] = (int)$perPage;
            $params[] = (int)(($page - 1) * $perPage);
            
            $orders = $this->db->fetchAll($sql, $params);
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM orders WHERE user_id = ?";
            $countParams = [$userId];
            
            if (!empty($filters['status'])) {
                $countSql .= " AND status = ?";
                $countParams[] = $filters['status'];
            }
            
            if (!empty($filters['date_from'])) {
                $countSql .= " AND created_at >= ?";
                $countParams[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $countSql .= " AND created_at <= ?";
                $countParams[] = $filters['date_to'];
            }
            
            $total = (int)$this->db->fetchColumn($countSql, $countParams);
            
            // Sanitize order data
            $sanitizedOrders = array_map([$this, 'sanitizeOrderData'], $orders);
            
            return [
                'orders' => $sanitizedOrders,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'has_next' => $page < ceil($total / $perPage),
                    'has_prev' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get orders by user', [
                'user_id' => $userId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update order status
     * 
     * @param int $orderId Order ID
     * @param string $status New status
     * @param string $notes Optional notes
     * @return bool Success status
     * @throws Exception If update fails
     */
    public function updateOrderStatus($orderId, $status, $notes = null) {
        try {
            $this->beginTransaction();
            
            // Validate status
            if (!$this->isValidStatus($status)) {
                throw new Exception('Invalid order status', 400);
            }
            
            // Get current order
            $order = $this->find($orderId);
            if (!$order) {
                throw new Exception('Order not found', 404);
            }
            
            // Check if status change is valid
            if (!$this->isValidStatusTransition($order['status'], $status)) {
                throw new Exception('Invalid status transition', 400);
            }
            
            // Update order status
            $updated = $this->updateById($orderId, [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$updated) {
                throw new Exception('Failed to update order status', 500);
            }
            
            // Create status history entry
            $this->createOrderStatusHistory($orderId, $status, $notes);
            
            // Handle status-specific actions
            $this->handleStatusChange($orderId, $status, $order['status']);
            
            $this->commit();
            
            Logger::info('Order status updated', [
                'order_id' => $orderId,
                'old_status' => $order['status'],
                'new_status' => $status,
                'notes' => $notes
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Order status update failed', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Cancel order
     * 
     * @param int $orderId Order ID
     * @param string $reason Cancellation reason
     * @return bool Success status
     * @throws Exception If cancellation fails
     */
    public function cancelOrder($orderId, $reason = null) {
        try {
            $this->beginTransaction();
            
            // Get order
            $order = $this->find($orderId);
            if (!$order) {
                throw new Exception('Order not found', 404);
            }
            
            // Check if order can be cancelled
            if (!in_array($order['status'], [self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
                throw new Exception('Order cannot be cancelled in current status', 400);
            }
            
            // Update order status
            $this->updateOrderStatus($orderId, self::STATUS_CANCELLED, $reason);
            
            // Restore product stock
            $orderItems = $this->getOrderItems($orderId);
            $this->restoreProductStock($orderItems);
            
            $this->commit();
            
            Logger::info('Order cancelled', [
                'order_id' => $orderId,
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Order cancellation failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all orders with pagination and filtering (admin)
     * 
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Paginated order data
     */
    public function getAllOrders($filters = [], $page = 1, $perPage = 20) {
        try {
            $sql = "SELECT o.*, u.first_name, u.last_name, u.email,
                           COUNT(oi.id) as items_count
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE 1=1";
            $params = [];
            
            // Add filters
            if (!empty($filters['status'])) {
                $sql .= " AND o.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['payment_method'])) {
                $sql .= " AND o.payment_method = ?";
                $params[] = $filters['payment_method'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND o.created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND o.created_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
            $params[] = (int)$perPage;
            $params[] = (int)(($page - 1) * $perPage);
            
            $orders = $this->db->fetchAll($sql, $params);
            
            // Get total count
            $countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
            $countParams = [];
            
            if (!empty($filters['status'])) {
                $countSql .= " AND o.status = ?";
                $countParams[] = $filters['status'];
            }
            
            if (!empty($filters['payment_method'])) {
                $countSql .= " AND o.payment_method = ?";
                $countParams[] = $filters['payment_method'];
            }
            
            if (!empty($filters['date_from'])) {
                $countSql .= " AND o.created_at >= ?";
                $countParams[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $countSql .= " AND o.created_at <= ?";
                $countParams[] = $filters['date_to'];
            }
            
            if (!empty($filters['search'])) {
                $countSql .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }
            
            $total = (int)$this->db->fetchColumn($countSql, $countParams);
            
            // Sanitize order data
            $sanitizedOrders = array_map([$this, 'sanitizeOrderData'], $orders);
            
            return [
                'orders' => $sanitizedOrders,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'has_next' => $page < ceil($total / $perPage),
                    'has_prev' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get all orders', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get order statistics
     * 
     * @return array Order statistics
     */
    public function getOrderStats() {
        try {
            $stats = [];
            
            // Total orders
            $stats['total_orders'] = $this->count();
            
            // Orders by status
            $statusStats = $this->db->fetchAll("
                SELECT status, COUNT(*) as count 
                FROM orders 
                GROUP BY status
            ");
            
            foreach ($statusStats as $stat) {
                $stats['by_status'][$stat['status']] = (int)$stat['count'];
            }
            
            // Revenue statistics
            $revenueStats = $this->db->fetchOne("
                SELECT 
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as average_order_value,
                    COUNT(*) as completed_orders
                FROM orders 
                WHERE status IN ('delivered', 'completed')
            ");
            
            $stats['revenue'] = [
                'total_revenue' => (float)($revenueStats['total_revenue'] ?? 0),
                'average_order_value' => (float)($revenueStats['average_order_value'] ?? 0),
                'completed_orders' => (int)($revenueStats['completed_orders'] ?? 0)
            ];
            
            // Recent orders (last 30 days)
            $stats['recent_orders'] = (int)$this->db->fetchColumn("
                SELECT COUNT(*) 
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            // Payment method distribution
            $paymentStats = $this->db->fetchAll("
                SELECT payment_method, COUNT(*) as count 
                FROM orders 
                GROUP BY payment_method
            ");
            
            foreach ($paymentStats as $stat) {
                $stats['by_payment_method'][$stat['payment_method']] = (int)$stat['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Failed to get order statistics', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Generate unique order number
     * 
     * @return string Unique order number
     */
    private function generateOrderNumber() {
        $maxAttempts = 10;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            // Format: RC + YYYYMMDD + 4-digit random number
            $orderNumber = 'RC' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if order number already exists
            if (!$this->exists(['order_number' => $orderNumber])) {
                return $orderNumber;
            }
            
            $attempt++;
        }
        
        // Fallback: use timestamp + random number
        return 'RC' . date('YmdHis') . mt_rand(10, 99);
    }
    
    /**
     * Calculate order totals
     * 
     * @param array $items Order items
     * @return array Calculated totals
     */
    private function calculateOrderTotals($items) {
        $subtotal = 0;
        
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        
        // Calculate tax (assuming 18% GST for India)
        $taxRate = 0.18;
        $taxAmount = $subtotal * $taxRate;
        
        // Calculate shipping (free shipping for orders above 500)
        $shippingAmount = $subtotal >= 500 ? 0 : 50;
        
        // Discount amount (if any)
        $discountAmount = 0; // Can be calculated based on coupons/offers
        
        $totalAmount = $subtotal + $taxAmount + $shippingAmount - $discountAmount;
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'shipping_amount' => round($shippingAmount, 2),
            'discount_amount' => round($discountAmount, 2),
            'total_amount' => round($totalAmount, 2)
        ];
    }
    
    /**
     * Create order items
     * 
     * @param int $orderId Order ID
     * @param array $items Order items
     * @throws Exception If creation fails
     */
    private function createOrderItems($orderId, $items) {
        foreach ($items as $item) {
            $itemData = [
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
                'product_name' => $item['product_name'] ?? null,
                'product_sku' => $item['product_sku'] ?? null
            ];
            
            $sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, product_name, product_sku, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $this->db->executeQuery($sql, [
                $itemData['order_id'],
                $itemData['product_id'],
                $itemData['quantity'],
                $itemData['unit_price'],
                $itemData['total_price'],
                $itemData['product_name'],
                $itemData['product_sku']
            ]);
        }
    }
    
    /**
     * Get order items
     * 
     * @param int $orderId Order ID
     * @return array Order items
     */
    private function getOrderItems($orderId) {
        $sql = "SELECT oi.*, p.name as current_product_name, p.sku as current_product_sku,
                       p.price as current_product_price
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.id";
        
        return $this->db->fetchAll($sql, [$orderId]);
    }
    
    /**
     * Create order status history entry
     * 
     * @param int $orderId Order ID
     * @param string $status Status
     * @param string $notes Notes
     */
    private function createOrderStatusHistory($orderId, $status, $notes = null) {
        $sql = "INSERT INTO order_status_history (order_id, status, notes, created_at)
                VALUES (?, ?, ?, NOW())";
        
        $this->db->executeQuery($sql, [$orderId, $status, $notes]);
    }
    
    /**
     * Get order status history
     * 
     * @param int $orderId Order ID
     * @return array Status history
     */
    private function getOrderStatusHistory($orderId) {
        $sql = "SELECT * FROM order_status_history 
                WHERE order_id = ? 
                ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, [$orderId]);
    }
    
    /**
     * Get order payment details
     * 
     * @param int $orderId Order ID
     * @return array|null Payment details
     */
    private function getOrderPayment($orderId) {
        $sql = "SELECT * FROM payments 
                WHERE order_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        return $this->db->fetchOne($sql, [$orderId]);
    }
    
    /**
     * Update product stock quantities
     * 
     * @param array $items Order items
     * @throws Exception If stock update fails
     */
    private function updateProductStock($items) {
        foreach ($items as $item) {
            // Check current stock
            $currentStock = $this->db->fetchColumn(
                "SELECT stock_quantity FROM products WHERE id = ?",
                [$item['product_id']]
            );
            
            if ($currentStock < $item['quantity']) {
                throw new Exception("Insufficient stock for product ID {$item['product_id']}", 400);
            }
            
            // Update stock
            $sql = "UPDATE products 
                    SET stock_quantity = stock_quantity - ?, 
                        updated_at = NOW() 
                    WHERE id = ?";
            
            $this->db->executeQuery($sql, [$item['quantity'], $item['product_id']]);
        }
    }
    
    /**
     * Restore product stock quantities (for cancelled orders)
     * 
     * @param array $items Order items
     */
    private function restoreProductStock($items) {
        foreach ($items as $item) {
            $sql = "UPDATE products 
                    SET stock_quantity = stock_quantity + ?, 
                        updated_at = NOW() 
                    WHERE id = ?";
            
            $this->db->executeQuery($sql, [$item['quantity'], $item['product_id']]);
        }
    }
    
    /**
     * Check if status is valid
     * 
     * @param string $status Status to check
     * @return bool True if valid
     */
    private function isValidStatus($status) {
        $validStatuses = [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED
        ];
        
        return in_array($status, $validStatuses);
    }
    
    /**
     * Check if status transition is valid
     * 
     * @param string $currentStatus Current status
     * @param string $newStatus New status
     * @return bool True if transition is valid
     */
    private function isValidStatusTransition($currentStatus, $newStatus) {
        $validTransitions = [
            self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
            self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
            self::STATUS_CANCELLED => [], // No transitions from cancelled
            self::STATUS_REFUNDED => [] // No transitions from refunded
        ];
        
        return isset($validTransitions[$currentStatus]) && 
               in_array($newStatus, $validTransitions[$currentStatus]);
    }
    
    /**
     * Handle status-specific actions
     * 
     * @param int $orderId Order ID
     * @param string $newStatus New status
     * @param string $oldStatus Old status
     */
    private function handleStatusChange($orderId, $newStatus, $oldStatus) {
        // Load PollingService for notifications
        require_once __DIR__ . '/../services/PollingService.php';
        $pollingService = new PollingService();
        
        // Get order details for notifications
        $order = $this->find($orderId);
        if (!$order) {
            return;
        }
        
        switch ($newStatus) {
            case self::STATUS_CONFIRMED:
                // Send order confirmation email and create notification
                Logger::info('Order confirmed, should send confirmation email', [
                    'order_id' => $orderId
                ]);
                
                $pollingService->createNotification(
                    $order['user_id'],
                    'order_status',
                    'Order Confirmed',
                    "Your order {$order['order_number']} has been confirmed and is being prepared.",
                    [
                        'order_id' => $orderId,
                        'order_number' => $order['order_number'],
                        'status' => $newStatus,
                        'total_amount' => $order['total_amount']
                    ]
                );
                break;
                
            case self::STATUS_PROCESSING:
                // Create processing notification
                $pollingService->createNotification(
                    $order['user_id'],
                    'order_status',
                    'Order Processing',
                    "Your order {$order['order_number']} is currently being processed.",
                    [
                        'order_id' => $orderId,
                        'order_number' => $order['order_number'],
                        'status' => $newStatus,
                        'total_amount' => $order['total_amount']
                    ]
                );
                break;
                
            case self::STATUS_SHIPPED:
                // Send shipping notification and create notification
                Logger::info('Order shipped, should send shipping notification', [
                    'order_id' => $orderId
                ]);
                
                $pollingService->createNotification(
                    $order['user_id'],
                    'order_status',
                    'Order Shipped',
                    "Great news! Your order {$order['order_number']} has been shipped and is on its way.",
                    [
                        'order_id' => $orderId,
                        'order_number' => $order['order_number'],
                        'status' => $newStatus,
                        'total_amount' => $order['total_amount'],
                        'tracking_info' => 'Available in order details'
                    ]
                );
                break;
                
            case self::STATUS_DELIVERED:
                // Send delivery confirmation and create notification
                Logger::info('Order delivered, should send delivery confirmation', [
                    'order_id' => $orderId
                ]);
                
                $pollingService->createNotification(
                    $order['user_id'],
                    'order_status',
                    'Order Delivered',
                    "Your order {$order['order_number']} has been delivered successfully.",
                    [
                        'order_id' => $orderId,
                        'order_number' => $order['order_number'],
                        'status' => $newStatus,
                        'total_amount' => $order['total_amount']
                    ]
                );
                break;
                
            case self::STATUS_CANCELLED:
                // Create cancellation notification
                $pollingService->createNotification(
                    $order['user_id'],
                    'order_status',
                    'Order Cancelled',
                    "Your order {$order['order_number']} has been cancelled.",
                    [
                        'order_id' => $orderId,
                        'order_number' => $order['order_number'],
                        'status' => $newStatus,
                        'total_amount' => $order['total_amount']
                    ]
                );
                break;
        }
    }
    
    /**
     * Validate order data
     * 
     * @param array $orderData Order data to validate
     * @throws Exception If validation fails
     */
    private function validateOrderData($orderData) {
        $errors = [];
        
        // User ID validation
        if (empty($orderData['user_id'])) {
            $errors[] = 'User ID is required';
        } elseif (!is_numeric($orderData['user_id'])) {
            $errors[] = 'Invalid user ID';
        }
        
        // Payment method validation
        if (empty($orderData['payment_method'])) {
            $errors[] = 'Payment method is required';
        } elseif (!in_array($orderData['payment_method'], [self::PAYMENT_COD, self::PAYMENT_ONLINE, self::PAYMENT_RAZORPAY])) {
            $errors[] = 'Invalid payment method';
        }
        
        // Items validation
        if (empty($orderData['items']) || !is_array($orderData['items'])) {
            $errors[] = 'Order items are required';
        } else {
            foreach ($orderData['items'] as $index => $item) {
                if (empty($item['product_id'])) {
                    $errors[] = "Product ID is required for item {$index}";
                }
                
                if (empty($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                    $errors[] = "Valid quantity is required for item {$index}";
                }
                
                if (empty($item['unit_price']) || !is_numeric($item['unit_price']) || $item['unit_price'] <= 0) {
                    $errors[] = "Valid unit price is required for item {$index}";
                }
            }
        }
        
        // Currency validation
        if (!empty($orderData['currency']) && !in_array($orderData['currency'], ['INR', 'USD', 'EUR'])) {
            $errors[] = 'Invalid currency';
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
        }
    }
    
    /**
     * Sanitize order data for API response
     * 
     * @param array $order Order data
     * @return array Sanitized order data
     */
    private function sanitizeOrderData($order) {
        $sanitized = [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'user_id' => (int)$order['user_id'],
            'status' => $order['status'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'subtotal' => (float)$order['subtotal'],
            'tax_amount' => (float)$order['tax_amount'],
            'shipping_amount' => (float)$order['shipping_amount'],
            'discount_amount' => (float)$order['discount_amount'],
            'total_amount' => (float)$order['total_amount'],
            'currency' => $order['currency'],
            'notes' => $order['notes'],
            'expected_delivery_date' => $order['expected_delivery_date'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at']
        ];
        
        // Add user information if available
        if (isset($order['first_name'])) {
            $sanitized['user'] = [
                'first_name' => $order['first_name'],
                'last_name' => $order['last_name'],
                'email' => $order['email'],
                'phone' => $order['phone'] ?? null
            ];
        }
        
        // Add address information if available
        if (isset($order['shipping_address_line1'])) {
            $sanitized['shipping_address'] = [
                'address_line1' => $order['shipping_address_line1'],
                'address_line2' => $order['shipping_address_line2'],
                'city' => $order['shipping_city'],
                'state' => $order['shipping_state'],
                'postal_code' => $order['shipping_postal_code'],
                'country' => $order['shipping_country']
            ];
        }
        
        if (isset($order['billing_address_line1'])) {
            $sanitized['billing_address'] = [
                'address_line1' => $order['billing_address_line1'],
                'address_line2' => $order['billing_address_line2'],
                'city' => $order['billing_city'],
                'state' => $order['billing_state'],
                'postal_code' => $order['billing_postal_code'],
                'country' => $order['billing_country']
            ];
        }
        
        // Add items if available
        if (isset($order['items'])) {
            $sanitized['items'] = $order['items'];
        }
        
        // Add status history if available
        if (isset($order['status_history'])) {
            $sanitized['status_history'] = $order['status_history'];
        }
        
        // Add payment details if available
        if (isset($order['payment'])) {
            $sanitized['payment'] = $order['payment'];
        }
        
        // Add items count if available
        if (isset($order['items_count'])) {
            $sanitized['items_count'] = (int)$order['items_count'];
        }
        
        return $sanitized;
    }
}