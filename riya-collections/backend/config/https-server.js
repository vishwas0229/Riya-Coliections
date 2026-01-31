/**
 * HTTPS Server Configuration for Riya Collections
 * 
 * This module provides HTTPS server setup with SSL certificate management
 * and automatic HTTP to HTTPS redirection for production environments.
 * 
 * Requirements: 9.4 (HTTPS Communication)
 */

const https = require('https');
const http = require('http');
const { loadSSLCertificates, getSSLConfig, logSecurityEvent } = require('./security');

/**
 * Create HTTPS server with SSL certificates
 * @param {Express} app - Express application instance
 * @returns {Object} - Server instances and configuration
 */
const createHTTPSServer = (app) => {
  const sslConfig = getSSLConfig();
  const credentials = loadSSLCertificates();
  
  if (!sslConfig.enabled || !credentials) {
    console.log('🔓 HTTPS disabled - running HTTP server only');
    return {
      httpServer: null,
      httpsServer: null,
      sslEnabled: false,
      config: sslConfig
    };
  }

  try {
    // Create HTTPS server
    const httpsServer = https.createServer(credentials, app);
    
    // Create HTTP server for redirection
    const httpApp = require('express')();
    
    // Redirect all HTTP traffic to HTTPS
    httpApp.use((req, res) => {
      const httpsUrl = `https://${req.headers.host}${req.url}`;
      console.log(`🔀 Redirecting HTTP to HTTPS: ${req.url} -> ${httpsUrl}`);
      res.redirect(301, httpsUrl);
    });
    
    const httpServer = http.createServer(httpApp);
    
    logSecurityEvent('HTTPS_SERVER_CREATED', {
      sslPort: sslConfig.port,
      httpPort: process.env.PORT || 5000,
      keyPath: sslConfig.keyPath,
      certPath: sslConfig.certPath
    });
    
    console.log('🔒 HTTPS server created successfully');
    
    return {
      httpServer,
      httpsServer,
      sslEnabled: true,
      config: sslConfig
    };
    
  } catch (error) {
    console.error('❌ Failed to create HTTPS server:', error.message);
    logSecurityEvent('HTTPS_SERVER_ERROR', {
      error: error.message,
      stack: error.stack
    });
    
    return {
      httpServer: null,
      httpsServer: null,
      sslEnabled: false,
      config: sslConfig,
      error: error.message
    };
  }
};

/**
 * Start servers (HTTP and/or HTTPS)
 * @param {Express} app - Express application instance
 * @returns {Promise} - Promise that resolves when servers are started
 */
const startServers = async (app) => {
  const { httpServer, httpsServer, sslEnabled, config, error } = createHTTPSServer(app);
  
  const httpPort = parseInt(process.env.PORT) || 5000;
  const httpsPort = config.port || 443;
  
  return new Promise((resolve, reject) => {
    const results = {
      http: null,
      https: null,
      sslEnabled,
      error
    };
    
    if (sslEnabled && httpsServer && httpServer) {
      // Start HTTPS server
      httpsServer.listen(httpsPort, (err) => {
        if (err) {
          console.error(`❌ Failed to start HTTPS server on port ${httpsPort}:`, err.message);
          results.error = err.message;
          return reject(err);
        }
        
        console.log(`🔒 HTTPS server running on port ${httpsPort}`);
        results.https = { port: httpsPort, server: httpsServer };
        
        // Start HTTP redirect server
        httpServer.listen(httpPort, (err) => {
          if (err) {
            console.error(`❌ Failed to start HTTP redirect server on port ${httpPort}:`, err.message);
            results.error = err.message;
            return reject(err);
          }
          
          console.log(`🔀 HTTP redirect server running on port ${httpPort}`);
          results.http = { port: httpPort, server: httpServer };
          
          logSecurityEvent('SERVERS_STARTED', {
            httpsPort,
            httpPort,
            sslEnabled: true
          });
          
          resolve(results);
        });
      });
      
    } else {
      // Start HTTP server only
      const httpServerInstance = http.createServer(app);
      
      httpServerInstance.listen(httpPort, (err) => {
        if (err) {
          console.error(`❌ Failed to start HTTP server on port ${httpPort}:`, err.message);
          results.error = err.message;
          return reject(err);
        }
        
        console.log(`🔓 HTTP server running on port ${httpPort}`);
        results.http = { port: httpPort, server: httpServerInstance };
        
        logSecurityEvent('SERVERS_STARTED', {
          httpPort,
          sslEnabled: false,
          reason: error || 'SSL not configured'
        });
        
        resolve(results);
      });
    }
  });
};

