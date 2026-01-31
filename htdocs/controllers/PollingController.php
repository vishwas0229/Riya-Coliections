<?php
/**
 * PollingController Class
 * 
 * Handles polling-based real-time update endpoints for order status tracking,
 * notifications, and other real-time features. Provides REST API endpoints
 * for efficient client-side polling.
 * 
 * Requirements: 18.1, 18.2, 18.4
 */

require_once __DIR__ . '/../services/PollingService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class PollingController {
    private $pollingService;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->pollingService = new PollingService();
    }
    
    /**
     * GET /api/polling/updates - Get all updates for authenticated user
     * 
     * Query parameters:
     * - last_update: ISO 8601 timestamp of last update
     * - types: Comma-separated list of update types to fetch
     */
    public function getUpdates() {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Get query parameters
            $lastUpdate = $_GET['last_update'] ?? null;
            $typesParam = $_GET['types'] ?? '';
            
            // Parse update types
            $types = [];
            if (!empty($typesParam)) {
                $types = array_map('trim', explode(',', $typesParam));
                
                // Validate update types
                $validTypes = [
                    PollingService::UPDATE_TYPE_ORDER_STATUS,
                    PollingService::UPDATE_TYPE_PAYMENT_STATUS,
                    PollingService::UPDATE_TYPE_NOTIFICATION,
                    PollingService::UPDATE_TYPE_SYSTEM_ALERT
                ];
                
                $types = array_intersect($types, $validTypes);
            }
            
            // Get updates
            $updates = $this->pollingService->getUserUpdates($user['id'], $lastUpdate, $types);
            
            Logger::info('User updates retrieved', [
                'user_id' => $user['id'],
                'last_update' => $lastUpdate,
                'types' => $types,
                'update_count' => count($updates['updates'])
            ]);
            
            Response::success('Updates retrieved successfully', $updates);
            
        } catch (Exception $e) {
            Logger::error('Failed to get user updates', [
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * GET /api/polling/orders/{id}/updates - Get updates for a specific order
     * 
     * Query parameters:
     * - last_update: ISO 8601 timestamp of last update
     */
    public function getOrderUpdates($orderId) {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Validate order ID
            if (!is_numeric($orderId) || $orderId <= 0) {
                Response::error('Invalid order ID', 400);
                return;
            }
            
            // Get query parameters
            $lastUpdate = $_GET['last_update'] ?? null;
            
            // Get order updates
            $updates = $this->pollingService->getOrderUpdates($orderId, $user['id'], $lastUpdate);
            
            Logger::info('Order updates retrieved', [
                'user_id' => $user['id'],
                'order_id' => $orderId,
                'last_update' => $lastUpdate,
                'update_count' => count($updates['updates'])
            ]);
            
            Response::success('Order updates retrieved successfully', $updates);
            
        } catch (Exception $e) {
            Logger::error('Failed to get order updates', [
                'user_id' => $user['id'] ?? 'unknown',
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * POST /api/polling/notifications/read - Mark notifications as read
     * 
     * Request body:
     * {
     *   "notification_ids": [1, 2, 3]
     * }
     */
    public function markNotificationsRead() {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['notification_ids']) || !is_array($input['notification_ids'])) {
                Response::error('Notification IDs are required', 400);
                return;
            }
            
            $notificationIds = array_map('intval', $input['notification_ids']);
            
            // Mark notifications as read
            $success = $this->pollingService->markNotificationsAsRead($notificationIds, $user['id']);
            
            if (!$success) {
                Response::error('Failed to mark notifications as read', 500);
                return;
            }
            
            Logger::info('Notifications marked as read', [
                'user_id' => $user['id'],
                'notification_ids' => $notificationIds
            ]);
            
            Response::success('Notifications marked as read successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to mark notifications as read', [
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * POST /api/polling/notifications - Create a notification (Admin only)
     * 
     * Request body:
     * {
     *   "user_id": 123,
     *   "type": "system_alert",
     *   "title": "System Maintenance",
     *   "message": "System will be under maintenance...",
     *   "data": {}
     * }
     */
    public function createNotification() {
        try {
            // Authenticate admin
            $user = AuthMiddleware::authenticate();
            if (!$user || !AuthMiddleware::hasRole(AuthMiddleware::ROLE_ADMIN)) {
                Response::unauthorized('Admin access required');
                return;
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON data', 400);
                return;
            }
            
            // Validate required fields
            $requiredFields = ['user_id', 'type', 'title', 'message'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    Response::error("Field '{$field}' is required", 400);
                    return;
                }
            }
            
            $userId = (int)$input['user_id'];
            $type = $input['type'];
            $title = $input['title'];
            $message = $input['message'];
            $data = $input['data'] ?? [];
            
            // Create notification
            $success = $this->pollingService->createNotification($userId, $type, $title, $message, $data);
            
            if (!$success) {
                Response::error('Failed to create notification', 500);
                return;
            }
            
            Logger::info('Notification created by admin', [
                'admin_id' => $user['id'],
                'user_id' => $userId,
                'type' => $type,
                'title' => $title
            ]);
            
            Response::success('Notification created successfully');
            
        } catch (Exception $e) {
            Logger::error('Failed to create notification', [
                'admin_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * GET /api/polling/config - Get polling configuration for client
     */
    public function getPollingConfig() {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }
            
            $config = [
                'intervals' => [
                    'fast' => PollingService::FAST_POLLING_INTERVAL,
                    'normal' => PollingService::NORMAL_POLLING_INTERVAL,
                    'slow' => PollingService::SLOW_POLLING_INTERVAL
                ],
                'update_types' => [
                    PollingService::UPDATE_TYPE_ORDER_STATUS,
                    PollingService::UPDATE_TYPE_PAYMENT_STATUS,
                    PollingService::UPDATE_TYPE_NOTIFICATION,
                    PollingService::UPDATE_TYPE_SYSTEM_ALERT
                ],
                'endpoints' => [
                    'updates' => '/api/polling/updates',
                    'order_updates' => '/api/polling/orders/{id}/updates',
                    'mark_read' => '/api/polling/notifications/read'
                ],
                'recommendations' => [
                    'use_fast_polling_for' => ['active_order_tracking', 'payment_processing'],
                    'use_normal_polling_for' => ['general_updates', 'notifications'],
                    'use_slow_polling_for' => ['background_sync', 'idle_state']
                ]
            ];
            
            Response::success('Polling configuration retrieved successfully', $config);
            
        } catch (Exception $e) {
            Logger::error('Failed to get polling configuration', [
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * GET /api/polling/health - Health check for polling service
     */
    public function healthCheck() {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'service' => 'polling',
                'version' => '1.0.0',
                'checks' => [
                    'database' => 'ok',
                    'notifications_table' => 'ok'
                ]
            ];
            
            // Test database connection
            try {
                $db = Database::getInstance();
                $db->testConnection();
            } catch (Exception $e) {
                $health['status'] = 'unhealthy';
                $health['checks']['database'] = 'failed';
                $health['errors'][] = 'Database connection failed';
            }
            
            // Check notifications table
            try {
                $db = Database::getInstance();
                $tableExists = $db->fetchColumn(
                    "SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = DATABASE() AND table_name = 'notifications'"
                );
                
                if (!$tableExists) {
                    $health['checks']['notifications_table'] = 'missing';
                    $health['warnings'][] = 'Notifications table does not exist';
                }
            } catch (Exception $e) {
                $health['checks']['notifications_table'] = 'error';
                $health['warnings'][] = 'Could not check notifications table';
            }
            
            $statusCode = ($health['status'] === 'healthy') ? 200 : 503;
            Response::json($health, $statusCode);
            
        } catch (Exception $e) {
            Logger::error('Polling health check failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::error('Health check failed', 500);
        }
    }
}