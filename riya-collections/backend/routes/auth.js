const express = require('express');
const bcrypt = require('bcrypt');
const { body, validationResult } = require('express-validator');
const { executeQuery } = require('../config/database');
const { secureExecuteQuery } = require('../middleware/database-security');
const { generateTokenPair } = require('../config/jwt');
const { authenticateToken, refreshToken } = require('../middleware/auth');
const { validationMiddleware } = require('../middleware/validation');

const router = express.Router();

// User Registration Endpoint
router.post('/register', validationMiddleware.userRegistration, async (req, res) => {
  try {
    const { email, password, firstName, lastName, phone } = req.body;

    // Check if user already exists
    const existingUser = await secureExecuteQuery(
      'SELECT id FROM users WHERE email = ?',
      [email]
    );

    if (existingUser.length > 0) {
      return res.status(409).json({
        success: false,
        message: 'User with this email already exists'
      });
    }

    // Hash password with bcrypt
    const saltRounds = parseInt(process.env.BCRYPT_SALT_ROUNDS) || 12;
    const passwordHash = await bcrypt.hash(password, saltRounds);

    // Insert new user into database
    const result = await secureExecuteQuery(
      `INSERT INTO users (email, password_hash, first_name, last_name, phone) 
       VALUES (?, ?, ?, ?, ?)`,
      [email, passwordHash, firstName, lastName, phone || null]
    );

    // Get the created user (without password)
    const newUser = await secureExecuteQuery(
      `SELECT id, email, first_name, last_name, phone, created_at 
       FROM users WHERE id = ?`,
      [result.insertId]
    );

    const user = newUser[0];

    // Generate JWT tokens
    const tokenPayload = {
      userId: user.id,
      email: user.email,
      type: 'customer'
    };

    const tokens = generateTokenPair(tokenPayload);

    // Return success response
    res.status(201).json({
      success: true,
      message: 'User registered successfully',
      data: {
        user: {
          id: user.id,
          email: user.email,
          firstName: user.first_name,
          lastName: user.last_name,
          phone: user.phone,
          createdAt: user.created_at
        },
        tokens
      }
    });

  } catch (error) {
    console.error('Registration error:', error);
    
    // Handle specific database errors
    if (error.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({
        success: false,
        message: 'User with this email already exists'
      });
    }

    res.status(500).json({
      success: false,
      message: 'Internal server error during registration'
    });
  }
});

// User Login Endpoint
router.post('/login', validationMiddleware.userLogin, async (req, res) => {
  try {
    const { email, password } = req.body;

    // Find user by email
    const users = await secureExecuteQuery(
      'SELECT id, email, password_hash, first_name, last_name, phone, created_at FROM users WHERE email = ?',
      [email]
    );

    if (users.length === 0) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const user = users[0];

    // Verify password
    if (!user.password_hash) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const isPasswordValid = await bcrypt.compare(password, user.password_hash);
    
    if (!isPasswordValid) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    // Generate JWT tokens
    const tokenPayload = {
      userId: user.id,
      email: user.email,
      type: 'customer'
    };

    const tokens = generateTokenPair(tokenPayload);

    // Return success response
    res.json({
      success: true,
      message: 'Login successful',
      data: {
        user: {
          id: user.id,
          email: user.email,
          firstName: user.first_name,
          lastName: user.last_name,
          phone: user.phone,
          createdAt: user.created_at
        },
        tokens
      }
    });

  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error during login'
    });
  }
});

// Get User Profile Endpoint
router.get('/profile', authenticateToken, async (req, res) => {
  try {
    // User information is already available in req.user from middleware
    res.json({
      success: true,
      message: 'Profile retrieved successfully',
      data: {
        user: req.user
      }
    });
  } catch (error) {
    console.error('Profile retrieval error:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error'
    });
  }
});

