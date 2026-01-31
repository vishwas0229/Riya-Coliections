const express = require('express');
const router = express.Router();
const { secureExecuteQuery, secureExecuteTransactionCallback } = require('../middleware/database-security');
const { authenticateToken, authenticateAdmin } = require('../middleware/auth');
const { validationMiddleware: validation } = require('../middleware/validation');
const { validationResult } = require('express-validator');
const { sendOrderConfirmationEmail, sendOrderStatusEmail } = require('../utils/email-service');

/**
 * Order Management Routes
 * 
 * This module handles all order-related operations including:
 * - Order creation from cart contents
 * - Order status workflow management
 * - Order history and tracking
 * - Admin order management
 */

// Generate unique order number
function generateOrderNumber() {
  const timestamp = Date.now().toString();
  const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
  return `RC${timestamp}${random}`;
}

// Validate order status transitions
function isValidStatusTransition(currentStatus, newStatus) {
  const validTransitions = {
    'placed': ['processing', 'cancelled'],
    'processing': ['shipped', 'cancelled'],
    'shipped': ['out_for_delivery', 'delivered'],
    'out_for_delivery': ['delivered'],
    'delivered': [], // Final state
    'cancelled': [] // Final state
  };
  
  return validTransitions[currentStatus]?.includes(newStatus) || false;
}

/**
 * POST /api/orders
 * Create a new order from cart contents
 * Requires authentication
 */
