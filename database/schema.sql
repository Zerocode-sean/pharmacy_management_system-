-- Pharmacy Management System Database Schema
-- Created: November 1, 2025
-- Last Updated: November 3, 2025
-- 
-- This comprehensive schema includes:
-- - Core pharmacy management (users, medicines, sales, inventory)
-- - Customer portal and e-commerce functionality
-- - M-Pesa payment integration
-- - Order management and tracking
-- - Advanced analytics and reporting
-- - Stock management and alerts
-- - User performance tracking
-- - System configuration management

-- Create database
CREATE DATABASE IF NOT EXISTS pharmacy_management;
USE pharmacy_management;

-- Users table (for authentication)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pharmacist', 'cashier') NOT NULL DEFAULT 'cashier',
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    hire_date DATE NULL,
    birth_date DATE NULL,
    department VARCHAR(100) NULL,
    employee_id VARCHAR(50) NULL,
    salary DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- User yearly performance tracking
CREATE TABLE user_yearly_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    year INT NOT NULL,
    sales_total DECIMAL(12,2) DEFAULT 0,
    transactions_count INT DEFAULT 0,
    customers_served INT DEFAULT 0,
    work_hours DECIMAL(8,2) DEFAULT 0,
    performance_score DECIMAL(5,2) DEFAULT 0,
    attendance_rate DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_year (user_id, year)
);

-- User yearly goals and targets
CREATE TABLE user_yearly_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    year INT NOT NULL,
    sales_target DECIMAL(12,2) DEFAULT 0,
    transactions_target INT DEFAULT 0,
    customers_target INT DEFAULT 0,
    performance_target DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_goal_year (user_id, year)
);

-- User attendance tracking
CREATE TABLE user_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    hours_worked DECIMAL(4,2) DEFAULT 0,
    status ENUM('present', 'absent', 'late', 'half_day', 'holiday', 'sick_leave') DEFAULT 'present',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date)
);

-- User evaluations and reviews
CREATE TABLE user_evaluations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    evaluation_date DATE NOT NULL,
    year INT NOT NULL,
    quarter INT NULL,
    overall_score DECIMAL(5,2) NOT NULL,
    technical_skills DECIMAL(5,2) DEFAULT 0,
    communication_skills DECIMAL(5,2) DEFAULT 0,
    teamwork DECIMAL(5,2) DEFAULT 0,
    punctuality DECIMAL(5,2) DEFAULT 0,
    customer_service DECIMAL(5,2) DEFAULT 0,
    comments TEXT NULL,
    recommendations TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User training and certifications
CREATE TABLE user_training (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    training_name VARCHAR(200) NOT NULL,
    training_type ENUM('certification', 'workshop', 'seminar', 'online_course', 'internal_training') NOT NULL,
    provider VARCHAR(200) NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    completion_date DATE NULL,
    status ENUM('enrolled', 'in_progress', 'completed', 'cancelled') DEFAULT 'enrolled',
    certificate_number VARCHAR(100) NULL,
    expiry_date DATE NULL,
    cost DECIMAL(10,2) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Suppliers table (enhanced)
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    address TEXT,
    category ENUM('medicines', 'equipment', 'supplies', 'other') NOT NULL DEFAULT 'medicines',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    credit_terms INT DEFAULT 30, -- Days
    tax_id VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Medicines table
CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    brand VARCHAR(100),
    category_id INT,
    supplier_id INT,
    barcode VARCHAR(50) UNIQUE,
    unit_price DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 10,
    expiry_date DATE,
    manufacture_date DATE,
    batch_number VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    cost_price DECIMAL(10,2) DEFAULT 0.00,
    minimum_stock INT DEFAULT 10,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

-- Customers table
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    date_of_birth DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sales table
CREATE TABLE sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'digital') DEFAULT 'cash',
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Sale items table
CREATE TABLE sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- Purchase orders table
CREATE TABLE purchase_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE NOT NULL,
    received_date DATE NULL,
    status ENUM('pending', 'partial', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Purchase Order Items table
CREATE TABLE purchase_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purchase_order_id INT NOT NULL,
    description VARCHAR(300) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    received_quantity INT DEFAULT 0,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
);

-- Stock movements table (for inventory tracking)
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type ENUM('sale', 'purchase', 'adjustment', 'expiry') NOT NULL,
    reference_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT NOT NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- User Activity Log table
CREATE TABLE user_activity (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'logout', 'sales', 'inventory', 'user_management', 'system') NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_activity_user (user_id),
    INDEX idx_user_activity_type (activity_type),
    INDEX idx_user_activity_date (created_at)
);

-- User Login Log table
CREATE TABLE user_login_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(128),
    is_successful BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_login_log_user (user_id),
    INDEX idx_login_log_session (session_id),
    INDEX idx_login_log_time (login_time)
);

-- User Sessions table
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(128) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    location VARCHAR(100),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_sessions_user (user_id),
    INDEX idx_user_sessions_activity (last_activity)
);

-- Role Permissions table
CREATE TABLE role_permissions (
    role VARCHAR(50) PRIMARY KEY,
    permissions JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, email, password, role, full_name, phone, hire_date, birth_date, department, employee_id) VALUES
