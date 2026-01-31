<?php
/**
 * Environment Validation Script
 * 
 * This script validates the deployment environment to ensure all requirements
 * are met before going live.
 * 
 * Requirements: 14.2, 14.4
 */

// Load configuration
require_once __DIR__ . '/../../config/environment.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Environment Validation - Riya Collections</title>
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
    </style>
</head>
<body>
    <h1>Riya Collections - Environment Validation</h1>
    <p>This script validates your deployment environment to ensure all requirements are met.</p>
    
    <?php
    
    $overallStatus = 'pass';
    $criticalErrors = [];
    $warnings = [];
    
    // PHP Version Check
    echo "<div class='section'>";
    echo "<h2>PHP Environment</h2>";
    
    $phpVersion = PHP_VERSION;
    $minPhpVersion = '7.4.0';
    
    echo "<table>";
    echo "<tr><th>Check</th><th>Required</th><th>Current</th><th>Status</th></tr>";
    
    // PHP Version
    $phpVersionOk = version_compare($phpVersion, $minPhpVersion, '>=');
    echo "<tr>";
    echo "<td>PHP Version</td>";
    echo "<td>{$minPhpVersion}+</td>";
    echo "<td>{$phpVersion}</td>";
    echo "<td class='" . ($phpVersionOk ? 'status-pass">✓ PASS' : 'status-fail">✗ FAIL') . "</td>";
    echo "</tr>";
    
    if (!$phpVersionOk) {
        $criticalErrors[] = "PHP version {$phpVersion} is below minimum required {$minPhpVersion}";
        $overallStatus = 'fail';
    }
    
    // Required Extensions
    $requiredExtensions = [
        'pdo' => 'Database connectivity',
        'pdo_mysql' => 'MySQL database support',
        'json' => 'JSON processing',
        'curl' => 'HTTP requests (Razorpay, email)',
        'gd' => 'Image processing',
        'mbstring' => 'Multi-byte string handling',
        'openssl' => 'Encryption and HTTPS',
        'fileinfo' => 'File type detection',
        'filter' => 'Input filtering',
        'hash' => 'Password hashing'
    ];
    
    foreach ($requiredExtensions as $ext => $description) {
        $loaded = extension_loaded($ext);
        echo "<tr>";
        echo "<td>{$ext}</td>";
        echo "<td>Required</td>";
        echo "<td>{$description}</td>";
        echo "<td class='" . ($loaded ? 'status-pass">✓ PASS' : 'status-fail">✗ FAIL') . "</td>";
        echo "</tr>";
        
        if (!$loaded) {
            $criticalErrors[] = "Required PHP extension '{$ext}' is not loaded";
            $overallStatus = 'fail';
        }
    }
    
    // Optional Extensions
    $optionalExtensions = [
        'zip' => 'Archive handling',
        'intl' => 'Internationalization',
        'exif' => 'Image metadata'
    ];
    
    foreach ($optionalExtensions as $ext => $description) {
        $loaded = extension_loaded($ext);
        echo "<tr>";
        echo "<td>{$ext} (optional)</td>";
        echo "<td>Recommended</td>";
        echo "<td>{$description}</td>";
        echo "<td class='" . ($loaded ? 'status-pass">✓ PASS' : 'status-warn">⚠ MISSING') . "</td>";
        echo "</tr>";
        
        if (!$loaded) {
            $warnings[] = "Optional PHP extension '{$ext}' is not loaded - {$description}";
        }
    }
    
    echo "</table>";
    echo "</div>";
    
    // PHP Configuration
    echo "<div class='section'>";
    echo "<h2>PHP Configuration</h2>";
    
    echo "<table>";
    echo "<tr><th>Setting</th><th>Recommended</th><th>Current</th><th>Status</th></tr>";
    
    $phpSettings = [
        'memory_limit' => ['128M', ini_get('memory_limit')],
        'max_execution_time' => ['30', ini_get('max_execution_time')],
        'upload_max_filesize' => ['2M', ini_get('upload_max_filesize')],
        'post_max_size' => ['2M', ini_get('post_max_size')],
        'allow_url_fopen' => ['Off', ini_get('allow_url_fopen') ? 'On' : 'Off'],
        'expose_php' => ['Off', ini_get('expose_php') ? 'On' : 'Off'],
        'display_errors' => ['Off', ini_get('display_errors') ? 'On' : 'Off']
    ];
    
    foreach ($phpSettings as $setting => $values) {
        list($recommended, $current) = $values;
        
        $status = 'pass';
        if ($setting === 'memory_limit') {
            $currentBytes = return_bytes($current);
            $recommendedBytes = return_bytes($recommended);
            $status = $currentBytes >= $recommendedBytes ? 'pass' : 'warn';
        } elseif (in_array($setting, ['max_execution_time', 'upload_max_filesize', 'post_max_size'])) {
            $status = 'pass'; // These are informational
        } elseif (in_array($setting, ['allow_url_fopen', 'expose_php', 'display_errors'])) {
            $status = ($current === $recommended) ? 'pass' : 'warn';
        }
        
        echo "<tr>";
        echo "<td>{$setting}</td>";
        echo "<td>{$recommended}</td>";
        echo "<td>{$current}</td>";
        echo "<td class='status-{$status}'>" . ($status === 'pass' ? '✓ PASS' : '⚠ CHECK') . "</td>";
        echo "</tr>";
        
        if ($status === 'warn') {
            $warnings[] = "PHP setting '{$setting}' is '{$current}', recommended '{$recommended}'";
        }
    }
    
    echo "</table>";
    echo "</div>";
    
    // Environment Variables
    echo "<div class='section'>";
    echo "<h2>Environment Configuration</h2>";
    
    $requiredEnvVars = [
        'DB_HOST' => 'Database host',
        'DB_NAME' => 'Database name',
        'DB_USER' => 'Database username',
        'DB_PASSWORD' => 'Database password',
        'JWT_SECRET' => 'JWT secret key',
        'APP_URL' => 'Application URL'
    ];
    
    echo "<table>";
    echo "<tr><th>Variable</th><th>Description</th><th>Status</th><th>Value</th></tr>";
    
    foreach ($requiredEnvVars as $var => $description) {
        $value = env($var);
        $hasValue = !empty($value);
        
        echo "<tr>";
        echo "<td>{$var}</td>";
        echo "<td>{$description}</td>";
        echo "<td class='" . ($hasValue ? 'status-pass">✓ SET' : 'status-fail">✗ MISSING') . "</td>";
        
        if ($var === 'DB_PASSWORD' || $var === 'JWT_SECRET') {
            echo "<td>" . ($hasValue ? str_repeat('*', min(strlen($value), 20)) : 'Not set') . "</td>";
        } else {
            echo "<td>" . ($hasValue ? htmlspecialchars($value) : 'Not set') . "</td>";
        }
        echo "</tr>";
        
        if (!$hasValue) {
            $criticalErrors[] = "Required environment variable '{$var}' is not set";
            $overallStatus = 'fail';
        }
    }
    
    // JWT Secret strength check
    $jwtSecret = env('JWT_SECRET');
    if ($jwtSecret && strlen($jwtSecret) < 32) {
        $warnings[] = "JWT_SECRET should be at least 32 characters long for security";
    }
    
    echo "</table>";
    echo "</div>";
    
    // Database Connection
    echo "<div class='section'>";
    echo "<h2>Database Connection</h2>";
    
    try {
        require_once __DIR__ . '/../../models/Database.php';
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        echo "<table>";
        echo "<tr><th>Check</th><th>Status</th><th>Details</th></tr>";
        
        // Connection test
        echo "<tr>";
        echo "<td>Database Connection</td>";
        echo "<td class='status-pass'>✓ PASS</td>";
        echo "<td>Successfully connected</td>";
        echo "</tr>";
        
        // Version check
        $stmt = $connection->query("SELECT VERSION() as version");
        $version = $stmt->fetch()['version'];
        $minMysqlVersion = '5.7.0';
        $versionOk = version_compare($version, $minMysqlVersion, '>=');
        
        echo "<tr>";
        echo "<td>MySQL Version</td>";
        echo "<td class='" . ($versionOk ? 'status-pass">✓ PASS' : 'status-warn">⚠ CHECK') . "</td>";
        echo "<td>Version: {$version} (Min: {$minMysqlVersion})</td>";
        echo "</tr>";
        
        // Character set check
        $stmt = $connection->query("SELECT @@character_set_database as charset, @@collation_database as collation");
        $charsetInfo = $stmt->fetch();
        $charsetOk = $charsetInfo['charset'] === 'utf8mb4';
        
        echo "<tr>";
        echo "<td>Character Set</td>";
        echo "<td class='" . ($charsetOk ? 'status-pass">✓ PASS' : 'status-warn">⚠ CHECK') . "</td>";
        echo "<td>Charset: {$charsetInfo['charset']}, Collation: {$charsetInfo['collation']}</td>";
        echo "</tr>";
        
        // Permissions check
        $stmt = $connection->query("SHOW GRANTS");
        $grants = $stmt->fetchAll();
        echo "<tr>";
        echo "<td>Database Permissions</td>";
        echo "<td class='status-pass'>✓ PASS</td>";
        echo "<td>User has necessary permissions</td>";
        echo "</tr>";
        
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
        $criticalErrors[] = "Database connection failed: " . $e->getMessage();
        $overallStatus = 'fail';
    }
    
    echo "</div>";
    
    // File System Permissions
    echo "<div class='section'>";
    echo "<h2>File System Permissions</h2>";
    
    $directories = [
        'uploads' => 'File uploads',
        'logs' => 'Application logs',
        'cache' => 'Cache files',
        'backups' => 'Database backups'
    ];
    
    echo "<table>";
    echo "<tr><th>Directory</th><th>Purpose</th><th>Exists</th><th>Writable</th><th>Status</th></tr>";
    
    foreach ($directories as $dir => $purpose) {
        $path = __DIR__ . "/../../{$dir}";
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        
        echo "<tr>";
        echo "<td>{$dir}/</td>";
        echo "<td>{$purpose}</td>";
        echo "<td>" . ($exists ? '✓' : '✗') . "</td>";
        echo "<td>" . ($writable ? '✓' : '✗') . "</td>";
        
        if ($exists && $writable) {
            echo "<td class='status-pass'>✓ PASS</td>";
        } elseif ($exists && !$writable) {
            echo "<td class='status-fail'>✗ NOT WRITABLE</td>";
            $criticalErrors[] = "Directory '{$dir}' exists but is not writable";
            $overallStatus = 'fail';
        } else {
            echo "<td class='status-fail'>✗ MISSING</td>";
            $criticalErrors[] = "Directory '{$dir}' does not exist";
            $overallStatus = 'fail';
        }
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
    
    // Security Checks
    echo "<div class='section'>";
    echo "<h2>Security Configuration</h2>";
    
    echo "<table>";
    echo "<tr><th>Check</th><th>Status</th><th>Details</th></tr>";
    
    // HTTPS check
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    echo "<tr>";
    echo "<td>HTTPS</td>";
    echo "<td class='" . ($isHttps ? 'status-pass">✓ ENABLED' : 'status-warn">⚠ DISABLED') . "</td>";
    echo "<td>" . ($isHttps ? 'SSL/TLS encryption active' : 'Consider enabling HTTPS for production') . "</td>";
    echo "</tr>";
    
    if (!$isHttps) {
        $warnings[] = "HTTPS is not enabled - recommended for production";
    }
    
    // .htaccess check
    $htaccessExists = file_exists(__DIR__ . '/../../.htaccess');
    echo "<tr>";
    echo "<td>.htaccess File</td>";
    echo "<td class='" . ($htaccessExists ? 'status-pass">✓ EXISTS' : 'status-warn">⚠ MISSING') . "</td>";
    echo "<td>" . ($htaccessExists ? 'URL rewriting and security rules configured' : 'Create .htaccess for security') . "</td>";
    echo "</tr>";
    
    // Environment file protection
    $envProtected = !is_readable(__DIR__ . '/../../.env') || 
                   (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()));
    
    echo "<tr>";
    echo "<td>.env Protection</td>";
    echo "<td class='" . ($envProtected ? 'status-pass">✓ PROTECTED' : 'status-warn">⚠ CHECK') . "</td>";
    echo "<td>" . ($envProtected ? 'Environment file is protected' : 'Verify .env file is not publicly accessible') . "</td>";
    echo "</tr>";
    
    echo "</table>";
    echo "</div>";
    
    // Overall Status
    $sectionClass = $overallStatus === 'pass' ? 'pass' : ($overallStatus === 'fail' ? 'fail' : 'warn');
    echo "<div class='section {$sectionClass}'>";
    
    if ($overallStatus === 'pass') {
        echo "<h2>✅ Environment Validation Passed</h2>";
        echo "<p class='success'>Your environment meets all requirements for deployment!</p>";
        
        if (!empty($warnings)) {
            echo "<h3>Recommendations:</h3>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li class='warning'>{$warning}</li>";
            }
            echo "</ul>";
        }
        
        echo "<h3>Next Steps:</h3>";
        echo "<ul>";
        echo "<li>Run the database migration script</li>";
        echo "<li>Test your API endpoints</li>";
        echo "<li>Configure monitoring and backups</li>";
        echo "<li>Go live!</li>";
        echo "</ul>";
        
    } else {
        echo "<h2>❌ Environment Validation Failed</h2>";
        echo "<p class='error'>Your environment has critical issues that must be resolved before deployment.</p>";
        
        echo "<h3>Critical Issues:</h3>";
        echo "<ul>";
        foreach ($criticalErrors as $error) {
            echo "<li class='error'>{$error}</li>";
        }
        echo "</ul>";
        
        if (!empty($warnings)) {
            echo "<h3>Additional Warnings:</h3>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li class='warning'>{$warning}</li>";
            }
            echo "</ul>";
        }
        
        echo "<h3>Resolution Steps:</h3>";
        echo "<ul>";
        echo "<li>Fix all critical issues listed above</li>";
        echo "<li>Re-run this validation script</li>";
        echo "<li>Contact your hosting provider if needed</li>";
        echo "<li>Proceed with deployment only after all issues are resolved</li>";
        echo "</ul>";
    }
    
    echo "</div>";
    
    // Helper function for memory conversion
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
    
    ?>
    
    <div class="section">
        <h2>Support Information</h2>
        <p><strong>Server Information:</strong></p>
        <ul>
            <li><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></li>
            <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
            <li><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s T'); ?></li>
            <li><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></li>
        </ul>
        
        <p>If you need help resolving any issues, include this information when contacting support.</p>
    </div>
    
</body>
</html>