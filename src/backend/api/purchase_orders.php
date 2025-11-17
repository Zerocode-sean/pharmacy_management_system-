<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/auth_middleware.php';

header('Content-Type: application/json');

// Apply security middleware
AuthMiddleware::checkAuth(true);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $database = new Database();
    $db = $database->connect();
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                handleGetPurchaseOrder($db, $_GET['id']);
            } else {
                handleGetPurchaseOrders($db);
            }
            break;
            
        case 'POST':
            AuthMiddleware::checkRole('admin');
            handleCreatePurchaseOrder($db, $input);
            break;
            
        case 'PUT':
            AuthMiddleware::checkRole('admin');
            handleUpdatePurchaseOrder($db, $_GET['id'], $input);
            break;
            
        case 'DELETE':
            AuthMiddleware::checkRole('admin');
            handleDeletePurchaseOrder($db, $_GET['id']);
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('purchase_orders_api_error', 
        'Error: ' . $e->getMessage() . ' | User ID: ' . ($_SESSION['user_id'] ?? 'null')
    );
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetPurchaseOrders($db) {
    $query = "
        SELECT 
            po.*,
            s.company_name as supplier_name,
            COUNT(poi.id) as item_count,
            SUM(poi.quantity * poi.unit_price) as total_amount
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
        GROUP BY po.id
        ORDER BY po.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $orders
    ]);
}

function handleGetPurchaseOrder($db, $orderId) {
    $orderId = Security::sanitizeInput($orderId);
    
    if (!is_numeric($orderId)) {
        throw new Exception('Invalid order ID', 400);
    }
    
    // Get order details
    $query = "
        SELECT 
            po.*,
            s.company_name as supplier_name,
            s.contact_person,
            s.phone,
            s.email
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Purchase order not found', 404);
    }
    
    // Get order items
    $itemQuery = "
        SELECT * FROM purchase_order_items 
        WHERE purchase_order_id = ?
        ORDER BY id
    ";
    
    $stmt = $db->prepare($itemQuery);
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $order['items'] = $items;
    
    echo json_encode([
        'success' => true,
        'data' => $order
    ]);
}

function handleCreatePurchaseOrder($db, $input) {
    // Validate required fields
    $requiredFields = ['supplier_id', 'order_date', 'expected_date', 'items'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("$field is required", 400);
        }
    }
    
    // Sanitize inputs
    $supplierId = intval($input['supplier_id']);
    $orderDate = Security::sanitizeInput($input['order_date']);
    $expectedDate = Security::sanitizeInput($input['expected_date']);
    $items = $input['items'];
    
    // Validate supplier exists
    $stmt = $db->prepare("SELECT id FROM suppliers WHERE id = ? AND status = 'active'");
    $stmt->execute([$supplierId]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid or inactive supplier', 400);
    }
    
    // Validate dates
    if (!validateDate($orderDate) || !validateDate($expectedDate)) {
        throw new Exception('Invalid date format', 400);
    }
    
    if (strtotime($expectedDate) <= strtotime($orderDate)) {
        throw new Exception('Expected date must be after order date', 400);
    }
    
    // Validate items
    if (empty($items) || !is_array($items)) {
        throw new Exception('At least one item is required', 400);
    }
    
    $totalAmount = 0;
    foreach ($items as $item) {
        if (empty($item['description']) || empty($item['quantity']) || empty($item['unit_price'])) {
            throw new Exception('All item fields are required', 400);
        }
        
        if (!is_numeric($item['quantity']) || !is_numeric($item['unit_price'])) {
            throw new Exception('Quantity and unit price must be numeric', 400);
        }
        
        if ($item['quantity'] <= 0 || $item['unit_price'] <= 0) {
            throw new Exception('Quantity and unit price must be positive', 400);
        }
        
        $totalAmount += $item['quantity'] * $item['unit_price'];
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Create purchase order
        $orderQuery = "
            INSERT INTO purchase_orders (
                supplier_id, order_date, expected_date, status, 
                total_amount, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW())
        ";
        
        $stmt = $db->prepare($orderQuery);
        $success = $stmt->execute([
            $supplierId, $orderDate, $expectedDate, $totalAmount, $_SESSION['user_id']
        ]);
        
        if (!$success) {
            throw new Exception('Failed to create purchase order', 500);
        }
        
        $orderId = $db->lastInsertId();
        
        // Add order items
        $itemQuery = "
            INSERT INTO purchase_order_items (
                purchase_order_id, description, quantity, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $db->prepare($itemQuery);
        
        foreach ($items as $item) {
            $description = Security::sanitizeInput($item['description']);
            $quantity = intval($item['quantity']);
            $unitPrice = floatval($item['unit_price']);
            $totalPrice = $quantity * $unitPrice;
            
            $success = $stmt->execute([
                $orderId, $description, $quantity, $unitPrice, $totalPrice
            ]);
            
            if (!$success) {
                throw new Exception('Failed to add order item', 500);
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Log security event
        Security::logSecurityEvent('purchase_order_created', 
            "Order ID: $orderId | Supplier ID: $supplierId | Total: $totalAmount | User ID: " . $_SESSION['user_id']
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Purchase order created successfully',
            'data' => ['id' => $orderId]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleUpdatePurchaseOrder($db, $orderId, $input) {
    $orderId = Security::sanitizeInput($orderId);
    
    if (!is_numeric($orderId)) {
        throw new Exception('Invalid order ID', 400);
    }
    
    // Check if order exists and can be updated
    $stmt = $db->prepare("SELECT id, status FROM purchase_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Purchase order not found', 404);
    }
    
    if ($order['status'] === 'completed' || $order['status'] === 'cancelled') {
        throw new Exception('Cannot update completed or cancelled orders', 400);
    }
    
    // Update allowed fields
    $allowedFields = ['status', 'expected_date'];
    $updates = [];
    $values = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $values[] = Security::sanitizeInput($input[$field]);
        }
    }
    
    if (empty($updates)) {
        throw new Exception('No valid fields to update', 400);
    }
    
    $updates[] = "updated_at = NOW()";
    $values[] = $orderId;
    
    $query = "UPDATE purchase_orders SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute($values);
    
    if (!$success) {
        throw new Exception('Failed to update purchase order', 500);
    }
    
    // Log security event
    Security::logSecurityEvent('purchase_order_updated', 
        "Order ID: $orderId | User ID: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase order updated successfully'
    ]);
}

function handleDeletePurchaseOrder($db, $orderId) {
    $orderId = Security::sanitizeInput($orderId);
    
    if (!is_numeric($orderId)) {
        throw new Exception('Invalid order ID', 400);
    }
    
    // Check if order exists
    $stmt = $db->prepare("SELECT id, status FROM purchase_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Purchase order not found', 404);
    }
    
    if ($order['status'] === 'completed') {
        throw new Exception('Cannot delete completed orders', 400);
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Delete order items first
        $stmt = $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?");
        $stmt->execute([$orderId]);
        
        // Delete order
        $stmt = $db->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $success = $stmt->execute([$orderId]);
        
        if (!$success) {
            throw new Exception('Failed to delete purchase order', 500);
        }
        
        // Commit transaction
        $db->commit();
        
        // Log security event
        Security::logSecurityEvent('purchase_order_deleted', 
            "Order ID: $orderId | User ID: " . $_SESSION['user_id']
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Purchase order deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
