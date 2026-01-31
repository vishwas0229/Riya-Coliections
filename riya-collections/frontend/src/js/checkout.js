/**
 * Checkout Page JavaScript
 * Handles multi-step checkout process, address management, payment integration
 */

class CheckoutPage {
  constructor() {
    this.currentStep = 1;
    this.maxSteps = 4;
    this.cartData = null;
    this.shippingAddress = null;
    this.paymentMethod = 'razorpay';
    this.savedAddresses = [];
    this.orderData = null;
    this.isProcessing = false;
    
    // DOM elements
    this.elements = {
      // Loading and content
      checkoutLoading: document.getElementById('checkout-loading'),
      checkoutContent: document.getElementById('checkout-content'),
      
      // Progress
      progressFill: document.getElementById('progress-fill'),
      progressSteps: document.querySelectorAll('.progress-step'),
      
      // Steps
      steps: document.querySelectorAll('.checkout-step'),
      
      // Step 1 - Shipping
      savedAddresses: document.getElementById('saved-addresses'),
      addressesGrid: document.getElementById('addresses-grid'),
      addNewAddressBtn: document.getElementById('add-new-address-btn'),
      addressForm: document.getElementById('address-form'),
      shippingForm: document.getElementById('shipping-form'),
      continueToPayment: document.getElementById('continue-to-payment'),
      
      // Step 2 - Payment
      paymentRadios: document.querySelectorAll('input[name="paymentMethod"]'),
      backToShipping: document.getElementById('back-to-shipping'),
      continueToReview: document.getElementById('continue-to-review'),
      
      // Step 3 - Review
      orderItemsReview: document.getElementById('order-items-review'),
      shippingAddressReview: document.getElementById('shipping-address-review'),
      paymentMethodReview: document.getElementById('payment-method-review'),
      editShippingAddress: document.getElementById('edit-shipping-address'),
      editPaymentMethod: document.getElementById('edit-payment-method'),
      backToPayment: document.getElementById('back-to-payment'),
      placeOrderBtn: document.getElementById('place-order-btn'),
      
      // Step 4 - Confirmation
      orderConfirmationDetails: document.getElementById('order-confirmation-details'),
      
      // Order Summary
      summaryItems: document.getElementById('summary-items'),
      summarySubtotal: document.getElementById('summary-subtotal'),
      summaryDiscountRow: document.getElementById('summary-discount-row'),
      summaryDiscount: document.getElementById('summary-discount'),
      summaryDiscountCode: document.getElementById('summary-discount-code'),
      summaryShipping: document.getElementById('summary-shipping'),
      summaryTax: document.getElementById('summary-tax'),
      summaryTotal: document.getElementById('summary-total'),
      
      // Templates
      savedAddressTemplate: document.getElementById('saved-address-template'),
      orderItemReviewTemplate: document.getElementById('order-item-review-template'),
      summaryItemTemplate: document.getElementById('summary-item-template')
    };
    
    this.init();
  }
  
  /**
   * Initialize checkout page
   */
  async init() {
    try {
      this.bindEvents();
      await this.loadCartData();
      await this.loadSavedAddresses();
      this.renderOrderSummary();
      this.updateProgress();
      this.hideLoading();
    } catch (error) {
      console.error('Error initializing checkout:', error);
      this.showError('Failed to load checkout. Please try again.');
      this.hideLoading();
    }
  }
  
