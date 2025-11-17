<?php
/**
 * Gmail SMTP Email Service
 * Real email sending using Gmail SMTP with PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer (we'll download this)
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

/**
 * Gmail SMTP Configuration
 * 
 * SETUP INSTRUCTIONS:
 * 1. Go to Google Account settings: https://myaccount.google.com/
 * 2. Enable 2-Factor Authentication
 * 3. Go to "App passwords": https://myaccount.google.com/apppasswords
 * 4. Generate an app password for "Mail"
 * 5. Update the credentials below
 */
class GmailEmailService {
    
    // Gmail SMTP Configuration
    private static $config = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_encryption' => PHPMailer::ENCRYPTION_STARTTLS,
        
        // UPDATE THESE WITH YOUR GMAIL CREDENTIALS
        'smtp_username' => 'your-email@gmail.com',        // Your Gmail address
        'smtp_password' => 'your-16-digit-app-password',  // 16-digit app password from Google
        
        // Email settings
        'from_email' => 'your-email@gmail.com',          // Same as username usually
        'from_name' => 'PharmaCare Pro',
        'reply_to_email' => 'your-email@gmail.com',
        
        // Application URLs
        'app_url' => 'http://localhost/Phamarcy/customer-portal',
        'verification_url' => 'http://localhost/Phamarcy/customer-portal/verify-email.html'
    ];
    
    /**
     * Update email configuration
     */
    public static function updateConfig($newConfig) {
        self::$config = array_merge(self::$config, $newConfig);
    }
    
    /**
     * Send email using Gmail SMTP
     */
    public static function sendEmail($to, $subject, $htmlBody, $textBody = '', $attachments = []) {
        try {
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = self::$config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = self::$config['smtp_username'];
            $mail->Password = self::$config['smtp_password'];
            $mail->SMTPSecure = self::$config['smtp_encryption'];
            $mail->Port = self::$config['smtp_port'];
            
            // Enable verbose debug output (disable in production)
            $mail->SMTPDebug = SMTP::DEBUG_OFF; // Change to DEBUG_SERVER for debugging
            
            // Recipients
            $mail->setFrom(self::$config['from_email'], self::$config['from_name']);
            $mail->addAddress($to);
            $mail->addReplyTo(self::$config['reply_to_email'], self::$config['from_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            
            // Add attachments if any
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }
            
            // Send email
            $result = $mail->send();
            
            // Log successful email
            self::logEmail([
                'status' => 'success',
                'to' => $to,
                'subject' => $subject,
                'timestamp' => date('Y-m-d H:i:s'),
                'method' => 'gmail_smtp',
                'message_id' => $mail->getLastMessageID()
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            // Log error
            self::logEmail([
                'status' => 'error',
                'to' => $to,
                'subject' => $subject,
                'timestamp' => date('Y-m-d H:i:s'),
                'method' => 'gmail_smtp',
                'error' => $e->getMessage()
            ]);
            
            // In production, you might want to fall back to the old method
            error_log("Gmail SMTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send verification email
     */
    public static function sendVerificationEmail($email, $name, $verificationCode) {
        $verificationLink = self::$config['verification_url'] . '?code=' . urlencode($verificationCode) . '&email=' . urlencode($email);
        
        $subject = '🔐 Verify Your PharmaCare Pro Account';
        
        $htmlBody = self::getVerificationEmailTemplate($name, $verificationLink, $verificationCode);
        
        $textBody = "
Hi $name,

Welcome to PharmaCare Pro!

Please verify your email address by visiting this link:
$verificationLink

Or enter this verification code manually: $verificationCode

This verification code will expire in 15 minutes for security.

If you didn't create an account, please ignore this email.

Best regards,
PharmaCare Pro Team
        ";
        
        return self::sendEmail($email, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordResetEmail($email, $name, $resetLink, $resetToken) {
        $subject = '🔑 Reset Your PharmaCare Pro Password';
        
        $htmlBody = self::getPasswordResetEmailTemplate($name, $resetLink, $resetToken);
        
        $textBody = "
Hi $name,

You recently requested to reset your password for your PharmaCare Pro account.

To reset your password, click the link below:
$resetLink

Or copy and paste this reset token: $resetToken

If you didn't request this reset, please ignore this email or contact support if you have concerns.

This link will expire in 1 hour for security reasons.

Best regards,
PharmaCare Pro Team
        ";
        
        return self::sendEmail($email, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Test email configuration
     */
    public static function testEmailConfiguration($testEmail = null) {
        $testEmail = $testEmail ?: self::$config['smtp_username'];
        
        $subject = '✅ PharmaCare Pro - Email Configuration Test';
        $htmlBody = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #2563eb;">🎉 Email Configuration Successful!</h2>
            <p>Congratulations! Your Gmail SMTP configuration is working correctly.</p>
            <p><strong>Test Details:</strong></p>
            <ul>
                <li>SMTP Host: ' . self::$config['smtp_host'] . '</li>
                <li>SMTP Port: ' . self::$config['smtp_port'] . '</li>
                <li>From Email: ' . self::$config['from_email'] . '</li>
                <li>Test Time: ' . date('Y-m-d H:i:s') . '</li>
            </ul>
            <p>Your PharmaCare Pro email system is ready for production use!</p>
        </div>';
        
        $textBody = "Email Configuration Test Successful!\n\nYour Gmail SMTP setup is working correctly.\nTest Time: " . date('Y-m-d H:i:s');
        
        return self::sendEmail($testEmail, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Get HTML template for verification email
     */
    private static function getVerificationEmailTemplate($name, $verificationLink, $verificationCode) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify Your Account - PharmaCare Pro</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 0;
                    background: #f7f9fc;
                }
                .email-container {
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                    margin: 20px;
                }
                .header {
                    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                }
                .content {
                    padding: 40px 30px;
                }
                .welcome-text {
                    font-size: 18px;
                    color: #1f2937;
                    margin-bottom: 20px;
                }
                .verification-button {
                    display: inline-block;
                    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                    color: white !important;
                    padding: 16px 32px;
                    text-decoration: none;
                    border-radius: 8px;
                    margin: 20px 0;
                    font-weight: 600;
                    font-size: 16px;
                    text-align: center;
                }
                .verification-code {
                    background: #f3f4f6;
                    padding: 20px;
                    border-radius: 8px;
                    text-align: center;
                    font-family: 'Courier New', monospace;
                    font-size: 24px;
                    font-weight: bold;
                    letter-spacing: 3px;
                    margin: 20px 0;
                    border: 2px dashed #d1d5db;
                }
                .features {
                    background: #f9fafb;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .features h3 {
                    margin-top: 0;
                    color: #1f2937;
                }
                .features ul {
                    margin: 0;
                    padding-left: 20px;
                }
                .features li {
                    margin: 8px 0;
                    color: #4b5563;
                }
                .footer {
                    background: #f9fafb;
                    padding: 30px;
                    text-align: center;
                    color: #6b7280;
                    font-size: 14px;
                    border-top: 1px solid #e5e7eb;
                }
                .security-notice {
                    background: #fef3c7;
                    border: 1px solid #f59e0b;
                    color: #92400e;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 20px 0;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🏥 Welcome to PharmaCare Pro!</h1>
                </div>
                
                <div class='content'>
                    <div class='welcome-text'>
                        Hello <strong>$name</strong>! 👋
                    </div>
                    
                    <p>Thank you for joining PharmaCare Pro! We're excited to be your trusted healthcare partner.</p>
                    
                    <p>To get started and secure your account, please verify your email address:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$verificationLink' class='verification-button'>
                            ✅ Verify My Email Address
                        </a>
                    </div>
                    
                    <p>Or enter this verification code manually:</p>
                    
                    <div class='verification-code'>$verificationCode</div>
                    
                    <div class='security-notice'>
                        <strong>⚠️ Security Notice:</strong> This verification code expires in 15 minutes. If you didn't create this account, please ignore this email.
                    </div>
                    
                    <div class='features'>
                        <h3>🚀 What's Next?</h3>
                        <ul>
                            <li>Browse our comprehensive medicine catalog</li>
                            <li>Place orders for prescription refills</li>
                            <li>Track your order status in real-time</li>
                            <li>Access your complete medication history</li>
                            <li>Receive health tips and medication reminders</li>
                            <li>Connect with our licensed pharmacists</li>
                        </ul>
                    </div>
                    
                    <p>Need help? Our support team is available 24/7 at <a href='mailto:support@pharmacare.pro'>support@pharmacare.pro</a></p>
                </div>
                
                <div class='footer'>
                    <p><strong>PharmaCare Pro</strong> - Your Trusted Healthcare Partner</p>
                    <p>© 2025 PharmaCare Pro. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get HTML template for password reset email
     */
    private static function getPasswordResetEmailTemplate($name, $resetLink, $resetToken) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Reset Your Password - PharmaCare Pro</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 0;
                    background: #f7f9fc;
                }
                .email-container {
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                    margin: 20px;
                }
                .header {
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                }
                .content {
                    padding: 40px 30px;
                }
                .reset-button {
                    display: inline-block;
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                    color: white !important;
                    padding: 16px 32px;
                    text-decoration: none;
                    border-radius: 8px;
                    margin: 20px 0;
                    font-weight: 600;
                    font-size: 16px;
                }
                .reset-token {
                    background: #f3f4f6;
                    padding: 15px;
                    border-radius: 8px;
                    font-family: 'Courier New', monospace;
                    font-size: 14px;
                    word-break: break-all;
                    border: 2px dashed #d1d5db;
                    margin: 20px 0;
                }
                .security-warning {
                    background: #fef2f2;
                    border: 1px solid #fca5a5;
                    color: #dc2626;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .footer {
                    background: #f9fafb;
                    padding: 30px;
                    text-align: center;
                    color: #6b7280;
                    font-size: 14px;
                    border-top: 1px solid #e5e7eb;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                
                <div class='content'>
                    <p><strong>Hello $name,</strong></p>
                    
                    <p>We received a request to reset the password for your PharmaCare Pro account.</p>
                    
                    <p>To reset your password, click the button below:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$resetLink' class='reset-button'>
                            🔑 Reset My Password
                        </a>
                    </div>
                    
                    <p>Or copy and paste this reset token:</p>
                    <div class='reset-token'>$resetToken</div>
                    
                    <div class='security-warning'>
                        <h3>🛡️ Security Information</h3>
                        <ul>
                            <li><strong>This link expires in 1 hour</strong></li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Never share this token with anyone</li>
                            <li>Contact support immediately if you suspect unauthorized access</li>
                        </ul>
                    </div>
                    
                    <p><strong>What happens next?</strong></p>
                    <ol>
                        <li>Click the reset link or use the token above</li>
                        <li>Enter your new secure password</li>
                        <li>Log in with your new credentials</li>
                        <li>All existing sessions will be logged out for security</li>
                    </ol>
                    
                    <p>Need help? Contact our support team at <a href='mailto:support@pharmacare.pro'>support@pharmacare.pro</a></p>
                </div>
                
                <div class='footer'>
                    <p><strong>PharmaCare Pro</strong> - Your Trusted Healthcare Partner</p>
                    <p>© 2025 PharmaCare Pro. All rights reserved.</p>
                    <p>This is an automated security message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Log email activity
     */
    private static function logEmail($data) {
        $logFile = __DIR__ . '/gmail_email_log.json';
        $logs = [];
        
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        // Keep only last 100 log entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        $logs[] = $data;
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get email logs
     */
    public static function getEmailLogs() {
        $logFile = __DIR__ . '/gmail_email_log.json';
        
        if (file_exists($logFile)) {
            return json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        return [];
    }
    
    /**
     * Get configuration status
     */
    public static function getConfigurationStatus() {
        $status = [
            'configured' => false,
            'smtp_host' => self::$config['smtp_host'],
            'smtp_port' => self::$config['smtp_port'],
            'from_email' => self::$config['from_email'],
            'phpmailer_available' => false,
            'issues' => []
        ];
        
        // Check if PHPMailer is available
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $status['phpmailer_available'] = true;
        } else {
            $status['issues'][] = 'PHPMailer library not found';
        }
        
        // Check if credentials are configured
        if (self::$config['smtp_username'] === 'your-email@gmail.com') {
            $status['issues'][] = 'Gmail username not configured';
        }
        
        if (self::$config['smtp_password'] === 'your-16-digit-app-password') {
            $status['issues'][] = 'Gmail app password not configured';
        }
        
        if (empty($status['issues'])) {
            $status['configured'] = true;
        }
        
        return $status;
    }
}
?>
