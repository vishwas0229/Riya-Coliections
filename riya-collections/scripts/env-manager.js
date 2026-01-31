#!/usr/bin/env node

/**
 * Environment Variable Management Script
 * 
 * This script helps manage environment variables across different environments
 * and validates configuration before deployment.
 * 
 * Usage:
 *   node scripts/env-manager.js validate --env=production
 *   node scripts/env-manager.js generate --env=staging
 *   node scripts/env-manager.js check-secrets
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

// Environment variable definitions
const ENV_DEFINITIONS = {
    // Server Configuration
    NODE_ENV: {
        required: true,
        type: 'string',
        values: ['development', 'staging', 'production'],
        description: 'Application environment'
    },
    PORT: {
        required: false,
        type: 'number',
        default: 5000,
        description: 'Server port'
    },
    HOST: {
        required: false,
        type: 'string',
        default: '0.0.0.0',
        description: 'Server host'
    },

    // Database Configuration
    DB_HOST: {
        required: true,
        type: 'string',
        description: 'Database host'
    },
    DB_PORT: {
        required: false,
        type: 'number',
        default: 3306,
        description: 'Database port'
    },
    DB_NAME: {
        required: true,
        type: 'string',
        description: 'Database name'
    },
    DB_USER: {
        required: true,
        type: 'string',
        description: 'Database username'
    },
    DB_PASSWORD: {
        required: true,
        type: 'string',
        sensitive: true,
        description: 'Database password'
    },
    DB_CONNECTION_LIMIT: {
        required: false,
        type: 'number',
        default: 10,
        description: 'Database connection pool limit'
    },

    // JWT Configuration
    JWT_SECRET: {
        required: true,
        type: 'string',
        sensitive: true,
        minLength: 32,
        description: 'JWT signing secret'
    },
    JWT_EXPIRES_IN: {
        required: false,
        type: 'string',
        default: '24h',
        description: 'JWT expiration time'
    },
    JWT_REFRESH_SECRET: {
        required: false,
        type: 'string',
        sensitive: true,
        minLength: 32,
        description: 'JWT refresh token secret'
    },

    // Email Configuration
    SMTP_HOST: {
        required: true,
        type: 'string',
        description: 'SMTP server host'
    },
    SMTP_PORT: {
        required: false,
        type: 'number',
        default: 587,
        description: 'SMTP server port'
    },
    SMTP_SECURE: {
        required: false,
        type: 'boolean',
        default: false,
        description: 'Use SSL/TLS for SMTP'
    },
    SMTP_USER: {
        required: true,
        type: 'string',
        description: 'SMTP username'
    },
    SMTP_PASSWORD: {
        required: true,
        type: 'string',
        sensitive: true,
        description: 'SMTP password'
    },

    // Razorpay Configuration
    RAZORPAY_KEY_ID: {
        required: true,
        type: 'string',
        description: 'Razorpay key ID'
    },
    RAZORPAY_KEY_SECRET: {
        required: true,
        type: 'string',
        sensitive: true,
        description: 'Razorpay key secret'
    },
    RAZORPAY_WEBHOOK_SECRET: {
        required: false,
        type: 'string',
        sensitive: true,
        description: 'Razorpay webhook secret'
    },

    // Security Configuration
    BCRYPT_SALT_ROUNDS: {
        required: false,
        type: 'number',
        default: 12,
        description: 'Bcrypt salt rounds'
    },
    SESSION_SECRET: {
        required: false,
        type: 'string',
        sensitive: true,
        minLength: 32,
        description: 'Session secret'
    },
    RATE_LIMIT_WINDOW_MS: {
        required: false,
        type: 'number',
        default: 900000,
        description: 'Rate limiting window in milliseconds'
    },
    RATE_LIMIT_MAX_REQUESTS: {
        required: false,
        type: 'number',
        default: 100,
        description: 'Maximum requests per window'
    },

    // File Upload Configuration
    UPLOAD_PATH: {
        required: false,
        type: 'string',
        default: 'uploads',
        description: 'File upload directory'
    },
    MAX_FILE_SIZE: {
        required: false,
        type: 'number',
        default: 5242880,
        description: 'Maximum file size in bytes'
    },
    ALLOWED_FILE_TYPES: {
        required: false,
        type: 'string',
        default: 'image/jpeg,image/png,image/webp',
        description: 'Allowed file MIME types'
    },

    // SSL Configuration
    SSL_ENABLED: {
        required: false,
        type: 'boolean',
        default: false,
        description: 'Enable SSL/HTTPS'
    },
    SSL_PORT: {
        required: false,
        type: 'number',
        default: 443,
        description: 'SSL port'
    },
    SSL_KEY_PATH: {
        required: false,
        type: 'string',
        description: 'Path to SSL private key'
    },
    SSL_CERT_PATH: {
        required: false,
        type: 'string',
        description: 'Path to SSL certificate'
    },

    // CORS Configuration
    ALLOWED_ORIGINS: {
        required: false,
        type: 'string',
        description: 'Comma-separated list of allowed origins'
    },

    // Application URLs
    BASE_URL: {
        required: false,
        type: 'string',
        description: 'Base application URL'
    },
    FRONTEND_URL: {
        required: false,
        type: 'string',
        description: 'Frontend application URL'
    },
    ADMIN_PANEL_URL: {
        required: false,
        type: 'string',
        description: 'Admin panel URL'
    }
};

// Environment-specific requirements
const ENV_REQUIREMENTS = {
    production: [
        'NODE_ENV', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'JWT_SECRET', 'SMTP_HOST', 'SMTP_USER', 'SMTP_PASSWORD',
        'RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET'
    ],
    staging: [
        'NODE_ENV', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'JWT_SECRET', 'SMTP_HOST', 'SMTP_USER', 'SMTP_PASSWORD'
    ],
    development: [
        'NODE_ENV'
    ]
};

/**
 * Parse command line arguments
 */