('admin', 'admin@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', '1234567890', '2020-01-01', '1985-06-15', 'Administration', 'EMP001');

-- Insert sample users with year-based data
INSERT INTO users (username, email, password, role, full_name, phone, hire_date, birth_date, department, employee_id, salary) VALUES
('pharmacist1', 'pharmacist1@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist', 'Dr. Sarah Johnson', '555-0101', '2021-03-15', '1988-09-22', 'Pharmacy', 'EMP002', 65000.00),
('pharmacist2', 'pharmacist2@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist', 'Dr. Michael Chen', '555-0102', '2020-08-01', '1985-12-10', 'Pharmacy', 'EMP003', 68000.00),
('cashier1', 'cashier1@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Emma Williams', '555-0103', '2022-01-10', '1995-04-18', 'Sales', 'EMP004', 35000.00),
('cashier2', 'cashier2@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'David Rodriguez', '555-0104', '2021-11-20', '1992-07-08', 'Sales', 'EMP005', 36000.00);

-- Insert sample yearly performance data (2023-2024)
INSERT INTO user_yearly_performance (user_id, year, sales_total, transactions_count, customers_served, work_hours, performance_score, attendance_rate) VALUES
(1, 2023, 0, 0, 0, 2080, 95.5, 98.2),
(1, 2024, 0, 0, 0, 1560, 96.2, 99.1),
(2, 2023, 285000.50, 2150, 1890, 2040, 92.8, 96.5),
(2, 2024, 298750.25, 2280, 1950, 1520, 94.2, 97.8),
(3, 2023, 275000.00, 2050, 1820, 2060, 90.5, 95.2),
(3, 2024, 287500.75, 2180, 1875, 1540, 91.8, 96.4),
(4, 2022, 180000.25, 1850, 1620, 1920, 88.2, 94.1),
(4, 2023, 195000.50, 1950, 1705, 2000, 89.5, 95.8),
(4, 2024, 205000.75, 2080, 1840, 1480, 90.8, 96.2),
(5, 2022, 165000.00, 1720, 1480, 1880, 87.5, 93.8),
(5, 2023, 175000.25, 1820, 1560, 1960, 88.2, 94.5),
(5, 2024, 185000.50, 1920, 1680, 1460, 89.1, 95.2);

-- Insert yearly goals for current and next year
INSERT INTO user_yearly_goals (user_id, year, sales_target, transactions_target, customers_target, performance_target) VALUES
(2, 2024, 300000, 2300, 2000, 95.0),
(2, 2025, 320000, 2400, 2100, 96.0),
(3, 2024, 290000, 2200, 1900, 93.0),
(3, 2025, 310000, 2300, 2000, 94.0),
(4, 2024, 210000, 2100, 1850, 92.0),
(4, 2025, 225000, 2200, 1950, 93.0),
(5, 2024, 190000, 2000, 1700, 90.0),
(5, 2025, 205000, 2100, 1800, 91.0);

-- Insert sample attendance data (last 30 days for active users)
INSERT INTO user_attendance (user_id, date, check_in, check_out, hours_worked, status) VALUES
-- Admin user (last 10 days)
(1, CURDATE() - INTERVAL 9 DAY, '08:00:00', '17:00:00', 8.0, 'present'),
(1, CURDATE() - INTERVAL 8 DAY, '08:15:00', '17:15:00', 8.0, 'late'),
(1, CURDATE() - INTERVAL 7 DAY, '08:00:00', '17:00:00', 8.0, 'present'),
(1, CURDATE() - INTERVAL 6 DAY, NULL, NULL, 0, 'sick_leave'),
(1, CURDATE() - INTERVAL 5 DAY, '08:00:00', '17:00:00', 8.0, 'present'),
-- Pharmacist 1 (last 10 days)
(2, CURDATE() - INTERVAL 9 DAY, '08:30:00', '17:30:00', 8.0, 'present'),
(2, CURDATE() - INTERVAL 8 DAY, '08:00:00', '16:00:00', 7.0, 'present'),
(2, CURDATE() - INTERVAL 7 DAY, '08:00:00', '17:00:00', 8.0, 'present'),
(2, CURDATE() - INTERVAL 6 DAY, '08:00:00', '17:00:00', 8.0, 'present'),
(2, CURDATE() - INTERVAL 5 DAY, '08:00:00', '17:00:00', 8.0, 'present'),
-- Cashier 1 (last 10 days)
(4, CURDATE() - INTERVAL 9 DAY, '09:00:00', '18:00:00', 8.0, 'present'),
(4, CURDATE() - INTERVAL 8 DAY, '09:00:00', '18:00:00', 8.0, 'present'),
(4, CURDATE() - INTERVAL 7 DAY, '09:30:00', '18:00:00', 7.5, 'late'),
(4, CURDATE() - INTERVAL 6 DAY, '09:00:00', '18:00:00', 8.0, 'present'),
(4, CURDATE() - INTERVAL 5 DAY, '09:00:00', '18:00:00', 8.0, 'present');

-- Insert sample evaluations
INSERT INTO user_evaluations (user_id, evaluator_id, evaluation_date, year, quarter, overall_score, technical_skills, communication_skills, teamwork, punctuality, customer_service, comments) VALUES
(2, 1, '2024-03-31', 2024, 1, 92.5, 95.0, 90.0, 88.0, 96.0, 94.0, 'Excellent pharmaceutical knowledge and customer service. Strong team leader.'),
(3, 1, '2024-03-31', 2024, 1, 91.0, 93.0, 89.0, 92.0, 88.0, 92.0, 'Very knowledgeable and reliable. Occasional punctuality issues.'),
(4, 1, '2024-03-31', 2024, 1, 89.5, 85.0, 92.0, 90.0, 88.0, 95.0, 'Excellent customer service skills. Growing in technical knowledge.'),
(5, 1, '2024-03-31', 2024, 1, 88.0, 82.0, 90.0, 88.0, 90.0, 92.0, 'Good progress and improvement. Strong communication skills.');

-- Insert sample training records
INSERT INTO user_training (user_id, training_name, training_type, provider, start_date, end_date, completion_date, status, certificate_number, cost) VALUES
(2, 'Advanced Pharmaceutical Care', 'certification', 'Pharmacy Board', '2024-01-15', '2024-03-15', '2024-03-10', 'completed', 'APC2024-001', 1200.00),
(2, 'Drug Interaction Seminar', 'seminar', 'Medical Association', '2024-02-20', '2024-02-20', '2024-02-20', 'completed', NULL, 250.00),
(3, 'Clinical Pharmacy Update', 'workshop', 'Pharmacy College', '2024-01-10', '2024-01-12', '2024-01-12', 'completed', 'CPU2024-002', 800.00),
(4, 'Customer Service Excellence', 'internal_training', 'Pharmacy Team', '2024-02-01', '2024-02-03', '2024-02-03', 'completed', NULL, 0.00),
(4, 'Point of Sale Systems', 'online_course', 'Tech Academy', '2024-03-01', '2024-03-15', NULL, 'in_progress', NULL, 150.00),
(5, 'Basic Pharmacy Operations', 'internal_training', 'Pharmacy Team', '2024-01-15', '2024-01-17', '2024-01-17', 'completed', NULL, 0.00);

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Antibiotics', 'Medications that fight bacterial infections'),
('Pain Relief', 'Over-the-counter and prescription pain medications'),
('Vitamins', 'Dietary supplements and vitamins'),
('Cold & Flu', 'Medications for cold and flu symptoms'),
('Digestive Health', 'Medications for digestive issues');

-- Insert sample supplier
INSERT INTO suppliers (company_name, contact_person, phone, email, address) VALUES
('MedSupply Inc.', 'John Smith', '555-0123', 'orders@medsupply.com', '123 Medical Drive, Healthcare City, HC 12345');

-- Update medicines table for better reporting
ALTER TABLE medicines 
ADD COLUMN IF NOT EXISTS cost_price DECIMAL(10,2) DEFAULT 0.00 AFTER unit_price,
ADD COLUMN IF NOT EXISTS minimum_stock INT DEFAULT 10 AFTER stock_quantity;

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_sales_date ON sales(sale_date);
CREATE INDEX IF NOT EXISTS idx_customers_created ON customers(created_at);
CREATE INDEX IF NOT EXISTS idx_medicines_stock ON medicines(stock_quantity, minimum_stock);
CREATE INDEX IF NOT EXISTS idx_medicines_expiry ON medicines(expiry_date);

-- Sample data for testing (optional)
-- Insert sample categories if they don't exist
INSERT IGNORE INTO categories (name, description) VALUES
('Antibiotics', 'Prescription antibiotics and antimicrobials'),
('Pain Relief', 'Pain relievers and anti-inflammatory medications'),
('Vitamins', 'Vitamins and dietary supplements'),
('Cold & Flu', 'Cold, flu, and respiratory medications'),
('Digestive', 'Digestive health and antacids');

-- Update existing medicines with cost prices for profit calculations
UPDATE medicines SET cost_price = unit_price * 0.6 WHERE cost_price = 0;

-- Insert default role permissions
INSERT INTO role_permissions (role, permissions) VALUES
('admin', '["sales.create","sales.view","sales.refund","inventory.create","inventory.edit","inventory.delete","inventory.adjust","customers.create","customers.edit","customers.delete","reports.sales","reports.inventory","reports.financial","reports.export","users.create","users.edit","users.delete","users.permissions","system.backup","system.settings","system.logs"]'),
('pharmacist', '["sales.create","sales.view","inventory.create","inventory.edit","inventory.adjust","customers.create","customers.edit","reports.sales","reports.inventory"]'),
('cashier', '["sales.create","sales.view","customers.create","reports.sales"]')
ON DUPLICATE KEY UPDATE permissions = VALUES(permissions);

-- ===============================================
-- CUSTOMER PORTAL & E-COMMERCE ENHANCEMENT
-- Added: November 3, 2025
-- ===============================================

-- Enhanced customers table for e-commerce (if columns don't exist)
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS city VARCHAR(100) AFTER address,
ADD COLUMN IF NOT EXISTS zip_code VARCHAR(20) AFTER city,
ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE AFTER zip_code,
ADD COLUMN IF NOT EXISTS total_orders INT DEFAULT 0 AFTER is_verified,
ADD COLUMN IF NOT EXISTS total_spent DECIMAL(12,2) DEFAULT 0.00 AFTER total_orders,
ADD COLUMN IF NOT EXISTS last_order_date TIMESTAMP NULL AFTER total_spent;

-- Customer orders table (for online orders from customer portal)
CREATE TABLE IF NOT EXISTS customer_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_amount DECIMAL(10,2) DEFAULT 0.00,
    final_amount DECIMAL(12,2) NOT NULL,
    status ENUM('payment_pending', 'confirmed', 'processing', 'ready_for_pickup', 'completed', 'cancelled', 'refunded') DEFAULT 'payment_pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded', 'partial') DEFAULT 'pending',
    delivery_method ENUM('pickup', 'delivery', 'shipping') DEFAULT 'pickup',
    delivery_address TEXT,
    delivery_city VARCHAR(100),
    delivery_zip VARCHAR(20),
    special_instructions TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_by INT NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer_orders_customer (customer_id),
    INDEX idx_customer_orders_status (status),
    INDEX idx_customer_orders_date (order_date),
    INDEX idx_customer_orders_payment (payment_status)
);

