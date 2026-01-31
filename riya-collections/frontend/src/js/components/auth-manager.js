/**
 * Authentication Manager for Riya Collections Frontend
 * Handles token management, authentication state, and session management
 */

class AuthenticationManager {
  constructor() {
    this.isAuthenticated = false;
    this.currentUser = null;
    this.tokenRefreshTimer = null;
    this.authStateListeners = [];
    this.init();
  }

  /**
   * Initialize authentication manager
   */
  init() {
    this.loadStoredAuth();
    this.setupTokenRefresh();
    this.setupStorageListener();
    this.setupBeforeUnloadHandler();
  }

  /**
   * Load stored authentication data
   */
  loadStoredAuth() {
    const token = this.getStoredToken();
    const user = this.getStoredUser();

    if (token && user) {
      // Validate token expiration
      if (this.isTokenValid(token)) {
        this.setAuthState(true, user, token);
      } else {
        this.clearStoredAuth();
      }
    }
  }

  /**
   * Get stored authentication token
   * @returns {string|null} Auth token
   */
  getStoredToken() {
    return CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
  }

  /**
   * Get stored user data
   * @returns {Object|null} User data
   */
  getStoredUser() {
    return CONFIG_UTILS.getStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
  }

  /**
   * Get stored refresh token
   * @returns {string|null} Refresh token
   */
  getStoredRefreshToken() {
    return CONFIG_UTILS.getStorageItem('refresh_token');
  }

