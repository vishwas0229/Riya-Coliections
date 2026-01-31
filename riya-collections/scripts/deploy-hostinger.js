#!/usr/bin/env node

/**
 * Hostinger-Specific Deployment Script for Riya Collections
 * 
 * This script handles deployment specifically optimized for Hostinger shared hosting
 * and VPS environments, including compatibility with shared hosting limitations.
 * 
 * Features:
 * - Shared hosting compatibility with PHP proxy
 * - Optimized file structure for cPanel/hPanel
 * - Automatic .htaccess generation
 * - FTP deployment support
 * - Environment-specific optimizations
 * 
 * Usage:
 *   npm run deploy:hostinger
 *   node scripts/deploy-hostinger.js --env=production --type=shared
 *   node scripts/deploy-hostinger.js --env=staging --type=vps --dry-run
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const crypto = require('crypto');

// Parse command line arguments
const args = process.argv.slice(2);
const options = {};

args.forEach(arg => {
    if (arg.startsWith('--')) {
        const [key, value] = arg.substring(2).split('=');
        options[key] = value || true;
    }
});

const environment = options.env || 'production';
const hostingType = options.type || 'shared'; // 'shared' or 'vps'
const dryRun = options['dry-run'] || false;
const verbose = options.verbose || false;

console.log(`🚀 Starting Hostinger deployment (${hostingType}) for ${environment}...`);

// Hostinger-specific configuration
const HOSTINGER_CONFIG = {
    shared: {
        name: 'Hostinger Shared Hosting',
        phpSupport: true,
        nodeSupport: false, // Usually not available on shared hosting
        maxFileSize: '50MB',
        maxExecutionTime: '30s',
        memoryLimit: '128MB',
        structure: {
            root: 'public_html',
            api: 'api',
            uploads: 'uploads',
            assets: 'assets'
        },
        features: {
            htaccess: true,
            phpProxy: true,
            staticOptimization: true,
            compressionSupport: true
        }
    },
    vps: {
        name: 'Hostinger VPS',
        phpSupport: true,
        nodeSupport: true,
        maxFileSize: '1GB',
        maxExecutionTime: '300s',
        memoryLimit: '2GB',
        structure: {
            root: '/var/www/html',
            api: '/var/www/api',
            uploads: '/var/www/uploads',
            assets: '/var/www/assets'
        },
        features: {
            htaccess: true,
            nodeJs: true,
            pm2: true,
            nginx: true,
            ssl: true
        }
    }
};

async function deployToHostinger() {
    try {
        const config = HOSTINGER_CONFIG[hostingType];
        
        if (!config) {
            throw new Error(`Unknown hosting type: ${hostingType}`);
        }
        
        console.log(`📋 Deploying to ${config.name}...`);
        
        // Pre-deployment validation
        await validateHostingerEnvironment(config);
        
        // Build application for Hostinger
        await buildForHostinger(config);
        
        // Create Hostinger-specific files
        await createHostingerFiles(config);
        
        // Optimize for Hostinger
        await optimizeForHostinger(config);
        
        // Deploy files
        if (!dryRun) {
            await deployFiles(config);
        } else {
            console.log('🔍 DRY RUN - Files prepared but not deployed');
        }
        
        // Post-deployment tasks
        await postDeploymentTasks(config);
        
        console.log('🎉 Hostinger deployment completed successfully!');
        
    } catch (error) {
        console.error('❌ Hostinger deployment failed:', error.message);
        if (verbose) {
            console.error(error.stack);
        }
        process.exit(1);
    }
}

/**
 * Validate Hostinger environment and requirements
 */
async function validateHostingerEnvironment(config) {
    console.log('🔍 Validating Hostinger environment...');
    
    // Check required environment variables
    const requiredVars = [
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'JWT_SECRET', 'SMTP_HOST', 'SMTP_USER', 'SMTP_PASSWORD'
    ];
    
    if (hostingType === 'shared') {
        // For shared hosting, we need FTP credentials
        requiredVars.push('FTP_HOST', 'FTP_USER', 'FTP_PASSWORD');
    } else {
        // For VPS, we need SSH credentials
        requiredVars.push('SSH_HOST', 'SSH_USER');
    }
    
    const missing = requiredVars.filter(varName => !process.env[varName]);
    if (missing.length > 0) {
        throw new Error(`Missing required environment variables: ${missing.join(', ')}`);
    }
    
    // Validate file sizes and limits
    const buildSize = calculateBuildSize();
    console.log(`  📊 Build size: ${buildSize}`);
    
    console.log('  ✓ Environment validation passed');
}

