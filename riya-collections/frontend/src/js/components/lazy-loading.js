/**
 * Lazy Loading Component for Riya Collections
 * 
 * Implements lazy loading for product images to improve performance
 * Requirements: 21.1 - Implement lazy loading for better performance
 */

class LazyLoadingManager {
  constructor(options = {}) {
    this.options = {
      // Intersection Observer options
      rootMargin: '50px 0px',
      threshold: 0.01,
      
      // Image loading options
      fadeInDuration: 300,
      retryAttempts: 3,
      retryDelay: 1000,
      
      // Placeholder options
      showPlaceholder: true,
      placeholderColor: '#f5f5f5',
      
      // Progressive loading
      enableProgressiveLoading: true,
      lowQualityFirst: true,
      
      // WebP support detection
      enableWebP: true,
      
      ...options
    };
    
    this.observer = null;
    this.loadedImages = new Set();
    this.failedImages = new Set();
    this.retryCount = new Map();
    this.supportsWebP = false;
    
    this.init();
  }

  /**
   * Initialize lazy loading
   */
  async init() {
    // Check WebP support
    if (this.options.enableWebP) {
      this.supportsWebP = await this.checkWebPSupport();
    }
    
    // Initialize Intersection Observer
    this.initIntersectionObserver();
    
    // Scan for existing images
    this.scanForImages();
    
    // Listen for dynamic content
    this.setupMutationObserver();
    
    console.log('🖼️ Lazy loading initialized', {
      webpSupport: this.supportsWebP,
      rootMargin: this.options.rootMargin
    });
  }

