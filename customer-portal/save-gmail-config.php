<?php
/**
 * Save Gmail Configuration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $required = ['username', 'app_password', 'from_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate email
    if (!filter_var($input['username'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Validate app password (should be 16 characters without spaces)
    $cleanPassword = str_replace(' ', '', $input['app_password']);
    if (strlen($cleanPassword) !== 16) {
        throw new Exception('App password should be 16 characters (remove spaces)');
    }
    
    // Create configuration content
    $configContent = '<?php
/**
 * Gmail SMTP Configuration - Auto-generated
 * Generated on: ' . date('Y-m-d H:i:s') . '
 */

// Gmail SMTP Settings
define("GMAIL_USERNAME", "' . addslashes($input['username']) . '");
define("GMAIL_APP_PASSWORD", "' . addslashes($cleanPassword) . '");
define("GMAIL_FROM_NAME", "' . addslashes($input['from_name']) . '");

// Application Settings
define("APP_URL", "http://localhost/Phamarcy/customer-portal");
define("SUPPORT_EMAIL", "' . addslashes($input['username']) . '");

// Email Templates Settings
define("COMPANY_NAME", "PharmaCare Pro");
define("COMPANY_TAGLINE", "Your Trusted Healthcare Partner");

?>';
    
    // Save configuration
    $configFile = __DIR__ . '/email-config.php';
    if (file_put_contents($configFile, $configContent) === false) {
        throw new Exception('Failed to save configuration file');
    }
    
    // Test basic file inclusion
    include $configFile;
    
    echo json_encode([
        'success' => true,
        'message' => 'Gmail configuration saved successfully!',
        'timestamp' => date('Y-m-d H:i:s'),
        'config' => [
            'username' => $input['username'],
            'from_name' => $input['from_name'],
            'config_file' => $configFile,
            'app_password_length' => strlen($cleanPassword)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