/**
 * Graceful server shutdown
 * @param {Object} servers - Server instances from startServers()
 * @returns {Promise} - Promise that resolves when servers are shut down
 */
const shutdownServers = async (servers) => {
  const shutdownPromises = [];
  
  if (servers.http && servers.http.server) {
    shutdownPromises.push(new Promise((resolve) => {
      servers.http.server.close(() => {
        console.log('🔓 HTTP server shut down');
        resolve();
      });
    }));
  }
  
  if (servers.https && servers.https.server) {
    shutdownPromises.push(new Promise((resolve) => {
      servers.https.server.close(() => {
        console.log('🔒 HTTPS server shut down');
        resolve();
      });
    }));
  }
  
  await Promise.all(shutdownPromises);
  logSecurityEvent('SERVERS_SHUTDOWN');
};

/**
 * SSL certificate validation and monitoring
 */
const validateSSLCertificate = () => {
  const sslConfig = getSSLConfig();
  
  if (!sslConfig.enabled) {
    return { valid: false, reason: 'SSL not enabled' };
  }
  
  try {
    const credentials = loadSSLCertificates();
    
    if (!credentials) {
      return { valid: false, reason: 'Failed to load certificates' };
    }
    
    // Basic certificate validation
    const cert = credentials.cert;
    const key = credentials.key;
    
    if (!cert || !key) {
      return { valid: false, reason: 'Missing certificate or key' };
    }
    
    // Check certificate format
    if (!cert.includes('-----BEGIN CERTIFICATE-----') || !cert.includes('-----END CERTIFICATE-----')) {
      return { valid: false, reason: 'Invalid certificate format' };
    }
    
    if (!key.includes('-----BEGIN') || !key.includes('-----END')) {
      return { valid: false, reason: 'Invalid private key format' };
    }
    
    console.log('✅ SSL certificate validation passed');
    logSecurityEvent('SSL_CERTIFICATE_VALIDATED');
    
    return { valid: true };
    
  } catch (error) {
    console.error('❌ SSL certificate validation failed:', error.message);
    logSecurityEvent('SSL_CERTIFICATE_VALIDATION_FAILED', {
      error: error.message
    });
    
    return { valid: false, reason: error.message };
  }
};

/**
 * Generate self-signed certificate for development
 * Note: This is for development only - use proper certificates in production
 */
const generateSelfSignedCert = () => {
  console.warn('⚠️  Self-signed certificate generation not implemented');
  console.warn('⚠️  For development, consider using mkcert or similar tools');
  console.warn('⚠️  For production, use certificates from a trusted CA');
  
  return {
    generated: false,
    reason: 'Self-signed certificate generation not implemented'
  };
};

/**
 * SSL certificate expiry monitoring
 */
const checkCertificateExpiry = () => {
  const sslConfig = getSSLConfig();
  
  if (!sslConfig.enabled) {
    return { checked: false, reason: 'SSL not enabled' };
  }
  
  try {
    const credentials = loadSSLCertificates();
    
    if (!credentials) {
      return { checked: false, reason: 'Failed to load certificates' };
    }
    
    // Note: For full certificate expiry checking, you would need to parse
    // the certificate using a library like node-forge or similar
    console.log('ℹ️  Certificate expiry checking requires additional parsing');
    console.log('ℹ️  Consider implementing with node-forge or openssl commands');
    
    return { checked: true, warning: 'Basic check only - implement full parsing for expiry dates' };
    
  } catch (error) {
    console.error('❌ Certificate expiry check failed:', error.message);
    return { checked: false, reason: error.message };
  }
};

module.exports = {
  createHTTPSServer,
  startServers,
  shutdownServers,
  validateSSLCertificate,
  generateSelfSignedCert,
  checkCertificateExpiry
};