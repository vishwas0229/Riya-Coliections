<?php
/**
 * Basic AuthController Test
 */

echo "Testing AuthController basic setup...\n";

try {
    // Test basic class loading
    echo "1. Loading AuthController...\n";
    
    // Include required files in correct order
    require_once __DIR__ . '/config/environment.php';
    require_once __DIR__ . '/utils/Logger.php';
    require_once __DIR__ . '/utils/Response.php';
    require_once __DIR__ . '/models/Database.php';
    require_once __DIR__ . '/services/AuthService.php';
    require_once __DIR__ . '/controllers/AuthController.php';
    
    echo "✓ All files loaded successfully\n";
    
    // Test class instantiation
    echo "2. Creating AuthController instance...\n";
    $authController = new AuthController();
    echo "✓ AuthController created successfully\n";
    
    // Test method existence
    echo "3. Checking required methods...\n";
    $methods = ['register', 'login', 'adminLogin', 'getProfile', 'verifyToken'];
    foreach ($methods as $method) {
        if (method_exists($authController, $method)) {
            echo "✓ Method $method exists\n";
        } else {
            echo "✗ Method $method missing\n";
        }
    }
    
    echo "\n✅ Basic AuthController test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}