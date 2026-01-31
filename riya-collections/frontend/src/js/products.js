/**
 * Products page functionality for Riya Collections
 * Handles product catalog, filtering, sorting, and search
 */

class ProductsPage {
  constructor() {
    this.currentPage = 1;
    this.totalPages = 1;
    this.totalProducts = 0;
    this.productsPerPage = APP_CONFIG.PAGINATION.DEFAULT_LIMIT;
    this.currentView = 'grid';
    this.currentSort = 'featured';
    this.isLoading = false;
    this.loadingMore = false;
    
    // Filter state
    this.filters = {
      search: '',
      category: null,
      minPrice: null,
      maxPrice: null,
      brands: [],
      inStock: null,
      outOfStock: null
    };
    
    // Available options
    this.categories = [];
    this.brands = [];
    
    this.init();
  }

  /**
   * Initialize products page
   */
  init() {
    this.parseUrlParams();
    this.setupEventListeners();
    this.loadCategories();
    this.loadProducts();
    this.initializePriceRange();
  }

  /**
   * Parse URL parameters for initial filters
   */
  parseUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Search query
    const search = urlParams.get('search');
    if (search) {
      this.filters.search = search;
      this.updateSearchInput(search);
    }
    
    // Category filter
    const category = urlParams.get('category');
    if (category) {
      this.filters.category = parseInt(category);
    }
    
    // Price range
    const minPrice = urlParams.get('minPrice');
    const maxPrice = urlParams.get('maxPrice');
    if (minPrice) this.filters.minPrice = parseFloat(minPrice);
    if (maxPrice) this.filters.maxPrice = parseFloat(maxPrice);
    
    // Sort
    const sort = urlParams.get('sort');
    if (sort) {
      this.currentSort = sort;
    }
    
    // View
    const view = urlParams.get('view');
    if (view && ['grid', 'list'].includes(view)) {
      this.currentView = view;
    }
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Search form
    const searchForm = DOMUtils.getId('page-search-form');
    if (searchForm) {
      DOMUtils.addEventListener(searchForm, 'submit', (e) => {
        e.preventDefault();
        this.handleSearch();
      });
    }

    // Search input with debounce
    const searchInput = DOMUtils.getId('page-search-input');
    if (searchInput) {
      DOMUtils.addEventListener(searchInput, 'input', 
        TimingUtils.debounce((e) => {
          this.handleSearchInput(e.target.value);
        }, APP_CONFIG.SEARCH.DEBOUNCE_DELAY)
      );
    }

    // Filter toggle (mobile)
    const filterToggle = DOMUtils.getId('filter-toggle');
    if (filterToggle) {
      DOMUtils.addEventListener(filterToggle, 'click', () => {
        this.toggleSidebar();
      });
    }

    // Sidebar close
    const sidebarClose = DOMUtils.getId('sidebar-close');
    if (sidebarClose) {
      DOMUtils.addEventListener(sidebarClose, 'click', () => {
        this.closeSidebar();
      });
    }

