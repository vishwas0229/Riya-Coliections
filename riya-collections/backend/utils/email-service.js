const { queueEmail, brandingConfig, emailUtils } = require('../config/email');
const {
  generateOrderConfirmationEmail,
  generateOrderStatusEmail,
  generatePaymentConfirmationEmail,
  generateCODDeliveryEmail
} = require('./email-templates');

/**
 * Email Service Module
 * 
 * This module provides high-level email sending functions for various
 * order events and customer communications with proper error handling
 * and logging.
 * 
 * Requirements: 12.1, 12.2, 12.3, 12.4, 12.5
 */

/**
 * Send order confirmation email
 * Requirements: 12.2
 */
async function sendOrderConfirmationEmail(order, customer, items, address) {
  try {
    console.log(`📧 Preparing order confirmation email for order ${order.order_number}`);
    
    const { html, text } = generateOrderConfirmationEmail(order, customer, items, address);
    
    const mailOptions = {
      from: {
        name: brandingConfig.companyName,
        address: brandingConfig.companyEmail
      },
      to: {
        name: `${customer.first_name} ${customer.last_name}`,
        address: customer.email
      },
      subject: `Order Confirmation - ${order.order_number} | ${brandingConfig.companyName}`,
      html: html,
      text: text,
      headers: {
        'X-Order-Number': order.order_number,
        'X-Customer-ID': customer.id || customer.user_id,
        'X-Email-Type': 'order-confirmation'
      }
    };

    const result = await queueEmail(mailOptions);
    
    if (result.success) {
      console.log(`✅ Order confirmation email queued successfully for ${customer.email}`);
      return {
        success: true,
        messageId: result.messageId,
        type: 'order-confirmation'
      };
    } else {
      console.error(`❌ Failed to queue order confirmation email: ${result.error}`);
      return {
        success: false,
        error: result.error,
        type: 'order-confirmation'
      };
    }
  } catch (error) {
    console.error('Order confirmation email error:', error);
    return {
      success: false,
      error: error.message,
      type: 'order-confirmation'
    };
  }
}

/**
 * Send order status update email
 * Requirements: 12.3
 */
async function sendOrderStatusEmail(order, customer, newStatus, previousStatus, notes = null) {
  try {
    console.log(`📧 Preparing order status email for order ${order.order_number}: ${previousStatus} → ${newStatus}`);
    
    const { html, text } = generateOrderStatusEmail(order, customer, newStatus, previousStatus, notes);
    
    const mailOptions = {
      from: {
        name: brandingConfig.companyName,
        address: brandingConfig.companyEmail
      },
      to: {
        name: `${customer.first_name} ${customer.last_name}`,
        address: customer.email
      },
      subject: `Order Update - ${order.order_number} | ${brandingConfig.companyName}`,
      html: html,
      text: text,
      headers: {
        'X-Order-Number': order.order_number,
        'X-Customer-ID': customer.id || customer.user_id,
        'X-Email-Type': 'order-status-update',
        'X-New-Status': newStatus,
        'X-Previous-Status': previousStatus
      }
    };

    const result = await queueEmail(mailOptions);
    
    if (result.success) {
      console.log(`✅ Order status email queued successfully for ${customer.email}`);
      return {
        success: true,
        messageId: result.messageId,
        type: 'order-status-update',
        status: newStatus
      };
    } else {
      console.error(`❌ Failed to queue order status email: ${result.error}`);
      return {
        success: false,
        error: result.error,
        type: 'order-status-update'
      };
    }
  } catch (error) {
    console.error('Order status email error:', error);
    return {
      success: false,
      error: error.message,
      type: 'order-status-update'
    };
  }
}

/**
 * Send payment confirmation email
 * Requirements: 12.2
 */
async function sendPaymentConfirmationEmail(order, customer, paymentDetails) {
  try {
    console.log(`📧 Preparing payment confirmation email for order ${order.order_number}`);
    
    const { html, text } = generatePaymentConfirmationEmail(order, customer, paymentDetails);
    
    const mailOptions = {
      from: {
        name: brandingConfig.companyName,
        address: brandingConfig.companyEmail
      },
      to: {
        name: `${customer.first_name} ${customer.last_name}`,
        address: customer.email
      },
      subject: `Payment Confirmed - ${order.order_number} | ${brandingConfig.companyName}`,
      html: html,
      text: text,
      headers: {
        'X-Order-Number': order.order_number,
        'X-Customer-ID': customer.id || customer.user_id,
        'X-Email-Type': 'payment-confirmation',
        'X-Transaction-ID': paymentDetails.transaction_id
      }
    };

    const result = await queueEmail(mailOptions);
    
    if (result.success) {
      console.log(`✅ Payment confirmation email queued successfully for ${customer.email}`);
      return {
        success: true,
        messageId: result.messageId,
        type: 'payment-confirmation'
      };
    } else {
      console.error(`❌ Failed to queue payment confirmation email: ${result.error}`);
      return {
        success: false,
        error: result.error,
        type: 'payment-confirmation'
      };
    }
  } catch (error) {
    console.error('Payment confirmation email error:', error);
    return {
      success: false,
      error: error.message,
      type: 'payment-confirmation'
    };
  }
}

