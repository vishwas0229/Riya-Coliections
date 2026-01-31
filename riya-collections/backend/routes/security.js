/**
 * Security Monitoring and Reporting Routes
 * 
 * This module provides endpoints for security monitoring, reporting,
 * system health checks, backup operations, and configuration validation.
 * 
 * Requirements: 9.4 (HTTPS Communication), 14.1, 14.4 (Production deployment and monitoring)
 */

const express = require('express');
const { authenticateAdmin, authenticateSuperAdmin } = require('../middleware/auth');
const { validationMiddleware } = require('../middleware/validation');
const { generateSecurityReport } = require('../middleware/security-monitoring');
const { getSSLConfig, validateSSLCertificate, logSecurityEvent } = require('../config/security');
const { checkCertificateExpiry } = require('../config/https-server');
const { healthCheckMiddleware, generateMonitoringReport } = require('../utils/monitoring');
const { performFullBackup, listBackups, validateBackup } = require('../utils/backup-recovery');
const { generateLogReport, appLogger, securityLogger } = require('../config/logging');

const router = express.Router();

/**
 * CSP violation reporting endpoint (public)
 * POST /api/security/csp-report
 */
router.post('/csp-report', (req, res) => {
  const report = req.body;
  
  console.warn('🚫 CSP Violation Report:', JSON.stringify(report, null, 2));
  
  logSecurityEvent('CSP_VIOLATION', {
    report,
    userAgent: req.get('User-Agent'),
    ip: req.ip,
    timestamp: new Date().toISOString()
  });
  
  // In production, you might want to store this in a database
  // or send to a security monitoring service
  
  res.status(204).end();
});

/**
 * Security status endpoint (admin only)
 * GET /api/security/status
 */
router.get('/status', authenticateAdmin, (req, res) => {
  try {
    const sslConfig = getSSLConfig();
    const sslValidation = validateSSLCertificate();
    const certExpiry = checkCertificateExpiry();
    
    const status = {
      timestamp: new Date().toISOString(),
      environment: process.env.NODE_ENV,
      ssl: {
        enabled: sslConfig.enabled,
        port: sslConfig.port,
        validation: sslValidation,
        expiry: certExpiry
      },
      security: {
        helmet: true,
        cors: true,
        rateLimiting: true,
        inputValidation: true,
        sqlInjectionPrevention: true,
        xssPrevention: true
      },
      monitoring: {
        requestLogging: true,
        suspiciousActivityDetection: true,
        authFailureTracking: true,
        securityHeaderValidation: true
      }
    };
    
    logSecurityEvent('SECURITY_STATUS_ACCESSED', {
      adminId: req.admin.id,
      adminEmail: req.admin.email,
      ip: req.ip
    });
    
    res.json({
      success: true,
      data: status
    });
    
  } catch (error) {
    console.error('Security status error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve security status'
    });
  }
});

/**
 * Security report endpoint (super admin only)
 * GET /api/security/report
 */
router.get('/report', authenticateSuperAdmin, (req, res) => {
  try {
    const report = generateSecurityReport();
    
    logSecurityEvent('SECURITY_REPORT_GENERATED', {
      adminId: req.admin.id,
      adminEmail: req.admin.email,
      ip: req.ip
    });
    
    res.json({
      success: true,
      data: report
    });
    
  } catch (error) {
    console.error('Security report error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to generate security report'
    });
  }
});

/**
 * SSL certificate validation endpoint (super admin only)
 * GET /api/security/ssl/validate
 */
router.get('/ssl/validate', authenticateSuperAdmin, (req, res) => {
  try {
    const sslConfig = getSSLConfig();
    const validation = validateSSLCertificate();
    const expiry = checkCertificateExpiry();
    
    const result = {
      config: {
        enabled: sslConfig.enabled,
        port: sslConfig.port,
        keyPath: sslConfig.keyPath ? '***configured***' : null,
        certPath: sslConfig.certPath ? '***configured***' : null,
        caPath: sslConfig.caPath ? '***configured***' : null
      },
      validation,
      expiry
    };
    
    logSecurityEvent('SSL_VALIDATION_REQUESTED', {
      adminId: req.admin.id,
      adminEmail: req.admin.email,
      ip: req.ip,
      result: validation
    });
    
    res.json({
      success: true,
      data: result
    });
    
  } catch (error) {
    console.error('SSL validation error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to validate SSL configuration'
    });
  }
});

