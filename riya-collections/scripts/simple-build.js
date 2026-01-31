#!/usr/bin/env node

/**
 * Simple Build Script for Riya Collections
 * Prepares files for deployment without requiring all environment variables
 */

const fs = require('fs');
const path = require('path');

console.log('🚀 Building Riya Collections for deployment...');

// Create dist directory
const distDir = 'dist';
if (fs.existsSync(distDir)) {
    fs.rmSync(distDir, { recursive: true, force: true });
}
fs.mkdirSync(distDir, { recursive: true });

// Create frontend dist
const frontendDist = path.join(distDir, 'frontend');
fs.mkdirSync(frontendDist, { recursive: true });

// Copy frontend files
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

// Copy frontend files
console.log('📁 Copying frontend files...');
copyDirectory('frontend/src', path.join(frontendDist, 'src'));
copyDirectory('frontend/assets', path.join(frontendDist, 'assets'));
copyDirectory('frontend/pages', path.join(frontendDist, 'pages'));

// Copy main HTML file
if (fs.existsSync('frontend/index.html')) {
    fs.copyFileSync('frontend/index.html', path.join(frontendDist, 'index.html'));
}
if (fs.existsSync('riya-collections/index.html')) {
    fs.copyFileSync('riya-collections/index.html', path.join(frontendDist, 'index.html'));
}

// Create backend dist
const backendDist = path.join(distDir, 'backend');
fs.mkdirSync(backendDist, { recursive: true });

console.log('📁 Copying backend files...');

// Copy backend files (excluding node_modules, logs, uploads)
const backendFiles = [
    'config',
    'middleware', 
    'routes',
    'utils',
    'migrations',
    'server.js',
    'package.json'
];

