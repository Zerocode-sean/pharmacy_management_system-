<?php
/**
 * Gmail Status Check
 */

header('Content-Type: application/json');

try {
    $status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'phpmailer_installed' => false,
        'configured' => false,
        'smtp_test_passed' => false,
        'emails_sent' => 0,
        'config_details' => [],
        'issues' => []
    ];
    
    // Check PHPMailer installation
    if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
        $status['phpmailer_installed'] = true;
    } else {
        $status['issues'][] = 'PHPMailer not installed';
    }
    
    // Check configuration
    $configFile = __DIR__ . '/email-config.php';
    if (file_exists($configFile)) {
        include $configFile;
        
        $status['config_details'] = [
            'config_file_exists' => true,
            'username_configured' => defined('GMAIL_USERNAME') && GMAIL_USERNAME !== 'your-email@gmail.com',
            'password_configured' => defined('GMAIL_APP_PASSWORD') && GMAIL_APP_PASSWORD !== 'your-16-digit-app-password',
            'from_name' => defined('GMAIL_FROM_NAME') ? GMAIL_FROM_NAME : 'Not set'
        ];
        
        if ($status['config_details']['username_configured'] && $status['config_details']['password_configured']) {
            $status['configured'] = true;
        } else {
            if (!$status['config_details']['username_configured']) {
                $status['issues'][] = 'Gmail username not configured';
            }
            if (!$status['config_details']['password_configured']) {
                $status['issues'][] = 'Gmail app password not configured';
            }
        }
    } else {
        $status['issues'][] = 'Configuration file not found';
    }
    
    // Check email logs
    $logFile = __DIR__ . '/gmail_email_log.json';
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        $status['emails_sent'] = count($logs);
        
        // Check for successful sends
        $successful = array_filter($logs, function($log) {
            return isset($log['status']) && $log['status'] === 'success';
        });
        
        if (count($successful) > 0) {
            $status['smtp_test_passed'] = true;
        }
    }
    
    // Overall system status
    $status['overall_status'] = $status['phpmailer_installed'] && $status['configured'] ? 'ready' : 'needs_setup';
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
