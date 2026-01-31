/**
 * Profile page functionality for Riya Collections
 * Handles user dashboard, order history, profile management, and addresses
 */

class ProfileManager {
  constructor() {
    this.isInitialized = false;
    this.currentTab = 'overview';
    this.currentUser = null;
    this.orders = [];
    this.addresses = [];
    this.currentPage = 1;
    this.ordersPerPage = 10;
    this.init();
  }

  /**
   * Initialize profile manager
   */
  async init() {
    if (this.isInitialized) return;

    // Check authentication
    if (!this.checkAuthentication()) {
      return;
    }

    try {
      // Load user data
      await this.loadUserData();
      
      // Setup event listeners
      this.setupEventListeners();
      
      // Initialize tabs
      this.initializeTabs();
      
      // Load initial data
      await this.loadDashboardData();
      
      this.isInitialized = true;

      if (window.IS_DEVELOPMENT) {
        console.log('ProfileManager initialized');
      }
    } catch (error) {
      console.error('Profile initialization error:', error);
      this.showError('Failed to load profile data');
    }
  }

  /**
   * Check if user is authenticated
   * @returns {boolean} Authentication status
   */
  checkAuthentication() {
    const token = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    
    if (!token) {
      // Redirect to login with return URL
      const returnUrl = encodeURIComponent(window.location.href);
      window.location.href = `login.html?redirect=${returnUrl}`;
      return false;
    }
    
    return true;
  }

  /**
   * Load user data from API or storage
   */
  async loadUserData() {
    try {
      // Try to get fresh data from API
      const response = await ApiService.auth.getProfile();
      
      if (response.success) {
        this.currentUser = response.data.user;
        CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, this.currentUser);
      }
    } catch (error) {
      console.warn('Failed to fetch fresh profile data:', error);
      
      // Fall back to stored data
      this.currentUser = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
      
      if (!this.currentUser) {
        throw new Error('No user data available');
      }
    }

