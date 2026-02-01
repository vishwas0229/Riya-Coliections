/**
 * Admin Product Management JavaScript
 * Handles product CRUD operations, image uploads, and inventory management
 */

class AdminProductManager {
  constructor() {
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.totalItems = 0;
    this.currentFilters = {};
    this.products = [];
    this.categories = [];
    this.editingProduct = null;
    this.uploadedImages = [];
    
    this.init();
  }

  async init() {
    // Check authentication
    if (!this.checkAuth()) {
      return;
    }

    // Initialize components
    this.bindEvents();
    await this.loadCategories();
    await this.loadProducts();
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
    // Filter and search events
    document.getElementById('productSearch')?.addEventListener('input', 
      this.debounce(() => this.handleSearch(), 300));
    document.getElementById('searchProductsBtn')?.addEventListener('click', () => this.handleSearch());
    document.getElementById('applyFilters')?.addEventListener('click', () => this.applyFilters());
    document.getElementById('clearFilters')?.addEventListener('click', () => this.clearFilters());
    document.getElementById('refreshProducts')?.addEventListener('click', () => this.loadProducts());

    // Product form events
    document.getElementById('addProductBtn')?.addEventListener('click', () => this.showProductModal());
    document.getElementById('closeProductModal')?.addEventListener('click', () => this.hideProductModal());
    document.getElementById('cancelProductForm')?.addEventListener('click', () => this.hideProductModal());
    document.getElementById('productForm')?.addEventListener('submit', (e) => this.handleProductSubmit(e));

    // Image upload events
    this.bindImageUploadEvents();

    // Modal events
    document.getElementById('productModalOverlay')?.addEventListener('click', (e) => {
      if (e.target.id === 'productModalOverlay') {
        this.hideProductModal();
      }
    });

    // Image preview modal events
    document.getElementById('closeImagePreview')?.addEventListener('click', () => this.hideImagePreview());
    document.getElementById('imagePreviewModal')?.addEventListener('click', (e) => {
      if (e.target.id === 'imagePreviewModal') {
        this.hideImagePreview();
      }
    });

    // Pagination events
    document.getElementById('prevPage')?.addEventListener('click', () => this.goToPage(this.currentPage - 1));
    document.getElementById('nextPage')?.addEventListener('click', () => this.goToPage(this.currentPage + 1));

    // Keyboard events
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.hideProductModal();
        this.hideImagePreview();
      }
    });
  }

  /**
   * Bind image upload events
   */
  bindImageUploadEvents() {
    const dropzone = document.getElementById('uploadDropzone');
    const fileInput = document.getElementById('productImages');

    if (dropzone && fileInput) {
      // Click to upload
      dropzone.addEventListener('click', () => fileInput.click());

      // File input change
      fileInput.addEventListener('change', (e) => this.handleFileSelect(e.target.files));

      // Drag and drop
      dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
      });

      dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
      });

      dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        this.handleFileSelect(e.dataTransfer.files);
      });
    }
  }

  /**
   * Load categories from API
   */
  async loadCategories() {
    try {
      const response = await ApiService.categories.getAll();
      
      if (response.success) {
        this.categories = response.data.categories;
        this.populateCategorySelects();
      }
    } catch (error) {
      console.error('Error loading categories:', error);
      NotificationManager.show('Failed to load categories', 'error');
    }
  }

  /**
   * Populate category select elements
   */
  populateCategorySelects() {
    const selects = ['categoryFilter', 'productCategory'];
    
    selects.forEach(selectId => {
      const select = document.getElementById(selectId);
      if (select) {
        // Clear existing options (except first one)
        while (select.children.length > 1) {
          select.removeChild(select.lastChild);
        }
        
        // Add category options
        this.categories.forEach(category => {
          const option = document.createElement('option');
          option.value = category.id;
          option.textContent = category.name;
          select.appendChild(option);
        });
      }
    });
  }

  /**
   * Load products from API
   */
  async loadProducts() {
    try {
      this.showLoading(true);
      
      const params = {
        page: this.currentPage,
        limit: this.itemsPerPage,
        ...this.currentFilters
      };

      const token = localStorage.getItem('admin_auth_token');
      const response = await fetch(`${API_CONFIG.BASE_URL}/products`, {
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
        this.products = data.data.products;
        this.totalItems = data.data.pagination.totalItems;
        this.updateProductsTable();
        this.updatePagination(data.data.pagination);
        this.updateProductsStats();
      } else {
        throw new Error(data.message || 'Failed to load products');
      }
      
    } catch (error) {
      console.error('Products loading error:', error);
      NotificationManager.show('Failed to load products', 'error');
    } finally {
      this.showLoading(false);
    }
  }

  /**
   * Update products table
   */
  updateProductsTable() {
    const tbody = document.getElementById('productsTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (this.products.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="table-loading">No products found</td>
        </tr>
      `;
      return;
    }

    this.products.forEach(product => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>
          ${product.primaryImage ? 
            `<img src="${product.primaryImage}" alt="${product.name}" class="product-image" onclick="adminProductManager.showImagePreview('${product.primaryImage}')">` :
            `<div class="product-image-placeholder"><i class="ri-image-line"></i></div>`
          }
        </td>
        <td>
          <div class="product-info">
            <h4 class="product-name">${product.name}</h4>
            ${product.brand ? `<div class="product-brand">${product.brand}</div>` : ''}
            ${product.sku ? `<div class="product-sku">SKU: ${product.sku}</div>` : ''}
          </div>
        </td>
        <td>${product.category ? product.category.name : 'No Category'}</td>
        <td>
          <div class="product-price">₹${this.formatPrice(product.price)}</div>
        </td>
        <td>
          <div class="stock-info">
            <div class="stock-quantity">${product.stockQuantity}</div>
            <span class="stock-status stock-status--${this.getStockLevel(product.stockQuantity)}">
              ${this.getStockStatusText(product.stockQuantity)}
            </span>
          </div>
        </td>
        <td>
          <span class="product-status product-status--${product.isActive ? 'active' : 'inactive'}">
            ${product.isActive ? 'Active' : 'Inactive'}
          </span>
        </td>
        <td>
          <div class="product-actions">
            <button class="action-btn action-btn--view" onclick="adminProductManager.viewProduct(${product.id})" title="View Details">
              <i class="ri-eye-line"></i>
            </button>
            <button class="action-btn action-btn--edit" onclick="adminProductManager.editProduct(${product.id})" title="Edit Product">
              <i class="ri-edit-line"></i>
            </button>
            <button class="action-btn action-btn--delete" onclick="adminProductManager.deleteProduct(${product.id})" title="Delete Product">
              <i class="ri-delete-bin-line"></i>
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(row);
    });
  }

  /**
   * Update pagination
   */
  updatePagination(pagination) {
    const container = document.getElementById('productsPagination');
    const info = document.getElementById('paginationInfo');
    const pages = document.getElementById('paginationPages');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');

    if (!container) return;

    // Show/hide pagination
    container.style.display = pagination.totalPages > 1 ? 'flex' : 'none';

    // Update info
    if (info) {
      const start = ((pagination.currentPage - 1) * pagination.itemsPerPage) + 1;
      const end = Math.min(start + pagination.itemsPerPage - 1, pagination.totalItems);
      info.textContent = `Showing ${start}-${end} of ${pagination.totalItems} products`;
    }

    // Update buttons
    if (prevBtn) {
      prevBtn.disabled = !pagination.hasPrevPage;
    }
    if (nextBtn) {
      nextBtn.disabled = !pagination.hasNextPage;
    }

    // Update page numbers
    if (pages) {
      pages.innerHTML = '';
      this.generatePageNumbers(pagination).forEach(page => {
        pages.appendChild(page);
      });
    }
  }

  /**
   * Generate page number elements
   */
  generatePageNumbers(pagination) {
    const pages = [];
    const current = pagination.currentPage;
    const total = pagination.totalPages;
    
    // Always show first page
    if (total > 0) {
      pages.push(this.createPageElement(1, current === 1));
    }
    
    // Show ellipsis if needed
    if (current > 3) {
      pages.push(this.createEllipsis());
    }
    
    // Show pages around current
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    
    for (let i = start; i <= end; i++) {
      pages.push(this.createPageElement(i, i === current));
    }
    
    // Show ellipsis if needed
    if (current < total - 2) {
      pages.push(this.createEllipsis());
    }
    
    // Always show last page
    if (total > 1) {
      pages.push(this.createPageElement(total, current === total));
    }
    
    return pages;
  }

  /**
   * Create page element
   */
  createPageElement(pageNum, isActive) {
    const page = document.createElement('button');
    page.className = `pagination-page ${isActive ? 'active' : ''}`;
    page.textContent = pageNum;
    page.onclick = () => this.goToPage(pageNum);
    return page;
  }

  /**
   * Create ellipsis element
   */
  createEllipsis() {
    const ellipsis = document.createElement('span');
    ellipsis.className = 'pagination-ellipsis';
    ellipsis.textContent = '...';
    return ellipsis;
  }

  /**
   * Update products statistics
   */
  updateProductsStats() {
    const totalEl = document.getElementById('totalProductsCount');
    const activeEl = document.getElementById('activeProductsCount');
    const lowStockEl = document.getElementById('lowStockCount');

    if (totalEl) totalEl.textContent = this.totalItems;
    
    const activeCount = this.products.filter(p => p.isActive).length;
    if (activeEl) activeEl.textContent = activeCount;
    
    const lowStockCount = this.products.filter(p => p.stockQuantity <= 10 && p.stockQuantity > 0).length;
    if (lowStockEl) lowStockEl.textContent = lowStockCount;
  }

  /**
   * Handle search
   */
  handleSearch() {
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
      this.currentFilters.search = searchInput.value.trim();
      this.currentPage = 1;
      this.loadProducts();
    }
  }

  /**
   * Apply filters
   */
  applyFilters() {
    const filters = {};
    
    // Get filter values
    const categoryFilter = document.getElementById('categoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const statusFilter = document.getElementById('statusFilter');
    const sortBy = document.getElementById('sortBy');
    const sortOrder = document.getElementById('sortOrder');

    if (categoryFilter?.value) filters.category = categoryFilter.value;
    if (stockFilter?.value) filters.stockStatus = stockFilter.value;
    if (statusFilter?.value) filters.status = statusFilter.value;
    if (sortBy?.value) filters.sortBy = sortBy.value;
    if (sortOrder?.value) filters.sortOrder = sortOrder.value;

    // Apply search if exists
    const searchInput = document.getElementById('productSearch');
    if (searchInput?.value.trim()) {
      filters.search = searchInput.value.trim();
    }

    this.currentFilters = filters;
    this.currentPage = 1;
    this.loadProducts();
  }

  /**
   * Clear filters
   */
  clearFilters() {
    // Reset filter inputs
    document.getElementById('productSearch').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('stockFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('sortBy').value = 'created_at';
    document.getElementById('sortOrder').value = 'desc';

    // Clear filters and reload
    this.currentFilters = {};
    this.currentPage = 1;
    this.loadProducts();
  }

  /**
   * Go to specific page
   */
  goToPage(page) {
    if (page >= 1 && page <= Math.ceil(this.totalItems / this.itemsPerPage)) {
      this.currentPage = page;
      this.loadProducts();
    }
  }

  /**
   * Show product modal
   */
  showProductModal(product = null) {
    this.editingProduct = product;
    this.uploadedImages = [];
    
    const modal = document.getElementById('productModalOverlay');
    const title = document.getElementById('productModalTitle');
    const form = document.getElementById('productForm');
    
    if (modal && title && form) {
      // Set title
      title.textContent = product ? 'Edit Product' : 'Add New Product';
      
      // Reset form
      form.reset();
      this.clearFormErrors();
      
      // Populate form if editing
      if (product) {
        this.populateProductForm(product);
      }
      
      // Clear uploaded images display
      this.updateUploadedImagesDisplay();
      
      // Show modal
      modal.classList.add('show');
    }
  }

  /**
   * Hide product modal
   */
  hideProductModal() {
    const modal = document.getElementById('productModalOverlay');
    if (modal) {
      modal.classList.remove('show');
      this.editingProduct = null;
      this.uploadedImages = [];
    }
  }

  /**
   * Populate product form for editing
   */
  async populateProductForm(product) {
    // Basic fields
    document.getElementById('productName').value = product.name || '';
    document.getElementById('productBrand').value = product.brand || '';
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('productCategory').value = product.category?.id || '';
    document.getElementById('productSku').value = product.sku || '';
    document.getElementById('productPrice').value = product.price || '';
    document.getElementById('productStock').value = product.stockQuantity || '';
    document.getElementById('productActive').checked = product.isActive !== false;

    // Load existing images
    try {
      const response = await fetch(`${API_CONFIG.BASE_URL}/products/${product.id}/images`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('admin_auth_token')}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success && data.data.images) {
          this.uploadedImages = data.data.images.map(img => ({
            id: img.id,
            url: img.url,
            altText: img.altText,
            isPrimary: img.isPrimary,
            existing: true
          }));
          this.updateUploadedImagesDisplay();
        }
      }
    } catch (error) {
      console.error('Error loading product images:', error);
    }
  }

  /**
   * Handle product form submission
   */
  async handleProductSubmit(e) {
    e.preventDefault();
    
    if (!this.validateProductForm()) {
      return;
    }

    const formData = new FormData(e.target);
    const productData = Object.fromEntries(formData.entries());
    
    // Convert checkbox value
    productData.is_active = document.getElementById('productActive').checked;
    
    // Convert numeric values
    productData.price = parseFloat(productData.price);
    productData.stock_quantity = parseInt(productData.stock_quantity);
    productData.category_id = productData.category_id ? parseInt(productData.category_id) : null;

    try {
      this.setFormLoading(true);
      
      let response;
      if (this.editingProduct) {
        // Update existing product
        response = await this.updateProduct(this.editingProduct.id, productData);
      } else {
        // Create new product
        response = await this.createProduct(productData);
      }

      if (response.success) {
        // Handle image uploads if any
        if (this.uploadedImages.some(img => !img.existing)) {
          await this.uploadProductImages(response.data.product.id);
        }

        NotificationManager.show(
          this.editingProduct ? 'Product updated successfully' : 'Product created successfully',
          'success'
        );
        
        this.hideProductModal();
        this.loadProducts();
      } else {
        throw new Error(response.message || 'Failed to save product');
      }
      
    } catch (error) {
      console.error('Product save error:', error);
      NotificationManager.show(error.message || 'Failed to save product', 'error');
    } finally {
      this.setFormLoading(false);
    }
  }

  /**
   * Create new product
   */
  async createProduct(productData) {
    const token = localStorage.getItem('admin_auth_token');
    const response = await fetch(`${API_CONFIG.BASE_URL}/products`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(productData)
    });

    return await response.json();
  }

  /**
   * Update existing product
   */
  async updateProduct(productId, productData) {
    const token = localStorage.getItem('admin_auth_token');
    const response = await fetch(`${API_CONFIG.BASE_URL}/products/${productId}`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(productData)
    });

    return await response.json();
  }

  /**
   * Upload product images
   */
  async uploadProductImages(productId) {
    const newImages = this.uploadedImages.filter(img => !img.existing);
    
    if (newImages.length === 0) return;

    const formData = new FormData();
    
    // Add image files
    newImages.forEach((img, index) => {
      if (img.file) {
        formData.append('images', img.file);
        if (img.altText) {
          formData.append('alt_text', img.altText);
        }
      }
    });

    // Set primary image
    const primaryImage = this.uploadedImages.find(img => img.isPrimary);
    if (primaryImage && !primaryImage.existing) {
      formData.append('is_primary', 'true');
    }

    const token = localStorage.getItem('admin_auth_token');
    const response = await fetch(`${API_CONFIG.BASE_URL}/products/${productId}/images`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });

    const result = await response.json();
    if (!result.success) {
      throw new Error(result.message || 'Failed to upload images');
    }

    return result;
  }

  /**
   * Handle file selection
   */
  handleFileSelect(files) {
    const maxFiles = 10;
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    Array.from(files).forEach(file => {
      // Check file count
      if (this.uploadedImages.length >= maxFiles) {
        NotificationManager.show(`Maximum ${maxFiles} images allowed`, 'warning');
        return;
      }

      // Check file type
      if (!allowedTypes.includes(file.type)) {
        NotificationManager.show(`Invalid file type: ${file.name}`, 'error');
        return;
      }

      // Check file size
      if (file.size > maxSize) {
        NotificationManager.show(`File too large: ${file.name}`, 'error');
        return;
      }

      // Create image object
      const imageObj = {
        file,
        url: URL.createObjectURL(file),
        altText: '',
        isPrimary: this.uploadedImages.length === 0, // First image is primary by default
        existing: false
      };

      this.uploadedImages.push(imageObj);
    });

    this.updateUploadedImagesDisplay();
  }

  /**
   * Update uploaded images display
   */
  updateUploadedImagesDisplay() {
    const container = document.getElementById('uploadedImages');
    if (!container) return;

    container.innerHTML = '';

    this.uploadedImages.forEach((image, index) => {
      const imageEl = document.createElement('div');
      imageEl.className = 'uploaded-image';
      imageEl.innerHTML = `
        <img src="${image.url}" alt="${image.altText || 'Product image'}">
        ${image.isPrimary ? '<div class="primary-badge">Primary</div>' : ''}
        <div class="image-overlay">
          <button class="image-action image-action--view" onclick="adminProductManager.showImagePreview('${image.url}')" title="Preview">
            <i class="ri-eye-line"></i>
          </button>
          <button class="image-action image-action--primary" onclick="adminProductManager.setPrimaryImage(${index})" title="Set as Primary">
            <i class="ri-star-line"></i>
          </button>
          <button class="image-action image-action--danger" onclick="adminProductManager.removeImage(${index})" title="Remove">
            <i class="ri-delete-bin-line"></i>
          </button>
        </div>
      `;
      container.appendChild(imageEl);
    });
  }

  /**
   * Set primary image
   */
  setPrimaryImage(index) {
    this.uploadedImages.forEach((img, i) => {
      img.isPrimary = i === index;
    });
    this.updateUploadedImagesDisplay();
  }

  /**
   * Remove image
   */
  removeImage(index) {
    const image = this.uploadedImages[index];
    
    // Revoke object URL if it's a new file
    if (!image.existing && image.url.startsWith('blob:')) {
      URL.revokeObjectURL(image.url);
    }
    
    this.uploadedImages.splice(index, 1);
    
    // Set new primary if removed image was primary
    if (image.isPrimary && this.uploadedImages.length > 0) {
      this.uploadedImages[0].isPrimary = true;
    }
    
    this.updateUploadedImagesDisplay();
  }

  /**
   * Show image preview
   */
  showImagePreview(imageUrl) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('previewImage');
    
    if (modal && img) {
      img.src = imageUrl;
      modal.classList.add('show');
    }
  }

  /**
   * Hide image preview
   */
  hideImagePreview() {
    const modal = document.getElementById('imagePreviewModal');
    if (modal) {
      modal.classList.remove('show');
    }
  }

  /**
   * View product details
   */
  async viewProduct(productId) {
    try {
      const token = localStorage.getItem('admin_auth_token');
      const response = await fetch(`${API_CONFIG.BASE_URL}/products/${productId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          // Open product in new tab (customer view)
          window.open(`../test-product-detail.html?id=${productId}`, '_blank');
        }
      }
    } catch (error) {
      console.error('Error viewing product:', error);
      NotificationManager.show('Failed to view product', 'error');
    }
  }

  /**
   * Edit product
   */
  editProduct(productId) {
    const product = this.products.find(p => p.id === productId);
    if (product) {
      this.showProductModal(product);
    }
  }

  /**
   * Delete product
   */
  async deleteProduct(productId) {
    const product = this.products.find(p => p.id === productId);
    if (!product) return;

    const confirmed = confirm(`Are you sure you want to delete "${product.name}"? This action cannot be undone.`);
    if (!confirmed) return;

    try {
      const token = localStorage.getItem('admin_auth_token');
      const response = await fetch(`${API_CONFIG.BASE_URL}/products/${productId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      const result = await response.json();
      
      if (result.success) {
        NotificationManager.show('Product deleted successfully', 'success');
        this.loadProducts();
      } else {
        throw new Error(result.message || 'Failed to delete product');
      }
      
    } catch (error) {
      console.error('Product deletion error:', error);
      NotificationManager.show(error.message || 'Failed to delete product', 'error');
    }
  }

  /**
   * Validate product form
   */
  validateProductForm() {
    let isValid = true;
    
    // Clear previous errors
    this.clearFormErrors();
    
    // Required fields
    const requiredFields = [
      { id: 'productName', message: 'Product name is required' },
      { id: 'productPrice', message: 'Price is required' },
      { id: 'productStock', message: 'Stock quantity is required' }
    ];
    
    requiredFields.forEach(field => {
      const input = document.getElementById(field.id);
      if (!input.value.trim()) {
        this.showFieldError(field.id, field.message);
        isValid = false;
      }
    });
    
    // Validate price
    const price = parseFloat(document.getElementById('productPrice').value);
    if (isNaN(price) || price < 0) {
      this.showFieldError('productPrice', 'Please enter a valid price');
      isValid = false;
    }
    
    // Validate stock
    const stock = parseInt(document.getElementById('productStock').value);
    if (isNaN(stock) || stock < 0) {
      this.showFieldError('productStock', 'Please enter a valid stock quantity');
      isValid = false;
    }
    
    return isValid;
  }

  /**
   * Show field error
   */
  showFieldError(fieldId, message) {
    const errorEl = document.getElementById(`${fieldId}Error`);
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.classList.add('show');
    }
  }

  /**
   * Clear form errors
   */
  clearFormErrors() {
    document.querySelectorAll('.form-error').forEach(error => {
      error.classList.remove('show');
      error.textContent = '';
    });
  }

  /**
   * Set form loading state
   */
  setFormLoading(loading) {
    const btn = document.getElementById('saveProductBtn');
    const btnText = btn?.querySelector('.btn-text');
    const btnLoading = btn?.querySelector('.btn-loading');
    
    if (btn) {
      btn.disabled = loading;
      btn.classList.toggle('loading', loading);
    }
    
    if (btnLoading) {
      btnLoading.style.display = loading ? 'block' : 'none';
    }
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
   * Get stock level class
   */
  getStockLevel(quantity) {
    if (quantity === 0) return 'out';
    if (quantity <= 5) return 'low';
    if (quantity <= 10) return 'medium';
    return 'high';
  }

  /**
   * Get stock status text
   */
  getStockStatusText(quantity) {
    if (quantity === 0) return 'Out of Stock';
    if (quantity <= 5) return 'Critical';
    if (quantity <= 10) return 'Low Stock';
    return 'In Stock';
  }

  /**
   * Format price
   */
  formatPrice(price) {
    return parseFloat(price).toLocaleString('en-IN', { 
      minimumFractionDigits: 2,
      maximumFractionDigits: 2 
    });
  }

  /**
   * Debounce function
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

// Initialize product manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize if we're on the admin dashboard page
  if (document.getElementById('productsSection')) {
    window.adminProductManager = new AdminProductManager();
  }
});

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AdminProductManager;
}