/**
 * Navigation component for Riya Collections
 * Handles mobile menu, user menu, and navigation interactions
 */

class Navigation {
  constructor() {
    this.isMenuOpen = false;
    this.isUserMenuOpen = false;
    this.init();
  }

  /**
   * Initialize navigation
   */
  init() {
    this.setupEventListeners();
    this.updateActiveLink();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Mobile menu toggle
    const menuToggle = DOMUtils.getId('nav-toggle');
    const menuClose = DOMUtils.getId('nav-close');
    const navMenu = DOMUtils.getId('nav-menu');

    if (menuToggle) {
      DOMUtils.addEventListener(menuToggle, 'click', () => {
        this.toggleMobileMenu();
      });
    }

    if (menuClose) {
      DOMUtils.addEventListener(menuClose, 'click', () => {
        this.closeMobileMenu();
      });
    }

    // Close menu when clicking outside
    if (navMenu) {
      DOMUtils.addEventListener(document, 'click', (e) => {
        if (this.isMenuOpen && !navMenu.contains(e.target) && !menuToggle.contains(e.target)) {
          this.closeMobileMenu();
        }
      });
    }

    // User menu toggle
    const userBtn = DOMUtils.getId('user-btn');
    const userMenu = DOMUtils.getId('user-menu');

    if (userBtn && userMenu) {
      DOMUtils.addEventListener(userBtn, 'click', (e) => {
        e.stopPropagation();
        this.toggleUserMenu();
      });

      // Close user menu when clicking outside
      DOMUtils.addEventListener(document, 'click', (e) => {
        if (this.isUserMenuOpen && !userMenu.contains(e.target) && !userBtn.contains(e.target)) {
          this.closeUserMenu();
        }
      });
    }

    // Navigation links
    const navLinks = DOMUtils.getElements('.nav__link');
    navLinks.forEach(link => {
      DOMUtils.addEventListener(link, 'click', (e) => {
        // Close mobile menu when link is clicked
        if (this.isMenuOpen) {
          this.closeMobileMenu();
        }

        // Handle smooth scrolling for anchor links
        const href = link.getAttribute('href');
        if (href && href.startsWith('#')) {
          e.preventDefault();
          this.scrollToSection(href);
        }
      });
    });

    // Handle window resize
    DOMUtils.addEventListener(window, 'resize', 
      TimingUtils.throttle(() => this.handleResize(), 250)
    );

    // Handle scroll for header effects
    DOMUtils.addEventListener(window, 'scroll', 
      TimingUtils.throttle(() => this.handleScroll(), 100)
    );
  }

  /**
   * Toggle mobile menu
   */
  toggleMobileMenu() {
    const navMenu = DOMUtils.getId('nav-menu');
    if (!navMenu) return;

    this.isMenuOpen = !this.isMenuOpen;
    
    if (this.isMenuOpen) {
      navMenu.classList.add('show-menu');
      document.body.classList.add('menu-open');
      
      // Focus first menu item for accessibility
      const firstLink = navMenu.querySelector('.nav__link');
      if (firstLink) {
        firstLink.focus();
      }
    } else {
      navMenu.classList.remove('show-menu');
      document.body.classList.remove('menu-open');
    }
  }

  /**
   * Close mobile menu
   */
  closeMobileMenu() {
    const navMenu = DOMUtils.getId('nav-menu');
    if (!navMenu) return;

    this.isMenuOpen = false;
    navMenu.classList.remove('show-menu');
    document.body.classList.remove('menu-open');
  }

  /**
   * Toggle user menu
   */
  toggleUserMenu() {
    const userMenu = DOMUtils.getId('user-menu');
    if (!userMenu) return;

    this.isUserMenuOpen = !this.isUserMenuOpen;
    
    if (this.isUserMenuOpen) {
      userMenu.classList.add('show');
    } else {
      userMenu.classList.remove('show');
    }
  }

  /**
   * Close user menu
   */
  closeUserMenu() {
    const userMenu = DOMUtils.getId('user-menu');
    if (!userMenu) return;

    this.isUserMenuOpen = false;
    userMenu.classList.remove('show');
  }

