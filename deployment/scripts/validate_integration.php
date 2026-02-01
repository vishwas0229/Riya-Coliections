<?php
/**
 * Integration Validation Script
 * 
 * This script validates that the frontend-backend integration is working correctly
 * after deployment. It tests all integration components and provides detailed feedback.
 * 
 * Requirements: Frontend-Backend Integration
 */

// Prevent direct access without confirmation
if (!isset($_GET['validate']) || $_GET['validate'] !== 'integration') {
    die('Access denied. Add ?validate=integration to run validation.');
}

// Load configuration
require_once __DIR__ . '/../../app/config/environment.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Integration Validation - Riya Collections</title>
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
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .test-pass { background-color: #e8f5e8; border-left: 4px solid #4CAF50; }
        .test-fail { background-color: #ffeaea; border-left: 4px solid #f44336; }
        .test-warn { background-color: #fff8e1; border-left: 4px solid #ff9800; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Riya Collections - Integration Validation</h1>
    <p>Comprehensive validation of frontend-backend integration components.</p>
    
    <?php
    
    $validationResults = [
        'timestamp' => date('Y-m-d H:i:s'),
        'overall_status' => 'pass',
        'tests' => [],
        'warnings' => [],
        'errors' => []
    ];
    
    function addTest($category, $name, $status, $message, $details = null) {
        global $validationResults;
        
        $test = [
            'category' => $category,
            'name' => $name,
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $validationResults['tests'][] = $test;
        
        if ($status === 'fail') {
            $validationResults['errors'][] = $message;
            $validationResults['overall_status'] = 'fail';
        } elseif ($status === 'warn') {
            $validationResults['warnings'][] = $message;
            if ($validationResults['overall_status'] === 'pass') {
                $validationResults['overall_status'] = 'warn';
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
    
    // 1. Project Structure Validation
    echo "<div class='section'>";
    echo "<h2>Project Structure Validation</h2>";
    
    $requiredDirs = [
        'app' => __DIR__ . '/../../app',
        'public' => __DIR__ . '/../../public',
        'storage' => __DIR__ . '/../../storage',
        'logs' => __DIR__ . '/../../logs'
    ];
    
    foreach ($requiredDirs as $name => $path) {
        $test = addTest(
            'Structure',
            ucfirst($name) . ' Directory',
            is_dir($path) ? 'pass' : 'fail',
            is_dir($path) ? "$name directory exists" : "$name directory missing",
            ['path' => $path]
        );
        displayTest($test);
    }
    
    $requiredFiles = [
        'public/index.php' => __DIR__ . '/../../public/index.php',
        'app/services/AssetServer.php' => __DIR__ . '/../../app/services/AssetServer.php',
        'app/services/SPARouteHandler.php' => __DIR__ . '/../../app/services/SPARouteHandler.php',
        'app/services/FrontendConfigManager.php' => __DIR__ . '/../../app/services/FrontendConfigManager.php'
    ];
    
    foreach ($requiredFiles as $name => $path) {
        $test = addTest(
            'Structure',
            $name,
            file_exists($path) ? 'pass' : 'fail',
            file_exists($path) ? "$name exists" : "$name missing",
            ['path' => $path]
        );
        displayTest($test);
    }
    
    echo "</div>";
    
    // 2. Integration Classes Validation
    echo "<div class='section'>";
    echo "<h2>Integration Classes Validation</h2>";
    
    // AssetServer Class
    if (file_exists(__DIR__ . '/../../app/services/AssetServer.php')) {
        require_once __DIR__ . '/../../app/services/AssetServer.php';
        
        $test = addTest(
            'Classes',
            'AssetServer Class Loading',
            class_exists('AssetServer') ? 'pass' : 'fail',
            class_exists('AssetServer') ? 'AssetServer class loaded successfully' : 'AssetServer class failed to load'
        );
        displayTest($test);
        
        if (class_exists('AssetServer')) {
            $methods = ['serve', 'getMimeType', 'setCacheHeaders', 'compressOutput'];
            foreach ($methods as $method) {
                $test = addTest(
                    'Classes',
                    "AssetServer::$method Method",
                    method_exists('AssetServer', $method) ? 'pass' : 'fail',
                    method_exists('AssetServer', $method) ? "$method method exists" : "$method method missing"
                );
                displayTest($test);
            }
        }
    }
    
    // SPARouteHandler Class
    if (file_exists(__DIR__ . '/../../app/services/SPARouteHandler.php')) {
        require_once __DIR__ . '/../../app/services/SPARouteHandler.php';
        
        $test = addTest(
            'Classes',
            'SPARouteHandler Class Loading',
            class_exists('SPARouteHandler') ? 'pass' : 'fail',
            class_exists('SPARouteHandler') ? 'SPARouteHandler class loaded successfully' : 'SPARouteHandler class failed to load'
        );
        displayTest($test);
        
        if (class_exists('SPARouteHandler')) {
            $methods = ['handleRoute', 'isAPIRoute', 'isFrontendRoute', 'serveMainHTML'];
            foreach ($methods as $method) {
                $test = addTest(
                    'Classes',
                    "SPARouteHandler::$method Method",
                    method_exists('SPARouteHandler', $method) ? 'pass' : 'fail',
                    method_exists('SPARouteHandler', $method) ? "$method method exists" : "$method method missing"
                );
                displayTest($test);
            }
        }
    }
    
    // FrontendConfigManager Class
    if (file_exists(__DIR__ . '/../../app/services/FrontendConfigManager.php')) {
        require_once __DIR__ . '/../../app/services/FrontendConfigManager.php';
        
        $test = addTest(
            'Classes',
            'FrontendConfigManager Class Loading',
            class_exists('FrontendConfigManager') ? 'pass' : 'fail',
            class_exists('FrontendConfigManager') ? 'FrontendConfigManager class loaded successfully' : 'FrontendConfigManager class failed to load'
        );
        displayTest($test);
    }
    
    echo "</div>";
    
    // 3. Frontend Assets Validation
    echo "<div class='section'>";
    echo "<h2>Frontend Assets Validation</h2>";
    
    $assetDirs = [
        'CSS' => __DIR__ . '/../../public/assets/css',
        'JavaScript' => __DIR__ . '/../../public/assets/js',
        'Images' => __DIR__ . '/../../public/assets/images',
        'Fonts' => __DIR__ . '/../../public/assets/fonts'
    ];
    
    foreach ($assetDirs as $type => $dir) {
        $exists = is_dir($dir);
        $fileCount = $exists ? count(glob($dir . '/*')) : 0;
        
        $test = addTest(
            'Assets',
            "$type Assets Directory",
            $exists ? 'pass' : 'warn',
            $exists ? "$type assets directory exists with $fileCount files" : "$type assets directory missing",
            ['path' => $dir, 'file_count' => $fileCount]
        );
        displayTest($test);
    }
    
    // Check for main HTML files
    $htmlFiles = [
        'index.html' => __DIR__ . '/../../public/index.html',
        'pages directory' => __DIR__ . '/../../public/pages'
    ];
    
    foreach ($htmlFiles as $name => $path) {
        $exists = file_exists($path) || is_dir($path);
        $test = addTest(
            'Assets',
            "Frontend $name",
            $exists ? 'pass' : 'warn',
            $exists ? "Frontend $name exists" : "Frontend $name missing",
            ['path' => $path]
        );
        displayTest($test);
    }
    
    echo "</div>";
    
    // 4. Routing Integration Test
    echo "<div class='section'>";
    echo "<h2>Routing Integration Test</h2>";
    
    // Test main application route
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    $routes = [
        'Frontend Root' => $baseUrl . '/',
        'API Health' => $baseUrl . '/api/health',
        'API Products' => $baseUrl . '/api/products',
        'Static Asset (CSS)' => $baseUrl . '/assets/css/style.css',
        'Static Asset (JS)' => $baseUrl . '/assets/js/app.js'
    ];
    
    foreach ($routes as $name => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        $status = 'warn';
        $message = "$name returned HTTP $httpCode";
        
        if ($httpCode === 200) {
            $status = 'pass';
        } elseif ($httpCode === 404 && strpos($name, 'Static Asset') !== false) {
            $status = 'warn'; // Assets might not exist yet
            $message .= ' (asset may not exist - this is OK for testing)';
        } elseif ($httpCode >= 400) {
            $status = 'fail';
        }
        
        $test = addTest(
            'Routing',
            $name,
            $status,
            $message,
            ['url' => $url, 'http_code' => $httpCode, 'content_type' => $contentType]
        );
        displayTest($test);
    }
    
    echo "</div>";
    
    // 5. Configuration Validation
    echo "<div class='section'>";
    echo "<h2>Configuration Validation</h2>";
    
    // Environment files
    $envFiles = [
        'development' => __DIR__ . '/../../app/config/environments/development.env',
        'staging' => __DIR__ . '/../../app/config/environments/staging.env',
        'production' => __DIR__ . '/../../app/config/environments/production.env'
    ];
    
    foreach ($envFiles as $env => $file) {
        $test = addTest(
            'Configuration',
            ucfirst($env) . ' Environment File',
            file_exists($file) ? 'pass' : 'warn',
            file_exists($file) ? "$env environment file exists" : "$env environment file missing",
            ['path' => $file]
        );
        displayTest($test);
    }
    
    // Web server configurations
    $webConfigs = [
        'Apache Production' => __DIR__ . '/../../app/config/webserver/apache-production.conf',
        'Apache Development' => __DIR__ . '/../../app/config/webserver/apache-development.conf',
        'Nginx Production' => __DIR__ . '/../../app/config/webserver/nginx-production.conf',
        'Nginx Development' => __DIR__ . '/../../app/config/webserver/nginx-development.conf'
    ];
    
    foreach ($webConfigs as $name => $file) {
        $test = addTest(
            'Configuration',
            $name . ' Config',
            file_exists($file) ? 'pass' : 'warn',
            file_exists($file) ? "$name configuration exists" : "$name configuration missing",
            ['path' => $file]
        );
        displayTest($test);
    }
    
    echo "</div>";
    
    // 6. Database Integration Test
    echo "<div class='section'>";
    echo "<h2>Database Integration Test</h2>";
    
    try {
        require_once __DIR__ . '/../../app/models/Database.php';
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        $test = addTest(
            'Database',
            'Database Connection',
            'pass',
            'Database connection successful'
        );
        displayTest($test);
        
        // Test basic query
        $stmt = $connection->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        $test = addTest(
            'Database',
            'Database Query Test',
            ($result && $result['test'] == 1) ? 'pass' : 'fail',
            ($result && $result['test'] == 1) ? 'Database query test successful' : 'Database query test failed'
        );
        displayTest($test);
        
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
    
    // Summary
    $totalTests = count($validationResults['tests']);
    $passedTests = count(array_filter($validationResults['tests'], function($test) { return $test['status'] === 'pass'; }));
    $warningTests = count(array_filter($validationResults['tests'], function($test) { return $test['status'] === 'warn'; }));
    $failedTests = count(array_filter($validationResults['tests'], function($test) { return $test['status'] === 'fail'; }));
    
    $sectionClass = $validationResults['overall_status'] === 'pass' ? 'pass' : ($validationResults['overall_status'] === 'fail' ? 'fail' : 'warn');
    
    echo "<div class='section {$sectionClass}'>";
    echo "<h2>Integration Validation Summary</h2>";
    
    echo "<table>";
    echo "<tr><th>Metric</th><th>Value</th></tr>";
    echo "<tr><td>Total Tests</td><td>{$totalTests}</td></tr>";
    echo "<tr><td>Passed</td><td style='color: green;'>{$passedTests}</td></tr>";
    echo "<tr><td>Warnings</td><td style='color: orange;'>{$warningTests}</td></tr>";
    echo "<tr><td>Failed</td><td style='color: red;'>{$failedTests}</td></tr>";
    echo "<tr><td>Overall Status</td><td style='color: " . ($validationResults['overall_status'] === 'pass' ? 'green' : ($validationResults['overall_status'] === 'fail' ? 'red' : 'orange')) . ";'>" . strtoupper($validationResults['overall_status']) . "</td></tr>";
    echo "</table>";
    
    if ($validationResults['overall_status'] === 'pass') {
        echo "<p class='success'>✅ Integration validation completed successfully!</p>";
        echo "<p>Your frontend-backend integration appears to be working correctly.</p>";
    } else {
        echo "<p class='error'>❌ Integration validation found issues that need attention.</p>";
        
        if (!empty($validationResults['errors'])) {
            echo "<h3>Critical Issues:</h3>";
            echo "<ul>";
            foreach ($validationResults['errors'] as $error) {
                echo "<li style='color: red;'>{$error}</li>";
            }
            echo "</ul>";
        }
    }
    
    if (!empty($validationResults['warnings'])) {
        echo "<h3>Warnings:</h3>";
        echo "<ul>";
        foreach ($validationResults['warnings'] as $warning) {
            echo "<li style='color: orange;'>{$warning}</li>";
        }
        echo "</ul>";
    }
    
    echo "</div>";
    
    ?>
    
    <div class="section">
        <h2>Integration Testing Checklist</h2>
        
        <h3>Manual Tests to Perform:</h3>
        <ol>
            <li><strong>Frontend Application:</strong> Visit <code><?php echo $baseUrl; ?>/</code> and verify the main application loads</li>
            <li><strong>API Endpoints:</strong> Test <code><?php echo $baseUrl; ?>/api/health</code> returns JSON response</li>
            <li><strong>Static Assets:</strong> Check that CSS, JS, and images load correctly</li>
            <li><strong>SPA Routing:</strong> Navigate between frontend pages and refresh browser</li>
            <li><strong>API Integration:</strong> Test frontend forms that call backend APIs</li>
            <li><strong>File Uploads:</strong> Test image upload functionality</li>
            <li><strong>Authentication:</strong> Test user login/logout flows</li>
            <li><strong>Error Handling:</strong> Test 404 pages and error responses</li>
        </ol>
        
        <h3>Performance Tests:</h3>
        <ul>
            <li>Check page load times for frontend application</li>
            <li>Verify static assets are properly cached</li>
            <li>Test API response times</li>
            <li>Monitor server resource usage</li>
        </ul>
        
        <h3>Security Tests:</h3>
        <ul>
            <li>Verify .env file is not accessible via HTTP</li>
            <li>Check that app/ directory is protected</li>
            <li>Test CORS headers for API requests</li>
            <li>Verify file upload security</li>
        </ul>
    </div>
    
</body>
</html>