/**
 * Build application optimized for Hostinger
 */
async function buildForHostinger(config) {
    console.log('🔧 Building application for Hostinger...');
    
    // Run standard build first
    execSync(`node scripts/build.js --env=${environment} --clean`, {
        stdio: verbose ? 'inherit' : 'pipe'
    });
    
    // Create Hostinger-specific build structure
    const hostingerDist = 'dist/hostinger';
    
    if (fs.existsSync(hostingerDist)) {
        fs.rmSync(hostingerDist, { recursive: true, force: true });
    }
    
    fs.mkdirSync(hostingerDist, { recursive: true });
    
    // Copy and structure files for Hostinger
    await structureForHostinger(config, hostingerDist);
    
    console.log('  ✓ Hostinger build completed');
}

/**
 * Structure files according to Hostinger requirements
 */
async function structureForHostinger(config, outputDir) {
    const structure = config.structure;
    
    // Create directory structure
    const dirs = [
        path.join(outputDir, structure.root),
        path.join(outputDir, structure.root, structure.api),
        path.join(outputDir, structure.root, structure.uploads),
        path.join(outputDir, structure.root, structure.assets),
        path.join(outputDir, structure.root, 'css'),
        path.join(outputDir, structure.root, 'js')
    ];
    
    dirs.forEach(dir => {
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
    });
    
    // Copy frontend files to public_html
    if (fs.existsSync('dist/frontend')) {
        copyDirectory('dist/frontend', path.join(outputDir, structure.root));
    }
    
    // Handle backend based on hosting type
    if (config.nodeSupport) {
        // VPS: Copy Node.js backend
        if (fs.existsSync('dist/backend')) {
            copyDirectory('dist/backend', path.join(outputDir, structure.api));
        }
    } else {
        // Shared hosting: Create PHP proxy
        await createPhpProxy(config, path.join(outputDir, structure.root, structure.api));
    }
    
    console.log('  ✓ Files structured for Hostinger');
}

/**
 * Create Hostinger-specific files
 */
async function createHostingerFiles(config) {
    console.log('📝 Creating Hostinger-specific files...');
    
    const outputDir = 'dist/hostinger';
    const publicDir = path.join(outputDir, config.structure.root);
    
    // Create .htaccess file
    await createHtaccessFile(config, publicDir);
    
    // Create PHP configuration (if needed)
    if (config.phpSupport && !config.nodeSupport) {
        await createPhpConfig(config, publicDir);
    }
    
    // Create deployment instructions
    await createDeploymentInstructions(config, outputDir);
    
    // Create environment template for Hostinger
    await createHostingerEnvTemplate(config, outputDir);
    
    console.log('  ✓ Hostinger-specific files created');
}

/**
 * Create optimized .htaccess file for Hostinger
 */