function parseArgs() {
    const args = process.argv.slice(2);
    const options = { command: args[0] };

    args.slice(1).forEach(arg => {
        if (arg.startsWith('--')) {
            const [key, value] = arg.substring(2).split('=');
            options[key] = value || true;
        }
    });

    return options;
}

/**
 * Validate environment variables
 */
function validateEnvironment(env) {
    console.log(`🔍 Validating environment: ${env}`);
    
    const required = ENV_REQUIREMENTS[env] || [];
    const errors = [];
    const warnings = [];

    // Check required variables
    required.forEach(varName => {
        const definition = ENV_DEFINITIONS[varName];
        const value = process.env[varName];

        if (!value) {
            errors.push(`Missing required variable: ${varName} - ${definition.description}`);
            return;
        }

        // Type validation
        if (definition.type === 'number' && isNaN(Number(value))) {
            errors.push(`Invalid type for ${varName}: expected number, got ${typeof value}`);
        }

        if (definition.type === 'boolean' && !['true', 'false'].includes(value.toLowerCase())) {
            errors.push(`Invalid type for ${varName}: expected boolean, got ${value}`);
        }

        // Length validation
        if (definition.minLength && value.length < definition.minLength) {
            errors.push(`${varName} must be at least ${definition.minLength} characters long`);
        }

        // Value validation
        if (definition.values && !definition.values.includes(value)) {
            errors.push(`Invalid value for ${varName}: must be one of ${definition.values.join(', ')}`);
        }
    });

    // Check for recommended variables
    Object.entries(ENV_DEFINITIONS).forEach(([varName, definition]) => {
        if (!required.includes(varName) && !process.env[varName] && definition.default === undefined) {
            warnings.push(`Recommended variable not set: ${varName} - ${definition.description}`);
        }
    });

    // Report results
    if (errors.length > 0) {
        console.error('❌ Environment validation failed:');
        errors.forEach(error => console.error(`  • ${error}`));
        return false;
    }

    if (warnings.length > 0) {
        console.warn('⚠️  Environment warnings:');
        warnings.forEach(warning => console.warn(`  • ${warning}`));
    }

    console.log('✅ Environment validation passed');
    return true;
}

/**
 * Generate environment template
 */
