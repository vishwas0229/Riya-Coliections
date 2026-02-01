<?php
/**
 * Email Triggers Utility
 * 
 * Handles automatic email sending based on system events like order creation,
 * payment confirmation, status updates, etc. Integrates with the EmailService
 * to provide seamless email notifications.
 * 
 * Requirements: 9.1, 12.2
 */

require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../utils/Logger.php';

class EmailTriggers {
    private $emailService;
    
    public function __construct() {
        $this->emailService = new EmailService();
    }
    
    /**
     * Trigger order confirmation email
     * 
     * @param array $orderData Order data
     * @param array $userData User data
     * @return bool Success status
     */
    public function triggerOrderConfirmation($orderData, $userData) {
        try {
            Logger::info('Triggering order confirmation email', [
                'order_id' => $orderData['id'],
                'user_id' => $userData['id'],
                'order_number' => $orderData['order_number']
            ]);
            
            // Prepare order data for email
            $emailOrderData = [
                'id' => $orderData['id'],
                'order_number' => $orderData['order_number'],
                'created_at' => $orderData['created_at'],
                'total_amount' => $orderData['total_amount'],
                'currency' => $orderData['currency'] ?? 'INR',
                'payment_method' => $this->formatPaymentMethod($orderData['payment_method']),
                'expected_delivery_date' => $orderData['expected_delivery_date'],
                'items' => $orderData['items'] ?? [],
                'shipping_address' => $orderData['shipping_address'] ?? [],
                'customer_name' => $userData['first_name'] . ' ' . $userData['last_name'],
                'customer_email' => $userData['email']
            ];
            
            return $this->emailService->sendOrderConfirmation($emailOrderData);
            
        } catch (Exception $e) {
            Logger::error('Failed to trigger order confirmation email', [
                'order_id' => $orderData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Trigger payment confirmation email
     * 
     * @param array $paymentData Payment data
     * @param array $orderData Order data
     * @param array $userData User data
     * @return bool Success status
     */
    public function triggerPaymentConfirmation($paymentData, $orderData, $userData) {
        try {
            Logger::info('Triggering payment confirmation email', [
                'payment_id' => $paymentData['id'],
                'order_id' => $orderData['id'],
                'user_id' => $userData['id']
            ]);
            
            // Prepare payment data for email
            $emailPaymentData = [
                'id' => $paymentData['id'],
                'payment_id' => $paymentData['razorpay_payment_id'] ?? $paymentData['id'],
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'INR',
                'payment_method' => $this->formatPaymentMethod($paymentData['payment_method']),
                'payment_date' => $paymentData['created_at'],
                'transaction_id' => $paymentData['razorpay_payment_id'] ?? $paymentData['transaction_id'] ?? null,
                'order_number' => $orderData['order_number'],
                'customer_name' => $userData['first_name'] . ' ' . $userData['last_name'],
                'customer_email' => $userData['email']
            ];
            
            return $this->emailService->sendPaymentConfirmation($emailPaymentData);
            
        } catch (Exception $e) {
            Logger::error('Failed to trigger payment confirmation email', [
                'payment_id' => $paymentData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Trigger user registration welcome email
     * 
     * @param array $userData User data
     * @return bool Success status
     */
    public function triggerRegistrationWelcome($userData) {
        try {
            Logger::info('Triggering registration welcome email', [
                'user_id' => $userData['id'],
                'email' => $userData['email']
            ]);
            
            return $this->emailService->sendRegistrationWelcome($userData);
            
        } catch (Exception $e) {
            Logger::error('Failed to trigger registration welcome email', [
                'user_id' => $userData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Trigger password reset email
     * 
     * @param array $userData User data
     * @param string $resetToken Reset token
     * @return bool Success status
     */
    public function triggerPasswordReset($userData, $resetToken) {
        try {
            Logger::info('Triggering password reset email', [
                'user_id' => $userData['id'],
                'email' => $userData['email']
            ]);
            
            return $this->emailService->sendPasswordReset($userData, $resetToken);
            
        } catch (Exception $e) {
            Logger::error('Failed to trigger password reset email', [
                'user_id' => $userData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Trigger order status update email
     * 
     * @param array $orderData Order data
     * @param array $userData User data
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function triggerOrderStatusUpdate($orderData, $userData, $oldStatus, $newStatus) {
        try {
            // Only send email for significant status changes
            $significantStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
            
            if (!in_array($newStatus, $significantStatuses)) {
                Logger::debug('Skipping email for non-significant status change', [
                    'order_id' => $orderData['id'],
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
                return true;
            }
            
            Logger::info('Triggering order status update email', [
                'order_id' => $orderData['id'],
                'user_id' => $userData['id'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
            
            // Prepare order data for email
            $emailOrderData = [
                'id' => $orderData['id'],
                'order_number' => $orderData['order_number'],
                'customer_name' => $userData['first_name'] . ' ' . $userData['last_name'],
                'customer_email' => $userData['email'],
                'tracking_number' => $orderData['tracking_number'] ?? null,
                'estimated_delivery' => $orderData['estimated_delivery_date'] ?? null
            ];
            
            return $this->emailService->sendOrderStatusUpdate($emailOrderData, $newStatus);
            
        } catch (Exception $e) {
            Logger::error('Failed to trigger order status update email', [
                'order_id' => $orderData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Trigger shipping notification email
     * 
     * @param array $orderData Order data
     * @param array $userData User data
     * @param array $shippingData Shipping data
     * @return bool Success status
     */
    public function triggerShippingNotification($orderData, $userData, $shippingData) {
        try {
            Logger::info('Triggering shipping notification email', [
                'order_id' => $orderData['id'],
                'user_id' => $userData['id'],
                'tracking_number' => $shippingData['tracking_number'] ?? null
            ]);
            
            // Use order status update with shipping-specific data
            $orderDataWithShipping = array_merge($orderData, [
                'tracking_number' => $shippingData['tracking_number'] ?? null,
                'carrier' => $shippingData['carrier'] ?? null,
                'estimated_delivery_date' => $shippingData['estimated_delivery'] ?? null
            ]);
            
            return $this->triggerOrderStatusUpdate($orderDataWithShipping, $userData, 'processing', 'shipped');
            
        } catch (Exception $e) {
            Logger::error('Failed to trigger shipping notification email', [
                'order_id' => $orderData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Trigger delivery confirmation email
     * 
     * @param array $orderData Order data
     * @param array $userData User data
     * @return bool Success status
     */
    public function triggerDeliveryConfirmation($orderData, $userData) {
        try {
            Logger::info('Triggering delivery confirmation email', [
                'order_id' => $orderData['id'],
                'user_id' => $userData['id']
            ]);
            
            return $this->triggerOrderStatusUpdate($orderData, $userData, 'shipped', 'delivered');
            
        } catch (Exception $e) {
            Logger::error('Failed to trigger delivery confirmation email', [
                'order_id' => $orderData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Trigger bulk email notifications
     * 
     * @param string $type Email type
     * @param array $recipients List of recipients
     * @param array $data Email data
     * @return array Results
     */
    public function triggerBulkEmails($type, $recipients, $data) {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($recipients as $recipient) {
            try {
                $success = false;
                
                switch ($type) {
                    case 'newsletter':
                        $success = $this->sendNewsletterEmail($recipient, $data);
                        break;
                        
                    case 'promotion':
                        $success = $this->sendPromotionalEmail($recipient, $data);
                        break;
                        
                    case 'announcement':
                        $success = $this->sendAnnouncementEmail($recipient, $data);
                        break;
                        
                    default:
                        throw new Exception("Unknown bulk email type: {$type}");
                }
                
                if ($success) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'recipient' => $recipient['email'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                
                Logger::error('Bulk email failed for recipient', [
                    'type' => $type,
                    'recipient' => $recipient['email'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Logger::info('Bulk email campaign completed', [
            'type' => $type,
            'total_recipients' => count($recipients),
            'sent' => $results['sent'],
            'failed' => $results['failed']
        ]);
        
        return $results;
    }
    
    /**
     * Format payment method for display
     * 
     * @param string $method Payment method
     * @return string Formatted method
     */
    private function formatPaymentMethod($method) {
        $methods = [
            'cod' => 'Cash on Delivery',
            'razorpay' => 'Online Payment (Razorpay)',
            'online' => 'Online Payment',
            'card' => 'Credit/Debit Card',
            'upi' => 'UPI Payment',
            'netbanking' => 'Net Banking',
            'wallet' => 'Digital Wallet'
        ];
        
        return $methods[$method] ?? ucfirst($method);
    }
    
    /**
     * Send newsletter email
     * 
     * @param array $recipient Recipient data
     * @param array $data Newsletter data
     * @return bool Success status
     */
    private function sendNewsletterEmail($recipient, $data) {
        $subject = $data['subject'] ?? 'Newsletter - Riya Collections';
        $template = 'newsletter';
        
        $templateData = array_merge($data, [
            'first_name' => $recipient['first_name'] ?? 'Valued Customer',
            'unsubscribe_url' => $this->generateUnsubscribeUrl($recipient['email'])
        ]);
        
        return $this->emailService->sendEmail(
            $recipient['email'],
            $subject,
            $template,
            $templateData
        );
    }
    
    /**
     * Send promotional email
     * 
     * @param array $recipient Recipient data
     * @param array $data Promotion data
     * @return bool Success status
     */
    private function sendPromotionalEmail($recipient, $data) {
        $subject = $data['subject'] ?? 'Special Offer - Riya Collections';
        $template = 'promotion';
        
        $templateData = array_merge($data, [
            'first_name' => $recipient['first_name'] ?? 'Valued Customer',
            'unsubscribe_url' => $this->generateUnsubscribeUrl($recipient['email'])
        ]);
        
        return $this->emailService->sendEmail(
            $recipient['email'],
            $subject,
            $template,
            $templateData
        );
    }
    
    /**
     * Send announcement email
     * 
     * @param array $recipient Recipient data
     * @param array $data Announcement data
     * @return bool Success status
     */
    private function sendAnnouncementEmail($recipient, $data) {
        $subject = $data['subject'] ?? 'Important Announcement - Riya Collections';
        $template = 'announcement';
        
        $templateData = array_merge($data, [
            'first_name' => $recipient['first_name'] ?? 'Valued Customer'
        ]);
        
        return $this->emailService->sendEmail(
            $recipient['email'],
            $subject,
            $template,
            $templateData
        );
    }
    
    /**
     * Generate unsubscribe URL
     * 
     * @param string $email Email address
     * @return string Unsubscribe URL
     */
    private function generateUnsubscribeUrl($email) {
        $token = base64_encode($email . '|' . time());
        $config = getEmailConfig();
        return $config['app_url'] . '/unsubscribe?token=' . urlencode($token);
    }
    
    /**
     * Test email triggers
     * 
     * @return array Test results
     */
    public function testEmailTriggers() {
        $results = [];
        
        try {
            // Test order confirmation
            $testOrder = [
                'id' => 'test_order_123',
                'order_number' => 'RC20241201001',
                'created_at' => date('Y-m-d H:i:s'),
                'total_amount' => 1500.00,
                'currency' => 'INR',
                'payment_method' => 'razorpay',
                'expected_delivery_date' => date('Y-m-d', strtotime('+7 days')),
                'items' => [
                    [
                        'product_name' => 'Test Product',
                        'quantity' => 2,
                        'unit_price' => 750.00,
                        'total_price' => 1500.00
                    ]
                ],
                'shipping_address' => [
                    'address_line1' => '123 Test Street',
                    'city' => 'Test City',
                    'state' => 'Test State',
                    'postal_code' => '123456',
                    'country' => 'India'
                ]
            ];
            
            $testUser = [
                'id' => 'test_user_123',
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com'
            ];
            
            $results['order_confirmation'] = $this->triggerOrderConfirmation($testOrder, $testUser);
            
            Logger::info('Email triggers test completed', $results);
            
        } catch (Exception $e) {
            Logger::error('Email triggers test failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
}

// Global helper functions
function getEmailTriggers() {
    static $triggers = null;
    if ($triggers === null) {
        $triggers = new EmailTriggers();
    }
    return $triggers;
}

function triggerOrderConfirmationEmail($orderData, $userData) {
    return getEmailTriggers()->triggerOrderConfirmation($orderData, $userData);
}

function triggerPaymentConfirmationEmail($paymentData, $orderData, $userData) {
    return getEmailTriggers()->triggerPaymentConfirmation($paymentData, $orderData, $userData);
}

function triggerRegistrationWelcomeEmail($userData) {
    return getEmailTriggers()->triggerRegistrationWelcome($userData);
}

function triggerPasswordResetEmail($userData, $resetToken) {
    return getEmailTriggers()->triggerPasswordReset($userData, $resetToken);
}

function triggerOrderStatusUpdateEmail($orderData, $userData, $oldStatus, $newStatus) {
    return getEmailTriggers()->triggerOrderStatusUpdate($orderData, $userData, $oldStatus, $newStatus);
}