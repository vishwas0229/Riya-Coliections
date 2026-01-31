const Razorpay = require('razorpay');

/**
 * Razorpay Configuration
 * 
 * This module provides Razorpay SDK configuration and utility functions
 * for payment processing in the Riya Collections e-commerce platform.
 * 
 * Requirements: 4.1
 */

// Validate required environment variables
const validateRazorpayConfig = () => {
  const requiredVars = ['RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET'];
  const missingVars = requiredVars.filter(varName => !process.env[varName]);
  
  if (missingVars.length > 0) {
    throw new Error(`Missing required Razorpay environment variables: ${missingVars.join(', ')}`);
  }
  
  // Validate key format (allow test keys)
  if (!process.env.RAZORPAY_KEY_ID.startsWith('rzp_')) {
    throw new Error('Invalid Razorpay Key ID format. Must start with "rzp_"');
  }
  
  console.log('✅ Razorpay configuration validated successfully');
};

// Initialize Razorpay instance
let razorpayInstance = null;

const initializeRazorpay = () => {
  try {
    validateRazorpayConfig();
    
    razorpayInstance = new Razorpay({
      key_id: process.env.RAZORPAY_KEY_ID,
      key_secret: process.env.RAZORPAY_KEY_SECRET,
    });
    
    console.log('🔑 Razorpay SDK initialized successfully');
    return razorpayInstance;
  } catch (error) {
    console.error('❌ Failed to initialize Razorpay:', error.message);
    throw error;
  }
};

// Get Razorpay instance (singleton pattern)
const getRazorpayInstance = () => {
  if (!razorpayInstance) {
    razorpayInstance = initializeRazorpay();
  }
  return razorpayInstance;
};

// Utility functions for payment processing
const paymentUtils = {
  /**
   * Convert amount from rupees to paise (Razorpay uses paise)
   * @param {number} amount - Amount in rupees
   * @returns {number} - Amount in paise
   */
  convertToPaise: (amount) => {
    return Math.round(parseFloat(amount) * 100);
  },

  /**
   * Convert amount from paise to rupees
   * @param {number} amount - Amount in paise
   * @returns {number} - Amount in rupees
   */
  convertToRupees: (amount) => {
    return parseFloat(amount) / 100;
  },

  /**
   * Generate receipt ID for Razorpay order
   * @param {string} orderNumber - Order number
   * @returns {string} - Receipt ID
   */
  generateReceiptId: (orderNumber) => {
    return `receipt_${orderNumber}_${Date.now()}`;
  },

  /**
   * Validate Razorpay payment ID format
   * @param {string} paymentId - Payment ID to validate
   * @returns {boolean} - True if valid format
   */
  isValidPaymentId: (paymentId) => {
    return /^pay_[a-zA-Z0-9]+$/.test(paymentId);
  },

  /**
   * Validate Razorpay order ID format
   * @param {string} orderId - Order ID to validate
   * @returns {boolean} - True if valid format
   */
  isValidOrderId: (orderId) => {
    return /^order_[a-zA-Z0-9]+$/.test(orderId);
  },

  /**
   * Validate Razorpay signature format
   * @param {string} signature - Signature to validate
   * @returns {boolean} - True if valid format
   */
  isValidSignature: (signature) => {
    return /^[a-f0-9]{64}$/.test(signature);
  },

  /**
   * Get supported payment methods
   * @returns {Array} - Array of supported payment methods
   */
  getSupportedMethods: () => {
    return [
      'card',
      'netbanking', 
      'wallet',
      'upi',
      'emi'
    ];
  },

  /**
   * Get supported currencies
   * @returns {Array} - Array of supported currencies
   */
  getSupportedCurrencies: () => {
    return ['INR']; // Razorpay primarily supports INR
  },

  /**
   * Format payment method display name
   * @param {string} method - Payment method code
   * @returns {string} - Display name
   */
  formatPaymentMethod: (method) => {
    const methodNames = {
      'card': 'Credit/Debit Card',
      'netbanking': 'Net Banking',
      'wallet': 'Digital Wallet',
      'upi': 'UPI',
      'emi': 'EMI'
    };
    return methodNames[method] || method;
  }
};

// Test Razorpay connection
const testRazorpayConnection = async () => {
  try {
    const razorpay = getRazorpayInstance();
    
    // Test by fetching a non-existent payment (this will return a 400 error but confirms API connectivity)
    try {
      await razorpay.payments.fetch('pay_test_connection');
    } catch (error) {
      // Expected error for non-existent payment ID
      if (error.statusCode === 400 && error.error && error.error.code === 'BAD_REQUEST_ERROR') {
        console.log('✅ Razorpay API connection test successful');
        return true;
      }
      throw error;
    }
  } catch (error) {
    console.error('❌ Razorpay connection test failed:', error.message);
    return false;
  }
};

module.exports = {
  initializeRazorpay,
  getRazorpayInstance,
  validateRazorpayConfig,
  testRazorpayConnection,
  paymentUtils
};