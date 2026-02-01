<?php
/**
 * Deployment Verification Script
 * 
 * This script performs comprehensive verification of a deployment
 * to ensure all components are working correctly.
 * 
 * Requirements: 14.3, 20.1
 */

// Prevent direct access without confirmation
if (!isset($_GET['verify']) || $_GET['verify'] !== 'deployment') {
    die('Access denied. Add ?verify=deployment to run verification.');
}

// Load configuration
require_once __DIR__ . '/../../config/environment.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Deployment Verification - Riya Collections</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .pass { background-color: #f0f8f0; border-color: #4CAF50; }
        .fail { background-color: #fff0f0; border-color: #f44336; }
        .warn { background-color: #fff8f0; border-color: #ff9800; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .status-pass { color: green; font-weight: bold; }
        .status-fail { color: red; font-weight: bold; }
        .status-warn { color: orange; font-weight: bold; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .test-pass { background-color: #e8f5e8; border-left: 4px solid #4CAF50; }
        .test-fail { background-color: #ffeaea; border-left: 4px solid #f44336; }
        .test-warn { background-color: #fff8e1; border-left: 4px solid #ff9800; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .progress { background: #f0f0f0; border-radius: 4px; padding: 3px; margin: 10px 0; }
        .progress-bar { background: #4CAF50; height: 20px; border-radius: 2px; transition: width 0.3s; }
    </style>
</head>
<body>
    <h1>Riya Collections - Deployment Verification</h1>
    <p>Comprehensive verification of deployment status and functionality.</p>
    
    <?php
    
    $verificationResults = [
        'timestamp' => date('Y-m-d H:i:s'),
        'overall_status' => 'pass',
        'tests' => [],
        'warnings' => [],
        'errors' => [],
        'critical_failures' => []
    ];
    
    function addTest($category, $name, $status, $message, $details = null) {
        global $verificationResults;
        
        $test = [
            'category' => $category,
            'name' => $name,
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $verificationResults['tests'][] = $test;
        
        if ($status === 'fail') {
            $verificationResults['errors'][] = $message;
            $verificationResults['critical_failures'][] = $name;
            $verificationResults['overall_status'] = 'fail';
        } elseif ($status === 'warn') {
            $verificationResults['warnings'][] = $message;
            if ($verificationResults['overall_status'] === 'pass') {
                $verificationResults['overall_status'] = 'warn';
            }
        }
        
        return $test;
    }
    
    function displayTest($test) {
        $class = 'test-' . $test['status'];
        $icon = $test['status'] === 'pass' ? '✓' : ($test['status'] === 'fail' ? '✗' : '⚠');
        
        echo "<div class='test-result {$class}'>";
        echo "<strong>{$icon} {$test['name']}</strong><br>";
        echo $test['message'];
        
        if ($test['details']) {
            echo "<br><small>Details: " . (is_array($test['details']) ? json_encode($test['details']) : $test['details']) . "</small>";
        }
        echo "</div>";
    }
    
    // Test Categories
    $categories = [
        'Environment' => 'Basic environment and configuration checks',
        'Database' => 'Database connectivity and schema verification',
        'API' => 'API endpoint functionality tests',
        'Authentication' => 'User authentication system tests',
        'File System' => 'File upload and storage tests',
        'Security' => 'Security configuration verification',
        'Performance' => 'Basic performance and resource checks',
        'Integration' => 'External service integration tests'
    ];
    
    echo "<div class='section'>";
    echo "<h2>Test Categories</h2>";
    echo "<p>The following test categories will be executed:</p>";
    echo "<ul>";
    foreach ($categories as $category => $description) {
        echo "<li><strong>{$category}:</strong> {$description}</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // 1. Environment Tests
    echo "<div class='section'>";
    echo "<h2>Environment Tests</h2>";
    
    // PHP Version
    $phpVersion = PHP_VERSION;
    $minPhpVersion = '7.4.0';
    $test = addTest(
        'Environment',
        'PHP Version Check',
        version_compare($phpVersion, $minPhpVersion, '>=') ? 'pass' : 'fail',
        "PHP version: {$phpVersion} (minimum: {$minPhpVersion})",
        ['current' => $phpVersion, 'minimum' => $minPhpVersion]
    );
    displayTest($test);
    
    // Required Extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'gd', 'mbstring', 'openssl'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    $test = addTest(
        'Environment',
        'PHP Extensions Check',
        empty($missingExtensions) ? 'pass' : 'fail',
        empty($missingExtensions) ? 'All required extensions loaded' : 'Missing extensions: ' . implode(', ', $missingExtensions),
        ['required' => $requiredExtensions, 'missing' => $missingExtensions]
    );
    displayTest($test);
    
    // Environment Variables
    $requiredEnvVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'JWT_SECRET'];
    $missingEnvVars = [];
    
    foreach ($requiredEnvVars as $var) {
        if (empty(env($var))) {
            $missingEnvVars[] = $var;
        }
    }
    
    $test = addTest(
        'Environment',
        'Environment Variables Check',
        empty($missingEnvVars) ? 'pass' : 'fail',
        empty($missingEnvVars) ? 'All required environment variables set' : 'Missing variables: ' . implode(', ', $missingEnvVars),
        ['required' => $requiredEnvVars, 'missing' => $missingEnvVars]
    );
    displayTest($test);
    
    echo "</div>";
    
    // 2. Database Tests
    echo "<div class='section'>";
    echo "<h2>Database Tests</h2>";
    
    try {
        require_once __DIR__ . '/../../models/Database.php';
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        // Connection Test
        $test = addTest(
            'Database',
            'Database Connection',
            'pass',
            'Successfully connected to database'
        );
        displayTest($test);
        
        // Version Check
        $stmt = $connection->query("SELECT VERSION() as version");
        $version = $stmt->fetch()['version'];
        $minMysqlVersion = '5.7.0';
        
        $test = addTest(
            'Database',
            'MySQL Version Check',
            version_compare($version, $minMysqlVersion, '>=') ? 'pass' : 'warn',
            "MySQL version: {$version} (recommended: {$minMysqlVersion}+)",
            ['current' => $version, 'recommended' => $minMysqlVersion]
        );
        displayTest($test);
        
        // Tables Check
        $stmt = $connection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredTables = ['users', 'products', 'orders', 'categories', 'order_items', 'payments'];
        $missingTables = array_diff($requiredTables, $tables);
        
        $test = addTest(
            'Database',
            'Required Tables Check',
            empty($missingTables) ? 'pass' : 'fail',
            empty($missingTables) ? 'All required tables exist' : 'Missing tables: ' . implode(', ', $missingTables),
            ['required' => $requiredTables, 'existing' => $tables, 'missing' => $missingTables]
        );
        displayTest($test);
        
        // Sample Data Test
        if (in_array('users', $tables)) {
            $stmt = $connection->query("SELECT COUNT(*) as count FROM users");
            $userCount = $stmt->fetch()['count'];
            
            $test = addTest(
                'Database',
                'Sample Data Check',
                'pass',
                "Database contains {$userCount} users",
                ['user_count' => $userCount]
            );
            displayTest($test);
        }
        
    } catch (Exception $e) {
        $test = addTest(
            'Database',
            'Database Connection',
            'fail',
            'Database connection failed: ' . $e->getMessage(),
            ['error' => $e->getMessage()]
        );
        displayTest($test);
    }
    
    echo "</div>";
    
    // 3. API Tests
    echo "<div class='section'>";
    echo "<h2>API Endpoint Tests</h2>";
    
    // Health Check Endpoint
    $healthUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/health';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $healthUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $healthData = json_decode($response, true);
        $test = addTest(
            'API',
            'Health Check Endpoint',
            'pass',
            'Health check endpoint responding correctly',
            ['url' => $healthUrl, 'status_code' => $httpCode]
        );
    } else {
        $test = addTest(
            'API',
            'Health Check Endpoint',
            'fail',
            "Health check endpoint failed (HTTP {$httpCode}): {$error}",
            ['url' => $healthUrl, 'status_code' => $httpCode, 'error' => $error]
        );
    }
    displayTest($test);
    
    // Products Endpoint
    $productsUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/products';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $productsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $test = addTest(
        'API',
        'Products Endpoint',
        $httpCode === 200 ? 'pass' : 'warn',
        "Products endpoint returned HTTP {$httpCode}",
        ['url' => $productsUrl, 'status_code' => $httpCode]
    );
    displayTest($test);
    
    echo "</div>";
    
    // 4. Authentication Tests
    echo "<div class='section'>";
    echo "<h2>Authentication System Tests</h2>";
    
    // JWT Secret Strength
    $jwtSecret = env('JWT_SECRET');
    $test = addTest(
        'Authentication',
        'JWT Secret Strength',
        strlen($jwtSecret) >= 32 ? 'pass' : 'warn',
        "JWT secret length: " . strlen($jwtSecret) . " characters (recommended: 32+)",
        ['length' => strlen($jwtSecret), 'recommended' => 32]
    );
    displayTest($test);
    
    // Password Hashing Test
    if (function_exists('password_hash') && function_exists('password_verify')) {
        $testPassword = 'test123';
        $hash = password_hash($testPassword, PASSWORD_BCRYPT);
        $verified = password_verify($testPassword, $hash);
        
        $test = addTest(
            'Authentication',
            'Password Hashing',
            $verified ? 'pass' : 'fail',
            $verified ? 'Password hashing and verification working' : 'Password hashing verification failed'
        );
        displayTest($test);
    }
    
    echo "</div>";
    
    // 5. File System Tests
    echo "<div class='section'>";
    echo "<h2>File System Tests</h2>";
    
    // Directory Permissions
    $directories = [
        'uploads' => __DIR__ . '/../../uploads',
        'logs' => __DIR__ . '/../../logs',
        'cache' => __DIR__ . '/../../cache'
    ];
    
    foreach ($directories as $name => $path) {
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        
        if ($exists && $writable) {
            $status = 'pass';
            $message = "{$name} directory exists and is writable";
        } elseif ($exists && !$writable) {
            $status = 'fail';
            $message = "{$name} directory exists but is not writable";
        } else {
            $status = 'fail';
            $message = "{$name} directory does not exist";
        }
        
        $test = addTest(
            'File System',
            ucfirst($name) . ' Directory',
            $status,
            $message,
            ['path' => $path, 'exists' => $exists, 'writable' => $writable]
        );
        displayTest($test);
    }
    
    // File Upload Test
    $uploadDir = __DIR__ . '/../../uploads';
    if (is_dir($uploadDir) && is_writable($uploadDir)) {
        $testFile = $uploadDir . '/test_' . time() . '.txt';
        $testContent = 'Deployment verification test file';
        
        if (file_put_contents($testFile, $testContent) !== false) {
            $readContent = file_get_contents($testFile);
            unlink($testFile); // Clean up
            
            $test = addTest(
                'File System',
                'File Upload Test',
                $readContent === $testContent ? 'pass' : 'fail',
                $readContent === $testContent ? 'File upload and read test successful' : 'File upload test failed'
            );
        } else {
            $test = addTest(
                'File System',
                'File Upload Test',
                'fail',
                'Could not write test file to uploads directory'
            );
        }
        displayTest($test);
    }
    
    echo "</div>";
    
    // 6. Security Tests
    echo "<div class='section'>";
    echo "<h2>Security Configuration Tests</h2>";
    
    // HTTPS Check
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    $test = addTest(
        'Security',
        'HTTPS Configuration',
        $isHttps ? 'pass' : 'warn',
        $isHttps ? 'HTTPS is enabled' : 'HTTPS is not enabled (recommended for production)'
    );
    displayTest($test);
    
    // .htaccess File
    $htaccessExists = file_exists(__DIR__ . '/../../.htaccess');
    $test = addTest(
        'Security',
        '.htaccess Configuration',
        $htaccessExists ? 'pass' : 'warn',
        $htaccessExists ? '.htaccess file exists' : '.htaccess file missing (URL rewriting may not work)'
    );
    displayTest($test);
    
    // Environment File Protection
    $envFile = __DIR__ . '/../../.env';
    $envExists = file_exists($envFile);
    
    // Try to access .env via HTTP (should be blocked)
    $envUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/.env';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $envUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $envProtected = $httpCode === 403 || $httpCode === 404;
    
    $test = addTest(
        'Security',
        'Environment File Protection',
        $envProtected ? 'pass' : 'fail',
        $envProtected ? '.env file is protected from HTTP access' : '.env file may be accessible via HTTP (security risk)',
        ['http_code' => $httpCode, 'url' => $envUrl]
    );
    displayTest($test);
    
    echo "</div>";
    
    // 7. Performance Tests
    echo "<div class='section'>";
    echo "<h2>Performance Tests</h2>";
    
    // Memory Usage
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = return_bytes(ini_get('memory_limit'));
    $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
    
    $test = addTest(
        'Performance',
        'Memory Usage',
        $memoryPercent < 80 ? 'pass' : 'warn',
        sprintf('Memory usage: %.1f%% (%s / %s)', $memoryPercent, formatBytes($memoryUsage), ini_get('memory_limit')),
        ['usage_percent' => round($memoryPercent, 1)]
    );
    displayTest($test);
    
    // Response Time Test
    $startTime = microtime(true);
    
    // Simulate some work
    if (isset($connection)) {
        $stmt = $connection->query("SELECT 1");
        $stmt->fetch();
    }
    
    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    
    $test = addTest(
        'Performance',
        'Database Response Time',
        $responseTime < 100 ? 'pass' : ($responseTime < 500 ? 'warn' : 'fail'),
        sprintf('Database query response time: %.2f ms', $responseTime),
        ['response_time_ms' => round($responseTime, 2)]
    );
    displayTest($test);
    
    echo "</div>";
    
    // Summary
    $totalTests = count($verificationResults['tests']);
    $passedTests = count(array_filter($verificationResults['tests'], function($test) { return $test['status'] === 'pass'; }));
    $warningTests = count(array_filter($verificationResults['tests'], function($test) { return $test['status'] === 'warn'; }));
    $failedTests = count(array_filter($verificationResults['tests'], function($test) { return $test['status'] === 'fail'; }));
    
    $sectionClass = $verificationResults['overall_status'] === 'pass' ? 'pass' : ($verificationResults['overall_status'] === 'fail' ? 'fail' : 'warn');
    
    echo "<div class='section {$sectionClass}'>";
    echo "<h2>Verification Summary</h2>";
    
    echo "<table>";
    echo "<tr><th>Metric</th><th>Value</th></tr>";
    echo "<tr><td>Total Tests</td><td>{$totalTests}</td></tr>";
    echo "<tr><td>Passed</td><td class='status-pass'>{$passedTests}</td></tr>";
    echo "<tr><td>Warnings</td><td class='status-warn'>{$warningTests}</td></tr>";
    echo "<tr><td>Failed</td><td class='status-fail'>{$failedTests}</td></tr>";
    echo "<tr><td>Overall Status</td><td class='status-" . $verificationResults['overall_status'] . "'>" . strtoupper($verificationResults['overall_status']) . "</td></tr>";
    echo "</table>";
    
    if ($verificationResults['overall_status'] === 'pass') {
        echo "<p class='success'>✅ Deployment verification completed successfully!</p>";
        echo "<p>Your deployment appears to be working correctly. All critical tests passed.</p>";
        
        if (!empty($verificationResults['warnings'])) {
            echo "<h3>Recommendations:</h3>";
            echo "<ul>";
            foreach ($verificationResults['warnings'] as $warning) {
                echo "<li class='warning'>{$warning}</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "<p class='error'>❌ Deployment verification found issues that need attention.</p>";
        
        if (!empty($verificationResults['errors'])) {
            echo "<h3>Critical Issues:</h3>";
            echo "<ul>";
            foreach ($verificationResults['errors'] as $error) {
                echo "<li class='error'>{$error}</li>";
            }
            echo "</ul>";
        }
        
        if (!empty($verificationResults['warnings'])) {
            echo "<h3>Warnings:</h3>";
            echo "<ul>";
            foreach ($verificationResults['warnings'] as $warning) {
                echo "<li class='warning'>{$warning}</li>";
            }
            echo "</ul>";
        }
    }
    
    echo "</div>";
    
    // Helper functions
    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
    
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    ?>
    
    <div class="section">
        <h2>Next Steps</h2>
        
        <?php if ($verificationResults['overall_status'] === 'pass'): ?>
            <p>Your deployment is ready for production use. Consider these final steps:</p>
            <ol>
                <li>Delete deployment scripts for security</li>
                <li>Set up monitoring and alerting</li>
                <li>Configure automated backups</li>
                <li>Test all user workflows thoroughly</li>
                <li>Update DNS if needed</li>
            </ol>
        <?php else: ?>
            <p>Please resolve the issues identified above before going live:</p>
            <ol>
                <li>Fix all critical failures</li>
                <li>Address security warnings</li>
                <li>Re-run this verification script</li>
                <li>Test functionality manually</li>
                <li>Proceed only when all tests pass</li>
            </ol>
        <?php endif; ?>
        
        <h3>Useful Commands:</h3>
        <pre>
# Re-run health check
curl https://your-domain.com/deployment/scripts/health_check.php

# Check logs
tail -f logs/app.log

# Test API endpoints
curl https://your-domain.com/api/health
curl https://your-domain.com/api/products
        </pre>
    </div>
    
</body>
</html>