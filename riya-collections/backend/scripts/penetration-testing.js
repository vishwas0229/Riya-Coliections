#!/usr/bin/env node

/**
 * Advanced Penetration Testing Module
 * for Riya Collections E-commerce Platform
 * 
 * This module performs advanced penetration testing including:
 * - Automated attack vector testing
 * - Business logic vulnerability testing
 * - Advanced authentication bypass attempts
 * - Payment system security testing
 * - Session hijacking attempts
 * - Advanced injection testing
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
 */

const axios = require('axios');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { performance } = require('perf_hooks');

class PenetrationTester {
  constructor(config = {}) {
    this.baseUrl = config.baseUrl || process.env.BASE_URL || 'http://localhost:5000';
    this.testResults = [];
    this.exploits = [];
    this.businessLogicFlaws = [];
    
    // Configure axios for penetration testing
    this.client = axios.create({
      baseURL: this.baseUrl,
      timeout: 30000,
      validateStatus: () => true,
      maxRedirects: 0
    });
    
    // Test credentials and data
    this.testData = {
      users: [
        { email: 'pentest1@example.com', password: 'TestPass123!' },
        { email: 'pentest2@example.com', password: 'TestPass456!' }
      ],
      products: [],
      orders: [],
      sessions: new Map()
    };
  }

  /**
   * Run comprehensive penetration testing
   */
  async runPenetrationTest() {
    console.log('🎯 Starting Advanced Penetration Testing for Riya Collections');
    console.log('=' .repeat(70));
    
    const startTime = performance.now();
    
    try {
      // Setup test environment
      await this.setupTestEnvironment();
      
      // 1. Authentication & Session Testing
      await this.testAuthenticationVulnerabilities();
      
      // 2. Authorization & Access Control Testing
      await this.testAuthorizationVulnerabilities();
      
      // 3. Business Logic Testing
      await this.testBusinessLogicVulnerabilities();
      
      // 4. Payment System Testing
      await this.testPaymentSystemVulnerabilities();
      
      // 5. Advanced Injection Testing
      await this.testAdvancedInjectionVulnerabilities();
      
      // 6. File Upload & Download Testing
      await this.testFileHandlingVulnerabilities();
      
      // 7. API Security Testing
      await this.testAPISecurityVulnerabilities();
      
      // 8. Race Condition Testing
      await this.testRaceConditionVulnerabilities();
      
      // 9. Cryptographic Testing
      await this.testCryptographicVulnerabilities();
      
      // 10. Infrastructure Testing
      await this.testInfrastructureVulnerabilities();
      
      const endTime = performance.now();
      const duration = Math.round(endTime - startTime);
      
      // Generate penetration testing report
      await this.generatePenetrationTestReport(duration);
      
    } catch (error) {
      console.error('❌ Penetration testing failed:', error.message);
      throw error;
    }
  }

  /**
   * Setup test environment with test data
   */
  async setupTestEnvironment() {
    console.log('\n🔧 Setting up test environment...');
    
    try {
      // Create test users
      for (const user of this.testData.users) {
        try {
          await this.client.post('/api/auth/register', {
            ...user,
            firstName: 'PenTest',
            lastName: 'User'
          });
        } catch (error) {
          // User might already exist
        }
      }
      
      // Get authentication tokens
      for (const user of this.testData.users) {
        try {
          const response = await this.client.post('/api/auth/login', user);
          if (response.data && response.data.data && response.data.data.tokens) {
            this.testData.sessions.set(user.email, {
              accessToken: response.data.data.tokens.accessToken,
              refreshToken: response.data.data.tokens.refreshToken,
              user: response.data.data.user
            });
          }
        } catch (error) {
          console.warn(`Failed to login test user ${user.email}:`, error.message);
        }
      }
      
      console.log(`✅ Test environment setup complete. ${this.testData.sessions.size} test sessions created.`);
      
    } catch (error) {
      console.warn('⚠️  Test environment setup had issues:', error.message);
    }
  }

  /**
   * Test authentication vulnerabilities
   */
  async testAuthenticationVulnerabilities() {
    console.log('\n🔐 Testing Authentication Vulnerabilities...');
    
    // Test 1: JWT Token Manipulation
    await this.testJWTManipulation();
    
    // Test 2: Session Fixation
    await this.testSessionFixation();
    
    // Test 3: Concurrent Session Handling
    await this.testConcurrentSessions();
    
    // Test 4: Token Replay Attacks
    await this.testTokenReplayAttacks();
    
    // Test 5: Authentication Bypass
    await this.testAuthenticationBypass();
    
    // Test 6: Password Reset Vulnerabilities
    await this.testPasswordResetVulnerabilities();
  }