/**
 * Send COD delivery confirmation email
 * Requirements: 12.3
 */
async function sendCODDeliveryEmail(order, customer, codDetails) {
  try {
    console.log(`📧 Preparing COD delivery email for order ${order.order_number}`);
    
    const { html, text } = generateCODDeliveryEmail(order, customer, codDetails);
    
    const mailOptions = {
      from: {
        name: brandingConfig.companyName,
        address: brandingConfig.companyEmail
      },
      to: {
        name: `${customer.first_name} ${customer.last_name}`,
        address: customer.email
      },
      subject: `COD Payment Collected - ${order.order_number} | ${brandingConfig.companyName}`,
      html: html,
      text: text,
      headers: {
        'X-Order-Number': order.order_number,
        'X-Customer-ID': customer.id || customer.user_id,
        'X-Email-Type': 'cod-delivery-confirmation',
        'X-Collection-Amount': codDetails.collection_amount
      }
    };

    const result = await queueEmail(mailOptions);
    
    if (result.success) {
      console.log(`✅ COD delivery email queued successfully for ${customer.email}`);
      return {
        success: true,
        messageId: result.messageId,
        type: 'cod-delivery-confirmation'
      };
    } else {
      console.error(`❌ Failed to queue COD delivery email: ${result.error}`);
      return {
        success: false,
        error: result.error,
        type: 'cod-delivery-confirmation'
      };
    }
  } catch (error) {
    console.error('COD delivery email error:', error);
    return {
      success: false,
      error: error.message,
      type: 'cod-delivery-confirmation'
    };
  }
}

/**
 * Send welcome email for new customers
 */
async function sendWelcomeEmail(customer) {
  try {
    console.log(`📧 Preparing welcome email for new customer ${customer.email}`);
    
    const mailOptions = {
      from: {
        name: brandingConfig.companyName,
        address: brandingConfig.companyEmail
      },
      to: {
        name: `${customer.first_name} ${customer.last_name}`,
        address: customer.email
      },
      subject: `Welcome to ${brandingConfig.companyName}! 💄`,
      html: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <div style="background: linear-gradient(135deg, #E91E63 0%, #F8BBD9 100%); padding: 30px; text-align: center; color: white;">
            <h1>Welcome to ${brandingConfig.companyName}!</h1>
            <p>Your beauty journey starts here</p>
          </div>
          <div style="padding: 30px;">
            <h2>Hello ${emailUtils.sanitizeContent(customer.first_name)}!</h2>
            <p>Thank you for joining ${brandingConfig.companyName}. We're excited to help you discover amazing cosmetics and beauty products.</p>
            <p>As a welcome gift, use code <strong>WELCOME10</strong> for 10% off your first order!</p>
            <div style="text-align: center; margin: 30px 0;">
              <a href="${brandingConfig.websiteUrl}" style="background: #E91E63; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px;">Start Shopping</a>
            </div>
            <p>Best regards,<br>The ${brandingConfig.companyName} Team</p>
          </div>
        </div>
      `,
      text: `
Welcome to ${brandingConfig.companyName}!

Hello ${customer.first_name}!

Thank you for joining ${brandingConfig.companyName}. We're excited to help you discover amazing cosmetics and beauty products.

As a welcome gift, use code WELCOME10 for 10% off your first order!

Start shopping: ${brandingConfig.websiteUrl}

Best regards,
The ${brandingConfig.companyName} Team
      `,
      headers: {
        'X-Customer-ID': customer.id,
        'X-Email-Type': 'welcome'
      }
    };

    const result = await queueEmail(mailOptions);
    
    if (result.success) {
      console.log(`✅ Welcome email queued successfully for ${customer.email}`);
      return {
        success: true,
        messageId: result.messageId,
        type: 'welcome'
      };
    } else {
      console.error(`❌ Failed to queue welcome email: ${result.error}`);
      return {
        success: false,
        error: result.error,
        type: 'welcome'
      };
    }
  } catch (error) {
    console.error('Welcome email error:', error);
    return {
      success: false,
      error: error.message,
      type: 'welcome'
    };
  }
}

/**
 * Send password reset email
 */
async function sendPasswordResetEmail(customer, resetToken) {
  try {
    console.log(`📧 Preparing password reset email for ${customer.email}`);
    
    const resetUrl = `${brandingConfig.websiteUrl}/reset-password?token=${resetToken}`;
    
    const mailOptions = {
      from: {
        name: brandingConfig.companyName,
        address: brandingConfig.companyEmail
      },
      to: {
        name: `${customer.first_name} ${customer.last_name}`,
        address: customer.email
      },
      subject: `Password Reset Request | ${brandingConfig.companyName}`,
      html: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <div style="background: #4A4A4A; padding: 30px; text-align: center; color: white;">
            <h1>Password Reset Request</h1>
          </div>
          <div style="padding: 30px;">
            <h2>Hello ${emailUtils.sanitizeContent(customer.first_name)},</h2>
            <p>We received a request to reset your password for your ${brandingConfig.companyName} account.</p>
            <p>Click the button below to reset your password:</p>
            <div style="text-align: center; margin: 30px 0;">
              <a href="${resetUrl}" style="background: #E91E63; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px;">Reset Password</a>
            </div>
            <p>This link will expire in 1 hour for security reasons.</p>
            <p>If you didn't request this password reset, please ignore this email.</p>
            <p>Best regards,<br>The ${brandingConfig.companyName} Team</p>
          </div>
        </div>
      `,
      text: `
Password Reset Request - ${brandingConfig.companyName}

Hello ${customer.first_name},

We received a request to reset your password for your ${brandingConfig.companyName} account.

Reset your password: ${resetUrl}

This link will expire in 1 hour for security reasons.

If you didn't request this password reset, please ignore this email.

Best regards,
The ${brandingConfig.companyName} Team
      `,
      headers: {
        'X-Customer-ID': customer.id,
        'X-Email-Type': 'password-reset'
      }
    };

    const result = await queueEmail(mailOptions);
    
    if (result.success) {
      console.log(`✅ Password reset email queued successfully for ${customer.email}`);
      return {
        success: true,
        messageId: result.messageId,
        type: 'password-reset'
      };
    } else {
      console.error(`❌ Failed to queue password reset email: ${result.error}`);
      return {
        success: false,
        error: result.error,
        type: 'password-reset'
      };
    }
  } catch (error) {
    console.error('Password reset email error:', error);
    return {
      success: false,
      error: error.message,
      type: 'password-reset'
    };
  }
}

