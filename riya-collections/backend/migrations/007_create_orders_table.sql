-- Migration: Create orders table
-- Description: Creates the orders table for managing customer orders and order workflow
-- Requirements: 10.1, 10.4

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