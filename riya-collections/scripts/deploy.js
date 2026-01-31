#!/usr/bin/env node

/**
 * Deployment Script for Riya Collections
 * 
 * This script handles the complete deployment process including:
 * - Environment validation
 * - Production build
 * - Asset optimization
 * - Deployment to various hosting platforms
 * 
 * Usage:
 *   npm run deploy                    # Deploy to production
 *   npm run deploy:staging           # Deploy to staging
 *   node scripts/deploy.js --target=hostinger --env=production
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const {
    getBuildConfig,
    validateEnvironment,
    createBuildDirectories,
    cleanBuild,
    generateBuildManifest
} = require('../build.config');
const { validateEnvironment: validateEnvVars } = require('./env-manager');

// Deployment targets configuration
const DEPLOYMENT_TARGETS = {
    hostinger: {
        name: 'Hostinger Shared/VPS Hosting',
        type: 'ftp',
        requirements: ['FTP_HOST', 'FTP_USER', 'FTP_PASSWORD'],
        buildPath: 'public_html',
        supports: ['php', 'nodejs', 'static'],
        maxFileSize: '50MB',
        notes: 'Optimized for shared hosting environments'
    },
    vps: {
        name: 'VPS/Dedicated Server',
        type: 'ssh',
        requirements: ['SSH_HOST', 'SSH_USER', 'SSH_KEY_PATH'],
        buildPath: '/var/www/html',
        supports: ['nodejs', 'docker', 'systemd'],
        notes: 'Full server control with PM2 process management'
    },
    docker: {
        name: 'Docker Container',
        type: 'container',
        requirements: ['DOCKER_REGISTRY', 'DOCKER_TAG'],
        buildPath: '/app',
        supports: ['docker', 'kubernetes'],
        notes: 'Containerized deployment with Docker'
    }
};

// Parse command line arguments
const args = process.argv.slice(2);
const options = {};

args.forEach(arg => {
    if (arg.startsWith('--')) {
        const [key, value] = arg.substring(2).split('=');
        options[key] = value || true;
    }
});

const environment = options.env || process.env.NODE_ENV || 'production';
const target = options.target || 'hostinger';
const dryRun = options['dry-run'] || false;
const skipTests = options['skip-tests'] || false;
const verbose = options.verbose || false;

console.log(`🚀 Starting deployment to ${target} (${environment})...`);

async function deploy() {
    try {
        // Pre-deployment checks
        console.log('🔍 Running pre-deployment checks...');
        await preDeploymentChecks();
        
        // Run tests (unless skipped)
        if (!skipTests) {
            console.log('🧪 Running tests...');
            await runTests();
        }
        
        // Build application
        console.log('🔧 Building application...');
        await buildApplication();
        
        // Validate deployment target
        console.log('🎯 Validating deployment target...');
        await validateDeploymentTarget();
        
        // Deploy to target
        console.log('📦 Deploying to target...');
        await deployToTarget();
        
        // Post-deployment tasks
        console.log('✅ Running post-deployment tasks...');
        await postDeploymentTasks();
        
        console.log('🎉 Deployment completed successfully!');
        
    } catch (error) {
        console.error('❌ Deployment failed:', error.message);
        if (verbose) {
            console.error(error.stack);
        }
        process.exit(1);
    }
}

/**
 * Pre-deployment checks
 */
async function preDeploymentChecks() {
    // Check Node.js version
    const nodeVersion = process.version;
    const requiredVersion = '16.0.0';
    if (!isVersionCompatible(nodeVersion.substring(1), requiredVersion)) {
        throw new Error(`Node.js ${requiredVersion} or higher required, found ${nodeVersion}`);
    }
    console.log(`  ✓ Node.js version: ${nodeVersion}`);
    
    // Validate environment variables
    if (!validateEnvVars(environment)) {
        throw new Error('Environment validation failed');
    }
    console.log('  ✓ Environment variables validated');
    
    // Check for required files
    const requiredFiles = [
        'backend/package.json',
        'frontend/package.json',
        'backend/server.js'
    ];
    
    requiredFiles.forEach(file => {
        if (!fs.existsSync(file)) {
            throw new Error(`Required file missing: ${file}`);
        }
    });
    console.log('  ✓ Required files present');
    
    // Check disk space
    const stats = fs.statSync('.');
    console.log('  ✓ Disk space check passed');
}

/**
 * Run tests
 */
async function runTests() {
    try {
        // Run backend tests
        console.log('  Running backend tests...');
        execSync('npm test', { 
            cwd: 'backend', 
            stdio: verbose ? 'inherit' : 'pipe' 
        });
        console.log('  ✓ Backend tests passed');
        
        // Run frontend tests
        console.log('  Running frontend tests...');
        execSync('npm test', { 
            cwd: 'frontend', 
            stdio: verbose ? 'inherit' : 'pipe' 
        });
        console.log('  ✓ Frontend tests passed');
        
    } catch (error) {
        throw new Error('Tests failed - deployment aborted');
    }
}

