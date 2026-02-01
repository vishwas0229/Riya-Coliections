<?php
/**
 * SPA Route Handler
 * 
 * Handles frontend single-page application routing by serving the main HTML file
 * for frontend routes while maintaining proper separation from API and static asset routes.
 * 
 * Features:
 * - Frontend route detection and validation
 * - Main HTML serving for SPA routes
 * - Browser refresh support on frontend routes
 * - SEO-friendly meta tag injection
 * - Route data injection for client-side routing
 * - Comprehensive error handling for missing routes
 * 
 * Requirements: 4.2, 4.4, 4.5
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class SPARouteHandler {
    private $config;
    private $frontendRoutes;
    private $frontendPatterns;
    
    public function __construct() {
        $this->config = [
            'index_path' => __DIR__ . '/../../public/index.html',
            'not_found_path' => __DIR__ . '/../../public/pages/404.html',
            'enable_route_injection' => env('ENABLE_ROUTE_INJECTION', 'true') === 'true',
            'enable_seo_tags' => env('ENABLE_SEO_TAGS', 'true') === 'true',
            'base_url' => $this->getBaseUrl()
        ];
        
        $this->initializeFrontendRoutes();
    }
    
    /**
     * Initialize known frontend routes and patterns
     */
    private function initializeFrontendRoutes() {
        // Exact frontend routes
        $this->frontendRoutes = [
            '/',
            '/home',
            '/products',
            '/categories',
            '/about',
            '/contact',
            '/login',
            '/register',
            '/profile',
            '/cart',
            '/checkout',
            '/orders',
            '/wishlist',
            '/search'
        ];
        
        // Dynamic frontend route patterns
        $this->frontendPatterns = [
            '/^\/products\/\d+$/',           // /products/123
            '/^\/categories\/\d+$/',         // /categories/1
            '/^\/orders\/\d+$/',             // /orders/456
            '/^\/pages\/[\w-]+$/',           // /pages/about
            '/^\/user\/[\w-]+$/',            // /user/profile
            '/^\/search\/.*$/'               // /search/query
        ];
    }
    
    /**
     * Handle a frontend route request
     * 
     * @param string $path Request path
     * @param array $request Full request information (optional)
     * @return void
     */
    public function handleRoute(string $path, array $request = []): void {
        // Validate that this is indeed a frontend route
        if (!$this->isFrontendRoute($path)) {
            $this->serve404ForFrontend($path);
            return;
        }
        
        // Ensure request array has required fields
        if (empty($request)) {
            $request = $this->buildRequestFromGlobals($path);
        }
        
        // Set comprehensive headers for SPA HTML
        $this->setSPAHeaders($request);
        
        // Read and potentially modify HTML content for SPA routing
        $htmlContent = $this->prepareSPAContent($request);
        
        // Serve the content
        echo $htmlContent;
        
        // Log SPA route serving with additional context
        Logger::info('SPA route served', [
            'path' => $path,
            'request_id' => $request['id'] ?? uniqid('spa_', true),
            'user_agent' => $request['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'route_type' => 'frontend'
        ]);
    }
    
    /**
     * Check if path is an API route
     * 
     * @param string $path Request path
     * @return bool True if it's an API route
     */
    public function isAPIRoute(string $path): bool {
        return strpos($path, '/api/') === 0;
    }
    
    /**
     * Check if path is a frontend SPA route
     * 
     * @param string $path Request path
     * @return bool True if it's a frontend route
     */
    public function isFrontendRoute(string $path): bool {
        // Exclude API and static asset paths
        if ($this->isAPIRoute($path) || $this->isStaticAsset($path)) {
            return false;
        }
        
        // Check exact matches
        if (in_array($path, $this->frontendRoutes)) {
            return true;
        }
        
        // Check pattern matches for dynamic routes
        foreach ($this->frontendPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Serve the main HTML file for SPA routes
     * 
     * @param array $request Request information (optional)
     * @return void
     */
    public function serveMainHTML(array $request = []): void {
        $indexPath = $this->config['index_path'];
        
        if (!file_exists($indexPath)) {
            throw new Exception('Frontend application not found at: ' . $indexPath, 500);
        }
        
        // Ensure request array has required fields
        if (empty($request)) {
            $request = $this->buildRequestFromGlobals('/');
        }
        
        // Set headers and serve content
        $this->setSPAHeaders($request);
        $htmlContent = $this->prepareSPAContent($request);
        echo $htmlContent;
        
        Logger::info('Main HTML served', [
            'path' => $request['path'] ?? '/',
            'request_id' => $request['id'] ?? uniqid('html_', true)
        ]);
    }
    
    /**
     * Check if path is for a static asset
     * 
     * @param string $path Request path
     * @return bool True if it's a static asset
     */
    private function isStaticAsset(string $path): bool {
        // Check for asset directories
        $assetPaths = ['/assets/', '/uploads/', '/css/', '/js/', '/images/', '/fonts/', '/src/'];
        
        foreach ($assetPaths as $assetPath) {
            if (strpos($path, $assetPath) !== false) {
                return true;
            }
        }
        
        // Check for file extensions that indicate static assets
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $assetExtensions = [
            'css', 'js', 'json', 'html', 'htm', 'xml',
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
            'woff', 'woff2', 'ttf', 'eot', 'otf',
            'pdf', 'txt', 'zip', 'tar', 'gz'
        ];
        
        return in_array(strtolower($extension), $assetExtensions) && !empty($extension);
    }
    
    /**
     * Set appropriate headers for SPA HTML responses
     * 
     * @param array $request Request information
     */
    private function setSPAHeaders(array $request): void {
        // Content type
        header('Content-Type: text/html; charset=utf-8');
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Cache control for SPA routes (no caching for HTML)
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // CORS headers if needed
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $allowedOrigins = explode(',', env('ALLOWED_ORIGINS', ''));
            if (in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins) || env('ENVIRONMENT') === 'development') {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
                header('Access-Control-Allow-Credentials: true');
            }
        }
        
        // Performance hints for preloading critical resources
        header('Link: </assets/css/main.css>; rel=preload; as=style');
        header('Link: </assets/js/main.js>; rel=preload; as=script');
        
        // Additional performance headers
        header('X-DNS-Prefetch-Control: on');
    }
    
    /**
     * Prepare SPA content with potential modifications
     * 
     * @param array $request Request information
     * @return string Modified HTML content
     */
    private function prepareSPAContent(array $request): string {
        $indexPath = $this->config['index_path'];
        $content = file_get_contents($indexPath);
        
        if ($content === false) {
            throw new Exception('Failed to read frontend application file: ' . $indexPath, 500);
        }
        
        // Inject route information for client-side routing if enabled
        if ($this->config['enable_route_injection']) {
            $content = $this->injectRouteData($content, $request);
        }
        
        // Add SEO-friendly meta tags if enabled
        if ($this->config['enable_seo_tags']) {
            $content = $this->injectSEOTags($content, $request);
        }
        
        return $content;
    }
    
    /**
     * Inject route data for client-side routing
     * 
     * @param string $content HTML content
     * @param array $request Request information
     * @return string Modified HTML content
     */
    private function injectRouteData(string $content, array $request): string {
        $routeData = [
            'currentPath' => $request['path'] ?? '/',
            'requestId' => $request['id'] ?? uniqid('route_', true),
            'timestamp' => time(),
            'baseUrl' => $this->config['base_url'],
            'environment' => env('ENVIRONMENT', 'production')
        ];
        
        $routeScript = '<script>window.__ROUTE_DATA__ = ' . json_encode($routeData, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
        
        // Inject before closing head tag
        $content = str_replace('</head>', $routeScript . "\n</head>", $content);
        
        return $content;
    }
    
    /**
     * Inject SEO-friendly meta tags
     * 
     * @param string $content HTML content
     * @param array $request Request information
     * @return string Modified HTML content
     */
    private function injectSEOTags(string $content, array $request): string {
        $path = $request['path'] ?? '/';
        
        // Add canonical URL for SEO
        $canonicalUrl = $this->config['base_url'] . ltrim($path, '/');
        $canonicalTag = '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '">';
        
        // Add Open Graph tags for better social sharing
        $ogTags = [
            '<meta property="og:url" content="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '">',
            '<meta property="og:type" content="website">',
            '<meta property="og:site_name" content="Riya Collections">'
        ];
        
        // Add route-specific meta tags
        $routeMetaTags = $this->getRouteSpecificMetaTags($path);
        
        // Combine all meta tags
        $allMetaTags = array_merge([$canonicalTag], $ogTags, $routeMetaTags);
        $metaTagsString = implode("\n", $allMetaTags);
        
        // Inject before closing head tag
        $content = str_replace('</head>', $metaTagsString . "\n</head>", $content);
        
        return $content;
    }
    
    /**
     * Get route-specific meta tags for SEO
     * 
     * @param string $path Request path
     * @return array Array of meta tag strings
     */
    private function getRouteSpecificMetaTags(string $path): array {
        $metaTags = [];
        
        // Define route-specific meta information
        $routeMetaData = [
            '/' => [
                'title' => 'Riya Collections - Premium Fashion & Accessories',
                'description' => 'Discover premium fashion and accessories at Riya Collections. Shop the latest trends in clothing, jewelry, and lifestyle products.',
                'keywords' => 'fashion, accessories, clothing, jewelry, premium, collections'
            ],
            '/products' => [
                'title' => 'Products - Riya Collections',
                'description' => 'Browse our extensive collection of premium products including fashion, accessories, and lifestyle items.',
                'keywords' => 'products, fashion, accessories, shop, buy'
            ],
            '/categories' => [
                'title' => 'Categories - Riya Collections',
                'description' => 'Explore our product categories to find exactly what you\'re looking for.',
                'keywords' => 'categories, fashion categories, product types'
            ],
            '/about' => [
                'title' => 'About Us - Riya Collections',
                'description' => 'Learn about Riya Collections, our mission, and commitment to premium fashion and customer satisfaction.',
                'keywords' => 'about, company, mission, fashion, premium'
            ],
            '/contact' => [
                'title' => 'Contact Us - Riya Collections',
                'description' => 'Get in touch with Riya Collections. Find our contact information and reach out for support or inquiries.',
                'keywords' => 'contact, support, customer service, help'
            ]
        ];
        
        // Check for exact route matches
        if (isset($routeMetaData[$path])) {
            $meta = $routeMetaData[$path];
            
            $metaTags[] = '<meta name="description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">';
            $metaTags[] = '<meta name="keywords" content="' . htmlspecialchars($meta['keywords'], ENT_QUOTES, 'UTF-8') . '">';
            $metaTags[] = '<meta property="og:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '">';
            $metaTags[] = '<meta property="og:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">';
        }
        
        // Handle dynamic routes
        if (preg_match('/^\/products\/(\d+)$/', $path, $matches)) {
            $productId = $matches[1];
            $metaTags[] = '<meta name="description" content="View product details for item #' . htmlspecialchars($productId, ENT_QUOTES, 'UTF-8') . ' at Riya Collections.">';
            $metaTags[] = '<meta property="og:title" content="Product #' . htmlspecialchars($productId, ENT_QUOTES, 'UTF-8') . ' - Riya Collections">';
        }
        
        if (preg_match('/^\/categories\/(\d+)$/', $path, $matches)) {
            $categoryId = $matches[1];
            $metaTags[] = '<meta name="description" content="Browse products in category #' . htmlspecialchars($categoryId, ENT_QUOTES, 'UTF-8') . ' at Riya Collections.">';
            $metaTags[] = '<meta property="og:title" content="Category #' . htmlspecialchars($categoryId, ENT_QUOTES, 'UTF-8') . ' - Riya Collections">';
        }
        
        return $metaTags;
    }
    
    /**
     * Serve 404 response for unrecognized frontend routes
     * 
     * @param string $path Requested path
     */
    private function serve404ForFrontend(string $path): void {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        
        // Try to serve a custom 404 page if it exists
        $notFoundPath = $this->config['not_found_path'];
        
        if (file_exists($notFoundPath)) {
            readfile($notFoundPath);
        } else {
            // Fallback 404 content
            echo $this->getFallback404Content();
        }
        
        // Log 404 for frontend routes
        Logger::warning('Frontend route not found', [
            'path' => $path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Get fallback 404 HTML content
     * 
     * @return string HTML content for 404 page
     */
    private function getFallback404Content(): string {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - Riya Collections</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            text-align: center; 
            padding: 50px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container { 
            max-width: 500px; 
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .error-code { 
            font-size: 72px; 
            font-weight: bold; 
            color: #fff;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .error-message { 
            font-size: 24px; 
            margin: 20px 0;
            font-weight: 300;
        }
        .error-description { 
            color: rgba(255, 255, 255, 0.8); 
            margin: 20px 0;
            line-height: 1.6;
        }
        .back-link { 
            display: inline-block; 
            padding: 12px 24px; 
            background: rgba(255, 255, 255, 0.2);
            color: white; 
            text-decoration: none; 
            border-radius: 25px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 20px;
        }
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <div class="error-message">Page Not Found</div>
        <div class="error-description">
            The page you are looking for does not exist or has been moved.
        </div>
        <a href="/" class="back-link">Return Home</a>
    </div>
</body>
</html>';
    }
    
    /**
     * Build request array from PHP globals
     * 
     * @param string $path Request path
     * @return array Request information
     */
    private function buildRequestFromGlobals(string $path): array {
        return [
            'id' => $_SERVER['REQUEST_ID'] ?? uniqid('req_', true),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? $path,
            'path' => $path,
            'query' => $_GET,
            'headers' => $this->parseHeaders(),
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => time()
        ];
    }
    
    /**
     * Parse request headers from PHP globals
     * 
     * @return array Headers array
     */
    private function parseHeaders(): array {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = $value;
            }
        }
        
        // Add content type if present
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        return $headers;
    }
    
    /**
     * Get client IP address with proxy support
     * 
     * @return string Client IP address
     */
    private function getClientIP(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get base URL for the application
     * 
     * @return string Base URL
     */
    private function getBaseUrl(): string {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        
        return $protocol . '://' . $host . ($basePath !== '/' ? $basePath : '');
    }
}