<?php
// Suppress all notices and warnings for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/auth_middleware.php';
require_once '../config/session_fix.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Use SessionCookieFixer for consistent session management
SessionCookieFixer::startSession();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
    exit;
}

// Only admin can manage users
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - Admin role required']);
    exit;
}

// Get method and action first
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    $database = new Database();
    $db = $database->connect();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'stats':
                    handleGetStats($db);
                    break;
                case 'permissions':
                    handleGetPermissions($db);
                    break;
                case 'activity':
                    handleGetActivity($db);
                    break;
                case 'sessions':
                    handleGetActiveSessions($db);
                    break;
                case 'performance':
                    handleGetPerformance($db);
                    break;
                case 'attendance':
                    handleGetAttendance($db);
                    break;
                case 'evaluations':
                    handleGetEvaluations($db);
                    break;
                case 'training':
                    handleGetTraining($db);
                    break;
                case 'expiring_certifications':
                    handleGetExpiringCertifications($db);
                    break;
                case 'export_attendance':
                    handleExportAttendance($db);
                    break;
                default:
                    if (isset($_GET['id'])) {
                        handleGetUser($db, $_GET['id']);
                    } else {
                        handleGetUsers($db);
                    }
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'reset_password':
                    handleResetPassword($db, $_GET['id']);
                    break;
                case 'terminate_session':
                    handleTerminateSession($db, $input);
                    break;
                case 'terminate_all_sessions':
                    handleTerminateAllSessions($db);
                    break;
                case 'set_goals':
                    handleSetGoals($db, $input);
                    break;
                case 'record_attendance':
                    handleRecordAttendance($db, $input);
                    break;
                case 'save_evaluation':
                    handleSaveEvaluation($db, $input);
                    break;
                case 'save_training':
                    handleSaveTraining($db, $input);
                    break;
                default:
                    handleCreateUser($db, $input);
            }
            break;
            
        case 'PUT':
            switch ($action) {
                case 'suspend':
                    handleSuspendUser($db, $_GET['id']);
                    break;
                case 'activate':
                    handleActivateUser($db, $_GET['id']);
                    break;
                case 'update_permissions':
                    handleUpdatePermissions($db, $input);
                    break;
                default:
                    handleUpdateUser($db, $_GET['id'], $input);
            }
            break;
            
        case 'DELETE':
            handleDeleteUser($db, $_GET['id']);
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('users_api_error', 
        'Error: ' . $e->getMessage() . ' | User ID: ' . ($_SESSION['user_id'] ?? 'null')
    );
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetUsers($db) {
    // First try with user_login_log, fallback if table doesn't exist
    try {
        $query = "
            SELECT 
                u.*,
                (SELECT MAX(ul.login_time) FROM user_login_log ul WHERE ul.user_id = u.id) as last_login
            FROM users u 
            ORDER BY u.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If user_login_log table doesn't exist, use simple query
        if (strpos($e->getMessage(), 'user_login_log') !== false) {
            $query = "
                SELECT 
                    u.*,
                    NULL as last_login
                FROM users u 
                ORDER BY u.created_at DESC
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            throw $e; // Re-throw if it's a different error
        }
    }
    
    // Remove password from response and add status field
    foreach ($users as &$user) {
        unset($user['password']);
        $user['is_active'] = (bool)$user['is_active'];
        $user['status'] = $user['is_active'] ? 'active' : 'inactive';
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}

function handleGetUser($db, $userId) {
    $userId = Security::sanitizeInput($userId);
    
    if (!is_numeric($userId)) {
        throw new Exception('Invalid user ID', 400);
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found', 404);
    }
    
    // Remove password from response
    unset($user['password']);
    $user['is_active'] = (bool)$user['is_active'];
    $user['status'] = $user['is_active'] ? 'active' : 'inactive';
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}

function handleGetStats($db) {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active users
    $stmt = $db->prepare("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
    $stmt->execute();
    $active = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    // Admin users
    $stmt = $db->prepare("SELECT COUNT(*) as admin FROM users WHERE role = 'admin' AND is_active = 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC)['admin'];
    
    // Pharmacist users
    $stmt = $db->prepare("SELECT COUNT(*) as pharmacist FROM users WHERE role = 'pharmacist' AND is_active = 1");
    $stmt->execute();
    $pharmacist = $stmt->fetch(PDO::FETCH_ASSOC)['pharmacist'];
    
    // Cashier users
    $stmt = $db->prepare("SELECT COUNT(*) as cashier FROM users WHERE role = 'cashier' AND is_active = 1");
    $stmt->execute();
    $cashier = $stmt->fetch(PDO::FETCH_ASSOC)['cashier'];
    
    // Online users (logged in within last 15 minutes)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) as online 
        FROM user_sessions 
        WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute();
    $online = $stmt->fetch(PDO::FETCH_ASSOC)['online'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => (int)$total,
            'active' => (int)$active,
            'admin' => (int)$admin,
            'pharmacist' => (int)$pharmacist,
            'cashier' => (int)$cashier,
            'online' => (int)$online
        ]
    ]);
}

function handleGetPermissions($db) {
    // Default role permissions
    $permissions = [
        'admin' => [
            'sales.create', 'sales.view', 'sales.refund',
            'inventory.create', 'inventory.edit', 'inventory.delete', 'inventory.adjust',
            'customers.create', 'customers.edit', 'customers.delete',
            'reports.sales', 'reports.inventory', 'reports.financial', 'reports.export',
            'users.create', 'users.edit', 'users.delete', 'users.permissions',
            'system.backup', 'system.settings', 'system.logs'
        ],
        'pharmacist' => [
            'sales.create', 'sales.view',
            'inventory.create', 'inventory.edit', 'inventory.adjust',
            'customers.create', 'customers.edit',
            'reports.sales', 'reports.inventory'
        ],
        'cashier' => [
            'sales.create', 'sales.view',
            'customers.create',
            'reports.sales'
        ]
    ];
    
    // Load custom permissions from database if they exist
    $stmt = $db->prepare("SELECT role, permissions FROM role_permissions");
    $stmt->execute();
    $customPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($customPermissions as $rolePermission) {
        $permissions[$rolePermission['role']] = json_decode($rolePermission['permissions'], true);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $permissions
    ]);
}

