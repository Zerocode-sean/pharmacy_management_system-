<?php
/**
 * Customer Password Reset Request API
 * Handles password reset requests and sends reset emails
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Disable error display for clean JSON
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once '../../src/backend/config/config.php';
    require_once '../../src/backend/config/database.php';
    require_once 'enhanced-email-service.php';
    
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
    
    if (!$input || empty($input['email'])) {
        throw new Exception('Email is required');
    }
    
    $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        throw new Exception('Invalid email format');
    }
    
    // Check if customer exists
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM customers WHERE email = ? AND deleted_at IS NULL");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        // For security, don't reveal if email exists or not
        echo json_encode([
            'success' => true,
            'message' => 'If an account with this email exists, a password reset link has been sent.',
            'timestamp' => date('Y-m-d H:i:s'),
            'debug' => [
                'email_checked' => $email,
                'action' => 'password_reset_requested'
            ]
        ]);
        exit();
    }
    
    // Check for recent reset requests to prevent spam
    $stmt = $db->prepare("
        SELECT COUNT(*) as recent_requests 
        FROM customer_password_resets 
        WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND used_at IS NULL
    ");
    $stmt->execute([$email]);
    $recent_count = $stmt->fetchColumn();
    
    if ($recent_count > 2) {
        throw new Exception('Too many reset requests. Please wait 15 minutes before trying again.');
    }
    
    // Generate secure reset token
    $reset_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Store reset token in database
    $stmt = $db->prepare("
        INSERT INTO customer_password_resets (email, token, expires_at, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$email, $reset_token, $expires_at, $ip_address]);
    
    // Generate reset link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_path = dirname(dirname($_SERVER['REQUEST_URI']));
    $reset_link = $protocol . '://' . $host . $base_path . '/reset-password.html?token=' . $reset_token;
    
    // Send reset email
    $email_sent = EnhancedEmailService::sendPasswordResetEmail(
        $email,
        $customer['first_name'] . ' ' . $customer['last_name'],
        $reset_link,
        $reset_token
    );
    
    if (!$email_sent) {
        throw new Exception('Failed to send password reset email. Please try again later.');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset instructions have been sent to your email.',
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'email' => $email,
            'token_expires' => $expires_at,
            'reset_link' => $reset_link, // Remove in production
            'email_sent' => true
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'error_type' => 'password_reset_error',
            'file' => basename(__FILE__)
        ]
    ]);
}
?>
