const express = require('express');
const router = express.Router();
const crypto = require('crypto');
const { getRazorpayInstance, paymentUtils } = require('../config/razorpay');
const { secureExecuteQuery, secureExecuteTransactionCallback } = require('../middleware/database-security');
const { authenticateToken, authenticateAdmin } = require('../middleware/auth');
const { validationMiddleware: validation } = require('../middleware/validation');
const { validationResult } = require('express-validator');
const { sendPaymentConfirmationEmail, sendCODDeliveryEmail } = require('../utils/email-service');

/**
 * Razorpay Payment Integration Routes
 * 
 * This module handles all payment-related operations including:
 * - Payment order creation (initialization)
 * - Payment verification (callback handling)
 * - Payment status checking
 * - Webhook handling for payment updates
 * - Cash on Delivery processing
 * 
 * Requirements: 4.1, 4.2, 4.3
 */

// Initialize Razorpay instance
let razorpay;
try {
  razorpay = getRazorpayInstance();
} catch (error) {
  if (process.env.NODE_ENV === 'test') {
    // Mock Razorpay for testing
    razorpay = {
      orders: {
        create: async (options) => ({
          id: `order_test_${Date.now()}`,
          amount: options.amount,
          currency: options.currency,
          receipt: options.receipt,
          status: 'created',
          created_at: Math.floor(Date.now() / 1000)
        })
      },
      payments: {
        fetch: async (paymentId) => ({
          id: paymentId,
          amount: 100000, // 1000 rupees in paise
          currency: 'INR',
          status: 'captured',
          method: 'card',
          created_at: Math.floor(Date.now() / 1000)
        })
      }
    };
    console.log('🧪 Using mock Razorpay for testing');
  } else {
    throw error;
  }
}

/**
 * POST /api/payments/razorpay/create
 * Create a Razorpay payment order
 * Requires authentication
 * Requirements: 4.1
 */
router.post('/razorpay/create',
  authenticateToken,
  validation.paymentOrderCreation,
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({
          error: 'Validation failed',
          details: errors.array()
        });
      }

      const userId = req.user.id;
      const { order_id, amount, currency = 'INR' } = req.body;

      // Validate that the order belongs to the user and is in correct state
      const orderResult = await secureExecuteQuery(
        `SELECT id, order_number, total_amount, payment_status, status
         FROM orders 
         WHERE id = ? AND user_id = ? AND payment_status = 'pending'`,
        [order_id, userId]
      );

      if (orderResult.length === 0) {
        return res.status(404).json({
          error: 'Order not found or payment already processed'
        });
      }

      const order = orderResult[0];

      // Verify amount matches order total
      const orderAmount = paymentUtils.convertToPaise(order.total_amount);
      const requestAmount = paymentUtils.convertToPaise(amount);

      if (orderAmount !== requestAmount) {
        return res.status(400).json({
          error: 'Amount mismatch',
          expected: orderAmount / 100,
          received: requestAmount / 100
        });
      }

      // Create Razorpay order
      const razorpayOrderOptions = {
        amount: orderAmount, // Amount in paise
        currency: currency,
        receipt: paymentUtils.generateReceiptId(order.order_number),
        notes: {
          order_id: order.id,
          order_number: order.order_number,
          user_id: userId
        }
      };

      const razorpayOrder = await razorpay.orders.create(razorpayOrderOptions);

      // Update payment record with Razorpay order ID
      await secureExecuteQuery(
        `UPDATE payments 
         SET razorpay_order_id = ?, updated_at = CURRENT_TIMESTAMP
         WHERE order_id = ?`,
        [razorpayOrder.id, order_id]
      );

      // Return payment details for frontend
      res.json({
        message: 'Payment order created successfully',
        payment_order: {
          id: razorpayOrder.id,
          amount: razorpayOrder.amount,
          currency: razorpayOrder.currency,
          receipt: razorpayOrder.receipt,
          status: razorpayOrder.status,
          created_at: razorpayOrder.created_at
        },
        order_details: {
          id: order.id,
          order_number: order.order_number,
          amount: order.total_amount
        },
        razorpay_key: process.env.RAZORPAY_KEY_ID
      });

    } catch (error) {
      console.error('Payment order creation error:', error);
      res.status(500).json({
        error: 'Failed to create payment order',
        message: error.message
      });
    }
  }
);

