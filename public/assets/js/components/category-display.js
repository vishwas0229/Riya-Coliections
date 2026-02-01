/**
 * Enhanced Category Display Component
 * 
 * Handles optimized category image display with consistent branding
 * Requirements: 11.2, 11.4 - Category images and branding consistency
 */

class CategoryDisplay {
  constructor() {
    this.categories = [];
    this.imageFormats = ['webp', 'jpg'];
    this.imageSizes = ['card', 'thumbnail', 'banner'];
    this.loadingStates = new Map();
    
    this.init();
  }

  /**
   * Initialize category display
   */
  init() {
    this.setupImageSupport();
    this.setupLazyLoading();
  }

  /**
   * Setup image format support detection
   */
  setupImageSupport() {
    // Detect WebP support
    this.supportsWebP = this.checkWebPSupport();
    
    // Add class to document for CSS targeting
    document.documentElement.classList.add(
      this.supportsWebP ? 'webp' : 'no-webp'
    );
  }

  /**
   * Check WebP support
   */
  checkWebPSupport() {
    try {
      // Check if we're in a test environment
      if (typeof process !== 'undefined' && process.env && process.env.NODE_ENV === 'test') {
        return false; // Default to false in test environment
      }
      
      const canvas = document.createElement('canvas');
      canvas.width = 1;
      canvas.height = 1;
      
      const dataURL = canvas.toDataURL('image/webp');
      return dataURL && dataURL.indexOf('data:image/webp') === 0;
    } catch (error) {
      // Fallback for test environments or unsupported browsers
      return false;
    }
  }

