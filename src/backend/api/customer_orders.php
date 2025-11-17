<?php
/**
 * CUSTOMER ORDERS API
 * ==================
 * View and manage customer orders from the customer portal
 * 
 * Actions:
 * - GET - List all customer orders
 * - GET ?id=X - Get specific order details with items
 * - POST ?action=process - Mark order as processing
 * - POST ?action=cancel - Cancel an order
 * - POST ?action=complete - Mark order as completed
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/session_fix.php';
require_once '../config/cors.php';

header('Content-Type: application/json');

// Use session_fix to ensure same session as login
SessionCookieFixer::startSession();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
    exit();
}

// Only admin, pharmacist, and cashier can view orders
if (!in_array($_SESSION['role'] ?? '', ['admin', 'pharmacist', 'cashier'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    $database = new Database();
    $db = $database->connect();
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getOrderDetails($db, $_GET['id']);
            } else {
                getAllOrders($db);
            }
            break;
            
        case 'POST':
            if ($action === 'process') {
                processOrder($db);
            } elseif ($action === 'cancel') {
                cancelOrder($db);
            } elseif ($action === 'complete') {
                completeOrder($db);
            } else {
                throw new Exception('Invalid action', 400);
            }
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('customer_orders_api_error', 
        'Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | User ID: ' . ($_SESSION['user_id'] ?? 'null') . ' | Method: ' . $method
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

/**
 * GET ALL ORDERS
 * List all customer orders with customer info
 */
function getAllOrders($db) {
    $query = "
        SELECT 
            co.id,
            co.order_reference,
            co.customer_id,
            co.order_date,
            co.total_amount,
            co.final_amount,
            co.discount_amount,
            co.tax_amount,
            co.shipping_amount,
            co.payment_method,
            co.payment_status,
            co.status,
            co.delivery_method,
            co.delivery_address,
            co.delivery_city,
            co.delivery_zip,
            co.notes,
            co.created_at,
            co.updated_at,
            co.completed_at,
            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            c.address AS customer_address,
            COUNT(coi.id) AS items_count,
            u.full_name AS processed_by_name
        FROM customer_orders co
        LEFT JOIN customers c ON co.customer_id = c.id
        LEFT JOIN customer_order_items coi ON co.id = coi.order_id
        LEFT JOIN users u ON co.processed_by = u.id
        GROUP BY co.id
        ORDER BY co.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format amounts
    foreach ($orders as &$order) {
        $order['total_amount'] = number_format((float)$order['total_amount'], 2, '.', '');
        $order['final_amount'] = number_format((float)($order['final_amount'] ?? $order['total_amount']), 2, '.', '');
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
}

/**
 * GET ORDER DETAILS
 * Get full order details including items
 */
function getOrderDetails($db, $orderId) {
    $orderId = Security::sanitizeInput($orderId);
    
    if (!is_numeric($orderId)) {
        throw new Exception('Invalid order ID', 400);
    }
    
    // Get order header
    $query = "
        SELECT 
            co.*,
            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            c.address AS customer_address,
            c.city AS customer_city,
            c.zip AS customer_zip,
            u.full_name AS processed_by_name
        FROM customer_orders co
        LEFT JOIN customers c ON co.customer_id = c.id
        LEFT JOIN users u ON co.processed_by = u.id
        WHERE co.id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found', 404);
    }
    
    // Get order items
    $query = "
        SELECT 
            coi.*,
            m.name AS medicine_name,
            m.generic_name,
            m.brand,
            m.stock_quantity AS available_stock
        FROM customer_order_items coi
        LEFT JOIN medicines m ON coi.medicine_id = m.id
        WHERE coi.order_id = ?
        ORDER BY coi.id
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format amounts
    $order['total_amount'] = number_format((float)$order['total_amount'], 2, '.', '');
    $order['final_amount'] = number_format((float)($order['final_amount'] ?? $order['total_amount']), 2, '.', '');
    
    foreach ($items as &$item) {
        $item['unit_price'] = number_format((float)$item['unit_price'], 2, '.', '');
        $item['total_price'] = number_format((float)$item['total_price'], 2, '.', '');
    }
    
    // Get payment transaction if exists
    $query = "SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'payment' => $payment
    ]);
}

/**
 * PROCESS ORDER
 * Mark order as processing
 */
function processOrder($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['order_id'] ?? null;
    
    if (!$orderId || !is_numeric($orderId)) {
        throw new Exception('Invalid order ID', 400);
    }
    
    // Check if order exists
    $stmt = $db->prepare("SELECT status FROM customer_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found', 404);
    }
    
    if ($order['status'] === 'completed') {
        throw new Exception('Order is already completed', 400);
    }
    
    if ($order['status'] === 'cancelled') {
        throw new Exception('Cannot process cancelled order', 400);
    }
    
    // Update order status
    $stmt = $db->prepare("
        UPDATE customer_orders 
        SET status = 'processing', 
            processed_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $orderId]);
    
    // Log activity
    Security::logSecurityEvent('customer_order_processed', 
        "Order ID: $orderId | User ID: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Order marked as processing'
    ]);
}

/**
 * CANCEL ORDER
 * Cancel an order and restore stock
 */
function cancelOrder($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['order_id'] ?? null;
    
    if (!$orderId || !is_numeric($orderId)) {
        throw new Exception('Invalid order ID', 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Check if order exists
        $stmt = $db->prepare("SELECT status FROM customer_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found', 404);
        }
        
        if ($order['status'] === 'completed') {
            throw new Exception('Cannot cancel completed order', 400);
        }
        
        if ($order['status'] === 'cancelled') {
            throw new Exception('Order is already cancelled', 400);
        }
        
        // Restore stock for all items
        $stmt = $db->prepare("SELECT medicine_id, quantity FROM customer_order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $stmt = $db->prepare("UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['medicine_id']]);
        }
        
        // Update order status
        $stmt = $db->prepare("
            UPDATE customer_orders 
            SET status = 'cancelled',
                processed_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $orderId]);
        
        $db->commit();
        
        // Log activity
        Security::logSecurityEvent('customer_order_cancelled', 
            "Order ID: $orderId | User ID: " . $_SESSION['user_id']
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Order cancelled and stock restored'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * COMPLETE ORDER
 * Mark order as completed
 */
function completeOrder($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['order_id'] ?? null;
    
    if (!$orderId || !is_numeric($orderId)) {
        throw new Exception('Invalid order ID', 400);
    }
    
    // Check if order exists and is being processed
    $stmt = $db->prepare("SELECT status FROM customer_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found', 404);
    }
    
    if ($order['status'] === 'completed') {
        throw new Exception('Order is already completed', 400);
    }
    
    if ($order['status'] === 'cancelled') {
        throw new Exception('Cannot complete cancelled order', 400);
    }
    
    // Update order status
    $stmt = $db->prepare("
        UPDATE customer_orders 
        SET status = 'completed',
            processed_by = ?,
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $orderId]);
    
    // Log activity
    Security::logSecurityEvent('customer_order_completed', 
        "Order ID: $orderId | User ID: " . $_SESSION['user_id']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Order marked as completed'
    ]);
}
?>
