/**
 * Security Configuration for Riya Collections
 * 
 * This module provides comprehensive security configuration including:
 * - HTTPS/SSL certificate setup
 * - Security headers configuration
 * - CORS policies
 * - Rate limiting
 * - Content Security Policy (CSP)
 * 
 * Requirements: 9.4 (HTTPS Communication)
 */

const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const fs = require('fs');
const path = require('path');

/**
 * SSL/HTTPS Configuration
 */
const getSSLConfig = () => {
  const sslConfig = {
    enabled: process.env.SSL_ENABLED === 'true',
    port: parseInt(process.env.SSL_PORT) || 443,
    keyPath: process.env.SSL_KEY_PATH,
    certPath: process.env.SSL_CERT_PATH,
    caPath: process.env.SSL_CA_PATH,
    passphrase: process.env.SSL_PASSPHRASE
  };

  // Validate SSL configuration if enabled
  if (sslConfig.enabled) {
    if (!sslConfig.keyPath || !sslConfig.certPath) {
      console.warn('⚠️  SSL enabled but key/cert paths not provided');
      sslConfig.enabled = false;
    } else {
      // Check if SSL files exist
      try {
        if (!fs.existsSync(sslConfig.keyPath)) {
          console.warn(`⚠️  SSL key file not found: ${sslConfig.keyPath}`);
          sslConfig.enabled = false;
        }
        if (!fs.existsSync(sslConfig.certPath)) {
          console.warn(`⚠️  SSL certificate file not found: ${sslConfig.certPath}`);
          sslConfig.enabled = false;
        }
        if (sslConfig.caPath && !fs.existsSync(sslConfig.caPath)) {
          console.warn(`⚠️  SSL CA file not found: ${sslConfig.caPath}`);
        }
      } catch (error) {
        console.warn('⚠️  Error checking SSL files:', error.message);
        sslConfig.enabled = false;
      }
    }
  }

  return sslConfig;
};

/**
 * Load SSL certificates
 */
const loadSSLCertificates = () => {
  const sslConfig = getSSLConfig();
  
  if (!sslConfig.enabled) {
    return null;
  }

  try {
    const credentials = {
      key: fs.readFileSync(sslConfig.keyPath, 'utf8'),
      cert: fs.readFileSync(sslConfig.certPath, 'utf8')
    };

    // Add CA certificate if provided
    if (sslConfig.caPath && fs.existsSync(sslConfig.caPath)) {
      credentials.ca = fs.readFileSync(sslConfig.caPath, 'utf8');
    }

    // Add passphrase if provided
    if (sslConfig.passphrase) {
      credentials.passphrase = sslConfig.passphrase;
    }

    console.log('✅ SSL certificates loaded successfully');
    return credentials;
  } catch (error) {
    console.error('❌ Failed to load SSL certificates:', error.message);
    return null;
  }
};

/**
 * Content Security Policy Configuration
 */
const getCSPConfig = () => {
  const isDevelopment = process.env.NODE_ENV === 'development';
  const frontendUrl = process.env.FRONTEND_URL || 'http://localhost:3000';
  const baseUrl = process.env.BASE_URL || 'http://localhost:5000';
  
  return {
    directives: {
      defaultSrc: ["'self'"],
      styleSrc: [
        "'self'",
        "'unsafe-inline'", // Allow inline styles for dynamic styling
        "https://fonts.googleapis.com",
        "https://cdnjs.cloudflare.com"
      ],
      scriptSrc: [
        "'self'",
        ...(isDevelopment ? ["'unsafe-eval'", "'unsafe-inline'"] : []),
        "https://checkout.razorpay.com",
        "https://cdnjs.cloudflare.com"
      ],
      imgSrc: [
        "'self'",
        "data:",
        "blob:",
        baseUrl,
        frontendUrl,
        "https://via.placeholder.com", // For placeholder images
        "https://images.unsplash.com" // For demo images
      ],
      fontSrc: [
        "'self'",
        "https://fonts.gstatic.com",
        "https://cdnjs.cloudflare.com"
      ],
      connectSrc: [
        "'self'",
        baseUrl,
        "https://api.razorpay.com",
        "https://checkout.razorpay.com",
        ...(isDevelopment ? [frontendUrl, "ws://localhost:*", "wss://localhost:*"] : [])
      ],
      frameSrc: [
        "'self'",
        "https://api.razorpay.com",
        "https://checkout.razorpay.com"
      ],
      objectSrc: ["'none'"],
      mediaSrc: ["'self'", baseUrl],
      childSrc: ["'none'"],
      workerSrc: ["'self'"],
      manifestSrc: ["'self'"],
      formAction: ["'self'"],
      frameAncestors: ["'none'"],
      baseUri: ["'self'"],
      upgradeInsecureRequests: process.env.NODE_ENV === 'production' ? [] : null
    },
    reportOnly: isDevelopment, // Only report violations in development
    reportUri: '/api/security/csp-report'
  };
};

/**
 * Helmet Security Headers Configuration
 */