  /**
   * Test JWT token manipulation
   */
  async testJWTManipulation() {
    console.log('  Testing JWT token manipulation...');
    
    const session = this.testData.sessions.values().next().value;
    if (!session) {
      console.log('    Skipping - no test session available');
      return;
    }
    
    const originalToken = session.accessToken;
    const tokenParts = originalToken.split('.');
    
    if (tokenParts.length !== 3) {
      console.log('    Invalid JWT token format');
      return;
    }
    
    // Test 1: Algorithm confusion attack (change alg to none)
    try {
      const header = JSON.parse(Buffer.from(tokenParts[0], 'base64').toString());
      header.alg = 'none';
      const manipulatedHeader = Buffer.from(JSON.stringify(header)).toString('base64');
      const noneAlgToken = `${manipulatedHeader}.${tokenParts[1]}.`;
      
      const response = await this.client.get('/api/orders', {
        headers: { Authorization: `Bearer ${noneAlgToken}` }
      });
      
      if (response.status === 200) {
        this.addExploit(
          'JWT_ALGORITHM_CONFUSION',
          'JWT token with "none" algorithm accepted',
          'CRITICAL',
          { token: noneAlgToken }
        );
      }
    } catch (error) {
      // Expected
    }
    
    // Test 2: Payload manipulation
    try {
      const payload = JSON.parse(Buffer.from(tokenParts[1], 'base64').toString());
      payload.userId = 999999; // Try to escalate to different user
      payload.type = 'admin'; // Try to escalate to admin
      const manipulatedPayload = Buffer.from(JSON.stringify(payload)).toString('base64');
      const manipulatedToken = `${tokenParts[0]}.${manipulatedPayload}.${tokenParts[2]}`;
      
      const response = await this.client.get('/api/orders', {
        headers: { Authorization: `Bearer ${manipulatedToken}` }
      });
      
      if (response.status === 200) {
        this.addExploit(
          'JWT_PAYLOAD_MANIPULATION',
          'JWT token with manipulated payload accepted',
          'CRITICAL',
          { originalPayload: payload, manipulatedToken }
        );
      }
    } catch (error) {
      // Expected
    }
    
    // Test 3: Signature stripping
    try {
      const strippedToken = `${tokenParts[0]}.${tokenParts[1]}.`;
      
      const response = await this.client.get('/api/orders', {
        headers: { Authorization: `Bearer ${strippedToken}` }
      });
      
      if (response.status === 200) {
        this.addExploit(
          'JWT_SIGNATURE_STRIPPING',
          'JWT token without signature accepted',
          'CRITICAL',
          { token: strippedToken }
        );
      }
    } catch (error) {
      // Expected
    }
  }

  /**
   * Test authorization vulnerabilities
   */
  async testAuthorizationVulnerabilities() {
    console.log('\n🛡️ Testing Authorization Vulnerabilities...');
    
    // Test 1: Horizontal Privilege Escalation
    await this.testHorizontalPrivilegeEscalation();
    
    // Test 2: Vertical Privilege Escalation
    await this.testVerticalPrivilegeEscalation();
    
    // Test 3: Direct Object Reference
    await this.testDirectObjectReference();
    
    // Test 4: Parameter Pollution
    await this.testParameterPollution();
    
    // Test 5: HTTP Method Override
    await this.testHTTPMethodOverride();
  }

  /**
   * Test horizontal privilege escalation
   */
  async testHorizontalPrivilegeEscalation() {
    console.log('  Testing horizontal privilege escalation...');
    
    const sessions = Array.from(this.testData.sessions.values());
    if (sessions.length < 2) {
      console.log('    Skipping - need at least 2 test sessions');
      return;
    }
    
    const user1Session = sessions[0];
    const user2Session = sessions[1];
    
    // Create an order with user1
    try {
      const orderResponse = await this.client.post('/api/orders', {
        items: [{ product_id: 1, quantity: 1 }],
        shipping_address_id: 1,
        payment_method: 'cod'
      }, {
        headers: { Authorization: `Bearer ${user1Session.accessToken}` }
      });
      
      if (orderResponse.status === 201 && orderResponse.data.data) {
        const orderId = orderResponse.data.data.id;
        
        // Try to access user1's order with user2's token
        const accessResponse = await this.client.get(`/api/orders/${orderId}`, {
          headers: { Authorization: `Bearer ${user2Session.accessToken}` }
        });
        
        if (accessResponse.status === 200) {
          this.addExploit(
            'HORIZONTAL_PRIVILEGE_ESCALATION',
            `User can access another user's order (Order ID: ${orderId})`,
            'HIGH',
            { orderId, user1: user1Session.user.id, user2: user2Session.user.id }
          );
        }
      }
    } catch (error) {
      // Expected for proper authorization
    }
  }

