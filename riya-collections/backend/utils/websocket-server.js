/**
 * WebSocket Server for Real-time Updates
 * Handles real-time communication for cart, inventory, and order updates
 */

const WebSocket = require('ws');
const jwt = require('jsonwebtoken');
const { appLogger } = require('../config/logging');

class WebSocketServer {
  constructor(server) {
    this.wss = new WebSocket.Server({ 
      server,
      path: '/ws',
      verifyClient: this.verifyClient.bind(this)
    });
    
    this.clients = new Map(); // Map of userId -> Set of WebSocket connections
    this.adminClients = new Set(); // Set of admin WebSocket connections
    
    this.setupEventHandlers();
    appLogger.info('WebSocket server initialized');
  }

  /**
   * Verify client connection
   */
  verifyClient(info) {
    const url = new URL(info.req.url, 'http://localhost');
    const token = url.searchParams.get('token');
    
    if (!token) {
      return false;
    }
    
    try {
      const decoded = jwt.verify(token, process.env.JWT_SECRET);
      info.req.user = decoded;
      return true;
    } catch (error) {
      appLogger.warn('WebSocket authentication failed:', error.message);
      return false;
    }
  }

  /**
   * Setup WebSocket event handlers
   */
  setupEventHandlers() {
    this.wss.on('connection', (ws, req) => {
      const user = req.user;
      
      // Store client connection
      if (user.isAdmin) {
        this.adminClients.add(ws);
        appLogger.info(`Admin WebSocket connected: ${user.id}`);
      } else {
        if (!this.clients.has(user.id)) {
          this.clients.set(user.id, new Set());
        }
        this.clients.get(user.id).add(ws);
        appLogger.info(`User WebSocket connected: ${user.id}`);
      }

      // Handle client messages
      ws.on('message', (message) => {
        try {
          const data = JSON.parse(message);
          this.handleClientMessage(ws, user, data);
        } catch (error) {
          appLogger.error('Invalid WebSocket message:', error);
        }
      });

      // Handle client disconnect
      ws.on('close', () => {
        if (user.isAdmin) {
          this.adminClients.delete(ws);
          appLogger.info(`Admin WebSocket disconnected: ${user.id}`);
        } else {
          const userConnections = this.clients.get(user.id);
          if (userConnections) {
            userConnections.delete(ws);
            if (userConnections.size === 0) {
              this.clients.delete(user.id);
            }
          }
          appLogger.info(`User WebSocket disconnected: ${user.id}`);
        }
      });

      // Send welcome message
      ws.send(JSON.stringify({
        type: 'connected',
        message: 'WebSocket connection established',
        timestamp: Date.now()
      }));
    });
  }

  /**
   * Handle client messages
   */
  handleClientMessage(ws, user, data) {
    switch (data.type) {
      case 'ping':
        ws.send(JSON.stringify({ type: 'pong', timestamp: Date.now() }));
        break;
        
      case 'subscribe':
        // Handle subscription requests
        this.handleSubscription(ws, user, data);
        break;
        
      default:
        appLogger.warn(`Unknown WebSocket message type: ${data.type}`);
    }
  }

  /**
   * Handle subscription requests
   */
  handleSubscription(ws, user, data) {
    const { channels } = data;
    
    // Store subscription info on the WebSocket
    ws.subscriptions = ws.subscriptions || new Set();
    
    channels.forEach(channel => {
      ws.subscriptions.add(channel);
    });
    
    ws.send(JSON.stringify({
      type: 'subscribed',
      channels: Array.from(ws.subscriptions),
      timestamp: Date.now()
    }));
  }

  /**
   * Broadcast cart update to user
   */
  broadcastCartUpdate(userId, cartData) {
    const userConnections = this.clients.get(userId);
    if (userConnections) {
      const message = JSON.stringify({
        type: 'cart_updated',
        data: cartData,
        timestamp: Date.now()
      });
      
      userConnections.forEach(ws => {
        if (ws.readyState === WebSocket.OPEN && 
            ws.subscriptions?.has('cart')) {
          ws.send(message);
        }
      });
    }
  }

  /**
   * Broadcast inventory update to all connected clients
   */
  broadcastInventoryUpdate(inventoryChanges) {
    const message = JSON.stringify({
      type: 'inventory_updated',
      data: inventoryChanges,
      timestamp: Date.now()
    });

    // Send to all user connections
    this.clients.forEach((connections, userId) => {
      connections.forEach(ws => {
        if (ws.readyState === WebSocket.OPEN && 
            ws.subscriptions?.has('inventory')) {
          ws.send(message);
        }
      });
    });

    // Send to admin connections
    this.adminClients.forEach(ws => {
      if (ws.readyState === WebSocket.OPEN && 
          ws.subscriptions?.has('inventory')) {
        ws.send(message);
      }
    });
  }

  /**
   * Broadcast order update to user and admins
   */
  broadcastOrderUpdate(userId, orderData) {
    const message = JSON.stringify({
      type: 'order_updated',
      data: orderData,
      timestamp: Date.now()
    });

    // Send to specific user
    const userConnections = this.clients.get(userId);
    if (userConnections) {
      userConnections.forEach(ws => {
        if (ws.readyState === WebSocket.OPEN && 
            ws.subscriptions?.has('orders')) {
          ws.send(message);
        }
      });
    }

    // Send to all admin connections
    this.adminClients.forEach(ws => {
      if (ws.readyState === WebSocket.OPEN && 
          ws.subscriptions?.has('orders')) {
        ws.send(message);
      }
    });
  }

  /**
   * Broadcast admin notification to all admin clients
   */
  broadcastAdminNotification(notification) {
    const message = JSON.stringify({
      type: 'admin_notification',
      data: notification,
      timestamp: Date.now()
    });

    this.adminClients.forEach(ws => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(message);
      }
    });
  }

  /**
   * Get connection statistics
   */
  getStats() {
    return {
      totalConnections: this.clients.size + this.adminClients.size,
      userConnections: this.clients.size,
      adminConnections: this.adminClients.size,
      totalSockets: Array.from(this.clients.values()).reduce((sum, set) => sum + set.size, 0) + this.adminClients.size
    };
  }

  /**
   * Close all connections
   */
  close() {
    this.clients.forEach((connections) => {
      connections.forEach(ws => ws.close());
    });
    
    this.adminClients.forEach(ws => ws.close());
    
    this.wss.close();
    appLogger.info('WebSocket server closed');
  }
}

module.exports = WebSocketServer;