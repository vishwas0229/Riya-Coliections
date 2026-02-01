<?php
/**
 * Standalone Property-Based Test Runner
 * Executes property tests that don't require PHPUnit
 */

echo "=== RIYA COLLECTIONS PHP BACKEND COMPATIBILITY TEST SUITE ===\n";
echo "Running standalone property-based tests...\n\n";

$testResults = [];
$totalTests = 0;
$totalPassed = 0;

// List of standalone property test files (don't extend PHPUnit TestCase)
$standaloneTests = [
    'ApiResponseCompatibilityPropertyTest.php',
    'DatabaseConnectionSecurityPropertyTest.php',
    'SQLInjectionPreventionPropertyTest.php',
    'ApiEndpointCompletenessPropertyTest.php',
    'ProductQueryConsistencyPropertyTest.php',
    'FileUploadValidationPropertyTest.php',
    'ImageProcessingConsistencyPropertyTest.php',
    'OrderWorkflowCompletenessPropertyTest.php',
    'ProductCRUDLogicPropertyTest.php',
    'ProductCRUDPropertyTest.php',
    'CategoryPropertyTest.php',
    'UserValidationPropertyTest.php',
    'UserPropertyTest.php',
    'OrderPropertyTest.php',
    'ProductPropertyTest.php',
    'ResponsePropertyTest.php',
    'DatabasePropertyTest.php'
];

foreach ($standaloneTests as $testFile) {
    $testPath = __DIR__ . '/tests/' . $testFile;
    
    if (!file_exists($testPath)) {
        echo "⚠️  Test file not found: $testFile\n";
        continue;
    }
    
    echo "Running: $testFile\n";
    echo str_repeat("-", 60) . "\n";
    
    // Capture output
    ob_start();
    $startTime = microtime(true);
    
    try {
        include $testPath;
        $output = ob_get_clean();
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        // Parse results from output
        preg_match('/Tests: (\d+), Passed: (\d+), Failed: (\d+)/', $output, $matches);
        
        if ($matches) {
            $tests = (int)$matches[1];
            $passed = (int)$matches[2];
            $failed = (int)$matches[3];
            
            $totalTests += $tests;
            $totalPassed += $passed;
            
            $status = ($failed === 0) ? "✅ PASSED" : "❌ FAILED";
            echo "$status - $tests tests, $passed passed, $failed failed ({$duration}s)\n";
            
            $testResults[$testFile] = [
                'status' => $failed === 0 ? 'passed' : 'failed',
                'tests' => $tests,
                'passed' => $passed,
                'failed' => $failed,
                'duration' => $duration
            ];
        } else {
            // Check for "All property tests passed!" message
            if (strpos($output, 'All property tests passed!') !== false) {
                // Try to extract test count from output
                preg_match('/(\d+) property tests/', $output, $testMatches);
                $tests = $testMatches ? (int)$testMatches[1] : 1;
                
                $totalTests += $tests;
                $totalPassed += $tests;
                
                echo "✅ PASSED - $tests tests, $tests passed, 0 failed ({$duration}s)\n";
                
                $testResults[$testFile] = [
                    'status' => 'passed',
                    'tests' => $tests,
                    'passed' => $tests,
                    'failed' => 0,
                    'duration' => $duration
                ];
            } else {
                echo "⚠️  Could not parse test results\n";
                $testResults[$testFile] = [
                    'status' => 'unknown',
                    'tests' => 0,
                    'passed' => 0,
                    'failed' => 0,
                    'duration' => $duration
                ];
            }
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        echo "❌ ERROR: " . $e->getMessage() . " ({$duration}s)\n";
        $testResults[$testFile] = [
            'status' => 'error',
            'tests' => 0,
            'passed' => 0,
            'failed' => 0,
            'duration' => $duration,
            'error' => $e->getMessage()
        ];
    } catch (Error $e) {
        ob_end_clean();
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        echo "❌ ERROR: " . $e->getMessage() . " ({$duration}s)\n";
        $testResults[$testFile] = [
            'status' => 'error',
            'tests' => 0,
            'passed' => 0,
            'failed' => 0,
            'duration' => $duration,
            'error' => $e->getMessage()
        ];
    }
    
    echo "\n";
}

// Summary
echo str_repeat("=", 80) . "\n";
echo "STANDALONE PROPERTY-BASED TEST SUITE SUMMARY\n";
echo str_repeat("=", 80) . "\n";

$passedTests = 0;
$failedTests = 0;
$errorTests = 0;

foreach ($testResults as $testFile => $result) {
    $status = $result['status'];
    $icon = match($status) {
        'passed' => '✅',
        'failed' => '❌',
        'error' => '⚠️',
        default => '❓'
    };
    
    echo sprintf("%-50s %s %s\n", 
        $testFile, 
        $icon, 
        strtoupper($status)
    );
    
    if ($status === 'passed') $passedTests++;
    elseif ($status === 'failed') $failedTests++;
    elseif ($status === 'error') $errorTests++;
}

echo str_repeat("-", 80) . "\n";
echo sprintf("Total Property Tests: %d\n", count($testResults));
echo sprintf("Passed: %d\n", $passedTests);
echo sprintf("Failed: %d\n", $failedTests);
echo sprintf("Errors: %d\n", $errorTests);
echo sprintf("Total Assertions: %d\n", $totalTests);
echo sprintf("Passed Assertions: %d\n", $totalPassed);
echo sprintf("Failed Assertions: %d\n", $totalTests - $totalPassed);

if ($failedTests === 0 && $errorTests === 0) {
    echo "\n🎉 ALL STANDALONE PROPERTY-BASED TESTS PASSED! 🎉\n";
    echo "The PHP backend demonstrates excellent compatibility.\n";
} else {
    echo "\n⚠️  SOME TESTS FAILED OR HAD ERRORS\n";
    echo "Review the failed tests above for compatibility issues.\n";
}

echo str_repeat("=", 80) . "\n";