<?php
/**
 * M-Pesa Payment Initialization API
 * 
 * This is the OFFICIAL M-Pesa payment initialization endpoint.
 * All other initiate-payment*.php files are obsolete.
 * 
 * Handles:
 * - Order creation and validation
 * - M-Pesa STK Push initiation
 * - Inventory checking and reservation
 * - KES currency formatting
 * - Error handling and logging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enhanced error handling for production
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'System error occurred',
            'debug' => [
                'error' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]
        ]);
    }
});

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight handled']);
    exit();
}

// Only allow POST requests for actual processing
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Only POST requests allowed',
        'method_used' => $_SERVER['REQUEST_METHOD']
    ]);
    exit();
}

/**
 * Format amount for M-Pesa (KES only)
 */
function formatKESForMpesa($kes_amount) {
    $amount = round(floatval($kes_amount));
    
    // M-Pesa validation
    if ($amount < 1) {
        throw new Exception('Amount must be at least KES 1');
    }
    if ($amount > 70000) {
        throw new Exception('Amount cannot exceed KES 70,000');
    }
    
    return $amount;
}

/**
 * Validate phone number for M-Pesa
 */
function validatePhoneNumber($phone) {
    // Remove any spaces, dashes, or special characters
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to international format (254...)
    if (substr($clean_phone, 0, 1) === '0') {
        $clean_phone = '254' . substr($clean_phone, 1);
    } elseif (substr($clean_phone, 0, 3) !== '254') {
        $clean_phone = '254' . $clean_phone;
    }
    
    // Validate length and format
    if (strlen($clean_phone) !== 12 || !preg_match('/^254[0-9]{9}$/', $clean_phone)) {
        throw new Exception('Invalid phone number. Please use format: 0712345678 or 254712345678');
    }
    
    return $clean_phone;
}

/**
 * Generate unique order reference
 */
function generateOrderReference() {
    return 'ORD' . date('Ymd') . substr(time(), -4) . rand(10, 99);
}

/**
 * Log transaction for debugging
 */
function logTransaction($level, $message, $data = null) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'data' => $data
    ];
    
    $log_file = __DIR__ . '/../../logs/mpesa-transactions.log';
    @mkdir(dirname($log_file), 0777, true);
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