    // Update UI with user data
    this.updateUserDisplay();
  }

  /**
   * Update user display elements
   */
  updateUserDisplay() {
    const userName = `${this.currentUser.firstName} ${this.currentUser.lastName}`;
    
    // Update navigation
    const navUserName = DOMUtils.getId('nav-user-name');
    if (navUserName) {
      navUserName.textContent = this.currentUser.firstName;
    }

    // Update hero section
    const heroUserName = DOMUtils.getId('hero-user-name');
    if (heroUserName) {
      heroUserName.textContent = userName;
    }

    // Update avatar
    const userAvatar = DOMUtils.getId('user-avatar');
    if (userAvatar) {
      const initials = `${this.currentUser.firstName.charAt(0)}${this.currentUser.lastName.charAt(0)}`;
      userAvatar.innerHTML = `<span>${initials}</span>`;
    }
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Tab navigation
    const tabButtons = DOMUtils.getElements('[data-tab]');
    tabButtons.forEach(button => {
      DOMUtils.addEventListener(button, 'click', (e) => {
        const tabName = e.target.closest('[data-tab]').dataset.tab;
        this.switchTab(tabName);
      });
    });

    // Profile form
    const profileForm = DOMUtils.getId('profile-form');
    if (profileForm) {
      DOMUtils.addEventListener(profileForm, 'submit', (e) => {
        e.preventDefault();
        this.handleProfileUpdate(e);
      });
    }

    // Order filters
    const statusFilter = DOMUtils.getId('order-status-filter');
    if (statusFilter) {
      DOMUtils.addEventListener(statusFilter, 'change', () => {
        this.filterOrders();
      });
    }

    const orderSearch = DOMUtils.getId('order-search');
    if (orderSearch) {
      DOMUtils.addEventListener(orderSearch, 'input', 
        this.debounce(() => this.filterOrders(), 300)
      );
    }

    // Pagination
    const prevPage = DOMUtils.getId('prev-page');
    const nextPage = DOMUtils.getId('next-page');
    
    if (prevPage) {
      DOMUtils.addEventListener(prevPage, 'click', () => {
        if (this.currentPage > 1) {
          this.currentPage--;
          this.loadOrders();
        }
      });
    }

    if (nextPage) {
      DOMUtils.addEventListener(nextPage, 'click', () => {
        this.currentPage++;
        this.loadOrders();
      });
    }

    // Address management
    const addAddressBtn = DOMUtils.getId('add-address-btn');
    if (addAddressBtn) {
      DOMUtils.addEventListener(addAddressBtn, 'click', () => {
        this.showAddressModal();
      });
    }

    // Modal close handlers
    this.setupModalHandlers();

    // Logout handler
    const logoutBtn = DOMUtils.getId('logout-btn');
    if (logoutBtn) {
      DOMUtils.addEventListener(logoutBtn, 'click', (e) => {
        e.preventDefault();
        this.handleLogout();
      });
    }
  }

  /**
   * Initialize tabs
   */
  initializeTabs() {
    // Check URL hash for initial tab
    const hash = window.location.hash.substring(1);
    if (hash && ['overview', 'orders', 'profile', 'addresses', 'security'].includes(hash)) {
      this.switchTab(hash);
    } else {
      this.switchTab('overview');
    }
  }

  /**
   * Switch between tabs
   * @param {string} tabName - Tab to switch to
   */
  switchTab(tabName) {
    // Update active tab button
    const tabButtons = DOMUtils.getElements('[data-tab]');
    tabButtons.forEach(button => {
      button.classList.remove('active');
      if (button.dataset.tab === tabName) {
        button.classList.add('active');
      }
    });

    // Update active tab content
    const tabContents = DOMUtils.getElements('.tab-content');
    tabContents.forEach(content => {
      content.classList.remove('active');
    });

    const activeTab = DOMUtils.getId(`${tabName}-tab`);
    if (activeTab) {
      activeTab.classList.add('active');
    }

    // Update URL hash
    window.location.hash = tabName;
    this.currentTab = tabName;

    // Load tab-specific data
    this.loadTabData(tabName);
  }

  /**
   * Load data for specific tab
   * @param {string} tabName - Tab name
   */
  async loadTabData(tabName) {
    switch (tabName) {
      case 'overview':
        await this.loadOverviewData();
        break;
      case 'orders':
        await this.loadOrders();
        break;
      case 'profile':
        this.loadProfileForm();
        break;
      case 'addresses':
        await this.loadAddresses();
        break;
      case 'security':
        this.loadSecurityData();
        break;
    }
  }

  /**
   * Load dashboard overview data
   */
  async loadDashboardData() {
    try {
      // Load orders for stats
      const ordersResponse = await ApiService.orders.getAll({ limit: 100 });
      this.orders = ordersResponse.orders || [];

      // Load addresses for count
      const addressesResponse = await ApiService.addresses.getAll();
      this.addresses = addressesResponse.data?.addresses || [];

      // Update stats
      this.updateDashboardStats();
      
      // Update sidebar badges
      this.updateSidebarBadges();

    } catch (error) {
      console.error('Failed to load dashboard data:', error);
    }
  }

  /**
   * Load overview tab data
   */
  async loadOverviewData() {
    await this.loadDashboardData();
    await this.loadRecentOrders();
  }

  /**
   * Update dashboard statistics
   */
  updateDashboardStats() {
    const totalOrders = this.orders.length;
    const totalSpent = this.orders.reduce((sum, order) => sum + parseFloat(order.total_amount || 0), 0);
    const pendingOrders = this.orders.filter(order => 
      ['placed', 'processing', 'shipped', 'out_for_delivery'].includes(order.status)
    ).length;
    const savedAddresses = this.addresses.length;

    // Update stat cards
    this.updateStatCard('total-orders', totalOrders);
    this.updateStatCard('total-spent', `₹${totalSpent.toLocaleString('en-IN')}`);
    this.updateStatCard('pending-orders', pendingOrders);
    this.updateStatCard('saved-addresses', savedAddresses);
  }

  /**
   * Update individual stat card
   * @param {string} id - Element ID
   * @param {string|number} value - Value to display
   */
  updateStatCard(id, value) {
    const element = DOMUtils.getId(id);
    if (element) {
      element.textContent = value;
    }
  }

  /**
   * Update sidebar badges
   */
  updateSidebarBadges() {
    const ordersCount = DOMUtils.getId('orders-count');
    const addressesCount = DOMUtils.getId('addresses-count');

    if (ordersCount) {
      ordersCount.textContent = this.orders.length;
    }

    if (addressesCount) {
      addressesCount.textContent = this.addresses.length;
    }
  }

  /**
   * Load recent orders for overview
   */
  async loadRecentOrders() {
    const recentOrdersContainer = DOMUtils.getId('recent-orders');
    if (!recentOrdersContainer) return;

    try {
      const recentOrders = this.orders.slice(0, 3);
      
      if (recentOrders.length === 0) {
        recentOrdersContainer.innerHTML = `
          <div class="empty-state">
            <i class="ri-shopping-bag-line"></i>
            <h3>No orders yet</h3>
            <p>Start shopping to see your orders here</p>
            <a href="products.html" class="btn btn--primary btn--small">
              Start Shopping
            </a>
          </div>
        `;
        return;
      }

      const ordersHTML = recentOrders.map(order => this.createOrderCard(order)).join('');
      recentOrdersContainer.innerHTML = ordersHTML;

    } catch (error) {
      console.error('Failed to load recent orders:', error);
      recentOrdersContainer.innerHTML = `
        <div class="error-state">
          <p>Failed to load recent orders</p>
        </div>
      `;
    }
  }

  /**
   * Load orders with pagination and filters
   */
  async loadOrders() {
    const ordersContainer = DOMUtils.getId('orders-container');
    if (!ordersContainer) return;

    try {
      // Show loading state
      ordersContainer.innerHTML = `
        <div class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading your orders...</p>
        </div>
      `;

      // Get filter values
      const statusFilter = DOMUtils.getId('order-status-filter')?.value || '';
      const searchQuery = DOMUtils.getId('order-search')?.value || '';

      // Prepare API parameters
      const params = {
        page: this.currentPage,
        limit: this.ordersPerPage
      };

      if (statusFilter) {
        params.status = statusFilter;
      }

      if (searchQuery) {
        params.search = searchQuery;
      }

      // Fetch orders
      const response = await ApiService.orders.getAll(params);
      const orders = response.orders || [];
      const pagination = response.pagination || {};

      if (orders.length === 0) {
        ordersContainer.innerHTML = `
          <div class="empty-state">
            <i class="ri-shopping-bag-line"></i>
            <h3>No orders found</h3>
            <p>No orders match your current filters</p>
          </div>
        `;
        this.hidePagination();
        return;
      }

      // Render orders
      const ordersHTML = orders.map(order => this.createOrderCard(order, true)).join('');
      ordersContainer.innerHTML = ordersHTML;

      // Update pagination
      this.updatePagination(pagination);

    } catch (error) {
      console.error('Failed to load orders:', error);
      ordersContainer.innerHTML = `
        <div class="error-state">
          <p>Failed to load orders. Please try again.</p>
          <button class="btn btn--outline btn--small" onclick="profileManager.loadOrders()">
            Retry
          </button>
        </div>
      `;
    }
  }

  /**
   * Create order card HTML
   * @param {Object} order - Order data
   * @param {boolean} detailed - Show detailed view
   * @returns {string} HTML string
   */
  createOrderCard(order, detailed = false) {
    const orderDate = new Date(order.created_at).toLocaleDateString('en-IN');
    const items = order.items || [];
    const itemsPreview = items.slice(0, 3);

    return `
      <div class="order-card">
        <div class="order-header">
          <div class="order-number">#${order.order_number}</div>
          <div class="order-status ${order.status}">${order.status.replace('_', ' ')}</div>
        </div>
        
        <div class="order-details">
          <div class="order-detail">
            <div class="order-detail-label">Order Date</div>
            <div class="order-detail-value">${orderDate}</div>
          </div>
          <div class="order-detail">
            <div class="order-detail-label">Total Amount</div>
            <div class="order-detail-value">₹${parseFloat(order.total_amount).toLocaleString('en-IN')}</div>
          </div>
          <div class="order-detail">
            <div class="order-detail-label">Payment Method</div>
            <div class="order-detail-value">${order.payment_method.toUpperCase()}</div>
          </div>
          ${detailed ? `
          <div class="order-detail">
            <div class="order-detail-label">Items</div>
            <div class="order-detail-value">${items.length} item${items.length !== 1 ? 's' : ''}</div>
          </div>
          ` : ''}
        </div>

        ${itemsPreview.length > 0 ? `
        <div class="order-items">
          ${itemsPreview.map(item => `
            <img src="${item.image_url || '../assets/placeholder.jpg'}" 
                 alt="${item.product_name}" 
                 class="order-item-image"
                 onerror="this.src='../assets/placeholder.jpg'">
          `).join('')}
          ${items.length > 3 ? `<span class="more-items">+${items.length - 3} more</span>` : ''}
        </div>
        ` : ''}

        <div class="order-actions">
          <button class="btn btn--outline btn--small" onclick="profileManager.viewOrderDetails('${order.id}')">
            View Details
          </button>
          ${this.canTrackOrder(order.status) ? `
          <button class="btn btn--primary btn--small" onclick="profileManager.trackOrder('${order.id}')">
            Track Order
          </button>
          ` : ''}
        </div>
      </div>
    `;
  }

  /**
   * Check if order can be tracked
   * @param {string} status - Order status
   * @returns {boolean} Can track
   */
  canTrackOrder(status) {
    return ['processing', 'shipped', 'out_for_delivery'].includes(status);
  }

  /**
   * Filter orders based on current filters
   */
  filterOrders() {
    this.currentPage = 1; // Reset to first page
    this.loadOrders();
  }

  /**
   * Update pagination controls
   * @param {Object} pagination - Pagination data
   */
  updatePagination(pagination) {
    const paginationContainer = DOMUtils.getId('orders-pagination');
    const prevBtn = DOMUtils.getId('prev-page');
    const nextBtn = DOMUtils.getId('next-page');
    const pageInfo = DOMUtils.getId('page-info');

    if (!paginationContainer) return;

    if (pagination.total_pages <= 1) {
      paginationContainer.style.display = 'none';
      return;
    }

    paginationContainer.style.display = 'block';

    // Update buttons
    if (prevBtn) {
      prevBtn.disabled = pagination.current_page <= 1;
    }

    if (nextBtn) {
      nextBtn.disabled = pagination.current_page >= pagination.total_pages;
    }

    // Update page info
    if (pageInfo) {
      pageInfo.textContent = `Page ${pagination.current_page} of ${pagination.total_pages}`;
    }
  }

  /**
   * Hide pagination
   */
  hidePagination() {
    const paginationContainer = DOMUtils.getId('orders-pagination');
    if (paginationContainer) {
      paginationContainer.style.display = 'none';
    }
  }

  /**
   * View order details
   * @param {string} orderId - Order ID
   */
  async viewOrderDetails(orderId) {
    try {
      this.showLoading();
      
      const response = await ApiService.orders.getById(orderId);
      const order = response.order;

      this.showOrderDetailModal(order);
      
    } catch (error) {
      console.error('Failed to load order details:', error);
      this.showError('Failed to load order details');
    } finally {
      this.hideLoading();
    }
  }

  /**
   * Show order detail modal
   * @param {Object} order - Order data
   */
  showOrderDetailModal(order) {
    const modal = DOMUtils.getId('order-detail-modal');
    const content = DOMUtils.getId('order-detail-content');
    
    if (!modal || !content) return;

    const orderDate = new Date(order.created_at).toLocaleDateString('en-IN');
    const items = order.items || [];

    content.innerHTML = `
      <div class="order-detail-full">
        <div class="order-summary">
          <h4>Order #${order.order_number}</h4>
          <p>Placed on ${orderDate}</p>
          <div class="status-badge ${order.status}">${order.status.replace('_', ' ')}</div>
        </div>

        <div class="order-sections">
          <div class="order-section">
            <h5>Items Ordered</h5>
            <div class="order-items-list">
              ${items.map(item => `
                <div class="order-item-detail">
                  <img src="${item.image_url || '../assets/placeholder.jpg'}" 
                       alt="${item.product_name}" 
                       class="item-image"
                       onerror="this.src='../assets/placeholder.jpg'">
                  <div class="item-info">
                    <h6>${item.product_name}</h6>
                    <p>Quantity: ${item.quantity}</p>
                    <p>Price: ₹${parseFloat(item.unit_price).toLocaleString('en-IN')}</p>
                  </div>
                  <div class="item-total">
                    ₹${parseFloat(item.total_price).toLocaleString('en-IN')}
                  </div>
                </div>
              `).join('')}
            </div>
          </div>

          <div class="order-section">
            <h5>Shipping Address</h5>
            <div class="address-info">
              <p><strong>${order.shipping_address.first_name} ${order.shipping_address.last_name}</strong></p>
              <p>${order.shipping_address.address_line1}</p>
              ${order.shipping_address.address_line2 ? `<p>${order.shipping_address.address_line2}</p>` : ''}
              <p>${order.shipping_address.city}, ${order.shipping_address.state} ${order.shipping_address.postal_code}</p>
              <p>${order.shipping_address.country}</p>
              ${order.shipping_address.phone ? `<p>Phone: ${order.shipping_address.phone}</p>` : ''}
            </div>
          </div>

          <div class="order-section">
            <h5>Payment Information</h5>
            <div class="payment-info">
              <p><strong>Method:</strong> ${order.payment_method.toUpperCase()}</p>
              <p><strong>Status:</strong> ${order.payment_status}</p>
              ${order.payment_details?.razorpay_payment_id ? 
                `<p><strong>Transaction ID:</strong> ${order.payment_details.razorpay_payment_id}</p>` : ''}
            </div>
          </div>

          <div class="order-section">
            <h5>Order Total</h5>
            <div class="order-totals">
              <div class="total-line">
                <span>Subtotal:</span>
                <span>₹${(parseFloat(order.total_amount) - parseFloat(order.shipping_amount || 0) - parseFloat(order.tax_amount || 0) + parseFloat(order.discount_amount || 0)).toLocaleString('en-IN')}</span>
              </div>
              ${order.discount_amount > 0 ? `
              <div class="total-line discount">
                <span>Discount:</span>
                <span>-₹${parseFloat(order.discount_amount).toLocaleString('en-IN')}</span>
              </div>
              ` : ''}
              <div class="total-line">
                <span>Shipping:</span>
                <span>₹${parseFloat(order.shipping_amount || 0).toLocaleString('en-IN')}</span>
              </div>
              <div class="total-line">
                <span>Tax:</span>
                <span>₹${parseFloat(order.tax_amount || 0).toLocaleString('en-IN')}</span>
              </div>
              <div class="total-line final">
                <span><strong>Total:</strong></span>
                <span><strong>₹${parseFloat(order.total_amount).toLocaleString('en-IN')}</strong></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    modal.classList.add('active');
  }

  /**
   * Track order
   * @param {string} orderId - Order ID
   */
  trackOrder(orderId) {
    // For now, just show order details
    // In a real implementation, this would show tracking information
    this.viewOrderDetails(orderId);
  }

  /**
   * Load profile form with current user data
   */
  loadProfileForm() {
    const firstNameInput = DOMUtils.getId('profile-first-name');
    const lastNameInput = DOMUtils.getId('profile-last-name');
    const emailInput = DOMUtils.getId('profile-email');
    const phoneInput = DOMUtils.getId('profile-phone');

    if (firstNameInput) firstNameInput.value = this.currentUser.firstName || '';
    if (lastNameInput) lastNameInput.value = this.currentUser.lastName || '';
    if (emailInput) emailInput.value = this.currentUser.email || '';
    if (phoneInput) phoneInput.value = this.currentUser.phone || '';
  }

  /**
   * Handle profile form update
   * @param {Event} event - Form submit event
   */
  async handleProfileUpdate(event) {
    const form = event.target;
    const submitBtn = DOMUtils.getId('save-profile-btn');

    try {
      // Show loading state
      this.setButtonLoading(submitBtn, true);

      // Get form data
      const formData = new FormData(form);
      const updateData = {
        firstName: formData.get('firstName'),
        lastName: formData.get('lastName'),
        phone: formData.get('phone') || null
      };

      // Update profile
      const response = await ApiService.auth.updateProfile(updateData);

      if (response.success) {
        this.currentUser = response.data.user;
        this.updateUserDisplay();
        this.showSuccess('Profile updated successfully!');
      }

    } catch (error) {
      console.error('Profile update error:', error);
      this.showError(error.message || 'Failed to update profile');
    } finally {
      this.setButtonLoading(submitBtn, false);
    }
  }

  /**
   * Load addresses
   */
  async loadAddresses() {
    const addressesContainer = DOMUtils.getId('addresses-container');
    if (!addressesContainer) return;

    try {
      // Show loading state
      addressesContainer.innerHTML = `
        <div class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading your addresses...</p>
        </div>
      `;

      const response = await ApiService.addresses.getAll();
      this.addresses = response.data?.addresses || [];

      if (this.addresses.length === 0) {
        addressesContainer.innerHTML = `
          <div class="empty-state">
            <i class="ri-map-pin-line"></i>
            <h3>No addresses saved</h3>
            <p>Add your first address to make checkout faster</p>
            <button class="btn btn--primary" onclick="profileManager.showAddressModal()">
              Add Address
            </button>
          </div>
        `;
        return;
      }

      // Render addresses
      const addressesHTML = this.addresses.map(address => this.createAddressCard(address)).join('');
      addressesContainer.innerHTML = addressesHTML;

    } catch (error) {
      console.error('Failed to load addresses:', error);
      addressesContainer.innerHTML = `
        <div class="error-state">
          <p>Failed to load addresses</p>
          <button class="btn btn--outline btn--small" onclick="profileManager.loadAddresses()">
            Retry
          </button>
        </div>
      `;
    }
  }

  /**
   * Create address card HTML
   * @param {Object} address - Address data
   * @returns {string} HTML string
   */
  createAddressCard(address) {
    return `
      <div class="address-card ${address.isDefault ? 'default' : ''}">
        <div class="address-header">
          <div class="address-type">
            <i class="ri-${this.getAddressIcon(address.type)}"></i>
            ${address.type}
          </div>
          ${address.isDefault ? '<div class="default-badge">Default</div>' : ''}
        </div>
        
        <div class="address-content">
          <div class="address-name">${address.firstName} ${address.lastName}</div>
          <div class="address-text">
            ${address.addressLine1}<br>
            ${address.addressLine2 ? address.addressLine2 + '<br>' : ''}
            ${address.city}, ${address.state} ${address.postalCode}<br>
            ${address.country}
            ${address.phone ? '<br>Phone: ' + address.phone : ''}
          </div>
        </div>
        
        <div class="address-actions">
          <button class="address-btn" onclick="profileManager.editAddress(${address.id})">
            Edit
          </button>
          ${!address.isDefault ? `
          <button class="address-btn" onclick="profileManager.setDefaultAddress(${address.id})">
            Set Default
          </button>
          ` : ''}
          <button class="address-btn danger" onclick="profileManager.deleteAddress(${address.id})">
            Delete
          </button>
        </div>
      </div>
    `;
  }

  /**
   * Get icon for address type
   * @param {string} type - Address type
   * @returns {string} Icon class
   */
  getAddressIcon(type) {
    const icons = {
      home: 'home-line',
      work: 'building-line',
      other: 'map-pin-line'
    };
    return icons[type] || 'map-pin-line';
  }

  /**
   * Show address modal for adding/editing
   * @param {Object} address - Address to edit (null for new)
   */
  showAddressModal(address = null) {
    const modal = DOMUtils.getId('address-modal');
    const title = DOMUtils.getId('address-modal-title');
    const form = DOMUtils.getId('address-form');
    
    if (!modal || !form) return;

    // Update modal title
    if (title) {
      title.textContent = address ? 'Edit Address' : 'Add New Address';
    }

    // Create form HTML
    form.innerHTML = this.createAddressFormHTML(address);

    // Show modal
    modal.classList.add('active');

    // Setup form handler
    DOMUtils.addEventListener(form, 'submit', (e) => {
      e.preventDefault();
      this.handleAddressSubmit(e, address);
    });
  }

  /**
   * Create address form HTML
   * @param {Object} address - Address data for editing
   * @returns {string} HTML string
   */
  createAddressFormHTML(address = null) {
    return `
      <div class="form-row">
        <div class="form-group">
          <label for="address-first-name" class="form-label">
            First Name <span class="required">*</span>
          </label>
          <input type="text" id="address-first-name" name="firstName" 
                 class="form-input" value="${address?.firstName || ''}" required>
        </div>
        <div class="form-group">
          <label for="address-last-name" class="form-label">
            Last Name <span class="required">*</span>
          </label>
          <input type="text" id="address-last-name" name="lastName" 
                 class="form-input" value="${address?.lastName || ''}" required>
        </div>
      </div>

      <div class="form-group">
        <label for="address-type" class="form-label">Address Type</label>
        <select id="address-type" name="type" class="form-input">
          <option value="home" ${address?.type === 'home' ? 'selected' : ''}>Home</option>
          <option value="work" ${address?.type === 'work' ? 'selected' : ''}>Work</option>
          <option value="other" ${address?.type === 'other' ? 'selected' : ''}>Other</option>
        </select>
      </div>

      <div class="form-group">
        <label for="address-line1" class="form-label">
          Address Line 1 <span class="required">*</span>
        </label>
        <input type="text" id="address-line1" name="addressLine1" 
               class="form-input" value="${address?.addressLine1 || ''}" 
               placeholder="Street address, building name" required>
      </div>

      <div class="form-group">
        <label for="address-line2" class="form-label">Address Line 2</label>
        <input type="text" id="address-line2" name="addressLine2" 
               class="form-input" value="${address?.addressLine2 || ''}" 
               placeholder="Apartment, suite, floor (optional)">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="address-city" class="form-label">
            City <span class="required">*</span>
          </label>
          <input type="text" id="address-city" name="city" 
                 class="form-input" value="${address?.city || ''}" required>
        </div>
        <div class="form-group">
          <label for="address-state" class="form-label">
            State <span class="required">*</span>
          </label>
          <input type="text" id="address-state" name="state" 
                 class="form-input" value="${address?.state || ''}" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="address-postal-code" class="form-label">
            Postal Code <span class="required">*</span>
          </label>
          <input type="text" id="address-postal-code" name="postalCode" 
                 class="form-input" value="${address?.postalCode || ''}" required>
        </div>
        <div class="form-group">
          <label for="address-country" class="form-label">Country</label>
          <input type="text" id="address-country" name="country" 
                 class="form-input" value="${address?.country || 'India'}" readonly>
        </div>
      </div>

      <div class="form-group">
        <label for="address-phone" class="form-label">Phone Number</label>
        <input type="tel" id="address-phone" name="phone" 
               class="form-input" value="${address?.phone || ''}" 
               placeholder="+91 98765 43210">
      </div>

      <div class="form-group">
        <label class="checkbox-label">
          <input type="checkbox" name="isDefault" ${address?.isDefault ? 'checked' : ''}>
          <span class="checkbox-custom"></span>
          <span class="checkbox-text">Set as default address</span>
        </label>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn--outline" onclick="profileManager.closeAddressModal()">
          Cancel
        </button>
        <button type="submit" class="btn btn--primary">
          ${address ? 'Update Address' : 'Add Address'}
        </button>
      </div>
    `;
  }

  /**
   * Handle address form submission
   * @param {Event} event - Form submit event
   * @param {Object} address - Existing address for editing
   */
  async handleAddressSubmit(event, address = null) {
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');

    try {
      // Show loading state
      this.setButtonLoading(submitBtn, true);

      // Get form data
      const formData = new FormData(form);
      const addressData = {
        type: formData.get('type'),
        firstName: formData.get('firstName'),
        lastName: formData.get('lastName'),
        addressLine1: formData.get('addressLine1'),
        addressLine2: formData.get('addressLine2') || null,
        city: formData.get('city'),
        state: formData.get('state'),
        postalCode: formData.get('postalCode'),
        country: formData.get('country'),
        phone: formData.get('phone') || null,
        isDefault: formData.has('isDefault')
      };

      let response;
      if (address) {
        // Update existing address
        response = await ApiService.addresses.update(address.id, addressData);
      } else {
        // Create new address
        response = await ApiService.addresses.create(addressData);
      }

      if (response.success) {
        this.showSuccess(address ? 'Address updated successfully!' : 'Address added successfully!');
        this.closeAddressModal();
        await this.loadAddresses();
        this.updateSidebarBadges();
      }

    } catch (error) {
      console.error('Address save error:', error);
      this.showError(error.message || 'Failed to save address');
    } finally {
      this.setButtonLoading(submitBtn, false);
    }
  }

  /**
   * Edit address
   * @param {number} addressId - Address ID
   */
  editAddress(addressId) {
    const address = this.addresses.find(addr => addr.id === addressId);
    if (address) {
      this.showAddressModal(address);
    }
  }

  /**
   * Set default address
   * @param {number} addressId - Address ID
   */
  async setDefaultAddress(addressId) {
    try {
      this.showLoading();
      
      // Update address to set as default
      const address = this.addresses.find(addr => addr.id === addressId);
      if (address) {
        const response = await ApiService.addresses.update(addressId, {
          ...address,
          isDefault: true
        });

        if (response.success) {
          this.showSuccess('Default address updated!');
          await this.loadAddresses();
        }
      }

    } catch (error) {
      console.error('Set default address error:', error);
      this.showError('Failed to update default address');
    } finally {
      this.hideLoading();
    }
  }

  /**
   * Delete address
   * @param {number} addressId - Address ID
   */
  async deleteAddress(addressId) {
    if (!confirm('Are you sure you want to delete this address?')) {
      return;
    }

    try {
      this.showLoading();
      
      const response = await ApiService.addresses.delete(addressId);

      if (response.success) {
        this.showSuccess('Address deleted successfully!');
        await this.loadAddresses();
        this.updateSidebarBadges();
      }

    } catch (error) {
      console.error('Delete address error:', error);
      this.showError(error.message || 'Failed to delete address');
    } finally {
      this.hideLoading();
    }
  }

  /**
   * Close address modal
   */
  closeAddressModal() {
    const modal = DOMUtils.getId('address-modal');
    if (modal) {
      modal.classList.remove('active');
    }
  }

  /**
   * Load security data
   */
  loadSecurityData() {
    // Update last login info
    const lastLoginElement = DOMUtils.getId('last-login');
    if (lastLoginElement && this.currentUser.createdAt) {
      const lastLogin = new Date(this.currentUser.createdAt).toLocaleDateString('en-IN');
      lastLoginElement.textContent = lastLogin;
    }
  }

  /**
   * Setup modal event handlers
   */
  setupModalHandlers() {
    // Order detail modal
    const orderDetailOverlay = DOMUtils.getId('order-detail-overlay');
    const closeOrderDetail = DOMUtils.getId('close-order-detail');
    
    if (orderDetailOverlay) {
      DOMUtils.addEventListener(orderDetailOverlay, 'click', () => {
        this.closeOrderDetailModal();
      });
    }

    if (closeOrderDetail) {
      DOMUtils.addEventListener(closeOrderDetail, 'click', () => {
        this.closeOrderDetailModal();
      });
    }

    // Address modal
    const addressOverlay = DOMUtils.getId('address-overlay');
    const closeAddressModal = DOMUtils.getId('close-address-modal');
    
    if (addressOverlay) {
      DOMUtils.addEventListener(addressOverlay, 'click', () => {
        this.closeAddressModal();
      });
    }

    if (closeAddressModal) {
      DOMUtils.addEventListener(closeAddressModal, 'click', () => {
        this.closeAddressModal();
      });
    }
  }

  /**
   * Close order detail modal
   */
  closeOrderDetailModal() {
    const modal = DOMUtils.getId('order-detail-modal');
    if (modal) {
      modal.classList.remove('active');
    }
  }

  /**
   * Handle logout
   */
  async handleLogout() {
    if (!confirm('Are you sure you want to logout?')) {
      return;
    }

    try {
      this.showLoading();
      
      // Call logout API
      await ApiService.auth.logout();
      
      // Redirect to login
      window.location.href = 'login.html';

    } catch (error) {
      console.error('Logout error:', error);
      // Even if API fails, clear local data and redirect
      CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
      CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
      window.location.href = 'login.html';
    }
  }

  /**
   * Set button loading state
   * @param {Element} button - Button element
   * @param {boolean} loading - Loading state
   */
  setButtonLoading(button, loading) {
    if (!button) return;

    const btnText = button.querySelector('.btn-text');
    const btnLoading = button.querySelector('.btn-loading');
    
    if (loading) {
      button.disabled = true;
      if (btnText) btnText.style.display = 'none';
      if (btnLoading) btnLoading.style.display = 'flex';
    } else {
      button.disabled = false;
      if (btnText) btnText.style.display = 'block';
      if (btnLoading) btnLoading.style.display = 'none';
    }
  }

  /**
   * Show loading overlay
   */
  showLoading() {
    const overlay = DOMUtils.getId('loading-overlay');
    if (overlay) {
      overlay.style.display = 'flex';
    }
  }

  /**
   * Hide loading overlay
   */
  hideLoading() {
    const overlay = DOMUtils.getId('loading-overlay');
    if (overlay) {
      overlay.style.display = 'none';
    }
  }

  /**
   * Show success message
   * @param {string} message - Success message
   */
  showSuccess(message) {
    if (window.NotificationManager) {
      NotificationManager.show(message, 'success');
    } else {
      alert(message);
    }
  }

  /**
   * Show error message
   * @param {string} message - Error message
   */
  showError(message) {
    if (window.NotificationManager) {
      NotificationManager.show(message, 'error');
    } else {
      alert(message);
    }
  }

  /**
   * Debounce function
   * @param {Function} func - Function to debounce
   * @param {number} wait - Wait time in milliseconds
   * @returns {Function} Debounced function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
}

// Global function to switch tabs (called from HTML)
window.switchTab = function(tabName) {
  if (window.profileManager) {
    window.profileManager.switchTab(tabName);
  }
};

// Initialize profile manager when DOM is ready
DOMUtils.addEventListener(document, 'DOMContentLoaded', () => {
  window.profileManager = new ProfileManager();
});

// Export for global access
window.ProfileManager = ProfileManager;