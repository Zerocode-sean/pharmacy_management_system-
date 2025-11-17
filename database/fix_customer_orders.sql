-- Fix Customer Orders System
-- Add missing columns and create payment_transactions table

USE pharmacy_management;

-- Add order_reference to customer_orders if it doesn't exist
ALTER TABLE customer_orders 
ADD COLUMN IF NOT EXISTS order_reference VARCHAR(50) UNIQUE AFTER id,
ADD INDEX idx_order_reference (order_reference);

-- Add payment_method to customer_orders if it doesn't exist
ALTER TABLE customer_orders 
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'cash' AFTER payment_status;

-- Create payment_transactions table if it doesn't exist
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    order_reference VARCHAR(50),
    phone_number VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(20) DEFAULT 'mpesa',
    checkout_request_id VARCHAR(255),
    merchant_request_id VARCHAR(255),
    mpesa_receipt VARCHAR(100),
    transaction_date VARCHAR(20),
    mpesa_response TEXT,
    callback_response TEXT,
    failure_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_order_reference (order_reference),
    INDEX idx_checkout_request (checkout_request_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Show results
SELECT 'Customer orders table updated successfully' AS message;
SELECT COUNT(*) as total_orders FROM customer_orders;
SELECT COUNT(*) as total_payments FROM payment_transactions;
