/**
 * Real-time Integration Utilities
 * Integrates real-time updates with existing page functionality
 */

class RealTimeIntegration {
  constructor() {
    this.initialized = false;
    this.pageHandlers = new Map();
    this.statusIndicator = null;
    
    this.init();
  }

  /**
   * Initialize real-time integration
   */
  init() {
    if (this.initialized) return;
    
    // Wait for DOM and dependencies to load
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setup());
    } else {
      this.setup();
    }
  }

  /**
   * Setup real-time integration
   */
  setup() {
    this.initialized = true;
    
    // Create status indicator
    this.createStatusIndicator();
    
    // Setup page-specific handlers
    this.setupPageHandlers();
    
    // Subscribe to real-time updates
    this.subscribeToUpdates();
    
    // Setup event listeners
    this.setupEventListeners();
    
    console.log('Real-time integration initialized');
  }

  /**
   * Create real-time status indicator
   */
  createStatusIndicator() {
    this.statusIndicator = document.createElement('div');
    this.statusIndicator.className = 'real-time-status';
    this.statusIndicator.textContent = 'Live Updates';
    
    document.body.appendChild(this.statusIndicator);
    
    // Show indicator briefly on page load
    setTimeout(() => {
      this.statusIndicator.classList.add('active');
      setTimeout(() => {
        this.statusIndicator.classList.remove('active');
      }, 3000);
    }, 1000);
    
    // Update indicator based on connection status
    this.updateStatusIndicator();
  }

  /**
   * Update status indicator
   */
  updateStatusIndicator() {
    if (!this.statusIndicator) return;
    
    if (navigator.onLine && window.RealTimeManager?.isActive) {
      this.statusIndicator.classList.remove('offline');
      this.statusIndicator.textContent = 'Live Updates';
    } else {
      this.statusIndicator.classList.add('offline');
      this.statusIndicator.textContent = 'Offline';
    }
  }

  /**
   * Setup page-specific handlers
   */
  setupPageHandlers() {
    const currentPage = this.getCurrentPage();
    
    switch (currentPage) {
      case 'home':
        this.setupHomePageHandlers();
        break;
      case 'products':
        this.setupProductsPageHandlers();
        break;
      case 'product-detail':
        this.setupProductDetailHandlers();
        break;
      case 'cart':
        this.setupCartPageHandlers();
        break;
      case 'profile':
        this.setupProfilePageHandlers();
        break;
      case 'admin-dashboard':
        this.setupAdminDashboardHandlers();
        break;
      case 'admin-products':
        this.setupAdminProductsHandlers();
        break;
      case 'admin-orders':
        this.setupAdminOrdersHandlers();
        break;
    }
  }

  /**
   * Get current page identifier
   * @returns {string} Page identifier
   */
  getCurrentPage() {
    const path = window.location.pathname;
    
    if (path.includes('index.html') || path === '/') return 'home';
    if (path.includes('products.html')) return 'products';
    if (path.includes('product.html')) return 'product-detail';
    if (path.includes('cart.html')) return 'cart';
    if (path.includes('profile.html')) return 'profile';
    if (path.includes('admin-dashboard.html')) return 'admin-dashboard';
    if (path.includes('admin-products.html')) return 'admin-products';
    if (path.includes('admin-orders.html')) return 'admin-orders';
    
    return 'unknown';
  }

  /**
   * Subscribe to real-time updates
   */
  subscribeToUpdates() {
    if (!window.RealTimeManager) return;
    
    // Subscribe to cart updates
    window.RealTimeManager.subscribe('cart', (data) => {
      this.handleCartUpdate(data);
    });
    
    // Subscribe to inventory updates
    window.RealTimeManager.subscribe('inventory', (data) => {
      this.handleInventoryUpdate(data);
    });
    
    // Subscribe to order updates
    window.RealTimeManager.subscribe('orders', (data) => {
      this.handleOrderUpdate(data);
    });
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Online/offline status
    window.addEventListener('online', () => {
      this.updateStatusIndicator();
      this.showStatusMessage('Back online - resuming live updates');
    });
    
    window.addEventListener('offline', () => {
      this.updateStatusIndicator();
      this.showStatusMessage('Offline - live updates paused');
    });
    
    // Page visibility changes
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        this.updateStatusIndicator();
      }
    });
  }

  /**
   * Setup home page handlers
   */
  setupHomePageHandlers() {
    // Handle featured products inventory updates
    this.pageHandlers.set('home', {
      handleInventoryUpdate: (changes) => {
        changes.forEach(change => {
          const productCards = document.querySelectorAll(`.featured-product[data-product-id="${change.id}"]`);
          productCards.forEach(card => {
            this.updateProductCard(card, change);
          });
        });
      }
    });
  }

  /**
   * Setup products page handlers
   */
  setupProductsPageHandlers() {
    this.pageHandlers.set('products', {
      handleInventoryUpdate: (changes) => {
        changes.forEach(change => {
          const productCards = document.querySelectorAll(`.product-card[data-product-id="${change.id}"]`);
          productCards.forEach(card => {
            this.updateProductCard(card, change);
          });
        });
        
        // Update filters if stock status changed
        this.updateFilters();
      }
    });
  }

  /**
   * Setup product detail page handlers
   */
  setupProductDetailHandlers() {
    this.pageHandlers.set('product-detail', {
      handleInventoryUpdate: (changes) => {
        const urlParams = new URLSearchParams(window.location.search);
        const currentProductId = parseInt(urlParams.get('id'));
        
        const relevantChange = changes.find(change => change.id === currentProductId);
        if (relevantChange) {
          this.updateProductDetailPage(relevantChange);
        }
      }
    });
  }

  /**
   * Setup cart page handlers
   */
  setupCartPageHandlers() {
    this.pageHandlers.set('cart', {
      handleCartUpdate: (data) => {
        // Refresh cart display
        if (window.CartManager && window.CartManager.refreshCart) {
          window.CartManager.refreshCart(data.data);
        }
      },
      
      handleInventoryUpdate: (changes) => {
        // Check if any cart items are affected
        const cartData = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA);
        if (!cartData || !cartData.items) return;
        
        const affectedItems = cartData.items.filter(item => 
          changes.some(change => change.id === item.product_id)
        );
        
        if (affectedItems.length > 0) {
          this.showInventoryWarning(affectedItems, changes);
        }
      }
    });
    
    // Listen for cart update events
    document.addEventListener('cartUpdated', (event) => {
      const handler = this.pageHandlers.get('cart');
      if (handler && handler.handleCartUpdate) {
        handler.handleCartUpdate({ data: event.detail });
      }
    });
  }

  /**
   * Setup profile page handlers
   */
  setupProfilePageHandlers() {
    this.pageHandlers.set('profile', {
      handleOrderUpdate: (data) => {
        if (data.data && data.data.length > 0) {
          // Refresh order history section
          this.refreshOrderHistory();
        }
      }
    });
  }

  /**
   * Setup admin dashboard handlers
   */
  setupAdminDashboardHandlers() {
    this.pageHandlers.set('admin-dashboard', {
      handleOrderUpdate: (data) => {
        // Update dashboard metrics
        if (window.AdminDashboard && window.AdminDashboard.updateMetrics) {
          window.AdminDashboard.updateMetrics();
        }
      }
    });
  }

  /**
   * Setup admin products handlers
   */
  setupAdminProductsHandlers() {
    this.pageHandlers.set('admin-products', {
      handleInventoryUpdate: (changes) => {
        // Update product table rows
        changes.forEach(change => {
          const rows = document.querySelectorAll(`tr[data-product-id="${change.id}"]`);
          rows.forEach(row => {
            this.updateAdminProductRow(row, change);
          });
        });
      }
    });
  }

  /**
   * Setup admin orders handlers
   */
  setupAdminOrdersHandlers() {
    this.pageHandlers.set('admin-orders', {
      handleOrderUpdate: (data) => {
        // Refresh orders table
        if (window.AdminOrders && window.AdminOrders.refreshOrders) {
          window.AdminOrders.refreshOrders();
        }
      }
    });
  }

  /**
   * Handle cart updates
   * @param {Object} data - Cart update data
   */
  handleCartUpdate(data) {
    const currentPage = this.getCurrentPage();
    const handler = this.pageHandlers.get(currentPage);
    
    if (handler && handler.handleCartUpdate) {
      handler.handleCartUpdate(data);
    }
    
    // Show brief status message
    this.showStatusMessage('Cart updated');
  }

  /**
   * Handle inventory updates
   * @param {Object} data - Inventory update data
   */
  handleInventoryUpdate(data) {
    const currentPage = this.getCurrentPage();
    const handler = this.pageHandlers.get(currentPage);
    
    if (handler && handler.handleInventoryUpdate) {
      handler.handleInventoryUpdate(data.data);
    }
    
    // Show status message for significant changes
    const outOfStockChanges = data.data.filter(change => 
      change.changes.stock_quantity && change.changes.stock_quantity.new === 0
    );
    
    if (outOfStockChanges.length > 0) {
      this.showStatusMessage(`${outOfStockChanges.length} item(s) went out of stock`);
    }
  }

  /**
   * Handle order updates
   * @param {Object} data - Order update data
   */
  handleOrderUpdate(data) {
    const currentPage = this.getCurrentPage();
    const handler = this.pageHandlers.get(currentPage);
    
    if (handler && handler.handleOrderUpdate) {
      handler.handleOrderUpdate(data);
    }
  }

  /**
   * Update product card with new data
   * @param {Element} card - Product card element
   * @param {Object} change - Inventory change data
   */
  updateProductCard(card, change) {
    if (change.changes.stock_quantity) {
      const stockEl = card.querySelector('.stock-quantity');
      if (stockEl) {
        stockEl.textContent = change.changes.stock_quantity.new;
        stockEl.classList.add('updated');
        setTimeout(() => stockEl.classList.remove('updated'), 300);
      }
      
      // Update add to cart button
      const addToCartBtn = card.querySelector('.add-to-cart-btn');
      if (addToCartBtn) {
        if (change.changes.stock_quantity.new === 0) {
          addToCartBtn.disabled = true;
          addToCartBtn.textContent = 'Out of Stock';
        } else {
          addToCartBtn.disabled = false;
          addToCartBtn.textContent = 'Add to Cart';
        }
      }
    }
    
    if (change.changes.price) {
      const priceEl = card.querySelector('.product-price');
      if (priceEl) {
        const oldPrice = change.changes.price.old;
        const newPrice = change.changes.price.new;
        
        priceEl.textContent = `₹${newPrice}`;
        priceEl.classList.add('updated');
        
        // Add price change indicator
        if (newPrice > oldPrice) {
          priceEl.classList.add('price-increased');
        } else if (newPrice < oldPrice) {
          priceEl.classList.add('price-decreased');
        }
        
        setTimeout(() => {
          priceEl.classList.remove('updated', 'price-increased', 'price-decreased');
        }, 2000);
      }
    }
  }

  /**
   * Update product detail page
   * @param {Object} change - Inventory change data
   */
  updateProductDetailPage(change) {
    if (change.changes.stock_quantity) {
      const stockElements = document.querySelectorAll('.stock-quantity');
      stockElements.forEach(el => {
        el.textContent = change.changes.stock_quantity.new;
        el.classList.add('updated');
        setTimeout(() => el.classList.remove('updated'), 300);
      });
      
      // Update quantity selector max value
      const quantityInput = document.querySelector('#quantity');
      if (quantityInput) {
        quantityInput.max = Math.max(1, change.changes.stock_quantity.new);
        if (parseInt(quantityInput.value) > change.changes.stock_quantity.new) {
          quantityInput.value = Math.max(1, change.changes.stock_quantity.new);
        }
      }
    }
    
    if (change.changes.price) {
      const priceElements = document.querySelectorAll('.product-price');
      priceElements.forEach(el => {
        el.textContent = `₹${change.changes.price.new}`;
        el.classList.add('updated');
        setTimeout(() => el.classList.remove('updated'), 300);
      });
    }
  }

  /**
   * Update admin product row
   * @param {Element} row - Table row element
   * @param {Object} change - Inventory change data
   */
  updateAdminProductRow(row, change) {
    if (change.changes.stock_quantity) {
      const stockCell = row.querySelector('.stock-cell');
      if (stockCell) {
        stockCell.textContent = change.changes.stock_quantity.new;
        stockCell.classList.add('updated');
        setTimeout(() => stockCell.classList.remove('updated'), 300);
      }
    }
    
    if (change.changes.price) {
      const priceCell = row.querySelector('.price-cell');
      if (priceCell) {
        priceCell.textContent = `₹${change.changes.price.new}`;
        priceCell.classList.add('updated');
        setTimeout(() => priceCell.classList.remove('updated'), 300);
      }
    }
  }

  /**
   * Show inventory warning for cart items
   * @param {Array} affectedItems - Affected cart items
   * @param {Array} changes - Inventory changes
   */
  showInventoryWarning(affectedItems, changes) {
    const messages = [];
    
    affectedItems.forEach(item => {
      const change = changes.find(c => c.id === item.product_id);
      if (change && change.changes.stock_quantity) {
        const newStock = change.changes.stock_quantity.new;
        if (newStock === 0) {
          messages.push(`${item.name} is now out of stock`);
        } else if (item.quantity > newStock) {
          messages.push(`${item.name} stock reduced to ${newStock}`);
        }
      }
    });
    
    if (messages.length > 0 && window.NotificationManager) {
      window.NotificationManager.show({
        type: 'warning',
        title: 'Cart Items Updated',
        message: messages.join(', '),
        duration: 8000
      });
    }
  }

  /**
   * Update filters based on inventory changes
   */
  updateFilters() {
    // This would update any stock-based filters
    const stockFilter = document.querySelector('#stock-filter');
    if (stockFilter && window.ProductFilters) {
      // Trigger filter update
      const event = new CustomEvent('inventoryUpdated');
      stockFilter.dispatchEvent(event);
    }
  }

  /**
   * Refresh order history section
   */
  refreshOrderHistory() {
    const orderHistorySection = document.querySelector('#order-history');
    if (orderHistorySection && window.ProfileManager) {
      window.ProfileManager.loadOrderHistory();
    }
  }

  /**
   * Show status message
   * @param {string} message - Status message
   */
  showStatusMessage(message) {
    if (!this.statusIndicator) return;
    
    this.statusIndicator.textContent = message;
    this.statusIndicator.classList.add('active');
    
    setTimeout(() => {
      this.statusIndicator.classList.remove('active');
      setTimeout(() => {
        this.updateStatusIndicator();
      }, 300);
    }, 2000);
  }

  /**
   * Force refresh all real-time data
   */
  forceRefresh() {
    if (window.RealTimeManager) {
      window.RealTimeManager.forceUpdate();
      this.showStatusMessage('Refreshing data...');
    }
  }

  /**
   * Get integration statistics
   * @returns {Object} Integration stats
   */
  getStats() {
    return {
      initialized: this.initialized,
      currentPage: this.getCurrentPage(),
      activeHandlers: this.pageHandlers.size,
      realTimeManagerStats: window.RealTimeManager ? window.RealTimeManager.getStats() : null
    };
  }
}

// Create global instance
const realTimeIntegration = new RealTimeIntegration();

// Export for debugging
window.RealTimeIntegration = realTimeIntegration;

// Add manual refresh button for development
if (window.IS_DEVELOPMENT) {
  document.addEventListener('keydown', (e) => {
    // Ctrl+Shift+R for manual refresh
    if (e.ctrlKey && e.shiftKey && e.key === 'R') {
      e.preventDefault();
      realTimeIntegration.forceRefresh();
    }
  });
}