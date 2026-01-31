#!/usr/bin/env node

/**
 * Production Build Script for Riya Collections
 * 
 * Usage:
 *   npm run build                    # Production build
 *   npm run build:staging           # Staging build
 *   npm run build:dev              # Development build
 *   node scripts/build.js --env=production --clean
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

// Parse command line arguments
const args = process.argv.slice(2);
const options = {};

args.forEach(arg => {
    if (arg.startsWith('--')) {
        const [key, value] = arg.substring(2).split('=');
        options[key] = value || true;
    }
});

// Determine environment
const environment = options.env || process.env.NODE_ENV || 'production';
const shouldClean = options.clean || false;
const verbose = options.verbose || false;

console.log(`🚀 Starting ${environment} build...`);

try {
    // Get build configuration
    const config = getBuildConfig(environment);
    
    // Validate environment variables
    if (environment !== 'development') {
        validateEnvironment(environment);
    }
    
    // Clean build if requested
    if (shouldClean) {
        console.log('🧹 Cleaning build directories...');
        cleanBuild(config);
    }
    
    // Create build directories
    console.log('📁 Creating build directories...');
    createBuildDirectories(config);
    
    // Build backend
    console.log('🔧 Building backend...');
    buildBackend(config);
    
    // Build frontend
    console.log('🎨 Building frontend...');
    buildFrontend(config);
    
    // Optimize assets
    if (config.env.MINIFY || config.env.COMPRESS) {
        console.log('⚡ Optimizing assets...');
        optimizeAssets(config);
    }
    
    // Generate build manifest
    console.log('📋 Generating build manifest...');
    const manifest = generateBuildManifest(config);
    
    console.log('✅ Build completed successfully!');
    console.log(`📦 Build output: ${config.paths.dist.root}`);
    console.log(`🕒 Build time: ${manifest.buildTime}`);
    
} catch (error) {
    console.error('❌ Build failed:', error.message);
    if (verbose) {
        console.error(error.stack);
    }
    process.exit(1);
}

/**
 * Build backend components
 */
function buildBackend(config) {
    const backendSrc = config.paths.src.backend;
    const backendDist = config.paths.dist.backend;
    
    // Copy backend files (excluding node_modules and temp files)
    const excludePatterns = [
        'node_modules',
        'logs',
        'uploads',
        '.env*',
        '*.log',
        'coverage',
        '.nyc_output'
    ];
    
    copyDirectory(backendSrc, backendDist, excludePatterns);
    
    // Copy package.json and package-lock.json
    copyFile(path.join(backendSrc, 'package.json'), path.join(backendDist, 'package.json'));
    
    if (fs.existsSync(path.join(backendSrc, 'package-lock.json'))) {
        copyFile(path.join(backendSrc, 'package-lock.json'), path.join(backendDist, 'package-lock.json'));
    }
    
    // Create production environment file
    createProductionEnv(config, backendDist);
    
    console.log('  ✓ Backend files copied');
}

/**
 * Build frontend components
 */
function buildFrontend(config) {
    const frontendSrc = config.paths.src.frontend;
    const frontendDist = config.paths.dist.frontend;
    
    // Copy HTML files
    copyHtmlFiles(config);
    
    // Process CSS files
    processCssFiles(config);
    
    // Process JavaScript files
    processJsFiles(config);
    
    // Copy and optimize assets
    copyAssets(config);
    
    console.log('  ✓ Frontend files processed');
}

/**
 * Copy HTML files and inject build-specific configurations
 */
