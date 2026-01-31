/**
 * Shopping Cart Page JavaScript
 * Handles cart display, item management, coupon application, and checkout
 */

class CartPage {
  constructor() {
    this.cartData = null;
    this.coupons = [];
    this.appliedCoupon = null;
    this.isLoading = false;
    
    // DOM elements
    this.elements = {
      cartLoading: document.getElementById('cart-loading'),
      cartEmpty: document.getElementById('cart-empty'),
      cartContent: document.getElementById('cart-content'),
      cartItemsList: document.getElementById('cart-items-list'),
      cartItemsCount: document.getElementById('cart-items-count'),
      cartCount: document.getElementById('cart-count'),
      
      // Summary elements
      subtotal: document.getElementById('subtotal'),
      discountRow: document.getElementById('discount-row'),
      discountAmount: document.getElementById('discount-amount'),
      discountCodeDisplay: document.getElementById('discount-code-display'),
      shippingCost: document.getElementById('shipping-cost'),
      taxAmount: document.getElementById('tax-amount'),
      totalAmount: document.getElementById('total-amount'),
      savingsDisplay: document.getElementById('savings-display'),
      totalSavings: document.getElementById('total-savings'),
      
      // Coupon elements
      couponToggle: document.getElementById('coupon-toggle'),
      couponForm: document.getElementById('coupon-form'),
      couponInputForm: document.getElementById('coupon-input-form'),
      couponCode: document.getElementById('coupon-code'),
      couponApplyBtn: document.getElementById('coupon-apply-btn'),
      appliedCoupon: document.getElementById('applied-coupon'),
      appliedCouponCode: document.getElementById('applied-coupon-code'),
      appliedCouponDescription: document.getElementById('applied-coupon-description'),
      couponRemoveBtn: document.getElementById('coupon-remove-btn'),
      couponsList: document.getElementById('coupons-list'),
      
      // Action elements
      clearCartBtn: document.getElementById('clear-cart-btn'),
      checkoutBtn: document.getElementById('checkout-btn'),
      
      // Modal elements
      clearCartModal: document.getElementById('clear-cart-modal'),
      
      // Templates
      cartItemTemplate: document.getElementById('cart-item-template'),
      couponTemplate: document.getElementById('coupon-template')
    };
    
    this.init();
  }
  
  /**
   * Initialize the cart page
   */
  async init() {
    try {
      this.bindEvents();
      await this.loadCart();
      await this.loadAvailableCoupons();
    } catch (error) {
      console.error('Error initializing cart page:', error);
      this.showError('Failed to load cart. Please refresh the page.');
    }
  }
  
