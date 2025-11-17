<?php
/**
 * Session Check API - Simple authentication check
 * Returns authentication status and user info
 */

require_once '../config/config.php';
require_once '../config/session_fix.php';

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Start session
SessionCookieFixer::startSession();

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
    exit();
}

// Check session timeout
$lastActivity = $_SESSION['last_activity'] ?? 0;
$sessionTimeout = 3600; // 1 hour
if (time() - $lastActivity > $sessionTimeout) {
    // Session expired
    session_destroy();
    echo json_encode([
        'authenticated' => false,
        'message' => 'Session expired'
    ]);
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Return user info
echo json_encode([
    'authenticated' => true,
    'user' => [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email']
    ]
]);
?>