/**
 * Home page functionality for Riya Collections
 * Handles hero animations, featured products carousel, and category display
 */

class HomePage {
  constructor() {
    this.featuredProducts = [];
    this.categories = [];
    this.currentCategoryFilter = 'all';
    this.carouselPosition = 0;
    this.carouselItemWidth = 320; // Including gap
    this.isLoading = false;
    
    this.init();
  }

  /**
   * Initialize home page
   */
  async init() {
    this.setupEventListeners();
    this.initializeAnimations();
    await this.loadData();
    this.setupIntersectionObserver();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Hero scroll button
    const heroScrollBtn = DOMUtils.getElement('.hero__scroll-link');
    if (heroScrollBtn) {
      DOMUtils.addEventListener(heroScrollBtn, 'click', (e) => {
        e.preventDefault();
        DOMUtils.scrollTo('#categories', UI_CONFIG.HEADER_HEIGHT);
      });
    }

    // Featured products navigation
    const prevBtn = DOMUtils.getId('featured-prev');
    const nextBtn = DOMUtils.getId('featured-next');
    
    if (prevBtn) {
      DOMUtils.addEventListener(prevBtn, 'click', () => this.navigateCarousel('prev'));
    }
    
    if (nextBtn) {
      DOMUtils.addEventListener(nextBtn, 'click', () => this.navigateCarousel('next'));
    }

    // Featured products category tabs
    const categoryTabs = DOMUtils.getElements('.featured__tab');
    categoryTabs.forEach(tab => {
      DOMUtils.addEventListener(tab, 'click', () => {
        const category = tab.dataset.category;
        this.filterFeaturedProducts(category);
        this.updateActiveTab(tab);
      });
    });

    // Newsletter form
    const newsletterForm = DOMUtils.getId('newsletter-form');
    if (newsletterForm) {
      DOMUtils.addEventListener(newsletterForm, 'submit', (e) => {
        e.preventDefault();
        this.handleNewsletterSubmit(e);
      });
    }

    // Category cards click handlers
    DOMUtils.addEventListener(document, 'click', (e) => {
      const categoryCard = e.target.closest('.category__card');
      if (categoryCard) {
        const categoryId = categoryCard.dataset.categoryId;
        this.navigateToCategory(categoryId);
      }
    });

    // Product cards click handlers
    DOMUtils.addEventListener(document, 'click', (e) => {
      const productCard = e.target.closest('.product__card');
      if (productCard && !e.target.closest('.product__add-to-cart, .product__action-btn')) {
        const productId = productCard.dataset.productId;
        this.navigateToProduct(productId);
      }
    });

    // Add to cart buttons
    DOMUtils.addEventListener(document, 'click', (e) => {
      if (e.target.closest('.product__add-to-cart')) {
        e.preventDefault();
        e.stopPropagation();
        const productCard = e.target.closest('.product__card');
        const productId = productCard.dataset.productId;
        this.addToCart(productId);
      }
    });

    // Window resize handler
    DOMUtils.addEventListener(window, 'resize', 
      TimingUtils.throttle(() => this.handleResize(), 250)
    );
  }

  /**
   * Initialize animations
   */
  initializeAnimations() {
    // Animate hero stats counters
    const statNumbers = DOMUtils.getElements('.hero__stat-number');
    statNumbers.forEach(stat => {
      const targetValue = parseInt(stat.dataset.count);
      AnimationUtils.animateCounter(stat, 0, targetValue, 2000);
    });

    // Add reveal animation to sections
    const sections = DOMUtils.getElements('.section');
    sections.forEach(section => {
      section.classList.add('reveal');
    });
  }

  /**
   * Load initial data
   */
  async loadData() {
    try {
      this.showLoading();
      
      // Load categories and featured products in parallel
      const [categoriesResponse, productsResponse] = await Promise.all([
        this.loadCategories(),
        this.loadFeaturedProducts()
      ]);

      this.hideLoading();
      
      // Ensure loading spinners are hidden after successful load
      this.hideAllLoadingStates();
    } catch (error) {
      console.error('Error loading home page data:', error);
      this.showError('Failed to load page content. Please refresh the page.');
      this.hideLoading();
      
      // Ensure loading spinners are hidden even on error
      this.hideAllLoadingStates();
    }
  }

  /**
   * Hide all loading states
   */
  hideAllLoadingStates() {
    // Hide categories loading
    const categoriesLoading = DOMUtils.getElement('.categories__loading');
    if (categoriesLoading) {
      categoriesLoading.style.display = 'none';
    }
    
    // Hide featured products loading
    const featuredLoading = DOMUtils.getElement('.featured__loading');
    if (featuredLoading) {
      featuredLoading.style.display = 'none';
    }
    
    // Show empty states if no content was loaded
    if (this.categories.length === 0) {
      this.renderCategories();
    }
    
    if (this.featuredProducts.length === 0) {
      this.renderFeaturedProducts();
    }
  }

