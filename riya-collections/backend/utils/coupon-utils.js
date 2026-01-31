const { secureExecuteQuery } = require('../middleware/database-security');

/**
 * Coupon Utility Functions
 * 
 * Extracted functions for coupon validation and cart calculations
 * to enable property-based testing while maintaining encapsulation
 */

/**
 * Helper function to validate and apply coupon
 */
const validateAndApplyCoupon = async (couponCode, subtotal) => {
  if (!couponCode) {
    return {
      valid: false,
      discount: 0,
      message: 'No coupon code provided'
    };
  }

  try {
    // Get coupon from database
    const coupons = await secureExecuteQuery(
      `SELECT id, code, description, discount_type, discount_value, minimum_amount, 
              maximum_discount, usage_limit, used_count, is_active, valid_from, valid_until 
       FROM coupons 
       WHERE code = ? AND is_active = TRUE`,
      [couponCode]
    );

    if (coupons.length === 0) {
      return {
        valid: false,
        discount: 0,
        message: 'Invalid coupon code'
      };
    }

    const coupon = coupons[0];
    const now = new Date();

    // Check if coupon is within valid date range
    if (coupon.valid_from && new Date(coupon.valid_from) > now) {
      return {
        valid: false,
        discount: 0,
        message: 'Coupon is not yet active'
      };
    }

    if (coupon.valid_until && new Date(coupon.valid_until) < now) {
      return {
        valid: false,
        discount: 0,
        message: 'Coupon has expired'
      };
    }

    // Check usage limit
    if (coupon.usage_limit && coupon.used_count >= coupon.usage_limit) {
      return {
        valid: false,
        discount: 0,
        message: 'Coupon usage limit exceeded'
      };
    }

    // Check minimum amount requirement
    if (coupon.minimum_amount && subtotal < coupon.minimum_amount) {
      return {
        valid: false,
        discount: 0,
        message: `Minimum order amount of ₹${coupon.minimum_amount} required for this coupon`
      };
    }

    // Calculate discount
    let discount = 0;
    if (coupon.discount_type === 'percentage') {
      discount = (subtotal * coupon.discount_value) / 100;
    } else if (coupon.discount_type === 'fixed') {
      discount = coupon.discount_value;
    }

    // Apply maximum discount limit if specified
    if (coupon.maximum_discount && discount > coupon.maximum_discount) {
      discount = coupon.maximum_discount;
    }

    // Ensure discount doesn't exceed subtotal
    if (discount > subtotal) {
      discount = subtotal;
    }

    return {
      valid: true,
      discount: parseFloat(discount.toFixed(2)),
      message: `Coupon applied: ${coupon.description || coupon.code}`,
      couponId: coupon.id,
      couponCode: coupon.code,
      discountType: coupon.discount_type,
      discountValue: parseFloat(coupon.discount_value)
    };

  } catch (error) {
    console.error('Coupon validation error:', error);
    return {
      valid: false,
      discount: 0,
      message: 'Failed to validate coupon'
    };
  }
};

/**
 * Helper function to calculate cart totals with optional coupon
 */
const calculateCartTotals = (cartItems, couponDiscount = 0) => {
  let subtotal = 0;
  let totalItems = 0;

  cartItems.forEach(item => {
    const itemTotal = parseFloat(item.price) * item.quantity;
    subtotal += itemTotal;
    totalItems += item.quantity;
  });

  // Apply coupon discount
  const discountAmount = parseFloat(couponDiscount.toFixed(2));
  const discountedSubtotal = subtotal - discountAmount;

  // Calculate tax on discounted amount
  const tax = discountedSubtotal * 0.18; // 18% GST
  
  // Calculate shipping (free shipping above ₹500 or empty cart)
  const shipping = discountedSubtotal > 500 || discountedSubtotal === 0 ? 0 : 50;
  
  const total = discountedSubtotal + tax + shipping;

  return {
    subtotal: parseFloat(subtotal.toFixed(2)),
    discount: discountAmount,
    discountedSubtotal: parseFloat(discountedSubtotal.toFixed(2)),
    tax: parseFloat(tax.toFixed(2)),
    shipping: parseFloat(shipping.toFixed(2)),
    total: parseFloat(total.toFixed(2)),
    totalItems
  };
};

module.exports = {
  validateAndApplyCoupon,
  calculateCartTotals
};