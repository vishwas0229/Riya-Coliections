<?php
/**
 * Integration Test Script
 * 
 * This script tests the basic functionality of the integrated application
 * to ensure the file reorganization was successful.
 */

echo "=== Riya Collections Integration Test ===\n\n";

// Test 1: Check if main entry point exists
echo "1. Testing main entry point...\n";
if (file_exists('public/index.php')) {
    echo "   ✓ public/index.php exists\n";
} else {
    echo "   ✗ public/index.php missing\n";
}

// Test 2: Check if .htaccess exists
echo "2. Testing Apache configuration...\n";
if (file_exists('public/.htaccess')) {
    echo "   ✓ public/.htaccess exists\n";
} else {
    echo "   ✗ public/.htaccess missing\n";
}

// Test 3: Check if app directory structure exists
echo "3. Testing application structure...\n";
$appDirs = ['controllers', 'models', 'services', 'middleware', 'config', 'utils'];
foreach ($appDirs as $dir) {
    if (is_dir("app/$dir")) {
        echo "   ✓ app/$dir exists\n";
    } else {
        echo "   ✗ app/$dir missing\n";
    }
}

// Test 4: Check if frontend assets exist
echo "4. Testing frontend assets...\n";
$assetDirs = ['css', 'js'];
foreach ($assetDirs as $dir) {
    if (is_dir("public/assets/$dir")) {
        echo "   ✓ public/assets/$dir exists\n";
    } else {
        echo "   ✗ public/assets/$dir missing\n";
    }
}

// Test 5: Check if frontend HTML exists
echo "5. Testing frontend HTML...\n";
if (file_exists('public/index.html')) {
    echo "   ✓ public/index.html exists\n";
} else {
    echo "   ✗ public/index.html missing\n";
}

// Test 6: Check if configuration files exist
echo "6. Testing configuration files...\n";
$configFiles = ['environment.php', 'database.php'];
foreach ($configFiles as $file) {
    if (file_exists("app/config/$file")) {
        echo "   ✓ app/config/$file exists\n";
    } else {
        echo "   ✗ app/config/$file missing\n";
    }
}

// Test 7: Check if storage directories exist
echo "7. Testing storage structure...\n";
$storageDirs = ['logs', 'cache', 'backups'];
foreach ($storageDirs as $dir) {
    if (is_dir("storage/$dir")) {
        echo "   ✓ storage/$dir exists\n";
    } else {
        echo "   ✗ storage/$dir missing\n";
    }
}

// Test 8: Check if environment file exists
echo "8. Testing environment configuration...\n";
if (file_exists('.env') || file_exists('.env.example')) {
    echo "   ✓ Environment configuration available\n";
} else {
    echo "   ✗ Environment configuration missing\n";
}

// Test 9: Test frontend configuration
echo "9. Testing frontend configuration...\n";
if (file_exists('public/assets/js/config.js')) {
    $config = file_get_contents('public/assets/js/config.js');
    if (strpos($config, "BASE_URL: '/api'") !== false) {
        echo "   ✓ Frontend API configuration updated\n";
    } else {
        echo "   ✗ Frontend API configuration not updated\n";
    }
} else {
    echo "   ✗ Frontend configuration missing\n";
}

// Test 10: Check file permissions (if on Unix-like system)
echo "10. Testing file permissions...\n";
if (function_exists('posix_getuid')) {
    $publicPerms = fileperms('public');
    $storagePerms = is_dir('storage') ? fileperms('storage') : false;
    
    if ($publicPerms && ($publicPerms & 0444)) {
        echo "   ✓ Public directory is readable\n";
    } else {
        echo "   ✗ Public directory permissions issue\n";
    }
    
    if ($storagePerms && ($storagePerms & 0222)) {
        echo "   ✓ Storage directory is writable\n";
    } else {
        echo "   ✗ Storage directory permissions issue\n";
    }
} else {
    echo "   ~ Permission check skipped (Windows or limited environment)\n";
}

echo "\n=== Integration Test Complete ===\n";
echo "If all tests pass, the integration was successful!\n";
echo "You can now configure your web server to point to the 'public' directory.\n";
?>