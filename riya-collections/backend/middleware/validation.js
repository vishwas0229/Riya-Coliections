const { body, param, query, validationResult } = require('express-validator');
const validator = require('validator');

/**
 * Comprehensive Input Validation Middleware for Riya Collections
 * 
 * This middleware provides:
 * 1. SQL injection prevention through parameterized queries validation
 * 2. XSS prevention through input sanitization
 * 3. Comprehensive input validation for all user inputs
 * 
 * Requirements: 9.1, 9.2, 9.5
 */

/**
 * Sanitize input to prevent XSS attacks
 * @param {string} input - The input string to sanitize
 * @returns {string} - Sanitized string
 */
const sanitizeInput = (input) => {
  if (typeof input !== 'string') {
    return input;
  }
  
  // Remove HTML tags and encode special characters
  let sanitized = validator.stripLow(input);
  
  // Escape HTML characters to prevent XSS
  sanitized = validator.escape(sanitized);
  
  // Remove common XSS attack vectors
  sanitized = sanitized.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
  sanitized = sanitized.replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '');
  sanitized = sanitized.replace(/javascript:/gi, '');
  sanitized = sanitized.replace(/vbscript:/gi, '');
  sanitized = sanitized.replace(/on\w+\s*=/gi, '');
  
  return sanitized.trim();
};

/**
 * Validate and sanitize request body recursively
 * @param {object} obj - Object to sanitize
 * @returns {object} - Sanitized object
 */
const sanitizeObject = (obj) => {
  if (obj === null || obj === undefined) {
    return obj;
  }
  
  if (typeof obj === 'string') {
    return sanitizeInput(obj);
  }
  
  if (Array.isArray(obj)) {
    return obj.map(item => sanitizeObject(item));
  }
  
  if (typeof obj === 'object') {
    const sanitized = {};
    for (const [key, value] of Object.entries(obj)) {
      // Sanitize both key and value
      const sanitizedKey = sanitizeInput(key);
      // Do not mutate password fields or raw password hashes
      if (sanitizedKey === 'password' || sanitizedKey === 'confirmPassword' || sanitizedKey === 'password_hash') {
        sanitized[sanitizedKey] = value;
      } else {
        sanitized[sanitizedKey] = sanitizeObject(value);
      }
    }
    return sanitized;
  }
  
  return obj;
};

/**
 * Middleware to sanitize all incoming request data
 */
const sanitizeRequest = (req, res, next) => {
  try {
    // Sanitize request body
    if (req.body && typeof req.body === 'object') {
      req.body = sanitizeObject(req.body);
    }
    
    // Sanitize query parameters
    if (req.query && typeof req.query === 'object') {
      req.query = sanitizeObject(req.query);
    }
    
    // Sanitize URL parameters
    if (req.params && typeof req.params === 'object') {
      req.params = sanitizeObject(req.params);
    }
    
    next();
  } catch (error) {
    console.error('Input sanitization error:', error);
    res.status(400).json({
      success: false,
      message: 'Invalid input data format'
    });
  }
};

/**
 * Validate SQL injection patterns in input
 * @param {string} input - Input to validate
 * @returns {boolean} - True if input contains potential SQL injection
 */