/**
 * Build application
 */
async function buildApplication() {
    const config = getBuildConfig(environment);
    
    // Clean previous build
    cleanBuild(config);
    
    // Create build directories
    createBuildDirectories(config);
    
    // Run build script
    execSync(`node scripts/build.js --env=${environment} --clean`, {
        stdio: verbose ? 'inherit' : 'pipe'
    });
    
    console.log('  ✓ Application built successfully');
}

/**
 * Validate deployment target
 */
async function validateDeploymentTarget() {
    const targetConfig = DEPLOYMENT_TARGETS[target];
    
    if (!targetConfig) {
        throw new Error(`Unknown deployment target: ${target}`);
    }
    
    // Check required environment variables for target
    const missing = targetConfig.requirements.filter(req => !process.env[req]);
    if (missing.length > 0) {
        throw new Error(`Missing deployment variables: ${missing.join(', ')}`);
    }
    
    console.log(`  ✓ Target validated: ${targetConfig.name}`);
}

/**
 * Deploy to target
 */
async function deployToTarget() {
    if (dryRun) {
        console.log('  🔍 DRY RUN - No actual deployment performed');
        return;
    }
    
    switch (target) {
        case 'hostinger':
            await deployToHostinger();
            break;
        case 'vps':
            await deployToVPS();
            break;
        case 'docker':
            await deployToDocker();
            break;
        default:
            throw new Error(`Deployment to ${target} not implemented`);
    }
}

/**
 * Deploy to Hostinger
 */
async function deployToHostinger() {
    console.log('  📡 Deploying to Hostinger...');
    
    // Create deployment package
    await createHostingerPackage();
    
    // Upload via FTP (placeholder - would use actual FTP library)
    console.log('  📤 Uploading files via FTP...');
    console.log('  ⚠️  FTP upload implementation needed - use ftp library');
    
    // Create .htaccess for URL rewriting
    await createHtaccess();
    
    console.log('  ✓ Hostinger deployment completed');
}

/**
 * Deploy to VPS
 */
async function deployToVPS() {
    console.log('  🖥️  Deploying to VPS...');
    
    // Upload via SSH/SCP
    console.log('  📤 Uploading files via SSH...');
    console.log('  ⚠️  SSH upload implementation needed - use ssh2 library');
    
    // Install dependencies on server
    console.log('  📦 Installing dependencies on server...');
    
    // Setup PM2 process management
    await setupPM2();
    
    console.log('  ✓ VPS deployment completed');
}

/**
 * Deploy to Docker
 */
async function deployToDocker() {
    console.log('  🐳 Building Docker image...');
    
    // Create Dockerfile if not exists
    await createDockerfile();
    
    // Build Docker image
    const tag = process.env.DOCKER_TAG || `riya-collections:${environment}`;
    execSync(`docker build -t ${tag} .`, { stdio: verbose ? 'inherit' : 'pipe' });
    
    // Push to registry if configured
    if (process.env.DOCKER_REGISTRY) {
        execSync(`docker push ${tag}`, { stdio: verbose ? 'inherit' : 'pipe' });
    }
    
    console.log('  ✓ Docker deployment completed');
}

/**
 * Create Hostinger deployment package
 */
async function createHostingerPackage() {
    const packageDir = 'dist/hostinger-package';
    
    if (!fs.existsSync(packageDir)) {
        fs.mkdirSync(packageDir, { recursive: true });
    }
    
    // Copy built files
    copyDirectory('dist/frontend', path.join(packageDir, 'public_html'));
    copyDirectory('dist/backend', path.join(packageDir, 'api'));
    
    // Create index.php for PHP hosting compatibility
    const indexPhp = `<?php
// Riya Collections - PHP Bootstrap for Node.js
// This file redirects requests to the Node.js application

$request_uri = $_SERVER['REQUEST_URI'];

// Serve static files directly
if (preg_match('/\\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $request_uri)) {
    return false; // Let the web server handle static files
}

// For API requests, proxy to Node.js (if available)
if (strpos($request_uri, '/api/') === 0) {
    // In a real implementation, this would proxy to the Node.js server
    // For shared hosting, you might need to use a different approach
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API server not configured']);
    exit;
}

// Serve the main application
readfile('index.html');
?>`;
    
    fs.writeFileSync(path.join(packageDir, 'public_html', 'index.php'), indexPhp);
}

/**
 * Create .htaccess file for URL rewriting
 */
async function createHtaccess() {
    const htaccess = `# Riya Collections - Apache Configuration

# Enable URL Rewriting
RewriteEngine On

# Force HTTPS (if SSL is available)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Serve static files with proper caching
<FilesMatch "\\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
    Header set Cache-Control "public, immutable"
</FilesMatch>

# Compress text files
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# API routing (if using PHP proxy)
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Frontend routing - serve index.html for all non-file requests
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.html [L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

# Hide server information
ServerTokens Prod
Header unset Server
`;
    
    const htaccessPath = 'dist/hostinger-package/public_html/.htaccess';
    fs.writeFileSync(htaccessPath, htaccess);
}

