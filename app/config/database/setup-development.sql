-- Riya Collections - Development Database Setup
-- This script sets up the development database with all necessary tables and sample data

-- Create development database
CREATE DATABASE IF NOT EXISTS `riya_collections_dev` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the development database
USE `riya_collections_dev`;

-- Create development user (if not exists)
CREATE USER IF NOT EXISTS 'riya_dev'@'localhost' IDENTIFIED BY 'dev_password_123';
GRANT ALL PRIVILEGES ON `riya_collections_dev`.* TO 'riya_dev'@'localhost';
FLUSH PRIVILEGES;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL UNIQUE,
    `email` varchar(100) NOT NULL UNIQUE,
    `password_hash` varchar(255) NOT NULL,
    `first_name` varchar(50) DEFAULT NULL,
    `last_name` varchar(50) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `role` enum('customer','admin','manager') DEFAULT 'customer',
    `is_active` tinyint(1) DEFAULT 1,
    `email_verified` tinyint(1) DEFAULT 0,
    `email_verification_token` varchar(255) DEFAULT NULL,
    `password_reset_token` varchar(255) DEFAULT NULL,
    `password_reset_expires` datetime DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `login_attempts` int(11) DEFAULT 0,
    `locked_until` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_username` (`username`),
    KEY `idx_role` (`role`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `slug` varchar(100) NOT NULL UNIQUE,
    `description` text,
    `parent_id` int(11) DEFAULT NULL,
    `image_url` varchar(255) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `sort_order` int(11) DEFAULT 0,
    `meta_title` varchar(255) DEFAULT NULL,
    `meta_description` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `parent_id` (`parent_id`),
    KEY `idx_active` (`is_active`),
    KEY `idx_sort` (`sort_order`),
    CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE IF NOT EXISTS `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `slug` varchar(255) NOT NULL UNIQUE,
    `description` text,
    `short_description` varchar(500) DEFAULT NULL,
    `sku` varchar(100) DEFAULT NULL UNIQUE,
    `price` decimal(10,2) NOT NULL,
    `sale_price` decimal(10,2) DEFAULT NULL,
    `cost_price` decimal(10,2) DEFAULT NULL,
    `stock_quantity` int(11) DEFAULT 0,
    `manage_stock` tinyint(1) DEFAULT 1,
    `stock_status` enum('in_stock','out_of_stock','on_backorder') DEFAULT 'in_stock',
    `weight` decimal(8,2) DEFAULT NULL,
    `dimensions` varchar(100) DEFAULT NULL,
    `category_id` int(11) DEFAULT NULL,
    `brand` varchar(100) DEFAULT NULL,
    `tags` text,
    `is_active` tinyint(1) DEFAULT 1,
    `is_featured` tinyint(1) DEFAULT 0,
    `visibility` enum('visible','hidden','catalog','search') DEFAULT 'visible',
    `meta_title` varchar(255) DEFAULT NULL,
    `meta_description` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    UNIQUE KEY `sku` (`sku`),
    KEY `category_id` (`category_id`),
    KEY `idx_active` (`is_active`),
    KEY `idx_featured` (`is_featured`),
    KEY `idx_stock_status` (`stock_status`),
    KEY `idx_price` (`price`),
    CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images table
CREATE TABLE IF NOT EXISTS `product_images` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `image_url` varchar(255) NOT NULL,
    `alt_text` varchar(255) DEFAULT NULL,
    `is_primary` tinyint(1) DEFAULT 0,
    `sort_order` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    KEY `idx_primary` (`is_primary`),
    KEY `idx_sort` (`sort_order`),
    CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Addresses table