  /**
   * Test business logic vulnerabilities
   */
  async testBusinessLogicVulnerabilities() {
    console.log('\n💼 Testing Business Logic Vulnerabilities...');
    
    // Test 1: Price Manipulation
    await this.testPriceManipulation();
    
    // Test 2: Quantity Manipulation
    await this.testQuantityManipulation();
    
    // Test 3: Coupon Abuse
    await this.testCouponAbuse();
    
    // Test 4: Race Conditions in Orders
    await this.testOrderRaceConditions();
    
    // Test 5: Inventory Bypass
    await this.testInventoryBypass();
    
    // Test 6: Payment Amount Manipulation
    await this.testPaymentAmountManipulation();
  }

  /**
   * Test price manipulation
   */
  async testPriceManipulation() {
    console.log('  Testing price manipulation...');
    
    const session = this.testData.sessions.values().next().value;
    if (!session) return;
    
    // Test 1: Negative prices
    try {
      const response = await this.client.post('/api/orders', {
        items: [{ product_id: 1, quantity: 1, price: -100 }],
        shipping_address_id: 1,
        payment_method: 'cod'
      }, {
        headers: { Authorization: `Bearer ${session.accessToken}` }
      });
      
      if (response.status === 201) {
        this.addBusinessLogicFlaw(
          'NEGATIVE_PRICE_ACCEPTED',
          'Order accepted with negative price',
          'HIGH'
        );
      }
    } catch (error) {
      // Expected
    }
    
    // Test 2: Zero prices
    try {
      const response = await this.client.post('/api/orders', {
        items: [{ product_id: 1, quantity: 1, price: 0 }],
        shipping_address_id: 1,
        payment_method: 'cod'
      }, {
        headers: { Authorization: `Bearer ${session.accessToken}` }
      });
      
      if (response.status === 201) {
        this.addBusinessLogicFlaw(
          'ZERO_PRICE_ACCEPTED',
          'Order accepted with zero price',
          'MEDIUM'
        );
      }
    } catch (error) {
      // Expected
    }
    
    // Test 3: Extremely high prices (overflow)
    try {
      const response = await this.client.post('/api/orders', {
        items: [{ product_id: 1, quantity: 1, price: Number.MAX_SAFE_INTEGER }],
        shipping_address_id: 1,
        payment_method: 'cod'
      }, {
        headers: { Authorization: `Bearer ${session.accessToken}` }
      });
      
      if (response.status === 201) {
        this.addBusinessLogicFlaw(
          'PRICE_OVERFLOW_ACCEPTED',
          'Order accepted with overflow price',
          'MEDIUM'
        );
      }
    } catch (error) {
      // Expected
    }
  }

  /**
   * Test payment system vulnerabilities
   */
  async testPaymentSystemVulnerabilities() {
    console.log('\n💳 Testing Payment System Vulnerabilities...');
    
    // Test 1: Payment Bypass
    await this.testPaymentBypass();
    
    // Test 2: Double Spending
    await this.testDoubleSpending();
    
    // Test 3: Payment Amount Manipulation
    await this.testPaymentAmountManipulation();
    
    // Test 4: Currency Manipulation
    await this.testCurrencyManipulation();
    
    // Test 5: Razorpay Integration Security
    await this.testRazorpayIntegrationSecurity();
  }

  /**
   * Test payment bypass
   */
  async testPaymentBypass() {
    console.log('  Testing payment bypass...');
    
    const session = this.testData.sessions.values().next().value;
    if (!session) return;
    
    // Test 1: Direct order completion without payment
    try {
      const orderResponse = await this.client.post('/api/orders', {
        items: [{ product_id: 1, quantity: 1 }],
        shipping_address_id: 1,
        payment_method: 'razorpay'
      }, {
        headers: { Authorization: `Bearer ${session.accessToken}` }
      });
      
      if (orderResponse.status === 201 && orderResponse.data.data) {
        const orderId = orderResponse.data.data.id;
        
        // Try to mark order as paid without actual payment
        const paymentResponse = await this.client.post('/api/payments/verify', {
          order_id: orderId,
          razorpay_order_id: 'fake_order_id',
          razorpay_payment_id: 'fake_payment_id',
          razorpay_signature: 'fake_signature'
        }, {
          headers: { Authorization: `Bearer ${session.accessToken}` }
        });
        
        if (paymentResponse.status === 200) {
          this.addExploit(
            'PAYMENT_BYPASS',
            'Order marked as paid with fake payment details',
            'CRITICAL',
            { orderId }
          );
        }
      }
    } catch (error) {
      // Expected for proper payment validation
    }
  }

  /**
   * Test advanced injection vulnerabilities
   */
  async testAdvancedInjectionVulnerabilities() {
    console.log('\n💉 Testing Advanced Injection Vulnerabilities...');
    
    // Test 1: NoSQL Injection
    await this.testNoSQLInjection();
    
    // Test 2: LDAP Injection
    await this.testLDAPInjection();
    
    // Test 3: Command Injection
    await this.testCommandInjection();
    
    // Test 4: Template Injection
    await this.testTemplateInjection();
    
    // Test 5: JSON Injection
    await this.testJSONInjection();
  }

