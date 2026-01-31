# InfinityFree Deployment Guide

Complete guide to deploy Riya Collections on InfinityFree hosting.

## Prerequisites

- InfinityFree account (free hosting)
- FTP client (FileZilla recommended)
- MySQL database access
- Domain/subdomain configured

## Step 1: Prepare Your Files

### Backend Preparation
```bash
# Navigate to backend directory
cd riya-collections/backend

# Install production dependencies only
npm install --production

# Create deployment package
zip -r backend-deploy.zip . -x "node_modules/*" "*.log" "uploads/*"
```

### Frontend Preparation
```bash
# Navigate to frontend directory
cd ../frontend

# Create frontend package
zip -r frontend-deploy.zip . -x "node_modules/*" "*.log"
```

## Step 2: Database Setup

### Create MySQL Database
1. Login to InfinityFree control panel
2. Go to **MySQL Databases**
3. Create new database:
   - Database Name: `epiz_xxxxx_riya_collections`
   - Username: `epiz_xxxxx_riya`
   - Password: `[secure_password]`

### Import Database Schema
1. Open **phpMyAdmin** from control panel
2. Select your database
3. Import the following SQL:

```sql
-- Create users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    category_id INT,
    brand VARCHAR(255),
    stock_quantity INT DEFAULT 0,
    primary_image VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Create orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('razorpay', 'cod') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Face Makeup', 'Foundation, concealer, powder, and face makeup products'),
('Lip Care', 'Lipsticks, lip gloss, lip balm, and lip care products'),
('Hair Care', 'Shampoo, conditioner, hair oil, and hair styling products'),
('Skin Care', 'Face wash, moisturizer, serum, and skincare products');
```

## Step 3: File Upload via FTP

### FTP Connection Details
- **Host:** `files.000webhost.com` (or your InfinityFree FTP server)
- **Username:** Your InfinityFree username
- **Password:** Your InfinityFree password
- **Port:** 21

### Upload Structure
```
htdocs/
├── index.html              # Frontend home page
├── pages/                  # Frontend pages
├── src/                    # Frontend assets
├── assets/                 # Images and static files
├── api/                    # Backend API (PHP proxy)
│   ├── index.php          # Main API router
│   ├── config.php         # Database configuration
│   └── endpoints/         # API endpoint handlers
└── uploads/               # File uploads directory
```

### Upload Frontend Files
1. Extract `frontend-deploy.zip`
2. Upload all files to `htdocs/` directory
3. Ensure `index.html` is in root of `htdocs/`

### Upload Backend Files (PHP Proxy Method)
Since InfinityFree doesn't support Node.js, create PHP proxy files:

## Step 4: Create PHP API Proxy

### Create `htdocs/api/config.php`
```php
<?php
// Database configuration
define('DB_HOST', 'sql200.infinityfree.com'); // Your DB host
define('DB_NAME', 'epiz_xxxxx_riya_collections'); // Your DB name
define('DB_USER', 'epiz_xxxxx_riya'); // Your DB user
define('DB_PASS', 'your_password'); // Your DB password

// JWT Secret
define('JWT_SECRET', 'your_super_secure_jwt_secret_key_here');

// Razorpay Configuration
define('RAZORPAY_KEY_ID', 'your_razorpay_key_id');
define('RAZORPAY_KEY_SECRET', 'your_razorpay_secret');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}
?>
```

### Create `htdocs/api/index.php`
```php
<?php
require_once 'config.php';

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove /api from the beginning of the path
$path = str_replace('/api', '', parse_url($request_uri, PHP_URL_PATH));

// Route requests
switch ($path) {
    case '/products':
        if ($request_method === 'GET') {
            include 'endpoints/products.php';
        }
        break;
    
    case '/products/categories/all':
        if ($request_method === 'GET') {
            include 'endpoints/categories.php';
        }
        break;
    
    case '/auth/register':
        if ($request_method === 'POST') {
            include 'endpoints/auth.php';
        }
        break;
    
    case '/auth/login':
        if ($request_method === 'POST') {
            include 'endpoints/auth.php';
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
?>
```

### Create `htdocs/api/endpoints/products.php`
```php
<?php
try {
    // Get query parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    
    // Build query
    $sql = "SELECT p.*, c.name as category_name FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1";
    
    $params = [];
    
    if ($category) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format products
    $formatted_products = [];
    foreach ($products as $product) {
        $formatted_products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'originalPrice' => $product['original_price'] ? (float)$product['original_price'] : null,
            'brand' => $product['brand'],
            'stockQuantity' => (int)$product['stock_quantity'],
            'primaryImage' => $product['primary_image'],
            'category' => [
                'id' => $product['category_id'],
                'name' => $product['category_name']
            ],
            'rating' => 4.5, // Default rating
            'reviewCount' => 0 // Default review count
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'products' => $formatted_products,
            'total' => count($formatted_products),
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch products'
    ]);
}
?>
```

