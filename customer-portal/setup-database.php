<?php
/**
 * Complete Database Setup Script
 * This script will set up the entire pharmacy management database from scratch
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'operations' => [],
    'errors' => [],
    'success' => true,
    'message' => ''
];

function logOperation($message, $status = 'success') {
    global $results;
    $results['operations'][] = [
        'message' => $message,
        'status' => $status,
        'timestamp' => date('H:i:s')
    ];
    
    if ($status === 'error') {
        $results['success'] = false;
    }
}

function logError($message) {
    global $results;
    $results['errors'][] = $message;
    $results['success'] = false;
}

try {
    logOperation('Starting complete database setup...', 'info');

    // Step 1: Connect to MySQL server (without specifying database)
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $charset = 'utf8mb4';
    
    try {
        $dsn = "mysql:host=$host;charset=$charset";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        logOperation('✅ Connected to MySQL server');
    } catch (PDOException $e) {
        logError('❌ Cannot connect to MySQL server: ' . $e->getMessage());
        throw new Exception('MySQL server connection failed. Please ensure XAMPP MySQL service is running.');
    }

    // Step 2: Create database
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS pharmacy_management");
        $pdo->exec("USE pharmacy_management");
        logOperation('✅ Created/selected pharmacy_management database');
    } catch (PDOException $e) {
        logError('❌ Database creation failed: ' . $e->getMessage());
        throw new Exception('Database creation failed');
    }

    // Step 3: Create users table (for staff authentication)
    $users_sql = "
    CREATE TABLE IF NOT EXISTS users (
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
    )";

    try {
        $pdo->exec($users_sql);
        logOperation('✅ Created users table');
    } catch (PDOException $e) {
        logOperation('⚠️ Users table: ' . $e->getMessage(), 'warning');
    }

    // Step 4: Create medicines table
    $medicines_sql = "
    CREATE TABLE IF NOT EXISTS medicines (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        generic_name VARCHAR(100),
        brand VARCHAR(100),
        category VARCHAR(50),
        description TEXT,
        dosage_form VARCHAR(50),
        strength VARCHAR(50),
        unit_price DECIMAL(10,2) NOT NULL,
        cost_price DECIMAL(10,2),
        quantity_in_stock INT DEFAULT 0,
        minimum_stock_level INT DEFAULT 10,
        maximum_stock_level INT DEFAULT 1000,
        expiry_date DATE,
        batch_number VARCHAR(50),
        supplier_id INT,
        barcode VARCHAR(100),
        prescription_required BOOLEAN DEFAULT FALSE,
        storage_conditions TEXT,
        side_effects TEXT,
        contraindications TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE
    )";

    try {
        $pdo->exec($medicines_sql);
        logOperation('✅ Created medicines table');
    } catch (PDOException $e) {
        logOperation('⚠️ Medicines table: ' . $e->getMessage(), 'warning');
    }

    // Step 5: Create customers table with authentication fields
    $customers_sql = "
    CREATE TABLE IF NOT EXISTS customers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        email_verified BOOLEAN DEFAULT FALSE,
        verification_code VARCHAR(100) NULL,
        reset_token VARCHAR(100) NULL,
        reset_token_expires TIMESTAMP NULL,
        remember_token VARCHAR(100) NULL,
        last_login TIMESTAMP NULL,
        login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL,
        preferences JSON NULL,
        address TEXT,
        date_of_birth DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    try {
        $pdo->exec($customers_sql);
        logOperation('✅ Created customers table with authentication fields');
    } catch (PDOException $e) {
        logOperation('⚠️ Customers table: ' . $e->getMessage(), 'warning');
    }

    // Step 6: Create authentication support tables
    $customer_sessions_sql = "
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
    )";

    try {
        $pdo->exec($customer_sessions_sql);
        logOperation('✅ Created customer_sessions table');
    } catch (PDOException $e) {
        logOperation('⚠️ Customer sessions table: ' . $e->getMessage(), 'warning');
    }

    $customer_login_logs_sql = "
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
    )";

    try {
        $pdo->exec($customer_login_logs_sql);
        logOperation('✅ Created customer_login_logs table');
    } catch (PDOException $e) {
        logOperation('⚠️ Customer login logs table: ' . $e->getMessage(), 'warning');
    }

    $customer_password_resets_sql = "
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
    )";

    try {
        $pdo->exec($customer_password_resets_sql);
        logOperation('✅ Created customer_password_resets table');
    } catch (PDOException $e) {
        logOperation('⚠️ Customer password resets table: ' . $e->getMessage(), 'warning');
    }

    // Step 7: Create sales table
    $sales_sql = "
    CREATE TABLE IF NOT EXISTS sales (
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
    )";

    try {
        $pdo->exec($sales_sql);
        logOperation('✅ Created sales table');
    } catch (PDOException $e) {
        logOperation('⚠️ Sales table: ' . $e->getMessage(), 'warning');
    }

    // Step 8: Create sale_items table
    $sale_items_sql = "
    CREATE TABLE IF NOT EXISTS sale_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sale_id INT NOT NULL,
        medicine_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (medicine_id) REFERENCES medicines(id)
    )";

    try {
        $pdo->exec($sale_items_sql);
        logOperation('✅ Created sale_items table');
    } catch (PDOException $e) {
        logOperation('⚠️ Sale items table: ' . $e->getMessage(), 'warning');
    }

    // Step 9: Insert sample data
    
    // Insert sample users
    $sample_users = [
        ['admin', 'admin@pharmacare.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'System Administrator'],
        ['pharmacist1', 'pharmacist@pharmacare.com', password_hash('pharm123', PASSWORD_DEFAULT), 'pharmacist', 'John Pharmacist'],
        ['cashier1', 'cashier@pharmacare.com', password_hash('cash123', PASSWORD_DEFAULT), 'cashier', 'Jane Cashier']
    ];

    $user_stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, role, full_name) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($sample_users as $user) {
        try {
            $user_stmt->execute($user);
            logOperation("✅ Inserted sample user: {$user[0]}");
        } catch (PDOException $e) {
            logOperation("⚠️ User {$user[0]}: " . $e->getMessage(), 'warning');
        }
    }

    // Insert sample customers
    $sample_customers = [
        ['John Doe', '+254700123456', 'john.doe@example.com', password_hash('password123', PASSWORD_DEFAULT), 1, '123 Main Street, Nairobi', '1990-05-15'],
        ['Jane Smith', '+254700654321', 'jane.smith@example.com', password_hash('password123', PASSWORD_DEFAULT), 1, '456 Oak Avenue, Mombasa', '1985-08-22'],
        ['Michael Johnson', '+254700987654', 'michael.johnson@example.com', password_hash('password123', PASSWORD_DEFAULT), 0, '789 Pine Road, Kisumu', '1992-12-08']
    ];

    $customer_stmt = $pdo->prepare("INSERT IGNORE INTO customers (name, phone, email, password, email_verified, address, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($sample_customers as $customer) {
        try {
            $customer_stmt->execute($customer);
            logOperation("✅ Inserted sample customer: {$customer[2]}");
        } catch (PDOException $e) {
            logOperation("⚠️ Customer {$customer[2]}: " . $e->getMessage(), 'warning');
        }
    }

    // Insert sample medicines
    $sample_medicines = [
        ['Paracetamol', 'Acetaminophen', 'Generic', 1, 1, 'TAB500', 5.00, 8.00, 3.00, 100, 10],
        ['Amoxicillin', 'Amoxicillin', 'Amoxil', 1, 1, 'CAP250', 15.00, 20.00, 10.00, 50, 5],
        ['Ibuprofen', 'Ibuprofen', 'Advil', 1, 1, 'TAB400', 8.00, 12.00, 5.00, 75, 8]
    ];

    $medicine_stmt = $pdo->prepare("INSERT IGNORE INTO medicines (name, generic_name, brand, category_id, supplier_id, barcode, unit_price, selling_price, cost_price, stock_quantity, min_stock_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($sample_medicines as $medicine) {
        try {
            $medicine_stmt->execute($medicine);
            logOperation("✅ Inserted sample medicine: {$medicine[0]}");
        } catch (PDOException $e) {
            logOperation("⚠️ Medicine {$medicine[0]}: " . $e->getMessage(), 'warning');
        }
    }

    if ($results['success']) {
        $results['message'] = '🎉 Database setup completed successfully! Your pharmacy management system is ready to use.';
        logOperation('🎉 Complete database setup finished successfully!', 'success');
    } else {
        $results['message'] = '⚠️ Database setup completed with some warnings. Please check the operations log.';
    }

} catch (Exception $e) {
    logError('Critical error during database setup: ' . $e->getMessage());
    $results['message'] = '❌ Database setup failed: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
