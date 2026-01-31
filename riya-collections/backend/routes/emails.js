const express = require('express');
const router = express.Router();
const { authenticateAdmin } = require('../middleware/auth');
const { validationResult } = require('express-validator');
const { 
  sendOrderConfirmationEmail,
  sendOrderStatusEmail,
  sendPaymentConfirmationEmail,
  sendCODDeliveryEmail,
  sendWelcomeEmail,
  sendPasswordResetEmail,
  sendBatchEmails,
  healthCheck
} = require('../utils/email-service');
const { verifyEmailConfig } = require('../config/email');

/**
 * Email Management Routes
 * 
 * This module provides endpoints for email service management,
 * testing, and monitoring.
 * 
 * Requirements: 12.1, 12.4, 12.5
 */

/**
 * GET /api/emails/health
 * Check email service health
 * Requires admin authentication
 */
router.get('/health', authenticateAdmin, async (req, res) => {
  try {
    const health = await healthCheck();
    
    res.json({
      service: 'email',
      status: health.healthy ? 'healthy' : 'unhealthy',
      timestamp: health.timestamp,
      details: health
    });
  } catch (error) {
    res.status(500).json({
      service: 'email',
      status: 'error',
      error: error.message,
      timestamp: new Date().toISOString()
    });
  }
});

/**
 * POST /api/emails/test
 * Send test email to verify configuration
 * Requires admin authentication
 */
router.post('/test', authenticateAdmin, async (req, res) => {
  try {
    const { email, type = 'welcome' } = req.body;
    
    if (!email) {
      return res.status(400).json({
        error: 'Email address is required'
      });
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return res.status(400).json({
        error: 'Invalid email format'
      });
    }

    let result;
    const testCustomer = {
      id: 'test',
      first_name: 'Test',
      last_name: 'User',
      email: email
    };

    switch (type) {
      case 'welcome':
        result = await sendWelcomeEmail(testCustomer);
        break;
        
      case 'order-confirmation':
        const testOrder = {
          id: 'test-123',
          order_number: 'RC' + Date.now(),
          status: 'placed',
          total_amount: 1299.00,
          discount_amount: 100.00,
          shipping_amount: 0,
          tax_amount: 215.82,
          payment_method: 'razorpay',
          created_at: new Date()
        };
        
        const testItems = [
          {
            product_name: 'Lakme Absolute Matte Lipstick',
            quantity: 2,
            unit_price: 599.50,
            total_price: 1199.00
          }
        ];
        
        const testAddress = {
          first_name: 'Test',
          last_name: 'User',
          address_line1: '123 Test Street',
          city: 'Mumbai',
          state: 'Maharashtra',
          postal_code: '400001',
          country: 'India',
          phone: '+91 9876543210'
        };
        
        result = await sendOrderConfirmationEmail(testOrder, testCustomer, testItems, testAddress);
        break;
        
      case 'order-status':
        result = await sendOrderStatusEmail(
          { id: 'test-123', order_number: 'RC' + Date.now() },
          testCustomer,
          'shipped',
          'processing',
          'Your order has been shipped and is on its way!'
        );
        break;
        
      case 'payment-confirmation':
        result = await sendPaymentConfirmationEmail(
          { id: 'test-123', order_number: 'RC' + Date.now(), total_amount: 1299.00 },
          testCustomer,
          { transaction_id: 'pay_test_' + Date.now(), method: 'card', created_at: new Date() }
        );
        break;
        
      default:
        return res.status(400).json({
          error: 'Invalid email type. Supported types: welcome, order-confirmation, order-status, payment-confirmation'
        });
    }

    if (result.success) {
      res.json({
        message: 'Test email sent successfully',
        email_type: type,
        recipient: email,
        message_id: result.messageId,
        timestamp: new Date().toISOString()
      });
    } else {
      res.status(500).json({
        error: 'Failed to send test email',
        details: result.error,
        email_type: type,
        recipient: email
      });
    }
  } catch (error) {
    console.error('Test email error:', error);
    res.status(500).json({
      error: 'Failed to send test email',
      message: error.message
    });
  }
});

