/**
 * Comprehensive Logging Configuration for Riya Collections
 * 
 * This module provides production-ready logging including:
 * - Structured logging with different levels
 * - File-based logging with rotation
 * - Security event logging
 * - Performance monitoring
 * - Error tracking and alerting
 * 
 * Requirements: 14.4 (Production error handling and logging)
 */

const fs = require('fs');
const path = require('path');

/**
 * Logging levels configuration
 */
const LOG_LEVELS = {
  ERROR: 0,
  WARN: 1,
  INFO: 2,
  DEBUG: 3,
  TRACE: 4
};

/**
 * Get current log level from environment
 */
const getCurrentLogLevel = () => {
  const level = (process.env.LOG_LEVEL || 'info').toUpperCase();
  return LOG_LEVELS[level] !== undefined ? LOG_LEVELS[level] : LOG_LEVELS.INFO;
};

/**
 * Ensure log directory exists
 */
const ensureLogDirectory = () => {
  const logDir = path.join(process.cwd(), 'logs');
  if (!fs.existsSync(logDir)) {
    fs.mkdirSync(logDir, { recursive: true });
  }
  return logDir;
};

/**
 * Format log entry with structured data
 */
const formatLogEntry = (level, message, metadata = {}) => {
  const timestamp = new Date().toISOString();
  const logEntry = {
    timestamp,
    level: level.toUpperCase(),
    message,
    environment: process.env.NODE_ENV || 'development',
    pid: process.pid,
    ...metadata
  };
  
  return JSON.stringify(logEntry);
};

/**
 * Write log entry to file
 */
const writeToFile = (filename, content) => {
  const logDir = ensureLogDirectory();
  const filePath = path.join(logDir, filename);
  
  try {
    fs.appendFileSync(filePath, content + '\n', 'utf8');
  } catch (error) {
    console.error('Failed to write to log file:', error.message);
  }
};

/**
 * Get log filename with date rotation
 */
const getLogFilename = (type = 'app') => {
  const date = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
  return `${type}-${date}.log`;
};

/**
 * Main logger class
 */
class Logger {
  constructor(category = 'app') {
    this.category = category;
    this.currentLevel = getCurrentLogLevel();
  }
  
  /**
   * Check if log level should be written
   */
  shouldLog(level) {
    return LOG_LEVELS[level.toUpperCase()] <= this.currentLevel;
  }
  
  /**
   * Core logging method
   */
  log(level, message, metadata = {}) {
    if (!this.shouldLog(level)) {
      return;
    }
    
    const enhancedMetadata = {
      category: this.category,
      ...metadata
    };
    
    const logEntry = formatLogEntry(level, message, enhancedMetadata);
    
    // Console output (with colors in development)
    if (process.env.NODE_ENV === 'development') {
      const colors = {
        ERROR: '\x1b[31m', // Red
        WARN: '\x1b[33m',  // Yellow
        INFO: '\x1b[36m',  // Cyan
        DEBUG: '\x1b[35m', // Magenta
        TRACE: '\x1b[37m'  // White
      };
      const reset = '\x1b[0m';
      const color = colors[level.toUpperCase()] || '';
      console.log(`${color}[${level.toUpperCase()}]${reset} ${message}`, enhancedMetadata);
    } else {
      console.log(logEntry);
    }
    
    // File output
    const filename = getLogFilename(this.category);
    writeToFile(filename, logEntry);
    
    // Special handling for errors and security events
    if (level.toUpperCase() === 'ERROR') {
      writeToFile(getLogFilename('error'), logEntry);
    }
    
    if (enhancedMetadata.type === 'security' || this.category === 'security') {
      writeToFile(getLogFilename('security'), logEntry);
    }
  }
  
  /**
   * Convenience methods for different log levels
   */
  error(message, metadata = {}) {
    this.log('ERROR', message, metadata);
  }
  
  warn(message, metadata = {}) {
    this.log('WARN', message, metadata);
  }
  
  info(message, metadata = {}) {
    this.log('INFO', message, metadata);
  }
  
  debug(message, metadata = {}) {
    this.log('DEBUG', message, metadata);
  }
  
  trace(message, metadata = {}) {
    this.log('TRACE', message, metadata);
  }
  
  /**
   * Security-specific logging
   */
  security(event, details = {}) {
    this.log('WARN', `Security Event: ${event}`, {
      type: 'security',
      event,
      ...details
    });
  }
  
  /**
   * Performance logging
   */
  performance(operation, duration, metadata = {}) {
    this.log('INFO', `Performance: ${operation} took ${duration}ms`, {
      type: 'performance',
      operation,
      duration,
      ...metadata
    });
  }
  
  /**
   * Database operation logging
   */
  database(operation, query, duration, metadata = {}) {
    this.log('DEBUG', `Database: ${operation}`, {
      type: 'database',
      operation,
      query: query.substring(0, 200), // Truncate long queries
      duration,
      ...metadata
    });
  }
  
  /**
   * API request logging
   */
  request(method, path, statusCode, duration, metadata = {}) {
    const level = statusCode >= 500 ? 'ERROR' : statusCode >= 400 ? 'WARN' : 'INFO';
    this.log(level, `${method} ${path} ${statusCode} ${duration}ms`, {
      type: 'request',
      method,
      path,
      statusCode,
      duration,
      ...metadata
    });
  }
}

/**
 * Create logger instances for different categories
 */
const createLogger = (category) => new Logger(category);

/**
 * Default logger instances
 */
