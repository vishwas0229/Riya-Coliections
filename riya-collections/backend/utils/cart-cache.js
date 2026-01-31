/**
 * Advanced Cart Caching System
 * 
 * This module provides optimized cart management with:
 * - Redis-backed cart storage with fallback
 * - Cart session management
 * - Real-time stock validation
 * - Cart performance optimization
 * 
 * Requirements: 3.1, 3.2, 10.3, 11.4
 */

const { cacheManager, CacheKeys } = require('./cache-manager');
const { appLogger } = require('../config/logging');
const { secureExecuteQuery } = require('../middleware/database-security');

class CartCacheManager {
  constructor() {
    this.sessionTimeout = 30 * 60 * 1000; // 30 minutes
    this.maxCartItems = 50; // Maximum items per cart
  }

  /**
   * Get user cart with caching
   */
  async getCart(userId) {
    try {
      const cacheKey = CacheKeys.cart(userId);
      let cart = await cacheManager.get(cacheKey);
      
      if (!cart) {
        // Initialize empty cart
        cart = {
          userId,
          items: [],
          totals: {
            subtotal: 0,
            discount: 0,
            shipping: 0,
            tax: 0,
            total: 0
          },
          updatedAt: new Date().toISOString(),
          expiresAt: new Date(Date.now() + this.sessionTimeout).toISOString()
        };
        
        await this.saveCart(cart);
      }
      
      // Check if cart has expired
      if (new Date(cart.expiresAt) < new Date()) {
        await this.clearCart(userId);
        return await this.getCart(userId); // Return fresh cart
      }
      
      return cart;
      
    } catch (error) {
      appLogger.error('Error getting cart', { userId, error: error.message });
      throw new Error('Failed to retrieve cart');
    }
  }

  /**
   * Save cart to cache
   */
  async saveCart(cart) {
    try {
      const cacheKey = CacheKeys.cart(cart.userId);
      cart.updatedAt = new Date().toISOString();
      cart.expiresAt = new Date(Date.now() + this.sessionTimeout).toISOString();
      
      await cacheManager.set(cacheKey, cart, this.sessionTimeout / 1000);
      
      appLogger.debug('Cart saved', { 
        userId: cart.userId, 
        itemCount: cart.items.length,
        total: cart.totals.total
      });
      
      return true;
      
    } catch (error) {
      appLogger.error('Error saving cart', { 
        userId: cart.userId, 
        error: error.message 
      });
      return false;
    }
  }

  /**
   * Add item to cart with stock validation
   */
  async addItem(userId, productId, quantity = 1) {
    try {
      // Validate product and stock
      const product = await this.validateProductStock(productId, quantity);
      
      // Get current cart
      const cart = await this.getCart(userId);
      
      // Check cart size limit
      if (cart.items.length >= this.maxCartItems) {
        throw new Error(`Cart cannot contain more than ${this.maxCartItems} different items`);
      }
      
      // Check if item already exists in cart
      const existingItemIndex = cart.items.findIndex(item => item.productId === productId);
      
      if (existingItemIndex >= 0) {
        // Update existing item
        const existingItem = cart.items[existingItemIndex];
        const newQuantity = existingItem.quantity + quantity;
        
        // Validate total quantity against stock
        await this.validateProductStock(productId, newQuantity);
        
        cart.items[existingItemIndex] = {
          ...existingItem,
          quantity: newQuantity,
          totalPrice: product.price * newQuantity,
          updatedAt: new Date().toISOString()
        };
      } else {
        // Add new item
        cart.items.push({
          productId,
          name: product.name,
          price: product.price,
          quantity,
          totalPrice: product.price * quantity,
          brand: product.brand,
          sku: product.sku,
          imageUrl: product.imageUrl,
          stockQuantity: product.stock_quantity,
          addedAt: new Date().toISOString(),
          updatedAt: new Date().toISOString()
        });
      }
      
      // Recalculate totals
      await this.calculateTotals(cart);
      
      // Save updated cart
      await this.saveCart(cart);
      
      appLogger.info('Item added to cart', {
        userId,
        productId,
        quantity,
        cartTotal: cart.totals.total
      });
      
      return cart;
      
    } catch (error) {
      appLogger.error('Error adding item to cart', {
        userId,
        productId,
        quantity,
        error: error.message
      });
      throw error;
    }
  }

