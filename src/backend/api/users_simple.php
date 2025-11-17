<?php
// Simplified users API for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

try {
    // Debug information
    $debug = [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD']
    ];
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Not authenticated',
            'debug' => $debug
        ]);
        exit();
    }
    
    // Check role (allow pharmacist and admin to view users)
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, ['pharmacist', 'admin'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Insufficient permissions. Pharmacist or Admin role required.',
            'debug' => $debug
        ]);
        exit();
    }
    
    $database = new Database();
    $db = $database->connect();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Simple query without login log for now
        $query = "
            SELECT 
                id,
                username,
                email,
                full_name,
                role,
                phone,
                hire_date,
                birth_date,
                department,
                employee_id,
                is_active,
                created_at,
                updated_at
            FROM users 
            ORDER BY created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert is_active to boolean
        foreach ($users as &$user) {
            $user['is_active'] = (bool)$user['is_active'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $users,
            'count' => count($users),
            'debug' => $debug
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false, 
            'message' => 'Method not allowed',
            'debug' => $debug
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => $debug ?? []
    ]);
}
?>
