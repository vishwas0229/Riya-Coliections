<?php
/**
 * Database Migration Script
 * 
 * This script handles database schema creation and updates for deployment.
 * Run this after uploading files to ensure database is properly configured.
 * 
 * Requirements: 14.1, 14.3
 * 
 * SECURITY WARNING: Delete this file after successful deployment!
 */

// Prevent direct access in production
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    die('Access denied. Add ?confirm=yes to run migration.');
}

// Load configuration
require_once __DIR__ . '/../../config/environment.php';
require_once __DIR__ . '/../../models/Database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Riya Collections</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007cba; }
    </style>
</head>
<body>
    <h1>Riya Collections - Database Migration</h1>
    <p>This script will create and update the database schema for your deployment.</p>
    
    <?php
    
    $errors = [];
    $warnings = [];
    $success = [];
    
    try {
        echo "<div class='step'>";
        echo "<h2>Step 1: Environment Validation</h2>";
        
        // Validate environment
        $config = getDatabaseConfig();
        
        if (empty($config['host']) || empty($config['database']) || empty($config['username'])) {
            throw new Exception('Database configuration is incomplete. Check your .env file.');
        }
        
        echo "<p class='success'>✓ Environment configuration loaded</p>";
        echo "<p class='info'>Database: {$config['database']} on {$config['host']}</p>";
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 2: Database Connection</h2>";
        
        // Test database connection
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        echo "<p class='success'>✓ Database connection successful</p>";
        
        // Check database version
        $stmt = $connection->query("SELECT VERSION() as version");
        $version = $stmt->fetch()['version'];
        echo "<p class='info'>MySQL Version: {$version}</p>";
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 3: Schema Creation</h2>";
        
        // Create tables
        $tables = [
            'users' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    phone VARCHAR(20),
                    email_verified BOOLEAN DEFAULT FALSE,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'categories' => "
                CREATE TABLE IF NOT EXISTS categories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    slug VARCHAR(100) UNIQUE NOT NULL,
                    description TEXT,
                    parent_id INT NULL,
                    sort_order INT DEFAULT 0,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
                    INDEX idx_slug (slug),
                    INDEX idx_parent (parent_id),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'products' => "
                CREATE TABLE IF NOT EXISTS products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) UNIQUE NOT NULL,
                    description TEXT,
                    short_description VARCHAR(500),
                    sku VARCHAR(100) UNIQUE,
                    price DECIMAL(10,2) NOT NULL,
                    compare_price DECIMAL(10,2),
                    cost_price DECIMAL(10,2),
                    stock_quantity INT DEFAULT 0,
                    track_stock BOOLEAN DEFAULT TRUE,
                    weight DECIMAL(8,2),
                    dimensions VARCHAR(100),
                    category_id INT,
                    brand VARCHAR(100),
                    tags TEXT,
                    meta_title VARCHAR(255),
                    meta_description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    is_featured BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
                    INDEX idx_slug (slug),
                    INDEX idx_category (category_id),
                    INDEX idx_active (is_active),
                    INDEX idx_featured (is_featured),
                    INDEX idx_price (price),
                    INDEX idx_stock (stock_quantity),
                    FULLTEXT idx_search (name, description, tags)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'product_images' => "
                CREATE TABLE IF NOT EXISTS product_images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    image_url VARCHAR(500) NOT NULL,
                    alt_text VARCHAR(255),
                    is_primary BOOLEAN DEFAULT FALSE,
                    sort_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                    INDEX idx_product (product_id),
                    INDEX idx_primary (is_primary)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'addresses' => "
                CREATE TABLE IF NOT EXISTS addresses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type ENUM('billing', 'shipping') DEFAULT 'shipping',
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    company VARCHAR(100),
                    address_line1 VARCHAR(255) NOT NULL,
                    address_line2 VARCHAR(255),
                    city VARCHAR(100) NOT NULL,
                    state VARCHAR(100) NOT NULL,
                    postal_code VARCHAR(20) NOT NULL,
                    country VARCHAR(100) DEFAULT 'India',
                    phone VARCHAR(20),
                    is_default BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user (user_id),
                    INDEX idx_type (type),
                    INDEX idx_default (is_default)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'orders' => "
                CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_number VARCHAR(50) UNIQUE NOT NULL,
                    user_id INT NOT NULL,
                    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
                    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
                    payment_method ENUM('razorpay', 'cod') NOT NULL,
                    subtotal DECIMAL(10,2) NOT NULL,
                    tax_amount DECIMAL(10,2) DEFAULT 0,
                    shipping_amount DECIMAL(10,2) DEFAULT 0,
                    discount_amount DECIMAL(10,2) DEFAULT 0,
                    total_amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT 'INR',
                    shipping_address_id INT,
                    billing_address_id INT,
                    notes TEXT,
                    shipped_at TIMESTAMP NULL,
                    delivered_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                    FOREIGN KEY (shipping_address_id) REFERENCES addresses(id) ON DELETE SET NULL,
                    FOREIGN KEY (billing_address_id) REFERENCES addresses(id) ON DELETE SET NULL,
                    INDEX idx_order_number (order_number),
                    INDEX idx_user (user_id),
                    INDEX idx_status (status),
                    INDEX idx_payment_status (payment_status),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'order_items' => "
                CREATE TABLE IF NOT EXISTS order_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    product_id INT NOT NULL,
                    quantity INT NOT NULL,
                    unit_price DECIMAL(10,2) NOT NULL,
                    total_price DECIMAL(10,2) NOT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    product_sku VARCHAR(100),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
                    INDEX idx_order (order_id),
                    INDEX idx_product (product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'payments' => "
                CREATE TABLE IF NOT EXISTS payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    payment_method ENUM('razorpay', 'cod') NOT NULL,
                    payment_gateway VARCHAR(50),
                    gateway_transaction_id VARCHAR(255),
                    gateway_order_id VARCHAR(255),
                    gateway_payment_id VARCHAR(255),
                    gateway_signature VARCHAR(500),
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT 'INR',
                    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
                    gateway_response TEXT,
                    processed_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
                    INDEX idx_order (order_id),
                    INDEX idx_gateway_transaction (gateway_transaction_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'admin_users' => "
                CREATE TABLE IF NOT EXISTS admin_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) UNIQUE NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    role ENUM('super_admin', 'admin', 'manager') DEFAULT 'admin',
                    permissions TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    last_login_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_username (username),
                    INDEX idx_email (email),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'email_logs' => "
                CREATE TABLE IF NOT EXISTS email_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    to_email VARCHAR(255) NOT NULL,
                    from_email VARCHAR(255) NOT NULL,
                    subject VARCHAR(500) NOT NULL,
                    template VARCHAR(100),
                    status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
                    error_message TEXT,
                    sent_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_to_email (to_email),
                    INDEX idx_status (status),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $connection->exec($sql);
                echo "<p class='success'>✓ Table '{$tableName}' created/verified</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>✗ Error creating table '{$tableName}': " . $e->getMessage() . "</p>";
                $errors[] = "Table creation failed: {$tableName}";
            }
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 4: Default Data</h2>";
        
        // Insert default admin user if not exists
        $stmt = $connection->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
        $stmt->execute(['admin']);
        
        if ($stmt->fetchColumn() == 0) {
            $defaultPassword = 'admin123'; // Change this!
            $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);
            
            $stmt = $connection->prepare("
                INSERT INTO admin_users (username, email, password_hash, first_name, last_name, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                'admin',
                'admin@riyacollections.com',
                $hashedPassword,
                'Admin',
                'User',
                'super_admin'
            ]);
            
            echo "<p class='success'>✓ Default admin user created</p>";
            echo "<p class='warning'>⚠ Default admin credentials: admin / admin123 (CHANGE IMMEDIATELY!)</p>";
        } else {
            echo "<p class='info'>ℹ Admin user already exists</p>";
        }
        
        // Insert default categories if not exists
        $stmt = $connection->prepare("SELECT COUNT(*) FROM categories");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $defaultCategories = [
                ['Women\'s Clothing', 'womens-clothing', 'Fashion and clothing for women'],
                ['Men\'s Clothing', 'mens-clothing', 'Fashion and clothing for men'],
                ['Accessories', 'accessories', 'Fashion accessories and jewelry'],
                ['Footwear', 'footwear', 'Shoes and sandals for all']
            ];
            
            $stmt = $connection->prepare("
                INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)
            ");
            
            foreach ($defaultCategories as $category) {
                $stmt->execute($category);
            }
            
            echo "<p class='success'>✓ Default categories created</p>";
        } else {
            echo "<p class='info'>ℹ Categories already exist</p>";
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 5: Database Optimization</h2>";
        
        // Optimize tables
        $stmt = $connection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            try {
                $connection->exec("OPTIMIZE TABLE `{$table}`");
                echo "<p class='success'>✓ Optimized table '{$table}'</p>";
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠ Could not optimize table '{$table}': " . $e->getMessage() . "</p>";
            }
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 6: Verification</h2>";
        
        // Verify installation
        $verificationQueries = [
            'Users table' => "SELECT COUNT(*) FROM users",
            'Products table' => "SELECT COUNT(*) FROM products",
            'Orders table' => "SELECT COUNT(*) FROM orders",
            'Admin users' => "SELECT COUNT(*) FROM admin_users",
            'Categories' => "SELECT COUNT(*) FROM categories"
        ];
        
        foreach ($verificationQueries as $description => $query) {
            try {
                $stmt = $connection->query($query);
                $count = $stmt->fetchColumn();
                echo "<p class='success'>✓ {$description}: {$count} records</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>✗ {$description} verification failed: " . $e->getMessage() . "</p>";
                $errors[] = "Verification failed: {$description}";
            }
        }
        
        echo "</div>";
        
        if (empty($errors)) {
            echo "<div class='step'>";
            echo "<h2>✅ Migration Completed Successfully!</h2>";
            echo "<p class='success'>Your database has been set up successfully.</p>";
            echo "<h3>Next Steps:</h3>";
            echo "<ul>";
            echo "<li>Delete this migration script for security</li>";
            echo "<li>Change the default admin password</li>";
            echo "<li>Test your API endpoints</li>";
            echo "<li>Configure your frontend application</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='step'>";
            echo "<h2>⚠ Migration Completed with Errors</h2>";
            echo "<p class='error'>Some errors occurred during migration:</p>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li class='error'>{$error}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='step'>";
        echo "<h2>❌ Migration Failed</h2>";
        echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
        echo "<p>Please check your database configuration and try again.</p>";
        echo "</div>";
    }
    
    ?>
    
    <div class="step">
        <h2>Security Notice</h2>
        <p class="warning"><strong>IMPORTANT:</strong> Delete this file after successful migration!</p>
        <p>This script should not be accessible in production for security reasons.</p>
        <pre>rm <?php echo __FILE__; ?></pre>
    </div>
    
    <div class="step">
        <h2>Support</h2>
        <p>If you encounter issues:</p>
        <ul>
            <li>Check your .env configuration</li>
            <li>Verify database credentials</li>
            <li>Check PHP error logs</li>
            <li>Contact support if problems persist</li>
        </ul>
    </div>
    
</body>
</html>