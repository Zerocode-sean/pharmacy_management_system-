<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Only allow POST requests for actual operations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Database configuration
$host = 'localhost';
$dbname = 'pharmacy_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get request data
$rawInput = file_get_contents('php://input');
error_log("Raw input: " . $rawInput);

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

$action = $input['action'] ?? '';
error_log("Action: " . $action);

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

switch ($action) {
    case 'send_reset':
        handleSendReset($pdo, $input);
        break;
    case 'verify_code':
        handleVerifyCode($pdo, $input);
        break;
    case 'reset_password':
        handleResetPassword($pdo, $input);
        break;
    case 'resend_code':
        handleResendCode($pdo, $input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleSendReset($pdo, $input) {
    $identifier = trim($input['identifier'] ?? '');
    
    if (empty($identifier)) {
        echo json_encode(['success' => false, 'message' => 'Username or email is required']);
        return;
    }
    
    try {
        // Check if user exists by username or email
        $stmt = $pdo->prepare("SELECT id, username, email, full_name FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Don't reveal if user exists or not for security
            echo json_encode(['success' => true, 'message' => 'If the account exists, reset instructions have been sent']);
            return;
        }
        
        // Generate verification code and token
        $verificationCode = sprintf('%06d', mt_rand(0, 999999));
        $resetToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
        
        // Store reset request in database
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (user_id, email, verification_code, reset_token, expires_at, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                verification_code = VALUES(verification_code),
                reset_token = VALUES(reset_token),
                expires_at = VALUES(expires_at),
                created_at = NOW(),
                verified = 0
        ");
        
        $stmt->execute([
            $user['id'],
            $user['email'] ?: $user['username'] . '@pharmacy.local',
            $verificationCode,
            $resetToken,
            $expiresAt
        ]);
        
        // In a real application, send email here
        // For now, we'll log the code (in production, remove this)
        error_log("Password reset code for {$user['username']}: $verificationCode");
        
        // Store verification info temporarily (for demo purposes)
        file_put_contents(
            sys_get_temp_dir() . "/reset_code_{$user['id']}.txt", 
            json_encode([
                'code' => $verificationCode,
                'identifier' => $identifier,
                'timestamp' => time()
            ])
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Reset instructions sent successfully',
            'debug_code' => $verificationCode // Remove in production
        ]);
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again']);
    }
}

function handleVerifyCode($pdo, $input) {
    $identifier = trim($input['identifier'] ?? '');
    $code = trim($input['code'] ?? '');
    
    if (empty($identifier) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Identifier and code are required']);
        return;
    }
    
    try {
        // Find user and reset request
        $stmt = $pdo->prepare("
            SELECT pr.*, u.username, u.email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE (u.username = ? OR u.email = ?) 
            AND pr.verification_code = ?
            AND pr.expires_at > NOW()
            AND pr.verified = 0
            ORDER BY pr.created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$identifier, $identifier, $code]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
            return;
        }
        
        // Mark as verified
        $stmt = $pdo->prepare("UPDATE password_resets SET verified = 1 WHERE id = ?");
        $stmt->execute([$reset['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Code verified successfully',
            'reset_token' => $reset['reset_token']
        ]);
        
    } catch (Exception $e) {
        error_log("Code verification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again']);
    }
}

function handleResetPassword($pdo, $input) {
    $identifier = trim($input['identifier'] ?? '');
    $resetToken = trim($input['reset_token'] ?? '');
    $newPassword = $input['new_password'] ?? '';
    
    if (empty($identifier) || empty($resetToken) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        return;
    }
    
    try {
        // Find valid reset request
        $stmt = $pdo->prepare("
            SELECT pr.*, u.id as user_id, u.username 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE (u.username = ? OR u.email = ?)
            AND pr.reset_token = ?
            AND pr.verified = 1
            AND pr.expires_at > NOW()
            ORDER BY pr.created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$identifier, $identifier, $resetToken]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
            return;
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $reset['user_id']]);
        
        // Clean up reset request
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$reset['user_id']]);
        
        // Clean up temporary file
        $tempFile = sys_get_temp_dir() . "/reset_code_{$reset['user_id']}.txt";
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again']);
    }
}

function handleResendCode($pdo, $input) {
    $identifier = trim($input['identifier'] ?? '');
    
    if (empty($identifier)) {
        echo json_encode(['success' => false, 'message' => 'Identifier is required']);
        return;
    }
    
    try {
        // Find user
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => true, 'message' => 'If the account exists, a new code has been sent']);
            return;
        }
        
        // Check if can resend (prevent spam)
        $stmt = $pdo->prepare("
            SELECT created_at FROM password_resets 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $lastReset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastReset && (time() - strtotime($lastReset['created_at'])) < 60) {
            echo json_encode(['success' => false, 'message' => 'Please wait before requesting another code']);
            return;
        }
        
        // Generate new code
        $verificationCode = sprintf('%06d', mt_rand(0, 999999));
        $resetToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        
        // Update reset request
        $stmt = $pdo->prepare("
            UPDATE password_resets 
            SET verification_code = ?, reset_token = ?, expires_at = ?, created_at = NOW(), verified = 0
            WHERE user_id = ?
        ");
        $stmt->execute([$verificationCode, $resetToken, $expiresAt, $user['id']]);
        
        // Store verification info temporarily (for demo)
        file_put_contents(
            sys_get_temp_dir() . "/reset_code_{$user['id']}.txt", 
            json_encode([
                'code' => $verificationCode,
                'identifier' => $identifier,
                'timestamp' => time()
            ])
        );
        
        error_log("New password reset code for {$user['username']}: $verificationCode");
        
        echo json_encode([
            'success' => true,
            'message' => 'New verification code sent',
            'debug_code' => $verificationCode // Remove in production
        ]);
        
    } catch (Exception $e) {
        error_log("Resend code error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again']);
    }
}

// Create password_resets table if it doesn't exist
function createPasswordResetsTable($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                verification_code VARCHAR(6) NOT NULL,
                reset_token VARCHAR(64) NOT NULL,
                verified TINYINT(1) DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_token (reset_token),
                INDEX idx_expires (expires_at)
            )
        ");
    } catch (Exception $e) {
        error_log("Error creating password_resets table: " . $e->getMessage());
    }
}

// Create table on first run
createPasswordResetsTable($pdo);
?>
