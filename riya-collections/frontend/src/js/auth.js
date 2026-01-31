/**
 * Authentication functionality for Riya Collections
 * Handles login, registration, and form validation
 */

class AuthManager {
  constructor() {
    this.isInitialized = false;
    this.currentPage = this.detectCurrentPage();
    this.init();
  }

  /**
   * Initialize authentication manager
   */
  init() {
    if (this.isInitialized) return;

    this.setupEventListeners();
    this.initializePasswordToggles();
    this.initializeFormValidation();
    
    if (this.currentPage === 'register') {
      this.initializePasswordStrength();
    }

    this.checkExistingAuth();
    this.isInitialized = true;

    if (window.IS_DEVELOPMENT) {
      console.log('AuthManager initialized for page:', this.currentPage);
    }
  }

  /**
   * Detect current page type
   * @returns {string} Page type
   */
  detectCurrentPage() {
    const path = window.location.pathname;
    if (path.includes('login')) return 'login';
    if (path.includes('register')) return 'register';
    return 'unknown';
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Form submissions
    const loginForm = DOMUtils.getId('login-form');
    const registerForm = DOMUtils.getId('register-form');

    if (loginForm) {
      DOMUtils.addEventListener(loginForm, 'submit', (e) => {
        e.preventDefault();
        this.handleLogin(e);
      });
    }

    if (registerForm) {
      DOMUtils.addEventListener(registerForm, 'submit', (e) => {
        e.preventDefault();
        this.handleRegister(e);
      });
    }

    // Real-time validation
    this.setupRealTimeValidation();

    // Social login buttons (if enabled)
    if (FEATURES.SOCIAL_LOGIN) {
      this.setupSocialLogin();
    }
  }

  /**
   * Setup real-time form validation
   */
  setupRealTimeValidation() {
    const inputs = DOMUtils.getElements('.form-input');
    
    inputs.forEach(input => {
      // Validate on blur
      DOMUtils.addEventListener(input, 'blur', () => {
        this.validateField(input);
      });

      // Clear errors on input
      DOMUtils.addEventListener(input, 'input', () => {
        this.clearFieldError(input);
        
        // Special handling for password confirmation
        if (input.name === 'confirmPassword') {
          this.validatePasswordMatch();
        }
      });
    });
  }

  /**
   * Initialize password toggle functionality
   */
  initializePasswordToggles() {
    const toggles = DOMUtils.getElements('.form-input-toggle');
    
    toggles.forEach(toggle => {
      DOMUtils.addEventListener(toggle, 'click', (e) => {
        e.preventDefault();
        this.togglePasswordVisibility(toggle);
      });
    });
  }

