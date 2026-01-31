/**
 * Environment-Specific Configuration for Riya Collections
 * 
 * This module provides environment-specific configurations for:
 * - Development, staging, and production environments
 * - Security settings per environment
 * - Performance optimizations
 * - Monitoring and alerting configurations
 * 
 * Requirements: 14.1, 14.2 (Environment compatibility and configuration management)
 */

const { appLogger } = require('./logging');

/**
 * Base configuration shared across all environments
 */
const baseConfig = {
  app: {
    name: 'Riya Collections',
    version: '1.0.0',
    description: 'Professional cosmetic e-commerce platform'
  },
  
  server: {
    port: parseInt(process.env.PORT) || 5000,
    host: process.env.HOST || '0.0.0.0',
    timeout: parseInt(process.env.SERVER_TIMEOUT) || 30000,
    keepAliveTimeout: parseInt(process.env.KEEP_ALIVE_TIMEOUT) || 5000
  },
  
  database: {
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT) || 3306,
    name: process.env.DB_NAME || 'riya_collections',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    connectionLimit: parseInt(process.env.DB_CONNECTION_LIMIT) || 10,
    acquireTimeout: parseInt(process.env.DB_ACQUIRE_TIMEOUT) || 60000,
    timeout: parseInt(process.env.DB_TIMEOUT) || 60000,
    reconnect: process.env.DB_RECONNECT !== 'false',
    charset: 'utf8mb4'
  },
  
  jwt: {
    secret: process.env.JWT_SECRET,
    expiresIn: process.env.JWT_EXPIRES_IN || '24h',
    refreshSecret: process.env.JWT_REFRESH_SECRET,
    refreshExpiresIn: process.env.JWT_REFRESH_EXPIRES_IN || '7d',
    issuer: 'riya-collections',
    audience: 'riya-collections-users'
  },
  
  security: {
    bcryptSaltRounds: parseInt(process.env.BCRYPT_SALT_ROUNDS) || 12,
    sessionSecret: process.env.SESSION_SECRET,
    rateLimitWindowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 900000,
    rateLimitMaxRequests: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100,
    maxRequestSize: parseInt(process.env.MAX_REQUEST_SIZE) || 10485760, // 10MB
    slowRequestThreshold: parseInt(process.env.SLOW_REQUEST_THRESHOLD) || 5000
  },
  
  upload: {
    path: process.env.UPLOAD_PATH || 'uploads',
    maxFileSize: parseInt(process.env.MAX_FILE_SIZE) || 5242880, // 5MB
    allowedTypes: (process.env.ALLOWED_FILE_TYPES || 'image/jpeg,image/png,image/webp').split(','),
    imageQuality: parseInt(process.env.IMAGE_QUALITY) || 85,
    thumbnailSize: parseInt(process.env.THUMBNAIL_SIZE) || 300
  },
  
  email: {
    host: process.env.SMTP_HOST,
    port: parseInt(process.env.SMTP_PORT) || 587,
    secure: process.env.SMTP_SECURE === 'true',
    user: process.env.SMTP_USER,
    password: process.env.SMTP_PASSWORD,
    from: process.env.EMAIL_FROM || 'noreply@riyacollections.com',
    fromName: process.env.EMAIL_FROM_NAME || 'Riya Collections'
  },
  
  payment: {
    razorpay: {
      keyId: process.env.RAZORPAY_KEY_ID,
      keySecret: process.env.RAZORPAY_KEY_SECRET,
      webhookSecret: process.env.RAZORPAY_WEBHOOK_SECRET
    }
  },
  
  urls: {
    frontend: process.env.FRONTEND_URL || 'http://localhost:3000',
    backend: process.env.BASE_URL || 'http://localhost:5000',
    adminPanel: process.env.ADMIN_PANEL_URL || 'http://localhost:3000/admin',
    website: process.env.WEBSITE_URL || 'https://riyacollections.com',
    logo: process.env.LOGO_URL || 'https://riyacollections.com/assets/logo.png'
  }
};

/**
 * Development environment configuration
 */
const developmentConfig = {
  ...baseConfig,
  
  environment: 'development',
  
  debug: true,
  
  logging: {
    level: 'debug',
    console: true,
    file: true,
    colorize: true
  },
  
  security: {
    ...baseConfig.security,
    strictSSL: false,
    allowInsecureConnections: true,
    corsOrigins: ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000'],
    trustProxy: false
  },
  
  ssl: {
    enabled: false,
    enforceHTTPS: false
  },
  
  database: {
    ...baseConfig.database,
    debug: true,
    connectionLimit: 5
  },
  
  cache: {
    enabled: false,
    ttl: 300 // 5 minutes
  },
  
  monitoring: {
    enabled: false,
    metricsInterval: 60000 // 1 minute
  }
};

