<?php
/**
 * Session Status Check API
 * Provides detailed session information for debugging
 */

// Suppress all notices and warnings for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/session_fix.php';

// Set CORS headers with improved origin detection
$origin = '';
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
} elseif (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $origin = $protocol . '://' . $_SERVER['HTTP_HOST'];
} else {
    $origin = 'http://localhost';
}

$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:80',
    'http://localhost:3000',
    'https://localhost',
    'https://127.0.0.1',
];

// More flexible origin matching for local development
$allowOrigin = 'http://localhost'; // Default fallback
foreach ($allowedOrigins as $allowed) {
    if (strpos($origin, $allowed) === 0 || $origin === $allowed) {
        $allowOrigin = $origin;
        break;
    }
}

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: $allowOrigin");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Start session with proper localhost configuration
SessionCookieFixer::startSession();

// Collect session information
$sessionData = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_cookie_params' => session_get_cookie_params(),
    'session_save_path' => session_save_path(),
    'session_module_name' => session_module_name(),
    'php_session_constants' => [
        'PHP_SESSION_DISABLED' => PHP_SESSION_DISABLED,
        'PHP_SESSION_NONE' => PHP_SESSION_NONE,
        'PHP_SESSION_ACTIVE' => PHP_SESSION_ACTIVE
    ]
];

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$sessionValid = false;

if ($isLoggedIn) {
    // Check session timeout
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $sessionTimeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
    $timeSinceLastActivity = time() - $lastActivity;
    $sessionValid = $timeSinceLastActivity < $sessionTimeout;
    
    if ($sessionValid) {
        // Update last activity
        $_SESSION['last_activity'] = time();
    }
}

// Collect all session variables (for debugging)
$sessionVariables = [];
foreach ($_SESSION as $key => $value) {
    if ($key === 'csrf_token') {
        $sessionVariables[$key] = '[HIDDEN]';
    } else {
        $sessionVariables[$key] = $value;
    }
}

// Response data
$responseData = [
    'success' => true,
    'logged_in' => $isLoggedIn,
    'session_valid' => $sessionValid,
    'session_data' => $sessionData,
    'session_variables' => $sessionVariables,
    'server_time' => time(),
    'server_datetime' => date('Y-m-d H:i:s'),
    'request_info' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'origin' => $origin,
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'Unknown'
    ]
];

if ($isLoggedIn) {
    $responseData['user_info'] = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'time_since_login' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : null,
        'time_since_activity' => isset($_SESSION['last_activity']) ? (time() - $_SESSION['last_activity']) : null
    ];
}

// Add cookie information
$responseData['cookies'] = [
    'all_cookies' => $_COOKIE,
    'session_cookie_exists' => isset($_COOKIE[session_name()]),
    'session_cookie_value' => isset($_COOKIE[session_name()]) ? '[EXISTS]' : '[NOT SET]'
];

echo json_encode($responseData, JSON_PRETTY_PRINT);
?>
