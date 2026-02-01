<?php
/**
 * Custom Error Page for Riya Collections
 * 
 * This page handles HTTP error responses with user-friendly messages
 */

// Get error code from query parameter or default to 500
$errorCode = isset($_GET['code']) ? (int)$_GET['code'] : 500;

// Define error messages
$errorMessages = [
    400 => [
        'title' => 'Bad Request',
        'message' => 'The request could not be understood by the server.',
        'description' => 'Please check your request and try again.'
    ],
    401 => [
        'title' => 'Unauthorized',
        'message' => 'Authentication is required to access this resource.',
        'description' => 'Please log in and try again.'
    ],
    403 => [
        'title' => 'Forbidden',
        'message' => 'You do not have permission to access this resource.',
        'description' => 'Contact support if you believe this is an error.'
    ],
    404 => [
        'title' => 'Page Not Found',
        'message' => 'The requested page could not be found.',
        'description' => 'The page may have been moved or deleted.'
    ],
    500 => [
        'title' => 'Internal Server Error',
        'message' => 'An unexpected error occurred on the server.',
        'description' => 'Please try again later or contact support if the problem persists.'
    ],
    503 => [
        'title' => 'Service Unavailable',
        'message' => 'The service is temporarily unavailable.',
        'description' => 'Please try again in a few minutes.'
    ]
];

// Get error details or use default
$error = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : $errorMessages[500];

// Set appropriate HTTP status code
http_response_code($errorCode);

// Check if this is an API request
$isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 || 
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isApiRequest) {
    // Return JSON response for API requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $errorCode,
            'title' => $error['title'],
            'message' => $error['message']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    exit;
}

// Return HTML response for web requests
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $errorCode; ?> - <?php echo htmlspecialchars($error['title']); ?> | Riya Collections</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .error-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            margin: 2rem;
        }
        
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 1rem;
            line-height: 1;
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .error-message {
            font-size: 1.1rem;
            color: #4a5568;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        
        .error-description {
            font-size: 1rem;
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
            transform: translateY(-1px);
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: #667eea;
            border-radius: 50%;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        @media (max-width: 480px) {
            .error-container {
                padding: 2rem;
            }
            
            .error-code {
                font-size: 4rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">RC</div>
        
        <div class="error-code"><?php echo $errorCode; ?></div>
        
        <h1 class="error-title"><?php echo htmlspecialchars($error['title']); ?></h1>
        
        <p class="error-message"><?php echo htmlspecialchars($error['message']); ?></p>
        
        <p class="error-description"><?php echo htmlspecialchars($error['description']); ?></p>
        
        <div class="action-buttons">
            <a href="/" class="btn btn-primary">Go Home</a>
            <button onclick="history.back()" class="btn btn-secondary">Go Back</button>
        </div>
    </div>
    
    <script>
        // Auto-refresh for 503 errors (service unavailable)
        <?php if ($errorCode === 503): ?>
        setTimeout(function() {
            window.location.reload();
        }, 30000); // Refresh after 30 seconds
        <?php endif; ?>
    </script>
</body>
</html>