  /**
   * Toggle password visibility
   * @param {Element} toggle - Toggle button
   */
  togglePasswordVisibility(toggle) {
    const input = toggle.parentElement.querySelector('.form-input');
    const icon = toggle.querySelector('i');
    
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'ri-eye-off-line';
      toggle.setAttribute('aria-label', 'Hide password');
    } else {
      input.type = 'password';
      icon.className = 'ri-eye-line';
      toggle.setAttribute('aria-label', 'Show password');
    }
  }

  /**
   * Initialize password strength indicator
   */
  initializePasswordStrength() {
    const passwordInput = DOMUtils.getId('password');
    const strengthContainer = DOMUtils.getId('password-strength');
    
    if (!passwordInput || !strengthContainer) return;

    DOMUtils.addEventListener(passwordInput, 'input', () => {
      this.updatePasswordStrength(passwordInput.value);
    });

    DOMUtils.addEventListener(passwordInput, 'focus', () => {
      strengthContainer.style.display = 'block';
    });
  }

  /**
   * Update password strength indicator
   * @param {string} password - Password value
   */
  updatePasswordStrength(password) {
    const strengthFill = DOMUtils.getId('strength-fill');
    const strengthText = DOMUtils.getId('strength-text');
    const requirements = DOMUtils.getElements('.requirement');
    
    if (!strengthFill || !strengthText) return;

    const validation = ValidationUtils.password(password);
    const score = validation.score;
    
    // Update strength bar
    strengthFill.className = 'strength-fill';
    
    if (score === 0) {
      strengthFill.classList.add('weak');
      strengthText.textContent = 'Very Weak';
    } else if (score === 1) {
      strengthFill.classList.add('weak');
      strengthText.textContent = 'Weak';
    } else if (score === 2) {
      strengthFill.classList.add('fair');
      strengthText.textContent = 'Fair';
    } else if (score === 3) {
      strengthFill.classList.add('good');
      strengthText.textContent = 'Good';
    } else if (score === 4) {
      strengthFill.classList.add('strong');
      strengthText.textContent = 'Strong';
    } else if (score === 5) {
      strengthFill.classList.add('very-strong');
      strengthText.textContent = 'Very Strong';
    }

    // Update requirements
    this.updatePasswordRequirements(password, requirements);
  }

  /**
   * Update password requirements display
   * @param {string} password - Password value
   * @param {NodeList} requirements - Requirement elements
   */
  updatePasswordRequirements(password, requirements) {
    const checks = {
      length: password.length >= 8,
      lowercase: /[a-z]/.test(password),
      uppercase: /[A-Z]/.test(password),
      number: /\d/.test(password),
      special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };

    requirements.forEach(req => {
      const requirement = req.dataset.requirement;
      const icon = req.querySelector('i');
      
      if (checks[requirement]) {
        req.classList.add('met');
        icon.className = 'ri-check-circle-line';
      } else {
        req.classList.remove('met');
        icon.className = 'ri-close-circle-line';
      }
    });
  }

  /**
   * Initialize form validation
   */
  initializeFormValidation() {
    // Add validation rules
    this.validationRules = {
      firstName: {
        required: true,
        minLength: 2,
        maxLength: 50,
        pattern: /^[a-zA-Z\s]+$/
      },
      lastName: {
        required: true,
        minLength: 2,
        maxLength: 50,
        pattern: /^[a-zA-Z\s]+$/
      },
      email: {
        required: true,
        email: true
      },
      phone: {
        required: false,
        phone: true
      },
      password: {
        required: true,
        minLength: 8,
        password: true
      },
      confirmPassword: {
        required: true,
        match: 'password'
      },
      termsAgreement: {
        required: true,
        checked: true
      }
    };
  }

  /**
   * Validate individual field
   * @param {Element} field - Input field
   * @returns {boolean} Is valid
   */
  validateField(field) {
    const name = field.name;
    const value = field.value.trim();
    const rules = this.validationRules[name];
    
    if (!rules) return true;

    const errors = [];

    // Required validation
    if (rules.required && !ValidationUtils.required(value)) {
      errors.push(ERROR_MESSAGES.REQUIRED_FIELD);
    }

    // Skip other validations if field is empty and not required
    if (!value && !rules.required) {
      this.clearFieldError(field);
      return true;
    }

    // Email validation
    if (rules.email && value && !ValidationUtils.email(value)) {
      errors.push(ERROR_MESSAGES.INVALID_EMAIL);
    }

    // Phone validation
    if (rules.phone && value && !ValidationUtils.phone(value)) {
      errors.push(ERROR_MESSAGES.INVALID_PHONE);
    }

    // Length validation
    if (rules.minLength && !ValidationUtils.minLength(value, rules.minLength)) {
      errors.push(`Must be at least ${rules.minLength} characters long`);
    }

    if (rules.maxLength && !ValidationUtils.maxLength(value, rules.maxLength)) {
      errors.push(`Must be no more than ${rules.maxLength} characters long`);
    }

    // Pattern validation
    if (rules.pattern && value && !rules.pattern.test(value)) {
      if (name === 'firstName' || name === 'lastName') {
        errors.push('Only letters and spaces are allowed');
      }
    }

    // Password validation
    if (rules.password && value) {
      const passwordValidation = ValidationUtils.password(value);
      if (!passwordValidation.isValid) {
        errors.push(ERROR_MESSAGES.PASSWORD_TOO_SHORT);
      }
    }

    // Match validation (for password confirmation)
    if (rules.match) {
      const matchField = DOMUtils.getElement(`[name="${rules.match}"]`);
      if (matchField && value !== matchField.value) {
        errors.push(ERROR_MESSAGES.PASSWORDS_DONT_MATCH);
      }
    }

    // Checkbox validation
    if (rules.checked && field.type === 'checkbox' && !field.checked) {
      errors.push('This field is required');
    }

    // Display errors
    if (errors.length > 0) {
      this.showFieldError(field, errors[0]);
      return false;
    } else {
      this.clearFieldError(field);
      return true;
    }
  }

  /**
   * Validate password match
   */
  validatePasswordMatch() {
    const passwordField = DOMUtils.getId('password');
    const confirmField = DOMUtils.getId('confirm-password');
    
    if (!passwordField || !confirmField) return;

    if (confirmField.value && passwordField.value !== confirmField.value) {
      this.showFieldError(confirmField, ERROR_MESSAGES.PASSWORDS_DONT_MATCH);
    } else {
      this.clearFieldError(confirmField);
    }
  }

  /**
   * Show field error
   * @param {Element} field - Input field
   * @param {string} message - Error message
   */
  showFieldError(field, message) {
    const errorElement = DOMUtils.getElement(`#${field.name}-error`);
    const formGroup = field.closest('.form-group');
    
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.innerHTML = `<i class="ri-error-warning-line"></i>${message}`;
    }
    
    if (formGroup) {
      formGroup.classList.add('has-error');
    }
    
    field.classList.add('invalid');
    field.classList.remove('valid');
  }

  /**
   * Clear field error
   * @param {Element} field - Input field
   */
  clearFieldError(field) {
    const errorElement = DOMUtils.getElement(`#${field.name}-error`);
    const formGroup = field.closest('.form-group');
    
    if (errorElement) {
      errorElement.textContent = '';
    }
    
    if (formGroup) {
      formGroup.classList.remove('has-error');
    }
    
    field.classList.remove('invalid');
    
    if (field.value.trim()) {
      field.classList.add('valid');
    } else {
      field.classList.remove('valid');
    }
  }

  /**
   * Validate entire form
   * @param {Element} form - Form element
   * @returns {boolean} Is valid
   */
  validateForm(form) {
    const inputs = form.querySelectorAll('.form-input, input[type="checkbox"]');
    let isValid = true;

    inputs.forEach(input => {
      if (!this.validateField(input)) {
        isValid = false;
      }
    });

    return isValid;
  }

  /**
   * Handle login form submission
   * @param {Event} event - Submit event
   */
  async handleLogin(event) {
    const form = event.target;
    const submitBtn = DOMUtils.getId('login-btn');
    
    // Validate form
    if (!this.validateForm(form)) {
      return;
    }

    // Get form data
    const formData = new FormData(form);
    const loginData = {
      email: formData.get('email'),
      password: formData.get('password')
    };

    try {
      // Show loading state
      this.setLoadingState(submitBtn, true);
      form.classList.add('loading');

      // Make API request
      const response = await ApiClient.prototype.request(API_CONFIG.ENDPOINTS.AUTH.LOGIN, {
        method: 'POST',
        body: JSON.stringify(loginData)
      });

      if (response.success) {
        // Store authentication data
        this.handleAuthSuccess(response.data);
        
        // Show success message
        if (window.NotificationManager) {
          NotificationManager.show('Login successful! Redirecting...', 'success');
        }

        // Redirect after short delay
        setTimeout(() => {
          this.redirectAfterAuth();
        }, 1000);
      }

    } catch (error) {
      console.error('Login error:', error);
      
      // Show error message
      if (window.NotificationManager) {
        NotificationManager.show(error.message || ERROR_MESSAGES.GENERIC, 'error');
      }
      
      // Focus on email field for retry
      DOMUtils.getId('email')?.focus();
      
    } finally {
      // Hide loading state
      this.setLoadingState(submitBtn, false);
      form.classList.remove('loading');
    }
  }

  /**
   * Handle registration form submission
   * @param {Event} event - Submit event
   */
  async handleRegister(event) {
    const form = event.target;
    const submitBtn = DOMUtils.getId('register-btn');
    
    // Validate form
    if (!this.validateForm(form)) {
      return;
    }

    // Get form data
    const formData = new FormData(form);
    const registerData = {
      firstName: formData.get('firstName'),
      lastName: formData.get('lastName'),
      email: formData.get('email'),
      password: formData.get('password'),
      phone: formData.get('phone') || null
    };

    try {
      // Show loading state
      this.setLoadingState(submitBtn, true);
      form.classList.add('loading');

      // Make API request
      const response = await ApiClient.prototype.request(API_CONFIG.ENDPOINTS.AUTH.REGISTER, {
        method: 'POST',
        body: JSON.stringify(registerData)
      });

      if (response.success) {
        // Store authentication data
        this.handleAuthSuccess(response.data);
        
        // Show success message
        if (window.NotificationManager) {
          NotificationManager.show('Account created successfully! Welcome to Riya Collections!', 'success');
        }

        // Redirect after short delay
        setTimeout(() => {
          this.redirectAfterAuth();
        }, 1500);
      }

    } catch (error) {
      console.error('Registration error:', error);
      
      // Show error message
      if (window.NotificationManager) {
        NotificationManager.show(error.message || ERROR_MESSAGES.GENERIC, 'error');
      }
      
      // Focus on first error field
      const errorField = form.querySelector('.form-input.invalid');
      if (errorField) {
        errorField.focus();
      }
      
    } finally {
      // Hide loading state
      this.setLoadingState(submitBtn, false);
      form.classList.remove('loading');
    }
  }

  /**
   * Handle successful authentication
   * @param {Object} data - Auth response data
   */
  handleAuthSuccess(data) {
    // Store tokens
    if (data.tokens) {
      CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, data.tokens.accessToken);
      
      if (data.tokens.refreshToken) {
        CONFIG_UTILS.setStorageItem('refresh_token', data.tokens.refreshToken);
      }
    }

    // Store user data
    if (data.user) {
      CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, data.user);
    }

    // Update UI state
    this.updateAuthState(true, data.user);
  }

  /**
   * Set loading state for button
   * @param {Element} button - Button element
   * @param {boolean} loading - Loading state
   */
  setLoadingState(button, loading) {
    const btnText = button.querySelector('.btn-text');
    const btnLoading = button.querySelector('.btn-loading');
    
    if (loading) {
      button.disabled = true;
      btnText.style.display = 'none';
      btnLoading.style.display = 'flex';
    } else {
      button.disabled = false;
      btnText.style.display = 'block';
      btnLoading.style.display = 'none';
    }
  }

  /**
   * Update authentication state
   * @param {boolean} isAuthenticated - Auth state
   * @param {Object} user - User data
   */
  updateAuthState(isAuthenticated, user = null) {
    // Update global auth state
    window.isAuthenticated = isAuthenticated;
    window.currentUser = user;

    // Dispatch auth state change event
    const event = new CustomEvent('authStateChanged', {
      detail: { isAuthenticated, user }
    });
    document.dispatchEvent(event);
  }

  /**
   * Redirect after successful authentication
   */
  redirectAfterAuth() {
    // Check for redirect URL in query params
    const urlParams = new URLSearchParams(window.location.search);
    const redirectUrl = urlParams.get('redirect');
    
    if (redirectUrl) {
      // Validate redirect URL for security
      try {
        const url = new URL(redirectUrl, window.location.origin);
        if (url.origin === window.location.origin) {
          window.location.href = redirectUrl;
          return;
        }
      } catch (e) {
        // Invalid URL, fall through to default
      }
    }

    // Default redirect to home page
    window.location.href = '../index.html';
  }

  /**
   * Check for existing authentication
   */
  checkExistingAuth() {
    const token = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    const user = CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
    
    if (token && user) {
      // User is already authenticated, redirect to home
      if (window.location.pathname.includes('login') || window.location.pathname.includes('register')) {
        window.location.href = '../index.html';
      }
    }
  }

  /**
   * Setup social login (if enabled)
   */
  setupSocialLogin() {
    const googleBtn = DOMUtils.getId('google-login') || DOMUtils.getId('google-register');
    const facebookBtn = DOMUtils.getId('facebook-login') || DOMUtils.getId('facebook-register');
    
    if (googleBtn) {
      DOMUtils.addEventListener(googleBtn, 'click', () => {
        this.handleSocialLogin('google');
      });
    }
    
    if (facebookBtn) {
      DOMUtils.addEventListener(facebookBtn, 'click', () => {
        this.handleSocialLogin('facebook');
      });
    }
  }

  /**
   * Handle social login
   * @param {string} provider - Social provider
   */
  handleSocialLogin(provider) {
    if (window.NotificationManager) {
      NotificationManager.show('Social login coming soon!', 'info');
    }
  }

  /**
   * Logout user
   */
  async logout() {
    try {
      // Call logout API
      await ApiClient.prototype.request(API_CONFIG.ENDPOINTS.AUTH.LOGOUT, {
        method: 'POST'
      });
    } catch (error) {
      console.warn('Logout API error:', error);
    } finally {
      // Clear local storage
      CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
      CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
      CONFIG_UTILS.removeStorageItem('refresh_token');
      
      // Update auth state
      this.updateAuthState(false);
      
      // Redirect to login
      window.location.href = 'login.html';
    }
  }
}

// Initialize authentication manager when DOM is ready
DOMUtils.addEventListener(document, 'DOMContentLoaded', () => {
  window.authManager = new AuthManager();
});

// Export for global access
window.AuthManager = AuthManager;