/**
 * POST /api/payments/razorpay/verify
 * Verify Razorpay payment signature and update order status
 * Requires authentication
 * Requirements: 4.2, 4.3
 */
router.post('/razorpay/verify',
  authenticateToken,
  validation.paymentVerification,
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({
          error: 'Validation failed',
          details: errors.array()
        });
      }

      const userId = req.user.id;
      const { 
        razorpay_order_id, 
        razorpay_payment_id, 
        razorpay_signature,
        order_id 
      } = req.body;

      await secureExecuteTransactionCallback(async (connection) => {
        // Get order and payment details
        const orderResult = await connection.execute(
          `SELECT o.id, o.order_number, o.total_amount, o.user_id, o.status,
                  p.id as payment_id, p.razorpay_order_id, p.payment_status
           FROM orders o
           JOIN payments p ON o.id = p.order_id
           WHERE o.id = ? AND o.user_id = ? AND p.razorpay_order_id = ?`,
          [order_id, userId, razorpay_order_id]
        );

        if (orderResult[0].length === 0) {
          throw new Error('Order not found or invalid payment details');
        }

        const order = orderResult[0][0];

        // Verify payment signature
        const body = razorpay_order_id + "|" + razorpay_payment_id;
        const expectedSignature = crypto
          .createHmac('sha256', process.env.RAZORPAY_KEY_SECRET)
          .update(body.toString())
          .digest('hex');

        const isSignatureValid = expectedSignature === razorpay_signature;

        if (!isSignatureValid) {
          // Update payment status to failed
          await connection.execute(
            `UPDATE payments 
             SET payment_status = 'failed', 
                 razorpay_payment_id = ?,
                 razorpay_signature = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?`,
            [razorpay_payment_id, razorpay_signature, order.payment_id]
          );

          // Update order payment status
          await connection.execute(
            `UPDATE orders 
             SET payment_status = 'failed', updated_at = CURRENT_TIMESTAMP
             WHERE id = ?`,
            [order_id]
          );

          throw new Error('Payment signature verification failed');
        }

        // Fetch payment details from Razorpay to get additional information
        let paymentDetails = null;
        try {
          paymentDetails = await razorpay.payments.fetch(razorpay_payment_id);
        } catch (razorpayError) {
          console.error('Error fetching payment details from Razorpay:', razorpayError);
          // Continue with verification even if we can't fetch details
        }

        // Update payment record with successful payment details
        await connection.execute(
          `UPDATE payments 
           SET payment_status = 'completed',
               razorpay_payment_id = ?,
               razorpay_signature = ?,
               transaction_id = ?,
               updated_at = CURRENT_TIMESTAMP
           WHERE id = ?`,
          [
            razorpay_payment_id, 
            razorpay_signature, 
            paymentDetails?.id || razorpay_payment_id,
            order.payment_id
          ]
        );

        // Update order status to processing and payment status to paid
        await connection.execute(
          `UPDATE orders 
           SET payment_status = 'paid', 
               status = 'processing',
               updated_at = CURRENT_TIMESTAMP
           WHERE id = ?`,
          [order_id]
        );

        // Get updated order details for response
        const updatedOrderResult = await connection.execute(
          `SELECT o.*, p.razorpay_payment_id, p.transaction_id
           FROM orders o
           JOIN payments p ON o.id = p.order_id
           WHERE o.id = ?`,
          [order_id]
        );

        const updatedOrder = updatedOrderResult[0][0];

        res.json({
          message: 'Payment verified successfully',
          payment_status: 'success',
          order: {
            id: updatedOrder.id,
            order_number: updatedOrder.order_number,
            status: updatedOrder.status,
            payment_status: updatedOrder.payment_status,
            total_amount: parseFloat(updatedOrder.total_amount),
            payment_id: updatedOrder.razorpay_payment_id,
            transaction_id: updatedOrder.transaction_id
          },
          payment_details: paymentDetails ? {
            id: paymentDetails.id,
            amount: paymentUtils.convertToRupees(paymentDetails.amount),
            currency: paymentDetails.currency,
            status: paymentDetails.status,
            method: paymentDetails.method,
            created_at: paymentDetails.created_at
          } : null
        });

        // Send payment confirmation email asynchronously
        setImmediate(async () => {
          try {
            // Get customer information
            const customerResult = await secureExecuteQuery(
              `SELECT first_name, last_name, email FROM users WHERE id = ?`,
              [order.user_id]
            );
            
            if (customerResult.length > 0) {
              const customer = customerResult[0];
              
              await sendPaymentConfirmationEmail(
                {
                  id: updatedOrder.id,
                  order_number: updatedOrder.order_number,
                  total_amount: updatedOrder.total_amount
                },
                customer,
                {
                  transaction_id: updatedOrder.transaction_id,
                  method: paymentDetails?.method || 'Online Payment',
                  created_at: new Date()
                }
              );
            }
          } catch (emailError) {
            console.error('Failed to send payment confirmation email:', emailError);
            // Don't fail the payment verification if email fails
          }
        });

        // TODO: Send order confirmation email
        console.log(`Payment verified for order ${order.order_number}, payment ID: ${razorpay_payment_id}`);
      });

    } catch (error) {
      console.error('Payment verification error:', error);
      res.status(400).json({
        error: 'Payment verification failed',
        message: error.message,
        payment_status: 'failed'
      });
    }
  }
);

