/**
 * Performance Monitoring API Routes
 * 
 * This module provides endpoints for:
 * - Performance metrics and statistics
 * - Cache management and monitoring
 * - Database query performance
 * - System health monitoring
 * 
 * Requirements: 10.3, 10.4, 11.4
 */

const express = require('express');
const router = express.Router();
const { authenticateAdmin } = require('../middleware/auth');
const { performanceMonitor } = require('../utils/performance-monitor');
const { cacheManager } = require('../utils/cache-manager');
const { queryMonitor } = require('../middleware/query-optimization');
const { appLogger } = require('../config/logging');

/**
 * GET /api/performance/stats
 * Get comprehensive performance statistics
 * Admin only
 */
router.get('/stats', authenticateAdmin, async (req, res) => {
  try {
    const stats = performanceMonitor.getStats();
    
    res.json({
      success: true,
      message: 'Performance statistics retrieved successfully',
      data: stats
    });
    
  } catch (error) {
    appLogger.error('Error getting performance stats', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve performance statistics',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/report
 * Get detailed performance report with recommendations
 * Admin only
 */
router.get('/report', authenticateAdmin, async (req, res) => {
  try {
    const report = performanceMonitor.generateReport();
    
    res.json({
      success: true,
      message: 'Performance report generated successfully',
      data: report
    });
    
  } catch (error) {
    appLogger.error('Error generating performance report', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to generate performance report',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/cache/stats
 * Get cache performance statistics
 * Admin only
 */
router.get('/cache/stats', authenticateAdmin, async (req, res) => {
  try {
    const cacheStats = cacheManager.getStats();
    
    res.json({
      success: true,
      message: 'Cache statistics retrieved successfully',
      data: cacheStats
    });
    
  } catch (error) {
    appLogger.error('Error getting cache stats', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve cache statistics',
      error: error.message
    });
  }
});

/**
 * POST /api/performance/cache/clear
 * Clear cache by pattern or all cache
 * Admin only
 */
router.post('/cache/clear', authenticateAdmin, async (req, res) => {
  try {
    const { pattern, clearAll = false } = req.body;
    
    if (clearAll) {
      await cacheManager.clearPattern('*');
      appLogger.info('All cache cleared by admin', { adminId: req.admin.id });
      
      res.json({
        success: true,
        message: 'All cache cleared successfully'
      });
    } else if (pattern) {
      await cacheManager.clearPattern(pattern);
      appLogger.info('Cache pattern cleared by admin', { 
        pattern, 
        adminId: req.admin.id 
      });
      
      res.json({
        success: true,
        message: `Cache pattern '${pattern}' cleared successfully`
      });
    } else {
      res.status(400).json({
        success: false,
        message: 'Either pattern or clearAll must be specified'
      });
    }
    
  } catch (error) {
    appLogger.error('Error clearing cache', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to clear cache',
      error: error.message
    });
  }
});

/**
 * POST /api/performance/cache/warmup
 * Warm up cache with frequently accessed data
 * Admin only
 */
router.post('/cache/warmup', authenticateAdmin, async (req, res) => {
  try {
    await cacheManager.warmupCache();
    
    appLogger.info('Cache warmup initiated by admin', { adminId: req.admin.id });
    
    res.json({
      success: true,
      message: 'Cache warmup completed successfully'
    });
    
  } catch (error) {
    appLogger.error('Error warming up cache', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to warm up cache',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/database/stats
 * Get database query performance statistics
 * Admin only
 */
router.get('/database/stats', authenticateAdmin, async (req, res) => {
  try {
    const dbStats = queryMonitor.getStats();
    
    res.json({
      success: true,
      message: 'Database performance statistics retrieved successfully',
      data: dbStats
    });
    
  } catch (error) {
    appLogger.error('Error getting database stats', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve database statistics',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/endpoints
 * Get endpoint performance analysis
 * Admin only
 */
router.get('/endpoints', authenticateAdmin, async (req, res) => {
  try {
    const { sortBy = 'avgDuration', limit = 20 } = req.query;
    const stats = performanceMonitor.getStats();
    
    let endpoints = stats.endpoints.topByTraffic;
    
    // Sort endpoints based on criteria
    switch (sortBy) {
      case 'avgDuration':
        endpoints = stats.endpoints.slowest;
        break;
      case 'traffic':
        endpoints = stats.endpoints.topByTraffic;
        break;
      case 'errorRate':
        endpoints = stats.endpoints.topByTraffic
          .sort((a, b) => b.errorRate - a.errorRate);
        break;
      default:
        endpoints = stats.endpoints.topByTraffic;
    }
    
    res.json({
      success: true,
      message: 'Endpoint performance analysis retrieved successfully',
      data: {
        endpoints: endpoints.slice(0, parseInt(limit)),
        sortBy,
        totalEndpoints: stats.endpoints.topByTraffic.length
      }
    });
    
  } catch (error) {
    appLogger.error('Error getting endpoint stats', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve endpoint statistics',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/alerts
 * Get recent performance alerts
 * Admin only
 */
router.get('/alerts', authenticateAdmin, async (req, res) => {
  try {
    const { limit = 50, severity } = req.query;
    const stats = performanceMonitor.getStats();
    
    let alerts = stats.alerts;
    
    // Filter by severity if specified
    if (severity) {
      alerts = alerts.filter(alert => alert.severity === severity.toUpperCase());
    }
    
    // Sort by timestamp (newest first)
    alerts = alerts
      .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
      .slice(0, parseInt(limit));
    
    res.json({
      success: true,
      message: 'Performance alerts retrieved successfully',
      data: {
        alerts,
        totalAlerts: stats.alerts.length,
        criticalAlerts: stats.alerts.filter(a => a.severity === 'ERROR').length,
        warningAlerts: stats.alerts.filter(a => a.severity === 'WARNING').length
      }
    });
    
  } catch (error) {
    appLogger.error('Error getting performance alerts', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve performance alerts',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/system
 * Get system resource usage
 * Admin only
 */
router.get('/system', authenticateAdmin, async (req, res) => {
  try {
    const stats = performanceMonitor.getStats();
    const systemInfo = {
      memory: stats.system,
      uptime: process.uptime(),
      nodeVersion: process.version,
      platform: process.platform,
      arch: process.arch,
      pid: process.pid
    };
    
    res.json({
      success: true,
      message: 'System information retrieved successfully',
      data: systemInfo
    });
    
  } catch (error) {
    appLogger.error('Error getting system info', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve system information',
      error: error.message
    });
  }
});

/**
 * POST /api/performance/reset
 * Reset performance metrics
 * Admin only
 */
router.post('/reset', authenticateAdmin, async (req, res) => {
  try {
    const { resetCache = false, resetMetrics = false, resetDatabase = false } = req.body;
    
    const results = {};
    
    if (resetMetrics) {
      performanceMonitor.reset();
      results.metrics = 'reset';
      appLogger.info('Performance metrics reset by admin', { adminId: req.admin.id });
    }
    
    if (resetCache) {
      cacheManager.resetStats();
      results.cache = 'reset';
      appLogger.info('Cache stats reset by admin', { adminId: req.admin.id });
    }
    
    if (resetDatabase) {
      queryMonitor.reset();
      results.database = 'reset';
      appLogger.info('Database stats reset by admin', { adminId: req.admin.id });
    }
    
    if (Object.keys(results).length === 0) {
      return res.status(400).json({
        success: false,
        message: 'No reset options specified'
      });
    }
    
    res.json({
      success: true,
      message: 'Performance data reset successfully',
      data: results
    });
    
  } catch (error) {
    appLogger.error('Error resetting performance data', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to reset performance data',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/export
 * Export performance metrics for external analysis
 * Admin only
 */
router.get('/export', authenticateAdmin, async (req, res) => {
  try {
    const { format = 'json', timeRange = '1h' } = req.query;
    
    const exportData = {
      performance: performanceMonitor.exportMetrics(),
      cache: cacheManager.getStats(),
      database: queryMonitor.getStats(),
      exportInfo: {
        timestamp: new Date().toISOString(),
        timeRange,
        format,
        exportedBy: req.admin.id
      }
    };
    
    if (format === 'csv') {
      // Convert to CSV format (simplified)
      const csvData = this.convertToCSV(exportData);
      res.setHeader('Content-Type', 'text/csv');
      res.setHeader('Content-Disposition', 'attachment; filename="performance-metrics.csv"');
      res.send(csvData);
    } else {
      res.json({
        success: true,
        message: 'Performance metrics exported successfully',
        data: exportData
      });
    }
    
    appLogger.info('Performance metrics exported', { 
      format, 
      timeRange, 
      adminId: req.admin.id 
    });
    
  } catch (error) {
    appLogger.error('Error exporting performance metrics', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to export performance metrics',
      error: error.message
    });
  }
});

/**
 * GET /api/performance/health
 * Get overall system health status
 * Admin only
 */
router.get('/health', authenticateAdmin, async (req, res) => {
  try {
    const report = performanceMonitor.generateReport();
    
    const healthStatus = {
      status: report.summary.status,
      score: this.calculateHealthScore(report.summary),
      summary: report.summary,
      recommendations: report.recommendations.filter(r => r.priority === 'HIGH'),
      lastUpdated: new Date().toISOString()
    };
    
    res.json({
      success: true,
      message: 'System health status retrieved successfully',
      data: healthStatus
    });
    
  } catch (error) {
    appLogger.error('Error getting health status', { error: error.message });
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve health status',
      error: error.message
    });
  }
});

/**
 * Calculate health score based on metrics
 */
function calculateHealthScore(summary) {
  let score = 100;
  
  // Deduct points for issues
  if (summary.errorRate > 5) score -= 20;
  else if (summary.errorRate > 2) score -= 10;
  
  if (summary.avgResponseTime > 2000) score -= 20;
  else if (summary.avgResponseTime > 1000) score -= 10;
  
  if (summary.memoryUsage > 80) score -= 15;
  else if (summary.memoryUsage > 60) score -= 5;
  
  if (summary.slowQueries > 5) score -= 15;
  else if (summary.slowQueries > 2) score -= 5;
  
  const cacheHitRate = parseFloat(summary.cacheHitRate?.replace('%', '') || '0');
  if (cacheHitRate < 50) score -= 10;
  else if (cacheHitRate < 70) score -= 5;
  
  return Math.max(0, score);
}

module.exports = router;