<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/session_fix.php';

// CORS headers
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

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $database = new Database();
    $db = $database->connect();
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'stats') {
                handleGetStats($db);
            } elseif (isset($_GET['id'])) {
                handleGetSupplier($db, $_GET['id']);
            } else {
                handleGetSuppliers($db);
            }
            break;
            
        case 'POST':
            // Allow admin and cashier to create suppliers
            if (!in_array($_SESSION['role'] ?? '', ['admin', 'cashier'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit();
            }
            handleCreateSupplier($db, $input);
            break;
            
        case 'PUT':
            // Allow admin and cashier to update suppliers
            if (!in_array($_SESSION['role'] ?? '', ['admin', 'cashier'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit();
            }
            handleUpdateSupplier($db, $_GET['id'], $input);
            break;
            
        case 'DELETE':
            // Allow admin and cashier to delete suppliers
            if (!in_array($_SESSION['role'] ?? '', ['admin', 'cashier'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit();
            }
            handleDeleteSupplier($db, $_GET['id']);
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('suppliers_api_error', 
        'Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | User ID: ' . ($_SESSION['user_id'] ?? 'null') . ' | Method: ' . $method . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine()
    );
    
    $statusCode = $e->getCode();
    if ($statusCode < 100 || $statusCode >= 600) {
        $statusCode = 500;
    }
    http_response_code($statusCode);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'code' => $e->getCode(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

function handleGetSuppliers($db) {
    $query = "
        SELECT 
            s.*,
            (
                SELECT MAX(po.created_at) 
                FROM purchase_orders po 
                WHERE po.supplier_id = s.id
            ) as last_order_date,
            (
                SELECT COUNT(*) 
                FROM purchase_orders po 
                WHERE po.supplier_id = s.id 
                AND po.status = 'pending'
            ) as pending_orders_count
        FROM suppliers s 
        WHERE s.is_deleted = FALSE
        ORDER BY s.company_name ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers
    ]);
}

function handleGetSupplier($db, $supplierId) {
    $supplierId = Security::sanitizeInput($supplierId);
    
    if (!is_numeric($supplierId)) {
        throw new Exception('Invalid supplier ID', 400);
    }
    
    $query = "
        SELECT 
            s.*,
            (
                SELECT MAX(po.created_at) 
                FROM purchase_orders po 
                WHERE po.supplier_id = s.id
            ) as last_order_date,
            (
                SELECT COUNT(*) 
                FROM purchase_orders po 
                WHERE po.supplier_id = s.id 
                AND po.status = 'pending'
            ) as pending_orders_count
        FROM suppliers s 
        WHERE s.id = ? AND s.is_deleted = FALSE
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        throw new Exception('Supplier not found', 404);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $supplier
    ]);
}

function handleGetStats($db) {
    // Total suppliers (excluding deleted)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM suppliers WHERE is_deleted = FALSE");
    $stmt->execute();
    $totalSuppliers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active suppliers (excluding deleted)
    $stmt = $db->prepare("SELECT COUNT(*) as active FROM suppliers WHERE status = 'active' AND is_deleted = FALSE");
    $stmt->execute();
    $activeSuppliers = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    // Pending orders
    $stmt = $db->prepare("SELECT COUNT(*) as pending FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE po.status = 'pending' AND s.is_deleted = FALSE");
    $stmt->execute();
    $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Monthly orders
    $stmt = $db->prepare("
        SELECT COUNT(*) as monthly 
        FROM purchase_orders po 
        JOIN suppliers s ON po.supplier_id = s.id 
        WHERE YEAR(po.created_at) = YEAR(CURDATE()) 
        AND MONTH(po.created_at) = MONTH(CURDATE())
        AND s.is_deleted = FALSE
    ");
    $stmt->execute();
    $monthlyOrders = $stmt->fetch(PDO::FETCH_ASSOC)['monthly'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => (int)$totalSuppliers,
            'active' => (int)$activeSuppliers,
            'pending_orders' => (int)$pendingOrders,
            'monthly_orders' => (int)$monthlyOrders
        ]
    ]);
}

function handleCreateSupplier($db, $input) {
    
    // Validate required fields
    $requiredFields = ['company_name', 'contact_person', 'phone', 'email', 'category', 'status'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("$field is required", 400);
        }
    }
    
    // Sanitize inputs
    $companyName = Security::sanitizeInput($input['company_name']);
    $contactPerson = Security::sanitizeInput($input['contact_person']);
    $phone = Security::sanitizeInput($input['phone']);
    $email = Security::sanitizeInput($input['email']);
    $category = Security::sanitizeInput($input['category']);
    $status = Security::sanitizeInput($input['status']);
    $address = isset($input['address']) ? Security::sanitizeInput($input['address']) : null;
    $creditTerms = isset($input['credit_terms']) ? intval($input['credit_terms']) : null;
    $taxId = isset($input['tax_id']) ? Security::sanitizeInput($input['tax_id']) : null;
    $notes = isset($input['notes']) ? Security::sanitizeInput($input['notes']) : null;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format', 400);
    }
    
    // Validate category
    $validCategories = ['medicines', 'equipment', 'supplies', 'other'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Invalid category', 400);
    }
    
    // Validate status
    $validStatuses = ['active', 'inactive'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status', 400);
    }
    
    // Check for duplicate email or company name
    $stmt = $db->prepare("SELECT id FROM suppliers WHERE email = ? OR company_name = ?");
    $stmt->execute([$email, $companyName]);
    if ($stmt->fetch()) {
        throw new Exception('Supplier with this email or company name already exists', 400);
    }
    
    // Insert supplier
    $query = "
        INSERT INTO suppliers (
            company_name, contact_person, phone, email, category, status,
            address, credit_terms, tax_id, notes, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        $companyName, $contactPerson, $phone, $email, $category, $status,
        $address, $creditTerms, $taxId, $notes
    ]);
    
    if (!$success) {
        throw new Exception('Failed to create supplier', 500);
    }
    
    $supplierId = $db->lastInsertId();
    
    // Log security event
    Security::logSecurityEvent('supplier_created', 
        "Supplier ID: $supplierId | Company: $companyName | User ID: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Supplier created successfully',
        'data' => ['id' => $supplierId]
    ]);
}