function handleCreateUser($db, $input) {
    // Validate required fields
    $requiredFields = ['full_name', 'username', 'email', 'password', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("$field is required", 400);
        }
    }
    
    // Sanitize inputs
    $fullName = Security::sanitizeInput($input['full_name']);
    $username = Security::sanitizeInput($input['username']);
    $email = Security::sanitizeInput($input['email']);
    $password = $input['password']; // Don't sanitize password
    $role = Security::sanitizeInput($input['role']);
    $phone = isset($input['phone']) ? Security::sanitizeInput($input['phone']) : null;
    $address = isset($input['address']) ? Security::sanitizeInput($input['address']) : null;
    
    // Support both 'is_active' and 'status' fields
    if (isset($input['status'])) {
        $isActive = ($input['status'] === 'active') ? 1 : 0;
    } else {
        $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : 1;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format', 400);
    }
    
    // Validate role
    $validRoles = ['admin', 'pharmacist', 'cashier'];
    if (!in_array($role, $validRoles)) {
        throw new Exception('Invalid role', 400);
    }
    
    // Validate password strength - simplified for easier use
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters', 400);
    }
    
    // Check for duplicate username or email
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        throw new Exception('Username or email already exists', 400);
    }
    
    // Hash password
    $hashedPassword = Security::hashPassword($password);
    
    // Insert user
    $query = "
        INSERT INTO users (
            full_name, username, email, password, role, phone,
            is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        $fullName, $username, $email, $hashedPassword, $role, $phone, $isActive
    ]);
    
    if (!$success) {
        throw new Exception('Failed to create user', 500);
    }
    
    $userId = $db->lastInsertId();
    
    // Log security event
    Security::logSecurityEvent('user_created', 
        "User ID: $userId | Username: $username | Role: $role | Created by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'data' => ['id' => $userId]
    ]);
}

