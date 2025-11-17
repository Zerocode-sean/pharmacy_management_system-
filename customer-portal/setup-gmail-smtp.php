<?php
/**
 * PHPMailer Auto-Installer
 * Downloads and installs PHPMailer for Gmail SMTP functionality
 */

header('Content-Type: application/json');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'operations' => [],
    'success' => true,
    'errors' => []
];

function logOperation($message, $status = 'success') {
    global $results;
    $results['operations'][] = [
        'message' => $message,
        'status' => $status,
        'timestamp' => date('H:i:s')
    ];
    
    if ($status === 'error') {
        $results['success'] = false;
        $results['errors'][] = $message;
    }
}

try {
    $phpmailerDir = __DIR__ . '/phpmailer';
    
    // Check if PHPMailer already exists
    if (is_dir($phpmailerDir) && file_exists($phpmailerDir . '/src/PHPMailer.php')) {
        logOperation('✅ PHPMailer already installed', 'info');
        
        // Verify installation
        require_once $phpmailerDir . '/src/PHPMailer.php';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            logOperation('✅ PHPMailer verified and working');
        } else {
            logOperation('❌ PHPMailer installed but not working', 'error');
        }
        
    } else {
        logOperation('📥 Downloading PHPMailer...');
        
        // Create directory
        if (!is_dir($phpmailerDir)) {
            mkdir($phpmailerDir, 0755, true);
            logOperation('📁 Created PHPMailer directory');
        }
        
        // Download PHPMailer files
        $files = [
            'src/PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
            'src/SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
            'src/Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
        ];
        
        foreach ($files as $localPath => $url) {
            $fullPath = $phpmailerDir . '/' . $localPath;
            $dir = dirname($fullPath);
            
            // Create subdirectory if needed
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Download file
            $content = file_get_contents($url);
            
            if ($content !== false) {
                file_put_contents($fullPath, $content);
                logOperation("✅ Downloaded: $localPath");
            } else {
                logOperation("❌ Failed to download: $localPath", 'error');
            }
        }
        
        // Verify installation
        if (file_exists($phpmailerDir . '/src/PHPMailer.php')) {
            require_once $phpmailerDir . '/src/PHPMailer.php';
            require_once $phpmailerDir . '/src/SMTP.php';
            require_once $phpmailerDir . '/src/Exception.php';
            
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                logOperation('🎉 PHPMailer installation successful!');
            } else {
                logOperation('❌ PHPMailer installation failed', 'error');
            }
        } else {
            logOperation('❌ PHPMailer files not found after download', 'error');
        }
    }
    
    // Create configuration file template
    $configFile = __DIR__ . '/email-config.php';
    if (!file_exists($configFile)) {
        $configTemplate = '<?php
/**
 * Email Configuration for Gmail SMTP
 * 
 * SETUP INSTRUCTIONS:
 * 1. Go to Google Account settings: https://myaccount.google.com/
 * 2. Enable 2-Factor Authentication
 * 3. Go to "App passwords": https://myaccount.google.com/apppasswords
 * 4. Generate an app password for "Mail"
 * 5. Update the credentials below
 */

// Gmail SMTP Settings
define("GMAIL_USERNAME", "your-email@gmail.com");        // Your Gmail address
define("GMAIL_APP_PASSWORD", "your-16-digit-app-password"); // 16-digit app password
define("GMAIL_FROM_NAME", "PharmaCare Pro");             // Display name for emails

// Application Settings
define("APP_URL", "http://localhost/Phamarcy/customer-portal");
define("SUPPORT_EMAIL", "your-email@gmail.com");

// Email Templates Settings
define("COMPANY_NAME", "PharmaCare Pro");
define("COMPANY_TAGLINE", "Your Trusted Healthcare Partner");

?>';
        
        file_put_contents($configFile, $configTemplate);
        logOperation('📝 Created email configuration template');
        logOperation('⚠️ Please update email-config.php with your Gmail credentials', 'warning');
    } else {
        logOperation('📋 Configuration file already exists', 'info');
    }
    
    // Check current configuration status
    if (file_exists($configFile)) {
        include $configFile;
        
        if (defined('GMAIL_USERNAME') && GMAIL_USERNAME !== 'your-email@gmail.com') {
            logOperation('✅ Gmail username configured');
        } else {
            logOperation('⚠️ Gmail username needs configuration', 'warning');
        }
        
        if (defined('GMAIL_APP_PASSWORD') && GMAIL_APP_PASSWORD !== 'your-16-digit-app-password') {
            logOperation('✅ Gmail app password configured');
        } else {
            logOperation('⚠️ Gmail app password needs configuration', 'warning');
        }
    }
    
    $results['summary'] = [
        'phpmailer_installed' => file_exists($phpmailerDir . '/src/PHPMailer.php'),
        'config_file_exists' => file_exists($configFile),
        'installation_path' => $phpmailerDir,
        'config_path' => $configFile,
        'next_steps' => [
            'Update email-config.php with your Gmail credentials',
            'Test email configuration using test-gmail-setup.php',
            'Update your authentication APIs to use Gmail service'
        ]
    ];
    
    if ($results['success']) {
        $results['message'] = '🎉 PHPMailer setup complete! Please configure your Gmail credentials.';
    } else {
        $results['message'] = '⚠️ Setup completed with some issues. Check operations log.';
    }
    
} catch (Exception $e) {
    logOperation("❌ Critical error: " . $e->getMessage(), 'error');
    $results['message'] = "❌ Setup failed: " . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
