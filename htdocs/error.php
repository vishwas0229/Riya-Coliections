<?php
/**
 * Error Page Handler
 * 
 * This page handles HTTP error responses and provides user-friendly error messages
 * while maintaining security by not exposing system details.
 */

$errorCode = $_GET['code'] ?? '500';
$errorMessages = [
    '400' => 'Bad Request - The request could not be understood by the server.',
    '401' => 'Unauthorized - Authentication is required to access this resource.',
    '403' => 'Forbidden - You do not have permission to access this resource.',
    '404' => 'Not Found - The requested resource could not be found.',
    '405' => 'Method Not Allowed - The request method is not supported for this resource.',
    '429' => 'Too Many Requests - Rate limit exceeded. Please try again later.',
    '500' => 'Internal Server Error - An unexpected error occurred.',
    '502' => 'Bad Gateway - The server received an invalid response from an upstream server.',
    '503' => 'Service Unavailable - The server is temporarily unavailable.',
    '504' => 'Gateway Timeout - The server did not receive a timely response from an upstream server.'
];

$message = $errorMessages[$errorCode] ?? 'An error occurred.';

// Set appropriate HTTP status code
http_response_code((int) $errorCode);

// Check if this is an API request
$isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 || 
                strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

if ($isApiRequest) {
    // Return JSON error response for API requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data' => null,
        'errors' => [
            [
                'code' => $errorCode,
                'message' => $message
            ]
        ]
    ]);
} else {
    // Return HTML error page for browser requests
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error <?php echo htmlspecialchars($errorCode); ?> - Riya Collections</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #E91E63, #F8BBD9);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-container {
                background: white;
                padding: 2rem;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
                margin: 1rem;
            }
            .error-code {
                font-size: 4rem;
                font-weight: bold;
                color: #E91E63;
                margin: 0;
            }
            .error-message {
                font-size: 1.2rem;
                color: #333;
                margin: 1rem 0;
            }
            .error-description {
                color: #666;
                margin: 1rem 0;
            }
            .back-button {
                display: inline-block;
                background: #E91E63;
                color: white;
                padding: 0.75rem 1.5rem;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 1rem;
                transition: background 0.3s;
            }
            .back-button:hover {
                background: #C2185B;
            }
            .logo {
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="logo">
                <h2 style="color: #E91E63; margin: 0;">Riya Collections</h2>
            </div>
            <h1 class="error-code"><?php echo htmlspecialchars($errorCode); ?></h1>
            <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <p class="error-description">
                <?php if ($errorCode === '404'): ?>
                    The page you're looking for might have been moved, deleted, or you entered the wrong URL.
                <?php elseif ($errorCode === '500'): ?>
                    We're experiencing some technical difficulties. Please try again later.
                <?php elseif ($errorCode === '403'): ?>
                    You don't have permission to access this resource. Please contact support if you believe this is an error.
                <?php else: ?>
                    Please try again or contact our support team if the problem persists.
                <?php endif; ?>
            </p>
            <a href="/" class="back-button">Go to Homepage</a>
        </div>
    </body>
    </html>
    <?php
}
?>