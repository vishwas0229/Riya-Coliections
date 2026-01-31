<?php

require_once __DIR__ . '/../services/BackupService.php';
require_once __DIR__ . '/../services/RecoveryService.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

/**
 * BackupController - API endpoints for backup and recovery operations
 * 
 * All endpoints require admin authentication for security
 */
class BackupController {
    private $backupService;
    private $recoveryService;
    
    public function __construct() {
        $this->backupService = new BackupService();
        $this->recoveryService = new RecoveryService();
    }
    
    /**
     * Handle backup and recovery requests
     */
    public function handleRequest($method = null, $path = null, $params = []) {
        try {
            // All backup operations require admin authentication
            AdminMiddleware::authenticate();
            
            // Get method and path from server if not provided
            $method = $method ?: $_SERVER['REQUEST_METHOD'];
            $path = $path ?: $_SERVER['REQUEST_URI'];
            
            // Parse path to extract the action
            $pathParts = explode('/', trim(parse_url($path, PHP_URL_PATH), '/'));
            
            // Remove 'api', 'admin', 'backup' from path parts
            $pathParts = array_slice($pathParts, 3);
            
            // Get request body for POST requests
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $input = file_get_contents('php://input');
                $requestData = json_decode($input, true) ?: [];
                $params = array_merge($params, $requestData);
            }
            
            switch ($method) {
                case 'GET':
                    return $this->handleGet($pathParts, $params);
                case 'POST':
                    return $this->handlePost($pathParts, $params);
                case 'DELETE':
                    return $this->handleDelete($pathParts, $params);
                default:
                    Response::error('Method not allowed', 405);
            }
            
        } catch (Exception $e) {
            Logger::error('Backup controller error', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            
            Response::error($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGet($pathParts, $params) {
        if (empty($pathParts)) {
            Response::error('Invalid endpoint', 404);
            return;
        }
        
        switch ($pathParts[0]) {
            case 'list':
                return $this->listBackups();
                
            case 'info':
                if (!isset($pathParts[1])) {
                    Response::error('Backup ID required', 400);
                }
                return $this->getBackupInfo($pathParts[1]);
                
            case 'recovery-options':
                if (!isset($pathParts[1])) {
                    Response::error('Backup ID required', 400);
                }
                return $this->getRecoveryOptions($pathParts[1]);
                
            case 'schedule':
                return $this->getBackupSchedule();
                
            case 'status':
                return $this->getSystemStatus();
                
            default:
                Response::error('Invalid endpoint', 404);
        }
    }
    
    /**
     * Handle POST requests
     */
    private function handlePost($pathParts, $params) {
        if (empty($pathParts)) {
            Response::error('Invalid endpoint', 404);
            return;
        }
        
        switch ($pathParts[0]) {
            case 'create':
                return $this->createBackup($params);
                
            case 'restore':
                if (!isset($pathParts[1])) {
                    Response::error('Backup ID required', 400);
                }
                return $this->restoreBackup($pathParts[1], $params);
                
            case 'test-restore':
                if (!isset($pathParts[1])) {
                    Response::error('Backup ID required', 400);
                }
                return $this->testRestore($pathParts[1]);
                
            case 'schedule':
                return $this->scheduleBackup($params);
                
            case 'run-scheduled':
                return $this->runScheduledBackup();
                
            default:
                Response::error('Invalid endpoint', 404);
        }
    }
    
    /**
     * Handle DELETE requests
     */
    private function handleDelete($pathParts, $params) {
        if (empty($pathParts)) {
            Response::error('Invalid endpoint', 404);
            return;
        }
        
        if ($pathParts[0] === 'delete' && isset($pathParts[1])) {
            return $this->deleteBackup($pathParts[1]);
        }
        
        Response::error('Invalid endpoint', 404);
    }
    
    /**
     * Create a new backup
     */
    private function createBackup($params) {
        try {
            $options = [
                'include_data' => $params['include_data'] ?? true,
                'include_structure' => $params['include_structure'] ?? true,
                'compress' => $params['compress'] ?? true,
                'verify' => $params['verify'] ?? true,
                'description' => $params['description'] ?? 'Manual backup'
            ];
            
            $result = $this->backupService->createBackup($options);
            
            Response::success('Backup created successfully', $result);
            
        } catch (Exception $e) {
            Response::error('Failed to create backup: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * List all backups
     */
    private function listBackups() {
        try {
            $backups = $this->backupService->listBackups();
            
            Response::success('Backups retrieved successfully', [
                'backups' => $backups,
                'count' => count($backups)
            ]);
            
        } catch (Exception $e) {
            Response::error('Failed to list backups: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get backup information
     */
    private function getBackupInfo($backupId) {
        try {
            $info = $this->backupService->getBackupInfo($backupId);
            
            if (!$info) {
                Response::error('Backup not found', 404);
            }
            
            Response::success('Backup information retrieved', $info);
            
        } catch (Exception $e) {
            Response::error('Failed to get backup info: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Restore from backup
     */
    private function restoreBackup($backupId, $params) {
        try {
            $options = [
                'verify_before' => $params['verify_before'] ?? true,
                'verify_after' => $params['verify_after'] ?? true,
                'create_backup' => $params['create_backup'] ?? true,
                'tables' => $params['tables'] ?? null,
                'dry_run' => $params['dry_run'] ?? false
            ];
            
            $result = $this->recoveryService->restoreFromBackup($backupId, $options);
            
            Response::success('Restoration completed successfully', $result);
            
        } catch (Exception $e) {
            Response::error('Failed to restore backup: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Test restore without making changes
     */
    private function testRestore($backupId) {
        try {
            $result = $this->recoveryService->testRestore($backupId);
            
            Response::success('Test restore completed', $result);
            
        } catch (Exception $e) {
            Response::error('Test restore failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get recovery options for a backup
     */
    private function getRecoveryOptions($backupId) {
        try {
            $options = $this->recoveryService->getRecoveryOptions($backupId);
            
            Response::success('Recovery options retrieved', $options);
            
        } catch (Exception $e) {
            Response::error('Failed to get recovery options: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Schedule automatic backups
     */
    private function scheduleBackup($params) {
        try {
            $frequency = $params['frequency'] ?? 'daily';
            
            if (!in_array($frequency, ['hourly', 'daily', 'weekly'])) {
                Response::error('Invalid frequency. Must be: hourly, daily, or weekly', 400);
            }
            
            $schedule = $this->backupService->scheduleBackup($frequency);
            
            Response::success('Backup schedule configured', $schedule);
            
        } catch (Exception $e) {
            Response::error('Failed to schedule backup: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get backup schedule
     */
    private function getBackupSchedule() {
        try {
            $scheduleFile = __DIR__ . '/../backups/schedule.json';
            
            if (!file_exists($scheduleFile)) {
                Response::success('No backup schedule configured', null);
                return;
            }
            
            $schedule = json_decode(file_get_contents($scheduleFile), true);
            
            Response::success('Backup schedule retrieved', $schedule);
            
        } catch (Exception $e) {
            Response::error('Failed to get backup schedule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Run scheduled backup manually
     */
    private function runScheduledBackup() {
        try {
            if (!$this->backupService->shouldRunScheduledBackup()) {
                Response::success('No scheduled backup due at this time', [
                    'should_run' => false
                ]);
                return;
            }
            
            $result = $this->backupService->runScheduledBackup();
            
            Response::success('Scheduled backup completed', $result);
            
        } catch (Exception $e) {
            Response::error('Scheduled backup failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete a backup
     */
    private function deleteBackup($backupId) {
        try {
            $backupInfo = $this->backupService->getBackupInfo($backupId);
            
            if (!$backupInfo) {
                Response::error('Backup not found', 404);
            }
            
            // Delete backup file
            if (file_exists($backupInfo['full_path'])) {
                unlink($backupInfo['full_path']);
            }
            
            // Remove from metadata
            $metadataFile = __DIR__ . '/../backups/metadata.json';
            if (file_exists($metadataFile)) {
                $allMetadata = json_decode(file_get_contents($metadataFile), true) ?: [];
                unset($allMetadata[$backupId]);
                file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
            }
            
            Logger::info('Backup deleted', ['backup_id' => $backupId]);
            
            Response::success('Backup deleted successfully', [
                'backup_id' => $backupId
            ]);
            
        } catch (Exception $e) {
            Response::error('Failed to delete backup: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get backup system status
     */
    public function getSystemStatus() {
        try {
            AdminMiddleware::authenticate();
            
            $backups = $this->backupService->listBackups();
            $scheduleFile = __DIR__ . '/../backups/schedule.json';
            $schedule = null;
            
            if (file_exists($scheduleFile)) {
                $schedule = json_decode(file_get_contents($scheduleFile), true);
            }
            
            $status = [
                'backup_count' => count($backups),
                'latest_backup' => !empty($backups) ? reset($backups) : null,
                'schedule' => $schedule,
                'backup_directory' => realpath(__DIR__ . '/../backups'),
                'disk_space' => $this->getBackupDirectorySize()
            ];
            
            Response::success('Backup system status', $status);
            
        } catch (Exception $e) {
            Response::error('Failed to get system status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Calculate backup directory size
     */
    private function getBackupDirectorySize() {
        $backupDir = __DIR__ . '/../backups';
        $size = 0;
        
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                }
            }
        }
        
        return [
            'bytes' => $size,
            'human_readable' => $this->formatBytes($size)
        ];
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}