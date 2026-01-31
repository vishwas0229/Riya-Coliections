/**
 * Security Monitoring and Logging Middleware
 * 
 * This module provides comprehensive security monitoring including:
 * - Request logging and analysis
 * - Suspicious activity detection
 * - Security event logging
 * - Attack pattern recognition
 * 
 * Requirements: 9.4 (HTTPS Communication), Security monitoring
 */

const { logSecurityEvent } = require('../config/security');

/**
 * Security request logging middleware
 */
const securityLogger = (req, res, next) => {
  const startTime = Date.now();
  
  // Capture original end method
  const originalEnd = res.end;
  
  res.end = function(chunk, encoding) {
    const duration = Date.now() - startTime;
    
    // Log security-relevant requests
    const securityRelevantPaths = [
      '/api/auth/',
      '/api/admin/',
      '/api/payments/',
      '/api/security/'
    ];
    
    const isSecurityRelevant = securityRelevantPaths.some(path => req.path.startsWith(path));
    
    if (isSecurityRelevant || res.statusCode >= 400) {
      const logData = {
        method: req.method,
        path: req.path,
        statusCode: res.statusCode,
        duration,
        ip: req.ip,
        userAgent: req.get('User-Agent'),
        referer: req.get('Referer'),
        contentLength: res.get('Content-Length'),
        timestamp: new Date().toISOString()
      };
      
      // Add authentication info if available
      if (req.user) {
        logData.userId = req.user.id;
        logData.userType = 'customer';
      } else if (req.admin) {
        logData.adminId = req.admin.id;
        logData.userType = 'admin';
      }
      
      // Log based on status code
      if (res.statusCode >= 500) {
        logSecurityEvent('SERVER_ERROR', logData);
      } else if (res.statusCode >= 400) {
        logSecurityEvent('CLIENT_ERROR', logData);
      } else if (isSecurityRelevant) {
        logSecurityEvent('SECURITY_REQUEST', logData);
      }
    }
    
    // Call original end method
    originalEnd.call(this, chunk, encoding);
  };
  
  next();
};

/**
 * Suspicious activity detection middleware
 */
const suspiciousActivityDetector = (req, res, next) => {
  const suspiciousPatterns = {
    // SQL injection patterns in URL
    sqlInjection: /(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION)\b).*?(\b(FROM|INTO|SET|WHERE|VALUES)\b)/i,
    
    // XSS patterns
    xssAttempt: /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
    
    // Path traversal
    pathTraversal: /(\.\.[\/\\]){2,}/,
    
    // Command injection (excluding & in query parameters)
    commandInjection: /[;|`$(){}[\]]/,
    
    // Common attack tools user agents
    attackTools: /(sqlmap|nikto|nmap|burp|acunetix|nessus|openvas|w3af)/i
  };
  
  const userAgent = req.get('User-Agent') || '';
  const fullUrl = req.originalUrl;
  const referer = req.get('Referer') || '';
  
  // Separate URL path from query parameters for more intelligent checking
  const urlParts = fullUrl.split('?');
  const urlPath = urlParts[0];
  const queryString = urlParts[1] || '';
  
  // Check for suspicious patterns
  const detectedThreats = [];
  
  for (const [threatType, pattern] of Object.entries(suspiciousPatterns)) {
    let shouldCheck = true;
    
    // For command injection, don't check query parameters for & character
    if (threatType === 'commandInjection') {
      // Check path and user agent, but be more lenient with query parameters
      if (pattern.test(urlPath) || pattern.test(userAgent) || pattern.test(referer)) {
        detectedThreats.push(threatType);
      }
      // For query parameters, use a stricter pattern that excludes normal URL characters
      const strictCommandPattern = /[;|`$(){}[\]]/;
      if (queryString && strictCommandPattern.test(queryString)) {
        detectedThreats.push(threatType);
      }
      shouldCheck = false; // Skip the general check for this pattern
    }
    
    if (shouldCheck && (pattern.test(fullUrl) || pattern.test(userAgent) || pattern.test(referer))) {
      detectedThreats.push(threatType);
    }
  }
  
  // Check request body for threats (if present)
  if (req.body && typeof req.body === 'object') {
    const bodyString = JSON.stringify(req.body);
    for (const [threatType, pattern] of Object.entries(suspiciousPatterns)) {
      if (pattern.test(bodyString)) {
        detectedThreats.push(`${threatType}_in_body`);
      }
    }
  }
  
  // Log suspicious activity
  if (detectedThreats.length > 0) {
    logSecurityEvent('SUSPICIOUS_ACTIVITY', {
      threats: detectedThreats,
      method: req.method,
      path: req.path,
      fullUrl,
      ip: req.ip,
      userAgent,
      referer,
      timestamp: new Date().toISOString()
    });
    
    // Block obvious attack attempts
    const highRiskThreats = ['sqlInjection', 'commandInjection', 'attackTools'];
    const hasHighRiskThreat = detectedThreats.some(threat => 
      highRiskThreats.some(highRisk => threat.includes(highRisk))
    );
    
    if (hasHighRiskThreat) {
      console.warn(`🚫 Blocking high-risk request from ${req.ip}: ${detectedThreats.join(', ')}`);
      return res.status(403).json({
        success: false,
        error: {
          message: 'Request blocked for security reasons',
          type: 'SECURITY_VIOLATION'
        }
      });
    }
  }
  
  next();
};

/**
 * Failed authentication attempt tracker
 */