/**
 * Staging environment configuration
 */
const stagingConfig = {
  ...baseConfig,
  
  environment: 'staging',
  
  debug: false,
  
  logging: {
    level: 'info',
    console: true,
    file: true,
    colorize: false
  },
  
  security: {
    ...baseConfig.security,
    strictSSL: true,
    allowInsecureConnections: false,
    corsOrigins: process.env.ALLOWED_ORIGINS ? process.env.ALLOWED_ORIGINS.split(',') : [],
    trustProxy: true,
    rateLimitMaxRequests: 200
  },
  
  ssl: {
    enabled: process.env.SSL_ENABLED === 'true',
    enforceHTTPS: true,
    port: parseInt(process.env.SSL_PORT) || 443,
    keyPath: process.env.SSL_KEY_PATH,
    certPath: process.env.SSL_CERT_PATH,
    caPath: process.env.SSL_CA_PATH
  },
  
  database: {
    ...baseConfig.database,
    debug: false,
    connectionLimit: 15
  },
  
  cache: {
    enabled: true,
    ttl: 900 // 15 minutes
  },
  
  monitoring: {
    enabled: true,
    metricsInterval: 30000, // 30 seconds
    alerting: true
  },
  
  backup: {
    enabled: true,
    schedule: '0 2 * * *', // Daily at 2 AM
    retention: 7 // Keep 7 days
  }
};

/**
 * Production environment configuration
 */
const productionConfig = {
  ...baseConfig,
  
  environment: 'production',
  
  debug: false,
  
  logging: {
    level: 'warn',
    console: false,
    file: true,
    colorize: false,
    structured: true
  },
  
  security: {
    ...baseConfig.security,
    strictSSL: true,
    allowInsecureConnections: false,
    corsOrigins: process.env.ALLOWED_ORIGINS ? process.env.ALLOWED_ORIGINS.split(',') : [],
    trustProxy: true,
    rateLimitMaxRequests: 500,
    enableSecurityHeaders: true,
    enableCSP: true
  },
  
  ssl: {
    enabled: true,
    enforceHTTPS: true,
    port: parseInt(process.env.SSL_PORT) || 443,
    keyPath: process.env.SSL_KEY_PATH,
    certPath: process.env.SSL_CERT_PATH,
    caPath: process.env.SSL_CA_PATH,
    hsts: {
      maxAge: 31536000, // 1 year
      includeSubDomains: true,
      preload: true
    }
  },
  
  database: {
    ...baseConfig.database,
    debug: false,
    connectionLimit: 25,
    ssl: process.env.DB_SSL === 'true' ? {
      rejectUnauthorized: false
    } : false
  },
  
  cache: {
    enabled: true,
    ttl: 3600, // 1 hour
    redis: {
      host: process.env.REDIS_HOST,
      port: parseInt(process.env.REDIS_PORT) || 6379,
      password: process.env.REDIS_PASSWORD
    }
  },
  
  monitoring: {
    enabled: true,
    metricsInterval: 15000, // 15 seconds
    alerting: true,
    healthCheck: {
      enabled: true,
      interval: 30000, // 30 seconds
      timeout: 5000 // 5 seconds
    }
  },
  
  backup: {
    enabled: true,
    schedule: '0 1 * * *', // Daily at 1 AM
    retention: 30, // Keep 30 days
    compression: true,
    encryption: true
  },
  
  performance: {
    compression: true,
    staticCaching: true,
    apiCaching: true,
    imageOptimization: true
  }
};

/**
 * Get configuration for current environment
 */
const getConfig = () => {
  const env = process.env.NODE_ENV || 'development';
  
  let config;
  switch (env) {
    case 'production':
      config = productionConfig;
      break;
    case 'staging':
      config = stagingConfig;
      break;
    case 'development':
    default:
      config = developmentConfig;
      break;
  }
  
  // Validate required configuration
  validateConfig(config);
  
  return config;
};

/**
 * Validate configuration for required fields
 */
