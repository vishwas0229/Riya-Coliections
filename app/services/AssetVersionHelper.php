<?php
/**
 * AssetVersionHelper Class - Asset Versioning and Cache Busting Helper
 * 
 * This class provides helper methods for generating versioned asset URLs
 * in HTML templates and managing asset versioning across the application.
 * 
 * Requirements: 6.3
 */

require_once __DIR__ . '/AssetServer.php';

class AssetVersionHelper {
    private $assetServer;
    private $baseUrl;
    private $manifestCache;
    
    public function __construct($baseUrl = '') {
        $this->assetServer = new AssetServer();
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->manifestCache = null;
    }
    
    /**
     * Generate versioned URL for a single asset
     * 
     * @param string $assetPath The asset path relative to public directory
     * @return string The versioned URL
     */
    public function asset($assetPath) {
        // Ensure path starts with /
        $cleanPath = '/' . ltrim($assetPath, '/');
        
        // Get versioned URL from AssetServer
        $versionedPath = $this->assetServer->getVersionedAssetUrl($cleanPath);
        
        return $this->baseUrl . $versionedPath;
    }
    
    /**
     * Generate versioned URLs for multiple assets
     * 
     * @param array $assetPaths Array of asset paths
     * @return array Array of versioned URLs
     */
    public function assets($assetPaths) {
        $versionedAssets = [];
        
        foreach ($assetPaths as $key => $assetPath) {
            $versionedAssets[$key] = $this->asset($assetPath);
        }
        
        return $versionedAssets;
    }
    
    /**
     * Generate CSS link tag with versioned URL
     * 
     * @param string $cssPath The CSS file path
     * @param array $attributes Additional attributes for the link tag
     * @return string The HTML link tag
     */
    public function css($cssPath, $attributes = []) {
        $url = $this->asset($cssPath);
        
        $defaultAttributes = [
            'rel' => 'stylesheet',
            'type' => 'text/css',
            'href' => $url
        ];
        
        $allAttributes = array_merge($defaultAttributes, $attributes);
        
        return $this->buildHtmlTag('link', $allAttributes, true);
    }
    
    /**
     * Generate JavaScript script tag with versioned URL
     * 
     * @param string $jsPath The JavaScript file path
     * @param array $attributes Additional attributes for the script tag
     * @return string The HTML script tag
     */
    public function js($jsPath, $attributes = []) {
        $url = $this->asset($jsPath);
        
        $defaultAttributes = [
            'type' => 'text/javascript',
            'src' => $url
        ];
        
        $allAttributes = array_merge($defaultAttributes, $attributes);
        
        return $this->buildHtmlTag('script', $allAttributes, false);
    }
    
    /**
     * Generate image tag with versioned URL
     * 
     * @param string $imagePath The image file path
     * @param string $alt Alt text for the image
     * @param array $attributes Additional attributes for the img tag
     * @return string The HTML img tag
     */
    public function img($imagePath, $alt = '', $attributes = []) {
        $url = $this->asset($imagePath);
        
        $defaultAttributes = [
            'src' => $url,
            'alt' => $alt
        ];
        
        $allAttributes = array_merge($defaultAttributes, $attributes);
        
        return $this->buildHtmlTag('img', $allAttributes, true);
    }
    
    /**
     * Generate preload link tag for critical resources
     * 
     * @param string $assetPath The asset file path
     * @param string $asType The asset type (style, script, image, font, etc.)
     * @param array $attributes Additional attributes
     * @return string The HTML link tag
     */
    public function preload($assetPath, $asType, $attributes = []) {
        $url = $this->asset($assetPath);
        
        $defaultAttributes = [
            'rel' => 'preload',
            'href' => $url,
            'as' => $asType
        ];
        
        $allAttributes = array_merge($defaultAttributes, $attributes);
        
        return $this->buildHtmlTag('link', $allAttributes, true);
    }
    