  /**
   * Setup lazy loading for category images
   */
  setupLazyLoading() {
    if ('IntersectionObserver' in window) {
      this.lazyImageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            this.loadCategoryImage(entry.target);
            this.lazyImageObserver.unobserve(entry.target);
          }
        });
      }, {
        rootMargin: '50px 0px',
        threshold: 0.1
      });
    }
  }

  /**
   * Render categories with optimized images and branding
   */
  renderCategories(categories, container) {
    if (!container) return;

    this.categories = categories;

    if (categories.length === 0) {
      container.innerHTML = this.renderEmptyState();
      return;
    }

    const categoriesHTML = categories.map((category, index) => 
      this.renderCategoryCard(category, index)
    ).join('');

    container.innerHTML = categoriesHTML;

    // Setup lazy loading for new images
    this.setupCategoryImageLazyLoading(container);
    
    // Add staggered animation
    this.addStaggeredAnimation(container);
  }

  /**
   * Render individual category card
   */
  renderCategoryCard(category, index) {
    const categoryClass = this.getCategoryBrandingClass(category.name);
    const imageUrl = this.getOptimizedImageUrl(category, 'card');
    const fallbackUrl = this.getFallbackImageUrl(category);

    return `
      <div class="category__card brand-card category-branded ${categoryClass}" 
           data-category-id="${category.id}"
           data-category-name="${category.name.toLowerCase().replace(/\s+/g, '-')}"
           style="animation-delay: ${index * 0.1}s">
        
        <div class="category__image lazy-image-container">
          ${this.renderResponsiveImage(category, 'card')}
          <div class="category__overlay brand-overlay"></div>
          
          <!-- Category Badge -->
          <div class="category__badge-container">
            <span class="category-badge ${categoryClass}">
              ${category.name}
            </span>
          </div>
        </div>
        
        <div class="category__content">
          <h3 class="category__title brand-title">${category.name}</h3>
          <p class="category__description brand-text">
            ${category.description || this.getDefaultDescription(category.name)}
          </p>
          <div class="category__meta">
            <span class="category__count brand-accent-text">
              ${category.productCount || 0} products
            </span>
            <span class="category__trending ${this.isTrending(category) ? 'visible' : 'hidden'}">
              <i class="ri-fire-line brand-icon"></i>
              Trending
            </span>
          </div>
          <a href="#" class="category__cta btn--brand-outline">
            Explore Collection
            <i class="ri-arrow-right-line brand-icon"></i>
          </a>
        </div>
        
        <!-- Brand Pattern Overlay -->
        <div class="brand-pattern"></div>
      </div>
    `;
  }

  /**
   * Render responsive image with multiple formats and sizes
   */
  renderResponsiveImage(category, size = 'card') {
    const imageName = category.name.toLowerCase().replace(/\s+/g, '_');
    const categoryClass = this.getCategoryBrandingClass(category.name);
    
    return `
      <picture class="category__picture">
        ${this.supportsWebP ? `
          <source 
            data-lazy-srcset="${this.getImagePath(imageName, size, 'webp')}"
            type="image/webp">
        ` : ''}
        <img 
          data-lazy-src="${this.getImagePath(imageName, size, 'jpg')}"
          alt="${category.name} - Premium ${category.name.toLowerCase()} products"
          class="category__img responsive-image lazy-loading ${categoryClass}"
          data-category="${category.name.toLowerCase()}"
          loading="lazy"
          width="${this.getImageDimensions(size).width}"
          height="${this.getImageDimensions(size).height}">
      </picture>
    `;
  }

  /**
   * Get optimized image URL
   */
  getOptimizedImageUrl(category, size = 'card') {
    const imageName = category.name.toLowerCase().replace(/\s+/g, '_');
    const format = this.supportsWebP ? 'webp' : 'jpg';
    
    return this.getImagePath(imageName, size, format);
  }

  /**
   * Get image path for specific size and format
   */
  getImagePath(imageName, size, format) {
    // Check if optimized images are available
    if (this.hasOptimizedImages()) {
      return `/api/images/categories/${size}/${imageName}.${format}`;
    }
    
    // Fallback to original assets
    return `assets/categories/${imageName}.png`;
  }

  /**
   * Check if optimized images are available
   */
  hasOptimizedImages() {
    // This could be enhanced to actually check if the optimized images exist
    return true; // Assume they exist after running the optimization script
  }

  /**
   * Get fallback image URL
   */
  getFallbackImageUrl(category) {
    return `assets/categories/${category.filename || 'default.png'}`;
  }

  /**
   * Get image dimensions for size
   */
  getImageDimensions(size) {
    const dimensions = {
      hero: { width: 1920, height: 800 },
      banner: { width: 1200, height: 400 },
      card: { width: 400, height: 300 },
      thumbnail: { width: 150, height: 150 }
    };
    
    return dimensions[size] || dimensions.card;
  }

  /**
   * Get category branding class
   */
  getCategoryBrandingClass(categoryName) {
    const classMap = {
      'Face Makeup': 'category--face-makeup',
      'Hair Care': 'category--hair-care',
      'Lip Care': 'category--lip-care',
      'Skin Care': 'category--skin-care'
    };
    
    return classMap[categoryName] || 'category--default';
  }

  /**
   * Get default description for category
   */
  getDefaultDescription(categoryName) {
    const descriptions = {
      'Face Makeup': 'Premium face makeup products for a flawless, radiant look',
      'Hair Care': 'Nourishing hair care solutions for healthy, beautiful hair',
      'Lip Care': 'Beautiful lip care and color products for perfect lips',
      'Skin Care': 'Advanced skincare solutions for radiant, healthy skin'
    };
    
    return descriptions[categoryName] || 'Discover amazing beauty products';
  }

  /**
   * Check if category is trending
   */
  isTrending(category) {
    // This could be based on actual data
    return category.productCount > 20 || category.name === 'Face Makeup';
  }

  /**
   * Setup lazy loading for category images
   */
  setupCategoryImageLazyLoading(container) {
    if (!this.lazyImageObserver) return;

    const lazyImages = container.querySelectorAll('.lazy-loading');
    lazyImages.forEach(img => {
      this.lazyImageObserver.observe(img);
    });
  }

  /**
   * Load category image with error handling
   */
  loadCategoryImage(img) {
    const container = img.closest('.lazy-image-container');
    const picture = img.closest('picture');
    
    if (container) {
      container.classList.add('loading');
    }

    // Load WebP source if available
    const webpSource = picture?.querySelector('source[type="image/webp"]');
    if (webpSource && webpSource.dataset.lazySrcset) {
      webpSource.srcset = webpSource.dataset.lazySrcset;
    }

    // Load main image
    const originalSrc = img.dataset.lazySrc;
    if (originalSrc) {
      img.onload = () => {
        img.classList.remove('lazy-loading');
        img.classList.add('lazy-loaded');
        if (container) {
          container.classList.remove('loading');
        }
      };

      img.onerror = () => {
        this.handleImageError(img);
        if (container) {
          container.classList.remove('loading');
        }
      };

      img.src = originalSrc;
    }
  }

  /**
   * Handle image loading errors
   */
  handleImageError(img) {
    img.classList.remove('lazy-loading');
    img.classList.add('lazy-error');
    
    // Try fallback image
    const category = this.categories.find(cat => 
      cat.name.toLowerCase() === img.dataset.category
    );
    
    if (category) {
      const fallbackUrl = this.getFallbackImageUrl(category);
      if (img.src !== fallbackUrl) {
        img.src = fallbackUrl;
        return;
      }
    }
    
    // Show placeholder
    this.showImagePlaceholder(img);
  }

  /**
   * Show image placeholder
   */
  showImagePlaceholder(img) {
    const container = img.closest('.category__image');
    if (!container) return;

    const categoryName = img.dataset.category || 'Category';
    const placeholder = document.createElement('div');
    placeholder.className = 'image-placeholder brand-bg--light';
    placeholder.innerHTML = `
      <i class="ri-image-line brand-icon brand-icon--large"></i>
      <span>${categoryName}</span>
    `;

    container.appendChild(placeholder);
    img.style.display = 'none';
  }

  /**
   * Add staggered animation to category cards
   */
  addStaggeredAnimation(container) {
    const cards = container.querySelectorAll('.category__card');
    
    cards.forEach((card, index) => {
      card.style.setProperty('--animation-delay', `${index * 0.1}s`);
      card.classList.add('animate-fade-in-up');
      
      // Add intersection observer for scroll animations
      if ('IntersectionObserver' in window) {
        const animationObserver = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              entry.target.classList.add('reveal', 'active');
              animationObserver.unobserve(entry.target);
            }
          });
        }, {
          threshold: 0.1,
          rootMargin: '0px 0px -50px 0px'
        });
        
        animationObserver.observe(card);
      }
    });
  }

  /**
   * Render empty state
   */
  renderEmptyState() {
    return `
      <div class="categories__empty brand-card">
        <div class="brand-pattern">
          <i class="ri-shopping-bag-line brand-icon brand-icon--large"></i>
          <h3 class="brand-title">No Categories Available</h3>
          <p class="brand-text">Categories will appear here once they are added.</p>
          <button class="btn--brand" onclick="window.location.reload()">
            <i class="ri-refresh-line"></i>
            Refresh Page
          </button>
        </div>
      </div>
    `;
  }

  /**
   * Update category display with new data
   */
  updateCategories(categories) {
    this.categories = categories;
    
    // Find container and re-render
    const container = document.getElementById('categories-container');
    if (container) {
      this.renderCategories(categories, container);
    }
  }

  /**
   * Preload category images for better performance
   */
  preloadCategoryImages(categories) {
    categories.forEach(category => {
      const imageUrl = this.getOptimizedImageUrl(category, 'card');
      const img = new Image();
      img.src = imageUrl;
    });
  }

  /**
   * Get category performance metrics
   */
  getPerformanceMetrics() {
    return {
      totalCategories: this.categories.length,
      loadedImages: document.querySelectorAll('.category__img.lazy-loaded').length,
      failedImages: document.querySelectorAll('.category__img.lazy-error').length,
      supportsWebP: this.supportsWebP,
      loadingStates: Object.fromEntries(this.loadingStates)
    };
  }
}

// Export for use in other modules
window.CategoryDisplay = CategoryDisplay;

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.categoryDisplay = new CategoryDisplay();
  });
} else {
  window.categoryDisplay = new CategoryDisplay();
}