/**
 * POST /api/payments/cod
 * Process Cash on Delivery order with comprehensive workflow
 * Requires authentication
 * Requirements: 4.4 - Enhanced COD payment processing and management
 */
router.post('/cod',
  authenticateToken,
  validation.codPayment,
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({
          error: 'Validation failed',
          details: errors.array()
        });
      }

      const userId = req.user.id;
      const { order_id, delivery_instructions = null } = req.body;

      await secureExecuteTransactionCallback(async (connection) => {
        // Validate order belongs to user and is in correct state
        const orderResult = await connection.execute(
          `SELECT o.id, o.order_number, o.total_amount, o.payment_method, o.payment_status, o.status,
                  a.city, a.state, a.postal_code, a.phone
           FROM orders o
           JOIN addresses a ON o.shipping_address_id = a.id
           WHERE o.id = ? AND o.user_id = ? AND o.payment_method = 'cod' AND o.payment_status = 'pending'`,
          [order_id, userId]
        );

        if (orderResult[0].length === 0) {
          throw new Error('Order not found or not eligible for COD processing');
        }

        const order = orderResult[0][0];

        // COD-specific business rules validation
        if (order.total_amount > 5000) {
          throw new Error('COD orders are limited to ₹5,000. Please use online payment for higher amounts.');
        }

        // Check if delivery location supports COD
        const codSupportedStates = ['Maharashtra', 'Karnataka', 'Delhi', 'Gujarat', 'Tamil Nadu', 'Uttar Pradesh', 'West Bengal'];
        if (!codSupportedStates.includes(order.state)) {
          throw new Error(`COD is not available in ${order.state}. Please use online payment.`);
        }

        // Update order status to processing (COD orders are confirmed immediately)
        await connection.execute(
          `UPDATE orders 
           SET status = 'processing', 
               notes = COALESCE(CONCAT(COALESCE(notes, ""), ?, "\n"), ?),
               updated_at = CURRENT_TIMESTAMP
           WHERE id = ?`,
          [
            `[${new Date().toISOString()}] COD Order Confirmed - Delivery Instructions: ${delivery_instructions || 'None'}`,
            delivery_instructions ? `COD Order - Delivery Instructions: ${delivery_instructions}` : 'COD Order Confirmed',
            order_id
          ]
        );

        // Update payment record with COD-specific details
        await connection.execute(
          `UPDATE payments 
           SET payment_status = 'pending', 
               transaction_id = ?,
               updated_at = CURRENT_TIMESTAMP
           WHERE order_id = ?`,
          [`COD_${order.order_number}_${Date.now()}`, order_id]
        );

        // Create COD tracking record for delivery and payment collection
        await connection.execute(
          `INSERT INTO cod_tracking (order_id, cod_amount, delivery_instructions, status, created_at)
           VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
           ON DUPLICATE KEY UPDATE 
           cod_amount = VALUES(cod_amount),
           delivery_instructions = VALUES(delivery_instructions),
           status = VALUES(status),
           updated_at = CURRENT_TIMESTAMP`,
          [order_id, order.total_amount, delivery_instructions, 'confirmed']
        );

        res.json({
          message: 'Cash on Delivery order confirmed successfully',
          payment_status: 'confirmed',
          order: {
            id: order.id,
            order_number: order.order_number,
            status: 'processing',
            payment_status: 'pending',
            payment_method: 'cod',
            total_amount: parseFloat(order.total_amount),
            cod_details: {
              amount_to_collect: parseFloat(order.total_amount),
              delivery_instructions: delivery_instructions,
              collection_status: 'pending',
              delivery_location: `${order.city}, ${order.state} ${order.postal_code}`,
              contact_phone: order.phone
            }
          }
        });

        // TODO: Send order confirmation email with COD details
        console.log(`COD order confirmed: ${order.order_number}, Amount: ₹${order.total_amount}`);
      });

    } catch (error) {
      console.error('COD processing error:', error);
      res.status(400).json({
        error: 'Failed to process COD order',
        message: error.message
      });
    }
  }
);

