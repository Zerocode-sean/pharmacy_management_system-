<?php
// Customer Login API
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
    if (empty($input['email']) || empty($input['password'])) {
        throw new Exception('Email and password are required');
    }
    
    $email = trim(strtolower($input['email']));
    $password = $input['password'];
    $remember = isset($input['remember']) ? (bool)$input['remember'] : false;
    
    // Get client info for logging
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Find customer by email
    $stmt = $db->prepare("
        SELECT id, name, email, phone, password, email_verified, 
               login_attempts, locked_until, last_login, created_at
        FROM customers 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        // Log failed login attempt (if table exists)
        try {
            $logStmt = $db->prepare("
                INSERT INTO customer_login_logs (
                    customer_id, email, ip_address, user_agent, 
                    login_successful, failure_reason, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([null, $email, $clientIp, $userAgent, false, 'Email not found']);
        } catch (PDOException $e) {
            // Silently continue if logging table doesn't exist
            error_log('Login logging failed: ' . $e->getMessage());
        }
        
        throw new Exception('Invalid email or password');
    }
    
    // Check if account is locked
    if ($customer['locked_until'] && new DateTime() < new DateTime($customer['locked_until'])) {
        $lockExpires = new DateTime($customer['locked_until']);
        throw new Exception('Account is locked due to multiple failed login attempts. Try again after ' . $lockExpires->format('Y-m-d H:i:s'));
    }
    
    // Verify password
    if (!password_verify($password, $customer['password'])) {
        // Increment login attempts
        $attempts = $customer['login_attempts'] + 1;
        $lockUntil = null;
        
        // Lock account after 5 failed attempts for 30 minutes
        if ($attempts >= 5) {
            $lockUntil = (new DateTime())->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s');
        }
        
        $updateStmt = $db->prepare("
            UPDATE customers 
            SET login_attempts = ?, locked_until = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([$attempts, $lockUntil, $customer['id']]);
        
        // Log failed login attempt (if table exists)
        try {
            $logStmt = $db->prepare("
                INSERT INTO customer_login_logs (
                    customer_id, email, ip_address, user_agent, 
                    login_successful, failure_reason, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([$customer['id'], $email, $clientIp, $userAgent, false, 'Invalid password']);
        } catch (PDOException $e) {
            // Silently continue if logging table doesn't exist
            error_log('Login logging failed: ' . $e->getMessage());
        }
        
        if ($lockUntil) {
            throw new Exception('Too many failed login attempts. Account has been locked for 30 minutes.');
        } else {
            $remainingAttempts = 5 - $attempts;
            throw new Exception("Invalid email or password. $remainingAttempts attempts remaining.");
        }
    }
    
    // Check if email is verified
    if (!$customer['email_verified']) {
        throw new Exception('Please verify your email address before logging in. Check your inbox for verification email.');
    }
    
    // Generate session token
    $token = bin2hex(random_bytes(32));
    $rememberToken = $remember ? bin2hex(random_bytes(32)) : null;
    
    // Update customer login info
    $updateStmt = $db->prepare("
        UPDATE customers 
        SET login_attempts = 0, locked_until = NULL, last_login = NOW(), 
            remember_token = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$rememberToken, $customer['id']]);
    
    // Create session record
    $sessionExpires = $remember ? 
        (new DateTime())->add(new DateInterval('P30D')) : // 30 days for remember me
        (new DateTime())->add(new DateInterval('PT8H'));   // 8 hours for regular session
    
    // Create session (if table exists)
    try {
        $sessionStmt = $db->prepare("
            INSERT INTO customer_sessions (
                id, customer_id, ip_address, user_agent, payload, 
                last_activity, expires_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $payload = json_encode([
            'customer_id' => $customer['id'],
            'email' => $customer['email'],
            'remember' => $remember,
            'created' => time()
        ]);
        
        $sessionStmt->execute([
            $token, 
            $customer['id'], 
            $clientIp, 
            $userAgent, 
            $payload, 
            time(), 
            $sessionExpires->format('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        // If session table doesn't exist, continue without session logging
        error_log('Session creation failed: ' . $e->getMessage());
    }
    
    // Log successful login (if table exists)
    try {
        $logStmt = $db->prepare("
            INSERT INTO customer_login_logs (
                customer_id, email, ip_address, user_agent, 
                login_successful, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$customer['id'], $email, $clientIp, $userAgent, true]);
    } catch (PDOException $e) {
        // Silently continue if logging table doesn't exist
        error_log('Login logging failed: ' . $e->getMessage());
    }
    
    // Parse customer name
    $nameParts = explode(' ', $customer['name'], 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Login successful! Welcome back.',
        'customer' => [
            'id' => $customer['id'],
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'email_verified' => (bool)$customer['email_verified'],
            'member_since' => $customer['created_at']
        ],
        'token' => $token,
        'remember_token' => $rememberToken,
        'session' => [
            'expires_at' => $sessionExpires->format('Y-m-d H:i:s'),
            'remember_me' => $remember
        ],
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
