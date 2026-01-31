const express = require('express');
const cors = require('cors');
const compression = require('compression');
require('dotenv').config();

// Initialize environment configuration
const { initializeEnvironment, isFeatureEnabled } = require('./config/environment');
const config = initializeEnvironment();

// Initialize logging system
const { initializeLogging, requestLoggingMiddleware, errorLoggingMiddleware, appLogger } = require('./config/logging');
initializeLogging();

// Import security configuration
const { getCORSConfig, setupSecurityMiddleware, setupCSPReporting, httpsRedirect, logSecurityEvent } = require('./config/security');
const { setupSecurityMonitoring } = require('./middleware/security-monitoring');

// Initialize backup system
const { initializeBackupSystem } = require('./utils/backup-recovery');
initializeBackupSystem();

// Initialize monitoring system
const { startMonitoring } = require('./utils/monitoring');
if (config.monitoring?.enabled) {
  startMonitoring(config.monitoring.metricsInterval || 60000);
}

// Initialize performance monitoring
const { performanceMiddleware } = require('./utils/performance-monitor');

// Initialize cache system
const { cacheManager } = require('./utils/cache-manager');

// Initialize query optimization
const { queryOptimizationMiddleware } = require('./middleware/query-optimization');

// Import database configuration
const { testConnection } = require('./config/database');

// Import image upload system
const { initializeUploadSystem } = require('./middleware/image-upload');

// Import routes
const authRoutes = require('./routes/auth');
const productRoutes = require('./routes/products');
const cartRoutes = require('./routes/cart');
const orderRoutes = require('./routes/orders');
const paymentRoutes = require('./routes/payments');
const addressRoutes = require('./routes/addresses');
const emailRoutes = require('./routes/emails');
const imageRoutes = require('./routes/images');
const adminRoutes = require('./routes/admin');
const securityRoutes = require('./routes/security');
const performanceRoutes = require('./routes/performance');

// Import WebSocket server
const WebSocketServer = require('./utils/websocket-server');

const app = express();
const PORT = process.env.PORT || 5000;

// HTTPS redirect middleware (production only)
app.use(httpsRedirect);

// Security middleware setup
setupSecurityMiddleware(app);

// Security monitoring setup
setupSecurityMonitoring(app);

// Performance monitoring middleware
app.use(performanceMiddleware);

// Query optimization middleware
app.use(queryOptimizationMiddleware);

// Request logging middleware
app.use(requestLoggingMiddleware);

// CORS configuration
app.use(cors(getCORSConfig()));

// CSP violation reporting
setupCSPReporting(app);

// Body parsing middleware
app.use(compression());
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Static file serving for uploads
app.use('/uploads', express.static('uploads'));

// Health check endpoint
app.get('/health', (req, res) => {
  res.status(200).json({ 
    status: 'OK', 
    message: 'Riya Collections API is running',
    timestamp: new Date().toISOString()
  });
});

// API routes will be added here
app.use('/api/auth', authRoutes);
app.use('/api/products', productRoutes);
app.use('/api/cart', cartRoutes);
app.use('/api/orders', orderRoutes);
app.use('/api/payments', paymentRoutes);
app.use('/api/addresses', addressRoutes);
app.use('/api/emails', emailRoutes);
app.use('/api/images', imageRoutes);
app.use('/api/admin', adminRoutes);
app.use('/api/security', securityRoutes);
app.use('/api/performance', performanceRoutes);

app.get('/api', (req, res) => {
  res.json({ 
    message: 'Welcome to Riya Collections API',
    version: '1.0.0',
    security: {
      ssl: process.env.SSL_ENABLED === 'true',
      environment: process.env.NODE_ENV
    },
    endpoints: {
      health: '/health',
      api: '/api',
      auth: '/api/auth',
      products: '/api/products',
      cart: '/api/cart',
      orders: '/api/orders',
      payments: '/api/payments',
      addresses: '/api/addresses',
      emails: '/api/emails',
      admin: '/api/admin',
      security: '/api/security',
      performance: '/api/performance'
    }
  });
});

