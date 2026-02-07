<?php
/**
 * Riya Collections Integrated Application - Main Entry Point
 * 
 * This is the unified entry point that handles:
 * - API requests (routed to PHP backend)
 * - Static asset serving (CSS, JS, images)
 * - Frontend SPA routing (serving main HTML for frontend routes)
 * 
 * Features:
 * - Enhanced routing with asset serving capabilities
 * - CORS and security middleware integration
 * - MIME type detection and caching headers
 * - SPA route handling for frontend navigation
 * - Comprehensive error handling and logging
 * - Performance optimizations with compression
 * 
 * Requirements: 1.1, 1.2, 1.4, 1.5, 2.1, 2.3, 2.4, 2.5, 4.1, 4.2, 4.3, 4.4, 4.5
 */

// Start performance monitoring
$startTime = microtime(true);
$startMemory = memory_get_usage(true);

// Include environment configuration first
require_once __DIR__ . '/../app/config/environment.php';

// Include core dependencies
require_once __DIR__ . '/../app/utils/Logger.php';
require_once __DIR__ . '/../app/utils/InputValidator.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/CorsMiddleware.php';
require_once __DIR__ . '/../app/middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/utils/Response.php';
require_once __DIR__ . '/../app/services/AssetServer.php';
require_once __DIR__ . '/../app/services/SPARouteHandler.php';

// Generate unique request ID for tracking
$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_', true);
$_SERVER['REQUEST_ID'] = $requestId;

// Initialize request context
$requestContext = [
    'request_id' => $requestId,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'ip' => getClientIP(),
    'timestamp' => date('c'),
    'start_time' => $startTime
];

// Log incoming request
Logger::info('Request received', $requestContext);

try {
    // Initialize integrated router with enhanced features
    $router = new IntegratedRouter();
    
    // Apply global middleware stack
    $router->applyMiddleware();
    
    // Parse and validate request
    $request = $router->parseRequest();
    
    // Determine request type and route accordingly using enhanced classification
    $requestType = $router->classifyRequestType($request['path']);
    
    // Log request classification for monitoring
    Logger::debug('Request classified', [
        'path' => $request['path'],
        'type' => $requestType,
        'method' => $request['method'],
        'request_id' => $request['id']
    ]);
    
    switch ($requestType) {
        case 'api':
            // Handle API request
            $response = $router->routeApiRequest($request);
            break;
            
        case 'asset':
            // Handle static asset request
            $response = $router->serveStaticAsset($request);
            break;
            
        case 'frontend':
        default:
            // Handle frontend SPA route
            $response = $router->serveFrontendApp($request);
            break;
    }
    
    // Log successful request completion with enhanced metrics
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    
    Logger::logRequest(
        $request['method'],
        $request['path'],
        http_response_code(),
        $executionTime
    );
    
    // Log performance metrics with enhanced context
    Logger::logPerformance('request_processing', $executionTime, [
        'memory_usage' => memory_get_usage(true) - $startMemory,
        'memory_peak' => memory_get_peak_usage(true),
        'request_id' => $requestId,
        'request_type' => $requestType,
        'path' => $request['path'],
        'method' => $request['method']
    ]);
    
} catch (RouterException $e) {
    // Handle router-specific exceptions
    Logger::error('Router exception', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'request_id' => $requestId,
        'context' => $e->getContext()
    ]);
    
    Response::error($e->getMessage(), $e->getCode());
    
} catch (ValidationException $e) {
    // Handle validation exceptions
    Logger::warning('Validation error', [
        'message' => $e->getMessage(),
        'errors' => $e->getErrors(),
        'request_id' => $requestId
    ]);
    
    Response::validationError($e->getErrors(), $e->getMessage());
    
} catch (AuthenticationException $e) {
    // Handle authentication exceptions
    Logger::warning('Authentication error', [
        'message' => $e->getMessage(),
        'request_id' => $requestId
    ]);
    
    Response::unauthorized($e->getMessage());
    
} catch (AuthorizationException $e) {
    // Handle authorization exceptions
    Logger::warning('Authorization error', [
        'message' => $e->getMessage(),
        'request_id' => $requestId
    ]);
    
    Response::forbidden($e->getMessage());
    
} catch (DatabaseException $e) {
    // Handle database exceptions
    Logger::error('Database error', [
        'message' => $e->getMessage(),
        'query' => $e->getQuery(),
        'request_id' => $requestId
    ]);
    
    Response::serverError('Database operation failed');
    
} catch (Exception $e) {
    // Handle all other exceptions
    Logger::error('Unhandled exception in main entry point', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'request_id' => $requestId
    ]);
    
    Response::serverError('Internal server error');
}

