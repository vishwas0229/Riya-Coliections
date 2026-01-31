-- Migration: Create products table
-- Description: Creates the products table for cosmetic product inventory management
-- Requirements: 10.1, 10.4

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