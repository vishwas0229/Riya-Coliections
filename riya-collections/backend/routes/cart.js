const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const { authenticateToken, optionalAuth } = require('../middleware/auth');
const { validationMiddleware, createValidationMiddleware, handleValidationErrors } = require('../middleware/validation');
const { cartCache } = require('../utils/cart-cache');
const { appLogger } = require('../config/logging');

/**
 * Shopping Cart Management Routes with Advanced Caching
 * 
 * Implements optimized cart operations with:
 * - Redis-backed cart storage with fallback
 * - Real-time stock validation
 * - Performance monitoring
 * - Cache invalidation strategies
 * 
 * Requirements: 3.1, 3.2, 10.3, 11.4
 */

/**
 * GET /api/cart
 * Get user's cart contents
 * Requires authentication
 */
router.get('/', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.id;
    const cart = await cartCache.getCart(userId);
    
    res.json({
      success: true,
      message: 'Cart retrieved successfully',
      data: {
        cart,
        stats: await cartCache.getCartStats(userId)
      }
    });
    
  } catch (error) {
    appLogger.error('Error retrieving cart', { 
      userId: req.user?.id, 
      error: error.message 
    });
    
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve cart',
      error: error.message
    });
  }
});

/**
 * POST /api/cart/add
 * Add item to cart with stock validation
 * Requires authentication
 */
router.post('/add', 
  authenticateToken,
  [
    body('product_id').isInt({ min: 1 }).withMessage('Valid product ID is required'),
    body('quantity').isInt({ min: 1, max: 50 }).withMessage('Quantity must be between 1 and 50')
  ],
  handleValidationErrors,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const { product_id, quantity = 1 } = req.body;
      
      const cart = await cartCache.addItem(userId, product_id, quantity);
      
      // Broadcast cart update via WebSocket
      if (global.wsServer) {
        global.wsServer.broadcastCartUpdate(userId, cart);
      }
      
      res.json({
        success: true,
        message: 'Item added to cart successfully',
        data: {
          cart,
          stats: await cartCache.getCartStats(userId)
        }
      });
      
    } catch (error) {
      appLogger.error('Error adding item to cart', {
        userId: req.user?.id,
        productId: req.body.product_id,
        quantity: req.body.quantity,
        error: error.message
      });
      
      res.status(400).json({
        success: false,
        message: error.message || 'Failed to add item to cart'
      });
    }
  }
);

/**
 * PUT /api/cart/update
 * Update item quantity in cart
 * Requires authentication
 */
router.put('/update',
  authenticateToken,
  [
    body('product_id').isInt({ min: 1 }).withMessage('Valid product ID is required'),
    body('quantity').isInt({ min: 0, max: 50 }).withMessage('Quantity must be between 0 and 50')
  ],
  handleValidationErrors,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const { product_id, quantity } = req.body;
      
      const cart = await cartCache.updateItem(userId, product_id, quantity);
      
      // Broadcast cart update via WebSocket
      if (global.wsServer) {
        global.wsServer.broadcastCartUpdate(userId, cart);
      }
      
      res.json({
        success: true,
        message: 'Cart item updated successfully',
        data: {
          cart,
          stats: await cartCache.getCartStats(userId)
        }
      });
      
    } catch (error) {
      appLogger.error('Error updating cart item', {
        userId: req.user?.id,
        productId: req.body.product_id,
        quantity: req.body.quantity,
        error: error.message
      });
      
      res.status(400).json({
        success: false,
        message: error.message || 'Failed to update cart item'
      });
    }
  }
);

/**
 * DELETE /api/cart/remove
 * Remove item from cart
 * Requires authentication
 */
router.delete('/remove',
  authenticateToken,
  [
    body('product_id').isInt({ min: 1 }).withMessage('Valid product ID is required')
  ],
  handleValidationErrors,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const { product_id } = req.body;
      
      const cart = await cartCache.removeItem(userId, product_id);
      
      res.json({
        success: true,
        message: 'Item removed from cart successfully',
        data: {
          cart,
          stats: await cartCache.getCartStats(userId)
        }
      });
      
    } catch (error) {
      appLogger.error('Error removing cart item', {
        userId: req.user?.id,
        productId: req.body.product_id,
        error: error.message
      });
      
      res.status(400).json({
        success: false,
        message: error.message || 'Failed to remove cart item'
      });
    }
  }
);