/**
 * POST /api/payments/cod/delivery-confirm
 * Confirm delivery and collect payment for COD orders
 * Requires admin authentication
 * Requirements: 4.4 - COD delivery confirmation and payment collection
 */
router.post('/cod/delivery-confirm',
  authenticateAdmin,
  validation.codDeliveryConfirm,
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({
          error: 'Validation failed',
          details: errors.array()
        });
      }

      const adminId = req.admin.id;
      const { 
        order_id, 
        payment_collected, 
        collection_amount, 
        delivery_notes = null,
        delivery_person_name,
        delivery_person_phone 
      } = req.body;

      await secureExecuteTransactionCallback(async (connection) => {
        // Get order and COD tracking details
        const orderResult = await connection.execute(
          `SELECT o.id, o.order_number, o.total_amount, o.payment_method, o.status, o.user_id,
                  ct.cod_amount, ct.status as cod_status, ct.delivery_instructions,
                  u.email, u.first_name, u.last_name
           FROM orders o
           JOIN cod_tracking ct ON o.id = ct.order_id
           JOIN users u ON o.user_id = u.id
           WHERE o.id = ? AND o.payment_method = 'cod' AND o.status IN ('shipped', 'out_for_delivery')`,
          [order_id]
        );

        if (orderResult[0].length === 0) {
          throw new Error('COD order not found or not ready for delivery confirmation');
        }

        const order = orderResult[0][0];

        // Validate collection amount
        if (payment_collected && Math.abs(collection_amount - order.total_amount) > 0.01) {
          throw new Error(`Collection amount mismatch. Expected: ₹${order.total_amount}, Received: ₹${collection_amount}`);
        }

        if (payment_collected) {
          // Payment collected successfully - mark as delivered and paid
          await connection.execute(
            `UPDATE orders 
             SET status = 'delivered', 
                 payment_status = 'paid',
                 notes = COALESCE(CONCAT(COALESCE(notes, ""), ?, "\n"), ?),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?`,
            [
              `[${new Date().toISOString()}] COD Delivery Confirmed - Payment Collected by ${delivery_person_name} (${delivery_person_phone}). Notes: ${delivery_notes || 'None'}`,
              `COD Delivery Confirmed - Payment Collected: ₹${collection_amount}`,
              order_id
            ]
          );

          // Update payment record
          await connection.execute(
            `UPDATE payments 
             SET payment_status = 'completed',
                 transaction_id = CONCAT(transaction_id, '_COLLECTED_', ?),
                 updated_at = CURRENT_TIMESTAMP
             WHERE order_id = ?`,
            [Date.now(), order_id]
          );

          // Update COD tracking
          await connection.execute(
            `UPDATE cod_tracking 
             SET status = 'collected',
                 collection_amount = ?,
                 delivery_confirmed_at = CURRENT_TIMESTAMP,
                 delivery_person_name = ?,
                 delivery_person_phone = ?,
                 delivery_notes = ?,
                 confirmed_by_admin_id = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE order_id = ?`,
            [collection_amount, delivery_person_name, delivery_person_phone, delivery_notes, adminId, order_id]
          );

        } else {
          // Delivery attempted but payment not collected
          await connection.execute(
            `UPDATE orders 
             SET notes = COALESCE(CONCAT(COALESCE(notes, ""), ?, "\n"), ?),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?`,
            [
              `[${new Date().toISOString()}] COD Delivery Attempted - Payment NOT Collected by ${delivery_person_name} (${delivery_person_phone}). Notes: ${delivery_notes || 'Customer unavailable'}`,
              `COD Delivery Attempted - Payment NOT Collected`,
              order_id
            ]
          );

          // Update COD tracking
          await connection.execute(
            `UPDATE cod_tracking 
             SET status = 'delivery_attempted',
                 delivery_attempt_count = delivery_attempt_count + 1,
                 last_delivery_attempt = CURRENT_TIMESTAMP,
                 delivery_person_name = ?,
                 delivery_person_phone = ?,
                 delivery_notes = ?,
                 confirmed_by_admin_id = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE order_id = ?`,
            [delivery_person_name, delivery_person_phone, delivery_notes, adminId, order_id]
          );
        }

        res.json({
          message: payment_collected ? 
            'COD delivery confirmed and payment collected successfully' : 
            'COD delivery attempt recorded',
          order: {
            id: order.id,
            order_number: order.order_number,
            status: payment_collected ? 'delivered' : order.status,
            payment_status: payment_collected ? 'paid' : 'pending',
            payment_collected: payment_collected,
            collection_amount: payment_collected ? collection_amount : null,
            delivery_person: {
              name: delivery_person_name,
              phone: delivery_person_phone
            },
            delivery_notes: delivery_notes
          }
        });

        // Send COD delivery confirmation email if payment was collected
        if (payment_collected) {
          setImmediate(async () => {
            try {
              const customer = {
                first_name: order.first_name,
                last_name: order.last_name,
                email: order.email
              };
              
              await sendCODDeliveryEmail(
                {
                  id: order.id,
                  order_number: order.order_number
                },
                customer,
                {
                  collection_amount: collection_amount,
                  delivery_person_name: delivery_person_name,
                  delivery_confirmed_at: new Date(),
                  delivery_notes: delivery_notes
                }
              );
            } catch (emailError) {
              console.error('Failed to send COD delivery email:', emailError);
              // Don't fail the delivery confirmation if email fails
            }
          });
        }

        console.log(`COD delivery ${payment_collected ? 'confirmed' : 'attempted'} for order ${order.order_number}`);
      });

    } catch (error) {
      console.error('COD delivery confirmation error:', error);
      res.status(400).json({
        error: 'Failed to process COD delivery confirmation',
        message: error.message
      });
    }
  }
);

