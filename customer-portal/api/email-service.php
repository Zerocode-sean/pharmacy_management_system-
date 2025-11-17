<?php
/**
 * Email Service Configuration
 * Configure email settings for the pharmacy system
 */

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'ndindajohn22@gmail.com'); // Change this to your email
define('SMTP_PASSWORD', 'nqfg kibk vmog uorn');    // Change this to your app password
define('SMTP_ENCRYPTION', 'tls');

// Email Settings
define('FROM_EMAIL', 'noreply@pharmacare.com');
define('FROM_NAME', 'PharmaConnect');
define('REPLY_TO_EMAIL', 'support@pharmacare.com');

// Application URLs
define('APP_URL', 'http://localhost/Phamarcy/customer-portal');
define('VERIFICATION_URL', APP_URL . '/verify-email.php');

/**
 * Simple Email Service Class
 * For basic email functionality without external dependencies
 */
class EmailService {
    
    /**
     * Send email using Gmail SMTP
     */
    public static function sendEmail($to, $subject, $htmlBody, $textBody = '') {
        
        // Log email for debugging
        $emailData = [
            'method' => 'smtp_attempt',
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody
        ];
        
        $logFile = __DIR__ . '/../logs/email-log.json';
        
        // Create logs directory if it doesn't exist
        $logsDir = dirname($logFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        $logs = [];
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        try {
            // Try to use Gmail SMTP
            if (self::sendViaGmailSMTP($to, $subject, $htmlBody, $textBody)) {
                $emailData['method'] = 'smtp';
                $emailData['status'] = 'sent';
                $logs[] = $emailData;
                file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
                return true;
            } else {
                throw new Exception('SMTP sending failed');
            }
        } catch (Exception $e) {
            // Fallback to file logging if SMTP fails
            $emailData['method'] = 'file_log';
            $emailData['status'] = 'logged_only';
            $emailData['smtp_error'] = $e->getMessage();
            $logs[] = $emailData;
            file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
            
            // Still return true so registration doesn't fail
            return true;
        }
    }
    
    /**
     * Send email via Gmail SMTP
     */
    private static function sendViaGmailSMTP($to, $subject, $htmlBody, $textBody) {
        // Check if we can use fsockopen for SMTP
        if (!function_exists('fsockopen')) {
            throw new Exception('fsockopen is not available');
        }
        
        $from = SMTP_USERNAME;
        $fromName = FROM_NAME;
        
        // Connect to Gmail SMTP server
        $smtpServer = SMTP_HOST;
        $smtpPort = SMTP_PORT;
        $username = SMTP_USERNAME;
        $password = SMTP_PASSWORD;
        
        // Create socket connection - use tcp for port 587, not ssl
        // Gmail uses STARTTLS on port 587, not implicit SSL
        $socket = @fsockopen($smtpServer, $smtpPort, $errno, $errstr, 30);
        
        if (!$socket) {
            throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
        }
        
        // Read initial response
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            throw new Exception("Server not ready: $response");
        }
        
        // Send EHLO command
        fputs($socket, "EHLO localhost\r\n");
        // Read all EHLO responses (multiline)
        while ($response = fgets($socket, 515)) {
            if (substr($response, 3, 1) == ' ') break; // Last line starts with code followed by space
        }
        
        // Start TLS
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            throw new Exception("STARTTLS failed: $response");
        }
        
        // Enable crypto - use TLSv1.2 or higher
        $crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        if (!stream_socket_enable_crypto($socket, true, $crypto_method)) {
            fclose($socket);
            throw new Exception("Failed to enable TLS encryption");
        }
        
