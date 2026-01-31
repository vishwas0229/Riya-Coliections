/**
 * Production Build Configuration for Riya Collections
 * 
 * This configuration handles:
 * - Environment variable management
 * - Asset minification and compression
 * - Production optimizations
 * - Build validation
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Build configuration
const BUILD_CONFIG = {
    // Environment settings
    environments: {
        development: {
            NODE_ENV: 'development',
            API_URL: 'http://localhost:5000',
            DEBUG: true,
            MINIFY: false,
            COMPRESS: false
        },
        staging: {
            NODE_ENV: 'staging',
            API_URL: 'https://api-staging.yourdomain.com',
            DEBUG: false,
            MINIFY: true,
            COMPRESS: true
        },
        production: {
            NODE_ENV: 'production',
            API_URL: 'https://api.yourdomain.com',
            DEBUG: false,
            MINIFY: true,
            COMPRESS: true,
            OPTIMIZE_IMAGES: true,
            GENERATE_SOURCEMAPS: false
        }
    },

    // Build paths
    paths: {
        src: {
            frontend: 'frontend/src',
            backend: 'backend',
            css: 'frontend/src/css',
            js: 'frontend/src/js',
            assets: 'frontend/assets'
        },
        dist: {
            root: 'dist',
            frontend: 'dist/frontend',
            backend: 'dist/backend',
            css: 'dist/frontend/css',
            js: 'dist/frontend/js',
            assets: 'dist/frontend/assets'
        },
        temp: '.build-temp'
    },

    // Asset optimization settings
    optimization: {
        css: {
            minify: true,
            autoprefixer: true,
            removeComments: true,
            removeUnusedCSS: false // Set to true if you have CSS purging tools
        },
        js: {
            minify: true,
            mangle: true,
            compress: true,
            removeComments: true,
            sourceMaps: false // Set based on environment
        },
        images: {
            optimize: true,
            quality: 85,
            progressive: true,
            formats: ['webp', 'jpg', 'png']
        },
        html: {
            minify: true,
            removeComments: true,
            collapseWhitespace: true
        }
    },

    // Compression settings
    compression: {
        gzip: true,
        brotli: true,
        level: 9
    },

    // Cache busting
    cacheBusting: {
        enabled: true,
        hashLength: 8,
        pattern: '[name].[hash].[ext]'
    }
};

// Environment variable validation
const REQUIRED_ENV_VARS = {
    production: [
        'DB_HOST',
        'DB_USER',
        'DB_PASSWORD',
        'JWT_SECRET',
        'SMTP_HOST',
        'SMTP_USER',
        'SMTP_PASSWORD',
        'RAZORPAY_KEY_ID',
        'RAZORPAY_KEY_SECRET'
    ],
    staging: [
        'DB_HOST',
        'DB_USER',
        'DB_PASSWORD',
        'JWT_SECRET'
    ]
};

/**
 * Validate environment variables for the target environment
 */
function validateEnvironment(env) {
    const required = REQUIRED_ENV_VARS[env] || [];
    const missing = [];

    required.forEach(varName => {
        if (!process.env[varName]) {
            missing.push(varName);
        }
    });

    if (missing.length > 0) {
        throw new Error(`Missing required environment variables for ${env}: ${missing.join(', ')}`);
    }

    console.log(`✓ Environment validation passed for ${env}`);
}

/**
 * Get build configuration for environment
 */
function getBuildConfig(env = 'production') {
    if (!BUILD_CONFIG.environments[env]) {
        throw new Error(`Unknown environment: ${env}`);
    }

    return {
        ...BUILD_CONFIG,
        env: BUILD_CONFIG.environments[env],
        target: env
    };
}

/**
 * Create directory structure for build
 */
function createBuildDirectories(config) {
    const dirs = Object.values(config.paths.dist);
    
    dirs.forEach(dir => {
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
            console.log(`Created directory: ${dir}`);
        }
    });
}

/**
 * Clean build directories
 */
function cleanBuild(config) {
    if (fs.existsSync(config.paths.dist.root)) {
        fs.rmSync(config.paths.dist.root, { recursive: true, force: true });
        console.log('Cleaned build directory');
    }
    
    if (fs.existsSync(config.paths.temp)) {
        fs.rmSync(config.paths.temp, { recursive: true, force: true });
        console.log('Cleaned temp directory');
    }
}

/**
 * Generate build manifest
 */
function generateBuildManifest(config) {
    const manifest = {
        buildTime: new Date().toISOString(),
        environment: config.target,
        version: process.env.npm_package_version || '1.0.0',
        commit: process.env.GIT_COMMIT || 'unknown',
        config: {
            minified: config.env.MINIFY,
            compressed: config.env.COMPRESS,
            optimized: config.env.OPTIMIZE_IMAGES
        }
    };

    const manifestPath = path.join(config.paths.dist.root, 'build-manifest.json');
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
    console.log('Generated build manifest');
    
    return manifest;
}

module.exports = {
    BUILD_CONFIG,
    getBuildConfig,
    validateEnvironment,
    createBuildDirectories,
    cleanBuild,
    generateBuildManifest
};