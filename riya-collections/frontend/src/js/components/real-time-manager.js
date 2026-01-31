/**
 * Real-time UI Updates Manager for Riya Collections
 * Handles real-time updates for cart, inventory, and order status changes
 */

class RealTimeManager {
  constructor() {
    this.updateInterval = 30000; // 30 seconds fallback polling
    this.intervals = new Map();
    this.subscribers = new Map();
    this.isActive = false;
    this.lastUpdateTimes = new Map();
    this.retryAttempts = new Map();
    this.maxRetries = 3;
    
    // WebSocket connection
    this.ws = null;
    this.wsReconnectAttempts = 0;
    this.maxWsReconnectAttempts = 5;
    this.wsReconnectDelay = 1000;
    
    // Initialize event listeners
    this.initializeEventListeners();
  }

  /**
   * Initialize event listeners for page visibility and focus
   */
  initializeEventListeners() {
    // Handle page visibility changes
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        this.pauseUpdates();
      } else {
        this.resumeUpdates();
      }
    });

    // Handle window focus/blur
    window.addEventListener('focus', () => {
      this.resumeUpdates();
    });

    window.addEventListener('blur', () => {
      this.pauseUpdates();
    });

    // Handle online/offline status
    window.addEventListener('online', () => {
      this.resumeUpdates();
    });

    window.addEventListener('offline', () => {
      this.pauseUpdates();
    });
  }

  /**
   * Start real-time updates
   */
  start() {
    if (this.isActive) return;
    
    this.isActive = true;
    console.log('Real-time updates started');
    
    // Try to establish WebSocket connection first
    this.connectWebSocket();
    
    // Start polling as fallback
    this.startCartUpdates();
    this.startInventoryUpdates();
    this.startOrderUpdates();
    
    // Trigger initial updates
    this.triggerUpdate('cart');
    this.triggerUpdate('inventory');
    this.triggerUpdate('orders');
  }

  /**
   * Connect to WebSocket server
   */
  connectWebSocket() {
    if (!window.isAuthenticated()) return;
    
    try {
      const token = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
      if (!token) return;
      
      const wsUrl = `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws?token=${token}`;
      
      this.ws = new WebSocket(wsUrl);
      
      this.ws.onopen = () => {
        console.log('WebSocket connected');
        this.wsReconnectAttempts = 0;
        
        // Subscribe to channels
        this.ws.send(JSON.stringify({
          type: 'subscribe',
          channels: ['cart', 'inventory', 'orders']
        }));
        
        // Reduce polling frequency when WebSocket is active
        this.updateInterval = 60000; // 1 minute
      };
      
      this.ws.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleWebSocketMessage(data);
        } catch (error) {
          console.error('WebSocket message parse error:', error);
        }
      };
      
      this.ws.onclose = () => {
        console.log('WebSocket disconnected');
        this.ws = null;
        
        // Increase polling frequency when WebSocket is down
        this.updateInterval = 30000; // 30 seconds
        
        // Attempt to reconnect
        this.attemptWebSocketReconnect();
      };
      
      this.ws.onerror = (error) => {
        console.error('WebSocket error:', error);
      };
      
    } catch (error) {
      console.error('WebSocket connection failed:', error);
    }
  }

  /**
   * Handle WebSocket messages
   */
  handleWebSocketMessage(data) {
    switch (data.type) {
      case 'connected':
        console.log('WebSocket connection confirmed');
        break;
        
      case 'subscribed':
        console.log('Subscribed to channels:', data.channels);
        break;
        
      case 'cart_updated':
        this.handleWebSocketCartUpdate(data);
        break;
        
      case 'inventory_updated':
        this.handleWebSocketInventoryUpdate(data);
        break;
        
      case 'order_updated':
        this.handleWebSocketOrderUpdate(data);
        break;
        
      case 'pong':
        // Handle ping/pong for connection health
        break;
        
      default:
        console.log('Unknown WebSocket message type:', data.type);
    }
  }

  /**
   * Handle WebSocket cart updates
   */
  handleWebSocketCartUpdate(data) {
    // Update local storage
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, data.data);
    
    // Notify subscribers
    this.notifySubscribers('cart', data);
    
    // Update UI
    this.updateCartUI(data.data);
  }

  /**
   * Handle WebSocket inventory updates
   */
  handleWebSocketInventoryUpdate(data) {
    // Notify subscribers
    this.notifySubscribers('inventory', data);
    
    // Update UI
    this.updateInventoryUI(data.data);
  }

  /**
   * Handle WebSocket order updates
   */
  handleWebSocketOrderUpdate(data) {
    // Update local storage
    const currentOrders = CONFIG_UTILS.getStorageItem('recent_orders', []);
    const updatedOrders = this.mergeOrderUpdates(currentOrders, data.data);
    CONFIG_UTILS.setStorageItem('recent_orders', updatedOrders);
    
    // Notify subscribers
    this.notifySubscribers('orders', data);
    
    // Update UI
    this.updateOrdersUI([data.data]);
    
    // Show notification
    this.showOrderNotifications([data.data]);
  }

  /**
   * Attempt WebSocket reconnection
   */
  attemptWebSocketReconnect() {
    if (this.wsReconnectAttempts >= this.maxWsReconnectAttempts) {
      console.log('Max WebSocket reconnection attempts reached');
      return;
    }
    
    this.wsReconnectAttempts++;
    const delay = this.wsReconnectDelay * Math.pow(2, this.wsReconnectAttempts - 1);
    
    console.log(`Attempting WebSocket reconnection in ${delay}ms (attempt ${this.wsReconnectAttempts})`);
    
    setTimeout(() => {
      if (this.isActive && !this.ws) {
        this.connectWebSocket();
      }
    }, delay);
  }

  /**
   * Merge order updates with existing orders
   */
  mergeOrderUpdates(currentOrders, orderUpdate) {
    const updatedOrders = [...currentOrders];
    const existingIndex = updatedOrders.findIndex(order => order.id === orderUpdate.id);
    
    if (existingIndex >= 0) {
      updatedOrders[existingIndex] = { ...updatedOrders[existingIndex], ...orderUpdate };
    } else {
      updatedOrders.unshift(orderUpdate);
    }
    
    return updatedOrders.slice(0, 10); // Keep only recent 10 orders
  }

  /**
   * Stop real-time updates
   */
  stop() {
    if (!this.isActive) return;
    
    this.isActive = false;
    console.log('Real-time updates stopped');
    
    // Close WebSocket connection
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    
    // Clear all intervals
    this.intervals.forEach((interval, key) => {
      clearInterval(interval);
    });
    this.intervals.clear();
  }

  /**
   * Pause updates (when page is hidden or offline)
   */
  pauseUpdates() {
    this.intervals.forEach((interval, key) => {
      clearInterval(interval);
    });
    this.intervals.clear();
  }

  /**
   * Resume updates (when page becomes visible or online)
   */
  resumeUpdates() {
    if (!this.isActive) return;
    
    // Restart intervals
    this.startCartUpdates();
    this.startInventoryUpdates();
    this.startOrderUpdates();
    
    // Trigger immediate updates
    this.triggerUpdate('cart');
    this.triggerUpdate('inventory');
    this.triggerUpdate('orders');
  }

  /**
   * Subscribe to real-time updates
   * @param {string} type - Update type (cart, inventory, orders)
   * @param {Function} callback - Callback function
   * @returns {Function} Unsubscribe function
   */
  subscribe(type, callback) {
    if (!this.subscribers.has(type)) {
      this.subscribers.set(type, new Set());
    }
    
    this.subscribers.get(type).add(callback);
    
    // Return unsubscribe function
    return () => {
      const typeSubscribers = this.subscribers.get(type);
      if (typeSubscribers) {
        typeSubscribers.delete(callback);
      }
    };
  }

  /**
   * Notify subscribers of updates
   * @param {string} type - Update type
   * @param {Object} data - Update data
   */
  notifySubscribers(type, data) {
    const typeSubscribers = this.subscribers.get(type);
    if (typeSubscribers) {
      typeSubscribers.forEach(callback => {
        try {
          callback(data);
        } catch (error) {
          console.error(`Error in ${type} subscriber:`, error);
        }
      });
    }
  }

  /**
   * Start cart updates
   */
  startCartUpdates() {
    if (this.intervals.has('cart')) return;
    
    const interval = setInterval(() => {
      this.triggerUpdate('cart');
    }, this.updateInterval);
    
    this.intervals.set('cart', interval);
  }

  /**
   * Start inventory updates
   */
  startInventoryUpdates() {
    if (this.intervals.has('inventory')) return;
    
    const interval = setInterval(() => {
      this.triggerUpdate('inventory');
    }, this.updateInterval * 2); // Less frequent for inventory
    
    this.intervals.set('inventory', interval);
  }

  /**
   * Start order updates
   */
  startOrderUpdates() {
    if (this.intervals.has('orders')) return;
    
    const interval = setInterval(() => {
      this.triggerUpdate('orders');
    }, this.updateInterval);
    
    this.intervals.set('orders', interval);
  }

  /**
   * Trigger specific update type
   * @param {string} type - Update type
   */
  async triggerUpdate(type) {
    if (!navigator.onLine) return;
    
    try {
      switch (type) {
        case 'cart':
          await this.updateCart();
          break;
        case 'inventory':
          await this.updateInventory();
          break;
        case 'orders':
          await this.updateOrders();
          break;
      }
      
      // Reset retry attempts on success
      this.retryAttempts.delete(type);
      
    } catch (error) {
      console.error(`Error updating ${type}:`, error);
      this.handleUpdateError(type, error);
    }
  }

  /**
   * Handle update errors with retry logic
   * @param {string} type - Update type
   * @param {Error} error - Error object
   */
  handleUpdateError(type, error) {
    const attempts = this.retryAttempts.get(type) || 0;
    
    if (attempts < this.maxRetries) {
      this.retryAttempts.set(type, attempts + 1);
      
      // Retry with exponential backoff
      const delay = Math.pow(2, attempts) * 1000;
      setTimeout(() => {
        this.triggerUpdate(type);
      }, delay);
    } else {
      console.error(`Max retries exceeded for ${type} updates`);
      this.retryAttempts.delete(type);
    }
  }

  /**
   * Update cart data
   */
  async updateCart() {
    if (!window.isAuthenticated()) return;
    
    try {
      const response = await ApiService.cart.get();
      
      if (response.success) {
        const currentCart = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA);
        const newCart = response.data;
        
        // Check if cart has changed
        if (this.hasCartChanged(currentCart, newCart)) {
          // Update local storage
          CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, newCart);
          
          // Notify subscribers
          this.notifySubscribers('cart', {
            type: 'cart_updated',
            data: newCart,
            timestamp: Date.now()
          });
          
          // Update cart UI elements
          this.updateCartUI(newCart);
        }
      }
    } catch (error) {
      // Only log non-auth errors
      if (!error.message.includes('authentication')) {
        console.error('Cart update error:', error);
      }
    }
  }

  /**
   * Update inventory data for current page products
   */
  async updateInventory() {
    try {
      // Get current page products
      const currentProducts = this.getCurrentPageProducts();
      if (currentProducts.length === 0) return;
      
      // Fetch updated product data
      const productIds = currentProducts.map(p => p.id);
      const response = await ApiService.products.getAll({
        ids: productIds.join(','),
        fields: 'id,stock_quantity,price,is_active'
      });
      
      if (response.success && response.data.products) {
        const updatedProducts = response.data.products;
        
        // Check for inventory changes
        const changes = this.detectInventoryChanges(currentProducts, updatedProducts);
        
        if (changes.length > 0) {
          // Notify subscribers
          this.notifySubscribers('inventory', {
            type: 'inventory_updated',
            data: changes,
            timestamp: Date.now()
          });
          
          // Update product UI elements
          this.updateInventoryUI(changes);
        }
      }
    } catch (error) {
      console.error('Inventory update error:', error);
    }
  }

  /**
   * Update order status for authenticated users
   */
  async updateOrders() {
    if (!window.isAuthenticated()) return;
    
    try {
      // Get recent orders (last 30 days)
      const thirtyDaysAgo = new Date();
      thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
      
      const response = await ApiService.orders.getAll({
        from_date: thirtyDaysAgo.toISOString().split('T')[0],
        limit: 10
      });
      
      if (response.success && response.data.orders) {
        const currentOrders = CONFIG_UTILS.getStorageItem('recent_orders', []);
        const newOrders = response.data.orders;
        
        // Check for order status changes
        const changes = this.detectOrderChanges(currentOrders, newOrders);
        
        if (changes.length > 0) {
          // Update local storage
          CONFIG_UTILS.setStorageItem('recent_orders', newOrders);
          
          // Notify subscribers
          this.notifySubscribers('orders', {
            type: 'orders_updated',
            data: changes,
            timestamp: Date.now()
          });
          
          // Update order UI elements
          this.updateOrdersUI(changes);
          
          // Show notifications for status changes
          this.showOrderNotifications(changes);
        }
      }
    } catch (error) {
      if (!error.message.includes('authentication')) {
        console.error('Orders update error:', error);
      }
    }
  }

  /**
   * Check if cart has changed
   * @param {Object} currentCart - Current cart data
   * @param {Object} newCart - New cart data
   * @returns {boolean} Has changed
   */
  hasCartChanged(currentCart, newCart) {
    if (!currentCart && !newCart) return false;
    if (!currentCart || !newCart) return true;
    
    // Compare item count and total
    return currentCart.item_count !== newCart.item_count ||
           currentCart.total !== newCart.total ||
           JSON.stringify(currentCart.items) !== JSON.stringify(newCart.items);
  }

  /**
   * Get products displayed on current page
   * @returns {Array} Current page products
   */
  getCurrentPageProducts() {
    const products = [];
    
    // Look for product elements with data-product-id
    const productElements = document.querySelectorAll('[data-product-id]');
    
    productElements.forEach(element => {
      const productId = element.getAttribute('data-product-id');
      const stockElement = element.querySelector('[data-stock-quantity]');
      const priceElement = element.querySelector('[data-price]');
      
      if (productId) {
        products.push({
          id: parseInt(productId),
          stock_quantity: stockElement ? parseInt(stockElement.getAttribute('data-stock-quantity')) : 0,
          price: priceElement ? parseFloat(priceElement.getAttribute('data-price')) : 0
        });
      }
    });
    
    return products;
  }

  /**
   * Detect inventory changes
   * @param {Array} currentProducts - Current products
   * @param {Array} updatedProducts - Updated products
   * @returns {Array} Changes detected
   */
  detectInventoryChanges(currentProducts, updatedProducts) {
    const changes = [];
    
    updatedProducts.forEach(updated => {
      const current = currentProducts.find(p => p.id === updated.id);
      
      if (current) {
        const change = { id: updated.id, changes: {} };
        
        if (current.stock_quantity !== updated.stock_quantity) {
          change.changes.stock_quantity = {
            old: current.stock_quantity,
            new: updated.stock_quantity
          };
        }
        
        if (current.price !== updated.price) {
          change.changes.price = {
            old: current.price,
            new: updated.price
          };
        }
        
        if (Object.keys(change.changes).length > 0) {
          changes.push(change);
        }
      }
    });
    
    return changes;
  }

  /**
   * Detect order changes
   * @param {Array} currentOrders - Current orders
   * @param {Array} newOrders - New orders
   * @returns {Array} Changes detected
   */
  detectOrderChanges(currentOrders, newOrders) {
    const changes = [];
    
    newOrders.forEach(newOrder => {
      const currentOrder = currentOrders.find(o => o.id === newOrder.id);
      
      if (!currentOrder) {
        // New order
        changes.push({
          type: 'new_order',
          order: newOrder
        });
      } else if (currentOrder.status !== newOrder.status) {
        // Status change
        changes.push({
          type: 'status_change',
          order: newOrder,
          old_status: currentOrder.status,
          new_status: newOrder.status
        });
      }
    });
    
    return changes;
  }

  /**
   * Update cart UI elements
   * @param {Object} cartData - Updated cart data
   */
  updateCartUI(cartData) {
    // Update cart count badges
    const cartCountElements = document.querySelectorAll('.cart-count, [data-cart-count]');
    cartCountElements.forEach(element => {
      element.textContent = cartData.item_count || 0;
      
      // Add animation class
      element.classList.add('updated');
      setTimeout(() => element.classList.remove('updated'), 300);
    });
    
    // Update cart total displays
    const cartTotalElements = document.querySelectorAll('.cart-total, [data-cart-total]');
    cartTotalElements.forEach(element => {
      element.textContent = `₹${cartData.total || 0}`;
      
      // Add animation class
      element.classList.add('updated');
      setTimeout(() => element.classList.remove('updated'), 300);
    });
    
    // Update cart page if visible
    if (window.location.pathname.includes('cart.html')) {
      this.updateCartPage(cartData);
    }
  }

  /**
   * Update inventory UI elements
   * @param {Array} changes - Inventory changes
   */
  updateInventoryUI(changes) {
    changes.forEach(change => {
      const productElements = document.querySelectorAll(`[data-product-id="${change.id}"]`);
      
      productElements.forEach(element => {
        // Update stock quantity
        if (change.changes.stock_quantity) {
          const stockElements = element.querySelectorAll('[data-stock-quantity]');
          stockElements.forEach(stockEl => {
            stockEl.setAttribute('data-stock-quantity', change.changes.stock_quantity.new);
            stockEl.textContent = change.changes.stock_quantity.new;
            
            // Add visual indicator
            stockEl.classList.add('updated');
            setTimeout(() => stockEl.classList.remove('updated'), 300);
          });
          
          // Update stock status
          this.updateStockStatus(element, change.changes.stock_quantity.new);
        }
        
        // Update price
        if (change.changes.price) {
          const priceElements = element.querySelectorAll('[data-price]');
          priceElements.forEach(priceEl => {
            priceEl.setAttribute('data-price', change.changes.price.new);
            priceEl.textContent = `₹${change.changes.price.new}`;
            
            // Add visual indicator
            priceEl.classList.add('updated');
            setTimeout(() => priceEl.classList.remove('updated'), 300);
          });
        }
      });
    });
  }

  /**
   * Update stock status indicators
   * @param {Element} element - Product element
   * @param {number} stockQuantity - New stock quantity
   */
  updateStockStatus(element, stockQuantity) {
    const statusElements = element.querySelectorAll('.stock-status');
    
    statusElements.forEach(statusEl => {
      statusEl.classList.remove('in-stock', 'low-stock', 'out-of-stock');
      
      if (stockQuantity === 0) {
        statusEl.classList.add('out-of-stock');
        statusEl.textContent = 'Out of Stock';
      } else if (stockQuantity <= 5) {
        statusEl.classList.add('low-stock');
        statusEl.textContent = `Only ${stockQuantity} left`;
      } else {
        statusEl.classList.add('in-stock');
        statusEl.textContent = 'In Stock';
      }
    });
    
    // Update add to cart buttons
    const addToCartButtons = element.querySelectorAll('.add-to-cart-btn');
    addToCartButtons.forEach(btn => {
      if (stockQuantity === 0) {
        btn.disabled = true;
        btn.textContent = 'Out of Stock';
      } else {
        btn.disabled = false;
        btn.textContent = 'Add to Cart';
      }
    });
  }

  /**
   * Update orders UI elements
   * @param {Array} changes - Order changes
   */
  updateOrdersUI(changes) {
    changes.forEach(change => {
      if (change.type === 'status_change') {
        const orderElements = document.querySelectorAll(`[data-order-id="${change.order.id}"]`);
        
        orderElements.forEach(element => {
          const statusElements = element.querySelectorAll('.order-status');
          statusElements.forEach(statusEl => {
            statusEl.textContent = this.formatOrderStatus(change.new_status);
            statusEl.className = `order-status status-${change.new_status.toLowerCase().replace(' ', '-')}`;
            
            // Add animation
            statusEl.classList.add('updated');
            setTimeout(() => statusEl.classList.remove('updated'), 300);
          });
        });
      }
    });
  }

  /**
   * Show order status change notifications
   * @param {Array} changes - Order changes
   */
  showOrderNotifications(changes) {
    changes.forEach(change => {
      if (change.type === 'status_change') {
        const message = `Order #${change.order.id} status updated to ${this.formatOrderStatus(change.new_status)}`;
        
        if (window.NotificationManager) {
          NotificationManager.show({
            type: 'info',
            title: 'Order Update',
            message: message,
            duration: 5000
          });
        }
      }
    });
  }

  /**
   * Update cart page content
   * @param {Object} cartData - Updated cart data
   */
  updateCartPage(cartData) {
    // This would be implemented based on the specific cart page structure
    // For now, trigger a custom event that the cart page can listen to
    const event = new CustomEvent('cartUpdated', {
      detail: cartData
    });
    document.dispatchEvent(event);
  }

  /**
   * Format order status for display
   * @param {string} status - Order status
   * @returns {string} Formatted status
   */
  formatOrderStatus(status) {
    return status.split('_').map(word => 
      word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
  }

  /**
   * Force update all data
   */
  forceUpdate() {
    this.triggerUpdate('cart');
    this.triggerUpdate('inventory');
    this.triggerUpdate('orders');
  }

  /**
   * Get update statistics
   * @returns {Object} Update statistics
   */
  getStats() {
    return {
      isActive: this.isActive,
      activeIntervals: this.intervals.size,
      subscribers: Object.fromEntries(
        Array.from(this.subscribers.entries()).map(([key, value]) => [key, value.size])
      ),
      lastUpdateTimes: Object.fromEntries(this.lastUpdateTimes),
      retryAttempts: Object.fromEntries(this.retryAttempts)
    };
  }
}

// Create global instance
const realTimeManager = new RealTimeManager();

// Export for use in other modules
window.RealTimeManager = realTimeManager;

// Auto-start when page loads
document.addEventListener('DOMContentLoaded', () => {
  // Start real-time updates if user is authenticated or on product pages
  if (window.isAuthenticated() || 
      window.location.pathname.includes('products') ||
      window.location.pathname.includes('cart') ||
      window.location.pathname.includes('profile')) {
    realTimeManager.start();
  }
});

// Stop updates when page unloads
window.addEventListener('beforeunload', () => {
  realTimeManager.stop();
});