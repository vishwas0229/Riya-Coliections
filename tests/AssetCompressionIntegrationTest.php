<?php
/**
 * Asset Compression Integration Tests
 * 
 * Comprehensive tests for asset compression functionality including:
 * - Client compression support detection
 * - Gzip compression for compressible assets
 * - Configuration settings by asset type
 * - Performance and security validation
 * 
 * **Validates: Requirements 6.2**
 */

require_once __DIR__ . '/../app/services/AssetServer.php';
require_once __DIR__ . '/../app/config/environment.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class AssetCompressionIntegrationTest extends TestCase {
    private $assetServer;
    private $testAssetsDir;
    
    protected function setUp(): void {
        $this->assetServer = new AssetServer();
        $this->testAssetsDir = __DIR__ . '/../public/assets/test-compression/';
        
        // Create test directory
        if (!is_dir($this->testAssetsDir)) {
            mkdir($this->testAssetsDir, 0755, true);
        }
        
        // Reset server variables
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }
    
    protected function tearDown(): void {
        // Clean up test files
        if (is_dir($this->testAssetsDir)) {
            $files = glob($this->testAssetsDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testAssetsDir);
        }
        
        // Reset server variables
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }
    
    /**
     * Test client compression support detection
     * **Validates: Requirements 6.2 - Client compression support detection**
     */
    public function testClientCompressionSupportDetection() {
        $testFile = $this->createTestFile('test.css', 'body { color: red; }');
        
        // Test 1: Client supports gzip
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate';
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->assetServer);
        $shouldCompressMethod = $reflection->getMethod('shouldCompress');
        $shouldCompressMethod->setAccessible(true);
        
        $result = $shouldCompressMethod->invoke($this->assetServer, 'text/css');
        $this->assertTrue($result, 'Should compress when client supports gzip and content is compressible');
        
        // Test 2: Client doesn't support gzip
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'identity';
        $result = $shouldCompressMethod->invoke($this->assetServer, 'text/css');
        $this->assertFalse($result, 'Should not compress when client does not support gzip');
        
        // Test 3: No Accept-Encoding header
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
        $result = $shouldCompressMethod->invoke($this->assetServer, 'text/css');
        $this->assertFalse($result, 'Should not compress when no Accept-Encoding header present');
        
        // Test 4: Client supports multiple encodings including gzip
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'br, gzip, deflate';
        $result = $shouldCompressMethod->invoke($this->assetServer, 'text/css');
        $this->assertTrue($result, 'Should compress when gzip is among supported encodings');
    }
    
    /**
     * Test gzip compression for compressible assets
     * **Validates: Requirements 6.2 - Gzip compression implementation**
     */
    public function testGzipCompressionForCompressibleAssets() {
        // Create highly compressible content
        $compressibleContent = str_repeat('/* CSS comment with repeated content */ body { margin: 0; padding: 0; } ', 100);
        $testFile = $this->createTestFile('compressible.css', $compressibleContent);
        
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        
        // Test compression
        $compressed = $this->assetServer->compressOutput($testFile, 'text/css');
        
        // Verify compression worked
        $this->assertNotEmpty($compressed, 'Compressed content should not be empty');
        $this->assertLessThan(strlen($compressibleContent), strlen($compressed), 'Compressed size should be smaller than original');
        
        // Verify compression ratio is significant (should be > 50% for repetitive content)
        $compressionRatio = (1 - strlen($compressed) / strlen($compressibleContent)) * 100;
        $this->assertGreaterThan(50, $compressionRatio, 'Compression ratio should be significant for repetitive content');
        
        // Verify compressed content can be decompressed
        $decompressed = gzdecode($compressed);
        $this->assertEquals($compressibleContent, $decompressed, 'Decompressed content should match original');
    }
    
    /**
     * Test compression configuration by asset type
     * **Validates: Requirements 6.2 - Configuration settings by asset type**
     */
    public function testCompressionConfigurationByAssetType() {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        
        // Use reflection to access private method and property
        $reflection = new ReflectionClass($this->assetServer);
        $shouldCompressMethod = $reflection->getMethod('shouldCompress');
        $shouldCompressMethod->setAccessible(true);
        
        $compressionTypesProperty = $reflection->getProperty('compressionTypes');
        $compressionTypesProperty->setAccessible(true);
        $compressionTypes = $compressionTypesProperty->getValue($this->assetServer);
        
        // Test compressible MIME types
        $compressibleTypes = [
            'text/css' => true,
            'application/javascript' => true,
            'text/html' => true,
            'application/json' => true,
            'text/plain' => true,
            'image/svg+xml' => true,
            'application/xml' => true
        ];
        
        foreach ($compressibleTypes as $mimeType => $shouldCompress) {
            $result = $shouldCompressMethod->invoke($this->assetServer, $mimeType);
            $this->assertEquals($shouldCompress, $result, "MIME type {$mimeType} compression setting incorrect");
            
            if ($shouldCompress) {
                $this->assertContains($mimeType, $compressionTypes, "MIME type {$mimeType} should be in compression types list");
            }
        }
        
        // Test non-compressible MIME types
        $nonCompressibleTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'video/mp4',
            'audio/mpeg',
            'application/pdf',
            'application/zip'
        ];
        
        foreach ($nonCompressibleTypes as $mimeType) {
            $result = $shouldCompressMethod->invoke($this->assetServer, $mimeType);
            $this->assertFalse($result, "MIME type {$mimeType} should not be compressed");
            $this->assertNotContains($mimeType, $compressionTypes, "MIME type {$mimeType} should not be in compression types list");
        }
    }
    
    /**
     * Test compression with different compression levels
     * **Validates: Requirements 6.2 - Compression level configuration**
     */
    public function testCompressionLevels() {
        $testContent = str_repeat('Test content for compression level testing. ', 50);
        $testFile = $this->createTestFile('level-test.css', $testContent);
        
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        
        // Test different compression levels by modifying the config
        $reflection = new ReflectionClass($this->assetServer);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->assetServer);
        
        $compressionSizes = [];
        
        // Test compression levels 1, 6 (default), and 9
        foreach ([1, 6, 9] as $level) {
            $config['compression_level'] = $level;
            $configProperty->setValue($this->assetServer, $config);
            
            $compressed = $this->assetServer->compressOutput($testFile, 'text/css');
            $compressionSizes[$level] = strlen($compressed);
            
            $this->assertNotEmpty($compressed, "Compression should work at level {$level}");
        }
        
        // Verify that higher compression levels generally produce smaller files
        // (though this isn't guaranteed for all content types)
        $this->assertLessThanOrEqual($compressionSizes[1], $compressionSizes[9], 'Level 9 should compress at least as well as level 1');
    }
    
    /**
     * Test compression with various file sizes
     * **Validates: Requirements 6.2 - Compression efficiency across file sizes**
     */
    public function testCompressionWithVariousFileSizes() {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        
        $testSizes = [
            'small' => 100,    // 100 bytes
            'medium' => 5000,  // 5KB
            'large' => 50000   // 50KB
        ];
        
        foreach ($testSizes as $sizeName => $size) {
            $content = str_repeat('a', $size);
            $testFile = $this->createTestFile("size-test-{$sizeName}.css", $content);
            
            $compressed = $this->assetServer->compressOutput($testFile, 'text/css');
            
            $this->assertNotEmpty($compressed, "Compression should work for {$sizeName} files");
            $this->assertLessThan($size, strlen($compressed), "Compressed {$sizeName} file should be smaller than original");
            
            // For highly repetitive content, compression should be effective
            $compressionRatio = (1 - strlen($compressed) / $size) * 100;
            $this->assertGreaterThan(50, $compressionRatio, "Compression ratio should be significant for repetitive {$sizeName} content");
        }
    }
    
    /**
     * Test compression error handling
     * **Validates: Requirements 6.2 - Robust error handling**
     */
    public function testCompressionErrorHandling() {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        
        // Test with non-existent file
        $nonExistentFile = $this->testAssetsDir . 'non-existent.css';
        
        try {
            $this->assetServer->compressOutput($nonExistentFile, 'text/css');
            $this->fail('Expected exception for non-existent file');
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to open stream', $e->getMessage());
        }
    }
    
    /**
     * Test compression statistics and monitoring
     * **Validates: Requirements 6.2 - Compression monitoring**
     */
    public function testCompressionStatistics() {
        $stats = $this->assetServer->getStatistics();
        
        // Verify compression-related statistics are available
        $this->assertArrayHasKey('compression_enabled', $stats);
        $this->assertIsBool($stats['compression_enabled']);
        
        // Verify compression is enabled by default
        $this->assertTrue($stats['compression_enabled'], 'Compression should be enabled by default');
    }
    
    /**
     * Test compression with real-world content types
     * **Validates: Requirements 6.2 - Real-world asset compression**
     */
    public function testCompressionWithRealWorldContent() {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        
        $realWorldContent = [
            'css' => [
                'content' => '/* Bootstrap-like CSS */ .container { max-width: 1200px; margin: 0 auto; } .row { display: flex; } .col { flex: 1; }',
                'mime' => 'text/css'
            ],
            'js' => [
                'content' => 'function initApp() { console.log("App initialized"); document.addEventListener("DOMContentLoaded", function() { console.log("DOM ready"); }); }',
                'mime' => 'application/javascript'
            ],
            'json' => [
                'content' => json_encode(['users' => [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']], 'meta' => ['total' => 2]]),
                'mime' => 'application/json'
            ],
            'svg' => [
                'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="blue"/></svg>',
                'mime' => 'image/svg+xml'
            ]
        ];
        
        foreach ($realWorldContent as $type => $data) {
            $testFile = $this->createTestFile("real-world.{$type}", $data['content']);
            
            $compressed = $this->assetServer->compressOutput($testFile, $data['mime']);
            
            $this->assertNotEmpty($compressed, "Real-world {$type} content should compress successfully");
            
            // For small content, compression might not always reduce size due to gzip overhead
            // Just verify that compression doesn't fail and produces valid output
            $decompressed = gzdecode($compressed);
            $this->assertEquals($data['content'], $decompressed, "Decompressed {$type} content should match original");
        }
    }
    
    /**
     * Helper method to create test files
     */
    private function createTestFile($filename, $content) {
        $filePath = $this->testAssetsDir . $filename;
        file_put_contents($filePath, $content);
        return $filePath;
    }
}