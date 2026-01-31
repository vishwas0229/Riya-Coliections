const crypto = require('crypto');

/**
 * Encryption Utilities for Riya Collections
 * 
 * This module provides encryption and decryption utilities for sensitive data
 * including payment information, personal data, and other confidential information.
 * 
 * Requirements: 4.5, 6.5, 9.3
 */

// Encryption configuration
const ALGORITHM = 'aes-256-cbc';
const KEY_LENGTH = 32; // 256 bits
const IV_LENGTH = 16; // 128 bits
const SALT_LENGTH = 32; // 256 bits

/**
 * Generate a secure encryption key from a password using PBKDF2
 * @param {string} password - The password to derive key from
 * @param {Buffer} salt - The salt for key derivation
 * @returns {Buffer} - The derived key
 */
const deriveKey = (password, salt) => {
  return crypto.pbkdf2Sync(password, salt, 100000, KEY_LENGTH, 'sha256');
};

/**
 * Get encryption key from environment or generate one
 * @returns {string} - The encryption key
 */
const getEncryptionKey = () => {
  const key = process.env.ENCRYPTION_KEY;
  if (!key) {
    throw new Error('ENCRYPTION_KEY environment variable is required');
  }
  if (key.length < 32) {
    throw new Error('ENCRYPTION_KEY must be at least 32 characters long');
  }
  return key;
};

/**
 * Encrypt sensitive data using AES-256-CBC
 * @param {string} plaintext - The data to encrypt
 * @param {string} [customKey] - Optional custom encryption key
 * @returns {string} - Base64 encoded encrypted data with salt and IV
 */
const encrypt = (plaintext, customKey = null) => {
  try {
    if (typeof plaintext !== 'string') {
      throw new Error('Plaintext must be a string');
    }

    if (plaintext.trim().length === 0) {
      throw new Error('Cannot encrypt empty or whitespace-only string');
    }

    // Generate random salt and IV
    const salt = crypto.randomBytes(SALT_LENGTH);
    const iv = crypto.randomBytes(IV_LENGTH);
    
    // Derive key from password and salt
    const password = customKey || getEncryptionKey();
    const key = deriveKey(password, salt);
    
    // Create cipher
    const cipher = crypto.createCipheriv(ALGORITHM, key, iv);
    
    // Encrypt the data
    let encrypted = cipher.update(plaintext, 'utf8', 'hex');
    encrypted += cipher.final('hex');
    
    // Combine salt, IV, and encrypted data
    const combined = salt.toString('hex') + ':' + iv.toString('hex') + ':' + encrypted;
    
    // Return base64 encoded result
    return Buffer.from(combined).toString('base64');
    
  } catch (error) {
    throw error; // Re-throw the original error for better debugging
  }
};

/**
 * Decrypt sensitive data using AES-256-CBC
 * @param {string} encryptedData - Base64 encoded encrypted data
 * @param {string} [customKey] - Optional custom encryption key
 * @returns {string} - The decrypted plaintext
 */
const decrypt = (encryptedData, customKey = null) => {
  try {
    if (typeof encryptedData !== 'string') {
      throw new Error('Encrypted data must be a string');
    }

    if (encryptedData.length === 0) {
      throw new Error('Cannot decrypt empty string');
    }

    // Decode from base64
    const combined = Buffer.from(encryptedData, 'base64').toString();
    
    // Split components
    const parts = combined.split(':');
    if (parts.length !== 3) {
      throw new Error('Invalid encrypted data format');
    }
    
    const salt = Buffer.from(parts[0], 'hex');
    const iv = Buffer.from(parts[1], 'hex');
    const encrypted = parts[2];
    
    // Derive key from password and salt
    const password = customKey || getEncryptionKey();
    const key = deriveKey(password, salt);
    
    // Create decipher
    const decipher = crypto.createDecipheriv(ALGORITHM, key, iv);
    
    // Decrypt the data
    let decrypted = decipher.update(encrypted, 'hex', 'utf8');
    decrypted += decipher.final('utf8');
    
    return decrypted;
    
  } catch (error) {
    throw new Error('Failed to decrypt data');
  }
};

/**
 * Hash sensitive data using SHA-256 (one-way)
 * @param {string} data - The data to hash
 * @param {string} [salt] - Optional salt for hashing
 * @returns {string} - The hashed data in hex format
 */
const hash = (data, salt = '') => {
  try {
    if (typeof data !== 'string') {
      throw new Error('Data must be a string');
    }

    const hash = crypto.createHash('sha256');
    hash.update(data + salt);
    return hash.digest('hex');
    
  } catch (error) {
    console.error('Hashing error:', error.message);
    throw new Error('Failed to hash data');
  }
};

/**
 * Generate a secure random token
 * @param {number} [length=32] - The length of the token in bytes
 * @returns {string} - The random token in hex format
 */
const generateToken = (length = 32) => {
  try {
    if (!Number.isInteger(length) || length <= 0) {
      throw new Error('Length must be a positive integer');
    }

    return crypto.randomBytes(length).toString('hex');
    
  } catch (error) {
    console.error('Token generation error:', error.message);
    throw new Error('Failed to generate token');
  }
};

