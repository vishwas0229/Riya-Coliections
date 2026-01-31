-- Migration: Create addresses table
-- Description: Creates the addresses table for customer shipping and billing addresses
-- Requirements: 10.1, 10.4

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