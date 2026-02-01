/**
 * Utility functions for Riya Collections Frontend
 * Common helper functions used throughout the application
 */

// DOM Utilities
const DOMUtils = {
  /**
   * Get element by ID
   * @param {string} id - Element ID
   * @returns {Element|null} DOM element
   */
  getId(id) {
    return document.getElementById(id);
  },

  /**
   * Get element by selector
   * @param {string} selector - CSS selector
   * @param {Element} parent - Parent element (optional)
   * @returns {Element|null} DOM element
   */
  getElement(selector, parent = document) {
    return parent.querySelector(selector);
  },

  /**
   * Get elements by selector
   * @param {string} selector - CSS selector
   * @param {Element} parent - Parent element (optional)
   * @returns {NodeList} DOM elements
   */
  getElements(selector, parent = document) {
    return parent.querySelectorAll(selector);
  },

  /**
   * Create element with attributes
   * @param {string} tag - HTML tag
   * @param {Object} attributes - Element attributes
   * @param {string} content - Element content
   * @returns {Element} Created element
   */
  createElement(tag, attributes = {}, content = '') {
    const element = document.createElement(tag);
    
    Object.keys(attributes).forEach(key => {
      if (key === 'className') {
        element.className = attributes[key];
      } else if (key === 'dataset') {
        Object.keys(attributes[key]).forEach(dataKey => {
          element.dataset[dataKey] = attributes[key][dataKey];
        });
      } else {
        element.setAttribute(key, attributes[key]);
      }
    });
    
    if (content) {
      element.innerHTML = content;
    }
    
    return element;
  },

  /**
   * Add event listener with cleanup
   * @param {Element} element - Target element
   * @param {string} event - Event type
   * @param {Function} handler - Event handler
   * @param {Object} options - Event options
   * @returns {Function} Cleanup function
   */
  addEventListener(element, event, handler, options = {}) {
    element.addEventListener(event, handler, options);
    
    return () => {
      element.removeEventListener(event, handler, options);
    };
  },

  /**
   * Toggle class on element
   * @param {Element} element - Target element
   * @param {string} className - Class name
   * @param {boolean} force - Force add/remove
   */
  toggleClass(element, className, force) {
    if (force !== undefined) {
      element.classList.toggle(className, force);
    } else {
      element.classList.toggle(className);
    }
  },

  /**
   * Check if element has class
   * @param {Element} element - Target element
   * @param {string} className - Class name
   * @returns {boolean} Has class
   */
  hasClass(element, className) {
    return element.classList.contains(className);
  },

  /**
   * Get element offset from document
   * @param {Element} element - Target element
   * @returns {Object} Offset coordinates
   */
  getOffset(element) {
    const rect = element.getBoundingClientRect();
    return {
      top: rect.top + window.pageYOffset,
      left: rect.left + window.pageXOffset,
      width: rect.width,
      height: rect.height
    };
  },

  /**
   * Smooth scroll to element
   * @param {Element|string} target - Target element or selector
   * @param {number} offset - Offset from target
   */
  scrollTo(target, offset = 0) {
    const element = typeof target === 'string' ? 
      this.getElement(target) : target;
    
    if (element) {
      const targetPosition = this.getOffset(element).top - offset;
      window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
      });
    }
  },

  /**
   * Check if element is in viewport
   * @param {Element} element - Target element
   * @param {number} threshold - Visibility threshold (0-1)
   * @returns {boolean} Is visible
   */
  isInViewport(element, threshold = 0) {
    const rect = element.getBoundingClientRect();
    const windowHeight = window.innerHeight || document.documentElement.clientHeight;
    const windowWidth = window.innerWidth || document.documentElement.clientWidth;
    
    const vertInView = (rect.top <= windowHeight) && ((rect.top + rect.height) >= 0);
    const horInView = (rect.left <= windowWidth) && ((rect.left + rect.width) >= 0);
    
    return vertInView && horInView;
  }
};