const getHelmetConfig = () => {
  const isDevelopment = process.env.NODE_ENV === 'development';
  const isProduction = process.env.NODE_ENV === 'production';
  
  return {
    // Content Security Policy
    contentSecurityPolicy: getCSPConfig(),
    
    // HTTP Strict Transport Security (HSTS)
    hsts: {
      maxAge: 31536000, // 1 year
      includeSubDomains: true,
      preload: true
    },
    
    // X-Frame-Options
    frameguard: {
      action: 'deny' // Prevent clickjacking
    },
    
    // X-Content-Type-Options
    noSniff: true, // Prevent MIME type sniffing
    
    // X-XSS-Protection
    xssFilter: true,
    
    // Referrer Policy
    referrerPolicy: {
      policy: ['no-referrer-when-downgrade', 'strict-origin-when-cross-origin']
    },
    
    // Hide X-Powered-By header
    hidePoweredBy: true,
    
    // DNS Prefetch Control
    dnsPrefetchControl: {
      allow: false
    },
    
    // Expect-CT
    expectCt: isProduction ? {
      maxAge: 86400, // 24 hours
      enforce: true
    } : false,
    
    // Feature Policy / Permissions Policy
    permissionsPolicy: {
      camera: [],
      microphone: [],
      geolocation: [],
      payment: ['self', 'https://api.razorpay.com'],
      usb: [],
      magnetometer: [],
      gyroscope: [],
      accelerometer: []
    },
    
    // Cross-Origin Embedder Policy
    crossOriginEmbedderPolicy: false, // Disabled for compatibility
    
    // Cross-Origin Opener Policy
    crossOriginOpenerPolicy: {
      policy: 'same-origin-allow-popups' // Allow Razorpay popups
    },
    
    // Cross-Origin Resource Policy
    crossOriginResourcePolicy: {
      policy: 'cross-origin' // Allow cross-origin requests for API
    }
  };
};

/**
 * CORS Configuration
 */
const getCORSConfig = () => {
  const frontendUrl = process.env.FRONTEND_URL || 'http://localhost:3000';
  const adminPanelUrl = process.env.ADMIN_PANEL_URL || 'http://localhost:3000/admin';
  const isDevelopment = process.env.NODE_ENV === 'development';
  
  // Allowed origins
  const allowedOrigins = [
    frontendUrl,
    adminPanelUrl
  ];
  
  // Add development origins
  if (isDevelopment) {
    allowedOrigins.push(
      'http://localhost:3000',
      'http://localhost:3001',
      'http://127.0.0.1:3000',
      'http://127.0.0.1:3001'
    );
  }
  
  // Add production origins from environment
  if (process.env.ALLOWED_ORIGINS) {
    const additionalOrigins = process.env.ALLOWED_ORIGINS.split(',').map(origin => origin.trim());
    allowedOrigins.push(...additionalOrigins);
  }
  
  return {
    origin: (origin, callback) => {
      // Allow requests with no origin (mobile apps, Postman, etc.)
      if (!origin) return callback(null, true);
      
      if (allowedOrigins.includes(origin)) {
        callback(null, true);
      } else {
        console.warn(`🚫 CORS blocked origin: ${origin}`);
        callback(new Error('Not allowed by CORS'));
      }
    },
    credentials: true, // Allow cookies and authorization headers
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    allowedHeaders: [
      'Origin',
      'X-Requested-With',
      'Content-Type',
      'Accept',
      'Authorization',
      'Cache-Control',
      'Pragma',
      'X-API-Key'
    ],
    exposedHeaders: [
      'X-Total-Count',
      'X-Page-Count',
      'X-Current-Page',
      'X-Per-Page'
    ],
    maxAge: 86400 // 24 hours
  };
};

/**
 * Rate Limiting Configuration
 */
