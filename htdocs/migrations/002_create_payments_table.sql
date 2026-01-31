-- Migration: Create payments table
-- Description: Creates the payments table for handling Razorpay and COD transactions
-- Requirements: 7.1, 7.2

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('razorpay', 'cod') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    
    -- Razorpay specific fields
    razorpay_order_id VARCHAR(255) NULL,
    razorpay_payment_id VARCHAR(255) NULL,
    razorpay_signature VARCHAR(255) NULL,
    razorpay_order_data TEXT NULL,
    razorpay_payment_data TEXT NULL,
    
    -- COD specific fields
    cod_charges DECIMAL(10, 2) NULL,
    
    -- Common fields
    failure_reason TEXT NULL,
    notes TEXT NULL,
    
    -- Timestamp fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    confirmed_at TIMESTAMP NULL,
    captured_at TIMESTAMP NULL,
    authorized_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    
    -- Additional data field for webhook confirmations
    confirmation_data TEXT NULL,
    
    -- Indexes
    INDEX idx_order_id (order_id),
    INDEX idx_payment_method (payment_method),
    INDEX idx_status (status),
    INDEX idx_razorpay_order_id (razorpay_order_id),
    INDEX idx_razorpay_payment_id (razorpay_payment_id),
    INDEX idx_created_at (created_at),
    INDEX idx_amount (amount),
    
    -- Composite indexes for common queries
    INDEX idx_order_status (order_id, status),
    INDEX idx_method_status (payment_method, status),
    INDEX idx_status_created (status, created_at),
    
    -- Foreign key constraint (assuming orders table exists)
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_logs table for audit trail
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_payment_id (payment_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial data or configuration if needed
-- This can be used for setting up default payment configurations

-- Create a view for payment summaries
CREATE OR REPLACE VIEW payment_summary AS
SELECT 
    p.id,
    p.order_id,
    p.payment_method,
    p.amount,
    p.currency,
    p.status,
    p.created_at,
    o.order_number,
    o.user_id,
    u.email as customer_email,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name
FROM payments p
LEFT JOIN orders o ON p.order_id = o.id
LEFT JOIN users u ON o.user_id = u.id;

-- Create indexes on the orders table if they don't exist (for foreign key performance)
-- Note: These might already exist, so we use IF NOT EXISTS equivalent
CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number);