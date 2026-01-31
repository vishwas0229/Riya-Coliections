/**
 * Notification system for Riya Collections
 * Handles success, error, warning, and info messages
 */

class NotificationManager {
  constructor() {
    this.container = null;
    this.notifications = new Map();
    this.init();
  }

  /**
   * Initialize notification system
   */
  init() {
    this.createContainer();
    this.setupStyles();
  }

  /**
   * Create notification container
   */
  createContainer() {
    // Remove existing container if any
    const existing = document.getElementById('notification-container');
    if (existing) {
      existing.remove();
    }

    // Create new container
    this.container = DOMUtils.createElement('div', {
      id: 'notification-container',
      className: 'notification-container',
      'aria-live': 'polite',
      'aria-atomic': 'false'
    });

    document.body.appendChild(this.container);
  }

  /**
   * Setup notification styles
   */
  setupStyles() {
    // Check if styles already exist
    if (document.getElementById('notification-styles')) return;

    const styles = `
      .notification-container {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 10000;
        pointer-events: none;
        max-width: 400px;
        width: 100%;
      }

      .notification {
        background: var(--white-color);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-lg);
        margin-bottom: 0.75rem;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        pointer-events: auto;
        transform: translateX(100%);
        opacity: 0;
        transition: all 0.3s ease;
        border-left: 4px solid var(--primary-color);
        position: relative;
        overflow: hidden;
      }

      .notification.show {
        transform: translateX(0);
        opacity: 1;
      }

      .notification.hide {
        transform: translateX(100%);
        opacity: 0;
      }

      .notification::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--primary-color);
        animation: notificationProgress linear;
      }

      .notification.success {
        border-left-color: var(--success-color);
      }

      .notification.success::before {
        background: var(--success-color);
      }

      .notification.error {
        border-left-color: var(--error-color);
      }

      .notification.error::before {
        background: var(--error-color);
      }

      .notification.warning {
        border-left-color: var(--warning-color);
      }

      .notification.warning::before {
        background: var(--warning-color);
      }

      .notification.info {
        border-left-color: var(--info-color);
      }

      .notification.info::before {
        background: var(--info-color);
      }

      .notification-icon {
        flex-shrink: 0;
        width: 1.25rem;
        height: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 0.875rem;
        color: var(--white-color);
        margin-top: 0.125rem;
      }

      .notification.success .notification-icon {
        background: var(--success-color);
      }

      .notification.error .notification-icon {
        background: var(--error-color);
      }

      .notification.warning .notification-icon {
        background: var(--warning-color);
      }

      .notification.info .notification-icon {
        background: var(--info-color);
      }

      .notification-content {
        flex: 1;
        min-width: 0;
      }

      .notification-title {
        font-weight: var(--font-semi-bold);
        color: var(--text-color);
        margin-bottom: 0.25rem;
        font-size: var(--small-font-size);
        line-height: 1.3;
      }

      .notification-message {
        color: var(--text-color-light);
        font-size: var(--smaller-font-size);
        line-height: 1.4;
        word-wrap: break-word;
      }

      .notification-close {
        flex-shrink: 0;
        background: none;
        border: none;
        color: var(--text-color-light);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: var(--border-radius-sm);
        transition: var(--transition-fast);
        margin-top: -0.125rem;
        margin-right: -0.25rem;
      }

      .notification-close:hover {
        color: var(--text-color);
        background: var(--gray-color);
      }

      @keyframes notificationProgress {
        from {
          width: 100%;
        }
        to {
          width: 0%;
        }
      }

      /* Mobile adjustments */
      @media screen and (max-width: 480px) {
        .notification-container {
          top: 0.5rem;
          right: 0.5rem;
          left: 0.5rem;
          max-width: none;
        }

        .notification {
          margin-bottom: 0.5rem;
          padding: 0.875rem 1rem;
        }
      }

      /* Reduced motion */
      @media (prefers-reduced-motion: reduce) {
        .notification {
          transition: none;
        }

        .notification::before {
          animation: none;
        }
      }
    `;

    const styleSheet = DOMUtils.createElement('style', {
      id: 'notification-styles'
    }, styles);

    document.head.appendChild(styleSheet);
  }