-- Customer order items table
CREATE TABLE IF NOT EXISTS customer_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    medicine_id INT NOT NULL,
    medicine_name VARCHAR(200) NOT NULL, -- Store name in case medicine is deleted
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) DEFAULT 0.00, -- For profit calculation
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    INDEX idx_customer_order_items_order (order_id),
    INDEX idx_customer_order_items_medicine (medicine_id)
);

-- Payment transactions table (M-Pesa and other payment methods)
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    payment_method ENUM('mpesa', 'card', 'cash', 'bank_transfer', 'other') DEFAULT 'mpesa',
    phone_number VARCHAR(20),
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'KES',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    
    -- M-Pesa specific fields
    checkout_request_id VARCHAR(255) NULL,
    merchant_request_id VARCHAR(255) NULL,
    mpesa_receipt VARCHAR(100) NULL,
    mpesa_transaction_id VARCHAR(100) NULL,
    transaction_date VARCHAR(20) NULL,
    mpesa_response TEXT NULL,
    callback_response TEXT NULL,
    
    -- General payment fields
    external_transaction_id VARCHAR(255) NULL,
    gateway_response TEXT NULL,
    failure_reason TEXT NULL,
    reference_number VARCHAR(100) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    INDEX idx_payment_transactions_order (order_id),
    INDEX idx_payment_transactions_customer (customer_id),
    INDEX idx_payment_transactions_status (status),
    INDEX idx_payment_transactions_method (payment_method),
    INDEX idx_payment_transactions_checkout (checkout_request_id),
    INDEX idx_payment_transactions_date (created_at)
);

