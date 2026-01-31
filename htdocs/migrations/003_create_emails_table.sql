-- Email System Tables Migration
-- Creates tables for email tracking and template management
-- Requirements: 9.1, 12.1, 12.2

-- Create emails table for tracking sent emails
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    html_content LONGTEXT,
    text_content LONGTEXT,
    template VARCHAR(100),
    template_data JSON,
    status ENUM('pending', 'queued', 'sending', 'sent', 'failed') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    scheduled_at DATETIME,
    sent_at DATETIME NULL,
    failed_at DATETIME NULL,
    error_message TEXT NULL,
    smtp_response TEXT NULL,
    retry_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_to_email (to_email),
    INDEX idx_template (template),
    INDEX idx_created_at (created_at)
);

-- Create email templates table for managing email templates
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(500) NOT NULL,
    html_content LONGTEXT NOT NULL,
    text_content LONGTEXT,
    variables JSON,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_is_active (is_active)
);

-- Create email queue table for batch processing
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_id INT NOT NULL,
    priority INT DEFAULT 0,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    available_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
    INDEX idx_available_at (available_at),
    INDEX idx_priority (priority),
    INDEX idx_attempts (attempts)
);

-- Insert default email templates
INSERT INTO email_templates (name, subject, html_content, text_content, variables, description) VALUES
('order_confirmation', 'Order Confirmation - {{order_number}} | Riya Collections', 
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #E91E63; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .order-details { background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .items-table th { background: #f8f9fa; font-weight: bold; }
        .button { display: inline-block; background: #E91E63; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
            <p>Thank you for your order!</p>
        </div>
        <div class="content">
            <p>Dear {{customer_name}},</p>
            <p>We have received your order and it is being processed. Here are the details:</p>
            
            <div class="order-details">
                <h3>Order #{{order_number}}</h3>
                <p><strong>Order Date:</strong> {{order_date}}</p>
                <p><strong>Total Amount:</strong> ₹{{total_amount}}</p>
                <p><strong>Payment Method:</strong> {{payment_method}}</p>
                <p><strong>Expected Delivery:</strong> {{expected_delivery}}</p>
            </div>
            
            <h3>Items Ordered:</h3>
            <table class="items-table">
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
            <div>{{shipping_address}}</div>
            
            <p>We will send you another email when your order ships.</p>
            <p>If you have any questions, please contact us at support@riyacollections.com</p>
        </div>
        <div class="footer">
            <p>&copy; 2024 Riya Collections. All rights reserved.</p>
            <p>Visit us at https://riyacollections.com</p>
        </div>
    </div>
</body>
</html>',
'RIYA COLLECTIONS - ORDER CONFIRMATION

Dear {{customer_name}},

Thank you for your order! We have received your order {{order_number}} and it is being processed.

Order Details:
- Order Number: {{order_number}}
- Order Date: {{order_date}}
- Total Amount: ₹{{total_amount}}
- Payment Method: {{payment_method}}
- Expected Delivery: {{expected_delivery}}

Shipping Address:
{{shipping_address}}

We will send you another email when your order ships.

If you have any questions, please contact us at support@riyacollections.com

Thank you for choosing Riya Collections!

---
Riya Collections
https://riyacollections.com',
'["customer_name", "order_number", "order_date", "total_amount", "payment_method", "expected_delivery", "items_table", "shipping_address"]',
'Order confirmation email sent to customers after placing an order'),

('payment_confirmation', 'Payment Confirmation - {{order_number}} | Riya Collections',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #4CAF50; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .payment-details { background: #e8f5e8; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #4caf50; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Received</h1>
            <p>Your payment has been processed successfully!</p>
        </div>
        <div class="content">
            <p>Dear {{customer_name}},</p>
            <p>We have successfully received your payment for order #{{order_number}}.</p>
            
            <div class="payment-details">
                <h3>Payment Details</h3>
                <p><strong>Payment ID:</strong> {{payment_id}}</p>
                <p><strong>Amount:</strong> ₹{{amount}}</p>
                <p><strong>Payment Method:</strong> {{payment_method}}</p>
                <p><strong>Payment Date:</strong> {{payment_date}}</p>
                <p><strong>Transaction ID:</strong> {{transaction_id}}</p>
            </div>
            
            <p>Your order is now confirmed and will be processed shortly.</p>
            <p>Thank you for choosing Riya Collections!</p>
        </div>
        <div class="footer">
            <p>&copy; 2024 Riya Collections. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
'RIYA COLLECTIONS - PAYMENT CONFIRMATION

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
https://riyacollections.com',
'["customer_name", "order_number", "payment_id", "amount", "payment_method", "payment_date", "transaction_id"]',
'Payment confirmation email sent after successful payment'),

('user_registration', 'Welcome to Riya Collections - Account Created Successfully',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to Riya Collections</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #E91E63; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .welcome-box { background: #f0f8ff; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .button { display: inline-block; background: #E91E63; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Riya Collections!</h1>
        </div>
        <div class="content">
            <p>Dear {{first_name}} {{last_name}},</p>
            
            <div class="welcome-box">
                <h3>Your account has been created successfully!</h3>
                <p><strong>Email:</strong> {{email}}</p>
                <p><strong>Registration Date:</strong> {{registration_date}}</p>
            </div>
            
            <p>You can now log in to your account and start shopping for beautiful collections.</p>
            <p><a href="{{login_url}}" class="button">Login to Your Account</a></p>
            
            <p>If you have any questions, feel free to contact our support team at {{support_email}}.</p>
            <p>Thank you for joining Riya Collections!</p>
        </div>
        <div class="footer">
            <p>&copy; 2024 Riya Collections. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
'RIYA COLLECTIONS - WELCOME

Dear {{first_name}} {{last_name}},

Welcome to Riya Collections! Your account has been created successfully.

Account Details:
- Email: {{email}}
- Registration Date: {{registration_date}}

You can now log in to your account and start shopping.
Login URL: {{login_url}}

If you have any questions, contact us at {{support_email}}.

Thank you for joining Riya Collections!

---
Riya Collections
https://riyacollections.com',
'["first_name", "last_name", "email", "registration_date", "login_url", "support_email"]',
'Welcome email sent to new users after registration');

-- Create indexes for better performance
CREATE INDEX idx_emails_status_scheduled ON emails(status, scheduled_at);
CREATE INDEX idx_emails_template_status ON emails(template, status);
CREATE INDEX idx_email_queue_available_priority ON email_queue(available_at, priority);