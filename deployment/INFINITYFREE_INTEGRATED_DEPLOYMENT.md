# Riya Collections Integrated Application - InfinityFree Deployment Guide

## Overview

This guide provides comprehensive instructions for deploying the integrated Riya Collections frontend-backend application to InfinityFree hosting. The integrated application combines the PHP backend and frontend into a unified structure where the PHP backend serves both API endpoints and frontend assets.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [InfinityFree Account Setup](#infinityfree-account-setup)
3. [Project Structure for InfinityFree](#project-structure-for-infinityfree)
4. [File Upload and Organization](#file-upload-and-organization)
5. [Environment Configuration](#environment-configuration)
6. [Database Setup](#database-setup)
7. [Web Server Configuration](#web-server-configuration)
8. [Frontend Asset Configuration](#frontend-asset-configuration)
9. [Testing the Deployment](#testing-the-deployment)
10. [Security Configuration](#security-configuration)
11. [Performance Optimization](#performance-optimization)
12. [Troubleshooting](#troubleshooting)
13. [Maintenance and Monitoring](#maintenance-and-monitoring)

## Prerequisites

### Required Items
- InfinityFree hosting account (free or premium)
- Domain name configured with InfinityFree
- FTP client or File Manager access
- Local copy of the integrated Riya Collections application
- MySQL database credentials from InfinityFree
- Email account for SMTP configuration
- Razorpay account for payment processing (optional)

### System Requirements
- PHP 7.4+ (InfinityFree supports PHP 8.0+)
- MySQL 5.7+ database
- SSL certificate (free with InfinityFree)
- Minimum 100MB storage space
- Basic understanding of file management

## InfinityFree Account Setup

### 1. Create InfinityFree Account
1. Visit [InfinityFree.net](https://infinityfree.net)
2. Sign up for a free account
3. Verify your email address
4. Access the control panel

### 2. Create Website
1. In control panel, click "Create Account"
2. Choose your subdomain or use custom domain
3. Wait for account activation (usually instant)
4. Note your account details:
   - **Account Username:** `epiz_XXXXXXXX`
   - **FTP Hostname:** `ftpupload.net`
   - **Website URL:** `https://your-domain.infinityfreeapp.com`

### 3. Create MySQL Database
1. Go to "MySQL Databases" in control panel
2. Click "Create Database"
3. Enter database name: `riya_collections`
4. Note the generated details:
   - **Database Name:** `epiz_XXXXXXXX_riya_collections`
   - **Database User:** `epiz_XXXXXXXX`
   - **Database Host:** `sqlXXX.infinityfree.com`
   - **Password:** (set your own secure password)

### 4. Enable SSL Certificate
1. Go to "SSL Certificates"
2. Click "Create Certificate"
3. Choose "Let's Encrypt" (free)
4. Wait for certificate activation
5. Enable "Force HTTPS" option

## Project Structure for InfinityFree

The integrated application needs to be organized for InfinityFree's file structure:

```
htdocs/                             # InfinityFree web root
├── index.php                      # Main entry point (from public/)
├── .htaccess                       # Web server configuration
├── assets/                         # Frontend static assets (from public/assets/)
│   ├── css/                        # Stylesheets
│   ├── js/                         # JavaScript files
│   ├── images/                     # Image assets
│   └── fonts/                      # Font files
├── pages/                          # Frontend HTML pages (from public/pages/)
├── uploads/                        # User uploaded files (from public/uploads/)
├── app/                            # Application logic (PROTECTED)
│   ├── controllers/                # PHP controllers
│   ├── models/                     # Data models
│   ├── services/                   # Business logic & integration services
│   ├── middleware/                 # Request middleware
│   └── config/                     # Configuration files
├── storage/                        # Storage directory (PROTECTED)
│   ├── logs/                       # Application logs
│   ├── cache/                      # Cache files
│   └── backups/                    # Database backups
├── logs/                          # Additional logs
├── vendor/                        # Composer dependencies (PROTECTED)
└── .env                           # Environment configuration (PROTECTED)
```

## File Upload and Organization

### Method 1: File Manager (Recommended)

1. **Access File Manager:**
   - Log into InfinityFree control panel
   - Click "File Manager"
   - Navigate to `htdocs` folder

2. **Upload Project Files:**
   ```
   # Upload these directories/files to htdocs/:
   - public/index.php → htdocs/index.php
   - public/assets/ → htdocs/assets/
   - public/pages/ → htdocs/pages/
   - public/uploads/ → htdocs/uploads/
   - app/ → htdocs/app/
   - storage/ → htdocs/storage/
   - logs/ → htdocs/logs/
   - vendor/ → htdocs/vendor/
   - .env.example → htdocs/.env (rename and configure)
   ```

3. **Create .htaccess:**
   - Copy content from `app/config/webserver/.htaccess.production`
   - Upload as `htdocs/.htaccess`

### Method 2: FTP Upload

1. **Connect via FTP:**
   ```bash
   # FTP Details from InfinityFree control panel
   Host: ftpupload.net
   Username: epiz_XXXXXXXX
   Password: your_ftp_password
   Port: 21
   ```

2. **Upload Files:**
   ```bash
   # Using command line FTP
   ftp ftpupload.net
   # Enter credentials
   cd htdocs
   
   # Upload files (adjust paths to your local project)
   put public/index.php index.php
   mkdir assets
   cd assets
   mput public/assets/*
   cd ..
   mkdir app
   cd app
   mput -r app/*
   ```

### Method 3: ZIP Upload

1. **Create Deployment Package:**
   ```bash
   # Create a zip file with the correct structure
   mkdir infinityfree-deploy
   cp public/index.php infinityfree-deploy/
   cp -r public/assets infinityfree-deploy/
   cp -r public/pages infinityfree-deploy/
   cp -r public/uploads infinityfree-deploy/
   cp -r app infinityfree-deploy/
   cp -r storage infinityfree-deploy/
   cp -r logs infinityfree-deploy/
   cp -r vendor infinityfree-deploy/
   cp app/config/webserver/.htaccess.production infinityfree-deploy/.htaccess
   
   # Create zip
   zip -r riya-collections-infinityfree.zip infinityfree-deploy/
   ```

2. **Upload and Extract:**
   - Upload zip file via File Manager
   - Extract to `htdocs` directory
   - Delete zip file after extraction

## Environment Configuration

### 1. Create .env File

Create `.env` file in `htdocs/` with InfinityFree-specific configuration:

```env
# Riya Collections Integrated Application - InfinityFree Configuration

# Application Configuration
APP_NAME="Riya Collections"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.infinityfreeapp.com
APP_TIMEZONE=Asia/Kolkata
APP_LOCALE=en
APP_VERSION=1.0.0

# Database Configuration (Update with your InfinityFree database details)
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_NAME=epiz_XXXXXXXX_riya_collections
DB_USER=epiz_XXXXXXXX
DB_PASSWORD=your_secure_database_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_CONNECTION_LIMIT=3

# JWT Configuration (IMPORTANT: Generate strong secrets)
JWT_SECRET=your_super_secure_jwt_secret_minimum_32_characters_long_for_production
JWT_EXPIRES_IN=24h
JWT_REFRESH_SECRET=different_secure_refresh_secret_key_minimum_32_characters
JWT_REFRESH_EXPIRES_IN=7d
JWT_ISSUER=riya-collections
JWT_AUDIENCE=riya-collections-users

# Password Hashing
BCRYPT_SALT_ROUNDS=12

# Email Configuration (Gmail example with App Password)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=false
SMTP_USER=your-business-email@gmail.com
SMTP_PASSWORD=your-gmail-app-password

# Company Email Settings
COMPANY_NAME="Riya Collections"
COMPANY_EMAIL=orders@your-domain.com
SUPPORT_EMAIL=support@your-domain.com
WEBSITE_URL=https://your-domain.infinityfreeapp.com
LOGO_URL=https://your-domain.infinityfreeapp.com/assets/logo.png

# Razorpay Configuration (Optional - for payments)
RAZORPAY_KEY_ID=rzp_live_XXXXXXXXXXXXXXXX
RAZORPAY_KEY_SECRET=your_razorpay_secret_key
RAZORPAY_WEBHOOK_SECRET=your_webhook_secret_from_razorpay
RAZORPAY_CURRENCY=INR

# File Upload Configuration (InfinityFree optimized)
UPLOAD_PATH=uploads
MAX_FILE_SIZE=1048576
ALLOWED_FILE_TYPES=image/jpeg,image/png,image/webp
IMAGE_QUALITY=75
MAX_IMAGE_WIDTH=1000
MAX_IMAGE_HEIGHT=800

# Security Configuration (Shared hosting optimized)
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=30
SESSION_SECRET=your_secure_session_secret_key_here
ALLOWED_ORIGINS=https://your-domain.infinityfreeapp.com

# PHP Configuration (InfinityFree limits)
PHP_MEMORY_LIMIT=64M
PHP_MAX_EXECUTION_TIME=30
PHP_MAX_INPUT_TIME=30

# Logging Configuration (Minimal for shared hosting)
LOG_LEVEL=error
LOG_FILE=logs/app.log
LOG_MAX_FILES=3
LOG_MAX_SIZE=2MB
LOG_DAILY_ROTATION=true
LOG_FORMAT=simple

# InfinityFree Specific Optimizations
CACHE_ENABLED=true
CACHE_TTL=1800
DB_QUERY_CACHE=true
OPTIMIZE_IMAGES=true
COMPRESS_RESPONSES=true

# Frontend Configuration
FRONTEND_URL=https://your-domain.infinityfreeapp.com
API_BASE_URL=/api

# Backup Configuration
BACKUP_ENABLED=false
BACKUP_RETENTION_DAYS=3
BACKUP_PATH=storage/backups

# Monitoring Configuration
HEALTH_CHECK_ENABLED=true
PERFORMANCE_MONITORING=false
ERROR_REPORTING_EMAIL=admin@your-domain.com

# Development Settings (Keep false for production)
ENABLE_CORS_ALL=false
ENABLE_DEBUG_TOOLBAR=false
SHOW_ERROR_DETAILS=false
```

### 2. Secure Environment File

Set proper permissions for the `.env` file:
```bash
# Via File Manager: Right-click .env → Permissions → 644
# Via FTP: SITE CHMOD 644 .env
```

## Database Setup

### 1. Access phpMyAdmin

1. Go to InfinityFree control panel
2. Click "MySQL Databases"
3. Click "phpMyAdmin" next to your database
4. Login with your database credentials

### 2. Import Database Schema

1. **Create Schema File:**
   Create `database_schema.sql` with the complete database structure:

```sql
-- Riya Collections Integrated Application Database Schema
-- Optimized for InfinityFree MySQL

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_verification` (`verification_token`),
  KEY `idx_reset` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `short_description` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `manage_stock` tinyint(1) DEFAULT 1,
  `in_stock` tinyint(1) DEFAULT 1,
  `weight` decimal(8,2) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_featured` (`featured`),
  KEY `idx_stock` (`in_stock`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images table
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_primary` (`is_primary`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'INR',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `shipping_address` text,
  `billing_address` text,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items table
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Addresses table
CREATE TABLE `addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('billing','shipping') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company` varchar(100) DEFAULT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) DEFAULT 'India',
  `phone` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_default` (`is_default`),
  CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `payment_id` varchar(255) NOT NULL,
  `razorpay_order_id` varchar(255) DEFAULT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'INR',
  `status` enum('created','authorized','captured','refunded','failed') DEFAULT 'created',
  `method` varchar(50) DEFAULT NULL,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_id` (`payment_id`),
  KEY `order_id` (`order_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample categories
INSERT INTO `categories` (`name`, `slug`, `description`, `is_active`, `sort_order`) VALUES
('Sarees', 'sarees', 'Traditional Indian sarees in various fabrics and designs', 1, 1),
('Lehengas', 'lehengas', 'Designer lehengas for special occasions', 1, 2),
('Suits', 'suits', 'Elegant suits and dress materials', 1, 3),
('Accessories', 'accessories', 'Jewelry and fashion accessories', 1, 4);

COMMIT;
```

2. **Import Schema:**
   - In phpMyAdmin, select your database
   - Go to "Import" tab
   - Choose the `database_schema.sql` file
   - Click "Go" to import

### 3. Verify Database Connection

Create a test file `test_db.php` in `htdocs/`:

```php
<?php
// Test database connection
require_once 'app/config/environment.php';

try {
    $host = env('DB_HOST');
    $dbname = env('DB_NAME');
    $username = env('DB_USER');
    $password = env('DB_PASSWORD');
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful!<br>";
    echo "Host: $host<br>";
    echo "Database: $dbname<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $result = $stmt->fetch();
    echo "Categories in database: " . $result['count'] . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage();
}

// Delete this file after testing for security
?>
```

Visit `https://your-domain.infinityfreeapp.com/test_db.php` to test, then delete the file.

## Web Server Configuration

### 1. Create Optimized .htaccess

Create `htdocs/.htaccess` with InfinityFree-optimized configuration:

```apache
# Riya Collections Integrated Application - InfinityFree .htaccess
# Optimized for InfinityFree hosting with frontend-backend integration

RewriteEngine On

# Force HTTPS (InfinityFree SSL)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self'"
    Header unset Server
    Header unset X-Powered-By
</IfModule>

# Protect sensitive files and directories
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.lock">
    Order allow,deny
    Deny from all
</Files>

# Protect directories
RewriteRule ^(app|storage|logs|vendor)/ - [F,L]

# Static asset serving with caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
    ExpiresByType font/woff "access plus 1 month"
    ExpiresByType font/woff2 "access plus 1 month"
</IfModule>

# Compression (if supported)
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Main routing - everything goes to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# PHP Configuration (InfinityFree limits)
php_value upload_max_filesize 1M
php_value post_max_size 1M
php_value memory_limit 64M
php_value max_execution_time 30
php_value max_input_time 30

# Disable directory browsing
Options -Indexes

# Custom error pages
ErrorDocument 404 /index.php
ErrorDocument 500 /index.php
```

### 2. Set File Permissions

Set appropriate permissions for InfinityFree:

```bash
# Directories: 755
# Files: 644
# .env file: 600 (if possible, otherwise 644)

# Via File Manager:
# Right-click each directory → Permissions → 755
# Right-click each file → Permissions → 644
# Right-click .env → Permissions → 600
```

## Frontend Asset Configuration

### 1. Update Frontend Configuration

Ensure frontend assets are properly configured for the integrated structure. The `app/services/FrontendConfigManager.php` should generate correct paths:

```php
// This should already be configured in your integrated application
// The FrontendConfigManager will serve configuration at /api/config
```

### 2. Verify Asset Paths

Check that frontend JavaScript files use correct API paths:

```javascript
// In your frontend JS files, ensure API calls use relative paths:
// Good: fetch('/api/products')
// Bad: fetch('http://localhost/api/products')
```

### 3. Test Static Asset Serving

Create a test file to verify asset serving works:

```html
<!-- Create test.html in htdocs/ -->
<!DOCTYPE html>
<html>
<head>
    <title>Asset Test</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Testing Static Assets</h1>
    <img src="/assets/images/logo.png" alt="Logo">
    <script src="/assets/js/app.js"></script>
</body>
</html>
```

## Testing the Deployment

### 1. Basic Functionality Tests

1. **Frontend Application:**
   ```
   Visit: https://your-domain.infinityfreeapp.com/
   Expected: Main application loads with proper styling
   ```

2. **API Health Check:**
   ```
   Visit: https://your-domain.infinityfreeapp.com/api/health
   Expected: JSON response with system status
   ```

3. **Static Assets:**
   ```
   Visit: https://your-domain.infinityfreeapp.com/assets/css/style.css
   Expected: CSS file loads with proper MIME type
   ```

### 2. Integration Tests

1. **Frontend-Backend Communication:**
   - Test product listing page
   - Test user registration/login
   - Test API calls from frontend

2. **SPA Routing:**
   - Navigate between frontend pages
   - Refresh browser on frontend routes
   - Test browser back/forward buttons

3. **File Uploads:**
   - Test image upload functionality
   - Verify uploaded files are accessible
   - Check file size limits

### 3. Performance Tests

```bash
# Test page load times
curl -w "@curl-format.txt" -o /dev/null -s https://your-domain.infinityfreeapp.com/

# Test API response times
curl -w "@curl-format.txt" -o /dev/null -s https://your-domain.infinityfreeapp.com/api/health

# Test static asset caching
curl -I https://your-domain.infinityfreeapp.com/assets/css/style.css
```

### 4. Automated Testing Script

Create `test_deployment.php` for comprehensive testing:

```php
<?php
// Comprehensive deployment test
require_once 'app/config/environment.php';

$tests = [];

// Test 1: Database Connection
try {
    require_once 'app/models/Database.php';
    $db = Database::getInstance();
    $connection = $db->getConnection();
    $tests['database'] = '✅ Database connection successful';
} catch (Exception $e) {
    $tests['database'] = '❌ Database connection failed: ' . $e->getMessage();
}

// Test 2: Environment Configuration
$requiredEnvVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'JWT_SECRET'];
$missingVars = [];
foreach ($requiredEnvVars as $var) {
    if (empty(env($var))) {
        $missingVars[] = $var;
    }
}
$tests['environment'] = empty($missingVars) ? 
    '✅ Environment configuration complete' : 
    '❌ Missing environment variables: ' . implode(', ', $missingVars);

// Test 3: File Permissions
$writableDirs = ['uploads', 'logs', 'storage/cache'];
$permissionIssues = [];
foreach ($writableDirs as $dir) {
    if (!is_writable($dir)) {
        $permissionIssues[] = $dir;
    }
}
$tests['permissions'] = empty($permissionIssues) ? 
    '✅ File permissions correct' : 
    '❌ Permission issues: ' . implode(', ', $permissionIssues);

// Test 4: Integration Classes
$integrationClasses = ['AssetServer', 'SPARouteHandler', 'FrontendConfigManager'];
$missingClasses = [];
foreach ($integrationClasses as $class) {
    if (!class_exists($class)) {
        $missingClasses[] = $class;
    }
}
$tests['integration'] = empty($missingClasses) ? 
    '✅ Integration classes loaded' : 
    '❌ Missing classes: ' . implode(', ', $missingClasses);

// Output results
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Deployment Test Results</h1>";
foreach ($tests as $test => $result) {
    echo "<p><strong>" . ucfirst($test) . ":</strong> $result</p>";
}

// Delete this file after testing
echo "<p><em>Delete this file after testing for security.</em></p>";
?>
```

## Security Configuration

### 1. File Security

Ensure sensitive files are protected:

```apache
# Already included in .htaccess, but verify:
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

# Test protection by visiting:
# https://your-domain.infinityfreeapp.com/.env (should return 403/404)
```

### 2. Directory Protection

Verify protected directories:

```bash
# These should return 403/404:
# https://your-domain.infinityfreeapp.com/app/
# https://your-domain.infinityfreeapp.com/storage/
# https://your-domain.infinityfreeapp.com/logs/
# https://your-domain.infinityfreeapp.com/vendor/
```

### 3. SSL Configuration

1. Verify SSL certificate is active
2. Test HTTPS redirect
3. Check security headers in browser dev tools

### 4. Input Validation

Ensure all user inputs are properly validated and sanitized in your PHP code.

## Performance Optimization

### 1. InfinityFree Resource Limits

InfinityFree has specific limits:
- **CPU Usage:** Limited per hour
- **Memory:** 64MB per script
- **Execution Time:** 30 seconds max
- **Database Queries:** Limited per hour
- **File Size:** 10MB max upload

### 2. Optimization Strategies

1. **Database Optimization:**
   ```php
   // Use connection pooling
   // Optimize queries with proper indexes
   // Use pagination for large datasets
   // Cache frequently accessed data
   ```

2. **Image Optimization:**
   ```php
   // Compress images before upload
   // Use WebP format when possible
   // Implement lazy loading
   // Serve responsive images
   ```

3. **Caching:**
   ```php
   // Enable output caching
   // Cache database query results
   // Use browser caching for static assets
   ```

### 3. Monitor Resource Usage

1. Check InfinityFree control panel for resource usage
2. Monitor error logs for performance issues
3. Optimize code based on usage patterns

## Troubleshooting

### Common Issues and Solutions

#### 1. Database Connection Errors

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

**Solutions:**
- Verify database credentials in `.env`
- Check InfinityFree database hostname
- Ensure database exists and user has permissions
- Try connecting via phpMyAdmin first

#### 2. File Permission Errors

**Error:** `Warning: file_put_contents(): Permission denied`

**Solutions:**
- Set directory permissions to 755
- Set file permissions to 644
- Ensure web server can write to logs/ and uploads/
- Contact InfinityFree support if issues persist

#### 3. .htaccess Errors

**Error:** `Internal Server Error (500)`

**Solutions:**
- Check .htaccess syntax
- Verify mod_rewrite is enabled
- Simplify .htaccess rules if needed
- Check error logs for specific issues

#### 4. Memory Limit Errors

**Error:** `Fatal error: Allowed memory size exhausted`

**Solutions:**
- Optimize code for lower memory usage
- Reduce image sizes
- Use pagination for large datasets
- Consider upgrading to premium hosting

#### 5. Frontend Assets Not Loading

**Error:** 404 errors for CSS/JS files

**Solutions:**
- Verify asset paths in HTML
- Check file permissions
- Ensure AssetServer class is working
- Test direct asset URLs

#### 6. API Endpoints Not Working

**Error:** 404 or 500 errors on API calls

**Solutions:**
- Check .htaccess rewrite rules
- Verify database connection
- Check PHP error logs
- Test API endpoints directly

### Debug Mode

For troubleshooting, temporarily enable debug mode:

```env
# In .env file
APP_DEBUG=true
LOG_LEVEL=debug
```

**Important:** Disable debug mode after troubleshooting!

### Log Analysis

Check logs for issues:

1. **Application Logs:** `logs/app.log`
2. **Error Logs:** `logs/error.log`
3. **InfinityFree Logs:** Available in control panel

## Maintenance and Monitoring

### 1. Regular Maintenance Tasks

1. **Monitor Resource Usage:**
   - Check InfinityFree control panel daily
   - Monitor CPU and database usage
   - Watch for suspension warnings

2. **Update Dependencies:**
   - Keep Composer packages updated
   - Monitor security advisories
   - Test updates in staging first

3. **Database Maintenance:**
   - Regular backups via phpMyAdmin
   - Optimize database tables monthly
   - Monitor database size limits

4. **Security Updates:**
   - Monitor PHP security updates
   - Update application code regularly
   - Review access logs for suspicious activity

### 2. Monitoring Setup

1. **Uptime Monitoring:**
   - Use external services like UptimeRobot
   - Monitor main application and API endpoints
   - Set up email alerts

2. **Performance Monitoring:**
   - Monitor page load times
   - Track API response times
   - Watch for resource limit warnings

3. **Error Monitoring:**
   - Check error logs regularly
   - Set up email notifications for critical errors
   - Monitor 404 and 500 error rates

### 3. Backup Strategy

1. **Database Backups:**
   ```bash
   # Via phpMyAdmin: Export → SQL → Go
   # Schedule regular exports
   # Store backups securely
   ```

2. **File Backups:**
   ```bash
   # Download critical files regularly:
   # - .env file
   # - uploads/ directory
   # - custom code changes
   ```

3. **Automated Backups:**
   - Use InfinityFree backup features
   - Consider third-party backup services
   - Test backup restoration regularly

### 4. Scaling Considerations

When your application grows:

1. **Monitor Limits:**
   - Track resource usage trends
   - Plan for traffic growth
   - Monitor database size

2. **Optimization:**
   - Implement caching strategies
   - Optimize database queries
   - Compress images and assets

3. **Upgrade Path:**
   - Consider InfinityFree premium plans
   - Plan migration to VPS if needed
   - Prepare for increased traffic

## Support and Resources

### InfinityFree Resources
- **Documentation:** [https://infinityfree.net/support](https://infinityfree.net/support)
- **Community Forum:** [https://forum.infinityfree.net/](https://forum.infinityfree.net/)
- **Knowledge Base:** Available in control panel
- **Ticket System:** For premium support

### Development Resources
- **PHP Documentation:** [https://php.net/docs.php](https://php.net/docs.php)
- **MySQL Documentation:** [https://dev.mysql.com/doc/](https://dev.mysql.com/doc/)
- **Composer:** [https://getcomposer.org/doc/](https://getcomposer.org/doc/)

### Emergency Contacts
- **InfinityFree Support:** Via control panel
- **Development Team:** [Your contact information]
- **Domain Registrar:** [Your domain provider]

## Conclusion

This guide provides comprehensive instructions for deploying the integrated Riya Collections application to InfinityFree hosting. The integrated structure allows the PHP backend to serve both API endpoints and frontend assets efficiently within InfinityFree's resource constraints.

### Key Success Factors:
1. **Proper file organization** for InfinityFree structure
2. **Optimized configuration** for shared hosting limits
3. **Comprehensive testing** of all integration features
4. **Regular monitoring** and maintenance
5. **Security best practices** for shared hosting

### Next Steps After Deployment:
1. Test all functionality thoroughly
2. Set up monitoring and alerts
3. Configure regular backups
4. Train users on the system
5. Plan for future scaling needs

Remember to always test changes in a staging environment before applying them to production, and keep regular backups of both your database and files.

---

**Important Notes:**
- InfinityFree has resource limitations that may affect performance
- Monitor usage regularly to avoid account suspension
- Consider upgrading to premium hosting for production use
- Always maintain backups and have a rollback plan
- Test thoroughly before going live with real users