-- Order status history table (for tracking order progression)
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NULL,
    change_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_status_history_order (order_id),
    INDEX idx_order_status_history_date (created_at)
);

-- Shopping cart table (for persistent carts - optional)
CREATE TABLE IF NOT EXISTS shopping_carts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL,
    session_id VARCHAR(128) NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    INDEX idx_shopping_carts_customer (customer_id),
    INDEX idx_shopping_carts_session (session_id),
    INDEX idx_shopping_carts_medicine (medicine_id)
);

-- Customer notifications table
CREATE TABLE IF NOT EXISTS customer_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    order_id INT NULL,
    type ENUM('order_confirmed', 'payment_received', 'order_ready', 'order_completed', 'order_cancelled', 'general') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_via ENUM('email', 'sms', 'push', 'system') DEFAULT 'system',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    INDEX idx_customer_notifications_customer (customer_id),
    INDEX idx_customer_notifications_order (order_id),
    INDEX idx_customer_notifications_type (type),
    INDEX idx_customer_notifications_read (is_read)
);

-- Medicine reviews and ratings table (optional feature)
CREATE TABLE IF NOT EXISTS medicine_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_id INT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_title VARCHAR(200),
    review_text TEXT,
    is_verified_purchase BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE SET NULL,
    INDEX idx_medicine_reviews_medicine (medicine_id),
    INDEX idx_medicine_reviews_customer (customer_id),
    INDEX idx_medicine_reviews_rating (rating)
);

-- Customer portal analytics table
CREATE TABLE IF NOT EXISTS customer_portal_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    unique_visitors INT DEFAULT 0,
    page_views INT DEFAULT 0,
    orders_placed INT DEFAULT 0,
    orders_completed INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    conversion_rate DECIMAL(5,2) DEFAULT 0.00,
    avg_order_value DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (date),
    INDEX idx_customer_portal_analytics_date (date)
);

-- ===============================================
-- ENHANCED INVENTORY & PRODUCT MANAGEMENT
-- ===============================================