// Format Utilities
const FormatUtils = {
  /**
   * Format currency
   * @param {number} amount - Amount to format
   * @param {string} currency - Currency code
   * @returns {string} Formatted currency
   */
  currency(amount, currency = 'INR') {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2
    }).format(amount);
  },

  /**
   * Format number with commas
   * @param {number} number - Number to format
   * @returns {string} Formatted number
   */
  number(number) {
    return new Intl.NumberFormat('en-IN').format(number);
  },

  /**
   * Format date
   * @param {Date|string} date - Date to format
   * @param {Object} options - Format options
   * @returns {string} Formatted date
   */
  date(date, options = {}) {
    const defaultOptions = {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    };
    
    const formatOptions = { ...defaultOptions, ...options };
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    
    return new Intl.DateTimeFormat('en-IN', formatOptions).format(dateObj);
  },

  /**
   * Format relative time
   * @param {Date|string} date - Date to format
   * @returns {string} Relative time
   */
  relativeTime(date) {
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    const now = new Date();
    const diffInSeconds = Math.floor((now - dateObj) / 1000);
    
    const intervals = {
      year: 31536000,
      month: 2592000,
      week: 604800,
      day: 86400,
      hour: 3600,
      minute: 60
    };
    
    for (const [unit, seconds] of Object.entries(intervals)) {
      const interval = Math.floor(diffInSeconds / seconds);
      if (interval >= 1) {
        return `${interval} ${unit}${interval > 1 ? 's' : ''} ago`;
      }
    }
    
    return 'Just now';
  },

  /**
   * Truncate text
   * @param {string} text - Text to truncate
   * @param {number} length - Maximum length
   * @param {string} suffix - Suffix for truncated text
   * @returns {string} Truncated text
   */
  truncate(text, length = 100, suffix = '...') {
    if (text.length <= length) return text;
    return text.substring(0, length).trim() + suffix;
  },

  /**
   * Capitalize first letter
   * @param {string} text - Text to capitalize
   * @returns {string} Capitalized text
   */
  capitalize(text) {
    return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
  },

  /**
   * Convert to title case
   * @param {string} text - Text to convert
   * @returns {string} Title case text
   */
  titleCase(text) {
    return text.replace(/\w\S*/g, (txt) => 
      txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
    );
  },

  /**
   * Generate slug from text
   * @param {string} text - Text to convert
   * @returns {string} URL-friendly slug
   */
  slug(text) {
    return text
      .toLowerCase()
      .replace(/[^\w ]+/g, '')
      .replace(/ +/g, '-');
  }
};

// Validation Utilities
const ValidationUtils = {
  /**
   * Validate email address
   * @param {string} email - Email to validate
   * @returns {boolean} Is valid email
   */
  email(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  },

  /**
   * Validate phone number (Indian format)
   * @param {string} phone - Phone number to validate
   * @returns {boolean} Is valid phone
   */
  phone(phone) {
    const phoneRegex = /^[6-9]\d{9}$/;
    return phoneRegex.test(phone.replace(/\D/g, ''));
  },

  /**
   * Validate password strength
   * @param {string} password - Password to validate
   * @returns {Object} Validation result
   */
  password(password) {
    const result = {
      isValid: false,
      score: 0,
      feedback: []
    };

    if (password.length < 8) {
      result.feedback.push('Password must be at least 8 characters long');
    } else {
      result.score += 1;
    }

    if (!/[a-z]/.test(password)) {
      result.feedback.push('Password must contain lowercase letters');
    } else {
      result.score += 1;
    }

    if (!/[A-Z]/.test(password)) {
      result.feedback.push('Password must contain uppercase letters');
    } else {
      result.score += 1;
    }

    if (!/\d/.test(password)) {
      result.feedback.push('Password must contain numbers');
    } else {
      result.score += 1;
    }

    if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
      result.feedback.push('Password must contain special characters');
    } else {
      result.score += 1;
    }

    result.isValid = result.score >= 3;
    return result;
  },

  /**
   * Validate required field
   * @param {*} value - Value to validate
   * @returns {boolean} Is valid
   */
  required(value) {
    if (typeof value === 'string') {
      return value.trim().length > 0;
    }
    return value !== null && value !== undefined;
  },

  /**
   * Validate minimum length
   * @param {string} value - Value to validate
   * @param {number} minLength - Minimum length
   * @returns {boolean} Is valid
   */
  minLength(value, minLength) {
    return value && value.length >= minLength;
  },

  /**
   * Validate maximum length
   * @param {string} value - Value to validate
   * @param {number} maxLength - Maximum length
   * @returns {boolean} Is valid
   */
  maxLength(value, maxLength) {
    return !value || value.length <= maxLength;
  }
};