    // View toggle
    const viewButtons = DOMUtils.getElements('.view-btn');
    viewButtons.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', () => {
        this.changeView(btn.dataset.view);
      });
    });

    // Sort dropdown
    const sortToggle = DOMUtils.getId('sort-toggle');
    const sortMenu = DOMUtils.getId('sort-menu');
    if (sortToggle && sortMenu) {
      DOMUtils.addEventListener(sortToggle, 'click', () => {
        this.toggleSortMenu();
      });

      // Close sort menu when clicking outside
      DOMUtils.addEventListener(document, 'click', (e) => {
        if (!sortToggle.contains(e.target) && !sortMenu.contains(e.target)) {
          this.closeSortMenu();
        }
      });
    }

    // Sort options
    const sortOptions = DOMUtils.getElements('.sort-option');
    sortOptions.forEach(option => {
      DOMUtils.addEventListener(option, 'click', () => {
        this.changeSort(option.dataset.sort);
      });
    });

    // Price range inputs
    const minPriceInput = DOMUtils.getId('min-price');
    const maxPriceInput = DOMUtils.getId('max-price');
    if (minPriceInput && maxPriceInput) {
      DOMUtils.addEventListener(minPriceInput, 'change', () => {
        this.updatePriceFilter();
      });
      DOMUtils.addEventListener(maxPriceInput, 'change', () => {
        this.updatePriceFilter();
      });
    }

    // Price range sliders
    const priceRangeMin = DOMUtils.getId('price-range-min');
    const priceRangeMax = DOMUtils.getId('price-range-max');
    if (priceRangeMin && priceRangeMax) {
      DOMUtils.addEventListener(priceRangeMin, 'input', () => {
        this.updatePriceRangeFromSlider();
      });
      DOMUtils.addEventListener(priceRangeMax, 'input', () => {
        this.updatePriceRangeFromSlider();
      });
    }

    // Availability filters
    const inStockFilter = DOMUtils.getId('in-stock');
    const outOfStockFilter = DOMUtils.getId('out-of-stock');
    if (inStockFilter) {
      DOMUtils.addEventListener(inStockFilter, 'change', () => {
        this.updateAvailabilityFilter();
      });
    }
    if (outOfStockFilter) {
      DOMUtils.addEventListener(outOfStockFilter, 'change', () => {
        this.updateAvailabilityFilter();
      });
    }

    // Clear filters
    const clearFilters = DOMUtils.getId('clear-filters');
    if (clearFilters) {
      DOMUtils.addEventListener(clearFilters, 'click', () => {
        this.clearAllFilters();
      });
    }

    // Clear all filters (from active filters)
    const clearAllFilters = DOMUtils.getId('clear-all-filters');
    if (clearAllFilters) {
      DOMUtils.addEventListener(clearAllFilters, 'click', () => {
        this.clearAllFilters();
      });
    }

    // Load more button
    const loadMoreBtn = DOMUtils.getId('load-more-btn');
    if (loadMoreBtn) {
      DOMUtils.addEventListener(loadMoreBtn, 'click', () => {
        this.loadMoreProducts();
      });
    }

    // Brand search
    const brandSearch = DOMUtils.getId('brand-search');
    if (brandSearch) {
      DOMUtils.addEventListener(brandSearch, 'input', 
        TimingUtils.debounce((e) => {
          this.filterBrandOptions(e.target.value);
        }, 300)
      );
    }

    // Window resize
    DOMUtils.addEventListener(window, 'resize', 
      TimingUtils.throttle(() => this.handleResize(), 250)
    );

    // Handle browser back/forward
    DOMUtils.addEventListener(window, 'popstate', () => {
      this.parseUrlParams();
      this.loadProducts();
    });
  }

  /**
   * Update search input with value
   */
  updateSearchInput(value) {
    const searchInput = DOMUtils.getId('page-search-input');
    if (searchInput) {
      searchInput.value = value;
    }
  }

  /**
   * Handle search form submission
   */
  handleSearch() {
    const searchInput = DOMUtils.getId('page-search-input');
    if (searchInput) {
      const query = searchInput.value.trim();
      this.filters.search = query;
      this.currentPage = 1;
      this.updateUrl();
      this.loadProducts();
    }
  }

  /**
   * Handle search input changes
   */
  handleSearchInput(value) {
    const query = value.trim();
    if (query.length >= APP_CONFIG.SEARCH.MIN_QUERY_LENGTH || query.length === 0) {
      this.filters.search = query;
      this.currentPage = 1;
      this.updateUrl();
      this.loadProducts();
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
        this.renderCategoryFilters();
      }
    } catch (error) {
      console.error('Error loading categories:', error);
      this.showCategoryError();
    }
  }

  /**
   * Render category filters
   */
  renderCategoryFilters() {
    const container = DOMUtils.getId('category-filters');
    if (!container) return;

    const filtersHTML = `
      <div class="filter-options">
        ${this.categories.map(category => `
          <label class="filter-option">
            <input type="checkbox" 
                   class="filter-checkbox category-filter" 
                   data-category-id="${category.id}"
                   ${this.filters.category === category.id ? 'checked' : ''}>
            <span class="filter-checkmark"></span>
            <span class="filter-label">${category.name}</span>
          </label>
        `).join('')}
      </div>
    `;

    container.innerHTML = filtersHTML;

    // Add event listeners
    const categoryFilters = container.querySelectorAll('.category-filter');
    categoryFilters.forEach(filter => {
      DOMUtils.addEventListener(filter, 'change', () => {
        this.updateCategoryFilter(filter);
      });
    });
  }

  /**
   * Show category loading error
   */
  showCategoryError() {
    const container = DOMUtils.getId('category-filters');
    if (container) {
      container.innerHTML = `
        <div class="filter-error">
          <p>Failed to load categories</p>
          <button class="btn btn--outline btn--small" onclick="window.productsPage.loadCategories()">
            Retry
          </button>
        </div>
      `;
    }
  }

  /**
   * Update category filter
   */
  updateCategoryFilter(filterElement) {
    const categoryId = parseInt(filterElement.dataset.categoryId);
    
    if (filterElement.checked) {
      this.filters.category = categoryId;
      
      // Uncheck other categories (single selection)
      const otherFilters = DOMUtils.getElements('.category-filter');
      otherFilters.forEach(filter => {
        if (filter !== filterElement) {
          filter.checked = false;
        }
      });
    } else {
      this.filters.category = null;
    }

    this.currentPage = 1;
    this.updateUrl();
    this.loadProducts();
    this.updateActiveFilters();
  }

  /**
   * Initialize price range sliders
   */
  initializePriceRange() {
    const minSlider = DOMUtils.getId('price-range-min');
    const maxSlider = DOMUtils.getId('price-range-max');
    const minInput = DOMUtils.getId('min-price');
    const maxInput = DOMUtils.getId('max-price');

    if (minSlider && maxSlider && minInput && maxInput) {
      // Set initial values from filters
      if (this.filters.minPrice !== null) {
        minSlider.value = this.filters.minPrice;
        minInput.value = this.filters.minPrice;
      }
      if (this.filters.maxPrice !== null) {
        maxSlider.value = this.filters.maxPrice;
        maxInput.value = this.filters.maxPrice;
      }

      this.updatePriceRangeDisplay();
    }
  }

  /**
   * Update price filter from inputs
   */
  updatePriceFilter() {
    const minInput = DOMUtils.getId('min-price');
    const maxInput = DOMUtils.getId('max-price');
    const minSlider = DOMUtils.getId('price-range-min');
    const maxSlider = DOMUtils.getId('price-range-max');

    if (minInput && maxInput && minSlider && maxSlider) {
      const minValue = parseFloat(minInput.value) || 0;
      const maxValue = parseFloat(maxInput.value) || 5000;

      // Validate range
      if (minValue > maxValue) {
        minInput.value = maxValue;
        this.filters.minPrice = maxValue;
      } else {
        this.filters.minPrice = minValue || null;
      }

      this.filters.maxPrice = maxValue || null;

      // Update sliders
      minSlider.value = this.filters.minPrice || 0;
      maxSlider.value = this.filters.maxPrice || 5000;

      this.updatePriceRangeDisplay();
      this.currentPage = 1;
      this.updateUrl();
      this.loadProducts();
      this.updateActiveFilters();
    }
  }

  /**
   * Update price range from sliders
   */
  updatePriceRangeFromSlider() {
    const minSlider = DOMUtils.getId('price-range-min');
    const maxSlider = DOMUtils.getId('price-range-max');
    const minInput = DOMUtils.getId('min-price');
    const maxInput = DOMUtils.getId('max-price');

    if (minSlider && maxSlider && minInput && maxInput) {
      let minValue = parseInt(minSlider.value);
      let maxValue = parseInt(maxSlider.value);

      // Ensure min is not greater than max
      if (minValue > maxValue) {
        minValue = maxValue;
        minSlider.value = minValue;
      }

      this.filters.minPrice = minValue || null;
      this.filters.maxPrice = maxValue || null;

      // Update inputs
      minInput.value = minValue;
      maxInput.value = maxValue;

      this.updatePriceRangeDisplay();
      
      // Debounce the API call
      clearTimeout(this.priceRangeTimeout);
      this.priceRangeTimeout = setTimeout(() => {
        this.currentPage = 1;
        this.updateUrl();
        this.loadProducts();
        this.updateActiveFilters();
      }, 500);
    }
  }

  /**
   * Update price range display
   */
  updatePriceRangeDisplay() {
    const display = DOMUtils.getId('price-range-text');
    if (display) {
      const min = this.filters.minPrice || 0;
      const max = this.filters.maxPrice || 5000;
      display.textContent = `₹${min} - ₹${max}`;
    }
  }

  /**
   * Update availability filter
   */
  updateAvailabilityFilter() {
    const inStockFilter = DOMUtils.getId('in-stock');
    const outOfStockFilter = DOMUtils.getId('out-of-stock');

    if (inStockFilter && outOfStockFilter) {
      this.filters.inStock = inStockFilter.checked ? true : null;
      this.filters.outOfStock = outOfStockFilter.checked ? true : null;

      this.currentPage = 1;
      this.updateUrl();
      this.loadProducts();
      this.updateActiveFilters();
    }
  }

  /**
   * Toggle sidebar (mobile)
   */
  toggleSidebar() {
    const sidebar = DOMUtils.getId('products-sidebar');
    if (sidebar) {
      sidebar.classList.toggle('show');
      
      // Add/remove overlay
      let overlay = DOMUtils.getElement('.sidebar-overlay');
      if (sidebar.classList.contains('show')) {
        if (!overlay) {
          overlay = DOMUtils.createElement('div', {
            className: 'sidebar-overlay'
          });
          document.body.appendChild(overlay);
          
          DOMUtils.addEventListener(overlay, 'click', () => {
            this.closeSidebar();
          });
        }
        overlay.classList.add('show');
        document.body.classList.add('sidebar-open');
      } else {
        if (overlay) {
          overlay.classList.remove('show');
        }
        document.body.classList.remove('sidebar-open');
      }
    }
  }

  /**
   * Close sidebar
   */
  closeSidebar() {
    const sidebar = DOMUtils.getId('products-sidebar');
    const overlay = DOMUtils.getElement('.sidebar-overlay');
    
    if (sidebar) {
      sidebar.classList.remove('show');
    }
    if (overlay) {
      overlay.classList.remove('show');
    }
    document.body.classList.remove('sidebar-open');
  }

  /**
   * Change view (grid/list)
   */
  changeView(view) {
    if (this.currentView === view) return;

    this.currentView = view;
    
    // Update view buttons
    const viewButtons = DOMUtils.getElements('.view-btn');
    viewButtons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.view === view);
    });

    // Update products grid
    const productsGrid = DOMUtils.getId('products-grid');
    if (productsGrid) {
      productsGrid.classList.toggle('list-view', view === 'list');
    }

    // Update product cards
    const productCards = DOMUtils.getElements('.product-card');
    productCards.forEach(card => {
      card.classList.toggle('list-view', view === 'list');
    });

    this.updateUrl();
  }

  /**
   * Toggle sort menu
   */
  toggleSortMenu() {
    const sortToggle = DOMUtils.getId('sort-toggle');
    const sortMenu = DOMUtils.getId('sort-menu');
    
    if (sortToggle && sortMenu) {
      const isOpen = sortMenu.classList.contains('show');
      
      if (isOpen) {
        this.closeSortMenu();
      } else {
        sortToggle.classList.add('active');
        sortMenu.classList.add('show');
      }
    }
  }

  /**
   * Close sort menu
   */
  closeSortMenu() {
    const sortToggle = DOMUtils.getId('sort-toggle');
    const sortMenu = DOMUtils.getId('sort-menu');
    
    if (sortToggle && sortMenu) {
      sortToggle.classList.remove('active');
      sortMenu.classList.remove('show');
    }
  }

  /**
   * Change sort option
   */
  changeSort(sort) {
    if (this.currentSort === sort) return;

    this.currentSort = sort;
    
    // Update sort options
    const sortOptions = DOMUtils.getElements('.sort-option');
    sortOptions.forEach(option => {
      option.classList.toggle('active', option.dataset.sort === sort);
    });

    // Update current sort display
    const currentSort = DOMUtils.getId('current-sort');
    if (currentSort) {
      const activeOption = DOMUtils.getElement('.sort-option.active');
      if (activeOption) {
        currentSort.textContent = activeOption.textContent;
      }
    }

    this.closeSortMenu();
    this.currentPage = 1;
    this.updateUrl();
    this.loadProducts();
  }

  /**
   * Load products from API
   */
  async loadProducts(append = false) {
    if (this.isLoading) return;

    this.isLoading = true;
    
    try {
      // Show loading state
      if (!append) {
        this.showProductsLoading();
      } else {
        this.showLoadMoreLoading();
      }

      // Build query parameters
      const params = {
        page: this.currentPage,
        limit: this.productsPerPage,
        sort: this.currentSort
      };

      // Add filters
      if (this.filters.search) {
        params.search = this.filters.search;
      }
      if (this.filters.category) {
        params.category = this.filters.category;
      }
      if (this.filters.minPrice !== null) {
        params.minPrice = this.filters.minPrice;
      }
      if (this.filters.maxPrice !== null) {
        params.maxPrice = this.filters.maxPrice;
      }
      if (this.filters.brands.length > 0) {
        params.brands = this.filters.brands.join(',');
      }
      if (this.filters.inStock) {
        params.inStock = true;
      }
      if (this.filters.outOfStock) {
        params.outOfStock = true;
      }

      const response = await ApiService.products.getAll(params);

      if (response.success) {
        const { products, pagination, brands } = response.data;
        
        this.totalProducts = pagination.total;
        this.totalPages = pagination.totalPages;
        this.currentPage = pagination.currentPage;

        // Update brands list
        if (brands && brands.length > 0) {
          this.brands = brands;
          this.renderBrandFilters();
        }

        if (append) {
          this.appendProducts(products);
        } else {
          this.renderProducts(products);
        }

        this.updateResultsInfo();
        this.updatePagination();
        this.updateActiveFilters();
        
      } else {
        this.showProductsError(response.message);
      }
    } catch (error) {
      console.error('Error loading products:', error);
      this.showProductsError('Failed to load products. Please try again.');
    } finally {
      this.isLoading = false;
      this.hideProductsLoading();
      this.hideLoadMoreLoading();
    }
  }

  /**
   * Load more products (pagination)
   */
  async loadMoreProducts() {
    if (this.loadingMore || this.currentPage >= this.totalPages) return;

    this.loadingMore = true;
    this.currentPage++;
    await this.loadProducts(true);
    this.loadingMore = false;
  }

  /**
   * Show products loading state
   */
  showProductsLoading() {
    const productsGrid = DOMUtils.getId('products-grid');
    const noResults = DOMUtils.getId('no-results');
    const loadingElement = DOMUtils.getId('products-loading');

    if (productsGrid) {
      productsGrid.innerHTML = '';
    }
    if (noResults) {
      noResults.style.display = 'none';
    }
    if (loadingElement) {
      loadingElement.style.display = 'block';
      productsGrid?.appendChild(loadingElement);
    }
  }

  /**
   * Hide products loading state
   */
  hideProductsLoading() {
    const loadingElement = DOMUtils.getId('products-loading');
    if (loadingElement) {
      loadingElement.style.display = 'none';
    }
  }

  /**
   * Show load more loading state
   */
  showLoadMoreLoading() {
    const loadMoreBtn = DOMUtils.getId('load-more-btn');
    if (loadMoreBtn) {
      loadMoreBtn.classList.add('loading');
    }
  }

  /**
   * Hide load more loading state
   */
  hideLoadMoreLoading() {
    const loadMoreBtn = DOMUtils.getId('load-more-btn');
    if (loadMoreBtn) {
      loadMoreBtn.classList.remove('loading');
    }
  }

  /**
   * Render products
   */
  renderProducts(products) {
    const productsGrid = DOMUtils.getId('products-grid');
    const noResults = DOMUtils.getId('no-results');

    if (!productsGrid) return;

    if (products.length === 0) {
      productsGrid.innerHTML = '';
      if (noResults) {
        noResults.style.display = 'block';
      }
      return;
    }

    if (noResults) {
      noResults.style.display = 'none';
    }

    const productsHTML = products.map(product => this.renderProductCard(product)).join('');
    productsGrid.innerHTML = productsHTML;

    // Apply current view
    if (this.currentView === 'list') {
      productsGrid.classList.add('list-view');
      const productCards = productsGrid.querySelectorAll('.product-card');
      productCards.forEach(card => card.classList.add('list-view'));
    }

    // Add event listeners
    this.addProductEventListeners(productsGrid);
  }

  /**
   * Append products (for load more)
   */
  appendProducts(products) {
    const productsGrid = DOMUtils.getId('products-grid');
    if (!productsGrid || products.length === 0) return;

    const productsHTML = products.map(product => this.renderProductCard(product)).join('');
    productsGrid.insertAdjacentHTML('beforeend', productsHTML);

    // Apply current view to new cards
    if (this.currentView === 'list') {
      const newCards = productsGrid.querySelectorAll('.product-card:not(.list-view)');
      newCards.forEach(card => card.classList.add('list-view'));
    }

    // Add event listeners to new products
    const newProducts = productsGrid.querySelectorAll('.product-card:not([data-listeners])');
    newProducts.forEach(product => {
      this.addProductCardListeners(product);
      product.setAttribute('data-listeners', 'true');
    });
  }

  /**
   * Render product card
   */
  renderProductCard(product) {
    const isOutOfStock = product.stockQuantity <= 0;
    const hasDiscount = product.originalPrice && product.originalPrice > product.price;
    const discountPercent = hasDiscount ? Math.round(((product.originalPrice - product.price) / product.originalPrice) * 100) : 0;

    return `
      <div class="product-card" data-product-id="${product.id}">
        <div class="product-card__image-container">
          <img src="${product.primaryImage || '../assets/placeholder.jpg'}" 
               alt="${product.name}" 
               class="product-card__image"
               loading="lazy">
          
          ${isOutOfStock ? '<div class="product-card__badge product-card__badge--out-of-stock">Out of Stock</div>' : ''}
          ${hasDiscount ? `<div class="product-card__badge product-card__badge--sale">${discountPercent}% Off</div>` : ''}
          ${product.isNew ? '<div class="product-card__badge product-card__badge--new">New</div>' : ''}
          
          <div class="product-card__actions">
            <button class="product-card__action-btn wishlist-btn" 
                    data-product-id="${product.id}"
                    aria-label="Add to wishlist">
              <i class="ri-heart-line"></i>
            </button>
            <button class="product-card__action-btn quick-view-btn" 
                    data-product-id="${product.id}"
                    aria-label="Quick view">
              <i class="ri-eye-line"></i>
            </button>
          </div>
        </div>
        
        <div class="product-card__content">
          ${product.brand ? `<div class="product-card__brand">${product.brand}</div>` : ''}
          
          <h3 class="product-card__title">${product.name}</h3>
          
          ${product.description ? `<p class="product-card__description">${product.description}</p>` : ''}
          
          <div class="product-card__price">
            <span class="product-card__current-price">₹${product.price}</span>
            ${hasDiscount ? `<span class="product-card__original-price">₹${product.originalPrice}</span>` : ''}
            ${hasDiscount ? `<span class="product-card__discount">${discountPercent}% OFF</span>` : ''}
          </div>
          
          ${product.rating ? `
            <div class="product-card__rating">
              <div class="product-card__stars">
                ${this.renderStars(product.rating)}
              </div>
              <span class="product-card__rating-text">(${product.reviewCount || 0})</span>
            </div>
          ` : ''}
          
          <div class="product-card__stock ${isOutOfStock ? 'out-of-stock' : product.stockQuantity <= 5 ? 'low-stock' : 'in-stock'}">
            ${isOutOfStock ? 'Out of Stock' : product.stockQuantity <= 5 ? `Only ${product.stockQuantity} left` : 'In Stock'}
          </div>
          
          <div class="product-card__footer">
            <button class="product-card__add-to-cart" 
                    data-product-id="${product.id}"
                    ${isOutOfStock ? 'disabled' : ''}>
              <i class="ri-shopping-cart-line"></i>
              ${isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
            </button>
            <button class="product-card__quick-view" 
                    data-product-id="${product.id}"
                    aria-label="Quick view">
              <i class="ri-eye-line"></i>
            </button>
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Render star rating
   */
  renderStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

    let starsHTML = '';
    
    // Full stars
    for (let i = 0; i < fullStars; i++) {
      starsHTML += '<i class="ri-star-fill product-card__star filled"></i>';
    }
    
    // Half star
    if (hasHalfStar) {
      starsHTML += '<i class="ri-star-half-line product-card__star filled"></i>';
    }
    
    // Empty stars
    for (let i = 0; i < emptyStars; i++) {
      starsHTML += '<i class="ri-star-line product-card__star"></i>';
    }

    return starsHTML;
  }

  /**
   * Add event listeners to products
   */
  addProductEventListeners(container) {
    const productCards = container.querySelectorAll('.product-card');
    productCards.forEach(card => {
      this.addProductCardListeners(card);
      card.setAttribute('data-listeners', 'true');
    });
  }

  /**
   * Add event listeners to product card
   */
  addProductCardListeners(card) {
    const productId = card.dataset.productId;

    // Card click (navigate to product page)
    DOMUtils.addEventListener(card, 'click', (e) => {
      // Don't navigate if clicking on buttons
      if (e.target.closest('button')) return;
      
      window.location.href = `product.html?id=${productId}`;
    });

    // Add to cart button
    const addToCartBtn = card.querySelector('.product-card__add-to-cart');
    if (addToCartBtn) {
      DOMUtils.addEventListener(addToCartBtn, 'click', (e) => {
        e.stopPropagation();
        this.addToCart(productId);
      });
    }

    // Wishlist button
    const wishlistBtn = card.querySelector('.wishlist-btn');
    if (wishlistBtn) {
      DOMUtils.addEventListener(wishlistBtn, 'click', (e) => {
        e.stopPropagation();
        this.toggleWishlist(productId, wishlistBtn);
      });
    }

    // Quick view buttons
    const quickViewBtns = card.querySelectorAll('.quick-view-btn, .product-card__quick-view');
    quickViewBtns.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', (e) => {
        e.stopPropagation();
        this.openQuickView(productId);
      });
    });
  }

  /**
   * Add product to cart
   */
  async addToCart(productId) {
    try {
      if (!isAuthenticated()) {
        this.showNotification('Please log in to add items to cart', 'warning');
        return;
      }

      const response = await ApiService.cart.add({
        productId: parseInt(productId),
        quantity: 1
      });

      if (response.success) {
        this.showNotification('Product added to cart!', 'success');
        
        // Update cart count
        if (window.Navigation) {
          window.Navigation.updateCartCount();
        }
      } else {
        this.showNotification(response.message || 'Failed to add product to cart', 'error');
      }
    } catch (error) {
      console.error('Add to cart error:', error);
      this.showNotification('Failed to add product to cart', 'error');
    }
  }

  /**
   * Toggle wishlist
   */
  async toggleWishlist(productId, button) {
    try {
      if (!isAuthenticated()) {
        this.showNotification('Please log in to manage wishlist', 'warning');
        return;
      }

      // Toggle button state immediately for better UX
      const isActive = button.classList.contains('active');
      button.classList.toggle('active');
      
      const icon = button.querySelector('i');
      if (icon) {
        icon.className = isActive ? 'ri-heart-line' : 'ri-heart-fill';
      }

      // TODO: Implement wishlist API calls
      this.showNotification(
        isActive ? 'Removed from wishlist' : 'Added to wishlist', 
        'success'
      );
    } catch (error) {
      console.error('Wishlist error:', error);
      // Revert button state on error
      button.classList.toggle('active');
      const icon = button.querySelector('i');
      if (icon) {
        icon.className = button.classList.contains('active') ? 'ri-heart-fill' : 'ri-heart-line';
      }
      this.showNotification('Failed to update wishlist', 'error');
    }
  }

  /**
   * Open quick view modal
   */
  openQuickView(productId) {
    // TODO: Implement quick view modal
    console.log('Opening quick view for product:', productId);
    this.showNotification('Quick view coming soon!', 'info');
  }

  /**
   * Show products error
   */
  showProductsError(message) {
    const productsGrid = DOMUtils.getId('products-grid');
    const noResults = DOMUtils.getId('no-results');

    if (productsGrid) {
      productsGrid.innerHTML = `
        <div class="products-error">
          <div class="products-error-icon">
            <i class="ri-error-warning-line"></i>
          </div>
          <h3>Error Loading Products</h3>
          <p>${message}</p>
          <button class="btn btn--primary" onclick="window.productsPage.loadProducts()">
            Try Again
          </button>
        </div>
      `;
    }

    if (noResults) {
      noResults.style.display = 'none';
    }
  }

  /**
   * Update results info
   */
  updateResultsInfo() {
    const resultsCount = DOMUtils.getId('results-count');
    if (resultsCount) {
      const start = (this.currentPage - 1) * this.productsPerPage + 1;
      const end = Math.min(this.currentPage * this.productsPerPage, this.totalProducts);
      
      if (this.totalProducts === 0) {
        resultsCount.textContent = 'No products found';
      } else {
        resultsCount.textContent = `Showing ${start}-${end} of ${this.totalProducts} products`;
      }
    }
  }

  /**
   * Update pagination
   */
  updatePagination() {
    const loadMoreContainer = DOMUtils.getId('load-more-container');
    const paginationContainer = DOMUtils.getId('pagination-container');

    // Show/hide load more button
    if (loadMoreContainer) {
      if (this.currentPage < this.totalPages && this.totalProducts > 0) {
        loadMoreContainer.style.display = 'block';
      } else {
        loadMoreContainer.style.display = 'none';
      }
    }

    // TODO: Implement numbered pagination if needed
    if (paginationContainer) {
      paginationContainer.style.display = 'none';
    }
  }

  /**
   * Render brand filters
   */
  renderBrandFilters() {
    const container = DOMUtils.getId('brand-options');
    if (!container || this.brands.length === 0) return;

    const filtersHTML = `
      ${this.brands.map(brand => `
        <label class="filter-option">
          <input type="checkbox" 
                 class="filter-checkbox brand-filter" 
                 data-brand="${brand}"
                 ${this.filters.brands.includes(brand) ? 'checked' : ''}>
          <span class="filter-checkmark"></span>
          <span class="filter-label">${brand}</span>
        </label>
      `).join('')}
    `;

    container.innerHTML = filtersHTML;

    // Add event listeners
    const brandFilters = container.querySelectorAll('.brand-filter');
    brandFilters.forEach(filter => {
      DOMUtils.addEventListener(filter, 'change', () => {
        this.updateBrandFilter(filter);
      });
    });
  }

  /**
   * Update brand filter
   */
  updateBrandFilter(filterElement) {
    const brand = filterElement.dataset.brand;
    
    if (filterElement.checked) {
      if (!this.filters.brands.includes(brand)) {
        this.filters.brands.push(brand);
      }
    } else {
      const index = this.filters.brands.indexOf(brand);
      if (index > -1) {
        this.filters.brands.splice(index, 1);
      }
    }

    this.currentPage = 1;
    this.updateUrl();
    this.loadProducts();
    this.updateActiveFilters();
  }

  /**
   * Filter brand options based on search
   */
  filterBrandOptions(query) {
    const brandOptions = DOMUtils.getElements('.brand-filter');
    const searchQuery = query.toLowerCase();

    brandOptions.forEach(option => {
      const label = option.parentNode.querySelector('.filter-label');
      const brandName = label.textContent.toLowerCase();
      const shouldShow = brandName.includes(searchQuery);
      
      option.parentNode.style.display = shouldShow ? 'flex' : 'none';
    });
  }

  /**
   * Update active filters display
   */
  updateActiveFilters() {
    const activeFiltersContainer = DOMUtils.getId('active-filters');
    const activeFiltersList = DOMUtils.getId('active-filters-list');
    
    if (!activeFiltersContainer || !activeFiltersList) return;

    const activeFilters = [];

    // Search filter
    if (this.filters.search) {
      activeFilters.push({
        type: 'search',
        label: `Search: "${this.filters.search}"`,
        value: this.filters.search
      });
    }

    // Category filter
    if (this.filters.category) {
      const category = this.categories.find(cat => cat.id === this.filters.category);
      if (category) {
        activeFilters.push({
          type: 'category',
          label: `Category: ${category.name}`,
          value: this.filters.category
        });
      }
    }

    // Price range filter
    if (this.filters.minPrice !== null || this.filters.maxPrice !== null) {
      const min = this.filters.minPrice || 0;
      const max = this.filters.maxPrice || 5000;
      activeFilters.push({
        type: 'price',
        label: `Price: ₹${min} - ₹${max}`,
        value: 'price'
      });
    }

    // Brand filters
    this.filters.brands.forEach(brand => {
      activeFilters.push({
        type: 'brand',
        label: `Brand: ${brand}`,
        value: brand
      });
    });

    // Availability filters
    if (this.filters.inStock) {
      activeFilters.push({
        type: 'availability',
        label: 'In Stock',
        value: 'inStock'
      });
    }
    if (this.filters.outOfStock) {
      activeFilters.push({
        type: 'availability',
        label: 'Out of Stock',
        value: 'outOfStock'
      });
    }

    // Show/hide active filters
    if (activeFilters.length > 0) {
      activeFiltersContainer.style.display = 'block';
      
      const filtersHTML = activeFilters.map(filter => `
        <div class="filter-tag">
          <span>${filter.label}</span>
          <button class="filter-tag-remove" 
                  data-filter-type="${filter.type}" 
                  data-filter-value="${filter.value}">
            <i class="ri-close-line"></i>
          </button>
        </div>
      `).join('');
      
      activeFiltersList.innerHTML = filtersHTML;

      // Add remove listeners
      const removeButtons = activeFiltersList.querySelectorAll('.filter-tag-remove');
      removeButtons.forEach(btn => {
        DOMUtils.addEventListener(btn, 'click', () => {
          this.removeFilter(btn.dataset.filterType, btn.dataset.filterValue);
        });
      });
    } else {
      activeFiltersContainer.style.display = 'none';
    }
  }

  /**
   * Remove specific filter
   */
  removeFilter(type, value) {
    switch (type) {
      case 'search':
        this.filters.search = '';
        this.updateSearchInput('');
        break;
      case 'category':
        this.filters.category = null;
        const categoryFilter = DOMUtils.getElement(`[data-category-id="${value}"]`);
        if (categoryFilter) categoryFilter.checked = false;
        break;
      case 'price':
        this.filters.minPrice = null;
        this.filters.maxPrice = null;
        this.initializePriceRange();
        break;
      case 'brand':
        const index = this.filters.brands.indexOf(value);
        if (index > -1) this.filters.brands.splice(index, 1);
        const brandFilter = DOMUtils.getElement(`[data-brand="${value}"]`);
        if (brandFilter) brandFilter.checked = false;
        break;
      case 'availability':
        if (value === 'inStock') {
          this.filters.inStock = null;
          const inStockFilter = DOMUtils.getId('in-stock');
          if (inStockFilter) inStockFilter.checked = false;
        } else if (value === 'outOfStock') {
          this.filters.outOfStock = null;
          const outOfStockFilter = DOMUtils.getId('out-of-stock');
          if (outOfStockFilter) outOfStockFilter.checked = false;
        }
        break;
    }

    this.currentPage = 1;
    this.updateUrl();
    this.loadProducts();
  }

  /**
   * Clear all filters
   */
  clearAllFilters() {
    // Reset filters
    this.filters = {
      search: '',
      category: null,
      minPrice: null,
      maxPrice: null,
      brands: [],
      inStock: null,
      outOfStock: null
    };

    // Reset UI elements
    this.updateSearchInput('');
    
    const categoryFilters = DOMUtils.getElements('.category-filter');
    categoryFilters.forEach(filter => filter.checked = false);
    
    const brandFilters = DOMUtils.getElements('.brand-filter');
    brandFilters.forEach(filter => filter.checked = false);
    
    const inStockFilter = DOMUtils.getId('in-stock');
    const outOfStockFilter = DOMUtils.getId('out-of-stock');
    if (inStockFilter) inStockFilter.checked = false;
    if (outOfStockFilter) outOfStockFilter.checked = false;
    
    this.initializePriceRange();

    this.currentPage = 1;
    this.updateUrl();
    this.loadProducts();
  }

  /**
   * Update URL with current filters and state
   */
  updateUrl() {
    const params = new URLSearchParams();

    // Add filters to URL
    if (this.filters.search) params.set('search', this.filters.search);
    if (this.filters.category) params.set('category', this.filters.category);
    if (this.filters.minPrice !== null) params.set('minPrice', this.filters.minPrice);
    if (this.filters.maxPrice !== null) params.set('maxPrice', this.filters.maxPrice);
    if (this.filters.brands.length > 0) params.set('brands', this.filters.brands.join(','));
    if (this.filters.inStock) params.set('inStock', 'true');
    if (this.filters.outOfStock) params.set('outOfStock', 'true');
    
    // Add sort and view
    if (this.currentSort !== 'featured') params.set('sort', this.currentSort);
    if (this.currentView !== 'grid') params.set('view', this.currentView);

    // Update URL without page reload
    const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
    window.history.replaceState({}, '', newUrl);
  }

  /**
   * Handle window resize
   */
  handleResize() {
    const breakpoint = CONFIG_UTILS.getCurrentBreakpoint();
    
    // Close sidebar on larger screens
    if ((breakpoint === 'desktop' || breakpoint === 'large')) {
      this.closeSidebar();
    }
  }

  /**
   * Show notification
   */
  showNotification(message, type = 'info') {
    // This will be handled by the main notification system
    console.log(`${type.toUpperCase()}: ${message}`);
  }
}

// Initialize products page when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize on products page
  if (window.location.pathname.includes('products.html')) {
    const productsPage = new ProductsPage();
    
    // Export for global access
    window.productsPage = productsPage;
  }
});

// Export class for testing
window.ProductsPageClass = ProductsPage;