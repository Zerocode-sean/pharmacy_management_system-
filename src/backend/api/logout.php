<?php
/**
 * Logout API Endpoint
 * Handles secure user logout
 */

require_once '../config/config.php';
require_once '../config/security.php';
require_once '../config/cors.php';

// Initialize CORS with proper headers
CORSHelper::init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Set session name to match the application
    session_name('PHARMACY_SESSION');
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'message' => 'User not logged in']);
        exit();
    }
    
    // Log logout event
    Security::logSecurityEvent('LOGOUT', 'Username: ' . ($_SESSION['username'] ?? 'Unknown'));
    
    // Clear all session variables
    session_unset();
    
    // Destroy the session
    session_destroy();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Logout failed: ' . $e->getMessage()
    ]);
}
?>