  /**
   * Initialize Intersection Observer
   */
  initIntersectionObserver() {
    if (!('IntersectionObserver' in window)) {
      console.warn('IntersectionObserver not supported, falling back to immediate loading');
      this.loadAllImages();
      return;
    }

    this.observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          this.loadImage(entry.target);
          this.observer.unobserve(entry.target);
        }
      });
    }, {
      rootMargin: this.options.rootMargin,
      threshold: this.options.threshold
    });
  }

  /**
   * Setup mutation observer for dynamic content
   */
  setupMutationObserver() {
    if (!('MutationObserver' in window)) return;

    const mutationObserver = new MutationObserver((mutations) => {
      mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            this.scanElement(node);
          }
        });
      });
    });

    mutationObserver.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  /**
   * Scan for images to lazy load
   */
  scanForImages() {
    this.scanElement(document);
  }

  /**
   * Scan element for lazy load images
   */
  scanElement(element) {
    const images = element.querySelectorAll ? 
      element.querySelectorAll('[data-lazy-src], [data-src]') : 
      [];
    
    images.forEach(img => this.setupLazyImage(img));
  }

  /**
   * Setup lazy loading for an image
   */
  setupLazyImage(img) {
    if (img.dataset.lazySetup) return; // Already setup
    
    img.dataset.lazySetup = 'true';
    
    // Add loading class
    img.classList.add('lazy-loading');
    
    // Setup placeholder
    if (this.options.showPlaceholder) {
      this.setupPlaceholder(img);
    }
    
    // Setup progressive loading if enabled
    if (this.options.enableProgressiveLoading) {
      this.setupProgressiveLoading(img);
    }
    
    // Start observing
    if (this.observer) {
      this.observer.observe(img);
    } else {
      // Fallback for browsers without IntersectionObserver
      this.loadImage(img);
    }
  }

  /**
   * Setup image placeholder
   */
  setupPlaceholder(img) {
    if (img.src && !img.src.includes('data:')) return; // Already has source
    
    // Create placeholder based on dimensions
    const width = img.dataset.width || img.getAttribute('width') || 300;
    const height = img.dataset.height || img.getAttribute('height') || 300;
    
    // Generate placeholder SVG
    const placeholder = this.generatePlaceholderSVG(width, height);
    img.src = placeholder;
    
    // Add placeholder styling
    img.style.backgroundColor = this.options.placeholderColor;
    img.style.transition = `opacity ${this.options.fadeInDuration}ms ease`;
  }

  /**
   * Generate placeholder SVG
   */
  generatePlaceholderSVG(width, height) {
    const svg = `
      <svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
        <rect width="100%" height="100%" fill="${this.options.placeholderColor}"/>
        <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999" font-family="Arial, sans-serif" font-size="14">
          Loading...
        </text>
      </svg>
    `;
    
    return `data:image/svg+xml;base64,${btoa(svg)}`;
  }

  /**
   * Setup progressive loading (low quality first)
   */
  setupProgressiveLoading(img) {
    if (!this.options.lowQualityFirst) return;
    
    const originalSrc = img.dataset.lazySrc || img.dataset.src;
    if (!originalSrc) return;
    
    // Generate low quality version URL
    const lowQualitySrc = this.getLowQualityVersion(originalSrc);
    if (lowQualitySrc !== originalSrc) {
      img.dataset.lowQualitySrc = lowQualitySrc;
    }
  }

  /**
   * Get low quality version of image
   */
  getLowQualityVersion(src) {
    // If using backend API, request small thumbnail
    if (src.includes('/uploads/products/')) {
      return src.replace('/uploads/products/', '/uploads/products/small/');
    }
    
    // For other images, return original (could implement blur or low-res versions)
    return src;
  }

  /**
   * Load an image
   */
  async loadImage(img) {
    const originalSrc = img.dataset.lazySrc || img.dataset.src;
    if (!originalSrc || this.loadedImages.has(img)) return;
    
    try {
      // Load low quality first if available
      if (img.dataset.lowQualitySrc && this.options.enableProgressiveLoading) {
        await this.loadImageSrc(img, img.dataset.lowQualitySrc, true);
      }
      
      // Get optimized source
      const optimizedSrc = this.getOptimizedSource(originalSrc, img);
      
      // Load full quality image
      await this.loadImageSrc(img, optimizedSrc, false);
      
      this.loadedImages.add(img);
      this.onImageLoaded(img);
      
    } catch (error) {
      console.error('Failed to load image:', originalSrc, error);
      this.onImageError(img, error);
    }
  }

  /**
   * Load image source
   */
  loadImageSrc(img, src, isLowQuality = false) {
    return new Promise((resolve, reject) => {
      const tempImg = new Image();
      
      tempImg.onload = () => {
        img.src = src;
        
        if (!isLowQuality) {
          // Final image loaded, add loaded class
          img.classList.remove('lazy-loading');
          img.classList.add('lazy-loaded');
          
          // Fade in effect
          img.style.opacity = '0';
          setTimeout(() => {
            img.style.opacity = '1';
          }, 10);
        }
        
        resolve();
      };
      
      tempImg.onerror = () => {
        reject(new Error(`Failed to load image: ${src}`));
      };
      
      // Set source to start loading
      tempImg.src = src;
    });
  }

  /**
   * Get optimized image source
   */
  getOptimizedSource(originalSrc, img) {
    // Determine appropriate size based on image dimensions
    const containerWidth = img.parentElement ? img.parentElement.offsetWidth : 300;
    const devicePixelRatio = window.devicePixelRatio || 1;
    const targetWidth = containerWidth * devicePixelRatio;
    
    let size = 'medium'; // default
    if (targetWidth <= 150) {
      size = 'small';
    } else if (targetWidth <= 300) {
      size = 'medium';
    } else {
      size = 'large';
    }
    
    // Get size-appropriate version
    let optimizedSrc = originalSrc;
    
    if (originalSrc.includes('/uploads/products/')) {
      // Backend optimized images
      optimizedSrc = originalSrc.replace('/uploads/products/', `/uploads/products/${size}/`);
      
      // Use WebP if supported
      if (this.supportsWebP) {
        const pathParts = optimizedSrc.split('.');
        pathParts[pathParts.length - 1] = 'webp';
        const webpSrc = pathParts.join('.');
        
        // Check if WebP version exists (could implement a cache/check mechanism)
        optimizedSrc = webpSrc;
      }
    }
    
    return optimizedSrc;
  }

  /**
   * Handle successful image load
   */
  onImageLoaded(img) {
    // Remove retry count
    this.retryCount.delete(img);
    
    // Dispatch custom event
    img.dispatchEvent(new CustomEvent('lazyloaded', {
      detail: { src: img.src }
    }));
    
    // Update alt text if needed
    if (!img.alt && img.dataset.alt) {
      img.alt = img.dataset.alt;
    }
  }

  /**
   * Handle image load error
   */
  async onImageError(img, error) {
    const retries = this.retryCount.get(img) || 0;
    
    if (retries < this.options.retryAttempts) {
      // Retry loading
      this.retryCount.set(img, retries + 1);
      
      console.warn(`Retrying image load (${retries + 1}/${this.options.retryAttempts}):`, img.dataset.lazySrc);
      
      // Wait before retry
      await new Promise(resolve => setTimeout(resolve, this.options.retryDelay));
      
      // Try again
      this.loadImage(img);
    } else {
      // Max retries reached
      this.failedImages.add(img);
      img.classList.remove('lazy-loading');
      img.classList.add('lazy-error');
      
      // Show error placeholder
      this.showErrorPlaceholder(img);
      
      // Dispatch error event
      img.dispatchEvent(new CustomEvent('lazyerror', {
        detail: { error, retries }
      }));
    }
  }

  /**
   * Show error placeholder
   */
  showErrorPlaceholder(img) {
    const width = img.dataset.width || img.getAttribute('width') || 300;
    const height = img.dataset.height || img.getAttribute('height') || 300;
    
    const errorSvg = `
      <svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
        <rect width="100%" height="100%" fill="#f5f5f5"/>
        <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999" font-family="Arial, sans-serif" font-size="12">
          Image not available
        </text>
      </svg>
    `;
    
    img.src = `data:image/svg+xml;base64,${btoa(errorSvg)}`;
  }

  /**
   * Check WebP support
   */
  checkWebPSupport() {
    return new Promise((resolve) => {
      const webP = new Image();
      webP.onload = webP.onerror = () => {
        resolve(webP.height === 2);
      };
      webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    });
  }

  /**
   * Load all images immediately (fallback)
   */
  loadAllImages() {
    const images = document.querySelectorAll('[data-lazy-src], [data-src]');
    images.forEach(img => {
      const src = img.dataset.lazySrc || img.dataset.src;
      if (src) {
        img.src = src;
        img.classList.remove('lazy-loading');
        img.classList.add('lazy-loaded');
      }
    });
  }

  /**
   * Refresh lazy loading (scan for new images)
   */
  refresh() {
    this.scanForImages();
  }

  /**
   * Destroy lazy loading
   */
  destroy() {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
    
    this.loadedImages.clear();
    this.failedImages.clear();
    this.retryCount.clear();
  }

  /**
   * Get loading statistics
   */
  getStats() {
    const totalImages = document.querySelectorAll('[data-lazy-src], [data-src]').length;
    
    return {
      total: totalImages,
      loaded: this.loadedImages.size,
      failed: this.failedImages.size,
      pending: totalImages - this.loadedImages.size - this.failedImages.size,
      webpSupport: this.supportsWebP
    };
  }
}

