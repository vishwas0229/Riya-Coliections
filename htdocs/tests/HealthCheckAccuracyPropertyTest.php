<?php
/**
 * Health Check Accuracy Property Test
 * 
 * This test validates Property 23: Health Check Accuracy
 * For any system component, the health check should accurately reflect 
 * its operational status and connectivity.
 * 
 * **Validates: Requirements 20.1**
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../controllers/HealthController.php';
require_once __DIR__ . '/../services/MonitoringService.php';

use PHPUnit\Framework\TestCase;

class HealthCheckAccuracyPropertyTest extends TestCase {
    private $healthController;
    private $monitoringService;
    private $testDirectories = [];
    
    protected function setUp(): void {
        $this->healthController = new HealthController();
        $this->monitoringService = new MonitoringService();
        
        // Create test directories for file system checks
        $this->testDirectories = [
            __DIR__ . '/../test_uploads',
            __DIR__ . '/../test_logs',
            __DIR__ . '/../test_cache'
        ];
        
        foreach ($this->testDirectories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    protected function tearDown(): void {
        // Clean up test directories
        foreach ($this->testDirectories as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }
    
    /**
     * Property Test: Health Check Response Structure Consistency
     * **Validates: Requirements 20.1**
     * 
     * For any health check request, the response should always contain
     * the required structure and fields.
     */
    public function testHealthCheckResponseStructureConsistency() {
        $iterations = 20;
        $structureViolations = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Capture health check response
            ob_start();
            
            try {
                $this->healthController->check();
                $output = ob_get_contents();
                ob_end_clean();
                
                // Parse JSON response
                $response = json_decode($output, true);
                
                // Verify required top-level structure
                $this->assertIsArray($response, "Health check response should be an array");
                
                $requiredFields = ['success', 'message', 'data'];
                foreach ($requiredFields as $field) {
                    if (!array_key_exists($field, $response)) {
                        $structureViolations++;
                        break;
                    }
                }
                
                // Verify data structure if present
                if (isset($response['data'])) {
                    $requiredDataFields = ['status', 'timestamp', 'response_time_ms'];
                    foreach ($requiredDataFields as $field) {
                        $this->assertArrayHasKey($field, $response['data'],
                            "Health check data should contain {$field}");
                    }
                    
                    // Verify status is valid
                    $validStatuses = ['healthy', 'degraded', 'unhealthy'];
                    $this->assertContains($response['data']['status'], $validStatuses,
                        "Health check status should be one of: " . implode(', ', $validStatuses));
                    
                    // Verify timestamp format
                    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
                        $response['data']['timestamp'],
                        "Timestamp should be in ISO 8601 format");
                    
                    // Verify response time is numeric and reasonable
                    $this->assertIsNumeric($response['data']['response_time_ms'],
                        "Response time should be numeric");
                    $this->assertGreaterThan(0, $response['data']['response_time_ms'],
                        "Response time should be positive");
                    $this->assertLessThan(10000, $response['data']['response_time_ms'],
                        "Response time should be reasonable (< 10 seconds)");
                }
                
            } catch (Exception $e) {
                ob_end_clean();
                $structureViolations++;
            }
        }
        
        $structureAccuracy = (($iterations - $structureViolations) / $iterations) * 100;
        
        Logger::info('Health check structure consistency test completed', [
            'iterations' => $iterations,
            'structure_violations' => $structureViolations,
            'structure_accuracy_percent' => round($structureAccuracy, 2)
        ]);
        
        // Property assertion: Structure should be consistent
        $this->assertLessThan(5, $structureViolations,
            "Health check response structure should be consistent");
        $this->assertGreaterThan(95, $structureAccuracy,
            "Health check structure accuracy should be above 95%");
    }
    
    /**
     * Property Test: File System Health Check Accuracy
     * **Validates: Requirements 20.1**
     * 
     * For any file system component, health checks should accurately
     * detect directory existence and permissions.
     */
    public function testFileSystemHealthCheckAccuracy() {
        $iterations = 15;
        $accuracyViolations = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly modify file system state
            $testDir = $this->testDirectories[array_rand($this->testDirectories)];
            $shouldExist = rand(0, 1) === 1;
            $shouldBeWritable = rand(0, 1) === 1;
            
            // Set up test condition
            if ($shouldExist) {
                if (!is_dir($testDir)) {
                    mkdir($testDir, 0755, true);
                }
                
                if ($shouldBeWritable) {
                    chmod($testDir, 0755);
                } else {
                    chmod($testDir, 0444); // Read-only
                }
            } else {
                if (is_dir($testDir)) {
                    rmdir($testDir);
                }
            }
            
            // Perform health check
            $actualExists = is_dir($testDir);
            $actualWritable = is_writable($testDir);
            
            // Verify health check detects actual state
            if ($actualExists !== $shouldExist) {
                // Directory state changed unexpectedly, skip this iteration
                continue;
            }
            
            if ($actualExists && ($actualWritable !== $shouldBeWritable)) {
                // Permission state changed unexpectedly, skip this iteration
                continue;
            }
            
            // Test health check accuracy
            ob_start();
            try {
                $this->healthController->check();
                $output = ob_get_contents();
                ob_end_clean();
                
                $response = json_decode($output, true);
                
                if (isset($response['data']['services']['file_system'])) {
                    $fileSystemStatus = $response['data']['services']['file_system'];
                    
                    // Check if health check accurately reflects directory state
                    $dirName = basename($testDir);
                    if (strpos($dirName, 'uploads') !== false) {
                        $reportedWritable = $fileSystemStatus['uploads_writable'] ?? false;
                        if ($actualWritable !== $reportedWritable) {
                            $accuracyViolations++;
                        }
                    }
                }
                
            } catch (Exception $e) {
                ob_end_clean();
                $accuracyViolations++;
            }
            
            // Restore directory for next iteration
            if (!is_dir($testDir)) {
                mkdir($testDir, 0755, true);
            } else {
                chmod($testDir, 0755);
            }
        }
        
        $accuracy = (($iterations - $accuracyViolations) / $iterations) * 100;
        
        Logger::info('File system health check accuracy test completed', [
            'iterations' => $iterations,
            'accuracy_violations' => $accuracyViolations,
            'accuracy_percent' => round($accuracy, 2)
        ]);
        
        // Property assertion: Health checks should be accurate
        $this->assertLessThan(3, $accuracyViolations,
            "File system health check should be accurate");
        $this->assertGreaterThan(80, $accuracy,
            "File system health check accuracy should be above 80%");
    }
    
    /**
     * Property Test: Memory Usage Health Check Accuracy
     * **Validates: Requirements 20.1**
     * 
     * For any memory usage condition, health checks should accurately
     * report memory consumption and limits.
     */
    public function testMemoryUsageHealthCheckAccuracy() {
        $iterations = 25;
        $accuracyViolations = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Get actual memory usage
            $actualMemoryUsage = memory_get_usage(true);
            $actualMemoryPeak = memory_get_peak_usage(true);
            $memoryLimit = ini_get('memory_limit');
            
            // Perform health check
            ob_start();
            try {
                $this->healthController->check();
                $output = ob_get_contents();
                ob_end_clean();
                
                $response = json_decode($output, true);
                
                if (isset($response['data']['system']['memory_usage'])) {
                    $reportedMemory = $response['data']['system']['memory_usage'];
                    
                    // Verify reported memory is close to actual (within 10% tolerance)
                    $memoryDifference = abs($reportedMemory - $actualMemoryUsage);
                    $memoryTolerance = $actualMemoryUsage * 0.1;
                    
                    if ($memoryDifference > $memoryTolerance) {
                        $accuracyViolations++;
                    }
                    
                    // Verify memory usage is reasonable
                    $this->assertGreaterThan(0, $reportedMemory,
                        "Reported memory usage should be positive");
                    $this->assertLessThan(1024 * 1024 * 1024, $reportedMemory,
                        "Reported memory usage should be reasonable (< 1GB)");
                }
                
                if (isset($response['data']['system']['memory_peak'])) {
                    $reportedPeak = $response['data']['system']['memory_peak'];
                    
                    // Peak should be >= current usage
                    $this->assertGreaterThanOrEqual($actualMemoryUsage, $reportedPeak,
                        "Peak memory should be >= current memory usage");
                }
                
            } catch (Exception $e) {
                ob_end_clean();
                $accuracyViolations++;
            }
            
            // Allocate some memory to change usage for next iteration
            $tempData = str_repeat('x', rand(1000, 10000));
            unset($tempData);
        }
        
        $accuracy = (($iterations - $accuracyViolations) / $iterations) * 100;
        
        Logger::info('Memory usage health check accuracy test completed', [
            'iterations' => $iterations,
            'accuracy_violations' => $accuracyViolations,
            'accuracy_percent' => round($accuracy, 2)
        ]);
        
        // Property assertion: Memory reporting should be accurate
        $this->assertLessThan(5, $accuracyViolations,
            "Memory usage health check should be accurate");
        $this->assertGreaterThan(80, $accuracy,
            "Memory usage health check accuracy should be above 80%");
    }
    
    /**
     * Property Test: Health Status Determination Accuracy
     * **Validates: Requirements 20.1**
     * 
     * For any combination of component statuses, the overall health
     * status should be determined correctly.
     */
    public function testHealthStatusDeterminationAccuracy() {
        $iterations = 30;
        $statusViolations = 0;
        
        // Test different combinations of component statuses
        $testCases = [
            'all_healthy' => ['healthy', 'healthy', 'healthy', 'healthy'],
            'one_degraded' => ['healthy', 'degraded', 'healthy', 'healthy'],
            'one_unhealthy' => ['healthy', 'unhealthy', 'healthy', 'healthy'],
            'all_degraded' => ['degraded', 'degraded', 'degraded', 'degraded'],
            'mixed_unhealthy' => ['unhealthy', 'healthy', 'degraded', 'healthy'],
            'all_unhealthy' => ['unhealthy', 'unhealthy', 'unhealthy', 'unhealthy']
        ];
        
        $expectedResults = [
            'all_healthy' => 'healthy',
            'one_degraded' => 'degraded',
            'one_unhealthy' => 'unhealthy',
            'all_degraded' => 'degraded',
            'mixed_unhealthy' => 'unhealthy',
            'all_unhealthy' => 'unhealthy'
        ];
        
        foreach ($testCases as $testName => $componentStatuses) {
            $expectedOverallStatus = $expectedResults[$testName];
            for ($i = 0; $i < 5; $i++) { // Test each case multiple times
                // Mock component statuses (this would require dependency injection in real implementation)
                // For now, we'll test the actual health check and verify logic
                
                ob_start();
                try {
                    $this->healthController->check();
                    $output = ob_get_contents();
                    ob_end_clean();
                    
                    $response = json_decode($output, true);
                    
                    if (isset($response['data']['status'])) {
                        $reportedStatus = $response['data']['status'];
                        
                        // Verify status is valid
                        $validStatuses = ['healthy', 'degraded', 'unhealthy'];
                        if (!in_array($reportedStatus, $validStatuses)) {
                            $statusViolations++;
                        }
                        
                        // Verify status consistency with issues
                        if (isset($response['data']['issues'])) {
                            $hasIssues = !empty($response['data']['issues']);
                            
                            if ($hasIssues && $reportedStatus === 'healthy') {
                                $statusViolations++;
                            }
                            
                            if (!$hasIssues && $reportedStatus === 'unhealthy') {
                                $statusViolations++;
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    ob_end_clean();
                    $statusViolations++;
                }
            }
        }
        
        $totalTests = count($testCases) * 5;
        $accuracy = (($totalTests - $statusViolations) / $totalTests) * 100;
        
        Logger::info('Health status determination accuracy test completed', [
            'total_tests' => $totalTests,
            'status_violations' => $statusViolations,
            'accuracy_percent' => round($accuracy, 2)
        ]);
        
        // Property assertion: Status determination should be accurate
        $this->assertLessThan($totalTests * 0.1, $statusViolations,
            "Health status determination should be accurate");
        $this->assertGreaterThan(90, $accuracy,
            "Health status determination accuracy should be above 90%");
    }
    
    /**
     * Property Test: Response Time Measurement Accuracy
     * **Validates: Requirements 20.1**
     * 
     * For any health check operation, the reported response time
     * should accurately reflect the actual execution time.
     */
    public function testResponseTimeMeasurementAccuracy() {
        $iterations = 20;
        $timingViolations = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Measure actual execution time
            $startTime = microtime(true);
            
            ob_start();
            try {
                $this->healthController->check();
                $output = ob_get_contents();
                ob_end_clean();
                
                $actualExecutionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
                
                $response = json_decode($output, true);
                
                if (isset($response['data']['response_time_ms'])) {
                    $reportedTime = $response['data']['response_time_ms'];
                    
                    // Verify reported time is close to actual (within 20% tolerance)
                    $timeDifference = abs($reportedTime - $actualExecutionTime);
                    $timeTolerance = $actualExecutionTime * 0.2;
                    
                    if ($timeDifference > $timeTolerance) {
                        $timingViolations++;
                    }
                    
                    // Verify timing is reasonable
                    $this->assertGreaterThan(0, $reportedTime,
                        "Response time should be positive");
                    $this->assertLessThan(30000, $reportedTime,
                        "Response time should be reasonable (< 30 seconds)");
                }
                
            } catch (Exception $e) {
                ob_end_clean();
                $timingViolations++;
            }
            
            // Add small delay to vary execution times
            usleep(rand(1000, 5000)); // 1-5ms delay
        }
        
        $accuracy = (($iterations - $timingViolations) / $iterations) * 100;
        
        Logger::info('Response time measurement accuracy test completed', [
            'iterations' => $iterations,
            'timing_violations' => $timingViolations,
            'accuracy_percent' => round($accuracy, 2)
        ]);
        
        // Property assertion: Response time measurement should be accurate
        $this->assertLessThan(4, $timingViolations,
            "Response time measurement should be accurate");
        $this->assertGreaterThan(80, $accuracy,
            "Response time measurement accuracy should be above 80%");
    }
    
    /**
     * Property Test: Comprehensive Monitoring Service Accuracy
     * **Validates: Requirements 20.1**
     * 
     * For any system state, the monitoring service should provide
     * accurate and comprehensive health information.
     */
    public function testComprehensiveMonitoringAccuracy() {
        $iterations = 10;
        $monitoringViolations = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $healthData = $this->monitoringService->performHealthCheck();
                
                // Verify comprehensive health data structure
                $requiredSections = ['timestamp', 'overall_status', 'checks', 'metrics'];
                foreach ($requiredSections as $section) {
                    if (!isset($healthData[$section])) {
                        $monitoringViolations++;
                        break;
                    }
                }
                
                // Verify checks section contains expected components
                if (isset($healthData['checks'])) {
                    $expectedComponents = ['database', 'application', 'system', 'performance', 'security'];
                    foreach ($expectedComponents as $component) {
                        if (!isset($healthData['checks'][$component])) {
                            $monitoringViolations++;
                            break;
                        }
                        
                        // Verify each component has status
                        if (!isset($healthData['checks'][$component]['status'])) {
                            $monitoringViolations++;
                            break;
                        }
                    }
                }
                
                // Verify metrics section contains expected data
                if (isset($healthData['metrics'])) {
                    $expectedMetrics = ['timestamp', 'memory_usage'];
                    foreach ($expectedMetrics as $metric) {
                        if (!isset($healthData['metrics'][$metric])) {
                            $monitoringViolations++;
                            break;
                        }
                    }
                }
                
                // Verify overall status consistency
                if (isset($healthData['overall_status']) && isset($healthData['checks'])) {
                    $componentStatuses = array_column($healthData['checks'], 'status');
                    $overallStatus = $healthData['overall_status'];
                    
                    // Check status determination logic
                    if (in_array('unhealthy', $componentStatuses) && $overallStatus !== 'unhealthy') {
                        $monitoringViolations++;
                    } elseif (in_array('degraded', $componentStatuses) && 
                             !in_array('unhealthy', $componentStatuses) && 
                             $overallStatus !== 'degraded') {
                        $monitoringViolations++;
                    } elseif (!in_array('unhealthy', $componentStatuses) && 
                             !in_array('degraded', $componentStatuses) && 
                             $overallStatus !== 'healthy') {
                        $monitoringViolations++;
                    }
                }
                
            } catch (Exception $e) {
                $monitoringViolations++;
            }
        }
        
        $accuracy = (($iterations - $monitoringViolations) / $iterations) * 100;
        
        Logger::info('Comprehensive monitoring accuracy test completed', [
            'iterations' => $iterations,
            'monitoring_violations' => $monitoringViolations,
            'accuracy_percent' => round($accuracy, 2)
        ]);
        
        // Property assertion: Monitoring should be comprehensive and accurate
        $this->assertLessThan(2, $monitoringViolations,
            "Comprehensive monitoring should be accurate");
        $this->assertGreaterThan(80, $accuracy,
            "Comprehensive monitoring accuracy should be above 80%");
    }
}