# Polling System Implementation

## Overview

The polling system provides real-time update functionality as an alternative to WebSocket connections. It allows clients to efficiently poll for order status updates, payment notifications, and other real-time features through REST API endpoints.

## Features

- **Order Status Updates**: Real-time tracking of order status changes
- **Payment Notifications**: Instant updates on payment status
- **User Notifications**: System alerts and custom notifications
- **Adaptive Polling**: Dynamic polling intervals based on activity
- **Efficient Querying**: Timestamp-based incremental updates

## API Endpoints

### 1. Get User Updates
```
GET /api/polling/updates?last_update=2024-01-31T10:00:00Z&types=order_status,payment_status
```

**Query Parameters:**
- `last_update` (optional): ISO 8601 timestamp of last update
- `types` (optional): Comma-separated list of update types

**Response:**
```json
{
  "success": true,
  "message": "Updates retrieved successfully",
  "data": {
    "updates": [
      {
        "id": "order_status_123",
        "type": "order_status",
        "title": "Order Status Update",
        "message": "Your order RC20240131001 has been shipped and is on its way.",
        "data": {
          "order_id": 123,
          "order_number": "RC20240131001",
          "status": "shipped",
          "notes": "Shipped via FedEx",
          "total_amount": 1299.99
        },
        "timestamp": "2024-01-31T10:15:00Z",
        "read": false,
        "priority": "high"
      }
    ],
    "last_update": "2024-01-31T10:30:00Z",
    "has_updates": true,
    "polling_interval": 30
  }
}
```

### 2. Get Order-Specific Updates
```
GET /api/polling/orders/123/updates?last_update=2024-01-31T10:00:00Z
```

**Response:**
```json
{
  "success": true,
  "message": "Order updates retrieved successfully",
  "data": {
    "updates": [
      {
        "id": "order_status_456",
        "type": "order_status",
        "title": "Order Status Update",
        "message": "Your order RC20240131001 status has been updated to processing.",
        "data": {
          "order_id": 123,
          "order_number": "RC20240131001",
          "status": "processing",
          "notes": "Order is being prepared",
          "total_amount": 1299.99,
          "payment_status": "completed"
        },
        "timestamp": "2024-01-31T10:20:00Z",
        "read": false,
        "priority": "normal"
      }
    ],
    "last_update": "2024-01-31T10:30:00Z",
    "has_updates": true,
    "polling_interval": 30
  }
}
```

### 3. Mark Notifications as Read
```
POST /api/polling/notifications/read
Content-Type: application/json

{
  "notification_ids": [1, 2, 3]
}
```

### 4. Create Notification (Admin Only)
```
POST /api/polling/notifications
Content-Type: application/json

{
  "user_id": 123,
  "type": "system_alert",
  "title": "System Maintenance",
  "message": "System will be under maintenance from 2 AM to 4 AM.",
  "data": {
    "maintenance_window": "2024-02-01T02:00:00Z to 2024-02-01T04:00:00Z"
  }
}
```

### 5. Get Polling Configuration
```
GET /api/polling/config
```

**Response:**
```json
{
  "success": true,
  "message": "Polling configuration retrieved successfully",
  "data": {
    "intervals": {
      "fast": 5,
      "normal": 30,
      "slow": 60
    },
    "update_types": [
      "order_status",
      "payment_status",
      "notification",
      "system_alert"
    ],
    "endpoints": {
      "updates": "/api/polling/updates",
      "order_updates": "/api/polling/orders/{id}/updates",
      "mark_read": "/api/polling/notifications/read"
    },
    "recommendations": {
      "use_fast_polling_for": ["active_order_tracking", "payment_processing"],
      "use_normal_polling_for": ["general_updates", "notifications"],
      "use_slow_polling_for": ["background_sync", "idle_state"]
    }
  }
}
```

## Client-Side Implementation

### JavaScript Polling Client