// Error handling middleware
app.use(errorLoggingMiddleware);
app.use((err, req, res, next) => {
  appLogger.error('Unhandled application error', {
    error: err.message,
    stack: err.stack,
    path: req.path,
    method: req.method,
    ip: req.ip,
    userAgent: req.get('User-Agent')
  });
  
  // Log security-related errors
  if (err.message.includes('CORS') || err.message.includes('rate limit') || err.message.includes('security')) {
    logSecurityEvent('SECURITY_ERROR', {
      error: err.message,
      path: req.path,
      ip: req.ip,
      userAgent: req.get('User-Agent')
    });
  }
  
  // Don't leak error details in production
  const isDevelopment = process.env.NODE_ENV === 'development';
  
  res.status(err.status || 500).json({
    error: {
      message: err.message || 'Internal Server Error',
      ...(isDevelopment && { stack: err.stack })
    }
  });
});

// 404 handler
app.use('*', (req, res) => {
  res.status(404).json({
    error: {
      message: 'Endpoint not found',
      path: req.originalUrl
    }
  });
});

// Start server only if not in test environment
if (process.env.NODE_ENV !== 'test') {
  // Import HTTPS server configuration
  const { startServers, validateSSLCertificate } = require('./config/https-server');
  
  // Validate SSL configuration if enabled
  const sslValidation = validateSSLCertificate();
  if (process.env.SSL_ENABLED === 'true' && !sslValidation.valid) {
    console.warn('⚠️  SSL validation failed:', sslValidation.reason);
    console.warn('⚠️  Falling back to HTTP server');
  }
  
  // Start servers (HTTP and/or HTTPS)
  startServers(app)
    .then(async (servers) => {
      console.log(`🚀 Riya Collections API server started`);
      console.log(`📱 Environment: ${process.env.NODE_ENV || 'development'}`);
      
      if (servers.https) {
        console.log(`🔒 HTTPS: https://localhost:${servers.https.port}`);
        console.log(`🔗 Health check: https://localhost:${servers.https.port}/health`);
      }
      
      if (servers.http) {
        const protocol = servers.sslEnabled ? 'HTTP (redirect)' : 'HTTP';
        console.log(`🔓 ${protocol}: http://localhost:${servers.http.port}`);
        if (!servers.sslEnabled) {
          console.log(`🔗 Health check: http://localhost:${servers.http.port}/health`);
        }
      }
      
      // Test database connection
      await testConnection();
      
      // Initialize image upload system
      await initializeUploadSystem();
      
      // Initialize cache warmup
      try {
        await cacheManager.warmupCache();
        console.log('🔥 Cache warmup completed');
      } catch (error) {
        console.warn('⚠️  Cache warmup failed:', error.message);
      }
      
      // Initialize email service
      try {
        const { verifyEmailConfig } = require('./config/email');
        await verifyEmailConfig();
      } catch (error) {
        console.warn('⚠️  Email service initialization failed:', error.message);
        console.warn('📧 Email notifications will not be sent');
      }
      
      // Initialize WebSocket server
      let wsServer;
      try {
        const serverToUse = servers.https?.server || servers.http?.server;
        if (serverToUse) {
          wsServer = new WebSocketServer(serverToUse);
          console.log('🔌 WebSocket server initialized');
          
          // Make WebSocket server available globally for broadcasting
          global.wsServer = wsServer;
        }
      } catch (error) {
        console.warn('⚠️  WebSocket server initialization failed:', error.message);
        console.warn('🔌 Real-time updates will not be available');
      }
      
      // Log security configuration status
      logSecurityEvent('SERVER_STARTED', {
        sslEnabled: servers.sslEnabled,
        httpPort: servers.http?.port,
        httpsPort: servers.https?.port,
        environment: process.env.NODE_ENV
      });
      
      // Graceful shutdown handling
      const gracefulShutdown = async (signal) => {
        console.log(`\n🛑 Received ${signal}, shutting down gracefully...`);
        
        const { shutdownServers } = require('./config/https-server');
        await shutdownServers(servers);
        
        console.log('👋 Server shut down complete');
        process.exit(0);
      };
      
      process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
      process.on('SIGINT', () => gracefulShutdown('SIGINT'));
      
    })
    .catch((error) => {
      console.error('❌ Failed to start servers:', error.message);
      logSecurityEvent('SERVER_START_FAILED', {
        error: error.message,
        stack: error.stack
      });
      process.exit(1);
    });
}

module.exports = app;