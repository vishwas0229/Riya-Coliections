#!/usr/bin/env node

/**
 * Comprehensive Security Validation Runner
 * for Riya Collections E-commerce Platform
 * 
 * This script orchestrates all security testing and validation:
 * - Runs security audit
 * - Executes penetration testing
 * - Validates property-based security tests
 * - Generates comprehensive reports
 * - Provides actionable recommendations
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
 */

const { spawn, exec } = require('child_process');
const fs = require('fs');
const path = require('path');
const SecurityAuditor = require('./security-audit');
const PenetrationTester = require('./penetration-testing');
const SecurityReportGenerator = require('./security-report-generator');

class SecurityValidationRunner {
  constructor(config = {}) {
    this.baseUrl = config.baseUrl || process.env.BASE_URL || 'http://localhost:5000';
    this.config = {
      runAudit: config.runAudit !== false,
      runPentest: config.runPentest !== false,
      runPropertyTests: config.runPropertyTests !== false,
      generateReports: config.generateReports !== false,
      verbose: config.verbose || false,
      outputDir: config.outputDir || path.join(__dirname, '..', 'test-reports'),
      ...config
    };
    
    this.results = {
      audit: null,
      pentest: null,
      propertyTests: null,
      reports: null,
      summary: null
    };
    
    this.startTime = Date.now();
  }

  /**
   * Run complete security validation suite
   */
  async runCompleteValidation() {
    console.log('🛡️ Starting Comprehensive Security Validation Suite');
    console.log('🎯 Target: Riya Collections E-commerce Platform');
    console.log('🌐 Base URL:', this.baseUrl);
    console.log('=' .repeat(80));
    
    try {
      // Pre-validation checks
      await this.performPreValidationChecks();
      
      // Run security audit
      if (this.config.runAudit) {
        await this.runSecurityAudit();
      }
      
      // Run penetration testing
      if (this.config.runPentest) {
        await this.runPenetrationTesting();
      }
      
      // Run property-based security tests
      if (this.config.runPropertyTests) {
        await this.runPropertyBasedTests();
      }
      
      // Generate comprehensive reports
      if (this.config.generateReports) {
        await this.generateComprehensiveReports();
      }
      
      // Generate final summary
      await this.generateFinalSummary();
      
      // Provide recommendations
      await this.provideRecommendations();
      
      const totalTime = Date.now() - this.startTime;
      console.log(`\n✅ Security validation completed in ${Math.round(totalTime / 1000)}s`);
      
      return this.results;
      
    } catch (error) {
      console.error('❌ Security validation failed:', error.message);
      if (this.config.verbose) {
        console.error(error.stack);
      }
      throw error;
    }
  }

  /**
   * Perform pre-validation checks
   */
  async performPreValidationChecks() {
    console.log('\n🔍 Performing Pre-Validation Checks...');
    
    // Check if server is running
    try {
      const response = await this.makeRequest('/api/health');
      if (response.ok) {
        console.log('✅ Server is running and accessible');
      } else {
        throw new Error(`Server returned status ${response.status}`);
      }
    } catch (error) {
      console.warn('⚠️  Server health check failed:', error.message);
      console.log('🚀 Attempting to start server...');
      
      // Try to start the server
      await this.startServer();
    }
    
    // Check database connectivity
    try {
      const response = await this.makeRequest('/api/health/database');
      if (response.ok) {
        console.log('✅ Database connectivity verified');
      }
    } catch (error) {
      console.warn('⚠️  Database connectivity check failed:', error.message);
    }
    
    // Ensure output directory exists
    if (!fs.existsSync(this.config.outputDir)) {
      fs.mkdirSync(this.config.outputDir, { recursive: true });
      console.log('✅ Output directory created');
    }
    
    // Check required dependencies
    await this.checkDependencies();
    
    console.log('✅ Pre-validation checks completed');
  }

