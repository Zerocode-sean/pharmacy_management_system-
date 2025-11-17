<?php
/**
 * Auth API - Handle authentication queries
 * Supports: ?action=me (check current session)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
$origin = '';
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
} elseif (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $origin = $protocol . '://' . $_SERVER['HTTP_HOST'];
} else {
    $origin = 'http://localhost';
}

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Start session with consistent settings
require_once __DIR__ . '/../config/session_fix.php';
SessionCookieFixer::startSession();

// Get action from query string
$action = $_GET['action'] ?? 'me';

/**
 * Action: me - Get current user session info
 */
if ($action === 'me') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
        exit();
    }
    
    // Return user info from session
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'last_activity' => $_SESSION['last_activity'] ?? time()
        ],
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'email' => $_SESSION['email'] ?? ''
        ]
    ]);
    exit();
}

/**
 * Action: check - Simple session check (returns boolean)
 */
if ($action === 'check') {
    echo json_encode([
        'success' => true,
        'authenticated' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null
    ]);
    exit();
}

/**
 * Action: logout - Destroy session
 */
if ($action === 'logout') {
    // Clear session data
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    exit();
}

// Unknown action
http_response_code(400);
echo json_encode([
    'success' => false,
    'message' => 'Unknown action: ' . $action
]);