// Update User Profile Endpoint
router.put('/profile', authenticateToken, validationMiddleware.profileUpdate, async (req, res) => {
  try {
    const { firstName, lastName, phone } = req.body;
    const userId = req.user.id;

    // Build update query dynamically based on provided fields
    const updateFields = [];
    const updateValues = [];

    if (firstName !== undefined) {
      updateFields.push('first_name = ?');
      updateValues.push(firstName);
    }

    if (lastName !== undefined) {
      updateFields.push('last_name = ?');
      updateValues.push(lastName);
    }

    if (phone !== undefined) {
      updateFields.push('phone = ?');
      updateValues.push(phone || null);
    }

    if (updateFields.length === 0) {
      return res.status(400).json({
        success: false,
        message: 'No valid fields provided for update'
      });
    }

    // Add user ID to values array
    updateValues.push(userId);

    // Update user profile
    await secureExecuteQuery(
      `UPDATE users SET ${updateFields.join(', ')}, updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
      updateValues
    );

    // Get updated user data
    const updatedUser = await secureExecuteQuery(
      'SELECT id, email, first_name, last_name, phone, created_at, updated_at FROM users WHERE id = ?',
      [userId]
    );

    res.json({
      success: true,
      message: 'Profile updated successfully',
      data: {
        user: {
          id: updatedUser[0].id,
          email: updatedUser[0].email,
          firstName: updatedUser[0].first_name,
          lastName: updatedUser[0].last_name,
          phone: updatedUser[0].phone,
          createdAt: updatedUser[0].created_at,
          updatedAt: updatedUser[0].updated_at
        }
      }
    });

  } catch (error) {
    console.error('Profile update error:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error during profile update'
    });
  }
});

// Admin Registration Endpoint (for initial setup or super admin use)
router.post('/admin/register', [
  body('email')
    .isEmail()
    .normalizeEmail()
    .withMessage('Please provide a valid email address'),
  
  body('password')
    .isLength({ min: 8 })
    .withMessage('Password must be at least 8 characters long')
    .matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/)
    .withMessage('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'),
  
  body('name')
    .trim()
    .isLength({ min: 2, max: 100 })
    .matches(/^[a-zA-Z\s]+$/)
    .withMessage('Name must be 2-100 characters and contain only letters and spaces'),
  
  body('role')
    .optional()
    .isIn(['admin', 'super_admin'])
    .withMessage('Role must be either admin or super_admin')
], async (req, res) => {
  try {
    // Check for validation errors
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({
        success: false,
        message: 'Validation failed',
        errors: errors.array()
      });
    }

    const { email, password, name, role = 'admin' } = req.body;

    // Check if admin already exists
    const existingAdmin = await secureExecuteQuery(
      'SELECT id FROM admins WHERE email = ?',
      [email]
    );

    if (existingAdmin.length > 0) {
      return res.status(409).json({
        success: false,
        message: 'Admin with this email already exists'
      });
    }

    // Hash password with bcrypt
    const saltRounds = parseInt(process.env.BCRYPT_SALT_ROUNDS) || 12;
    const passwordHash = await bcrypt.hash(password, saltRounds);

    // Insert new admin into database
    const result = await secureExecuteQuery(
      `INSERT INTO admins (email, password_hash, name, role) 
       VALUES (?, ?, ?, ?)`,
      [email, passwordHash, name, role]
    );

    // Get the created admin (without password)
    const newAdmin = await secureExecuteQuery(
      `SELECT id, email, name, role, created_at 
       FROM admins WHERE id = ?`,
      [result.insertId]
    );

    const admin = newAdmin[0];

    // Generate JWT tokens
    const tokenPayload = {
      userId: admin.id,
      email: admin.email,
      type: 'admin',
      role: admin.role
    };

    const tokens = generateTokenPair(tokenPayload);

    // Return success response
    res.status(201).json({
      success: true,
      message: 'Admin registered successfully',
      data: {
        admin: {
          id: admin.id,
          email: admin.email,
          name: admin.name,
          role: admin.role,
          createdAt: admin.created_at
        },
        tokens
      }
    });

  } catch (error) {
    console.error('Admin registration error:', error);
    
    // Handle specific database errors
    if (error.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({
        success: false,
        message: 'Admin with this email already exists'
      });
    }

    res.status(500).json({
      success: false,
      message: 'Internal server error during admin registration'
    });
  }
});

// Admin Login Endpoint
router.post('/admin/login', validationMiddleware.userLogin, async (req, res) => {
  try {
    const { email, password } = req.body;
    console.debug('[admin/login] request email=', email);

    // Find admin by email
    const admins = await secureExecuteQuery(
      'SELECT id, email, password_hash, name, role, created_at FROM admins WHERE email = ?',
      [email]
    );

    if (admins.length === 0) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const admin = admins[0];
    console.debug('[admin/login] db admin row:', { id: admin.id, email: admin.email, hasPassword: !!admin.password_hash, role: admin.role });

    // Verify password
    if (!admin.password_hash) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const isPasswordValid = await bcrypt.compare(password, admin.password_hash);
    console.debug('[admin/login] password valid=', isPasswordValid);
    
    if (!isPasswordValid) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    // Generate JWT tokens
    const tokenPayload = {
      userId: admin.id,
      email: admin.email,
      type: 'admin',
      role: admin.role
    };

    const tokens = generateTokenPair(tokenPayload);

    // Return success response
    res.json({
      success: true,
      message: 'Admin login successful',
      data: {
        admin: {
          id: admin.id,
          email: admin.email,
          name: admin.name,
          role: admin.role,
          createdAt: admin.created_at
        },
        tokens
      }
    });

  } catch (error) {
    console.error('Admin login error:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error during admin login'
    });
  }
});

// Token Refresh Endpoint
router.post('/refresh', refreshToken);

// Logout Endpoint (Optional - mainly for client-side token cleanup)
router.post('/logout', authenticateToken, async (req, res) => {
  try {
    // In a JWT-based system, logout is mainly handled client-side
    // by removing the token. Here we just confirm the logout.
    res.json({
      success: true,
      message: 'Logged out successfully'
    });
  } catch (error) {
    console.error('Logout error:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error during logout'
    });
  }
});

module.exports = router;