    /**
     * Generate asset manifest for JavaScript consumption
     * 
     * @param array $assetPaths Array of asset paths to include in manifest
     * @return string JSON manifest of versioned assets
     */
    public function generateManifest($assetPaths = []) {
        if (empty($assetPaths)) {
            // Default common assets
            $assetPaths = [
                'css/main.css',
                'css/home.css',
                'css/accessibility.css',
                'js/config.js',
                'js/api.js',
                'js/utils.js',
                'js/main.js',
                'logo.svg'
            ];
        }
        
        $manifest = [];
        
        foreach ($assetPaths as $assetPath) {
            $cleanPath = '/' . ltrim($assetPath, '/');
            $versionedUrl = $this->assetServer->getVersionedAssetUrl($cleanPath);
            $manifest[$assetPath] = $this->baseUrl . $versionedUrl;
        }
        
        return json_encode($manifest, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get cached manifest or generate new one
     * 
     * @param array $assetPaths Array of asset paths
     * @param int $cacheTimeout Cache timeout in seconds
     * @return array Manifest array
     */
    public function getManifest($assetPaths = [], $cacheTimeout = 3600) {
        $cacheKey = md5(serialize($assetPaths));
        
        // Check if we have a cached manifest
        if ($this->manifestCache && 
            isset($this->manifestCache[$cacheKey]) &&
            time() - $this->manifestCache[$cacheKey]['timestamp'] < $cacheTimeout) {
            return $this->manifestCache[$cacheKey]['data'];
        }
        
        // Generate new manifest
        $manifestJson = $this->generateManifest($assetPaths);
        $manifestData = json_decode($manifestJson, true);
        
        // Cache the manifest
        $this->manifestCache[$cacheKey] = [
            'data' => $manifestData,
            'timestamp' => time()
        ];
        
        return $manifestData;
    }
    
    /**
     * Clear asset version cache
     * 
     * @param string|null $assetPath Optional specific asset to clear
     * @return void
     */
    public function clearCache($assetPath = null) {
        $this->assetServer->clearVersionCache($assetPath);
        
        if ($assetPath === null) {
            $this->manifestCache = null;
        }
    }
    
    /**
     * Check if an asset exists
     * 
     * @param string $assetPath The asset path
     * @return bool True if asset exists
     */
    public function exists($assetPath) {
        $cleanPath = '/' . ltrim($assetPath, '/');
        return $this->assetServer->validateAssetPath($cleanPath) !== false;
    }
    
    /**
     * Get asset modification time
     * 
     * @param string $assetPath The asset path
     * @return int|false Modification time or false if not found
     */
    public function getModificationTime($assetPath) {
        $cleanPath = '/' . ltrim($assetPath, '/');
        $filePath = $this->assetServer->validateAssetPath($cleanPath);
        
        if ($filePath && file_exists($filePath)) {
            return filemtime($filePath);
        }
        
        return false;
    }
    
    /**
     * Get asset size
     * 
     * @param string $assetPath The asset path
     * @return int|false File size in bytes or false if not found
     */
    public function getSize($assetPath) {
        $cleanPath = '/' . ltrim($assetPath, '/');
        $filePath = $this->assetServer->validateAssetPath($cleanPath);
        
        if ($filePath && file_exists($filePath)) {
            return filesize($filePath);
        }
        
        return false;
    }
    
    /**
     * Build HTML tag with attributes
     * 
     * @param string $tagName The HTML tag name
     * @param array $attributes Tag attributes
     * @param bool $selfClosing Whether the tag is self-closing
     * @return string The HTML tag
     */
    private function buildHtmlTag($tagName, $attributes, $selfClosing = false) {
        $attributeString = '';
        
        foreach ($attributes as $name => $value) {
            if ($value !== null && $value !== false) {
                $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $attributeString .= " {$name}=\"{$escapedValue}\"";
            }
        }
        
        if ($selfClosing) {
            return "<{$tagName}{$attributeString}>";
        } else {
            return "<{$tagName}{$attributeString}></{$tagName}>";
        }
    }
    
    /**
     * Generate inline JavaScript for asset manifest
     * 
     * @param array $assetPaths Array of asset paths
     * @param string $variableName JavaScript variable name for the manifest
     * @return string JavaScript code
     */
    public function inlineManifestScript($assetPaths = [], $variableName = 'ASSET_MANIFEST') {
        $manifest = $this->getManifest($assetPaths);
        $manifestJson = json_encode($manifest);
        
        return "<script type=\"text/javascript\">\n" .
               "window.{$variableName} = {$manifestJson};\n" .
               "</script>";
    }
    
    /**
     * Get versioning statistics
     * 
     * @return array Statistics about asset versioning
     */
    public function getStatistics() {
        $assetServerStats = $this->assetServer->getStatistics();
        
        $stats = [
            'versioning_enabled' => $assetServerStats['versioning_enabled'] ?? false,
            'manifest_cache_entries' => $this->manifestCache ? count($this->manifestCache) : 0,
            'base_url' => $this->baseUrl
        ];
        
        if (isset($assetServerStats['version_cache'])) {
            $stats['version_cache'] = $assetServerStats['version_cache'];
        }
        
        return $stats;
    }
}