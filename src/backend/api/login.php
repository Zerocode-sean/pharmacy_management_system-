<?php
// Simple direct login - no Security/Database classes

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', 3);

// Use session_fix.php to ensure same session as APIs
require_once '../config/session_fix.php';
SessionCookieFixer::startSession();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'pharmacy_management');
    $mysqli->set_charset('utf8mb4');
    
    $input = json_decode(file_get_contents('php://input'), true);
    file_put_contents('C:\xampp\htdocs\Phamarcy\login_debug.txt', date('Y-m-d H:i:s') . " - Input decoded\n", FILE_APPEND);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit();
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password cannot be empty']);
        exit();
    }
    
    // Get user from database
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit();
    }
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $mysqli->close();
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'email' => $user['email']
        ],
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Login error: ' . $e->getMessage()
    ]);
}
?>
