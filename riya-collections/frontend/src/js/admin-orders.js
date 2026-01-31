/**
 * Admin Orders Management JavaScript
 * Handles order management functionality for admin dashboard
 */

class AdminOrdersManager {
  constructor() {
    this.orders = [];
    this.filteredOrders = [];
    this.selectedOrders = new Set();
    this.currentPage = 1;
    this.ordersPerPage = 20;
    this.totalOrders = 0;
    this.filters = {
      search: '',
      status: '',
      payment_method: '',
      payment_status: '',
      start_date: '',
      end_date: '',
      sort_by: 'created_at',
      sort_order: 'desc'
    };
    this.orderStats = null;
    
    this.init();
  }

  async init() {
    // Check if we're on the orders section
    if (!document.getElementById('ordersSection')) {
      return;
    }

    this.bindEvents();
    await this.loadOrders();
  }

  /**
   * Bind event listeners
   */
  bindEvents() {
    // Navigation - load orders when orders section is activated
    document.addEventListener('click', (e) => {
      if (e.target.matches('[data-section="orders"]')) {
        setTimeout(() => this.loadOrders(), 100);
      }
    });

    // Filter and search controls
    const searchBtn = document.getElementById('searchOrdersBtn');
    const searchInput = document.getElementById('orderSearch');
    const applyFiltersBtn = document.getElementById('applyOrderFilters');
    const clearFiltersBtn = document.getElementById('clearOrderFilters');

    if (searchBtn) {
      searchBtn.addEventListener('click', () => this.handleSearch());
    }

    if (searchInput) {
      searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          this.handleSearch();
        }
      });
    }

    if (applyFiltersBtn) {
      applyFiltersBtn.addEventListener('click', () => this.applyFilters());
    }

    if (clearFiltersBtn) {
      clearFiltersBtn.addEventListener('click', () => this.clearFilters());
    }

    // Action buttons
    const refreshBtn = document.getElementById('refreshOrders');
    const exportBtn = document.getElementById('exportOrders');
    const bulkUpdateBtn = document.getElementById('bulkUpdateBtn');

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => this.loadOrders());
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => this.exportOrders());
    }

    if (bulkUpdateBtn) {
      bulkUpdateBtn.addEventListener('click', () => this.showBulkUpdateModal());
    }

    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAllOrders');
    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', (e) => this.handleSelectAll(e.target.checked));
    }

    // Pagination
    const prevBtn = document.getElementById('prevOrdersPage');
    const nextBtn = document.getElementById('nextOrdersPage');

    if (prevBtn) {
      prevBtn.addEventListener('click', () => this.goToPage(this.currentPage - 1));
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', () => this.goToPage(this.currentPage + 1));
    }

    // Modal events
    this.bindModalEvents();
  }

  /**
   * Bind modal event listeners
   */
  bindModalEvents() {
    // Order detail modal
    const closeOrderDetail = document.getElementById('closeOrderDetail');
    if (closeOrderDetail) {
      closeOrderDetail.addEventListener('click', () => this.hideOrderDetailModal());
    }

    // Order status modal
    const closeOrderStatus = document.getElementById('closeOrderStatus');
    const cancelOrderStatus = document.getElementById('cancelOrderStatus');
    const orderStatusForm = document.getElementById('orderStatusForm');

    if (closeOrderStatus) {
      closeOrderStatus.addEventListener('click', () => this.hideOrderStatusModal());
    }

    if (cancelOrderStatus) {
      cancelOrderStatus.addEventListener('click', () => this.hideOrderStatusModal());
    }

    if (orderStatusForm) {
      orderStatusForm.addEventListener('submit', (e) => this.handleOrderStatusUpdate(e));
    }

    // Bulk update modal
    const closeBulkUpdate = document.getElementById('closeBulkUpdate');
    const cancelBulkUpdate = document.getElementById('cancelBulkUpdate');
    const bulkUpdateForm = document.getElementById('bulkUpdateForm');

    if (closeBulkUpdate) {
      closeBulkUpdate.addEventListener('click', () => this.hideBulkUpdateModal());
    }

    if (cancelBulkUpdate) {
      cancelBulkUpdate.addEventListener('click', () => this.hideBulkUpdateModal());
    }

    if (bulkUpdateForm) {
      bulkUpdateForm.addEventListener('submit', (e) => this.handleBulkUpdate(e));
    }

    // Close modals on overlay click
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-overlay')) {
        this.hideAllModals();
      }
    });
  }

  /**
   * Load orders from API
   */
  async loadOrders() {
    try {
      this.showLoading(true);
      
      const token = localStorage.getItem('admin_auth_token');
      const queryParams = new URLSearchParams({
        page: this.currentPage,
        limit: this.ordersPerPage,
        ...this.filters
      });

      const response = await fetch(`${API_CONFIG.BASE_URL}/admin/orders?${queryParams}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      
      if (data.orders) {
        this.orders = data.orders;
        this.totalOrders = data.pagination?.total_orders || 0;
        this.orderStats = data.statistics || {};
        
        this.updateOrdersUI();
        this.updateOrderStats();
        this.updatePagination(data.pagination);
      } else {
        throw new Error('Invalid response format');
      }
      
    } catch (error) {
      console.error('Orders loading error:', error);
      NotificationManager.show('Failed to load orders', 'error');
    } finally {
      this.showLoading(false);
    }
  }

  /**
   * Update orders UI
   */
  updateOrdersUI() {
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (this.orders.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="9" class="table-loading">No orders found</td>
        </tr>
      `;
      return;
    }

    this.orders.forEach(order => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="order-checkbox">
          <label class="form-checkbox">
            <input type="checkbox" class="order-select" data-order-id="${order.id}" ${this.selectedOrders.has(order.id) ? 'checked' : ''}>
            <span class="checkbox-mark"></span>
          </label>
        </td>
        <td>
          <div class="order-info">
            <a href="#" class="order-number" data-order-id="${order.id}">#${order.order_number}</a>
            <div class="order-date">${this.formatDate(order.created_at)}</div>
          </div>
        </td>
        <td>
          <div class="customer-info">
            <div class="customer-name">${order.customer.name}</div>
            <div class="customer-email">${order.customer.email}</div>
            <div class="customer-location">${order.shipping_location}</div>
          </div>
        </td>
        <td>
          <span class="order-status order-status--${order.status}">${this.formatStatus(order.status)}</span>
        </td>
        <td>
          <div class="payment-info">
            <span class="payment-method payment-method--${order.payment_method}">${this.formatPaymentMethod(order.payment_method)}</span>
            <span class="payment-status payment-status--${order.payment_status}">${this.formatPaymentStatus(order.payment_status)}</span>
          </div>
        </td>
        <td>
          <div class="order-amount">${this.formatCurrency(order.total_amount)}</div>
        </td>
        <td>
          <div class="order-items-count">${order.item_count} items (${order.total_items} qty)</div>
        </td>
        <td>
          <div class="order-date">${this.formatDate(order.created_at)}</div>
        </td>
        <td>
          <div class="order-actions">
            <button class="action-btn action-btn--view" title="View Details" data-action="view" data-order-id="${order.id}">
              <i class="ri-eye-line"></i>
            </button>
            <button class="action-btn action-btn--status" title="Update Status" data-action="status" data-order-id="${order.id}">
              <i class="ri-edit-line"></i>
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(row);
    });

    // Bind row events
    this.bindRowEvents();
    this.updateSelectedCount();
  }

  /**
   * Bind events for table rows
   */
  bindRowEvents() {
    // Order selection checkboxes
    document.querySelectorAll('.order-select').forEach(checkbox => {
      checkbox.addEventListener('change', (e) => {
        const orderId = parseInt(e.target.dataset.orderId);
        if (e.target.checked) {
          this.selectedOrders.add(orderId);
        } else {
          this.selectedOrders.delete(orderId);
        }
        this.updateSelectedCount();
      });
    });

    // Order number links
    document.querySelectorAll('.order-number').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const orderId = parseInt(e.target.dataset.orderId);
        this.showOrderDetail(orderId);
      });
    });

    // Action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const action = e.currentTarget.dataset.action;
        const orderId = parseInt(e.currentTarget.dataset.orderId);
        
        switch (action) {
          case 'view':
            this.showOrderDetail(orderId);
            break;
          case 'status':
            this.showOrderStatusModal(orderId);
            break;
        }
      });
    });
  }

  /**
   * Update order statistics
   */
  updateOrderStats() {
    if (!this.orderStats) return;

    // Update stat cards
    const totalOrdersEl = document.getElementById('totalOrdersCount');
    const pendingOrdersEl = document.getElementById('pendingOrdersCount');
    const revenueEl = document.getElementById('ordersRevenue');
    const aovEl = document.getElementById('averageOrderValue');

    if (totalOrdersEl) {
      totalOrdersEl.textContent = this.formatNumber(this.orderStats.total_orders || 0);
    }

    if (pendingOrdersEl) {
      const pendingCount = (this.orderStats.status_breakdown?.placed || 0) + 
                          (this.orderStats.status_breakdown?.processing || 0);
      pendingOrdersEl.textContent = this.formatNumber(pendingCount);
    }

    if (revenueEl) {
      revenueEl.textContent = this.formatCurrency(this.orderStats.total_revenue || 0);
    }

    if (aovEl) {
      aovEl.textContent = this.formatCurrency(this.orderStats.average_order_value || 0);
    }
  }

  /**
   * Update pagination
   */
  updatePagination(pagination) {
    if (!pagination) return;

    const paginationContainer = document.getElementById('ordersPagination');
    const paginationInfo = document.getElementById('ordersPaginationInfo');
    const paginationPages = document.getElementById('ordersPaginationPages');
    const prevBtn = document.getElementById('prevOrdersPage');
    const nextBtn = document.getElementById('nextOrdersPage');

    if (paginationContainer) {
      paginationContainer.style.display = pagination.total_pages > 1 ? 'flex' : 'none';
    }

    if (paginationInfo) {
      const start = ((pagination.current_page - 1) * pagination.per_page) + 1;
      const end = Math.min(start + pagination.per_page - 1, pagination.total_orders);
      paginationInfo.textContent = `Showing ${start}-${end} of ${pagination.total_orders} orders`;
    }

    if (prevBtn) {
      prevBtn.disabled = pagination.current_page <= 1;
    }

    if (nextBtn) {
      nextBtn.disabled = pagination.current_page >= pagination.total_pages;
    }

    // Generate page numbers
    if (paginationPages) {
      paginationPages.innerHTML = '';
      const maxPages = 5;
      const startPage = Math.max(1, pagination.current_page - Math.floor(maxPages / 2));
      const endPage = Math.min(pagination.total_pages, startPage + maxPages - 1);

      for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `pagination-page ${i === pagination.current_page ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => this.goToPage(i));
        paginationPages.appendChild(pageBtn);
      }
    }
  }

  /**
   * Handle search
   */
  handleSearch() {
    const searchInput = document.getElementById('orderSearch');
    if (searchInput) {
      this.filters.search = searchInput.value.trim();
      this.currentPage = 1;
      this.loadOrders();
    }
  }

  /**
   * Apply filters
   */
  applyFilters() {
    // Get filter values
    const statusFilter = document.getElementById('statusFilter');
    const paymentMethodFilter = document.getElementById('paymentMethodFilter');
    const paymentStatusFilter = document.getElementById('paymentStatusFilter');
    const dateFromFilter = document.getElementById('dateFromFilter');
    const dateToFilter = document.getElementById('dateToFilter');
    const sortByFilter = document.getElementById('orderSortBy');
    const sortOrderFilter = document.getElementById('orderSortOrder');

    this.filters = {
      search: this.filters.search,
      status: statusFilter?.value || '',
      payment_method: paymentMethodFilter?.value || '',
      payment_status: paymentStatusFilter?.value || '',
      start_date: dateFromFilter?.value || '',
      end_date: dateToFilter?.value || '',
      sort_by: sortByFilter?.value || 'created_at',
      sort_order: sortOrderFilter?.value || 'desc'
    };

    this.currentPage = 1;
    this.loadOrders();
  }

  /**
   * Clear filters
   */
  clearFilters() {
    // Reset filter form
    const filterElements = [
      'orderSearch', 'statusFilter', 'paymentMethodFilter', 
      'paymentStatusFilter', 'dateFromFilter', 'dateToFilter',
      'orderSortBy', 'orderSortOrder'
    ];

    filterElements.forEach(id => {
      const element = document.getElementById(id);
      if (element) {
        element.value = '';
      }
    });

    // Reset sort to defaults
    const sortByEl = document.getElementById('orderSortBy');
    const sortOrderEl = document.getElementById('orderSortOrder');
    if (sortByEl) sortByEl.value = 'created_at';
    if (sortOrderEl) sortOrderEl.value = 'desc';

    // Reset filters and reload
    this.filters = {
      search: '',
      status: '',
      payment_method: '',
      payment_status: '',
      start_date: '',
      end_date: '',
      sort_by: 'created_at',
      sort_order: 'desc'
    };

    this.currentPage = 1;
    this.loadOrders();
  }

  /**
   * Go to specific page
   */
  goToPage(page) {
    if (page < 1) return;
    this.currentPage = page;
    this.loadOrders();
  }

  /**
   * Handle select all checkbox
   */
  handleSelectAll(checked) {
    this.selectedOrders.clear();
    
    if (checked) {
      this.orders.forEach(order => {
        this.selectedOrders.add(order.id);
      });
    }

    // Update individual checkboxes
    document.querySelectorAll('.order-select').forEach(checkbox => {
      checkbox.checked = checked;
    });

    this.updateSelectedCount();
  }

  /**
   * Update selected count display
   */
  updateSelectedCount() {
    const countEl = document.getElementById('selectedOrdersCount');
    const showingEl = document.getElementById('showingOrdersCount');
    const selectAllCheckbox = document.getElementById('selectAllOrders');

    if (countEl) {
      countEl.textContent = this.selectedOrders.size;
    }

    if (showingEl) {
      showingEl.textContent = this.orders.length;
    }

    // Update select all checkbox state
    if (selectAllCheckbox) {
      const allSelected = this.orders.length > 0 && this.selectedOrders.size === this.orders.length;
      const someSelected = this.selectedOrders.size > 0 && this.selectedOrders.size < this.orders.length;
      
      selectAllCheckbox.checked = allSelected;
      selectAllCheckbox.indeterminate = someSelected;
    }
  }

  /**
   * Show order detail modal
   */
  async showOrderDetail(orderId) {
    try {
      const modal = document.getElementById('orderDetailModal');
      const content = document.getElementById('orderDetailContent');
      const title = document.getElementById('orderDetailTitle');

      if (!modal || !content) return;

      // Show modal with loading state
      content.innerHTML = `
        <div class="loading-spinner"></div>
        <p>Loading order details...</p>
      `;
      
      if (title) {
        title.textContent = 'Order Details';
      }

      this.showModal(modal);

      // Load order details
      const token = localStorage.getItem('admin_auth_token');
      const response = await fetch(`${API_CONFIG.BASE_URL}/admin/orders/${orderId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      
      if (data.order) {
        this.renderOrderDetail(data.order);
        if (title) {
          title.textContent = `Order #${data.order.order_number}`;
        }
      } else {
        throw new Error('Order not found');
      }

    } catch (error) {
      console.error('Order detail loading error:', error);
      NotificationManager.show('Failed to load order details', 'error');
      this.hideOrderDetailModal();
    }
  }

  /**
   * Render order detail content
   */
  renderOrderDetail(order) {
    const content = document.getElementById('orderDetailContent');
    if (!content) return;

    content.innerHTML = `
      <div class="order-detail-header">
        <div class="order-detail-info">
          <h3 class="order-detail-number">#${order.order_number}</h3>
          <div class="order-detail-date">Placed on ${this.formatDate(order.created_at)}</div>
        </div>
        <div class="order-detail-status">
          <span class="order-status order-status--${order.status}">${this.formatStatus(order.status)}</span>
          <button class="btn btn--primary btn--small" onclick="adminOrdersManager.showOrderStatusModal(${order.id})">
            <i class="ri-edit-line"></i>
            Update Status
          </button>
        </div>
      </div>

      <div class="order-detail-grid">
        <div class="order-detail-section">
          <div class="order-detail-section-header">Customer Information</div>
          <div class="order-detail-section-content">
            <div class="detail-row">
              <span class="detail-label">Name:</span>
              <span class="detail-value">${order.customer.first_name} ${order.customer.last_name}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Email:</span>
              <span class="detail-value">${order.customer.email}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Phone:</span>
              <span class="detail-value">${order.customer.phone || 'N/A'}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Member Since:</span>
              <span class="detail-value">${this.formatDate(order.customer.member_since)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Total Orders:</span>
              <span class="detail-value">${order.customer.statistics.total_orders}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Total Spent:</span>
              <span class="detail-value">${this.formatCurrency(order.customer.statistics.total_spent)}</span>
            </div>
          </div>
        </div>

        <div class="order-detail-section">
          <div class="order-detail-section-header">Shipping Address</div>
          <div class="order-detail-section-content">
            <div class="detail-row">
              <span class="detail-label">Name:</span>
              <span class="detail-value">${order.shipping_address.first_name} ${order.shipping_address.last_name}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Address:</span>
              <span class="detail-value">
                ${order.shipping_address.address_line1}<br>
                ${order.shipping_address.address_line2 ? order.shipping_address.address_line2 + '<br>' : ''}
                ${order.shipping_address.city}, ${order.shipping_address.state} ${order.shipping_address.postal_code}<br>
                ${order.shipping_address.country}
              </span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Phone:</span>
              <span class="detail-value">${order.shipping_address.phone || 'N/A'}</span>
            </div>
          </div>
        </div>

        <div class="order-detail-section">
          <div class="order-detail-section-header">Payment Information</div>
          <div class="order-detail-section-content">
            <div class="detail-row">
              <span class="detail-label">Method:</span>
              <span class="detail-value">
                <span class="payment-method payment-method--${order.payment_method}">${this.formatPaymentMethod(order.payment_method)}</span>
              </span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Status:</span>
              <span class="detail-value">
                <span class="payment-status payment-status--${order.payment_status}">${this.formatPaymentStatus(order.payment_status)}</span>
              </span>
            </div>
            ${order.payment_details.razorpay_payment_id ? `
            <div class="detail-row">
              <span class="detail-label">Payment ID:</span>
              <span class="detail-value">${order.payment_details.razorpay_payment_id}</span>
            </div>
            ` : ''}
            ${order.payment_details.transaction_id ? `
            <div class="detail-row">
              <span class="detail-label">Transaction ID:</span>
              <span class="detail-value">${order.payment_details.transaction_id}</span>
            </div>
            ` : ''}
          </div>
        </div>

        <div class="order-detail-section">
          <div class="order-detail-section-header">Order Summary</div>
          <div class="order-detail-section-content">
            <div class="detail-row">
              <span class="detail-label">Items:</span>
              <span class="detail-value">${order.order_summary.item_count} items (${order.order_summary.total_quantity} qty)</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Subtotal:</span>
              <span class="detail-value">${this.formatCurrency(order.order_summary.subtotal)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Discount:</span>
              <span class="detail-value">-${this.formatCurrency(order.discount_amount)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Shipping:</span>
              <span class="detail-value">${this.formatCurrency(order.shipping_amount)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Tax:</span>
              <span class="detail-value">${this.formatCurrency(order.tax_amount)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label"><strong>Total:</strong></span>
              <span class="detail-value"><strong>${this.formatCurrency(order.total_amount)}</strong></span>
            </div>
          </div>
        </div>
      </div>

      <div class="order-detail-section">
        <div class="order-detail-section-header">Order Items</div>
        <div class="order-detail-section-content">
          <div class="order-items-list">
            ${order.items.map(item => `
              <div class="order-item">
                ${item.image_url ? 
                  `<img src="${item.image_url}" alt="${item.product_name}" class="order-item-image">` :
                  `<div class="order-item-image-placeholder"><i class="ri-image-line"></i></div>`
                }
                <div class="order-item-info">
                  <h4 class="order-item-name">${item.product_name}</h4>
                  ${item.brand ? `<div class="order-item-brand">${item.brand}</div>` : ''}
                  ${item.sku ? `<div class="order-item-sku">SKU: ${item.sku}</div>` : ''}
                </div>
                <div class="order-item-pricing">
                  <div class="order-item-quantity">Qty: ${item.quantity}</div>
                  <div class="order-item-price">${this.formatCurrency(item.unit_price)} each</div>
                  <div class="order-item-price"><strong>${this.formatCurrency(item.total_price)}</strong></div>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      </div>

      ${order.status_history && order.status_history.length > 0 ? `
      <div class="order-detail-section">
        <div class="order-detail-section-header">Status History</div>
        <div class="order-detail-section-content">
          <div class="status-history">
            ${order.status_history.map(history => `
              <div class="status-history-item">
                <div class="status-history-icon">
                  <i class="ri-time-line"></i>
                </div>
                <div class="status-history-content">
                  <div class="status-history-status">
                    ${this.formatStatus(history.previous_status)} → ${this.formatStatus(history.new_status)}
                  </div>
                  <div class="status-history-date">${this.formatDate(history.changed_at)} by ${history.changed_by}</div>
                  ${history.notes ? `<div class="status-history-notes">${history.notes}</div>` : ''}
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      </div>
      ` : ''}

      ${order.notes ? `
      <div class="order-detail-section">
        <div class="order-detail-section-header">Order Notes</div>
        <div class="order-detail-section-content">
          <p>${order.notes}</p>
        </div>
      </div>
      ` : ''}
    `;
  }

  /**
   * Show order status update modal
   */
  showOrderStatusModal(orderId) {
    const modal = document.getElementById('orderStatusModal');
    const form = document.getElementById('orderStatusForm');
    const title = document.getElementById('orderStatusTitle');

    if (!modal || !form) return;

    // Find the order
    const order = this.orders.find(o => o.id === orderId);
    if (!order) {
      NotificationManager.show('Order not found', 'error');
      return;
    }

    // Set form data
    form.dataset.orderId = orderId;
    
    if (title) {
      title.textContent = `Update Status - Order #${order.order_number}`;
    }

    // Reset form
    form.reset();
    this.clearFormErrors(form);

    // Set current status as selected (but allow changing)
    const statusSelect = document.getElementById('orderStatusSelect');
    if (statusSelect) {
      statusSelect.value = order.status;
    }

    this.showModal(modal);
  }

  /**
   * Handle order status update
   */
  async handleOrderStatusUpdate(e) {
    e.preventDefault();
    
    const form = e.target;
    const orderId = parseInt(form.dataset.orderId);
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveOrderStatus');

    try {
      this.setButtonLoading(saveBtn, true);
      this.clearFormErrors(form);

      const token = localStorage.getItem('admin_auth_token');
      const response = await fetch(`${API_CONFIG.BASE_URL}/admin/orders/${orderId}/status`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          status: formData.get('status'),
          notes: formData.get('notes'),
          tracking_number: formData.get('tracking_number')
        })
      });

      const data = await response.json();

      if (!response.ok) {
        if (data.details) {
          this.showFormErrors(form, data.details);
        } else {
          throw new Error(data.error || 'Failed to update order status');
        }
        return;
      }

      NotificationManager.show('Order status updated successfully', 'success');
      this.hideOrderStatusModal();
      
      // Reload orders to reflect changes
      await this.loadOrders();

    } catch (error) {
      console.error('Order status update error:', error);
      NotificationManager.show(error.message || 'Failed to update order status', 'error');
    } finally {
      this.setButtonLoading(saveBtn, false);
    }
  }

  /**
   * Show bulk update modal
   */
  showBulkUpdateModal() {
    if (this.selectedOrders.size === 0) {
      NotificationManager.show('Please select orders to update', 'warning');
      return;
    }

    const modal = document.getElementById('bulkUpdateModal');
    const form = document.getElementById('bulkUpdateForm');
    const countEl = document.getElementById('bulkUpdateCount');
    const previewEl = document.getElementById('selectedOrdersPreview');

    if (!modal || !form) return;

    // Update count
    if (countEl) {
      countEl.textContent = this.selectedOrders.size;
    }

    // Show selected orders preview
    if (previewEl) {
      const selectedOrderNumbers = this.orders
        .filter(order => this.selectedOrders.has(order.id))
        .map(order => order.order_number)
        .slice(0, 10); // Show first 10

      previewEl.innerHTML = selectedOrderNumbers
        .map(orderNumber => `<span class="selected-order-tag">#${orderNumber}</span>`)
        .join('');

      if (this.selectedOrders.size > 10) {
        previewEl.innerHTML += `<span class="selected-order-tag">+${this.selectedOrders.size - 10} more</span>`;
      }
    }

    // Reset form
    form.reset();
    this.clearFormErrors(form);

    this.showModal(modal);
  }

  /**
   * Handle bulk update
   */
  async handleBulkUpdate(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveBulkUpdate');

    try {
      this.setButtonLoading(saveBtn, true);
      this.clearFormErrors(form);

      const token = localStorage.getItem('admin_auth_token');
      const response = await fetch(`${API_CONFIG.BASE_URL}/admin/orders/bulk-status`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          order_ids: Array.from(this.selectedOrders),
          status: formData.get('status'),
          notes: formData.get('notes')
        })
      });

      const data = await response.json();

      if (!response.ok) {
        if (data.details) {
          this.showFormErrors(form, data.details);
        } else {
          throw new Error(data.error || 'Failed to update orders');
        }
        return;
      }

      const { results, errors, summary } = data;
      
      if (errors && errors.length > 0) {
        NotificationManager.show(
          `Bulk update completed with ${summary.successful_updates} successes and ${summary.failed_updates} errors`, 
          'warning'
        );
      } else {
        NotificationManager.show(`Successfully updated ${summary.successful_updates} orders`, 'success');
      }

      this.hideBulkUpdateModal();
      this.selectedOrders.clear();
      
      // Reload orders to reflect changes
      await this.loadOrders();

    } catch (error) {
      console.error('Bulk update error:', error);
      NotificationManager.show(error.message || 'Failed to update orders', 'error');
    } finally {
      this.setButtonLoading(saveBtn, false);
    }
  }

  /**
   * Export orders
   */
  async exportOrders() {
    try {
      const token = localStorage.getItem('admin_auth_token');
      const queryParams = new URLSearchParams({
        format: 'csv',
        ...this.filters
      });

      const response = await fetch(`${API_CONFIG.BASE_URL}/admin/orders/export?${queryParams}`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      // Download the file
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `orders-export-${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);

      NotificationManager.show('Orders exported successfully', 'success');

    } catch (error) {
      console.error('Export error:', error);
      NotificationManager.show('Failed to export orders', 'error');
    }
  }

  /**
   * Modal management methods
   */
  showModal(modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  hideOrderDetailModal() {
    const modal = document.getElementById('orderDetailModal');
    if (modal) {
      modal.classList.remove('show');
      document.body.style.overflow = '';
    }
  }

  hideOrderStatusModal() {
    const modal = document.getElementById('orderStatusModal');
    if (modal) {
      modal.classList.remove('show');
      document.body.style.overflow = '';
    }
  }

  hideBulkUpdateModal() {
    const modal = document.getElementById('bulkUpdateModal');
    if (modal) {
      modal.classList.remove('show');
      document.body.style.overflow = '';
    }
  }

  hideAllModals() {
    this.hideOrderDetailModal();
    this.hideOrderStatusModal();
    this.hideBulkUpdateModal();
  }

  /**
   * Utility methods
   */
  showLoading(show) {
    const tbody = document.getElementById('ordersTableBody');
    if (show && tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="9" class="table-loading">
            <div class="loading-spinner"></div>
            Loading orders...
          </td>
        </tr>
      `;
    }
  }

  setButtonLoading(button, loading) {
    if (!button) return;
    
    const text = button.querySelector('.btn-text');
    const spinner = button.querySelector('.btn-loading');
    
    if (loading) {
      button.disabled = true;
      button.classList.add('loading');
      if (text) text.style.opacity = '0';
      if (spinner) spinner.style.display = 'block';
    } else {
      button.disabled = false;
      button.classList.remove('loading');
      if (text) text.style.opacity = '1';
      if (spinner) spinner.style.display = 'none';
    }
  }

  clearFormErrors(form) {
    form.querySelectorAll('.form-error').forEach(error => {
      error.classList.remove('show');
      error.textContent = '';
    });
  }

  showFormErrors(form, errors) {
    errors.forEach(error => {
      const field = error.path || error.param;
      const errorEl = form.querySelector(`#${field}Error`);
      if (errorEl) {
        errorEl.textContent = error.msg || error.message;
        errorEl.classList.add('show');
      }
    });
  }

  formatNumber(num) {
    if (num === null || num === undefined) return '0';
    return num.toLocaleString();
  }

  formatCurrency(amount) {
    if (amount === null || amount === undefined) return '₹0.00';
    return `₹${parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
  }

  formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  formatStatus(status) {
    const statusMap = {
      'placed': 'Placed',
      'processing': 'Processing',
      'shipped': 'Shipped',
      'out_for_delivery': 'Out for Delivery',
      'delivered': 'Delivered',
      'cancelled': 'Cancelled'
    };
    return statusMap[status] || status;
  }

  formatPaymentMethod(method) {
    const methodMap = {
      'razorpay': 'Razorpay',
      'cod': 'COD'
    };
    return methodMap[method] || method;
  }

  formatPaymentStatus(status) {
    const statusMap = {
      'pending': 'Pending',
      'paid': 'Paid',
      'failed': 'Failed',
      'refunded': 'Refunded'
    };
    return statusMap[status] || status;
  }
}

// Initialize admin orders manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.adminOrdersManager = new AdminOrdersManager();
});

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AdminOrdersManager;
}