/**
 * GET /api/payments/cod/tracking/:order_id
 * Get COD tracking information for an order
 * Requires admin authentication
 * Requirements: 4.4 - COD payment status management
 */
router.get('/cod/tracking/:order_id',
  authenticateAdmin,
  validation.validateId,
  async (req, res) => {
    try {
      const orderId = req.params.order_id;

      const trackingResult = await secureExecuteQuery(
        `SELECT o.id, o.order_number, o.status, o.payment_status, o.total_amount,
                ct.cod_amount, ct.delivery_instructions, ct.status as cod_status,
                ct.collection_amount, ct.delivery_confirmed_at, ct.delivery_attempt_count,
                ct.last_delivery_attempt, ct.delivery_person_name, ct.delivery_person_phone,
                ct.delivery_notes, ct.created_at as cod_created_at,
                u.first_name, u.last_name, u.email, u.phone as customer_phone,
                a.address_line1, a.address_line2, a.city, a.state, a.postal_code, a.phone as delivery_phone
         FROM orders o
         JOIN cod_tracking ct ON o.id = ct.order_id
         JOIN users u ON o.user_id = u.id
         JOIN addresses a ON o.shipping_address_id = a.id
         WHERE o.id = ? AND o.payment_method = 'cod'`,
        [orderId]
      );

      if (trackingResult.length === 0) {
        return res.status(404).json({
          error: 'COD order not found'
        });
      }

      const tracking = trackingResult[0];

      res.json({
        order: {
          id: tracking.id,
          order_number: tracking.order_number,
          status: tracking.status,
          payment_status: tracking.payment_status,
          total_amount: parseFloat(tracking.total_amount)
        },
        cod_details: {
          cod_amount: parseFloat(tracking.cod_amount),
          collection_amount: tracking.collection_amount ? parseFloat(tracking.collection_amount) : null,
          status: tracking.cod_status,
          delivery_instructions: tracking.delivery_instructions,
          delivery_attempt_count: tracking.delivery_attempt_count,
          last_delivery_attempt: tracking.last_delivery_attempt,
          delivery_confirmed_at: tracking.delivery_confirmed_at,
          delivery_person: {
            name: tracking.delivery_person_name,
            phone: tracking.delivery_person_phone
          },
          delivery_notes: tracking.delivery_notes,
          created_at: tracking.cod_created_at
        },
        customer: {
          name: `${tracking.first_name} ${tracking.last_name}`,
          email: tracking.email,
          phone: tracking.customer_phone
        },
        delivery_address: {
          address_line1: tracking.address_line1,
          address_line2: tracking.address_line2,
          city: tracking.city,
          state: tracking.state,
          postal_code: tracking.postal_code,
          phone: tracking.delivery_phone
        }
      });

    } catch (error) {
      console.error('Get COD tracking error:', error);
      res.status(500).json({
        error: 'Failed to retrieve COD tracking information'
      });
    }
  }
);