  /**
   * Load categories
   */
  async loadCategories() {
    try {
      const response = await ApiService.categories.getAll();
      
      if (response.success) {
        this.categories = response.data.categories;
        this.renderCategories();
      } else {
        console.error('Categories API returned error:', response.message);
        this.categories = [];
        this.renderCategories(); // This will show empty state
      }
      
      return response;
    } catch (error) {
      console.error('Error loading categories:', error);
      this.categories = [];
      this.renderCategories(); // This will show empty state
      throw error;
    }
  }

  /**
   * Load featured products
   */
  async loadFeaturedProducts() {
    try {
      const response = await ApiService.products.getAll({
        limit: 20,
        sortBy: 'created_at',
        sortOrder: 'desc'
      });
      
      if (response.success) {
        this.featuredProducts = response.data.products;
        this.renderFeaturedProducts();
        this.updateCarouselNavigation();
      } else {
        console.error('Products API returned error:', response.message);
        this.featuredProducts = [];
        this.renderFeaturedProducts(); // This will show empty state
      }
      
      return response;
    } catch (error) {
      console.error('Error loading featured products:', error);
      this.featuredProducts = [];
      this.renderFeaturedProducts(); // This will show empty state
      throw error;
    }
  }

  /**
   * Render categories
   */
  renderCategories() {
    const container = DOMUtils.getId('categories-container');
    if (!container) return;

    if (this.categories.length === 0) {
      container.innerHTML = `
        <div class="categories__empty brand-card">
          <div class="brand-pattern">
            <i class="ri-shopping-bag-line brand-icon brand-icon--large"></i>
            <h3 class="brand-title">No Categories Available</h3>
            <p class="brand-text">Categories will appear here once they are added.</p>
          </div>
        </div>
      `;
      return;
    }

    // Use enhanced category display component
    if (window.categoryDisplay) {
      window.categoryDisplay.renderCategories(this.categories, container);
    } else {
      // Fallback to basic rendering
      this.renderCategoriesBasic(container);
    }
  }

  /**
   * Basic category rendering fallback
   */
  renderCategoriesBasic(container) {
    const categoriesHTML = this.categories.map((category, index) => `
      <div class="category__card brand-card category-branded" 
           data-category-id="${category.id}"
           style="animation-delay: ${index * 0.1}s">
        <div class="category__image lazy-image-container">
          <img data-lazy-src="${category.imageUrl || `assets/categories/${category.name.replace(/\s+/g, '_')}.png`}" 
               alt="${category.name}" 
               class="category__img lazy-loading responsive-image"
               data-width="400"
               data-height="300">
          <div class="category__overlay brand-overlay"></div>
        </div>
        <div class="category__content">
          <h3 class="category__title brand-title">${category.name}</h3>
          <p class="category__description brand-text">${category.description || 'Discover amazing products'}</p>
          <p class="category__count brand-accent-text">${category.productCount || 0} products</p>
          <a href="#" class="category__cta btn--brand-outline">
            Explore Now <i class="ri-arrow-right-line brand-icon"></i>
          </a>
        </div>
      </div>
    `).join('');

    container.innerHTML = categoriesHTML;

    // Add animation delay to each category card
    const categoryCards = container.querySelectorAll('.category__card');
    categoryCards.forEach((card, index) => {
      card.style.animationDelay = `${index * 0.1}s`;
      card.classList.add('animate-fade-in-up');
    });
    
    // Refresh lazy loading for new images
    if (window.lazyLoader) {
      window.lazyLoader.refresh();
    }
  }

