<?php
/**
 * Password Change API Endpoint
 * Handles secure password changes with validation
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/auth_middleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check authentication
AuthMiddleware::checkAuth(true);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['current_password']) || empty($input['new_password']) || empty($input['confirm_password'])) {
        throw new Exception('All password fields are required');
    }
    
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];
    $confirmPassword = $input['confirm_password'];
    
    // Check if new passwords match
    if ($newPassword !== $confirmPassword) {
        throw new Exception('New passwords do not match');
    }
    
    // Validate new password strength
    $passwordErrors = Security::validatePasswordStrength($newPassword);
    if (!empty($passwordErrors)) {
        throw new Exception('Password requirements not met: ' . implode(', ', $passwordErrors));
    }
    
    // Database connection
    $database = new Database();
    $conn = $database->connect();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Get current user's password
    $query = "SELECT password FROM users WHERE id = :user_id AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Verify current password
    if (!Security::verifyPassword($currentPassword, $user['password'])) {
        Security::logSecurityEvent('PASSWORD_CHANGE_FAILED', 'Invalid current password - User ID: ' . $_SESSION['user_id']);
        throw new Exception('Current password is incorrect');
    }
    
    // Hash new password
    $hashedPassword = Security::hashPassword($newPassword);
    
    // Update password in database
    $updateQuery = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':user_id', $_SESSION['user_id']);
    
    if ($updateStmt->execute()) {
        Security::logSecurityEvent('PASSWORD_CHANGED', 'User ID: ' . $_SESSION['user_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    } else {
        throw new Exception('Failed to update password');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    Security::logSecurityEvent('PASSWORD_CHANGE_ERROR', 'Database error - User ID: ' . $_SESSION['user_id']);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
