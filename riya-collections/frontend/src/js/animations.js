/**
 * Animation Controller for Riya Collections Frontend
 * Handles all CSS animations and transitions
 */

class AnimationController {
  constructor() {
    this.init();
  }

  init() {
    this.setupButtonAnimations();
    this.setupFormAnimations();
    this.setupScrollReveal();
    this.setupCartAnimations();
    this.setupNavigationAnimations();
    this.setupCardAnimations();
    this.setupLoadingAnimations();
    this.setupNotificationAnimations();
    this.respectReducedMotion();
  }

  /**
   * Setup button animations (hover, loading, ripple)
   */
  setupButtonAnimations() {
    const buttons = document.querySelectorAll('.btn');

    buttons.forEach(button => {
      // Add ripple effect class
      button.classList.add('ripple');

      // Hover effect
      button.addEventListener('mouseenter', () => {
        if (!this.prefersReducedMotion()) {
          button.style.transform = 'translateY(-2px)';
          button.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
        }
      });

      button.addEventListener('mouseleave', () => {
        button.style.transform = '';
        button.style.boxShadow = '';
      });

      // Loading state
      button.addEventListener('click', () => {
        if (button.dataset.loading === 'true') {
          button.classList.add('loading');
          button.disabled = true;
        }
      });
    });
  }

  /**
   * Setup form input animations
   */
  setupFormAnimations() {
    const inputs = document.querySelectorAll('.form-input');

    inputs.forEach(input => {
      const formGroup = input.closest('.form-group');

      // Add floating label class if form group exists
      if (formGroup) {
        formGroup.classList.add('floating-label');
      }

      // Focus animation
      input.addEventListener('focus', () => {
        const formGroup = input.closest('.form-group') || input.parentElement;
        if (formGroup) {
          formGroup.classList.add('focused');
        }
      });

      input.addEventListener('blur', () => {
        const formGroup = input.closest('.form-group') || input.parentElement;
        if (formGroup) {
          formGroup.classList.remove('focused');
        }
      });

      // Invalid animation
      input.addEventListener('invalid', () => {
        input.classList.add('error');
        // Shake animation
        if (!this.prefersReducedMotion()) {
          input.style.animation = 'shake 0.5s ease-in-out';
          setTimeout(() => {
            input.style.animation = '';
          }, 500);
        }
      });
    });
  }

  /**
   * Setup scroll reveal animations
   */
  setupScrollReveal() {
    if (!('IntersectionObserver' in window)) {
      // Fallback for unsupported browsers
      const elements = document.querySelectorAll('.scroll-reveal');
      elements.forEach(el => el.classList.add('revealed'));
      return;
    }

    try {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('revealed');
          }
        });
      }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      });

      const elements = document.querySelectorAll('.scroll-reveal');
      elements.forEach(el => observer.observe(el));
    } catch (error) {
      // Fallback if IntersectionObserver fails
      const elements = document.querySelectorAll('.scroll-reveal');
      elements.forEach(el => el.classList.add('revealed'));
    }
  }

  /**
   * Setup cart animations
   */
  setupCartAnimations() {
    const cartCount = document.getElementById('cartCount');
    if (cartCount) {
      // Animate cart count changes
      const observer = new MutationObserver(() => {
        if (!this.prefersReducedMotion()) {
          cartCount.style.animation = 'bounce 0.5s ease';
          setTimeout(() => {
            cartCount.style.animation = '';
          }, 500);
        }
      });

      observer.observe(cartCount, {
        childList: true,
        characterData: true,
        subtree: true
      });
    }
  }

  /**
   * Setup navigation animations
   */
  setupNavigationAnimations() {
    const navToggle = document.getElementById('navToggle');
    if (navToggle) {
      navToggle.addEventListener('click', () => {
        navToggle.classList.toggle('active');
        const navMenu = document.getElementById('nav-menu');
        if (navMenu) {
          navMenu.classList.toggle('active');
        }
      });
    }
  }

  /**
   * Setup card animations
   */
  setupCardAnimations() {
    const cards = document.querySelectorAll('.product-card');

    cards.forEach(card => {
      card.classList.add('hover-effect');

      card.addEventListener('mouseenter', () => {
        if (!this.prefersReducedMotion()) {
          card.style.transform = 'translateY(-4px)';
          card.style.boxShadow = '0 8px 16px rgba(0,0,0,0.15)';
        }
      });

      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
        card.style.boxShadow = '';
      });
    });
  }

  /**
   * Setup loading animations
   */
  setupLoadingAnimations() {
    // Loading overlay
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
      // Methods to show/hide loading
      window.showLoading = () => {
        loadingOverlay.classList.add('show');
      };

      window.hideLoading = () => {
        loadingOverlay.classList.remove('show');
      };
    }
  }

  /**
   * Setup notification animations
   */
  setupNotificationAnimations() {
    // Notification system
    window.showNotification = (message, type = 'info') => {
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      notification.innerHTML = `
        <span class="notification-message">${message}</span>
        <button class="notification-close">&times;</button>
      `;

      document.body.appendChild(notification);

      // Animate in
      if (!this.prefersReducedMotion()) {
        notification.style.animation = 'slideIn 0.3s ease-out';
      }

      // Auto hide
      setTimeout(() => {
        if (!this.prefersReducedMotion()) {
          notification.style.animation = 'slideOut 0.3s ease-in';
        }
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }, 3000);

      // Close button
      const closeBtn = notification.querySelector('.notification-close');
      closeBtn.addEventListener('click', () => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      });
    };
  }

  /**
   * Check if user prefers reduced motion
   */
  prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /**
   * Respect reduced motion preferences
   */
  respectReducedMotion() {
    if (this.prefersReducedMotion()) {
      // Disable animations
      const style = document.createElement('style');
      style.textContent = `
        *, *::before, *::after {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
        }
      `;
      document.head.appendChild(style);
    }
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AnimationController;
}