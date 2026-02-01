/**
 * Search component for Riya Collections
 * Handles search functionality, suggestions, and recent searches
 */

class Search {
  constructor() {
    this.isSearchOpen = false;
    this.currentQuery = '';
    this.searchTimeout = null;
    this.recentSearches = [];
    this.init();
  }

  /**
   * Initialize search component
   */
  init() {
    this.loadRecentSearches();
    this.setupEventListeners();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Search button
    const searchBtn = DOMUtils.getId('search-btn');
    if (searchBtn) {
      DOMUtils.addEventListener(searchBtn, 'click', () => {
        this.openSearch();
      });
    }

    // Search close button
    const searchClose = DOMUtils.getId('search-close');
    if (searchClose) {
      DOMUtils.addEventListener(searchClose, 'click', () => {
        this.closeSearch();
      });
    }

    // Search overlay
    const searchOverlay = DOMUtils.getId('search-overlay');
    if (searchOverlay) {
      DOMUtils.addEventListener(searchOverlay, 'click', (e) => {
        if (e.target === searchOverlay) {
          this.closeSearch();
        }
      });
    }

    // Search form
    const searchForm = DOMUtils.getId('search-form');
    if (searchForm) {
      DOMUtils.addEventListener(searchForm, 'submit', (e) => {
        e.preventDefault();
        this.handleSearchSubmit();
      });
    }

    // Search input
    const searchInput = DOMUtils.getId('search-input');
    if (searchInput) {
      DOMUtils.addEventListener(searchInput, 'input', (e) => {
        this.handleSearchInput(e.target.value);
      });

      DOMUtils.addEventListener(searchInput, 'focus', () => {
        this.showSearchSuggestions();
      });

      DOMUtils.addEventListener(searchInput, 'keydown', (e) => {
        this.handleSearchKeydown(e);
      });
    }

    // Keyboard shortcuts
    DOMUtils.addEventListener(document, 'keydown', (e) => {
      // Ctrl/Cmd + K to open search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        this.openSearch();
      }
      
      // Escape to close search
      if (e.key === 'Escape' && this.isSearchOpen) {
        this.closeSearch();
      }
    });
  }

  /**
   * Open search overlay
   */
  openSearch() {
    const searchOverlay = DOMUtils.getId('search-overlay');
    const searchInput = DOMUtils.getId('search-input');
    
    if (searchOverlay) {
      this.isSearchOpen = true;
      searchOverlay.classList.add('show');
      document.body.classList.add('search-open');
      
      // Focus input
      if (searchInput) {
        setTimeout(() => {
          searchInput.focus();
          searchInput.select();
        }, 100);
      }
      
      // Show recent searches if input is empty
      if (!searchInput.value.trim()) {
        this.showRecentSearches();
      }
    }
  }

  /**
   * Close search overlay
   */
  closeSearch() {
    const searchOverlay = DOMUtils.getId('search-overlay');
    
    if (searchOverlay) {
      this.isSearchOpen = false;
      searchOverlay.classList.remove('show');
      document.body.classList.remove('search-open');
      
      // Clear results
      this.clearSearchResults();
    }
  }

  /**
   * Handle search input
   */
  handleSearchInput(query) {
    this.currentQuery = query.trim();
    
    // Clear previous timeout
    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
    }
    
    // If query is empty, show recent searches
    if (!this.currentQuery) {
      this.showRecentSearches();
      return;
    }
    
    // If query is too short, clear results
    if (this.currentQuery.length < APP_CONFIG.SEARCH.MIN_QUERY_LENGTH) {
      this.clearSearchResults();
      return;
    }
    
    // Debounce search
    this.searchTimeout = setTimeout(() => {
      this.performSearch(this.currentQuery);
    }, APP_CONFIG.SEARCH.DEBOUNCE_DELAY);
  }

  /**
   * Handle search form submission
   */
  handleSearchSubmit() {
    const searchInput = DOMUtils.getId('search-input');
    const query = searchInput ? searchInput.value.trim() : '';
    
    if (query.length >= APP_CONFIG.SEARCH.MIN_QUERY_LENGTH) {
      this.saveRecentSearch(query);
      this.navigateToSearchResults(query);
    }
  }

  /**
   * Handle search input keydown
   */
  handleSearchKeydown(e) {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;
    
    const suggestions = searchResults.querySelectorAll('.search-suggestion, .recent-search-item');
    const activeSuggestion = searchResults.querySelector('.search-suggestion.active, .recent-search-item.active');
    
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        this.navigateSuggestions(suggestions, activeSuggestion, 'down');
        break;
        
      case 'ArrowUp':
        e.preventDefault();
        this.navigateSuggestions(suggestions, activeSuggestion, 'up');
        break;
        
      case 'Enter':
        if (activeSuggestion) {
          e.preventDefault();
          this.selectSuggestion(activeSuggestion);
        }
        break;
        
      case 'Escape':
        this.closeSearch();
        break;
    }
  }

  /**
   * Navigate through search suggestions with keyboard
   */
  navigateSuggestions(suggestions, activeSuggestion, direction) {
    if (suggestions.length === 0) return;
    
    // Remove current active state
    if (activeSuggestion) {
      activeSuggestion.classList.remove('active');
    }
    
    let nextIndex = 0;
    
    if (activeSuggestion) {
      const currentIndex = Array.from(suggestions).indexOf(activeSuggestion);
      
      if (direction === 'down') {
        nextIndex = (currentIndex + 1) % suggestions.length;
      } else {
        nextIndex = currentIndex === 0 ? suggestions.length - 1 : currentIndex - 1;
      }
    }
    
    // Add active state to next suggestion
    suggestions[nextIndex].classList.add('active');
    suggestions[nextIndex].scrollIntoView({ block: 'nearest' });
  }

  /**
   * Select a search suggestion
   */
  selectSuggestion(suggestion) {
    if (suggestion.classList.contains('recent-search-item')) {
      const query = suggestion.dataset.query;
      this.fillSearchInput(query);
      this.performSearch(query);
    } else if (suggestion.classList.contains('search-suggestion')) {
      const productId = suggestion.dataset.productId;
      if (productId) {
        this.navigateToProduct(productId);
      }
    }
  }

  /**
   * Perform search and show suggestions
   */
  async performSearch(query) {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;
    
    try {
      // Show loading state
      searchResults.innerHTML = `
        <div class="search-loading">
          <div class="loading-spinner"></div>
          <p>Searching for "${query}"...</p>
        </div>
      `;
      
      // Perform search
      const response = await ApiService.products.search(query, {
        limit: APP_CONFIG.SEARCH.MAX_SUGGESTIONS
      });
      
      if (response.success) {
        this.renderSearchResults(response.data.products, query);
      } else {
        this.showNoResults(query);
      }
    } catch (error) {
      console.error('Search error:', error);
      this.showSearchError();
    }
  }

  /**
   * Render search results
   */
  renderSearchResults(products, query) {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;
    
    if (products.length === 0) {
      this.showNoResults(query);
      return;
    }
    
    const resultsHTML = `
      <div class="search-results-container">
        <div class="search-results-header">
          <h3>Products</h3>
          <span class="search-results-count">${products.length} results</span>
        </div>
        <div class="search-suggestions">
          ${products.map(product => this.renderProductSuggestion(product, query)).join('')}
        </div>
        <div class="search-view-all">
          <button class="search-view-all-btn" data-query="${query}">
            <i class="ri-search-line"></i>
            View all results for "${query}"
          </button>
        </div>
      </div>
    `;
    
    searchResults.innerHTML = resultsHTML;
    
    // Add event listeners
    this.addSearchResultListeners(searchResults, query);
  }

  /**
   * Render product suggestion
   */
  renderProductSuggestion(product, query) {
    return `
      <div class="search-suggestion" data-product-id="${product.id}">
        <div class="search-suggestion__image">
          <img src="${product.primaryImage || 'assets/placeholder.jpg'}" 
               alt="${product.name}" 
               loading="lazy">
        </div>
        <div class="search-suggestion__content">
          <h4 class="search-suggestion__title">${this.highlightQuery(product.name, query)}</h4>
          <p class="search-suggestion__brand">${product.brand || ''}</p>
          <div class="search-suggestion__price">
            <span class="search-suggestion__current-price">${FormatUtils.currency(product.price)}</span>
            ${product.originalPrice ? `<span class="search-suggestion__original-price">${FormatUtils.currency(product.originalPrice)}</span>` : ''}
          </div>
          <div class="search-suggestion__meta">
            <span class="search-suggestion__category">${product.category?.name || ''}</span>
            ${product.stockQuantity > 0 ? '<span class="search-suggestion__stock in-stock">In Stock</span>' : '<span class="search-suggestion__stock out-of-stock">Out of Stock</span>'}
          </div>
        </div>
        <div class="search-suggestion__actions">
          <button class="search-suggestion__quick-add" data-product-id="${product.id}" ${product.stockQuantity <= 0 ? 'disabled' : ''}>
            <i class="ri-shopping-cart-line"></i>
          </button>
        </div>
      </div>
    `;
  }

  /**
   * Show recent searches
   */
  showRecentSearches() {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults || this.recentSearches.length === 0) {
      this.showSearchSuggestions();
      return;
    }
    
    const recentHTML = `
      <div class="recent-searches">
        <div class="recent-searches-header">
          <h3>Recent Searches</h3>
          <button class="recent-searches-clear">Clear All</button>
        </div>
        <div class="recent-searches-list">
          ${this.recentSearches.map(query => `
            <div class="recent-search-item" data-query="${query}">
              <i class="ri-time-line"></i>
              <span class="recent-search-query">${query}</span>
              <button class="recent-search-remove" data-query="${query}">
                <i class="ri-close-line"></i>
              </button>
            </div>
          `).join('')}
        </div>
      </div>
    `;
    
    searchResults.innerHTML = recentHTML;
    
    // Add event listeners
    this.addRecentSearchListeners(searchResults);
  }

  /**
   * Show search suggestions (popular searches, categories, etc.)
   */
  showSearchSuggestions() {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;
    
    const suggestionsHTML = `
      <div class="search-suggestions-default">
        <div class="search-popular">
          <h3>Popular Searches</h3>
          <div class="search-tags">
            <button class="search-tag" data-query="lipstick">Lipstick</button>
            <button class="search-tag" data-query="foundation">Foundation</button>
            <button class="search-tag" data-query="mascara">Mascara</button>
            <button class="search-tag" data-query="skincare">Skincare</button>
            <button class="search-tag" data-query="hair oil">Hair Oil</button>
            <button class="search-tag" data-query="face wash">Face Wash</button>
          </div>
        </div>
        <div class="search-categories">
          <h3>Browse Categories</h3>
          <div class="search-category-list">
            <a href="pages/products.html?category=1" class="search-category-item">
              <i class="ri-palette-line"></i>
              <span>Face Makeup</span>
            </a>
            <a href="pages/products.html?category=2" class="search-category-item">
              <i class="ri-heart-line"></i>
              <span>Lip Care</span>
            </a>
            <a href="pages/products.html?category=3" class="search-category-item">
              <i class="ri-scissors-line"></i>
              <span>Hair Care</span>
            </a>
            <a href="pages/products.html?category=4" class="search-category-item">
              <i class="ri-drop-line"></i>
              <span>Skin Care</span>
            </a>
          </div>
        </div>
      </div>
    `;
    
    searchResults.innerHTML = suggestionsHTML;
    
    // Add event listeners for tags
    const searchTags = searchResults.querySelectorAll('.search-tag');
    searchTags.forEach(tag => {
      DOMUtils.addEventListener(tag, 'click', () => {
        const query = tag.dataset.query;
        this.fillSearchInput(query);
        this.performSearch(query);
      });
    });
  }

  /**
   * Show no results message
   */
  showNoResults(query) {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;
    
    searchResults.innerHTML = `
      <div class="search-no-results">
        <div class="search-no-results-icon">
          <i class="ri-search-line"></i>
        </div>
        <h3>No results found</h3>
        <p>We couldn't find any products matching "${query}"</p>
        <div class="search-no-results-suggestions">
          <p>Try:</p>
          <ul>
            <li>Checking your spelling</li>
            <li>Using different keywords</li>
            <li>Searching for a product category</li>
          </ul>
        </div>
        <button class="btn btn--outline search-browse-all">
          Browse All Products
        </button>
      </div>
    `;
    
    // Add browse all button listener
    const browseBtn = searchResults.querySelector('.search-browse-all');
    if (browseBtn) {
      DOMUtils.addEventListener(browseBtn, 'click', () => {
        window.location.href = 'pages/products.html';
      });
    }
  }

  /**
   * Show search error
   */
  showSearchError() {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;
    
    searchResults.innerHTML = `
      <div class="search-error">
        <div class="search-error-icon">
          <i class="ri-error-warning-line"></i>
        </div>
        <h3>Search Error</h3>
        <p>Something went wrong while searching. Please try again.</p>
        <button class="btn btn--primary search-retry">
          Try Again
        </button>
      </div>
    `;
    
    // Add retry button listener
    const retryBtn = searchResults.querySelector('.search-retry');
    if (retryBtn) {
      DOMUtils.addEventListener(retryBtn, 'click', () => {
        if (this.currentQuery) {
          this.performSearch(this.currentQuery);
        }
      });
    }
  }

  /**
   * Add event listeners to search results
   */
  addSearchResultListeners(container, query) {
    // Product suggestions
    const suggestions = container.querySelectorAll('.search-suggestion');
    suggestions.forEach(suggestion => {
      DOMUtils.addEventListener(suggestion, 'click', (e) => {
        if (!e.target.closest('.search-suggestion__quick-add')) {
          const productId = suggestion.dataset.productId;
          this.navigateToProduct(productId);
        }
      });
    });
    
    // Quick add buttons
    const quickAddBtns = container.querySelectorAll('.search-suggestion__quick-add');
    quickAddBtns.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', (e) => {
        e.stopPropagation();
        const productId = btn.dataset.productId;
        this.quickAddToCart(productId);
      });
    });
    
    // View all results button
    const viewAllBtn = container.querySelector('.search-view-all-btn');
    if (viewAllBtn) {
      DOMUtils.addEventListener(viewAllBtn, 'click', () => {
        this.navigateToSearchResults(query);
      });
    }
  }

  /**
   * Add event listeners to recent searches
   */
  addRecentSearchListeners(container) {
    // Recent search items
    const recentItems = container.querySelectorAll('.recent-search-item');
    recentItems.forEach(item => {
      DOMUtils.addEventListener(item, 'click', (e) => {
        if (!e.target.closest('.recent-search-remove')) {
          const query = item.dataset.query;
          this.fillSearchInput(query);
          this.performSearch(query);
        }
      });
    });
    
    // Remove buttons
    const removeButtons = container.querySelectorAll('.recent-search-remove');
    removeButtons.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', (e) => {
        e.stopPropagation();
        const query = btn.dataset.query;
        this.removeRecentSearch(query);
      });
    });
    
    // Clear all button
    const clearAllBtn = container.querySelector('.recent-searches-clear');
    if (clearAllBtn) {
      DOMUtils.addEventListener(clearAllBtn, 'click', () => {
        this.clearRecentSearches();
      });
    }
  }

  /**
   * Highlight search query in text
   */
  highlightQuery(text, query) {
    if (!query || !text) return text;
    
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
  }

  /**
   * Fill search input with query
   */
  fillSearchInput(query) {
    const searchInput = DOMUtils.getId('search-input');
    if (searchInput) {
      searchInput.value = query;
      this.currentQuery = query;
    }
  }

  /**
   * Clear search results
   */
  clearSearchResults() {
    const searchResults = DOMUtils.getId('search-results');
    if (searchResults) {
      searchResults.innerHTML = '';
    }
  }

  /**
   * Navigate to search results page
   */
  navigateToSearchResults(query) {
    this.saveRecentSearch(query);
    window.location.href = `pages/products.html?search=${encodeURIComponent(query)}`;
  }

  /**
   * Navigate to product page
   */
  navigateToProduct(productId) {
    window.location.href = `pages/product.html?id=${productId}`;
  }

  /**
   * Quick add product to cart
   */
  async quickAddToCart(productId) {
    try {
      if (!isAuthenticated()) {
        this.showNotification('Please log in to add items to cart', 'warning');
        return;
      }

      const response = await ApiService.cart.add({
        productId: productId,
        quantity: 1
      });

      if (response.success) {
        this.showNotification('Product added to cart!', 'success');
        // Update cart count if navigation component is available
        if (window.Navigation) {
          window.Navigation.updateCartCount();
        }
      } else {
        this.showNotification(response.message || 'Failed to add product to cart', 'error');
      }
    } catch (error) {
      console.error('Quick add to cart error:', error);
      this.showNotification('Failed to add product to cart', 'error');
    }
  }

  /**
   * Load recent searches from storage
   */
  loadRecentSearches() {
    this.recentSearches = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.RECENT_SEARCHES, []);
  }

  /**
   * Save recent search
   */
  saveRecentSearch(query) {
    if (!query || query.length < APP_CONFIG.SEARCH.MIN_QUERY_LENGTH) return;
    
    // Remove if already exists
    const index = this.recentSearches.indexOf(query);
    if (index > -1) {
      this.recentSearches.splice(index, 1);
    }
    
    // Add to beginning
    this.recentSearches.unshift(query);
    
    // Keep only last 10 searches
    if (this.recentSearches.length > 10) {
      this.recentSearches.pop();
    }
    
    // Save to storage
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.RECENT_SEARCHES, this.recentSearches);
  }

  /**
   * Remove recent search
   */
  removeRecentSearch(query) {
    const index = this.recentSearches.indexOf(query);
    if (index > -1) {
      this.recentSearches.splice(index, 1);
      CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.RECENT_SEARCHES, this.recentSearches);
      this.showRecentSearches(); // Refresh display
    }
  }

  /**
   * Clear all recent searches
   */
  clearRecentSearches() {
    this.recentSearches = [];
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.RECENT_SEARCHES, []);
    this.showSearchSuggestions(); // Show default suggestions
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
}

// Initialize search when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  const search = new Search();
  
  // Export for global access
  window.Search = search;
});

// Export class for testing
window.SearchClass = Search;