function copyHtmlFiles(config) {
    const htmlFiles = [
        'frontend/index.html',
        'frontend/pages/*.html'
    ];
    
    // Copy main index.html
    if (fs.existsSync('frontend/index.html')) {
        let htmlContent = fs.readFileSync('frontend/index.html', 'utf8');
        
        // Update asset paths for production
        if (config.env.MINIFY) {
            htmlContent = htmlContent
                .replace(/src\/css\/style\.css/g, 'css/style.min.css')
                .replace(/src\/js\/([^"']+)\.js/g, 'js/$1.min.js');
        }
        
        // Add cache busting if enabled
        if (config.cacheBusting.enabled) {
            const timestamp = Date.now();
            htmlContent = htmlContent
                .replace(/\.css/g, `.css?v=${timestamp}`)
                .replace(/\.js/g, `.js?v=${timestamp}`);
        }
        
        fs.writeFileSync(path.join(config.paths.dist.frontend, 'index.html'), htmlContent);
    }
    
    // Copy pages directory
    if (fs.existsSync('frontend/pages')) {
        copyDirectory('frontend/pages', path.join(config.paths.dist.frontend, 'pages'));
    }
}

/**
 * Process and minify CSS files
 */
function processCssFiles(config) {
    const cssDir = config.paths.src.css;
    const outputDir = config.paths.dist.css;
    
    if (!fs.existsSync(cssDir)) {
        return;
    }
    
    const cssFiles = fs.readdirSync(cssDir).filter(file => file.endsWith('.css'));
    
    cssFiles.forEach(file => {
        const inputPath = path.join(cssDir, file);
        const outputName = config.env.MINIFY ? file.replace('.css', '.min.css') : file;
        const outputPath = path.join(outputDir, outputName);
        
        let cssContent = fs.readFileSync(inputPath, 'utf8');
        
        if (config.env.MINIFY) {
            // Basic CSS minification
            cssContent = minifyCss(cssContent);
        }
        
        fs.writeFileSync(outputPath, cssContent);
    });
    
    console.log('  ✓ CSS files processed');
}

/**
 * Process and minify JavaScript files
 */
function processJsFiles(config) {
    const jsDir = config.paths.src.js;
    const outputDir = config.paths.dist.js;
    
    if (!fs.existsSync(jsDir)) {
        return;
    }
    
    // Update config.js with environment-specific settings
    updateJsConfig(config);
    
    const jsFiles = fs.readdirSync(jsDir).filter(file => file.endsWith('.js'));
    
    jsFiles.forEach(file => {
        const inputPath = path.join(jsDir, file);
        const outputName = config.env.MINIFY ? file.replace('.js', '.min.js') : file;
        const outputPath = path.join(outputDir, outputName);
        
        let jsContent = fs.readFileSync(inputPath, 'utf8');
        
        // Replace environment variables
        jsContent = replaceEnvVariables(jsContent, config);
        
        if (config.env.MINIFY) {
            // Basic JS minification (remove comments and extra whitespace)
            jsContent = minifyJs(jsContent);
        }
        
        fs.writeFileSync(outputPath, jsContent);
    });
    
    console.log('  ✓ JavaScript files processed');
}

/**
 * Update JavaScript config with environment-specific settings
 */
function updateJsConfig(config) {
    const configPath = path.join(config.paths.src.js, 'config.js');
    const outputPath = path.join(config.paths.dist.js, config.env.MINIFY ? 'config.min.js' : 'config.js');
    
    if (!fs.existsSync(configPath)) {
        return;
    }
    
    let configContent = fs.readFileSync(configPath, 'utf8');
    
    // Replace API base URL
    const apiUrl = process.env.API_URL || config.env.API_URL || 'http://localhost:5000';
    configContent = configContent.replace(
        /BASE_URL:\s*['"][^'"]*['"]/,
        `BASE_URL: '${apiUrl}'`
    );
    
    // Add environment flag
    configContent = configContent.replace(
        /const CONFIG = {/,
        `const CONFIG = {\n    ENVIRONMENT: '${config.target}',\n    DEBUG: ${config.env.DEBUG},`
    );
    
    if (config.env.MINIFY) {
        configContent = minifyJs(configContent);
    }
    
    fs.writeFileSync(outputPath, configContent);
}

/**
 * Copy and optimize assets
 */
function copyAssets(config) {
    const assetsDir = config.paths.src.assets;
    const outputDir = config.paths.dist.assets;
    
    if (fs.existsSync(assetsDir)) {
        copyDirectory(assetsDir, outputDir);
        console.log('  ✓ Assets copied');
    }
}

/**
 * Optimize assets (images, etc.)
 */
function optimizeAssets(config) {
    if (config.env.OPTIMIZE_IMAGES) {
        optimizeImages(config);
    }
    
    if (config.env.COMPRESS) {
        compressAssets(config);
    }
}

/**
 * Optimize images (placeholder - would use imagemin or similar)
 */
function optimizeImages(config) {
    console.log('  ✓ Image optimization (placeholder - implement with imagemin)');
}

/**
 * Compress assets with gzip
 */
function compressAssets(config) {
    const zlib = require('zlib');
    
    // Compress CSS and JS files
    const compressibleExtensions = ['.css', '.js', '.html', '.json'];
    
    function compressDirectory(dir) {
        if (!fs.existsSync(dir)) return;
        
        const files = fs.readdirSync(dir, { withFileTypes: true });
        
        files.forEach(file => {
            const filePath = path.join(dir, file.name);
            
            if (file.isDirectory()) {
                compressDirectory(filePath);
            } else if (compressibleExtensions.some(ext => file.name.endsWith(ext))) {
                const content = fs.readFileSync(filePath);
                const compressed = zlib.gzipSync(content);
                fs.writeFileSync(filePath + '.gz', compressed);
            }
        });
    }
    
    compressDirectory(config.paths.dist.frontend);
    console.log('  ✓ Assets compressed');
}

/**
 * Create production environment file
 */
function createProductionEnv(config, outputDir) {
    const envTemplate = `# Production Environment Configuration
# Generated automatically during build process

NODE_ENV=${config.target}
PORT=\${PORT:-5000}

# Database Configuration
DB_HOST=\${DB_HOST}
DB_PORT=\${DB_PORT:-3306}
DB_NAME=\${DB_NAME}
DB_USER=\${DB_USER}
DB_PASSWORD=\${DB_PASSWORD}

# JWT Configuration
JWT_SECRET=\${JWT_SECRET}
JWT_EXPIRES_IN=24h

# Email Configuration
SMTP_HOST=\${SMTP_HOST}
SMTP_PORT=\${SMTP_PORT:-587}
SMTP_USER=\${SMTP_USER}
SMTP_PASSWORD=\${SMTP_PASSWORD}

# Razorpay Configuration
RAZORPAY_KEY_ID=\${RAZORPAY_KEY_ID}
RAZORPAY_KEY_SECRET=\${RAZORPAY_KEY_SECRET}

# Security Configuration
BCRYPT_SALT_ROUNDS=12
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100

# File Upload Configuration
UPLOAD_PATH=uploads
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=image/jpeg,image/png,image/webp
`;

    fs.writeFileSync(path.join(outputDir, '.env.production'), envTemplate);
}

/**
 * Utility functions
 */

function copyFile(src, dest) {
    const destDir = path.dirname(dest);
    if (!fs.existsSync(destDir)) {
        fs.mkdirSync(destDir, { recursive: true });
    }
    fs.copyFileSync(src, dest);
}

function copyDirectory(src, dest, excludePatterns = []) {
    if (!fs.existsSync(src)) return;
    
    if (!fs.existsSync(dest)) {
        fs.mkdirSync(dest, { recursive: true });
    }
    
    const files = fs.readdirSync(src, { withFileTypes: true });
    
    files.forEach(file => {
        const srcPath = path.join(src, file.name);
        const destPath = path.join(dest, file.name);
        
        // Check if file should be excluded
        if (excludePatterns.some(pattern => {
            if (pattern.includes('*')) {
                const regex = new RegExp(pattern.replace(/\*/g, '.*'));
                return regex.test(file.name);
            }
            return file.name === pattern;
        })) {
            return;
        }
        
        if (file.isDirectory()) {
            copyDirectory(srcPath, destPath, excludePatterns);
        } else {
            copyFile(srcPath, destPath);
        }
    });
}

function minifyCss(css) {
    return css
        .replace(/\/\*[\s\S]*?\*\//g, '') // Remove comments
        .replace(/\s+/g, ' ') // Collapse whitespace
        .replace(/;\s*}/g, '}') // Remove last semicolon in blocks
        .replace(/\s*{\s*/g, '{') // Clean up braces
        .replace(/}\s*/g, '}')
        .replace(/;\s*/g, ';')
        .trim();
}

function minifyJs(js) {
    return js
        .replace(/\/\*[\s\S]*?\*\//g, '') // Remove block comments
        .replace(/\/\/.*$/gm, '') // Remove line comments
        .replace(/\s+/g, ' ') // Collapse whitespace
        .replace(/;\s*}/g, '}') // Clean up semicolons
        .replace(/\s*{\s*/g, '{') // Clean up braces
        .replace(/}\s*/g, '}')
        .replace(/;\s*/g, ';')
        .trim();
}

function replaceEnvVariables(content, config) {
    // Replace common environment variables
    const replacements = {
        'process.env.NODE_ENV': `'${config.target}'`,
        'process.env.API_URL': `'${config.env.API_URL}'`,
        'process.env.DEBUG': config.env.DEBUG
    };
    
    let result = content;
    Object.entries(replacements).forEach(([key, value]) => {
        const regex = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
        result = result.replace(regex, value);
    });
    
    return result;
}