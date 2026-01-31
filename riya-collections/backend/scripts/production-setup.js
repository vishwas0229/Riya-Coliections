#!/usr/bin/env node

/**
 * Production Setup and Security Configuration Script
 * 
 * This script helps set up the Riya Collections backend for production deployment.
 * It performs security checks, configuration validation, and system initialization.
 * 
 * Requirements: 14.1, 14.4 (Production deployment and security)
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execSync } = require('child_process');

// Colors for console output
const colors = {
  reset: '\x1b[0m',
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  magenta: '\x1b[35m',
  cyan: '\x1b[36m'
};

/**
 * Print colored console messages
 */
const print = {
  info: (msg) => console.log(`${colors.blue}ℹ ${msg}${colors.reset}`),
  success: (msg) => console.log(`${colors.green}✅ ${msg}${colors.reset}`),
  warning: (msg) => console.log(`${colors.yellow}⚠️  ${msg}${colors.reset}`),
  error: (msg) => console.log(`${colors.red}❌ ${msg}${colors.reset}`),
  header: (msg) => console.log(`\n${colors.cyan}🚀 ${msg}${colors.reset}\n`)
};

/**
 * Generate secure random string
 */
const generateSecureSecret = (length = 32) => {
  return crypto.randomBytes(length).toString('hex');
};

/**
 * Check if required system dependencies are installed
 */
const checkSystemDependencies = () => {
  print.header('Checking System Dependencies');
  
  const dependencies = [
    { name: 'Node.js', command: 'node --version', required: true },
    { name: 'npm', command: 'npm --version', required: true },
    { name: 'MySQL', command: 'mysql --version', required: true },
    { name: 'mysqldump', command: 'mysqldump --version', required: true },
    { name: 'tar', command: 'tar --version', required: false },
    { name: 'gzip', command: 'gzip --version', required: false }
  ];
  
  let allRequired = true;
  
  dependencies.forEach(dep => {
    try {
      const version = execSync(dep.command, { encoding: 'utf8', stdio: 'pipe' });
      print.success(`${dep.name}: ${version.split('\n')[0]}`);
    } catch (error) {
      if (dep.required) {
        print.error(`${dep.name}: Not found (REQUIRED)`);
        allRequired = false;
      } else {
        print.warning(`${dep.name}: Not found (optional)`);
      }
    }
  });
  
  if (!allRequired) {
    print.error('Missing required dependencies. Please install them before proceeding.');
    process.exit(1);
  }
  
  print.success('All required dependencies are installed');
};

/**
 * Create production environment file with secure defaults
 */
const createProductionEnv = () => {
  print.header('Creating Production Environment Configuration');
  
  const envPath = path.join(process.cwd(), '.env.production');
  const examplePath = path.join(process.cwd(), '.env.production');
  
  if (fs.existsSync(envPath)) {
    print.warning('Production environment file already exists');
    return;
  }
  
  if (!fs.existsSync(examplePath)) {
    print.error('Production environment template not found');
    return;
  }
  
  // Read template and replace placeholders with secure values
  let envContent = fs.readFileSync(examplePath, 'utf8');
  
  // Generate secure secrets
  const secrets = {
    JWT_SECRET: generateSecureSecret(64),
    JWT_REFRESH_SECRET: generateSecureSecret(64),
    SESSION_SECRET: generateSecureSecret(64),
    BACKUP_ENCRYPTION_KEY: generateSecureSecret(32)
  };
  
  // Replace placeholders
  Object.entries(secrets).forEach(([key, value]) => {
    const placeholder = `REPLACE_WITH_STRONG_${key.replace('_SECRET', '_SECRET').replace('_KEY', '_KEY')}`;
    envContent = envContent.replace(new RegExp(placeholder, 'g'), value);
  });
  
  // Write production environment file
  fs.writeFileSync(envPath, envContent);
  
  print.success('Production environment file created');
  print.warning('Please review and update the production configuration with your actual values');
  
  // Display generated secrets
  print.info('Generated secure secrets:');
  Object.entries(secrets).forEach(([key, value]) => {
    console.log(`  ${key}: ${value.substring(0, 16)}...`);
  });
};

