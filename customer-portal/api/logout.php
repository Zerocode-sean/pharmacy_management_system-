<?php
// Customer Logout API
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
    
    $token = isset($input['token']) ? $input['token'] : null;
    $customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : null;
    
    if ($token) {
        // Remove specific session by token
        $stmt = $db->prepare("DELETE FROM customer_sessions WHERE id = ?");
        $stmt->execute([$token]);
        $sessionsRemoved = $stmt->rowCount();
    } elseif ($customerId) {
        // Remove all sessions for customer (logout from all devices)
        $stmt = $db->prepare("DELETE FROM customer_sessions WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $sessionsRemoved = $stmt->rowCount();
    } else {
        throw new Exception('Token or customer_id required for logout');
    }
    
    // Clear remember token if provided
    if ($customerId) {
        $stmt = $db->prepare("UPDATE customers SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$customerId]);
    }
    
    // Clean up expired sessions (housekeeping)
    $cleanupStmt = $db->prepare("DELETE FROM customer_sessions WHERE expires_at < NOW()");
    $cleanupStmt->execute();
    $expiredSessionsRemoved = $cleanupStmt->rowCount();
    
    $response = [
        'success' => true,
        'message' => 'Logged out successfully',
        'sessions_removed' => $sessionsRemoved,
        'expired_sessions_cleaned' => $expiredSessionsRemoved,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
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