/**
 * POST /api/emails/batch
 * Send batch emails
 * Requires admin authentication
 */
router.post('/batch', authenticateAdmin, async (req, res) => {
  try {
    const { emails } = req.body;
    
    if (!Array.isArray(emails) || emails.length === 0) {
      return res.status(400).json({
        error: 'emails must be a non-empty array'
      });
    }

    if (emails.length > 100) {
      return res.status(400).json({
        error: 'Maximum 100 emails allowed per batch'
      });
    }

    const result = await sendBatchEmails(emails);
    
    res.json({
      message: 'Batch email processing completed',
      total: result.total,
      successful: result.successful,
      failed: result.failed,
      results: result.results,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Batch email error:', error);
    res.status(500).json({
      error: 'Failed to process batch emails',
      message: error.message
    });
  }
});

/**
 * GET /api/emails/config
 * Get email configuration status
 * Requires admin authentication
 */
router.get('/config', authenticateAdmin, async (req, res) => {
  try {
    const isConfigured = await verifyEmailConfig();
    
    res.json({
      configured: isConfigured,
      smtp_host: process.env.SMTP_HOST || 'Not configured',
      smtp_port: process.env.SMTP_PORT || 'Not configured',
      smtp_user: process.env.SMTP_USER ? 'Configured' : 'Not configured',
      company_email: process.env.COMPANY_EMAIL || 'Not configured',
      support_email: process.env.SUPPORT_EMAIL || 'Not configured',
      website_url: process.env.WEBSITE_URL || 'Not configured',
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    res.status(500).json({
      error: 'Failed to check email configuration',
      message: error.message
    });
  }
});

/**
 * POST /api/emails/resend-order-confirmation
 * Resend order confirmation email
 * Requires admin authentication
 */
router.post('/resend-order-confirmation', authenticateAdmin, async (req, res) => {
  try {
    const { order_id } = req.body;
    
    if (!order_id) {
      return res.status(400).json({
        error: 'order_id is required'
      });
    }

    // Get order details from database
    const { secureExecuteQuery } = require('../middleware/database-security');
    
    const orderResult = await secureExecuteQuery(
      `SELECT o.*, u.first_name, u.last_name, u.email,
              a.first_name as addr_first_name, a.last_name as addr_last_name,
              a.address_line1, a.address_line2, a.city, a.state, a.postal_code, a.country, a.phone
       FROM orders o
       JOIN users u ON o.user_id = u.id
       JOIN addresses a ON o.shipping_address_id = a.id
       WHERE o.id = ?`,
      [order_id]
    );

    if (orderResult.length === 0) {
      return res.status(404).json({
        error: 'Order not found'
      });
    }

    const order = orderResult[0];

    // Get order items
    const itemsResult = await secureExecuteQuery(
      `SELECT oi.quantity, oi.unit_price, oi.total_price, p.name as product_name
       FROM order_items oi
       JOIN products p ON oi.product_id = p.id
       WHERE oi.order_id = ?`,
      [order_id]
    );

    const customer = {
      id: order.user_id,
      first_name: order.first_name,
      last_name: order.last_name,
      email: order.email
    };

    const address = {
      first_name: order.addr_first_name,
      last_name: order.addr_last_name,
      address_line1: order.address_line1,
      address_line2: order.address_line2,
      city: order.city,
      state: order.state,
      postal_code: order.postal_code,
      country: order.country,
      phone: order.phone
    };

    const result = await sendOrderConfirmationEmail(order, customer, itemsResult, address);

    if (result.success) {
      res.json({
        message: 'Order confirmation email resent successfully',
        order_id: order_id,
        order_number: order.order_number,
        recipient: customer.email,
        message_id: result.messageId
      });
    } else {
      res.status(500).json({
        error: 'Failed to resend order confirmation email',
        details: result.error
      });
    }
  } catch (error) {
    console.error('Resend order confirmation error:', error);
    res.status(500).json({
      error: 'Failed to resend order confirmation email',
      message: error.message
    });
  }
});

module.exports = router;