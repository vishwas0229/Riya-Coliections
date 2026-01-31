/**
 * System Monitoring and Health Check Utilities
 * 
 * This module provides comprehensive system monitoring including:
 * - Health checks for database, external services
 * - Performance metrics collection
 * - System resource monitoring
 * - Alerting and notification system
 * 
 * Requirements: 14.4 (Production monitoring and error handling)
 */

const os = require('os');
const fs = require('fs');
const path = require('path');
const { appLogger, securityLogger } = require('../config/logging');
const { getDatabaseConfig } = require('../config/environment');

/**
 * System health status
 */
let systemHealth = {
  status: 'unknown',
  lastCheck: null,
  components: {},
  metrics: {}
};

/**
 * Check database connectivity
 */
const checkDatabase = async () => {
  try {
    const mysql = require('mysql2/promise');
    const dbConfig = getDatabaseConfig();
    
    const connection = await mysql.createConnection({
      host: dbConfig.host,
      port: dbConfig.port,
      user: dbConfig.user,
      password: dbConfig.password,
      database: dbConfig.database,
      timeout: 5000
    });
    
    // Simple query to test connection
    await connection.execute('SELECT 1 as test');
    await connection.end();
    
    return {
      status: 'healthy',
      responseTime: Date.now(),
      message: 'Database connection successful'
    };
    
  } catch (error) {
    return {
      status: 'unhealthy',
      responseTime: null,
      message: error.message,
      error: error.code
    };
  }
};

/**
 * Check file system health
 */
const checkFileSystem = async () => {
  try {
    const checks = [];
    
    // Check uploads directory
    const uploadsDir = path.join(process.cwd(), 'uploads');
    if (fs.existsSync(uploadsDir)) {
      const stats = fs.statSync(uploadsDir);
      checks.push({
        path: 'uploads',
        writable: true,
        size: stats.size
      });
    } else {
      checks.push({
        path: 'uploads',
        writable: false,
        error: 'Directory does not exist'
      });
    }
    
    // Check logs directory
    const logsDir = path.join(process.cwd(), 'logs');
    if (fs.existsSync(logsDir)) {
      const stats = fs.statSync(logsDir);
      checks.push({
        path: 'logs',
        writable: true,
        size: stats.size
      });
    } else {
      checks.push({
        path: 'logs',
        writable: false,
        error: 'Directory does not exist'
      });
    }
    
    // Check disk space
    const diskUsage = await getDiskUsage();
    
    const allHealthy = checks.every(check => check.writable);
    
    return {
      status: allHealthy ? 'healthy' : 'unhealthy',
      checks,
      diskUsage,
      message: allHealthy ? 'File system accessible' : 'File system issues detected'
    };
    
  } catch (error) {
    return {
      status: 'unhealthy',
      message: error.message,
      error: error.code
    };
  }
};

/**
 * Get disk usage information
 */
const getDiskUsage = async () => {
  try {
    const stats = fs.statSync(process.cwd());
    
    // On Unix-like systems, try to get disk usage
    if (process.platform !== 'win32') {
      const { execSync } = require('child_process');
      try {
        const output = execSync(`df -h ${process.cwd()}`, { encoding: 'utf8' });
        const lines = output.split('\n');
        if (lines.length > 1) {
          const parts = lines[1].split(/\s+/);
          return {
            total: parts[1],
            used: parts[2],
            available: parts[3],
            percentage: parts[4]
          };
        }
      } catch (dfError) {
        // Fall back to basic info
      }
    }
    
    return {
      available: 'unknown',
      message: 'Disk usage information not available'
    };
    
  } catch (error) {
    return {
      error: error.message
    };
  }
};

/**
 * Check external services
 */
