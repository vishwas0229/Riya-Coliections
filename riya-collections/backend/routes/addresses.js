const express = require('express');
const router = express.Router();
const { secureExecuteQuery, secureExecuteTransactionCallback } = require('../middleware/database-security');
const { authenticateToken } = require('../middleware/auth');
const { validationMiddleware: validation } = require('../middleware/validation');
const { validationResult } = require('express-validator');

/**
 * Address Management Routes
 * 
 * This module handles all address-related operations including:
 * - Creating new addresses
 * - Retrieving user addresses
 * - Updating existing addresses
 * - Deleting addresses
 * - Setting default addresses
 * 
 * Requirements: 3.5 - Address management for checkout
 */

/**
 * GET /api/addresses
 * Get all addresses for authenticated user
 * Requires authentication
 */
router.get('/', 
  authenticateToken,
  async (req, res) => {
    try {
      const userId = req.user.id;

      const addresses = await secureExecuteQuery(
        `SELECT id, type, first_name, last_name, address_line1, address_line2,
                city, state, postal_code, country, phone, is_default,
                created_at, updated_at
         FROM addresses 
         WHERE user_id = ?
         ORDER BY is_default DESC, created_at DESC`,
        [userId]
      );

      res.json({
        success: true,
        data: {
          addresses: addresses.map(address => ({
            id: address.id,
            type: address.type,
            firstName: address.first_name,
            lastName: address.last_name,
            addressLine1: address.address_line1,
            addressLine2: address.address_line2,
            city: address.city,
            state: address.state,
            postalCode: address.postal_code,
            country: address.country,
            phone: address.phone,
            isDefault: Boolean(address.is_default),
            createdAt: address.created_at,
            updatedAt: address.updated_at
          }))
        }
      });

    } catch (error) {
      console.error('Get addresses error:', error);
      res.status(500).json({
        success: false,
        error: 'Failed to retrieve addresses'
      });
    }
  }
);

/**
 * POST /api/addresses
 * Create a new address for authenticated user
 * Requires authentication
 */