function handleUpdateUser($db, $userId, $input) {
    $userId = Security::sanitizeInput($userId);
    
    if (!is_numeric($userId)) {
        throw new Exception('Invalid user ID', 400);
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        throw new Exception('User not found', 404);
    }
    
    // Validate required fields
    $requiredFields = ['full_name', 'username', 'email', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("$field is required", 400);
        }
    }
    
    // Sanitize inputs
    $fullName = Security::sanitizeInput($input['full_name']);
    $username = Security::sanitizeInput($input['username']);
    $email = Security::sanitizeInput($input['email']);
    $role = Security::sanitizeInput($input['role']);
    $phone = isset($input['phone']) ? Security::sanitizeInput($input['phone']) : null;
    $address = isset($input['address']) ? Security::sanitizeInput($input['address']) : null;
    
    // Support both 'is_active' and 'status' fields
    if (isset($input['status'])) {
        $isActive = ($input['status'] === 'active') ? 1 : 0;
    } else {
        $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : 1;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format', 400);
    }
    
    // Validate role
    $validRoles = ['admin', 'pharmacist', 'cashier'];
    if (!in_array($role, $validRoles)) {
        throw new Exception('Invalid role', 400);
    }
    
    // Check for duplicate username or email (excluding current user)
    $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $userId]);
    if ($stmt->fetch()) {
        throw new Exception('Username or email already exists', 400);
    }
    
    // Update user - handle password separately if provided
    if (!empty($input['password'])) {
        // Validate password strength
        if (strlen($input['password']) < 6) {
            throw new Exception('Password must be at least 6 characters', 400);
        }
        
        $hashedPassword = Security::hashPassword($input['password']);
        $query = "
            UPDATE users SET 
                full_name = ?, username = ?, email = ?, password = ?, role = ?, 
                phone = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $db->prepare($query);
        $success = $stmt->execute([
            $fullName, $username, $email, $hashedPassword, $role, $phone, $isActive, $userId
        ]);
    } else {
        // Update without changing password
        $query = "
            UPDATE users SET 
                full_name = ?, username = ?, email = ?, role = ?, 
                phone = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $db->prepare($query);
        $success = $stmt->execute([
            $fullName, $username, $email, $role, $phone, $isActive, $userId
        ]);
    }
    
    if (!$success) {
        throw new Exception('Failed to update user', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('user_updated', 
        "User ID: $userId | Username: $username | Updated by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
}

function handleResetPassword($db, $userId) {
    $userId = Security::sanitizeInput($userId);
    
    if (!is_numeric($userId)) {
        throw new Exception('Invalid user ID', 400);
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found', 404);
    }
    
    // Generate temporary password
    $tempPassword = generateTempPassword();
    $hashedPassword = Security::hashPassword($tempPassword);
    
    // Update password
    $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$hashedPassword, $userId]);
    
    if (!$success) {
        throw new Exception('Failed to reset password', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('password_reset', 
        "User ID: $userId | Username: " . $user['username'] . " | Reset by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully',
        'data' => ['temporary_password' => $tempPassword]
    ]);
}

function handleSuspendUser($db, $userId) {
    updateUserStatus($db, $userId, false, 'suspended');
}

function handleActivateUser($db, $userId) {
    updateUserStatus($db, $userId, true, 'activated');
}

function updateUserStatus($db, $userId, $isActive, $action) {
    $userId = Security::sanitizeInput($userId);
    
    if (!is_numeric($userId)) {
        throw new Exception('Invalid user ID', 400);
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found', 404);
    }
    
    // Don't allow suspending yourself
    if ($userId == $_SESSION['user_id'] && !$isActive) {
        throw new Exception('Cannot suspend your own account', 400);
    }
    
    // Update status
    $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$isActive, $userId]);
    
    if (!$success) {
        throw new Exception("Failed to {$action} user", 500);
    }
    
    // Terminate user sessions if suspending
    if (!$isActive) {
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    
    // Log security event
    Security::logSecurityEvent("user_{$action}", 
        "User ID: $userId | Username: " . $user['username'] . " | {$action} by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => "User {$action} successfully"
    ]);
}