const appLogger = createLogger('app');
const securityLogger = createLogger('security');
const databaseLogger = createLogger('database');
const apiLogger = createLogger('api');

/**
 * Express middleware for request logging
 */
const requestLoggingMiddleware = (req, res, next) => {
  const startTime = Date.now();
  
  // Capture original end method
  const originalEnd = res.end;
  
  res.end = function(chunk, encoding) {
    const duration = Date.now() - startTime;
    
    // Log the request
    apiLogger.request(req.method, req.path, res.statusCode, duration, {
      ip: req.ip,
      userAgent: req.get('User-Agent'),
      contentLength: res.get('Content-Length'),
      userId: req.user?.id,
      adminId: req.admin?.id
    });
    
    // Call original end method
    originalEnd.call(this, chunk, encoding);
  };
  
  next();
};

/**
 * Error logging middleware
 */
const errorLoggingMiddleware = (err, req, res, next) => {
  appLogger.error('Unhandled error', {
    error: err.message,
    stack: err.stack,
    path: req.path,
    method: req.method,
    ip: req.ip,
    userAgent: req.get('User-Agent'),
    body: req.body,
    params: req.params,
    query: req.query
  });
  
  next(err);
};

/**
 * Log rotation utility
 */
const rotateLogFiles = () => {
  const logDir = ensureLogDirectory();
  const maxAge = 30 * 24 * 60 * 60 * 1000; // 30 days
  const now = Date.now();
  
  try {
    const files = fs.readdirSync(logDir);
    
    files.forEach(file => {
      const filePath = path.join(logDir, file);
      const stats = fs.statSync(filePath);
      
      if (now - stats.mtime.getTime() > maxAge) {
        fs.unlinkSync(filePath);
        appLogger.info(`Rotated old log file: ${file}`);
      }
    });
  } catch (error) {
    appLogger.error('Failed to rotate log files', { error: error.message });
  }
};

/**
 * Initialize logging system
 */
const initializeLogging = () => {
  // Ensure log directory exists
  ensureLogDirectory();
  
  // Set up log rotation (run daily)
  setInterval(rotateLogFiles, 24 * 60 * 60 * 1000);
  
  // Handle uncaught exceptions
  process.on('uncaughtException', (error) => {
    appLogger.error('Uncaught Exception', {
      error: error.message,
      stack: error.stack
    });
    
    // Give time for log to be written before exiting
    setTimeout(() => {
      process.exit(1);
    }, 1000);
  });
  
  // Handle unhandled promise rejections
  process.on('unhandledRejection', (reason, promise) => {
    appLogger.error('Unhandled Promise Rejection', {
      reason: reason?.message || reason,
      stack: reason?.stack,
      promise: promise.toString()
    });
  });
  
  appLogger.info('Logging system initialized', {
    logLevel: Object.keys(LOG_LEVELS)[getCurrentLogLevel()],
    logDirectory: ensureLogDirectory()
  });
};

/**
 * Generate log analysis report
 */
const generateLogReport = (days = 7) => {
  const logDir = ensureLogDirectory();
  const report = {
    period: `Last ${days} days`,
    generated: new Date().toISOString(),
    summary: {
      totalEntries: 0,
      errorCount: 0,
      warningCount: 0,
      securityEvents: 0,
      performanceIssues: 0
    },
    topErrors: [],
    securityEvents: [],
    performanceMetrics: {
      slowRequests: [],
      averageResponseTime: 0
    }
  };
  
  try {
    const files = fs.readdirSync(logDir);
    const cutoffDate = new Date(Date.now() - days * 24 * 60 * 60 * 1000);
    
    files.forEach(file => {
      if (!file.endsWith('.log')) return;
      
      const filePath = path.join(logDir, file);
      const content = fs.readFileSync(filePath, 'utf8');
      const lines = content.split('\n').filter(line => line.trim());
      
      lines.forEach(line => {
        try {
          const entry = JSON.parse(line);
          const entryDate = new Date(entry.timestamp);
          
          if (entryDate >= cutoffDate) {
            report.summary.totalEntries++;
            
            if (entry.level === 'ERROR') {
              report.summary.errorCount++;
            } else if (entry.level === 'WARN') {
              report.summary.warningCount++;
            }
            
            if (entry.type === 'security') {
              report.summary.securityEvents++;
              report.securityEvents.push({
                timestamp: entry.timestamp,
                event: entry.event,
                details: entry
              });
            }
            
            if (entry.type === 'request' && entry.duration > 5000) {
              report.summary.performanceIssues++;
              report.performanceMetrics.slowRequests.push({
                timestamp: entry.timestamp,
                path: entry.path,
                duration: entry.duration
              });
            }
          }
        } catch (parseError) {
          // Skip invalid JSON lines
        }
      });
    });
    
    // Limit arrays to top 10 items
    report.securityEvents = report.securityEvents.slice(0, 10);
    report.performanceMetrics.slowRequests = report.performanceMetrics.slowRequests.slice(0, 10);
    
  } catch (error) {
    appLogger.error('Failed to generate log report', { error: error.message });
  }
  
  return report;
};

module.exports = {
  Logger,
  createLogger,
  appLogger,
  securityLogger,
  databaseLogger,
  apiLogger,
  requestLoggingMiddleware,
  errorLoggingMiddleware,
  initializeLogging,
  rotateLogFiles,
  generateLogReport,
  LOG_LEVELS,
  getCurrentLogLevel
};