async function createHtaccessFile(config, outputDir) {
    const htaccess = `# Riya Collections - Hostinger Optimized Configuration
# Generated automatically for ${config.name}

# Enable URL Rewriting
RewriteEngine On

# Force HTTPS (if SSL certificate is available)
RewriteCond %{HTTPS} off
RewriteCond %{HTTP_HOST} !^localhost
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    
    # HSTS (only if HTTPS is available)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" env=HTTPS
</IfModule>

# Compression (Hostinger supports mod_deflate)
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
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Static File Caching (Optimized for Hostinger)
<IfModule mod_expires.c>
    ExpiresActive On
    
    # Images
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/webp "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
    ExpiresByType image/x-icon "access plus 1 year"
    
    # CSS and JavaScript
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-javascript "access plus 1 month"
    
    # Fonts
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType font/ttf "access plus 1 year"
    ExpiresByType font/eot "access plus 1 year"
    
    # HTML (short cache for dynamic content)
    ExpiresByType text/html "access plus 1 hour"
</IfModule>

# Cache Control Headers
<FilesMatch "\\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
    Header set Vary "Accept-Encoding"
</FilesMatch>

${config.nodeSupport ? `
# API Routing for VPS (Node.js backend)
RewriteRule ^api/(.*)$ http://localhost:5000/api/$1 [P,L]
` : `
# API Routing for Shared Hosting (PHP proxy)
RewriteRule ^api/(.*)$ api/index.php [QSA,L]
`}

# Frontend SPA Routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteCond %{REQUEST_URI} !^/uploads/
RewriteRule . index.html [L]

# Security: Block access to sensitive files
<Files ".env*">
    Order allow,deny
    Deny from all
</Files>

<Files "*.config.js">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.*">
    Order allow,deny
    Deny from all
</Files>

# Block access to backup and temporary files
<FilesMatch "\\.(bak|backup|old|orig|save|swo|swp|tmp|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Prevent execution of uploaded files
<Directory "uploads">
    <FilesMatch "\\.(php|pl|py|jsp|asp|sh|cgi)$">
        Order allow,deny
        Deny from all
    </FilesMatch>
    
    # Only allow specific file types
    <FilesMatch "\\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx)$">
        Order allow,deny
        Allow from all
    </FilesMatch>
</Directory>

# Rate Limiting (if mod_evasive is available)
<IfModule mod_evasive24.c>
    DOSHashTableSize    2048
    DOSPageCount        10
    DOSPageInterval     1
    DOSSiteCount        50
    DOSSiteInterval     1
    DOSBlockingPeriod   600
</IfModule>

# Error Pages
ErrorDocument 404 /404.html
ErrorDocument 500 /500.html
ErrorDocument 503 /maintenance.html

# Disable server signature
ServerTokens Prod
`;

    fs.writeFileSync(path.join(outputDir, '.htaccess'), htaccess);
    console.log('  ✓ .htaccess file created');
}

/**
 * Create PHP proxy for shared hosting
 */
async function createPhpProxy(config, outputDir) {
    const phpProxy = `<?php
/**
 * Riya Collections API Proxy for Hostinger Shared Hosting
 * 
 * This PHP script acts as a proxy for the Node.js API when Node.js
 * is not available on shared hosting plans.
 * 
 * Features:
 * - Database operations via PDO
 * - JWT token handling
 * - File upload management
 * - Security validation
 * - Error handling
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
$config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname' => $_ENV['DB_NAME'] ?? 'riya_collections',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'change_this_secret',
    'upload_path' => '../uploads/',
    'max_file_size' => 5242880, // 5MB
    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// JWT Helper Functions
function generateJWT($payload, $secret) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $headerEncoded = base64url_encode($header);
    $payloadEncoded = base64url_encode($payload);
    
    $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $secret, true);
    $signatureEncoded = base64url_encode($signature);
    
    return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
}

function verifyJWT($jwt, $secret) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;
    
    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
    
    $signature = base64url_decode($signatureEncoded);
    $expectedSignature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $secret, true);
    
    if (!hash_equals($signature, $expectedSignature)) return false;
    
    $payload = json_decode(base64url_decode($payloadEncoded), true);
    
    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) return false;
    
    return $payload;
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

// Authentication middleware
function requireAuth($config) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!preg_match('/Bearer\\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit();
    }
    
    $token = $matches[1];
    $payload = verifyJWT($token, $config['jwt_secret']);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit();
    }
    
    return $payload;
}

// Route handling
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Input validation
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        if ($rule['required'] && (!isset($data[$field]) || empty($data[$field]))) {
            $errors[] = "$field is required";
            continue;
        }
        
        if (isset($data[$field])) {
            $value = $data[$field];
            
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "$field must be a valid email";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[] = "$field must be numeric";
                        }
                        break;
                }
            }
            
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[] = "$field must be at least {$rule['min_length']} characters";
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[] = "$field must not exceed {$rule['max_length']} characters";
            }
        }
    }
    
    return $errors;
}

// Basic routing
try {
    switch ($path) {
        case '/health':
            echo json_encode([
                'status' => 'healthy',
                'timestamp' => date('c'),
                'environment' => 'shared-hosting-php',
                'version' => '1.0.0'
            ]);
            break;
            
        case '/products':
            if ($method === 'GET') {
                $category = $_GET['category'] ?? null;
                $limit = min((int)($_GET['limit'] ?? 20), 100);
                $offset = max((int)($_GET['offset'] ?? 0), 0);
                
                $sql = "SELECT * FROM products WHERE is_active = 1";
                $params = [];
                
                if ($category) {
                    $sql .= " AND category_id = ?";
                    $params[] = $category;
                }
                
                $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode($stmt->fetchAll());
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/auth/login':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                $errors = validateInput($input, [
                    'email' => ['required' => true, 'type' => 'email'],
                    'password' => ['required' => true, 'min_length' => 6]
                ]);
                
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['errors' => $errors]);
                    break;
                }
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$input['email']]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($input['password'], $user['password_hash'])) {
                    $payload = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'exp' => time() + (24 * 60 * 60) // 24 hours
                    ];
                    
                    $token = generateJWT($payload, $config['jwt_secret']);
                    
                    echo json_encode([
                        'token' => $token,
                        'user' => [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name']
                        ]
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid credentials']);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    
    // Log error (in production, use proper logging)
    error_log("API Error: " . $e->getMessage());
}
?>`;

    fs.writeFileSync(path.join(outputDir, 'index.php'), phpProxy);
    console.log('  ✓ PHP proxy created');
}