backendFiles.forEach(file => {
    const srcPath = path.join('backend', file);
    const destPath = path.join(backendDist, file);
    
    if (fs.existsSync(srcPath)) {
        if (fs.statSync(srcPath).isDirectory()) {
            copyDirectory(srcPath, destPath);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    }
});

// Copy environment template
if (fs.existsSync('backend/.env.production')) {
    fs.copyFileSync('backend/.env.production', path.join(backendDist, '.env.production.template'));
}

// Create uploads directory structure
const uploadsDir = path.join(backendDist, 'uploads');
fs.mkdirSync(uploadsDir, { recursive: true });
fs.mkdirSync(path.join(uploadsDir, 'products'), { recursive: true });
fs.mkdirSync(path.join(uploadsDir, 'categories'), { recursive: true });

// Create .gitkeep files
fs.writeFileSync(path.join(uploadsDir, '.gitkeep'), '');
fs.writeFileSync(path.join(uploadsDir, 'products', '.gitkeep'), '');
fs.writeFileSync(path.join(uploadsDir, 'categories', '.gitkeep'), '');

// Create deployment package for Hostinger
const hostingerDist = path.join(distDir, 'hostinger-package');
fs.mkdirSync(hostingerDist, { recursive: true });

const publicHtml = path.join(hostingerDist, 'public_html');
fs.mkdirSync(publicHtml, { recursive: true });

console.log('📦 Creating Hostinger deployment package...');

// Copy frontend to public_html
copyDirectory(frontendDist, publicHtml);

// Copy backend to public_html/api
const apiDir = path.join(publicHtml, 'api');
copyDirectory(backendDist, apiDir);

// Create .htaccess file
const htaccess = `# Riya Collections - Production .htaccess

# Enable URL Rewriting
RewriteEngine On

# Force HTTPS (uncomment when SSL is configured)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Compression
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

# Static File Caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/webp "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-javascript "access plus 1 month"
    ExpiresByType text/html "access plus 1 hour"
</IfModule>

# API Routing (for shared hosting with PHP proxy)
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

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

# Block access to backup files
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
</Directory>

# Error Pages
ErrorDocument 404 /404.html
ErrorDocument 500 /500.html
`;

fs.writeFileSync(path.join(publicHtml, '.htaccess'), htaccess);

// Create PHP proxy for shared hosting (basic version)
const phpProxy = `<?php
/**
 * Basic PHP Proxy for Riya Collections API
 * For shared hosting environments without Node.js support
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

// Basic health check
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);

if ($path === '/health') {
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'environment' => 'shared-hosting-php',
        'version' => '1.0.0',
        'message' => 'PHP proxy is running. Configure database and implement API endpoints.'
    ]);
} else {
    http_response_code(501);
    echo json_encode([
        'error' => 'API endpoint not implemented',
        'message' => 'Please configure your database and implement API endpoints in this PHP file.',
        'path' => $path
    ]);
}
?>`;

fs.writeFileSync(path.join(apiDir, 'index.php'), phpProxy);

// Create deployment instructions
const instructions = `# Riya Collections - Deployment Instructions

## Files Ready for Deployment!

Your Riya Collections e-commerce platform has been built and is ready for deployment.

### 📁 File Structure:
- \`dist/frontend/\` - Frontend application files
- \`dist/backend/\` - Backend API files  
- \`dist/hostinger-package/public_html/\` - Ready-to-upload files for Hostinger

### 🚀 Quick Deployment Steps:

#### For Hostinger Shared Hosting:
1. **Upload Files:**
   - Upload everything from \`dist/hostinger-package/public_html/\` to your domain's \`public_html\` folder
   - Use File Manager in hPanel or FTP client

2. **Database Setup:**
   - Create MySQL database in hPanel
   - Import \`backend/migrations/complete_schema.sql\`
   - Note your database credentials

3. **Configure Environment:**
   - Rename \`.env.production.template\` to \`.env\`
   - Update with your database credentials, email settings, etc.

4. **Test:**
   - Visit your domain
   - Check that the website loads
   - Test basic functionality

#### For VPS/Cloud Hosting:
1. **Upload Files:**
   - Upload \`dist/backend/\` to your server (e.g., \`/var/www/api/\`)
   - Upload \`dist/frontend/\` to your web root (e.g., \`/var/www/html/\`)

2. **Install Dependencies:**
   \`\`\`bash
   cd /var/www/api
   npm install --production
   \`\`\`

3. **Start Application:**
   \`\`\`bash
   pm2 start server.js --name "riya-collections"
   \`\`\`

### 📋 Required Configuration:

Before your site will work, you need to configure:

1. **Database Connection** - Update database credentials in \`.env\`
2. **Email Service** - Configure SMTP settings for notifications
3. **Payment Gateway** - Add your Razorpay production keys
4. **Domain Settings** - Update URLs to match your domain

### 🔧 Environment Variables to Configure:

\`\`\`env
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_database_password

JWT_SECRET=your_32_character_secret_key
SMTP_HOST=smtp.yourdomain.com
SMTP_USER=noreply@yourdomain.com
SMTP_PASSWORD=your_email_password

RAZORPAY_KEY_ID=rzp_live_your_key
RAZORPAY_KEY_SECRET=your_secret

FRONTEND_URL=https://yourdomain.com
BASE_URL=https://yourdomain.com
\`\`\`

### 🆘 Need Help?

Check the complete \`DEPLOYMENT_GUIDE.md\` for detailed instructions, troubleshooting, and advanced configuration options.

---

**Your e-commerce platform is ready to launch! 🎉**
`;

fs.writeFileSync(path.join(distDir, 'DEPLOYMENT_INSTRUCTIONS.md'), instructions);

// Create build report
const report = {
    timestamp: new Date().toISOString(),
    version: '1.0.0',
    environment: 'production',
    files: {
        frontend: fs.readdirSync(frontendDist).length,
        backend: fs.readdirSync(backendDist).length,
        hostinger: fs.readdirSync(publicHtml).length
    },
    size: calculateBuildSize()
};

fs.writeFileSync(path.join(distDir, 'build-report.json'), JSON.stringify(report, null, 2));

console.log('✅ Build completed successfully!');
console.log(`📊 Build size: ${report.size}`);
console.log('📁 Files ready in: dist/');
console.log('🚀 Hostinger package ready in: dist/hostinger-package/public_html/');
console.log('📖 Read DEPLOYMENT_INSTRUCTIONS.md for next steps');

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
    
    getDirectorySize(distDir);
    
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