const getRateLimitConfig = () => {
  const windowMs = parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000; // 15 minutes
  const maxRequests = parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100;
  
  return {
    // General API rate limit
    general: rateLimit({
      windowMs,
      max: maxRequests,
      message: {
        error: {
          message: 'Too many requests from this IP, please try again later.',
          type: 'RATE_LIMIT_EXCEEDED',
          retryAfter: Math.ceil(windowMs / 1000)
        }
      },
      standardHeaders: true, // Return rate limit info in headers
      legacyHeaders: false,
      handler: (req, res) => {
        console.warn(`🚫 Rate limit exceeded for IP: ${req.ip}, Path: ${req.path}`);
        res.status(429).json({
          success: false,
          error: {
            message: 'Too many requests from this IP, please try again later.',
            type: 'RATE_LIMIT_EXCEEDED',
            retryAfter: Math.ceil(windowMs / 1000)
          }
        });
      }
    }),
    
    // Strict rate limit for authentication endpoints
    auth: rateLimit({
      windowMs: 15 * 60 * 1000, // 15 minutes
      max: 10, // 10 attempts per window
      message: {
        error: {
          message: 'Too many authentication attempts, please try again later.',
          type: 'AUTH_RATE_LIMIT_EXCEEDED',
          retryAfter: 900 // 15 minutes
        }
      },
      standardHeaders: true,
      legacyHeaders: false,
      skipSuccessfulRequests: true, // Don't count successful requests
      handler: (req, res) => {
        console.warn(`🚫 Auth rate limit exceeded for IP: ${req.ip}, Path: ${req.path}`);
        res.status(429).json({
          success: false,
          error: {
            message: 'Too many authentication attempts, please try again later.',
            type: 'AUTH_RATE_LIMIT_EXCEEDED',
            retryAfter: 900
          }
        });
      }
    }),
    
    // Payment endpoints rate limit
    payment: rateLimit({
      windowMs: 5 * 60 * 1000, // 5 minutes
      max: 20, // 20 payment attempts per window
      message: {
        error: {
          message: 'Too many payment attempts, please try again later.',
          type: 'PAYMENT_RATE_LIMIT_EXCEEDED',
          retryAfter: 300 // 5 minutes
        }
      },
      standardHeaders: true,
      legacyHeaders: false,
      handler: (req, res) => {
        console.warn(`🚫 Payment rate limit exceeded for IP: ${req.ip}, Path: ${req.path}`);
        res.status(429).json({
          success: false,
          error: {
            message: 'Too many payment attempts, please try again later.',
            type: 'PAYMENT_RATE_LIMIT_EXCEEDED',
            retryAfter: 300
          }
        });
      }
    }),
    
    // File upload rate limit
    upload: rateLimit({
      windowMs: 10 * 60 * 1000, // 10 minutes
      max: 50, // 50 uploads per window
      message: {
        error: {
          message: 'Too many file uploads, please try again later.',
          type: 'UPLOAD_RATE_LIMIT_EXCEEDED',
          retryAfter: 600 // 10 minutes
        }
      },
      standardHeaders: true,
      legacyHeaders: false,
      handler: (req, res) => {
        console.warn(`🚫 Upload rate limit exceeded for IP: ${req.ip}, Path: ${req.path}`);
        res.status(429).json({
          success: false,
          error: {
            message: 'Too many file uploads, please try again later.',
            type: 'UPLOAD_RATE_LIMIT_EXCEEDED',
            retryAfter: 600
          }
        });
      }
    })
  };
};

/**
 * Security middleware setup function
 */
const setupSecurityMiddleware = (app) => {
  // Apply Helmet security headers
  app.use(helmet(getHelmetConfig()));
  
  // Apply rate limiting
  const rateLimits = getRateLimitConfig();
  
  // General rate limiting for all API routes
  app.use('/api/', rateLimits.general);
  
  // Specific rate limits for sensitive endpoints
  app.use('/api/auth/', rateLimits.auth);
  app.use('/api/payments/', rateLimits.payment);
  app.use('/api/admin/products/*/images', rateLimits.upload);
  
  console.log('✅ Security middleware configured');
};

/**
 * CSP violation reporting endpoint
 */
const setupCSPReporting = (app) => {
  app.post('/api/security/csp-report', (req, res) => {
    const report = req.body;
    console.warn('🚫 CSP Violation Report:', JSON.stringify(report, null, 2));
    
    // In production, you might want to log this to a security monitoring service
    if (process.env.NODE_ENV === 'production') {
      // Log to security monitoring service
      // Example: securityLogger.warn('CSP Violation', report);
    }
    
    res.status(204).end();
  });
};

/**
 * Security headers validation middleware
 */
const validateSecurityHeaders = (req, res, next) => {
  // Check for required security headers in responses
  const originalSend = res.send;
  
  res.send = function(data) {
    // Ensure security headers are present
    if (!res.get('X-Content-Type-Options')) {
      res.set('X-Content-Type-Options', 'nosniff');
    }
    
    if (!res.get('X-Frame-Options')) {
      res.set('X-Frame-Options', 'DENY');
    }
    
    if (!res.get('X-XSS-Protection')) {
      res.set('X-XSS-Protection', '1; mode=block');
    }
    
    // Call original send
    originalSend.call(this, data);
  };
  
  next();
};

/**
 * HTTPS redirect middleware for production
 */
const httpsRedirect = (req, res, next) => {
  if (process.env.NODE_ENV === 'production' && !req.secure && req.get('X-Forwarded-Proto') !== 'https') {
    return res.redirect(301, `https://${req.get('Host')}${req.url}`);
  }
  next();
};

/**
 * Security audit logging
 */
const logSecurityEvent = (event, details = {}) => {
  const logEntry = {
    timestamp: new Date().toISOString(),
    event,
    details,
    environment: process.env.NODE_ENV
  };
  
  console.log('🔒 Security Event:', JSON.stringify(logEntry));
  
  // In production, send to security monitoring service
  if (process.env.NODE_ENV === 'production') {
    // Example: securityLogger.info('Security Event', logEntry);
  }
};

module.exports = {
  getSSLConfig,
  loadSSLCertificates,
  getHelmetConfig,
  getCORSConfig,
  getRateLimitConfig,
  setupSecurityMiddleware,
  setupCSPReporting,
  validateSecurityHeaders,
  httpsRedirect,
  logSecurityEvent
};