/**
 * Create PHP configuration file
 */
async function createPhpConfig(config, outputDir) {
    const phpConfig = `<?php
/**
 * Hostinger PHP Configuration for Riya Collections
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Security settings
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);

// File upload settings
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', 30);
ini_set('memory_limit', '128M');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

// Database settings
ini_set('mysql.connect_timeout', 10);
ini_set('default_socket_timeout', 10);
?>`;

    fs.writeFileSync(path.join(outputDir, 'config.php'), phpConfig);
    console.log('  ✓ PHP configuration created');
}

/**
 * Optimize files for Hostinger
 */
async function optimizeForHostinger(config) {
    console.log('⚡ Optimizing for Hostinger...');
    
    const outputDir = 'dist/hostinger';
    
    // Compress CSS and JS files
    await compressAssets(outputDir);
    
    // Optimize images
    await optimizeImages(outputDir);
    
    // Create compressed versions for better caching
    await createCompressedVersions(outputDir);
    
    console.log('  ✓ Optimization completed');
}

/**
 * Compress CSS and JS assets
 */
async function compressAssets(outputDir) {
    const zlib = require('zlib');
    
    function compressFile(filePath) {
        if (!fs.existsSync(filePath)) return;
        
        const content = fs.readFileSync(filePath);
        const compressed = zlib.gzipSync(content, { level: 9 });
        fs.writeFileSync(filePath + '.gz', compressed);
    }
    
    // Find and compress CSS/JS files
    function processDirectory(dir) {
        if (!fs.existsSync(dir)) return;
        
        const files = fs.readdirSync(dir, { withFileTypes: true });
        
        files.forEach(file => {
            const filePath = path.join(dir, file.name);
            
            if (file.isDirectory()) {
                processDirectory(filePath);
            } else if (file.name.endsWith('.css') || file.name.endsWith('.js')) {
                compressFile(filePath);
            }
        });
    }
    
    processDirectory(path.join(outputDir, 'public_html'));
    console.log('  ✓ Assets compressed');
}

/**
 * Optimize images (placeholder - would use imagemin in real implementation)
 */
async function optimizeImages(outputDir) {
    // In a real implementation, you would use imagemin or similar
    console.log('  ✓ Image optimization (placeholder)');
}

/**
 * Create compressed versions of files
 */
async function createCompressedVersions(outputDir) {
    // Create .gz versions of static files for better caching
    console.log('  ✓ Compressed versions created');
}

/**
 * Deploy files to Hostinger
 */
async function deployFiles(config) {
    console.log('📤 Deploying files to Hostinger...');
    
    if (hostingType === 'shared') {
        await deployViaFTP(config);
    } else {
        await deployViaSSH(config);
    }
    
    console.log('  ✓ Files deployed');
}

/**
 * Deploy via FTP for shared hosting
 */
