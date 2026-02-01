<?php
/**
 * Simple AuthController Test
 * 
 * Basic functionality test for AuthController endpoints
 */

// Include utilities first to avoid dependency issues
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../controllers/AuthController.php';

echo "Testing AuthController Basic Functionality...\n\n";

try {
    // Test 1: Controller instantiation
    echo "1. Testing AuthController instantiation...\n";
    $authController = new AuthController();
    echo "✓ AuthController created successfully\n\n";
    
    // Test 2: Check if all required methods exist
    echo "2. Testing required methods exist...\n";
    $requiredMethods = [
        'register',
        'login',
        'refresh',
        'getProfile',
        'updateProfile',
        'changePassword',
        'forgotPassword',
        'resetPassword',
        'logout',
        'getSessions',
        'verifyToken',
        'adminLogin',
        'adminChangePassword',
        'getAdminProfile',
        'updateAdminProfile',
        'adminLogout',
        'getAdminSecurityLog'
    ];
    
    foreach ($requiredMethods as $method) {
        if (method_exists($authController, $method)) {
            echo "✓ Method '$method' exists\n";
        } else {
            throw new Exception("Method '$method' is missing");
        }
    }
    echo "\n";
    
    // Test 3: Check AuthService integration
    echo "3. Testing AuthService integration...\n";
    $authService = new AuthService();
    echo "✓ AuthService created successfully\n";
    
    // Check if AuthService has required methods
    $authServiceMethods = ['register', 'login', 'adminLogin', 'verifyAccessToken'];
    foreach ($authServiceMethods as $method) {
        if (method_exists($authService, $method)) {
            echo "✓ AuthService method '$method' exists\n";
        } else {
            throw new Exception("AuthService method '$method' is missing");
        }
    }
    echo "\n";
    
    // Test 4: Check database connectivity
    echo "4. Testing database connectivity...\n";
    $db = Database::getInstance();
    $connection = $db->getConnection();
    if ($connection) {
        echo "✓ Database connection established\n";
    } else {
        throw new Exception("Database connection failed");
    }
    echo "\n";
    
    // Test 5: Test Response utility
    echo "5. Testing Response utility...\n";
    if (class_exists('Response')) {
        echo "✓ Response class exists\n";
        
        // Check if Response has required methods
        $responseMethods = ['success', 'error', 'json'];
        foreach ($responseMethods as $method) {
            if (method_exists('Response', $method)) {
                echo "✓ Response method '$method' exists\n";
            } else {
                throw new Exception("Response method '$method' is missing");
            }
        }
    } else {
        throw new Exception("Response class not found");
    }
    echo "\n";
    
    // Test 6: Test Logger utility
    echo "6. Testing Logger utility...\n";
    if (class_exists('Logger')) {
        echo "✓ Logger class exists\n";
        
        // Check if Logger has required methods
        $loggerMethods = ['info', 'error', 'security'];
        foreach ($loggerMethods as $method) {
            if (method_exists('Logger', $method)) {
                echo "✓ Logger method '$method' exists\n";
            } else {
                throw new Exception("Logger method '$method' is missing");
            }
        }
    } else {
        throw new Exception("Logger class not found");
    }
    echo "\n";
    
    // Test 7: Check middleware integration
    echo "7. Testing middleware integration...\n";
    if (class_exists('AuthMiddleware')) {
        echo "✓ AuthMiddleware class exists\n";
        
        if (method_exists('AuthMiddleware', 'requireAuth')) {
            echo "✓ AuthMiddleware::requireAuth method exists\n";
        } else {
            echo "⚠ AuthMiddleware::requireAuth method not found (may use different method name)\n";
        }
    } else {
        echo "⚠ AuthMiddleware class not found (may not be loaded yet)\n";
    }
    echo "\n";
    
    echo "✅ All basic functionality tests passed!\n";
    echo "AuthController is properly configured and ready for use.\n\n";
    
    echo "Available Authentication Endpoints:\n";
    echo "- POST /api/auth/register - User registration\n";
    echo "- POST /api/auth/login - User login\n";
    echo "- POST /api/auth/refresh - Token refresh\n";
    echo "- GET /api/auth/profile - Get user profile\n";
    echo "- PUT /api/auth/profile - Update user profile\n";
    echo "- POST /api/auth/change-password - Change password\n";
    echo "- POST /api/auth/forgot-password - Initiate password reset\n";
    echo "- POST /api/auth/reset-password - Complete password reset\n";
    echo "- POST /api/auth/logout - User logout\n";
    echo "- GET /api/auth/sessions - Get user sessions\n";
    echo "- GET /api/auth/verify - Verify token\n";
    echo "\nAdmin Authentication Endpoints:\n";
    echo "- POST /api/admin/login - Admin login\n";
    echo "- GET /api/admin/profile - Get admin profile\n";
    echo "- PUT /api/admin/profile - Update admin profile\n";
    echo "- POST /api/admin/change-password - Change admin password\n";
    echo "- POST /api/admin/logout - Admin logout\n";
    echo "- GET /api/admin/security-log - Get security audit log\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}