router.post('/', 
  authenticateToken,
  validation.addressCreation,
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({
          success: false,
          error: 'Validation failed',
          details: errors.array()
        });
      }

      const userId = req.user.id;
      const {
        type = 'home',
        firstName,
        lastName,
        addressLine1,
        addressLine2,
        city,
        state,
        postalCode,
        country = 'India',
        phone,
        isDefault = false
      } = req.body;

      await secureExecuteTransactionCallback(async (connection) => {
        // If this is being set as default, unset other defaults
        if (isDefault) {
          await connection.execute(
            `UPDATE addresses SET is_default = false WHERE user_id = ?`,
            [userId]
          );
        }

        // Create the new address
        const result = await connection.execute(
          `INSERT INTO addresses (
             user_id, type, first_name, last_name, address_line1, address_line2,
             city, state, postal_code, country, phone, is_default
           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [
            userId, type, firstName, lastName, addressLine1, addressLine2,
            city, state, postalCode, country, phone, isDefault
          ]
        );

        const addressId = result[0].insertId;

        // Get the created address
        const addressResult = await connection.execute(
          `SELECT id, type, first_name, last_name, address_line1, address_line2,
                  city, state, postal_code, country, phone, is_default,
                  created_at, updated_at
           FROM addresses 
           WHERE id = ?`,
          [addressId]
        );

        const address = addressResult[0][0];

        res.status(201).json({
          success: true,
          message: 'Address created successfully',
          data: {
            address: {
              id: address.id,
              type: address.type,
              firstName: address.first_name,
              lastName: address.last_name,
              addressLine1: address.address_line1,
              addressLine2: address.address_line2,
              city: address.city,
              state: address.state,
              postalCode: address.postal_code,
              country: address.country,
              phone: address.phone,
              isDefault: Boolean(address.is_default),
              createdAt: address.created_at,
              updatedAt: address.updated_at
            }
          }
        });
      });

    } catch (error) {
      console.error('Create address error:', error);
      res.status(500).json({
        success: false,
        error: 'Failed to create address'
      });
    }
  }
);

/**
 * PUT /api/addresses/:id
 * Update an existing address
 * Requires authentication
 */
router.put('/:id', 
  authenticateToken,
  validation.addressUpdate,
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({
          success: false,
          error: 'Validation failed',
          details: errors.array()
        });
      }

      const userId = req.user.id;
      const addressId = req.params.id;
      const {
        type,
        firstName,
        lastName,
        addressLine1,
        addressLine2,
        city,
        state,
        postalCode,
        country,
        phone,
        isDefault
      } = req.body;

      await secureExecuteTransactionCallback(async (connection) => {
        // Verify address belongs to user
        const existingResult = await connection.execute(
          `SELECT id FROM addresses WHERE id = ? AND user_id = ?`,
          [addressId, userId]
        );

        if (existingResult[0].length === 0) {
          throw new Error('Address not found or access denied');
        }

        // If this is being set as default, unset other defaults
        if (isDefault) {
          await connection.execute(
            `UPDATE addresses SET is_default = false WHERE user_id = ? AND id != ?`,
            [userId, addressId]
          );
        }

        // Update the address
        await connection.execute(
          `UPDATE addresses SET
             type = ?, first_name = ?, last_name = ?, address_line1 = ?, address_line2 = ?,
             city = ?, state = ?, postal_code = ?, country = ?, phone = ?, is_default = ?,
             updated_at = CURRENT_TIMESTAMP
           WHERE id = ? AND user_id = ?`,
          [
            type, firstName, lastName, addressLine1, addressLine2,
            city, state, postalCode, country, phone, isDefault,
            addressId, userId
          ]
        );

        // Get the updated address
        const addressResult = await connection.execute(
          `SELECT id, type, first_name, last_name, address_line1, address_line2,
                  city, state, postal_code, country, phone, is_default,
                  created_at, updated_at
           FROM addresses 
           WHERE id = ?`,
          [addressId]
        );

        const address = addressResult[0][0];

        res.json({
          success: true,
          message: 'Address updated successfully',
          data: {
            address: {
              id: address.id,
              type: address.type,
              firstName: address.first_name,
              lastName: address.last_name,
              addressLine1: address.address_line1,
              addressLine2: address.address_line2,
              city: address.city,
              state: address.state,
              postalCode: address.postal_code,
              country: address.country,
              phone: address.phone,
              isDefault: Boolean(address.is_default),
              createdAt: address.created_at,
              updatedAt: address.updated_at
            }
          }
        });
      });

    } catch (error) {
      console.error('Update address error:', error);
      res.status(400).json({
        success: false,
        error: error.message || 'Failed to update address'
      });
    }
  }
);

/**
 * DELETE /api/addresses/:id
 * Delete an address
 * Requires authentication
 */
router.delete('/:id', 
  authenticateToken,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const addressId = req.params.id;

      await secureExecuteTransactionCallback(async (connection) => {
        // Verify address belongs to user and get details
        const existingResult = await connection.execute(
          `SELECT id, is_default FROM addresses WHERE id = ? AND user_id = ?`,
          [addressId, userId]
        );

        if (existingResult[0].length === 0) {
          throw new Error('Address not found or access denied');
        }

        const address = existingResult[0][0];

        // Check if address is used in any orders
        const orderResult = await connection.execute(
          `SELECT COUNT(*) as order_count FROM orders WHERE shipping_address_id = ?`,
          [addressId]
        );

        if (orderResult[0][0].order_count > 0) {
          throw new Error('Cannot delete address that has been used in orders');
        }

        // Delete the address
        await connection.execute(
          `DELETE FROM addresses WHERE id = ? AND user_id = ?`,
          [addressId, userId]
        );

        // If this was the default address, set another one as default
        if (address.is_default) {
          await connection.execute(
            `UPDATE addresses SET is_default = true 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1`,
            [userId]
          );
        }

        res.json({
          success: true,
          message: 'Address deleted successfully'
        });
      });

    } catch (error) {
      console.error('Delete address error:', error);
      res.status(400).json({
        success: false,
        error: error.message || 'Failed to delete address'
      });
    }
  }
);

/**
 * PUT /api/addresses/:id/default
 * Set an address as default
 * Requires authentication
 */
router.put('/:id/default', 
  authenticateToken,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const addressId = req.params.id;

      await secureExecuteTransactionCallback(async (connection) => {
        // Verify address belongs to user
        const existingResult = await connection.execute(
          `SELECT id FROM addresses WHERE id = ? AND user_id = ?`,
          [addressId, userId]
        );

        if (existingResult[0].length === 0) {
          throw new Error('Address not found or access denied');
        }

        // Unset all defaults for this user
        await connection.execute(
          `UPDATE addresses SET is_default = false WHERE user_id = ?`,
          [userId]
        );

        // Set this address as default
        await connection.execute(
          `UPDATE addresses SET is_default = true, updated_at = CURRENT_TIMESTAMP 
           WHERE id = ? AND user_id = ?`,
          [addressId, userId]
        );

        res.json({
          success: true,
          message: 'Default address updated successfully'
        });
      });

    } catch (error) {
      console.error('Set default address error:', error);
      res.status(400).json({
        success: false,
        error: error.message || 'Failed to set default address'
      });
    }
  }
);

module.exports = router;