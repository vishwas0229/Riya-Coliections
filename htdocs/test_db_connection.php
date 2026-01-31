<?php
/**
 * Simple Database Connection Test
 */

require_once __DIR__ . '/config/environment.php';

echo "=== Database Connection Test ===\n\n";

// Test direct PDO connection
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'riya_collections';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    echo "Attempting to connect to database:\n";
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "Database: $dbname\n";
    echo "Username: $username\n";
    echo "Password: " . (empty($password) ? '(empty)' : '(set)') . "\n\n";
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    echo "✓ Database connection successful!\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test, NOW() as current_time");
    $result = $stmt->fetch();
    
    echo "✓ Test query successful!\n";
    echo "Current time: {$result['current_time']}\n";
    
    // Check if database has tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✓ Found " . count($tables) . " table(s) in database\n";
    if (!empty($tables)) {
        echo "Tables: " . implode(', ', $tables) . "\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    
    // Check if it's a database not found error
    if ($e->getCode() == 1049) {
        echo "\nThe database '$dbname' does not exist. You may need to create it first.\n";
        echo "You can create it with: CREATE DATABASE $dbname;\n";
    }
    
    exit(1);
}

echo "\n=== Database Connection Test Completed ===\n";