  /**
   * Update active navigation link
   */
  updateActiveLink() {
    const currentPath = window.location.pathname;
    const navLinks = DOMUtils.getElements('.nav__link');

    navLinks.forEach(link => {
      link.classList.remove('active-link');
      
      const href = link.getAttribute('href');
      if (href) {
        // Handle exact matches
        if (href === currentPath) {
          link.classList.add('active-link');
        }
        // Handle home page
        else if (currentPath === '/' && (href === '/' || href === 'index.html' || href === '#home')) {
          link.classList.add('active-link');
        }
        // Handle page matches
        else if (href.includes('.html') && currentPath.includes(href.replace('.html', ''))) {
          link.classList.add('active-link');
        }
      }
    });
  }

  /**
   * Scroll to section
   */
  scrollToSection(sectionId) {
    const target = DOMUtils.getElement(sectionId);
    if (target) {
      DOMUtils.scrollTo(target, UI_CONFIG.HEADER_HEIGHT);
      
      // Update active link
      const navLinks = DOMUtils.getElements('.nav__link');
      navLinks.forEach(link => {
        link.classList.remove('active-link');
        if (link.getAttribute('href') === sectionId) {
          link.classList.add('active-link');
        }
      });
    }
  }

  /**
   * Handle window resize
   */
  handleResize() {
    const breakpoint = CONFIG_UTILS.getCurrentBreakpoint();
    
    // Close mobile menu on larger screens
    if ((breakpoint === 'tablet' || breakpoint === 'desktop' || breakpoint === 'large') && this.isMenuOpen) {
      this.closeMobileMenu();
    }
  }

  /**
   * Handle scroll events
   */
  handleScroll() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const header = DOMUtils.getElement('.header');
    
    if (!header) return;

    // Add scrolled class for styling
    if (scrollTop > 50) {
      header.classList.add('header--scrolled');
    } else {
      header.classList.remove('header--scrolled');
    }

