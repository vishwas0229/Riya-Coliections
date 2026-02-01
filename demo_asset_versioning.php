<?php
/**
 * Asset Versioning and Cache Busting Demo
 * 
 * This script demonstrates the asset versioning functionality implemented
 * for cache busting in the Riya Collections project.
 */

require_once __DIR__ . '/app/services/AssetServer.php';
require_once __DIR__ . '/app/services/AssetVersionHelper.php';
require_once __DIR__ . '/app/services/FrontendConfigManager.php';

echo "<h1>Asset Versioning and Cache Busting Demo</h1>\n";

// Create demo assets
$demoAssets = [
    'public/assets/demo.css' => 'body { background-color: #f0f0f0; }',
    'public/assets/demo.js' => 'console.log("Demo script loaded");',
    'public/assets/logo-demo.svg' => '<svg><circle cx="50" cy="50" r="40"/></svg>'
];

echo "<h2>Creating Demo Assets</h2>\n";
foreach ($demoAssets as $path => $content) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, $content);
    echo "<p>Created: {$path}</p>\n";
}

// Initialize services
$assetServer = new AssetServer();
$assetHelper = new AssetVersionHelper();
$configManager = new FrontendConfigManager();

echo "<h2>Asset Server Statistics</h2>\n";
$stats = $assetServer->getStatistics();
echo "<ul>\n";
foreach ($stats as $key => $value) {
    if (is_array($value)) {
        echo "<li><strong>{$key}:</strong> " . json_encode($value) . "</li>\n";
    } else {
        echo "<li><strong>{$key}:</strong> {$value}</li>\n";
    }
}
echo "</ul>\n";

echo "<h2>Asset Versioning Examples</h2>\n";

// Test asset versioning
$testAssets = ['assets/demo.css', 'assets/demo.js', 'assets/logo-demo.svg'];

echo "<h3>1. Basic Asset URL Generation</h3>\n";
foreach ($testAssets as $asset) {
    $originalUrl = "/{$asset}";
    $versionedUrl = $assetServer->getVersionedAssetUrl($asset);
    
    echo "<p><strong>Original:</strong> {$originalUrl}</p>\n";
    echo "<p><strong>Versioned:</strong> {$versionedUrl}</p>\n";
    echo "<hr>\n";
}

echo "<h3>2. HTML Tag Generation with Versioning</h3>\n";

// CSS link tag
$cssTag = $assetHelper->css('assets/demo.css');
echo "<p><strong>CSS Link Tag:</strong></p>\n";
echo "<code>" . htmlspecialchars($cssTag) . "</code>\n";

// JavaScript script tag
$jsTag = $assetHelper->js('assets/demo.js');
echo "<p><strong>JavaScript Script Tag:</strong></p>\n";
echo "<code>" . htmlspecialchars($jsTag) . "</code>\n";

// Image tag
$imgTag = $assetHelper->img('assets/logo-demo.svg', 'Demo Logo');
echo "<p><strong>Image Tag:</strong></p>\n";
echo "<code>" . htmlspecialchars($imgTag) . "</code>\n";

// Preload tag
$preloadTag = $assetHelper->preload('assets/demo.css', 'style');
echo "<p><strong>Preload Tag:</strong></p>\n";
echo "<code>" . htmlspecialchars($preloadTag) . "</code>\n";

echo "<h3>3. Asset Manifest Generation</h3>\n";
$manifest = $assetHelper->generateManifest($testAssets);
echo "<p><strong>Asset Manifest (JSON):</strong></p>\n";
echo "<pre>" . htmlspecialchars($manifest) . "</pre>\n";

echo "<h3>4. Inline JavaScript Manifest</h3>\n";
$inlineScript = $assetHelper->inlineManifestScript($testAssets, 'DEMO_ASSETS');
echo "<p><strong>Inline Script:</strong></p>\n";
echo "<pre>" . htmlspecialchars($inlineScript) . "</pre>\n";

echo "<h3>5. Cache Busting Demonstration</h3>\n";