        // Send EHLO again after TLS
        fputs($socket, "EHLO localhost\r\n");
        // Read all EHLO responses
        while ($response = fgets($socket, 515)) {
            if (substr($response, 3, 1) == ' ') break;
        }
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            throw new Exception("AUTH LOGIN not accepted: $response");
        }
        
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            throw new Exception("Username not accepted: $response");
        }
        
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            throw new Exception("Authentication failed: $response");
        }
        
        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <$from>\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("MAIL FROM rejected: $response");
        }
        
        // Send RCPT TO
        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("RCPT TO rejected: $response");
        }
        
        // Send DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            throw new Exception("DATA command rejected: $response");
        }
        
        // Send email headers and body
        $headers = "From: $fromName <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";
        
        fputs($socket, $headers . $htmlBody . "\r\n.\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("Message not accepted: $response");
        }
        
        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    }
    
    /**
     * Send verification email
     */
    public static function sendVerificationEmail($email, $name, $verificationCode) {
        // Properly encode URL parameters for verification link
        $verificationLink = VERIFICATION_URL . '?email=' . urlencode($email) . '&code=' . urlencode($verificationCode);
        
        $subject = 'Verify Your PharmaConnect Account';
        
        $htmlBody = self::getVerificationEmailTemplate($name, $verificationLink, $verificationCode);
        
        $textBody = "
Hi $name,

Welcome to PharmaConnect!

Please verify your email address by visiting this link:
$verificationLink

Or enter this verification code manually: $verificationCode

This verification link will expire in 24 hours.

If you didn't create an account, please ignore this email.

Best regards,
PharmaConnect Team
        ";
        
        return self::sendEmail($email, $subject, $htmlBody, $textBody);
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
            <title>Verify Your Account - PharmaConnect</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f5f7fa;
                }
                .email-container {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0 0 10px 0;
                    font-size: 28px;
                }
                .header p {
                    margin: 0;
                    font-size: 16px;
                    opacity: 0.9;
                }
                .content {
                    padding: 40px 30px;
                }
                .content h2 {
                    color: #333;
                    margin-top: 0;
                    font-size: 24px;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white !important;
                    padding: 16px 40px;
                    text-decoration: none;
                    border-radius: 30px;
                    margin: 20px 0;
                    font-weight: 600;
                    font-size: 16px;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                    transition: transform 0.2s;
                }
                .button:hover {
                    transform: translateY(-2px);
                }
                .verification-code {
                    background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
                    padding: 20px;
                    border-radius: 10px;
                    text-align: center;
                    font-family: 'Courier New', monospace;
                    font-size: 24px;
                    font-weight: bold;
                    letter-spacing: 4px;
                    margin: 20px 0;
                    color: #667eea;
                    border: 2px dashed #667eea;
                }
                .info-box {
                    background: #e8f4fd;
                    border-left: 4px solid #3498db;
                    padding: 15px;
                    border-radius: 6px;
                    margin: 20px 0;
                }
                .features {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .features ul {
                    margin: 10px 0;
                    padding-left: 20px;
                }
                .features li {
                    margin: 8px 0;
                    color: #555;
                }
                .footer {
                    text-align: center;
                    padding: 30px;
                    background: #f8f9fa;
                    color: #666;
                    font-size: 13px;
                }
                .footer p {
                    margin: 5px 0;
                }
                .divider {
                    height: 1px;
                    background: linear-gradient(to right, transparent, #ddd, transparent);
                    margin: 30px 0;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🏥 PharmaConnect</h1>
                    <p>Welcome to Your Healthcare Partner</p>
                </div>
                
                <div class='content'>
                    <h2>Welcome, $name! 👋</h2>
                    
                    <p>Thank you for joining PharmaConnect! We're excited to help you manage your healthcare needs.</p>
                    
                    <p>To get started and activate your account, please verify your email address by clicking the button below:</p>
                    
                    <p style='text-align: center;'>
                        <a href='$verificationLink' class='button'>✓ Verify My Email Address</a>
                    </p>
                    
                    <div class='info-box'>
                        <strong>📱 Alternative Method:</strong> Can't click the button? Copy and paste this verification code manually:
                    </div>
                    
                    <div class='verification-code'>
                        $verificationCode
                    </div>
                    
                    <div class='divider'></div>
                    
                    <div class='features'>
                        <p><strong>🎉 What's waiting for you:</strong></p>
                        <ul>
                            <li>📦 Browse our comprehensive medicine catalog</li>
                            <li>💊 Place orders for prescription refills</li>
                            <li>🚚 Track your order status in real-time</li>
                            <li>📋 Access your complete medication history</li>
                            <li>💡 Receive health tips and important updates</li>
                        </ul>
                    </div>
                    
                    <p style='font-size: 13px; color: #666; margin-top: 30px;'>
                        <strong>Note:</strong> This verification link will expire in 24 hours for security reasons. 
                        If you didn't create this account, please ignore this email.
                    </p>
                    
                    <p style='font-size: 14px;'>
                        Need help? Contact our support team at <a href='mailto:support@pharmacare.com' style='color: #667eea;'>support@pharmacare.com</a>
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>© 2025 PharmaConnect</strong> - All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p style='margin-top: 15px; font-size: 12px;'>
                        PharmaConnect | Your Trusted Healthcare Partner<br>
                        Verification Link: <span style='color: #999; font-size: 11px;'>$verificationLink</span>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordResetEmail($email, $name, $resetLink, $resetToken) {
        $subject = "Reset Your Password - PharmaConnect";
        
        $htmlBody = self::getPasswordResetEmailTemplate($name, $resetLink, $resetToken);
        
        $textBody = "
Hello $name,

You recently requested to reset your password for your PharmaConnect account.

To reset your password, click the link below:
$resetLink

Or copy and paste this reset token: $resetToken

If you didn't request this reset, please ignore this email or contact support if you have concerns.

This link will expire in 1 hour for security reasons.

Best regards,
PharmaConnect Team
        ";
        
        return self::sendEmail($email, $subject, $htmlBody, $textBody);
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
            <title>Reset Your Password - PharmaConnect</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                    border-radius: 10px 10px 0 0;
                }
                .content {
                    background: #f9f9f9;
                    padding: 30px;
                    border-radius: 0 0 10px 10px;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                    color: white !important;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 25px;
                    margin: 20px 0;
                    font-weight: bold;
                }
                .reset-token {
                    background: #e9ecef;
                    padding: 15px;
                    border-radius: 8px;
                    text-align: center;
                    font-family: monospace;
                    font-size: 16px;
                    font-weight: bold;
                    letter-spacing: 1px;
                    margin: 20px 0;
                    word-break: break-all;
                }
                .warning {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    color: #666;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🔒 PharmaConnect</h1>
                <p>Password Reset Request</p>
            </div>
            
            <div class='content'>
                <h2>Hello, $name! 🔑</h2>
                
                <p>We received a request to reset the password for your PharmaConnect account.</p>
                
                <p>To reset your password, click the button below:</p>
                
                <p style='text-align: center;'>
                    <a href='$resetLink' class='button'>Reset My Password</a>
                </p>
                
                <p>Or you can copy and paste this reset token manually:</p>
                
                <div class='reset-token'>
                    $resetToken
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Security Notice:</strong>
                    <ul>
                        <li>This link will expire in 1 hour</li>
                        <li>If you didn't request this reset, please ignore this email</li>
                        <li>Never share this token with anyone</li>
                        <li>Contact support if you have security concerns</li>
                    </ul>
                </div>
                
                <p><strong>What happens next?</strong></p>
                <ul>
                    <li>Click the reset link or use the token</li>
                    <li>Enter your new password (minimum 8 characters)</li>
                    <li>Log in with your new credentials</li>
                </ul>
                
                <p>If you need help, contact our support team at support@pharmacare.com</p>
            </div>
            
            <div class='footer'>
                <p>© 2025 PharmaConnect. All rights reserved.</p>
                <p>This is an automated message, please do not reply to this email.</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get recent email logs (for testing)
     */
    public static function getEmailLogs() {
        $logFile = __DIR__ . '/email_log.json';
        
        if (file_exists($logFile)) {
            return json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        return [];
    }
}
?>