```javascript
class PollingClient {
    constructor(baseUrl, authToken) {
        this.baseUrl = baseUrl;
        this.authToken = authToken;
        this.lastUpdate = null;
        this.pollingInterval = 30000; // Default 30 seconds
        this.isPolling = false;
        this.timeoutId = null;
        this.callbacks = {
            onUpdate: [],
            onError: [],
            onConnect: [],
            onDisconnect: []
        };
    }

    // Start polling for updates
    startPolling(types = []) {
        if (this.isPolling) {
            return;
        }

        this.isPolling = true;
        this.triggerCallbacks('onConnect');
        this.poll(types);
    }

    // Stop polling
    stopPolling() {
        if (!this.isPolling) {
            return;
        }

        this.isPolling = false;
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
            this.timeoutId = null;
        }
        this.triggerCallbacks('onDisconnect');
    }

    // Perform a single poll
    async poll(types = []) {
        if (!this.isPolling) {
            return;
        }

        try {
            const url = new URL(`${this.baseUrl}/api/polling/updates`);
            
            if (this.lastUpdate) {
                url.searchParams.set('last_update', this.lastUpdate);
            }
            
            if (types.length > 0) {
                url.searchParams.set('types', types.join(','));
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.authToken}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success && result.data) {
                this.lastUpdate = result.data.last_update;
                
                // Update polling interval based on server recommendation
                if (result.data.polling_interval) {
                    this.pollingInterval = result.data.polling_interval * 1000;
                }

                // Trigger update callbacks if there are new updates
                if (result.data.has_updates && result.data.updates.length > 0) {
                    this.triggerCallbacks('onUpdate', result.data.updates);
                }
            }

        } catch (error) {
            console.error('Polling error:', error);
            this.triggerCallbacks('onError', error);
            
            // Increase polling interval on error (exponential backoff)
            this.pollingInterval = Math.min(this.pollingInterval * 1.5, 60000);
        }

        // Schedule next poll
        if (this.isPolling) {
            this.timeoutId = setTimeout(() => this.poll(types), this.pollingInterval);
        }
    }

    // Poll for specific order updates
    async pollOrderUpdates(orderId) {
        try {
            const url = new URL(`${this.baseUrl}/api/polling/orders/${orderId}/updates`);
            
            if (this.lastUpdate) {
                url.searchParams.set('last_update', this.lastUpdate);
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.authToken}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success && result.data && result.data.has_updates) {
                return result.data.updates;
            }

            return [];

        } catch (error) {
            console.error('Order polling error:', error);
            this.triggerCallbacks('onError', error);
            return [];
        }
    }

    // Mark notifications as read
    async markNotificationsRead(notificationIds) {
        try {
            const response = await fetch(`${this.baseUrl}/api/polling/notifications/read`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.authToken}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_ids: notificationIds
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            return result.success;

        } catch (error) {
            console.error('Mark notifications read error:', error);
            return false;
        }
    }

    // Add event listener
    on(event, callback) {
        if (this.callbacks[event]) {
            this.callbacks[event].push(callback);
        }
    }

    // Remove event listener
    off(event, callback) {
        if (this.callbacks[event]) {
            const index = this.callbacks[event].indexOf(callback);
            if (index > -1) {
                this.callbacks[event].splice(index, 1);
            }
        }
    }

    // Trigger callbacks
    triggerCallbacks(event, data = null) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Callback error for ${event}:`, error);
                }
            });
        }
    }
}

// Usage example
const pollingClient = new PollingClient('https://api.riyacollections.com', 'your-jwt-token');

// Set up event listeners
pollingClient.on('onUpdate', (updates) => {
    console.log('New updates received:', updates);
    
    updates.forEach(update => {
        switch (update.type) {
            case 'order_status':
                showOrderStatusNotification(update);
                break;
            case 'payment_status':
                showPaymentNotification(update);
                break;
            case 'notification':
                showGeneralNotification(update);
                break;
        }
    });
});

pollingClient.on('onError', (error) => {
    console.error('Polling error:', error);
    showErrorMessage('Connection error. Retrying...');
});

pollingClient.on('onConnect', () => {
    console.log('Polling started');
    showSuccessMessage('Connected to real-time updates');
});

pollingClient.on('onDisconnect', () => {
    console.log('Polling stopped');
    showInfoMessage('Disconnected from real-time updates');
});

// Start polling for all update types
pollingClient.startPolling();

// Or start polling for specific types
// pollingClient.startPolling(['order_status', 'payment_status']);

// Stop polling when page is unloaded
window.addEventListener('beforeunload', () => {
    pollingClient.stopPolling();
});
```

### Order Tracking Page Example

```javascript
// Order tracking specific implementation
class OrderTracker {
    constructor(orderId, pollingClient) {
        this.orderId = orderId;
        this.pollingClient = pollingClient;
        this.isTracking = false;
    }

    startTracking() {
        if (this.isTracking) {
            return;
        }

        this.isTracking = true;
        
        // Use fast polling for active order tracking
        this.pollingClient.pollingInterval = 5000; // 5 seconds
        
        this.trackOrder();
    }

    stopTracking() {
        this.isTracking = false;
    }

