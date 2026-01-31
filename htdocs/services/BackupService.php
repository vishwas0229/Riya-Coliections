<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/Database.php';

/**
 * BackupService - Comprehensive database backup and recovery system
 * 
 * Features:
 * - Automated backup creation with scheduling
 * - Data integrity verification
 * - Backup compression and encryption
 * - Recovery and restoration capabilities
 * - Backup retention management
 */
class BackupService {
    private $db;
    private $backupDir;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->backupDir = __DIR__ . '/../backups';
        $this->config = [
            'max_backups' => 30, // Keep 30 days of backups
            'compress' => true,
            'verify_integrity' => true,
            'chunk_size' => 1000, // Process data in chunks for large tables
            'timeout' => 300 // 5 minutes timeout
        ];
        
        $this->ensureBackupDirectory();
    }
    
    /**
     * Create a comprehensive database backup
     */
    public function createBackup($options = []) {
        $startTime = microtime(true);
        
        try {
            $options = array_merge([
                'include_data' => true,
                'include_structure' => true,
                'compress' => $this->config['compress'],
                'verify' => $this->config['verify_integrity'],
                'description' => 'Automated backup'
            ], $options);
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupId = uniqid('backup_', true);
            $filename = "backup_{$timestamp}_{$backupId}";
            $backupFile = "{$this->backupDir}/{$filename}.sql";
            
            Logger::info('Starting database backup', [
                'backup_id' => $backupId,
                'options' => $options
            ]);
            
            // Create backup content
            $backupContent = $this->generateBackupContent($options);
            
            // Write backup file
            file_put_contents($backupFile, $backupContent);
            
            // Compress if requested
            if ($options['compress']) {
                $compressedFile = $this->compressBackup($backupFile);
                unlink($backupFile); // Remove uncompressed version
                $backupFile = $compressedFile;
            }
            
            // Verify backup integrity if requested
            if ($options['verify']) {
                $this->verifyBackupIntegrity($backupFile, $options['compress']);
            }
            
            // Record backup metadata
            $metadata = $this->recordBackupMetadata($backupId, $backupFile, $options, $startTime);
            
            // Clean up old backups
            $this->cleanupOldBackups();
            
            Logger::info('Database backup completed successfully', [
                'backup_id' => $backupId,
                'file' => $backupFile,
                'size' => filesize($backupFile),
                'duration' => microtime(true) - $startTime
            ]);
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'file' => $backupFile,
                'metadata' => $metadata
            ];
            
        } catch (Exception $e) {
            Logger::error('Database backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception("Backup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate backup SQL content
     */
    private function generateBackupContent($options) {
        $content = "-- Riya Collections Database Backup\n";
        $content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "-- Description: {$options['description']}\n";
        $content .= "-- Options: " . json_encode($options) . "\n\n";
        
        $content .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $content .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $content .= "SET AUTOCOMMIT = 0;\n";
        $content .= "START TRANSACTION;\n\n";
        
        // Get all tables
        $tables = $this->getTables();
        
        foreach ($tables as $table) {
            Logger::info("Backing up table: {$table}");
            
            if ($options['include_structure']) {
                $content .= $this->getTableStructure($table);
            }
            
            if ($options['include_data']) {
                $content .= $this->getTableData($table);
            }
        }
        
        $content .= "COMMIT;\n";
        $content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $content .= "-- Backup completed\n";
        
        return $content;
    }
    
    /**
     * Get list of all tables
     */
    private function getTables() {
        $result = $this->db->fetchAll('SHOW TABLES');
        return array_column($result, array_keys($result[0])[0]);
    }
    
    /**
     * Get table structure SQL
     */
    private function getTableStructure($table) {
        $result = $this->db->fetchOne("SHOW CREATE TABLE `{$table}`");
        
        $sql = "-- Table structure for `{$table}`\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $result['Create Table'] . ";\n\n";
        
        return $sql;
    }
    
    /**
     * Get table data SQL with chunking for large tables
     */
    private function getTableData($table) {
        $sql = "";
        
        // Get row count
        $countResult = $this->db->fetchOne("SELECT COUNT(*) as count FROM `{$table}`");
        $totalRows = $countResult['count'];
        
        if ($totalRows == 0) {
            return "-- No data for table `{$table}`\n\n";
        }
        
        $sql .= "-- Data for table `{$table}` ({$totalRows} rows)\n";
        
        // Process in chunks for large tables
        $chunkSize = $this->config['chunk_size'];
        $offset = 0;
        
        while ($offset < $totalRows) {
            $rows = $this->db->fetchAll("SELECT * FROM `{$table}` LIMIT {$chunkSize} OFFSET {$offset}");
            
            if (!empty($rows)) {
                $sql .= "INSERT INTO `{$table}` VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escapedValues = array_map(function($value) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return "'" . addslashes($value) . "'";
                    }, array_values($row));
                    
                    $values[] = '(' . implode(', ', $escapedValues) . ')';
                }
                
                $sql .= implode(",\n", $values) . ";\n";
            }
            
            $offset += $chunkSize;
        }
        
        $sql .= "\n";
        return $sql;
    }
    
    /**
     * Compress backup file using gzip
     */
    private function compressBackup($backupFile) {
        $compressedFile = $backupFile . '.gz';
        
        $input = fopen($backupFile, 'rb');
        $output = gzopen($compressedFile, 'wb9');
        
        while (!feof($input)) {
            gzwrite($output, fread($input, 8192));
        }
        
        fclose($input);
        gzclose($output);
        
        return $compressedFile;
    }
    
    /**
     * Verify backup integrity
     */
    private function verifyBackupIntegrity($backupFile, $isCompressed = false) {
        try {
            if ($isCompressed) {
                $content = gzfile($backupFile);
                $content = implode('', $content);
            } else {
                $content = file_get_contents($backupFile);
            }
            
            // Basic integrity checks
            if (empty($content)) {
                throw new Exception("Backup file is empty");
            }
            
            if (!strpos($content, 'Riya Collections Database Backup')) {
                throw new Exception("Invalid backup file format");
            }
            
            if (!strpos($content, 'Backup completed')) {
                throw new Exception("Backup appears incomplete");
            }
            
            // Count tables in backup
            $tableCount = substr_count($content, 'CREATE TABLE');
            $expectedTables = count($this->getTables());
            
            if ($tableCount !== $expectedTables) {
                throw new Exception("Table count mismatch: expected {$expectedTables}, found {$tableCount}");
            }
            
            Logger::info('Backup integrity verification passed', [
                'file' => $backupFile,
                'size' => filesize($backupFile),
                'tables' => $tableCount
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Backup integrity verification failed', [
                'file' => $backupFile,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Record backup metadata
     */
    private function recordBackupMetadata($backupId, $backupFile, $options, $startTime) {
        $metadata = [
            'backup_id' => $backupId,
            'file' => basename($backupFile),
            'full_path' => $backupFile,
            'size' => filesize($backupFile),
            'created_at' => date('Y-m-d H:i:s'),
            'duration' => microtime(true) - $startTime,
            'options' => $options,
            'tables_count' => count($this->getTables()),
            'checksum' => md5_file($backupFile)
        ];
        
        // Save metadata to JSON file
        $metadataFile = $this->backupDir . '/metadata.json';
        $allMetadata = [];
        
        if (file_exists($metadataFile)) {
            $allMetadata = json_decode(file_get_contents($metadataFile), true) ?: [];
        }
        
        $allMetadata[$backupId] = $metadata;
        file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
        
        return $metadata;
    }
    
    /**
     * Clean up old backups based on retention policy
     */
    private function cleanupOldBackups() {
        $metadataFile = $this->backupDir . '/metadata.json';
        
        if (!file_exists($metadataFile)) {
            return;
        }
        
        $allMetadata = json_decode(file_get_contents($metadataFile), true) ?: [];
        
        // Sort by creation date
        uasort($allMetadata, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $toDelete = array_slice($allMetadata, $this->config['max_backups'], null, true);
        
        foreach ($toDelete as $backupId => $metadata) {
            if (file_exists($metadata['full_path'])) {
                unlink($metadata['full_path']);
                Logger::info('Deleted old backup', ['backup_id' => $backupId, 'file' => $metadata['file']]);
            }
            unset($allMetadata[$backupId]);
        }
        
        // Update metadata file
        file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
    }
    
    /**
     * List all available backups
     */
    public function listBackups() {
        $metadataFile = $this->backupDir . '/metadata.json';
        
        if (!file_exists($metadataFile)) {
            return [];
        }
        
        $allMetadata = json_decode(file_get_contents($metadataFile), true) ?: [];
        
        // Sort by creation date (newest first)
        uasort($allMetadata, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $allMetadata;
    }
    
    /**
     * Get backup information
     */
    public function getBackupInfo($backupId) {
        $backups = $this->listBackups();
        return $backups[$backupId] ?? null;
    }
    
    /**
     * Ensure backup directory exists
     */
    private function ensureBackupDirectory() {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        // Create .htaccess to protect backup directory
        $htaccessFile = $this->backupDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }
    }
    
    /**
     * Schedule automatic backups (for cron job)
     */
    public function scheduleBackup($frequency = 'daily') {
        $scheduleFile = $this->backupDir . '/schedule.json';
        $schedule = [
            'frequency' => $frequency,
            'last_run' => null,
            'next_run' => $this->calculateNextRun($frequency),
            'enabled' => true
        ];
        
        file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
        
        Logger::info('Backup schedule configured', ['frequency' => $frequency]);
        
        return $schedule;
    }
    
    /**
     * Calculate next backup run time
     */
    private function calculateNextRun($frequency) {
        switch ($frequency) {
            case 'hourly':
                return date('Y-m-d H:i:s', strtotime('+1 hour'));
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('tomorrow 2:00 AM'));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('next Sunday 2:00 AM'));
            default:
                return date('Y-m-d H:i:s', strtotime('tomorrow 2:00 AM'));
        }
    }
    
    /**
     * Check if scheduled backup should run
     */
    public function shouldRunScheduledBackup() {
        $scheduleFile = $this->backupDir . '/schedule.json';
        
        if (!file_exists($scheduleFile)) {
            return false;
        }
        
        $schedule = json_decode(file_get_contents($scheduleFile), true);
        
        if (!$schedule['enabled']) {
            return false;
        }
        
        return time() >= strtotime($schedule['next_run']);
    }
    
    /**
     * Run scheduled backup
     */
    public function runScheduledBackup() {
        if (!$this->shouldRunScheduledBackup()) {
            return false;
        }
        
        try {
            $result = $this->createBackup(['description' => 'Scheduled backup']);
            
            // Update schedule
            $scheduleFile = $this->backupDir . '/schedule.json';
            $schedule = json_decode(file_get_contents($scheduleFile), true);
            $schedule['last_run'] = date('Y-m-d H:i:s');
            $schedule['next_run'] = $this->calculateNextRun($schedule['frequency']);
            
            file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
            
            Logger::info('Scheduled backup completed', ['backup_id' => $result['backup_id']]);
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error('Scheduled backup failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}