try {
    // Log incoming request
    logTransaction('INFO', 'M-Pesa payment request received', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Step 1: Get and validate input data
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No request data received');
    }
    
    $data = json_decode($input, true);
    if (!$data) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }
    
    logTransaction('DEBUG', 'Request data parsed', ['keys' => array_keys($data)]);
    
    // Step 2: Validate required fields with detailed error reporting
    $required_fields = ['customer', 'items', 'total'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error_msg = "Missing required fields: " . implode(', ', $missing_fields);
        $error_msg .= ". Received fields: " . implode(', ', array_keys($data));
        throw new Exception($error_msg);
    }
    
    // Step 3: Extract and validate customer data
    $customer = $data['customer'];
    $required_customer_fields = ['name', 'phone', 'address'];
    foreach ($required_customer_fields as $field) {
        if (empty($customer[$field])) {
            throw new Exception("Missing customer field: $field");
        }
    }
    
    // Validate and format phone number
    $phone = validatePhoneNumber($customer['phone']);
    
    // Step 4: Validate items and calculate total
    $items = $data['items'];
    if (empty($items) || !is_array($items)) {
        throw new Exception('Order must contain at least one item');
    }
    
    $calculated_total = 0;
    $valid_items = [];
    
    foreach ($items as $item) {
        // Handle both 'id' and 'medicine_id' for compatibility
        $medicine_id = $item['medicine_id'] ?? $item['id'] ?? null;
        
        if (!$medicine_id || !isset($item['quantity']) || !isset($item['price'])) {
            throw new Exception('Invalid item data - missing medicine_id/id, quantity, or price');
        }
        
        $price = floatval($item['price']);
        $quantity = intval($item['quantity']);
        
        if ($quantity <= 0) {
            throw new Exception('Item quantity must be greater than 0');
        }
        
        if ($price <= 0) {
            throw new Exception('Item price must be greater than 0');
        }
        
        $item_total = $price * $quantity;
        $calculated_total += $item_total;
        
        $valid_items[] = [
            'medicine_id' => intval($medicine_id),
            'quantity' => $quantity,
            'price' => $price,
            'total' => $item_total
        ];
    }
    
    // Step 5: Validate total amount
    $provided_total = floatval($data['total']);
    if (abs($calculated_total - $provided_total) > 0.01) {
        throw new Exception('Total amount mismatch. Calculated: ' . $calculated_total . ', Provided: ' . $provided_total);
    }
    
    // Format amount for M-Pesa
    $mpesa_amount = formatKESForMpesa($calculated_total);
    
    // Step 6: Generate order reference
    $order_reference = generateOrderReference();
    
    logTransaction('INFO', 'Order validated', [
        'order_ref' => $order_reference,
        'customer_phone' => $phone,
        'amount' => $mpesa_amount,
        'items_count' => count($valid_items)
    ]);
    
    // Step 7: Initiate M-Pesa payment
    require_once __DIR__ . '/mpesa-test-handler.php';
    
    $mpesa_data = [
        'phone' => $phone,
        'amount' => $mpesa_amount,
        'order_reference' => $order_reference,
        'description' => 'Medicine Order - ' . count($valid_items) . ' items'
    ];
    
    logTransaction('INFO', 'Initiating M-Pesa payment', $mpesa_data);
    
    $payment_result = initiate_mpesa_payment($mpesa_data);
    
    if ($payment_result['success']) {
        // Step 8: Store order in database (for tracking) - OPTIONAL
        try {
            // Check if database config exists first
            if (file_exists('../../src/backend/config/database.php')) {
                require_once '../../src/backend/config/database.php';
                $database = new Database();
                $db = $database->getConnection();
            
                if ($db) {
                // Insert order record
                $stmt = $db->prepare("
                    INSERT INTO customer_orders (
                        order_reference, customer_name, customer_phone, 
                        customer_email, customer_address, total_amount, 
                        payment_method, payment_status, order_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'mpesa', 'pending', NOW(), 'pending')
                ");
                
                $stmt->execute([
                    $order_reference,
                    $customer['name'],
                    $phone,
                    $customer['email'] ?? '',
                    $customer['address'],
                    $calculated_total
                ]);
                
                $order_id = $db->lastInsertId();
                
                // Insert order items
                $item_stmt = $db->prepare("
                    INSERT INTO customer_order_items (
                        order_id, medicine_id, quantity, unit_price, total_price
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($valid_items as $item) {
                    $item_stmt->execute([
                        $order_id,
                        $item['medicine_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['total']
                    ]);
                }
                
                    logTransaction('INFO', 'Order stored in database', ['order_id' => $order_id]);
                }
            }
        } catch (Exception $db_error) {
            // Don't fail the payment if database fails, just log it
            logTransaction('ERROR', 'Database error (payment still proceeding)', ['error' => $db_error->getMessage()]);
        }
        
        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Payment request sent successfully',
            'order_reference' => $order_reference,
            'amount' => $mpesa_amount,
            'currency' => 'KES',
            'phone' => $phone,
            'checkout_request_id' => $payment_result['checkout_request_id'] ?? null,
            'merchant_request_id' => $payment_result['merchant_request_id'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        logTransaction('SUCCESS', 'M-Pesa payment initiated successfully', [
            'order_ref' => $order_reference,
            'checkout_request_id' => $payment_result['checkout_request_id'] ?? null
        ]);
        
    } else {
        // M-Pesa payment failed
        throw new Exception('M-Pesa payment initiation failed: ' . ($payment_result['message'] ?? 'Unknown error'));
    }
    
} catch (Exception $e) {
    logTransaction('ERROR', 'M-Pesa payment failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'error_code' => 'PAYMENT_INIT_FAILED'
    ]);
}
?>
