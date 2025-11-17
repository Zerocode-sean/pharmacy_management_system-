<?php
/**
 * Create Missing Authentication Support Tables
 * This script will create all the missing authentication support tables
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'operations' => [],
    'errors' => [],
    'success' => true
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
        $results['errors'][] = $message;
    }
}

try {
    // Connect to database
    $host = 'localhost';
    $dbname = 'pharmacy_management';
    $username = 'root';
    $password = '';
    $charset = 'utf8mb4';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    logOperation('✅ Connected to database successfully');
    
    // Check which tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    logOperation('📋 Existing tables: ' . implode(', ', $existing_tables));
    
    // Define authentication support tables
    $auth_tables = [
        'customer_sessions' => "
            CREATE TABLE customer_sessions (
                id VARCHAR(128) PRIMARY KEY,
                customer_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                payload TEXT NOT NULL,
                last_activity INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                INDEX idx_customer_sessions_customer (customer_id),
                INDEX idx_customer_sessions_last_activity (last_activity),
                INDEX idx_customer_sessions_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'customer_login_logs' => "
            CREATE TABLE customer_login_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                customer_id INT NULL,
                email VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                login_successful BOOLEAN NOT NULL,
                failure_reason VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_customer_login_logs_customer (customer_id),
                INDEX idx_customer_login_logs_email (email),
                INDEX idx_customer_login_logs_ip (ip_address),
                INDEX idx_customer_login_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'customer_password_resets' => "
            CREATE TABLE customer_password_resets (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "
    ];
    
    // Create missing tables
    $created_count = 0;
    $skipped_count = 0;
    
    foreach ($auth_tables as $table_name => $sql) {
        if (!in_array($table_name, $existing_tables)) {
            try {
                $pdo->exec($sql);
                logOperation("✅ Created table: $table_name");
                $created_count++;
            } catch (PDOException $e) {
                logOperation("❌ Failed to create $table_name: " . $e->getMessage(), 'error');
            }
        } else {
            logOperation("⏭️ Table already exists: $table_name", 'info');
            $skipped_count++;
        }
    }
    
    // Add foreign key constraints (after tables are created)
    $foreign_keys = [
        'customer_sessions' => "ALTER TABLE customer_sessions ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE",
        'customer_login_logs' => "ALTER TABLE customer_login_logs ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL"
    ];
    
    foreach ($foreign_keys as $table_name => $sql) {
        if (in_array($table_name, $existing_tables) || $created_count > 0) {
            try {
                $pdo->exec($sql);
                logOperation("✅ Added foreign key constraint to: $table_name");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate foreign key constraint') !== false) {
                    logOperation("⏭️ Foreign key already exists for: $table_name", 'info');
                } else {
                    logOperation("⚠️ Foreign key constraint for $table_name: " . $e->getMessage(), 'warning');
                }
            }
        }
    }
    
    // Verify all tables now exist
    $stmt = $pdo->query("SHOW TABLES");
    $final_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_tables = ['customers', 'customer_sessions', 'customer_login_logs', 'customer_password_resets'];
    $missing_tables = array_diff($required_tables, $final_tables);
    
    if (empty($missing_tables)) {
        logOperation("✅ All required authentication tables are now present");
    } else {
        logOperation("❌ Still missing tables: " . implode(', ', $missing_tables), 'error');
    }
    
    // Test insert into customer_login_logs to verify it works
    try {
        $stmt = $pdo->prepare("INSERT INTO customer_login_logs (customer_id, email, ip_address, login_successful, failure_reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([null, 'test@example.com', '127.0.0.1', false, 'Test log entry']);
        
        // Delete the test entry
        $pdo->exec("DELETE FROM customer_login_logs WHERE email = 'test@example.com'");
        
        logOperation("✅ customer_login_logs table is working correctly");
    } catch (PDOException $e) {
        logOperation("❌ customer_login_logs test failed: " . $e->getMessage(), 'error');
    }
    
    $results['summary'] = [
        'tables_created' => $created_count,
        'tables_skipped' => $skipped_count,
        'total_tables' => count($final_tables),
        'final_tables' => $final_tables
    ];
    
    if ($results['success']) {
        $results['message'] = "🎉 Authentication support tables setup complete! Created $created_count new tables.";
        logOperation('🎉 All authentication support tables are ready!');
    } else {
        $results['message'] = "⚠️ Setup completed with some errors. Check operations log.";
    }
    
} catch (Exception $e) {
    logOperation("❌ Critical error: " . $e->getMessage(), 'error');
    $results['message'] = "❌ Setup failed: " . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