// CSS for lazy loading (inject into page)
const lazyLoadingCSS = `
  .lazy-loading {
    opacity: 0.7;
    transition: opacity 300ms ease;
  }
  
  .lazy-loaded {
    opacity: 1;
  }
  
  .lazy-error {
    opacity: 0.5;
    filter: grayscale(100%);
  }
  
  /* Skeleton loading animation */
  .lazy-loading::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: lazy-skeleton 1.5s infinite;
  }
  
  @keyframes lazy-skeleton {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
  }
  
  /* Responsive image container */
  .lazy-image-container {
    position: relative;
    overflow: hidden;
    background-color: #f5f5f5;
  }
  
  .lazy-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
`;

// Inject CSS
if (typeof document !== 'undefined') {
  const style = document.createElement('style');
  style.textContent = lazyLoadingCSS;
  document.head.appendChild(style);
}

// Auto-initialize if in browser
if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  let lazyLoader = null;
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      lazyLoader = new LazyLoadingManager();
      window.lazyLoader = lazyLoader; // Make globally available
    });
  } else {
    lazyLoader = new LazyLoadingManager();
    window.lazyLoader = lazyLoader;
  }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
  module.exports = LazyLoadingManager;
}

// Export for ES6 modules
if (typeof window !== 'undefined') {
  window.LazyLoadingManager = LazyLoadingManager;
}