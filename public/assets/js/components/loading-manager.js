/**
 * Loading Manager for Riya Collections Frontend
 * Handles loading states, spinners, and progress indicators
 */

class LoadingManager {
  constructor() {
    this.activeRequests = new Set();
    this.loadingElements = new Map();
    this.init();
  }

  /**
   * Initialize loading manager
   */
  init() {
    this.createGlobalLoader();
    this.setupStyles();
  }

  /**
   * Create global loading overlay
   */
  createGlobalLoader() {
    if (DOMUtils.getId('global-loader')) return;

    const loader = DOMUtils.createElement('div', {
      id: 'global-loader',
      className: 'global-loader hidden'
    }, `
      <div class="loader-backdrop"></div>
      <div class="loader-content">
        <div class="loader-spinner">
          <div class="spinner-ring"></div>
          <div class="spinner-ring"></div>
          <div class="spinner-ring"></div>
        </div>
        <div class="loader-text">Loading...</div>
      </div>
    `);

    document.body.appendChild(loader);
  }

  /**
   * Setup loading styles
   */
  setupStyles() {
    if (DOMUtils.getId('loading-styles')) return;

    const styles = DOMUtils.createElement('style', {
      id: 'loading-styles'
    }, `
      .global-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.3s ease;
      }

      .global-loader.hidden {
        opacity: 0;
        pointer-events: none;
      }

      .loader-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(2px);
      }

      .loader-content {
        position: relative;
        text-align: center;
        z-index: 1;
      }

      .loader-spinner {
        position: relative;
        width: 60px;
        height: 60px;
        margin: 0 auto 16px;
      }

      .spinner-ring {
        position: absolute;
        width: 100%;
        height: 100%;
        border: 3px solid transparent;
        border-top: 3px solid var(--primary-color, #E91E63);
        border-radius: 50%;
        animation: spin 1.2s linear infinite;
      }

      .spinner-ring:nth-child(2) {
        animation-delay: -0.4s;
        border-top-color: var(--secondary-color, #FF6B9D);
      }

      .spinner-ring:nth-child(3) {
        animation-delay: -0.8s;
        border-top-color: var(--accent-color, #C2185B);
      }

      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      .loader-text {
        color: var(--text-color, #333);
        font-size: 14px;
        font-weight: 500;
      }

      .btn-loading {
        position: relative;
        pointer-events: none;
      }

      .btn-loading .btn-text {
        opacity: 0;
      }

      .btn-loading .btn-spinner {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 20px;
        height: 20px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }

      .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
      }

      .loading-skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: skeleton-loading 1.5s infinite;
      }

      @keyframes skeleton-loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
      }

      .loading-dots {
        display: inline-flex;
        align-items: center;
        gap: 4px;
      }

      .loading-dots::after {
        content: '';
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: currentColor;
        animation: loading-dots 1.4s infinite ease-in-out both;
      }

      .loading-dots::before {
        content: '';
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: currentColor;
        animation: loading-dots 1.4s infinite ease-in-out both;
        animation-delay: -0.32s;
      }

      @keyframes loading-dots {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
      }
    `);

    document.head.appendChild(styles);
  }

