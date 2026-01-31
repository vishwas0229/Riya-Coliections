<?php
/**
 * PollingService Class
 * 
 * Provides polling-based real-time update functionality as an alternative to WebSocket
 * connections. Handles order status updates, notifications, and other real-time features
 * through efficient polling mechanisms.
 * 
 * Requirements: 18.1, 18.2, 18.4
 */

require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class PollingService {
    private $db;
    
    // Polling intervals (in seconds)
    const FAST_POLLING_INTERVAL = 5;    // For active order tracking
    const NORMAL_POLLING_INTERVAL = 30; // For general updates
    const SLOW_POLLING_INTERVAL = 60;   // For background updates
    
    // Update types
    const UPDATE_TYPE_ORDER_STATUS = 'order_status';
    const UPDATE_TYPE_PAYMENT_STATUS = 'payment_status';
    const UPDATE_TYPE_NOTIFICATION = 'notification';
    const UPDATE_TYPE_SYSTEM_ALERT = 'system_alert';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get updates for a specific user since a given timestamp
     * 
     * @param int $userId User ID
     * @param string $lastUpdate Last update timestamp (ISO 8601 format)
     * @param array $types Types of updates to fetch
     * @return array Updates data
     */
    public function getUserUpdates($userId, $lastUpdate = null, $types = []) {
        try {
            $updates = [];
            $lastUpdateTime = $lastUpdate ? date('Y-m-d H:i:s', strtotime($lastUpdate)) : date('Y-m-d H:i:s', strtotime('-1 hour'));
            
            // Default to all update types if none specified
            if (empty($types)) {
                $types = [
                    self::UPDATE_TYPE_ORDER_STATUS,
                    self::UPDATE_TYPE_PAYMENT_STATUS,
                    self::UPDATE_TYPE_NOTIFICATION
                ];
            }
            
            // Get order status updates
            if (in_array(self::UPDATE_TYPE_ORDER_STATUS, $types)) {
                $orderUpdates = $this->getOrderStatusUpdates($userId, $lastUpdateTime);
                $updates = array_merge($updates, $orderUpdates);
            }
            
            // Get payment status updates
            if (in_array(self::UPDATE_TYPE_PAYMENT_STATUS, $types)) {
                $paymentUpdates = $this->getPaymentStatusUpdates($userId, $lastUpdateTime);
                $updates = array_merge($updates, $paymentUpdates);
            }
            
            // Get notifications
            if (in_array(self::UPDATE_TYPE_NOTIFICATION, $types)) {
                $notifications = $this->getNotifications($userId, $lastUpdateTime);
                $updates = array_merge($updates, $notifications);
            }
            
            // Sort updates by timestamp
            usort($updates, function($a, $b) {
                return strtotime($a['timestamp']) - strtotime($b['timestamp']);
            });
            
            return [
                'updates' => $updates,
                'last_update' => date('c'), // ISO 8601 format
                'has_updates' => !empty($updates),
                'polling_interval' => $this->getRecommendedPollingInterval($updates)
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get user updates', [
                'user_id' => $userId,
                'last_update' => $lastUpdate,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get order status updates for a user
     * 
     * @param int $userId User ID
     * @param string $lastUpdateTime Last update timestamp
     * @return array Order status updates
     */
    private function getOrderStatusUpdates($userId, $lastUpdateTime) {
        $sql = "SELECT 
                    osh.id,
                    osh.order_id,
                    osh.status,
                    osh.notes,
                    osh.created_at,
                    o.order_number,
                    o.total_amount
                FROM order_status_history osh
                JOIN orders o ON osh.order_id = o.id
                WHERE o.user_id = ? 
                AND osh.created_at > ?
                ORDER BY osh.created_at DESC";
        
        $statusUpdates = $this->db->fetchAll($sql, [$userId, $lastUpdateTime]);
        
        $updates = [];
        foreach ($statusUpdates as $update) {
            $updates[] = [
                'id' => 'order_status_' . $update['id'],
                'type' => self::UPDATE_TYPE_ORDER_STATUS,
                'title' => 'Order Status Update',
                'message' => $this->getOrderStatusMessage($update['status'], $update['order_number']),
                'data' => [
                    'order_id' => (int)$update['order_id'],
                    'order_number' => $update['order_number'],
                    'status' => $update['status'],
                    'notes' => $update['notes'],
                    'total_amount' => (float)$update['total_amount']
                ],
                'timestamp' => $update['created_at'],
                'read' => false,
                'priority' => $this->getUpdatePriority($update['status'])
            ];
        }
        
        return $updates;
    }
    
    /**
     * Get payment status updates for a user
     * 
     * @param int $userId User ID
     * @param string $lastUpdateTime Last update timestamp
     * @return array Payment status updates
     */
    private function getPaymentStatusUpdates($userId, $lastUpdateTime) {
        $sql = "SELECT 
                    p.id,
                    p.order_id,
                    p.status,
                    p.payment_method,
                    p.amount,
                    p.updated_at,
                    o.order_number
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE o.user_id = ? 
                AND p.updated_at > ?
                AND p.status IN ('completed', 'failed', 'refunded')
                ORDER BY p.updated_at DESC";
        
        $paymentUpdates = $this->db->fetchAll($sql, [$userId, $lastUpdateTime]);
        
        $updates = [];
        foreach ($paymentUpdates as $update) {
            $updates[] = [
                'id' => 'payment_status_' . $update['id'],
                'type' => self::UPDATE_TYPE_PAYMENT_STATUS,
                'title' => 'Payment Status Update',
                'message' => $this->getPaymentStatusMessage($update['status'], $update['order_number']),
                'data' => [
                    'order_id' => (int)$update['order_id'],
                    'order_number' => $update['order_number'],
                    'payment_status' => $update['status'],
                    'payment_method' => $update['payment_method'],
                    'amount' => (float)$update['amount']
                ],
                'timestamp' => $update['updated_at'],
                'read' => false,
                'priority' => $this->getPaymentUpdatePriority($update['status'])
            ];
        }
        
        return $updates;
    }
    
    /**
     * Get notifications for a user
     * 
     * @param int $userId User ID
     * @param string $lastUpdateTime Last update timestamp
     * @return array Notifications
     */
    private function getNotifications($userId, $lastUpdateTime) {
        // Check if notifications table exists, if not return empty array
        $tableExists = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() AND table_name = 'notifications'"
        );
        
        if (!$tableExists) {
            return [];
        }
        
        $sql = "SELECT 
                    id,
                    type,
                    title,
                    message,
                    data,
                    created_at,
                    is_read
                FROM notifications
                WHERE user_id = ? 
                AND created_at > ?
                ORDER BY created_at DESC";
        
        $notifications = $this->db->fetchAll($sql, [$userId, $lastUpdateTime]);
        
        $updates = [];
        foreach ($notifications as $notification) {
            $updates[] = [
                'id' => 'notification_' . $notification['id'],
                'type' => self::UPDATE_TYPE_NOTIFICATION,
                'title' => $notification['title'],
                'message' => $notification['message'],
                'data' => json_decode($notification['data'] ?? '{}', true),
                'timestamp' => $notification['created_at'],
                'read' => (bool)$notification['is_read'],
                'priority' => 'normal'
            ];
        }
        
        return $updates;
    }
    
    /**
     * Get order status updates for a specific order
     * 
     * @param int $orderId Order ID
     * @param int $userId User ID (for security)
     * @param string $lastUpdate Last update timestamp
     * @return array Order-specific updates
     */
    public function getOrderUpdates($orderId, $userId, $lastUpdate = null) {
        try {
            $lastUpdateTime = $lastUpdate ? date('Y-m-d H:i:s', strtotime($lastUpdate)) : date('Y-m-d H:i:s', strtotime('-1 hour'));
            
            // Verify order belongs to user
            $orderExists = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM orders WHERE id = ? AND user_id = ?",
                [$orderId, $userId]
            );
            
            if (!$orderExists) {
                throw new Exception('Order not found or access denied', 404);
            }
            
            // Get order status history
            $sql = "SELECT 
                        osh.id,
                        osh.status,
                        osh.notes,
                        osh.created_at,
                        o.order_number,
                        o.total_amount,
                        o.payment_status
                    FROM order_status_history osh
                    JOIN orders o ON osh.order_id = o.id
                    WHERE osh.order_id = ? 
                    AND osh.created_at > ?
                    ORDER BY osh.created_at DESC";
            
            $statusUpdates = $this->db->fetchAll($sql, [$orderId, $lastUpdateTime]);
            
            // Get payment updates for this order
            $paymentSql = "SELECT 
                            p.id,
                            p.status,
                            p.payment_method,
                            p.amount,
                            p.updated_at
                        FROM payments p
                        WHERE p.order_id = ? 
                        AND p.updated_at > ?
                        ORDER BY p.updated_at DESC";
            
            $paymentUpdates = $this->db->fetchAll($paymentSql, [$orderId, $lastUpdateTime]);
            
            $updates = [];
            
            // Process status updates
            foreach ($statusUpdates as $update) {
                $updates[] = [
                    'id' => 'order_status_' . $update['id'],
                    'type' => self::UPDATE_TYPE_ORDER_STATUS,
                    'title' => 'Order Status Update',
                    'message' => $this->getOrderStatusMessage($update['status'], $update['order_number']),
                    'data' => [
                        'order_id' => $orderId,
                        'order_number' => $update['order_number'],
                        'status' => $update['status'],
                        'notes' => $update['notes'],
                        'total_amount' => (float)$update['total_amount'],
                        'payment_status' => $update['payment_status']
                    ],
                    'timestamp' => $update['created_at'],
                    'read' => false,
                    'priority' => $this->getUpdatePriority($update['status'])
                ];
            }
            
            // Process payment updates
            foreach ($paymentUpdates as $update) {
                $updates[] = [
                    'id' => 'payment_status_' . $update['id'],
                    'type' => self::UPDATE_TYPE_PAYMENT_STATUS,
                    'title' => 'Payment Status Update',
                    'message' => $this->getPaymentStatusMessage($update['status'], null),
                    'data' => [
                        'order_id' => $orderId,
                        'payment_status' => $update['status'],
                        'payment_method' => $update['payment_method'],
                        'amount' => (float)$update['amount']
                    ],
                    'timestamp' => $update['updated_at'],
                    'read' => false,
                    'priority' => $this->getPaymentUpdatePriority($update['status'])
                ];
            }
            
            // Sort by timestamp
            usort($updates, function($a, $b) {
                return strtotime($a['timestamp']) - strtotime($b['timestamp']);
            });
            
            return [
                'updates' => $updates,
                'last_update' => date('c'),
                'has_updates' => !empty($updates),
                'polling_interval' => $this->getRecommendedPollingInterval($updates)
            ];
            
        } catch (Exception $e) {
            Logger::error('Failed to get order updates', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create a notification for a user
     * 
     * @param int $userId User ID
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data
     * @return bool Success status
     */
    public function createNotification($userId, $type, $title, $message, $data = []) {
        try {
            // Create notifications table if it doesn't exist
            $this->createNotificationsTable();
            
            $sql = "INSERT INTO notifications (user_id, type, title, message, data, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            
            $this->db->executeQuery($sql, [
                $userId,
                $type,
                $title,
                $message,
                json_encode($data)
            ]);
            
            Logger::info('Notification created', [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to create notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Mark notifications as read
     * 
     * @param array $notificationIds Notification IDs
     * @param int $userId User ID (for security)
     * @return bool Success status
     */
    public function markNotificationsAsRead($notificationIds, $userId) {
        try {
            if (empty($notificationIds)) {
                return true;
            }
            
            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
            $sql = "UPDATE notifications 
                    SET is_read = 1, updated_at = NOW() 
                    WHERE id IN ({$placeholders}) AND user_id = ?";
            
            $params = array_merge($notificationIds, [$userId]);
            $this->db->executeQuery($sql, $params);
            
            Logger::info('Notifications marked as read', [
                'user_id' => $userId,
                'notification_ids' => $notificationIds
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Failed to mark notifications as read', [
                'user_id' => $userId,
                'notification_ids' => $notificationIds,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get recommended polling interval based on update activity
     * 
     * @param array $updates Recent updates
     * @return int Polling interval in seconds
     */
    private function getRecommendedPollingInterval($updates) {
        if (empty($updates)) {
            return self::SLOW_POLLING_INTERVAL;
        }
        
        // Check for high-priority updates
        $highPriorityCount = 0;
        foreach ($updates as $update) {
            if ($update['priority'] === 'high') {
                $highPriorityCount++;
            }
        }
        
        // If there are high-priority updates, use fast polling
        if ($highPriorityCount > 0) {
            return self::FAST_POLLING_INTERVAL;
        }
        
        // If there are recent updates, use normal polling
        if (count($updates) > 0) {
            return self::NORMAL_POLLING_INTERVAL;
        }
        
        return self::SLOW_POLLING_INTERVAL;
    }
    
    /**
     * Get order status message
     * 
     * @param string $status Order status
     * @param string $orderNumber Order number
     * @return string Status message
     */
    private function getOrderStatusMessage($status, $orderNumber) {
        $messages = [
            'pending' => "Your order {$orderNumber} is pending confirmation.",
            'confirmed' => "Your order {$orderNumber} has been confirmed and is being prepared.",
            'processing' => "Your order {$orderNumber} is currently being processed.",
            'shipped' => "Great news! Your order {$orderNumber} has been shipped and is on its way.",
            'delivered' => "Your order {$orderNumber} has been delivered successfully.",
            'cancelled' => "Your order {$orderNumber} has been cancelled.",
            'refunded' => "Your order {$orderNumber} has been refunded."
        ];
        
        return $messages[$status] ?? "Your order {$orderNumber} status has been updated to {$status}.";
    }
    
    /**
     * Get payment status message
     * 
     * @param string $status Payment status
     * @param string $orderNumber Order number
     * @return string Status message
     */
    private function getPaymentStatusMessage($status, $orderNumber) {
        $messages = [
            'completed' => $orderNumber ? "Payment for order {$orderNumber} has been completed successfully." : "Payment has been completed successfully.",
            'failed' => $orderNumber ? "Payment for order {$orderNumber} has failed. Please try again." : "Payment has failed. Please try again.",
            'refunded' => $orderNumber ? "Payment for order {$orderNumber} has been refunded." : "Payment has been refunded."
        ];
        
        return $messages[$status] ?? "Payment status has been updated to {$status}.";
    }
    
    /**
     * Get update priority based on order status
     * 
     * @param string $status Order status
     * @return string Priority level
     */
    private function getUpdatePriority($status) {
        $highPriorityStatuses = ['shipped', 'delivered', 'cancelled'];
        $normalPriorityStatuses = ['confirmed', 'processing'];
        
        if (in_array($status, $highPriorityStatuses)) {
            return 'high';
        } elseif (in_array($status, $normalPriorityStatuses)) {
            return 'normal';
        }
        
        return 'low';
    }
    
    /**
     * Get payment update priority
     * 
     * @param string $status Payment status
     * @return string Priority level
     */
    private function getPaymentUpdatePriority($status) {
        $highPriorityStatuses = ['completed', 'failed'];
        
        if (in_array($status, $highPriorityStatuses)) {
            return 'high';
        }
        
        return 'normal';
    }
    
    /**
     * Create notifications table if it doesn't exist
     */
    private function createNotificationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            data JSON,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_user_read (user_id, is_read),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $this->db->executeQuery($sql);
    }
}