/**
 * GET /admin/payments/cod/pending
 * Get all pending COD orders for delivery management
 * Requires admin authentication
 * Requirements: 4.4 - COD order management
 */
router.get('/admin/cod/pending',
  authenticateAdmin,
  async (req, res) => {
    try {
      const { page = 1, limit = 20, state, city } = req.query;
      const offset = (page - 1) * limit;
      
      let whereClause = 'WHERE o.payment_method = "cod" AND o.status IN ("processing", "shipped", "out_for_delivery") AND ct.status IN ("confirmed", "delivery_attempted")';
      let queryParams = [];
      
      if (state) {
        whereClause += ' AND a.state = ?';
        queryParams.push(state);
      }
      
      if (city) {
        whereClause += ' AND a.city = ?';
        queryParams.push(city);
      }

      const pendingCodQuery = `
        SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
               ct.cod_amount, ct.delivery_instructions, ct.status as cod_status,
               ct.delivery_attempt_count, ct.last_delivery_attempt,
               u.first_name, u.last_name, u.phone as customer_phone,
               a.address_line1, a.city, a.state, a.postal_code, a.phone as delivery_phone
        FROM orders o
        JOIN cod_tracking ct ON o.id = ct.order_id
        JOIN users u ON o.user_id = u.id
        JOIN addresses a ON o.shipping_address_id = a.id
        ${whereClause}
        ORDER BY 
          CASE 
            WHEN ct.status = 'delivery_attempted' THEN 1 
            ELSE 2 
          END,
          ct.delivery_attempt_count DESC,
          o.created_at ASC
        LIMIT ? OFFSET ?
      `;
      
      queryParams.push(parseInt(limit), offset);
      const pendingOrders = await secureExecuteQuery(pendingCodQuery, queryParams);

      // Get total count
      const countQuery = `
        SELECT COUNT(*) as total
        FROM orders o
        JOIN cod_tracking ct ON o.id = ct.order_id
        JOIN addresses a ON o.shipping_address_id = a.id
        ${whereClause}
      `;
      const countResult = await secureExecuteQuery(countQuery, queryParams.slice(0, -2));
      const totalOrders = countResult[0].total;

      res.json({
        pending_cod_orders: pendingOrders.map(order => ({
          id: order.id,
          order_number: order.order_number,
          status: order.status,
          cod_status: order.cod_status,
          total_amount: parseFloat(order.total_amount),
          cod_amount: parseFloat(order.cod_amount),
          delivery_attempt_count: order.delivery_attempt_count,
          last_delivery_attempt: order.last_delivery_attempt,
          priority: order.cod_status === 'delivery_attempted' ? 'high' : 'normal',
          customer: {
            name: `${order.first_name} ${order.last_name}`,
            phone: order.customer_phone
          },
          delivery_address: {
            address: order.address_line1,
            city: order.city,
            state: order.state,
            postal_code: order.postal_code,
            phone: order.delivery_phone
          },
          delivery_instructions: order.delivery_instructions,
          created_at: order.created_at
        })),
        pagination: {
          current_page: parseInt(page),
          per_page: parseInt(limit),
          total_orders: totalOrders,
          total_pages: Math.ceil(totalOrders / limit)
        },
        summary: {
          total_pending: totalOrders,
          delivery_attempted: pendingOrders.filter(o => o.cod_status === 'delivery_attempted').length,
          ready_for_delivery: pendingOrders.filter(o => o.cod_status === 'confirmed').length
        }
      });

    } catch (error) {
      console.error('Get pending COD orders error:', error);
      res.status(500).json({
        error: 'Failed to retrieve pending COD orders'
      });
    }
  }
);

