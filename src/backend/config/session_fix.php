<?php
/**
 * Session Cookie Fix for Local Development
 * Handles SameSite cookie issues on localhost
 */

class SessionCookieFixer {
    
    /**
     * Fix session configuration for localhost development
     */
    public static function fixSessionForLocalhost() {
        // Don't configure if session is already active
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Detect if we're on localhost
        $isLocalhost = (
            $_SERVER['HTTP_HOST'] === 'localhost' || 
            $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
            strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
            strpos($_SERVER['SERVER_NAME'], 'localhost') !== false
        );
        
        if ($isLocalhost) {
            // Localhost-specific session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // HTTP only for localhost
            ini_set('session.cookie_samesite', ''); // Empty to avoid SameSite issues
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_domain', ''); // No domain restriction
            ini_set('session.cookie_path', '/'); // Root path
            
            // Custom session name
            session_name('PHARMACY_SESSION');
            
        } else {
            // Production configuration (HTTPS)
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.cookie_samesite', 'None'); // Requires secure=true
            ini_set('session.use_strict_mode', 1);
        }
    }
    
    /**
     * Start session with proper configuration
     */
    public static function startSession() {
        // Configure session before starting
        self::fixSessionForLocalhost();
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            
            // Additional debug info for localhost
            if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
                // Log session cookie info for debugging
                error_log("Session started: " . session_id());
                error_log("Cookie params: " . print_r(session_get_cookie_params(), true));
            }
        }
        
        return session_id();
    }
    
    /**
     * Set session cookie manually with correct attributes
     */
    public static function setSessionCookie($sessionId = null) {
        if (!$sessionId) {
            $sessionId = session_id();
        }
        
        $isLocalhost = (
            $_SERVER['HTTP_HOST'] === 'localhost' || 
            $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
            strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0
        );
        
        $cookieName = session_name();
        $cookiePath = '/';
        $cookieDomain = '';
        $cookieSecure = false;
        $cookieHttpOnly = true;
        
        if ($isLocalhost) {
            // For localhost - no SameSite attribute to avoid browser issues
            setcookie($cookieName, $sessionId, [
                'expires' => 0, // Session cookie
                'path' => $cookiePath,
                'domain' => $cookieDomain,
                'secure' => $cookieSecure,
                'httponly' => $cookieHttpOnly
                // Deliberately not setting samesite for localhost
            ]);
        } else {
            // For production
            setcookie($cookieName, $sessionId, [
                'expires' => 0,
                'path' => $cookiePath,
                'domain' => $cookieDomain,
                'secure' => true,
                'httponly' => $cookieHttpOnly,
                'samesite' => 'None'
            ]);
        }
    }
}
?>