-- Medicine images table (for customer portal display)
CREATE TABLE IF NOT EXISTS medicine_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    image_name VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_type ENUM('primary', 'secondary', 'thumbnail') DEFAULT 'secondary',
    alt_text VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    INDEX idx_medicine_images_medicine (medicine_id),
    INDEX idx_medicine_images_type (image_type)
);

-- Medicine stock alerts table
CREATE TABLE IF NOT EXISTS medicine_stock_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    alert_type ENUM('low_stock', 'out_of_stock', 'expiring_soon', 'expired') NOT NULL,
    threshold_value INT,
    alert_message VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_medicine_stock_alerts_medicine (medicine_id),
    INDEX idx_medicine_stock_alerts_type (alert_type),
    INDEX idx_medicine_stock_alerts_status (is_active, is_resolved)
);

-- ===============================================
-- SYSTEM CONFIGURATION & SETTINGS
-- ===============================================

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_system_settings_key (setting_key),
    INDEX idx_system_settings_public (is_public)
);

-- ===============================================
-- DEFAULT DATA & SETTINGS
-- ===============================================

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('store_name', 'PharmaCare Pharmacy', 'string', 'Store name displayed on customer portal', TRUE),
('store_phone', '+254-700-000-000', 'string', 'Store contact phone number', TRUE),
('store_email', 'info@pharmacare.co.ke', 'string', 'Store contact email', TRUE),
('store_address', '123 Health Street, Nairobi, Kenya', 'string', 'Store physical address', TRUE),
('customer_portal_enabled', 'true', 'boolean', 'Enable/disable customer portal', FALSE),
('online_ordering_enabled', 'true', 'boolean', 'Enable/disable online ordering', FALSE),
('mpesa_enabled', 'true', 'boolean', 'Enable/disable M-Pesa payments', FALSE),
('mpesa_shortcode', '174379', 'string', 'M-Pesa business shortcode', FALSE),
('mpesa_environment', 'sandbox', 'string', 'M-Pesa environment (sandbox/production)', FALSE),
('order_auto_confirm', 'false', 'boolean', 'Auto-confirm orders after payment', FALSE),
('low_stock_threshold', '10', 'integer', 'Default low stock alert threshold', FALSE),
('currency_code', 'KES', 'string', 'Store currency code', TRUE),
('currency_symbol', 'KES', 'string', 'Store currency symbol', TRUE),
('tax_rate', '16.0', 'decimal', 'Default tax rate percentage', FALSE),
('delivery_fee', '200.0', 'decimal', 'Standard delivery fee', TRUE),
('free_delivery_threshold', '2000.0', 'decimal', 'Minimum order for free delivery', TRUE),
('order_confirmation_email', 'true', 'boolean', 'Send order confirmation emails', FALSE),
('payment_timeout_minutes', '10', 'integer', 'Payment timeout in minutes', FALSE)
ON DUPLICATE KEY UPDATE 
setting_value = VALUES(setting_value),
updated_at = CURRENT_TIMESTAMP;

-- ===============================================
-- TRIGGERS FOR AUTOMATION
-- ===============================================

-- Trigger to update customer statistics when order is completed
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS update_customer_stats_after_order
AFTER UPDATE ON customer_orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE customers 
        SET 
            total_orders = total_orders + 1,
            total_spent = total_spent + NEW.final_amount,
            last_order_date = NEW.completed_at
        WHERE id = NEW.customer_id;
    END IF;
END$$

