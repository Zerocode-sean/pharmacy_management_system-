<?php
// Customer Registration API
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
    $required_fields = ['firstName', 'lastName', 'email', 'phone', 'password'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }
    
    // Sanitize and validate input
    $firstName = trim($input['firstName']);
    $lastName = trim($input['lastName']);
    $email = trim(strtolower($input['email']));
    $phone = trim($input['phone']);
    $password = $input['password'];
    $address = isset($input['address']) ? trim($input['address']) : '';
    $dateOfBirth = isset($input['dateOfBirth']) ? $input['dateOfBirth'] : null;
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        throw new Exception('Password must contain at least one uppercase letter, one lowercase letter, and one number');
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Email address is already registered');
    }
    
    // Check if phone already exists (optional validation)
    $stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
    $stmt->execute([$phone]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Phone number is already registered');
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate verification code
    $verificationCode = bin2hex(random_bytes(16));
    
    // Prepare full name
    $fullName = $firstName . ' ' . $lastName;
    
    // Check which column structure the table uses
    $stmt = $db->query("SHOW COLUMNS FROM customers LIKE 'first_name'");
    $hasFirstNameColumn = $stmt->rowCount() > 0;
    
    // Insert new customer with appropriate column structure
    if ($hasFirstNameColumn) {
        // New structure with first_name and last_name
        $stmt = $db->prepare("
            INSERT INTO customers (
                first_name, last_name, phone, email, password, email_verified, 
                verification_code, address, date_of_birth, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $emailVerified = false;
        
        $result = $stmt->execute([
            $firstName,
            $lastName,
            $phone, 
            $email, 
            $hashedPassword, 
            $emailVerified, 
            $verificationCode, 
            $address, 
            $dateOfBirth
        ]);
    } else {
        // Old structure with name column
        $stmt = $db->prepare("
            INSERT INTO customers (
                name, phone, email, password, email_verified, 
                verification_code, address, date_of_birth, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $emailVerified = false;
        
        $result = $stmt->execute([
            $fullName,
            $phone, 
            $email, 
            $hashedPassword, 
            $emailVerified, 
            $verificationCode, 
            $address, 
            $dateOfBirth
        ]);
    }
    
    if (!$result) {
        throw new Exception('Failed to create customer account');
    }
    
    $customerId = $db->lastInsertId();
    
    // Send verification email
    require_once 'email-service.php';
    
    $emailSent = false;
    $emailError = '';
    
    try {
        $emailSent = EmailService::sendVerificationEmail($email, $fullName, $verificationCode);
    } catch (Exception $emailException) {
        $emailError = $emailException->getMessage();
        error_log('Email sending failed: ' . $emailError);
    }
    
    // Log successful registration
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    try {
        $logStmt = $db->prepare("
            INSERT INTO customer_login_logs (
                customer_id, email, ip_address, user_agent, 
                login_successful, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$customerId, $email, $clientIp, $userAgent, true]);
    } catch (PDOException $e) {
        // Continue if logging fails
        error_log('Registration logging failed: ' . $e->getMessage());
    }
    
    // Return success response
    $response = [
        'success' => true,
        'message' => $emailSent ? 
            'Account created successfully! Please check your email for verification instructions.' : 
            'Account created successfully! Email verification will be sent shortly.',
        'customer' => [
            'id' => $customerId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'email_verified' => $emailVerified
        ],
        'verification' => [
            'required' => true,
            'code_sent' => $emailSent,
            'verification_code' => $verificationCode, // For testing - remove in production
            'message' => $emailSent ? 
                'Verification email sent! Please check your inbox and spam folder.' :
                'Account created but email sending failed. Please contact support.'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!$emailSent && $emailError) {
        $response['email_error'] = $emailError;
    }
    
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
