<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/BackupService.php';

/**
 * RecoveryService - Database recovery and restoration system
 * 
 * Features:
 * - Full database restoration from backups
 * - Selective table restoration
 * - Data integrity verification during recovery
 * - Recovery rollback capabilities
 * - Recovery testing and validation
 */
class RecoveryService {
    private $db;
    private $backupService;
    private $backupDir;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->backupService = new BackupService();
        $this->backupDir = __DIR__ . '/../backups';
        $this->config = [
            'timeout' => 600, // 10 minutes timeout for recovery
            'verify_after_restore' => true,
            'create_recovery_backup' => true,
            'chunk_size' => 1000
        ];
    }
    
    /**
     * Restore database from backup
     */
    public function restoreFromBackup($backupId, $options = []) {
        $startTime = microtime(true);
        
        try {
            $options = array_merge([
                'verify_before' => true,
                'verify_after' => $this->config['verify_after_restore'],
                'create_backup' => $this->config['create_recovery_backup'],
                'tables' => null, // null = all tables, array = specific tables
                'dry_run' => false
            ], $options);
            
            Logger::info('Starting database recovery', [
                'backup_id' => $backupId,
                'options' => $options
            ]);
            
            // Get backup information
            $backupInfo = $this->backupService->getBackupInfo($backupId);
            if (!$backupInfo) {
                throw new Exception("Backup not found: {$backupId}");
            }
            
            // Verify backup file exists and is readable
            if (!file_exists($backupInfo['full_path'])) {
                throw new Exception("Backup file not found: {$backupInfo['full_path']}");
            }
            
            // Verify backup integrity before restoration
            if ($options['verify_before']) {
                $this->verifyBackupBeforeRestore($backupInfo);
            }
            
            // Create recovery backup of current state
            $recoveryBackupId = null;
            if ($options['create_backup']) {
                $recoveryResult = $this->backupService->createBackup([
                    'description' => "Pre-recovery backup before restoring {$backupId}"
                ]);
                $recoveryBackupId = $recoveryResult['backup_id'];
                
                Logger::info('Created recovery backup', ['recovery_backup_id' => $recoveryBackupId]);
            }
            
            // Perform the restoration
            if (!$options['dry_run']) {
                $this->performRestore($backupInfo, $options);
            } else {
                Logger::info('Dry run completed - no changes made');
            }
            
            // Verify restoration if requested
            if ($options['verify_after'] && !$options['dry_run']) {
                $this->verifyRestorationIntegrity($backupInfo);
            }
            
            $duration = microtime(true) - $startTime;
            
            Logger::info('Database recovery completed successfully', [
                'backup_id' => $backupId,
                'recovery_backup_id' => $recoveryBackupId,
                'duration' => $duration,
                'dry_run' => $options['dry_run']
            ]);
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'recovery_backup_id' => $recoveryBackupId,
                'duration' => $duration,
                'dry_run' => $options['dry_run']
            ];
            
        } catch (Exception $e) {
            Logger::error('Database recovery failed', [
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception("Recovery failed: " . $e->getMessage());
        }
    }
    
    /**
     * Verify backup before restoration
     */
    private function verifyBackupBeforeRestore($backupInfo) {
        Logger::info('Verifying backup before restoration', ['backup_id' => $backupInfo['backup_id']]);
        
        $backupFile = $backupInfo['full_path'];
        
        // Check file integrity
        if (!is_readable($backupFile)) {
            throw new Exception("Backup file is not readable");
        }
        
        // Verify checksum if available
        if (isset($backupInfo['checksum'])) {
            $currentChecksum = md5_file($backupFile);
            if ($currentChecksum !== $backupInfo['checksum']) {
                throw new Exception("Backup file checksum mismatch - file may be corrupted");
            }
        }
        
        // Read and validate backup content
        $isCompressed = pathinfo($backupFile, PATHINFO_EXTENSION) === 'gz';
        
        if ($isCompressed) {
            $handle = gzopen($backupFile, 'r');
            if (!$handle) {
                throw new Exception("Cannot open compressed backup file");
            }
            $firstLine = gzgets($handle);
            gzclose($handle);
        } else {
            $handle = fopen($backupFile, 'r');
            if (!$handle) {
                throw new Exception("Cannot open backup file");
            }
            $firstLine = fgets($handle);
            fclose($handle);
        }
        
        if (!strpos($firstLine, 'Riya Collections Database Backup')) {
            throw new Exception("Invalid backup file format");
        }
        
        Logger::info('Backup verification passed');
    }
    
    /**
     * Perform the actual database restoration
     */
    private function performRestore($backupInfo, $options) {
        Logger::info('Starting database restoration', ['backup_id' => $backupInfo['backup_id']]);
        
        $backupFile = $backupInfo['full_path'];
        $isCompressed = pathinfo($backupFile, PATHINFO_EXTENSION) === 'gz';
        
        // Disable foreign key checks and autocommit
        $this->db->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->executeQuery('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"');
        $this->db->executeQuery('SET AUTOCOMMIT = 0');
        
        try {
            $this->db->beginTransaction();
            
            // Read and execute backup SQL
            if ($isCompressed) {
                $this->executeCompressedBackup($backupFile, $options);
            } else {
                $this->executeBackupFile($backupFile, $options);
            }
            
            $this->db->commit();
            
            Logger::info('Database restoration completed');
            
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error('Database restoration failed, rolled back', ['error' => $e->getMessage()]);
            throw $e;
            
        } finally {
            // Re-enable foreign key checks
            $this->db->executeQuery('SET FOREIGN_KEY_CHECKS = 1');
            $this->db->executeQuery('SET AUTOCOMMIT = 1');
        }
    }
    
    /**
     * Execute backup from compressed file
     */
    private function executeCompressedBackup($backupFile, $options) {
        $handle = gzopen($backupFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open compressed backup file");
        }
        
        try {
            $this->processBackupContent($handle, $options, true);
        } finally {
            gzclose($handle);
        }
    }
    
    /**
     * Execute backup from regular file
     */
    private function executeBackupFile($backupFile, $options) {
        $handle = fopen($backupFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open backup file");
        }
        
        try {
            $this->processBackupContent($handle, $options, false);
        } finally {
            fclose($handle);
        }
    }
    
    /**
     * Process backup content line by line
     */
    private function processBackupContent($handle, $options, $isCompressed) {
        $sqlBuffer = '';
        $lineNumber = 0;
        $tablesRestored = [];
        $targetTables = $options['tables'];
        
        while (($line = $isCompressed ? gzgets($handle) : fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }
            
            // Skip SET statements and transaction control
            if (preg_match('/^(SET|START|COMMIT)/i', $line)) {
                continue;
            }
            
            // Check if we should process this table
            if ($targetTables !== null && $this->shouldSkipTable($line, $targetTables)) {
                continue;
            }
            
            $sqlBuffer .= $line . ' ';
            
            // Execute when we hit a semicolon
            if (substr($line, -1) === ';') {
                $sql = trim($sqlBuffer);
                $sqlBuffer = '';
                
                if (!empty($sql)) {
                    try {
                        $this->db->executeQuery($sql);
                        
                        // Track restored tables
                        if (preg_match('/CREATE TABLE `?([^`\s]+)`?/i', $sql, $matches)) {
                            $tablesRestored[] = $matches[1];
                            Logger::info("Restored table structure: {$matches[1]}");
                        } elseif (preg_match('/INSERT INTO `?([^`\s]+)`?/i', $sql, $matches)) {
                            if (!in_array($matches[1], $tablesRestored)) {
                                Logger::info("Restored table data: {$matches[1]}");
                                $tablesRestored[] = $matches[1];
                            }
                        }
                        
                    } catch (Exception $e) {
                        Logger::error("SQL execution failed at line {$lineNumber}", [
                            'sql' => substr($sql, 0, 200) . '...',
                            'error' => $e->getMessage()
                        ]);
                        throw $e;
                    }
                }
            }
        }
        
        Logger::info('Backup processing completed', [
            'lines_processed' => $lineNumber,
            'tables_restored' => count(array_unique($tablesRestored))
        ]);
    }
    
    /**
     * Check if table should be skipped based on target tables
     */
    private function shouldSkipTable($line, $targetTables) {
        if (preg_match('/(?:CREATE TABLE|INSERT INTO) `?([^`\s]+)`?/i', $line, $matches)) {
            $tableName = $matches[1];
            return !in_array($tableName, $targetTables);
        }
        return false;
    }
    
    /**
     * Verify restoration integrity
     */
    private function verifyRestorationIntegrity($backupInfo) {
        Logger::info('Verifying restoration integrity');
        
        try {
            // Check that all expected tables exist
            $currentTables = $this->getCurrentTables();
            $expectedTables = $backupInfo['tables_count'] ?? 0;
            
            if ($expectedTables > 0 && count($currentTables) < $expectedTables) {
                throw new Exception("Table count mismatch after restoration");
            }
            
            // Verify database connectivity
            $this->db->executeQuery('SELECT 1');
            
            // Check for basic data integrity
            foreach ($currentTables as $table) {
                try {
                    $this->db->executeQuery("SELECT COUNT(*) FROM `{$table}`");
                } catch (Exception $e) {
                    throw new Exception("Table {$table} appears corrupted: " . $e->getMessage());
                }
            }
            
            Logger::info('Restoration integrity verification passed', [
                'tables_verified' => count($currentTables)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Restoration integrity verification failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get current database tables
     */
    private function getCurrentTables() {
        $result = $this->db->fetchAll('SHOW TABLES');
        return array_column($result, array_keys($result[0])[0]);
    }
    
    /**
     * Test restoration without making changes
     */
    public function testRestore($backupId) {
        return $this->restoreFromBackup($backupId, ['dry_run' => true]);
    }
    
    /**
     * Restore specific tables only
     */
    public function restoreSpecificTables($backupId, $tables) {
        if (empty($tables) || !is_array($tables)) {
            throw new Exception("Invalid tables specification");
        }
        
        return $this->restoreFromBackup($backupId, [
            'tables' => $tables,
            'description' => 'Selective table restoration: ' . implode(', ', $tables)
        ]);
    }
    
    /**
     * Get recovery options for a backup
     */
    public function getRecoveryOptions($backupId) {
        $backupInfo = $this->backupService->getBackupInfo($backupId);
        if (!$backupInfo) {
            throw new Exception("Backup not found: {$backupId}");
        }
        
        // Analyze backup content to determine available tables
        $availableTables = $this->analyzeBackupTables($backupInfo);
        
        return [
            'backup_info' => $backupInfo,
            'available_tables' => $availableTables,
            'recovery_options' => [
                'full_restore' => 'Restore entire database',
                'selective_restore' => 'Restore specific tables only',
                'test_restore' => 'Test restoration without making changes'
            ]
        ];
    }
    
    /**
     * Analyze backup to determine available tables
     */
    private function analyzeBackupTables($backupInfo) {
        $backupFile = $backupInfo['full_path'];
        $isCompressed = pathinfo($backupFile, PATHINFO_EXTENSION) === 'gz';
        $tables = [];
        
        if ($isCompressed) {
            $handle = gzopen($backupFile, 'r');
        } else {
            $handle = fopen($backupFile, 'r');
        }
        
        if (!$handle) {
            return [];
        }
        
        try {
            $lineCount = 0;
            while (($line = $isCompressed ? gzgets($handle) : fgets($handle)) !== false && $lineCount < 1000) {
                $lineCount++;
                
                if (preg_match('/CREATE TABLE `?([^`\s]+)`?/i', $line, $matches)) {
                    $tables[] = $matches[1];
                }
            }
        } finally {
            if ($isCompressed) {
                gzclose($handle);
            } else {
                fclose($handle);
            }
        }
        
        return array_unique($tables);
    }
    
    /**
     * Create recovery point before major operations
     */
    public function createRecoveryPoint($description = 'Recovery point') {
        return $this->backupService->createBackup([
            'description' => $description,
            'verify' => true
        ]);
    }
    
    /**
     * List available recovery points
     */
    public function listRecoveryPoints() {
        return $this->backupService->listBackups();
    }
}