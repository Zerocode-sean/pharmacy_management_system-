<?php
/**
 * Resend Verification Email API
 * Generates new verification code and sends email
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
    require_once 'email-service.php';
    
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
    if (empty($input['email'])) {
        throw new Exception('Email is required');
    }
    
    $email = trim(strtolower($input['email']));
    
    // Find customer by email
    $stmt = $db->prepare("
        SELECT id, name, email, email_verified, created_at
        FROM customers 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        throw new Exception('Email address not found. Please register first.');
    }
    
    // Check if already verified
    if ($customer['email_verified']) {
        throw new Exception('Email is already verified. You can log in now.');
    }
    
    // Check rate limiting (max 3 resends per hour)
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    try {
        $rateLimitStmt = $db->prepare("
            SELECT COUNT(*) as resend_count 
            FROM customer_login_logs 
            WHERE email = ? AND failure_reason = 'Verification resend' AND created_at > ?
        ");
        $rateLimitStmt->execute([$email, $oneHourAgo]);
        $rateLimitData = $rateLimitStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rateLimitData['resend_count'] >= 3) {
            throw new Exception('Too many verification emails sent. Please wait an hour before requesting another.');
        }
    } catch (PDOException $e) {
        // Continue if rate limiting check fails
        error_log('Rate limit check failed: ' . $e->getMessage());
    }
    
    // Generate new verification code
    $newVerificationCode = bin2hex(random_bytes(16));
    
    // Update customer with new verification code
    $updateStmt = $db->prepare("
        UPDATE customers 
        SET verification_code = ? 
        WHERE id = ?
    ");
    $result = $updateStmt->execute([$newVerificationCode, $customer['id']]);
    
    if (!$result) {
        throw new Exception('Failed to update verification code');
    }
    
    // Send verification email
    $emailSent = false;
    $emailError = '';
    
    try {
        $emailSent = EmailService::sendVerificationEmail($email, $customer['name'], $newVerificationCode);
    } catch (Exception $emailException) {
        $emailError = $emailException->getMessage();
        error_log('Email sending failed: ' . $emailError);
    }
    
    // Log resend attempt
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    try {
        $logStmt = $db->prepare("
            INSERT INTO customer_login_logs (
                customer_id, email, ip_address, user_agent, 
                login_successful, failure_reason, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$customer['id'], $email, $clientIp, $userAgent, false, 'Verification resend']);
    } catch (PDOException $e) {
        // Continue if logging fails
        error_log('Resend logging failed: ' . $e->getMessage());
    }
    
    // Response
    $response = [
        'success' => true,
        'message' => $emailSent ? 
            'New verification email sent! Please check your inbox and spam folder.' : 
            'Verification code updated but email sending failed.',
        'email_sent' => $emailSent,
        'verification_code' => $newVerificationCode, // For testing - remove in production
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!$emailSent && $emailError) {
        $response['email_error'] = $emailError;
        $response['message'] .= ' Error: ' . $emailError;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