const authFailureTracker = (() => {
  const failedAttempts = new Map();
  const WINDOW_SIZE = 15 * 60 * 1000; // 15 minutes
  const MAX_ATTEMPTS = 5;
  
  return (req, res, next) => {
    const ip = req.ip;
    const now = Date.now();
    
    // Clean old entries
    for (const [key, data] of failedAttempts.entries()) {
      if (now - data.firstAttempt > WINDOW_SIZE) {
        failedAttempts.delete(key);
      }
    }
    
    // Check if IP is blocked
    const attempts = failedAttempts.get(ip);
    if (attempts && attempts.count >= MAX_ATTEMPTS) {
      const timeLeft = Math.ceil((attempts.firstAttempt + WINDOW_SIZE - now) / 1000 / 60);
      
      logSecurityEvent('AUTH_BLOCKED_IP', {
        ip,
        attempts: attempts.count,
        timeLeft,
        path: req.path
      });
      
      return res.status(429).json({
        success: false,
        error: {
          message: `Too many failed authentication attempts. Try again in ${timeLeft} minutes.`,
          type: 'AUTH_RATE_LIMIT_EXCEEDED',
          retryAfter: timeLeft * 60
        }
      });
    }
    
    // Store original send method to intercept auth failures
    const originalSend = res.send;
    res.send = function(data) {
      // Check if this is an authentication failure
      const isAuthFailure = res.statusCode === 401 || 
        (typeof data === 'string' && data.includes('Invalid credentials')) ||
        (typeof data === 'object' && data.success === false && 
         (data.message?.includes('Invalid') || data.message?.includes('credentials')));
      
      if (isAuthFailure && req.path.includes('/auth/')) {
        // Track failed attempt
        const current = failedAttempts.get(ip) || { count: 0, firstAttempt: now };
        current.count++;
        if (current.count === 1) {
          current.firstAttempt = now;
        }
        failedAttempts.set(ip, current);
        
        logSecurityEvent('AUTH_FAILURE', {
          ip,
          path: req.path,
          attempts: current.count,
          userAgent: req.get('User-Agent'),
          timestamp: new Date().toISOString()
        });
      }
      
      // Call original send
      originalSend.call(this, data);
    };
    
    next();
  };
})();

/**
 * Security headers validation middleware
 */
const validateSecurityHeaders = (req, res, next) => {
  // Store original setHeader method
  const originalSetHeader = res.setHeader;
  
  res.setHeader = function(name, value) {
    // Validate security-critical headers
    if (name.toLowerCase() === 'access-control-allow-origin') {
      const allowedOrigins = (process.env.ALLOWED_ORIGINS || '').split(',').map(o => o.trim());
      const frontendUrl = process.env.FRONTEND_URL || 'http://localhost:3000';
      
      if (value !== '*' && !allowedOrigins.includes(value) && value !== frontendUrl) {
        console.warn(`⚠️  Potentially unsafe CORS origin: ${value}`);
        logSecurityEvent('UNSAFE_CORS_ORIGIN', {
          origin: value,
          path: req.path,
          ip: req.ip
        });
      }
    }
    
    // Call original setHeader
    originalSetHeader.call(this, name, value);
  };
  
  next();
};

/**
 * Request size monitoring
 */
const requestSizeMonitor = (req, res, next) => {
  const maxSize = parseInt(process.env.MAX_REQUEST_SIZE) || 10 * 1024 * 1024; // 10MB default
  
  if (req.get('Content-Length')) {
    const contentLength = parseInt(req.get('Content-Length'));
    
    if (contentLength > maxSize) {
      logSecurityEvent('LARGE_REQUEST', {
        contentLength,
        maxSize,
        path: req.path,
        ip: req.ip,
        method: req.method
      });
      
      return res.status(413).json({
        success: false,
        error: {
          message: 'Request entity too large',
          type: 'REQUEST_TOO_LARGE',
          maxSize
        }
      });
    }
  }
  
  next();
};

/**
 * Slow request detection
 */
const slowRequestDetector = (req, res, next) => {
  const startTime = Date.now();
  const slowThreshold = parseInt(process.env.SLOW_REQUEST_THRESHOLD) || 5000; // 5 seconds
  
  const originalEnd = res.end;
  res.end = function(chunk, encoding) {
    const duration = Date.now() - startTime;
    
    if (duration > slowThreshold) {
      logSecurityEvent('SLOW_REQUEST', {
        duration,
        threshold: slowThreshold,
        method: req.method,
        path: req.path,
        ip: req.ip,
        userAgent: req.get('User-Agent')
      });
    }
    
    originalEnd.call(this, chunk, encoding);
  };
  
  next();
};

/**
 * Security monitoring setup function
 */
const setupSecurityMonitoring = (app) => {
  // Apply security monitoring middleware
  app.use(securityLogger);
  app.use(suspiciousActivityDetector);
  app.use(authFailureTracker);
  app.use(validateSecurityHeaders);
  app.use(requestSizeMonitor);
  app.use(slowRequestDetector);
  
  console.log('✅ Security monitoring configured');
};

/**
 * Generate security report
 */
const generateSecurityReport = () => {
  // This would typically aggregate security events from a logging system
  // For now, return a basic report structure
  return {
    timestamp: new Date().toISOString(),
    environment: process.env.NODE_ENV,
    sslEnabled: process.env.SSL_ENABLED === 'true',
    securityHeaders: {
      helmet: true,
      cors: true,
      rateLimiting: true
    },
    monitoring: {
      requestLogging: true,
      suspiciousActivityDetection: true,
      authFailureTracking: true,
      securityHeaderValidation: true
    },
    recommendations: [
      'Ensure SSL certificates are valid and up to date',
      'Regularly review security logs for suspicious activity',
      'Keep security dependencies updated',
      'Monitor for new security vulnerabilities'
    ]
  };
};

module.exports = {
  securityLogger,
  suspiciousActivityDetector,
  authFailureTracker,
  validateSecurityHeaders,
  requestSizeMonitor,
  slowRequestDetector,
  setupSecurityMonitoring,
  generateSecurityReport
};