// Animation Utilities
const AnimationUtils = {
  /**
   * Animate number counter
   * @param {Element} element - Target element
   * @param {number} start - Start value
   * @param {number} end - End value
   * @param {number} duration - Animation duration
   */
  animateCounter(element, start, end, duration = 2000) {
    const startTime = performance.now();
    const difference = end - start;

    const step = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Easing function (ease-out)
      const easeOut = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(start + (difference * easeOut));
      
      element.textContent = FormatUtils.number(current);
      
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        element.textContent = FormatUtils.number(end);
      }
    };

    requestAnimationFrame(step);
  },

  /**
   * Fade in element
   * @param {Element} element - Target element
   * @param {number} duration - Animation duration
   * @returns {Promise} Animation promise
   */
  fadeIn(element, duration = 300) {
    return new Promise((resolve) => {
      element.style.opacity = '0';
      element.style.display = 'block';
      
      const start = performance.now();
      
      const animate = (currentTime) => {
        const elapsed = currentTime - start;
        const progress = Math.min(elapsed / duration, 1);
        
        element.style.opacity = progress;
        
        if (progress < 1) {
          requestAnimationFrame(animate);
        } else {
          resolve();
        }
      };
      
      requestAnimationFrame(animate);
    });
  },

  /**
   * Fade out element
   * @param {Element} element - Target element
   * @param {number} duration - Animation duration
   * @returns {Promise} Animation promise
   */
  fadeOut(element, duration = 300) {
    return new Promise((resolve) => {
      const start = performance.now();
      const startOpacity = parseFloat(getComputedStyle(element).opacity);
      
      const animate = (currentTime) => {
        const elapsed = currentTime - start;
        const progress = Math.min(elapsed / duration, 1);
        
        element.style.opacity = startOpacity * (1 - progress);
        
        if (progress < 1) {
          requestAnimationFrame(animate);
        } else {
          element.style.display = 'none';
          resolve();
        }
      };
      
      requestAnimationFrame(animate);
    });
  },

  /**
   * Slide down element
   * @param {Element} element - Target element
   * @param {number} duration - Animation duration
   * @returns {Promise} Animation promise
   */
  slideDown(element, duration = 300) {
    return new Promise((resolve) => {
      element.style.height = '0';
      element.style.overflow = 'hidden';
      element.style.display = 'block';
      
      const targetHeight = element.scrollHeight;
      const start = performance.now();
      
      const animate = (currentTime) => {
        const elapsed = currentTime - start;
        const progress = Math.min(elapsed / duration, 1);
        
        element.style.height = `${targetHeight * progress}px`;
        
        if (progress < 1) {
          requestAnimationFrame(animate);
        } else {
          element.style.height = '';
          element.style.overflow = '';
          resolve();
        }
      };
      
      requestAnimationFrame(animate);
    });
  },

  /**
   * Slide up element
   * @param {Element} element - Target element
   * @param {number} duration - Animation duration
   * @returns {Promise} Animation promise
   */
  slideUp(element, duration = 300) {
    return new Promise((resolve) => {
      const startHeight = element.offsetHeight;
      element.style.height = `${startHeight}px`;
      element.style.overflow = 'hidden';
      
      const start = performance.now();
      
      const animate = (currentTime) => {
        const elapsed = currentTime - start;
        const progress = Math.min(elapsed / duration, 1);
        
        element.style.height = `${startHeight * (1 - progress)}px`;
        
        if (progress < 1) {
          requestAnimationFrame(animate);
        } else {
          element.style.display = 'none';
          element.style.height = '';
          element.style.overflow = '';
          resolve();
        }
      };
      
      requestAnimationFrame(animate);
    });
  }
};

// Debounce and Throttle Utilities
const TimingUtils = {
  /**
   * Debounce function execution
   * @param {Function} func - Function to debounce
   * @param {number} delay - Delay in milliseconds
   * @returns {Function} Debounced function
   */
  debounce(func, delay) {
    let timeoutId;
    return function (...args) {
      clearTimeout(timeoutId);
      timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
  },

  /**
   * Throttle function execution
   * @param {Function} func - Function to throttle
   * @param {number} delay - Delay in milliseconds
   * @returns {Function} Throttled function
   */
  throttle(func, delay) {
    let lastCall = 0;
    return function (...args) {
      const now = Date.now();
      if (now - lastCall >= delay) {
        lastCall = now;
        func.apply(this, args);
      }
    };
  }
};

// Image Utilities
const ImageUtils = {
  /**
   * Lazy load image
   * @param {Element} img - Image element
   * @param {string} src - Image source
   * @returns {Promise} Load promise
   */
  lazyLoad(img, src) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      
      image.onload = () => {
        img.src = src;
        img.classList.add('loaded');
        resolve();
      };
      
      image.onerror = reject;
      image.src = src;
    });
  },

  /**
   * Get optimized image URL
   * @param {string} url - Original image URL
   * @param {string} size - Size variant (small, medium, large)
   * @returns {string} Optimized URL
   */
  getOptimizedUrl(url, size = 'medium') {
    if (!url) return APP_CONFIG.IMAGES.PLACEHOLDER;
    
    // If it's already a full URL, return as is
    if (url.startsWith('http')) return url;
    
    // For local images, add size parameter
    const baseUrl = url.replace(/\.[^/.]+$/, '');
    const extension = url.split('.').pop();
    
    return `${baseUrl}_${size}.${extension}`;
  },

  /**
   * Create image placeholder
   * @param {number} width - Placeholder width
   * @param {number} height - Placeholder height
   * @param {string} text - Placeholder text
   * @returns {string} Data URL
   */
  createPlaceholder(width = 300, height = 200, text = '') {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    canvas.width = width;
    canvas.height = height;
    
    // Background
    ctx.fillStyle = '#f0f0f0';
    ctx.fillRect(0, 0, width, height);
    
    // Text
    if (text) {
      ctx.fillStyle = '#999';
      ctx.font = '16px Arial';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(text, width / 2, height / 2);
    }
    
    return canvas.toDataURL();
  }
};

// Export utilities
window.DOMUtils = DOMUtils;
window.FormatUtils = FormatUtils;
window.ValidationUtils = ValidationUtils;
window.AnimationUtils = AnimationUtils;
window.TimingUtils = TimingUtils;
window.ImageUtils = ImageUtils;

// Common utility functions
window.Utils = {
  ...DOMUtils,
  ...FormatUtils,
  ...ValidationUtils,
  ...AnimationUtils,
  ...TimingUtils,
  ...ImageUtils
};