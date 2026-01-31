<?php
/**
 * Backup Cron Script
 * 
 * This script should be run via cron to perform scheduled backups.
 * 
 * Usage:
 * php backup_cron.php [--force] [--frequency=daily]
 * 
 * Options:
 * --force      Force backup even if not scheduled
 * --frequency  Set backup frequency (hourly, daily, weekly)
 */

// Ensure script is run from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Change to script directory
chdir(__DIR__);

// Include required files
require_once '../config/environment.php';
require_once '../services/BackupService.php';
require_once '../utils/Logger.php';

// Parse command line arguments
$options = getopt('', ['force', 'frequency:']);
$force = isset($options['force']);
$frequency = $options['frequency'] ?? null;

try {
    $backupService = new BackupService();
    
    // Set up backup schedule if frequency is provided
    if ($frequency) {
        if (!in_array($frequency, ['hourly', 'daily', 'weekly'])) {
            echo "Error: Invalid frequency. Must be: hourly, daily, or weekly\n";
            exit(1);
        }
        
        $schedule = $backupService->scheduleBackup($frequency);
        echo "Backup schedule configured: {$frequency}\n";
        echo "Next run: {$schedule['next_run']}\n";
        
        if (!$force) {
            exit(0);
        }
    }
    
    // Check if backup should run
    if (!$force && !$backupService->shouldRunScheduledBackup()) {
        echo "No scheduled backup due at this time\n";
        exit(0);
    }
    
    echo "Starting backup process...\n";
    
    // Run the backup
    if ($force) {
        $result = $backupService->createBackup([
            'description' => 'Manual backup via cron script'
        ]);
    } else {
        $result = $backupService->runScheduledBackup();
    }
    
    if ($result['success']) {
        echo "Backup completed successfully\n";
        echo "Backup ID: {$result['backup_id']}\n";
        echo "File: {$result['file']}\n";
        echo "Size: " . formatBytes(filesize($result['file'])) . "\n";
        
        if (isset($result['metadata'])) {
            echo "Duration: " . round($result['metadata']['duration'], 2) . " seconds\n";
            echo "Tables: {$result['metadata']['tables_count']}\n";
        }
    } else {
        echo "Backup failed\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    Logger::error('Backup cron script failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
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