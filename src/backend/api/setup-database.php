<?php
/**
 * Database Tables Setup and Verification Script
 * Run this to ensure all required tables exist for the dashboard
 */

header('Content-Type: text/html; charset=utf-8');

require_once '../config/config.php';
require_once '../config/database.php';

echo "<!DOCTYPE html><html><head><title>Database Setup Verification</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} .warning{color:orange;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";
echo "</head><body>";

echo "<h1>🏥 Pharmacy Management System - Database Verification</h1>";

try {
    $database = new Database();
    $pdo = $database->connect();
    echo "<p class='success'>✅ Database connection successful!</p>";

    // Required tables for the dashboard system
    $requiredTables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'pharmacist', 'cashier') NOT NULL,
            full_name VARCHAR(255),
            email VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'medicines' => "CREATE TABLE IF NOT EXISTS medicines (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            description TEXT,
            manufacturer VARCHAR(255),
            batch_number VARCHAR(100),
            expiry_date DATE,
            price DECIMAL(10,2) NOT NULL,
            cost_price DECIMAL(10,2),
            stock_quantity INT DEFAULT 0,
            minimum_stock INT DEFAULT 10,
            barcode VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'customers' => "CREATE TABLE IF NOT EXISTS customers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(20) NOT NULL,
            address TEXT,
            date_of_birth DATE,
            gender ENUM('male', 'female', 'other'),
            insurance_provider VARCHAR(255),
            insurance_number VARCHAR(100),
            allergies TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'suppliers' => "CREATE TABLE IF NOT EXISTS suppliers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(20) NOT NULL,
            address TEXT,
            license_number VARCHAR(100),
            rating INT DEFAULT 5,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'sales' => "CREATE TABLE IF NOT EXISTS sales (
            id INT PRIMARY KEY AUTO_INCREMENT,
            customer_id INT,
            customer_name VARCHAR(255),
            customer_phone VARCHAR(20),
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('cash', 'mpesa', 'card', 'insurance') DEFAULT 'cash',
            payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        'sale_items' => "CREATE TABLE IF NOT EXISTS sale_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sale_id INT NOT NULL,
            medicine_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (medicine_id) REFERENCES medicines(id)
        )",
        
        'orders' => "CREATE TABLE IF NOT EXISTS orders (
            id INT PRIMARY KEY AUTO_INCREMENT,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_email VARCHAR(255),
            delivery_address TEXT,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) DEFAULT 'mpesa',
            order_status ENUM('pending', 'paid', 'processing', 'delivered', 'cancelled') DEFAULT 'pending',
            payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'order_items' => "CREATE TABLE IF NOT EXISTS order_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            medicine_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (medicine_id) REFERENCES medicines(id)
        )"
    ];

    echo "<h2>📋 Creating/Verifying Required Tables</h2>";
    
    foreach ($requiredTables as $tableName => $createSQL) {
        try {
            $pdo->exec($createSQL);
            echo "<p class='success'>✅ Table '<strong>$tableName</strong>' created/verified successfully</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error with table '<strong>$tableName</strong>': " . $e->getMessage() . "</p>";
        }
    }

    // Insert sample data if tables are empty
    echo "<h2>📊 Inserting Sample Data (if needed)</h2>";
    
    // Check if users table has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    
    if ($userCount == 0) {
        echo "<p class='warning'>⚠️ No users found. Creating sample users...</p>";
        
        $sampleUsers = [
            ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'System Administrator'],
            ['pharmacist1', password_hash('pharma123', PASSWORD_DEFAULT), 'pharmacist', 'Dr. Sarah Johnson'],
            ['cashier1', password_hash('cash123', PASSWORD_DEFAULT), 'cashier', 'John Smith']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
        foreach ($sampleUsers as $user) {
            try {
                $stmt->execute($user);
                echo "<p class='success'>✅ Created user: {$user[0]} (Role: {$user[2]})</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Error creating user {$user[0]}: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p class='success'>✅ Users table has $userCount users</p>";
    }
    
    // Check medicines table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines");
    $medicineCount = $stmt->fetch()['count'];
    
    if ($medicineCount == 0) {
        echo "<p class='warning'>⚠️ No medicines found. Adding sample medicines...</p>";
        
        $sampleMedicines = [
            ['Paracetamol 500mg', 'Analgesic', 'Pain relief medication', 'PharmaCorp', 'BATCH001', '2025-12-31', 50.00, 35.00, 100],
            ['Amoxicillin 250mg', 'Antibiotic', 'Bacterial infection treatment', 'MediCorp', 'BATCH002', '2025-11-30', 120.00, 85.00, 75],
            ['Ibuprofen 400mg', 'NSAID', 'Anti-inflammatory medication', 'HealthPlus', 'BATCH003', '2025-10-15', 80.00, 55.00, 50],
            ['Aspirin 100mg', 'Analgesic', 'Blood thinner and pain relief', 'PharmaCorp', 'BATCH004', '2026-01-15', 30.00, 20.00, 200],
            ['Vitamin C 1000mg', 'Supplement', 'Immune system support', 'VitaHealth', 'BATCH005', '2026-03-30', 25.00, 15.00, 150]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO medicines (name, category, description, manufacturer, batch_number, expiry_date, price, cost_price, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleMedicines as $medicine) {
            try {
                $stmt->execute($medicine);
                echo "<p class='success'>✅ Added medicine: {$medicine[0]} - KES {$medicine[5]}</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Error adding medicine {$medicine[0]}: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p class='success'>✅ Medicines table has $medicineCount medicines</p>";
    }
    
    // Add sample customers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $customerCount = $stmt->fetch()['count'];
    
    if ($customerCount == 0) {
        echo "<p class='warning'>⚠️ Adding sample customers...</p>";
        
        $sampleCustomers = [
            ['John Doe', 'john@example.com', '0712345678', '123 Main St, Nairobi'],
            ['Jane Smith', 'jane@example.com', '0723456789', '456 Oak Ave, Mombasa'],
            ['Michael Johnson', 'michael@example.com', '0734567890', '789 Pine Rd, Kisumu']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)");
        foreach ($sampleCustomers as $customer) {
            try {
                $stmt->execute($customer);
                echo "<p class='success'>✅ Added customer: {$customer[0]}</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Error adding customer {$customer[0]}: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p class='success'>✅ Customers table has $customerCount customers</p>";
    }
    
    // Add sample sales
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
    $salesCount = $stmt->fetch()['count'];
    
    if ($salesCount == 0) {
        echo "<p class='warning'>⚠️ Adding sample sales data...</p>";
        
        $sampleSales = [
            [1, 'John Doe', '0712345678', 150.00, 'cash', 'completed', 2],
            [2, 'Jane Smith', '0723456789', 320.00, 'mpesa', 'completed', 3],
            [3, 'Michael Johnson', '0734567890', 80.00, 'cash', 'completed', 3]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO sales (customer_id, customer_name, customer_phone, total_amount, payment_method, payment_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleSales as $sale) {
            try {
                $stmt->execute($sale);
                echo "<p class='success'>✅ Added sale: KES {$sale[3]} to {$sale[1]}</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Error adding sale: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p class='success'>✅ Sales table has $salesCount sales</p>";
    }

    // Display current data summary
    echo "<h2>📈 Current Database Summary</h2>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Count</th><th>Status</th></tr>";
    
    foreach (array_keys($requiredTables) as $tableName) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $tableName");
            $count = $stmt->fetch()['count'];
            $status = $count > 0 ? "<span class='success'>✅ Has Data</span>" : "<span class='warning'>⚠️ Empty</span>";
            echo "<tr><td>$tableName</td><td>$count</td><td>$status</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td>$tableName</td><td>-</td><td><span class='error'>❌ Error</span></td></tr>";
        }
    }
    echo "</table>";
    
    echo "<h2>🎯 Next Steps</h2>";
    echo "<ul>";
    echo "<li>✅ Database setup complete!</li>";
    echo "<li>🔐 Login credentials created:</li>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Pharmacist:</strong> pharmacist1 / pharma123</li>";
    echo "<li><strong>Cashier:</strong> cashier1 / cash123</li>";
    echo "</ul>";
    echo "<li>🌐 You can now access the dashboards with real database data</li>";
    echo "<li>💰 All prices are displayed in Kenyan Shillings (KES)</li>";
    echo "</ul>";
    
    echo "<p><a href='../frontend/index.html' style='background:#007cba;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Login Page</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in <code>config/config.php</code></p>";
}

echo "</body></html>";
?>