    async trackOrder() {
        if (!this.isTracking) {
            return;
        }

        try {
            const updates = await this.pollingClient.pollOrderUpdates(this.orderId);
            
            if (updates.length > 0) {
                this.handleOrderUpdates(updates);
            }

        } catch (error) {
            console.error('Order tracking error:', error);
        }

        // Continue tracking
        if (this.isTracking) {
            setTimeout(() => this.trackOrder(), this.pollingClient.pollingInterval);
        }
    }

    handleOrderUpdates(updates) {
        updates.forEach(update => {
            if (update.type === 'order_status') {
                this.updateOrderStatus(update.data);
            } else if (update.type === 'payment_status') {
                this.updatePaymentStatus(update.data);
            }
        });
    }

    updateOrderStatus(data) {
        // Update UI with new order status
        const statusElement = document.getElementById('order-status');
        if (statusElement) {
            statusElement.textContent = data.status;
            statusElement.className = `status status-${data.status}`;
        }

        // Add to status history
        const historyElement = document.getElementById('status-history');
        if (historyElement) {
            const historyItem = document.createElement('div');
            historyItem.className = 'status-history-item';
            historyItem.innerHTML = `
                <div class="status-time">${new Date(data.timestamp).toLocaleString()}</div>
                <div class="status-message">${data.message}</div>
                ${data.notes ? `<div class="status-notes">${data.notes}</div>` : ''}
            `;
            historyElement.prepend(historyItem);
        }

        // Show notification
        this.showNotification(data.message, 'success');
    }

    updatePaymentStatus(data) {
        const paymentElement = document.getElementById('payment-status');
        if (paymentElement) {
            paymentElement.textContent = data.payment_status;
            paymentElement.className = `payment-status payment-${data.payment_status}`;
        }

        this.showNotification(`Payment ${data.payment_status}`, 'info');
    }

    showNotification(message, type = 'info') {
        // Create and show notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
}

// Usage on order tracking page
const orderId = 123; // Get from URL or page data
const pollingClient = new PollingClient('https://api.riyacollections.com', 'your-jwt-token');
const orderTracker = new OrderTracker(orderId, pollingClient);

// Start tracking when page loads
orderTracker.startTracking();

// Stop tracking when page is unloaded
window.addEventListener('beforeunload', () => {
    orderTracker.stopTracking();
});
```

## Update Types

### Order Status Updates
- **Type**: `order_status`
- **Triggers**: Order status changes (pending → confirmed → processing → shipped → delivered)
- **Priority**: High for shipped/delivered, Normal for others
- **Polling Interval**: Fast (5s) for active orders, Normal (30s) for others

### Payment Status Updates
- **Type**: `payment_status`
- **Triggers**: Payment completion, failure, or refund
- **Priority**: High for completed/failed, Normal for others
- **Polling Interval**: Fast (5s) during payment processing

### Notifications
- **Type**: `notification`
- **Triggers**: System alerts, promotional messages, account updates
- **Priority**: Normal
- **Polling Interval**: Normal (30s)

### System Alerts
- **Type**: `system_alert`
- **Triggers**: Maintenance notifications, service disruptions
- **Priority**: High
- **Polling Interval**: Fast (5s) during active alerts

## Performance Considerations

### Adaptive Polling Intervals
- **Fast Polling (5s)**: Active order tracking, payment processing
- **Normal Polling (30s)**: General updates, notifications
- **Slow Polling (60s)**: Background sync, idle state

### Efficient Querying
- Timestamp-based incremental updates
- Type-specific filtering
- Pagination for large result sets
- Database indexing on user_id and timestamps

### Error Handling
- Exponential backoff on errors
- Graceful degradation
- Connection retry logic
- User-friendly error messages

## Security Features

- JWT token authentication
- User-specific data isolation
- Admin-only notification creation
- Input validation and sanitization
- Rate limiting protection

## Database Schema

### Notifications Table
```sql
CREATE TABLE notifications (
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
);
```

### Order Status History (Existing)
```sql
CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_created (order_id, created_at),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```

## Testing

The polling system includes comprehensive property-based testing to ensure:
- Real-time update equivalence with WebSocket functionality
- Correct timestamp-based filtering
- Proper authentication and authorization
- Performance under load
- Error handling and recovery

## Deployment Notes

1. **Database Indexes**: Ensure proper indexing on timestamp and user_id columns
2. **Caching**: Consider Redis for high-frequency polling scenarios
3. **Load Balancing**: Stateless design supports horizontal scaling
4. **Monitoring**: Track polling frequency and response times
5. **Rate Limiting**: Implement per-user polling rate limits

This polling system provides equivalent functionality to WebSocket-based real-time updates while being compatible with standard PHP hosting environments.