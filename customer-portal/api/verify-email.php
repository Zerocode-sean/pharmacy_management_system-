<?php
/**
 * Email Verification API
 * Verifies customer email addresses using verification codes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../../src/backend/config/config.php';
    require_once '../../src/backend/config/database.php';
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (empty($input['email']) || empty($input['code'])) {
        throw new Exception('Email and verification code are required');
    }
    
    $email = trim(strtolower($input['email']));
    $code = trim($input['code']);
    
    // Find customer by email and verification code
    $stmt = $db->prepare("
        SELECT id, name, email, verification_code, email_verified, created_at
        FROM customers 
        WHERE email = ? AND verification_code = ?
    ");
    $stmt->execute([$email, $code]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        // Check if email exists but code is wrong
        $emailStmt = $db->prepare("SELECT id, email_verified FROM customers WHERE email = ?");
        $emailStmt->execute([$email]);
        $emailExists = $emailStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($emailExists) {
            if ($emailExists['email_verified']) {
                throw new Exception('Email is already verified. You can log in now.');
            } else {
                throw new Exception('Invalid verification code. Please check your email for the correct code.');
            }
        } else {
            throw new Exception('Email address not found. Please register first.');
        }
    }
    
    // Check if already verified
    if ($customer['email_verified']) {
        echo json_encode([
            'success' => true,
            'message' => 'Email is already verified. You can log in now.',
            'customer' => [
                'id' => $customer['id'],
                'email' => $customer['email'],
                'name' => $customer['name']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Check verification code age (expire after 24 hours)
    $createdTime = new DateTime($customer['created_at']);
    $now = new DateTime();
    $hoursSinceCreation = $now->diff($createdTime)->h + ($now->diff($createdTime)->days * 24);
    
    if ($hoursSinceCreation > 24) {
        throw new Exception('Verification code has expired. Please request a new one.');
    }
    
    // Update customer as verified and clear verification code
    $updateStmt = $db->prepare("
        UPDATE customers 
        SET email_verified = 1, verification_code = NULL 
        WHERE id = ?
    ");
    $result = $updateStmt->execute([$customer['id']]);
    
    if (!$result) {
        throw new Exception('Failed to update verification status');
    }
    
    // Log successful verification
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    try {
        $logStmt = $db->prepare("
            INSERT INTO customer_login_logs (
                customer_id, email, ip_address, user_agent, 
                login_successful, failure_reason, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$customer['id'], $email, $clientIp, $userAgent, true, 'Email verification']);
    } catch (PDOException $e) {
        // Continue if logging fails
        error_log('Verification logging failed: ' . $e->getMessage());
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully! You can now log in to your account.',
        'customer' => [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'name' => $customer['name'],
            'verified_at' => date('Y-m-d H:i:s')
        ],
        'next_steps' => [
            'action' => 'redirect_to_login',
            'message' => 'Please proceed to login with your email and password.'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
