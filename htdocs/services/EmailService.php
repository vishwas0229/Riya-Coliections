<?php
/**
 * Email Service
 * 
 * Comprehensive email service that handles SMTP integration, email templates,
 * and transactional email sending for the PHP backend. Supports multiple
 * email providers and template systems.
 * 
 * Requirements: 9.1
 */

require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

class EmailService {
    private $config;
    private $db;
    private $templatePath;
    private $defaultFromEmail;
    private $defaultFromName;
    
    // Email types
    const TYPE_ORDER_CONFIRMATION = 'order_confirmation';
    const TYPE_PAYMENT_CONFIRMATION = 'payment_confirmation';
    const TYPE_USER_REGISTRATION = 'user_registration';
    const TYPE_PASSWORD_RESET = 'password_reset';
    const TYPE_ORDER_STATUS_UPDATE = 'order_status_update';
    const TYPE_SHIPPING_NOTIFICATION = 'shipping_notification';
    const TYPE_DELIVERY_CONFIRMATION = 'delivery_confirmation';
    
    // Email status
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_QUEUED = 'queued';
    
    public function __construct() {
        $this->config = getEmailConfig();
        $this->db = Database::getInstance();
        $this->templatePath = __DIR__ . '/../templates/email/';
        $this->defaultFromEmail = $this->config['from_email'];
        $this->defaultFromName = $this->config['from_name'];
        
        // Ensure template directory exists
        if (!is_dir($this->templatePath)) {
            mkdir($this->templatePath, 0755, true);
        }
    }
    
