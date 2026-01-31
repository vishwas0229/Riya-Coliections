<?php
/**
 * Pre-Deployment Backup Script
 * 
 * This script creates a complete backup before deployment to enable rollback
 * if needed. It backs up both database and files.
 * 
 * Requirements: 14.3, 19.1
 */

// Prevent direct access without confirmation
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'backup') {
    die('Access denied. Add ?confirm=backup to run backup.');
}

// Load configuration
require_once __DIR__ . '/../../config/environment.php';
require_once __DIR__ . '/../../models/Database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Increase execution time for large backups
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Pre-Deployment Backup - Riya Collections</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007cba; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .progress { background: #f0f0f0; border-radius: 4px; padding: 3px; margin: 10px 0; }
        .progress-bar { background: #4CAF50; height: 20px; border-radius: 2px; transition: width 0.3s; }
    </style>
</head>
<body>
    <h1>Pre-Deployment Backup</h1>
    <p>Creating complete backup before deployment...</p>
    
    <?php
    
    $backupDir = __DIR__ . '/../../backups';
    $timestamp = date('Y-m-d_H-i-s');
    $backupName = "pre_deploy_backup_{$timestamp}";
    $backupPath = "{$backupDir}/{$backupName}";
    
    $errors = [];
    $warnings = [];
    
    try {
        echo "<div class='step'>";
        echo "<h2>Step 1: Backup Preparation</h2>";
        
        // Create backup directory if it doesn't exist
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception("Failed to create backup directory: {$backupDir}");
            }
            echo "<p class='success'>✓ Created backup directory</p>";
        } else {
            echo "<p class='info'>ℹ Backup directory exists</p>";
        }
        
        // Create specific backup folder
        if (!mkdir($backupPath, 0755, true)) {
            throw new Exception("Failed to create backup folder: {$backupPath}");
        }
        echo "<p class='success'>✓ Created backup folder: {$backupName}</p>";
        
        // Check available disk space
        $freeSpace = disk_free_space($backupDir);
        $totalSpace = disk_total_space($backupDir);
        $usedSpace = $totalSpace - $freeSpace;
        $freeSpaceMB = round($freeSpace / 1024 / 1024, 2);
        
        echo "<p class='info'>Available disk space: {$freeSpaceMB} MB</p>";
        
        if ($freeSpace < 100 * 1024 * 1024) { // Less than 100MB
            $warnings[] = "Low disk space available for backup";
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 2: Database Backup</h2>";
        
        // Get database configuration
        $dbConfig = getDatabaseConfig();
        
        // Connect to database
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        echo "<p class='info'>Backing up database: {$dbConfig['database']}</p>";
        
        // Get all tables
        $stmt = $connection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p class='info'>Found " . count($tables) . " tables to backup</p>";
        
        // Create SQL backup file
        $sqlBackupFile = "{$backupPath}/database_backup.sql";
        $sqlContent = "-- Riya Collections Database Backup\n";
        $sqlContent .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $sqlContent .= "-- Database: {$dbConfig['database']}\n\n";
        $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        $totalTables = count($tables);
        $processedTables = 0;
        
        foreach ($tables as $table) {
            echo "<p class='info'>Backing up table: {$table}</p>";
            
            // Get table structure
            $stmt = $connection->query("SHOW CREATE TABLE `{$table}`");
            $createTable = $stmt->fetch();
            
            $sqlContent .= "-- Table structure for {$table}\n";
            $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sqlContent .= $createTable['Create Table'] . ";\n\n";
            
            // Get table data
            $stmt = $connection->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $sqlContent .= "-- Data for table {$table}\n";
                $sqlContent .= "INSERT INTO `{$table}` (";
                
                $columns = array_keys($rows[0]);
                $sqlContent .= "`" . implode("`, `", $columns) . "`";
                $sqlContent .= ") VALUES\n";
                
                $valueStrings = [];
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $valueStrings[] = "(" . implode(", ", $values) . ")";
                }
                
                $sqlContent .= implode(",\n", $valueStrings) . ";\n\n";
            }
            
            $processedTables++;
            $progress = round(($processedTables / $totalTables) * 100);
            echo "<div class='progress'>";
            echo "<div class='progress-bar' style='width: {$progress}%'></div>";
            echo "</div>";
            echo "<p class='info'>Progress: {$progress}% ({$processedTables}/{$totalTables})</p>";
        }
        
        $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write SQL backup file
        if (file_put_contents($sqlBackupFile, $sqlContent) === false) {
            throw new Exception("Failed to write database backup file");
        }
        
        $backupSize = filesize($sqlBackupFile);
        $backupSizeMB = round($backupSize / 1024 / 1024, 2);
        
        echo "<p class='success'>✓ Database backup completed</p>";
        echo "<p class='info'>Backup size: {$backupSizeMB} MB</p>";
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 3: File System Backup</h2>";
        
        // Backup critical files and directories
        $filesToBackup = [
            '.env' => 'Environment configuration',
            '.htaccess' => 'Web server configuration',
            'config/' => 'Application configuration',
            'uploads/' => 'Uploaded files',
            'logs/' => 'Application logs (recent)',
            'composer.json' => 'Dependencies configuration',
            'composer.lock' => 'Dependencies lock file'
        ];
        
        $fileBackupDir = "{$backupPath}/files";
        if (!mkdir($fileBackupDir, 0755, true)) {
            throw new Exception("Failed to create file backup directory");
        }
        
        foreach ($filesToBackup as $source => $description) {
            $sourcePath = __DIR__ . "/../../{$source}";
            $destPath = "{$fileBackupDir}/{$source}";
            
            echo "<p class='info'>Backing up: {$source} ({$description})</p>";
            
            if (is_file($sourcePath)) {
                // Backup single file
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                
                if (copy($sourcePath, $destPath)) {
                    echo "<p class='success'>✓ Backed up file: {$source}</p>";
                } else {
                    $warnings[] = "Failed to backup file: {$source}";
                    echo "<p class='warning'>⚠ Failed to backup file: {$source}</p>";
                }
                
            } elseif (is_dir($sourcePath)) {
                // Backup directory
                if (copyDirectory($sourcePath, $destPath)) {
                    echo "<p class='success'>✓ Backed up directory: {$source}</p>";
                } else {
                    $warnings[] = "Failed to backup directory: {$source}";
                    echo "<p class='warning'>⚠ Failed to backup directory: {$source}</p>";
                }
                
            } else {
                echo "<p class='warning'>⚠ Source not found: {$source}</p>";
                $warnings[] = "Source not found for backup: {$source}";
            }
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 4: Backup Verification</h2>";
        
        // Verify backup integrity
        $verificationResults = [];
        
        // Verify database backup
        if (file_exists($sqlBackupFile) && filesize($sqlBackupFile) > 0) {
            $verificationResults['database'] = true;
            echo "<p class='success'>✓ Database backup verified</p>";
        } else {
            $verificationResults['database'] = false;
            $errors[] = "Database backup verification failed";
            echo "<p class='error'>✗ Database backup verification failed</p>";
        }
        
        // Verify file backups
        $fileBackupSuccess = true;
        foreach ($filesToBackup as $source => $description) {
            $sourcePath = __DIR__ . "/../../{$source}";
            $destPath = "{$fileBackupDir}/{$source}";
            
            if (file_exists($sourcePath) && file_exists($destPath)) {
                echo "<p class='success'>✓ File backup verified: {$source}</p>";
            } else {
                $fileBackupSuccess = false;
                echo "<p class='warning'>⚠ File backup missing: {$source}</p>";
            }
        }
        
        $verificationResults['files'] = $fileBackupSuccess;
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 5: Backup Summary</h2>";
        
        // Create backup manifest
        $manifest = [
            'backup_name' => $backupName,
            'created_at' => date('Y-m-d H:i:s'),
            'database' => [
                'name' => $dbConfig['database'],
                'tables' => count($tables),
                'file' => 'database_backup.sql',
                'size_mb' => $backupSizeMB
            ],
            'files' => $filesToBackup,
            'verification' => $verificationResults,
            'warnings' => $warnings,
            'errors' => $errors
        ];
        
        $manifestFile = "{$backupPath}/backup_manifest.json";
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
        
        // Calculate total backup size
        $totalSize = getDirSize($backupPath);
        $totalSizeMB = round($totalSize / 1024 / 1024, 2);
        
        echo "<p class='info'><strong>Backup Summary:</strong></p>";
        echo "<ul>";
        echo "<li>Backup Name: {$backupName}</li>";
        echo "<li>Database Tables: " . count($tables) . "</li>";
        echo "<li>Total Size: {$totalSizeMB} MB</li>";
        echo "<li>Location: {$backupPath}</li>";
        echo "</ul>";
        
        if (empty($errors)) {
            echo "<p class='success'>✅ Backup completed successfully!</p>";
            
            echo "<h3>Backup Files Created:</h3>";
            echo "<ul>";
            echo "<li><code>database_backup.sql</code> - Complete database dump</li>";
            echo "<li><code>files/</code> - Critical application files</li>";
            echo "<li><code>backup_manifest.json</code> - Backup metadata</li>";
            echo "</ul>";
            
            echo "<h3>Restoration Instructions:</h3>";
            echo "<p>To restore this backup:</p>";
            echo "<ol>";
            echo "<li>Import database: <code>mysql -u username -p database_name < database_backup.sql</code></li>";
            echo "<li>Restore files from the <code>files/</code> directory</li>";
            echo "<li>Update configuration as needed</li>";
            echo "</ol>";
            
        } else {
            echo "<p class='error'>❌ Backup completed with errors</p>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li class='error'>{$error}</li>";
            }
            echo "</ul>";
        }
        
        if (!empty($warnings)) {
            echo "<h3>Warnings:</h3>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li class='warning'>{$warning}</li>";
            }
            echo "</ul>";
        }
        
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='step'>";
        echo "<h2>❌ Backup Failed</h2>";
        echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
        echo "<p>Please resolve the issue and try again.</p>";
        echo "</div>";
    }
    
    // Helper function to copy directory recursively
    function copyDirectory($src, $dst) {
        if (!is_dir($src)) {
            return false;
        }
        
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $dir = opendir($src);
        if (!$dir) {
            return false;
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                
                if (is_dir($srcFile)) {
                    copyDirectory($srcFile, $dstFile);
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
        
        closedir($dir);
        return true;
    }
    
    // Helper function to calculate directory size
    function getDirSize($dir) {
        $size = 0;
        if (is_dir($dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($files as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
    
    ?>
    
    <div class="step">
        <h2>Next Steps</h2>
        <p>Now that your backup is complete, you can proceed with deployment:</p>
        <ol>
            <li>Upload your new application files</li>
            <li>Run the database migration script</li>
            <li>Test the new deployment</li>
            <li>If issues occur, restore from this backup</li>
        </ol>
        
        <p><strong>Important:</strong> Keep this backup safe until you're confident the new deployment is working correctly.</p>
    </div>
    
</body>
</html>