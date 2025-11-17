<?php
/**
 * Customer Password Reset Confirmation API
 * Handles password reset using reset tokens
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
    $required_fields = ['token', 'password'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }
    
    $token = trim($input['token']);
    $new_password = $input['password'];
    
    // Validate password strength
    if (strlen($new_password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
        throw new Exception('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character');
    }
    
    // Find and validate reset token
    $stmt = $db->prepare("
        SELECT pr.*, c.id as customer_id, c.email, c.first_name, c.last_name
        FROM customer_password_resets pr
        INNER JOIN customers c ON pr.email = c.email
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL AND c.deleted_at IS NULL
    ");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();
    
    if (!$reset_request) {
        throw new Exception('Invalid or expired reset token');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update customer password
        $stmt = $db->prepare("UPDATE customers SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$password_hash, $reset_request['customer_id']]);
        
        // Mark reset token as used
        $stmt = $db->prepare("UPDATE customer_password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$reset_request['id']]);
        
        // Invalidate all existing sessions for this customer
        $stmt = $db->prepare("DELETE FROM customer_sessions WHERE customer_id = ?");
        $stmt->execute([$reset_request['customer_id']]);
        
        // Log the password change
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $db->prepare("
            INSERT INTO customer_login_logs (customer_id, email, ip_address, user_agent, login_successful, failure_reason) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $reset_request['customer_id'],
            $reset_request['email'],
            $ip_address,
            $user_agent,
            true,
            'Password reset successful'
        ]);
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully. Please log in with your new password.',
            'timestamp' => date('Y-m-d H:i:s'),
            'debug' => [
                'customer_id' => $reset_request['customer_id'],
                'email' => $reset_request['email'],
                'reset_completed' => true,
                'sessions_invalidated' => true
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'error_type' => 'password_reset_confirmation_error',
            'file' => basename(__FILE__)
        ]
    ]);
}
?>
