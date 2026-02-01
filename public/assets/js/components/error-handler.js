/**
 * Error Handler for Riya Collections Frontend
 * Centralized error handling, logging, and user feedback
 */

class ErrorHandler {
  constructor() {
    this.errorLog = [];
    this.maxLogSize = 100;
    this.retryAttempts = new Map();
    this.init();
  }

  /**
   * Initialize error handler
   */
  init() {
    this.setupGlobalErrorHandlers();
    this.setupStyles();
  }

  /**
   * Setup global error handlers
   */
  setupGlobalErrorHandlers() {
    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      console.error('Unhandled promise rejection:', event.reason);
      this.logError('UNHANDLED_PROMISE', event.reason, {
        promise: event.promise
      });
      
      // Prevent default browser behavior
      event.preventDefault();
    });

    // Handle JavaScript errors
    window.addEventListener('error', (event) => {
      console.error('JavaScript error:', event.error);
      this.logError('JAVASCRIPT_ERROR', event.error, {
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno
      });
    });

    // Handle resource loading errors
    document.addEventListener('error', (event) => {
      if (event.target !== window) {
        console.warn('Resource loading error:', event.target);
        this.logError('RESOURCE_ERROR', new Error('Resource failed to load'), {
          element: event.target.tagName,
          src: event.target.src || event.target.href
        });
      }
    }, true);
  }

  /**
   * Setup error display styles
   */
  setupStyles() {
    if (DOMUtils.getId('error-styles')) return;

    const styles = DOMUtils.createElement('style', {
      id: 'error-styles'
    }, `
      .error-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 400px;
        background: #fff;
        border-left: 4px solid #f44336;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        padding: 16px;
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
      }

      .error-toast.show {
        transform: translateX(0);
      }

      .error-toast.success {
        border-left-color: #4caf50;
      }

      .error-toast.warning {
        border-left-color: #ff9800;
      }

      .error-toast.info {
        border-left-color: #2196f3;
      }

      .error-toast-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
      }

      .error-toast-title {
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .error-toast-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #666;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .error-toast-message {
        color: #666;
        font-size: 14px;
        line-height: 1.4;
      }

      .error-toast-actions {
        margin-top: 12px;
        display: flex;
        gap: 8px;
      }

      .error-toast-btn {
        padding: 6px 12px;
        border: 1px solid #ddd;
        background: #fff;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
      }

      .error-toast-btn:hover {
        background: #f5f5f5;
      }

      .error-toast-btn.primary {
        background: var(--primary-color, #E91E63);
        color: white;
        border-color: var(--primary-color, #E91E63);
      }

      .error-toast-btn.primary:hover {
        background: var(--primary-dark, #C2185B);
      }

      .error-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
      }

      .error-modal.show {
        opacity: 1;
        pointer-events: all;
      }

      .error-modal-content {
        background: white;
        border-radius: 8px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        transform: scale(0.9);
        transition: transform 0.3s ease;
      }

      .error-modal.show .error-modal-content {
        transform: scale(1);
      }

      .error-modal-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid #eee;
      }

      .error-modal-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        background: #f44336;
      }

      .error-modal-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
      }

      .error-modal-message {
        color: #666;
        line-height: 1.5;
        margin-bottom: 20px;
      }

      .error-modal-details {
        background: #f5f5f5;
        padding: 12px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 12px;
        color: #666;
        margin-bottom: 20px;
        max-height: 200px;
        overflow-y: auto;
      }

      .error-modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
      }

      .error-inline {
        background: #ffebee;
        border: 1px solid #ffcdd2;
        border-radius: 4px;
        padding: 12px;
        margin: 8px 0;
        color: #c62828;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .error-inline.success {
        background: #e8f5e8;
        border-color: #c8e6c9;
        color: #2e7d32;
      }

      .error-inline.warning {
        background: #fff3e0;
        border-color: #ffcc02;
        color: #ef6c00;
      }

      .error-inline.info {
        background: #e3f2fd;
        border-color: #bbdefb;
        color: #1565c0;
      }

      @media (max-width: 768px) {
        .error-toast {
          right: 10px;
          left: 10px;
          max-width: none;
        }

        .error-modal-content {
          margin: 20px;
          width: calc(100% - 40px);
        }
      }
    `);

    document.head.appendChild(styles);
  }

  /**
   * Log error for debugging and analytics
   * @param {string} type - Error type
   * @param {Error|string} error - Error object or message
   * @param {Object} context - Additional context
   */
  logError(type, error, context = {}) {
    const errorEntry = {
      id: Date.now() + Math.random(),
      type,
      message: error?.message || error,
      stack: error?.stack,
      timestamp: new Date().toISOString(),
      url: window.location.href,
      userAgent: navigator.userAgent,
      context
    };

    this.errorLog.push(errorEntry);

    // Limit log size
    if (this.errorLog.length > this.maxLogSize) {
      this.errorLog.shift();
    }

    // Send to analytics in production
    if (!window.IS_DEVELOPMENT) {
      this.sendToAnalytics(errorEntry);
    }

    return errorEntry.id;
  }

  /**
   * Send error to analytics service
   * @param {Object} errorEntry - Error entry
   */
  async sendToAnalytics(errorEntry) {
    try {
      // In a real application, you would send this to your analytics service
      // For now, we'll just log it
      if (window.IS_DEVELOPMENT) {
        console.log('Analytics Error:', errorEntry);
      }
    } catch (e) {
      console.warn('Failed to send error to analytics:', e);
    }
  }

  /**
   * Handle API errors
   * @param {Error} error - API error
   * @param {Object} options - Error handling options
   * @returns {Object} Error response
   */
  handleApiError(error, options = {}) {
    const {
      showToast = true,
      showModal = false,
      allowRetry = false,
      context = {}
    } = options;

    // Log the error
    const errorId = this.logError('API_ERROR', error, {
      ...context,
      endpoint: context.endpoint,
      method: context.method,
      status: error.status
    });

    // Determine error type and message
    const errorInfo = this.categorizeError(error);

    // Show user feedback
    if (showModal) {
      this.showErrorModal(errorInfo, { allowRetry, errorId });
    } else if (showToast) {
      this.showErrorToast(errorInfo, { allowRetry, errorId });
    }

    return {
      id: errorId,
      type: errorInfo.type,
      message: errorInfo.userMessage,
      canRetry: errorInfo.canRetry && allowRetry
    };
  }

  /**
   * Categorize error for appropriate handling
   * @param {Error} error - Error object
   * @returns {Object} Error information
   */
  categorizeError(error) {
    const message = error.message || 'An unexpected error occurred';
    
    // Network errors
    if (message.includes('Failed to fetch') || message.includes('Network')) {
      return {
        type: 'NETWORK_ERROR',
        userMessage: 'Network connection problem. Please check your internet connection.',
        canRetry: true,
        severity: 'warning'
      };
    }

    // Timeout errors
    if (message.includes('timeout') || message.includes('Timeout')) {
      return {
        type: 'TIMEOUT_ERROR',
        userMessage: 'Request timed out. Please try again.',
        canRetry: true,
        severity: 'warning'
      };
    }

    // Authentication errors
    if (error.status === 401 || message.includes('authentication')) {
      return {
        type: 'AUTH_ERROR',
        userMessage: 'Please log in to continue.',
        canRetry: false,
        severity: 'error'
      };
    }

    // Authorization errors
    if (error.status === 403 || message.includes('authorization')) {
      return {
        type: 'PERMISSION_ERROR',
        userMessage: 'You do not have permission to perform this action.',
        canRetry: false,
        severity: 'error'
      };
    }

    // Not found errors
    if (error.status === 404) {
      return {
        type: 'NOT_FOUND_ERROR',
        userMessage: 'The requested resource was not found.',
        canRetry: false,
        severity: 'warning'
      };
    }

    // Server errors
    if (error.status >= 500) {
      return {
        type: 'SERVER_ERROR',
        userMessage: 'Server error. Please try again later.',
        canRetry: true,
        severity: 'error'
      };
    }

    // Validation errors
    if (error.status === 400 || message.includes('validation')) {
      return {
        type: 'VALIDATION_ERROR',
        userMessage: message,
        canRetry: false,
        severity: 'warning'
      };
    }

    // Generic error
    return {
      type: 'GENERIC_ERROR',
      userMessage: message,
      canRetry: true,
      severity: 'error'
    };
  }

  /**
   * Show error toast notification
   * @param {Object} errorInfo - Error information
   * @param {Object} options - Display options
   */
  showErrorToast(errorInfo, options = {}) {
    const { allowRetry = false, errorId, duration = 5000 } = options;

    const toast = DOMUtils.createElement('div', {
      className: `error-toast ${errorInfo.severity}`,
      'data-error-id': errorId
    }, `
      <div class="error-toast-header">
        <div class="error-toast-title">
          <i class="ri-error-warning-line"></i>
          ${this.getErrorTitle(errorInfo.type)}
        </div>
        <button class="error-toast-close" aria-label="Close">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="error-toast-message">${errorInfo.userMessage}</div>
      ${allowRetry ? `
        <div class="error-toast-actions">
          <button class="error-toast-btn primary" data-action="retry">
            <i class="ri-refresh-line"></i> Retry
          </button>
          <button class="error-toast-btn" data-action="dismiss">Dismiss</button>
        </div>
      ` : ''}
    `);

    // Add event listeners
    const closeBtn = toast.querySelector('.error-toast-close');
    const retryBtn = toast.querySelector('[data-action="retry"]');
    const dismissBtn = toast.querySelector('[data-action="dismiss"]');

    const removeToast = () => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    };

    DOMUtils.addEventListener(closeBtn, 'click', removeToast);
    
    if (dismissBtn) {
      DOMUtils.addEventListener(dismissBtn, 'click', removeToast);
    }

    if (retryBtn) {
      DOMUtils.addEventListener(retryBtn, 'click', () => {
        this.dispatchRetryEvent(errorId);
        removeToast();
      });
    }

    // Add to DOM and show
    document.body.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);

    // Auto-remove after duration
    if (duration > 0) {
      setTimeout(removeToast, duration);
    }

    return toast;
  }

  /**
   * Show error modal
   * @param {Object} errorInfo - Error information
   * @param {Object} options - Display options
   */
  showErrorModal(errorInfo, options = {}) {
    const { allowRetry = false, errorId, showDetails = false } = options;

    const modal = DOMUtils.createElement('div', {
      className: 'error-modal',
      'data-error-id': errorId
    }, `
      <div class="error-modal-content">
        <div class="error-modal-header">
          <div class="error-modal-icon">
            <i class="ri-error-warning-line"></i>
          </div>
          <div class="error-modal-title">${this.getErrorTitle(errorInfo.type)}</div>
        </div>
        <div class="error-modal-message">${errorInfo.userMessage}</div>
        ${showDetails ? `
          <div class="error-modal-details">
            <strong>Error ID:</strong> ${errorId}<br>
            <strong>Type:</strong> ${errorInfo.type}<br>
            <strong>Time:</strong> ${new Date().toLocaleString()}
          </div>
        ` : ''}
        <div class="error-modal-actions">
          ${allowRetry ? `
            <button class="error-toast-btn primary" data-action="retry">
              <i class="ri-refresh-line"></i> Retry
            </button>
          ` : ''}
          <button class="error-toast-btn" data-action="close">Close</button>
        </div>
      </div>
    `);

    // Add event listeners
    const closeBtn = modal.querySelector('[data-action="close"]');
    const retryBtn = modal.querySelector('[data-action="retry"]');

    const removeModal = () => {
      modal.classList.remove('show');
      setTimeout(() => modal.remove(), 300);
    };

    DOMUtils.addEventListener(closeBtn, 'click', removeModal);
    
    if (retryBtn) {
      DOMUtils.addEventListener(retryBtn, 'click', () => {
        this.dispatchRetryEvent(errorId);
        removeModal();
      });
    }

    // Close on backdrop click
    DOMUtils.addEventListener(modal, 'click', (e) => {
      if (e.target === modal) {
        removeModal();
      }
    });

    // Add to DOM and show
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);

    return modal;
  }

  /**
   * Show inline error message
   * @param {Element} container - Container element
   * @param {string} message - Error message
   * @param {string} type - Error type (error, warning, success, info)
   */
  showInlineError(container, message, type = 'error') {
    if (!container) return;

    // Remove existing error
    this.clearInlineError(container);

    const errorElement = DOMUtils.createElement('div', {
      className: `error-inline ${type}`
    }, `
      <i class="ri-${this.getErrorIcon(type)}-line"></i>
      ${message}
    `);

    container.appendChild(errorElement);
    return errorElement;
  }

  /**
   * Clear inline error message
   * @param {Element} container - Container element
   */
  clearInlineError(container) {
    if (!container) return;

    const existingError = container.querySelector('.error-inline');
    if (existingError) {
      existingError.remove();
    }
  }

  /**
   * Get error title based on type
   * @param {string} type - Error type
   * @returns {string} Error title
   */
  getErrorTitle(type) {
    const titles = {
      NETWORK_ERROR: 'Connection Problem',
      TIMEOUT_ERROR: 'Request Timeout',
      AUTH_ERROR: 'Authentication Required',
      PERMISSION_ERROR: 'Access Denied',
      NOT_FOUND_ERROR: 'Not Found',
      SERVER_ERROR: 'Server Error',
      VALIDATION_ERROR: 'Invalid Input',
      GENERIC_ERROR: 'Error'
    };

    return titles[type] || 'Error';
  }

  /**
   * Get error icon based on type
   * @param {string} type - Error type
   * @returns {string} Icon name
   */
  getErrorIcon(type) {
    const icons = {
      error: 'error-warning',
      warning: 'alert',
      success: 'check-circle',
      info: 'information'
    };

    return icons[type] || 'error-warning';
  }

  /**
   * Dispatch retry event
   * @param {string} errorId - Error ID
   */
  dispatchRetryEvent(errorId) {
    const event = new CustomEvent('errorRetry', {
      detail: { errorId }
    });
    document.dispatchEvent(event);
  }

  /**
   * Get error log
   * @param {number} limit - Number of entries to return
   * @returns {Array} Error log entries
   */
  getErrorLog(limit = 10) {
    return this.errorLog.slice(-limit);
  }

  /**
   * Clear error log
   */
  clearErrorLog() {
    this.errorLog = [];
  }

  /**
   * Export error log for debugging
   * @returns {string} JSON string of error log
   */
  exportErrorLog() {
    return JSON.stringify(this.errorLog, null, 2);
  }
}

// Create global instance
window.ErrorHandler = new ErrorHandler();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ErrorHandler;
}