/**
 * DELETE /api/cart/clear
 * Clear entire cart
 * Requires authentication
 */
router.delete('/clear', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.id;
    
    await cartCache.clearCart(userId);
    
    res.json({
      success: true,
      message: 'Cart cleared successfully'
    });
    
  } catch (error) {
    appLogger.error('Error clearing cart', {
      userId: req.user?.id,
      error: error.message
    });
    
    res.status(500).json({
      success: false,
      message: 'Failed to clear cart'
    });
  }
});

/**
 * POST /api/cart/coupon/apply
 * Apply coupon to cart
 * Requires authentication
 */
router.post('/coupon/apply',
  authenticateToken,
  [
    body('coupon_code').isString().trim().isLength({ min: 1, max: 50 })
      .withMessage('Valid coupon code is required')
  ],
  handleValidationErrors,
  async (req, res) => {
    try {
      const userId = req.user.id;
      const { coupon_code } = req.body;
      
      const cart = await cartCache.applyCoupon(userId, coupon_code);
      
      res.json({
        success: true,
        message: 'Coupon applied successfully',
        data: {
          cart,
          stats: await cartCache.getCartStats(userId),
          discount: cart.totals.discount
        }
      });
      
    } catch (error) {
      appLogger.error('Error applying coupon', {
        userId: req.user?.id,
        couponCode: req.body.coupon_code,
        error: error.message
      });
      
      res.status(400).json({
        success: false,
        message: error.message || 'Failed to apply coupon'
      });
    }
  }
);

/**
 * DELETE /api/cart/coupon/remove
 * Remove coupon from cart
 * Requires authentication
 */
router.delete('/coupon/remove', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.id;
    
    const cart = await cartCache.removeCoupon(userId);
    
    res.json({
      success: true,
      message: 'Coupon removed successfully',
      data: {
        cart,
        stats: await cartCache.getCartStats(userId)
      }
    });
    
  } catch (error) {
    appLogger.error('Error removing coupon', {
      userId: req.user?.id,
      error: error.message
    });
    
    res.status(400).json({
      success: false,
      message: error.message || 'Failed to remove coupon'
    });
  }
});

/**
 * POST /api/cart/validate
 * Validate cart for checkout
 * Requires authentication
 */
router.post('/validate', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.id;
    
    const validation = await cartCache.validateCartForCheckout(userId);
    
    res.json({
      success: true,
      message: 'Cart validation completed',
      data: validation
    });
    
  } catch (error) {
    appLogger.error('Error validating cart', {
      userId: req.user?.id,
      error: error.message
    });
    
    res.status(400).json({
      success: false,
      message: error.message || 'Cart validation failed'
    });
  }
});

/**
 * GET /api/cart/stats
 * Get cart statistics
 * Requires authentication
 */
router.get('/stats', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.id;
    const stats = await cartCache.getCartStats(userId);
    
    res.json({
      success: true,
      message: 'Cart statistics retrieved successfully',
      data: stats
    });
    
  } catch (error) {
    appLogger.error('Error getting cart stats', {
      userId: req.user?.id,
      error: error.message
    });
    
    res.status(500).json({
      success: false,
      message: 'Failed to retrieve cart statistics'
    });
  }
});

/**
 * GET /api/cart/count
 * Get cart item count for authenticated user
 */
router.get('/count', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.id;
    const stats = await cartCache.getCartStats(userId);
    
    res.json({
      success: true,
      data: {
        count: stats.totalQuantity
      }
    });
    
  } catch (error) {
    appLogger.error('Error getting cart count', {
      userId: req.user?.id,
      error: error.message
    });
    
    res.status(500).json({
      success: false,
      message: 'Failed to get cart count'
    });
  }
});

module.exports = router;