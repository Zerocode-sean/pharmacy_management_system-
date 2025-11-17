<?php
// Check Payment Status API
error_reporting(0);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Include database and M-Pesa handler
    require_once '../../src/backend/config/database.php';
    require_once 'mpesa-handler.php';
    
    // Get input data (support both GET and POST)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $payment_id = $_GET['payment_id'] ?? null;
        $data = ['payment_id' => $payment_id];
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
    }
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit();
    }
    
    $checkout_request_id = $data['checkout_request_id'] ?? '';
    $order_reference = $data['order_reference'] ?? '';
    
    if (!$checkout_request_id || !$order_reference) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    // Check if this is a test mode request (checkout request ID starts with ws_CO_)
    if (strpos($checkout_request_id, 'ws_CO_') === 0) {
        // Return mock successful payment status for test mode
        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'message' => 'TEST MODE: Payment completed successfully',
            'payment_status' => 'Success',
            'transaction_id' => 'TEST' . time(),
            'amount' => 100, // This would normally come from database
            'phone' => '254708374149',
            'order_reference' => $order_reference,
            'timestamp' => date('Y-m-d H:i:s'),
            'test_mode' => true
        ]);
        exit();
    }
    
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // First check if payment is already recorded in our database
    $stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE checkout_request_id = ? OR order_reference = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$checkout_request_id, $order_reference]);
    $payment_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment_record) {
        // Check payment status in our database
        switch ($payment_record['status']) {
            case 'completed':
                echo json_encode([
                    'success' => true,
                    'status' => 'completed',
                    'order_id' => $payment_record['order_id'],
                    'transaction_id' => $payment_record['transaction_id'],
                    'amount' => $payment_record['amount'],
                    'message' => 'Payment completed successfully'
                ]);
                exit();
                
            case 'failed':
                echo json_encode([
                    'success' => true,
                    'status' => 'failed',
                    'message' => $payment_record['failure_reason'] ?: 'Payment failed'
                ]);
                exit();
                
            case 'cancelled':
                echo json_encode([
                    'success' => true,
                    'status' => 'failed',
                    'message' => 'Payment was cancelled'
                ]);
                exit();
        }
    }
    
    // If no record or status is pending, query M-Pesa API
    try {
        $mpesa = new MPesaPaymentHandler();
        $mpesa_status = $mpesa->querySTKPushStatus($checkout_request_id);
        
        if ($mpesa_status['success']) {
            $result_code = $mpesa_status['result_code'];
            
            if ($result_code == '0') {
                // Payment successful - complete the order
                session_start();
                $pending_order = $_SESSION['pending_order'] ?? null;
                
                if ($pending_order && $pending_order['reference'] === $order_reference) {
                    // Complete the order
                    $order_completion_result = completeOrder($pdo, $pending_order, $mpesa_status);
                    
                    if ($order_completion_result['success']) {
                        // Clear pending order from session
                        unset($_SESSION['pending_order']);
                        
                        echo json_encode([
                            'success' => true,
                            'status' => 'completed',
                            'order_id' => $order_completion_result['order_id'],
                            'transaction_id' => $mpesa_status['transaction_id'] ?? 'N/A',
                            'amount' => $pending_order['total'],
                            'message' => 'Payment and order completed successfully'
                        ]);
                    } else {
                        throw new Exception($order_completion_result['message']);
                    }
                } else {
                    throw new Exception('Pending order not found or expired');
                }
                
            } elseif ($result_code == '1032') {
                // User cancelled
                echo json_encode([
                    'success' => true,
                    'status' => 'failed',
                    'message' => 'Payment was cancelled by user'
                ]);
                
            } else {
                // Other failure
                echo json_encode([
                    'success' => true,
                    'status' => 'failed',
                    'message' => $mpesa_status['message'] ?: 'Payment failed'
                ]);
            }
        } else {
            // Still pending or query failed
            echo json_encode([
                'success' => true,
                'status' => 'pending',
                'message' => 'Payment is still being processed'
            ]);
        }
        
    } catch (Exception $mpesa_error) {
        // M-Pesa query failed, assume still pending
        echo json_encode([
            'success' => true,
            'status' => 'pending',
            'message' => 'Checking payment status...'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

// Function to complete order after successful payment
function completeOrder($pdo, $pending_order, $payment_data) {
    try {
        $pdo->beginTransaction();
        
        // Create or get customer
        $customer = $pending_order['customer'];
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
        $stmt->execute([$customer['phone']]);
        $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_customer) {
            $customer_id = $existing_customer['id'];
            
            // Update customer info
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ?, address = ? WHERE id = ?");
            $stmt->execute([
                $customer['name'],
                $customer['email'] ?? '',
                $customer['address'],
                $customer_id
            ]);
        } else {
            // Create new customer
            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $customer['name'],
                $customer['email'] ?? '',
                $customer['phone'],
                $customer['address']
            ]);
            $customer_id = $pdo->lastInsertId();
        }
        
        // Create order
        $stmt = $pdo->prepare("INSERT INTO customer_orders (customer_id, total_amount, status, payment_status, order_reference, created_at) VALUES (?, ?, 'confirmed', 'paid', ?, NOW())");
        $stmt->execute([
            $customer_id,
            $pending_order['total'],
            $pending_order['reference']
        ]);
        $order_id = $pdo->lastInsertId();
        
        // Add order items and update inventory
        foreach ($pending_order['items'] as $item) {
            // Add order item
            $stmt = $pdo->prepare("INSERT INTO customer_order_items (order_id, medicine_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $order_id,
                $item['id'],
                $item['quantity'],
                $item['price'],
                $item['total']
            ]);
            
            // Update medicine stock
            $stmt = $pdo->prepare("UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['id']]);
        }
        
        // Record payment transaction
        $stmt = $pdo->prepare("INSERT INTO payment_transactions (order_id, order_reference, checkout_request_id, transaction_id, amount, status, payment_method, created_at) VALUES (?, ?, ?, ?, ?, 'completed', 'mpesa', NOW())");
        $stmt->execute([
            $order_id,
            $pending_order['reference'],
            $payment_data['checkout_request_id'] ?? '',
            $payment_data['transaction_id'] ?? '',
            $pending_order['total']
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'message' => 'Order completed successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Failed to complete order: ' . $e->getMessage()
        ];
    }
}
?>