-- Trigger to create order status history
CREATE TRIGGER IF NOT EXISTS log_order_status_change
AFTER UPDATE ON customer_orders
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, change_reason)
        VALUES (NEW.id, OLD.status, NEW.status, NEW.processed_by, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$

-- Trigger to create stock alerts for low stock
CREATE TRIGGER IF NOT EXISTS check_stock_levels_after_update
AFTER UPDATE ON medicines
FOR EACH ROW
BEGIN
    -- Check for low stock
    IF NEW.stock_quantity <= NEW.min_stock_level AND OLD.stock_quantity > OLD.min_stock_level THEN
        INSERT INTO medicine_stock_alerts (medicine_id, alert_type, threshold_value, alert_message)
        VALUES (NEW.id, 'low_stock', NEW.min_stock_level, 
                CONCAT('Medicine "', NEW.name, '" is running low. Current stock: ', NEW.stock_quantity, ', Minimum: ', NEW.min_stock_level));
    END IF;
    
    -- Check for out of stock
    IF NEW.stock_quantity = 0 AND OLD.stock_quantity > 0 THEN
        INSERT INTO medicine_stock_alerts (medicine_id, alert_type, threshold_value, alert_message)
        VALUES (NEW.id, 'out_of_stock', 0, 
                CONCAT('Medicine "', NEW.name, '" is out of stock.'));
    END IF;
END$$

DELIMITER ;

-- ===============================================
-- VIEWS FOR REPORTING & ANALYTICS
-- ===============================================

-- View for customer order summary
CREATE OR REPLACE VIEW customer_order_summary AS
SELECT 
    c.id as customer_id,
    c.name as customer_name,
    c.email,
    c.phone,
    COUNT(co.id) as total_orders,
    COALESCE(SUM(CASE WHEN co.status = 'completed' THEN co.final_amount END), 0) as total_spent,
    COALESCE(AVG(CASE WHEN co.status = 'completed' THEN co.final_amount END), 0) as avg_order_value,
    MAX(co.order_date) as last_order_date,
    COUNT(CASE WHEN co.status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN co.status = 'cancelled' THEN 1 END) as cancelled_orders
FROM customers c
LEFT JOIN customer_orders co ON c.id = co.customer_id
GROUP BY c.id, c.name, c.email, c.phone;

-- View for medicine sales analytics
CREATE OR REPLACE VIEW medicine_sales_analytics AS
SELECT 
    m.id as medicine_id,
    m.name as medicine_name,
    m.category_id,
    cat.name as category_name,
    m.stock_quantity,
    m.unit_price,
    COUNT(coi.id) as times_ordered,
    COALESCE(SUM(coi.quantity), 0) as total_quantity_sold,
    COALESCE(SUM(coi.total_price), 0) as total_revenue,
    COALESCE(AVG(coi.unit_price), 0) as avg_selling_price,
    MAX(co.order_date) as last_sold_date
FROM medicines m
LEFT JOIN categories cat ON m.category_id = cat.id
LEFT JOIN customer_order_items coi ON m.id = coi.medicine_id
LEFT JOIN customer_orders co ON coi.order_id = co.id AND co.status = 'completed'
WHERE m.is_active = TRUE
GROUP BY m.id, m.name, m.category_id, cat.name, m.stock_quantity, m.unit_price;

-- View for daily sales summary
CREATE OR REPLACE VIEW daily_sales_summary AS
SELECT 
    DATE(co.order_date) as sale_date,
    COUNT(co.id) as total_orders,
    COUNT(CASE WHEN co.status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN co.status = 'cancelled' THEN 1 END) as cancelled_orders,
    COALESCE(SUM(CASE WHEN co.status = 'completed' THEN co.final_amount END), 0) as total_revenue,
    COALESCE(AVG(CASE WHEN co.status = 'completed' THEN co.final_amount END), 0) as avg_order_value,
    COUNT(DISTINCT co.customer_id) as unique_customers
FROM customer_orders co
WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(co.order_date)
ORDER BY sale_date DESC;

-- ===============================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- ===============================================

-- Additional indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_medicines_active_stock ON medicines(is_active, stock_quantity);
CREATE INDEX IF NOT EXISTS idx_medicines_category_active ON medicines(category_id, is_active);
CREATE INDEX IF NOT EXISTS idx_medicines_expiry_active ON medicines(expiry_date, is_active);
CREATE INDEX IF NOT EXISTS idx_customer_orders_status_date ON customer_orders(status, order_date);
CREATE INDEX IF NOT EXISTS idx_customer_orders_customer_status ON customer_orders(customer_id, status);
CREATE INDEX IF NOT EXISTS idx_payment_transactions_status_date ON payment_transactions(status, created_at);

-- ===============================================
-- ADDITIONAL MODERN FEATURES & ENHANCEMENTS
-- ===============================================

-- API rate limiting and security
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_rate_limits_ip (ip_address),
    INDEX idx_api_rate_limits_endpoint (endpoint),
    INDEX idx_api_rate_limits_window (window_start)
);

-- Customer login sessions (for future customer authentication)
CREATE TABLE IF NOT EXISTS customer_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_sessions_token (session_token),
    INDEX idx_customer_sessions_customer (customer_id),
    INDEX idx_customer_sessions_expires (expires_at)
);

-- Medicine batch tracking for better inventory control
CREATE TABLE IF NOT EXISTS medicine_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    supplier_id INT,
    quantity_received INT NOT NULL,
    quantity_remaining INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    manufacture_date DATE,
    expiry_date DATE NOT NULL,
    received_date DATE DEFAULT (CURDATE()),
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_medicine_batches_medicine (medicine_id),
    INDEX idx_medicine_batches_batch (batch_number),
    INDEX idx_medicine_batches_expiry (expiry_date),
    UNIQUE KEY unique_medicine_batch (medicine_id, batch_number)
);

-- Customer loyalty program
CREATE TABLE IF NOT EXISTS customer_loyalty_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    points_earned INT DEFAULT 0,
    points_spent INT DEFAULT 0,
    points_balance INT DEFAULT 0,
    tier_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    last_tier_update DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_loyalty_customer (customer_id),
    INDEX idx_customer_loyalty_tier (tier_level)
);

-- Customer loyalty transactions
CREATE TABLE IF NOT EXISTS customer_loyalty_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    order_id INT NULL,
    transaction_type ENUM('earned', 'spent', 'expired', 'bonus', 'adjustment') NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255),
    reference_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE SET NULL,
    INDEX idx_loyalty_transactions_customer (customer_id),
    INDEX idx_loyalty_transactions_type (transaction_type),
    INDEX idx_loyalty_transactions_date (created_at)
);