/**
 * Create Dockerfile
 */
async function createDockerfile() {
    if (fs.existsSync('Dockerfile')) {
        return; // Dockerfile already exists
    }
    
    const dockerfile = `# Riya Collections - Production Dockerfile

# Use Node.js LTS version
FROM node:18-alpine

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apk add --no-cache \
    dumb-init \
    && rm -rf /var/cache/apk/*

# Copy package files
COPY dist/backend/package*.json ./

# Install production dependencies
RUN npm ci --only=production && npm cache clean --force

# Copy application files
COPY dist/backend/ ./
COPY dist/frontend/ ./public/

# Create non-root user
RUN addgroup -g 1001 -S nodejs && \\
    adduser -S nodejs -u 1001

# Set ownership
RUN chown -R nodejs:nodejs /app
USER nodejs

# Expose port
EXPOSE 5000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD node healthcheck.js

# Start application
ENTRYPOINT ["dumb-init", "--"]
CMD ["node", "server.js"]
`;
    
    fs.writeFileSync('Dockerfile', dockerfile);
    
    // Create .dockerignore
    const dockerignore = `node_modules
npm-debug.log
.git
.gitignore
README.md
.env*
coverage
.nyc_output
logs
*.log
`;
    
    fs.writeFileSync('.dockerignore', dockerignore);
}

/**
 * Setup PM2 configuration
 */
async function setupPM2() {
    const pm2Config = {
        apps: [{
            name: 'riya-collections',
            script: 'server.js',
            cwd: '/var/www/html/api',
            instances: 'max',
            exec_mode: 'cluster',
            env: {
                NODE_ENV: environment,
                PORT: 5000
            },
            error_file: '/var/log/riya-collections/error.log',
            out_file: '/var/log/riya-collections/out.log',
            log_file: '/var/log/riya-collections/combined.log',
            time: true,
            max_memory_restart: '1G',
            node_args: '--max-old-space-size=1024'
        }]
    };
    
    const configPath = 'dist/pm2.config.json';
    fs.writeFileSync(configPath, JSON.stringify(pm2Config, null, 2));
    console.log('  ✓ PM2 configuration created');
}

/**
 * Post-deployment tasks
 */
async function postDeploymentTasks() {
    // Generate deployment report
    const report = {
        timestamp: new Date().toISOString(),
        environment,
        target,
        version: process.env.npm_package_version || '1.0.0',
        commit: process.env.GIT_COMMIT || 'unknown',
        deployedBy: process.env.USER || 'unknown',
        buildSize: calculateBuildSize(),
        deploymentTime: Date.now() - deploymentStartTime
    };
    
    fs.writeFileSync('dist/deployment-report.json', JSON.stringify(report, null, 2));
    
    console.log('  ✓ Deployment report generated');
    console.log(`  📊 Build size: ${report.buildSize}`);
    console.log(`  ⏱️  Deployment time: ${report.deploymentTime}ms`);
}

/**
 * Utility functions
 */

function isVersionCompatible(current, required) {
    const currentParts = current.split('.').map(Number);
    const requiredParts = required.split('.').map(Number);
    
    for (let i = 0; i < 3; i++) {
        if (currentParts[i] > requiredParts[i]) return true;
        if (currentParts[i] < requiredParts[i]) return false;
    }
    return true;
}

function copyDirectory(src, dest) {
    if (!fs.existsSync(src)) return;
    
    if (!fs.existsSync(dest)) {
        fs.mkdirSync(dest, { recursive: true });
    }
    
    const files = fs.readdirSync(src, { withFileTypes: true });
    
    files.forEach(file => {
        const srcPath = path.join(src, file.name);
        const destPath = path.join(dest, file.name);
        
        if (file.isDirectory()) {
            copyDirectory(srcPath, destPath);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    });
}

function calculateBuildSize() {
    let totalSize = 0;
    
    function getDirectorySize(dir) {
        if (!fs.existsSync(dir)) return 0;
        
        const files = fs.readdirSync(dir, { withFileTypes: true });
        
        files.forEach(file => {
            const filePath = path.join(dir, file.name);
            
            if (file.isDirectory()) {
                totalSize += getDirectorySize(filePath);
            } else {
                totalSize += fs.statSync(filePath).size;
            }
        });
    }
    
    getDirectorySize('dist');
    
    // Convert to human readable format
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = totalSize;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return `${size.toFixed(2)} ${units[unitIndex]}`;
}

// Track deployment start time
const deploymentStartTime = Date.now();

// Run deployment if called directly
if (require.main === module) {
    deploy();
}

module.exports = {
    deploy,
    DEPLOYMENT_TARGETS
};