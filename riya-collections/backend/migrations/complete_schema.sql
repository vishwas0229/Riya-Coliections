-- Complete Database Schema for Riya Collections E-commerce Platform
-- Description: Creates all required tables with proper foreign key constraints and indexes
-- Requirements: 10.1, 10.4
-- 
-- This file contains the complete database schema and can be executed as a single script
-- for initial database setup or deployment.

-- Set SQL mode and character set
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Set character set for the session
SET NAMES utf8mb4;

-- ============================================================================
-- 1. USERS TABLE
-- ============================================================================
-- Creates the users table for customer authentication and profile management

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_users_email (email),
    INDEX idx_users_phone (phone),
    INDEX idx_users_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ADMINS TABLE
-- ============================================================================
-- Creates the admins table for administrative user authentication and role management

CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_admins_email (email),
    INDEX idx_admins_role (role),
    INDEX idx_admins_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. CATEGORIES TABLE
-- ============================================================================
-- Creates the categories table for product categorization and organization

CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_categories_name (name),
    INDEX idx_categories_is_active (is_active),
    INDEX idx_categories_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. PRODUCTS TABLE
-- ============================================================================
-- Creates the products table for cosmetic product inventory management

CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    category_id INT,
    brand VARCHAR(100),
    sku VARCHAR(50) UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_products_name (name),
    INDEX idx_products_price (price),
    INDEX idx_products_stock_quantity (stock_quantity),
    INDEX idx_products_category_id (category_id),
    INDEX idx_products_brand (brand),
    INDEX idx_products_sku (sku),
    INDEX idx_products_is_active (is_active),
    INDEX idx_products_created_at (created_at),
    
    -- Composite indexes for common queries
    INDEX idx_products_category_active (category_id, is_active),
    INDEX idx_products_price_active (price, is_active),
    INDEX idx_products_brand_active (brand, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. PRODUCT IMAGES TABLE
-- ============================================================================
-- Creates the product_images table for managing multiple product images and galleries

CREATE TABLE IF NOT EXISTS product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    
    -- Foreign key constraints
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_product_images_product_id (product_id),
    INDEX idx_product_images_is_primary (is_primary),
    INDEX idx_product_images_sort_order (sort_order),
    
    -- Composite indexes for common queries
    INDEX idx_product_images_product_primary (product_id, is_primary),
    INDEX idx_product_images_product_sort (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. ADDRESSES TABLE
-- ============================================================================
-- Creates the addresses table for customer shipping and billing addresses

CREATE TABLE IF NOT EXISTS addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('home', 'work', 'other') DEFAULT 'home',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) DEFAULT 'India',
    phone VARCHAR(20),
    is_default BOOLEAN DEFAULT FALSE,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_addresses_user_id (user_id),
    INDEX idx_addresses_type (type),
    INDEX idx_addresses_is_default (is_default),
    INDEX idx_addresses_city (city),
    INDEX idx_addresses_state (state),
    INDEX idx_addresses_postal_code (postal_code),
    
    -- Composite indexes for common queries
    INDEX idx_addresses_user_default (user_id, is_default),
    INDEX idx_addresses_user_type (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. COUPONS TABLE
-- ============================================================================
-- Creates the coupons table for managing discount coupons and promotional codes

CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) DEFAULT 0,
    maximum_discount DECIMAL(10,2),
    usage_limit INT,
    used_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    valid_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_coupons_code (code),
    INDEX idx_coupons_discount_type (discount_type),
    INDEX idx_coupons_is_active (is_active),
    INDEX idx_coupons_valid_from (valid_from),
    INDEX idx_coupons_valid_until (valid_until),
    INDEX idx_coupons_created_at (created_at),
    
    -- Composite indexes for common queries
    INDEX idx_coupons_active_valid (is_active, valid_from, valid_until),
    INDEX idx_coupons_code_active (code, is_active),
    
    -- Constraints to ensure data integrity
    CONSTRAINT chk_coupons_discount_value CHECK (discount_value >= 0),
    CONSTRAINT chk_coupons_minimum_amount CHECK (minimum_amount >= 0),
    CONSTRAINT chk_coupons_maximum_discount CHECK (maximum_discount IS NULL OR maximum_discount >= 0),
    CONSTRAINT chk_coupons_usage_limit CHECK (usage_limit IS NULL OR usage_limit >= 0),
    CONSTRAINT chk_coupons_used_count CHECK (used_count >= 0),
    CONSTRAINT chk_coupons_percentage_limit CHECK (
        discount_type != 'percentage' OR discount_value <= 100
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. ORDERS TABLE
-- ============================================================================
-- Creates the orders table for managing customer orders and order workflow

CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('placed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'placed',
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    shipping_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    coupon_code VARCHAR(50),
    shipping_address_id INT NOT NULL,
    payment_method ENUM('razorpay', 'cod') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (shipping_address_id) REFERENCES addresses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_order_number (order_number),
    INDEX idx_orders_status (status),
    INDEX idx_orders_payment_method (payment_method),
    INDEX idx_orders_payment_status (payment_status),
    INDEX idx_orders_coupon_code (coupon_code),
    INDEX idx_orders_created_at (created_at),
    INDEX idx_orders_updated_at (updated_at),
    
    -- Composite indexes for common queries
    INDEX idx_orders_user_status (user_id, status),
    INDEX idx_orders_user_created (user_id, created_at),
    INDEX idx_orders_status_created (status, created_at),
    INDEX idx_orders_payment_status_method (payment_status, payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. ORDER ITEMS TABLE
-- ============================================================================
-- Creates the order_items table for managing individual products within orders

CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    
    -- Foreign key constraints
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_product_id (product_id),
    INDEX idx_order_items_quantity (quantity),
    INDEX idx_order_items_unit_price (unit_price),
    INDEX idx_order_items_total_price (total_price),
    
    -- Composite indexes for common queries
    INDEX idx_order_items_order_product (order_id, product_id),
    
    -- Constraints to ensure data integrity
    CONSTRAINT chk_order_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_order_items_unit_price CHECK (unit_price >= 0),
    CONSTRAINT chk_order_items_total_price CHECK (total_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. PAYMENTS TABLE
-- ============================================================================
-- Creates the payments table for managing payment transactions and Razorpay integration

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    payment_method ENUM('razorpay', 'cod') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    razorpay_payment_id VARCHAR(255),
    razorpay_order_id VARCHAR(255),
    razorpay_signature VARCHAR(255),
    transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_payments_order_id (order_id),
    INDEX idx_payments_payment_method (payment_method),
    INDEX idx_payments_payment_status (payment_status),
    INDEX idx_payments_razorpay_payment_id (razorpay_payment_id),
    INDEX idx_payments_razorpay_order_id (razorpay_order_id),
    INDEX idx_payments_transaction_id (transaction_id),
    INDEX idx_payments_created_at (created_at),
    INDEX idx_payments_updated_at (updated_at),
    
    -- Composite indexes for common queries
    INDEX idx_payments_method_status (payment_method, payment_status),
    INDEX idx_payments_status_created (payment_status, created_at),
    
    -- Constraints to ensure data integrity
    CONSTRAINT chk_payments_amount CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COMMIT TRANSACTION
-- ============================================================================

COMMIT;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- These queries can be used to verify the schema was created correctly

-- Show all tables
-- SHOW TABLES;

-- Show table structures
-- DESCRIBE users;
-- DESCRIBE admins;
-- DESCRIBE categories;
-- DESCRIBE products;
-- DESCRIBE product_images;
-- DESCRIBE addresses;
-- DESCRIBE orders;
-- DESCRIBE order_items;
-- DESCRIBE payments;
-- DESCRIBE coupons;

-- Show foreign key relationships
-- SELECT 
--     TABLE_NAME,
--     COLUMN_NAME,
--     CONSTRAINT_NAME,
--     REFERENCED_TABLE_NAME,
--     REFERENCED_COLUMN_NAME
-- FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
-- WHERE REFERENCED_TABLE_SCHEMA = 'riya_collections'
-- ORDER BY TABLE_NAME, COLUMN_NAME;

-- Show indexes
-- SELECT 
--     TABLE_NAME,
--     INDEX_NAME,
--     COLUMN_NAME,
--     SEQ_IN_INDEX
-- FROM INFORMATION_SCHEMA.STATISTICS
-- WHERE TABLE_SCHEMA = 'riya_collections'
-- ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;