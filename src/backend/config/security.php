<?php
/**
 * Security Enhancement Utilities
 * Provides security functions and CSRF protection
 */

class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate input length
     */
    public static function validateLength($input, $min = 1, $max = 255) {
        $length = strlen($input);
        return $length >= $min && $length <= $max;
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($action, $limit = 10, $window = 60) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Remove old entries
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Check if limit exceeded
        if (count($_SESSION[$key]) >= $limit) {
            return false;
        }
        
        // Add current attempt
        $_SESSION[$key][] = $now;
        return true;
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = '') {
        $logFile = '../logs/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $userId = $_SESSION['user_id'] ?? 'Anonymous';
        
        $logEntry = "[$timestamp] Event: $event | User: $userId | IP: $ip | Details: $details | User-Agent: $userAgent" . PHP_EOL;
        
        // Create logs directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        error_log($logEntry, 3, $logFile);
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $minLength = 8;
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/\d/', $password);
        $hasSpecial = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);
        
        return strlen($password) >= $minLength && $hasUpper && $hasLower && $hasNumber && $hasSpecial;
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }
    
    /**
     * Verify password against hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check session timeout
     */
    public static function checkSessionTimeout($timeout = SESSION_TIMEOUT) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['last_activity'])) {
            $inactiveTime = time() - $_SESSION['last_activity'];
            
            if ($inactiveTime > $timeout) {
                session_unset();
                session_destroy();
                return false;
            }
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $maxSize = 2097152) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error';
            return $errors;
        }
        
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size too large (max 2MB)';
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = 'Invalid file content';
        }
        
        return $errors;
    }
}
?>
