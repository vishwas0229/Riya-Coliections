-- Admin System Tables Migration
-- Creates tables for admin authentication, sessions, and access control
-- Requirements: 11.1, 11.4, 14.2

-- Create admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    allowed_ips TEXT NULL COMMENT 'Comma-separated list of allowed IP addresses',
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    password_changed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_last_login (last_login_at)
);

-- Create admin sessions table
CREATE TABLE IF NOT EXISTS admin_sessions (
    id VARCHAR(64) PRIMARY KEY,
    admin_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_token_hash (token_hash)
);

-- Create admin login attempts table for security tracking
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    email VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    success BOOLEAN DEFAULT FALSE,
    failure_reason VARCHAR(255) NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_success (success)
);

-- Create admin activity log table
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL,
    resource_id VARCHAR(50) NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
);

-- Create admin permissions table (for future role-based permissions)
CREATE TABLE IF NOT EXISTS admin_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_category (category)
);

-- Create admin role permissions junction table
CREATE TABLE IF NOT EXISTS admin_role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('super_admin', 'admin', 'moderator') NOT NULL,
    permission_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role, permission_id),
    INDEX idx_role (role)
);

-- Insert default admin permissions
INSERT INTO admin_permissions (name, description, category) VALUES
('user_management', 'Manage user accounts and profiles', 'users'),
('order_management', 'View and manage customer orders', 'orders'),
('product_management', 'Manage products and inventory', 'products'),
('analytics_view', 'View analytics and reports', 'analytics'),
('system_settings', 'Manage system configuration', 'system'),
('security_logs', 'View security logs and events', 'security'),
('admin_management', 'Manage admin users and roles', 'admin'),
('email_management', 'Manage email templates and campaigns', 'communication'),
('payment_management', 'View and manage payments', 'payments'),
('content_management', 'Manage website content', 'content');

-- Assign permissions to roles
INSERT INTO admin_role_permissions (role, permission_id) 
SELECT 'super_admin', id FROM admin_permissions;

INSERT INTO admin_role_permissions (role, permission_id) 
SELECT 'admin', id FROM admin_permissions 
WHERE name IN ('user_management', 'order_management', 'product_management', 'analytics_view', 'email_management', 'payment_management');

INSERT INTO admin_role_permissions (role, permission_id) 
SELECT 'moderator', id FROM admin_permissions 
WHERE name IN ('order_management', 'analytics_view');

-- Create default super admin user (password: admin123!)
-- Note: In production, this should be changed immediately
INSERT INTO admin_users (email, password_hash, first_name, last_name, role, status) VALUES
('admin@riyacollections.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj6hsxq9S7jG', 'Super', 'Admin', 'super_admin', 'active');

-- Create indexes for better performance
CREATE INDEX idx_admin_sessions_admin_expires ON admin_sessions(admin_id, expires_at);
CREATE INDEX idx_admin_login_attempts_ip_time ON admin_login_attempts(ip_address, attempted_at);
CREATE INDEX idx_admin_activity_admin_time ON admin_activity_log(admin_id, created_at);

-- Create cleanup events for old records
DELIMITER //

CREATE EVENT IF NOT EXISTS cleanup_expired_admin_sessions
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM admin_sessions WHERE expires_at < NOW();
END //

CREATE EVENT IF NOT EXISTS cleanup_old_login_attempts
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //

CREATE EVENT IF NOT EXISTS cleanup_old_activity_logs
ON SCHEDULE EVERY 1 WEEK
DO
BEGIN
    DELETE FROM admin_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END //

DELIMITER ;