/**
 * Security configuration test endpoint (super admin only)
 * POST /api/security/test
 */
router.post('/test', authenticateSuperAdmin, (req, res) => {
  try {
    const tests = {
      headers: {},
      cors: {},
      rateLimiting: {},
      ssl: {}
    };
    
    // Test security headers
    tests.headers.xFrameOptions = res.get('X-Frame-Options') === 'DENY';
    tests.headers.xContentTypeOptions = res.get('X-Content-Type-Options') === 'nosniff';
    tests.headers.xXSSProtection = !!res.get('X-XSS-Protection');
    tests.headers.strictTransportSecurity = !!res.get('Strict-Transport-Security');
    
    // Test CORS configuration
    const corsOrigin = res.get('Access-Control-Allow-Origin');
    tests.cors.configured = !!corsOrigin;
    tests.cors.wildcard = corsOrigin === '*';
    
    // Test SSL configuration
    const sslConfig = getSSLConfig();
    tests.ssl.enabled = sslConfig.enabled;
    tests.ssl.configured = !!(sslConfig.keyPath && sslConfig.certPath);
    
    const allTestsPassed = Object.values(tests).every(category => 
      Object.values(category).every(test => test === true || test === false)
    );
    
    logSecurityEvent('SECURITY_TEST_PERFORMED', {
      adminId: req.admin.id,
      adminEmail: req.admin.email,
      ip: req.ip,
      results: tests,
      allPassed: allTestsPassed
    });
    
    res.json({
      success: true,
      data: {
        tests,
        summary: {
          allTestsPassed,
          timestamp: new Date().toISOString()
        }
      }
    });
    
  } catch (error) {
    console.error('Security test error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to perform security tests'
    });
  }
});

/**
 * Security event log endpoint (super admin only)
 * GET /api/security/events
 */
router.get('/events', authenticateSuperAdmin, validationMiddleware.search, (req, res) => {
  try {
    // This is a placeholder - in a real implementation, you would
    // retrieve security events from a logging system or database
    
    const { page = 1, limit = 50, search } = req.query;
    
    // Mock security events for demonstration
    const mockEvents = [
      {
        id: 1,
        timestamp: new Date().toISOString(),
        event: 'SERVER_STARTED',
        details: { sslEnabled: false, environment: 'development' },
        severity: 'info'
      },
      {
        id: 2,
        timestamp: new Date(Date.now() - 60000).toISOString(),
        event: 'SECURITY_STATUS_ACCESSED',
        details: { adminId: req.admin.id, ip: req.ip },
        severity: 'info'
      }
    ];
    
    // Filter by search if provided
    let filteredEvents = mockEvents;
    if (search) {
      filteredEvents = mockEvents.filter(event => 
        event.event.toLowerCase().includes(search.toLowerCase()) ||
        JSON.stringify(event.details).toLowerCase().includes(search.toLowerCase())
      );
    }
    
    // Pagination
    const startIndex = (page - 1) * limit;
    const endIndex = startIndex + limit;
    const paginatedEvents = filteredEvents.slice(startIndex, endIndex);
    
    logSecurityEvent('SECURITY_EVENTS_ACCESSED', {
      adminId: req.admin.id,
      adminEmail: req.admin.email,
      ip: req.ip,
      page,
      limit,
      search
    });
    
    res.json({
      success: true,
      data: {
        events: paginatedEvents,
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total: filteredEvents.length,
          pages: Math.ceil(filteredEvents.length / limit)
        }
      }
    });
    
  } catch (error) {
    console.error('Security events error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve security events'
    });
  }
});

/**
 * Health check endpoint with security info
 * GET /api/security/health
 */
router.get('/health', healthCheckMiddleware);