function generateTemplate(env) {
    console.log(`📝 Generating environment template for: ${env}`);
    
    const required = ENV_REQUIREMENTS[env] || [];
    const template = [];

    template.push(`# Environment Configuration for ${env.toUpperCase()}`);
    template.push(`# Generated on ${new Date().toISOString()}`);
    template.push('');

    // Group variables by category
    const categories = {
        'Server Configuration': ['NODE_ENV', 'PORT', 'HOST'],
        'Database Configuration': ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_CONNECTION_LIMIT'],
        'JWT Configuration': ['JWT_SECRET', 'JWT_EXPIRES_IN', 'JWT_REFRESH_SECRET'],
        'Email Configuration': ['SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_USER', 'SMTP_PASSWORD'],
        'Razorpay Configuration': ['RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET', 'RAZORPAY_WEBHOOK_SECRET'],
        'Security Configuration': ['BCRYPT_SALT_ROUNDS', 'SESSION_SECRET', 'RATE_LIMIT_WINDOW_MS', 'RATE_LIMIT_MAX_REQUESTS'],
        'File Upload Configuration': ['UPLOAD_PATH', 'MAX_FILE_SIZE', 'ALLOWED_FILE_TYPES'],
        'SSL Configuration': ['SSL_ENABLED', 'SSL_PORT', 'SSL_KEY_PATH', 'SSL_CERT_PATH'],
        'CORS Configuration': ['ALLOWED_ORIGINS'],
        'Application URLs': ['BASE_URL', 'FRONTEND_URL', 'ADMIN_PANEL_URL']
    };

    Object.entries(categories).forEach(([category, vars]) => {
        const categoryVars = vars.filter(varName => 
            required.includes(varName) || ENV_DEFINITIONS[varName].default !== undefined
        );

        if (categoryVars.length > 0) {
            template.push(`# ${category}`);
            
            categoryVars.forEach(varName => {
                const definition = ENV_DEFINITIONS[varName];
                const isRequired = required.includes(varName);
                const placeholder = definition.sensitive ? 'REPLACE_WITH_ACTUAL_VALUE' : 
                                 definition.default !== undefined ? definition.default : 
                                 'REPLACE_WITH_VALUE';

                template.push(`# ${definition.description}`);
                if (isRequired) {
                    template.push(`${varName}=${placeholder}`);
                } else {
                    template.push(`# ${varName}=${placeholder}`);
                }
            });
            
            template.push('');
        }
    });

    const templateContent = template.join('\n');
    const filename = `.env.${env}.template`;
    
    fs.writeFileSync(filename, templateContent);
    console.log(`✅ Template generated: ${filename}`);
    
    return templateContent;
}

/**
 * Check for sensitive data in environment files
 */
function checkSecrets() {
    console.log('🔒 Checking for exposed secrets...');
    
    const envFiles = ['.env', '.env.local', '.env.development', '.env.staging', '.env.production'];
    const issues = [];

    envFiles.forEach(file => {
        if (fs.existsSync(file)) {
            const content = fs.readFileSync(file, 'utf8');
            const lines = content.split('\n');

            lines.forEach((line, index) => {
                const trimmed = line.trim();
                if (trimmed && !trimmed.startsWith('#')) {
                    const [key, value] = trimmed.split('=');
                    const definition = ENV_DEFINITIONS[key];

                    if (definition && definition.sensitive) {
                        // Check for common weak values
                        const weakValues = ['password', '123456', 'secret', 'changeme', 'admin'];
                        if (weakValues.some(weak => value && value.toLowerCase().includes(weak))) {
                            issues.push(`${file}:${index + 1} - Weak value for sensitive variable ${key}`);
                        }

                        // Check for minimum length
                        if (definition.minLength && value && value.length < definition.minLength) {
                            issues.push(`${file}:${index + 1} - ${key} is too short (minimum ${definition.minLength} characters)`);
                        }
                    }
                }
            });
        }
    });

    if (issues.length > 0) {
        console.error('❌ Security issues found:');
        issues.forEach(issue => console.error(`  • ${issue}`));
        return false;
    }

    console.log('✅ No security issues found');
    return true;
}

/**
 * Generate secure random values for secrets
 */
function generateSecrets() {
    console.log('🔐 Generating secure random values...');
    
    const secrets = {};
    
    Object.entries(ENV_DEFINITIONS).forEach(([key, definition]) => {
        if (definition.sensitive && definition.minLength) {
            secrets[key] = crypto.randomBytes(Math.max(32, definition.minLength)).toString('hex');
        }
    });

    console.log('Generated secrets (save these securely):');
    Object.entries(secrets).forEach(([key, value]) => {
        console.log(`${key}=${value}`);
    });

    return secrets;
}

/**
 * Main execution
 */
function main() {
    const options = parseArgs();
    const { command, env = 'production' } = options;

    try {
        switch (command) {
            case 'validate':
                if (!validateEnvironment(env)) {
                    process.exit(1);
                }
                break;

            case 'generate':
                generateTemplate(env);
                break;

            case 'check-secrets':
                if (!checkSecrets()) {
                    process.exit(1);
                }
                break;

            case 'generate-secrets':
                generateSecrets();
                break;

            default:
                console.log(`
Environment Variable Manager

Usage:
  node scripts/env-manager.js <command> [options]

Commands:
  validate          Validate environment variables
  generate          Generate environment template
  check-secrets     Check for security issues
  generate-secrets  Generate secure random values

Options:
  --env=<env>      Target environment (development, staging, production)

Examples:
  node scripts/env-manager.js validate --env=production
  node scripts/env-manager.js generate --env=staging
  node scripts/env-manager.js check-secrets
                `);
                break;
        }
    } catch (error) {
        console.error('❌ Error:', error.message);
        process.exit(1);
    }
}

if (require.main === module) {
    main();
}

module.exports = {
    validateEnvironment,
    generateTemplate,
    checkSecrets,
    generateSecrets,
    ENV_DEFINITIONS,
    ENV_REQUIREMENTS
};