const validateConfig = (config) => {
  const required = [
    'jwt.secret',
    'security.sessionSecret',
    'database.host',
    'database.name'
  ];
  
  const missing = [];
  
  required.forEach(path => {
    const value = getNestedValue(config, path);
    if (!value) {
      missing.push(path);
    }
  });
  
  if (missing.length > 0) {
    const error = `Missing required configuration: ${missing.join(', ')}`;
    appLogger.error('Configuration validation failed', { missing });
    throw new Error(error);
  }
  
  // Environment-specific validations
  if (config.environment === 'production') {
    const productionRequired = [
      'ssl.keyPath',
      'ssl.certPath',
      'email.host',
      'email.user',
      'payment.razorpay.keyId'
    ];
    
    const productionMissing = [];
    productionRequired.forEach(path => {
      const value = getNestedValue(config, path);
      if (!value) {
        productionMissing.push(path);
      }
    });
    
    if (productionMissing.length > 0) {
      appLogger.warn('Production configuration incomplete', { missing: productionMissing });
    }
  }
};

/**
 * Get nested configuration value by dot notation path
 */
const getNestedValue = (obj, path) => {
  return path.split('.').reduce((current, key) => current && current[key], obj);
};

/**
 * Set nested configuration value by dot notation path
 */
const setNestedValue = (obj, path, value) => {
  const keys = path.split('.');
  const lastKey = keys.pop();
  const target = keys.reduce((current, key) => {
    if (!current[key]) current[key] = {};
    return current[key];
  }, obj);
  target[lastKey] = value;
};

/**
 * Override configuration with environment variables
 */
const applyEnvironmentOverrides = (config) => {
  // Map environment variables to configuration paths
  const envMappings = {
    'NODE_ENV': 'environment',
    'PORT': 'server.port',
    'HOST': 'server.host',
    'DB_HOST': 'database.host',
    'DB_PORT': 'database.port',
    'DB_NAME': 'database.name',
    'DB_USER': 'database.user',
    'DB_PASSWORD': 'database.password',
    'JWT_SECRET': 'jwt.secret',
    'FRONTEND_URL': 'urls.frontend',
    'BASE_URL': 'urls.backend'
  };
  
  Object.entries(envMappings).forEach(([envVar, configPath]) => {
    const value = process.env[envVar];
    if (value !== undefined) {
      setNestedValue(config, configPath, value);
    }
  });
  
  return config;
};

/**
 * Get configuration with environment overrides applied
 */
const getEnvironmentConfig = () => {
  const config = getConfig();
  return applyEnvironmentOverrides(config);
};

/**
 * Initialize environment configuration
 */
const initializeEnvironment = () => {
  const config = getEnvironmentConfig();
  
  appLogger.info('Environment configuration initialized', {
    environment: config.environment,
    debug: config.debug,
    ssl: config.ssl?.enabled || false,
    database: config.database.host,
    logLevel: config.logging.level
  });
  
  // Set process title for easier identification
  process.title = `riya-collections-${config.environment}`;
  
  return config;
};

/**
 * Get environment-specific database configuration
 */
const getDatabaseConfig = () => {
  const config = getEnvironmentConfig();
  return {
    host: config.database.host,
    port: config.database.port,
    user: config.database.user,
    password: config.database.password,
    database: config.database.name,
    connectionLimit: config.database.connectionLimit,
    acquireTimeout: config.database.acquireTimeout,
    timeout: config.database.timeout,
    reconnect: config.database.reconnect,
    charset: config.database.charset,
    ssl: config.database.ssl,
    debug: config.database.debug
  };
};

/**
 * Get environment-specific security configuration
 */
const getSecurityConfig = () => {
  const config = getEnvironmentConfig();
  return {
    bcryptSaltRounds: config.security.bcryptSaltRounds,
    sessionSecret: config.security.sessionSecret,
    rateLimitWindowMs: config.security.rateLimitWindowMs,
    rateLimitMaxRequests: config.security.rateLimitMaxRequests,
    maxRequestSize: config.security.maxRequestSize,
    slowRequestThreshold: config.security.slowRequestThreshold,
    strictSSL: config.security.strictSSL,
    allowInsecureConnections: config.security.allowInsecureConnections,
    corsOrigins: config.security.corsOrigins,
    trustProxy: config.security.trustProxy
  };
};

/**
 * Check if feature is enabled in current environment
 */
const isFeatureEnabled = (feature) => {
  const config = getEnvironmentConfig();
  
  switch (feature) {
    case 'ssl':
      return config.ssl?.enabled || false;
    case 'cache':
      return config.cache?.enabled || false;
    case 'monitoring':
      return config.monitoring?.enabled || false;
    case 'backup':
      return config.backup?.enabled || false;
    case 'debug':
      return config.debug || false;
    default:
      return false;
  }
};

module.exports = {
  getConfig,
  getEnvironmentConfig,
  initializeEnvironment,
  getDatabaseConfig,
  getSecurityConfig,
  isFeatureEnabled,
  validateConfig,
  applyEnvironmentOverrides,
  developmentConfig,
  stagingConfig,
  productionConfig
};