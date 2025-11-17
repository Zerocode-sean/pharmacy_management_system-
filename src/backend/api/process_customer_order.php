<?php
/**
 * CUSTOMER ORDER TO SALES PROCESSOR
 * ===================================
 * Converts approved customer portal orders into POS sales
 * Updates stock, creates sale records, links order to sale
 * 
 * Actions:
 * - GET ?action=pending_orders - List orders awaiting processing
 * - POST ?action=approve_order - Convert order to sale
 * - POST ?action=reject_order - Reject/cancel order
 * - GET ?action=order_details&id=X - Get full order details
 */

// Session and CORS setup
require_once '../config/session_fix.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
require_once '../config/database.php';

// Start session using SessionCookieFixer to match login session
SessionCookieFixer::startSession();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only admin and pharmacist can process orders
if (!in_array($_SESSION['role'], ['admin', 'pharmacist'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'pending_orders';

try {
    switch ($action) {
        case 'pending_orders':
            getPendingOrders($conn);
            break;
            
        case 'order_details':
            getOrderDetails($conn);
            break;
            
        case 'approve_order':
            approveOrder($conn);
            break;
            
        case 'reject_order':
            rejectOrder($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * GET PENDING ORDERS
 * List all customer orders that are confirmed but not yet processed
 */
function getPendingOrders($conn) {
    $query = "
        SELECT 
            co.id,
            co.order_number,
            co.order_date,
            co.customer_id,
            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            co.subtotal,
            co.tax,
            co.discount,
            co.delivery_fee,
            co.total_amount,
            co.payment_method,
            co.payment_status,
            co.status,
            co.delivery_address,
            co.delivery_city,
            co.delivery_postal_code,
            COUNT(coi.id) AS item_count,
            co.created_at
        FROM customer_orders co
        LEFT JOIN customers c ON co.customer_id = c.id
        LEFT JOIN customer_order_items coi ON co.id = coi.order_id
        WHERE co.status IN ('confirmed', 'payment_pending') 
        AND co.sale_id IS NULL
        GROUP BY co.id
        ORDER BY co.order_date DESC, co.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
}

/**
 * GET ORDER DETAILS
 * Full details of specific order including items
 */
function getOrderDetails($conn) {
    $order_id = $_GET['id'] ?? null;
    
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }
    
    // Get order header
    $query = "
        SELECT 
            co.*,
            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            c.address AS customer_address
        FROM customer_orders co
        LEFT JOIN customers c ON co.customer_id = c.id
        WHERE co.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }
    
    // Get order items
    $query = "
        SELECT 
            coi.*,
            m.name AS medicine_name,
            m.generic_name,
            m.manufacturer,
            m.stock_quantity AS available_stock,
            m.unit_price AS current_price,
            m.image_url,
            cat.name AS category_name
        FROM customer_order_items coi
        LEFT JOIN medicines m ON coi.medicine_id = m.id
        LEFT JOIN categories cat ON m.category_id = cat.id
        WHERE coi.order_id = ?
        ORDER BY coi.id
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check stock availability
    $stock_issues = [];
    foreach ($items as $item) {
        if ($item['quantity'] > $item['available_stock']) {
            $stock_issues[] = [
                'medicine' => $item['medicine_name'],
                'required' => $item['quantity'],
                'available' => $item['available_stock'],
                'shortage' => $item['quantity'] - $item['available_stock']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'stock_issues' => $stock_issues,
        'can_process' => count($stock_issues) === 0
    ]);
}

/**
 * APPROVE ORDER
 * Convert customer order to POS sale
 * - Create sale record
 * - Create sale_items
 * - Reduce stock
 * - Update order status
 * - Link order to sale
 */
function approveOrder($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;
    $notes = $input['notes'] ?? '';
    
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }
    
    $conn->beginTransaction();
    
    try {
        // 1. Get order details
        $stmt = $conn->prepare("SELECT * FROM customer_orders WHERE id = ? AND sale_id IS NULL");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found or already processed');
        }
        
        // 2. Get order items
        $stmt = $conn->prepare("
            SELECT 
                coi.*,
                m.stock_quantity,
                m.unit_price AS current_price
            FROM customer_order_items coi
            LEFT JOIN medicines m ON coi.medicine_id = m.id
            WHERE coi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Verify stock availability
        foreach ($items as $item) {
            if ($item['quantity'] > $item['stock_quantity']) {
                throw new Exception("Insufficient stock for {$item['medicine_id']}. Required: {$item['quantity']}, Available: {$item['stock_quantity']}");
            }
        }
        
        // 4. Create sale record
        $sale_query = "
            INSERT INTO sales (
                customer_id,
                sale_date,
                subtotal,
                tax_amount,
                discount_amount,
                final_amount,
                payment_method,
                payment_status,
                served_by,
                notes,
                created_at
            ) VALUES (?, NOW(), ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
        ";
        
        $stmt = $conn->prepare($sale_query);
        $stmt->execute([
            $order['customer_id'],
            $order['subtotal'],
            $order['tax'] ?? 0,
            $order['discount'] ?? 0,
            $order['total_amount'],
            $order['payment_method'] ?? 'mpesa',
            $_SESSION['user_id'],
            "Customer Order #{$order['order_number']}" . ($notes ? " - $notes" : "")
        ]);
        
        $sale_id = $conn->lastInsertId();
        
        // 5. Create sale items and update stock
        $sale_item_query = "
            INSERT INTO sale_items (
                sale_id,
                medicine_id,
                quantity,
                unit_price,
                cost_price,
                subtotal
            ) VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $update_stock_query = "
            UPDATE medicines 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ?
        ";
        
        $stock_movement_query = "
            INSERT INTO stock_movements (
                medicine_id,
                movement_type,
                quantity,
                reference_type,
                reference_id,
                notes,
                created_by
            ) VALUES (?, 'out', ?, 'sale', ?, ?, ?)
        ";
        
        foreach ($items as $item) {
            // Create sale item
            $stmt = $conn->prepare($sale_item_query);
            $stmt->execute([
                $sale_id,
                $item['medicine_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['cost_price'] ?? 0,
                $item['total_price']
            ]);
            
            // Update stock
            $stmt = $conn->prepare($update_stock_query);
            $stmt->execute([$item['quantity'], $item['medicine_id']]);
            
            // Record stock movement
            $stmt = $conn->prepare($stock_movement_query);
            $stmt->execute([
                $item['medicine_id'],
                $item['quantity'],
                $sale_id,
                "Customer Order #{$order['order_number']}",
                $_SESSION['user_id']
            ]);
        }
        
        // 6. Update order status and link to sale
        $update_order_query = "
            UPDATE customer_orders 
            SET 
                status = 'processing',
                payment_status = 'paid',
                sale_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($update_order_query);
        $stmt->execute([$sale_id, $order_id]);
        
        // 7. Log activity
        $log_query = "
            INSERT INTO user_activity (
                user_id,
                activity_type,
                description,
                ip_address
            ) VALUES (?, 'order_approval', ?, ?)
        ";
        
        $stmt = $conn->prepare($log_query);
        $stmt->execute([
            $_SESSION['user_id'],
            "Approved customer order #{$order['order_number']} and created sale #{$sale_id}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order approved and converted to sale successfully',
            'sale_id' => $sale_id,
            'order_id' => $order_id,
            'order_number' => $order['order_number']
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * REJECT ORDER
 * Cancel/reject customer order
 */
function rejectOrder($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;
    $reason = $input['reason'] ?? 'No reason provided';
    
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }
    
    try {
        $query = "
            UPDATE customer_orders 
            SET 
                status = 'cancelled',
                notes = CONCAT(COALESCE(notes, ''), '\nRejected by admin: ', ?),
                updated_at = NOW()
            WHERE id = ? AND sale_id IS NULL
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$reason, $order_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Order not found or already processed');
        }
        
        // Log activity
        $log_query = "
            INSERT INTO user_activity (
                user_id,
                activity_type,
                description,
                ip_address
            ) VALUES (?, 'order_rejection', ?, ?)
        ";
        
        $stmt = $conn->prepare($log_query);
        $stmt->execute([
            $_SESSION['user_id'],
            "Rejected customer order #{$order_id}: $reason",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Order rejected successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