-- Medicine categories with hierarchy support
ALTER TABLE categories 
ADD COLUMN IF NOT EXISTS parent_id INT NULL AFTER id,
ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0 AFTER description,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER sort_order,
ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER is_active,
ADD FOREIGN KEY IF NOT EXISTS fk_categories_parent (parent_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Medicine tags for better search and categorization
CREATE TABLE IF NOT EXISTS medicine_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medicine to tags mapping
CREATE TABLE IF NOT EXISTS medicine_tag_mappings (
    medicine_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (medicine_id, tag_id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES medicine_tags(id) ON DELETE CASCADE
);

-- Medicine search analytics
CREATE TABLE IF NOT EXISTS medicine_search_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    search_term VARCHAR(255) NOT NULL,
    results_count INT DEFAULT 0,
    clicked_medicine_id INT NULL,
    customer_ip VARCHAR(45),
    search_date DATE DEFAULT (CURDATE()),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clicked_medicine_id) REFERENCES medicines(id) ON DELETE SET NULL,
    INDEX idx_medicine_search_term (search_term),
    INDEX idx_medicine_search_date (search_date),
    INDEX idx_medicine_search_clicked (clicked_medicine_id)
);

-- Customer feedback and suggestions
CREATE TABLE IF NOT EXISTS customer_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL,
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    feedback_type ENUM('suggestion', 'complaint', 'compliment', 'bug_report', 'feature_request') NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    rating INT NULL CHECK (rating >= 1 AND rating <= 5),
    status ENUM('new', 'in_review', 'resolved', 'closed') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    assigned_to INT NULL,
    response TEXT NULL,
    responded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer_feedback_customer (customer_id),
    INDEX idx_customer_feedback_type (feedback_type),
    INDEX idx_customer_feedback_status (status)
);

-- Promotional offers and discounts
CREATE TABLE IF NOT EXISTS promotional_offers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    offer_type ENUM('percentage', 'fixed_amount', 'buy_x_get_y', 'free_shipping') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_order_amount DECIMAL(10,2) DEFAULT 0.00,
    maximum_discount DECIMAL(10,2) NULL,
    promo_code VARCHAR(50) UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    usage_limit INT NULL,
    usage_count INT DEFAULT 0,
    applicable_to ENUM('all', 'category', 'medicine', 'customer_tier') DEFAULT 'all',
    target_value VARCHAR(255), -- category_id, medicine_id, or tier level
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_promotional_offers_code (promo_code),
    INDEX idx_promotional_offers_dates (start_date, end_date),
    INDEX idx_promotional_offers_active (is_active)
);

-- Promotional offer usage tracking
CREATE TABLE IF NOT EXISTS promotional_offer_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    offer_id INT NOT NULL,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    discount_applied DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES promotional_offers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_promo_usage_offer (offer_id),
    INDEX idx_promo_usage_customer (customer_id),
    INDEX idx_promo_usage_date (used_at)
);

-- Email campaign management
CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    template_name VARCHAR(100),
    target_audience ENUM('all_customers', 'active_customers', 'inactive_customers', 'by_tier', 'custom') NOT NULL,
    target_criteria JSON NULL,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'cancelled') DEFAULT 'draft',
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    delivered_count INT DEFAULT 0,
    opened_count INT DEFAULT 0,
    clicked_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_email_campaigns_status (status),
    INDEX idx_email_campaigns_scheduled (scheduled_at)
);

-- Email campaign recipients tracking
CREATE TABLE IF NOT EXISTS email_campaign_recipients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    customer_id INT NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    opened_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    error_message TEXT NULL,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_campaign_recipients_campaign (campaign_id),
    INDEX idx_campaign_recipients_customer (customer_id),
    INDEX idx_campaign_recipients_status (status)
);

-- ===============================================
-- DEFAULT SAMPLE DATA FOR NEW FEATURES
-- ===============================================

