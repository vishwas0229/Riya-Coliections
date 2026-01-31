const express = require('express');
const router = express.Router();
const { secureExecuteQuery } = require('../middleware/database-security');
const { authenticateAdmin } = require('../middleware/auth');

/**
 * Admin Dashboard Routes
 * Provides administrative functionality and metrics
 */

/**
 * GET /api/admin/dashboard
 * Get admin dashboard metrics and overview data
 * Requirements: 8.1 - Admin order dashboard display
 */
router.get('/dashboard', authenticateAdmin, async (req, res) => {
  try {
    // Get key metrics for dashboard
    const [
      orderStats,
      productStats,
      userStats,
      recentOrders,
      topProducts,
      revenueStats
    ] = await Promise.all([
      // Order statistics
      secureExecuteQuery(`
        SELECT 
          COUNT(*) as total_orders,
          COUNT(CASE WHEN status = 'placed' THEN 1 END) as pending_orders,
          COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
          COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_orders,
          COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
          COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
          COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_orders,
          COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_orders
        FROM orders
      `),
      
      // Product statistics
      secureExecuteQuery(`
        SELECT 
          COUNT(*) as total_products,
          COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
          COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
          COUNT(CASE WHEN stock_quantity <= 10 AND stock_quantity > 0 THEN 1 END) as low_stock
        FROM products
      `),
      
      // User statistics
      secureExecuteQuery(`
        SELECT 
          COUNT(*) as total_users,
          COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_registrations,
          COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_registrations
        FROM users
      `),
      
      // Recent orders (last 10)
      secureExecuteQuery(`
        SELECT 
          o.id,
          o.order_number,
          o.status,
          o.total_amount,
          o.payment_method,
          o.payment_status,
          o.created_at,
          u.first_name,
          u.last_name,
          u.email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 10
      `),
      
      // Top selling products (last 30 days)
      secureExecuteQuery(`
        SELECT 
          p.id,
          p.name,
          p.price,
          SUM(oi.quantity) as total_sold,
          SUM(oi.total_price) as total_revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND o.status != 'cancelled'
        GROUP BY p.id, p.name, p.price
        ORDER BY total_sold DESC
        LIMIT 5
      `),
      
      // Revenue statistics
      secureExecuteQuery(`
        SELECT 
          SUM(total_amount) as total_revenue,
          SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) as today_revenue,
          SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN total_amount ELSE 0 END) as week_revenue,
          SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END) as month_revenue,
          AVG(total_amount) as avg_order_value
        FROM orders
        WHERE status != 'cancelled'
      `)
    ]);

    // Format the response
    const dashboardData = {
      orders: {
        total: orderStats[0].total_orders || 0,
        pending: orderStats[0].pending_orders || 0,
        processing: orderStats[0].processing_orders || 0,
        shipped: orderStats[0].shipped_orders || 0,
        delivered: orderStats[0].delivered_orders || 0,
        cancelled: orderStats[0].cancelled_orders || 0,
        today: orderStats[0].today_orders || 0,
        thisWeek: orderStats[0].week_orders || 0
      },
      
      products: {
        total: productStats[0].total_products || 0,
        active: productStats[0].active_products || 0,
        outOfStock: productStats[0].out_of_stock || 0,
        lowStock: productStats[0].low_stock || 0
      },
      
      users: {
        total: userStats[0].total_users || 0,
        todayRegistrations: userStats[0].today_registrations || 0,
        weekRegistrations: userStats[0].week_registrations || 0
      },
      
      revenue: {
        total: parseFloat(revenueStats[0].total_revenue || 0),
        today: parseFloat(revenueStats[0].today_revenue || 0),
        thisWeek: parseFloat(revenueStats[0].week_revenue || 0),
        thisMonth: parseFloat(revenueStats[0].month_revenue || 0),
        averageOrderValue: parseFloat(revenueStats[0].avg_order_value || 0)
      },
      
      recentOrders: recentOrders.map(order => ({
        id: order.id,
        orderNumber: order.order_number,
        status: order.status,
        totalAmount: parseFloat(order.total_amount),
        paymentMethod: order.payment_method,
        paymentStatus: order.payment_status,
        createdAt: order.created_at,
        customer: {
          name: `${order.first_name} ${order.last_name}`,
          email: order.email
        }
      })),
      
      topProducts: topProducts.map(product => ({
        id: product.id,
        name: product.name,
        price: parseFloat(product.price),
        totalSold: parseInt(product.total_sold),
        totalRevenue: parseFloat(product.total_revenue)
      }))
    };

    res.json({
      success: true,
      message: 'Dashboard data retrieved successfully',
      data: dashboardData
    });

  } catch (error) {
    console.error('Admin dashboard error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve dashboard data'
    });
  }
});

/**
 * GET /api/admin/stats/orders
 * Get detailed order statistics for charts
 */
router.get('/stats/orders', authenticateAdmin, async (req, res) => {
  try {
    const { period = '30' } = req.query;
    const days = parseInt(period);

    // Get daily order counts for the specified period
    const dailyStats = await secureExecuteQuery(`
      SELECT 
        DATE(created_at) as date,
        COUNT(*) as order_count,
        SUM(total_amount) as revenue,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count
      FROM orders
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
      GROUP BY DATE(created_at)
      ORDER BY date ASC
    `, [days]);

    // Get status distribution
    const statusStats = await secureExecuteQuery(`
      SELECT 
        status,
        COUNT(*) as count
      FROM orders
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
      GROUP BY status
    `, [days]);

    res.json({
      success: true,
      data: {
        daily: dailyStats,
        statusDistribution: statusStats
      }
    });

  } catch (error) {
    console.error('Order stats error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve order statistics'
    });
  }
});

/**
 * GET /api/admin/stats/products
 * Get product performance statistics
 */
router.get('/stats/products', authenticateAdmin, async (req, res) => {
  try {
    // Get category performance
    const categoryStats = await secureExecuteQuery(`
      SELECT 
        c.name as category_name,
        COUNT(p.id) as product_count,
        SUM(COALESCE(oi.quantity, 0)) as total_sold,
        SUM(COALESCE(oi.total_price, 0)) as total_revenue
      FROM categories c
      LEFT JOIN products p ON c.id = p.category_id
      LEFT JOIN order_items oi ON p.id = oi.product_id
      LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
      WHERE c.is_active = 1
      GROUP BY c.id, c.name
      ORDER BY total_revenue DESC
    `);

    // Get inventory alerts
    const inventoryAlerts = await secureExecuteQuery(`
      SELECT 
        id,
        name,
        stock_quantity,
        CASE 
          WHEN stock_quantity = 0 THEN 'out_of_stock'
          WHEN stock_quantity <= 10 THEN 'low_stock'
          ELSE 'normal'
        END as alert_type
      FROM products
      WHERE is_active = 1 AND stock_quantity <= 10
      ORDER BY stock_quantity ASC
    `);

    res.json({
      success: true,
      data: {
        categoryPerformance: categoryStats,
        inventoryAlerts: inventoryAlerts
      }
    });

  } catch (error) {
    console.error('Product stats error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve product statistics'
    });
  }
});

module.exports = router;