  /**
   * Start the server if not running
   */
  async startServer() {
    return new Promise((resolve, reject) => {
      console.log('🚀 Starting server...');
      
      const serverProcess = spawn('npm', ['start'], {
        cwd: path.join(__dirname, '..'),
        stdio: 'pipe'
      });
      
      let serverStarted = false;
      
      serverProcess.stdout.on('data', (data) => {
        const output = data.toString();
        if (this.config.verbose) {
          console.log('Server:', output.trim());
        }
        
        if (output.includes('Server running') || output.includes('listening')) {
          if (!serverStarted) {
            serverStarted = true;
            console.log('✅ Server started successfully');
            resolve();
          }
        }
      });
      
      serverProcess.stderr.on('data', (data) => {
        const error = data.toString();
        if (this.config.verbose) {
          console.error('Server Error:', error.trim());
        }
      });
      
      serverProcess.on('error', (error) => {
        reject(new Error(`Failed to start server: ${error.message}`));
      });
      
      // Timeout after 30 seconds
      setTimeout(() => {
        if (!serverStarted) {
          serverProcess.kill();
          reject(new Error('Server startup timeout'));
        }
      }, 30000);
    });
  }

  /**
   * Check required dependencies
   */
  async checkDependencies() {
    const requiredPackages = [
      'axios',
      'fast-check',
      'jest',
      'supertest'
    ];
    
    const packageJsonPath = path.join(__dirname, '..', 'package.json');
    if (!fs.existsSync(packageJsonPath)) {
      throw new Error('package.json not found');
    }
    
    const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
    const allDependencies = {
      ...packageJson.dependencies,
      ...packageJson.devDependencies
    };
    
    const missingPackages = requiredPackages.filter(pkg => !allDependencies[pkg]);
    
    if (missingPackages.length > 0) {
      console.warn('⚠️  Missing dependencies:', missingPackages.join(', '));
      console.log('📦 Installing missing dependencies...');
      
      await this.installDependencies(missingPackages);
    }
    
    console.log('✅ All required dependencies are available');
  }

  /**
   * Install missing dependencies
   */
  async installDependencies(packages) {
    return new Promise((resolve, reject) => {
      const installProcess = spawn('npm', ['install', ...packages], {
        cwd: path.join(__dirname, '..'),
        stdio: 'pipe'
      });
      
      installProcess.on('close', (code) => {
        if (code === 0) {
          console.log('✅ Dependencies installed successfully');
          resolve();
        } else {
          reject(new Error(`Dependency installation failed with code ${code}`));
        }
      });
      
      installProcess.on('error', (error) => {
        reject(new Error(`Failed to install dependencies: ${error.message}`));
      });
    });
  }

  /**
   * Run security audit
   */
  async runSecurityAudit() {
    console.log('\n🔒 Running Security Audit...');
    
    try {
      const auditor = new SecurityAuditor({ baseUrl: this.baseUrl });
      this.results.audit = await auditor.runCompleteAudit();
      
      console.log('✅ Security audit completed');
      console.log(`   Vulnerabilities found: ${this.results.audit.summary.totalVulnerabilities}`);
      console.log(`   Critical: ${this.results.audit.summary.criticalVulnerabilities}`);
      console.log(`   High: ${this.results.audit.summary.highVulnerabilities}`);
      
    } catch (error) {
      console.error('❌ Security audit failed:', error.message);
      this.results.audit = { error: error.message };
    }
  }

  /**
   * Run penetration testing
   */
  async runPenetrationTesting() {
    console.log('\n🎯 Running Penetration Testing...');
    
    try {
      const tester = new PenetrationTester({ baseUrl: this.baseUrl });
      this.results.pentest = await tester.runPenetrationTest();
      
      console.log('✅ Penetration testing completed');
      console.log(`   Exploits found: ${this.results.pentest.summary.totalExploits}`);
      console.log(`   Business logic flaws: ${this.results.pentest.summary.totalBusinessLogicFlaws}`);
      console.log(`   Critical: ${this.results.pentest.summary.criticalExploits}`);
      
    } catch (error) {
      console.error('❌ Penetration testing failed:', error.message);
      this.results.pentest = { error: error.message };
    }
  }