  /**
   * Test NoSQL injection
   */
  async testNoSQLInjection() {
    console.log('  Testing NoSQL injection...');
    
    const nosqlPayloads = [
      { "$ne": null },
      { "$gt": "" },
      { "$regex": ".*" },
      { "$where": "1==1" },
      { "$or": [{"a": 1}, {"b": 2}] },
      "'; return true; var dummy='",
      "1; return true; var dummy=1",
      "true, $where: '1 == 1'"
    ];
    
    for (const payload of nosqlPayloads) {
      try {
        // Test in login endpoint
        const response = await this.client.post('/api/auth/login', {
          email: payload,
          password: payload
        });
        
        if (response.status === 200 && response.data.success) {
          this.addExploit(
            'NOSQL_INJECTION',
            'NoSQL injection successful in login endpoint',
            'CRITICAL',
            { payload }
          );
        }
      } catch (error) {
        // Expected
      }
    }
  }

  /**
   * Test command injection
   */
  async testCommandInjection() {
    console.log('  Testing command injection...');
    
    const commandPayloads = [
      '; ls -la',
      '| whoami',
      '& ping -c 1 127.0.0.1',
      '`id`',
      '$(whoami)',
      '; cat /etc/passwd',
      '|| dir',
      '& type C:\\Windows\\System32\\drivers\\etc\\hosts'
    ];
    
    const testEndpoints = [
      { method: 'GET', path: '/api/products', param: 'search' },
      { method: 'POST', path: '/api/auth/register', param: 'firstName' }
    ];
    
    for (const endpoint of testEndpoints) {
      for (const payload of commandPayloads) {
        try {
          let response;
          if (endpoint.method === 'GET') {
            const params = {};
            params[endpoint.param] = payload;
            response = await this.client.get(endpoint.path, { params });
          } else {
            const data = {
              email: 'test@example.com',
              password: 'TestPass123!',
              firstName: 'Test',
              lastName: 'User'
            };
            data[endpoint.param] = payload;
            response = await this.client.post(endpoint.path, data);
          }
          
          // Check for command execution indicators
          const responseText = JSON.stringify(response.data).toLowerCase();
          const commandIndicators = [
            'root:',
            'uid=',
            'gid=',
            'volume in drive',
            'directory of',
            '/bin/bash',
            '/bin/sh'
          ];
          
          if (commandIndicators.some(indicator => responseText.includes(indicator))) {
            this.addExploit(
              'COMMAND_INJECTION',
              `Command injection successful in ${endpoint.method} ${endpoint.path}`,
              'CRITICAL',
              { payload, endpoint }
            );
          }
        } catch (error) {
          // Expected
        }
      }
    }
  }

  /**
   * Test race condition vulnerabilities
   */
  async testRaceConditionVulnerabilities() {
    console.log('\n🏃 Testing Race Condition Vulnerabilities...');
    
    const session = this.testData.sessions.values().next().value;
    if (!session) return;
    
    // Test 1: Concurrent order creation with limited stock
    await this.testConcurrentOrderCreation(session);
    
    // Test 2: Concurrent coupon usage
    await this.testConcurrentCouponUsage(session);
    
    // Test 3: Concurrent account operations
    await this.testConcurrentAccountOperations(session);
  }

  /**
   * Test concurrent order creation
   */
  async testConcurrentOrderCreation(session) {
    console.log('  Testing concurrent order creation...');
    
    const orderData = {
      items: [{ product_id: 1, quantity: 100 }], // Large quantity to test stock limits
      shipping_address_id: 1,
      payment_method: 'cod'
    };
    
    // Create multiple concurrent requests
    const promises = [];
    for (let i = 0; i < 10; i++) {
      promises.push(
        this.client.post('/api/orders', orderData, {
          headers: { Authorization: `Bearer ${session.accessToken}` }
        })
      );
    }
    
    try {
      const responses = await Promise.all(promises);
      const successfulOrders = responses.filter(r => r.status === 201);
      
      if (successfulOrders.length > 1) {
        this.addBusinessLogicFlaw(
          'RACE_CONDITION_ORDERS',
          `${successfulOrders.length} concurrent orders created - potential stock overselling`,
          'HIGH',
          { successfulOrders: successfulOrders.length }
        );
      }
    } catch (error) {
      // Some requests might fail, which is expected
    }
  }

  /**
   * Test file handling vulnerabilities
   */
  async testFileHandlingVulnerabilities() {
    console.log('\n📁 Testing File Handling Vulnerabilities...');
    
    // Test 1: Path Traversal
    await this.testPathTraversal();
    
    // Test 2: File Upload Bypass
    await this.testFileUploadBypass();
    
    // Test 3: File Inclusion
    await this.testFileInclusion();
    
    // Test 4: Zip Bomb
    await this.testZipBomb();
  }