  /**
   * Bind event listeners
   */
  bindEvents() {
    // Step navigation
    if (this.elements.continueToPayment) {
      this.elements.continueToPayment.addEventListener('click', () => {
        this.validateShippingAndContinue();
      });
    }
    
    if (this.elements.backToShipping) {
      this.elements.backToShipping.addEventListener('click', () => {
        this.goToStep(1);
      });
    }
    
    if (this.elements.continueToReview) {
      this.elements.continueToReview.addEventListener('click', () => {
        this.goToStep(3);
      });
    }
    
    if (this.elements.backToPayment) {
      this.elements.backToPayment.addEventListener('click', () => {
        this.goToStep(2);
      });
    }
    
    // Edit buttons
    if (this.elements.editShippingAddress) {
      this.elements.editShippingAddress.addEventListener('click', () => {
        this.goToStep(1);
      });
    }
    
    if (this.elements.editPaymentMethod) {
      this.elements.editPaymentMethod.addEventListener('click', () => {
        this.goToStep(2);
      });
    }
    
    // Payment method selection
    this.elements.paymentRadios.forEach(radio => {
      radio.addEventListener('change', (e) => {
        this.paymentMethod = e.target.value;
        this.updateOrderSummary();
      });
    });
    
    // Add new address
    if (this.elements.addNewAddressBtn) {
      this.elements.addNewAddressBtn.addEventListener('click', () => {
        this.showAddressForm();
      });
    }
    
    // Form submission
    if (this.elements.shippingForm) {
      this.elements.shippingForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.validateShippingAndContinue();
      });
    }
    
    // Place order
    if (this.elements.placeOrderBtn) {
      this.elements.placeOrderBtn.addEventListener('click', () => {
        this.placeOrder();
      });
    }
    
    // Form validation
    this.bindFormValidation();
  }
  
  /**
   * Bind form validation events
   */
  bindFormValidation() {
    const form = this.elements.shippingForm;
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => {
      input.addEventListener('blur', () => {
        this.validateField(input);
      });
      
      input.addEventListener('input', () => {
        this.clearFieldError(input);
      });
    });
  }
  
  /**
   * Load cart data
   */
  async loadCartData() {
    try {
      if (isAuthenticated()) {
        const response = await ApiService.cart.get();
        this.cartData = response.data;
      } else {
        // Get cart from local storage for guest users
        this.cartData = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA, {
          items: [],
          subtotal: 0,
          total: 0,
          itemCount: 0
        });
      }
      
      // Redirect to cart if empty
      if (!this.cartData || !this.cartData.items || this.cartData.items.length === 0) {
        this.showError('Your cart is empty. Redirecting to cart page...');
        setTimeout(() => {
          window.location.href = 'cart.html';
        }, 2000);
        return;
      }
      
    } catch (error) {
      console.error('Error loading cart:', error);
      throw new Error('Failed to load cart data');
    }
  }
  
  /**
   * Load saved addresses for authenticated users
   */
  async loadSavedAddresses() {
    if (!isAuthenticated()) return;
    
    try {
      const response = await ApiService.addresses.getAll();
      
      if (response.success && response.data.addresses) {
        this.savedAddresses = response.data.addresses;
        
        if (this.savedAddresses.length > 0) {
          this.renderSavedAddresses();
          this.elements.savedAddresses.style.display = 'block';
          
          // Set default address if available
          const defaultAddress = this.savedAddresses.find(addr => addr.isDefault);
          if (defaultAddress) {
            this.shippingAddress = defaultAddress;
          }
        }
      }
      
    } catch (error) {
      console.error('Error loading saved addresses:', error);
      // Continue without saved addresses
    }
  }
  
  /**
   * Render saved addresses
   */
  renderSavedAddresses() {
    const container = this.elements.addressesGrid;
    const template = this.elements.savedAddressTemplate;
    
    if (!container || !template) return;
    
    container.innerHTML = '';
    
    this.savedAddresses.forEach(address => {
      const addressElement = this.createSavedAddressElement(address, template);
      container.appendChild(addressElement);
    });
  }
  
  /**
   * Create saved address element
   */
  createSavedAddressElement(address, template) {
    const clone = template.content.cloneNode(true);
    const addressCard = clone.querySelector('.address-card');
    
    addressCard.setAttribute('data-address-id', address.id);
    
    // Address name and type
    const name = clone.querySelector('.address-name');
    if (name) {
      name.textContent = `${address.firstName} ${address.lastName}`;
    }
    
    const typeBadge = clone.querySelector('.address-type-badge');
    if (typeBadge) {
      typeBadge.textContent = address.type;
    }
    
    // Address details
    const addressText = clone.querySelector('.address-text');
    if (addressText) {
      const fullAddress = [
        address.addressLine1,
        address.addressLine2,
        address.city,
        address.state,
        address.postalCode
      ].filter(Boolean).join(', ');
      addressText.textContent = fullAddress;
    }
    
    const phone = clone.querySelector('.address-phone');
    if (phone) {
      phone.textContent = address.phone;
    }
    
    // Action buttons
    const selectBtn = clone.querySelector('.select-address-btn');
    if (selectBtn) {
      selectBtn.addEventListener('click', () => {
        this.selectSavedAddress(address);
      });
    }
    
    const editBtn = clone.querySelector('.edit-address-btn');
    if (editBtn) {
      editBtn.addEventListener('click', () => {
        this.editSavedAddress(address);
      });
    }
    
    // Mark as selected if default
    if (address.isDefault) {
      addressCard.classList.add('selected');
      this.shippingAddress = address;
    }
    
    return addressCard;
  }
  
  /**
   * Select saved address
   */
  selectSavedAddress(address) {
    // Update UI
    document.querySelectorAll('.address-card').forEach(card => {
      card.classList.remove('selected');
    });
    
    const selectedCard = document.querySelector(`[data-address-id="${address.id}"]`);
    if (selectedCard) {
      selectedCard.classList.add('selected');
    }
    
    // Set shipping address
    this.shippingAddress = address;
    
    // Hide address form
    this.elements.addressForm.style.display = 'none';
    
    this.showSuccess('Address selected successfully');
  }
  
  /**
   * Edit saved address
   */
  editSavedAddress(address) {
    this.populateAddressForm(address);
    this.showAddressForm();
  }
  
  /**
   * Show address form
   */
  showAddressForm() {
    this.elements.addressForm.style.display = 'block';
    
    // Clear selection
    document.querySelectorAll('.address-card').forEach(card => {
      card.classList.remove('selected');
    });
    
    this.shippingAddress = null;
  }
  
  /**
   * Populate address form with data
   */
  populateAddressForm(address) {
    const form = this.elements.shippingForm;
    if (!form) return;
    
    const fields = {
      firstName: address.firstName,
      lastName: address.lastName,
      phone: address.phone,
      addressLine1: address.addressLine1,
      addressLine2: address.addressLine2,
      city: address.city,
      state: address.state,
      postalCode: address.postalCode,
      addressType: address.type
    };
    
    Object.keys(fields).forEach(fieldName => {
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (field && fields[fieldName]) {
        field.value = fields[fieldName];
      }
    });
  }
  
  /**
   * Validate shipping information and continue
   */
  async validateShippingAndContinue() {
    // If saved address is selected, continue
    if (this.shippingAddress) {
      this.goToStep(2);
      return;
    }
    
    // Validate form
    if (!this.validateShippingForm()) {
      return;
    }
    
    // Get form data
    const formData = new FormData(this.elements.shippingForm);
    const addressData = Object.fromEntries(formData.entries());
    
    // Set shipping address
    this.shippingAddress = {
      firstName: addressData.firstName,
      lastName: addressData.lastName,
      phone: addressData.phone,
      addressLine1: addressData.addressLine1,
      addressLine2: addressData.addressLine2,
      city: addressData.city,
      state: addressData.state,
      postalCode: addressData.postalCode,
      type: addressData.addressType || 'home'
    };
    
    // Save address if requested and user is authenticated
    if (addressData.saveAddress && isAuthenticated()) {
      try {
        const addressToSave = {
          firstName: this.shippingAddress.firstName,
          lastName: this.shippingAddress.lastName,
          phone: this.shippingAddress.phone,
          addressLine1: this.shippingAddress.addressLine1,
          addressLine2: this.shippingAddress.addressLine2,
          city: this.shippingAddress.city,
          state: this.shippingAddress.state,
          postalCode: this.shippingAddress.postalCode,
          type: this.shippingAddress.type || 'home',
          isDefault: false
        };
        
        const response = await ApiService.addresses.create(addressToSave);
        if (response.success) {
          this.showSuccess('Address saved successfully');
          // Add the new address to saved addresses
          this.savedAddresses.push(response.data.address);
        }
      } catch (error) {
        console.error('Error saving address:', error);
        // Continue anyway
      }
    }
    
    this.goToStep(2);
  }
  
  /**
   * Validate shipping form
   */
  validateShippingForm() {
    const form = this.elements.shippingForm;
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
      if (!this.validateField(field)) {
        isValid = false;
      }
    });
    
    return isValid;
  }
  
  /**
   * Validate individual field
   */
  validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name;
    let isValid = true;
    let errorMessage = '';
    
    // Required field validation
    if (field.hasAttribute('required') && !value) {
      isValid = false;
      errorMessage = 'This field is required';
    }
    
    // Specific field validations
    if (value && isValid) {
      switch (fieldName) {
        case 'phone':
          if (!/^[\+]?[0-9\s\-\(\)]{10,15}$/.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number';
          }
          break;
          
        case 'postalCode':
          if (!/^[0-9]{6}$/.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid 6-digit PIN code';
          }
          break;
          
        case 'firstName':
        case 'lastName':
          if (!/^[a-zA-Z\s]{2,}$/.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid name (minimum 2 characters)';
          }
          break;
      }
    }
    
    // Show/hide error
    this.showFieldError(field, isValid ? '' : errorMessage);
    
    return isValid;
  }
  
  /**
   * Show field error
   */
  showFieldError(field, message) {
    const errorElement = document.getElementById(`${field.id}-error`);
    
    if (message) {
      field.classList.add('error');
      if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.add('show');
      }
    } else {
      field.classList.remove('error');
      if (errorElement) {
        errorElement.classList.remove('show');
      }
    }
  }
  
  /**
   * Clear field error
   */
  clearFieldError(field) {
    field.classList.remove('error');
    const errorElement = document.getElementById(`${field.id}-error`);
    if (errorElement) {
      errorElement.classList.remove('show');
    }
  }
  
  /**
   * Go to specific step
   */
  goToStep(stepNumber) {
    if (stepNumber < 1 || stepNumber > this.maxSteps) return;
    
    // Hide all steps
    this.elements.steps.forEach(step => {
      step.style.display = 'none';
    });
    
    // Show target step
    const targetStep = document.getElementById(`step-${stepNumber}`);
    if (targetStep) {
      targetStep.style.display = 'block';
    }
    
    // Update current step
    this.currentStep = stepNumber;
    
    // Update progress
    this.updateProgress();
    
    // Update step-specific content
    if (stepNumber === 3) {
      this.renderOrderReview();
    }
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  
  /**
   * Update progress indicator
   */
  updateProgress() {
    // Update progress steps
    this.elements.progressSteps.forEach((step, index) => {
      const stepNumber = index + 1;
      
      step.classList.remove('active', 'completed');
      
      if (stepNumber < this.currentStep) {
        step.classList.add('completed');
      } else if (stepNumber === this.currentStep) {
        step.classList.add('active');
      }
    });
    
    // Update progress bar
    const progressPercentage = ((this.currentStep - 1) / (this.maxSteps - 1)) * 100;
    this.elements.progressFill.style.width = `${progressPercentage}%`;
  }
  
  /**
   * Render order review
   */
  renderOrderReview() {
    this.renderOrderItems();
    this.renderShippingAddressReview();
    this.renderPaymentMethodReview();
  }
  
  /**
   * Render order items for review
   */
  renderOrderItems() {
    const container = this.elements.orderItemsReview;
    const template = this.elements.orderItemReviewTemplate;
    
    if (!container || !template || !this.cartData.items) return;
    
    container.innerHTML = '';
    
    this.cartData.items.forEach(item => {
      const itemElement = this.createOrderItemReviewElement(item, template);
      container.appendChild(itemElement);
    });
  }
  
  /**
   * Create order item review element
   */
  createOrderItemReviewElement(item, template) {
    const clone = template.content.cloneNode(true);
    
    // Product image
    const image = clone.querySelector('.product-image');
    if (image) {
      image.src = item.image_url || APP_CONFIG.IMAGES.PLACEHOLDER;
      image.alt = item.name;
    }
    
    // Product details
    const name = clone.querySelector('.item-name');
    if (name) {
      name.textContent = item.name;
    }
    
    const brand = clone.querySelector('.item-brand');
    if (brand && item.brand) {
      brand.textContent = item.brand;
    }
    
    const quantity = clone.querySelector('.item-quantity span');
    if (quantity) {
      quantity.textContent = item.quantity;
    }
    
    const price = clone.querySelector('.price-amount');
    if (price) {
      price.textContent = this.formatPrice(item.price * item.quantity);
    }
    
    return clone;
  }
  
  /**
   * Render shipping address review
   */
  renderShippingAddressReview() {
    const container = this.elements.shippingAddressReview;
    if (!container || !this.shippingAddress) return;
    
    const fullAddress = [
      this.shippingAddress.addressLine1,
      this.shippingAddress.addressLine2,
      this.shippingAddress.city,
      this.shippingAddress.state,
      this.shippingAddress.postalCode
    ].filter(Boolean).join(', ');
    
    container.innerHTML = `
      <div class="address-name">${this.shippingAddress.firstName} ${this.shippingAddress.lastName}</div>
      <div class="address-details">
        <p>${fullAddress}</p>
        <p>Phone: ${this.shippingAddress.phone}</p>
      </div>
    `;
  }
  
  /**
   * Render payment method review
   */
  renderPaymentMethodReview() {
    const container = this.elements.paymentMethodReview;
    if (!container) return;
    
    const paymentMethods = {
      razorpay: {
        icon: 'ri-bank-card-line',
        title: 'Online Payment',
        description: 'Pay securely using UPI, Cards, Net Banking, or Wallets'
      },
      cod: {
        icon: 'ri-money-dollar-circle-line',
        title: 'Cash on Delivery',
        description: 'Pay when your order is delivered'
      }
    };
    
    const method = paymentMethods[this.paymentMethod];
    if (!method) return;
    
    container.innerHTML = `
      <div class="payment-icon">
        <i class="${method.icon}"></i>
      </div>
      <div class="payment-details">
        <h4>${method.title}</h4>
        <p>${method.description}</p>
      </div>
    `;
  }
  
  /**
   * Render order summary
   */
  renderOrderSummary() {
    this.renderSummaryItems();
    this.updateOrderSummary();
  }
  
  /**
   * Render summary items
   */
  renderSummaryItems() {
    const container = this.elements.summaryItems;
    const template = this.elements.summaryItemTemplate;
    
    if (!container || !template || !this.cartData.items) return;
    
    container.innerHTML = '';
    
    this.cartData.items.forEach(item => {
      const itemElement = this.createSummaryItemElement(item, template);
      container.appendChild(itemElement);
    });
  }
  
  /**
   * Create summary item element
   */
  createSummaryItemElement(item, template) {
    const clone = template.content.cloneNode(true);
    
    // Product image
    const image = clone.querySelector('.product-image');
    if (image) {
      image.src = item.image_url || APP_CONFIG.IMAGES.PLACEHOLDER;
      image.alt = item.name;
    }
    
    // Product details
    const name = clone.querySelector('.item-name');
    if (name) {
      name.textContent = item.name;
    }
    
    const quantity = clone.querySelector('.item-quantity');
    if (quantity) {
      quantity.textContent = `Qty: ${item.quantity}`;
    }
    
    const price = clone.querySelector('.item-price');
    if (price) {
      price.textContent = this.formatPrice(item.price * item.quantity);
    }
    
    return clone;
  }
  
  /**
   * Update order summary calculations
   */
  updateOrderSummary() {
    if (!this.cartData || !this.cartData.items) return;
    
    // Calculate subtotal
    const subtotal = this.cartData.items.reduce((total, item) => {
      return total + (item.price * item.quantity);
    }, 0);
    
    // Calculate discount (if any coupon is applied)
    let discountAmount = this.cartData.discount || 0;
    
    // Calculate shipping
    let shippingCost = subtotal >= 500 ? 0 : 50;
    
    // Add COD charges if applicable
    if (this.paymentMethod === 'cod') {
      shippingCost += 25; // COD handling charges
    }
    
    // Calculate tax (18% GST)
    const taxableAmount = subtotal - discountAmount + shippingCost;
    const taxAmount = taxableAmount * 0.18;
    
    // Calculate total
    const total = subtotal - discountAmount + shippingCost + taxAmount;
    
    // Update display
    this.updatePriceDisplay(subtotal, discountAmount, shippingCost, taxAmount, total);
  }
  
  /**
   * Update price display elements
   */
  updatePriceDisplay(subtotal, discountAmount, shippingCost, taxAmount, total) {
    if (this.elements.summarySubtotal) {
      this.elements.summarySubtotal.textContent = this.formatPrice(subtotal);
    }
    
    // Discount row
    if (discountAmount > 0) {
      this.elements.summaryDiscountRow.style.display = 'flex';
      this.elements.summaryDiscount.textContent = `-${this.formatPrice(discountAmount)}`;
      if (this.cartData.couponCode) {
        this.elements.summaryDiscountCode.textContent = `(${this.cartData.couponCode})`;
      }
    } else {
      this.elements.summaryDiscountRow.style.display = 'none';
    }
    
    if (this.elements.summaryShipping) {
      if (shippingCost === 0) {
        this.elements.summaryShipping.textContent = 'FREE';
      } else {
        this.elements.summaryShipping.textContent = this.formatPrice(shippingCost);
      }
    }
    
    if (this.elements.summaryTax) {
      this.elements.summaryTax.textContent = this.formatPrice(taxAmount);
    }
    
    if (this.elements.summaryTotal) {
      this.elements.summaryTotal.textContent = this.formatPrice(total);
    }
  }
  
  /**
   * Place order
   */
  async placeOrder() {
    if (this.isProcessing) return;
    
    try {
      this.isProcessing = true;
      this.setButtonLoading(this.elements.placeOrderBtn, true);
      
      // First create the order
      const orderData = {
        items: this.cartData.items.map(item => ({
          product_id: item.id || item.product_id,
          quantity: item.quantity
        })),
        shipping_address_id: this.shippingAddress.id,
        payment_method: this.paymentMethod,
        coupon_code: this.cartData.couponCode || null,
        notes: null
      };
      
      // If using a new address (not saved), we need to create it first
      if (!this.shippingAddress.id && isAuthenticated()) {
        const addressResponse = await ApiService.addresses.create({
          firstName: this.shippingAddress.firstName,
          lastName: this.shippingAddress.lastName,
          phone: this.shippingAddress.phone,
          addressLine1: this.shippingAddress.addressLine1,
          addressLine2: this.shippingAddress.addressLine2,
          city: this.shippingAddress.city,
          state: this.shippingAddress.state,
          postalCode: this.shippingAddress.postalCode,
          type: this.shippingAddress.type || 'home',
          isDefault: false
        });
        
        if (addressResponse.success) {
          orderData.shipping_address_id = addressResponse.data.address.id;
        } else {
          throw new Error('Failed to save shipping address');
        }
      }
      
      // Create the order
      const orderResponse = await ApiService.orders.create(orderData);
      
      if (!orderResponse.order) {
        throw new Error(orderResponse.error || 'Failed to create order');
      }
      
      const order = orderResponse.order;
      
      if (this.paymentMethod === 'razorpay') {
        await this.processRazorpayPayment(order);
      } else {
        await this.processCODOrder(order);
      }
      
    } catch (error) {
      console.error('Error placing order:', error);
      this.showError(error.message || 'Failed to place order. Please try again.');
    } finally {
      this.isProcessing = false;
      this.setButtonLoading(this.elements.placeOrderBtn, false);
    }
  }
  
  /**
   * Process Razorpay payment
   */
  async processRazorpayPayment(order) {
    try {
      // Create Razorpay order
      const response = await ApiService.payments.createRazorpay({
        order_id: order.id,
        amount: order.total_amount,
        currency: 'INR'
      });
      
      if (!response.payment_order) {
        throw new Error(response.error || 'Failed to create payment order');
      }
      
      const { payment_order, razorpay_key } = response;
      
      // Initialize Razorpay checkout
      const options = {
        key: razorpay_key,
        amount: payment_order.amount,
        currency: payment_order.currency,
        name: 'Riya Collections',
        description: 'Order Payment',
        order_id: payment_order.id,
        handler: async (response) => {
          await this.verifyRazorpayPayment(response, order);
        },
        prefill: {
          name: `${this.shippingAddress.firstName} ${this.shippingAddress.lastName}`,
          contact: this.shippingAddress.phone
        },
        theme: {
          color: '#E91E63'
        },
        modal: {
          ondismiss: () => {
            this.showError('Payment cancelled. Please try again.');
          }
        }
      };
      
      const rzp = new Razorpay(options);
      rzp.open();
      
    } catch (error) {
      console.error('Error processing Razorpay payment:', error);
      throw error;
    }
  }
  
  /**
   * Verify Razorpay payment
   */
  async verifyRazorpayPayment(paymentResponse, order) {
    try {
      const verificationData = {
        razorpay_order_id: paymentResponse.razorpay_order_id,
        razorpay_payment_id: paymentResponse.razorpay_payment_id,
        razorpay_signature: paymentResponse.razorpay_signature,
        order_id: order.id
      };
      
      const response = await ApiService.payments.verifyRazorpay(verificationData);
      
      if (response.payment_status === 'success') {
        this.orderData = response.order;
        this.showOrderSuccess();
      } else {
        throw new Error(response.message || 'Payment verification failed');
      }
      
    } catch (error) {
      console.error('Error verifying payment:', error);
      this.showError('Payment verification failed. Please contact support.');
    }
  }
  
  /**
   * Process COD order
   */
  async processCODOrder(order) {
    try {
      const response = await ApiService.payments.processCOD({
        order_id: order.id,
        delivery_instructions: null
      });
      
      if (response.payment_status === 'confirmed') {
        this.orderData = response.order;
        this.showOrderSuccess();
      } else {
        throw new Error(response.error || 'Failed to place COD order');
      }
      
    } catch (error) {
      console.error('Error processing COD order:', error);
      throw error;
    }
  }
  
  /**
   * Show order success
   */
  showOrderSuccess() {
    // Clear cart
    this.clearCart();
    
    // Go to success step
    this.goToStep(4);
    
    // Render order confirmation
    this.renderOrderConfirmation();
    
    // Show success message
    this.showSuccess('Order placed successfully!');
  }
  
  /**
   * Render order confirmation
   */
  renderOrderConfirmation() {
    const container = this.elements.orderConfirmationDetails;
    if (!container || !this.orderData) return;
    
    const paymentMethodText = this.orderData.payment_method === 'cod' ? 'Cash on Delivery' : 'Online Payment';
    const estimatedDelivery = new Date();
    estimatedDelivery.setDate(estimatedDelivery.getDate() + 5); // 5 days from now
    
    container.innerHTML = `
      <div class="order-info">
        <div class="info-row">
          <span class="info-label">Order Number:</span>
          <span class="info-value">${this.orderData.order_number}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Order Date:</span>
          <span class="info-value">${new Date(this.orderData.created_at).toLocaleDateString()}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Total Amount:</span>
          <span class="info-value">${this.formatPrice(this.orderData.total_amount)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Payment Method:</span>
          <span class="info-value">${paymentMethodText}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Estimated Delivery:</span>
          <span class="info-value">${estimatedDelivery.toLocaleDateString()}</span>
        </div>
      </div>
    `;
  }
  
  /**
   * Clear cart after successful order
   */
  async clearCart() {
    try {
      if (isAuthenticated()) {
        // Clear cart via API
        await ApiService.cart.clear();
      } else {
        CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA);
      }
      
      // Update cart count in navigation
      const cartCount = document.getElementById('cart-count');
      if (cartCount) {
        cartCount.textContent = '0';
      }
      
    } catch (error) {
      console.error('Error clearing cart:', error);
      // Clear local storage as fallback
      CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA);
    }
  }
  
  /**
   * Calculate shipping cost
   */
  calculateShippingCost() {
    const subtotal = this.cartData.items.reduce((total, item) => {
      return total + (item.price * item.quantity);
    }, 0);
    
    let shippingCost = subtotal >= 500 ? 0 : 50;
    
    if (this.paymentMethod === 'cod') {
      shippingCost += 25;
    }
    
    return shippingCost;
  }
  
  /**
   * Calculate tax amount
   */
  calculateTaxAmount() {
    const subtotal = this.cartData.items.reduce((total, item) => {
      return total + (item.price * item.quantity);
    }, 0);
    
    const discountAmount = this.cartData.discount || 0;
    const shippingCost = this.calculateShippingCost();
    const taxableAmount = subtotal - discountAmount + shippingCost;
    
    return taxableAmount * 0.18;
  }
  
  /**
   * Calculate total amount
   */
  calculateTotal() {
    const subtotal = this.cartData.items.reduce((total, item) => {
      return total + (item.price * item.quantity);
    }, 0);
    
    const discountAmount = this.cartData.discount || 0;
    const shippingCost = this.calculateShippingCost();
    const taxAmount = this.calculateTaxAmount();
    
    return subtotal - discountAmount + shippingCost + taxAmount;
  }
  
  /**
   * Show loading state
   */
  showLoading() {
    this.elements.checkoutLoading.style.display = 'block';
    this.elements.checkoutContent.style.display = 'none';
  }
  
  /**
   * Hide loading state
   */
  hideLoading() {
    this.elements.checkoutLoading.style.display = 'none';
    this.elements.checkoutContent.style.display = 'block';
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
    if (window.showNotification) {
      window.showNotification(message, 'success');
    }
  }
  
  /**
   * Show error message
   */
  showError(message) {
    if (window.showNotification) {
      window.showNotification(message, 'error');
    } else {
      alert(message);
    }
  }
}

// Initialize checkout page when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize if we're on the checkout page
  if (document.getElementById('checkout-loading')) {
    window.checkoutPage = new CheckoutPage();
  }
});

// Export for use in other modules
window.CheckoutPage = CheckoutPage;