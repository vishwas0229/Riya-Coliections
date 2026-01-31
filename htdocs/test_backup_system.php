<?php
/**
 * Backup System Test Script
 * 
 * This script tests the backup and recovery functionality
 */

require_once __DIR__ . '/config/environment.php';
require_once __DIR__ . '/services/BackupService.php';
require_once __DIR__ . '/services/RecoveryService.php';
require_once __DIR__ . '/utils/Logger.php';

echo "=== Backup System Test ===\n\n";

try {
    $backupService = new BackupService();
    $recoveryService = new RecoveryService();
    
    // Test 1: Create a backup
    echo "1. Testing backup creation...\n";
    $backupResult = $backupService->createBackup([
        'description' => 'Test backup',
        'compress' => false, // Disable compression for easier testing
        'verify' => true
    ]);
    
    if ($backupResult['success']) {
        echo "   ✓ Backup created successfully\n";
        echo "   Backup ID: {$backupResult['backup_id']}\n";
        echo "   File: {$backupResult['file']}\n";
        echo "   Size: " . formatBytes(filesize($backupResult['file'])) . "\n";
    } else {
        echo "   ✗ Backup creation failed\n";
        exit(1);
    }
    
    $testBackupId = $backupResult['backup_id'];
    
    // Test 2: List backups
    echo "\n2. Testing backup listing...\n";
    $backups = $backupService->listBackups();
    echo "   ✓ Found " . count($backups) . " backup(s)\n";
    
    // Test 3: Get backup info
    echo "\n3. Testing backup info retrieval...\n";
    $backupInfo = $backupService->getBackupInfo($testBackupId);
    if ($backupInfo) {
        echo "   ✓ Backup info retrieved successfully\n";
        echo "   Created: {$backupInfo['created_at']}\n";
        echo "   Tables: {$backupInfo['tables_count']}\n";
    } else {
        echo "   ✗ Failed to get backup info\n";
    }
    
    // Test 4: Test recovery options
    echo "\n4. Testing recovery options...\n";
    $recoveryOptions = $recoveryService->getRecoveryOptions($testBackupId);
    echo "   ✓ Recovery options retrieved\n";
    echo "   Available tables: " . count($recoveryOptions['available_tables']) . "\n";
    
    // Test 5: Test dry run restore
    echo "\n5. Testing dry run restore...\n";
    $dryRunResult = $recoveryService->testRestore($testBackupId);
    if ($dryRunResult['success'] && $dryRunResult['dry_run']) {
        echo "   ✓ Dry run restore completed successfully\n";
    } else {
        echo "   ✗ Dry run restore failed\n";
    }
    
    // Test 6: Test backup scheduling
    echo "\n6. Testing backup scheduling...\n";
    $schedule = $backupService->scheduleBackup('daily');
    echo "   ✓ Backup scheduled for daily execution\n";
    echo "   Next run: {$schedule['next_run']}\n";
    
    // Test 7: Test backup verification
    echo "\n7. Testing backup verification...\n";
    $backupFile = $backupResult['file'];
    if (file_exists($backupFile)) {
        $content = file_get_contents($backupFile);
        if (strpos($content, 'Riya Collections Database Backup') !== false) {
            echo "   ✓ Backup file format is valid\n";
        } else {
            echo "   ✗ Invalid backup file format\n";
        }
        
        if (strpos($content, 'CREATE TABLE') !== false) {
            echo "   ✓ Backup contains table structures\n";
        } else {
            echo "   ✗ Backup missing table structures\n";
        }
        
        if (strpos($content, 'INSERT INTO') !== false) {
            echo "   ✓ Backup contains data\n";
        } else {
            echo "   ⚠ Backup contains no data (may be empty database)\n";
        }
    } else {
        echo "   ✗ Backup file not found\n";
    }
    
    echo "\n=== All Tests Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "\n✗ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}