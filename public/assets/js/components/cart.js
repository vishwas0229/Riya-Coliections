/**
 * Cart component for Riya Collections
 * Handles cart functionality and UI updates
 */

class Cart {
  constructor() {
    this.cartData = {
      items: [],
      total: 0,
      count: 0
    };
    this.init();
  }

  /**
   * Initialize cart
   */
  init() {
    this.loadCartData();
    this.updateCartUI();
    this.setupEventListeners();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Cart icon click
    const cartIcon = DOMUtils.getElement('.nav__cart');
    if (cartIcon) {
      DOMUtils.addEventListener(cartIcon, 'click', (e) => {
        e.preventDefault();
        this.showCartPreview();
      });
    }

    // Listen for cart updates from other components
    DOMUtils.addEventListener(document, 'cartUpdated', (e) => {
      this.handleCartUpdate(e.detail);
    });

    // Listen for authentication changes
    DOMUtils.addEventListener(document, 'authChanged', () => {
      this.handleAuthChange();
    });
  }

  /**
   * Load cart data from storage or API
   */
  async loadCartData() {
    try {
      if (isAuthenticated()) {
        // Load from API for authenticated users
        const response = await ApiService.cart.get();
        if (response.success) {
          this.cartData = response.data;
        }
      } else {
        // Load from local storage for guest users
        this.cartData = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, {
          items: [],
          total: 0,
          count: 0
        });
      }
    } catch (error) {
      console.error('Error loading cart data:', error);
      // Fallback to local storage
      this.cartData = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, {
        items: [],
        total: 0,
        count: 0
      });
    }
  }

  /**
   * Save cart data to storage
   */
  saveCartData() {
    if (!isAuthenticated()) {
      CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, this.cartData);
    }
  }

  /**
   * Add item to cart
   */
  async addItem(productId, quantity = 1) {
    try {
      if (isAuthenticated()) {
        // Add via API for authenticated users
        const response = await ApiService.cart.add({
          productId: productId,
          quantity: quantity
        });

        if (response.success) {
          this.cartData = response.data;
          this.updateCartUI();
          this.showAddedNotification(response.data.addedItem);
          return response;
        } else {
          throw new Error(response.message);
        }
      } else {
        // Add to local storage for guest users
        return this.addItemLocally(productId, quantity);
      }
    } catch (error) {
      console.error('Error adding item to cart:', error);
      throw error;
    }
  }

  /**
   * Add item locally (for guest users)
   */
  async addItemLocally(productId, quantity) {
    try {
      // Get product details
      const productResponse = await ApiService.products.getById(productId);
      if (!productResponse.success) {
        throw new Error('Product not found');
      }

      const product = productResponse.data.product;

      // Check if item already exists in cart
      const existingItemIndex = this.cartData.items.findIndex(item => item.productId == productId);

      if (existingItemIndex > -1) {
        // Update quantity
        const newQuantity = this.cartData.items[existingItemIndex].quantity + quantity;
        
        // Check stock availability
        if (newQuantity > product.stockQuantity) {
          throw new Error('Not enough stock available');
        }

        this.cartData.items[existingItemIndex].quantity = newQuantity;
        this.cartData.items[existingItemIndex].total = newQuantity * product.price;
      } else {
        // Add new item
        if (quantity > product.stockQuantity) {
          throw new Error('Not enough stock available');
        }

        this.cartData.items.push({
          productId: product.id,
          name: product.name,
          price: product.price,
          quantity: quantity,
          total: quantity * product.price,
          image: product.images?.[0]?.url || product.primaryImage,
          brand: product.brand,
          sku: product.sku
        });
      }

      // Recalculate totals
      this.recalculateCart();
      this.saveCartData();
      this.updateCartUI();

      const addedItem = this.cartData.items.find(item => item.productId == productId);
      this.showAddedNotification(addedItem);

      return { success: true, data: this.cartData };
    } catch (error) {
      console.error('Error adding item locally:', error);
      throw error;
    }
  }

  /**
   * Update item quantity
   */
  async updateItem(productId, quantity) {
    try {
      if (quantity <= 0) {
        return this.removeItem(productId);
      }

      if (isAuthenticated()) {
        // Update via API
        const response = await ApiService.cart.update({
          productId: productId,
          quantity: quantity
        });

        if (response.success) {
          this.cartData = response.data;
          this.updateCartUI();
          return response;
        } else {
          throw new Error(response.message);
        }
      } else {
        // Update locally
        return this.updateItemLocally(productId, quantity);
      }
    } catch (error) {
      console.error('Error updating cart item:', error);
      throw error;
    }
  }

  /**
   * Update item locally
   */
  async updateItemLocally(productId, quantity) {
    try {
      const itemIndex = this.cartData.items.findIndex(item => item.productId == productId);
      
      if (itemIndex === -1) {
        throw new Error('Item not found in cart');
      }

      // Get product details to check stock
      const productResponse = await ApiService.products.getById(productId);
      if (productResponse.success) {
        const product = productResponse.data.product;
        
        if (quantity > product.stockQuantity) {
          throw new Error('Not enough stock available');
        }
      }

      // Update item
      this.cartData.items[itemIndex].quantity = quantity;
      this.cartData.items[itemIndex].total = quantity * this.cartData.items[itemIndex].price;

      this.recalculateCart();
      this.saveCartData();
      this.updateCartUI();

      return { success: true, data: this.cartData };
    } catch (error) {
      console.error('Error updating item locally:', error);
      throw error;
    }
  }

  /**
   * Remove item from cart
   */
  async removeItem(productId) {
    try {
      if (isAuthenticated()) {
        // Remove via API
        const response = await ApiService.cart.remove(productId);

        if (response.success) {
          this.cartData = response.data;
          this.updateCartUI();
          this.showRemovedNotification();
          return response;
        } else {
          throw new Error(response.message);
        }
      } else {
        // Remove locally
        return this.removeItemLocally(productId);
      }
    } catch (error) {
      console.error('Error removing cart item:', error);
      throw error;
    }
  }

  /**
   * Remove item locally
   */
  removeItemLocally(productId) {
    const itemIndex = this.cartData.items.findIndex(item => item.productId == productId);
    
    if (itemIndex > -1) {
      this.cartData.items.splice(itemIndex, 1);
      this.recalculateCart();
      this.saveCartData();
      this.updateCartUI();
      this.showRemovedNotification();
    }

    return { success: true, data: this.cartData };
  }

  /**
   * Clear entire cart
   */
  async clearCart() {
    try {
      if (isAuthenticated()) {
        // Clear via API (implement if needed)
        // For now, remove items one by one
        for (const item of this.cartData.items) {
          await this.removeItem(item.productId);
        }
      } else {
        // Clear locally
        this.cartData = {
          items: [],
          total: 0,
          count: 0
        };
        this.saveCartData();
        this.updateCartUI();
      }
    } catch (error) {
      console.error('Error clearing cart:', error);
      throw error;
    }
  }

  /**
   * Recalculate cart totals
   */
  recalculateCart() {
    this.cartData.total = this.cartData.items.reduce((sum, item) => sum + item.total, 0);
    this.cartData.count = this.cartData.items.reduce((sum, item) => sum + item.quantity, 0);
  }

  /**
   * Update cart UI elements
   */
  updateCartUI() {
    this.updateCartCount();
    this.updateCartIcon();
    
    // Dispatch cart updated event
    const event = new CustomEvent('cartUIUpdated', {
      detail: this.cartData
    });
    document.dispatchEvent(event);
  }

  /**
   * Update cart count in navigation
   */
  updateCartCount() {
    const cartCount = DOMUtils.getId('cart-count');
    if (cartCount) {
      cartCount.textContent = this.cartData.count;
      
      // Add animation for count change
      cartCount.classList.add('updated');
      setTimeout(() => {
        cartCount.classList.remove('updated');
      }, 300);

      // Show/hide count badge
      if (this.cartData.count > 0) {
        cartCount.style.display = 'flex';
      } else {
        cartCount.style.display = 'none';
      }
    }
  }

  /**
   * Update cart icon appearance
   */
  updateCartIcon() {
    const cartIcon = DOMUtils.getElement('.nav__cart');
    if (cartIcon) {
      if (this.cartData.count > 0) {
        cartIcon.classList.add('has-items');
      } else {
        cartIcon.classList.remove('has-items');
      }
    }
  }

  /**
   * Show cart preview/mini cart
   */
  showCartPreview() {
    // Create or show cart preview modal
    let cartPreview = DOMUtils.getId('cart-preview');
    
    if (!cartPreview) {
      cartPreview = this.createCartPreview();
      document.body.appendChild(cartPreview);
    }

    this.renderCartPreview(cartPreview);
    cartPreview.classList.add('show');
  }

  /**
   * Create cart preview modal
   */
  createCartPreview() {
    const cartPreview = DOMUtils.createElement('div', {
      id: 'cart-preview',
      className: 'cart-preview'
    });

    // Add close functionality
    DOMUtils.addEventListener(cartPreview, 'click', (e) => {
      if (e.target === cartPreview || e.target.closest('.cart-preview__close')) {
        cartPreview.classList.remove('show');
      }
    });

    return cartPreview;
  }

  /**
   * Render cart preview content
   */
  renderCartPreview(container) {
    const isEmpty = this.cartData.items.length === 0;

    const content = isEmpty ? this.renderEmptyCart() : this.renderCartItems();

    container.innerHTML = `
      <div class="cart-preview__content">
        <div class="cart-preview__header">
          <h3>Shopping Cart (${this.cartData.count})</h3>
          <button class="cart-preview__close">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="cart-preview__body">
          ${content}
        </div>
        ${!isEmpty ? this.renderCartFooter() : ''}
      </div>
    `;

    // Add event listeners
    this.addCartPreviewListeners(container);
  }

  /**
   * Render empty cart
   */
  renderEmptyCart() {
    return `
      <div class="cart-empty">
        <div class="cart-empty__icon">
          <i class="ri-shopping-cart-line"></i>
        </div>
        <h4>Your cart is empty</h4>
        <p>Add some products to get started</p>
        <a href="pages/products.html" class="btn btn--primary">
          Continue Shopping
        </a>
      </div>
    `;
  }

  /**
   * Render cart items
   */
  renderCartItems() {
    return `
      <div class="cart-items">
        ${this.cartData.items.map(item => `
          <div class="cart-item" data-product-id="${item.productId}">
            <div class="cart-item__image">
              <img src="${item.image || 'assets/placeholder.jpg'}" alt="${item.name}">
            </div>
            <div class="cart-item__content">
              <h4 class="cart-item__name">${item.name}</h4>
              <p class="cart-item__brand">${item.brand || ''}</p>
              <div class="cart-item__price">
                <span class="cart-item__unit-price">${FormatUtils.currency(item.price)}</span>
                <span class="cart-item__total">${FormatUtils.currency(item.total)}</span>
              </div>
            </div>
            <div class="cart-item__controls">
              <div class="quantity-controls">
                <button class="quantity-btn quantity-decrease" data-product-id="${item.productId}">
                  <i class="ri-subtract-line"></i>
                </button>
                <span class="quantity-value">${item.quantity}</span>
                <button class="quantity-btn quantity-increase" data-product-id="${item.productId}">
                  <i class="ri-add-line"></i>
                </button>
              </div>
              <button class="cart-item__remove" data-product-id="${item.productId}">
                <i class="ri-delete-bin-line"></i>
              </button>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  /**
   * Render cart footer
   */
  renderCartFooter() {
    return `
      <div class="cart-preview__footer">
        <div class="cart-total">
          <span class="cart-total__label">Total:</span>
          <span class="cart-total__amount">${FormatUtils.currency(this.cartData.total)}</span>
        </div>
        <div class="cart-actions">
          <a href="pages/cart.html" class="btn btn--outline">
            View Cart
          </a>
          <a href="pages/checkout.html" class="btn btn--primary">
            Checkout
          </a>
        </div>
      </div>
    `;
  }

  /**
   * Add event listeners to cart preview
   */
  addCartPreviewListeners(container) {
    // Quantity controls
    const decreaseBtns = container.querySelectorAll('.quantity-decrease');
    const increaseBtns = container.querySelectorAll('.quantity-increase');
    const removeBtns = container.querySelectorAll('.cart-item__remove');

    decreaseBtns.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', async () => {
        const productId = btn.dataset.productId;
        const item = this.cartData.items.find(item => item.productId == productId);
        if (item) {
          await this.updateItem(productId, item.quantity - 1);
          this.renderCartPreview(container);
        }
      });
    });

    increaseBtns.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', async () => {
        const productId = btn.dataset.productId;
        const item = this.cartData.items.find(item => item.productId == productId);
        if (item) {
          try {
            await this.updateItem(productId, item.quantity + 1);
            this.renderCartPreview(container);
          } catch (error) {
            this.showNotification(error.message, 'error');
          }
        }
      });
    });

    removeBtns.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', async () => {
        const productId = btn.dataset.productId;
        await this.removeItem(productId);
        this.renderCartPreview(container);
      });
    });
  }

  /**
   * Handle cart update from other components
   */
  handleCartUpdate(data) {
    this.cartData = data;
    this.updateCartUI();
  }

  /**
   * Handle authentication change
   */
  async handleAuthChange() {
    // Reload cart data when authentication status changes
    await this.loadCartData();
    this.updateCartUI();
  }

  /**
   * Show item added notification
   */
  showAddedNotification(item) {
    this.showNotification(`${item.name} added to cart`, 'success');
  }

  /**
   * Show item removed notification
   */
  showRemovedNotification() {
    this.showNotification('Item removed from cart', 'info');
  }

  /**
   * Show notification
   */
  showNotification(message, type = 'info') {
    if (window.showNotification) {
      window.showNotification(message, type);
    } else {
      console.log(`${type.toUpperCase()}: ${message}`);
    }
  }

  /**
   * Get cart data
   */
  getCartData() {
    return this.cartData;
  }

  /**
   * Get cart count
   */
  getCartCount() {
    return this.cartData.count;
  }

  /**
   * Get cart total
   */
  getCartTotal() {
    return this.cartData.total;
  }
}

// Initialize cart when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  const cart = new Cart();
  
  // Export for global access
  window.Cart = cart;
});

// Export class for testing
window.CartClass = Cart;