  /**
   * Update item quantity in cart
   */
  async updateItem(userId, productId, quantity) {
    try {
      if (quantity <= 0) {
        return await this.removeItem(userId, productId);
      }
      
      // Validate stock
      const product = await this.validateProductStock(productId, quantity);
      
      // Get current cart
      const cart = await this.getCart(userId);
      
      // Find item in cart
      const itemIndex = cart.items.findIndex(item => item.productId === productId);
      
      if (itemIndex === -1) {
        throw new Error('Item not found in cart');
      }
      
      // Update item
      cart.items[itemIndex] = {
        ...cart.items[itemIndex],
        quantity,
        totalPrice: product.price * quantity,
        updatedAt: new Date().toISOString()
      };
      
      // Recalculate totals
      await this.calculateTotals(cart);
      
      // Save updated cart
      await this.saveCart(cart);
      
      appLogger.info('Cart item updated', {
        userId,
        productId,
        quantity,
        cartTotal: cart.totals.total
      });
      
      return cart;
      
    } catch (error) {
      appLogger.error('Error updating cart item', {
        userId,
        productId,
        quantity,
        error: error.message
      });
      throw error;
    }
  }

  /**
   * Remove item from cart
   */
  async removeItem(userId, productId) {
    try {
      // Get current cart
      const cart = await this.getCart(userId);
      
      // Remove item
      const initialLength = cart.items.length;
      cart.items = cart.items.filter(item => item.productId !== productId);
      
      if (cart.items.length === initialLength) {
        throw new Error('Item not found in cart');
      }
      
      // Recalculate totals
      await this.calculateTotals(cart);
      
      // Save updated cart
      await this.saveCart(cart);
      
      appLogger.info('Item removed from cart', {
        userId,
        productId,
        cartTotal: cart.totals.total
      });
      
      return cart;
      
    } catch (error) {
      appLogger.error('Error removing cart item', {
        userId,
        productId,
        error: error.message
      });
      throw error;
    }
  }

  /**
   * Clear entire cart
   */
  async clearCart(userId) {
    try {
      const cacheKey = CacheKeys.cart(userId);
      await cacheManager.delete(cacheKey);
      
      appLogger.info('Cart cleared', { userId });
      
      return true;
      
    } catch (error) {
      appLogger.error('Error clearing cart', { userId, error: error.message });
      return false;
    }
  }

  /**
   * Apply coupon to cart
   */
  async applyCoupon(userId, couponCode) {
    try {
      // Get current cart
      const cart = await this.getCart(userId);
      
      if (cart.items.length === 0) {
        throw new Error('Cannot apply coupon to empty cart');
      }
      
      // Validate coupon
      const coupon = await this.validateCoupon(couponCode, cart.totals.subtotal);
      
      // Apply coupon
      cart.coupon = {
        code: couponCode,
        type: coupon.discount_type,
        value: coupon.discount_value,
        appliedAt: new Date().toISOString()
      };
      
      // Recalculate totals with coupon
      await this.calculateTotals(cart);
      
      // Save updated cart
      await this.saveCart(cart);
      
      appLogger.info('Coupon applied to cart', {
        userId,
        couponCode,
        discount: cart.totals.discount,
        cartTotal: cart.totals.total
      });
      
      return cart;
      
    } catch (error) {
      appLogger.error('Error applying coupon', {
        userId,
        couponCode,
        error: error.message
      });
      throw error;
    }
  }

  /**
   * Remove coupon from cart
   */
  async removeCoupon(userId) {
    try {
      // Get current cart
      const cart = await this.getCart(userId);
      
      // Remove coupon
      delete cart.coupon;
      
      // Recalculate totals without coupon
      await this.calculateTotals(cart);
      
      // Save updated cart
      await this.saveCart(cart);
      
      appLogger.info('Coupon removed from cart', {
        userId,
        cartTotal: cart.totals.total
      });
      
      return cart;
      
    } catch (error) {
      appLogger.error('Error removing coupon', { userId, error: error.message });
      throw error;
    }
  }

  /**
   * Validate product stock
   */
  async validateProductStock(productId, requestedQuantity) {
    try {
      const products = await secureExecuteQuery(
        `SELECT id, name, price, stock_quantity, is_active, brand, sku
         FROM products 
         WHERE id = ? AND is_active = TRUE`,
        [productId]
      );

      if (products.length === 0) {
        throw new Error('Product not found or inactive');
      }

      const product = products[0];

      if (product.stock_quantity < requestedQuantity) {
        throw new Error(
          `Insufficient stock. Available: ${product.stock_quantity}, Requested: ${requestedQuantity}`
        );
      }

      // Get primary image
      const images = await secureExecuteQuery(
        `SELECT image_url FROM product_images 
         WHERE product_id = ? AND is_primary = TRUE 
         LIMIT 1`,
        [productId]
      );

      return {
        ...product,
        imageUrl: images.length > 0 ? images[0].image_url : null
      };
      
    } catch (error) {
      appLogger.error('Product stock validation failed', {
        productId,
        requestedQuantity,
        error: error.message
      });
      throw error;
    }
  }

