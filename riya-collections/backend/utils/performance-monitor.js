/**
 * Advanced Performance Monitoring System
 * 
 * This module provides comprehensive performance monitoring including:
 * - Request/response time tracking
 * - Database query performance
 * - Memory and CPU usage monitoring
 * - API endpoint performance analysis
 * - Real-time performance alerts
 * 
 * Requirements: 10.3, 10.4, 11.4
 */

const { appLogger } = require('../config/logging');
const { cacheManager } = require('./cache-manager');
const { queryMonitor } = require('../middleware/query-optimization');

class PerformanceMonitor {
  constructor() {
    this.metrics = {
      requests: new Map(),
      endpoints: new Map(),
      errors: new Map(),
      systemMetrics: {
        memory: [],
        cpu: [],
        responseTime: []
      }
    };
    
    this.thresholds = {
      slowRequest: 2000, // 2 seconds
      highMemoryUsage: 80, // 80%
      highCpuUsage: 80, // 80%
      errorRate: 5 // 5%
    };

    this.alerts = [];
    this.startSystemMonitoring();
  }

  /**
   * Track request performance
   */
  trackRequest(req, res, duration) {
    const endpoint = `${req.method} ${req.route?.path || req.path}`;
    const timestamp = new Date();
    
    // Track endpoint performance
    if (!this.metrics.endpoints.has(endpoint)) {
      this.metrics.endpoints.set(endpoint, {
        count: 0,
        totalDuration: 0,
        avgDuration: 0,
        minDuration: Infinity,
        maxDuration: 0,
        errorCount: 0,
        lastAccessed: null,
        statusCodes: new Map()
      });
    }

    const endpointStats = this.metrics.endpoints.get(endpoint);
    endpointStats.count++;
    endpointStats.totalDuration += duration;
    endpointStats.avgDuration = endpointStats.totalDuration / endpointStats.count;
    endpointStats.minDuration = Math.min(endpointStats.minDuration, duration);
    endpointStats.maxDuration = Math.max(endpointStats.maxDuration, duration);
    endpointStats.lastAccessed = timestamp;

    // Track status codes
    const statusCode = res.statusCode.toString();
    endpointStats.statusCodes.set(statusCode, 
      (endpointStats.statusCodes.get(statusCode) || 0) + 1
    );

    // Track errors
    if (res.statusCode >= 400) {
      endpointStats.errorCount++;
      
      const errorKey = `${res.statusCode}:${endpoint}`;
      this.metrics.errors.set(errorKey, 
        (this.metrics.errors.get(errorKey) || 0) + 1
      );
    }

    // Track individual request
    this.metrics.requests.set(Date.now(), {
      endpoint,
      method: req.method,
      path: req.path,
      statusCode: res.statusCode,
      duration,
      timestamp,
      userAgent: req.get('User-Agent'),
      ip: req.ip
    });

    // Keep only last 1000 requests
    if (this.metrics.requests.size > 1000) {
      const oldestKey = Math.min(...this.metrics.requests.keys());
      this.metrics.requests.delete(oldestKey);
    }

    // Check for performance issues
    this.checkPerformanceThresholds(endpoint, duration, res.statusCode);

    // Log slow requests
    if (duration > this.thresholds.slowRequest) {
      appLogger.warn('Slow request detected', {
        endpoint,
        duration,
        statusCode: res.statusCode,
        method: req.method,
        path: req.path
      });
    }
  }