    // Update active link based on scroll position
    this.updateActiveLinkOnScroll(scrollTop);
  }

  /**
   * Update active link based on scroll position
   */
  updateActiveLinkOnScroll(scrollTop) {
    const sections = DOMUtils.getElements('section[id]');
    const navLinks = DOMUtils.getElements('.nav__link[href^="#"]');
    
    let currentSection = '';

    sections.forEach(section => {
      const sectionTop = section.offsetTop - UI_CONFIG.HEADER_HEIGHT - 50;
      const sectionHeight = section.offsetHeight;
      
      if (scrollTop >= sectionTop && scrollTop < sectionTop + sectionHeight) {
        currentSection = `#${section.id}`;
      }
    });

    // Update active link
    navLinks.forEach(link => {
      link.classList.remove('active-link');
      if (link.getAttribute('href') === currentSection) {
        link.classList.add('active-link');
      }
    });
  }

  /**
   * Update cart count
   */
  updateCartCount(count = 0) {
    const cartCount = DOMUtils.getId('cart-count');
    if (cartCount) {
      cartCount.textContent = count;
      
      // Add animation for count change
      cartCount.classList.add('updated');
      setTimeout(() => {
        cartCount.classList.remove('updated');
      }, 300);
    }
  }

  /**
   * Show cart notification
   */
  showCartNotification(message) {
    const cartIcon = DOMUtils.getElement('.nav__cart');
    if (!cartIcon) return;

    // Create notification bubble
    const notification = DOMUtils.createElement('div', {
      className: 'cart-notification',
      innerHTML: message
    });

    cartIcon.appendChild(notification);

    // Show and hide notification
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 2000);
  }

  /**
   * Update user info in navigation
   */
  updateUserInfo(userData) {
    const userBtn = DOMUtils.getId('user-btn');
    if (!userBtn || !userData) return;

    // Update user button with avatar or initials
    const userInitials = `${userData.firstName?.[0] || ''}${userData.lastName?.[0] || ''}`.toUpperCase();
    
    if (userData.avatar) {
      userBtn.innerHTML = `<img src="${userData.avatar}" alt="${userData.firstName}" class="nav__user-avatar">`;
    } else {
      userBtn.innerHTML = `<span class="nav__user-initials">${userInitials}</span>`;
    }
  }

  /**
   * Add search functionality
   */
  initializeSearch() {
    const searchBtn = DOMUtils.getId('search-btn');
    const searchOverlay = DOMUtils.getId('search-overlay');
    const searchClose = DOMUtils.getId('search-close');
    const searchForm = DOMUtils.getId('search-form');
    const searchInput = DOMUtils.getId('search-input');

    if (searchBtn && searchOverlay) {
      // Open search
      DOMUtils.addEventListener(searchBtn, 'click', () => {
        searchOverlay.classList.add('show');
        if (searchInput) {
          searchInput.focus();
        }
      });

      // Close search
      if (searchClose) {
        DOMUtils.addEventListener(searchClose, 'click', () => {
          searchOverlay.classList.remove('show');
        });
      }

      // Close on overlay click
      DOMUtils.addEventListener(searchOverlay, 'click', (e) => {
        if (e.target === searchOverlay) {
          searchOverlay.classList.remove('show');
        }
      });

      // Handle search form
      if (searchForm) {
        DOMUtils.addEventListener(searchForm, 'submit', (e) => {
          e.preventDefault();
          this.handleSearch(searchInput.value.trim());
        });
      }

      // Handle search input
      if (searchInput) {
        DOMUtils.addEventListener(searchInput, 'input', 
          TimingUtils.debounce((e) => {
            this.handleSearchInput(e.target.value.trim());
          }, APP_CONFIG.SEARCH.DEBOUNCE_DELAY)
        );
      }
    }
  }

  /**
   * Handle search submission
   */
  handleSearch(query) {
    if (query.length < APP_CONFIG.SEARCH.MIN_QUERY_LENGTH) {
      return;
    }

    // Save to recent searches
    this.saveRecentSearch(query);

    // Navigate to search results
    window.location.href = `pages/products.html?search=${encodeURIComponent(query)}`;
  }

  /**
   * Handle search input for suggestions
   */
  async handleSearchInput(query) {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;

    if (query.length < APP_CONFIG.SEARCH.MIN_QUERY_LENGTH) {
      searchResults.innerHTML = '';
      return;
    }

    try {
      // Show loading
      searchResults.innerHTML = '<div class="search-loading">Searching...</div>';

      // Search products
      const response = await ApiService.products.search(query, {
        limit: APP_CONFIG.SEARCH.MAX_SUGGESTIONS
      });

      if (response.success && response.data.products.length > 0) {
        this.renderSearchSuggestions(response.data.products, query);
      } else {
        searchResults.innerHTML = '<div class="search-no-results">No products found</div>';
      }
    } catch (error) {
      console.error('Search error:', error);
      searchResults.innerHTML = '<div class="search-error">Search failed. Please try again.</div>';
    }
  }

  /**
   * Render search suggestions
   */
  renderSearchSuggestions(products, query) {
    const searchResults = DOMUtils.getId('search-results');
    if (!searchResults) return;

    const suggestionsHTML = products.map(product => `
      <div class="search-suggestion" data-product-id="${product.id}">
        <img src="${product.primaryImage || 'assets/placeholder.jpg'}" 
             alt="${product.name}" 
             class="search-suggestion__image">
        <div class="search-suggestion__content">
          <h4 class="search-suggestion__title">${this.highlightQuery(product.name, query)}</h4>
          <p class="search-suggestion__brand">${product.brand || ''}</p>
          <p class="search-suggestion__price">${FormatUtils.currency(product.price)}</p>
        </div>
      </div>
    `).join('');

    searchResults.innerHTML = `
      <div class="search-suggestions">
        ${suggestionsHTML}
        <div class="search-view-all">
          <a href="pages/products.html?search=${encodeURIComponent(query)}" class="search-view-all-link">
            View all results for "${query}"
          </a>
        </div>
      </div>
    `;

    // Add click handlers for suggestions
    const suggestions = searchResults.querySelectorAll('.search-suggestion');
    suggestions.forEach(suggestion => {
      DOMUtils.addEventListener(suggestion, 'click', () => {
        const productId = suggestion.dataset.productId;
        window.location.href = `pages/product.html?id=${productId}`;
      });
    });
  }

  /**
   * Highlight search query in text
   */
  highlightQuery(text, query) {
    if (!query) return text;
    
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
  }

  /**
   * Save recent search
   */
  saveRecentSearch(query) {
    const recentSearches = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.RECENT_SEARCHES, []);
    
    // Remove if already exists
    const index = recentSearches.indexOf(query);
    if (index > -1) {
      recentSearches.splice(index, 1);
    }
    
    // Add to beginning
    recentSearches.unshift(query);
    
    // Keep only last 10 searches
    if (recentSearches.length > 10) {
      recentSearches.pop();
    }
    
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.RECENT_SEARCHES, recentSearches);
  }
}

// Initialize navigation when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  const navigation = new Navigation();
  navigation.initializeSearch();
  
  // Export for global access
  window.Navigation = navigation;
});

// Export class for testing
window.NavigationClass = Navigation;