/**
 * Encrypt payment information specifically
 * @param {Object} paymentData - Payment data to encrypt
 * @returns {Object} - Object with encrypted fields
 */
const encryptPaymentData = (paymentData) => {
  try {
    const encrypted = {};
    
    // Fields that should be encrypted
    const sensitiveFields = [
      'razorpay_payment_id',
      'razorpay_order_id', 
      'razorpay_signature',
      'transaction_id'
    ];
    
    // Copy all fields
    Object.assign(encrypted, paymentData);
    
    // Encrypt sensitive fields
    for (const field of sensitiveFields) {
      if (paymentData[field] && typeof paymentData[field] === 'string') {
        encrypted[field] = encrypt(paymentData[field]);
      }
    }
    
    return encrypted;
    
  } catch (error) {
    console.error('Payment encryption error:', error.message);
    throw new Error('Failed to encrypt payment data');
  }
};

/**
 * Decrypt payment information specifically
 * @param {Object} encryptedPaymentData - Encrypted payment data
 * @returns {Object} - Object with decrypted fields
 */
const decryptPaymentData = (encryptedPaymentData) => {
  try {
    const decrypted = {};
    
    // Fields that should be decrypted
    const sensitiveFields = [
      'razorpay_payment_id',
      'razorpay_order_id',
      'razorpay_signature', 
      'transaction_id'
    ];
    
    // Copy all fields
    Object.assign(decrypted, encryptedPaymentData);
    
    // Decrypt sensitive fields
    for (const field of sensitiveFields) {
      if (encryptedPaymentData[field] && typeof encryptedPaymentData[field] === 'string') {
        try {
          decrypted[field] = decrypt(encryptedPaymentData[field]);
        } catch (decryptError) {
          // If decryption fails, the field might not be encrypted (backward compatibility)
          console.warn(`Failed to decrypt field ${field}, keeping original value`);
          decrypted[field] = encryptedPaymentData[field];
        }
      }
    }
    
    return decrypted;
    
  } catch (error) {
    console.error('Payment decryption error:', error.message);
    throw new Error('Failed to decrypt payment data');
  }
};

/**
 * Encrypt personal information
 * @param {Object} personalData - Personal data to encrypt
 * @returns {Object} - Object with encrypted fields
 */
const encryptPersonalData = (personalData) => {
  try {
    const encrypted = {};
    
    // Fields that should be encrypted for personal data
    const sensitiveFields = [
      'phone',
      'address_line1',
      'address_line2'
    ];
    
    // Copy all fields
    Object.assign(encrypted, personalData);
    
    // Encrypt sensitive fields
    for (const field of sensitiveFields) {
      if (personalData[field] && typeof personalData[field] === 'string') {
        encrypted[field] = encrypt(personalData[field]);
      }
    }
    
    return encrypted;
    
  } catch (error) {
    console.error('Personal data encryption error:', error.message);
    throw new Error('Failed to encrypt personal data');
  }
};

/**
 * Decrypt personal information
 * @param {Object} encryptedPersonalData - Encrypted personal data
 * @returns {Object} - Object with decrypted fields
 */
const decryptPersonalData = (encryptedPersonalData) => {
  try {
    const decrypted = {};
    
    // Fields that should be decrypted
    const sensitiveFields = [
      'phone',
      'address_line1', 
      'address_line2'
    ];
    
    // Copy all fields
    Object.assign(decrypted, encryptedPersonalData);
    
    // Decrypt sensitive fields
    for (const field of sensitiveFields) {
      if (encryptedPersonalData[field] && typeof encryptedPersonalData[field] === 'string') {
        try {
          decrypted[field] = decrypt(encryptedPersonalData[field]);
        } catch (decryptError) {
          // If decryption fails, the field might not be encrypted (backward compatibility)
          console.warn(`Failed to decrypt field ${field}, keeping original value`);
          decrypted[field] = encryptedPersonalData[field];
        }
      }
    }
    
    return decrypted;
    
  } catch (error) {
    console.error('Personal data decryption error:', error.message);
    throw new Error('Failed to decrypt personal data');
  }
};

/**
 * Validate encryption key strength
 * @param {string} key - The key to validate
 * @returns {boolean} - True if key is strong enough
 */
const validateEncryptionKey = (key) => {
  if (typeof key !== 'string') {
    return false;
  }
  
  if (key.length < 32) {
    return false;
  }
  
  // Check for sufficient entropy (basic check)
  const uniqueChars = new Set(key).size;
  if (uniqueChars < 16) {
    return false;
  }
  
  return true;
};

module.exports = {
  encrypt,
  decrypt,
  hash,
  generateToken,
  encryptPaymentData,
  decryptPaymentData,
  encryptPersonalData,
  decryptPersonalData,
  validateEncryptionKey,
  // Export constants for testing
  ALGORITHM,
  KEY_LENGTH,
  IV_LENGTH,
  SALT_LENGTH
};