  /**
   * Render featured products
   */
  renderFeaturedProducts() {
    const carousel = DOMUtils.getId('featured-carousel');
    if (!carousel) return;

    const filteredProducts = this.getFilteredProducts();

    if (filteredProducts.length === 0) {
      carousel.innerHTML = `
        <div class="featured__empty">
          <p>No products available in this category.</p>
        </div>
      `;
      return;
    }

    const productsHTML = filteredProducts.map(product => `
      <div class="product__card" data-product-id="${product.id}">
        <div class="product__image lazy-image-container">
          <img data-lazy-src="${product.primaryImage || 'assets/placeholder.jpg'}" 
               alt="${product.name}" 
               class="product__img lazy-loading"
               data-width="300"
               data-height="300">
          ${product.isNew ? '<span class="product__badge product__badge--new">New</span>' : ''}
          ${product.isOnSale ? '<span class="product__badge product__badge--sale">Sale</span>' : ''}
          <div class="product__actions">
            <button class="product__action-btn" title="Add to Wishlist">
              <i class="ri-heart-line"></i>
            </button>
            <button class="product__action-btn" title="Quick View">
              <i class="ri-eye-line"></i>
            </button>
            <button class="product__action-btn" title="Compare">
              <i class="ri-scales-line"></i>
            </button>
          </div>
        </div>
        <div class="product__content">
          <p class="product__category">${product.category?.name || 'Uncategorized'}</p>
          <h3 class="product__title">${product.name}</h3>
          <p class="product__brand">${product.brand || ''}</p>
          <div class="product__price">
            <span class="product__price-current">${FormatUtils.currency(product.price)}</span>
            ${product.originalPrice ? `<span class="product__price-original">${FormatUtils.currency(product.originalPrice)}</span>` : ''}
          </div>
          <div class="product__rating">
            <div class="product__stars">
              ${this.generateStars(product.rating || 4.5)}
            </div>
            <span class="product__rating-count">(${product.reviewCount || 0})</span>
          </div>
          <button class="product__add-to-cart">
            <i class="ri-shopping-cart-line"></i>
            Add to Cart
          </button>
        </div>
      </div>
    `).join('');

    carousel.innerHTML = productsHTML;
    this.resetCarouselPosition();
    
    // Refresh lazy loading for new images
    if (window.lazyLoader) {
      window.lazyLoader.refresh();
    }
  }

  /**
   * Get filtered products based on current category
   */
  getFilteredProducts() {
    if (this.currentCategoryFilter === 'all') {
      return this.featuredProducts;
    }
    
    return this.featuredProducts.filter(product => 
      product.category?.id == this.currentCategoryFilter
    );
  }

  /**
   * Generate star rating HTML
   */
  generateStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    
    let starsHTML = '';
    
    // Full stars
    for (let i = 0; i < fullStars; i++) {
      starsHTML += '<i class="ri-star-fill product__star"></i>';
    }
    
    // Half star
    if (hasHalfStar) {
      starsHTML += '<i class="ri-star-half-line product__star"></i>';
    }
    
    // Empty stars
    for (let i = 0; i < emptyStars; i++) {
      starsHTML += '<i class="ri-star-line product__star product__star--empty"></i>';
    }
    
