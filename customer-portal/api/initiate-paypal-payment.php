<?php
/**
 * PayPal Payment Initialization API
 * Handles PayPal payment initiation for customer orders
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
 * Log transaction
 */
function logPayPalTransaction($level, $message, $data = null) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'data' => $data
    ];
    
    $log_file = __DIR__ . '/../../logs/paypal-transactions.log';
    @mkdir(dirname($log_file), 0777, true);
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Generate order reference
 */
function generateOrderReference() {
    return 'ORD' . date('Ymd') . substr(time(), -4) . rand(10, 99);
}

try {
    logPayPalTransaction('INFO', 'PayPal payment request received');
    
    // Get and validate input data
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No request data received');
    }
    
    $data = json_decode($input, true);
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $required_fields = ['customer', 'items', 'total'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Extract customer data
    $customer = $data['customer'];
    if (empty($customer['name']) || empty($customer['phone'])) {
        throw new Exception('Missing customer information');
    }
    
    // Validate items
    $items = $data['items'];
    if (empty($items) || !is_array($items)) {
        throw new Exception('Order must contain at least one item');
    }
    
    // Calculate total
    $total = floatval($data['total']);
    if ($total <= 0) {
        throw new Exception('Invalid order total');
    }
    
    // Generate order reference
    $order_reference = generateOrderReference();
    
    // Prepare PayPal payment data
    $payment_data = [
        'amount' => $total,
        'order_reference' => $order_reference,
        'description' => 'Medicine Order - ' . count($items) . ' items',
        'items' => $items
    ];
    
    logPayPalTransaction('INFO', 'Initiating PayPal payment', $payment_data);
    
    // Load PayPal handler
    require_once __DIR__ . '/paypal-handler.php';
    
    $result = initiate_paypal_payment($payment_data);
    
    if ($result['success']) {
        // Store order in database (optional)
        try {
            if (file_exists('../../src/backend/config/database.php')) {
                require_once '../../src/backend/config/database.php';
                $database = new Database();
                $db = $database->getConnection();
                
                if ($db) {
                    $stmt = $db->prepare("
                        INSERT INTO customer_orders (
                            order_reference, customer_name, customer_phone,
                            customer_email, customer_address, total_amount,
                            payment_method, payment_status, order_date, status
                        ) VALUES (?, ?, ?, ?, ?, ?, 'paypal', 'pending', NOW(), 'pending')
                    ");
                    
                    $stmt->execute([
                        $order_reference,
                        $customer['name'],
                        $customer['phone'],
                        $customer['email'] ?? '',
                        $customer['address'],
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
                    
                    logPayPalTransaction('INFO', 'Order stored in database', ['order_id' => $order_id]);
                }
            }
        } catch (Exception $db_error) {
            logPayPalTransaction('ERROR', 'Database error', ['error' => $db_error->getMessage()]);
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'order_reference' => $order_reference,
            'order_id' => $result['order_id'],
            'approval_url' => $result['approval_url'] ?? null,
            'amount_kes' => $total,
            'amount_usd' => $result['amount_usd'] ?? null,
            'test_mode' => $result['test_mode'] ?? false,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        logPayPalTransaction('SUCCESS', 'PayPal payment initiated', [
            'order_ref' => $order_reference,
            'order_id' => $result['order_id']
        ]);
        
    } else {
        throw new Exception($result['message'] ?? 'PayPal payment initiation failed');
    }
    
} catch (Exception $e) {
    logPayPalTransaction('ERROR', 'PayPal payment failed', ['error' => $e->getMessage()]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