### Create `htdocs/api/endpoints/categories.php`
```php
<?php
try {
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format categories
    $formatted_categories = [];
    foreach ($categories as $category) {
        // Count products in category
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND is_active = 1");
        $count_stmt->execute([$category['id']]);
        $product_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $formatted_categories[] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'imageUrl' => $category['image_url'],
            'productCount' => (int)$product_count
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'categories' => $formatted_categories
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch categories'
    ]);
}
?>
```

## Step 5: Update Frontend Configuration

### Update `htdocs/src/js/config.js`
```javascript
// API Configuration for InfinityFree
const API_CONFIG = {
    BASE_URL: 'https://yourdomain.infinityfreeapp.com/api', // Replace with your domain
    ENDPOINTS: {
        // Products
        PRODUCTS: '/products',
        PRODUCT_DETAIL: '/products',
        CATEGORIES: '/products/categories/all',
        
        // Authentication
        REGISTER: '/auth/register',
        LOGIN: '/auth/login',
        LOGOUT: '/auth/logout',
        PROFILE: '/auth/profile',
        
        // Cart & Orders
        CART: '/cart',
        ORDERS: '/orders',
        
        // Payments
        RAZORPAY_CREATE: '/payments/razorpay/create',
        RAZORPAY_VERIFY: '/payments/razorpay/verify'
    }
};

// Export for use in other files
window.API_CONFIG = API_CONFIG;
```

## Step 6: Create .htaccess File

### Create `htdocs/.htaccess`
```apache
# Enable rewrite engine
RewriteEngine On

# Handle API routes
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Handle frontend routes (SPA)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteRule ^(.*)$ index.html [QSA,L]

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# Gzip compression
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

# Cache static files
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>
```

## Step 7: Upload Product Images

### Create Upload Directory Structure
```
htdocs/uploads/
├── products/
│   ├── small/
│   ├── medium/
│   └── large/
└── categories/
    ├── hero/
    ├── banner/
    ├── card/
    └── thumbnail/
```

### Upload Images
1. Create folders in `htdocs/uploads/`
2. Upload product images from `backend/uploads/products/`
3. Upload category images from `backend/uploads/categories/`

## Step 8: Test Deployment

### Test URLs
- **Homepage:** `https://yourdomain.infinityfreeapp.com`
- **API Test:** `https://yourdomain.infinityfreeapp.com/api/products/categories/all`
- **Products:** `https://yourdomain.infinityfreeapp.com/pages/products.html`

### Verification Checklist
- [ ] Homepage loads correctly
- [ ] Categories display properly
- [ ] Products load from database
- [ ] Images display correctly
- [ ] API endpoints respond
- [ ] Database connection works
- [ ] No console errors

## Step 9: Domain Configuration (Optional)

### Custom Domain Setup
1. Go to InfinityFree control panel
2. Navigate to **Subdomains** or **Addon Domains**
3. Add your custom domain
4. Update DNS records:
   - A Record: Point to InfinityFree IP
   - CNAME: www to your domain

### SSL Certificate
1. Go to **SSL Certificates** in control panel
2. Generate free SSL certificate
3. Install certificate
4. Force HTTPS redirect

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Check database credentials in `config.php`
- Verify database exists in phpMyAdmin
- Ensure database user has proper permissions

**API Not Working**
- Check `.htaccess` file is uploaded
- Verify API endpoints in `index.php`
- Check PHP error logs in control panel

**Images Not Loading**
- Verify upload directory permissions
- Check image paths in database
- Ensure images are uploaded to correct folders

**CORS Errors**
- Verify CORS headers in `config.php`
- Check API base URL in frontend config
- Ensure domain matches in CORS settings

### Performance Optimization

**Enable Caching**
```php
// Add to config.php
header('Cache-Control: public, max-age=3600');
```

**Optimize Images**
- Use WebP format when possible
- Compress images before upload
- Implement lazy loading

**Minify Assets**
- Minify CSS and JavaScript files
- Use CDN for external libraries
- Enable Gzip compression

## Support

For deployment issues:
1. Check InfinityFree documentation
2. Review PHP error logs
3. Test API endpoints individually
4. Verify database connections

## Security Notes

- Change all default passwords
- Use strong JWT secrets
- Implement rate limiting
- Validate all user inputs
- Keep PHP version updated
- Regular security audits

---

**Note:** InfinityFree has limitations on CPU usage and file operations. For high-traffic sites, consider upgrading to premium hosting with Node.js support.