/**
 * Validate production configuration
 */
const validateProductionConfig = () => {
  print.header('Validating Production Configuration');
  
  const envPath = path.join(process.cwd(), '.env.production');
  
  if (!fs.existsSync(envPath)) {
    print.error('Production environment file not found');
    return false;
  }
  
  // Load environment variables
  const envContent = fs.readFileSync(envPath, 'utf8');
  const envVars = {};
  
  envContent.split('\n').forEach(line => {
    const match = line.match(/^([^#][^=]+)=(.*)$/);
    if (match) {
      envVars[match[1]] = match[2];
    }
  });
  
  // Required production variables
  const required = [
    'NODE_ENV',
    'JWT_SECRET',
    'JWT_REFRESH_SECRET',
    'SESSION_SECRET',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'FRONTEND_URL',
    'BASE_URL'
  ];
  
  // Security-critical variables
  const securityCritical = [
    'SSL_ENABLED',
    'SSL_CERT_PATH',
    'SSL_KEY_PATH',
    'RAZORPAY_KEY_ID',
    'RAZORPAY_KEY_SECRET'
  ];
  
  let isValid = true;
  
  // Check required variables
  required.forEach(key => {
    if (!envVars[key] || envVars[key].includes('REPLACE_WITH')) {
      print.error(`Missing or placeholder value for: ${key}`);
      isValid = false;
    } else {
      print.success(`${key}: Configured`);
    }
  });
  
  // Check security-critical variables
  securityCritical.forEach(key => {
    if (!envVars[key] || envVars[key].includes('REPLACE_WITH')) {
      print.warning(`Security-critical variable needs attention: ${key}`);
    }
  });
  
  // Validate secret strength
  const secrets = ['JWT_SECRET', 'JWT_REFRESH_SECRET', 'SESSION_SECRET'];
  secrets.forEach(key => {
    if (envVars[key] && envVars[key].length < 32) {
      print.warning(`${key} should be at least 32 characters long`);
    }
  });
  
  return isValid;
};

/**
 * Set up directory structure
 */
const setupDirectories = () => {
  print.header('Setting Up Directory Structure');
  
  const directories = [
    'logs',
    'backups',
    'uploads',
    'uploads/products',
    'uploads/temp'
  ];
  
  directories.forEach(dir => {
    const dirPath = path.join(process.cwd(), dir);
    if (!fs.existsSync(dirPath)) {
      fs.mkdirSync(dirPath, { recursive: true });
      print.success(`Created directory: ${dir}`);
    } else {
      print.info(`Directory exists: ${dir}`);
    }
  });
  
  // Set appropriate permissions (Unix-like systems)
  if (process.platform !== 'win32') {
    try {
      execSync('chmod 755 uploads uploads/products uploads/temp');
      execSync('chmod 700 logs backups');
      print.success('Set directory permissions');
    } catch (error) {
      print.warning('Could not set directory permissions (may require manual setup)');
    }
  }
};

/**
 * Install and verify npm dependencies
 */
const setupDependencies = () => {
  print.header('Setting Up Dependencies');
  
  try {
    print.info('Installing production dependencies...');
    execSync('npm ci --only=production', { stdio: 'inherit' });
    print.success('Production dependencies installed');
  } catch (error) {
    print.error('Failed to install dependencies');
    process.exit(1);
  }
  
  // Verify critical dependencies
  const criticalDeps = [
    'express',
    'mysql2',
    'bcrypt',
    'jsonwebtoken',
    'helmet',
    'cors',
    'express-rate-limit',
    'multer',
    'sharp',
    'nodemailer',
    'razorpay'
  ];
  
  criticalDeps.forEach(dep => {
    try {
      require.resolve(dep);
      print.success(`${dep}: Available`);
    } catch (error) {
      print.error(`${dep}: Missing`);
    }
  });
};

/**
 * Run database migrations
 */
const runDatabaseMigrations = () => {
  print.header('Running Database Migrations');
  
  try {
    const migrationScript = path.join(process.cwd(), 'migrations', 'run_migrations.js');
    
    if (fs.existsSync(migrationScript)) {
      print.info('Running database migrations...');
      execSync(`node ${migrationScript}`, { stdio: 'inherit' });
      print.success('Database migrations completed');
    } else {
      print.warning('Migration script not found - please run migrations manually');
    }
  } catch (error) {
    print.error('Database migration failed');
    print.info('Please check database connection and run migrations manually');
  }
};

/**
 * Perform security checks
 */
const performSecurityChecks = () => {
  print.header('Performing Security Checks');
  
  const checks = [
    {
      name: 'Environment file permissions',
      check: () => {
        const envPath = path.join(process.cwd(), '.env.production');
        if (fs.existsSync(envPath)) {
          const stats = fs.statSync(envPath);
          return (stats.mode & parseInt('077', 8)) === 0;
        }
        return false;
      },
      fix: 'Run: chmod 600 .env.production'
    },
    {
      name: 'SSL configuration',
      check: () => {
        const envPath = path.join(process.cwd(), '.env.production');
        if (fs.existsSync(envPath)) {
          const content = fs.readFileSync(envPath, 'utf8');
          return content.includes('SSL_ENABLED=true');
        }
        return false;
      },
      fix: 'Enable SSL in production environment'
    },
    {
      name: 'Strong JWT secrets',
      check: () => {
        const envPath = path.join(process.cwd(), '.env.production');
        if (fs.existsSync(envPath)) {
          const content = fs.readFileSync(envPath, 'utf8');
          const jwtMatch = content.match(/JWT_SECRET=(.+)/);
          return jwtMatch && jwtMatch[1].length >= 32;
        }
        return false;
      },
      fix: 'Use strong JWT secrets (32+ characters)'
    }
  ];
  
  checks.forEach(check => {
    if (check.check()) {
      print.success(check.name);
    } else {
      print.warning(`${check.name}: ${check.fix}`);
    }
  });
};

/**
 * Generate deployment summary
 */
const generateDeploymentSummary = () => {
  print.header('Deployment Summary');
  
  const summary = {
    timestamp: new Date().toISOString(),
    nodeVersion: process.version,
    platform: process.platform,
    architecture: process.arch,
    environment: 'production'
  };
  
  console.log(JSON.stringify(summary, null, 2));
  
  print.info('Production setup completed!');
  print.info('Next steps:');
  console.log('  1. Review and update .env.production with your actual values');
  console.log('  2. Configure SSL certificates');
  console.log('  3. Set up database connection');
  console.log('  4. Configure email service');
  console.log('  5. Set up Razorpay payment gateway');
  console.log('  6. Test the application');
  console.log('  7. Set up monitoring and backups');
};

/**
 * Main setup function
 */
const main = () => {
  console.log(`${colors.magenta}
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║           Riya Collections Production Setup                  ║
║                                                              ║
║  This script will configure your application for production  ║
║  deployment with security best practices and optimizations.  ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
${colors.reset}`);
  
  try {
    checkSystemDependencies();
    setupDirectories();
    createProductionEnv();
    
    if (!validateProductionConfig()) {
      print.warning('Configuration validation failed - please review the issues above');
    }
    
    setupDependencies();
    runDatabaseMigrations();
    performSecurityChecks();
    generateDeploymentSummary();
    
  } catch (error) {
    print.error(`Setup failed: ${error.message}`);
    process.exit(1);
  }
};

// Run setup if called directly
if (require.main === module) {
  main();
}

module.exports = {
  checkSystemDependencies,
  createProductionEnv,
  validateProductionConfig,
  setupDirectories,
  setupDependencies,
  runDatabaseMigrations,
  performSecurityChecks,
  generateDeploymentSummary
};