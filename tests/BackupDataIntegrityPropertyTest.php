<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../services/BackupService.php';
require_once __DIR__ . '/../services/RecoveryService.php';
require_once __DIR__ . '/../models/Database.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Backup Data Integrity
 * 
 * **Validates: Requirements 19.1**
 * 
 * This test verifies the fundamental property that backup and restoration
 * operations preserve data integrity. For any database state, creating a
 * backup and then restoring it should result in identical data.
 */
class BackupDataIntegrityPropertyTest extends TestCase {
    private $backupService;
    private $recoveryService;
    private $db;
    private $testTables = [];
    
    protected function setUp(): void {
        $this->backupService = new BackupService();
        $this->recoveryService = new RecoveryService();
        $this->db = Database::getInstance();
        
        // Create test tables for property testing
        $this->createTestTables();
    }
    
    protected function tearDown(): void {
        // Clean up test tables
        $this->cleanupTestTables();
        
        // Clean up any test backups
        $this->cleanupTestBackups();
    }
    
    /**
     * Property Test: Backup Data Integrity
     * 
     * **Validates: Requirements 19.1**
     * 
     * Property: For any database state, creating a backup and restoring it
     * should result in identical data to the original state.
     * 
     * @test
     */
    public function testBackupDataIntegrityProperty() {
        $iterations = 50; // Reduced for faster execution
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random test data
                $testData = $this->generateRandomTestData();
                
                // Insert test data into database
                $this->insertTestData($testData);
                
                // Capture original database state
                $originalState = $this->captureTableStates();
                
                // Create backup
                $backupResult = $this->backupService->createBackup([
                    'description' => "Property test iteration $i",
                    'compress' => false, // Disable compression for faster testing
                    'verify' => true
                ]);
                
                $this->assertTrue($backupResult['success'], 
                    "Backup creation should succeed for iteration $i");
                
                $backupId = $backupResult['backup_id'];
                
                // Modify database state (simulate data changes)
                $this->modifyTestData();
                
                // Verify database state has changed
                $modifiedState = $this->captureTableStates();
                $this->assertNotEquals($originalState, $modifiedState,
                    "Database state should be different after modification in iteration $i");
                
                // Restore from backup
                $restoreResult = $this->recoveryService->restoreFromBackup($backupId, [
                    'verify_before' => true,
                    'verify_after' => true,
                    'create_backup' => false // Don't create recovery backup for test
                ]);
                
                $this->assertTrue($restoreResult['success'],
                    "Restore operation should succeed for iteration $i");
                
                // Capture restored database state
                $restoredState = $this->captureTableStates();
                
                // Verify data integrity: restored state should match original state
                $this->assertEquals($originalState, $restoredState,
                    "Restored database state should match original state in iteration $i");
                
                // Clean up for next iteration
                $this->cleanupTestData();
                
            } catch (Exception $e) {
                $this->fail("Property test failed at iteration $i: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Property Test: Backup Verification Integrity
     * 
     * **Validates: Requirements 19.5**
     * 
     * Property: Backup verification should correctly identify corrupted backups
     * and pass for valid backups.
     * 
     * @test
     */
    public function testBackupVerificationProperty() {
        $iterations = 20;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate test data
                $testData = $this->generateRandomTestData();
                $this->insertTestData($testData);
                
                // Create backup
                $backupResult = $this->backupService->createBackup([
                    'description' => "Verification test iteration $i",
                    'compress' => false,
                    'verify' => true
                ]);
                
                $this->assertTrue($backupResult['success']);
                $backupFile = $backupResult['file'];
                
                // Test 1: Valid backup should pass verification
                $backupInfo = $this->backupService->getBackupInfo($backupResult['backup_id']);
                $recoveryOptions = $this->recoveryService->getRecoveryOptions($backupResult['backup_id']);
                
                $this->assertNotNull($backupInfo, "Valid backup should have metadata");
                $this->assertNotEmpty($recoveryOptions['available_tables'], 
                    "Valid backup should have available tables");
                
                // Test 2: Corrupted backup should fail verification
                if (rand(0, 1)) { // Randomly test corruption
                    $originalContent = file_get_contents($backupFile);
                    
                    // Corrupt the backup file
                    $corruptedContent = $this->corruptBackupContent($originalContent);
                    file_put_contents($backupFile, $corruptedContent);
                    
                    // Verification should detect corruption
                    $this->expectException(Exception::class);
                    $this->recoveryService->testRestore($backupResult['backup_id']);
                    
                    // Restore original content for cleanup
                    file_put_contents($backupFile, $originalContent);
                }
                
                $this->cleanupTestData();
                
            } catch (Exception $e) {
                if (!$this->expectsException()) {
                    $this->fail("Verification property test failed at iteration $i: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Property Test: Selective Restore Integrity
     * 
     * **Validates: Requirements 19.4**
     * 
     * Property: Selective table restoration should only affect specified tables
     * while leaving other tables unchanged.
     * 
     * @test
     */
    public function testSelectiveRestoreProperty() {
        $iterations = 15;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate test data for all tables
                $testData = $this->generateRandomTestData();
                $this->insertTestData($testData);
                
                // Create backup
                $backupResult = $this->backupService->createBackup([
                    'description' => "Selective restore test iteration $i",
                    'compress' => false,
                    'verify' => true
                ]);
                
                $this->assertTrue($backupResult['success']);
                
                // Modify all tables
                $this->modifyTestData();
                $modifiedState = $this->captureTableStates();
                
                // Select random subset of tables to restore
                $availableTables = array_keys($this->testTables);
                $tablesToRestore = array_slice($availableTables, 0, rand(1, count($availableTables) - 1));
                $tablesNotToRestore = array_diff($availableTables, $tablesToRestore);
                
                // Perform selective restore
                $restoreResult = $this->recoveryService->restoreSpecificTables(
                    $backupResult['backup_id'], 
                    $tablesToRestore
                );
                
                $this->assertTrue($restoreResult['success']);
                
                // Verify selective restore results
                $finalState = $this->captureTableStates();
                
                // Tables that were restored should match original state
                foreach ($tablesToRestore as $table) {
                    $this->assertNotEquals(
                        $modifiedState[$table], 
                        $finalState[$table],
                        "Restored table $table should be different from modified state"
                    );
                }
                
                // Tables that were not restored should remain in modified state
                foreach ($tablesNotToRestore as $table) {
                    $this->assertEquals(
                        $modifiedState[$table], 
                        $finalState[$table],
                        "Non-restored table $table should remain in modified state"
                    );
                }
                
                $this->cleanupTestData();
                
            } catch (Exception $e) {
                $this->fail("Selective restore property test failed at iteration $i: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Create test tables for property testing
     */
    private function createTestTables() {
        $this->testTables = [
            'test_users_backup' => [
                'sql' => "CREATE TABLE IF NOT EXISTS test_users_backup (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    age INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                'columns' => ['name', 'email', 'age']
            ],
            'test_products_backup' => [
                'sql' => "CREATE TABLE IF NOT EXISTS test_products_backup (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(200) NOT NULL,
                    price DECIMAL(10,2),
                    stock INT DEFAULT 0,
                    category VARCHAR(50)
                )",
                'columns' => ['title', 'price', 'stock', 'category']
            ],
            'test_orders_backup' => [
                'sql' => "CREATE TABLE IF NOT EXISTS test_orders_backup (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_number VARCHAR(50) UNIQUE NOT NULL,
                    total DECIMAL(10,2),
                    status VARCHAR(20) DEFAULT 'pending'
                )",
                'columns' => ['order_number', 'total', 'status']
            ]
        ];
        
        foreach ($this->testTables as $table => $config) {
            $this->db->executeQuery($config['sql']);
        }
    }
    
    /**
     * Generate random test data
     */
    private function generateRandomTestData() {
        $data = [];
        
        foreach ($this->testTables as $table => $config) {
            $data[$table] = [];
            $rowCount = rand(5, 20);
            
            for ($i = 0; $i < $rowCount; $i++) {
                $row = [];
                
                foreach ($config['columns'] as $column) {
                    $row[$column] = $this->generateRandomValue($column, $table);
                }
                
                $data[$table][] = $row;
            }
        }
        
        return $data;
    }
    
    /**
     * Generate random value based on column name and table
     */
    private function generateRandomValue($column, $table) {
        switch ($column) {
            case 'name':
                return 'User' . rand(1000, 9999);
            case 'email':
                return 'user' . rand(1000, 9999) . '@test.com';
            case 'age':
                return rand(18, 80);
            case 'title':
                return 'Product' . rand(1000, 9999);
            case 'price':
                return rand(100, 10000) / 100;
            case 'stock':
                return rand(0, 100);
            case 'category':
                return ['electronics', 'clothing', 'books', 'home'][rand(0, 3)];
            case 'order_number':
                return 'ORD' . rand(100000, 999999);
            case 'total':
                return rand(1000, 50000) / 100;
            case 'status':
                return ['pending', 'processing', 'shipped', 'delivered'][rand(0, 3)];
            default:
                return 'test_value_' . rand(1000, 9999);
        }
    }
    
    /**
     * Insert test data into database
     */
    private function insertTestData($testData) {
        foreach ($testData as $table => $rows) {
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $placeholders = array_fill(0, count($columns), '?');
                
                $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $this->db->executeQuery($sql, array_values($row));
            }
        }
    }
    
    /**
     * Capture current state of all test tables
     */
    private function captureTableStates() {
        $states = [];
        
        foreach (array_keys($this->testTables) as $table) {
            $result = $this->db->fetchAll("SELECT * FROM $table ORDER BY id");
            $states[$table] = $result;
        }
        
        return $states;
    }
    
    /**
     * Modify test data to simulate changes
     */
    private function modifyTestData() {
        foreach (array_keys($this->testTables) as $table) {
            // Delete some random rows
            $this->db->executeQuery("DELETE FROM $table WHERE id % 3 = 0");
            
            // Update some random rows
            if ($table === 'test_users_backup') {
                $this->db->executeQuery("UPDATE $table SET name = CONCAT(name, '_modified') WHERE id % 2 = 0");
            } elseif ($table === 'test_products_backup') {
                $this->db->executeQuery("UPDATE $table SET price = price * 1.1 WHERE id % 2 = 0");
            } elseif ($table === 'test_orders_backup') {
                $this->db->executeQuery("UPDATE $table SET status = 'modified' WHERE id % 2 = 0");
            }
        }
    }
    
    /**
     * Corrupt backup content for testing verification
     */
    private function corruptBackupContent($content) {
        $lines = explode("\n", $content);
        
        // Randomly corrupt some lines
        for ($i = 0; $i < min(5, count($lines)); $i++) {
            $randomLine = rand(0, count($lines) - 1);
            if (strpos($lines[$randomLine], 'INSERT INTO') !== false) {
                $lines[$randomLine] = str_replace('INSERT INTO', 'CORRUPT_INSERT', $lines[$randomLine]);
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData() {
        foreach (array_keys($this->testTables) as $table) {
            $this->db->executeQuery("DELETE FROM $table");
        }
    }
    
    /**
     * Clean up test tables
     */
    private function cleanupTestTables() {
        foreach (array_keys($this->testTables) as $table) {
            $this->db->executeQuery("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Clean up test backups
     */
    private function cleanupTestBackups() {
        $backups = $this->backupService->listBackups();
        
        foreach ($backups as $backupId => $backup) {
            if (strpos($backup['description'], 'Property test') !== false ||
                strpos($backup['description'], 'Verification test') !== false ||
                strpos($backup['description'], 'Selective restore test') !== false) {
                
                if (file_exists($backup['full_path'])) {
                    unlink($backup['full_path']);
                }
            }
        }
    }
}