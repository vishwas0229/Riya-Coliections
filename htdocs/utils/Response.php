<?php
/**
 * Response Utility Class
 * 
 * This utility provides standardized JSON response formatting for the API,
 * ensuring consistent response structure across all endpoints and maintaining
 * compatibility with the existing frontend application.
 * 
 * Requirements: 4.2, 13.1
 */

/**
 * API Response Utility
 */
class Response {
    /**
     * Send JSON response
     */
    public static function json($data, $statusCode = 200, $headers = []) {
        // Set HTTP status code (only if not in CLI mode)
        if (php_sapi_name() !== 'cli') {
            http_response_code($statusCode);
        }
        
        // Set default headers
        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        // Send headers (only if not in CLI mode and headers not sent)
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            foreach ($allHeaders as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        
        // Encode and send JSON response
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            Logger::error('JSON encoding failed', [
                'data' => $data,
                'json_error' => json_last_error_msg()
            ]);
            
            // Fallback error response
            if (php_sapi_name() !== 'cli') {
                http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error - JSON encoding failed',
                'data' => null,
                'errors' => null
            ]);
        } else {
            echo $json;
        }
        
        // Only exit if not in CLI mode (for testing)
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }
    
    /**
     * Send success response
     */
    public static function success($message = 'Success', $data = null, $statusCode = 200) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null
        ];
        
        // Add pagination if data contains it
        if (is_array($data) && isset($data['pagination'])) {
            $response['pagination'] = $data['pagination'];
            $response['data'] = $data['items'] ?? $data['data'] ?? $data;
        }
        
        self::json($response, $statusCode);
    }
    
    /**
     * Send error response
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = null) {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors
        ];
        
        // Log error for debugging
        Logger::error('API error response', [
            'message' => $message,
            'status_code' => $statusCode,
            'errors' => $errors,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ]);
        
        self::json($response, $statusCode);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        // Format errors array
        $formattedErrors = [];
        
        if (is_array($errors)) {
            foreach ($errors as $field => $error) {
                if (is_string($error)) {
                    $formattedErrors[] = [
                        'field' => $field,
                        'message' => $error
                    ];
                } elseif (is_array($error)) {
                    foreach ($error as $errorMessage) {
                        $formattedErrors[] = [
                            'field' => $field,
                            'message' => $errorMessage
                        ];
                    }
                }
            }
        } elseif (is_string($errors)) {
            $formattedErrors[] = [
                'field' => 'general',
                'message' => $errors
            ];
        }
        
        self::error($message, 422, $formattedErrors);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send method not allowed response
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, 405);
    }
    
    /**
     * Send rate limit exceeded response
     */
    public static function rateLimitExceeded($message = 'Rate limit exceeded') {
        $headers = [
            'Retry-After' => '900' // 15 minutes
        ];
        
        self::json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => null
        ], 429, $headers);
    }
    
    /**
     * Send server error response
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($items, $pagination, $message = 'Data retrieved successfully') {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $items,
            'pagination' => $pagination,
            'errors' => null
        ];
        
        self::json($response);
    }
    
    /**
     * Send created response
     */
    public static function created($data = null, $message = 'Resource created successfully') {
        self::success($message, $data, 201);
    }
    
    /**
     * Send updated response
     */
    public static function updated($data = null, $message = 'Resource updated successfully') {
        self::success($message, $data, 200);
    }
    
    /**
     * Send deleted response
     */
    public static function deleted($message = 'Resource deleted successfully') {
        self::success($message, null, 200);
    }
    
    /**
     * Send no content response
     */
    public static function noContent() {
        if (php_sapi_name() !== 'cli') {
            http_response_code(204);
            exit;
        }
    }
    
    /**
     * Create pagination metadata
     */
    public static function createPagination($currentPage, $perPage, $totalItems, $totalPages = null) {
        if ($totalPages === null) {
            $totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 1;
        }
        
        return [
            'current_page' => (int) $currentPage,
            'per_page' => (int) $perPage,
            'total_items' => (int) $totalItems,
            'total_pages' => (int) $totalPages,
            'has_next_page' => $currentPage < $totalPages,
            'has_prev_page' => $currentPage > 1,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
            'prev_page' => $currentPage > 1 ? $currentPage - 1 : null
        ];
    }
    
    /**
     * Format currency for response
     */
    public static function formatCurrency($amount, $currency = 'INR') {
        return [
            'amount' => (float) $amount,
            'formatted' => '₹' . number_format($amount, 2),
            'currency' => $currency
        ];
    }
    
    /**
     * Format date for response
     */
    public static function formatDate($date, $format = 'Y-m-d H:i:s') {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        return [
            'date' => $date->format($format),
            'timestamp' => $date->getTimestamp(),
            'iso' => $date->format('c'),
            'human' => $date->format('F j, Y g:i A')
        ];
    }
    
    /**
     * Sanitize data for response
     */
    public static function sanitizeData($data) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                // Check if the key contains sensitive information
                if (is_string($key) && preg_match('/password|secret|token|key/i', $key)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = self::sanitizeData($value);
                }
            }
            return $sanitized;
        }
        
        return $data;
    }
    
    /**
     * Handle CORS preflight request
     */
    public static function handlePreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if (php_sapi_name() !== 'cli') {
                http_response_code(200);
            }
            
            $allowedOrigins = SecurityConfig::getConfig()['cors']['allowed_origins'];
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                if (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins)) {
                    header("Access-Control-Allow-Origin: {$origin}");
                }
                
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
            }
            
            if (php_sapi_name() !== 'cli') {
                exit;
            }
        }
    }
    
    /**
     * Send file download response
     */
    public static function download($filePath, $filename = null, $mimeType = null) {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
            return;
        }
        
        $filename = $filename ?: basename($filePath);
        $mimeType = $mimeType ?: mime_content_type($filePath);
        
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        readfile($filePath);
        
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }
    
    /**
     * Send image response with caching
     */
    public static function image($filePath, $maxAge = 86400) {
        if (!file_exists($filePath)) {
            self::notFound('Image not found');
            return;
        }
        
        $mimeType = mime_content_type($filePath);
        $lastModified = filemtime($filePath);
        $etag = md5_file($filePath);
        
        // Check if client has cached version
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        
        if (($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified) ||
            ($ifNoneMatch && $ifNoneMatch === $etag)) {
            if (php_sapi_name() !== 'cli') {
                http_response_code(304);
                exit;
            }
            return;
        }
        
        // Set caching headers (only if not in CLI mode)
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($filePath));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
            header('ETag: ' . $etag);
            header('Cache-Control: public, max-age=' . $maxAge);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        }
        
        readfile($filePath);
        
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }
}