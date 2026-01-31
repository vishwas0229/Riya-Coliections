/**
 * Main JavaScript file for Riya Collections
 * Handles common functionality across all pages
 */

class RiyaCollections {
  constructor() {
    this.isInitialized = false;
    this.init();
  }

  /**
   * Initialize the application
   */
  init() {
    if (this.isInitialized) return;
    
    this.setupGlobalEventListeners();
    this.initializeBackToTop();
    this.initializeScrollEffects();
    this.checkAuthStatus();
    this.setupApiIntegration();
    this.isInitialized = true;
    
    // Log initialization in development
    if (window.IS_DEVELOPMENT) {
      console.log('Riya Collections initialized');
    }
  }

  /**
   * Setup global event listeners
   */
  setupGlobalEventListeners() {
    // Handle page load
    DOMUtils.addEventListener(window, 'load', () => {
      this.handlePageLoad();
    });

    // Handle scroll events
    DOMUtils.addEventListener(window, 'scroll', 
      TimingUtils.throttle(() => this.handleScroll(), 100)
    );

    // Handle resize events
    DOMUtils.addEventListener(window, 'resize', 
      TimingUtils.throttle(() => this.handleResize(), 250)
    );

    // Handle clicks on external links
    DOMUtils.addEventListener(document, 'click', (e) => {
      const link = e.target.closest('a[href^="http"]');
      if (link && !link.href.includes(window.location.hostname)) {
        link.setAttribute('target', '_blank');
        link.setAttribute('rel', 'noopener noreferrer');
      }
    });

    // Handle form submissions
    DOMUtils.addEventListener(document, 'submit', (e) => {
      const form = e.target;
      if (form.classList.contains('validate-form')) {
        if (!this.validateForm(form)) {
          e.preventDefault();
        }
      }
    });

    // Handle keyboard navigation
    DOMUtils.addEventListener(document, 'keydown', (e) => {
      this.handleKeyboardNavigation(e);
    });

    // Handle focus management
    DOMUtils.addEventListener(document, 'focusin', (e) => {
      this.handleFocusManagement(e);
    });
  }

  /**
   * Handle page load
   */
  handlePageLoad() {
    // Remove loading class from body
    document.body.classList.remove('loading');
    
    // Hide loading overlay
    if (window.hideLoading) {
      window.hideLoading();
    }
    
    // Initialize lazy loading for images
    this.initializeLazyLoading();
    
    // Initialize tooltips
    this.initializeTooltips();
    
    // Initialize smooth scrolling for anchor links
    this.initializeSmoothScrolling();
    
    // Check for saved theme preference
    this.initializeTheme();
  }

  /**
   * Handle scroll events
   */
  handleScroll() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // Update header on scroll
    this.updateHeaderOnScroll(scrollTop);
    
    // Update back to top button
    this.updateBackToTop(scrollTop);
    
