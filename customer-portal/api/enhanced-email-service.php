<?php
/**
 * Enhanced Email Service with Gmail SMTP Support
 * Supports both file logging (development) and Gmail SMTP (production)
 */

class EnhancedEmailService {
    
    private static $useGmailSMTP = false;
    private static $config = [];
    
    /**
     * Initialize email service
     */
    public static function init() {
        // Check if Gmail configuration exists
        $configFile = __DIR__ . '/email-config.php';
        if (file_exists($configFile)) {
            include $configFile;
            
            if (defined('GMAIL_USERNAME') && defined('GMAIL_APP_PASSWORD') && 
                GMAIL_USERNAME !== 'your-email@gmail.com' && 
                GMAIL_APP_PASSWORD !== 'your-16-digit-app-password') {
                
                // Check if PHPMailer is available
                if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
                    self::$useGmailSMTP = true;
                    self::$config = [
                        'username' => GMAIL_USERNAME,
                        'password' => GMAIL_APP_PASSWORD,
                        'from_name' => defined('GMAIL_FROM_NAME') ? GMAIL_FROM_NAME : 'PharmaCare Pro'
                    ];
                }
            }
        }
    }
    
    /**
     * Send email with automatic fallback
     */
    public static function sendEmail($to, $subject, $htmlBody, $textBody = '') {
        self::init();
        
        if (self::$useGmailSMTP) {
            return self::sendGmailEmail($to, $subject, $htmlBody, $textBody);
        } else {
            return self::sendFileLogEmail($to, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Send email using Gmail SMTP
     */
    private static function sendGmailEmail($to, $subject, $htmlBody, $textBody) {
        try {
            require_once __DIR__ . '/phpmailer/src/Exception.php';
            require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/src/SMTP.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = self::$config['username'];
            $mail->Password = self::$config['password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom(self::$config['username'], self::$config['from_name']);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            
            $result = $mail->send();
            
            // Log successful email
            self::logEmailActivity([
                'method' => 'gmail_smtp',
                'status' => 'success',
                'to' => $to,
                'subject' => $subject,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            // Log error and fall back to file logging
            self::logEmailActivity([
                'method' => 'gmail_smtp',
                'status' => 'error',
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            // Fallback to file logging
            return self::sendFileLogEmail($to, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Send email using file logging (development/fallback)
     */
    private static function sendFileLogEmail($to, $subject, $htmlBody, $textBody) {
        $emailData = [
            'method' => 'file_log',
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody
        ];
        
        self::logEmailActivity($emailData);
        return true; // Always return true for file logging
    }
    
    /**
     * Log email activity
     */
    private static function logEmailActivity($data) {
        $logFile = __DIR__ . '/email_log.json';
        $logs = [];
        
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        // Keep only last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        $logs[] = $data;
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    }
    
    /**
     * Send verification email
     */
    public static function sendVerificationEmail($email, $name, $verificationCode) {
        $verificationLink = 'http://localhost/Phamarcy/customer-portal/verify-email.html?code=' . 
                           urlencode($verificationCode) . '&email=' . urlencode($email);
        
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
     * Get verification email template
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
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 0; background: #f7f9fc; }
                .email-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); margin: 20px; }
                .header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 40px 30px; }
                .verification-button { display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white !important; padding: 16px 32px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 600; font-size: 16px; }
                .verification-code { background: #f3f4f6; padding: 20px; border-radius: 8px; text-align: center; font-family: 'Courier New', monospace; font-size: 24px; font-weight: bold; letter-spacing: 3px; margin: 20px 0; border: 2px dashed #d1d5db; }
                .footer { background: #f9fafb; padding: 30px; text-align: center; color: #6b7280; font-size: 14px; border-top: 1px solid #e5e7eb; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🏥 Welcome to PharmaCare Pro!</h1>
                </div>
                
                <div class='content'>
                    <p><strong>Hello $name! 👋</strong></p>
                    
                    <p>Thank you for joining PharmaCare Pro! To get started, please verify your email address:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$verificationLink' class='verification-button'>✅ Verify My Email Address</a>
                    </div>
                    
                    <p>Or enter this verification code manually:</p>
                    <div class='verification-code'>$verificationCode</div>
                    
                    <p><strong>⚠️ Security Notice:</strong> This code expires in 15 minutes.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>PharmaCare Pro</strong> - Your Trusted Healthcare Partner</p>
                    <p>© 2025 PharmaCare Pro. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get password reset email template
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
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 0; background: #f7f9fc; }
                .email-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); margin: 20px; }
                .header { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 40px 30px; }
                .reset-button { display: inline-block; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white !important; padding: 16px 32px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 600; font-size: 16px; }
                .reset-token { background: #f3f4f6; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 14px; word-break: break-all; border: 2px dashed #d1d5db; margin: 20px 0; }
                .footer { background: #f9fafb; padding: 30px; text-align: center; color: #6b7280; font-size: 14px; border-top: 1px solid #e5e7eb; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                
                <div class='content'>
                    <p><strong>Hello $name,</strong></p>
                    
                    <p>We received a request to reset your password. To reset your password, click the button below:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$resetLink' class='reset-button'>🔑 Reset My Password</a>
                    </div>
                    
                    <p>Or copy and paste this reset token:</p>
                    <div class='reset-token'>$resetToken</div>
                    
                    <p><strong>⚠️ Security Notice:</strong> This link expires in 1 hour. If you didn't request this reset, please ignore this email.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>PharmaCare Pro</strong> - Your Trusted Healthcare Partner</p>
                    <p>© 2025 PharmaCare Pro. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get email logs
     */
    public static function getEmailLogs() {
        $logFile = __DIR__ . '/email_log.json';
        
        if (file_exists($logFile)) {
            return json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        return [];
    }
    
    /**
     * Get email service status
     */
    public static function getStatus() {
        self::init();
        
        return [
            'gmail_smtp_enabled' => self::$useGmailSMTP,
            'method' => self::$useGmailSMTP ? 'gmail_smtp' : 'file_log',
            'config_exists' => file_exists(__DIR__ . '/email-config.php'),
            'phpmailer_available' => file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php'),
            'emails_logged' => count(self::getEmailLogs())
        ];
    }
}
?>
