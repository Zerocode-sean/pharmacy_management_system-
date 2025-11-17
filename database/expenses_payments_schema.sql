-- ============================================
-- EXPENSES & SUPPLIER PAYMENTS SYSTEM
-- Add these tables to pharmacy_management database
-- ============================================

USE pharmacy_management;

-- ============================================
-- 1. SUPPLIER PAYMENTS TABLE
-- Track all payments made to suppliers
-- ============================================
CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    purchase_order_id INT NULL COMMENT 'Link to specific purchase order if applicable',
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'mpesa', 'other') NOT NULL DEFAULT 'bank_transfer',
    reference_number VARCHAR(100) COMMENT 'Cheque number, transfer ID, M-Pesa code, etc.',
    bank_name VARCHAR(100) NULL,
    cheque_number VARCHAR(50) NULL,
    account_number VARCHAR(50) NULL,
    notes TEXT,
    receipt_url VARCHAR(255) NULL COMMENT 'Path to uploaded receipt/proof',
    status ENUM('pending', 'cleared', 'bounced', 'cancelled') DEFAULT 'cleared',
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_supplier_payments_supplier (supplier_id),
    INDEX idx_supplier_payments_date (payment_date),
    INDEX idx_supplier_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. GENERAL EXPENSES TABLE
-- Track all business operating expenses
-- ============================================
CREATE TABLE IF NOT EXISTS expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expense_date DATE NOT NULL,
    category ENUM(
        'rent',
        'utilities', 
        'salaries',
        'maintenance',
        'marketing',
        'transport',
        'insurance',
        'taxes',
        'licenses',
        'office_supplies',
        'professional_fees',
        'repairs',
        'fuel',
        'security',
        'cleaning',
        'communication',
        'training',
        'other'
    ) NOT NULL DEFAULT 'other',
    subcategory VARCHAR(100) NULL COMMENT 'e.g., Electricity, Water, Internet',
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'mpesa', 'credit_card', 'other') NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(100),
    receipt_number VARCHAR(100),
    receipt_url VARCHAR(255) NULL COMMENT 'Path to uploaded receipt',
    vendor_name VARCHAR(200) NULL COMMENT 'Who we paid (if not a supplier)',
    vendor_contact VARCHAR(100) NULL,
    is_recurring BOOLEAN DEFAULT FALSE COMMENT 'Monthly recurring expense?',
    recurrence_period ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NULL,
    notes TEXT,
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_expenses_date (expense_date),
    INDEX idx_expenses_category (category),
    INDEX idx_expenses_recurring (is_recurring)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. LINK CUSTOMER ORDERS TO SALES
-- Add sales_id field to customer_orders
-- ============================================
SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = 'pharmacy_management' 
    AND table_name = 'customer_orders' 
    AND column_name = 'sale_id');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE customer_orders ADD COLUMN sale_id INT NULL COMMENT "Links to sales table when order is processed" AFTER completed_at',
    'SELECT "Column sale_id already exists" AS message');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 4. ADD OUTSTANDING BALANCE TO SUPPLIERS
-- Track how much we owe each supplier
-- ============================================
SET @exist1 := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = 'pharmacy_management' 
    AND table_name = 'suppliers' 
    AND column_name = 'outstanding_balance');
SET @sqlstmt1 := IF(@exist1 = 0, 
    'ALTER TABLE suppliers ADD COLUMN outstanding_balance DECIMAL(12,2) DEFAULT 0.00 COMMENT "Total amount owed to supplier" AFTER notes',
    'SELECT "Column outstanding_balance already exists" AS message');
PREPARE stmt1 FROM @sqlstmt1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @exist2 := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = 'pharmacy_management' 
    AND table_name = 'suppliers' 
    AND column_name = 'last_payment_date');
SET @sqlstmt2 := IF(@exist2 = 0, 
    'ALTER TABLE suppliers ADD COLUMN last_payment_date DATE NULL COMMENT "Date of last payment" AFTER outstanding_balance',
    'SELECT "Column last_payment_date already exists" AS message');
PREPARE stmt2 FROM @sqlstmt2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @exist3 := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = 'pharmacy_management' 
    AND table_name = 'suppliers' 
    AND column_name = 'credit_limit');
SET @sqlstmt3 := IF(@exist3 = 0, 
    'ALTER TABLE suppliers ADD COLUMN credit_limit DECIMAL(12,2) DEFAULT 0.00 COMMENT "Maximum credit allowed" AFTER last_payment_date',
    'SELECT "Column credit_limit already exists" AS message');
PREPARE stmt3 FROM @sqlstmt3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- ============================================
-- 5. ADD COST TRACKING TO SALES
-- Track cost of goods sold for profit calculation
-- ============================================
SET @exist4 := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = 'pharmacy_management' 
    AND table_name = 'sale_items' 
    AND column_name = 'cost_price');
SET @sqlstmt4 := IF(@exist4 = 0, 
    'ALTER TABLE sale_items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0.00 COMMENT "What we paid for this item" AFTER unit_price',
    'SELECT "Column cost_price already exists" AS message');
PREPARE stmt4 FROM @sqlstmt4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

SET @exist5 := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = 'pharmacy_management' 
    AND table_name = 'sale_items' 
    AND column_name = 'profit');
SET @sqlstmt5 := IF(@exist5 = 0, 
    'ALTER TABLE sale_items ADD COLUMN profit DECIMAL(10,2) DEFAULT 0.00 COMMENT "Profit per item (selling - cost)" AFTER cost_price',
    'SELECT "Column profit already exists" AS message');
