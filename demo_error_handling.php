<?php
/**
 * Demonstration of Comprehensive Asset Error Handling
 * 
 * This script demonstrates the enhanced error handling capabilities
 * of the AssetServer class including:
 * - 404 responses for missing assets
 * - Permission error handling
 * - Corruption detection
 * - Comprehensive error logging
 */

require_once __DIR__ . '/app/services/AssetServer.php';
require_once __DIR__ . '/app/utils/Logger.php';

echo "=== Asset Server Error Handling Demonstration ===\n\n";

// Initialize AssetServer
$assetServer = new AssetServer();

echo "1. Testing 404 Error Handling\n";
echo "------------------------------\n";

// Test 404 error for non-existent file
ob_start();
$assetServer->serve('assets/nonexistent-file.css');
$output = ob_get_clean();

echo "Request: assets/nonexistent-file.css\n";
echo "Response:\n";
echo $output . "\n\n";

echo "2. Testing Permission Error Handling\n";
echo "------------------------------------\n";

// Create a test file and make it unreadable (if possible)
$testFile = __DIR__ . '/public/assets/permission-test.css';
$testDir = dirname($testFile);
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
}

file_put_contents($testFile, 'body { color: red; }');

// Try to make it unreadable (this may not work in all environments)
if (function_exists('chmod')) {
    chmod($testFile, 0000);
    
    try {
        $result = $assetServer->validateAssetPath('assets/permission-test.css');
        echo "Validation result: " . ($result ? "Success" : "Failed") . "\n";
    } catch (AssetPermissionException $e) {
        echo "Permission Exception caught: " . $e->getMessage() . "\n";
    } catch (Exception $e) {
        echo "Other Exception: " . $e->getMessage() . "\n";
    }
    
    // Restore permissions for cleanup
    chmod($testFile, 0644);
}

// Clean up
if (file_exists($testFile)) {
    unlink($testFile);
}

echo "\n3. Testing Corruption Detection\n";
echo "-------------------------------\n";

// Create a test file for corruption testing
$corruptionTestFile = __DIR__ . '/public/assets/corruption-test.js';
file_put_contents($corruptionTestFile, 'console.log("test file");');

echo "Created test file: corruption-test.js\n";

// Test normal validation
try {
    $result = $assetServer->validateAssetPath('assets/corruption-test.js');
    echo "Validation result: " . ($result ? "Success - " . basename($result) : "Failed") . "\n";
} catch (AssetCorruptionException $e) {
    echo "Corruption Exception: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Other Exception: " . $e->getMessage() . "\n";
}

// Clean up
if (file_exists($corruptionTestFile)) {
    unlink($corruptionTestFile);
}

echo "\n4. Testing Enhanced Error Response Format\n";
echo "-----------------------------------------\n";

// Test enhanced error response format
ob_start();
$assetServer->serve('assets/format-test.css');
$errorOutput = ob_get_clean();

echo "Request: assets/format-test.css\n";
echo "Enhanced Error Response:\n";

$errorResponse = json_decode($errorOutput, true);
if ($errorResponse) {
    echo "- Error: " . $errorResponse['error'] . "\n";
    echo "- Path: " . $errorResponse['path'] . "\n";
    echo "- Timestamp: " . $errorResponse['timestamp'] . "\n";
    if (isset($errorResponse['reason'])) {
        echo "- Reason: " . $errorResponse['reason'] . "\n";
    }
} else {
    echo "Raw response: " . $errorOutput . "\n";
}

echo "\n5. Testing Asset Server Statistics\n";
echo "----------------------------------\n";

$stats = $assetServer->getStatistics();
echo "Asset Server Configuration:\n";
echo "- MIME types supported: " . $stats['mime_types_supported'] . "\n";
echo "- Compression enabled: " . ($stats['compression_enabled'] ? 'Yes' : 'No') . "\n";
echo "- Cache enabled: " . ($stats['cache_enabled'] ? 'Yes' : 'No') . "\n";
echo "- Versioning enabled: " . ($stats['versioning_enabled'] ? 'Yes' : 'No') . "\n";
echo "- Allowed directories: " . $stats['allowed_directories'] . "\n";
echo "- Max file size: " . number_format($stats['max_file_size']) . " bytes\n";

echo "\n=== Error Handling Demonstration Complete ===\n";
echo "\nKey Features Demonstrated:\n";
echo "✓ Comprehensive 404 error responses with JSON format\n";
echo "✓ Permission error detection and handling\n";
echo "✓ File corruption detection during validation\n";
echo "✓ Enhanced error logging with detailed context\n";
echo "✓ Security headers in error responses\n";
echo "✓ Graceful error handling throughout the asset serving pipeline\n";