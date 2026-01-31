/**
 * Admin Dashboard JavaScript
 * Handles admin dashboard functionality and navigation
 */

class AdminDashboard {
  constructor() {
    this.currentSection = 'dashboard';
    this.dashboardData = null;
    this.refreshInterval = null;
    
    this.init();
  }

  async init() {
    // Check authentication
    if (!this.checkAuth()) {
      return;
    }

    // Initialize components
    this.bindEvents();
    this.loadAdminProfile();
    await this.loadDashboardData();
    this.startAutoRefresh();
  }

  /**
   * Check if admin is authenticated
   */
  checkAuth() {
    const token = localStorage.getItem('admin_auth_token');
    if (!token) {
      NotificationManager.show('Please log in to access the admin panel', 'error');
      window.location.href = 'admin-login.html';
      return false;
    }
    return true;
  }

  /**
   * Bind event listeners
   */
  bindEvents() {
    // Navigation links
    document.querySelectorAll('.admin-nav-link').forEach(link => {
      link.addEventListener('click', (e) => this.handleNavigation(e));
    });

    // Quick action buttons
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
      btn.addEventListener('click', (e) => this.handleQuickAction(e));
    });

    // Profile dropdown
    const profileBtn = document.getElementById('adminProfileBtn');
    const profileMenu = document.getElementById('adminProfileMenu');
    if (profileBtn && profileMenu) {
      profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileMenu.classList.toggle('show');
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', () => {
        profileMenu.classList.remove('show');
      });
    }

    // Logout button
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', (e) => this.handleLogout(e));
    }

    // Refresh dashboard button
    const refreshBtn = document.getElementById('refreshDashboard');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => this.loadDashboardData());
    }

    // Export report button
    const exportBtn = document.getElementById('exportReport');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => this.exportReport());
    }
  }

  /**
   * Load admin profile information
   */
  loadAdminProfile() {
    const adminData = localStorage.getItem('admin_user_data');
    if (adminData) {
      try {
        const admin = JSON.parse(adminData);
        
        // Update admin name and role
        const adminNameEl = document.getElementById('adminName');
        const adminRoleEl = document.getElementById('adminRole');
        
        if (adminNameEl) {
          adminNameEl.textContent = admin.name || 'Admin User';
        }
        
        if (adminRoleEl) {
          adminRoleEl.textContent = admin.role === 'super_admin' ? 'Super Administrator' : 'Administrator';
        }
        
      } catch (error) {
        console.error('Error parsing admin data:', error);
      }
    }
  }

  /**
   * Load dashboard data from API
   */
  async loadDashboardData() {
    try {
      this.showLoading(true);
      
      const token = localStorage.getItem('admin_auth_token');
      const response = await fetch(`${API_CONFIG.BASE_URL}/admin/dashboard`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      
      if (data.success) {
        this.dashboardData = data.data;
        this.updateDashboardUI();
      } else {
        throw new Error(data.message || 'Failed to load dashboard data');
      }
      
    } catch (error) {
      console.error('Dashboard data loading error:', error);
      NotificationManager.show('Failed to load dashboard data', 'error');
    } finally {
      this.showLoading(false);
    }
  }

  /**
   * Update dashboard UI with loaded data
   */
  updateDashboardUI() {
    if (!this.dashboardData) return;

    // Update stats cards
    this.updateStatsCards();
    
    // Update recent orders table
    this.updateRecentOrders();
    
    // Update top products
    this.updateTopProducts();
    
    // Update system status
    this.updateSystemStatus();
    
    // Update navigation badges
    this.updateNavigationBadges();
  }

  /**
   * Update stats cards
   */
  updateStatsCards() {
    const { orders, revenue, users, products } = this.dashboardData;

    // Total Orders
    const totalOrdersEl = document.getElementById('totalOrders');
    if (totalOrdersEl) {
      totalOrdersEl.textContent = this.formatNumber(orders.total);
    }

    // Total Revenue
    const totalRevenueEl = document.getElementById('totalRevenue');
    if (totalRevenueEl) {
      totalRevenueEl.textContent = this.formatCurrency(revenue.total);
    }

    // Total Customers
    const totalCustomersEl = document.getElementById('totalCustomers');
    if (totalCustomersEl) {
      totalCustomersEl.textContent = this.formatNumber(users.total);
    }

    // Total Products
    const totalProductsEl = document.getElementById('totalProducts');
    if (totalProductsEl) {
      totalProductsEl.textContent = this.formatNumber(products.total);
    }

    // Update change indicators (you can calculate these based on historical data)
    // For now, we'll use the current data to show some basic changes
    this.updateChangeIndicator('ordersChange', orders.thisWeek, orders.total);
    this.updateChangeIndicator('revenueChange', revenue.thisWeek, revenue.total);
    this.updateChangeIndicator('customersChange', users.weekRegistrations, users.total);
  }

  /**
   * Update change indicator
   */
  updateChangeIndicator(elementId, current, total) {
    const element = document.getElementById(elementId);
    if (!element || !total) return;

    const percentage = total > 0 ? ((current / total) * 100).toFixed(1) : 0;
    element.textContent = `+${percentage}%`;
  }

  /**
   * Update recent orders table
   */
  updateRecentOrders() {
    const tbody = document.getElementById('recentOrdersBody');
    if (!tbody || !this.dashboardData.recentOrders) return;

    tbody.innerHTML = '';

    if (this.dashboardData.recentOrders.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="table-loading">No recent orders found</td>
        </tr>
      `;
      return;
    }

    this.dashboardData.recentOrders.forEach(order => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>#${order.orderNumber}</td>
        <td>${order.customer.name}</td>
        <td><span class="order-status order-status--${order.status}">${order.status}</span></td>
        <td>${this.formatCurrency(order.totalAmount)}</td>
        <td>${this.formatDate(order.createdAt)}</td>
      `;
      tbody.appendChild(row);
    });
  }

  /**
   * Update top products list
   */
  updateTopProducts() {
    const container = document.getElementById('topProductsList');
    if (!container || !this.dashboardData.topProducts) return;

    container.innerHTML = '';

    if (this.dashboardData.topProducts.length === 0) {
      container.innerHTML = `
        <div class="top-product-loading">
          <p>No product sales data available</p>
        </div>
      `;
      return;
    }

    this.dashboardData.topProducts.forEach((product, index) => {
      const item = document.createElement('div');
      item.className = 'top-product-item';
      item.innerHTML = `
        <div class="top-product-rank">${index + 1}</div>
        <div class="top-product-info">
          <h4 class="top-product-name">${product.name}</h4>
          <div class="top-product-stats">
            ${product.totalSold} sold • ${this.formatCurrency(product.totalRevenue)} revenue
          </div>
        </div>
      `;
      container.appendChild(item);
    });
  }

  /**
   * Update system status
   */
  updateSystemStatus() {
    const { products } = this.dashboardData;
    
    // Update low stock count
    const lowStockEl = document.getElementById('lowStockCount');
    if (lowStockEl && products) {
      const lowStockCount = products.lowStock || 0;
      lowStockEl.textContent = `${lowStockCount} items`;
    }
  }

  /**
   * Update navigation badges
   */
  updateNavigationBadges() {
    const { orders } = this.dashboardData;
    
    // Update orders badge with pending orders
    const ordersBadge = document.getElementById('ordersBadge');
    if (ordersBadge && orders) {
      const pendingOrders = orders.pending + orders.processing;
      ordersBadge.textContent = pendingOrders;
      ordersBadge.style.display = pendingOrders > 0 ? 'block' : 'none';
    }
  }

  /**
   * Handle navigation between sections
   */
  handleNavigation(e) {
    e.preventDefault();
    
    const link = e.currentTarget;
    const section = link.dataset.section;
    
    if (section && section !== this.currentSection) {
      this.switchSection(section);
    }
  }

  /**
   * Switch between dashboard sections
   */
  switchSection(sectionName) {
    // Hide current section
    const currentSectionEl = document.getElementById(`${this.currentSection}Section`);
    if (currentSectionEl) {
      currentSectionEl.classList.remove('active');
    }

    // Remove active class from current nav link
    document.querySelectorAll('.admin-nav-link').forEach(link => {
      link.classList.remove('active');
    });

    // Show new section
    const newSectionEl = document.getElementById(`${sectionName}Section`);
    if (newSectionEl) {
      newSectionEl.classList.add('active');
    }

    // Add active class to new nav link
    const newNavLink = document.querySelector(`[data-section="${sectionName}"]`);
    if (newNavLink) {
      newNavLink.classList.add('active');
    }

    // Update current section
    this.currentSection = sectionName;

    // Update URL hash
    window.location.hash = sectionName;
  }

  /**
   * Handle quick action buttons
   */
  handleQuickAction(e) {
    const btn = e.currentTarget;
    const section = btn.dataset.section;
    const action = btn.dataset.action;

    if (section) {
      this.switchSection(section);
    }

    // Handle specific actions
    switch (action) {
      case 'add-product':
        // Switch to products section and trigger add product
        this.switchSection('products');
        setTimeout(() => {
          if (window.adminProductManager) {
            window.adminProductManager.showProductModal();
          }
        }, 100);
        break;
      case 'view-orders':
        this.switchSection('orders');
        break;
      case 'view-customers':
        NotificationManager.show('Customer management will be available in future updates', 'info');
        break;
      case 'view-reports':
        NotificationManager.show('Analytics and reports will be available in future updates', 'info');
        break;
    }
  }

  /**
   * Handle admin logout
   */
  async handleLogout(e) {
    e.preventDefault();
    
    try {
      // Clear local storage
      localStorage.removeItem('admin_auth_token');
      localStorage.removeItem('admin_refresh_token');
      localStorage.removeItem('admin_user_data');
      
      // Stop auto refresh
      if (this.refreshInterval) {
        clearInterval(this.refreshInterval);
      }
      
      NotificationManager.show('Logged out successfully', 'success');
      
      // Redirect to login page
      setTimeout(() => {
        window.location.href = 'admin-login.html';
      }, 1000);
      
    } catch (error) {
      console.error('Logout error:', error);
      NotificationManager.show('Error during logout', 'error');
    }
  }

  /**
   * Export dashboard report
   */
  exportReport() {
    if (!this.dashboardData) {
      NotificationManager.show('No data available to export', 'warning');
      return;
    }

    // Create CSV content
    const csvContent = this.generateCSVReport();
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `dashboard-report-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    NotificationManager.show('Report exported successfully', 'success');
  }

  /**
   * Generate CSV report content
   */
  generateCSVReport() {
    const { orders, revenue, users, products } = this.dashboardData;
    
    let csv = 'Dashboard Report\n\n';
    csv += 'Metric,Value\n';
    csv += `Total Orders,${orders.total}\n`;
    csv += `Pending Orders,${orders.pending}\n`;
    csv += `Processing Orders,${orders.processing}\n`;
    csv += `Shipped Orders,${orders.shipped}\n`;
    csv += `Delivered Orders,${orders.delivered}\n`;
    csv += `Total Revenue,${revenue.total}\n`;
    csv += `Today Revenue,${revenue.today}\n`;
    csv += `This Week Revenue,${revenue.thisWeek}\n`;
    csv += `This Month Revenue,${revenue.thisMonth}\n`;
    csv += `Average Order Value,${revenue.averageOrderValue}\n`;
    csv += `Total Customers,${users.total}\n`;
    csv += `Today Registrations,${users.todayRegistrations}\n`;
    csv += `Week Registrations,${users.weekRegistrations}\n`;
    csv += `Total Products,${products.total}\n`;
    csv += `Active Products,${products.active}\n`;
    csv += `Out of Stock,${products.outOfStock}\n`;
    csv += `Low Stock,${products.lowStock}\n`;
    
    return csv;
  }

  /**
   * Start auto refresh of dashboard data
   */
  startAutoRefresh() {
    // Refresh every 5 minutes
    this.refreshInterval = setInterval(() => {
      this.loadDashboardData();
    }, 5 * 60 * 1000);
  }

  /**
   * Show/hide loading overlay
   */
  showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
      overlay.classList.toggle('show', show);
    }
  }

  /**
   * Format number with commas
   */
  formatNumber(num) {
    if (num === null || num === undefined) return '0';
    return num.toLocaleString();
  }

  /**
   * Format currency
   */
  formatCurrency(amount) {
    if (amount === null || amount === undefined) return '₹0.00';
    return `₹${parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
  }

  /**
   * Format date
   */
  formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }
}

// Initialize admin dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  new AdminDashboard();
});

// Handle browser back/forward navigation
window.addEventListener('hashchange', () => {
  const hash = window.location.hash.substring(1);
  if (hash && window.adminDashboard) {
    window.adminDashboard.switchSection(hash);
  }
});

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AdminDashboard;
}