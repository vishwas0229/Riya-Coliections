/**
 * Admin Authentication JavaScript
 * Handles admin login functionality
 */

class AdminAuth {
  constructor() {
    this.form = document.getElementById('adminLoginForm');
    this.emailInput = document.getElementById('email');
    this.passwordInput = document.getElementById('password');
    this.passwordToggle = document.getElementById('passwordToggle');
    this.rememberMeCheckbox = document.getElementById('rememberMe');
    this.loginBtn = document.getElementById('loginBtn');
    
    this.init();
  }

  init() {
    this.bindEvents();
    this.checkExistingAuth();
    this.loadRememberedCredentials();
  }

  bindEvents() {
    // Form submission
    this.form.addEventListener('submit', (e) => this.handleLogin(e));
    
    // Password toggle
    this.passwordToggle.addEventListener('click', () => this.togglePassword());
    
    // Real-time validation
    this.emailInput.addEventListener('blur', () => this.validateEmail());
    this.passwordInput.addEventListener('blur', () => this.validatePassword());
    
    // Clear errors on input
    this.emailInput.addEventListener('input', () => this.clearError('emailError'));
    this.passwordInput.addEventListener('input', () => this.clearError('passwordError'));
    
    // Remember me functionality
    this.rememberMeCheckbox.addEventListener('change', () => this.handleRememberMe());
  }

  /**
   * Check if admin is already authenticated
   */
  checkExistingAuth() {
    const token = this.getAdminToken();
    if (token) {
      // Verify token validity
      this.verifyToken(token);
    }
  }

  /**
   * Load remembered credentials if available
   */
  loadRememberedCredentials() {
    const rememberedEmail = localStorage.getItem('admin_remembered_email');
    if (rememberedEmail) {
      this.emailInput.value = rememberedEmail;
      this.rememberMeCheckbox.checked = true;
    }
  }

  /**
   * Handle login form submission
   */
  async handleLogin(e) {
    e.preventDefault();
    
    // Validate form
    if (!this.validateForm()) {
      return;
    }

    const formData = new FormData(this.form);
    const credentials = {
      email: formData.get('email').trim(),
      password: formData.get('password')
    };

    try {
      this.setLoading(true);
      
      // Make API call to admin login endpoint
      const response = await this.loginAdmin(credentials);
      
      if (response.success) {
        // Store admin token and data
        this.storeAdminAuth(response.data);
        
        // Handle remember me
        if (this.rememberMeCheckbox.checked) {
          localStorage.setItem('admin_remembered_email', credentials.email);
        } else {
          localStorage.removeItem('admin_remembered_email');
        }
        
        // Show success message
        NotificationManager.show('Login successful! Redirecting...', 'success');
        
        // Redirect to admin dashboard
        setTimeout(() => {
          window.location.href = 'admin-dashboard.html';
        }, 1000);
        
      } else {
        throw new Error(response.message || 'Login failed');
      }
      
    } catch (error) {
      console.error('Admin login error:', error);
      NotificationManager.show(error.message || 'Login failed. Please try again.', 'error');
    } finally {
      this.setLoading(false);
    }
  }

  /**
   * Make admin login API call
   */
  async loginAdmin(credentials) {
    const response = await fetch(`${API_CONFIG.BASE_URL}/auth/admin/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(credentials)
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
    }

    return await response.json();
  }

  /**
   * Store admin authentication data
   */
  storeAdminAuth(authData) {
    // Store admin token
    if (authData.tokens) {
      localStorage.setItem('admin_auth_token', authData.tokens.accessToken);
      if (authData.tokens.refreshToken) {
        localStorage.setItem('admin_refresh_token', authData.tokens.refreshToken);
      }
    } else if (authData.token) {
      localStorage.setItem('admin_auth_token', authData.token);
    }
    
    // Store admin data
    if (authData.admin) {
      localStorage.setItem('admin_user_data', JSON.stringify(authData.admin));
    }
  }

  /**
   * Get admin token from storage
   */
  getAdminToken() {
    return localStorage.getItem('admin_auth_token');
  }

  /**
   * Verify token validity
   */
  async verifyToken(token) {
    try {
      const response = await fetch(`${API_CONFIG.BASE_URL}/auth/profile`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success && data.data.user && data.data.user.type === 'admin') {
          // Token is valid and user is admin, redirect to dashboard
          window.location.href = 'admin-dashboard.html';
        }
      }
    } catch (error) {
      console.error('Token verification error:', error);
      // Clear invalid token
      this.clearAdminAuth();
    }
  }

  /**
   * Clear admin authentication data
   */
  clearAdminAuth() {
    localStorage.removeItem('admin_auth_token');
    localStorage.removeItem('admin_refresh_token');
    localStorage.removeItem('admin_user_data');
  }

  /**
   * Toggle password visibility
   */
  togglePassword() {
    const type = this.passwordInput.type === 'password' ? 'text' : 'password';
    this.passwordInput.type = type;
    
    const icon = this.passwordToggle.querySelector('i');
    icon.className = type === 'password' ? 'ri-eye-line' : 'ri-eye-off-line';
  }

  /**
   * Handle remember me checkbox
   */
  handleRememberMe() {
    if (!this.rememberMeCheckbox.checked) {
      localStorage.removeItem('admin_remembered_email');
    }
  }

  /**
   * Validate entire form
   */
  validateForm() {
    const isEmailValid = this.validateEmail();
    const isPasswordValid = this.validatePassword();
    
    return isEmailValid && isPasswordValid;
  }

  /**
   * Validate email field
   */
  validateEmail() {
    const email = this.emailInput.value.trim();
    const emailError = document.getElementById('emailError');
    
    if (!email) {
      this.showError('emailError', 'Email is required');
      return false;
    }
    
    if (!this.isValidEmail(email)) {
      this.showError('emailError', 'Please enter a valid email address');
      return false;
    }
    
    this.clearError('emailError');
    return true;
  }

  /**
   * Validate password field
   */
  validatePassword() {
    const password = this.passwordInput.value;
    const passwordError = document.getElementById('passwordError');
    
    if (!password) {
      this.showError('passwordError', 'Password is required');
      return false;
    }
    
    if (password.length < 6) {
      this.showError('passwordError', 'Password must be at least 6 characters');
      return false;
    }
    
    this.clearError('passwordError');
    return true;
  }

  /**
   * Check if email is valid
   */
  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  /**
   * Show error message
   */
  showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.display = 'block';
    }
    
    // Add error class to input
    const inputId = elementId.replace('Error', '');
    const inputElement = document.getElementById(inputId);
    if (inputElement) {
      inputElement.classList.add('error');
    }
  }

  /**
   * Clear error message
   */
  clearError(elementId) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
      errorElement.textContent = '';
      errorElement.style.display = 'none';
    }
    
    // Remove error class from input
    const inputId = elementId.replace('Error', '');
    const inputElement = document.getElementById(inputId);
    if (inputElement) {
      inputElement.classList.remove('error');
    }
  }

  /**
   * Set loading state
   */
  setLoading(loading) {
    const btnText = this.loginBtn.querySelector('.btn-text');
    const btnLoading = this.loginBtn.querySelector('.btn-loading');
    
    if (loading) {
      btnText.style.display = 'none';
      btnLoading.style.display = 'flex';
      this.loginBtn.disabled = true;
      this.form.style.pointerEvents = 'none';
    } else {
      btnText.style.display = 'block';
      btnLoading.style.display = 'none';
      this.loginBtn.disabled = false;
      this.form.style.pointerEvents = 'auto';
    }
  }
}

// Initialize admin authentication when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  new AdminAuth();
});

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AdminAuth;
}