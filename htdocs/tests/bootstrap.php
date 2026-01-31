<?php
/**
 * PHPUnit Bootstrap File
 * Sets up the testing environment for Riya Collections PHP Backend
 */

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Define test environment
define('TEST_ENV', true);

// Set test environment variables first
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Set up test database configuration
putenv('DB_HOST=localhost');
putenv('DB_NAME=riya_collections_test');
putenv('DB_USER=root');
putenv('DB_PASSWORD=');
putenv('JWT_SECRET=test_jwt_secret_for_testing_only_32_chars_minimum');
putenv('LOG_LEVEL=error');

$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'riya_collections_test';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASSWORD'] = '';
$_ENV['JWT_SECRET'] = 'test_jwt_secret_for_testing_only_32_chars_minimum';
$_ENV['LOG_LEVEL'] = 'error';

// Include autoloader if composer is installed
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Include core classes in correct order
$coreClasses = [
    __DIR__ . '/../config/environment.php',
    __DIR__ . '/../utils/Logger.php',
    __DIR__ . '/../utils/Response.php',
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../models/Database.php',
    __DIR__ . '/../services/AuthService.php',
    __DIR__ . '/../middleware/AuthMiddleware.php'
];

foreach ($coreClasses as $class) {
    if (file_exists($class)) {
        require_once $class;
    }
}

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Create a simple test database setup function
function setupTestDatabase() {
    try {
        // Use SQLite in-memory database for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create basic tables for testing
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                phone VARCHAR(20),
                role VARCHAR(50) DEFAULT 'customer',
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                stock_quantity INTEGER DEFAULT 0,
                category_id INTEGER,
                brand VARCHAR(100),
                sku VARCHAR(100) UNIQUE,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                image_url VARCHAR(255) NOT NULL,
                alt_text VARCHAR(255),
                is_primary BOOLEAN DEFAULT 0,
                sort_order INTEGER DEFAULT 0
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS emails (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                to_email VARCHAR(255) NOT NULL,
                from_email VARCHAR(255) NOT NULL,
                from_name VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                html_content TEXT,
                text_content TEXT,
                template VARCHAR(100),
                template_data TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                priority VARCHAR(20) DEFAULT 'normal',
                scheduled_at DATETIME,
                sent_at DATETIME NULL,
                failed_at DATETIME NULL,
                error_message TEXT NULL,
                smtp_response TEXT NULL,
                retry_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        return $pdo;
    } catch (Exception $e) {
        echo "Failed to setup test database: " . $e->getMessage() . "\n";
        return null;
    }
}

// Global test database instance
$GLOBALS['test_db'] = setupTestDatabase();

echo "Test environment initialized successfully.\n";