router.post('/', 
  authenticateToken,
  validation.orderCreation,
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
        items, 
        shipping_address_id, 
        payment_method, 
        coupon_code = null,
        notes = null 
      } = req.body;

      await secureExecuteTransactionCallback(async (connection) => {
        // Validate shipping address belongs to user
        const addressResult = await connection.execute(
          `SELECT id, first_name, last_name, address_line1, address_line2, 
                  city, state, postal_code, country, phone
           FROM addresses 
           WHERE id = ? AND user_id = ?`,
          [shipping_address_id, userId]
        );

        if (addressResult[0].length === 0) {
          throw new Error('Invalid shipping address');
        }

        // Validate all products exist and have sufficient stock
        let subtotal = 0;
        const validatedItems = [];

        for (const item of items) {
          const productResult = await connection.execute(
            `SELECT id, name, price, stock_quantity, is_active
             FROM products 
             WHERE id = ? AND is_active = true`,
            [item.product_id]
          );

          if (productResult[0].length === 0) {
            throw new Error(`Product with ID ${item.product_id} not found or inactive`);
          }

          const product = productResult[0][0];
          
          if (product.stock_quantity < item.quantity) {
            throw new Error(`Insufficient stock for product ${product.name}. Available: ${product.stock_quantity}, Requested: ${item.quantity}`);
          }

          const itemTotal = parseFloat(product.price) * item.quantity;
          subtotal += itemTotal;

          validatedItems.push({
            product_id: product.id,
            name: product.name,
            quantity: item.quantity,
            unit_price: parseFloat(product.price),
            total_price: itemTotal
          });
        }

        // Apply coupon if provided
        let discount_amount = 0;
        let coupon = null;

        if (coupon_code) {
          const couponResult = await connection.execute(
            `SELECT id, code, discount_type, discount_value, minimum_amount, 
                    maximum_discount, usage_limit, used_count
             FROM coupons 
             WHERE code = ? AND is_active = true 
                   AND (valid_from IS NULL OR valid_from <= NOW())
                   AND (valid_until IS NULL OR valid_until >= NOW())`,
            [coupon_code]
          );

          if (couponResult[0].length === 0) {
            throw new Error('Invalid or expired coupon code');
          }

          coupon = couponResult[0][0];

          // Check usage limit
          if (coupon.usage_limit && coupon.used_count >= coupon.usage_limit) {
            throw new Error('Coupon usage limit exceeded');
          }

          // Check minimum amount
          if (coupon.minimum_amount && subtotal < coupon.minimum_amount) {
            throw new Error(`Minimum order amount of ₹${coupon.minimum_amount} required for this coupon`);
          }

          // Calculate discount
          if (coupon.discount_type === 'percentage') {
            discount_amount = (subtotal * coupon.discount_value) / 100;
            if (coupon.maximum_discount) {
              discount_amount = Math.min(discount_amount, coupon.maximum_discount);
            }
          } else if (coupon.discount_type === 'fixed') {
            discount_amount = Math.min(coupon.discount_value, subtotal);
          }
        }

        // Calculate totals
        const shipping_amount = subtotal >= 500 ? 0 : 50; // Free shipping above ₹500
        const tax_rate = 0.18; // 18% GST
        const tax_amount = (subtotal - discount_amount + shipping_amount) * tax_rate;
        const total_amount = subtotal - discount_amount + shipping_amount + tax_amount;

        // Generate order number
        const order_number = generateOrderNumber();

        // Create order
        const orderResult = await connection.execute(
          `INSERT INTO orders (
             user_id, order_number, status, total_amount, discount_amount,
             shipping_amount, tax_amount, coupon_code, shipping_address_id,
             payment_method, payment_status, notes
           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [
            userId, order_number, 'placed', total_amount, discount_amount,
            shipping_amount, tax_amount, coupon_code, shipping_address_id,
            payment_method, payment_method === 'cod' ? 'pending' : 'pending', notes
          ]
        );

        const orderId = orderResult[0].insertId;

        // Create order items and update stock
        for (const item of validatedItems) {
          // Insert order item
          await connection.execute(
            `INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?)`,
            [orderId, item.product_id, item.quantity, item.unit_price, item.total_price]
          );

          // Update product stock
          await connection.execute(
            `UPDATE products 
             SET stock_quantity = stock_quantity - ?
             WHERE id = ?`,
            [item.quantity, item.product_id]
          );
        }

        // Update coupon usage count if coupon was used
        if (coupon) {
          await connection.execute(
            `UPDATE coupons 
             SET used_count = used_count + 1
             WHERE id = ?`,
            [coupon.id]
          );
        }

        // Create payment record
        await connection.execute(
          `INSERT INTO payments (order_id, payment_method, payment_status, amount)
           VALUES (?, ?, ?, ?)`,
          [orderId, payment_method, 'pending', total_amount]
        );

        // Return order details
        const orderDetails = {
          id: orderId,
          order_number,
          status: 'placed',
          total_amount,
          discount_amount,
          shipping_amount,
          tax_amount,
          coupon_code,
          payment_method,
          payment_status: 'pending',
          items: validatedItems,
          shipping_address: addressResult[0][0],
          created_at: new Date().toISOString()
        };

        // Send order confirmation email asynchronously
        setImmediate(async () => {
          try {
            const customerInfo = {
              id: userId,
              first_name: req.user.first_name,
              last_name: req.user.last_name,
              email: req.user.email
            };
            
            await sendOrderConfirmationEmail(
              orderDetails,
              customerInfo,
              validatedItems,
              addressResult[0][0]
            );
          } catch (emailError) {
            console.error('Failed to send order confirmation email:', emailError);
            // Don't fail the order creation if email fails
          }
        });

        res.status(201).json({
          message: 'Order created successfully',
          order: orderDetails
        });
      });

    } catch (error) {
      console.error('Order creation error:', error);
      res.status(400).json({
        error: error.message || 'Failed to create order'
      });
    }
  }
);

/**
 * GET /api/orders
 * Get user's order history
 * Requires authentication
 */
router.get('/', 
  authenticateToken,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const { page = 1, limit = 10, status } = req.query;
      
      const offset = (page - 1) * limit;
      
      let whereClause = 'WHERE o.user_id = ?';
      let queryParams = [userId];
      
      if (status) {
        whereClause += ' AND o.status = ?';
        queryParams.push(status);
      }

      // Get orders with basic info
      const ordersQuery = `
        SELECT o.id, o.order_number, o.status, o.total_amount, 
               o.discount_amount, o.payment_method, o.payment_status,
               o.created_at, o.updated_at,
               a.first_name, a.last_name, a.city, a.state
        FROM orders o
        JOIN addresses a ON o.shipping_address_id = a.id
        ${whereClause}
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
      `;
      
      queryParams.push(parseInt(limit), offset);
      const orders = await secureExecuteQuery(ordersQuery, queryParams);

      // Get total count for pagination
      const countQuery = `
        SELECT COUNT(*) as total
        FROM orders o
        ${whereClause}
      `;
      const countResult = await secureExecuteQuery(countQuery, queryParams.slice(0, -2));
      const totalOrders = countResult[0].total;

      // Get order items for each order
      for (const order of orders) {
        const itemsQuery = `
          SELECT oi.quantity, oi.unit_price, oi.total_price,
                 p.id as product_id, p.name as product_name,
                 pi.image_url
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
          WHERE oi.order_id = ?
        `;
        
        const items = await secureExecuteQuery(itemsQuery, [order.id]);
        order.items = items;
      }

      res.json({
        orders,
        pagination: {
          current_page: parseInt(page),
          per_page: parseInt(limit),
          total_orders: totalOrders,
          total_pages: Math.ceil(totalOrders / limit)
        }
      });

    } catch (error) {
      console.error('Get orders error:', error);
      res.status(500).json({
        error: 'Failed to retrieve orders'
      });
    }
  }
);

/**
 * GET /api/orders/:id
 * Get detailed order information
 * Requires authentication
 */
router.get('/:id', 
  authenticateToken,
  async (req, res) => {
    try {
      const orderId = req.params.id;
      const userId = req.user.id;

      // Get order details
      const orderQuery = `
        SELECT o.*, 
               a.first_name, a.last_name, a.address_line1, a.address_line2,
               a.city, a.state, a.postal_code, a.country, a.phone,
               p.payment_status, p.razorpay_payment_id, p.transaction_id
        FROM orders o
        JOIN addresses a ON o.shipping_address_id = a.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ? AND o.user_id = ?
      `;

      const orderResult = await secureExecuteQuery(orderQuery, [orderId, userId]);
      
      if (orderResult.length === 0) {
        return res.status(404).json({
          error: 'Order not found'
        });
      }

      const order = orderResult[0];

      // Get order items
      const itemsQuery = `
        SELECT oi.quantity, oi.unit_price, oi.total_price,
               p.id as product_id, p.name as product_name, p.brand,
               pi.image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
        WHERE oi.order_id = ?
      `;
      
      const items = await secureExecuteQuery(itemsQuery, [orderId]);

      // Format response
      const orderDetails = {
        id: order.id,
        order_number: order.order_number,
        status: order.status,
        total_amount: parseFloat(order.total_amount),
        discount_amount: parseFloat(order.discount_amount),
        shipping_amount: parseFloat(order.shipping_amount),
        tax_amount: parseFloat(order.tax_amount),
        coupon_code: order.coupon_code,
        payment_method: order.payment_method,
        payment_status: order.payment_status,
        notes: order.notes,
        created_at: order.created_at,
        updated_at: order.updated_at,
        shipping_address: {
          first_name: order.first_name,
          last_name: order.last_name,
          address_line1: order.address_line1,
          address_line2: order.address_line2,
          city: order.city,
          state: order.state,
          postal_code: order.postal_code,
          country: order.country,
          phone: order.phone
        },
        payment_details: {
          status: order.payment_status,
          razorpay_payment_id: order.razorpay_payment_id,
          transaction_id: order.transaction_id
        },
        items: items.map(item => ({
          product_id: item.product_id,
          product_name: item.product_name,
          brand: item.brand,
          quantity: item.quantity,
          unit_price: parseFloat(item.unit_price),
          total_price: parseFloat(item.total_price),
          image_url: item.image_url
        }))
      };

      res.json({
        order: orderDetails
      });

    } catch (error) {
      console.error('Get order details error:', error);
      res.status(500).json({
        error: 'Failed to retrieve order details'
      });
    }
  }
);

/**
 * GET /admin/orders
 * Get all orders for admin management with comprehensive filtering and search
 * Requires admin authentication
 * Requirements: 8.1, 8.3
 */
router.get('/admin/orders', 
  authenticateAdmin,
  validation.adminOrderSearch,
  async (req, res) => {
    try {
      const { 
        page = 1, 
        limit = 20, 
        status, 
        search, 
        start_date, 
        end_date,
        sort_by = 'created_at',
        sort_order = 'desc',
        payment_method,
        payment_status,
        customer_email,
        order_number,
        min_amount,
        max_amount
      } = req.query;
      
      const offset = (page - 1) * limit;
      
      let whereClause = 'WHERE 1=1';
      let queryParams = [];
      
      // Filter by status
      if (status) {
        whereClause += ' AND o.status = ?';
        queryParams.push(status);
      }
      
      // Filter by payment method
      if (payment_method) {
        whereClause += ' AND o.payment_method = ?';
        queryParams.push(payment_method);
      }
      
      // Filter by payment status
      if (payment_status) {
        whereClause += ' AND o.payment_status = ?';
        queryParams.push(payment_status);
      }
      
      // Search by order number (exact or partial match)
      if (order_number) {
        whereClause += ' AND o.order_number LIKE ?';
        queryParams.push(`%${order_number}%`);
      }
      
      // Search by customer email
      if (customer_email) {
        whereClause += ' AND u.email LIKE ?';
        queryParams.push(`%${customer_email}%`);
      }
      
      // General search by order number, customer name, or email
      if (search) {
        whereClause += ' AND (o.order_number LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR u.email LIKE ?)';
        const searchTerm = `%${search}%`;
        queryParams.push(searchTerm, searchTerm, searchTerm);
      }
      
      // Date range filter
      if (start_date) {
        whereClause += ' AND DATE(o.created_at) >= ?';
        queryParams.push(start_date);
      }
      
      if (end_date) {
        whereClause += ' AND DATE(o.created_at) <= ?';
        queryParams.push(end_date);
      }
      
      // Amount range filter
      if (min_amount) {
        whereClause += ' AND o.total_amount >= ?';
        queryParams.push(parseFloat(min_amount));
      }
      
      if (max_amount) {
        whereClause += ' AND o.total_amount <= ?';
        queryParams.push(parseFloat(max_amount));
      }

      // Validate sort parameters
      const allowedSortColumns = ['created_at', 'updated_at', 'order_number', 'status', 'total_amount', 'customer_name'];
      const sortColumn = allowedSortColumns.includes(sort_by) ? 
        (sort_by === 'customer_name' ? 'CONCAT(u.first_name, " ", u.last_name)' : `o.${sort_by}`) : 
        'o.created_at';
      const sortDirection = sort_order.toLowerCase() === 'asc' ? 'ASC' : 'DESC';

      // Get orders with comprehensive information
      const ordersQuery = `
        SELECT o.id, o.order_number, o.status, o.total_amount, o.discount_amount,
               o.shipping_amount, o.tax_amount, o.coupon_code,
               o.payment_method, o.payment_status, o.notes,
               o.created_at, o.updated_at,
               u.id as user_id, u.first_name, u.last_name, u.email, u.phone as customer_phone,
               a.city, a.state, a.postal_code, a.phone as shipping_phone,
               COUNT(oi.id) as item_count,
               SUM(oi.quantity) as total_items,
               p.razorpay_payment_id, p.transaction_id
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN addresses a ON o.shipping_address_id = a.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN payments p ON o.id = p.order_id
        ${whereClause}
        GROUP BY o.id, u.id, a.id, p.id
        ORDER BY ${sortColumn} ${sortDirection}
        LIMIT ? OFFSET ?
      `;
      
      queryParams.push(parseInt(limit), offset);
      const orders = await secureExecuteQuery(ordersQuery, queryParams);

      // Get total count for pagination
      const countQuery = `
        SELECT COUNT(DISTINCT o.id) as total
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN addresses a ON o.shipping_address_id = a.id
        ${whereClause}
      `;
      const countResult = await secureExecuteQuery(countQuery, queryParams.slice(0, -2));
      const totalOrders = countResult[0].total;

      // Get order statistics for dashboard
      const statsQuery = `
        SELECT 
          COUNT(*) as total_orders,
          SUM(CASE WHEN status = 'placed' THEN 1 ELSE 0 END) as placed_orders,
          SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
          SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
          SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
          SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
          SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
          SUM(total_amount) as total_revenue,
          AVG(total_amount) as average_order_value
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN addresses a ON o.shipping_address_id = a.id
        ${whereClause}
      `;
      const statsResult = await secureExecuteQuery(statsQuery, queryParams.slice(0, -2));
      const stats = statsResult[0];

      res.json({
        orders: orders.map(order => ({
          id: order.id,
          order_number: order.order_number,
          status: order.status,
          total_amount: parseFloat(order.total_amount),
          discount_amount: parseFloat(order.discount_amount || 0),
          shipping_amount: parseFloat(order.shipping_amount || 0),
          tax_amount: parseFloat(order.tax_amount || 0),
          coupon_code: order.coupon_code,
          payment_method: order.payment_method,
          payment_status: order.payment_status,
          notes: order.notes,
          item_count: order.item_count,
          total_items: order.total_items,
          customer: {
            id: order.user_id,
            name: `${order.first_name} ${order.last_name}`,
            email: order.email,
            phone: order.customer_phone
          },
          shipping_location: `${order.city}, ${order.state} ${order.postal_code}`,
          shipping_phone: order.shipping_phone,
          payment_details: {
            razorpay_payment_id: order.razorpay_payment_id,
            transaction_id: order.transaction_id
          },
          created_at: order.created_at,
          updated_at: order.updated_at
        })),
        pagination: {
          current_page: parseInt(page),
          per_page: parseInt(limit),
          total_orders: totalOrders,
          total_pages: Math.ceil(totalOrders / limit)
        },
        statistics: {
          total_orders: stats.total_orders,
          status_breakdown: {
            placed: stats.placed_orders,
            processing: stats.processing_orders,
            shipped: stats.shipped_orders,
            delivered: stats.delivered_orders,
            cancelled: stats.cancelled_orders
          },
          pending_payments: stats.pending_payments,
          total_revenue: parseFloat(stats.total_revenue || 0),
          average_order_value: parseFloat(stats.average_order_value || 0)
        }
      });

    } catch (error) {
      console.error('Admin get orders error:', error);
      res.status(500).json({
        error: 'Failed to retrieve orders'
      });
    }
  }
);

/**
 * PUT /admin/orders/:id/status
 * Update order status with comprehensive validation and notifications
 * Requires admin authentication
 * Requirements: 8.2, 5.3
 */
router.put('/admin/orders/:id/status', 
  authenticateAdmin,
  validation.orderStatus,
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({
          error: 'Validation failed',
          details: errors.array()
        });
      }

      const orderId = req.params.id;
      const { status, notes, tracking_number, estimated_delivery } = req.body;
      const adminId = req.admin.id;

      await secureExecuteTransactionCallback(async (connection) => {
        // Get current order status and customer information
        const orderResult = await connection.execute(
          `SELECT o.id, o.status, o.user_id, o.order_number, o.payment_method,
                  u.email, u.first_name, u.last_name
           FROM orders o
           JOIN users u ON o.user_id = u.id
           WHERE o.id = ?`,
          [orderId]
        );

        if (orderResult[0].length === 0) {
          throw new Error('Order not found');
        }

        const order = orderResult[0][0];
        const currentStatus = order.status;

        // Validate status transition
        if (!isValidStatusTransition(currentStatus, status)) {
          throw new Error(`Invalid status transition from ${currentStatus} to ${status}. Valid transitions: ${getValidTransitions(currentStatus).join(', ')}`);
        }

        // Prepare update data
        let updateFields = ['status = ?', 'updated_at = CURRENT_TIMESTAMP'];
        let updateParams = [status];

        if (notes) {
          updateFields.push('notes = COALESCE(CONCAT(COALESCE(notes, ""), ?, "\n"), ?)');
          updateParams.push(`[${new Date().toISOString()}] Admin Update: ${notes}`, notes);
        }

        // Update order status
        const updateQuery = `UPDATE orders SET ${updateFields.join(', ')} WHERE id = ?`;
        updateParams.push(orderId);
        
        await connection.execute(updateQuery, updateParams);

        // Handle status-specific actions
        if (status === 'delivered') {
          // Update payment status for COD orders
          await connection.execute(
            `UPDATE payments 
             SET payment_status = 'completed', updated_at = CURRENT_TIMESTAMP
             WHERE order_id = ? AND payment_method = 'cod'`,
            [orderId]
          );

          // For COD orders, also update COD tracking if payment was collected
          const codTrackingResult = await connection.execute(
            `SELECT id, status FROM cod_tracking WHERE order_id = ?`,
            [orderId]
          );

          if (codTrackingResult[0].length > 0) {
            const codTracking = codTrackingResult[0][0];
            if (codTracking.status !== 'collected') {
              // If marked as delivered but payment not collected, update COD status
              await connection.execute(
                `UPDATE cod_tracking 
                 SET status = 'collected', 
                     collection_amount = (SELECT total_amount FROM orders WHERE id = ?),
                     delivery_confirmed_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE order_id = ?`,
                [orderId, orderId]
              );
            }
          }
        }

        if (status === 'cancelled') {
          // Restore product stock for cancelled orders
          await connection.execute(
            `UPDATE products p
             JOIN order_items oi ON p.id = oi.product_id
             SET p.stock_quantity = p.stock_quantity + oi.quantity
             WHERE oi.order_id = ?`,
            [orderId]
          );

          // Update payment status for cancelled orders
          await connection.execute(
            `UPDATE payments 
             SET payment_status = 'refunded', updated_at = CURRENT_TIMESTAMP
             WHERE order_id = ? AND payment_status != 'completed'`,
            [orderId]
          );
        }

        // Log the status change for audit trail
        await connection.execute(
          `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_admin_id, notes, created_at)
           VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
           ON DUPLICATE KEY UPDATE order_id = order_id`, // Handle if table doesn't exist yet
          [orderId, currentStatus, status, adminId, notes || `Status changed from ${currentStatus} to ${status}`]
        );

        // Get updated order information
        const updatedOrderResult = await connection.execute(
          `SELECT o.*, u.email, u.first_name, u.last_name
           FROM orders o
           JOIN users u ON o.user_id = u.id
           WHERE o.id = ?`,
          [orderId]
        );

        const updatedOrder = updatedOrderResult[0][0];

        res.json({
          message: 'Order status updated successfully',
          order: {
            id: orderId,
            order_number: order.order_number,
            previous_status: currentStatus,
            new_status: status,
            notes: notes,
            customer: {
              email: order.email,
              name: `${order.first_name} ${order.last_name}`
            },
            updated_at: new Date().toISOString(),
            updated_by: {
              admin_id: adminId,
              admin_name: req.admin.name
            }
          }
        });

        // Send order status update email asynchronously
        setImmediate(async () => {
          try {
            const customerInfo = {
              id: order.user_id,
              first_name: order.first_name,
              last_name: order.last_name,
              email: order.email
            };
            
            await sendOrderStatusEmail(
              { id: orderId, order_number: order.order_number },
              customerInfo,
              status,
              currentStatus,
              notes
            );
          } catch (emailError) {
            console.error('Failed to send order status email:', emailError);
            // Don't fail the status update if email fails
          }
        });

        // TODO: Send email notification to customer about status change
        // This would be implemented with the email service
        console.log(`Order ${order.order_number} status changed from ${currentStatus} to ${status} by admin ${req.admin.name}`);
      });

    } catch (error) {
      console.error('Update order status error:', error);
      res.status(400).json({
        error: error.message || 'Failed to update order status'
      });
    }
  }
);

/**
 * Helper function to get valid status transitions
 */
function getValidTransitions(currentStatus) {
  const validTransitions = {
    'placed': ['processing', 'cancelled'],
    'processing': ['shipped', 'cancelled'],
    'shipped': ['out_for_delivery', 'delivered'],
    'out_for_delivery': ['delivered'],
    'delivered': [], // Final state
    'cancelled': [] // Final state
  };
  
  return validTransitions[currentStatus] || [];
}

/**
 * GET /admin/orders/:id
 * Get detailed order information for admin with comprehensive details
 * Requires admin authentication
 * Requirements: 8.1, 8.4
 */
router.get('/admin/orders/:id', 
  authenticateAdmin,
  async (req, res) => {
    try {
      const orderId = req.params.id;

      // Get complete order details
      const orderQuery = `
        SELECT o.*, 
               u.first_name as customer_first_name, u.last_name as customer_last_name, 
               u.email as customer_email, u.phone as customer_phone, u.created_at as customer_since,
               a.first_name, a.last_name, a.address_line1, a.address_line2,
               a.city, a.state, a.postal_code, a.country, a.phone,
               p.payment_status, p.razorpay_payment_id, p.razorpay_order_id,
               p.razorpay_signature, p.transaction_id, p.created_at as payment_created_at
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN addresses a ON o.shipping_address_id = a.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ?
      `;

      const orderResult = await secureExecuteQuery(orderQuery, [orderId]);
      
      if (orderResult.length === 0) {
        return res.status(404).json({
          error: 'Order not found'
        });
      }

      const order = orderResult[0];

      // Get order items with product details
      const itemsQuery = `
        SELECT oi.quantity, oi.unit_price, oi.total_price,
               p.id as product_id, p.name as product_name, p.brand, p.sku,
               p.stock_quantity as current_stock,
               pi.image_url,
               c.name as category_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?
      `;
      
      const items = await secureExecuteQuery(itemsQuery, [orderId]);

      // Get order status history if available
      const historyQuery = `
        SELECT osh.previous_status, osh.new_status, osh.notes, osh.created_at,
               a.name as changed_by_admin
        FROM order_status_history osh
        LEFT JOIN admins a ON osh.changed_by_admin_id = a.id
        WHERE osh.order_id = ?
        ORDER BY osh.created_at DESC
      `;
      
      let statusHistory = [];
      try {
        statusHistory = await secureExecuteQuery(historyQuery, [orderId]);
      } catch (error) {
        // Table might not exist yet, ignore error
        console.log('Order status history table not available');
      }

      // Get customer's order statistics
      const customerStatsQuery = `
        SELECT COUNT(*) as total_orders,
               SUM(total_amount) as total_spent,
               AVG(total_amount) as average_order_value,
               MAX(created_at) as last_order_date
        FROM orders
        WHERE user_id = ?
      `;
      
      const customerStats = await secureExecuteQuery(customerStatsQuery, [order.user_id]);

      // Format comprehensive response
      const orderDetails = {
        id: order.id,
        order_number: order.order_number,
        status: order.status,
        total_amount: parseFloat(order.total_amount),
        discount_amount: parseFloat(order.discount_amount),
        shipping_amount: parseFloat(order.shipping_amount),
        tax_amount: parseFloat(order.tax_amount),
        coupon_code: order.coupon_code,
        payment_method: order.payment_method,
        payment_status: order.payment_status,
        notes: order.notes,
        created_at: order.created_at,
        updated_at: order.updated_at,
        customer: {
          id: order.user_id,
          first_name: order.customer_first_name,
          last_name: order.customer_last_name,
          email: order.customer_email,
          phone: order.customer_phone,
          member_since: order.customer_since,
          statistics: {
            total_orders: customerStats[0]?.total_orders || 0,
            total_spent: parseFloat(customerStats[0]?.total_spent || 0),
            average_order_value: parseFloat(customerStats[0]?.average_order_value || 0),
            last_order_date: customerStats[0]?.last_order_date
          }
        },
        shipping_address: {
          first_name: order.first_name,
          last_name: order.last_name,
          address_line1: order.address_line1,
          address_line2: order.address_line2,
          city: order.city,
          state: order.state,
          postal_code: order.postal_code,
          country: order.country,
          phone: order.phone
        },
        payment_details: {
          status: order.payment_status,
          razorpay_payment_id: order.razorpay_payment_id,
          razorpay_order_id: order.razorpay_order_id,
          razorpay_signature: order.razorpay_signature,
          transaction_id: order.transaction_id,
          payment_created_at: order.payment_created_at
        },
        items: items.map(item => ({
          product_id: item.product_id,
          product_name: item.product_name,
          brand: item.brand,
          sku: item.sku,
          category: item.category_name,
          quantity: item.quantity,
          unit_price: parseFloat(item.unit_price),
          total_price: parseFloat(item.total_price),
          image_url: item.image_url,
          current_stock: item.current_stock
        })),
        status_history: statusHistory.map(history => ({
          previous_status: history.previous_status,
          new_status: history.new_status,
          notes: history.notes,
          changed_at: history.created_at,
          changed_by: history.changed_by_admin || 'System'
        })),
        order_summary: {
          item_count: items.length,
          total_quantity: items.reduce((sum, item) => sum + item.quantity, 0),
          subtotal: items.reduce((sum, item) => sum + parseFloat(item.total_price), 0)
        }
      };

      res.json({
        order: orderDetails
      });

    } catch (error) {
      console.error('Admin get order details error:', error);
      res.status(500).json({
        error: 'Failed to retrieve order details'
      });
    }
  }
);

/**
 * PUT /admin/orders/bulk-status
 * Bulk update order status for multiple orders
 * Requires admin authentication
 * Requirements: 8.2
 */
router.put('/admin/orders/bulk-status', 
  authenticateAdmin,
  validation.bulkOrderStatus,
  async (req, res) => {
    try {
      const { order_ids, status, notes } = req.body;
      const adminId = req.admin.id;

      // Validate input
      if (!Array.isArray(order_ids) || order_ids.length === 0) {
        return res.status(400).json({
          error: 'order_ids must be a non-empty array'
        });
      }

      if (!['placed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'].includes(status)) {
        return res.status(400).json({
          error: 'Invalid status'
        });
      }

      const results = [];
      const errors = [];

      await secureExecuteTransactionCallback(async (connection) => {
        for (const orderId of order_ids) {
          try {
            // Get current order status
            const orderResult = await connection.execute(
              `SELECT id, status, order_number FROM orders WHERE id = ?`,
              [orderId]
            );

            if (orderResult[0].length === 0) {
              errors.push({ order_id: orderId, error: 'Order not found' });
              continue;
            }

            const order = orderResult[0][0];
            const currentStatus = order.status;

            // Validate status transition
            if (!isValidStatusTransition(currentStatus, status)) {
              errors.push({ 
                order_id: orderId, 
                order_number: order.order_number,
                error: `Invalid status transition from ${currentStatus} to ${status}` 
              });
              continue;
            }

            // Update order status
            await connection.execute(
              `UPDATE orders 
               SET status = ?, notes = COALESCE(CONCAT(COALESCE(notes, ""), ?, "\n"), ?), updated_at = CURRENT_TIMESTAMP
               WHERE id = ?`,
              [status, `[${new Date().toISOString()}] Bulk Update: ${notes || 'Status updated'}`, notes, orderId]
            );

            // Handle status-specific actions
            if (status === 'delivered') {
              await connection.execute(
                `UPDATE payments 
                 SET payment_status = 'completed', updated_at = CURRENT_TIMESTAMP
                 WHERE order_id = ? AND payment_method = 'cod'`,
                [orderId]
              );
            }

            if (status === 'cancelled') {
              // Restore product stock
              await connection.execute(
                `UPDATE products p
                 JOIN order_items oi ON p.id = oi.product_id
                 SET p.stock_quantity = p.stock_quantity + oi.quantity
                 WHERE oi.order_id = ?`,
                [orderId]
              );
            }

            results.push({
              order_id: orderId,
              order_number: order.order_number,
              previous_status: currentStatus,
              new_status: status,
              success: true
            });

          } catch (error) {
            errors.push({ 
              order_id: orderId, 
              error: error.message 
            });
          }
        }
      });

      res.json({
        message: `Bulk status update completed. ${results.length} orders updated successfully, ${errors.length} errors.`,
        results,
        errors,
        summary: {
          total_orders: order_ids.length,
          successful_updates: results.length,
          failed_updates: errors.length,
          new_status: status,
          updated_by: {
            admin_id: adminId,
            admin_name: req.admin.name
          }
        }
      });

    } catch (error) {
      console.error('Bulk order status update error:', error);
      res.status(500).json({
        error: 'Failed to perform bulk status update'
      });
    }
  }
);

/**
 * GET /admin/orders/export
 * Export orders data in CSV format
 * Requires admin authentication
 * Requirements: 8.1, 8.4
 */
router.get('/admin/orders/export', 
  authenticateAdmin,
  async (req, res) => {
    try {
      const { 
        status, 
        start_date, 
        end_date,
        format = 'csv'
      } = req.query;
      
      let whereClause = 'WHERE 1=1';
      let queryParams = [];
      
      if (status) {
        whereClause += ' AND o.status = ?';
        queryParams.push(status);
      }
      
      if (start_date) {
        whereClause += ' AND DATE(o.created_at) >= ?';
        queryParams.push(start_date);
      }
      
      if (end_date) {
        whereClause += ' AND DATE(o.created_at) <= ?';
        queryParams.push(end_date);
      }

      const exportQuery = `
        SELECT o.order_number, o.status, o.total_amount, o.discount_amount,
               o.shipping_amount, o.tax_amount, o.coupon_code,
               o.payment_method, o.payment_status, o.created_at,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               CONCAT(a.address_line1, ', ', a.city, ', ', a.state, ' ', a.postal_code) as shipping_address,
               COUNT(oi.id) as item_count,
               SUM(oi.quantity) as total_items
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN addresses a ON o.shipping_address_id = a.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        ${whereClause}
        GROUP BY o.id
        ORDER BY o.created_at DESC
      `;
      
      const orders = await secureExecuteQuery(exportQuery, queryParams);

      if (format === 'csv') {
        // Generate CSV content
        const csvHeaders = [
          'Order Number', 'Status', 'Customer Name', 'Customer Email',
          'Total Amount', 'Discount Amount', 'Shipping Amount', 'Tax Amount',
          'Payment Method', 'Payment Status', 'Item Count', 'Total Items',
          'Shipping Address', 'Order Date'
        ];

        const csvRows = orders.map(order => [
          order.order_number,
          order.status,
          order.customer_name,
          order.customer_email,
          order.total_amount,
          order.discount_amount || 0,
          order.shipping_amount || 0,
          order.tax_amount || 0,
          order.payment_method,
          order.payment_status,
          order.item_count,
          order.total_items,
          order.shipping_address,
          new Date(order.created_at).toISOString().split('T')[0]
        ]);

        const csvContent = [csvHeaders, ...csvRows]
          .map(row => row.map(field => `"${field}"`).join(','))
          .join('\n');

        res.setHeader('Content-Type', 'text/csv');
        res.setHeader('Content-Disposition', `attachment; filename="orders-export-${new Date().toISOString().split('T')[0]}.csv"`);
        res.send(csvContent);
      } else {
        // Return JSON format
        res.json({
          orders: orders.map(order => ({
            order_number: order.order_number,
            status: order.status,
            customer_name: order.customer_name,
            customer_email: order.customer_email,
            total_amount: parseFloat(order.total_amount),
            discount_amount: parseFloat(order.discount_amount || 0),
            shipping_amount: parseFloat(order.shipping_amount || 0),
            tax_amount: parseFloat(order.tax_amount || 0),
            payment_method: order.payment_method,
            payment_status: order.payment_status,
            item_count: order.item_count,
            total_items: order.total_items,
            shipping_address: order.shipping_address,
            order_date: order.created_at
          })),
          export_info: {
            total_orders: orders.length,
            export_date: new Date().toISOString(),
            filters_applied: { status, start_date, end_date }
          }
        });
      }

    } catch (error) {
      console.error('Order export error:', error);
      res.status(500).json({
        error: 'Failed to export orders'
      });
    }
  }
);

module.exports = router;