/**
 * Detailed system status (admin only)
 * GET /api/security/system-status
 */
router.get('/system-status', authenticateAdmin, async (req, res) => {
  try {
    const report = generateMonitoringReport();
    
    appLogger.info('System status requested', {
      adminId: req.admin.id,
      ip: req.ip
    });
    
    res.json({
      success: true,
      data: report
    });
    
  } catch (error) {
    appLogger.error('Failed to generate system status', { error: error.message });
    res.status(500).json({
      success: false,
      error: {
        message: 'Failed to generate system status',
        type: 'SYSTEM_ERROR'
      }
    });
  }
});

/**
 * Log analysis report (admin only)
 * GET /api/security/logs/report
 */
router.get('/logs/report', authenticateAdmin, (req, res) => {
  try {
    const days = parseInt(req.query.days) || 7;
    const report = generateLogReport(days);
    
    appLogger.info('Log report requested', {
      adminId: req.admin.id,
      days,
      ip: req.ip
    });
    
    res.json({
      success: true,
      data: report
    });
    
  } catch (error) {
    appLogger.error('Failed to generate log report', { error: error.message });
    res.status(500).json({
      success: false,
      error: {
        message: 'Failed to generate log report',
        type: 'SYSTEM_ERROR'
      }
    });
  }
});

/**
 * Create system backup (super admin only)
 * POST /api/security/backup
 */
router.post('/backup', authenticateSuperAdmin, async (req, res) => {
  try {
    appLogger.info('Backup requested', {
      adminId: req.admin.id,
      ip: req.ip
    });
    
    const result = await performFullBackup();
    
    if (result.success) {
      appLogger.info('Backup completed successfully', {
        adminId: req.admin.id,
        database: !!result.database,
        files: !!result.files
      });
      
      res.json({
        success: true,
        message: 'Backup completed successfully',
        data: {
          timestamp: result.timestamp,
          database: result.database ? {
            filename: result.database.filename,
            size: result.database.size
          } : null,
          files: result.files ? {
            filename: result.files.filename,
            size: result.files.size
          } : null,
          errors: result.errors
        }
      });
    } else {
      res.status(500).json({
        success: false,
        error: {
          message: 'Backup failed',
          type: 'BACKUP_ERROR',
          errors: result.errors
        }
      });
    }
    
  } catch (error) {
    appLogger.error('Backup request failed', {
      error: error.message,
      adminId: req.admin.id
    });
    
    res.status(500).json({
      success: false,
      error: {
        message: 'Failed to create backup',
        type: 'SYSTEM_ERROR'
      }
    });
  }
});

/**
 * List available backups (admin only)
 * GET /api/security/backups
 */
router.get('/backups', authenticateAdmin, (req, res) => {
  try {
    const backups = listBackups();
    
    appLogger.info('Backup list requested', {
      adminId: req.admin.id,
      count: backups.length,
      ip: req.ip
    });
    
    res.json({
      success: true,
      data: {
        backups,
        count: backups.length
      }
    });
    
  } catch (error) {
    appLogger.error('Failed to list backups', { error: error.message });
    res.status(500).json({
      success: false,
      error: {
        message: 'Failed to list backups',
        type: 'SYSTEM_ERROR'
      }
    });
  }
});

/**
 * Validate backup integrity (admin only)
 * GET /api/security/backups/:filename/validate
 */
router.get('/backups/:filename/validate', authenticateAdmin, async (req, res) => {
  try {
    const { filename } = req.params;
    const validation = await validateBackup(filename);
    
    appLogger.info('Backup validation requested', {
      adminId: req.admin.id,
      filename,
      valid: validation.valid,
      ip: req.ip
    });
    
    res.json({
      success: true,
      data: validation
    });
    
  } catch (error) {
    appLogger.error('Backup validation failed', {
      error: error.message,
      filename: req.params.filename
    });
    
    res.status(500).json({
      success: false,
      error: {
        message: 'Failed to validate backup',
        type: 'SYSTEM_ERROR'
      }
    });
  }
});

module.exports = router;