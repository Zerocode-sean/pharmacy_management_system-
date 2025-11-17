<?php
/**
 * Authentication Middleware
 * Checks authentication and session security for API endpoints
 */

require_once '../config/security.php';

class AuthMiddleware {
    
    /**
     * Check if user is authenticated and session is valid
     */
    public static function checkAuth($requireCSRF = false) {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit();
        }
        
        // Check session timeout
        if (!Security::checkSessionTimeout()) {
            Security::logSecurityEvent('SESSION_TIMEOUT', 'User ID: ' . ($_SESSION['user_id'] ?? 'Unknown'));
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expired']);
            exit();
        }
        
        // Check CSRF token for POST/PUT/DELETE requests
        if ($requireCSRF && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            if (!Security::validateCSRFToken($csrfToken)) {
                Security::logSecurityEvent('CSRF_TOKEN_INVALID', 'User ID: ' . $_SESSION['user_id']);
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                exit();
            }
        }
        
        return true;
    }
    
    /**
     * Check if user has required role
     */
    public static function checkRole($requiredRole) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $userRole = $_SESSION['role'] ?? '';
        
        // Admin has access to everything
        if ($userRole === 'admin') {
            return true;
        }
        
        // Check specific role requirements
        $roleHierarchy = [
            'cashier' => ['cashier'],
            'pharmacist' => ['cashier', 'pharmacist'],
            'admin' => ['cashier', 'pharmacist', 'admin']
        ];
        
        if (!isset($roleHierarchy[$userRole]) || !in_array($requiredRole, $roleHierarchy[$userRole])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
            exit();
        }
        
        return true;
    }
    
    /**
     * Log API access
     */
    public static function logAccess($endpoint, $action = '') {
        $userId = $_SESSION['user_id'] ?? 'Anonymous';
        $username = $_SESSION['username'] ?? 'Unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        Security::logSecurityEvent('API_ACCESS', 
            "Endpoint: $endpoint | Method: $method | User: $username ($userId) | IP: $ip | Action: $action"
        );
    }
}
?>