function handleDeleteUser($db, $userId) {
    $userId = Security::sanitizeInput($userId);
    
    if (!is_numeric($userId)) {
        throw new Exception('Invalid user ID', 400);
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found', 404);
    }
    
    // Don't allow deleting yourself
    if ($userId == $_SESSION['user_id']) {
        throw new Exception('Cannot delete your own account', 400);
    }
    
    // Check if user has associated records
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sales WHERE user_id = ?");
    $stmt->execute([$userId]);
    $salesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($salesCount > 0) {
        throw new Exception('Cannot delete user with existing sales records', 400);
    }
    
    // Delete user
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $success = $stmt->execute([$userId]);
    
    if (!$success) {
        throw new Exception('Failed to delete user', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('user_deleted', 
        "User ID: $userId | Username: " . $user['username'] . " | Deleted by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
}

function handleUpdatePermissions($db, $input) {
    $role = Security::sanitizeInput($input['role']);
    $permissions = $input['permissions'];
    
    if (!in_array($role, ['admin', 'pharmacist', 'cashier'])) {
        throw new Exception('Invalid role', 400);
    }
    
    // Create table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            role VARCHAR(50) PRIMARY KEY,
            permissions JSON NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Update or insert permissions
    $stmt = $db->prepare("
        INSERT INTO role_permissions (role, permissions) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)
    ");
    
    $success = $stmt->execute([$role, json_encode($permissions)]);
    
    if (!$success) {
        throw new Exception('Failed to update permissions', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('permissions_updated', 
        "Role: $role | Updated by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Permissions updated successfully'
    ]);
}

function handleGetActivity($db) {
    $filter = $_GET['filter'] ?? 'all';
    $period = $_GET['period'] ?? 'month';
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Apply filter
    if ($filter !== 'all') {
        $whereClause .= " AND activity_type = ?";
        $params[] = $filter;
    }
    
    // Apply period
    switch ($period) {
        case 'today':
            $whereClause .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $whereClause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $whereClause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
    }
    
    $query = "
        SELECT 
            ua.*,
            u.full_name as user_name
        FROM user_activity ua
        LEFT JOIN users u ON ua.user_id = u.id
        $whereClause
        ORDER BY ua.created_at DESC
        LIMIT 50
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $activities
    ]);
}

function handleGetActiveSessions($db) {
    $query = "
        SELECT 
            us.*,
            u.full_name as user_name
        FROM user_sessions us
        LEFT JOIN users u ON us.user_id = u.id
        WHERE us.last_activity >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY us.last_activity DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $sessions
    ]);
}

function handleTerminateSession($db, $input) {
    $sessionId = Security::sanitizeInput($input['session_id']);
    
    $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_id = ?");
    $success = $stmt->execute([$sessionId]);
    
    if (!$success) {
        throw new Exception('Failed to terminate session', 500);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Session terminated successfully'
    ]);
}

function handleTerminateAllSessions($db) {
    // Terminate all sessions except current user's
    $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id != ?");
    $success = $stmt->execute([$_SESSION['user_id']]);
    
    if (!$success) {
        throw new Exception('Failed to terminate sessions', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('all_sessions_terminated', 
        "Terminated by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'All sessions terminated successfully'
    ]);
}

function generateTempPassword($length = 12) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $password;
}

// ==================== PERFORMANCE ANALYTICS HANDLERS ====================

function handleGetPerformance($db) {
    $year = $_GET['year'] ?? date('Y');
    
    $query = "
        SELECT 
            u.id as user_id,
            u.full_name,
            u.username,
            u.role,
            u.employee_id,
            COALESCE(up.sales_total, 0) as sales_total,
            COALESCE(up.transactions_count, 0) as transactions_count,
            COALESCE(up.customers_served, 0) as customers_served,
            COALESCE(up.work_hours, 0) as work_hours,
            COALESCE(up.performance_score, 0) as performance_score,
            COALESCE(up.attendance_rate, 0) as attendance_rate,
            COALESCE(ug.sales_target, 0) as sales_target,
            COALESCE(ug.transactions_target, 0) as transactions_target,
            COALESCE(ug.customers_target, 0) as customers_target,
            COALESCE(ug.performance_target, 0) as performance_target,
            CASE 
                WHEN ug.sales_target > 0 THEN (COALESCE(up.sales_total, 0) / ug.sales_target * 100)
                ELSE 0 
            END as goal_achievement
        FROM users u
        LEFT JOIN user_yearly_performance up ON u.id = up.user_id AND up.year = ?
        LEFT JOIN user_yearly_goals ug ON u.id = ug.user_id AND ug.year = ?
        WHERE u.is_active = TRUE
        ORDER BY up.performance_score DESC, u.full_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$year, $year]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

