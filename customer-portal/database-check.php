<?php
/**
 * Database Schema Check - Customer Authentication Backend
 * This script checks the current database schema and verifies if 
 * customer authentication features are ready
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getTablePurpose($table) {
    $purposes = [
        'customer_sessions' => 'Manages customer login sessions and security',
        'customer_login_logs' => 'Tracks login attempts and security events',
        'customer_password_resets' => 'Handles password reset token management'
    ];
    
    return $purposes[$table] ?? 'Authentication support table';
}

try {
    // Include database configuration
    require_once '../src/backend/config/config.php';
    require_once '../src/backend/config/database.php';
    
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $results = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'database_name' => DB_NAME,
        'connection_status' => 'Connected',
        'tables_analysis' => [],
        'customers_table' => null,
        'authentication_ready' => false,
        'missing_fields' => [],
        'recommendations' => []
    ];
    
    // Check if customers table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'customers'");
    $stmt->execute();
    $customersExists = $stmt->rowCount() > 0;
    
    if ($customersExists) {
        // Get customers table structure
        $stmt = $db->prepare("DESCRIBE customers");
        $stmt->execute();
        $customersStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get customer count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM customers");
        $stmt->execute();
        $customerCount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get sample customers (without passwords for security)
        $stmt = $db->prepare("SELECT id, name, email, phone, email_verified, created_at FROM customers LIMIT 5");
        $stmt->execute();
        $sampleCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results['customers_table'] = [
            'exists' => true,
            'structure' => $customersStructure,
            'record_count' => $customerCount['total'],
            'sample_data' => $sampleCustomers
        ];
        
        // Check for authentication fields
        $existingFields = array_column($customersStructure, 'Field');
        $requiredAuthFields = [
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
        
        $missingFields = array_diff($requiredAuthFields, $existingFields);
        $results['missing_fields'] = $missingFields;
        $results['authentication_ready'] = empty($missingFields);
        
        if (!empty($missingFields)) {
            $results['recommendations'][] = "Customer authentication fields are missing. Run the customer_auth_schema.sql script to add authentication capabilities.";
            $results['recommendations'][] = "Missing fields: " . implode(', ', $missingFields);
        }
        
    } else {
        $results['customers_table'] = [
            'exists' => false,
            'message' => 'Customers table does not exist'
        ];
        $results['recommendations'][] = "Customers table not found. Please run the main schema.sql first.";
    }
    
    // Check for authentication support tables
    $authTables = [
        'customer_sessions',
        'customer_login_logs',
        'customer_password_resets'
    ];
    
    foreach ($authTables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->rowCount() > 0;
        
        $results['tables_analysis'][$table] = [
            'exists' => $exists,
            'purpose' => getTablePurpose($table)
        ];
        
        if (!$exists) {
            $results['recommendations'][] = "Authentication support table '{$table}' is missing.";
        }
    }
    
    // Overall assessment
    if ($results['authentication_ready'] && 
        isset($results['tables_analysis']['customer_sessions']) && $results['tables_analysis']['customer_sessions']['exists'] &&
        isset($results['tables_analysis']['customer_login_logs']) && $results['tables_analysis']['customer_login_logs']['exists'] &&
        isset($results['tables_analysis']['customer_password_resets']) && $results['tables_analysis']['customer_password_resets']['exists']) {
        
        $results['overall_status'] = 'READY';
        $results['message'] = 'Database is ready for customer authentication and registration.';
    } else {
        $results['overall_status'] = 'NEEDS_UPDATE';
        $results['message'] = 'Database schema needs updates for customer authentication.';
        $results['next_steps'] = [
            'Run customer_auth_schema.sql to add missing fields and tables',
            'Test database connectivity with the update-database.php script',
            'Verify sample customer data is inserted correctly'
        ];
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database check failed',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
