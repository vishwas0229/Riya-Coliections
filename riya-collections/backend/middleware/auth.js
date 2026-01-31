const { verifyAccessToken, verifyRefreshToken, generateTokenPair } = require('../config/jwt');
const { secureExecuteQuery } = require('./database-security');

/**
 * Middleware to authenticate JWT tokens for customers
 */
const authenticateToken = async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

    if (!token) {
      return res.status(401).json({
        success: false,
        message: 'Access token is required'
      });
    }

    // Verify the token
    const decoded = verifyAccessToken(token);
    
    // Ensure this is a customer token
    if (decoded.type !== 'customer') {
      return res.status(403).json({
        success: false,
        message: 'Customer access required'
      });
    }

    // Get user details from database
    const users = await secureExecuteQuery(
      'SELECT id, email, first_name, last_name, phone, created_at FROM users WHERE id = ?',
      [decoded.userId]
    );

    if (!users || users.length === 0) {
      return res.status(401).json({
        success: false,
        message: 'User not found'
      });
    }

    // Add user info to request object
    req.user = {
      id: users[0].id,
      email: users[0].email,
      firstName: users[0].first_name,
      lastName: users[0].last_name,
      phone: users[0].phone,
      createdAt: users[0].created_at,
      type: decoded.type
    };

    next();
  } catch (error) {
    console.error('Authentication error:', error);
    
    if (error.message.includes('Invalid or expired')) {
      return res.status(401).json({
        success: false,
        message: 'Invalid or expired token'
      });
    }

    res.status(500).json({
      success: false,
      message: 'Authentication failed'
    });
  }
};

/**
 * Middleware to authenticate admin users
 */
const authenticateAdmin = async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

    if (!token) {
      return res.status(401).json({
        success: false,
        message: 'Access token is required'
      });
    }

    // Verify the token
    const decoded = verifyAccessToken(token);
    
    // Check if user is admin
    if (decoded.type !== 'admin') {
      return res.status(403).json({
        success: false,
        message: 'Admin access required'
      });
    }

    // Get admin details from database
    const admins = await secureExecuteQuery(
      'SELECT id, email, name, role, created_at FROM admins WHERE id = ?',
      [decoded.userId]
    );

    if (!admins || admins.length === 0) {
      return res.status(401).json({
        success: false,
        message: 'Admin not found'
      });
    }

    // Add admin info to request object
    req.admin = {
      id: admins[0].id,
      email: admins[0].email,
      name: admins[0].name,
      role: admins[0].role,
      createdAt: admins[0].created_at,
      type: decoded.type
    };

    next();
  } catch (error) {
    console.error('Admin authentication error:', error);
    
    if (error.message.includes('Invalid or expired')) {
      return res.status(401).json({
        success: false,
        message: 'Invalid or expired token'
      });
    }

    res.status(500).json({
      success: false,
      message: 'Authentication failed'
    });
  }
};

/**
 * Middleware to authenticate super admin users only
 */
const authenticateSuperAdmin = async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

    if (!token) {
      return res.status(401).json({
        success: false,
        message: 'Access token is required'
      });
    }

    // Verify the token
    const decoded = verifyAccessToken(token);
    
    // Check if user is admin
    if (decoded.type !== 'admin') {
      return res.status(403).json({
        success: false,
        message: 'Admin access required'
      });
    }

    // Get admin details from database
    const admins = await secureExecuteQuery(
      'SELECT id, email, name, role, created_at FROM admins WHERE id = ?',
      [decoded.userId]
    );

    if (admins.length === 0) {
      return res.status(401).json({
        success: false,
        message: 'Admin not found'
      });
    }

    const admin = admins[0];

    // Check if admin has super_admin role
    if (admin.role !== 'super_admin') {
      return res.status(403).json({
        success: false,
        message: 'Super admin access required'
      });
    }

    // Add admin info to request object
    req.admin = {
      id: admin.id,
      email: admin.email,
      name: admin.name,
      role: admin.role,
      createdAt: admin.created_at,
      type: decoded.type
    };

    next();
  } catch (error) {
    console.error('Super admin authentication error:', error);
    
    if (error.message.includes('Invalid or expired')) {
      return res.status(401).json({
        success: false,
        message: 'Invalid or expired token'
      });
    }

    res.status(500).json({
      success: false,
      message: 'Authentication failed'
    });
  }
};