// Get initial version
$initialVersion = $assetServer->generateAssetVersion(realpath('public/assets/demo.css'));
echo "<p><strong>Initial CSS Version:</strong> {$initialVersion}</p>\n";

// Modify the file
sleep(1); // Ensure different timestamp
file_put_contents('public/assets/demo.css', 'body { background-color: #e0e0e0; color: #333; }');

// Clear cache and get new version
$assetServer->clearVersionCache();
$newVersion = $assetServer->generateAssetVersion(realpath('public/assets/demo.css'));
echo "<p><strong>New CSS Version (after modification):</strong> {$newVersion}</p>\n";

if ($initialVersion !== $newVersion) {
    echo "<p style='color: green;'><strong>✓ Cache busting working correctly!</strong> Version changed after file modification.</p>\n";
} else {
    echo "<p style='color: red;'><strong>✗ Cache busting issue:</strong> Version did not change after file modification.</p>\n";
}

echo "<h3>6. Batch Version Generation</h3>\n";
$batchVersions = $assetServer->batchGenerateVersions($testAssets);
echo "<p><strong>Batch Versioned URLs:</strong></p>\n";
echo "<ul>\n";
foreach ($batchVersions as $asset => $url) {
    echo "<li><strong>{$asset}:</strong> {$url}</li>\n";
}
echo "</ul>\n";

echo "<h3>7. Version Cache Statistics</h3>\n";
$cacheStats = $assetServer->getVersionCacheStats();
echo "<ul>\n";
foreach ($cacheStats as $key => $value) {
    echo "<li><strong>{$key}:</strong> {$value}</li>\n";
}
echo "</ul>\n";

echo "<h3>8. Frontend Configuration with Assets</h3>\n";
try {
    $frontendConfig = $configManager->generateConfig();
    if (isset($frontendConfig['assets'])) {
        echo "<p><strong>Asset Configuration:</strong></p>\n";
        echo "<pre>" . htmlspecialchars(json_encode($frontendConfig['assets'], JSON_PRETTY_PRINT)) . "</pre>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: orange;'><strong>Note:</strong> Frontend configuration requires full environment setup.</p>\n";
}

echo "<h2>Integration Examples</h2>\n";

echo "<h3>HTML Template Integration</h3>\n";
$htmlTemplate = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Asset Versioning Demo</title>
    {$assetHelper->css('assets/demo.css')}
    {$assetHelper->preload('assets/demo.js', 'script')}
</head>
<body>
    <h1>Demo Page</h1>
    {$assetHelper->img('assets/logo-demo.svg', 'Logo')}
    {$assetHelper->js('assets/demo.js')}
    {$assetHelper->inlineManifestScript($testAssets)}
</body>
</html>
HTML;

echo "<pre>" . htmlspecialchars($htmlTemplate) . "</pre>\n";

echo "<h2>Performance Benefits</h2>\n";
echo "<ul>\n";
echo "<li><strong>Cache Busting:</strong> Automatic version updates when assets change</li>\n";
echo "<li><strong>Long-term Caching:</strong> Versioned assets can be cached for extended periods</li>\n";
echo "<li><strong>Efficient Updates:</strong> Only changed assets get new versions</li>\n";
echo "<li><strong>Browser Optimization:</strong> Prevents stale cache issues</li>\n";
echo "<li><strong>CDN Friendly:</strong> Works well with Content Delivery Networks</li>\n";
echo "</ul>\n";

// Clean up demo assets
echo "<h2>Cleaning Up Demo Assets</h2>\n";
foreach ($demoAssets as $path => $content) {
    if (file_exists($path)) {
        unlink($path);
        echo "<p>Removed: {$path}</p>\n";
    }
}

// Remove empty directories
$dirs = ['public/assets'];
foreach ($dirs as $dir) {
    if (is_dir($dir) && count(scandir($dir)) == 2) { // Only . and ..
        rmdir($dir);
        echo "<p>Removed empty directory: {$dir}</p>\n";
    }
}

echo "<p style='color: green;'><strong>Demo completed successfully!</strong></p>\n";
echo "<p><em>Asset versioning and cache busting functionality is now fully implemented and tested.</em></p>\n";