function handleSetGoals($db, $input) {
    // Validate required fields
    if (empty($input['user_id']) || empty($input['year'])) {
        throw new Exception('User ID and year are required', 400);
    }
    
    $query = "
        INSERT INTO user_yearly_goals 
        (user_id, year, sales_target, transactions_target, customers_target, performance_target)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            sales_target = VALUES(sales_target),
            transactions_target = VALUES(transactions_target),
            customers_target = VALUES(customers_target),
            performance_target = VALUES(performance_target),
            updated_at = CURRENT_TIMESTAMP
    ";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        $input['user_id'],
        $input['year'],
        $input['sales_target'] ?? 0,
        $input['transactions_target'] ?? 0,
        $input['customers_target'] ?? 0,
        $input['performance_target'] ?? 0
    ]);
    
    if (!$success) {
        throw new Exception('Failed to set goals', 500);
    }
    
    // Log activity
    Security::logSecurityEvent('goals_set', 
        "Goals set for user {$input['user_id']} for year {$input['year']} by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Goals set successfully'
    ]);
}

// ==================== ATTENDANCE TRACKING HANDLERS ====================

function handleGetAttendance($db) {
    $month = $_GET['month'] ?? date('Y-m');
    
    $query = "
        SELECT 
            ua.*,
            u.full_name,
            u.employee_id,
            u.role
        FROM user_attendance ua
        JOIN users u ON ua.user_id = u.id
        WHERE DATE_FORMAT(ua.date, '%Y-%m') = ?
        ORDER BY ua.date DESC, u.full_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$month]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

function handleRecordAttendance($db, $input) {
    // Validate required fields
    if (empty($input['user_id']) || empty($input['date']) || empty($input['status'])) {
        throw new Exception('User ID, date, and status are required', 400);
    }
    
    // Calculate hours worked if check in and check out times are provided
    $hoursWorked = 0;
    if (!empty($input['check_in']) && !empty($input['check_out'])) {
        $checkIn = new DateTime($input['check_in']);
        $checkOut = new DateTime($input['check_out']);
        $diff = $checkOut->diff($checkIn);
        $hoursWorked = $diff->h + ($diff->i / 60);
    } else if (!empty($input['hours_worked'])) {
        $hoursWorked = $input['hours_worked'];
    }
    
    $query = "
        INSERT INTO user_attendance 
        (user_id, date, check_in, check_out, hours_worked, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            check_in = VALUES(check_in),
            check_out = VALUES(check_out),
            hours_worked = VALUES(hours_worked),
            status = VALUES(status),
            notes = VALUES(notes),
            updated_at = CURRENT_TIMESTAMP
    ";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        $input['user_id'],
        $input['date'],
        $input['check_in'] ?? null,
        $input['check_out'] ?? null,
        $hoursWorked,
        $input['status'],
        $input['notes'] ?? null
    ]);
    
    if (!$success) {
        throw new Exception('Failed to record attendance', 500);
    }
    
    // Update yearly performance data
    updateYearlyPerformance($db, $input['user_id'], $input['date']);
    
    // Log activity
    Security::logSecurityEvent('attendance_recorded', 
        "Attendance recorded for user {$input['user_id']} on {$input['date']} by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Attendance recorded successfully'
    ]);
}

function handleExportAttendance($db) {
    $month = $_GET['month'] ?? date('Y-m');
    
    $query = "
        SELECT 
            u.full_name as 'Employee Name',
            u.employee_id as 'Employee ID',
            ua.date as 'Date',
            ua.check_in as 'Check In',
            ua.check_out as 'Check Out',
            ua.hours_worked as 'Hours Worked',
            ua.status as 'Status',
            ua.notes as 'Notes'
        FROM user_attendance ua
        JOIN users u ON ua.user_id = u.id
        WHERE DATE_FORMAT(ua.date, '%Y-%m') = ?
        ORDER BY ua.date, u.full_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$month]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

// ==================== EVALUATIONS HANDLERS ====================

function handleGetEvaluations($db) {
    $year = $_GET['year'] ?? date('Y');
    
    $query = "
        SELECT 
            ue.*,
            u.full_name as employee_name,
            u.employee_id,
            evaluator.full_name as evaluator_name
        FROM user_evaluations ue
        JOIN users u ON ue.user_id = u.id
        JOIN users evaluator ON ue.evaluator_id = evaluator.id
        WHERE ue.year = ?
        ORDER BY ue.evaluation_date DESC, u.full_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$year]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