/**
 * Get client IP address with proxy support
 */
function getClientIP() {
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
 * Custom Exception Classes for Enhanced Error Handling
 */

class RouterException extends Exception {
    private $context;
    
    public function __construct($message, $code = 500, $context = []) {
        parent::__construct($message, $code);
        $this->context = $context;
    }
    
    public function getContext() {
        return $this->context;
    }
}

class ValidationException extends Exception {
    private $errors;
    
    public function __construct($message, $errors = []) {
        parent::__construct($message, 422);
        $this->errors = $errors;
    }
    
    public function getErrors() {
        return $this->errors;
    }
}

class AuthenticationException extends Exception {
    public function __construct($message = 'Authentication required') {
        parent::__construct($message, 401);
    }
}

class AuthorizationException extends Exception {
    public function __construct($message = 'Access forbidden') {
        parent::__construct($message, 403);
    }
}

class DatabaseException extends Exception {
    private $query;
    
    public function __construct($message, $query = null) {
        parent::__construct($message, 500);
        $this->query = $query;
    }
    
    public function getQuery() {
        return $this->query;
    }
}

/**
 * Integrated Router Class with Frontend and Backend Support
 * 
 * Features:
 * - API request routing to PHP controllers
 * - Static asset serving with proper MIME types and caching
 * - SPA route handling for frontend navigation
 * - Middleware stack support
 * - Route caching for performance
 * - Comprehensive error handling
 */
class IntegratedRouter {
    private $routes = [];
    private $middleware = [];
    private $routeCache = [];
    private $config;
    private $mimeTypes = [];
    
    public function __construct() {
        $this->config = [
            'cache_routes' => env('CACHE_ROUTES', 'true') === 'true',
            'cache_file' => __DIR__ . '/../storage/cache/routes.php',
            'enable_compression' => env('ENABLE_COMPRESSION', 'true') === 'true',
            'max_request_size' => (int) env('MAX_REQUEST_SIZE', 10485760), // 10MB
            'request_timeout' => (int) env('REQUEST_TIMEOUT', 30),
            'asset_cache_duration' => (int) env('ASSET_CACHE_DURATION', 86400), // 24 hours
            'enable_asset_compression' => env('ENABLE_ASSET_COMPRESSION', 'true') === 'true'
        ];
        
        $this->initializeMimeTypes();
        $this->initializeRoutes();
        $this->loadRouteCache();
    }
    
    /**
     * Initialize MIME type mappings for static assets
     */
    private function initializeMimeTypes() {
        $this->mimeTypes = [
            // Web assets
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            
            // Fonts
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            
            // Documents
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            
            // Archives
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            
            // Default
            '' => 'application/octet-stream'
        ];
    }
    
    /**
     * Check if request is for API endpoint
     */
    public function isApiRequest($path) {
        return strpos($path, '/api/') === 0;
    }
    
    /**
     * Check if request is for static asset
     */
    public function isStaticAsset($path) {
        // Check for asset directories
        $assetPaths = ['/assets/', '/uploads/', '/css/', '/js/', '/images/', '/fonts/', '/src/'];
        
        foreach ($assetPaths as $assetPath) {
            if (strpos($path, $assetPath) !== false) {
                return true;
            }
        }
        
        // Check for file extensions that indicate static assets
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return isset($this->mimeTypes[$extension]) && !empty($extension);
    }
    
    /**
     * Classify request type for enhanced routing
     * 
     * @param string $path Request path
     * @return string Request type: 'api', 'asset', 'frontend'
     */
    public function classifyRequestType($path) {
        if ($this->isApiRequest($path)) {
            return 'api';
        }
        
        if ($this->isStaticAsset($path)) {
            return 'asset';
        }
        
        return 'frontend';
    }
    
    /**
     * Check if path is a frontend SPA route using SPARouteHandler
     * 
     * @param string $path Request path
     * @return bool True if it's a frontend route
     */
    public function isFrontendRoute($path) {
        $spaHandler = new SPARouteHandler();
        return $spaHandler->isFrontendRoute($path);
    }
    
    /**
     * Apply middleware stack
     */
    public function applyMiddleware() {
        // Apply CORS middleware first
        CorsMiddleware::handle();
        
        // Apply security middleware
        SecurityMiddleware::handle();
        
        // Apply compression if enabled
        if ($this->config['enable_compression'] && extension_loaded('zlib')) {
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                ob_start('ob_gzhandler');
            }
        }
        
        // Set request timeout
        set_time_limit($this->config['request_timeout']);
    }
    
    /**
     * Parse and validate incoming request
     */
    public function parseRequest() {
        $request = [
            'id' => $_SERVER['REQUEST_ID'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'path' => $this->parsePath(),
            'query' => $_GET,
            'headers' => $this->parseHeaders(),
            'body' => null,
            'files' => $_FILES,
            'ip' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => time()
        ];
        
        // Validate request size
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ($contentLength > $this->config['max_request_size']) {
            throw new RouterException('Request too large', 413);
        }
        
        // Parse request body for POST/PUT/PATCH requests
        if (in_array($request['method'], ['POST', 'PUT', 'PATCH'])) {
            $request['body'] = $this->parseRequestBody();
        }
        
        // Validate and sanitize input for API requests
        if ($this->isApiRequest($request['path'])) {
            $request = $this->sanitizeRequest($request);
        }
        
        return $request;
    }
    
    /**
     * Parse request path with base path handling
     */
    private function parsePath() {
        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Remove base path if running in subdirectory
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        
        // Clean and normalize path
        $path = '/' . trim($path, '/');
        
        // Decode URL encoding
        $path = urldecode($path);
        
        // Validate path for security
        if (preg_match('/\.\.(\/|\\\\)/', $path)) {
            throw new RouterException('Invalid path traversal detected', 400);
        }
        
        return $path;
    }
    
    /**
     * Parse request headers
     */
    private function parseHeaders() {
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
     * Parse request body based on content type
     */
    private function parseRequestBody() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $rawBody = file_get_contents('php://input');
        
        if (empty($rawBody)) {
            return null;
        }
        
        // Parse JSON
        if (strpos($contentType, 'application/json') !== false) {
            $decoded = json_decode($rawBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException('Invalid JSON in request body', [
                    'json_error' => json_last_error_msg()
                ]);
            }
            
            return $decoded;
        }
        
        // Parse form data
        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($rawBody, $parsed);
            return $parsed;
        }
        
        // Parse multipart form data (handled by PHP automatically in $_POST)
        if (strpos($contentType, 'multipart/form-data') !== false) {
            return $_POST;
        }
        
        // Return raw body for other content types
        return $rawBody;
    }
    
    /**
     * Sanitize request data
     */
    private function sanitizeRequest($request) {
        $validator = getInputValidator();
        
        // Sanitize query parameters
        if (!empty($request['query'])) {
            $request['query'] = $validator->sanitize($request['query']);
        }
        
        // Sanitize body data
        if (!empty($request['body']) && is_array($request['body'])) {
            $request['body'] = $validator->sanitize($request['body']);
        }
        
        return $request;
    }
    
    /**
     * Route API request to appropriate controller
     */
    public function routeApiRequest($request) {
        $path = $request['path'];
        $method = $request['method'];
        
        // Find matching route
        $routeMatch = $this->findRoute($path, $method);
        
        if (!$routeMatch) {
            throw new RouterException('API endpoint not found', 404, [
                'path' => $path,
                'method' => $method,
                'available_routes' => $this->getAvailableRoutes($method)
            ]);
        }
        
        // Extract route information
        $handler = $routeMatch['handler'];
        $params = $routeMatch['params'];
        $middleware = $routeMatch['middleware'] ?? [];
        
        // Apply route-specific middleware
        foreach ($middleware as $middlewareClass) {
            $this->applyRouteMiddleware($middlewareClass, $request);
        }
        
        // Load and execute controller
        return $this->executeController($handler, $params, $request);
    }
    
    /**
     * Serve static asset using the dedicated AssetServer class
     */
    public function serveStaticAsset($request) {
        $assetServer = new AssetServer();
        $assetServer->serve($request['path']);
    }
    
    /**
     * Serve frontend application for SPA routes using dedicated SPARouteHandler
     */
    public function serveFrontendApp($request) {
        $spaHandler = new SPARouteHandler();
        $spaHandler->handleRoute($request['path'], $request);
    }
    
    /**
     * Find matching route with caching
     */
    private function findRoute($path, $method) {
        $cacheKey = $method . ':' . $path;
        
        // Check cache first
        if ($this->config['cache_routes'] && isset($this->routeCache[$cacheKey])) {
            return $this->routeCache[$cacheKey];
        }
        
        if (!isset($this->routes[$method])) {
            return null;
        }
        
        foreach ($this->routes[$method] as $pattern => $routeConfig) {
            $params = [];
            
            // Convert route pattern to regex
            $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';
            
            if (preg_match($regex, $path, $matches)) {
                // Extract parameters
                array_shift($matches); // Remove full match
                
                // Get parameter names from pattern
                preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames);
                $paramNames = $paramNames[1];
                
                // Combine parameter names with values
                for ($i = 0; $i < count($paramNames); $i++) {
                    $params[$paramNames[$i]] = $matches[$i] ?? null;
                }
                
                $result = [
                    'handler' => $routeConfig['handler'],
                    'params' => $params,
                    'middleware' => $routeConfig['middleware'] ?? []
                ];
                
                // Cache the result
                if ($this->config['cache_routes']) {
                    $this->routeCache[$cacheKey] = $result;
                }
                
                return $result;
            }
        }
        
        return null;
    }
    
    /**
     * Execute controller method
     */
    private function executeController($handler, $params, $request) {
        [$controllerName, $methodName] = $handler;
        
        // Load controller file
        $controllerFile = __DIR__ . "/../app/controllers/{$controllerName}.php";
        if (!file_exists($controllerFile)) {
            throw new RouterException("Controller file not found: {$controllerName}", 500);
        }
        
        require_once $controllerFile;
        
        if (!class_exists($controllerName)) {
            throw new RouterException("Controller class not found: {$controllerName}", 500);
        }
        
        // Instantiate controller
        $controller = new $controllerName();
        
        if (!method_exists($controller, $methodName)) {
            throw new RouterException("Controller method not found: {$controllerName}::{$methodName}", 500);
        }
        
        // Inject request and parameters into controller
        if (method_exists($controller, 'setRequest')) {
            $controller->setRequest($request);
        }
        
        if (method_exists($controller, 'setParams')) {
            $controller->setParams($params);
        }
        
        // Execute controller method
        $startTime = microtime(true);
        
        try {
            $result = call_user_func_array([$controller, $methodName], array_values($params));
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            Logger::logPerformance("controller_{$controllerName}_{$methodName}", $executionTime, [
                'controller' => $controllerName,
                'method' => $methodName,
                'params' => $params
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Controller execution failed", [
                'controller' => $controllerName,
                'method' => $methodName,
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Apply route-specific middleware
     */
    private function applyRouteMiddleware($middlewareClass, $request) {
        $middlewareFile = __DIR__ . "/../app/middleware/{$middlewareClass}.php";
        
        if (!file_exists($middlewareFile)) {
            throw new RouterException("Middleware file not found: {$middlewareClass}", 500);
        }
        
        require_once $middlewareFile;
        
        if (!class_exists($middlewareClass)) {
            throw new RouterException("Middleware class not found: {$middlewareClass}", 500);
        }
        
        // Execute middleware
        $middleware = new $middlewareClass();
        
        if (method_exists($middleware, 'handle')) {
            $middleware->handle($request);
        } elseif (method_exists($middlewareClass, 'handle')) {
            $middlewareClass::handle($request);
        } else {
            throw new RouterException("Middleware handle method not found: {$middlewareClass}", 500);
        }
    }
    
    /**
     * Get available routes for error reporting
     */
    private function getAvailableRoutes($method) {
        if (!isset($this->routes[$method])) {
            return [];
        }
        
        return array_keys($this->routes[$method]);
    }
    
    /**
     * Load route cache from file
     */
    private function loadRouteCache() {
        if (!$this->config['cache_routes']) {
            return;
        }
        
        $cacheFile = $this->config['cache_file'];
        
        if (file_exists($cacheFile)) {
            $this->routeCache = include $cacheFile;
        }
    }
    
    /**
     * Save route cache to file
     */
    private function saveRouteCache() {
        if (!$this->config['cache_routes'] || empty($this->routeCache)) {
            return;
        }
        
        $cacheFile = $this->config['cache_file'];
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $content = "<?php\nreturn " . var_export($this->routeCache, true) . ";\n";
        file_put_contents($cacheFile, $content, LOCK_EX);
    }
    
    /**
     * Initialize all API routes with middleware configuration
     */
    private function initializeRoutes() {
        // Authentication routes (no auth required)
        $this->routes['POST']['/api/auth/register'] = [
            'handler' => ['AuthController', 'register'],
            'middleware' => []
        ];
        $this->routes['POST']['/api/auth/login'] = [
            'handler' => ['AuthController', 'login'],
            'middleware' => []
        ];
        $this->routes['POST']['/api/auth/refresh'] = [
            'handler' => ['AuthController', 'refresh'],
            'middleware' => []
        ];
        $this->routes['POST']['/api/auth/forgot-password'] = [
            'handler' => ['AuthController', 'forgotPassword'],
            'middleware' => []
        ];
        $this->routes['POST']['/api/auth/reset-password'] = [
            'handler' => ['AuthController', 'resetPassword'],
            'middleware' => []
        ];
        
        // Protected authentication routes
        $this->routes['GET']['/api/auth/profile'] = [
            'handler' => ['AuthController', 'getProfile'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['PUT']['/api/auth/profile'] = [
            'handler' => ['AuthController', 'updateProfile'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/auth/change-password'] = [
            'handler' => ['AuthController', 'changePassword'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/auth/logout'] = [
            'handler' => ['AuthController', 'logout'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['GET']['/api/auth/sessions'] = [
            'handler' => ['AuthController', 'getSessions'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['GET']['/api/auth/verify'] = [
            'handler' => ['AuthController', 'verifyToken'],
            'middleware' => ['AuthMiddleware']
        ];
        
        // Public product routes
        $this->routes['GET']['/api/products'] = [
            'handler' => ['ProductController', 'getAll'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/products/{id}'] = [
            'handler' => ['ProductController', 'getById'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/products/search'] = [
            'handler' => ['ProductController', 'search'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/products/featured'] = [
            'handler' => ['ProductController', 'getFeatured'],
            'middleware' => []
        ];
        
        // Public category routes
        $this->routes['GET']['/api/categories'] = [
            'handler' => ['ProductController', 'getCategories'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/categories/{id}'] = [
            'handler' => ['ProductController', 'getCategoryById'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/categories/{id}/products'] = [
            'handler' => ['ProductController', 'getCategoryProducts'],
            'middleware' => []
        ];
        
        // Admin product routes
        $this->routes['POST']['/api/admin/products'] = [
            'handler' => ['ProductController', 'create'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['PUT']['/api/admin/products/{id}'] = [
            'handler' => ['ProductController', 'update'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['DELETE']['/api/admin/products/{id}'] = [
            'handler' => ['ProductController', 'delete'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/products/{id}/images'] = [
            'handler' => ['ProductController', 'uploadImages'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['PUT']['/api/admin/products/{id}/stock'] = [
            'handler' => ['ProductController', 'updateStock'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/products/stats'] = [
            'handler' => ['ProductController', 'getProductStats'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/products/low-stock'] = [
            'handler' => ['ProductController', 'getLowStockProducts'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        
        // Admin category routes
        $this->routes['POST']['/api/admin/categories'] = [
            'handler' => ['ProductController', 'createCategory'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['PUT']['/api/admin/categories/{id}'] = [
            'handler' => ['ProductController', 'updateCategory'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['DELETE']['/api/admin/categories/{id}'] = [
            'handler' => ['ProductController', 'deleteCategory'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/categories/stats'] = [
            'handler' => ['ProductController', 'getCategoryStats'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        
        // Protected order routes
        $this->routes['GET']['/api/orders'] = [
            'handler' => ['OrderController', 'getUserOrders'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['GET']['/api/orders/{id}'] = [
            'handler' => ['OrderController', 'getById'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/orders'] = [
            'handler' => ['OrderController', 'create'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['PUT']['/api/orders/{id}/status'] = [
            'handler' => ['OrderController', 'updateStatus'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        
        // Payment routes
        $this->routes['POST']['/api/payments/razorpay/create'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/payments/razorpay/verify'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/payments/cod'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/payments/webhook/razorpay'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => [] // Webhooks use signature verification
        ];
        $this->routes['GET']['/api/payments/{id}'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['GET']['/api/payments/methods'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/payments/statistics'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['PUT']['/api/payments/cod/confirm/{id}'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/payments/test'] = [
            'handler' => ['PaymentController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        
        // Protected address routes
        $this->routes['GET']['/api/addresses'] = [
            'handler' => ['AddressController', 'getUserAddresses'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/addresses'] = [
            'handler' => ['AddressController', 'create'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['PUT']['/api/addresses/{id}'] = [
            'handler' => ['AddressController', 'update'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['DELETE']['/api/addresses/{id}'] = [
            'handler' => ['AddressController', 'delete'],
            'middleware' => ['AuthMiddleware']
        ];
        
        // Admin authentication routes
        $this->routes['POST']['/api/admin/login'] = [
            'handler' => ['AuthController', 'adminLogin'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/admin/profile'] = [
            'handler' => ['AuthController', 'getAdminProfile'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['PUT']['/api/admin/profile'] = [
            'handler' => ['AuthController', 'updateAdminProfile'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/change-password'] = [
            'handler' => ['AuthController', 'adminChangePassword'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/logout'] = [
            'handler' => ['AuthController', 'adminLogout'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/security-log'] = [
            'handler' => ['AuthController', 'getAdminSecurityLog'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        
        // Admin management routes
        $this->routes['GET']['/api/admin/dashboard'] = [
            'handler' => ['AdminController', 'getDashboard'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/orders'] = [
            'handler' => ['AdminController', 'getAllOrders'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/users'] = [
            'handler' => ['AdminController', 'getAllUsers'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        
        // Health check and utility routes
        $this->routes['GET']['/api/health'] = [
            'handler' => ['HealthController', 'check'],
            'middleware' => []
        ];
        $this->routes['GET']['/api/health/detailed'] = [
            'handler' => ['HealthController', 'detailedCheck'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/health/performance'] = [
            'handler' => ['HealthController', 'performance'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/health/analyze'] = [
            'handler' => ['HealthController', 'analyzeDatabase'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/health/optimize'] = [
            'handler' => ['HealthController', 'optimizeDatabase'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/health/clear-cache'] = [
            'handler' => ['HealthController', 'clearCaches'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/health/monitor'] = [
            'handler' => ['HealthController', 'monitor'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/health/history'] = [
            'handler' => ['HealthController', 'history'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/health/uptime'] = [
            'handler' => ['HealthController', 'uptime'],
            'middleware' => []
        ];
        
        // Polling routes for real-time updates
        $this->routes['GET']['/api/polling/updates'] = [
            'handler' => ['PollingController', 'getUpdates'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['GET']['/api/polling/orders/{id}/updates'] = [
            'handler' => ['PollingController', 'getOrderUpdates'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/polling/notifications/read'] = [
            'handler' => ['PollingController', 'markNotificationsRead'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['POST']['/api/polling/notifications'] = [
            'handler' => ['PollingController', 'createNotification'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/polling/config'] = [
            'handler' => ['PollingController', 'getPollingConfig'],
            'middleware' => ['AuthMiddleware']
        ];
        $this->routes['GET']['/api/polling/health'] = [
            'handler' => ['PollingController', 'healthCheck'],
            'middleware' => []
        ];
        
        // API documentation route
        $this->routes['GET']['/api/docs'] = [
            'handler' => ['ApiController', 'documentation'],
            'middleware' => []
        ];
        
        // Frontend configuration endpoint
        $this->routes['GET']['/api/config'] = [
            'handler' => ['ConfigController', 'getFrontendConfig'],
            'middleware' => []
        ];
        
        // API testing utilities
        $this->routes['GET']['/api/test'] = [
            'handler' => ['ApiController', 'testInterface'],
            'middleware' => []
        ];
        $this->routes['POST']['/api/validate'] = [
            'handler' => ['ApiController', 'validateRequest'],
            'middleware' => []
        ];
        $this->routes['POST']['/api/test/execute'] = [
            'handler' => ['ApiController', 'executeTest'],
            'middleware' => []
        ];
        
        // Postman collection download
        $this->routes['GET']['/api/postman-collection'] = [
            'handler' => ['ApiController', 'postmanCollection'],
            'middleware' => []
        ];
        
        // Backup and recovery routes (admin only)
        $this->routes['GET']['/api/admin/backup/list'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/backup/info/{id}'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/backup/recovery-options/{id}'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/backup/schedule'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['GET']['/api/admin/backup/status'] = [
            'handler' => ['BackupController', 'getSystemStatus'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/backup/create'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/backup/restore/{id}'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/backup/test-restore/{id}'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/backup/schedule'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['POST']['/api/admin/backup/run-scheduled'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        $this->routes['DELETE']['/api/admin/backup/delete/{id}'] = [
            'handler' => ['BackupController', 'handleRequest'],
            'middleware' => ['AuthMiddleware', 'AdminMiddleware']
        ];
        
        // File serving routes
        $this->routes['GET']['/uploads/{path}'] = [
            'handler' => ['FileController', 'serve'],
            'middleware' => []
        ];
    }
    
    /**
     * Destructor to save route cache
     */
    public function __destruct() {
        $this->saveRouteCache();
    }
}