    /**
     * Send email with template
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $template Template name
     * @param array $data Template data
     * @param array $options Additional options
     * @return bool Success status
     */
    public function sendEmail($to, $subject, $template, $data = [], $options = []) {
        try {
            // Validate email address
            if (!$this->isValidEmail($to)) {
                throw new Exception("Invalid email address: {$to}");
            }
            
            // Generate email content from template
            $content = $this->renderTemplate($template, $data);
            
            // Prepare email data
            $emailData = [
                'to' => $to,
                'from_email' => $options['from_email'] ?? $this->defaultFromEmail,
                'from_name' => $options['from_name'] ?? $this->defaultFromName,
                'subject' => $subject,
                'html_content' => $content['html'],
                'text_content' => $content['text'],
                'template' => $template,
                'template_data' => json_encode($data),
                'status' => self::STATUS_PENDING,
                'priority' => $options['priority'] ?? 'normal',
                'scheduled_at' => $options['scheduled_at'] ?? date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Store email in database for tracking
            $emailId = $this->storeEmail($emailData);
            
            // Send email immediately or queue for later
            if ($options['queue'] ?? false) {
                $this->queueEmail($emailId);
                return true;
            } else {
                return $this->sendEmailNow($emailId, $emailData);
            }
            
        } catch (Exception $e) {
            Logger::error('Failed to send email', [
                'to' => $to,
                'template' => $template,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send order confirmation email
     * 
     * @param array $orderData Order data
     * @return bool Success status
     */
    public function sendOrderConfirmation($orderData) {
        try {
            $templateData = [
                'order_number' => $orderData['order_number'],
                'customer_name' => $orderData['customer_name'],
                'order_date' => $orderData['created_at'],
                'total_amount' => $orderData['total_amount'],
                'currency' => $orderData['currency'] ?? 'INR',
                'items' => $orderData['items'],
                'shipping_address' => $orderData['shipping_address'],
                'payment_method' => $orderData['payment_method'],
                'expected_delivery' => $orderData['expected_delivery_date']
            ];
            
            $subject = "Order Confirmation - {$orderData['order_number']} | Riya Collections";
            
            return $this->sendEmail(
                $orderData['customer_email'],
                $subject,
                self::TYPE_ORDER_CONFIRMATION,
                $templateData
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to send order confirmation email', [
                'order_id' => $orderData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send payment confirmation email
     * 
     * @param array $paymentData Payment data
     * @return bool Success status
     */
    public function sendPaymentConfirmation($paymentData) {
        try {
            $templateData = [
                'order_number' => $paymentData['order_number'],
                'customer_name' => $paymentData['customer_name'],
                'payment_id' => $paymentData['payment_id'],
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'INR',
                'payment_method' => $paymentData['payment_method'],
                'payment_date' => $paymentData['payment_date'],
                'transaction_id' => $paymentData['transaction_id'] ?? null
            ];
            
            $subject = "Payment Confirmation - {$paymentData['order_number']} | Riya Collections";
            
            return $this->sendEmail(
                $paymentData['customer_email'],
                $subject,
                self::TYPE_PAYMENT_CONFIRMATION,
                $templateData
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to send payment confirmation email', [
                'payment_id' => $paymentData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send user registration welcome email
     * 
     * @param array $userData User data
     * @return bool Success status
     */
    public function sendRegistrationWelcome($userData) {
        try {
            $templateData = [
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email'],
                'registration_date' => $userData['created_at'],
                'login_url' => $this->config['app_url'] . '/login',
                'support_email' => $this->config['support_email']
            ];
            
            $subject = "Welcome to Riya Collections - Account Created Successfully";
            
            return $this->sendEmail(
                $userData['email'],
                $subject,
                self::TYPE_USER_REGISTRATION,
                $templateData
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to send registration welcome email', [
                'user_id' => $userData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send password reset email
     * 
     * @param array $userData User data
     * @param string $resetToken Reset token
     * @return bool Success status
     */
    public function sendPasswordReset($userData, $resetToken) {
        try {
            $resetUrl = $this->config['app_url'] . "/reset-password?token={$resetToken}";
            
            $templateData = [
                'first_name' => $userData['first_name'],
                'reset_url' => $resetUrl,
                'reset_token' => $resetToken,
                'expiry_hours' => 24,
                'support_email' => $this->config['support_email']
            ];
            
            $subject = "Password Reset Request - Riya Collections";
            
            return $this->sendEmail(
                $userData['email'],
                $subject,
                self::TYPE_PASSWORD_RESET,
                $templateData,
                ['priority' => 'high']
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to send password reset email', [
                'user_id' => $userData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send order status update email
     * 
     * @param array $orderData Order data
     * @param string $newStatus New order status
     * @return bool Success status
     */
    public function sendOrderStatusUpdate($orderData, $newStatus) {
        try {
            $statusMessages = [
                'confirmed' => 'Your order has been confirmed and is being prepared.',
                'processing' => 'Your order is currently being processed.',
                'shipped' => 'Your order has been shipped and is on its way.',
                'delivered' => 'Your order has been delivered successfully.',
                'cancelled' => 'Your order has been cancelled as requested.'
            ];
            
            $templateData = [
                'order_number' => $orderData['order_number'],
                'customer_name' => $orderData['customer_name'],
                'new_status' => $newStatus,
                'status_message' => $statusMessages[$newStatus] ?? 'Your order status has been updated.',
                'order_url' => $this->config['app_url'] . "/orders/{$orderData['id']}",
                'tracking_number' => $orderData['tracking_number'] ?? null,
                'estimated_delivery' => $orderData['estimated_delivery'] ?? null
            ];
            
            $subject = "Order Status Update - {$orderData['order_number']} | Riya Collections";
            
            return $this->sendEmail(
                $orderData['customer_email'],
                $subject,
                self::TYPE_ORDER_STATUS_UPDATE,
                $templateData
            );
            
        } catch (Exception $e) {
            Logger::error('Failed to send order status update email', [
                'order_id' => $orderData['id'] ?? 'unknown',
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send email immediately using SMTP
     * 
     * @param int $emailId Email ID
     * @param array $emailData Email data
     * @return bool Success status
     */
    private function sendEmailNow($emailId, $emailData) {
        try {
            // Update status to sending
            $this->updateEmailStatus($emailId, 'sending');
            
            // Send via SMTP
            $result = $this->sendViaSMTP($emailData);
            
            if ($result) {
                $this->updateEmailStatus($emailId, self::STATUS_SENT, [
                    'sent_at' => date('Y-m-d H:i:s'),
                    'smtp_response' => $result['message'] ?? 'Email sent successfully'
                ]);
                
                Logger::info('Email sent successfully', [
                    'email_id' => $emailId,
                    'to' => $emailData['to'],
                    'template' => $emailData['template']
                ]);
                
                return true;
            } else {
                throw new Exception('SMTP sending failed');
            }
            
        } catch (Exception $e) {
            $this->updateEmailStatus($emailId, self::STATUS_FAILED, [
                'failed_at' => date('Y-m-d H:i:s'),
                'error_message' => $e->getMessage()
            ]);
            
            Logger::error('Email sending failed', [
                'email_id' => $emailId,
                'to' => $emailData['to'],
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send email via SMTP
     * 
     * @param array $emailData Email data
     * @return array|false Result or false on failure
     */
    private function sendViaSMTP($emailData) {
        try {
            // Prepare headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $emailData['from_name'] . ' <' . $emailData['from_email'] . '>',
                'Reply-To: ' . $emailData['from_email'],
                'X-Mailer: Riya Collections PHP Mailer',
                'X-Priority: 3'
            ];
            
            // Use PHP's mail() function for basic SMTP
            // In production, consider using PHPMailer or SwiftMailer for advanced SMTP
            $success = mail(
                $emailData['to'],
                $emailData['subject'],
                $emailData['html_content'],
                implode("\r\n", $headers)
            );
            
            if ($success) {
                return ['success' => true, 'message' => 'Email sent via PHP mail()'];
            } else {
                throw new Exception('PHP mail() function failed');
            }
            
        } catch (Exception $e) {
            Logger::error('SMTP sending failed', [
                'to' => $emailData['to'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Render email template
     * 
     * @param string $template Template name
     * @param array $data Template data
     * @return array HTML and text content
     */
    private function renderTemplate($template, $data) {
        $htmlTemplate = $this->templatePath . $template . '.html';
        $textTemplate = $this->templatePath . $template . '.txt';
        
        // Create templates if they don't exist
        if (!file_exists($htmlTemplate)) {
            $this->createDefaultTemplate($template, 'html');
        }
        
        if (!file_exists($textTemplate)) {
            $this->createDefaultTemplate($template, 'txt');
        }
        
        // Render HTML template
        $htmlContent = file_get_contents($htmlTemplate);
        $htmlContent = $this->replaceTemplatePlaceholders($htmlContent, $data);
        
        // Render text template
        $textContent = file_get_contents($textTemplate);
        $textContent = $this->replaceTemplatePlaceholders($textContent, $data);
        
        return [
            'html' => $htmlContent,
            'text' => $textContent
        ];
    }
    
    /**
     * Replace template placeholders with data
     * 
     * @param string $content Template content
     * @param array $data Template data
     * @return string Rendered content
     */
    private function replaceTemplatePlaceholders($content, $data) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Handle array data (like order items)
                if ($key === 'items' && is_array($value)) {
                    $itemsHtml = '';
                    foreach ($value as $item) {
                        $itemsHtml .= "<tr>";
                        $itemsHtml .= "<td>" . htmlspecialchars($item['product_name'] ?? 'Product') . "</td>";
                        $itemsHtml .= "<td>" . htmlspecialchars($item['quantity'] ?? 1) . "</td>";
                        $itemsHtml .= "<td>₹" . number_format($item['unit_price'] ?? 0, 2) . "</td>";
                        $itemsHtml .= "<td>₹" . number_format($item['total_price'] ?? 0, 2) . "</td>";
                        $itemsHtml .= "</tr>";
                    }
                    $content = str_replace('{{items_table}}', $itemsHtml, $content);
                } elseif ($key === 'shipping_address' && is_array($value)) {
                    $addressHtml = htmlspecialchars($value['address_line1'] ?? '') . '<br>';
                    if (!empty($value['address_line2'])) {
                        $addressHtml .= htmlspecialchars($value['address_line2']) . '<br>';
                    }
                    $addressHtml .= htmlspecialchars($value['city'] ?? '') . ', ';
                    $addressHtml .= htmlspecialchars($value['state'] ?? '') . ' ';
                    $addressHtml .= htmlspecialchars($value['postal_code'] ?? '') . '<br>';
                    $addressHtml .= htmlspecialchars($value['country'] ?? '');
                    
                    $content = str_replace('{{shipping_address}}', $addressHtml, $content);
                }
            } else {
                // Handle scalar values
                $placeholder = '{{' . $key . '}}';
                $content = str_replace($placeholder, htmlspecialchars($value), $content);
            }
        }
        
        // Replace any remaining placeholders with empty string
        $content = preg_replace('/\{\{[^}]+\}\}/', '', $content);
        
        return $content;
    }
    
    /**
     * Create default email template
     * 
     * @param string $template Template name
     * @param string $format Format (html or txt)
     */
    private function createDefaultTemplate($template, $format) {
        $templateFile = $this->templatePath . $template . '.' . $format;
        
        if ($format === 'html') {
            $content = $this->getDefaultHtmlTemplate($template);
        } else {
            $content = $this->getDefaultTextTemplate($template);
        }
        
        file_put_contents($templateFile, $content);
        
        Logger::info('Created default email template', [
            'template' => $template,
            'format' => $format,
            'file' => $templateFile
        ]);
    }
    
    /**
     * Get default HTML template content
     * 
     * @param string $template Template name
     * @return string Template content
     */
    private function getDefaultHtmlTemplate($template) {
        $baseTemplate = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{subject}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-bottom: 3px solid #007bff; }
        .content { padding: 20px; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .order-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .order-table th, .order-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .order-table th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Riya Collections</h1>
        </div>
        <div class="content">
            {{content}}
        </div>
        <div class="footer">
            <p>&copy; 2024 Riya Collections. All rights reserved.</p>
            <p>If you have any questions, contact us at support@riyacollections.com</p>
        </div>
    </div>
</body>
</html>';
        
        switch ($template) {
            case self::TYPE_ORDER_CONFIRMATION:
                return str_replace('{{content}}', '
                    <h2>Order Confirmation</h2>
                    <p>Dear {{customer_name}},</p>
                    <p>Thank you for your order! We have received your order <strong>{{order_number}}</strong> and it is being processed.</p>
                    
                    <h3>Order Details:</h3>
                    <p><strong>Order Number:</strong> {{order_number}}</p>
                    <p><strong>Order Date:</strong> {{order_date}}</p>
                    <p><strong>Total Amount:</strong> ₹{{total_amount}}</p>
                    <p><strong>Payment Method:</strong> {{payment_method}}</p>
                    
                    <h3>Items Ordered:</h3>
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{items_table}}
                        </tbody>
                    </table>
                    
                    <h3>Shipping Address:</h3>
                    <p>{{shipping_address}}</p>
                    
                    <p>Expected delivery: {{expected_delivery}}</p>
                    <p>We will send you another email when your order ships.</p>
                ', $baseTemplate);
                
            case self::TYPE_PAYMENT_CONFIRMATION:
                return str_replace('{{content}}', '
                    <h2>Payment Confirmation</h2>
                    <p>Dear {{customer_name}},</p>
                    <p>We have successfully received your payment for order <strong>{{order_number}}</strong>.</p>
                    
                    <h3>Payment Details:</h3>
                    <p><strong>Payment ID:</strong> {{payment_id}}</p>
                    <p><strong>Amount:</strong> ₹{{amount}}</p>
                    <p><strong>Payment Method:</strong> {{payment_method}}</p>
                    <p><strong>Payment Date:</strong> {{payment_date}}</p>
                    <p><strong>Transaction ID:</strong> {{transaction_id}}</p>
                    
                    <p>Your order is now confirmed and will be processed shortly.</p>
                ', $baseTemplate);
                
            case self::TYPE_USER_REGISTRATION:
                return str_replace('{{content}}', '
                    <h2>Welcome to Riya Collections!</h2>
                    <p>Dear {{first_name}} {{last_name}},</p>
                    <p>Welcome to Riya Collections! Your account has been created successfully.</p>
                    
                    <h3>Account Details:</h3>
                    <p><strong>Email:</strong> {{email}}</p>
                    <p><strong>Registration Date:</strong> {{registration_date}}</p>
                    
                    <p>You can now log in to your account and start shopping.</p>
                    <p><a href="{{login_url}}" class="button">Login to Your Account</a></p>
                    
                    <p>Thank you for choosing Riya Collections!</p>
                ', $baseTemplate);
                
            case self::TYPE_PASSWORD_RESET:
                return str_replace('{{content}}', '
                    <h2>Password Reset Request</h2>
                    <p>Dear {{first_name}},</p>
                    <p>We received a request to reset your password for your Riya Collections account.</p>
                    
                    <p>Click the button below to reset your password:</p>
                    <p><a href="{{reset_url}}" class="button">Reset Password</a></p>
                    
                    <p>This link will expire in {{expiry_hours}} hours for security reasons.</p>
                    <p>If you did not request this password reset, please ignore this email.</p>
                ', $baseTemplate);
                
            case self::TYPE_ORDER_STATUS_UPDATE:
                return str_replace('{{content}}', '
                    <h2>Order Status Update</h2>
                    <p>Dear {{customer_name}},</p>
                    <p>Your order <strong>{{order_number}}</strong> status has been updated.</p>
                    
                    <h3>New Status: {{new_status}}</h3>
                    <p>{{status_message}}</p>
                    
                    <p><a href="{{order_url}}" class="button">View Order Details</a></p>
                ', $baseTemplate);
                
            default:
                return str_replace('{{content}}', '
                    <h2>{{subject}}</h2>
                    <p>This is a default email template.</p>
                ', $baseTemplate);
        }
    }
    
    /**
     * Get default text template content
     * 
     * @param string $template Template name
     * @return string Template content
     */
    private function getDefaultTextTemplate($template) {
        switch ($template) {
            case self::TYPE_ORDER_CONFIRMATION:
                return "
RIYA COLLECTIONS - ORDER CONFIRMATION

Dear {{customer_name}},

Thank you for your order! We have received your order {{order_number}} and it is being processed.

Order Details:
- Order Number: {{order_number}}
- Order Date: {{order_date}}
- Total Amount: ₹{{total_amount}}
- Payment Method: {{payment_method}}

Shipping Address:
{{shipping_address}}

Expected delivery: {{expected_delivery}}

We will send you another email when your order ships.

Thank you for choosing Riya Collections!

---
Riya Collections
support@riyacollections.com
";
                
            case self::TYPE_PAYMENT_CONFIRMATION:
                return "
RIYA COLLECTIONS - PAYMENT CONFIRMATION

Dear {{customer_name}},

We have successfully received your payment for order {{order_number}}.

Payment Details:
- Payment ID: {{payment_id}}
- Amount: ₹{{amount}}
- Payment Method: {{payment_method}}
- Payment Date: {{payment_date}}
- Transaction ID: {{transaction_id}}

Your order is now confirmed and will be processed shortly.

Thank you for choosing Riya Collections!

---
Riya Collections
support@riyacollections.com
";
                
            default:
                return "
RIYA COLLECTIONS

{{subject}}

This is a default text email template.

---
Riya Collections
support@riyacollections.com
";
        }
    }
    
    /**
     * Store email in database
     * 
     * @param array $emailData Email data
     * @return int Email ID
     */
    private function storeEmail($emailData) {
        $sql = "INSERT INTO emails (
            to_email, from_email, from_name, subject, html_content, text_content,
            template, template_data, status, priority, scheduled_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $emailData['to'],
            $emailData['from_email'],
            $emailData['from_name'],
            $emailData['subject'],
            $emailData['html_content'],
            $emailData['text_content'],
            $emailData['template'],
            $emailData['template_data'],
            $emailData['status'],
            $emailData['priority'],
            $emailData['scheduled_at'],
            $emailData['created_at']
        ];
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Update email status
     * 
     * @param int $emailId Email ID
     * @param string $status New status
     * @param array $additionalData Additional data to update
     */
    private function updateEmailStatus($emailId, $status, $additionalData = []) {
        $updateData = array_merge(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], $additionalData);
        
        $fields = [];
        $params = [];
        
        foreach ($updateData as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $emailId;
        
        $sql = "UPDATE emails SET " . implode(', ', $fields) . " WHERE id = ?";
        $this->db->executeQuery($sql, $params);
    }
    
    /**
     * Queue email for later sending
     * 
     * @param int $emailId Email ID
     */
    private function queueEmail($emailId) {
        $this->updateEmailStatus($emailId, self::STATUS_QUEUED);
        
        Logger::info('Email queued for sending', ['email_id' => $emailId]);
    }
    
    /**
     * Validate email address
     * 
     * @param string $email Email address
     * @return bool True if valid
     */
    private function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Process email queue
     * 
     * @param int $limit Maximum emails to process
     * @return int Number of emails processed
     */
    public function processEmailQueue($limit = 50) {
        try {
            $sql = "SELECT * FROM emails 
                    WHERE status IN (?, ?) 
                    AND scheduled_at <= NOW() 
                    ORDER BY priority DESC, created_at ASC 
                    LIMIT ?";
            
            $emails = $this->db->fetchAll($sql, [self::STATUS_PENDING, self::STATUS_QUEUED, $limit]);
            
            $processed = 0;
            
            foreach ($emails as $email) {
                try {
                    $emailData = [
                        'to' => $email['to_email'],
                        'from_email' => $email['from_email'],
                        'from_name' => $email['from_name'],
                        'subject' => $email['subject'],
                        'html_content' => $email['html_content'],
                        'text_content' => $email['text_content'],
                        'template' => $email['template']
                    ];
                    
                    if ($this->sendEmailNow($email['id'], $emailData)) {
                        $processed++;
                    }
                    
                } catch (Exception $e) {
                    Logger::error('Failed to process queued email', [
                        'email_id' => $email['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            Logger::info('Email queue processed', [
                'processed' => $processed,
                'total_found' => count($emails)
            ]);
            
            return $processed;
            
        } catch (Exception $e) {
            Logger::error('Failed to process email queue', ['error' => $e->getMessage()]);
            return 0;
        }
    }
    
    /**
     * Get email statistics
     * 
     * @return array Email statistics
     */
    public function getEmailStatistics() {
        try {
            $stats = [];
            
            // Total emails
            $stats['total_emails'] = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM emails");
            
            // Emails by status
            $statusStats = $this->db->fetchAll("
                SELECT status, COUNT(*) as count 
                FROM emails 
                GROUP BY status
            ");
            
            foreach ($statusStats as $stat) {
                $stats['by_status'][$stat['status']] = (int)$stat['count'];
            }
            
            // Emails by template
            $templateStats = $this->db->fetchAll("
                SELECT template, COUNT(*) as count 
                FROM emails 
                GROUP BY template
            ");
            
            foreach ($templateStats as $stat) {
                $stats['by_template'][$stat['template']] = (int)$stat['count'];
            }
            
            // Recent emails (last 7 days)
            $stats['recent_emails'] = (int)$this->db->fetchColumn("
                SELECT COUNT(*) 
                FROM emails 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            // Success rate
            $sentEmails = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM emails WHERE status = 'sent'");
            $totalAttempted = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM emails WHERE status IN ('sent', 'failed')");
            
            $stats['success_rate'] = $totalAttempted > 0 ? round(($sentEmails / $totalAttempted) * 100, 2) : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Failed to get email statistics', ['error' => $e->getMessage()]);
            return [];
        }
    }
}