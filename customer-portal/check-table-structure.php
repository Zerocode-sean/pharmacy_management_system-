<?php
/**
 * Quick Database Table Structure Check
 * This will show us exactly what columns exist in the customers table
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $host = 'localhost';
    $dbname = 'pharmacy_management';
    $username = 'root';
    $password = '';
    $charset = 'utf8mb4';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CUSTOMERS TABLE STRUCTURE ===\n\n";
    
    // Get table structure
    $stmt = $pdo->prepare("DESCRIBE customers");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current columns in customers table:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Default']}\n";
    }
    
    echo "\n=== MISSING AUTHENTICATION COLUMNS ===\n\n";
    
    $current_fields = array_column($columns, 'Field');
    $required_auth_fields = [
        'password',
        'email_verified', 
        'verification_code',
        'reset_token',
        'reset_token_expires',
        'remember_token',
        'last_login',
        'login_attempts',
        'locked_until'
    ];
    
    $missing_fields = array_diff($required_auth_fields, $current_fields);
    
    if (empty($missing_fields)) {
        echo "✅ All authentication fields are present!\n";
    } else {
        echo "❌ Missing fields:\n";
        foreach ($missing_fields as $field) {
            echo "- $field\n";
        }
    }
    
    echo "\n=== FIX COMMANDS ===\n\n";
    
    if (!empty($missing_fields)) {
        echo "Run these SQL commands to fix:\n\n";
        
        $alter_commands = [
            'password' => "ALTER TABLE customers ADD COLUMN password VARCHAR(255) AFTER email",
            'email_verified' => "ALTER TABLE customers ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER password",
            'verification_code' => "ALTER TABLE customers ADD COLUMN verification_code VARCHAR(100) NULL AFTER email_verified",
            'reset_token' => "ALTER TABLE customers ADD COLUMN reset_token VARCHAR(100) NULL AFTER verification_code",
            'reset_token_expires' => "ALTER TABLE customers ADD COLUMN reset_token_expires TIMESTAMP NULL AFTER reset_token",
            'remember_token' => "ALTER TABLE customers ADD COLUMN remember_token VARCHAR(100) NULL AFTER reset_token_expires",
            'last_login' => "ALTER TABLE customers ADD COLUMN last_login TIMESTAMP NULL AFTER remember_token",
            'login_attempts' => "ALTER TABLE customers ADD COLUMN login_attempts INT DEFAULT 0 AFTER last_login",
            'locked_until' => "ALTER TABLE customers ADD COLUMN locked_until TIMESTAMP NULL AFTER login_attempts"
        ];
        
        foreach ($missing_fields as $field) {
            if (isset($alter_commands[$field])) {
                echo $alter_commands[$field] . ";\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