    return starsHTML;
  }

  /**
   * Filter featured products by category
   */
  filterFeaturedProducts(category) {
    this.currentCategoryFilter = category;
    this.renderFeaturedProducts();
    this.updateCarouselNavigation();
  }

  /**
   * Update active tab
   */
  updateActiveTab(activeTab) {
    const tabs = DOMUtils.getElements('.featured__tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    activeTab.classList.add('active');
  }

  /**
   * Navigate carousel
   */
  navigateCarousel(direction) {
    const carousel = DOMUtils.getId('featured-carousel');
    const container = DOMUtils.getElement('.featured__container');
    
    if (!carousel || !container) return;

    const containerWidth = container.offsetWidth;
    const totalWidth = carousel.scrollWidth;
    const maxPosition = totalWidth - containerWidth;

    if (direction === 'next') {
      this.carouselPosition = Math.min(this.carouselPosition + this.carouselItemWidth, maxPosition);
    } else {
      this.carouselPosition = Math.max(this.carouselPosition - this.carouselItemWidth, 0);
    }

    carousel.style.transform = `translateX(-${this.carouselPosition}px)`;
    this.updateCarouselNavigation();
  }

  /**
   * Reset carousel position
   */
  resetCarouselPosition() {
    this.carouselPosition = 0;
    const carousel = DOMUtils.getId('featured-carousel');
    if (carousel) {
      carousel.style.transform = 'translateX(0)';
    }
  }

  /**
   * Update carousel navigation buttons
   */
  updateCarouselNavigation() {
    const prevBtn = DOMUtils.getId('featured-prev');
    const nextBtn = DOMUtils.getId('featured-next');
    const carousel = DOMUtils.getId('featured-carousel');
    const container = DOMUtils.getElement('.featured__container');

    if (!prevBtn || !nextBtn || !carousel || !container) return;

    const containerWidth = container.offsetWidth;
    const totalWidth = carousel.scrollWidth;
    const maxPosition = totalWidth - containerWidth;

    prevBtn.disabled = this.carouselPosition <= 0;
    nextBtn.disabled = this.carouselPosition >= maxPosition || totalWidth <= containerWidth;
  }

  /**
   * Handle window resize
   */
  handleResize() {
    this.updateCarouselNavigation();
    
    // Update carousel item width based on screen size
    const breakpoint = CONFIG_UTILS.getCurrentBreakpoint();
    switch (breakpoint) {
      case 'mobile':
        this.carouselItemWidth = 300;
        break;
      case 'tablet':
        this.carouselItemWidth = 320;
        break;
      default:
        this.carouselItemWidth = 340;
    }
  }

  /**
   * Navigate to category page
   */
  navigateToCategory(categoryId) {
    window.location.href = `pages/products.html?category=${categoryId}`;
  }

  /**
   * Navigate to product page
   */
  navigateToProduct(productId) {
    window.location.href = `pages/product.html?id=${productId}`;
  }

  /**
   * Add product to cart
   */
  async addToCart(productId) {
    try {
      const product = this.featuredProducts.find(p => p.id == productId);
      if (!product) return;

      // Check if user is authenticated
      if (!isAuthenticated()) {
        this.showNotification('Please log in to add items to cart', 'warning');
        setTimeout(() => {
          window.location.href = 'pages/login.html';
        }, 1500);
        return;
      }

      // Check stock availability
      if (product.stockQuantity <= 0) {
        this.showNotification('This product is out of stock', 'error');
        return;
      }

      this.showLoading();

      const response = await ApiService.cart.add({
        productId: productId,
        quantity: 1
      });

      this.hideLoading();

      if (response.success) {
        this.showNotification(SUCCESS_MESSAGES.PRODUCT_ADDED_TO_CART, 'success');
        this.updateCartCount();
      } else {
        this.showNotification(response.message || 'Failed to add product to cart', 'error');
      }
    } catch (error) {
      this.hideLoading();
      console.error('Error adding to cart:', error);
      this.showNotification(error.message || 'Failed to add product to cart', 'error');
    }
  }

  /**
   * Handle newsletter subscription
   */
  async handleNewsletterSubmit(event) {
    const form = event.target;
    const emailInput = form.querySelector('.newsletter__input');
    const email = emailInput.value.trim();

    if (!ValidationUtils.email(email)) {
      this.showNotification('Please enter a valid email address', 'error');
      return;
    }

    try {
      // Simulate newsletter subscription (replace with actual API call)
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      this.showNotification(SUCCESS_MESSAGES.NEWSLETTER_SUBSCRIBED, 'success');
      form.reset();
    } catch (error) {
      console.error('Newsletter subscription error:', error);
      this.showNotification('Failed to subscribe. Please try again.', 'error');
    }
  }

  /**
   * Update cart count in navigation
   */
  updateCartCount() {
    // This will be implemented when cart functionality is added
    const cartCount = DOMUtils.getId('cart-count');
    if (cartCount) {
      // Get cart count from local storage or API
      const count = 0; // Placeholder
      cartCount.textContent = count;
    }
  }

  /**
   * Setup intersection observer for scroll animations
   */
  setupIntersectionObserver() {
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('active');
        }
      });
    }, observerOptions);

    // Observe all reveal elements
    const revealElements = DOMUtils.getElements('.reveal');
    revealElements.forEach(element => {
      observer.observe(element);
    });
  }

  /**
   * Show loading overlay
   */
  showLoading() {
    const loadingOverlay = DOMUtils.getId('loading-overlay');
    if (loadingOverlay) {
      loadingOverlay.classList.add('show');
    }
  }

  /**
   * Hide loading overlay
   */
  hideLoading() {
    const loadingOverlay = DOMUtils.getId('loading-overlay');
    if (loadingOverlay) {
      loadingOverlay.classList.remove('show');
    }
  }

  /**
   * Show notification
   */
  showNotification(message, type = 'info') {
    // Create notification element
    const notification = DOMUtils.createElement('div', {
      className: `notification notification--${type}`,
      innerHTML: `
        <div class="notification__content">
          <span class="notification__message">${message}</span>
          <button class="notification__close">
            <i class="ri-close-line"></i>
          </button>
        </div>
      `
    });

    // Add to page
    document.body.appendChild(notification);

    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);

    // Auto hide
    const duration = APP_CONFIG.NOTIFICATIONS.DURATION[type.toUpperCase()] || 3000;
    setTimeout(() => this.hideNotification(notification), duration);

    // Close button handler
    const closeBtn = notification.querySelector('.notification__close');
    DOMUtils.addEventListener(closeBtn, 'click', () => {
      this.hideNotification(notification);
    });
  }

  /**
   * Hide notification
   */
  hideNotification(notification) {
    notification.classList.remove('show');
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }

  /**
   * Show error message
   */
  showError(message) {
    this.showNotification(message, 'error');
  }
}

// Initialize home page when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  new HomePage();
});

// Export for testing
window.HomePage = HomePage;