  /**
   * Check if token is valid (not expired)
   * @param {string} token - JWT token
   * @returns {boolean} Is valid
   */
  isTokenValid(token) {
    if (!token) return false;

    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      const currentTime = Math.floor(Date.now() / 1000);
      
      // Check if token is expired (with 5 minute buffer)
      return payload.exp > (currentTime + 300);
    } catch (error) {
      console.warn('Invalid token format:', error);
      return false;
    }
  }

  /**
   * Get token expiration time
   * @param {string} token - JWT token
   * @returns {number|null} Expiration timestamp
   */
  getTokenExpiration(token) {
    if (!token) return null;

    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      return payload.exp * 1000; // Convert to milliseconds
    } catch (error) {
      return null;
    }
  }

  /**
   * Set authentication state
   * @param {boolean} authenticated - Is authenticated
   * @param {Object} user - User data
   * @param {string} token - Auth token
   */
  setAuthState(authenticated, user = null, token = null) {
    this.isAuthenticated = authenticated;
    this.currentUser = user;

    if (authenticated && token) {
      this.storeAuthData(token, user);
      this.scheduleTokenRefresh(token);
    } else {
      this.clearStoredAuth();
      this.clearTokenRefresh();
    }

    // Update global state
    window.isAuthenticated = authenticated;
    window.currentUser = user;

    // Notify listeners
    this.notifyAuthStateChange(authenticated, user);
  }

  /**
   * Store authentication data
   * @param {string} token - Auth token
   * @param {Object} user - User data
   * @param {string} refreshToken - Refresh token (optional)
   */
  storeAuthData(token, user, refreshToken = null) {
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, token);
    CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, user);
    
    if (refreshToken) {
      CONFIG_UTILS.setStorageItem('refresh_token', refreshToken);
    }
  }

  /**
   * Clear stored authentication data
   */
  clearStoredAuth() {
    CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA);
    CONFIG_UTILS.removeStorageItem('refresh_token');
    CONFIG_UTILS.removeStorageItem(APP_CONFIG.STORAGE_KEYS.CART_DATA);
  }

  /**
   * Login user
   * @param {Object} credentials - Login credentials
   * @returns {Promise<Object>} Login response
   */
  async login(credentials) {
    try {
      const response = await ApiService.auth.login(credentials);
      
      if (response.success && response.data) {
        const { user, token, tokens } = response.data;
        
        // Handle different token formats
        const authToken = tokens?.accessToken || token;
        const refreshToken = tokens?.refreshToken;
        
        if (authToken && user) {
          this.setAuthState(true, user, authToken);
          
          if (refreshToken) {
            CONFIG_UTILS.setStorageItem('refresh_token', refreshToken);
          }
        }
      }
      
      return response;
    } catch (error) {
      throw error;
    }
  }

  /**
   * Register user
   * @param {Object} userData - Registration data
   * @returns {Promise<Object>} Registration response
   */
  async register(userData) {
    try {
      const response = await ApiService.auth.register(userData);
      
      if (response.success && response.data) {
        const { user, token, tokens } = response.data;
        
        // Handle different token formats
        const authToken = tokens?.accessToken || token;
        const refreshToken = tokens?.refreshToken;
        
        if (authToken && user) {
          this.setAuthState(true, user, authToken);
          
          if (refreshToken) {
            CONFIG_UTILS.setStorageItem('refresh_token', refreshToken);
          }
        }
      }
      
      return response;
    } catch (error) {
      throw error;
    }
  }

  /**
   * Logout user
   * @returns {Promise<void>}
   */
  async logout() {
    try {
      // Call logout API if authenticated
      if (this.isAuthenticated) {
        await ApiService.auth.logout();
      }
    } catch (error) {
      console.warn('Logout API error:', error);
    } finally {
      // Clear local state regardless of API response
      this.setAuthState(false);
    }
  }

  /**
   * Refresh authentication token
   * @returns {Promise<boolean>} Success status
   */
  async refreshToken() {
    const refreshToken = this.getStoredRefreshToken();
    
    if (!refreshToken) {
      console.warn('No refresh token available');
      return false;
    }

    try {
      const response = await fetch(`${API_CONFIG.BASE_URL}/auth/refresh`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ refreshToken })
      });

      if (response.ok) {
        const data = await response.json();
        
        if (data.success && data.data.token) {
          const { token, user } = data.data;
          this.storeAuthData(token, user || this.currentUser);
          this.scheduleTokenRefresh(token);
          return true;
        }
      }
      
      // Refresh failed, logout user
      this.logout();
      return false;
      
    } catch (error) {
      console.error('Token refresh error:', error);
      this.logout();
      return false;
    }
  }

  /**
   * Schedule automatic token refresh
   * @param {string} token - Current token
   */
  scheduleTokenRefresh(token) {
    this.clearTokenRefresh();
    
    const expiration = this.getTokenExpiration(token);
    if (!expiration) return;

    // Refresh 5 minutes before expiration
    const refreshTime = expiration - Date.now() - (5 * 60 * 1000);
    
    if (refreshTime > 0) {
      this.tokenRefreshTimer = setTimeout(() => {
        this.refreshToken();
      }, refreshTime);
    }
  }

  /**
   * Clear token refresh timer
   */
  clearTokenRefresh() {
    if (this.tokenRefreshTimer) {
      clearTimeout(this.tokenRefreshTimer);
      this.tokenRefreshTimer = null;
    }
  }

  /**
   * Setup storage event listener for cross-tab sync
   */
  setupStorageListener() {
    window.addEventListener('storage', (event) => {
      if (event.key === APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN) {
        // Token changed in another tab
        if (event.newValue) {
          // User logged in elsewhere
          const user = this.getStoredUser();
          if (user) {
            this.setAuthState(true, user, event.newValue);
          }
        } else {
          // User logged out elsewhere
          this.setAuthState(false);
        }
      }
    });
  }

  /**
   * Setup before unload handler
   */
  setupBeforeUnloadHandler() {
    window.addEventListener('beforeunload', () => {
      // Update last activity timestamp
      if (this.isAuthenticated) {
        CONFIG_UTILS.setStorageItem('last_activity', Date.now());
      }
    });
  }

  /**
   * Check if user has permission
   * @param {string} permission - Permission to check
   * @returns {boolean} Has permission
   */
  hasPermission(permission) {
    if (!this.isAuthenticated || !this.currentUser) {
      return false;
    }

    const userPermissions = this.currentUser.permissions || [];
    const userRole = this.currentUser.role;

    // Admin has all permissions
    if (userRole === 'admin' || userRole === 'super_admin') {
      return true;
    }

    // Check specific permission
    return userPermissions.includes(permission);
  }

  /**
   * Check if user has role
   * @param {string} role - Role to check
   * @returns {boolean} Has role
   */
  hasRole(role) {
    if (!this.isAuthenticated || !this.currentUser) {
      return false;
    }

    return this.currentUser.role === role;
  }

  /**
   * Get user profile
   * @returns {Promise<Object>} User profile
   */
  async getProfile() {
    if (!this.isAuthenticated) {
      throw new Error('User not authenticated');
    }

    try {
      const response = await ApiService.auth.getProfile();
      
      if (response.success && response.data) {
        // Update stored user data
        this.currentUser = response.data;
        CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, response.data);
        
        // Notify listeners
        this.notifyAuthStateChange(true, response.data);
      }
      
      return response;
    } catch (error) {
      throw error;
    }
  }

  /**
   * Update user profile
   * @param {Object} profileData - Profile data
   * @returns {Promise<Object>} Update response
   */
  async updateProfile(profileData) {
    if (!this.isAuthenticated) {
      throw new Error('User not authenticated');
    }

    try {
      const response = await ApiService.auth.updateProfile(profileData);
      
      if (response.success && response.data) {
        // Update stored user data
        this.currentUser = response.data;
        CONFIG_UTILS.setStorageItem(APP_CONFIG.STORAGE_KEYS.USER_DATA, response.data);
        
        // Notify listeners
        this.notifyAuthStateChange(true, response.data);
      }
      
      return response;
    } catch (error) {
      throw error;
    }
  }

  /**
   * Add authentication state listener
   * @param {Function} listener - Listener function
   * @returns {Function} Unsubscribe function
   */
  onAuthStateChange(listener) {
    this.authStateListeners.push(listener);
    
    // Call immediately with current state
    listener(this.isAuthenticated, this.currentUser);
    
    // Return unsubscribe function
    return () => {
      const index = this.authStateListeners.indexOf(listener);
      if (index > -1) {
        this.authStateListeners.splice(index, 1);
      }
    };
  }

  /**
   * Notify authentication state change
   * @param {boolean} authenticated - Is authenticated
   * @param {Object} user - User data
   */
  notifyAuthStateChange(authenticated, user) {
    this.authStateListeners.forEach(listener => {
      try {
        listener(authenticated, user);
      } catch (error) {
        console.error('Auth state listener error:', error);
      }
    });

    // Dispatch global event
    const event = new CustomEvent('authStateChanged', {
      detail: { authenticated, user }
    });
    document.dispatchEvent(event);
  }

  /**
   * Require authentication
   * @param {string} redirectUrl - URL to redirect after login
   * @throws {Error} If not authenticated
   */
  requireAuth(redirectUrl = null) {
    if (!this.isAuthenticated) {
      const currentUrl = redirectUrl || window.location.href;
      const loginUrl = `pages/login.html?redirect=${encodeURIComponent(currentUrl)}`;
      
      if (window.NotificationManager) {
        NotificationManager.show('Please log in to continue', 'info');
      }
      
      window.location.href = loginUrl;
      throw new Error('Authentication required');
    }
  }

  /**
   * Require specific permission
   * @param {string} permission - Required permission
   * @throws {Error} If permission not granted
   */
  requirePermission(permission) {
    this.requireAuth();
    
    if (!this.hasPermission(permission)) {
      if (window.NotificationManager) {
        NotificationManager.show('You do not have permission to perform this action', 'error');
      }
      throw new Error('Permission denied');
    }
  }

  /**
   * Require specific role
   * @param {string} role - Required role
   * @throws {Error} If role not granted
   */
  requireRole(role) {
    this.requireAuth();
    
    if (!this.hasRole(role)) {
      if (window.NotificationManager) {
        NotificationManager.show('Access denied', 'error');
      }
      throw new Error('Role required');
    }
  }

  /**
   * Get authentication header
   * @returns {Object} Authorization header
   */
  getAuthHeader() {
    const token = this.getStoredToken();
    
    if (token) {
      return { Authorization: `Bearer ${token}` };
    }
    
    return {};
  }

  /**
   * Check session validity
   * @returns {boolean} Is session valid
   */
  isSessionValid() {
    const token = this.getStoredToken();
    return token && this.isTokenValid(token);
  }

  /**
   * Get time until token expiration
   * @returns {number} Milliseconds until expiration
   */
  getTimeUntilExpiration() {
    const token = this.getStoredToken();
    const expiration = this.getTokenExpiration(token);
    
    if (expiration) {
      return Math.max(0, expiration - Date.now());
    }
    
    return 0;
  }

  /**
   * Check if token needs refresh
   * @returns {boolean} Needs refresh
   */
  needsTokenRefresh() {
    const timeUntilExpiration = this.getTimeUntilExpiration();
    // Refresh if less than 10 minutes remaining
    return timeUntilExpiration > 0 && timeUntilExpiration < (10 * 60 * 1000);
  }
}

// Create global instance
window.AuthManager = new AuthenticationManager();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AuthenticationManager;
}