/**
 * Optional authentication middleware - doesn't fail if no token
 */
const optionalAuth = async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
      return next(); // Continue without authentication
    }

    // Verify the token
    const decoded = verifyAccessToken(token);
    
    if (decoded.type === 'customer') {
      // Get user details from database
      const users = await secureExecuteQuery(
        'SELECT id, email, first_name, last_name, phone, created_at FROM users WHERE id = ?',
        [decoded.userId]
      );

      if (users.length > 0) {
        req.user = {
          id: users[0].id,
          email: users[0].email,
          firstName: users[0].first_name,
          lastName: users[0].last_name,
          phone: users[0].phone,
          createdAt: users[0].created_at,
          type: decoded.type
        };
      }
    } else if (decoded.type === 'admin') {
      // Get admin details from database
      const admins = await secureExecuteQuery(
        'SELECT id, email, name, role, created_at FROM admins WHERE id = ?',
        [decoded.userId]
      );

      if (admins.length > 0) {
        req.admin = {
          id: admins[0].id,
          email: admins[0].email,
          name: admins[0].name,
          role: admins[0].role,
          createdAt: admins[0].created_at,
          type: decoded.type
        };
      }
    }

    next();
  } catch (error) {
    // Continue without authentication if token is invalid
    next();
  }
};

/**
 * Middleware to refresh JWT tokens
 */
const refreshToken = async (req, res, next) => {
  try {
    const { refreshToken } = req.body;

    if (!refreshToken) {
      return res.status(401).json({
        success: false,
        message: 'Refresh token is required'
      });
    }

    // Verify the refresh token
    const decoded = verifyRefreshToken(refreshToken);
    console.debug('[refreshToken] decoded=', decoded);
    
    let userData = null;

    if (decoded.type === 'customer') {
      // Get user details from database
      const users = await secureExecuteQuery(
        'SELECT id, email, first_name, last_name, phone, created_at FROM users WHERE id = ?',
        [decoded.userId]
      );

      if (!users || users.length === 0) {
        return res.status(401).json({
          success: false,
          message: 'User not found'
        });
      }

      userData = {
        userId: users[0].id,
        email: users[0].email,
        type: 'customer'
      };
    } else if (decoded.type === 'admin') {
      // Get admin details from database
      const admins = await secureExecuteQuery(
        'SELECT id, email, name, role, created_at FROM admins WHERE id = ?',
        [decoded.userId]
      );

      console.debug('[refreshToken] admins query result type/len=', Array.isArray(admins) ? admins.length : typeof admins);

      if (!admins || admins.length === 0) {
        return res.status(401).json({
          success: false,
          message: 'Admin not found'
        });
      }

      userData = {
        userId: admins[0].id,
        email: admins[0].email,
        type: 'admin',
        role: admins[0].role
      };
    }

    // Generate new token pair
    const tokens = generateTokenPair(userData);

    res.json({
      success: true,
      message: 'Tokens refreshed successfully',
      data: { tokens }
    });

  } catch (error) {
    console.error('Token refresh error:', error);
    
    if (error.message.includes('Invalid or expired')) {
      return res.status(401).json({
        success: false,
        message: 'Invalid or expired refresh token'
      });
    }

    res.status(500).json({
      success: false,
      message: 'Token refresh failed'
    });
  }
};

module.exports = {
  authenticateToken,
  authenticateAdmin,
  authenticateSuperAdmin,
  optionalAuth,
  refreshToken
};