  /**
   * Show global loading
   * @param {string} text - Loading text
   */
  showGlobal(text = 'Loading...') {
    const loader = DOMUtils.getId('global-loader');
    const textElement = loader.querySelector('.loader-text');
    
    if (textElement) {
      textElement.textContent = text;
    }
    
    loader.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  /**
   * Hide global loading
   */
  hideGlobal() {
    const loader = DOMUtils.getId('global-loader');
    loader.classList.add('hidden');
    document.body.style.overflow = '';
  }

  /**
   * Show button loading state
   * @param {Element} button - Button element
   * @param {string} text - Loading text
   */
  showButton(button, text = 'Loading...') {
    if (!button) return;

    button.disabled = true;
    button.classList.add('btn-loading');
    
    // Store original content
    if (!button.dataset.originalContent) {
      button.dataset.originalContent = button.innerHTML;
    }

    // Create loading content
    button.innerHTML = `
      <span class="btn-text">${text}</span>
      <span class="btn-spinner"></span>
    `;

    this.loadingElements.set(button, 'button');
  }

  /**
   * Hide button loading state
   * @param {Element} button - Button element
   */
  hideButton(button) {
    if (!button) return;

    button.disabled = false;
    button.classList.remove('btn-loading');
    
    // Restore original content
    if (button.dataset.originalContent) {
      button.innerHTML = button.dataset.originalContent;
      delete button.dataset.originalContent;
    }

    this.loadingElements.delete(button);
  }

  /**
   * Show element loading overlay
   * @param {Element} element - Target element
   * @param {string} text - Loading text
   */
  showElement(element, text = 'Loading...') {
    if (!element) return;

    // Remove existing overlay
    this.hideElement(element);

    const overlay = DOMUtils.createElement('div', {
      className: 'loading-overlay'
    }, `
      <div class="loader-spinner">
        <div class="spinner-ring"></div>
      </div>
      <div class="loader-text">${text}</div>
    `);

    element.style.position = 'relative';
    element.appendChild(overlay);
    
    this.loadingElements.set(element, 'overlay');
  }

  /**
   * Hide element loading overlay
   * @param {Element} element - Target element
   */
  hideElement(element) {
    if (!element) return;

    const overlay = element.querySelector('.loading-overlay');
    if (overlay) {
      overlay.remove();
    }

    this.loadingElements.delete(element);
  }

  /**
   * Create skeleton loader
   * @param {Element} container - Container element
   * @param {Object} config - Skeleton configuration
   */
  showSkeleton(container, config = {}) {
    if (!container) return;

    const {
      rows = 3,
      height = '20px',
      spacing = '12px',
      borderRadius = '4px'
    } = config;

    const skeleton = DOMUtils.createElement('div', {
      className: 'skeleton-container'
    });

    for (let i = 0; i < rows; i++) {
      const row = DOMUtils.createElement('div', {
        className: 'loading-skeleton',
        style: `
          height: ${height};
          margin-bottom: ${i < rows - 1 ? spacing : '0'};
          border-radius: ${borderRadius};
          width: ${i === rows - 1 ? '60%' : '100%'};
        `
      });
      skeleton.appendChild(row);
    }

    container.innerHTML = '';
    container.appendChild(skeleton);
    
    this.loadingElements.set(container, 'skeleton');
  }

  /**
   * Hide skeleton loader
   * @param {Element} container - Container element
   */
  hideSkeleton(container) {
    if (!container) return;

    const skeleton = container.querySelector('.skeleton-container');
    if (skeleton) {
      skeleton.remove();
    }

    this.loadingElements.delete(container);
  }

  /**
   * Track API request
   * @param {string} requestId - Unique request ID
   */
  trackRequest(requestId) {
    this.activeRequests.add(requestId);
    
    if (this.activeRequests.size === 1) {
      // First request, show global loading
      this.showGlobal();
    }
  }

  /**
   * Untrack API request
   * @param {string} requestId - Unique request ID
   */
  untrackRequest(requestId) {
    this.activeRequests.delete(requestId);
    
    if (this.activeRequests.size === 0) {
      // No more requests, hide global loading
      this.hideGlobal();
    }
  }

  /**
   * Check if any requests are active
   * @returns {boolean} Has active requests
   */
  hasActiveRequests() {
    return this.activeRequests.size > 0;
  }

  /**
   * Clear all loading states
   */
  clearAll() {
    // Hide global loading
    this.hideGlobal();
    
    // Clear all tracked elements
    for (const [element, type] of this.loadingElements) {
      switch (type) {
        case 'button':
          this.hideButton(element);
          break;
        case 'overlay':
          this.hideElement(element);
          break;
        case 'skeleton':
          this.hideSkeleton(element);
          break;
      }
    }
    
    // Clear all active requests
    this.activeRequests.clear();
  }

  /**
   * Create loading dots animation
   * @param {Element} element - Target element
   */
  showDots(element) {
    if (!element) return;

    element.classList.add('loading-dots');
    this.loadingElements.set(element, 'dots');
  }

  /**
   * Hide loading dots animation
   * @param {Element} element - Target element
   */
  hideDots(element) {
    if (!element) return;

    element.classList.remove('loading-dots');
    this.loadingElements.delete(element);
  }

  /**
   * Show progress bar
   * @param {Element} container - Container element
   * @param {number} progress - Progress percentage (0-100)
   */
  showProgress(container, progress = 0) {
    if (!container) return;

    let progressBar = container.querySelector('.progress-bar');
    
    if (!progressBar) {
      progressBar = DOMUtils.createElement('div', {
        className: 'progress-bar'
      }, `
        <div class="progress-fill"></div>
        <div class="progress-text">0%</div>
      `);
      container.appendChild(progressBar);
    }

    const fill = progressBar.querySelector('.progress-fill');
    const text = progressBar.querySelector('.progress-text');
    
    fill.style.width = `${Math.min(100, Math.max(0, progress))}%`;
    text.textContent = `${Math.round(progress)}%`;
    
    this.loadingElements.set(container, 'progress');
  }

  /**
   * Update progress bar
   * @param {Element} container - Container element
   * @param {number} progress - Progress percentage (0-100)
   */
  updateProgress(container, progress) {
    this.showProgress(container, progress);
  }

  /**
   * Hide progress bar
   * @param {Element} container - Container element
   */
  hideProgress(container) {
    if (!container) return;

    const progressBar = container.querySelector('.progress-bar');
    if (progressBar) {
      progressBar.remove();
    }

    this.loadingElements.delete(container);
  }
}

// Create global instance
window.LoadingManager = new LoadingManager();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = LoadingManager;
}