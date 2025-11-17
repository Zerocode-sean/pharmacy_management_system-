<?php
/**
 * CORS Helper Functions
 * Common CORS handling for all API endpoints
 */

class CORSHelper {
    
    /**
     * Configure session settings for cross-origin requests
     */
    public static function configureSession() {
        // Basic session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        
        // Handle SameSite and Secure attributes based on environment
        $isLocalhost = (
            $_SERVER['HTTP_HOST'] === 'localhost' || 
            $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
            strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0
        );

        if ($isLocalhost) {
            // For localhost development - use HTTP-compatible settings
            ini_set('session.cookie_secure', 0);
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.cookie_domain', '');
        } else {
            // For production with HTTPS
            ini_set('session.cookie_secure', 1);
            ini_set('session.cookie_samesite', 'None');
        }
    }
    
    /**
     * Set proper CORS headers for session-based authentication
     */
    public static function setCORSHeaders() {
        // Determine origin
        $origin = '';
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $origin = $protocol . '://' . $_SERVER['HTTP_HOST'];
        } else {
            $origin = 'http://localhost';
        }

        // Allowed origins for local development
        $allowedOrigins = [
            'http://localhost',
            'http://127.0.0.1',
            'http://localhost:80',
            'http://localhost:3000',
            'https://localhost',
            'https://127.0.0.1',
        ];

        // Find matching origin
        $allowOrigin = 'http://localhost'; // Default fallback
        foreach ($allowedOrigins as $allowed) {
            if (strpos($origin, $allowed) === 0 || $origin === $allowed) {
                $allowOrigin = $origin;
                break;
            }
        }

        // Set CORS headers
        header('Content-Type: application/json');
        header("Access-Control-Allow-Origin: $allowOrigin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
        header('Access-Control-Allow-Credentials: true');
    }

    /**
     * Handle OPTIONS preflight requests
     */
    public static function handlePreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::setCORSHeaders();
            http_response_code(200);
            exit();
        }
    }

    /**
     * Set CORS headers and handle preflight in one call
     */
    public static function init() {
        self::configureSession();
        self::setCORSHeaders();
        self::handlePreflight();
    }
}
?>