  /**
   * Show notification
   * @param {string} message - Notification message
   * @param {string} type - Notification type (success, error, warning, info)
   * @param {Object} options - Additional options
   */
  show(message, type = 'info', options = {}) {
    const config = {
      title: options.title || this.getDefaultTitle(type),
      duration: options.duration || APP_CONFIG.NOTIFICATIONS.DURATION[type.toUpperCase()] || 3000,
      closable: options.closable !== false,
      persistent: options.persistent === true,
      ...options
    };

    const notification = this.createNotification(message, type, config);
    this.container.appendChild(notification);

    // Show notification with animation
    requestAnimationFrame(() => {
      notification.classList.add('show');
    });

    // Auto-hide if not persistent
    if (!config.persistent && config.duration > 0) {
      const progressBar = notification.querySelector('::before');
      if (progressBar) {
        progressBar.style.animationDuration = `${config.duration}ms`;
      }

      setTimeout(() => {
        this.hide(notification.id);
      }, config.duration);
    }

    return notification.id;
  }

  /**
   * Create notification element
   * @param {string} message - Notification message
   * @param {string} type - Notification type
   * @param {Object} config - Configuration options
   * @returns {Element} Notification element
   */
  createNotification(message, type, config) {
    const id = `notification-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    const notification = DOMUtils.createElement('div', {
      id: id,
      className: `notification ${type}`,
      role: type === 'error' ? 'alert' : 'status',
      'aria-live': type === 'error' ? 'assertive' : 'polite'
    });

    // Icon
    const icon = DOMUtils.createElement('div', {
      className: 'notification-icon',
      'aria-hidden': 'true'
    }, `<i class="${this.getIcon(type)}"></i>`);

    // Content
    const content = DOMUtils.createElement('div', {
      className: 'notification-content'
    });

    if (config.title) {
      const title = DOMUtils.createElement('div', {
        className: 'notification-title'
      }, config.title);
      content.appendChild(title);
    }

    const messageEl = DOMUtils.createElement('div', {
      className: 'notification-message'
    }, message);
    content.appendChild(messageEl);

    // Close button
    let closeBtn = null;
    if (config.closable) {
      closeBtn = DOMUtils.createElement('button', {
        className: 'notification-close',
        'aria-label': 'Close notification',
        type: 'button'
      }, '<i class="ri-close-line"></i>');

      DOMUtils.addEventListener(closeBtn, 'click', () => {
        this.hide(id);
      });
    }

    // Assemble notification
    notification.appendChild(icon);
    notification.appendChild(content);
    if (closeBtn) {
      notification.appendChild(closeBtn);
    }

    // Store notification reference
    this.notifications.set(id, {
      element: notification,
      type: type,
      config: config
    });

    return notification;
  }

  /**
   * Hide notification
   * @param {string} id - Notification ID
   */
  hide(id) {
    const notificationData = this.notifications.get(id);
    if (!notificationData) return;

    const notification = notificationData.element;
    
    notification.classList.remove('show');
    notification.classList.add('hide');

    // Remove from DOM after animation
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
      this.notifications.delete(id);
    }, 300);
  }

  /**
   * Hide all notifications
   */
  hideAll() {
    this.notifications.forEach((_, id) => {
      this.hide(id);
    });
  }

  /**
   * Get default title for notification type
   * @param {string} type - Notification type
   * @returns {string} Default title
   */
  getDefaultTitle(type) {
    const titles = {
      success: 'Success',
      error: 'Error',
      warning: 'Warning',
      info: 'Information'
    };
    return titles[type] || 'Notification';
  }

  /**
   * Get icon for notification type
   * @param {string} type - Notification type
   * @returns {string} Icon class
   */
  getIcon(type) {
    const icons = {
      success: 'ri-check-line',
      error: 'ri-error-warning-line',
      warning: 'ri-alert-line',
      info: 'ri-information-line'
    };
    return icons[type] || 'ri-notification-line';
  }

  /**
   * Show success notification
   * @param {string} message - Message
   * @param {Object} options - Options
   */
  success(message, options = {}) {
    return this.show(message, 'success', options);
  }

  /**
   * Show error notification
   * @param {string} message - Message
   * @param {Object} options - Options
   */
  error(message, options = {}) {
    return this.show(message, 'error', options);
  }

  /**
   * Show warning notification
   * @param {string} message - Message
   * @param {Object} options - Options
   */
  warning(message, options = {}) {
    return this.show(message, 'warning', options);
  }

  /**
   * Show info notification
   * @param {string} message - Message
   * @param {Object} options - Options
   */
  info(message, options = {}) {
    return this.show(message, 'info', options);
  }
}

// Initialize notification manager
const notificationManager = new NotificationManager();

// Export for global access
window.NotificationManager = notificationManager;
window.notify = {
  show: (message, type, options) => notificationManager.show(message, type, options),
  success: (message, options) => notificationManager.success(message, options),
  error: (message, options) => notificationManager.error(message, options),
  warning: (message, options) => notificationManager.warning(message, options),
  info: (message, options) => notificationManager.info(message, options),
  hideAll: () => notificationManager.hideAll()
};