  /**
   * Bind event listeners
   */
  bindEvents() {
    // Coupon toggle
    if (this.elements.couponToggle) {
      this.elements.couponToggle.addEventListener('click', () => {
        this.toggleCouponForm();
      });
    }
    
    // Coupon form submission
    if (this.elements.couponInputForm) {
      this.elements.couponInputForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.applyCoupon();
      });
    }
    
    // Remove coupon
    if (this.elements.couponRemoveBtn) {
      this.elements.couponRemoveBtn.addEventListener('click', () => {
        this.removeCoupon();
      });
    }
    
    // Clear cart
    if (this.elements.clearCartBtn) {
      this.elements.clearCartBtn.addEventListener('click', () => {
        this.showClearCartModal();
      });
    }
    
    // Checkout
    if (this.elements.checkoutBtn) {
      this.elements.checkoutBtn.addEventListener('click', () => {
        this.proceedToCheckout();
      });
    }
    
    // Modal events
    this.bindModalEvents();
    
    // Listen for cart updates from other components
    document.addEventListener('cartUpdated', () => {
      this.loadCart();
    });
  }
  
  /**
   * Bind modal events
   */
  bindModalEvents() {
    const modal = this.elements.clearCartModal;
    if (!modal) return;
    
    const overlay = modal.querySelector('.modal-overlay');
    const closeBtn = modal.querySelector('.modal-close');
    const cancelBtn = modal.querySelector('.modal-cancel');
    const confirmBtn = modal.querySelector('.modal-confirm');
    
    // Close modal events
    [overlay, closeBtn, cancelBtn].forEach(element => {
      if (element) {
        element.addEventListener('click', () => {
          this.hideClearCartModal();
        });
      }
    });
    
    // Confirm clear cart
    if (confirmBtn) {
      confirmBtn.addEventListener('click', () => {
        this.clearCart();
      });
    }
    
    // Close on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('show')) {
        this.hideClearCartModal();
      }
    });
  }
  
  /**
   * Load cart data from API
   */
  async loadCart() {
    try {
      this.showLoading();
      
      // Try to get cart from API if user is authenticated
      if (isAuthenticated()) {
        const response = await ApiService.cart.get();
        this.cartData = response.data;
      } else {
        // Get cart from local storage for guest users
        this.cartData = this.getLocalCart();
      }
      
      this.renderCart();
      this.updateCartSummary();
      
    } catch (error) {
      console.error('Error loading cart:', error);
      
      // Fallback to local storage
      this.cartData = this.getLocalCart();
      this.renderCart();
      this.updateCartSummary();
    } finally {
      this.hideLoading();
    }
  }
  
  /**
   * Get cart from local storage
   */
  getLocalCart() {
    const cartData = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, {
      items: [],
      subtotal: 0,
      total: 0,
      itemCount: 0
    });
    
    return cartData;
  }
  
  /**
   * Save cart to local storage
   */
  saveLocalCart() {
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, this.cartData);
  }
  
  /**
   * Show loading state
   */
  showLoading() {
    this.elements.cartLoading.style.display = 'block';
    this.elements.cartEmpty.style.display = 'none';
    this.elements.cartContent.style.display = 'none';
  }
  
  /**
   * Hide loading state
   */
  hideLoading() {
    this.elements.cartLoading.style.display = 'none';
  }
  
  /**
   * Render cart content
   */
  renderCart() {
    if (!this.cartData || !this.cartData.items || this.cartData.items.length === 0) {
      this.showEmptyCart();
      return;
    }
    
    this.showCartContent();
    this.renderCartItems();
    this.updateCartCount();
  }
  
  /**
   * Show empty cart state
   */
  showEmptyCart() {
    this.elements.cartEmpty.style.display = 'block';
    this.elements.cartContent.style.display = 'none';
    this.updateCartCount(0);
  }
  
  /**
   * Show cart content
   */
  showCartContent() {
    this.elements.cartEmpty.style.display = 'none';
    this.elements.cartContent.style.display = 'block';
  }
  
  /**
   * Render cart items
   */
  renderCartItems() {
    const container = this.elements.cartItemsList;
    const template = this.elements.cartItemTemplate;
    
    if (!container || !template) return;
    
    container.innerHTML = '';
    
    this.cartData.items.forEach((item, index) => {
      const itemElement = this.createCartItemElement(item, template);
      container.appendChild(itemElement);
      
      // Add stagger animation delay
      itemElement.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Update items count
    if (this.elements.cartItemsCount) {
      this.elements.cartItemsCount.textContent = `(${this.cartData.items.length})`;
    }
  }
  
  /**
   * Create cart item element
   */
  createCartItemElement(item, template) {
    const clone = template.content.cloneNode(true);
    const itemElement = clone.querySelector('.cart-item');
    
    // Set product ID
    itemElement.setAttribute('data-product-id', item.product_id);
    
    // Product image
    const image = clone.querySelector('.item-image');
    if (image) {
      image.src = item.image_url || APP_CONFIG.IMAGES.PLACEHOLDER;
      image.alt = item.name;
    }
    
    // Product details
    const nameLink = clone.querySelector('.item-link');
    if (nameLink) {
      nameLink.textContent = item.name;
      nameLink.href = `product.html?id=${item.product_id}`;
    }
    
    const brand = clone.querySelector('.item-brand');
    if (brand && item.brand) {
      brand.textContent = item.brand;
    }
    
    const sku = clone.querySelector('.item-sku span');
    if (sku && item.sku) {
      sku.textContent = item.sku;
    }
    
    // Pricing
    const currentPrice = clone.querySelector('.current-price');
    if (currentPrice) {
      currentPrice.textContent = this.formatPrice(item.price);
    }
    
    const originalPrice = clone.querySelector('.original-price');
    if (originalPrice && item.original_price && item.original_price > item.price) {
      originalPrice.textContent = this.formatPrice(item.original_price);
      originalPrice.style.display = 'inline';
    }
    
    // Stock status
    const stockStatus = clone.querySelector('.stock-status');
    if (stockStatus) {
      this.updateStockStatus(stockStatus, item.stock_quantity);
    }
    
    // Quantity controls
    const quantityInput = clone.querySelector('.quantity-input');
    const decreaseBtn = clone.querySelector('.quantity-decrease');
    const increaseBtn = clone.querySelector('.quantity-increase');
    
    if (quantityInput) {
      quantityInput.value = item.quantity;
      quantityInput.max = Math.min(item.stock_quantity, APP_CONFIG.CART.MAX_QUANTITY);
      
      quantityInput.addEventListener('change', (e) => {
        this.updateItemQuantity(item.product_id, parseInt(e.target.value));
      });
    }
    
    if (decreaseBtn) {
      decreaseBtn.addEventListener('click', () => {
        const newQuantity = Math.max(1, item.quantity - 1);
        this.updateItemQuantity(item.product_id, newQuantity);
      });
    }
    
    if (increaseBtn) {
      increaseBtn.addEventListener('click', () => {
        const maxQuantity = Math.min(item.stock_quantity, APP_CONFIG.CART.MAX_QUANTITY);
        const newQuantity = Math.min(maxQuantity, item.quantity + 1);
        this.updateItemQuantity(item.product_id, newQuantity);
      });
    }
    
    // Update quantity button states
    this.updateQuantityButtons(decreaseBtn, increaseBtn, item.quantity, item.stock_quantity);
    
    // Item total
    const totalPrice = clone.querySelector('.total-price');
    if (totalPrice) {
      totalPrice.textContent = this.formatPrice(item.price * item.quantity);
    }
    
    // Action buttons
    const wishlistBtn = clone.querySelector('.wishlist-btn');
    if (wishlistBtn) {
      wishlistBtn.addEventListener('click', () => {
        this.moveToWishlist(item.product_id);
      });
    }
    
    const removeBtn = clone.querySelector('.remove-btn');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        this.removeItem(item.product_id);
      });
    }
    
    return itemElement;
  }
  
  /**
   * Update stock status display
   */
  updateStockStatus(element, stockQuantity) {
    element.className = 'stock-status';
    
    if (stockQuantity > 10) {
      element.classList.add('in-stock');
      element.textContent = 'In Stock';
    } else if (stockQuantity > 0) {
      element.classList.add('low-stock');
      element.textContent = `Only ${stockQuantity} left`;
    } else {
      element.classList.add('out-of-stock');
      element.textContent = 'Out of Stock';
    }
  }
  
  /**
   * Update quantity button states
   */
  updateQuantityButtons(decreaseBtn, increaseBtn, quantity, stockQuantity) {
    if (decreaseBtn) {
      decreaseBtn.disabled = quantity <= 1;
    }
    
    if (increaseBtn) {
      const maxQuantity = Math.min(stockQuantity, APP_CONFIG.CART.MAX_QUANTITY);
      increaseBtn.disabled = quantity >= maxQuantity;
    }
  }
  
  /**
   * Update item quantity
   */
  async updateItemQuantity(productId, newQuantity) {
    try {
      const item = this.cartData.items.find(item => item.product_id === productId);
      if (!item) return;
      
      // Validate quantity
      const maxQuantity = Math.min(item.stock_quantity, APP_CONFIG.CART.MAX_QUANTITY);
      newQuantity = Math.max(1, Math.min(maxQuantity, newQuantity));
      
      if (newQuantity === item.quantity) return;
      
      // Update locally first for immediate feedback
      item.quantity = newQuantity;
      this.updateCartSummary();
      this.renderCart();
      
      // Update on server if authenticated
      if (isAuthenticated()) {
        await ApiService.cart.update({
          product_id: productId,
          quantity: newQuantity
        });
      } else {
        this.saveLocalCart();
      }
      
      this.showSuccess('Cart updated successfully');
      
    } catch (error) {
      console.error('Error updating item quantity:', error);
      this.showError('Failed to update quantity. Please try again.');
      
      // Reload cart to restore correct state
      this.loadCart();
    }
  }
  
  /**
   * Remove item from cart
   */
  async removeItem(productId) {
    try {
      // Remove locally first for immediate feedback
      this.cartData.items = this.cartData.items.filter(item => item.product_id !== productId);
      this.updateCartSummary();
      this.renderCart();
      
      // Remove from server if authenticated
      if (isAuthenticated()) {
        await ApiService.cart.remove(productId);
      } else {
        this.saveLocalCart();
      }
      
      this.showSuccess('Item removed from cart');
      
      // Dispatch cart updated event
      document.dispatchEvent(new CustomEvent('cartUpdated'));
      
    } catch (error) {
      console.error('Error removing item:', error);
      this.showError('Failed to remove item. Please try again.');
      
      // Reload cart to restore correct state
      this.loadCart();
    }
  }
  
  /**
   * Move item to wishlist
   */
  async moveToWishlist(productId) {
    try {
      // This would integrate with wishlist functionality
      // For now, just remove from cart
      await this.removeItem(productId);
      this.showSuccess('Item moved to wishlist');
      
    } catch (error) {
      console.error('Error moving to wishlist:', error);
      this.showError('Failed to move item to wishlist.');
    }
  }
  
  /**
   * Update cart count in navigation
   */
  updateCartCount(count = null) {
    if (count === null) {
      count = this.cartData ? this.cartData.items.reduce((total, item) => total + item.quantity, 0) : 0;
    }
    
    if (this.elements.cartCount) {
      this.elements.cartCount.textContent = count;
    }
  }
  
  /**
   * Update cart summary calculations
   */
  updateCartSummary() {
    if (!this.cartData || !this.cartData.items) return;
    
    // Calculate subtotal
    const subtotal = this.cartData.items.reduce((total, item) => {
      return total + (item.price * item.quantity);
    }, 0);
    
    // Calculate discount
    let discountAmount = 0;
    if (this.appliedCoupon) {
      discountAmount = this.calculateDiscount(subtotal, this.appliedCoupon);
    }
    
    // Calculate shipping (free shipping over ₹500)
    const shippingCost = (subtotal - discountAmount) >= 500 ? 0 : 50;
    
    // Calculate tax (18% GST)
    const taxableAmount = subtotal - discountAmount + shippingCost;
    const taxAmount = taxableAmount * 0.18;
    
    // Calculate total
    const total = subtotal - discountAmount + shippingCost + taxAmount;
    
    // Calculate savings
    const originalTotal = this.cartData.items.reduce((total, item) => {
      const originalPrice = item.original_price || item.price;
      return total + (originalPrice * item.quantity);
    }, 0);
    const totalSavings = (originalTotal - subtotal) + discountAmount;
    
    // Update display
    this.updatePriceDisplay(subtotal, discountAmount, shippingCost, taxAmount, total, totalSavings);
    
    // Update cart data
    this.cartData.subtotal = subtotal;
    this.cartData.discount = discountAmount;
    this.cartData.shipping = shippingCost;
    this.cartData.tax = taxAmount;
    this.cartData.total = total;
    this.cartData.savings = totalSavings;
  }
  
  /**
   * Update price display elements
   */
  updatePriceDisplay(subtotal, discountAmount, shippingCost, taxAmount, total, totalSavings) {
    if (this.elements.subtotal) {
      this.elements.subtotal.textContent = this.formatPrice(subtotal);
    }
    
    // Discount row
    if (discountAmount > 0) {
      this.elements.discountRow.style.display = 'flex';
      this.elements.discountAmount.textContent = `-${this.formatPrice(discountAmount)}`;
      if (this.appliedCoupon) {
        this.elements.discountCodeDisplay.textContent = `(${this.appliedCoupon.code})`;
      }
    } else {
      this.elements.discountRow.style.display = 'none';
    }
    
    if (this.elements.shippingCost) {
      this.elements.shippingCost.textContent = shippingCost === 0 ? 'FREE' : this.formatPrice(shippingCost);
    }
    
    if (this.elements.taxAmount) {
      this.elements.taxAmount.textContent = this.formatPrice(taxAmount);
    }
    
    if (this.elements.totalAmount) {
      this.elements.totalAmount.textContent = this.formatPrice(total);
    }
    
    // Savings display
    if (totalSavings > 0) {
      this.elements.savingsDisplay.style.display = 'block';
      this.elements.totalSavings.textContent = this.formatPrice(totalSavings);
    } else {
      this.elements.savingsDisplay.style.display = 'none';
    }
  }
  
  /**
   * Toggle coupon form visibility
   */
  toggleCouponForm() {
    const form = this.elements.couponForm;
    const toggle = this.elements.couponToggle;
    
    if (form.style.display === 'none' || !form.style.display) {
      form.style.display = 'block';
      toggle.classList.add('active');
    } else {
      form.style.display = 'none';
      toggle.classList.remove('active');
    }
  }
  
  /**
   * Load available coupons
   */
  async loadAvailableCoupons() {
    try {
      // This would typically come from an API
      // For now, using mock data
      this.coupons = [
        {
          code: 'WELCOME10',
          description: 'Get 10% off on your first order',
          discount_type: 'percentage',
          discount_value: 10,
          minimum_amount: 500,
          validity: 'Valid till Dec 31, 2024'
        },
        {
          code: 'SAVE50',
          description: 'Flat ₹50 off on orders above ₹1000',
          discount_type: 'fixed',
          discount_value: 50,
          minimum_amount: 1000,
          validity: 'Valid for 7 days'
        },
        {
          code: 'BEAUTY20',
          description: '20% off on beauty products',
          discount_type: 'percentage',
          discount_value: 20,
          minimum_amount: 750,
          validity: 'Valid till month end'
        }
      ];
      
      this.renderAvailableCoupons();
      
    } catch (error) {
      console.error('Error loading coupons:', error);
    }
  }
  
  /**
   * Render available coupons
   */
  renderAvailableCoupons() {
    const container = this.elements.couponsList;
    const template = this.elements.couponTemplate;
    
    if (!container || !template || !this.coupons.length) return;
    
    container.innerHTML = '';
    
    this.coupons.forEach(coupon => {
      const couponElement = this.createCouponElement(coupon, template);
      container.appendChild(couponElement);
    });
  }
  
  /**
   * Create coupon element
   */
  createCouponElement(coupon, template) {
    const clone = template.content.cloneNode(true);
    const couponElement = clone.querySelector('.coupon-item');
    
    couponElement.setAttribute('data-coupon-code', coupon.code);
    
    const discount = clone.querySelector('.coupon-discount');
    if (discount) {
      if (coupon.discount_type === 'percentage') {
        discount.textContent = `${coupon.discount_value}% OFF`;
      } else {
        discount.textContent = `₹${coupon.discount_value} OFF`;
      }
    }
    
    const title = clone.querySelector('.coupon-title');
    if (title) {
      title.textContent = coupon.code;
    }
    
    const description = clone.querySelector('.coupon-description');
    if (description) {
      description.textContent = coupon.description;
    }
    
    const validity = clone.querySelector('.coupon-validity');
    if (validity) {
      validity.textContent = coupon.validity;
    }
    
    const applyBtn = clone.querySelector('.coupon-apply-btn');
    if (applyBtn) {
      applyBtn.addEventListener('click', () => {
        this.applyCouponByCode(coupon.code);
      });
    }
    
    return couponElement;
  }
  
  /**
   * Apply coupon
   */
  async applyCoupon() {
    const code = this.elements.couponCode.value.trim().toUpperCase();
    if (!code) return;
    
    await this.applyCouponByCode(code);
  }
  
  /**
   * Apply coupon by code
   */
  async applyCouponByCode(code) {
    try {
      this.setButtonLoading(this.elements.couponApplyBtn, true);
      
      // Find coupon
      const coupon = this.coupons.find(c => c.code === code);
      if (!coupon) {
        this.showError('Invalid coupon code');
        return;
      }
      
      // Validate minimum amount
      if (this.cartData.subtotal < coupon.minimum_amount) {
        this.showError(`Minimum order amount of ₹${coupon.minimum_amount} required for this coupon`);
        return;
      }
      
      // Apply coupon
      this.appliedCoupon = coupon;
      this.updateCartSummary();
      this.showAppliedCoupon();
      
      // Clear input
      this.elements.couponCode.value = '';
      
      this.showSuccess(`Coupon ${code} applied successfully!`);
      
    } catch (error) {
      console.error('Error applying coupon:', error);
      this.showError('Failed to apply coupon. Please try again.');
    } finally {
      this.setButtonLoading(this.elements.couponApplyBtn, false);
    }
  }
  
  /**
   * Remove applied coupon
   */
  removeCoupon() {
    this.appliedCoupon = null;
    this.updateCartSummary();
    this.hideAppliedCoupon();
    this.showSuccess('Coupon removed');
  }
  
  /**
   * Show applied coupon
   */
  showAppliedCoupon() {
    if (!this.appliedCoupon) return;
    
    this.elements.appliedCoupon.style.display = 'block';
    this.elements.appliedCouponCode.textContent = this.appliedCoupon.code;
    this.elements.appliedCouponDescription.textContent = this.appliedCoupon.description;
  }
  
  /**
   * Hide applied coupon
   */
  hideAppliedCoupon() {
    this.elements.appliedCoupon.style.display = 'none';
  }
  
  /**
   * Calculate discount amount
   */
  calculateDiscount(subtotal, coupon) {
    if (coupon.discount_type === 'percentage') {
      let discount = subtotal * (coupon.discount_value / 100);
      if (coupon.maximum_discount) {
        discount = Math.min(discount, coupon.maximum_discount);
      }
      return discount;
    } else {
      return coupon.discount_value;
    }
  }
  
  /**
   * Show clear cart modal
   */
  showClearCartModal() {
    this.elements.clearCartModal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  
  /**
   * Hide clear cart modal
   */
  hideClearCartModal() {
    this.elements.clearCartModal.classList.remove('show');
    document.body.style.overflow = '';
  }
  
  /**
   * Clear entire cart
   */
  async clearCart() {
    try {
      this.hideClearCartModal();
      
      // Clear locally first
      this.cartData = { items: [], subtotal: 0, total: 0, itemCount: 0 };
      this.appliedCoupon = null;
      
      this.renderCart();
      this.updateCartSummary();
      
      // Clear on server if authenticated
      if (isAuthenticated()) {
        // This would be an API call to clear cart
        // await ApiService.cart.clear();
      } else {
        this.saveLocalCart();
      }
      
      this.showSuccess('Cart cleared successfully');
      
      // Dispatch cart updated event
      document.dispatchEvent(new CustomEvent('cartUpdated'));
      
    } catch (error) {
      console.error('Error clearing cart:', error);
      this.showError('Failed to clear cart. Please try again.');
    }
  }
  
  /**
   * Proceed to checkout
   */
  proceedToCheckout() {
    if (!this.cartData || !this.cartData.items || this.cartData.items.length === 0) {
      this.showError('Your cart is empty');
      return;
    }
    
    // Check stock availability
    const outOfStockItems = this.cartData.items.filter(item => item.stock_quantity < item.quantity);
    if (outOfStockItems.length > 0) {
      this.showError('Some items in your cart are out of stock. Please update quantities.');
      return;
    }
    
    // Save cart data to ensure it's available in checkout
    if (!isAuthenticated()) {
      this.saveLocalCart();
    }
    
    // Redirect to checkout page
    window.location.href = 'checkout.html';
  }
  
  /**
   * Set button loading state
   */
  setButtonLoading(button, loading) {
    if (!button) return;
    
    const text = button.querySelector('.btn-text');
    const spinner = button.querySelector('.btn-loading');
    
    if (loading) {
      button.disabled = true;
      if (text) text.style.display = 'none';
      if (spinner) spinner.style.display = 'block';
    } else {
      button.disabled = false;
      if (text) text.style.display = 'inline';
      if (spinner) spinner.style.display = 'none';
    }
  }
  
  /**
   * Format price for display
   */
  formatPrice(amount) {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 2
    }).format(amount);
  }
  
  /**
   * Show success message
   */
  showSuccess(message) {
    // This would integrate with a notification system
    console.log('Success:', message);
    
    // For now, show a simple alert
    // In production, this would use a toast notification
    if (window.showNotification) {
      window.showNotification(message, 'success');
    }
  }
  
  /**
   * Show error message
   */
  showError(message) {
    // This would integrate with a notification system
    console.error('Error:', message);
    
    // For now, show a simple alert
    // In production, this would use a toast notification
    if (window.showNotification) {
      window.showNotification(message, 'error');
    } else {
      alert(message);
    }
  }
}

// Initialize cart page when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize if we're on the cart page
  if (document.getElementById('cart-loading')) {
    window.cartPage = new CartPage();
  }
});

// Export for use in other modules
window.CartPage = CartPage;