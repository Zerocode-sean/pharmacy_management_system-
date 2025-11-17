<?php
/**
 * Database Migration Script for Customer Portal & Payment Integration
 * Run this script to update your existing pharmacy database with new tables and features
 */

// Include database configuration
require_once '../src/backend/config/config.php';
require_once '../src/backend/config/database.php';

// Create database connection
try {
    $database = new Database();
    $pdo = $database->connect();
    
    echo "<h1>🔧 Pharmacy Database Migration</h1>";
    echo "<h2>Customer Portal & M-Pesa Payment Integration</h2>";
    echo "<hr>";
    
    $migrations = [];
    $errors = [];
    
    // Migration 1: Enhance customers table
    try {
        echo "<h3>📊 Step 1: Enhancing Customers Table</h3>";
        
        $alterCustomers = "
            ALTER TABLE customers 
            ADD COLUMN IF NOT EXISTS city VARCHAR(100) AFTER address,
            ADD COLUMN IF NOT EXISTS zip_code VARCHAR(20) AFTER city,
            ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE AFTER zip_code,
            ADD COLUMN IF NOT EXISTS total_orders INT DEFAULT 0 AFTER is_verified,
            ADD COLUMN IF NOT EXISTS total_spent DECIMAL(12,2) DEFAULT 0.00 AFTER total_orders,
            ADD COLUMN IF NOT EXISTS last_order_date TIMESTAMP NULL AFTER total_spent,
            ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT FALSE AFTER last_order_date,
            ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL AFTER is_deleted
        ";
        
        $pdo->exec($alterCustomers);
        echo "✅ Enhanced customers table with new columns<br>";
        $migrations[] = "Enhanced customers table";
        
    } catch (Exception $e) {
        $error = "❌ Error enhancing customers table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 1.5: Enhance suppliers table with soft delete
    try {
        echo "<h3>🏢 Step 1.5: Enhancing Suppliers Table</h3>";
        
        $alterSuppliers = "
            ALTER TABLE suppliers 
            ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT FALSE AFTER credit_limit,
            ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL AFTER is_deleted
        ";
        
        $pdo->exec($alterSuppliers);
        echo "✅ Enhanced suppliers table with soft delete columns<br>";
        $migrations[] = "Enhanced suppliers table";
        
    } catch (Exception $e) {
        $error = "❌ Error enhancing suppliers table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 2: Create customer_orders table
    try {
        echo "<h3>🛒 Step 2: Creating Customer Orders Table</h3>";
        
        $createCustomerOrders = "
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
                FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        
        $pdo->exec($createCustomerOrders);
        echo "✅ Created customer_orders table<br>";
        $migrations[] = "Created customer_orders table";
        
    } catch (Exception $e) {
        $error = "❌ Error creating customer_orders table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 3: Create customer_order_items table
    try {
        echo "<h3>📦 Step 3: Creating Customer Order Items Table</h3>";
        
        $createCustomerOrderItems = "
            CREATE TABLE IF NOT EXISTS customer_order_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_id INT NOT NULL,
                medicine_id INT NOT NULL,
                medicine_name VARCHAR(200) NOT NULL,
                quantity INT NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                cost_price DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT
            )
        ";
        
        $pdo->exec($createCustomerOrderItems);
        echo "✅ Created customer_order_items table<br>";
        $migrations[] = "Created customer_order_items table";
        
    } catch (Exception $e) {
        $error = "❌ Error creating customer_order_items table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 4: Create payment_transactions table
    try {
        echo "<h3>💳 Step 4: Creating Payment Transactions Table</h3>";
        
        $createPaymentTransactions = "
            CREATE TABLE IF NOT EXISTS payment_transactions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_id INT NOT NULL,
                customer_id INT NOT NULL,
                payment_method ENUM('mpesa', 'card', 'cash', 'bank_transfer', 'other') DEFAULT 'mpesa',
                phone_number VARCHAR(20),
                amount DECIMAL(12,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'KES',
                status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
                checkout_request_id VARCHAR(255) NULL,
                merchant_request_id VARCHAR(255) NULL,
                mpesa_receipt VARCHAR(100) NULL,
                mpesa_transaction_id VARCHAR(100) NULL,
                transaction_date VARCHAR(20) NULL,
                mpesa_response TEXT NULL,
                callback_response TEXT NULL,
                external_transaction_id VARCHAR(255) NULL,
                gateway_response TEXT NULL,
                failure_reason TEXT NULL,
                reference_number VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                failed_at TIMESTAMP NULL,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE RESTRICT,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
            )
        ";
        
        $pdo->exec($createPaymentTransactions);
        echo "✅ Created payment_transactions table<br>";
        $migrations[] = "Created payment_transactions table";
        
    } catch (Exception $e) {
        $error = "❌ Error creating payment_transactions table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 5: Create order_status_history table
    try {
        echo "<h3>📋 Step 5: Creating Order Status History Table</h3>";
        
        $createOrderStatusHistory = "
            CREATE TABLE IF NOT EXISTS order_status_history (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_id INT NOT NULL,
                old_status VARCHAR(50),
                new_status VARCHAR(50) NOT NULL,
                changed_by INT NULL,
                change_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        
        $pdo->exec($createOrderStatusHistory);
        echo "✅ Created order_status_history table<br>";
        $migrations[] = "Created order_status_history table";
        
    } catch (Exception $e) {
        $error = "❌ Error creating order_status_history table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 6: Create system_settings table
    try {
        echo "<h3>⚙️ Step 6: Creating System Settings Table</h3>";
        
        $createSystemSettings = "
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
                description TEXT,
                is_public BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        
        $pdo->exec($createSystemSettings);
        echo "✅ Created system_settings table<br>";
        $migrations[] = "Created system_settings table";
        
    } catch (Exception $e) {
        $error = "❌ Error creating system_settings table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 7: Create medicine_stock_alerts table
    try {
        echo "<h3>⚠️ Step 7: Creating Medicine Stock Alerts Table</h3>";
        
        $createStockAlerts = "
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
                FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        
        $pdo->exec($createStockAlerts);
        echo "✅ Created medicine_stock_alerts table<br>";
        $migrations[] = "Created medicine_stock_alerts table";
        
    } catch (Exception $e) {
        $error = "❌ Error creating medicine_stock_alerts table: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 8: Add indexes for performance
    try {
        echo "<h3>🚀 Step 8: Adding Performance Indexes</h3>";
        
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_customer_orders_customer ON customer_orders(customer_id)",
            "CREATE INDEX IF NOT EXISTS idx_customer_orders_status ON customer_orders(status)",
            "CREATE INDEX IF NOT EXISTS idx_customer_orders_date ON customer_orders(order_date)",
            "CREATE INDEX IF NOT EXISTS idx_customer_order_items_order ON customer_order_items(order_id)",
            "CREATE INDEX IF NOT EXISTS idx_customer_order_items_medicine ON customer_order_items(medicine_id)",
            "CREATE INDEX IF NOT EXISTS idx_payment_transactions_order ON payment_transactions(order_id)",
            "CREATE INDEX IF NOT EXISTS idx_payment_transactions_customer ON payment_transactions(customer_id)",
            "CREATE INDEX IF NOT EXISTS idx_payment_transactions_status ON payment_transactions(status)",
            "CREATE INDEX IF NOT EXISTS idx_payment_transactions_checkout ON payment_transactions(checkout_request_id)",
            "CREATE INDEX IF NOT EXISTS idx_medicines_active_stock ON medicines(is_active, stock_quantity)",
            "CREATE INDEX IF NOT EXISTS idx_medicines_category_active ON medicines(category_id, is_active)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $pdo->exec($index);
                echo "✅ Added index<br>";
            } catch (Exception $e) {
                echo "⚠️ Index may already exist or error: " . $e->getMessage() . "<br>";
            }
        }
        
        $migrations[] = "Added performance indexes";
        
    } catch (Exception $e) {
        $error = "❌ Error adding indexes: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 9: Insert default system settings
    try {
        echo "<h3>📝 Step 9: Inserting Default System Settings</h3>";
        
        $defaultSettings = [
            ['store_name', 'PharmaCare Pharmacy', 'string', 'Store name displayed on customer portal', 1],
            ['store_phone', '+254-700-000-000', 'string', 'Store contact phone number', 1],
            ['store_email', 'info@pharmacare.co.ke', 'string', 'Store contact email', 1],
            ['store_address', '123 Health Street, Nairobi, Kenya', 'string', 'Store physical address', 1],
            ['customer_portal_enabled', 'true', 'boolean', 'Enable/disable customer portal', 0],
            ['online_ordering_enabled', 'true', 'boolean', 'Enable/disable online ordering', 0],
            ['mpesa_enabled', 'true', 'boolean', 'Enable/disable M-Pesa payments', 0],
            ['mpesa_shortcode', '174379', 'string', 'M-Pesa business shortcode (sandbox)', 0],
            ['mpesa_environment', 'sandbox', 'string', 'M-Pesa environment (sandbox/production)', 0],
            ['currency_code', 'KES', 'string', 'Store currency code', 1],
            ['currency_symbol', 'KES', 'string', 'Store currency symbol', 1],
            ['tax_rate', '16.0', 'decimal', 'Default tax rate percentage', 0],
            ['delivery_fee', '200.0', 'decimal', 'Standard delivery fee', 1],
            ['free_delivery_threshold', '2000.0', 'decimal', 'Minimum order for free delivery', 1],
            ['low_stock_threshold', '10', 'integer', 'Default low stock alert threshold', 0],
            ['payment_timeout_minutes', '10', 'integer', 'Payment timeout in minutes', 0]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
            echo "✅ Added/Updated setting: {$setting[0]}<br>";
        }
        
        $migrations[] = "Inserted default system settings";
        
    } catch (Exception $e) {
        $error = "❌ Error inserting default settings: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Migration 10: Create database views
    try {
        echo "<h3>📊 Step 10: Creating Database Views</h3>";
        
        // Customer order summary view
        $createCustomerOrderSummaryView = "
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
            GROUP BY c.id, c.name, c.email, c.phone
        ";
        
        $pdo->exec($createCustomerOrderSummaryView);
        echo "✅ Created customer_order_summary view<br>";
        
        // Medicine sales analytics view
        $createMedicineSalesView = "
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
                MAX(co.order_date) as last_sold_date
            FROM medicines m
            LEFT JOIN categories cat ON m.category_id = cat.id
            LEFT JOIN customer_order_items coi ON m.id = coi.medicine_id
            LEFT JOIN customer_orders co ON coi.order_id = co.id AND co.status = 'completed'
            WHERE m.is_active = TRUE
            GROUP BY m.id, m.name, m.category_id, cat.name, m.stock_quantity, m.unit_price
        ";
        
        $pdo->exec($createMedicineSalesView);
        echo "✅ Created medicine_sales_analytics view<br>";
        
        $migrations[] = "Created database views";
        
    } catch (Exception $e) {
        $error = "❌ Error creating views: " . $e->getMessage();
        echo $error . "<br>";
        $errors[] = $error;
    }
    
    // Summary
    echo "<hr>";
    echo "<h2>🎉 Migration Summary</h2>";
    
    if (count($migrations) > 0) {
        echo "<h3 style='color: green;'>✅ Successful Migrations (" . count($migrations) . "):</h3>";
        echo "<ul>";
        foreach ($migrations as $migration) {
            echo "<li>$migration</li>";
        }
        echo "</ul>";
    }
    
    if (count($errors) > 0) {
        echo "<h3 style='color: red;'>❌ Errors (" . count($errors) . "):</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    }
    
    if (count($errors) === 0) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
        echo "<strong>🎉 Migration Completed Successfully!</strong><br>";
        echo "Your database has been updated with all the necessary tables and features for the customer portal and M-Pesa payment integration.";
        echo "</div>";
        
        echo "<h3>🚀 Next Steps:</h3>";
        echo "<ol>";
        echo "<li>✅ <a href='../customer-portal/'>Test Customer Portal</a></li>";
        echo "<li>✅ <a href='../mpesa-payment-test.html'>Test M-Pesa Integration</a></li>";
        echo "<li>✅ <a href='../view-payment-transactions.php'>View Payment Transactions</a></li>";
        echo "<li>✅ <a href='../src/frontend/pharmacist_dashboard_clean.html'>Check Pharmacist Dashboard</a></li>";
        echo "<li>✅ Add some medicines through the pharmacist dashboard for testing</li>";
        echo "</ol>";
    } else {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
        echo "<strong>⚠️ Migration Completed with Errors</strong><br>";
        echo "Some migrations failed. Please check the errors above and resolve them manually or contact support.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<strong>❌ Database Connection Error:</strong> " . $e->getMessage();
    echo "<br><br>Please check your database configuration in src/backend/config/config.php";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
}

h1, h2, h3 {
    color: #343a40;
}

h1 {
    border-bottom: 3px solid #007bff;
    padding-bottom: 10px;
}

h3 {
    background: #e9ecef;
    padding: 10px;
    border-left: 4px solid #007bff;
    margin-top: 20px;
}

ul, ol {
    background: white;
    padding: 15px 30px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
