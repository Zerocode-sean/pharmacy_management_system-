-- Customer Authentication Schema Update
-- Adds authentication and verification fields to existing customers table
-- Run this script to enable customer registration and login functionality

USE pharmacy_management;

-- Add authentication columns to customers table if they don't exist
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS password VARCHAR(255) AFTER email,
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE AFTER password,
ADD COLUMN IF NOT EXISTS verification_code VARCHAR(100) NULL AFTER email_verified,
ADD COLUMN IF NOT EXISTS reset_token VARCHAR(100) NULL AFTER verification_code,
ADD COLUMN IF NOT EXISTS reset_token_expires TIMESTAMP NULL AFTER reset_token,
ADD COLUMN IF NOT EXISTS remember_token VARCHAR(100) NULL AFTER reset_token_expires,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER remember_token,
ADD COLUMN IF NOT EXISTS login_attempts INT DEFAULT 0 AFTER last_login,
ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL AFTER login_attempts,
ADD COLUMN IF NOT EXISTS preferences JSON NULL AFTER locked_until;

-- Update existing customers table structure for better authentication
ALTER TABLE customers 
MODIFY COLUMN email VARCHAR(100) UNIQUE NOT NULL AFTER phone,
MODIFY COLUMN name VARCHAR(100) NOT NULL,
ADD INDEX IF NOT EXISTS idx_customers_email (email),
ADD INDEX IF NOT EXISTS idx_customers_verification (verification_code),
ADD INDEX IF NOT EXISTS idx_customers_reset_token (reset_token),
ADD INDEX IF NOT EXISTS idx_customers_remember_token (remember_token);

-- Create customer sessions table for better session management
CREATE TABLE IF NOT EXISTS customer_sessions (
    id VARCHAR(128) PRIMARY KEY,
    customer_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT NOT NULL,
    last_activity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_sessions_customer (customer_id),
    INDEX idx_customer_sessions_last_activity (last_activity),
    INDEX idx_customer_sessions_expires (expires_at)
);

-- Create customer login logs table for security tracking
CREATE TABLE IF NOT EXISTS customer_login_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_successful BOOLEAN NOT NULL,
    failure_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_customer_login_logs_customer (customer_id),
    INDEX idx_customer_login_logs_email (email),
    INDEX idx_customer_login_logs_ip (ip_address),
    INDEX idx_customer_login_logs_created (created_at)
);

-- Create customer password resets table
CREATE TABLE IF NOT EXISTS customer_password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    INDEX idx_password_resets_email (email),
    INDEX idx_password_resets_token (token),
    INDEX idx_password_resets_expires (expires_at)
);

-- Update customer_orders table to ensure proper foreign key relationships
ALTER TABLE customer_orders 
ADD COLUMN IF NOT EXISTS customer_email VARCHAR(100) AFTER customer_id,
ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(20) AFTER customer_email;

-- Insert some sample customers for testing (with hashed passwords)
INSERT IGNORE INTO customers (
    name, 
    phone, 
    email, 
    password, 
    email_verified, 
    address, 
    date_of_birth
) VALUES 
(
    'John Doe', 
    '+254700123456', 
    'john.doe@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
    TRUE, 
    '123 Main Street, Nairobi', 
    '1990-05-15'
),
(
    'Jane Smith', 
    '+254700654321', 
    'jane.smith@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
    TRUE, 
    '456 Oak Avenue, Mombasa', 
    '1985-08-22'
),
(
    'Michael Johnson', 
    '+254700987654', 
    'michael.johnson@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
    FALSE, 
    '789 Pine Road, Kisumu', 
    '1992-12-08'
);

-- Show updated table structure
SELECT 'Updated customers table structure:' as info;
DESCRIBE customers;

SELECT 'Customer authentication tables created:' as info;
SHOW TABLES LIKE 'customer_%';

-- Show sample data
SELECT 'Sample customer data:' as info;
SELECT id, name, email, phone, email_verified, created_at FROM customers LIMIT 5;
