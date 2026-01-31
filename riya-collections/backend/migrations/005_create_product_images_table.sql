-- Migration: Create product_images table
-- Description: Creates the product_images table for managing multiple product images and galleries
-- Requirements: 10.1, 10.4

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