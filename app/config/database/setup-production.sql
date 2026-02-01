-- Riya Collections - Production Database Setup
-- This script sets up the production database with optimized settings
-- IMPORTANT: Review and customize before running in production

-- Create production database
CREATE DATABASE IF NOT EXISTS `riya_collections_prod` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the production database
USE `riya_collections_prod`;

-- Create production user with limited privileges
-- IMPORTANT: Change the password before running in production
CREATE USER IF NOT EXISTS 'riya_prod'@'localhost' IDENTIFIED BY 'CHANGE_ME_SECURE_PRODUCTION_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON `riya_collections_prod`.* TO 'riya_prod'@'localhost';
FLUSH PRIVILEGES;

-- Users table with production optimizations
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
    UNIQUE KEY `idx_email` (`email`),
    UNIQUE KEY `idx_username` (`username`),
    KEY `idx_role_active` (`role`, `is_active`),
    KEY `idx_email_verified` (`email_verified`),
    KEY `idx_last_login` (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table with production optimizations
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
    KEY `idx_parent_active` (`parent_id`, `is_active`),
    KEY `idx_sort_active` (`sort_order`, `is_active`),
    CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table with production optimizations
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
    KEY `idx_category_active_visible` (`category_id`, `is_active`, `visibility`),
    KEY `idx_featured_active` (`is_featured`, `is_active`),
    KEY `idx_stock_status_active` (`stock_status`, `is_active`),
    KEY `idx_price_active` (`price`, `is_active`),
    KEY `idx_brand_active` (`brand`, `is_active`),
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
    KEY `idx_product_primary` (`product_id`, `is_primary`),
    KEY `idx_product_sort` (`product_id`, `sort_order`),
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
    KEY `idx_user_type` (`user_id`, `type`),
    KEY `idx_user_default` (`user_id`, `is_default`),
    CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table with production optimizations
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
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_created_status` (`created_at`, `status`),
    KEY `idx_payment_id` (`payment_id`),
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
    KEY `idx_order_product` (`order_id`, `product_id`),
    KEY `idx_product_id` (`product_id`),
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
    KEY `idx_order_status` (`order_id`, `status`),
    KEY `idx_gateway_status` (`gateway`, `status`),
    KEY `idx_created_status` (`created_at`, `status`),
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
    KEY `idx_status_priority` (`status`, `priority`),
    KEY `idx_scheduled_status` (`scheduled_at`, `status`),
    KEY `idx_attempts_status` (`attempts`, `status`)
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
    KEY `idx_role_active` (`role`, `is_active`),
    KEY `idx_last_login` (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Production audit log table
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `table_name` varchar(64) NOT NULL,
    `record_id` int(11) NOT NULL,
    `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
    `old_values` json DEFAULT NULL,
    `new_values` json DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `user_type` enum('user','admin') DEFAULT 'user',
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_table_record` (`table_name`, `record_id`),
    KEY `idx_action_created` (`action`, `created_at`),
    KEY `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Production session table
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` varchar(128) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text,
    `payload` longtext NOT NULL,
    `last_activity` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Production performance optimization indexes
CREATE INDEX idx_products_search ON products(name, description(100), tags(100));
CREATE INDEX idx_orders_reporting ON orders(created_at, status, total_amount);
CREATE INDEX idx_users_activity ON users(last_login, is_active);

-- Production database settings
SET GLOBAL sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';
SET GLOBAL innodb_buffer_pool_size = 512M;
SET GLOBAL innodb_log_file_size = 256M;
SET GLOBAL innodb_flush_log_at_trx_commit = 1;
SET GLOBAL sync_binlog = 1;
SET GLOBAL max_connections = 100;
SET GLOBAL query_cache_size = 64M;
SET GLOBAL query_cache_type = 1;

-- Create initial admin user (CHANGE PASSWORD BEFORE PRODUCTION!)
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `is_active`) VALUES
('admin', 'admin@riyacollections.com', '$2y$12$CHANGE_ME_GENERATE_SECURE_HASH_FOR_PRODUCTION', 'Admin', 'User', 'super_admin', 1);

-- Production security settings
-- Enable binary logging for replication and point-in-time recovery
SET GLOBAL log_bin = ON;
SET GLOBAL binlog_format = 'ROW';
SET GLOBAL expire_logs_days = 7;

-- Enable slow query log
SET GLOBAL slow_query_log = ON;
SET GLOBAL long_query_time = 2;
SET GLOBAL log_queries_not_using_indexes = ON;

-- Show completion message
SELECT 'Production database setup completed successfully! REMEMBER TO:' as message;
SELECT '1. Change all default passwords' as reminder_1;
SELECT '2. Review and adjust database settings for your server' as reminder_2;
SELECT '3. Set up regular backups' as reminder_3;
SELECT '4. Configure SSL/TLS for database connections' as reminder_4;