const containsSQLInjection = (input) => {
  if (typeof input !== 'string') {
    return false;
  }
  
  // Common SQL injection patterns - more precise to avoid false positives
  const sqlInjectionPatterns = [
    /(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b).*?(\b(FROM|INTO|SET|WHERE|VALUES)\b)/i,
    /(--|\/\*|\*\/);/,
    /(\bOR\b|\bAND\b).*?[=<>].*?['"`]/i,
    /(\b1\s*=\s*1\b|\b1\s*=\s*'1'\b)/i,
    /(\bUNION\b.*?\bSELECT\b)/i,
    /(\bINSERT\b.*?\bINTO\b)/i,
    /(\bDROP\b.*?\bTABLE\b)/i,
    /(\bEXEC\b.*?\b\w+)/i,
    /['"`].*?(\bOR\b|\bAND\b).*?['"`]/i,
    /['"`]\s*;\s*(SELECT|INSERT|UPDATE|DELETE|DROP|EXEC)/i,
    /;\s*(DROP|DELETE|INSERT|UPDATE|SELECT|EXEC)/i
  ];
  
  return sqlInjectionPatterns.some(pattern => pattern.test(input));
};

/**
 * Middleware to detect and prevent SQL injection attempts
 */
const preventSQLInjection = (req, res, next) => {
  try {
    const checkForSQLInjection = (obj, path = '') => {
      if (obj === null || obj === undefined) {
        return null;
      }
      
      if (typeof obj === 'string') {
        if (containsSQLInjection(obj)) {
          return `Potential SQL injection detected in ${path || 'input'}`;
        }
      } else if (Array.isArray(obj)) {
        for (let i = 0; i < obj.length; i++) {
          const result = checkForSQLInjection(obj[i], `${path}[${i}]`);
          if (result) return result;
        }
      } else if (typeof obj === 'object') {
        for (const [key, value] of Object.entries(obj)) {
          const currentPath = path ? `${path}.${key}` : key;
          const result = checkForSQLInjection(value, currentPath);
          if (result) return result;
        }
      }
      
      return null;
    };
    
    // Check request body
    const bodyResult = checkForSQLInjection(req.body, 'body');
    if (bodyResult) {
      console.warn('SQL injection attempt detected:', bodyResult, req.ip);
      return res.status(400).json({
        success: false,
        message: 'Invalid input detected'
      });
    }
    
    // Check query parameters
    const queryResult = checkForSQLInjection(req.query, 'query');
    if (queryResult) {
      console.warn('SQL injection attempt detected:', queryResult, req.ip);
      return res.status(400).json({
        success: false,
        message: 'Invalid query parameters'
      });
    }
    
    // Check URL parameters
    const paramsResult = checkForSQLInjection(req.params, 'params');
    if (paramsResult) {
      console.warn('SQL injection attempt detected:', paramsResult, req.ip);
      return res.status(400).json({
        success: false,
        message: 'Invalid URL parameters'
      });
    }
    
    next();
  } catch (error) {
    console.error('SQL injection prevention error:', error);
    res.status(500).json({
      success: false,
      message: 'Input validation failed'
    });
  }
};

/**
 * Handle validation errors from express-validator
 */
const handleValidationErrors = (req, res, next) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) {
    return res.status(400).json({
      success: false,
      message: 'Validation failed',
      errors: errors.array().map(error => ({
        field: error.path || error.param,
        message: error.msg,
        value: error.value
      }))
    });
  }
  next();
};

/**
 * Common validation rules for different data types
 */
const validationRules = {
  // Email validation
  email: () => body('email')
    .isEmail()
    .normalizeEmail()
    .withMessage('Please provide a valid email address')
    .isLength({ max: 255 })
    .withMessage('Email must not exceed 255 characters'),
  
  // Password validation
  password: () => body('password')
    .isLength({ min: 8, max: 128 })
    .withMessage('Password must be between 8 and 128 characters')
    .matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/)
    .withMessage('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'),
  
  // Name validation (first name, last name, etc.)
  name: (fieldName) => body(fieldName)
    .trim()
    .isLength({ min: 2, max: 100 })
    .withMessage(`${fieldName} must be between 2 and 100 characters`)
    .matches(/^[a-zA-Z\s'-]+$/)
    .withMessage(`${fieldName} must contain only letters, spaces, hyphens, and apostrophes`),
  
  // Phone number validation
  phone: () => body('phone')
    .optional()
    .matches(/^[+]?[1-9][\d\s\-()]{7,15}$/)
    .withMessage('Please provide a valid phone number'),
  
  // Product name validation
  productName: () => body('name')
    .trim()
    .isLength({ min: 3, max: 255 })
    .withMessage('Product name must be between 3 and 255 characters')
    .matches(/^[a-zA-Z0-9\s\-_.,()&]+$/)
    .withMessage('Product name contains invalid characters'),
  
  // Price validation
  price: () => body('price')
    .isFloat({ min: 0.01, max: 999999.99 })
    .withMessage('Price must be a positive number between 0.01 and 999999.99')
    .toFloat(),
  
  // Stock quantity validation
  stockQuantity: () => body('stock_quantity')
    .isInt({ min: 0, max: 999999 })
    .withMessage('Stock quantity must be a non-negative integer up to 999999')
    .toInt(),
  
  // Description validation
  description: () => body('description')
    .optional()
    .trim()
    .isLength({ max: 5000 })
    .withMessage('Description must not exceed 5000 characters'),
  
  // Category ID validation
  categoryId: () => body('category_id')
    .optional()
    .isInt({ min: 1 })
    .withMessage('Category ID must be a positive integer')
    .toInt(),
  
  // Brand validation
  brand: () => body('brand')
    .optional()
    .trim()
    .isLength({ max: 100 })
    .withMessage('Brand must not exceed 100 characters')
    .matches(/^[a-zA-Z0-9\s\-_&.]+$/)
    .withMessage('Brand contains invalid characters'),
  
  // SKU validation
  sku: () => body('sku')
    .optional()
    .trim()
    .isLength({ max: 50 })
    .withMessage('SKU must not exceed 50 characters')
    .matches(/^[a-zA-Z0-9\-_]+$/)
    .withMessage('SKU must contain only letters, numbers, hyphens, and underscores'),
  
  // Address validation
  addressLine: (fieldName) => body(fieldName)
    .trim()
    .isLength({ min: 5, max: 255 })
    .withMessage(`${fieldName} must be between 5 and 255 characters`)
    .matches(/^[a-zA-Z0-9\s\-_.,#/()]+$/)
    .withMessage(`${fieldName} contains invalid characters`),
  
  // City validation
  city: () => body('city')
    .trim()
    .isLength({ min: 2, max: 100 })
    .withMessage('City must be between 2 and 100 characters')
    .matches(/^[a-zA-Z\s\-']+$/)
    .withMessage('City must contain only letters, spaces, hyphens, and apostrophes'),
  
  // State validation
  state: () => body('state')
    .trim()
    .isLength({ min: 2, max: 100 })
    .withMessage('State must be between 2 and 100 characters')
    .matches(/^[a-zA-Z\s\-']+$/)
    .withMessage('State must contain only letters, spaces, hyphens, and apostrophes'),
  
  // Postal code validation
  postalCode: () => body('postal_code')
    .trim()
    .matches(/^[0-9]{6}$/)
    .withMessage('Postal code must be a 6-digit number'),
  
  // Order quantity validation
  quantity: () => body('quantity')
    .isInt({ min: 1, max: 999 })
    .withMessage('Quantity must be a positive integer between 1 and 999')
    .toInt(),
  
  // Coupon code validation
  couponCode: () => body('coupon_code')
    .optional()
    .trim()
    .isLength({ max: 50 })
    .withMessage('Coupon code must not exceed 50 characters')
    .matches(/^[a-zA-Z0-9\-_]+$/)
    .withMessage('Coupon code must contain only letters, numbers, hyphens, and underscores'),
  
  // ID parameter validation
  idParam: (paramName = 'id') => param(paramName)
    .isInt({ min: 1 })
    .withMessage(`${paramName} must be a positive integer`)
    .toInt(),
  
  // Search query validation
  searchQuery: () => query('search')
    .optional()
    .trim()
    .isLength({ max: 255 })
    .withMessage('Search query must not exceed 255 characters')
    .custom((value) => {
      // Allow empty string after trimming
      if (value === '') {
        return true;
      }
      // Check for valid characters if not empty
      if (!/^[a-zA-Z0-9\s\-_.,()&]+$/.test(value)) {
        throw new Error('Search query contains invalid characters');
      }
      return true;
    }),
  
  // Pagination validation
  page: () => query('page')
    .optional()
    .isInt({ min: 1, max: 10000 })
    .withMessage('Page must be a positive integer up to 10000')
    .toInt(),
  
  limit: () => query('limit')
    .optional()
    .isInt({ min: 1, max: 100 })
    .withMessage('Limit must be between 1 and 100')
    .toInt(),
  
  // Sort validation
  sortBy: (allowedFields) => query('sortBy')
    .optional()
    .isIn(allowedFields)
    .withMessage(`Sort field must be one of: ${allowedFields.join(', ')}`),
  
  sortOrder: () => query('sortOrder')
    .optional()
    .isIn(['asc', 'desc'])
    .withMessage('Sort order must be either asc or desc'),
  
  // Price range validation
  minPrice: () => query('minPrice')
    .optional()
    .isFloat({ min: 0 })
    .withMessage('Minimum price must be a non-negative number')
    .toFloat(),
  
  maxPrice: () => query('maxPrice')
    .optional()
    .isFloat({ min: 0 })
    .withMessage('Maximum price must be a non-negative number')
    .toFloat(),
  
  // Category filter validation
  categoryFilter: () => query('category')
    .optional()
    .isInt({ min: 1 })
    .withMessage('Category must be a positive integer')
    .toInt(),
  
  // Order status validation
  orderStatus: () => body('status')
    .isIn(['placed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'])
    .withMessage('Invalid order status'),
  
  // Payment method validation
  paymentMethod: () => body('payment_method')
    .isIn(['razorpay', 'cod'])
    .withMessage('Payment method must be either razorpay or cod'),
  
  // Payment amount validation
  paymentAmount: () => body('amount')
    .isFloat({ min: 0.01, max: 999999.99 })
    .withMessage('Amount must be a positive number between 0.01 and 999999.99')
    .toFloat(),
  
  // Razorpay order ID validation
  razorpayOrderId: () => body('razorpay_order_id')
    .matches(/^order_[a-zA-Z0-9]+$/)
    .withMessage('Invalid Razorpay order ID format'),
  
  // Razorpay payment ID validation
  razorpayPaymentId: () => body('razorpay_payment_id')
    .matches(/^pay_[a-zA-Z0-9]+$/)
    .withMessage('Invalid Razorpay payment ID format'),
  
  // Razorpay signature validation
  razorpaySignature: () => body('razorpay_signature')
    .matches(/^[a-f0-9]{64}$/)
    .withMessage('Invalid Razorpay signature format'),
  
  // Currency validation
  currency: () => body('currency')
    .optional()
    .isIn(['INR', 'USD', 'EUR'])
    .withMessage('Currency must be INR, USD, or EUR')
};

/**
 * Comprehensive validation middleware factory
 * Creates validation middleware for specific endpoints
 */
const createValidationMiddleware = (rules) => {
  return [
    sanitizeRequest,
    preventSQLInjection,
    ...rules,
    handleValidationErrors
  ];
};

/**
 * Pre-built validation middleware for common operations
 */
const validationMiddleware = {
  // User registration validation
  userRegistration: createValidationMiddleware([
    validationRules.email(),
    validationRules.password(),
    body('confirmPassword')
      .custom((value, { req }) => {
        if (value !== req.body.password) {
          throw new Error('Password confirmation does not match password');
        }
        return true;
      }),
    validationRules.name('firstName'),
    validationRules.name('lastName'),
    validationRules.phone()
  ]),
  
  // User login validation
  userLogin: createValidationMiddleware([
    validationRules.email(),
    body('password').notEmpty().withMessage('Password is required')
  ]),
  
  // Profile update validation
  profileUpdate: createValidationMiddleware([
    validationRules.name('firstName').optional(),
    validationRules.name('lastName').optional(),
    validationRules.phone()
  ]),
  
  // Product creation validation
  productCreation: createValidationMiddleware([
    validationRules.productName(),
    validationRules.description(),
    validationRules.price(),
    validationRules.stockQuantity(),
    validationRules.categoryId(),
    validationRules.brand(),
    validationRules.sku()
  ]),
  
  // Product update validation
  productUpdate: createValidationMiddleware([
    validationRules.idParam(),
    validationRules.productName().optional(),
    validationRules.description(),
    validationRules.price().optional(),
    validationRules.stockQuantity().optional(),
    validationRules.categoryId(),
    validationRules.brand(),
    validationRules.sku()
  ]),
  
  // Product listing validation
  productListing: createValidationMiddleware([
    validationRules.searchQuery(),
    validationRules.page(),
    validationRules.limit(),
    validationRules.sortBy(['name', 'price', 'created_at', 'stock_quantity']),
    validationRules.sortOrder(),
    validationRules.minPrice(),
    validationRules.maxPrice(),
    validationRules.categoryFilter()
  ]),
  
  // Address validation
  addressCreation: createValidationMiddleware([
    validationRules.name('first_name'),
    validationRules.name('last_name'),
    validationRules.addressLine('address_line1'),
    body('address_line2').optional().trim().isLength({ max: 255 }).withMessage('Address line 2 must not exceed 255 characters'),
    validationRules.city(),
    validationRules.state(),
    validationRules.postalCode(),
    validationRules.phone(),
    body('type').optional().isIn(['home', 'work', 'other']).withMessage('Address type must be home, work, or other')
  ]),

  // Address update validation
  addressUpdate: createValidationMiddleware([
    validationRules.idParam(),
    validationRules.name('first_name').optional(),
    validationRules.name('last_name').optional(),
    validationRules.addressLine('address_line1').optional(),
    body('address_line2').optional().trim().isLength({ max: 255 }).withMessage('Address line 2 must not exceed 255 characters'),
    validationRules.city().optional(),
    validationRules.state().optional(),
    validationRules.postalCode().optional(),
    validationRules.phone(),
    body('type').optional().isIn(['home', 'work', 'other']).withMessage('Address type must be home, work, or other'),
    body('isDefault').optional().isBoolean().withMessage('isDefault must be a boolean value')
  ]),
  
  // Order creation validation
  orderCreation: createValidationMiddleware([
    body('items').isArray({ min: 1 }).withMessage('Order must contain at least one item'),
    body('items.*.product_id').isInt({ min: 1 }).withMessage('Product ID must be a positive integer'),
    body('items.*.quantity').isInt({ min: 1, max: 999 }).withMessage('Quantity must be between 1 and 999'),
    body('shipping_address_id').isInt({ min: 1 }).withMessage('Shipping address ID must be a positive integer'),
    validationRules.paymentMethod(),
    validationRules.couponCode()
  ]),
  
  // Cart operations validation
  cartAdd: createValidationMiddleware([
    body('product_id').isInt({ min: 1 }).withMessage('Product ID must be a positive integer'),
    validationRules.quantity()
  ]),
  
  cartUpdate: createValidationMiddleware([
    body('product_id').isInt({ min: 1 }).withMessage('Product ID must be a positive integer'),
    validationRules.quantity()
  ]),
  
  // ID parameter validation
  validateId: createValidationMiddleware([
    validationRules.idParam()
  ]),
  
  // Order status update validation
  orderStatus: createValidationMiddleware([
    validationRules.orderStatus(),
    body('notes').optional().trim().isLength({ max: 1000 }).withMessage('Notes must not exceed 1000 characters'),
    body('tracking_number').optional().trim().isLength({ max: 100 }).withMessage('Tracking number must not exceed 100 characters'),
    body('estimated_delivery').optional().isISO8601().withMessage('Estimated delivery must be a valid date')
  ]),

  // Bulk order status update validation
  bulkOrderStatus: createValidationMiddleware([
    body('order_ids').isArray({ min: 1, max: 100 }).withMessage('order_ids must be an array with 1-100 items'),
    body('order_ids.*').isInt({ min: 1 }).withMessage('Each order ID must be a positive integer'),
    validationRules.orderStatus(),
    body('notes').optional().trim().isLength({ max: 500 }).withMessage('Notes must not exceed 500 characters')
  ]),

  // Admin order search and filtering validation
  adminOrderSearch: createValidationMiddleware([
    validationRules.searchQuery(),
    validationRules.page(),
    validationRules.limit(),
    query('status').optional().isIn(['placed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled']).withMessage('Invalid order status'),
    query('payment_method').optional().isIn(['razorpay', 'cod']).withMessage('Invalid payment method'),
    query('payment_status').optional().isIn(['pending', 'paid', 'failed', 'refunded', 'completed']).withMessage('Invalid payment status'),
    query('customer_email').optional().isEmail().withMessage('Invalid customer email format'),
    query('order_number').optional().trim().isLength({ max: 50 }).withMessage('Order number must not exceed 50 characters'),
    query('start_date').optional().isISO8601().withMessage('Start date must be a valid date'),
    query('end_date').optional().isISO8601().withMessage('End date must be a valid date'),
    query('min_amount').optional().isFloat({ min: 0 }).withMessage('Minimum amount must be a non-negative number'),
    query('max_amount').optional().isFloat({ min: 0 }).withMessage('Maximum amount must be a non-negative number'),
    validationRules.sortBy(['created_at', 'updated_at', 'order_number', 'status', 'total_amount', 'customer_name']),
    validationRules.sortOrder()
  ]),
  
  // Search validation
  search: createValidationMiddleware([
    validationRules.searchQuery(),
    validationRules.page(),
    validationRules.limit()
  ]),

  // Payment order creation validation
  paymentOrderCreation: createValidationMiddleware([
    body('order_id').isInt({ min: 1 }).withMessage('Order ID must be a positive integer'),
    validationRules.paymentAmount(),
    validationRules.currency()
  ]),

  // Payment verification validation
  paymentVerification: createValidationMiddleware([
    body('order_id').isInt({ min: 1 }).withMessage('Order ID must be a positive integer'),
    validationRules.razorpayOrderId(),
    validationRules.razorpayPaymentId(),
    validationRules.razorpaySignature()
  ]),

  // COD payment validation
  codPayment: createValidationMiddleware([
    body('order_id').isInt({ min: 1 }).withMessage('Order ID must be a positive integer'),
    body('delivery_instructions').optional().isLength({ max: 500 }).withMessage('Delivery instructions must not exceed 500 characters')
  ]),

  // Payment order creation validation
  paymentOrderCreation: createValidationMiddleware([
    body('order_id').isInt({ min: 1 }).withMessage('Order ID must be a positive integer'),
    body('amount').isFloat({ min: 0.01, max: 999999.99 }).withMessage('Amount must be a positive number between 0.01 and 999999.99'),
    body('currency').optional().isIn(['INR']).withMessage('Currency must be INR')
  ]),

  // Payment verification validation
  paymentVerification: createValidationMiddleware([
    body('razorpay_order_id').matches(/^order_[a-zA-Z0-9]+$/).withMessage('Invalid Razorpay order ID format'),
    body('razorpay_payment_id').matches(/^pay_[a-zA-Z0-9]+$/).withMessage('Invalid Razorpay payment ID format'),
    body('razorpay_signature').matches(/^[a-f0-9]{64}$/).withMessage('Invalid Razorpay signature format'),
    body('order_id').isInt({ min: 1 }).withMessage('Order ID must be a positive integer')
  ]),

  // COD delivery confirmation validation
  codDeliveryConfirm: createValidationMiddleware([
    body('order_id').isInt({ min: 1 }).withMessage('Order ID must be a positive integer'),
    body('payment_collected').isBoolean().withMessage('Payment collected must be true or false'),
    body('collection_amount').if(body('payment_collected').equals(true))
      .isFloat({ min: 0.01 }).withMessage('Collection amount must be a positive number when payment is collected'),
    body('delivery_notes').optional().isLength({ max: 500 }).withMessage('Delivery notes must not exceed 500 characters'),
    body('delivery_person_name').isLength({ min: 2, max: 100 }).withMessage('Delivery person name must be between 2 and 100 characters'),
    body('delivery_person_phone').isMobilePhone('en-IN').withMessage('Delivery person phone must be a valid Indian mobile number')
  ])
};

module.exports = {
  sanitizeInput,
  sanitizeObject,
  sanitizeRequest,
  preventSQLInjection,
  handleValidationErrors,
  validationRules,
  createValidationMiddleware,
  validationMiddleware,
  containsSQLInjection
};