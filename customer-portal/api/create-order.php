<?php
/**
 * Create Order API (Cash on Delivery)
 * Handles order creation without payment processing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests allowed'
    ]);
    exit();
}

/**
 * Generate order reference
 */
function generateOrderReference() {
    return 'ORD' . date('Ymd') . substr(time(), -4) . rand(10, 99);
}

/**
 * Log order
 */
function logOrder($level, $message, $data = null) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'data' => $data
    ];
    
    $log_file = __DIR__ . '/../../logs/cash-orders.log';
    @mkdir(dirname($log_file), 0777, true);
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

try {
    logOrder('INFO', 'Cash on Delivery order received');
    
    // Get input data
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No request data received');
    }
    
    $data = json_decode($input, true);
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    if (!isset($data['customer']) || !isset($data['items']) || !isset($data['total'])) {
        throw new Exception('Missing required fields');
    }
    
    $customer = $data['customer'];
    $items = $data['items'];
    $total = floatval($data['total']);
    
    // Validate customer data
    if (empty($customer['name']) || empty($customer['phone']) || empty($customer['address'])) {
        throw new Exception('Missing customer information');
    }
    
    // Validate items
    if (empty($items) || !is_array($items)) {
        throw new Exception('Order must contain at least one item');
    }
    
    // Generate order reference
    $order_reference = generateOrderReference();
    
    logOrder('INFO', 'Creating order', [
        'order_ref' => $order_reference,
        'customer' => $customer['name'],
        'items_count' => count($items),
        'total' => $total
    ]);
    
    // Store order in database
    try {
        if (file_exists('../../src/backend/config/database.php')) {
            require_once '../../src/backend/config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                // Insert order
                $stmt = $db->prepare("
                    INSERT INTO customer_orders (
                        order_reference, customer_name, customer_phone,
                        customer_email, customer_address, total_amount,
                        payment_method, payment_status, order_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'cash', 'pending', NOW(), 'pending')
                ");
                
                $stmt->execute([
                    $order_reference,
                    $customer['name'],
                    $customer['phone'],
                    $customer['email'] ?? '',
                    $customer['address'] . ', ' . ($customer['city'] ?? '') . ' ' . ($customer['zip'] ?? ''),
                    $total
                ]);
                
                $order_id = $db->lastInsertId();
                
                // Insert order items
                $item_stmt = $db->prepare("
                    INSERT INTO customer_order_items (
                        order_id, medicine_id, quantity, unit_price, total_price
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($items as $item) {
                    $medicine_id = $item['medicine_id'] ?? $item['id'] ?? null;
                    if ($medicine_id) {
                        $item_stmt->execute([
                            $order_id,
                            $medicine_id,
                            $item['quantity'],
                            $item['price'],
                            $item['price'] * $item['quantity']
                        ]);
                    }
                }
                
                logOrder('SUCCESS', 'Order stored in database', ['order_id' => $order_id]);
                
                // Success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Order placed successfully',
                    'order_reference' => $order_reference,
                    'order_id' => $order_id,
                    'total' => $total,
                    'payment_method' => 'Cash on Delivery',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
            } else {
                throw new Exception('Database connection failed');
            }
        } else {
            throw new Exception('Database configuration not found');
        }
        
    } catch (Exception $db_error) {
        logOrder('ERROR', 'Database error', ['error' => $db_error->getMessage()]);
        throw new Exception('Failed to store order: ' . $db_error->getMessage());
    }
    
} catch (Exception $e) {
    logOrder('ERROR', 'Order creation failed', ['error' => $e->getMessage()]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