/**
 * Batch send multiple emails with error handling
 * Requirements: 12.5
 */
async function sendBatchEmails(emailJobs) {
  const results = [];
  
  for (const job of emailJobs) {
    try {
      let result;
      
      switch (job.type) {
        case 'order-confirmation':
          result = await sendOrderConfirmationEmail(job.order, job.customer, job.items, job.address);
          break;
        case 'order-status-update':
          result = await sendOrderStatusEmail(job.order, job.customer, job.newStatus, job.previousStatus, job.notes);
          break;
        case 'payment-confirmation':
          result = await sendPaymentConfirmationEmail(job.order, job.customer, job.paymentDetails);
          break;
        case 'cod-delivery':
          result = await sendCODDeliveryEmail(job.order, job.customer, job.codDetails);
          break;
        case 'welcome':
          result = await sendWelcomeEmail(job.customer);
          break;
        case 'password-reset':
          result = await sendPasswordResetEmail(job.customer, job.resetToken);
          break;
        default:
          result = { success: false, error: `Unknown email type: ${job.type}` };
      }
      
      results.push({
        ...job,
        result
      });
    } catch (error) {
      results.push({
        ...job,
        result: { success: false, error: error.message }
      });
    }
  }
  
  const successful = results.filter(r => r.result.success).length;
  const failed = results.filter(r => !r.result.success).length;
  
  console.log(`📊 Batch email results: ${successful} successful, ${failed} failed`);
  
  return {
    total: results.length,
    successful,
    failed,
    results
  };
}

/**
 * Email service health check
 */
async function healthCheck() {
  try {
    const { verifyEmailConfig } = require('../config/email');
    const isHealthy = await verifyEmailConfig();
    
    return {
      healthy: isHealthy,
      timestamp: new Date().toISOString(),
      service: 'email'
    };
  } catch (error) {
    return {
      healthy: false,
      error: error.message,
      timestamp: new Date().toISOString(),
      service: 'email'
    };
  }
}

module.exports = {
  sendOrderConfirmationEmail,
  sendOrderStatusEmail,
  sendPaymentConfirmationEmail,
  sendCODDeliveryEmail,
  sendWelcomeEmail,
  sendPasswordResetEmail,
  sendBatchEmails,
  healthCheck
};