  /**
   * Test path traversal
   */
  async testPathTraversal() {
    console.log('  Testing path traversal...');
    
    const pathTraversalPayloads = [
      '../../../etc/passwd',
      '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts',
      '....//....//....//etc/passwd',
      '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
      '..%252f..%252f..%252fetc%252fpasswd',
      '..%c0%af..%c0%af..%c0%afetc%c0%afpasswd',
      '/var/www/../../etc/passwd',
      'file:///etc/passwd',
      '\\..\\..\\..\\etc\\passwd'
    ];
    
    for (const payload of pathTraversalPayloads) {
      try {
        // Test in file download endpoints
        const response = await this.client.get(`/api/files/${encodeURIComponent(payload)}`);
        
        const responseText = JSON.stringify(response.data);
        if (responseText.includes('root:') || responseText.includes('[boot loader]')) {
          this.addExploit(
            'PATH_TRAVERSAL',
            'Path traversal successful - sensitive file accessed',
            'HIGH',
            { payload, file: payload }
          );
        }
      } catch (error) {
        // Expected for non-existent endpoints
      }
    }
  }

  /**
   * Test cryptographic vulnerabilities
   */
  async testCryptographicVulnerabilities() {
    console.log('\n🔐 Testing Cryptographic Vulnerabilities...');
    
    // Test 1: Weak Random Number Generation
    await this.testWeakRandomGeneration();
    
    // Test 2: Predictable Tokens
    await this.testPredictableTokens();
    
    // Test 3: Weak Hashing
    await this.testWeakHashing();
    
    // Test 4: Encryption Weaknesses
    await this.testEncryptionWeaknesses();
  }

  /**
   * Test predictable tokens
   */
  async testPredictableTokens() {
    console.log('  Testing predictable tokens...');
    
    const tokens = [];
    
    // Generate multiple password reset tokens
    for (let i = 0; i < 5; i++) {
      try {
        const response = await this.client.post('/api/auth/forgot-password', {
          email: 'test@example.com'
        });
        
        if (response.data && response.data.token) {
          tokens.push(response.data.token);
        }
      } catch (error) {
        // Expected if endpoint doesn't exist
      }
    }
    
    // Analyze tokens for patterns
    if (tokens.length > 1) {
      const tokenLengths = tokens.map(t => t.length);
      const uniqueLengths = [...new Set(tokenLengths)];
      
      if (uniqueLengths.length === 1 && uniqueLengths[0] < 32) {
        this.addExploit(
          'WEAK_TOKEN_LENGTH',
          `Password reset tokens are too short (${uniqueLengths[0]} characters)`,
          'MEDIUM',
          { tokenLength: uniqueLengths[0] }
        );
      }
      
      // Check for sequential patterns
      const numericTokens = tokens.filter(t => /^\d+$/.test(t));
      if (numericTokens.length > 1) {
        this.addExploit(
          'PREDICTABLE_TOKENS',
          'Password reset tokens appear to be sequential numbers',
          'HIGH',
          { tokens: numericTokens }
        );
      }
    }
  }

  /**
   * Test infrastructure vulnerabilities
   */
  async testInfrastructureVulnerabilities() {
    console.log('\n🏗️ Testing Infrastructure Vulnerabilities...');
    
    // Test 1: Server Information Disclosure
    await this.testServerInformationDisclosure();
    
    // Test 2: Debug Information Exposure
    await this.testDebugInformationExposure();
    
    // Test 3: Backup File Discovery
    await this.testBackupFileDiscovery();
    
    // Test 4: Admin Interface Discovery
    await this.testAdminInterfaceDiscovery();
  }