/**
 * GET /api/payments/status/:order_id
 * Get payment status for an order with enhanced COD tracking
 * Requires authentication
 * Requirements: 4.1, 4.2, 4.3, 4.4
 */
router.get('/status/:order_id',
  authenticateToken,
  validation.validateId,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const orderId = req.params.order_id;

      const paymentResult = await secureExecuteQuery(
        `SELECT o.id, o.order_number, o.status, o.payment_method, o.payment_status, o.total_amount,
                p.payment_status as payment_record_status, p.razorpay_order_id, 
                p.razorpay_payment_id, p.transaction_id, p.created_at as payment_created_at,
                p.updated_at as payment_updated_at,
                ct.cod_amount, ct.delivery_instructions, ct.status as cod_status,
                ct.collection_amount, ct.delivery_confirmed_at, ct.delivery_attempt_count,
                ct.last_delivery_attempt, ct.delivery_notes
         FROM orders o
         LEFT JOIN payments p ON o.id = p.order_id
         LEFT JOIN cod_tracking ct ON o.id = ct.order_id
         WHERE o.id = ? AND o.user_id = ?`,
        [orderId, userId]
      );

      if (paymentResult.length === 0) {
        return res.status(404).json({
          error: 'Order not found'
        });
      }

      const payment = paymentResult[0];

      // If it's a Razorpay payment and we have a payment ID, fetch latest status from Razorpay
      let razorpayStatus = null;
      if (payment.payment_method === 'razorpay' && payment.razorpay_payment_id) {
        try {
          const razorpayPayment = await razorpay.payments.fetch(payment.razorpay_payment_id);
          razorpayStatus = {
            id: razorpayPayment.id,
            status: razorpayPayment.status,
            amount: paymentUtils.convertToRupees(razorpayPayment.amount),
            currency: razorpayPayment.currency,
            method: razorpayPayment.method,
            created_at: razorpayPayment.created_at
          };
        } catch (razorpayError) {
          console.error('Error fetching payment status from Razorpay:', razorpayError);
        }
      }

      // Prepare COD details if it's a COD order
      let codDetails = null;
      if (payment.payment_method === 'cod' && payment.cod_amount) {
        codDetails = {
          cod_amount: parseFloat(payment.cod_amount),
          collection_amount: payment.collection_amount ? parseFloat(payment.collection_amount) : null,
          status: payment.cod_status,
          delivery_instructions: payment.delivery_instructions,
          delivery_attempt_count: payment.delivery_attempt_count || 0,
          last_delivery_attempt: payment.last_delivery_attempt,
          delivery_confirmed_at: payment.delivery_confirmed_at,
          delivery_notes: payment.delivery_notes,
          payment_collected: payment.cod_status === 'collected',
          next_delivery_attempt: payment.cod_status === 'delivery_attempted' ? 
            'Will be rescheduled within 24 hours' : null
        };
      }

      res.json({
        order: {
          id: payment.id,
          order_number: payment.order_number,
          status: payment.status,
          payment_method: payment.payment_method,
          payment_status: payment.payment_status,
          total_amount: parseFloat(payment.total_amount)
        },
        payment: {
          status: payment.payment_record_status,
          razorpay_order_id: payment.razorpay_order_id,
          razorpay_payment_id: payment.razorpay_payment_id,
          transaction_id: payment.transaction_id,
          created_at: payment.payment_created_at,
          updated_at: payment.payment_updated_at
        },
        razorpay_status: razorpayStatus,
        cod_details: codDetails
      });

    } catch (error) {
      console.error('Get payment status error:', error);
      res.status(500).json({
        error: 'Failed to retrieve payment status'
      });
    }
  }
);

/**
 * POST /api/payments/webhook
 * Handle Razorpay webhooks for payment updates
 * No authentication required (webhook endpoint)
 * Requirements: 4.2, 4.3
 */