  /**
   * Run property-based security tests
   */
  async runPropertyBasedTests() {
    console.log('\n🧪 Running Property-Based Security Tests...');
    
    return new Promise((resolve, reject) => {
      const testProcess = spawn('npm', ['test', '--', '--testPathPattern=security-validation.property.test.js'], {
        cwd: path.join(__dirname, '..'),
        stdio: 'pipe'
      });
      
      let testOutput = '';
      let testResults = {
        passed: 0,
        failed: 0,
        total: 0,
        details: []
      };
      
      testProcess.stdout.on('data', (data) => {
        const output = data.toString();
        testOutput += output;
        
        if (this.config.verbose) {
          console.log(output.trim());
        }
        
        // Parse test results
        const passMatch = output.match(/(\d+) passing/);
        const failMatch = output.match(/(\d+) failing/);
        
        if (passMatch) testResults.passed = parseInt(passMatch[1]);
        if (failMatch) testResults.failed = parseInt(failMatch[1]);
      });
      
      testProcess.stderr.on('data', (data) => {
        const error = data.toString();
        testOutput += error;
        
        if (this.config.verbose) {
          console.error(error.trim());
        }
      });
      
      testProcess.on('close', (code) => {
        testResults.total = testResults.passed + testResults.failed;
        testResults.exitCode = code;
        testResults.output = testOutput;
        
        this.results.propertyTests = testResults;
        
        if (code === 0) {
          console.log('✅ Property-based tests completed successfully');
          console.log(`   Tests passed: ${testResults.passed}`);
        } else {
          console.log('⚠️  Property-based tests completed with issues');
          console.log(`   Tests passed: ${testResults.passed}`);
          console.log(`   Tests failed: ${testResults.failed}`);
        }
        
        resolve();
      });
      
      testProcess.on('error', (error) => {
        console.error('❌ Property-based tests failed to run:', error.message);
        this.results.propertyTests = { error: error.message };
        resolve(); // Don't reject, continue with other tests
      });
    });
  }

  /**
   * Generate comprehensive reports
   */
  async generateComprehensiveReports() {
    console.log('\n📊 Generating Comprehensive Reports...');
    
    try {
      const generator = new SecurityReportGenerator({
        baseUrl: this.baseUrl,
        outputDir: this.config.outputDir
      });
      
      // Create a combined report from all test results
      const combinedResults = this.combineAllResults();
      
      this.results.reports = await generator.generateComprehensiveReport();
      
      console.log('✅ Comprehensive reports generated');
      console.log(`   Reports saved to: ${this.config.outputDir}`);
      
    } catch (error) {
      console.error('❌ Report generation failed:', error.message);
      this.results.reports = { error: error.message };
    }
  }

  /**
   * Combine all test results
   */
  combineAllResults() {
    const combined = {
      timestamp: new Date().toISOString(),
      baseUrl: this.baseUrl,
      totalDuration: Date.now() - this.startTime,
      
      summary: {
        auditCompleted: !!this.results.audit && !this.results.audit.error,
        pentestCompleted: !!this.results.pentest && !this.results.pentest.error,
        propertyTestsCompleted: !!this.results.propertyTests && !this.results.propertyTests.error,
        
        totalVulnerabilities: 0,
        totalExploits: 0,
        totalPropertyTestFailures: 0,
        
        overallRiskLevel: 'UNKNOWN',
        complianceScore: 0
      },
      
      findings: {
        audit: this.results.audit || {},
        pentest: this.results.pentest || {},
        propertyTests: this.results.propertyTests || {}
      }
    };
    
    // Calculate totals
    if (this.results.audit && !this.results.audit.error) {
      combined.summary.totalVulnerabilities = this.results.audit.summary.totalVulnerabilities || 0;
    }
    
    if (this.results.pentest && !this.results.pentest.error) {
      combined.summary.totalExploits = this.results.pentest.summary.totalExploits || 0;
    }
    
    if (this.results.propertyTests && !this.results.propertyTests.error) {
      combined.summary.totalPropertyTestFailures = this.results.propertyTests.failed || 0;
    }
    
    // Calculate overall risk
    const totalIssues = combined.summary.totalVulnerabilities + 
                       combined.summary.totalExploits + 
                       combined.summary.totalPropertyTestFailures;
    
    if (totalIssues === 0) {
      combined.summary.overallRiskLevel = 'LOW';
    } else if (totalIssues < 5) {
      combined.summary.overallRiskLevel = 'MEDIUM';
    } else {
      combined.summary.overallRiskLevel = 'HIGH';
    }
    
    return combined;
  }

  /**
   * Generate final summary
   */
  async generateFinalSummary() {
    console.log('\n📋 Generating Final Summary...');
    
    const summary = this.combineAllResults();
    
    // Save summary to file
    const summaryPath = path.join(this.config.outputDir, `security-validation-summary-${Date.now()}.json`);
    fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
    
    // Generate summary report
    const summaryReport = this.generateSummaryReport(summary);
    const reportPath = path.join(this.config.outputDir, `security-validation-summary-${Date.now()}.html`);
    fs.writeFileSync(reportPath, summaryReport);
    
    this.results.summary = summary;
    
    console.log('✅ Final summary generated');
    console.log(`   Summary saved to: ${summaryPath}`);
    console.log(`   Report saved to: ${reportPath}`);
  }