  /**
   * Test server information disclosure
   */
  async testServerInformationDisclosure() {
    console.log('  Testing server information disclosure...');
    
    try {
      const response = await this.client.get('/api/health');
      const headers = response.headers;
      
      // Check for information disclosure headers
      const disclosureHeaders = {
        'server': 'Server software information',
        'x-powered-by': 'Technology stack information',
        'x-aspnet-version': 'ASP.NET version information',
        'x-generator': 'Generator information'
      };
      
      for (const [header, description] of Object.entries(disclosureHeaders)) {
        if (headers[header]) {
          this.addExploit(
            'INFORMATION_DISCLOSURE',
            `${description} disclosed: ${headers[header]}`,
            'LOW',
            { header, value: headers[header] }
          );
        }
      }
      
      // Check response body for version information
      const responseText = JSON.stringify(response.data);
      const versionPatterns = [
        /version\s*:\s*["']?([0-9.]+)["']?/i,
        /v([0-9.]+)/i,
        /build\s*:\s*["']?([0-9.]+)["']?/i
      ];
      
      for (const pattern of versionPatterns) {
        const match = responseText.match(pattern);
        if (match) {
          this.addExploit(
            'VERSION_DISCLOSURE',
            `Version information disclosed: ${match[0]}`,
            'LOW',
            { version: match[1] }
          );
        }
      }
    } catch (error) {
      // Handle error
    }
  }

  /**
   * Test backup file discovery
   */
  async testBackupFileDiscovery() {
    console.log('  Testing backup file discovery...');
    
    const backupFiles = [
      'backup.sql',
      'database.sql',
      'dump.sql',
      'backup.zip',
      'backup.tar.gz',
      'config.bak',
      '.env.backup',
      'package.json.bak',
      'server.js.backup',
      'app.js~',
      '.git/config',
      '.svn/entries',
      'web.config.bak',
      'htaccess.bak'
    ];
    
    for (const file of backupFiles) {
      try {
        const response = await this.client.get(`/${file}`);
        
        if (response.status === 200 && response.data) {
          this.addExploit(
            'BACKUP_FILE_ACCESSIBLE',
            `Backup file accessible: ${file}`,
            'MEDIUM',
            { file, size: JSON.stringify(response.data).length }
          );
        }
      } catch (error) {
        // Expected for non-existent files
      }
    }
  }

  // Helper methods
  addExploit(type, description, severity, details = {}) {
    this.exploits.push({
      type,
      description,
      severity,
      details,
      timestamp: new Date().toISOString()
    });
    
    const severityEmoji = {
      'CRITICAL': '🔴',
      'HIGH': '🟠',
      'MEDIUM': '🟡',
      'LOW': '🔵'
    };
    
    console.log(`  ${severityEmoji[severity]} EXPLOIT ${severity}: ${description}`);
  }

  addBusinessLogicFlaw(type, description, severity, details = {}) {
    this.businessLogicFlaws.push({
      type,
      description,
      severity,
      details,
      timestamp: new Date().toISOString()
    });
    
    const severityEmoji = {
      'CRITICAL': '🔴',
      'HIGH': '🟠',
      'MEDIUM': '🟡',
      'LOW': '🔵'
    };
    
    console.log(`  ${severityEmoji[severity]} BUSINESS LOGIC: ${description}`);
  }

  addTestResult(test, description, status, details = {}) {
    this.testResults.push({
      test,
      description,
      status,
      details,
      timestamp: new Date().toISOString()
    });
    
    const statusEmoji = status === 'PASS' ? '✅' : status === 'FAIL' ? '❌' : '⚠️';
    console.log(`  ${statusEmoji} ${description}`);
  }

  /**
   * Generate penetration testing report
   */
  async generatePenetrationTestReport(duration) {
    console.log('\n📊 Generating Penetration Testing Report...');
    
    const report = {
      metadata: {
        timestamp: new Date().toISOString(),
        duration: `${duration}ms`,
        baseUrl: this.baseUrl,
        testVersion: '1.0.0',
        environment: process.env.NODE_ENV || 'development',
        testType: 'Penetration Testing'
      },
      summary: {
        totalTests: this.testResults.length,
        totalExploits: this.exploits.length,
        totalBusinessLogicFlaws: this.businessLogicFlaws.length,
        criticalExploits: this.exploits.filter(e => e.severity === 'CRITICAL').length,
        highExploits: this.exploits.filter(e => e.severity === 'HIGH').length,
        mediumExploits: this.exploits.filter(e => e.severity === 'MEDIUM').length,
        lowExploits: this.exploits.filter(e => e.severity === 'LOW').length
      },
      exploits: this.exploits,
      businessLogicFlaws: this.businessLogicFlaws,
      testResults: this.testResults,
      recommendations: this.generatePentestRecommendations(),
      riskAssessment: this.generateRiskAssessment()
    };

    // Save report to file
    const reportPath = path.join(__dirname, '..', 'test-reports', `penetration-test-${Date.now()}.json`);
    
    // Ensure directory exists
    const reportDir = path.dirname(reportPath);
    if (!fs.existsSync(reportDir)) {
      fs.mkdirSync(reportDir, { recursive: true });
    }
    
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    
    // Generate HTML report
    await this.generatePentestHTMLReport(report, reportPath.replace('.json', '.html'));
    
    // Print summary
    this.printPentestSummary(report);
    
    console.log(`\n📄 Detailed report saved to: ${reportPath}`);
    console.log(`📄 HTML report saved to: ${reportPath.replace('.json', '.html')}`);
    
    return report;
  }

  generatePentestRecommendations() {
    const recommendations = [
      'Implement comprehensive input validation and output encoding',
      'Use parameterized queries to prevent injection attacks',
      'Implement proper authentication and session management',
      'Apply principle of least privilege for authorization',
      'Validate business logic thoroughly, especially for financial operations',
      'Implement rate limiting and anti-automation measures',
      'Use secure cryptographic functions and proper key management',
      'Regular security code reviews and penetration testing',
      'Implement comprehensive logging and monitoring',
      'Keep all dependencies and frameworks updated'
    ];

    // Add specific recommendations based on found exploits
    const exploitTypes = [...new Set(this.exploits.map(e => e.type))];
    
    if (exploitTypes.includes('JWT_ALGORITHM_CONFUSION')) {
      recommendations.push('URGENT: Fix JWT algorithm confusion vulnerability');
    }
    
    if (exploitTypes.includes('HORIZONTAL_PRIVILEGE_ESCALATION')) {
      recommendations.push('URGENT: Implement proper authorization checks');
    }
    
    if (exploitTypes.includes('PAYMENT_BYPASS')) {
      recommendations.push('CRITICAL: Fix payment bypass vulnerability immediately');
    }

    return recommendations;
  }

  generateRiskAssessment() {
    const totalExploits = this.exploits.length + this.businessLogicFlaws.length;
    const criticalCount = this.exploits.filter(e => e.severity === 'CRITICAL').length;
    const highCount = this.exploits.filter(e => e.severity === 'HIGH').length;
    
    let riskLevel = 'LOW';
    let riskScore = 0;
    
    // Calculate risk score
    riskScore += criticalCount * 10;
    riskScore += highCount * 5;
    riskScore += this.exploits.filter(e => e.severity === 'MEDIUM').length * 2;
    riskScore += this.exploits.filter(e => e.severity === 'LOW').length * 1;
    
    if (criticalCount > 0) {
      riskLevel = 'CRITICAL';
    } else if (highCount > 2) {
      riskLevel = 'HIGH';
    } else if (highCount > 0 || riskScore > 10) {
      riskLevel = 'MEDIUM';
    }
    
    return {
      riskLevel,
      riskScore,
      totalExploits,
      criticalCount,
      highCount,
      businessImpact: this.assessBusinessImpact(),
      recommendations: this.getImmediateActions()
    };
  }

  assessBusinessImpact() {
    const impacts = [];
    
    if (this.exploits.some(e => e.type.includes('PAYMENT'))) {
      impacts.push('Financial loss due to payment vulnerabilities');
    }
    
    if (this.exploits.some(e => e.type.includes('PRIVILEGE_ESCALATION'))) {
      impacts.push('Unauthorized access to sensitive data');
    }
    
    if (this.exploits.some(e => e.type.includes('INJECTION'))) {
      impacts.push('Data breach and system compromise');
    }
    
    if (this.businessLogicFlaws.length > 0) {
      impacts.push('Business process manipulation and fraud');
    }
    
    return impacts;
  }

  getImmediateActions() {
    const actions = [];
    
    if (this.exploits.filter(e => e.severity === 'CRITICAL').length > 0) {
      actions.push('Immediately patch critical vulnerabilities');
      actions.push('Consider taking affected systems offline until patched');
    }
    
    if (this.exploits.some(e => e.type.includes('PAYMENT'))) {
      actions.push('Suspend payment processing until vulnerabilities are fixed');
    }
    
    if (this.exploits.some(e => e.type.includes('PRIVILEGE_ESCALATION'))) {
      actions.push('Review and audit all user permissions');
    }
    
    return actions;
  }

  async generatePentestHTMLReport(report, htmlPath) {
    const html = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penetration Testing Report - Riya Collections</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .risk-banner { padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; font-weight: bold; }
        .risk-critical { background-color: #dc3545; color: white; }
        .risk-high { background-color: #fd7e14; color: white; }
        .risk-medium { background-color: #ffc107; color: black; }
        .risk-low { background-color: #28a745; color: white; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .critical { background-color: #dc3545; color: white; }
        .high { background-color: #fd7e14; color: white; }
        .medium { background-color: #ffc107; color: black; }
        .low { background-color: #17a2b8; color: white; }
        .exploit { margin: 10px 0; padding: 15px; border-left: 4px solid #ccc; background: #f8f9fa; }
        .exploit.critical { border-left-color: #dc3545; background: #f8d7da; }
        .exploit.high { border-left-color: #fd7e14; background: #fff3cd; }
        .exploit.medium { border-left-color: #ffc107; background: #fff3cd; }
        .exploit.low { border-left-color: #17a2b8; background: #d1ecf1; }
        .section { margin: 30px 0; }
        .section h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .details { background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Penetration Testing Report</h1>
            <h2>Riya Collections E-commerce Platform</h2>
            <p>Generated on: ${report.metadata.timestamp}</p>
            <p>Test Duration: ${report.metadata.duration}</p>
        </div>

        <div class="risk-banner risk-${report.riskAssessment.riskLevel.toLowerCase()}">
            🚨 OVERALL RISK LEVEL: ${report.riskAssessment.riskLevel}
            <br>Risk Score: ${report.riskAssessment.riskScore}
        </div>

        <div class="summary">
            <div class="summary-card">
                <h3>Total Exploits</h3>
                <h2>${report.summary.totalExploits}</h2>
            </div>
            <div class="summary-card critical">
                <h3>Critical</h3>
                <h2>${report.summary.criticalExploits}</h2>
            </div>
            <div class="summary-card high">
                <h3>High</h3>
                <h2>${report.summary.highExploits}</h2>
            </div>
            <div class="summary-card medium">
                <h3>Medium</h3>
                <h2>${report.summary.mediumExploits}</h2>
            </div>
            <div class="summary-card low">
                <h3>Low</h3>
                <h2>${report.summary.lowExploits}</h2>
            </div>
        </div>

        <div class="section">
            <h2>🚨 Security Exploits Found</h2>
            ${report.exploits.length === 0 ? 
                '<p style="color: #28a745; font-weight: bold;">✅ No security exploits found!</p>' :
                report.exploits.map(exploit => `
                    <div class="exploit ${exploit.severity.toLowerCase()}">
                        <h4>${exploit.type} (${exploit.severity})</h4>
                        <p>${exploit.description}</p>
                        ${Object.keys(exploit.details).length > 0 ? 
                            `<div class="details">${JSON.stringify(exploit.details, null, 2)}</div>` : ''
                        }
                        <small>Detected at: ${exploit.timestamp}</small>
                    </div>
                `).join('')
            }
        </div>

        <div class="section">
            <h2>💼 Business Logic Flaws</h2>
            ${report.businessLogicFlaws.length === 0 ? 
                '<p style="color: #28a745; font-weight: bold;">✅ No business logic flaws found!</p>' :
                report.businessLogicFlaws.map(flaw => `
                    <div class="exploit ${flaw.severity.toLowerCase()}">
                        <h4>${flaw.type} (${flaw.severity})</h4>
                        <p>${flaw.description}</p>
                        ${Object.keys(flaw.details).length > 0 ? 
                            `<div class="details">${JSON.stringify(flaw.details, null, 2)}</div>` : ''
                        }
                        <small>Detected at: ${flaw.timestamp}</small>
                    </div>
                `).join('')
            }
        </div>

        <div class="section">
            <h2>📊 Risk Assessment</h2>
            <p><strong>Business Impact:</strong></p>
            <ul>
                ${report.riskAssessment.businessImpact.map(impact => `<li>${impact}</li>`).join('')}
            </ul>
            
            <p><strong>Immediate Actions Required:</strong></p>
            <ul>
                ${report.riskAssessment.recommendations.map(action => `<li style="color: #dc3545; font-weight: bold;">${action}</li>`).join('')}
            </ul>
        </div>

        <div class="section">
            <h2>💡 Recommendations</h2>
            <ul>
                ${report.recommendations.map(rec => `<li>${rec}</li>`).join('')}
            </ul>
        </div>
    </div>
</body>
</html>`;

    fs.writeFileSync(htmlPath, html);
  }

  printPentestSummary(report) {
    console.log('\n' + '='.repeat(70));
    console.log('🎯 PENETRATION TESTING SUMMARY');
    console.log('='.repeat(70));
    console.log(`Overall Risk Level: ${report.riskAssessment.riskLevel}`);
    console.log(`Risk Score: ${report.riskAssessment.riskScore}`);
    console.log(`Total Exploits Found: ${report.summary.totalExploits}`);
    console.log(`🔴 Critical: ${report.summary.criticalExploits}`);
    console.log(`🟠 High: ${report.summary.highExploits}`);
    console.log(`🟡 Medium: ${report.summary.mediumExploits}`);
    console.log(`🔵 Low: ${report.summary.lowExploits}`);
    console.log(`Business Logic Flaws: ${report.summary.totalBusinessLogicFlaws}`);
    
    if (report.summary.totalExploits === 0) {
      console.log('\n🎉 Excellent! No security exploits found during penetration testing.');
    } else {
      console.log('\n⚠️  Security exploits detected. Immediate action required!');
      
      if (report.summary.criticalExploits > 0) {
        console.log('🚨 CRITICAL exploits found - system is at high risk!');
      }
    }
    
    console.log('='.repeat(70));
  }
}

// Export for use as module
module.exports = PenetrationTester;

// Run if called directly
if (require.main === module) {
  const tester = new PenetrationTester();
  tester.runPenetrationTest()
    .then(() => {
      console.log('\n✅ Penetration testing completed successfully');
      process.exit(0);
    })
    .catch((error) => {
      console.error('\n❌ Penetration testing failed:', error);
      process.exit(1);
    });
}