CREATE TABLE IF NOT EXISTS `addresses` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `type` enum('billing','shipping','both') DEFAULT 'both',
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `company` varchar(100) DEFAULT NULL,
    `address_line_1` varchar(255) NOT NULL,
    `address_line_2` varchar(255) DEFAULT NULL,
    `city` varchar(100) NOT NULL,
    `state` varchar(100) NOT NULL,
    `postal_code` varchar(20) NOT NULL,
    `country` varchar(100) NOT NULL DEFAULT 'India',
    `phone` varchar(20) DEFAULT NULL,
    `is_default` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `idx_type` (`type`),
    KEY `idx_default` (`is_default`),
    CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_number` varchar(50) NOT NULL UNIQUE,
    `user_id` int(11) DEFAULT NULL,
    `status` enum('pending','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
    `payment_status` enum('pending','paid','failed','refunded','partially_refunded') DEFAULT 'pending',
    `payment_method` varchar(50) DEFAULT NULL,
    `payment_id` varchar(255) DEFAULT NULL,
    `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` decimal(10,2) DEFAULT 0.00,
    `shipping_amount` decimal(10,2) DEFAULT 0.00,
    `discount_amount` decimal(10,2) DEFAULT 0.00,
    `total_amount` decimal(10,2) NOT NULL,
    `currency` varchar(3) DEFAULT 'INR',
    `billing_address` json DEFAULT NULL,
    `shipping_address` json DEFAULT NULL,
    `notes` text,
    `shipped_at` datetime DEFAULT NULL,
    `delivered_at` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `order_number` (`order_number`),
    KEY `user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `product_name` varchar(255) NOT NULL,
    `product_sku` varchar(100) DEFAULT NULL,
    `quantity` int(11) NOT NULL,
    `unit_price` decimal(10,2) NOT NULL,
    `total_price` decimal(10,2) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`),
    KEY `product_id` (`product_id`),
    CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `payment_id` varchar(255) NOT NULL UNIQUE,
    `gateway` varchar(50) NOT NULL DEFAULT 'razorpay',
    `method` varchar(50) DEFAULT NULL,
    `status` enum('created','authorized','captured','failed','cancelled','refunded') DEFAULT 'created',
    `amount` decimal(10,2) NOT NULL,
    `currency` varchar(3) DEFAULT 'INR',
    `gateway_response` json DEFAULT NULL,
    `failure_reason` varchar(255) DEFAULT NULL,
    `captured_at` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payment_id` (`payment_id`),
    KEY `order_id` (`order_id`),
    KEY `idx_status` (`status`),
    KEY `idx_gateway` (`gateway`),
    CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue table
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `to_email` varchar(255) NOT NULL,
    `to_name` varchar(255) DEFAULT NULL,
    `subject` varchar(255) NOT NULL,
    `body` text NOT NULL,
    `template` varchar(100) DEFAULT NULL,
    `template_data` json DEFAULT NULL,
    `priority` tinyint(4) DEFAULT 5,
    `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
    `attempts` int(11) DEFAULT 0,
    `max_attempts` int(11) DEFAULT 3,
    `error_message` text,
    `scheduled_at` datetime DEFAULT NULL,
    `sent_at` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_scheduled` (`scheduled_at`),
    KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL UNIQUE,
    `email` varchar(100) NOT NULL UNIQUE,
    `password_hash` varchar(255) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `role` enum('super_admin','admin','manager','editor') DEFAULT 'admin',
    `permissions` json DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `last_login` datetime DEFAULT NULL,
    `login_attempts` int(11) DEFAULT 0,
    `locked_until` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert development sample data
INSERT INTO `categories` (`name`, `slug`, `description`, `is_active`, `sort_order`) VALUES
('Clothing', 'clothing', 'Fashion and apparel items', 1, 1),
('Electronics', 'electronics', 'Electronic devices and gadgets', 1, 2),
('Home & Garden', 'home-garden', 'Home improvement and garden items', 1, 3),
('Books', 'books', 'Books and educational materials', 1, 4),
('Sports', 'sports', 'Sports and fitness equipment', 1, 5);

INSERT INTO `products` (`name`, `slug`, `description`, `price`, `stock_quantity`, `category_id`, `is_active`, `is_featured`) VALUES
('Cotton T-Shirt', 'cotton-t-shirt', 'Comfortable cotton t-shirt for everyday wear', 599.00, 100, 1, 1, 1),
('Wireless Headphones', 'wireless-headphones', 'High-quality wireless headphones with noise cancellation', 2999.00, 50, 2, 1, 1),
('Garden Tools Set', 'garden-tools-set', 'Complete set of essential garden tools', 1299.00, 25, 3, 1, 0),
('Programming Book', 'programming-book', 'Learn programming with this comprehensive guide', 899.00, 75, 4, 1, 0),
('Yoga Mat', 'yoga-mat', 'Non-slip yoga mat for exercise and meditation', 799.00, 60, 5, 1, 1);

INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `is_active`, `email_verified`) VALUES
('admin', 'admin@riyacollections.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBdXig/pjche4G', 'Admin', 'User', 'admin', 1, 1),
('testuser', 'test@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBdXig/pjche4G', 'Test', 'User', 'customer', 1, 1),
('manager', 'manager@riyacollections.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBdXig/pjche4G', 'Manager', 'User', 'manager', 1, 1);

INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `is_active`) VALUES
('superadmin', 'superadmin@riyacollections.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBdXig/pjche4G', 'Super', 'Admin', 'super_admin', 1),
('admin', 'admin@riyacollections.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBdXig/pjche4G', 'Admin', 'User', 'admin', 1);

-- Create indexes for better performance
CREATE INDEX idx_products_category_active ON products(category_id, is_active);
CREATE INDEX idx_products_featured_active ON products(is_featured, is_active);
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_order_items_order_product ON order_items(order_id, product_id);

-- Development-specific settings
SET GLOBAL sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';
SET GLOBAL innodb_buffer_pool_size = 128M;

-- Show completion message
SELECT 'Development database setup completed successfully!' as message;