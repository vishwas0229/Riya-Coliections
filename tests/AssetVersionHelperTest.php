<?php
/**
 * AssetVersionHelper Unit Tests
 * 
 * Tests the AssetVersionHelper class functionality including:
 * - Asset URL generation with versioning
 * - HTML tag generation with versioned assets
 * - Asset manifest generation
 * - Cache management
 */

require_once __DIR__ . '/../app/services/AssetVersionHelper.php';
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class AssetVersionHelperTest extends TestCase {
    private $assetHelper;
    private $testAssets = [];
    
    protected function setUp(): void {
        $this->assetHelper = new AssetVersionHelper();
        
        // Create test asset files
        $this->createTestAsset('css/test.css', 'body { color: red; }');
        $this->createTestAsset('js/test.js', 'console.log("test");');
        $this->createTestAsset('images/test.png', 'fake png data');
    }
    
    protected function tearDown(): void {
        // Clean up test files
        foreach ($this->testAssets as $assetPath) {
            if (file_exists($assetPath)) {
                unlink($assetPath);
            }
        }
        
        // Clean up empty directories
        $dirs = [
            __DIR__ . '/../public/assets/css',
            __DIR__ . '/../public/assets/js',
            __DIR__ . '/../public/assets/images'
        ];
        
        foreach ($dirs as $dir) {
            if (is_dir($dir) && count(scandir($dir)) == 2) { // Only . and ..
                rmdir($dir);
            }
        }
    }
    
    private function createTestAsset($relativePath, $content) {
        $fullPath = __DIR__ . '/../public/assets/' . $relativePath;
        $dir = dirname($fullPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($fullPath, $content);
        $this->testAssets[] = $fullPath;
        
        return $fullPath;
    }
    
    /**
     * Test basic asset URL generation
     * **Validates: Requirements 6.3**
     */
    public function testAssetUrlGeneration() {
        $url = $this->assetHelper->asset('css/test.css');
        
        $this->assertStringStartsWith('/', $url, 'Asset URL should start with /');
        $this->assertStringContainsString('css/test.css', $url, 'Asset URL should contain the asset path');
        
        // Test with leading slash
        $urlWithSlash = $this->assetHelper->asset('/css/test.css');
        $this->assertEquals($url, $urlWithSlash, 'URLs should be the same regardless of leading slash');
    }
    
    /**
     * Test multiple asset URL generation
     * **Validates: Requirements 6.3**
     */
    public function testMultipleAssetUrls() {
        $assetPaths = [
            'main_css' => 'css/test.css',
            'main_js' => 'js/test.js',
            'logo' => 'images/test.png'
        ];
        
        $urls = $this->assetHelper->assets($assetPaths);
        
        $this->assertCount(3, $urls, 'Should return URLs for all assets');
        $this->assertArrayHasKey('main_css', $urls, 'Should preserve array keys');
        $this->assertArrayHasKey('main_js', $urls, 'Should preserve array keys');
        $this->assertArrayHasKey('logo', $urls, 'Should preserve array keys');
        
        foreach ($urls as $key => $url) {
            $this->assertStringStartsWith('/', $url, "URL for {$key} should start with /");
        }
    }
    
    /**
     * Test CSS link tag generation
     * **Validates: Requirements 6.3**
     */
    public function testCssTagGeneration() {
        $cssTag = $this->assetHelper->css('css/test.css');
        
        $this->assertStringStartsWith('<link', $cssTag, 'Should generate link tag');
        $this->assertStringContainsString('rel="stylesheet"', $cssTag, 'Should have stylesheet rel');
        $this->assertStringContainsString('type="text/css"', $cssTag, 'Should have CSS type');
        $this->assertStringContainsString('href=', $cssTag, 'Should have href attribute');
        
        // Test with additional attributes
        $cssTagWithAttrs = $this->assetHelper->css('css/test.css', ['media' => 'print', 'id' => 'print-styles']);
        $this->assertStringContainsString('media="print"', $cssTagWithAttrs, 'Should include additional attributes');
        $this->assertStringContainsString('id="print-styles"', $cssTagWithAttrs, 'Should include additional attributes');
    }
    
    /**
     * Test JavaScript script tag generation
     * **Validates: Requirements 6.3**
     */
    public function testJsTagGeneration() {
        $jsTag = $this->assetHelper->js('js/test.js');
        
        $this->assertStringStartsWith('<script', $jsTag, 'Should generate script tag');
        $this->assertStringContainsString('type="text/javascript"', $jsTag, 'Should have JavaScript type');
        $this->assertStringContainsString('src=', $jsTag, 'Should have src attribute');
        $this->assertStringEndsWith('></script>', $jsTag, 'Should be properly closed');
        
        // Test with additional attributes
        $jsTagWithAttrs = $this->assetHelper->js('js/test.js', ['async' => 'async', 'defer' => 'defer']);
        $this->assertStringContainsString('async="async"', $jsTagWithAttrs, 'Should include additional attributes');
        $this->assertStringContainsString('defer="defer"', $jsTagWithAttrs, 'Should include additional attributes');
    }
    
    /**
     * Test image tag generation
     * **Validates: Requirements 6.3**
     */
    public function testImgTagGeneration() {
        $imgTag = $this->assetHelper->img('images/test.png', 'Test Image');
        
        $this->assertStringStartsWith('<img', $imgTag, 'Should generate img tag');
        $this->assertStringContainsString('src=', $imgTag, 'Should have src attribute');
        $this->assertStringContainsString('alt="Test Image"', $imgTag, 'Should have alt text');
        
        // Test with additional attributes
        $imgTagWithAttrs = $this->assetHelper->img('images/test.png', 'Test', ['width' => '100', 'height' => '100']);
        $this->assertStringContainsString('width="100"', $imgTagWithAttrs, 'Should include additional attributes');
        $this->assertStringContainsString('height="100"', $imgTagWithAttrs, 'Should include additional attributes');
    }
    
    /**
     * Test preload link generation
     * **Validates: Requirements 6.3**
     */
    public function testPreloadGeneration() {
        $preloadTag = $this->assetHelper->preload('css/test.css', 'style');
        
        $this->assertStringStartsWith('<link', $preloadTag, 'Should generate link tag');
        $this->assertStringContainsString('rel="preload"', $preloadTag, 'Should have preload rel');
        $this->assertStringContainsString('as="style"', $preloadTag, 'Should have correct as attribute');
        $this->assertStringContainsString('href=', $preloadTag, 'Should have href attribute');
        
        // Test font preload with crossorigin
        $fontPreload = $this->assetHelper->preload('fonts/test.woff2', 'font', ['crossorigin' => 'anonymous']);
        $this->assertStringContainsString('as="font"', $fontPreload, 'Should have font as attribute');
        $this->assertStringContainsString('crossorigin="anonymous"', $fontPreload, 'Should include crossorigin');
    }
    
    /**
     * Test asset manifest generation
     * **Validates: Requirements 6.3**
     */
    public function testManifestGeneration() {
        $assetPaths = ['css/test.css', 'js/test.js', 'images/test.png'];
        $manifestJson = $this->assetHelper->generateManifest($assetPaths);
        
        $this->assertJson($manifestJson, 'Should generate valid JSON');
        
        $manifest = json_decode($manifestJson, true);
        $this->assertIsArray($manifest, 'Should decode to array');
        $this->assertCount(3, $manifest, 'Should contain all requested assets');
        
        foreach ($assetPaths as $assetPath) {
            $this->assertArrayHasKey($assetPath, $manifest, "Should contain {$assetPath}");
            $this->assertStringStartsWith('/', $manifest[$assetPath], "URL for {$assetPath} should start with /");
        }
    }
    
    /**
     * Test cached manifest functionality
     * **Validates: Requirements 6.3**
     */
    public function testCachedManifest() {
        $assetPaths = ['css/test.css', 'js/test.js'];
        
        // First call should generate manifest
        $manifest1 = $this->assetHelper->getManifest($assetPaths);
        $this->assertIsArray($manifest1, 'Should return array');
        
        // Second call should return cached version
        $manifest2 = $this->assetHelper->getManifest($assetPaths);
        $this->assertEquals($manifest1, $manifest2, 'Cached manifest should be identical');
        
        // Clear cache and verify
        $this->assetHelper->clearCache();
        $manifest3 = $this->assetHelper->getManifest($assetPaths);
        $this->assertEquals($manifest1, $manifest3, 'Manifest should be regenerated after cache clear');
    }
    
    /**
     * Test asset existence checking
     * **Validates: Requirements 6.3**
     */
    public function testAssetExistence() {
        // Test existing asset
        $this->assertTrue($this->assetHelper->exists('css/test.css'), 'Should detect existing asset');
        
        // Test non-existing asset
        $this->assertFalse($this->assetHelper->exists('css/nonexistent.css'), 'Should detect non-existing asset');
        
        // Test with leading slash
        $this->assertTrue($this->assetHelper->exists('/css/test.css'), 'Should work with leading slash');
    }
    
    /**
     * Test asset modification time retrieval
     * **Validates: Requirements 6.3**
     */
    public function testAssetModificationTime() {
        $mtime = $this->assetHelper->getModificationTime('css/test.css');
        
        $this->assertIsInt($mtime, 'Should return integer timestamp');
        $this->assertGreaterThan(0, $mtime, 'Should return valid timestamp');
        
        // Test non-existing asset
        $this->assertFalse($this->assetHelper->getModificationTime('css/nonexistent.css'), 'Should return false for non-existing asset');
    }
    
    /**
     * Test asset size retrieval
     * **Validates: Requirements 6.3**
     */
    public function testAssetSize() {
        $size = $this->assetHelper->getSize('css/test.css');
        
        $this->assertIsInt($size, 'Should return integer size');
        $this->assertGreaterThan(0, $size, 'Should return valid size');
        
        // Test non-existing asset
        $this->assertFalse($this->assetHelper->getSize('css/nonexistent.css'), 'Should return false for non-existing asset');
    }
    
    /**
     * Test inline manifest script generation
     * **Validates: Requirements 6.3**
     */
    public function testInlineManifestScript() {
        $assetPaths = ['css/test.css', 'js/test.js'];
        $script = $this->assetHelper->inlineManifestScript($assetPaths, 'TEST_MANIFEST');
        
        $this->assertStringStartsWith('<script', $script, 'Should generate script tag');
        $this->assertStringContainsString('window.TEST_MANIFEST', $script, 'Should use custom variable name');
        $this->assertStringContainsString('css\/test.css', $script, 'Should contain asset paths');
        $this->assertStringEndsWith('</script>', $script, 'Should be properly closed');
    }
    
    /**
     * Test versioning statistics
     * **Validates: Requirements 6.3**
     */
    public function testVersioningStatistics() {
        $stats = $this->assetHelper->getStatistics();
        
        $this->assertIsArray($stats, 'Should return array');
        $this->assertArrayHasKey('versioning_enabled', $stats, 'Should include versioning status');
        $this->assertArrayHasKey('manifest_cache_entries', $stats, 'Should include cache info');
        $this->assertArrayHasKey('base_url', $stats, 'Should include base URL');
        
        $this->assertIsBool($stats['versioning_enabled'], 'Versioning enabled should be boolean');
        $this->assertIsInt($stats['manifest_cache_entries'], 'Cache entries should be integer');
    }
    
    /**
     * Test HTML attribute escaping
     * **Validates: Requirements 6.3**
     */
    public function testHtmlAttributeEscaping() {
        // Test with potentially dangerous content
        $dangerousAlt = 'Test "image" & <script>alert("xss")</script>';
        $imgTag = $this->assetHelper->img('images/test.png', $dangerousAlt);
        
        $this->assertStringNotContainsString('<script>', $imgTag, 'Should escape script tags');
        // Check that raw ampersands are escaped (but allow HTML entities)
        $this->assertStringNotContainsString('& ', $imgTag, 'Should escape standalone ampersands');
        $this->assertStringContainsString('&quot;', $imgTag, 'Should escape quotes');
        $this->assertStringContainsString('&lt;', $imgTag, 'Should escape less than');
    }
}