-- Migration: Create payments table
-- Description: Creates the payments table for managing payment transactions and Razorpay integration
-- Requirements: 10.1, 10.4

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