-- Insert default medicine tags
INSERT INTO medicine_tags (name, description, color) VALUES
('Over-the-Counter', 'Medicines available without prescription', '#28a745'),
('Prescription-Only', 'Medicines requiring doctor prescription', '#dc3545'),
('Generic', 'Generic version of branded medicine', '#17a2b8'),
('Brand', 'Branded medicine', '#ffc107'),
('Fast-Acting', 'Quick relief medicines', '#fd7e14'),
('Long-Lasting', 'Extended release medicines', '#6610f2'),
('Natural', 'Natural or herbal medicines', '#20c997'),
('Imported', 'Imported medicines', '#6c757d')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert default promotional offers
INSERT INTO promotional_offers (name, description, offer_type, discount_value, start_date, end_date, promo_code, created_by) VALUES
('New Customer Discount', '10% off for first-time customers', 'percentage', 10.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'WELCOME10', 1),
('Free Delivery', 'Free delivery on orders above KES 1000', 'free_shipping', 0.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'FREEDEL', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Update system settings with new features
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('loyalty_points_rate', '1', 'integer', 'Points earned per KES spent (1 point per KES)', FALSE),
('loyalty_redemption_rate', '10', 'integer', 'Points needed for 1 KES discount', FALSE),
('email_notifications_enabled', 'true', 'boolean', 'Enable email notifications', FALSE),
('sms_notifications_enabled', 'false', 'boolean', 'Enable SMS notifications', FALSE),
('customer_registration_enabled', 'true', 'boolean', 'Allow customer registration', FALSE),
('customer_reviews_enabled', 'true', 'boolean', 'Allow customer medicine reviews', TRUE),
('search_analytics_enabled', 'true', 'boolean', 'Track search analytics', FALSE),
('promotional_offers_enabled', 'true', 'boolean', 'Enable promotional offers', TRUE),
('api_rate_limit_requests', '100', 'integer', 'API requests per minute per IP', FALSE),
('api_rate_limit_window', '60', 'integer', 'Rate limit window in seconds', FALSE)
ON DUPLICATE KEY UPDATE 
setting_value = VALUES(setting_value),
updated_at = CURRENT_TIMESTAMP;

-- ===============================================
-- ENHANCED VIEWS FOR COMPREHENSIVE REPORTING
-- ===============================================

-- Customer lifetime value view
CREATE OR REPLACE VIEW customer_lifetime_value AS
SELECT 
    c.id as customer_id,
    c.name as customer_name,
    c.email,
    c.phone,
    c.created_at as registration_date,
    COALESCE(c.total_orders, 0) as total_orders,
    COALESCE(c.total_spent, 0) as lifetime_value,
    COALESCE(clp.points_balance, 0) as loyalty_points,
    COALESCE(clp.tier_level, 'bronze') as tier_level,
    DATEDIFF(CURDATE(), c.last_order_date) as days_since_last_order,
    CASE 
        WHEN c.last_order_date IS NULL THEN 'never_ordered'
        WHEN DATEDIFF(CURDATE(), c.last_order_date) <= 30 THEN 'active'
        WHEN DATEDIFF(CURDATE(), c.last_order_date) <= 90 THEN 'inactive'
        ELSE 'dormant'
    END as customer_status
FROM customers c
LEFT JOIN customer_loyalty_points clp ON c.id = clp.customer_id;

-- Medicine profitability analysis view
CREATE OR REPLACE VIEW medicine_profitability_analysis AS
SELECT 
    m.id as medicine_id,
    m.name as medicine_name,
    m.selling_price,
    m.cost_price,
    (m.selling_price - m.cost_price) as profit_per_unit,
    CASE 
        WHEN m.cost_price > 0 THEN ROUND(((m.selling_price - m.cost_price) / m.cost_price) * 100, 2)
        ELSE 0 
    END as profit_margin_percent,
    m.stock_quantity,
    (m.stock_quantity * (m.selling_price - m.cost_price)) as potential_profit,
    COUNT(coi.id) as times_sold,
    COALESCE(SUM(coi.quantity), 0) as total_quantity_sold,
    COALESCE(SUM(coi.total_price), 0) as total_revenue,
    COALESCE(SUM(coi.quantity * coi.cost_price), 0) as total_cost,
    COALESCE(SUM(coi.total_price) - SUM(coi.quantity * coi.cost_price), 0) as total_profit
FROM medicines m
LEFT JOIN customer_order_items coi ON m.id = coi.medicine_id
LEFT JOIN customer_orders co ON coi.order_id = co.id AND co.status = 'completed'
WHERE m.is_active = TRUE
GROUP BY m.id, m.name, m.selling_price, m.cost_price, m.stock_quantity;

-- Monthly business performance view
CREATE OR REPLACE VIEW monthly_business_performance AS
SELECT 
    DATE_FORMAT(co.order_date, '%Y-%m') as month,
    COUNT(DISTINCT co.id) as total_orders,
    COUNT(DISTINCT co.customer_id) as unique_customers,
    COUNT(DISTINCT coi.medicine_id) as unique_medicines_sold,
    AVG(co.final_amount) as avg_order_value,
    SUM(co.final_amount) as total_revenue,
    SUM(coi.quantity * coi.cost_price) as total_cost,
    SUM(co.final_amount) - SUM(coi.quantity * coi.cost_price) as gross_profit,
    COUNT(CASE WHEN co.status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN co.status = 'cancelled' THEN 1 END) as cancelled_orders,
    ROUND(COUNT(CASE WHEN co.status = 'completed' THEN 1 END) * 100.0 / COUNT(*), 2) as completion_rate
FROM customer_orders co
JOIN customer_order_items coi ON co.id = coi.order_id
WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(co.order_date, '%Y-%m')
ORDER BY month DESC;

-- ===============================================
-- FINAL PERFORMANCE OPTIMIZATIONS
-- ===============================================

-- Additional indexes for new features
CREATE INDEX IF NOT EXISTS idx_customer_loyalty_points_balance ON customer_loyalty_points(points_balance);
CREATE INDEX IF NOT EXISTS idx_medicine_batches_expiry_active ON medicine_batches(expiry_date, is_active);
CREATE INDEX IF NOT EXISTS idx_promotional_offers_dates_active ON promotional_offers(start_date, end_date, is_active);
CREATE INDEX IF NOT EXISTS idx_customer_feedback_status_date ON customer_feedback(status, created_at);
CREATE INDEX IF NOT EXISTS idx_medicine_search_term_date ON medicine_search_analytics(search_term, search_date);

-- ===============================================
-- END OF COMPREHENSIVE DATABASE SCHEMA
-- ===============================================
