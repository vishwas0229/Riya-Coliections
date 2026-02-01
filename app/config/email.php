<?php
/**
 * Email Configuration and Service
 * 
 * This module provides email service configuration and utilities for sending
 * transactional emails including order confirmations, status updates, and notifications.
 * Compatible with the existing Node.js email templates and functionality.
 * 
 * Requirements: 9.1, 12.1, 12.2
 */

require_once __DIR__ . '/environment.php';

/**
 * Email Configuration Class
 */
class EmailConfig {
    private static $config = null;
    
    /**
     * Get email configuration
     */
    public static function getConfig() {
        if (self::$config === null) {
            self::$config = [
                'smtp' => [
                    'host' => env('SMTP_HOST', 'smtp.gmail.com'),
                    'port' => (int) env('SMTP_PORT', 587),
                    'secure' => env('SMTP_SECURE', 'false') === 'true',
                    'username' => env('SMTP_USER'),
                    'password' => env('SMTP_PASSWORD'),
                    'timeout' => 30,
                    'debug' => isDevelopment()
                ],
                'from' => [
                    'email' => env('COMPANY_EMAIL', 'orders@riyacollections.com'),
                    'name' => env('COMPANY_NAME', 'Riya Collections')
                ],
                'support' => [
                    'email' => env('SUPPORT_EMAIL', 'support@riyacollections.com'),
                    'name' => 'Riya Collections Support'
                ],
                'branding' => [
                    'company_name' => env('COMPANY_NAME', 'Riya Collections'),
                    'website_url' => env('WEBSITE_URL', 'https://riyacollections.com'),
                    'logo_url' => env('LOGO_URL', 'https://riyacollections.com/assets/logo.png'),
                    'colors' => [
                        'primary' => '#E91E63',
                        'secondary' => '#F8BBD9',
                        'accent' => '#4A4A4A',
                        'background' => '#FFFFFF',
                        'text' => '#333333'
                    ]
                ],
                'retry' => [
                    'max_attempts' => 3,
                    'delay' => 1000, // milliseconds
                    'backoff_multiplier' => 2
                ]
            ];
        }
        
        return self::$config;
    }
}

/**
 * Simple SMTP Email Service
 * Implements basic SMTP functionality without external dependencies
 */
class SMTPService {
    private $config;
    private $socket;
    
    public function __construct() {
        $this->config = EmailConfig::getConfig()['smtp'];
    }
    
    /**
     * Send email via SMTP
     */
    public function sendEmail($to, $subject, $body, $headers = []) {
        try {
            $this->connect();
            $this->authenticate();
            
            // Prepare email data
            $from = EmailConfig::getConfig()['from'];
            $messageId = $this->generateMessageId();
            
            // Build headers
            $defaultHeaders = [
                'From' => "{$from['name']} <{$from['email']}>",
                'To' => $to,
                'Subject' => $subject,
                'Date' => date('r'),
                'Message-ID' => $messageId,
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Transfer-Encoding' => '8bit'
            ];
            
            $allHeaders = array_merge($defaultHeaders, $headers);
            
            // Send email
            $this->sendCommand("MAIL FROM: <{$from['email']}>");
            $this->sendCommand("RCPT TO: <{$to}>");
            $this->sendCommand("DATA");
            
            // Send headers
            foreach ($allHeaders as $key => $value) {
                $this->sendData("{$key}: {$value}");
            }
            
            $this->sendData(""); // Empty line to separate headers from body
            $this->sendData($body);
            $this->sendData("."); // End data
            
            $this->disconnect();
            
            Logger::info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'message_id' => $messageId
            ]);
            
            return [
                'success' => true,
                'message_id' => $messageId
            ];
            
        } catch (Exception $e) {
            Logger::error('Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Connect to SMTP server
     */
    private function connect() {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $secure = $this->config['secure'];
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        if ($secure && $port == 465) {
            // SSL connection
            $this->socket = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $this->config['timeout'], STREAM_CLIENT_CONNECT, $context);
        } else {
            // Plain or STARTTLS connection
            $this->socket = stream_socket_client("{$host}:{$port}", $errno, $errstr, $this->config['timeout'], STREAM_CLIENT_CONNECT, $context);
        }
        
        if (!$this->socket) {
            throw new Exception("Failed to connect to SMTP server: {$errstr} ({$errno})");
        }
        
        $this->readResponse(); // Read welcome message
        
        // Send EHLO
        $this->sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        
        // Start TLS if needed
        if (!$secure && $port == 587) {
            $this->sendCommand("STARTTLS");
            
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to enable TLS encryption");
            }
            
            // Send EHLO again after TLS
            $this->sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }
    }
    
    /**
     * Authenticate with SMTP server
     */
    private function authenticate() {
        if (empty($this->config['username']) || empty($this->config['password'])) {
            return; // No authentication required
        }
        
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->config['username']));
        $this->sendCommand(base64_encode($this->config['password']));
    }
    
    /**
     * Send SMTP command
     */
    private function sendCommand($command) {
        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse();
    }
    
    /**
     * Send data (for email content)
     */
    private function sendData($data) {
        fwrite($this->socket, $data . "\r\n");
    }
    
    /**
     * Read SMTP response
     */
    private function readResponse() {
        $response = '';
        
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            
            // Check if this is the last line (no dash after code)
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        
        $code = (int) substr($response, 0, 3);
        
        if ($code >= 400) {
            throw new Exception("SMTP Error: {$response}");
        }
        
        return $response;
    }
    
    /**
     * Disconnect from SMTP server
     */
    private function disconnect() {
        if ($this->socket) {
            $this->sendCommand("QUIT");
            fclose($this->socket);
            $this->socket = null;
        }
    }
    
    /**
     * Generate unique message ID
     */
    private function generateMessageId() {
        $domain = parse_url(env('WEBSITE_URL', 'https://riyacollections.com'), PHP_URL_HOST) ?? 'riyacollections.com';
        return '<' . uniqid() . '.' . time() . '@' . $domain . '>';
    }
}

// Global helper functions for backward compatibility
function getEmailService() {
    static $service = null;
    if ($service === null) {
        require_once __DIR__ . '/../services/EmailService.php';
        $service = new EmailService();
    }
    return $service;
}

function sendEmail($to, $subject, $body, $headers = []) {
    return getEmailService()->sendEmail($to, $subject, $body, $headers);
}

function queueEmail($to, $subject, $body, $headers = []) {
    return getEmailService()->queueEmail($to, $subject, $body, $headers);
}