const checkExternalServices = async () => {
  const services = [];
  
  // Check email service
  try {
    const { verifyEmailConfig } = require('../config/email');
    await verifyEmailConfig();
    services.push({
      name: 'email',
      status: 'healthy',
      message: 'Email service accessible'
    });
  } catch (error) {
    services.push({
      name: 'email',
      status: 'unhealthy',
      message: error.message
    });
  }
  
  // Check Razorpay (basic configuration check)
  try {
    const razorpayKeyId = process.env.RAZORPAY_KEY_ID;
    const razorpayKeySecret = process.env.RAZORPAY_KEY_SECRET;
    
    if (razorpayKeyId && razorpayKeySecret) {
      services.push({
        name: 'razorpay',
        status: 'configured',
        message: 'Razorpay credentials configured'
      });
    } else {
      services.push({
        name: 'razorpay',
        status: 'not_configured',
        message: 'Razorpay credentials missing'
      });
    }
  } catch (error) {
    services.push({
      name: 'razorpay',
      status: 'error',
      message: error.message
    });
  }
  
  const allHealthy = services.every(service => 
    service.status === 'healthy' || service.status === 'configured'
  );
  
  return {
    status: allHealthy ? 'healthy' : 'degraded',
    services,
    message: allHealthy ? 'All external services accessible' : 'Some external services have issues'
  };
};

/**
 * Get system metrics
 */
const getSystemMetrics = () => {
  const memUsage = process.memoryUsage();
  const cpuUsage = process.cpuUsage();
  
  return {
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
    memory: {
      rss: memUsage.rss,
      heapTotal: memUsage.heapTotal,
      heapUsed: memUsage.heapUsed,
      external: memUsage.external,
      arrayBuffers: memUsage.arrayBuffers
    },
    cpu: {
      user: cpuUsage.user,
      system: cpuUsage.system
    },
    system: {
      platform: os.platform(),
      arch: os.arch(),
      nodeVersion: process.version,
      totalMemory: os.totalmem(),
      freeMemory: os.freemem(),
      loadAverage: os.loadavg(),
      cpuCount: os.cpus().length
    },
    process: {
      pid: process.pid,
      version: process.version,
      versions: process.versions
    }
  };
};

/**
 * Perform comprehensive health check
 */
const performHealthCheck = async () => {
  const startTime = Date.now();
  
  appLogger.debug('Starting health check');
  
  try {
    const [database, fileSystem, externalServices] = await Promise.all([
      checkDatabase(),
      checkFileSystem(),
      checkExternalServices()
    ]);
    
    const metrics = getSystemMetrics();
    const duration = Date.now() - startTime;
    
    // Determine overall health status
    const components = { database, fileSystem, externalServices };
    const statuses = Object.values(components).map(c => c.status);
    
    let overallStatus = 'healthy';
    if (statuses.includes('unhealthy')) {
      overallStatus = 'unhealthy';
    } else if (statuses.includes('degraded')) {
      overallStatus = 'degraded';
    }
    
    systemHealth = {
      status: overallStatus,
      lastCheck: new Date().toISOString(),
      duration,
      components,
      metrics
    };
    
    // Log health check results
    if (overallStatus === 'healthy') {
      appLogger.debug('Health check completed - system healthy', { duration });
    } else {
      appLogger.warn('Health check completed - issues detected', {
        status: overallStatus,
        duration,
        issues: statuses.filter(s => s !== 'healthy')
      });
    }
    
    return systemHealth;
    
  } catch (error) {
    appLogger.error('Health check failed', { error: error.message });
    
    systemHealth = {
      status: 'unhealthy',
      lastCheck: new Date().toISOString(),
      duration: Date.now() - startTime,
      error: error.message,
      components: {},
      metrics: getSystemMetrics()
    };
    
    return systemHealth;
  }
};

/**
 * Get current system health
 */
const getSystemHealth = () => {
  return systemHealth;
};

/**
 * Monitor system performance
 */
