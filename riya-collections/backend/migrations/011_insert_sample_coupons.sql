-- Migration: Insert sample coupons for testing
-- Description: Adds sample coupon codes for testing the coupon system
-- Requirements: 3.3

-- Insert sample coupons
INSERT INTO coupons (code, description, discount_type, discount_value, minimum_amount, maximum_discount, usage_limit, is_active, valid_from, valid_until) VALUES
('WELCOME10', '10% off for new customers', 'percentage', 10.00, 100.00, 500.00, 100, TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('SAVE50', 'Flat ₹50 off on orders above ₹300', 'fixed', 50.00, 300.00, NULL, 200, TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY)),
('BEAUTY20', '20% off on all beauty products', 'percentage', 20.00, 200.00, 1000.00, 50, TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 45 DAY)),
('FLAT100', 'Flat ₹100 off on orders above ₹500', 'fixed', 100.00, 500.00, NULL, 150, TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY)),
('MEGA25', '25% off - Limited time offer', 'percentage', 25.00, 400.00, 2000.00, 25, TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY)),
('EXPIRED', 'Expired coupon for testing', 'percentage', 15.00, 100.00, 300.00, 10, TRUE, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('FUTURE', 'Future coupon for testing', 'percentage', 30.00, 200.00, 500.00, 20, TRUE, DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 37 DAY)),
('MAXED', 'Maxed out coupon for testing', 'fixed', 75.00, 150.00, NULL, 1, TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY));

-- Update the maxed out coupon to simulate usage limit reached
UPDATE coupons SET used_count = usage_limit WHERE code = 'MAXED';

-- Verify the insertions
SELECT 
    code, 
    description, 
    discount_type, 
    discount_value, 
    minimum_amount, 
    maximum_discount, 
    usage_limit, 
    used_count, 
    is_active,
    valid_from,
    valid_until
FROM coupons 
ORDER BY created_at DESC;