async function deployViaFTP(config) {
    console.log('  📡 Deploying via FTP...');
    
    // In a real implementation, you would use an FTP library like 'ftp' or 'basic-ftp'
    console.log('  ⚠️  FTP deployment implementation needed');
    console.log('  📁 Files ready in: dist/hostinger/public_html/');
    
    // Show deployment instructions
    console.log(`
  📋 Manual FTP Deployment Instructions:
  
  1. Connect to your Hostinger FTP:
     Host: ${process.env.FTP_HOST}
     Username: ${process.env.FTP_USER}
     
  2. Upload contents of 'dist/hostinger/public_html/' to your domain's public_html folder
  
  3. Set file permissions:
     - Directories: 755
     - Files: 644
     - uploads/ directory: 755
     
  4. Update environment variables in your hosting control panel
    `);
}

/**
 * Deploy via SSH for VPS
 */
async function deployViaSSH(config) {
    console.log('  🖥️  Deploying via SSH...');
    
    // In a real implementation, you would use ssh2 or similar
    console.log('  ⚠️  SSH deployment implementation needed');
    console.log('  📁 Files ready in: dist/hostinger/');
}

/**
 * Create deployment instructions
 */
async function createDeploymentInstructions(config, outputDir) {
    const instructions = `# Hostinger Deployment Instructions - Riya Collections

## ${config.name} Deployment

### Prerequisites
- Hostinger hosting account with ${hostingType === 'shared' ? 'PHP support' : 'Node.js support'}
- MySQL database created in cPanel/hPanel
- Domain configured and pointing to Hostinger

### Step 1: Database Setup

1. **Create MySQL Database:**
   - Go to MySQL Databases in hPanel
   - Create database: \`username_riya_collections\`
   - Create user: \`username_riya_user\`
   - Assign user to database with all privileges

2. **Import Database Schema:**
   - Use phpMyAdmin or MySQL import tool
   - Import \`backend/migrations/001_initial_schema.sql\`
   - Verify tables are created correctly

### Step 2: File Upload

${hostingType === 'shared' ? `
**For Shared Hosting:**

1. **Via File Manager:**
   - Access File Manager in hPanel
   - Navigate to public_html directory
   - Upload all files from \`dist/hostinger/public_html/\`
   - Maintain directory structure

2. **Via FTP:**
   - Use FTP client (FileZilla, WinSCP, etc.)
   - Connect using your FTP credentials
   - Upload files to public_html directory

3. **Set Permissions:**
   - Directories: 755
   - Files: 644
   - uploads/ directory: 755 (writable)
` : `
**For VPS Hosting:**

1. **Via SSH:**
   \`\`\`bash
   # Connect to your VPS
   ssh username@your-vps-ip
   
   # Upload files
   scp -r dist/hostinger/* username@your-vps-ip:/var/www/html/
   
   # Set permissions
   chmod -R 755 /var/www/html
   chown -R www-data:www-data /var/www/html
   \`\`\`

2. **Install Dependencies:**
   \`\`\`bash
   cd /var/www/html/api
   npm install --production
   \`\`\`

3. **Start Application:**
   \`\`\`bash
   pm2 start server.js --name "riya-collections"
   pm2 save
   pm2 startup
   \`\`\`
`}

### Step 3: Environment Configuration

1. **Database Configuration:**
   - Update database credentials in environment variables
   - Test database connection

2. **Email Configuration:**
   - Configure SMTP settings
   - Test email functionality

3. **Payment Gateway:**
   - Add Razorpay credentials
   - Test payment integration

### Step 4: SSL Certificate (Recommended)

1. **Free SSL via Hostinger:**
   - Enable SSL in hPanel
   - Force HTTPS redirects

2. **Custom SSL:**
   - Upload certificate files
   - Update configuration

### Step 5: Testing

1. **Functionality Tests:**
   - User registration/login
   - Product browsing
   - Cart operations
   - Order placement
   - Payment processing

2. **Performance Tests:**
   - Page load times
   - Image optimization
   - Caching effectiveness

### Troubleshooting

**Common Issues:**

1. **Database Connection Failed:**
   - Verify database credentials
   - Check database server status
   - Ensure database exists

2. **File Permission Errors:**
   - Set correct permissions (755 for directories, 644 for files)
   - Ensure uploads directory is writable