    // Update scroll progress
    this.updateScrollProgress(scrollTop);
  }

  /**
   * Handle resize events
   */
  handleResize() {
    // Update mobile menu state
    this.updateMobileMenuState();
    
    // Update carousel layouts
    this.updateCarouselLayouts();
    
    // Update modal positions
    this.updateModalPositions();
  }

  /**
   * Update header appearance on scroll
   */
  updateHeaderOnScroll(scrollTop) {
    const header = DOMUtils.getElement('.header');
    if (!header) return;

    if (scrollTop > 100) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  }

  /**
   * Initialize back to top button
   */
  initializeBackToTop() {
    const backToTopBtn = DOMUtils.getId('back-to-top');
    if (!backToTopBtn) return;

    DOMUtils.addEventListener(backToTopBtn, 'click', (e) => {
      e.preventDefault();
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }

  /**
   * Update back to top button visibility
   */
  updateBackToTop(scrollTop) {
    const backToTopBtn = DOMUtils.getId('back-to-top');
    if (!backToTopBtn) return;

    if (scrollTop > UI_CONFIG.SCROLL.BACK_TO_TOP_THRESHOLD) {
      backToTopBtn.classList.add('show');
    } else {
      backToTopBtn.classList.remove('show');
    }
  }

  /**
   * Initialize scroll effects
   */
  initializeScrollEffects() {
    // Parallax effects
    const parallaxElements = DOMUtils.getElements('[data-parallax]');
    if (parallaxElements.length > 0) {
      DOMUtils.addEventListener(window, 'scroll', 
        TimingUtils.throttle(() => this.updateParallax(parallaxElements), 16)
      );
    }

    // Reveal animations
    this.initializeRevealAnimations();
  }

  /**
   * Initialize reveal animations
   */
  initializeRevealAnimations() {
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-fade-in-up');
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    const revealElements = DOMUtils.getElements('.reveal');
    revealElements.forEach(element => {
      observer.observe(element);
    });
  }

  /**
   * Update parallax effects
   */
  updateParallax(elements) {
    const scrollTop = window.pageYOffset;

    elements.forEach(element => {
      const speed = parseFloat(element.dataset.parallax) || 0.5;
      const yPos = -(scrollTop * speed);
      element.style.transform = `translateY(${yPos}px)`;
    });
  }

  /**
   * Initialize lazy loading for images
   */
  initializeLazyLoading() {
    const lazyImages = DOMUtils.getElements('img[loading="lazy"]');
    
    if ('IntersectionObserver' in window) {
      const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            this.loadImage(img);
            imageObserver.unobserve(img);
          }
        });
      });

      lazyImages.forEach(img => imageObserver.observe(img));
    } else {
      // Fallback for older browsers
      lazyImages.forEach(img => this.loadImage(img));
    }
  }

  /**
   * Load image with error handling
   */
  loadImage(img) {
    const src = img.dataset.src || img.src;
    
    if (src) {
      ImageUtils.lazyLoad(img, src)
        .catch(() => {
          // Use placeholder on error
          img.src = APP_CONFIG.IMAGES.PLACEHOLDER;
          img.classList.add('error');
        });
    }
  }

  /**
   * Initialize tooltips
   */
  initializeTooltips() {
    const tooltipElements = DOMUtils.getElements('[data-tooltip]');
    
    tooltipElements.forEach(element => {
      this.createTooltip(element);
    });
  }

  /**
   * Create tooltip for element
   */
  createTooltip(element) {
    const tooltipText = element.dataset.tooltip;
    if (!tooltipText) return;

    let tooltip;

    const showTooltip = () => {
      tooltip = DOMUtils.createElement('div', {
        className: 'tooltip',
        innerHTML: tooltipText
      });

      document.body.appendChild(tooltip);
      this.positionTooltip(element, tooltip);
      
      setTimeout(() => tooltip.classList.add('show'), 10);
    };

    const hideTooltip = () => {
      if (tooltip) {
        tooltip.classList.remove('show');
        setTimeout(() => {
          if (tooltip.parentNode) {
            tooltip.parentNode.removeChild(tooltip);
          }
        }, 200);
      }
    };

    DOMUtils.addEventListener(element, 'mouseenter', showTooltip);
    DOMUtils.addEventListener(element, 'mouseleave', hideTooltip);
    DOMUtils.addEventListener(element, 'focus', showTooltip);
    DOMUtils.addEventListener(element, 'blur', hideTooltip);
  }

  /**
   * Position tooltip relative to element
   */
  positionTooltip(element, tooltip) {
    const elementRect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    const top = elementRect.top - tooltipRect.height - 10;
    const left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
    
    tooltip.style.position = 'fixed';
    tooltip.style.top = `${Math.max(10, top)}px`;
    tooltip.style.left = `${Math.max(10, Math.min(window.innerWidth - tooltipRect.width - 10, left))}px`;
  }

  /**
   * Initialize smooth scrolling for anchor links
   */
  initializeSmoothScrolling() {
    const anchorLinks = DOMUtils.getElements('a[href^="#"]');
    
    anchorLinks.forEach(link => {
      DOMUtils.addEventListener(link, 'click', (e) => {
        const href = link.getAttribute('href');
        if (href === '#') return;
        
        const target = DOMUtils.getElement(href);
        if (target) {
          e.preventDefault();
          DOMUtils.scrollTo(target, UI_CONFIG.SCROLL.SMOOTH_OFFSET);
        }
      });
    });
  }

  /**
   * Initialize theme
   */
  initializeTheme() {
    const savedTheme = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.THEME_PREFERENCE);
    
    if (savedTheme) {
      document.documentElement.setAttribute('data-theme', savedTheme);
    } else {
      // Detect system preference
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const theme = prefersDark ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', theme);
    }
  }

  /**
   * Setup API integration
   */
  setupApiIntegration() {
    // Listen for API system ready event
    document.addEventListener('apiSystemReady', () => {
      console.log('🔗 API System integrated with main application');
      
      // Setup auth state listener
      if (window.AuthManager) {
        window.AuthManager.onAuthStateChange((authenticated, user) => {
          if (authenticated) {
            this.updateAuthenticatedUI(user);
          } else {
            this.updateUnauthenticatedUI();
          }
        });
      }
      
      // Setup loading state listeners
      document.addEventListener('apiLoadingChange', (event) => {
        this.handleApiLoadingChange(event.detail);
      });
      
      // Setup error state listeners
      document.addEventListener('apiErrorChange', (event) => {
        this.handleApiErrorChange(event.detail);
      });
      
      // Initialize real-time integration
      this.initializeRealTimeIntegration();
    });
  }

  /**
   * Initialize real-time integration
   */
  initializeRealTimeIntegration() {
    // Real-time integration will auto-initialize
    // Just log when it's ready
    if (window.RealTimeIntegration) {
      console.log('🔄 Real-time updates integrated');
    }
  }

  /**
   * Handle API loading state changes
   * @param {Object} detail - Loading change details
   */
  handleApiLoadingChange(detail) {
    const { key, loading } = detail;
    
    // Update UI based on loading state
    if (loading) {
      document.body.classList.add('api-loading');
    } else {
      // Check if any other requests are still loading
      if (window.ApiStateManager && !window.ApiStateManager.getAllLoading().length) {
        document.body.classList.remove('api-loading');
      }
    }
  }

  /**
   * Handle API error state changes
   * @param {Object} detail - Error change details
   */
  handleApiErrorChange(detail) {
    const { key, error } = detail;
    
    if (error) {
      // Handle specific error types
      if (error.status === 401) {
        // Authentication error - redirect to login
        this.handleAuthenticationError();
      } else if (error.status === 403) {
        // Authorization error - show message
        this.showNotification('You do not have permission to perform this action', 'error');
      } else if (error.message && error.message.includes('Network')) {
        // Network error - show connectivity message
        this.showNotification('Network connection problem. Please check your internet connection.', 'warning');
      }
    }
  }

  /**
   * Handle authentication error
   */
  handleAuthenticationError() {
    // Clear stored auth data
    CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
    
    // Update UI
    this.updateUnauthenticatedUI();
    
    // Show message and redirect
    this.showNotification('Your session has expired. Please log in again.', 'warning');
    
    setTimeout(() => {
      const currentPath = window.location.pathname;
      const redirectUrl = encodeURIComponent(window.location.href);
      window.location.href = `pages/login.html?redirect=${redirectUrl}`;
    }, 2000);
  }
    const token = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    const userData = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
    
    if (token && userData) {
      this.updateAuthenticatedUI(userData);
    } else {
      this.updateUnauthenticatedUI();
    }
  }

  /**
   * Update UI for authenticated user
   */
  updateAuthenticatedUI(userData) {
    const userMenu = DOMUtils.getElement('.nav__user-menu');
    if (userMenu) {
      userMenu.innerHTML = `
        <a href="pages/profile.html" class="nav__user-link">
          <i class="ri-user-line"></i> ${userData.firstName}
        </a>
        <a href="pages/orders.html" class="nav__user-link">
          <i class="ri-file-list-line"></i> My Orders
        </a>
        <a href="pages/wishlist.html" class="nav__user-link">
          <i class="ri-heart-line"></i> Wishlist
        </a>
        <a href="#" class="nav__user-link" id="logout-btn">
          <i class="ri-logout-line"></i> Logout
        </a>
      `;

      // Add logout handler
      const logoutBtn = DOMUtils.getId('logout-btn');
      if (logoutBtn) {
        DOMUtils.addEventListener(logoutBtn, 'click', (e) => {
          e.preventDefault();
          this.handleLogout();
        });
      }
    }
  }

  /**
   * Update UI for unauthenticated user
   */
  updateUnauthenticatedUI() {
    const userMenu = DOMUtils.getElement('.nav__user-menu');
    if (userMenu) {
      userMenu.innerHTML = `
        <a href="pages/login.html" class="nav__user-link">
          <i class="ri-login-line"></i> Login
        </a>
        <a href="pages/register.html" class="nav__user-link">
          <i class="ri-user-add-line"></i> Register
        </a>
      `;
    }
  }

  /**
   * Check authentication status
   */
  checkAuthStatus() {
    const token = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    const userData = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
    
    if (token && userData) {
      this.updateAuthenticatedUI(userData);
    } else {
      this.updateUnauthenticatedUI();
    }
  }

  /**
   * Handle user logout
   */
  async handleLogout() {
    try {
      // Use AuthManager if available, otherwise fallback to ApiService
      if (window.AuthManager) {
        await window.AuthManager.logout();
      } else {
        await ApiService.auth.logout();
      }
      
      this.showNotification(SUCCESS_MESSAGES.LOGOUT_SUCCESS, 'success');
      
      // Update UI
      this.updateUnauthenticatedUI();
      
      // Redirect to home page
      setTimeout(() => {
        window.location.href = '/';
      }, 1500);
    } catch (error) {
      console.error('Logout error:', error);
      // Clear local data even if API call fails
      CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
      CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
      window.location.reload();
    }
  }

  /**
   * Validate form
   */
  validateForm(form) {
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
      const value = input.value.trim();
      const type = input.type;
      
      // Clear previous errors
      this.clearFieldError(input);
      
      // Required field validation
      if (!ValidationUtils.required(value)) {
        this.showFieldError(input, ERROR_MESSAGES.REQUIRED_FIELD);
        isValid = false;
        return;
      }
      
      // Type-specific validation
      switch (type) {
        case 'email':
          if (!ValidationUtils.email(value)) {
            this.showFieldError(input, ERROR_MESSAGES.INVALID_EMAIL);
            isValid = false;
          }
          break;
        case 'tel':
          if (!ValidationUtils.phone(value)) {
            this.showFieldError(input, ERROR_MESSAGES.INVALID_PHONE);
            isValid = false;
          }
          break;
        case 'password':
          const minLength = input.dataset.minLength || 8;
          if (!ValidationUtils.minLength(value, minLength)) {
            this.showFieldError(input, ERROR_MESSAGES.PASSWORD_TOO_SHORT);
            isValid = false;
          }
          break;
      }
      
      // Custom validation
      const customValidation = input.dataset.validation;
      if (customValidation && window[customValidation]) {
        const result = window[customValidation](value);
        if (!result.isValid) {
          this.showFieldError(input, result.message);
          isValid = false;
        }
      }
    });

    return isValid;
  }

  /**
   * Show field error
   */
  showFieldError(input, message) {
    input.classList.add('error');
    
    let errorElement = input.parentNode.querySelector('.field-error');
    if (!errorElement) {
      errorElement = DOMUtils.createElement('div', {
        className: 'field-error'
      });
      input.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
  }

  /**
   * Clear field error
   */
  clearFieldError(input) {
    input.classList.remove('error');
    
    const errorElement = input.parentNode.querySelector('.field-error');
    if (errorElement) {
      errorElement.remove();
    }
  }

  /**
   * Handle keyboard navigation
   */
  handleKeyboardNavigation(e) {
    // Escape key handling
    if (e.key === 'Escape') {
      this.handleEscapeKey();
    }
    
    // Tab key handling for focus management
    if (e.key === 'Tab') {
      this.handleTabKey(e);
    }
  }

  /**
   * Handle escape key press
   */
  handleEscapeKey() {
    // Close modals
    const openModals = DOMUtils.getElements('.modal.show');
    openModals.forEach(modal => {
      modal.classList.remove('show');
    });
    
    // Close dropdowns
    const openDropdowns = DOMUtils.getElements('.dropdown.show');
    openDropdowns.forEach(dropdown => {
      dropdown.classList.remove('show');
    });
    
    // Close mobile menu
    const mobileMenu = DOMUtils.getElement('.nav__menu.show-menu');
    if (mobileMenu) {
      mobileMenu.classList.remove('show-menu');
    }
  }

  /**
   * Handle tab key for focus management
   */
  handleTabKey(e) {
    const focusableElements = DOMUtils.getElements(
      'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    
    if (e.shiftKey) {
      if (document.activeElement === firstElement) {
        e.preventDefault();
        lastElement.focus();
      }
    } else {
      if (document.activeElement === lastElement) {
        e.preventDefault();
        firstElement.focus();
      }
    }
  }

  /**
   * Handle focus management
   */
  handleFocusManagement(e) {
    // Add focus-visible class for keyboard navigation
    if (e.target.matches(':focus-visible')) {
      e.target.classList.add('focus-visible');
    }
  }

  /**
   * Update scroll progress
   */
  updateScrollProgress(scrollTop) {
    const progressBar = DOMUtils.getElement('.scroll-progress');
    if (!progressBar) return;

    const documentHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = (scrollTop / documentHeight) * 100;
    
    progressBar.style.width = `${Math.min(100, Math.max(0, progress))}%`;
  }

  /**
   * Update mobile menu state
   */
  updateMobileMenuState() {
    const breakpoint = CONFIG_UTILS.getCurrentBreakpoint();
    const mobileMenu = DOMUtils.getElement('.nav__menu');
    
    if (mobileMenu && (breakpoint === 'tablet' || breakpoint === 'desktop' || breakpoint === 'large')) {
      mobileMenu.classList.remove('show-menu');
    }
  }

  /**
   * Update carousel layouts
   */
  updateCarouselLayouts() {
    // This will be implemented by specific carousel components
    const event = new CustomEvent('carouselResize');
    document.dispatchEvent(event);
  }

  /**
   * Update modal positions
   */
  updateModalPositions() {
    const openModals = DOMUtils.getElements('.modal.show');
    openModals.forEach(modal => {
      // Recalculate modal position
      const modalContent = modal.querySelector('.modal__content');
      if (modalContent) {
        modalContent.style.marginTop = '0';
        const rect = modalContent.getBoundingClientRect();
        if (rect.height > window.innerHeight) {
          modalContent.style.marginTop = '20px';
        }
      }
    });
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

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new RiyaCollections();
});

// Export for testing
window.RiyaCollections = RiyaCollections;