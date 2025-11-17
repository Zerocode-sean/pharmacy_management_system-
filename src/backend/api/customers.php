<?php
/**
 * Customers API Endpoint
 * Handles customer management operations
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/session_fix.php';
require_once '../config/cors.php';

header('Content-Type: application/json');

// Use session_fix to ensure same session as login
SessionCookieFixer::startSession();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
    exit();
}

// Role-based access control - Allow both admin and cashier
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'cashier'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Customer management requires cashier or admin role. Your role: ' . $userRole]);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($conn);
            break;
        case 'POST':
            handlePost($conn);
            break;
        case 'PUT':
            handlePut($conn);
            break;
        case 'DELETE':
            handleDelete($conn);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($conn) {
    try {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'list':
                getCustomers($conn);
                break;
            case 'details':
                getCustomerDetails($conn);
                break;
            case 'stats':
                getCustomerStats($conn);
                break;
            default:
                if (isset($_GET['id'])) {
                    getCustomerDetails($conn);
                } else {
                    getCustomers($conn);
                }
        }
        
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve customers: ' . $e->getMessage());
    }
}

function getCustomers($conn) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    
    $whereClause = "WHERE c.is_deleted = FALSE";
    $params = [];
    
    // Apply search filter
    if (!empty($search)) {
        $whereClause .= " AND (c.name LIKE :search OR c.phone LIKE :search OR c.email LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    // Apply status filter
    switch ($filter) {
        case 'active':
            $whereClause .= " AND EXISTS (SELECT 1 FROM sales s WHERE s.customer_id = c.id AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
            break;
        case 'inactive':
            $whereClause .= " AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.customer_id = c.id AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY))";
            break;
        case 'new':
            $whereClause .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
    
    $query = "SELECT c.*, 
                     COUNT(s.id) as purchase_count,
                     COALESCE(SUM(s.final_amount), 0) as total_purchases,
                     MAX(s.sale_date) as last_purchase
              FROM customers c
              LEFT JOIN sales s ON c.id = s.customer_id
              $whereClause
              GROUP BY c.id
              ORDER BY c.name
              LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $customers = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM customers c $whereClause";
    $countStmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function getCustomerDetails($conn) {
    $customerId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$customerId) {
        throw new Exception('Customer ID is required');
    }
    
    // Get customer basic info
    $query = "SELECT c.*,
                     COUNT(s.id) as purchase_count,
                     COALESCE(SUM(s.final_amount), 0) as total_spent,
                     COALESCE(AVG(s.final_amount), 0) as average_purchase,
                     MAX(s.sale_date) as last_purchase
              FROM customers c
              LEFT JOIN sales s ON c.id = s.customer_id
              WHERE c.id = :customer_id
              GROUP BY c.id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':customer_id', $customerId);
    $stmt->execute();
    
    $customer = $stmt->fetch();
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        return;
    }
    
    // Get recent purchases
    $recentQuery = "SELECT s.id, s.final_amount, s.sale_date,
                           COUNT(si.id) as item_count
                    FROM sales s
                    LEFT JOIN sale_items si ON s.id = si.sale_id
                    WHERE s.customer_id = :customer_id
                    GROUP BY s.id
                    ORDER BY s.sale_date DESC
                    LIMIT 10";
    
    $recentStmt = $conn->prepare($recentQuery);
    $recentStmt->bindParam(':customer_id', $customerId);
    $recentStmt->execute();
    
    $customer['recent_purchases'] = $recentStmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $customer]);
}

function getCustomerStats($conn) {
    $stats = [];
    
    // Total customers
    $query = "SELECT COUNT(*) as total FROM customers";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_customers'] = $stmt->fetch()['total'];
    
    // New customers this month
    $query = "SELECT COUNT(*) as new_month FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['new_this_month'] = $stmt->fetch()['new_month'];
    
    // Active customers (purchased in last 30 days)
    $query = "SELECT COUNT(DISTINCT customer_id) as active FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['active_customers'] = $stmt->fetch()['active'];
    
    // Top customer by spending
    $query = "SELECT c.name, SUM(s.final_amount) as total_spent
              FROM customers c
              JOIN sales s ON c.id = s.customer_id
              GROUP BY c.id, c.name
              ORDER BY total_spent DESC
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $topCustomer = $stmt->fetch();
    $stats['top_customer'] = $topCustomer ? $topCustomer['name'] : 'None';
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function handlePost($conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($input['name'])) {
            throw new Exception('Customer name is required');
        }
        
        // Validate email format if provided
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check for duplicate email
        if (!empty($input['email'])) {
            $checkQuery = "SELECT id FROM customers WHERE email = :email";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bindParam(':email', $input['email']);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                throw new Exception('Email address already exists');
            }
        }
        
        $query = "INSERT INTO customers (name, phone, email, address, date_of_birth) 
                 VALUES (:name, :phone, :email, :address, :date_of_birth)";
        
        $stmt = $conn->prepare($query);
        
        $stmt->bindParam(':name', $input['name']);
        $stmt->bindParam(':phone', $input['phone']);
        $stmt->bindParam(':email', $input['email']);
        $stmt->bindParam(':address', $input['address']);
        $stmt->bindParam(':date_of_birth', $input['date_of_birth']);
        
        if ($stmt->execute()) {
            $customerId = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Customer added successfully',
                'customer_id' => $customerId
            ]);
        } else {
            throw new Exception('Failed to add customer');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePut($conn) {
    try {
        $customerId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$customerId) {
            throw new Exception('Customer ID is required');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($input['name'])) {
            throw new Exception('Customer name is required');
        }
        
        // Validate email format if provided
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check for duplicate email (exclude current customer)
        if (!empty($input['email'])) {
            $checkQuery = "SELECT id FROM customers WHERE email = :email AND id != :customer_id";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bindParam(':email', $input['email']);
            $checkStmt->bindParam(':customer_id', $customerId);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                throw new Exception('Email address already exists');
            }
        }
        
        $query = "UPDATE customers SET 
                 name = :name, phone = :phone, email = :email, 
                 address = :address, city = :city, zip_code = :zip_code,
                 updated_at = NOW()
                 WHERE id = :customer_id AND is_deleted = FALSE";
        
        $stmt = $conn->prepare($query);
        
        $stmt->bindParam(':customer_id', $customerId);
        $stmt->bindParam(':name', $input['name']);
        $stmt->bindParam(':phone', $input['phone']);
        $stmt->bindParam(':email', $input['email']);
        $stmt->bindParam(':address', $input['address']);
        $stmt->bindParam(':city', $input['city']);
        $stmt->bindParam(':zip_code', $input['zip_code']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
        } else {
            throw new Exception('Failed to update customer');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDelete($conn) {
    try {
        $customerId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$customerId) {
            throw new Exception('Customer ID is required');
        }

        // Soft delete: mark as deleted instead of hard delete
        // This preserves all related data (orders, sales, etc.) while hiding the customer
        $query = "UPDATE customers SET is_deleted = TRUE, deleted_at = NOW() WHERE id = :customer_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':customer_id', $customerId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
        } else {
            throw new Exception('Failed to delete customer');
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