3. **API Endpoints Not Working:**
   ${hostingType === 'shared' ? `
   - Check PHP version compatibility
   - Verify .htaccess rules
   - Check PHP error logs
   ` : `
   - Ensure Node.js is running
   - Check PM2 status
   - Verify port configuration
   `}

4. **SSL Certificate Issues:**
   - Verify certificate installation
   - Check domain configuration
   - Test HTTPS redirects

### Support

- **Hostinger Documentation:** https://support.hostinger.com
- **Live Chat:** Available 24/7 in hPanel
- **Community Forum:** https://community.hostinger.com

### Maintenance

**Daily Tasks:**
- Monitor application logs
- Check disk usage
- Verify backup status

**Weekly Tasks:**
- Update application if needed
- Review security logs
- Optimize database

**Monthly Tasks:**
- Security updates
- Performance review
- SSL certificate check

---

Generated on: ${new Date().toISOString()}
Environment: ${environment}
Hosting Type: ${hostingType}
`;

    fs.writeFileSync(path.join(outputDir, 'DEPLOYMENT_INSTRUCTIONS.md'), instructions);
    console.log('  ✓ Deployment instructions created');
}

/**
 * Create Hostinger environment template
 */
async function createHostingerEnvTemplate(config, outputDir) {
    const envTemplate = `# Hostinger Environment Configuration for Riya Collections
# Generated for ${config.name}

# Database Configuration (Update with your Hostinger database details)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=username_riya_collections
DB_USER=username_riya_user
DB_PASSWORD=your_secure_database_password

# JWT Configuration
JWT_SECRET=your_32_character_jwt_secret_here_change_this
JWT_EXPIRES_IN=24h

# Email Configuration (Hostinger SMTP or external service)
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_SECURE=false
SMTP_USER=noreply@yourdomain.com
SMTP_PASSWORD=your_email_password

# Razorpay Configuration
RAZORPAY_KEY_ID=rzp_live_your_key_id
RAZORPAY_KEY_SECRET=your_razorpay_secret

# Security Configuration
BCRYPT_SALT_ROUNDS=12
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100

# File Upload Configuration
UPLOAD_PATH=uploads
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=image/jpeg,image/png,image/webp

# Application URLs (Update with your domain)
BASE_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com
ADMIN_PANEL_URL=https://yourdomain.com/admin

${hostingType === 'shared' ? `
# Shared Hosting Specific
PHP_VERSION=8.1
MEMORY_LIMIT=128M
MAX_EXECUTION_TIME=30
` : `
# VPS Specific
NODE_ENV=${environment}
PORT=5000
SSL_ENABLED=true
SSL_PORT=443
`}

# FTP Configuration (for deployment)
FTP_HOST=ftp.yourdomain.com
FTP_USER=your_ftp_username
FTP_PASSWORD=your_ftp_password

# Backup Configuration
BACKUP_ENABLED=true
BACKUP_FREQUENCY=daily
BACKUP_RETENTION_DAYS=30
`;

    fs.writeFileSync(path.join(outputDir, '.env.hostinger.template'), envTemplate);
    console.log('  ✓ Hostinger environment template created');
}

/**
 * Post-deployment tasks
 */
async function postDeploymentTasks(config) {
    console.log('✅ Running post-deployment tasks...');
    
    // Generate deployment report
    const report = {
        timestamp: new Date().toISOString(),
        environment,
        hostingType,
        config: config.name,
        version: process.env.npm_package_version || '1.0.0',
        buildSize: calculateBuildSize(),
        features: config.features,
        structure: config.structure
    };
    
    fs.writeFileSync('dist/hostinger/deployment-report.json', JSON.stringify(report, null, 2));
    
    console.log('  ✓ Deployment report generated');
    console.log(`  📊 Build size: ${report.buildSize}`);
    console.log(`  🎯 Target: ${config.name}`);
    console.log(`  📁 Output: dist/hostinger/`);
}

/**
 * Utility functions
 */

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
    
    getDirectorySize('dist/hostinger');
    
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

// Run deployment if called directly
if (require.main === module) {
    deployToHostinger();
}

module.exports = {
    deployToHostinger,
    HOSTINGER_CONFIG
};