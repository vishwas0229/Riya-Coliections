<?php
/**
 * AssetServer Class - Enhanced Static File Serving System
 * 
 * This class provides comprehensive static asset serving capabilities with:
 * - MIME type detection for all frontend asset types
 * - HTTP caching headers based on asset type
 * - Security validation to prevent path traversal attacks
 * - Compression support for compressible assets
 * - Performance optimizations and logging
 * - Comprehensive error handling for permissions and corruption
 * 
 * Requirements: 2.1, 2.3, 2.4, 6.1, 6.2, 6.4, 6.5
 */

require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Exception for asset permission errors
 */
class AssetPermissionException extends Exception {
    public function __construct($message = "", $code = 403, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Exception for asset corruption errors
 */
class AssetCorruptionException extends Exception {
    public function __construct($message = "", $code = 500, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class AssetServer {
    private $config;
    private $mimeTypes;
    private $cacheRules;
    private $compressionTypes;
    private $securityHeaders;
    private $versionCache;
    
    public function __construct() {
        $this->initializeConfiguration();
        $this->initializeMimeTypes();
        $this->initializeCacheRules();
        $this->initializeCompressionTypes();
        $this->initializeSecurityHeaders();
        $this->initializeVersionCache();
    }
    
    /**
     * Initialize asset server configuration
     */
    private function initializeConfiguration() {
        $this->config = [
            'asset_cache_duration' => (int) env('ASSET_CACHE_DURATION', 86400), // 24 hours
            'enable_compression' => env('ENABLE_ASSET_COMPRESSION', 'true') === 'true',
            'compression_level' => (int) env('COMPRESSION_LEVEL', 6),
            'max_file_size' => (int) env('MAX_ASSET_SIZE', 52428800), // 50MB
            'enable_etag' => env('ENABLE_ETAG', 'true') === 'true',
            'enable_last_modified' => env('ENABLE_LAST_MODIFIED', 'true') === 'true',
            'log_asset_requests' => env('LOG_ASSET_REQUESTS', 'false') === 'true',
            'enable_versioning' => env('ENABLE_ASSET_VERSIONING', 'true') === 'true',
            'version_cache_duration' => (int) env('VERSION_CACHE_DURATION', 3600), // 1 hour
            'version_query_param' => env('VERSION_QUERY_PARAM', 'v'),
            'allowed_directories' => [
                realpath(__DIR__ . '/../../public/assets'),
                realpath(__DIR__ . '/../../public/uploads'),
                realpath(__DIR__ . '/../../public/pages'),
                realpath(__DIR__ . '/../../public')
            ]
        ];
        
        // Filter out null paths (in case directories don't exist)
        $this->config['allowed_directories'] = array_filter($this->config['allowed_directories']);
    }
    
    /**
     * Initialize comprehensive MIME type mappings
     */
    private function initializeMimeTypes() {
        $this->mimeTypes = [
            // Web documents
            'html' => 'text/html',
            'htm' => 'text/html',
            'xhtml' => 'application/xhtml+xml',
            'xml' => 'application/xml',
            
            // Stylesheets
            'css' => 'text/css',
            'scss' => 'text/x-scss',
            'sass' => 'text/x-sass',
            'less' => 'text/x-less',
            
            // JavaScript
            'js' => 'application/javascript',
            'mjs' => 'application/javascript',
            'jsx' => 'text/jsx',
            'ts' => 'application/typescript',
            'tsx' => 'text/tsx',
            
            // Data formats
            'json' => 'application/json',
            'jsonld' => 'application/ld+json',
            'geojson' => 'application/geo+json',
            
            // Images - Raster
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'ico' => 'image/x-icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            
            // Images - Vector
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            
            // Fonts
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
            
            // Audio
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            
            // Video
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            
            // Documents
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'rtf' => 'application/rtf',
            
            // Archives
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'bz2' => 'application/x-bzip2',
            '7z' => 'application/x-7z-compressed',
            'rar' => 'application/vnd.rar',
            
            // Application files
            'swf' => 'application/x-shockwave-flash',
            'jar' => 'application/java-archive',
            
            // Default fallback
            '' => 'application/octet-stream'
        ];
    }
    
    /**
     * Initialize cache rules by file type
     */
    private function initializeCacheRules() {
        $this->cacheRules = [
            // Long-term caching for static assets
            'css' => 31536000,    // 1 year
            'js' => 31536000,     // 1 year
            'woff' => 31536000,   // 1 year
            'woff2' => 31536000,  // 1 year
            'ttf' => 31536000,    // 1 year
            'otf' => 31536000,    // 1 year
            'eot' => 31536000,    // 1 year
            
            // Medium-term caching for images
            'jpg' => 2592000,     // 30 days
            'jpeg' => 2592000,    // 30 days
            'png' => 2592000,     // 30 days
            'gif' => 2592000,     // 30 days
            'webp' => 2592000,    // 30 days
            'avif' => 2592000,    // 30 days
            'svg' => 2592000,     // 30 days
            'ico' => 2592000,     // 30 days
            
            // Short-term caching for dynamic content
            'html' => 3600,       // 1 hour
            'htm' => 3600,        // 1 hour
            'json' => 3600,       // 1 hour
            'xml' => 3600,        // 1 hour
            
            // No caching for certain file types
            'txt' => 0,           // No cache
            'md' => 0,            // No cache
            
            // Default cache duration
            'default' => $this->config['asset_cache_duration']
        ];
    }
    
    /**
     * Initialize compression-eligible file types
     */
    private function initializeCompressionTypes() {
        $this->compressionTypes = [
            'text/html',
            'text/css',
            'text/plain',
            'text/xml',
            'text/markdown',
            'text/x-scss',
            'text/x-sass',
            'text/x-less',
            'text/jsx',
            'text/tsx',
            'application/javascript',
            'application/typescript',
            'application/json',
            'application/ld+json',
            'application/geo+json',
            'application/xml',
            'application/xhtml+xml',
            'image/svg+xml'
        ];
    }
    
    /**
     * Initialize security headers by file type
     */
    private function initializeSecurityHeaders() {
        $this->securityHeaders = [
            'html' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-XSS-Protection' => '1; mode=block'
            ],
            'htm' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-XSS-Protection' => '1; mode=block'
            ],
            'svg' => [
                'X-Content-Type-Options' => 'nosniff'
            ],
            'js' => [
                'X-Content-Type-Options' => 'nosniff'
            ],
            'css' => [
                'X-Content-Type-Options' => 'nosniff'
            ],
            'default' => [
                'X-Content-Type-Options' => 'nosniff'
            ]
        ];
    }
    
    /**
     * Initialize version cache for asset versioning
     */
    private function initializeVersionCache() {
        $this->versionCache = [];
    }
    
    /**
     * Generate version hash for an asset file
     * 
     * @param string $filePath The file path
     * @return string The version hash
     */
    public function generateAssetVersion($filePath) {
        if (!$this->config['enable_versioning']) {
            return '';
        }
        
        if (!file_exists($filePath)) {
            return '';
        }
        
        // Check cache first
        $cacheKey = $filePath;
        if (isset($this->versionCache[$cacheKey])) {
            $cached = $this->versionCache[$cacheKey];
            // Check if cache is still valid
            if (time() - $cached['timestamp'] < $this->config['version_cache_duration']) {
                return $cached['version'];
            }
        }
        
        // Generate version based on file modification time and size
        $stat = stat($filePath);
        $version = substr(md5($filePath . $stat['mtime'] . $stat['size']), 0, 8);
        
        // Cache the version
        $this->versionCache[$cacheKey] = [
            'version' => $version,
            'timestamp' => time(),
            'mtime' => $stat['mtime'],
            'size' => $stat['size']
        ];
        
        return $version;
    }
    
    /**
     * Get versioned URL for an asset
     * 
     * @param string $assetPath The asset path (relative to public directory)
     * @return string The versioned URL
     */
    public function getVersionedAssetUrl($assetPath) {
        if (!$this->config['enable_versioning']) {
            return $assetPath;
        }
        
        // Resolve the full file path
        $filePath = $this->validateAssetPath($assetPath);
        
        if (!$filePath) {
            // Return original path if file doesn't exist
            return $assetPath;
        }
        
        // Generate version
        $version = $this->generateAssetVersion($filePath);
        
        if (empty($version)) {
            return $assetPath;
        }
        
        // Add version parameter to URL
        $separator = strpos($assetPath, '?') !== false ? '&' : '?';
        return $assetPath . $separator . $this->config['version_query_param'] . '=' . $version;
    }
    
    /**
     * Check if asset version is valid (for cache busting)
     * 
     * @param string $filePath The file path
     * @param string $requestedVersion The version from the request
     * @return bool True if version is valid
     */
    public function isAssetVersionValid($filePath, $requestedVersion) {
        if (!$this->config['enable_versioning'] || empty($requestedVersion)) {
            return true; // Always valid if versioning is disabled
        }
        
        $currentVersion = $this->generateAssetVersion($filePath);
        return $currentVersion === $requestedVersion;
    }
    
    /**
     * Clear version cache for a specific asset or all assets
     * 
     * @param string|null $assetPath Optional specific asset path to clear
     * @return void
     */
    public function clearVersionCache($assetPath = null) {
        if ($assetPath === null) {
            // Clear entire cache
            $this->versionCache = [];
        } else {
            // Clear specific asset
            $filePath = $this->validateAssetPath($assetPath);
            if ($filePath && isset($this->versionCache[$filePath])) {
                unset($this->versionCache[$filePath]);
            }
        }
    }
    
    /**
     * Get version cache statistics
     * 
     * @return array Cache statistics
     */
    public function getVersionCacheStats() {
        $stats = [
            'total_cached' => count($this->versionCache),
            'cache_hits' => 0,
            'expired_entries' => 0,
            'memory_usage' => 0
        ];
        
        $currentTime = time();
        
        foreach ($this->versionCache as $entry) {
            if ($currentTime - $entry['timestamp'] >= $this->config['version_cache_duration']) {
                $stats['expired_entries']++;
            }
        }
        
        $stats['memory_usage'] = strlen(serialize($this->versionCache));
        
        return $stats;
    }
    
    /**
     * Batch generate versions for multiple assets
     * 
     * @param array $assetPaths Array of asset paths
     * @return array Array of asset paths with their versions
     */
    public function batchGenerateVersions($assetPaths) {
        $versions = [];
        
        foreach ($assetPaths as $assetPath) {
            $versions[$assetPath] = $this->getVersionedAssetUrl($assetPath);
        }
        
        return $versions;
    }
    
    /**
     * Serve static asset with comprehensive error handling
     * 
     * @param string $requestPath The requested asset path
     * @return void
     */
    public function serve($requestPath) {
        try {
            // Parse version parameter from request path
            $parsedPath = $this->parseVersionedPath($requestPath);
            $cleanPath = $parsedPath['path'];
            $requestedVersion = $parsedPath['version'];
            
            // Validate and resolve asset path with comprehensive error handling
            try {
                $filePath = $this->validateAssetPath($cleanPath);
            } catch (AssetPermissionException $e) {
                $this->serve403($requestPath, $e->getMessage());
                return;
            } catch (AssetCorruptionException $e) {
                $this->serve500($requestPath, $e->getMessage(), $e);
                return;
            }
            
            if (!$filePath) {
                $this->serve404($requestPath, 'File not found in allowed directories');
                return;
            }
            
            // Check version validity for cache busting
            if (!$this->isAssetVersionValid($filePath, $requestedVersion)) {
                // Version mismatch - redirect to current version
                $currentVersionedUrl = $this->getVersionedAssetUrl($cleanPath);
                if ($currentVersionedUrl !== $requestPath) {
                    $this->redirectToCurrentVersion($currentVersionedUrl);
                    return;
                }
            }
            
            // Get file information with corruption detection
            try {
                $fileInfo = $this->getFileInfo($filePath);
            } catch (Exception $e) {
                $this->serve500($requestPath, 'Failed to read file information', $e);
                return;
            }
            
            // Check client cache
            if ($this->checkClientCache($fileInfo)) {
                $this->serve304();
                return;
            }
            
            // Determine MIME type
            $mimeType = $this->getMimeType($filePath);
            
            // Set response headers (including versioning headers)
            $this->setResponseHeaders($fileInfo, $mimeType, $requestedVersion);
            
            // Serve file content with error handling
            try {
                $this->serveFileContent($filePath, $mimeType);
            } catch (Exception $e) {
                // If we've already started sending content, we can't send error headers
                if (!headers_sent()) {
                    $this->serve500($requestPath, 'Failed to serve file content', $e);
                } else {
                    // Log the error but can't change response
                    Logger::error('Failed to serve file content after headers sent', [
                        'path' => $requestPath,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }
                return;
            }
            
            // Log successful serving
            if ($this->config['log_asset_requests']) {
                $this->logAssetRequest($requestPath, $fileInfo, 200);
            }
            
        } catch (Exception $e) {
            $this->handleAssetError($requestPath, $e);
        }
    }
    
    /**
     * Parse version parameter from request path
     * 
     * @param string $requestPath The request path with potential version parameter
     * @return array Array with 'path' and 'version' keys
     */
    private function parseVersionedPath($requestPath) {
        $result = [
            'path' => $requestPath,
            'version' => null
        ];
        
        if (!$this->config['enable_versioning']) {
            return $result;
        }
        
        // Parse query parameters
        $urlParts = parse_url($requestPath);
        
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            
            if (isset($queryParams[$this->config['version_query_param']])) {
                $result['version'] = $queryParams[$this->config['version_query_param']];
                
                // Remove version parameter from path
                unset($queryParams[$this->config['version_query_param']]);
                
                $cleanQuery = http_build_query($queryParams);
                $result['path'] = $urlParts['path'] . ($cleanQuery ? '?' . $cleanQuery : '');
            }
        } else {
            $result['path'] = $urlParts['path'] ?? $requestPath;
        }
        
        return $result;
    }
    
    /**
     * Redirect to current version of asset
     * 
     * @param string $currentVersionedUrl The current versioned URL
     * @return void
     */
    private function redirectToCurrentVersion($currentVersionedUrl) {
        http_response_code(302);
        header('Location: ' . $currentVersionedUrl);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        // Log redirect for monitoring
        Logger::info('Asset version redirect', [
            'from' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'to' => $currentVersionedUrl,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    /**
     * Validate asset path and prevent security issues with comprehensive error handling
     * 
     * @param string $requestPath The requested path
     * @return string|false The validated file path or false if invalid
     * @throws AssetPermissionException For permission-related errors
     * @throws AssetCorruptionException For file corruption errors
     */
    public function validateAssetPath($requestPath) {
        // Remove leading slash and decode URL encoding
        $path = ltrim(urldecode($requestPath), '/');
        
        // Security check: prevent path traversal
        if (preg_match('/\.\.(\/|\\\\)/', $path) || strpos($path, '..') !== false) {
            Logger::warning('Path traversal attempt detected', [
                'requested_path' => $requestPath,
                'decoded_path' => $path,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        
        // Security check: prevent access to sensitive files
        $sensitivePatterns = [
            '/\.env/',
            '/\.git/',
            '/\.htaccess/',
            '/\.htpasswd/',
            '/config\.php/',
            '/database\.php/',
            '/\.log$/',
            '/\.bak$/',
            '/\.backup$/',
            '/\.sql$/'
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                Logger::warning('Attempt to access sensitive file', [
                    'requested_path' => $requestPath,
                    'pattern_matched' => $pattern,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return false;
            }
        }
        
        // Try to find file in allowed directories
        foreach ($this->config['allowed_directories'] as $allowedDir) {
            $fullPath = $allowedDir . DIRECTORY_SEPARATOR . $path;
            $realPath = realpath($fullPath);
            
            // Check if path exists
            if (!$realPath || !file_exists($realPath)) {
                continue; // Try next directory
            }
            
            // Verify it's a file (not directory)
            if (!is_file($realPath)) {
                Logger::warning('Requested path is not a file', [
                    'path' => $requestPath,
                    'real_path' => $realPath,
                    'is_dir' => is_dir($realPath)
                ]);
                continue;
            }
            
            // Verify path is within allowed directory (security check)
            if (strpos($realPath, $allowedDir) !== 0) {
                Logger::warning('Path outside allowed directory', [
                    'path' => $requestPath,
                    'real_path' => $realPath,
                    'allowed_dir' => $allowedDir
                ]);
                continue;
            }
            
            // Check file permissions
            if (!is_readable($realPath)) {
                Logger::error('Asset file not readable - permission denied', [
                    'path' => $requestPath,
                    'real_path' => $realPath,
                    'permissions' => substr(sprintf('%o', fileperms($realPath)), -4),
                    'owner' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($realPath))['name'] ?? 'unknown' : 'unknown'
                ]);
                throw new AssetPermissionException("File not readable: {$requestPath}");
            }
            
            // Check file size
            $fileSize = filesize($realPath);
            if ($fileSize === false) {
                Logger::error('Cannot determine file size - possible corruption', [
                    'path' => $requestPath,
                    'real_path' => $realPath
                ]);
                throw new AssetCorruptionException("Cannot read file size: {$requestPath}");
            }
            
            if ($fileSize > $this->config['max_file_size']) {
                Logger::warning('Asset file too large', [
                    'path' => $requestPath,
                    'size' => $fileSize,
                    'max_size' => $this->config['max_file_size']
                ]);
                return false;
            }
            
            // Basic corruption check - ensure file can be opened
            $handle = @fopen($realPath, 'r');
            if ($handle === false) {
                Logger::error('Cannot open file - possible corruption or permission issue', [
                    'path' => $requestPath,
                    'real_path' => $realPath,
                    'error' => error_get_last()
                ]);
                throw new AssetCorruptionException("Cannot open file: {$requestPath}");
            }
            fclose($handle);
            
            return $realPath;
        }
        
        return false;
    }
    
    /**
     * Get comprehensive file information with corruption detection
     * 
     * @param string $filePath The file path
     * @return array File information
     * @throws AssetCorruptionException If file is corrupted or unreadable
     */
    private function getFileInfo($filePath) {
        // Attempt to get file stats
        $stat = @stat($filePath);
        if ($stat === false) {
            throw new AssetCorruptionException("Cannot read file statistics for: {$filePath}");
        }
        
        // Verify file is still readable (could have changed since validation)
        if (!is_readable($filePath)) {
            throw new AssetCorruptionException("File is no longer readable: {$filePath}");
        }
        
        // Additional corruption checks for critical file properties
        if ($stat['size'] < 0) {
            throw new AssetCorruptionException("Invalid file size detected: {$filePath}");
        }
        
        // Check if file can be opened (basic corruption test)
        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            $error = error_get_last();
            throw new AssetCorruptionException("Cannot open file for reading: {$filePath}. Error: " . ($error['message'] ?? 'Unknown error'));
        }
        
        // For non-empty files, try to read first byte to ensure file is accessible
        if ($stat['size'] > 0) {
            $firstByte = @fread($handle, 1);
            if ($firstByte === false) {
                fclose($handle);
                throw new AssetCorruptionException("Cannot read file content: {$filePath}");
            }
        }
        
        fclose($handle);
        
        return [
            'path' => $filePath,
            'size' => $stat['size'],
            'mtime' => $stat['mtime'],
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'basename' => basename($filePath),
            'etag' => $this->generateETag($filePath, $stat)
        ];
    }
    
    /**
     * Generate ETag for file
     * 
     * @param string $filePath The file path
     * @param array $stat File stat information
     * @return string The ETag value
     */
    private function generateETag($filePath, $stat) {
        if (!$this->config['enable_etag']) {
            return null;
        }
        
        return '"' . md5($filePath . $stat['mtime'] . $stat['size']) . '"';
    }
    
    /**
     * Check if client has cached version
     * 
     * @param array $fileInfo File information
     * @return bool True if client cache is valid
     */
    private function checkClientCache($fileInfo) {
        // Check ETag
        if ($this->config['enable_etag'] && $fileInfo['etag']) {
            $clientETag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($clientETag === $fileInfo['etag']) {
                return true;
            }
        }
        
        // Check Last-Modified
        if ($this->config['enable_last_modified']) {
            $clientModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
            if ($clientModified && strtotime($clientModified) >= $fileInfo['mtime']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get MIME type for file
     * 
     * @param string $filePath The file path
     * @return string The MIME type
     */
    public function getMimeType($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Use configured MIME type or default
        $mimeType = $this->mimeTypes[$extension] ?? $this->mimeTypes[''];
        
        // Try to use PHP's built-in detection as fallback
        if ($mimeType === $this->mimeTypes[''] && function_exists('mime_content_type')) {
            $detectedType = mime_content_type($filePath);
            if ($detectedType) {
                $mimeType = $detectedType;
            }
        }
        
        return $mimeType;
    }
    
    /**
     * Get cache headers for file
     * 
     * @param array $fileInfo File information
     * @param string|null $requestedVersion Requested version for enhanced caching
     * @return array Cache headers
     */
    public function getCacheHeaders($fileInfo, $requestedVersion = null) {
        $extension = $fileInfo['extension'];
        $cacheDuration = $this->cacheRules[$extension] ?? $this->cacheRules['default'];
        
        $headers = [];
        
        // Enhanced caching for versioned assets
        if ($this->config['enable_versioning'] && $requestedVersion) {
            // Versioned assets can be cached for much longer
            $versionedCacheDuration = max($cacheDuration, 31536000); // At least 1 year
            $headers['Cache-Control'] = "public, max-age={$versionedCacheDuration}, immutable";
            $headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $versionedCacheDuration) . ' GMT';
        } else if ($cacheDuration > 0) {
            $headers['Cache-Control'] = "public, max-age={$cacheDuration}";
            $headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $cacheDuration) . ' GMT';
        } else {
            $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
            $headers['Pragma'] = 'no-cache';
            $headers['Expires'] = '0';
        }
        
        if ($this->config['enable_last_modified']) {
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $fileInfo['mtime']) . ' GMT';
        }
        
        if ($this->config['enable_etag'] && $fileInfo['etag']) {
            $headers['ETag'] = $fileInfo['etag'];
        }
        
        return $headers;
    }
    
    /**
     * Set response headers for asset
     * 
     * @param array $fileInfo File information
     * @param string $mimeType MIME type
     * @param string|null $requestedVersion Requested version for cache headers
     * @return void
     */
    private function setResponseHeaders($fileInfo, $mimeType, $requestedVersion = null) {
        // Set content type
        header('Content-Type: ' . $mimeType);
        
        // Set cache headers (enhanced for versioning)
        $cacheHeaders = $this->getCacheHeaders($fileInfo, $requestedVersion);
        foreach ($cacheHeaders as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Set security headers
        $extension = $fileInfo['extension'];
        $securityHeaders = $this->securityHeaders[$extension] ?? $this->securityHeaders['default'];
        
        foreach ($securityHeaders as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Set versioning headers
        if ($this->config['enable_versioning'] && $requestedVersion) {
            header('X-Asset-Version: ' . $requestedVersion);
        }
        
        // Set content length (will be updated if compressed)
        header('Content-Length: ' . $fileInfo['size']);
        
        // Set additional headers
        header('Accept-Ranges: bytes');
        header('Vary: Accept-Encoding');
    }
    
    /**
     * Serve file content with optional compression and error handling
     * 
     * @param string $filePath The file path
     * @param string $mimeType MIME type
     * @return void
     * @throws Exception If file cannot be served
     */
    private function serveFileContent($filePath, $mimeType) {
        // Check if compression should be applied
        if ($this->shouldCompress($mimeType)) {
            $this->serveCompressed($filePath);
        } else {
            $this->serveUncompressed($filePath);
        }
    }
    
    /**
     * Check if content should be compressed
     * 
     * @param string $mimeType MIME type
     * @return bool True if should compress
     */
    private function shouldCompress($mimeType) {
        if (!$this->config['enable_compression'] || !extension_loaded('zlib')) {
            return false;
        }
        
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        if (strpos($acceptEncoding, 'gzip') === false) {
            return false;
        }
        
        return in_array($mimeType, $this->compressionTypes);
    }
    
    /**
     * Compress output for testing purposes (public method)
     * 
     * @param string $filePath The file path
     * @param string $mimeType MIME type
     * @return string The compressed content
     * @throws Exception If compression fails
     */
    public function compressOutput($filePath, $mimeType) {
        $content = @file_get_contents($filePath);
        
        if ($content === false) {
            throw new Exception('Failed to read file content');
        }
        
        $compressed = @gzencode($content, $this->config['compression_level']);
        
        if ($compressed === false) {
            throw new Exception('Failed to compress content');
        }
        
        // Only set headers if not in test mode (headers not already sent)
        if (!headers_sent()) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . strlen($compressed));
        }
        
        echo $compressed;
        
        return $compressed;
    }
    
    /**
     * Serve compressed content with error handling
     * 
     * @param string $filePath The file path
     * @return void
     * @throws Exception If compression or serving fails
     */
    private function serveCompressed($filePath) {
        $content = @file_get_contents($filePath);
        
        if ($content === false) {
            throw new Exception('Failed to read file content for compression');
        }
        
        $compressed = @gzencode($content, $this->config['compression_level']);
        
        if ($compressed === false) {
            throw new Exception('Failed to compress content');
        }
        
        header('Content-Encoding: gzip');
        header('Content-Length: ' . strlen($compressed));
        
        echo $compressed;
    }
    
    /**
     * Serve uncompressed content with error handling
     * 
     * @param string $filePath The file path
     * @return void
     * @throws Exception If file cannot be served
     */
    private function serveUncompressed($filePath) {
        // Use readfile for better memory efficiency with large files
        $result = @readfile($filePath);
        if ($result === false) {
            throw new Exception('Failed to serve file content');
        }
    }
    
    /**
     * Serve 304 Not Modified response
     * 
     * @return void
     */
    private function serve304() {
        http_response_code(304);
        
        // Remove content headers for 304 response
        header_remove('Content-Type');
        header_remove('Content-Length');
        header_remove('Content-Encoding');
    }
    
    /**
     * Serve 404 Not Found response with comprehensive error details
     * 
     * @param string $requestPath The requested path
     * @param string|null $reason Optional reason for 404
     * @return void
     */
    private function serve404($requestPath, $reason = null) {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        
        $response = [
            'error' => 'Asset not found',
            'path' => $requestPath,
            'timestamp' => date('c')
        ];
        
        if ($reason) {
            $response['reason'] = $reason;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
        // Enhanced logging for 404 errors
        Logger::warning('Asset not found', [
            'path' => $requestPath,
            'reason' => $reason,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'query_string' => $_SERVER['QUERY_STRING'] ?? null
        ]);
        
        if ($this->config['log_asset_requests']) {
            $this->logAssetRequest($requestPath, null, 404);
        }
    }
    
    /**
     * Serve 403 Forbidden response for permission errors
     * 
     * @param string $requestPath The requested path
     * @param string $reason Reason for permission denial
     * @return void
     */
    private function serve403($requestPath, $reason = 'Permission denied') {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        
        $response = [
            'error' => 'Access forbidden',
            'path' => $requestPath,
            'reason' => $reason,
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
        // Log permission errors with security context
        Logger::error('Asset access forbidden', [
            'path' => $requestPath,
            'reason' => $reason,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'security_event' => true
        ]);
        
        if ($this->config['log_asset_requests']) {
            $this->logAssetRequest($requestPath, null, 403);
        }
    }
    
    /**
     * Serve 500 Internal Server Error for corruption and other server errors
     * 
     * @param string $requestPath The requested path
     * @param string $reason Reason for server error
     * @param Exception|null $exception Optional exception details
     * @return void
     */
    private function serve500($requestPath, $reason = 'Internal server error', $exception = null) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        
        $response = [
            'error' => 'Internal server error',
            'path' => $requestPath,
            'reason' => $reason,
            'timestamp' => date('c')
        ];
        
        // Only include exception details in development mode
        if ($exception && env('APP_DEBUG', 'false') === 'true') {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
        // Comprehensive error logging
        $logData = [
            'path' => $requestPath,
            'reason' => $reason,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null
        ];
        
        if ($exception) {
            $logData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        Logger::error('Asset server error', $logData);
        
        if ($this->config['log_asset_requests']) {
            $this->logAssetRequest($requestPath, null, 500);
        }
    }
    
    /**
     * Handle asset serving errors with comprehensive error responses
     * 
     * @param string $requestPath The requested path
     * @param Exception $e The exception
     * @return void
     */
    private function handleAssetError($requestPath, $e) {
        // Determine appropriate error response based on exception type
        if ($e instanceof AssetPermissionException) {
            $this->serve403($requestPath, $e->getMessage());
        } elseif ($e instanceof AssetCorruptionException) {
            $this->serve500($requestPath, $e->getMessage(), $e);
        } else {
            // Generic server error
            $this->serve500($requestPath, 'Unexpected error occurred', $e);
        }
    }
    
    /**
     * Log asset request for monitoring
     * 
     * @param string $requestPath The requested path
     * @param array|null $fileInfo File information
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function logAssetRequest($requestPath, $fileInfo, $statusCode) {
        $logData = [
            'path' => $requestPath,
            'status' => $statusCode,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'timestamp' => date('c')
        ];
        
        if ($fileInfo) {
            $logData['file_size'] = $fileInfo['size'];
            $logData['mime_type'] = $this->getMimeType($fileInfo['path']);
        }
        
        Logger::info('Asset request', $logData);
    }
    
    /**
     * Get asset server statistics
     * 
     * @return array Statistics
     */
    public function getStatistics() {
        $stats = [
            'mime_types_supported' => count($this->mimeTypes),
            'compression_enabled' => $this->config['enable_compression'],
            'cache_enabled' => $this->config['enable_etag'] || $this->config['enable_last_modified'],
            'versioning_enabled' => $this->config['enable_versioning'],
            'allowed_directories' => count($this->config['allowed_directories']),
            'max_file_size' => $this->config['max_file_size'],
            'default_cache_duration' => $this->config['asset_cache_duration']
        ];
        
        if ($this->config['enable_versioning']) {
            $stats['version_cache'] = $this->getVersionCacheStats();
        }
        
        return $stats;
    }
}