PREPARE stmt5 FROM @sqlstmt5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

-- ============================================
-- 6. CREATE VIEWS FOR REPORTING
-- ============================================

-- View: Supplier Balance Summary
CREATE OR REPLACE VIEW supplier_balance_summary AS
SELECT 
    s.id,
    s.company_name,
    s.contact_person,
    s.phone,
    s.email,
    s.outstanding_balance,
    s.credit_limit,
    s.last_payment_date,
    COALESCE(SUM(po.total_amount), 0) AS total_purchases,
    COALESCE(SUM(sp.amount), 0) AS total_payments,
    (COALESCE(SUM(po.total_amount), 0) - COALESCE(SUM(sp.amount), 0)) AS calculated_balance,
    COUNT(DISTINCT po.id) AS total_orders,
    COUNT(DISTINCT sp.id) AS total_payments_count
FROM suppliers s
LEFT JOIN purchase_orders po ON s.id = po.supplier_id
LEFT JOIN supplier_payments sp ON s.id = sp.supplier_id
GROUP BY s.id;

-- View: Monthly Expense Summary
CREATE OR REPLACE VIEW monthly_expense_summary AS
SELECT 
    DATE_FORMAT(expense_date, '%Y-%m') AS month,
    category,
    COUNT(*) AS transaction_count,
    SUM(amount) AS total_amount
FROM expenses
GROUP BY DATE_FORMAT(expense_date, '%Y-%m'), category
ORDER BY month DESC, total_amount DESC;

-- View: Profit & Loss Summary
CREATE OR REPLACE VIEW profit_loss_summary AS
SELECT 
    DATE_FORMAT(s.sale_date, '%Y-%m') AS month,
    -- Revenue
    SUM(s.final_amount) AS total_revenue,
    -- Cost of Goods Sold
    SUM(si.quantity * si.cost_price) AS cogs,
    -- Gross Profit
    (SUM(s.final_amount) - SUM(si.quantity * si.cost_price)) AS gross_profit,
    -- Operating Expenses
    (
        SELECT COALESCE(SUM(amount), 0)
        FROM expenses
        WHERE DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(s.sale_date, '%Y-%m')
    ) AS operating_expenses,
    -- Net Profit
    (
        (SUM(s.final_amount) - SUM(si.quantity * si.cost_price)) - 
        (
            SELECT COALESCE(SUM(amount), 0)
            FROM expenses
            WHERE DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(s.sale_date, '%Y-%m')
        )
    ) AS net_profit
FROM sales s
LEFT JOIN sale_items si ON s.id = si.sale_id
GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
ORDER BY month DESC;

-- ============================================
-- 7. INSERT SAMPLE DATA (OPTIONAL - FOR TESTING)
-- ============================================

-- Sample expense categories
INSERT INTO expenses (expense_date, category, subcategory, description, amount, payment_method, created_by) VALUES
('2025-11-01', 'rent', 'Shop Rent', 'November rent payment', 50000.00, 'bank_transfer', 1),
('2025-11-05', 'utilities', 'Electricity', 'KPLC bill for October', 8500.00, 'mpesa', 1),
('2025-11-05', 'utilities', 'Water', 'Nairobi Water bill', 2300.00, 'mpesa', 1),
('2025-11-10', 'salaries', 'Staff Salaries', 'November salaries', 120000.00, 'bank_transfer', 1),
('2025-11-12', 'maintenance', 'AC Repair', 'Air conditioning repair', 15000.00, 'cash', 1);

-- Sample supplier payment
INSERT INTO supplier_payments (supplier_id, payment_date, amount, payment_method, reference_number, notes, created_by) VALUES
(1, '2025-11-15', 250000.00, 'bank_transfer', 'TXN987654321', 'Payment for October invoice', 1);

-- ============================================
-- 8. TRIGGERS FOR AUTO-CALCULATION
-- ============================================

-- Trigger: Update supplier outstanding balance when purchase order is created
DELIMITER //
CREATE TRIGGER update_supplier_balance_on_po
AFTER INSERT ON purchase_orders
FOR EACH ROW
BEGIN
    UPDATE suppliers 
    SET outstanding_balance = outstanding_balance + NEW.total_amount
    WHERE id = NEW.supplier_id;
END//
DELIMITER ;

-- Trigger: Update supplier outstanding balance when payment is made
DELIMITER //
CREATE TRIGGER update_supplier_balance_on_payment
AFTER INSERT ON supplier_payments
FOR EACH ROW
BEGIN
    UPDATE suppliers 
    SET 
        outstanding_balance = outstanding_balance - NEW.amount,
        last_payment_date = NEW.payment_date
    WHERE id = NEW.supplier_id;
END//
DELIMITER ;

-- Trigger: Calculate profit on sale item insert
DELIMITER //
CREATE TRIGGER calculate_profit_on_sale_item
BEFORE INSERT ON sale_items
FOR EACH ROW
BEGIN
    -- Get cost price from medicines table
    SET NEW.cost_price = (SELECT unit_price FROM medicines WHERE id = NEW.medicine_id);
    -- Calculate profit
    SET NEW.profit = (NEW.unit_price - NEW.cost_price) * NEW.quantity;
END//
DELIMITER ;

-- ============================================
-- SETUP COMPLETE!
-- ============================================
-- Next steps:
-- 1. Run this SQL file in phpMyAdmin or MySQL Workbench
-- 2. Create API endpoints for CRUD operations
-- 3. Build admin UI pages for managing expenses & payments
-- ============================================
