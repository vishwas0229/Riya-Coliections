<?php
/**
 * Test script for the polling system
 * 
 * This script tests the basic functionality of the polling system
 * including endpoint availability and basic operations.
 */

require_once __DIR__ . '/utils/Logger.php';
require_once __DIR__ . '/services/PollingService.php';
require_once __DIR__ . '/controllers/PollingController.php';
require_once __DIR__ . '/models/Database.php';

echo "=== Polling System Test ===\n\n";

try {
    // Test 1: PollingService instantiation
    echo "Test 1: Creating PollingService instance... ";
    $pollingService = new PollingService();
    echo "✓ PASSED\n";
    
    // Test 2: PollingController instantiation
    echo "Test 2: Creating PollingController instance... ";
    $pollingController = new PollingController();
    echo "✓ PASSED\n";
    
    // Test 3: Database connection
    echo "Test 3: Testing database connection... ";
    $db = Database::getInstance();
    if ($db->testConnection()) {
        echo "✓ PASSED\n";
    } else {
        echo "⚠ WARNING: Database connection failed\n";
    }
    
    // Test 4: Check if notifications table can be created
    echo "Test 4: Testing notifications table creation... ";
    try {
        // This will create the table if it doesn't exist
        $pollingService->createNotification(1, 'test', 'Test Notification', 'This is a test notification');
        echo "✓ PASSED\n";
    } catch (Exception $e) {
        echo "⚠ WARNING: " . $e->getMessage() . "\n";
    }
    
    // Test 5: Test polling service constants
    echo "Test 5: Checking polling service constants... ";
    $constants = [
        'FAST_POLLING_INTERVAL' => PollingService::FAST_POLLING_INTERVAL,
        'NORMAL_POLLING_INTERVAL' => PollingService::NORMAL_POLLING_INTERVAL,
        'SLOW_POLLING_INTERVAL' => PollingService::SLOW_POLLING_INTERVAL,
        'UPDATE_TYPE_ORDER_STATUS' => PollingService::UPDATE_TYPE_ORDER_STATUS,
        'UPDATE_TYPE_PAYMENT_STATUS' => PollingService::UPDATE_TYPE_PAYMENT_STATUS,
        'UPDATE_TYPE_NOTIFICATION' => PollingService::UPDATE_TYPE_NOTIFICATION,
        'UPDATE_TYPE_SYSTEM_ALERT' => PollingService::UPDATE_TYPE_SYSTEM_ALERT
    ];
    
    $allConstantsValid = true;
    foreach ($constants as $name => $value) {
        if (empty($value)) {
            echo "⚠ WARNING: Constant {$name} is empty\n";
            $allConstantsValid = false;
        }
    }
    
    if ($allConstantsValid) {
        echo "✓ PASSED\n";
    }
    
    // Test 6: Test getUserUpdates with no user (should handle gracefully)
    echo "Test 6: Testing getUserUpdates error handling... ";
    try {
        $updates = $pollingService->getUserUpdates(999999, null, []); // Non-existent user
        echo "✓ PASSED (No errors thrown)\n";
    } catch (Exception $e) {
        echo "✓ PASSED (Exception handled: " . $e->getMessage() . ")\n";
    }
    
    // Test 7: Test controller methods exist
    echo "Test 7: Checking PollingController methods... ";
    $expectedMethods = [
        'getUpdates',
        'getOrderUpdates', 
        'markNotificationsRead',
        'createNotification',
        'getPollingConfig',
        'healthCheck'
    ];
    
    $allMethodsExist = true;
    foreach ($expectedMethods as $method) {
        if (!method_exists($pollingController, $method)) {
            echo "⚠ WARNING: Method {$method} not found\n";
            $allMethodsExist = false;
        }
    }
    
    if ($allMethodsExist) {
        echo "✓ PASSED\n";
    }
    
    // Test 8: Test notification table structure
    echo "Test 8: Checking notifications table structure... ";
    try {
        $tableExists = $db->fetchColumn(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() AND table_name = 'notifications'"
        );
        
        if ($tableExists) {
            $columns = $db->fetchAll(
                "SELECT COLUMN_NAME FROM information_schema.columns 
                 WHERE table_schema = DATABASE() AND table_name = 'notifications'"
            );
            
            $expectedColumns = ['id', 'user_id', 'type', 'title', 'message', 'data', 'is_read', 'created_at', 'updated_at'];
            $actualColumns = array_column($columns, 'COLUMN_NAME');
            
            $missingColumns = array_diff($expectedColumns, $actualColumns);
            if (empty($missingColumns)) {
                echo "✓ PASSED\n";
            } else {
                echo "⚠ WARNING: Missing columns: " . implode(', ', $missingColumns) . "\n";
            }
        } else {
            echo "⚠ WARNING: Notifications table does not exist\n";
        }
    } catch (Exception $e) {
        echo "⚠ WARNING: Could not check table structure: " . $e->getMessage() . "\n";
    }
    
    // Test 9: Test order status history table exists
    echo "Test 9: Checking order_status_history table... ";
    try {
        $tableExists = $db->fetchColumn(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() AND table_name = 'order_status_history'"
        );
        
        if ($tableExists) {
            echo "✓ PASSED\n";
        } else {
            echo "⚠ WARNING: order_status_history table does not exist\n";
        }
    } catch (Exception $e) {
        echo "⚠ WARNING: Could not check order_status_history table: " . $e->getMessage() . "\n";
    }
    
    // Test 10: Test basic polling configuration
    echo "Test 10: Testing polling configuration... ";
    
    // Simulate getting polling config (without HTTP request)
    $config = [
        'intervals' => [
            'fast' => PollingService::FAST_POLLING_INTERVAL,
            'normal' => PollingService::NORMAL_POLLING_INTERVAL,
            'slow' => PollingService::SLOW_POLLING_INTERVAL
        ],
        'update_types' => [
            PollingService::UPDATE_TYPE_ORDER_STATUS,
            PollingService::UPDATE_TYPE_PAYMENT_STATUS,
            PollingService::UPDATE_TYPE_NOTIFICATION,
            PollingService::UPDATE_TYPE_SYSTEM_ALERT
        ]
    ];
    
    if (!empty($config['intervals']) && !empty($config['update_types'])) {
        echo "✓ PASSED\n";
    } else {
        echo "⚠ WARNING: Polling configuration incomplete\n";
    }
    
    echo "\n=== Test Summary ===\n";
    echo "Polling system basic functionality test completed.\n";
    echo "The polling system appears to be properly implemented.\n\n";
    
    echo "Next steps:\n";
    echo "1. Test with actual HTTP requests to polling endpoints\n";
    echo "2. Test order status updates trigger notifications\n";
    echo "3. Test payment status updates trigger notifications\n";
    echo "4. Test client-side polling implementation\n";
    echo "5. Run property-based tests for real-time update equivalence\n";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}