  /**
   * Check performance thresholds and generate alerts
   */
  checkPerformanceThresholds(endpoint, duration, statusCode) {
    const alerts = [];

    // Check slow request threshold
    if (duration > this.thresholds.slowRequest) {
      alerts.push({
        type: 'SLOW_REQUEST',
        severity: 'WARNING',
        message: `Slow request detected: ${endpoint} took ${duration}ms`,
        data: { endpoint, duration, statusCode },
        timestamp: new Date()
      });
    }

    // Check error rate
    const endpointStats = this.metrics.endpoints.get(endpoint);
    if (endpointStats && endpointStats.count > 10) {
      const errorRate = (endpointStats.errorCount / endpointStats.count) * 100;
      if (errorRate > this.thresholds.errorRate) {
        alerts.push({
          type: 'HIGH_ERROR_RATE',
          severity: 'ERROR',
          message: `High error rate detected: ${endpoint} has ${errorRate.toFixed(2)}% error rate`,
          data: { endpoint, errorRate, errorCount: endpointStats.errorCount, totalRequests: endpointStats.count },
          timestamp: new Date()
        });
      }
    }

    // Store alerts
    this.alerts.push(...alerts);
    
    // Keep only last 100 alerts
    if (this.alerts.length > 100) {
      this.alerts = this.alerts.slice(-100);
    }

    // Log critical alerts
    alerts.forEach(alert => {
      if (alert.severity === 'ERROR') {
        appLogger.error('Performance alert', alert);
      } else {
        appLogger.warn('Performance alert', alert);
      }
    });
  }

  /**
   * Start system resource monitoring
   */
  startSystemMonitoring() {
    setInterval(() => {
      this.collectSystemMetrics();
    }, 30000); // Every 30 seconds

    // Initial collection
    this.collectSystemMetrics();
  }

  /**
   * Collect system metrics
   */
  collectSystemMetrics() {
    const memUsage = process.memoryUsage();
    const cpuUsage = process.cpuUsage();
    
    // Calculate memory usage percentage
    const totalMemory = require('os').totalmem();
    const freeMemory = require('os').freemem();
    const usedMemory = totalMemory - freeMemory;
    const memoryUsagePercent = (usedMemory / totalMemory) * 100;

    // Calculate heap usage percentage
    const heapUsagePercent = (memUsage.heapUsed / memUsage.heapTotal) * 100;

    const timestamp = new Date();
    
    // Store metrics
    this.metrics.systemMetrics.memory.push({
      timestamp,
      heapUsed: memUsage.heapUsed,
      heapTotal: memUsage.heapTotal,
      heapUsagePercent,
      rss: memUsage.rss,
      external: memUsage.external,
      systemMemoryUsagePercent: memoryUsagePercent
    });

    this.metrics.systemMetrics.cpu.push({
      timestamp,
      user: cpuUsage.user,
      system: cpuUsage.system
    });

    // Keep only last 100 entries (about 50 minutes of data)
    if (this.metrics.systemMetrics.memory.length > 100) {
      this.metrics.systemMetrics.memory = this.metrics.systemMetrics.memory.slice(-100);
    }
    if (this.metrics.systemMetrics.cpu.length > 100) {
      this.metrics.systemMetrics.cpu = this.metrics.systemMetrics.cpu.slice(-100);
    }

    // Check memory threshold
    if (heapUsagePercent > this.thresholds.highMemoryUsage) {
      const alert = {
        type: 'HIGH_MEMORY_USAGE',
        severity: 'WARNING',
        message: `High memory usage detected: ${heapUsagePercent.toFixed(2)}%`,
        data: { heapUsagePercent, heapUsed: memUsage.heapUsed, heapTotal: memUsage.heapTotal },
        timestamp
      };
      
      this.alerts.push(alert);
      appLogger.warn('Performance alert', alert);
    }

    // Log system metrics periodically
    appLogger.debug('System metrics collected', {
      memoryUsage: `${heapUsagePercent.toFixed(2)}%`,
      heapUsed: `${(memUsage.heapUsed / 1024 / 1024).toFixed(2)}MB`,
      heapTotal: `${(memUsage.heapTotal / 1024 / 1024).toFixed(2)}MB`,
      systemMemoryUsage: `${memoryUsagePercent.toFixed(2)}%`
    });
  }

