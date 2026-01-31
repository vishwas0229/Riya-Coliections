-- Migration: Performance Optimization Indexes
-- Description: Add comprehensive indexes for query performance optimization
-- Requirements: 10.3, 10.4

-- Additional indexes for products table (beyond existing ones)
ALTER TABLE products 
ADD INDEX IF NOT EXISTS idx_products_price_stock_active (price, stock_quantity, is_active),
ADD INDEX IF NOT EXISTS idx_products_category_price_active (category_id, price, is_active),
ADD INDEX IF NOT EXISTS idx_products_name_fulltext (name),
ADD INDEX IF NOT EXISTS idx_products_description_fulltext (description),
ADD INDEX IF NOT EXISTS idx_products_brand_category (brand, category_id);

-- Additional indexes for orders table (beyond existing ones)
ALTER TABLE orders
ADD INDEX IF NOT EXISTS idx_orders_status_payment_method (status, payment_method),
ADD INDEX IF NOT EXISTS idx_orders_user_payment_status (user_id, payment_status),
ADD INDEX IF NOT EXISTS idx_orders_created_status (created_at, status),
ADD INDEX IF NOT EXISTS idx_orders_total_amount_status (total_amount, status),
ADD INDEX IF NOT EXISTS idx_orders_coupon_status (coupon_code, status);

-- Additional indexes for order_items table (check if exists first)
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'order_items' AND index_name = 'idx_order_items_product_quantity');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists''', 'ALTER TABLE order_items ADD INDEX idx_order_items_product_quantity (product_id, quantity)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Additional indexes for users table
ALTER TABLE users
ADD INDEX IF NOT EXISTS idx_users_email_active (email),
ADD INDEX IF NOT EXISTS idx_users_created_at (created_at),
ADD INDEX IF NOT EXISTS idx_users_name_search (first_name, last_name);

-- Additional indexes for addresses table
ALTER TABLE addresses
ADD INDEX IF NOT EXISTS idx_addresses_user_default (user_id, is_default),
ADD INDEX IF NOT EXISTS idx_addresses_city_state (city, state),
ADD INDEX IF NOT EXISTS idx_addresses_postal_code (postal_code);

-- Additional indexes for product_images table
ALTER TABLE product_images
ADD INDEX IF NOT EXISTS idx_product_images_product_primary (product_id, is_primary),
ADD INDEX IF NOT EXISTS idx_product_images_sort_order (sort_order);

-- Additional indexes for payments table
ALTER TABLE payments
ADD INDEX IF NOT EXISTS idx_payments_order_status (order_id, payment_status),
ADD INDEX IF NOT EXISTS idx_payments_method_status (payment_method, payment_status),
ADD INDEX IF NOT EXISTS idx_payments_created_at (created_at),
ADD INDEX IF NOT EXISTS idx_payments_razorpay_id (razorpay_payment_id);

-- Additional indexes for categories table
ALTER TABLE categories
ADD INDEX IF NOT EXISTS idx_categories_name_active (name, is_active);

-- Additional indexes for coupons table
ALTER TABLE coupons
ADD INDEX IF NOT EXISTS idx_coupons_code_active (code, is_active),
ADD INDEX IF NOT EXISTS idx_coupons_valid_dates (valid_from, valid_until),
ADD INDEX IF NOT EXISTS idx_coupons_usage (usage_limit, used_count);

-- Create composite indexes for common query patterns
ALTER TABLE products
ADD INDEX IF NOT EXISTS idx_products_search_composite (name, brand, category_id, is_active, stock_quantity);

ALTER TABLE orders
ADD INDEX IF NOT EXISTS idx_orders_admin_search (user_id, status, payment_status, created_at);

-- Full-text search indexes for better search performance (MySQL 5.6+)
SET @exist_ft := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'products' AND index_name = 'ft_products_search');
SET @sqlstmt_ft := IF(@exist_ft > 0, 'SELECT ''Full-text index already exists''', 'ALTER TABLE products ADD FULLTEXT INDEX ft_products_search (name, description, brand)');
PREPARE stmt_ft FROM @sqlstmt_ft;
EXECUTE stmt_ft;
DEALLOCATE PREPARE stmt_ft;

-- Create covering indexes for frequently accessed columns
ALTER TABLE products
ADD INDEX IF NOT EXISTS idx_products_listing_cover (id, name, price, stock_quantity, category_id, is_active, created_at);

ALTER TABLE orders
ADD INDEX IF NOT EXISTS idx_orders_summary_cover (id, order_number, status, total_amount, payment_status, created_at, user_id);