router.post('/webhook', async (req, res) => {
  try {
    const webhookSignature = req.headers['x-razorpay-signature'];
    const webhookBody = JSON.stringify(req.body);

    // Verify webhook signature
    const expectedSignature = crypto
      .createHmac('sha256', process.env.RAZORPAY_WEBHOOK_SECRET)
      .update(webhookBody)
      .digest('hex');

    if (webhookSignature !== expectedSignature) {
      console.error('Invalid webhook signature');
      return res.status(400).json({ error: 'Invalid signature' });
    }

    const { event, payload } = req.body;

    console.log(`Received Razorpay webhook: ${event}`);

    switch (event) {
      case 'payment.captured':
        await handlePaymentCaptured(payload.payment.entity);
        break;
      
      case 'payment.failed':
        await handlePaymentFailed(payload.payment.entity);
        break;
      
      case 'order.paid':
        await handleOrderPaid(payload.order.entity);
        break;
      
      default:
        console.log(`Unhandled webhook event: ${event}`);
    }

    res.status(200).json({ status: 'ok' });

  } catch (error) {
    console.error('Webhook processing error:', error);
    res.status(500).json({ error: 'Webhook processing failed' });
  }
});

/**
 * Handle payment captured webhook
 */
async function handlePaymentCaptured(payment) {
  try {
    await secureExecuteTransactionCallback(async (connection) => {
      // Find the order by Razorpay payment ID
      const orderResult = await connection.execute(
        `SELECT o.id, o.order_number, p.id as payment_id
         FROM orders o
         JOIN payments p ON o.id = p.order_id
         WHERE p.razorpay_payment_id = ?`,
        [payment.id]
      );

      if (orderResult[0].length === 0) {
        console.error(`Order not found for payment ID: ${payment.id}`);
        return;
      }

      const order = orderResult[0][0];

      // Update payment status
      await connection.execute(
        `UPDATE payments 
         SET payment_status = 'completed', updated_at = CURRENT_TIMESTAMP
         WHERE id = ?`,
        [order.payment_id]
      );

      // Update order status if not already updated
      await connection.execute(
        `UPDATE orders 
         SET payment_status = 'paid', updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND payment_status != 'paid'`,
        [order.id]
      );

      console.log(`Payment captured for order ${order.order_number}`);
    });
  } catch (error) {
    console.error('Error handling payment captured webhook:', error);
  }
}

/**
 * Handle payment failed webhook
 */
async function handlePaymentFailed(payment) {
  try {
    await secureExecuteTransactionCallback(async (connection) => {
      // Find the order by Razorpay payment ID or order ID
      let orderResult = await connection.execute(
        `SELECT o.id, o.order_number, p.id as payment_id
         FROM orders o
         JOIN payments p ON o.id = p.order_id
         WHERE p.razorpay_payment_id = ? OR p.razorpay_order_id = ?`,
        [payment.id, payment.order_id]
      );

      if (orderResult[0].length === 0) {
        console.error(`Order not found for failed payment: ${payment.id}`);
        return;
      }

      const order = orderResult[0][0];

      // Update payment status to failed
      await connection.execute(
        `UPDATE payments 
         SET payment_status = 'failed', 
             razorpay_payment_id = ?,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = ?`,
        [payment.id, order.payment_id]
      );

      // Update order payment status
      await connection.execute(
        `UPDATE orders 
         SET payment_status = 'failed', updated_at = CURRENT_TIMESTAMP
         WHERE id = ?`,
        [order.id]
      );

      console.log(`Payment failed for order ${order.order_number}`);
    });
  } catch (error) {
    console.error('Error handling payment failed webhook:', error);
  }
}

/**
 * Handle order paid webhook
 */
async function handleOrderPaid(order) {
  try {
    await secureExecuteTransactionCallback(async (connection) => {
      // Find the order by Razorpay order ID
      const orderResult = await connection.execute(
        `SELECT o.id, o.order_number, p.id as payment_id
         FROM orders o
         JOIN payments p ON o.id = p.order_id
         WHERE p.razorpay_order_id = ?`,
        [order.id]
      );

      if (orderResult[0].length === 0) {
        console.error(`Order not found for Razorpay order ID: ${order.id}`);
        return;
      }

      const dbOrder = orderResult[0][0];

      // Update order status to processing if payment is confirmed
      await connection.execute(
        `UPDATE orders 
         SET payment_status = 'paid', 
             status = CASE WHEN status = 'placed' THEN 'processing' ELSE status END,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = ?`,
        [dbOrder.id]
      );

      console.log(`Order paid webhook processed for order ${dbOrder.order_number}`);
    });
  } catch (error) {
    console.error('Error handling order paid webhook:', error);
  }
}

module.exports = router;