function handleUpdateSupplier($db, $supplierId, $input) {
    $supplierId = Security::sanitizeInput($supplierId);
    
    if (!is_numeric($supplierId)) {
        throw new Exception('Invalid supplier ID', 400);
    }
    
    // Check if supplier exists
    $stmt = $db->prepare("SELECT id FROM suppliers WHERE id = ?");
    $stmt->execute([$supplierId]);
    if (!$stmt->fetch()) {
        throw new Exception('Supplier not found', 404);
    }
    
    // Validate required fields
    $requiredFields = ['company_name', 'contact_person', 'phone', 'email', 'category', 'status'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("$field is required", 400);
        }
    }
    
    // Sanitize inputs
    $companyName = Security::sanitizeInput($input['company_name']);
    $contactPerson = Security::sanitizeInput($input['contact_person']);
    $phone = Security::sanitizeInput($input['phone']);
    $email = Security::sanitizeInput($input['email']);
    $category = Security::sanitizeInput($input['category']);
    $status = Security::sanitizeInput($input['status']);
    $address = isset($input['address']) ? Security::sanitizeInput($input['address']) : null;
    $creditTerms = isset($input['credit_terms']) ? intval($input['credit_terms']) : null;
    $taxId = isset($input['tax_id']) ? Security::sanitizeInput($input['tax_id']) : null;
    $notes = isset($input['notes']) ? Security::sanitizeInput($input['notes']) : null;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format', 400);
    }
    
    // Validate category
    $validCategories = ['medicines', 'equipment', 'supplies', 'other'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Invalid category', 400);
    }
    
    // Validate status
    $validStatuses = ['active', 'inactive'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status', 400);
    }
    
    // Check for duplicate email or company name (excluding current supplier)
    $stmt = $db->prepare("SELECT id FROM suppliers WHERE (email = ? OR company_name = ?) AND id != ?");
    $stmt->execute([$email, $companyName, $supplierId]);
    if ($stmt->fetch()) {
        throw new Exception('Supplier with this email or company name already exists', 400);
    }
    
    // Update supplier
    $query = "
        UPDATE suppliers SET 
            company_name = ?, contact_person = ?, phone = ?, email = ?, 
            category = ?, status = ?, address = ?, credit_terms = ?, 
            tax_id = ?, notes = ?, updated_at = NOW()
        WHERE id = ?
    ";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        $companyName, $contactPerson, $phone, $email, $category, $status,
        $address, $creditTerms, $taxId, $notes, $supplierId
    ]);
    
    if (!$success) {
        throw new Exception('Failed to update supplier', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('supplier_updated', 
        "Supplier ID: $supplierId | Company: $companyName | User ID: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Supplier updated successfully'
    ]);
}

function handleDeleteSupplier($db, $supplierId) {
    $supplierId = Security::sanitizeInput($supplierId);
    
    if (!is_numeric($supplierId)) {
        throw new Exception('Invalid supplier ID', 400);
    }
    
    // Check if supplier exists and is not already deleted
    $stmt = $db->prepare("SELECT company_name FROM suppliers WHERE id = ? AND is_deleted = FALSE");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        throw new Exception('Supplier not found', 404);
    }
    
    // Soft delete: mark as deleted instead of hard delete
    // This preserves all related data (medicines, purchase orders, payments, etc.) while hiding the supplier
    $stmt = $db->prepare("UPDATE suppliers SET is_deleted = TRUE, deleted_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$supplierId]);
    
    if (!$success) {
        throw new Exception('Failed to delete supplier', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('supplier_deleted', 
        "Supplier ID: $supplierId | Company: " . $supplier['company_name'] . " | User ID: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Supplier deleted successfully'
    ]);
}
?>
