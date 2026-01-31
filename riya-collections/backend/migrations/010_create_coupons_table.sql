-- Migration: Create coupons table
-- Description: Creates the coupons table for managing discount coupons and promotional codes
-- Requirements: 10.1, 10.4

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