  /**
   * Generate summary report HTML
   */
  generateSummaryReport(summary) {
    const riskColor = {
      'LOW': '#28a745',
      'MEDIUM': '#ffc107',
      'HIGH': '#fd7e14',
      'CRITICAL': '#dc3545',
      'UNKNOWN': '#6c757d'
    }[summary.summary.overallRiskLevel];
    
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Validation Summary - Riya Collections</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { color: #333; margin: 0; }
        .risk-banner { padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; font-weight: bold; color: white; background: ${riskColor}; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
        .metric-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .metric-value { font-size: 2em; font-weight: bold; margin: 10px 0; color: #007bff; }
        .status { margin: 20px 0; }
        .status-item { display: flex; justify-content: space-between; padding: 10px; margin: 5px 0; background: #f8f9fa; border-radius: 4px; }
        .status-pass { border-left: 4px solid #28a745; }
        .status-fail { border-left: 4px solid #dc3545; }
        .status-warn { border-left: 4px solid #ffc107; }
        .recommendations { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛡️ Security Validation Summary</h1>
            <h2>Riya Collections E-commerce Platform</h2>
            <p>Validation completed on: ${new Date(summary.timestamp).toLocaleString()}</p>
            <p>Total duration: ${Math.round(summary.totalDuration / 1000)} seconds</p>
        </div>

        <div class="risk-banner">
            🚨 OVERALL RISK LEVEL: ${summary.summary.overallRiskLevel}
        </div>

        <div class="metrics">
            <div class="metric-card">
                <h3>Vulnerabilities</h3>
                <div class="metric-value">${summary.summary.totalVulnerabilities}</div>
                <small>Security Audit</small>
            </div>
            <div class="metric-card">
                <h3>Exploits</h3>
                <div class="metric-value">${summary.summary.totalExploits}</div>
                <small>Penetration Testing</small>
            </div>
            <div class="metric-card">
                <h3>Test Failures</h3>
                <div class="metric-value">${summary.summary.totalPropertyTestFailures}</div>
                <small>Property Tests</small>
            </div>
            <div class="metric-card">
                <h3>Compliance</h3>
                <div class="metric-value">${summary.summary.complianceScore}%</div>
                <small>Overall Score</small>
            </div>
        </div>

        <div class="status">
            <h3>Test Execution Status</h3>
            <div class="status-item ${summary.summary.auditCompleted ? 'status-pass' : 'status-fail'}">
                <span>Security Audit</span>
                <span>${summary.summary.auditCompleted ? '✅ Completed' : '❌ Failed'}</span>
            </div>
            <div class="status-item ${summary.summary.pentestCompleted ? 'status-pass' : 'status-fail'}">
                <span>Penetration Testing</span>
                <span>${summary.summary.pentestCompleted ? '✅ Completed' : '❌ Failed'}</span>
            </div>
            <div class="status-item ${summary.summary.propertyTestsCompleted ? 'status-pass' : 'status-fail'}">
                <span>Property-Based Tests</span>
                <span>${summary.summary.propertyTestsCompleted ? '✅ Completed' : '❌ Failed'}</span>
            </div>
        </div>

        <div class="recommendations">
            <h3>💡 Key Recommendations</h3>
            ${this.generateKeyRecommendations(summary)}
        </div>
    </div>
</body>
</html>`;
  }

  /**
   * Generate key recommendations based on results
   */
  generateKeyRecommendations(summary) {
    const recommendations = [];
    
    if (summary.summary.totalVulnerabilities > 0) {
      recommendations.push('🔒 Address identified security vulnerabilities immediately');
    }
    
    if (summary.summary.totalExploits > 0) {
      recommendations.push('🎯 Fix exploitable security weaknesses found in penetration testing');
    }
    
    if (summary.summary.totalPropertyTestFailures > 0) {
      recommendations.push('🧪 Review and fix property-based test failures');
    }
    
    if (summary.summary.overallRiskLevel === 'HIGH' || summary.summary.overallRiskLevel === 'CRITICAL') {
      recommendations.push('⚠️ Implement emergency security measures');
      recommendations.push('👥 Consider engaging security experts');
    }
    
    recommendations.push('📚 Implement regular security training for development team');
    recommendations.push('🔄 Establish continuous security testing in CI/CD pipeline');
    recommendations.push('📊 Schedule regular security assessments');
    
    return '<ul>' + recommendations.map(rec => `<li>${rec}</li>`).join('') + '</ul>';
  }

  /**
   * Provide actionable recommendations
   */
  async provideRecommendations() {
    console.log('\n💡 Security Validation Recommendations');
    console.log('=' .repeat(50));
    
    const summary = this.results.summary;
    
    if (summary.summary.overallRiskLevel === 'LOW' && 
        summary.summary.totalVulnerabilities === 0 && 
        summary.summary.totalExploits === 0) {
      
      console.log('🎉 Excellent! No critical security issues found.');
      console.log('✅ Continue with current security practices');
      console.log('📅 Schedule regular security assessments');
      
    } else {
      console.log('⚠️  Security issues identified. Immediate action required:');
      
      if (summary.summary.totalVulnerabilities > 0) {
        console.log(`🔒 Fix ${summary.summary.totalVulnerabilities} security vulnerabilities`);
      }
      
      if (summary.summary.totalExploits > 0) {
        console.log(`🎯 Address ${summary.summary.totalExploits} exploitable weaknesses`);
      }
      
      if (summary.summary.totalPropertyTestFailures > 0) {
        console.log(`🧪 Fix ${summary.summary.totalPropertyTestFailures} property test failures`);
      }
      
      console.log('\n📋 Next Steps:');
      console.log('1. Review detailed reports in:', this.config.outputDir);
      console.log('2. Prioritize critical and high-severity issues');
      console.log('3. Implement fixes and re-run validation');
      console.log('4. Establish continuous security monitoring');
    }
    
    console.log('\n📁 All reports and logs saved to:', this.config.outputDir);
  }

  /**
   * Make HTTP request helper
   */
  async makeRequest(path) {
    const url = `${this.baseUrl}${path}`;
    
    try {
      const response = await fetch(url);
      return response;
    } catch (error) {
      throw new Error(`Request to ${url} failed: ${error.message}`);
    }
  }
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const config = {};
  
  // Parse command line arguments
  for (let i = 0; i < args.length; i++) {
    const arg = args[i];
    
    switch (arg) {
      case '--base-url':
        config.baseUrl = args[++i];
        break;
      case '--no-audit':
        config.runAudit = false;
        break;
      case '--no-pentest':
        config.runPentest = false;
        break;
      case '--no-property-tests':
        config.runPropertyTests = false;
        break;
      case '--no-reports':
        config.generateReports = false;
        break;
      case '--verbose':
        config.verbose = true;
        break;
      case '--output-dir':
        config.outputDir = args[++i];
        break;
      case '--help':
        console.log(`
Security Validation Runner for Riya Collections

Usage: node run-security-validation.js [options]

Options:
  --base-url <url>        Base URL of the application (default: http://localhost:5000)
  --no-audit             Skip security audit
  --no-pentest           Skip penetration testing
  --no-property-tests    Skip property-based tests
  --no-reports           Skip report generation
  --verbose              Enable verbose output
  --output-dir <dir>     Output directory for reports
  --help                 Show this help message

Examples:
  node run-security-validation.js
  node run-security-validation.js --base-url https://api.riyacollections.com
  node run-security-validation.js --verbose --output-dir ./security-reports
        `);
        process.exit(0);
        break;
    }
  }
  
  // Run security validation
  const runner = new SecurityValidationRunner(config);
  
  runner.runCompleteValidation()
    .then((results) => {
      console.log('\n🎉 Security validation suite completed successfully!');
      
      // Exit with appropriate code based on results
      const hasIssues = results.summary && (
        results.summary.summary.totalVulnerabilities > 0 ||
        results.summary.summary.totalExploits > 0 ||
        results.summary.summary.totalPropertyTestFailures > 0
      );
      
      process.exit(hasIssues ? 1 : 0);
    })
    .catch((error) => {
      console.error('\n❌ Security validation suite failed:', error.message);
      process.exit(2);
    });
}

// Export for use as module
module.exports = SecurityValidationRunner;