  /**
   * Get performance statistics
   */
  getStats() {
    const now = Date.now();
    const oneHourAgo = now - (60 * 60 * 1000);
    
    // Filter recent requests
    const recentRequests = Array.from(this.metrics.requests.entries())
      .filter(([timestamp]) => timestamp > oneHourAgo)
      .map(([, request]) => request);

    // Calculate overall statistics
    const totalRequests = recentRequests.length;
    const errorRequests = recentRequests.filter(req => req.statusCode >= 400).length;
    const errorRate = totalRequests > 0 ? (errorRequests / totalRequests) * 100 : 0;
    
    const durations = recentRequests.map(req => req.duration);
    const avgResponseTime = durations.length > 0 ? 
      durations.reduce((sum, duration) => sum + duration, 0) / durations.length : 0;
    
    const p95ResponseTime = durations.length > 0 ? 
      this.calculatePercentile(durations, 95) : 0;
    
    const p99ResponseTime = durations.length > 0 ? 
      this.calculatePercentile(durations, 99) : 0;

    // Get top endpoints by request count
    const topEndpoints = Array.from(this.metrics.endpoints.entries())
      .sort(([,a], [,b]) => b.count - a.count)
      .slice(0, 10)
      .map(([endpoint, stats]) => ({
        endpoint,
        ...stats,
        errorRate: stats.count > 0 ? (stats.errorCount / stats.count) * 100 : 0
      }));

    // Get slowest endpoints
    const slowestEndpoints = Array.from(this.metrics.endpoints.entries())
      .filter(([, stats]) => stats.count > 5) // Only endpoints with significant traffic
      .sort(([,a], [,b]) => b.avgDuration - a.avgDuration)
      .slice(0, 10)
      .map(([endpoint, stats]) => ({
        endpoint,
        avgDuration: stats.avgDuration,
        maxDuration: stats.maxDuration,
        count: stats.count,
        errorRate: stats.count > 0 ? (stats.errorCount / stats.count) * 100 : 0
      }));

    // Get recent system metrics
    const recentMemory = this.metrics.systemMetrics.memory.slice(-10);
    const recentCpu = this.metrics.systemMetrics.cpu.slice(-10);
    
    const currentMemory = recentMemory.length > 0 ? recentMemory[recentMemory.length - 1] : null;
    const avgMemoryUsage = recentMemory.length > 0 ? 
      recentMemory.reduce((sum, m) => sum + m.heapUsagePercent, 0) / recentMemory.length : 0;

    return {
      overview: {
        totalRequests,
        errorRequests,
        errorRate: parseFloat(errorRate.toFixed(2)),
        avgResponseTime: parseFloat(avgResponseTime.toFixed(2)),
        p95ResponseTime: parseFloat(p95ResponseTime.toFixed(2)),
        p99ResponseTime: parseFloat(p99ResponseTime.toFixed(2))
      },
      endpoints: {
        topByTraffic: topEndpoints,
        slowest: slowestEndpoints
      },
      system: {
        currentMemoryUsage: currentMemory ? parseFloat(currentMemory.heapUsagePercent.toFixed(2)) : 0,
        avgMemoryUsage: parseFloat(avgMemoryUsage.toFixed(2)),
        heapUsed: currentMemory ? currentMemory.heapUsed : 0,
        heapTotal: currentMemory ? currentMemory.heapTotal : 0,
        memoryHistory: recentMemory,
        cpuHistory: recentCpu
      },
      cache: cacheManager.getStats(),
      database: queryMonitor.getStats(),
      alerts: this.alerts.slice(-20), // Last 20 alerts
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Calculate percentile from array of numbers
   */
  calculatePercentile(arr, percentile) {
    const sorted = arr.slice().sort((a, b) => a - b);
    const index = Math.ceil((percentile / 100) * sorted.length) - 1;
    return sorted[index] || 0;
  }

  /**
   * Get performance report
   */
  generateReport() {
    const stats = this.getStats();
    
    return {
      summary: {
        status: this.getOverallHealthStatus(stats),
        totalRequests: stats.overview.totalRequests,
        errorRate: stats.overview.errorRate,
        avgResponseTime: stats.overview.avgResponseTime,
        memoryUsage: stats.system.currentMemoryUsage,
        cacheHitRate: stats.cache.hitRate,
        slowQueries: stats.database.slowQueries.length
      },
      details: stats,
      recommendations: this.generateRecommendations(stats),
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Get overall health status
   */
  getOverallHealthStatus(stats) {
    const issues = [];
    
    if (stats.overview.errorRate > this.thresholds.errorRate) {
      issues.push('HIGH_ERROR_RATE');
    }
    
    if (stats.overview.avgResponseTime > this.thresholds.slowRequest) {
      issues.push('SLOW_RESPONSE_TIME');
    }
    
    if (stats.system.currentMemoryUsage > this.thresholds.highMemoryUsage) {
      issues.push('HIGH_MEMORY_USAGE');
    }
    
    if (stats.database.slowQueries.length > 5) {
      issues.push('SLOW_QUERIES');
    }

    if (issues.length === 0) return 'HEALTHY';
    if (issues.length <= 2) return 'WARNING';
    return 'CRITICAL';
  }

  /**
   * Generate performance recommendations
   */
  generateRecommendations(stats) {
    const recommendations = [];

    // Response time recommendations
    if (stats.overview.avgResponseTime > 1000) {
      recommendations.push({
        type: 'PERFORMANCE',
        priority: 'HIGH',
        message: 'Average response time is high. Consider optimizing slow endpoints.',
        details: `Current average: ${stats.overview.avgResponseTime}ms`
      });
    }

    // Error rate recommendations
    if (stats.overview.errorRate > 3) {
      recommendations.push({
        type: 'RELIABILITY',
        priority: 'HIGH',
        message: 'Error rate is elevated. Review error logs and fix failing endpoints.',
        details: `Current error rate: ${stats.overview.errorRate}%`
      });
    }

    // Memory usage recommendations
    if (stats.system.currentMemoryUsage > 70) {
      recommendations.push({
        type: 'RESOURCE',
        priority: 'MEDIUM',
        message: 'Memory usage is high. Consider optimizing memory-intensive operations.',
        details: `Current usage: ${stats.system.currentMemoryUsage}%`
      });
    }

    // Cache recommendations
    const cacheHitRate = parseFloat(stats.cache.hitRate.replace('%', ''));
    if (cacheHitRate < 70) {
      recommendations.push({
        type: 'CACHING',
        priority: 'MEDIUM',
        message: 'Cache hit rate is low. Review caching strategy and TTL settings.',
        details: `Current hit rate: ${stats.cache.hitRate}`
      });
    }

    // Database recommendations
    if (stats.database.slowQueries.length > 3) {
      recommendations.push({
        type: 'DATABASE',
        priority: 'HIGH',
        message: 'Multiple slow queries detected. Review and optimize database queries.',
        details: `Slow queries: ${stats.database.slowQueries.length}`
      });
    }

    return recommendations;
  }

  /**
   * Reset all metrics
   */
  reset() {
    this.metrics.requests.clear();
    this.metrics.endpoints.clear();
    this.metrics.errors.clear();
    this.metrics.systemMetrics = {
      memory: [],
      cpu: [],
      responseTime: []
    };
    this.alerts = [];
    
    appLogger.info('Performance metrics reset');
  }

  /**
   * Export metrics for external monitoring
   */
  exportMetrics() {
    return {
      metrics: {
        requests: Array.from(this.metrics.requests.entries()),
        endpoints: Array.from(this.metrics.endpoints.entries()),
        errors: Array.from(this.metrics.errors.entries()),
        systemMetrics: this.metrics.systemMetrics
      },
      alerts: this.alerts,
      timestamp: new Date().toISOString()
    };
  }
}

// Create singleton instance
const performanceMonitor = new PerformanceMonitor();

/**
 * Express middleware for performance monitoring
 */
const performanceMiddleware = (req, res, next) => {
  const startTime = Date.now();
  
  // Capture original end method
  const originalEnd = res.end;
  
  res.end = function(chunk, encoding) {
    const duration = Date.now() - startTime;
    
    // Track request performance
    performanceMonitor.trackRequest(req, res, duration);
    
    // Call original end method
    originalEnd.call(this, chunk, encoding);
  };
  
  next();
};

module.exports = {
  performanceMonitor,
  performanceMiddleware,
  PerformanceMonitor
};