const nodemailer = require('nodemailer');

/**
 * Email Service Configuration
 * 
 * This module provides email service configuration and utilities for sending
 * transactional emails including order confirmations, status updates, and notifications.
 * 
 * Requirements: 12.1, 12.2, 12.3, 12.4
 */

// Email configuration from environment variables
const emailConfig = {
  host: process.env.SMTP_HOST || 'smtp.gmail.com',
  port: parseInt(process.env.SMTP_PORT) || 587,
  secure: process.env.SMTP_SECURE === 'true' || false, // true for 465, false for other ports
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASSWORD
  },
  // Additional options for better reliability
  pool: true, // Use connection pooling
  maxConnections: 5,
  maxMessages: 100,
  rateDelta: 1000, // 1 second
  rateLimit: 5 // Max 5 emails per second
};

// Company branding configuration
const brandingConfig = {
  companyName: 'Riya Collections',
  companyEmail: process.env.COMPANY_EMAIL || 'orders@riyacollections.com',
  supportEmail: process.env.SUPPORT_EMAIL || 'support@riyacollections.com',
  websiteUrl: process.env.WEBSITE_URL || 'https://riyacollections.com',
  logoUrl: process.env.LOGO_URL || 'https://riyacollections.com/assets/logo.png',
  colors: {
    primary: '#E91E63', // Pink
    secondary: '#F8BBD9', // Light Pink
    accent: '#4A4A4A', // Dark Gray
    background: '#FFFFFF',
    text: '#333333'
  }
};

// Create transporter instance
let transporter = null;

/**
 * Initialize email transporter
 */
function createTransporter() {
  if (!emailConfig.auth.user || !emailConfig.auth.pass) {
    if (process.env.NODE_ENV === 'test') {
      // Create test account for testing
      return nodemailer.createTransport({
        host: 'smtp.ethereal.email',
        port: 587,
        secure: false,
        auth: {
          user: 'test@ethereal.email',
          pass: 'test123'
        }
      });
    } else {
      throw new Error('SMTP credentials not configured. Please set SMTP_USER and SMTP_PASSWORD environment variables.');
    }
  }

  return nodemailer.createTransport(emailConfig);
}

/**
 * Get email transporter instance
 */
function getTransporter() {
  if (!transporter) {
    transporter = createTransporter();
  }
  return transporter;
}

/**
 * Verify email configuration
 */
async function verifyEmailConfig() {
  try {
    const testTransporter = getTransporter();
    await testTransporter.verify();
    console.log('✅ Email service configuration verified successfully');
    return true;
  } catch (error) {
    console.error('❌ Email service configuration error:', error.message);
    return false;
  }
}

/**
 * Email utilities
 */
const emailUtils = {
  /**
   * Format currency for display
   */
  formatCurrency: (amount) => {
    return `₹${parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  },

  /**
   * Format date for display
   */
  formatDate: (date) => {
    return new Date(date).toLocaleDateString('en-IN', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  },

  /**
   * Generate tracking URL
   */
  generateTrackingUrl: (orderNumber) => {
    return `${brandingConfig.websiteUrl}/track-order?order=${orderNumber}`;
  },

  /**
   * Generate order details URL
   */
  generateOrderUrl: (orderId) => {
    return `${brandingConfig.websiteUrl}/orders/${orderId}`;
  },

  /**
   * Sanitize email content
   */
  sanitizeContent: (content) => {
    return content
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#x27;');
  }
};

/**
 * Email retry configuration
 */
const retryConfig = {
  maxRetries: 3,
  retryDelay: 1000, // 1 second
  backoffMultiplier: 2 // Exponential backoff
};

/**
 * Send email with retry mechanism
 */
async function sendEmailWithRetry(mailOptions, retries = 0) {
  try {
    const transporter = getTransporter();
    const result = await transporter.sendMail(mailOptions);
    
    console.log(`📧 Email sent successfully to ${mailOptions.to}:`, result.messageId);
    return {
      success: true,
      messageId: result.messageId,
      response: result.response
    };
  } catch (error) {
    console.error(`❌ Email send attempt ${retries + 1} failed:`, error.message);
    
    if (retries < retryConfig.maxRetries) {
      const delay = retryConfig.retryDelay * Math.pow(retryConfig.backoffMultiplier, retries);
      console.log(`⏳ Retrying email send in ${delay}ms...`);
      
      await new Promise(resolve => setTimeout(resolve, delay));
      return sendEmailWithRetry(mailOptions, retries + 1);
    } else {
      console.error(`💥 Email send failed after ${retryConfig.maxRetries + 1} attempts`);
      return {
        success: false,
        error: error.message,
        attempts: retries + 1
      };
    }
  }
}

/**
 * Email queue for rate limiting (simple in-memory queue)
 */
class EmailQueue {
  constructor() {
    this.queue = [];
    this.processing = false;
  }

  async add(mailOptions) {
    return new Promise((resolve, reject) => {
      this.queue.push({
        mailOptions,
        resolve,
        reject,
        timestamp: Date.now()
      });
      
      this.process();
    });
  }

  async process() {
    if (this.processing || this.queue.length === 0) {
      return;
    }

    this.processing = true;

    while (this.queue.length > 0) {
      const emailJob = this.queue.shift();
      
      try {
        const result = await sendEmailWithRetry(emailJob.mailOptions);
        emailJob.resolve(result);
      } catch (error) {
        emailJob.reject(error);
      }

      // Rate limiting - wait between emails
      if (this.queue.length > 0) {
        await new Promise(resolve => setTimeout(resolve, 200)); // 200ms between emails
      }
    }

    this.processing = false;
  }
}

// Global email queue instance
const emailQueue = new EmailQueue();

/**
 * Queue email for sending
 */
async function queueEmail(mailOptions) {
  try {
    return await emailQueue.add(mailOptions);
  } catch (error) {
    console.error('Email queue error:', error);
    throw error;
  }
}

module.exports = {
  getTransporter,
  verifyEmailConfig,
  emailUtils,
  brandingConfig,
  sendEmailWithRetry,
  queueEmail,
  retryConfig
};