function handleSaveEvaluation($db, $input) {
    // Validate required fields
    if (empty($input['user_id']) || empty($input['evaluation_date']) || 
        empty($input['year']) || empty($input['overall_score'])) {
        throw new Exception('User ID, evaluation date, year, and overall score are required', 400);
    }
    
    $query = "
        INSERT INTO user_evaluations 
        (user_id, evaluator_id, evaluation_date, year, quarter, overall_score, 
         technical_skills, communication_skills, teamwork, punctuality, 
         customer_service, comments, recommendations)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        $input['user_id'],
        $_SESSION['user_id'], // Current user as evaluator
        $input['evaluation_date'],
        $input['year'],
        $input['quarter'] ?? null,
        $input['overall_score'],
        $input['technical_skills'] ?? null,
        $input['communication_skills'] ?? null,
        $input['teamwork'] ?? null,
        $input['punctuality'] ?? null,
        $input['customer_service'] ?? null,
        $input['comments'] ?? null,
        $input['recommendations'] ?? null
    ]);
    
    if (!$success) {
        throw new Exception('Failed to save evaluation', 500);
    }
    
    // Update yearly performance score
    updatePerformanceScore($db, $input['user_id'], $input['year'], $input['overall_score']);
    
    // Log activity
    Security::logSecurityEvent('evaluation_saved', 
        "Evaluation saved for user {$input['user_id']} by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluation saved successfully'
    ]);
}

// ==================== TRAINING HANDLERS ====================

function handleGetTraining($db) {
    $query = "
        SELECT 
            ut.*,
            u.full_name as employee_name,
            u.employee_id
        FROM user_training ut
        JOIN users u ON ut.user_id = u.id
        ORDER BY ut.start_date DESC, u.full_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

function handleSaveTraining($db, $input) {
    // Validate required fields
    if (empty($input['user_id']) || empty($input['training_name']) || 
        empty($input['training_type']) || empty($input['start_date'])) {
        throw new Exception('User ID, training name, type, and start date are required', 400);
    }
    
    $query = "
        INSERT INTO user_training 
        (user_id, training_name, training_type, provider, start_date, end_date, 
         completion_date, status, certificate_number, expiry_date, cost, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        $input['user_id'],
        $input['training_name'],
        $input['training_type'],
        $input['provider'] ?? null,
        $input['start_date'],
        $input['end_date'] ?? null,
        $input['completion_date'] ?? null,
        $input['status'] ?? 'enrolled',
        $input['certificate_number'] ?? null,
        $input['expiry_date'] ?? null,
        $input['cost'] ?? 0,
        $input['notes'] ?? null
    ]);
    
    if (!$success) {
        throw new Exception('Failed to save training record', 500);
    }
    
    // Log activity
    Security::logSecurityEvent('training_saved', 
        "Training record saved for user {$input['user_id']} by: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Training record saved successfully'
    ]);
}

function handleGetExpiringCertifications($db) {
    $query = "
        SELECT 
            ut.id,
            ut.training_name,
            ut.expiry_date,
            u.full_name as employee_name
        FROM user_training ut
        JOIN users u ON ut.user_id = u.id
        WHERE ut.expiry_date IS NOT NULL 
        AND ut.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND ut.status = 'completed'
        ORDER BY ut.expiry_date
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

// ==================== HELPER FUNCTIONS ====================

function updateYearlyPerformance($db, $userId, $date) {
    $year = date('Y', strtotime($date));
    
    // Calculate attendance rate for the year
    $attendanceQuery = "
        SELECT 
            COUNT(*) as total_days,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
            SUM(hours_worked) as total_hours
        FROM user_attendance 
        WHERE user_id = ? AND YEAR(date) = ?
    ";
    
    $stmt = $db->prepare($attendanceQuery);
    $stmt->execute([$userId, $year]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $attendanceRate = $attendance['total_days'] > 0 ? 
        ($attendance['present_days'] / $attendance['total_days']) * 100 : 0;
    
    // Update or insert yearly performance
    $updateQuery = "
        INSERT INTO user_yearly_performance 
        (user_id, year, work_hours, attendance_rate)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            work_hours = ?,
            attendance_rate = ?,
            updated_at = CURRENT_TIMESTAMP
    ";
    
    $stmt = $db->prepare($updateQuery);
    $stmt->execute([
        $userId, $year, 
        $attendance['total_hours'], $attendanceRate,
        $attendance['total_hours'], $attendanceRate
    ]);
}

function updatePerformanceScore($db, $userId, $year, $score) {
    $query = "
        INSERT INTO user_yearly_performance 
        (user_id, year, performance_score)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            performance_score = ?,
            updated_at = CURRENT_TIMESTAMP
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$userId, $year, $score, $score]);
}