const monitorPerformance = () => {
  const metrics = getSystemMetrics();
  
  // Check for performance issues
  const warnings = [];
  
  // Memory usage warnings
  const memoryUsagePercent = (metrics.memory.heapUsed / metrics.memory.heapTotal) * 100;
  if (memoryUsagePercent > 80) {
    warnings.push({
      type: 'memory',
      message: `High memory usage: ${memoryUsagePercent.toFixed(1)}%`,
      value: memoryUsagePercent
    });
  }
  
  // System memory warnings
  const systemMemoryUsagePercent = ((metrics.system.totalMemory - metrics.system.freeMemory) / metrics.system.totalMemory) * 100;
  if (systemMemoryUsagePercent > 90) {
    warnings.push({
      type: 'system_memory',
      message: `High system memory usage: ${systemMemoryUsagePercent.toFixed(1)}%`,
      value: systemMemoryUsagePercent
    });
  }
  
  // Load average warnings (Unix-like systems)
  if (metrics.system.loadAverage && metrics.system.loadAverage[0] > metrics.system.cpuCount * 2) {
    warnings.push({
      type: 'load_average',
      message: `High load average: ${metrics.system.loadAverage[0].toFixed(2)}`,
      value: metrics.system.loadAverage[0]
    });
  }
  
  // Log warnings
  warnings.forEach(warning => {
    appLogger.warn(`Performance warning: ${warning.message}`, {
      type: warning.type,
      value: warning.value,
      metrics: metrics
    });
  });
  
  return {
    metrics,
    warnings,
    timestamp: new Date().toISOString()
  };
};

/**
 * Start monitoring system
 */
const startMonitoring = (interval = 60000) => {
  appLogger.info('Starting system monitoring', { interval });
  
  // Initial health check
  performHealthCheck();
  
  // Schedule regular health checks
  const healthCheckInterval = setInterval(async () => {
    try {
      await performHealthCheck();
    } catch (error) {
      appLogger.error('Scheduled health check failed', { error: error.message });
    }
  }, interval);
  
  // Schedule performance monitoring
  const performanceInterval = setInterval(() => {
    try {
      monitorPerformance();
    } catch (error) {
      appLogger.error('Performance monitoring failed', { error: error.message });
    }
  }, interval / 2); // More frequent performance checks
  
  // Cleanup on process exit
  process.on('SIGTERM', () => {
    clearInterval(healthCheckInterval);
    clearInterval(performanceInterval);
  });
  
  process.on('SIGINT', () => {
    clearInterval(healthCheckInterval);
    clearInterval(performanceInterval);
  });
  
  return {
    healthCheckInterval,
    performanceInterval
  };
};

/**
 * Express middleware for health check endpoint
 */
const healthCheckMiddleware = async (req, res) => {
  try {
    const health = await performHealthCheck();
    
    const statusCode = health.status === 'healthy' ? 200 : 
                      health.status === 'degraded' ? 200 : 503;
    
    res.status(statusCode).json({
      status: health.status,
      timestamp: health.lastCheck,
      uptime: process.uptime(),
      version: process.env.npm_package_version || '1.0.0',
      environment: process.env.NODE_ENV || 'development',
      components: health.components,
      ...(req.query.metrics === 'true' && { metrics: health.metrics })
    });
    
  } catch (error) {
    res.status(503).json({
      status: 'unhealthy',
      error: error.message,
      timestamp: new Date().toISOString()
    });
  }
};

/**
 * Generate monitoring report
 */
const generateMonitoringReport = () => {
  const health = getSystemHealth();
  const performance = monitorPerformance();
  
  return {
    timestamp: new Date().toISOString(),
    health,
    performance,
    summary: {
      overallStatus: health.status,
      uptime: process.uptime(),
      lastHealthCheck: health.lastCheck,
      performanceWarnings: performance.warnings.length,
      memoryUsage: performance.metrics.memory.heapUsed,
      systemLoad: performance.metrics.system.loadAverage?.[0] || 'N/A'
    }
  };
};

module.exports = {
  performHealthCheck,
  getSystemHealth,
  monitorPerformance,
  startMonitoring,
  healthCheckMiddleware,
  generateMonitoringReport,
  checkDatabase,
  checkFileSystem,
  checkExternalServices,
  getSystemMetrics
};