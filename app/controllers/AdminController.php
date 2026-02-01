<?php
/**
 * Admin Controller
 * 
 * Handles administrative operations including dashboard statistics, user management,
 * order management, and system monitoring. Provides comprehensive admin functionality
 * with proper authentication and authorization.
 * 
 * Requirements: 11.1, 11.2, 11.3
 */

require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

class AdminController {
    private $db;
    private $userModel;
    private $orderModel;
    private $productModel;
    private $paymentService;
    private $emailService;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userModel = new User();
        $this->orderModel = new Order();
        $this->productModel = new Product();
        $this->paymentService = new PaymentService();
        $this->emailService = new EmailService();
    }
    
    /**
     * Handle admin requests
     * 
     * @param string $action Action to perform
     * @param array $params Request parameters
     */
    public function handleRequest($action, $params = []) {
        try {
            // Verify admin authentication
            AdminMiddleware::handle();
            
            switch ($action) {
                case 'dashboard':
                    return $this->getDashboard();
                    
                case 'users':
                    return $this->handleUserManagement($params);
                    
                case 'orders':
                    return $this->handleOrderManagement($params);
                    
                case 'products':
                    return $this->handleProductManagement($params);
                    
                case 'analytics':
                    return $this->getAnalytics($params);
                    
                case 'system':
                    return $this->getSystemInfo();
                    
                case 'logs':
                    return $this->getLogs($params);
                    
                case 'settings':
                    return $this->handleSettings($params);
                    
                default:
                    Response::notFound('Admin action not found');
            }
            
        } catch (Exception $e) {
            Logger::error('Admin controller error', [
                'action' => $action,
                'error' => $e->getMessage(),
                'admin_user' => $_SESSION['admin_user']['id'] ?? 'unknown'
            ]);
            
            Response::error('Admin operation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get admin dashboard data
     * 
     * @return void
     */
    public function getDashboard() {
        try {
            $dashboard = [
                'overview' => $this->getDashboardOverview(),
                'recent_orders' => $this->getRecentOrders(10),
                'recent_users' => $this->getRecentUsers(10),
                'sales_chart' => $this->getSalesChartData(),
                'top_products' => $this->getTopProducts(5),
                'system_alerts' => $this->getSystemAlerts()
            ];
            
            Response::success($dashboard);
            
        } catch (Exception $e) {
            Logger::error('Failed to get dashboard data', ['error' => $e->getMessage()]);
            Response::error('Failed to load dashboard');
        }
    }
    
    /**
     * Handle user management operations
     * 
     * @param array $params Request parameters
     */
    public function handleUserManagement($params) {
        $method = $_SERVER['REQUEST_METHOD'];
        $subAction = $params['sub_action'] ?? 'list';
        
        switch ($method) {
            case 'GET':
                if ($subAction === 'list') {
                    return $this->getUsersList($params);
                } elseif ($subAction === 'view' && isset($params['user_id'])) {
                    return $this->getUserDetails($params['user_id']);
                } elseif ($subAction === 'export') {
                    return $this->exportUsers($params);
                }
                break;
                
            case 'PUT':
                if (isset($params['user_id'])) {
                    return $this->updateUser($params['user_id'], $params);
                }
                break;
                
            case 'DELETE':
                if (isset($params['user_id'])) {
                    return $this->deleteUser($params['user_id']);
                }
                break;
        }
        
        Response::methodNotAllowed();
    }
    
    /**
     * Handle order management operations
     * 
     * @param array $params Request parameters
     */
    public function handleOrderManagement($params) {
        $method = $_SERVER['REQUEST_METHOD'];
        $subAction = $params['sub_action'] ?? 'list';
        
        switch ($method) {
            case 'GET':
                if ($subAction === 'list') {
                    return $this->getOrdersList($params);
                } elseif ($subAction === 'view' && isset($params['order_id'])) {
                    return $this->getOrderDetails($params['order_id']);
                } elseif ($subAction === 'export') {
                    return $this->exportOrders($params);
                }
                break;
                
            case 'PUT':
                if (isset($params['order_id'])) {
                    return $this->updateOrder($params['order_id'], $params);
                }
                break;
                
            case 'DELETE':
                if (isset($params['order_id'])) {
                    return $this->cancelOrder($params['order_id'], $params);
                }
                break;
        }
        
        Response::methodNotAllowed();
    }
    
    /**
     * Handle product management operations
     * 
     * @param array $params Request parameters
     */
    public function handleProductManagement($params) {
        $method = $_SERVER['REQUEST_METHOD'];
        $subAction = $params['sub_action'] ?? 'list';
        
        switch ($method) {
            case 'GET':
                if ($subAction === 'list') {
                    return $this->getProductsList($params);
                } elseif ($subAction === 'view' && isset($params['product_id'])) {
                    return $this->getProductDetails($params['product_id']);
                } elseif ($subAction === 'inventory') {
                    return $this->getInventoryReport($params);
                }
                break;
                
            case 'POST':
                return $this->createProduct($params);
                
            case 'PUT':
                if (isset($params['product_id'])) {
                    return $this->updateProduct($params['product_id'], $params);
                }
                break;
                
            case 'DELETE':
                if (isset($params['product_id'])) {
                    return $this->deleteProduct($params['product_id']);
                }
                break;
        }
        
        Response::methodNotAllowed();
    }
    
    /**
     * Get analytics data
     * 
     * @param array $params Request parameters
     * @return void
     */
    public function getAnalytics($params) {
        try {
            $type = $params['type'] ?? 'overview';
            $period = $params['period'] ?? '30d';
            
            switch ($type) {
                case 'sales':
                    $data = $this->getSalesAnalytics($period);
                    break;
                    
                case 'users':
                    $data = $this->getUserAnalytics($period);
                    break;
                    
                case 'products':
                    $data = $this->getProductAnalytics($period);
                    break;
                    
                case 'revenue':
                    $data = $this->getRevenueAnalytics($period);
                    break;
                    
                default:
                    $data = $this->getOverviewAnalytics($period);
            }
            
            Response::success($data);
            
        } catch (Exception $e) {
            Logger::error('Failed to get analytics data', [
                'type' => $type ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to load analytics');
        }
    }
    
    /**
     * Get system information
     * 
     * @return void
     */
    public function getSystemInfo() {
        try {
            $systemInfo = [
                'server' => $this->getServerInfo(),
                'database' => $this->getDatabaseInfo(),
                'performance' => $this->getPerformanceInfo(),
                'security' => $this->getSecurityInfo(),
                'storage' => $this->getStorageInfo()
            ];
            
            Response::success($systemInfo);
            
        } catch (Exception $e) {
            Logger::error('Failed to get system info', ['error' => $e->getMessage()]);
            Response::error('Failed to load system information');
        }
    }
    
    /**
     * Get dashboard overview statistics
     * 
     * @return array Overview data
     */
    private function getDashboardOverview() {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $thisMonth = date('Y-m-01');
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        
        // Total counts
        $totalUsers = $this->userModel->count();
        $totalOrders = $this->orderModel->count();
        $totalProducts = $this->productModel->count();
        
        // Today's stats
        $todayOrders = $this->orderModel->count(['created_at >=' => $today]);
        $todayRevenue = $this->getTotalRevenue($today);
        $todayUsers = $this->userModel->count(['created_at >=' => $today]);
        
        // Yesterday's stats for comparison
        $yesterdayOrders = $this->orderModel->count([
            'created_at >=' => $yesterday,
            'created_at <' => $today
        ]);
        $yesterdayRevenue = $this->getTotalRevenue($yesterday, $today);
        
        // Monthly stats
        $monthlyOrders = $this->orderModel->count(['created_at >=' => $thisMonth]);
        $monthlyRevenue = $this->getTotalRevenue($thisMonth);
        
        return [
            'totals' => [
                'users' => $totalUsers,
                'orders' => $totalOrders,
                'products' => $totalProducts,
                'revenue' => $this->getTotalRevenue()
            ],
            'today' => [
                'orders' => $todayOrders,
                'revenue' => $todayRevenue,
                'users' => $todayUsers,
                'orders_change' => $this->calculatePercentageChange($todayOrders, $yesterdayOrders),
                'revenue_change' => $this->calculatePercentageChange($todayRevenue, $yesterdayRevenue)
            ],
            'monthly' => [
                'orders' => $monthlyOrders,
                'revenue' => $monthlyRevenue
            ]
        ];
    }
    
    /**
     * Get recent orders
     * 
     * @param int $limit Number of orders to fetch
     * @return array Recent orders
     */
    private function getRecentOrders($limit = 10) {
        return $this->orderModel->getAllOrders([], 1, $limit)['orders'];
    }
    
    /**
     * Get recent users
     * 
     * @param int $limit Number of users to fetch
     * @return array Recent users
     */
    private function getRecentUsers($limit = 10) {
        $sql = "SELECT id, first_name, last_name, email, created_at 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Get sales chart data
     * 
     * @return array Sales chart data
     */
    private function getSalesChartData() {
        $days = 30;
        $data = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $nextDate = date('Y-m-d', strtotime("-{$i} days + 1 day"));
            
            $orders = $this->orderModel->count([
                'created_at >=' => $date,
                'created_at <' => $nextDate
            ]);
            
            $revenue = $this->getTotalRevenue($date, $nextDate);
            
            $data[] = [
                'date' => $date,
                'orders' => $orders,
                'revenue' => $revenue
            ];
        }
        
        return $data;
    }
    
    /**
     * Get top products
     * 
     * @param int $limit Number of products to fetch
     * @return array Top products
     */
    private function getTopProducts($limit = 5) {
        $sql = "SELECT p.id, p.name, p.price, 
                       SUM(oi.quantity) as total_sold,
                       SUM(oi.total_price) as total_revenue
                FROM products p
                JOIN order_items oi ON p.id = oi.product_id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.status IN ('completed', 'delivered')
                GROUP BY p.id
                ORDER BY total_sold DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Get system alerts
     * 
     * @return array System alerts
     */
    private function getSystemAlerts() {
        $alerts = [];
        
        // Check low stock products
        $lowStockProducts = $this->db->fetchAll(
            "SELECT name, stock_quantity FROM products WHERE stock_quantity < 10 AND is_active = 1"
        );
        
        if (!empty($lowStockProducts)) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Stock Alert',
                'message' => count($lowStockProducts) . ' products are running low on stock',
                'action' => 'View Inventory'
            ];
        }
        
        // Check pending orders
        $pendingOrders = $this->orderModel->count(['status' => 'pending']);
        
        if ($pendingOrders > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pending Orders',
                'message' => $pendingOrders . ' orders are waiting for confirmation',
                'action' => 'View Orders'
            ];
        }
        
        // Check failed payments
        $failedPayments = $this->paymentService->getPaymentStatistics(['status' => 'failed']);
        
        if (!empty($failedPayments) && $failedPayments[0]['count'] > 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Failed Payments',
                'message' => $failedPayments[0]['count'] . ' payments have failed',
                'action' => 'View Payments'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get users list with pagination and filtering
     * 
     * @param array $params Request parameters
     * @return void
     */
    private function getUsersList($params) {
        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 20);
        $search = $params['search'] ?? '';
        $status = $params['status'] ?? '';
        
        $sql = "SELECT id, first_name, last_name, email, phone, created_at, updated_at
                FROM users WHERE 1=1";
        $countSql = "SELECT COUNT(*) FROM users WHERE 1=1";
        $sqlParams = [];
        
        if (!empty($search)) {
            $searchCondition = " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $sql .= $searchCondition;
            $countSql .= $searchCondition;
            $searchTerm = '%' . $search . '%';
            $sqlParams = array_merge($sqlParams, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $sqlParams[] = $perPage;
        $sqlParams[] = ($page - 1) * $perPage;
        
        $users = $this->db->fetchAll($sql, $sqlParams);
        $total = $this->db->fetchColumn($countSql, array_slice($sqlParams, 0, -2));
        
        Response::success([
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => (int)$total,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);
    }
    
    /**
     * Get user details
     * 
     * @param int $userId User ID
     * @return void
     */
    private function getUserDetails($userId) {
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            Response::notFound('User not found');
            return;
        }
        
        // Get user's orders
        $orders = $this->orderModel->getOrdersByUser($userId, [], 1, 10);
        
        // Get user statistics
        $stats = [
            'total_orders' => $this->orderModel->count(['user_id' => $userId]),
            'total_spent' => $this->getTotalUserSpent($userId),
            'last_order_date' => $this->getLastOrderDate($userId)
        ];
        
        Response::success([
            'user' => $user,
            'orders' => $orders['orders'],
            'statistics' => $stats
        ]);
    }
    
    /**
     * Get orders list with pagination and filtering
     * 
     * @param array $params Request parameters
     * @return void
     */
    private function getOrdersList($params) {
        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 20);
        $status = $params['status'] ?? '';
        $search = $params['search'] ?? '';
        
        $filters = [];
        if (!empty($status)) {
            $filters['status'] = $status;
        }
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        
        $result = $this->orderModel->getAllOrders($filters, $page, $perPage);
        
        Response::success($result);
    }
    
    /**
     * Get order details
     * 
     * @param int $orderId Order ID
     * @return void
     */
    private function getOrderDetails($orderId) {
        $order = $this->orderModel->getOrderById($orderId);
        
        if (!$order) {
            Response::notFound('Order not found');
            return;
        }
        
        Response::success(['order' => $order]);
    }
    
    /**
     * Update order
     * 
     * @param int $orderId Order ID
     * @param array $params Update parameters
     * @return void
     */
    private function updateOrder($orderId, $params) {
        $order = $this->orderModel->findById($orderId);
        
        if (!$order) {
            Response::notFound('Order not found');
            return;
        }
        
        if (isset($params['status'])) {
            $success = $this->orderModel->updateOrderStatus($orderId, $params['status'], $params['notes'] ?? null);
            
            if ($success) {
                Logger::info('Order status updated by admin', [
                    'order_id' => $orderId,
                    'old_status' => $order['status'],
                    'new_status' => $params['status'],
                    'admin_user' => $_SESSION['admin_user']['id']
                ]);
                
                Response::success(['message' => 'Order updated successfully']);
            } else {
                Response::error('Failed to update order');
            }
        } else {
            Response::badRequest('No valid update parameters provided');
        }
    }
    
    /**
     * Get total revenue for a date range
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return float Total revenue
     */
    private function getTotalRevenue($startDate = null, $endDate = null) {
        $sql = "SELECT SUM(total_amount) FROM orders WHERE status IN ('completed', 'delivered')";
        $params = [];
        
        if ($startDate) {
            $sql .= " AND created_at >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND created_at < ?";
            $params[] = $endDate;
        }
        
        $result = $this->db->fetchColumn($sql, $params);
        return (float)($result ?? 0);
    }
    
    /**
     * Calculate percentage change
     * 
     * @param float $current Current value
     * @param float $previous Previous value
     * @return float Percentage change
     */
    private function calculatePercentageChange($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }
    
    /**
     * Get total amount spent by user
     * 
     * @param int $userId User ID
     * @return float Total spent
     */
    private function getTotalUserSpent($userId) {
        $sql = "SELECT SUM(total_amount) FROM orders 
                WHERE user_id = ? AND status IN ('completed', 'delivered')";
        
        $result = $this->db->fetchColumn($sql, [$userId]);
        return (float)($result ?? 0);
    }
    
    /**
     * Get last order date for user
     * 
     * @param int $userId User ID
     * @return string|null Last order date
     */
    private function getLastOrderDate($userId) {
        $sql = "SELECT MAX(created_at) FROM orders WHERE user_id = ?";
        return $this->db->fetchColumn($sql, [$userId]);
    }
    
    /**
     * Get server information
     * 
     * @return array Server info
     */
    private function getServerInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'operating_system' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
    }
    
    /**
     * Get database information
     * 
     * @return array Database info
     */
    private function getDatabaseInfo() {
        return $this->db->getDatabaseInfo();
    }
    
    /**
     * Get performance information
     * 
     * @return array Performance info
     */
    private function getPerformanceInfo() {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'database_stats' => $this->db->getConnectionStats()
        ];
    }
    
    /**
     * Get security information
     * 
     * @return array Security info
     */
    private function getSecurityInfo() {
        $rateLimiter = getRateLimiter();
        
        return [
            'rate_limiter_stats' => $rateLimiter->getStatistics(),
            'ssl_enabled' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'session_secure' => ini_get('session.cookie_secure'),
            'session_httponly' => ini_get('session.cookie_httponly')
        ];
    }
    
    /**
     * Get storage information
     * 
     * @return array Storage info
     */
    private function getStorageInfo() {
        $uploadDir = __DIR__ . '/../uploads/';
        $logDir = __DIR__ . '/../logs/';
        
        return [
            'upload_directory_size' => $this->getDirectorySize($uploadDir),
            'log_directory_size' => $this->getDirectorySize($logDir),
            'disk_free_space' => disk_free_space(__DIR__),
            'disk_total_space' => disk_total_space(__DIR__)
        ];
    }
    
    /**
     * Get directory size
     * 
     * @param string $directory Directory path
     * @return int Directory size in bytes
     */
    private function getDirectorySize($directory) {
        if (!is_dir($directory)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}