  /**
   * Validate coupon
   */
  async validateCoupon(couponCode, subtotal) {
    try {
      const coupons = await secureExecuteQuery(
        `SELECT id, code, discount_type, discount_value, minimum_amount, 
                maximum_discount, usage_limit, used_count
         FROM coupons 
         WHERE code = ? AND is_active = TRUE 
               AND (valid_from IS NULL OR valid_from <= NOW())
               AND (valid_until IS NULL OR valid_until >= NOW())`,
        [couponCode]
      );

      if (coupons.length === 0) {
        throw new Error('Invalid or expired coupon code');
      }

      const coupon = coupons[0];

      // Check usage limit
      if (coupon.usage_limit && coupon.used_count >= coupon.usage_limit) {
        throw new Error('Coupon usage limit exceeded');
      }

      // Check minimum amount
      if (coupon.minimum_amount && subtotal < coupon.minimum_amount) {
        throw new Error(
          `Minimum order amount of ₹${coupon.minimum_amount} required for this coupon`
        );
      }

      return coupon;
      
    } catch (error) {
      appLogger.error('Coupon validation failed', {
        couponCode,
        subtotal,
        error: error.message
      });
      throw error;
    }
  }

  /**
   * Calculate cart totals
   */
  async calculateTotals(cart) {
    try {
      // Calculate subtotal
      const subtotal = cart.items.reduce((sum, item) => sum + item.totalPrice, 0);
      
      // Calculate discount
      let discount = 0;
      if (cart.coupon) {
        if (cart.coupon.type === 'percentage') {
          discount = (subtotal * cart.coupon.value) / 100;
          if (cart.coupon.maximum_discount) {
            discount = Math.min(discount, cart.coupon.maximum_discount);
          }
        } else if (cart.coupon.type === 'fixed') {
          discount = Math.min(cart.coupon.value, subtotal);
        }
      }
      
      // Calculate shipping (free shipping above ₹500)
      const shipping = subtotal >= 500 ? 0 : 50;
      
      // Calculate tax (18% GST)
      const taxableAmount = subtotal - discount + shipping;
      const tax = taxableAmount * 0.18;
      
      // Calculate total
      const total = subtotal - discount + shipping + tax;
      
      cart.totals = {
        subtotal: parseFloat(subtotal.toFixed(2)),
        discount: parseFloat(discount.toFixed(2)),
        shipping: parseFloat(shipping.toFixed(2)),
        tax: parseFloat(tax.toFixed(2)),
        total: parseFloat(total.toFixed(2))
      };
      
      return cart.totals;
      
    } catch (error) {
      appLogger.error('Error calculating cart totals', { error: error.message });
      throw error;
    }
  }

  /**
   * Validate entire cart before checkout
   */
  async validateCartForCheckout(userId) {
    try {
      const cart = await this.getCart(userId);
      
      if (cart.items.length === 0) {
        throw new Error('Cart is empty');
      }
      
      const validationResults = [];
      
      // Validate each item
      for (const item of cart.items) {
        try {
          await this.validateProductStock(item.productId, item.quantity);
          validationResults.push({
            productId: item.productId,
            valid: true
          });
        } catch (error) {
          validationResults.push({
            productId: item.productId,
            valid: false,
            error: error.message
          });
        }
      }
      
      const invalidItems = validationResults.filter(result => !result.valid);
      
      if (invalidItems.length > 0) {
        throw new Error(`Cart validation failed: ${invalidItems.length} items have issues`);
      }
      
      return {
        valid: true,
        cart,
        validationResults
      };
      
    } catch (error) {
      appLogger.error('Cart validation failed', { userId, error: error.message });
      throw error;
    }
  }

  /**
   * Get cart statistics
   */
  async getCartStats(userId) {
    try {
      const cart = await this.getCart(userId);
      
      return {
        itemCount: cart.items.length,
        totalQuantity: cart.items.reduce((sum, item) => sum + item.quantity, 0),
        subtotal: cart.totals.subtotal,
        total: cart.totals.total,
        hasDiscount: cart.totals.discount > 0,
        hasCoupon: !!cart.coupon,
        updatedAt: cart.updatedAt,
        expiresAt: cart.expiresAt
      };
      
    } catch (error) {
      appLogger.error('Error getting cart stats', { userId, error: error.message });
      throw error;
    }
  }
}

// Create singleton instance
const cartCache = new CartCacheManager();

module.exports = {
  cartCache,
  CartCacheManager
};