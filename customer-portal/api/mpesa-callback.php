<?php
// M-Pesa Callback Handler
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    require_once '../../src/backend/config/database.php';
    
    // Get callback data from M-Pesa
    $input = file_get_contents('php://input');
    $callback_data = json_decode($input, true);
    
    // Log callback for debugging (optional)
    file_put_contents(
        '../../logs/mpesa_callbacks.log',
        date('Y-m-d H:i:s') . " - " . $input . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    
    if (!$callback_data) {
        throw new Exception('Invalid callback data');
    }
    
    // Extract callback information
    $stkCallback = $callback_data['Body']['stkCallback'] ?? null;
    if (!$stkCallback) {
        throw new Exception('Invalid callback structure');
    }
    
    $checkout_request_id = $stkCallback['CheckoutRequestID'];
    $result_code = $stkCallback['ResultCode'];
    $result_desc = $stkCallback['ResultDesc'];
    
    // Find payment in database
    $stmt = $pdo->prepare("
        SELECT pt.*, co.customer_id 
        FROM payment_transactions pt
        LEFT JOIN customer_orders co ON pt.order_id = co.id
        WHERE pt.checkout_request_id = ?
    ");
    $stmt->execute([$checkout_request_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        throw new Exception('Payment transaction not found');
    }
    
    $pdo->beginTransaction();
    
    try {
        if ($result_code == 0) {
            // Payment successful
            $callback_metadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
            
            // Extract payment details
            $mpesa_receipt = '';
            $transaction_date = '';
            $phone_number = '';
            
            foreach ($callback_metadata as $item) {
                switch ($item['Name']) {
                    case 'MpesaReceiptNumber':
                        $mpesa_receipt = $item['Value'];
                        break;
                    case 'TransactionDate':
                        $transaction_date = $item['Value'];
                        break;
                    case 'PhoneNumber':
                        $phone_number = $item['Value'];
                        break;
                }
            }
            
            // Update payment status to completed
            $stmt = $pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'completed',
                    mpesa_receipt = ?,
                    transaction_date = ?,
                    callback_response = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $mpesa_receipt,
                $transaction_date,
                json_encode($callback_data),
                $payment['id']
            ]);
            
            // Update order status to confirmed (payment received)
            $stmt = $pdo->prepare("
                UPDATE customer_orders 
                SET status = 'confirmed',
                    notes = CONCAT(IFNULL(notes, ''), 'Payment received via M-Pesa. Receipt: ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$mpesa_receipt, $payment['order_id']]);
            
            // You can add additional logic here like:
            // - Send SMS/Email confirmation to customer
            // - Notify pharmacy staff
            // - Update inventory status if needed
            
        } else {
            // Payment failed
            $stmt = $pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'failed',
                    failure_reason = ?,
                    callback_response = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $result_desc,
                json_encode($callback_data),
                $payment['id']
            ]);
            
            // Update order status back to pending (payment failed)
            $stmt = $pdo->prepare("
                UPDATE customer_orders 
                SET status = 'pending',
                    notes = CONCAT(IFNULL(notes, ''), 'Payment failed: ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$result_desc, $payment['order_id']]);
            
            // Restore inventory since payment failed
            $stmt = $pdo->prepare("
                SELECT medicine_id, quantity 
                FROM customer_order_items 
                WHERE order_id = ?
            ");
            $stmt->execute([$payment['order_id']]);
            $order_items = $stmt->fetchAll();
            
            foreach ($order_items as $item) {
                $stmt = $pdo->prepare("
                    UPDATE medicines 
                    SET stock_quantity = stock_quantity + ? 
                    WHERE id = ?
                ");
                $stmt->execute([$item['quantity'], $item['medicine_id']]);
            }
        }
        
        $pdo->commit();
        
        // Respond to M-Pesa
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Success. Callback processed successfully.'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error
    file_put_contents(
        '../../logs/mpesa_callback_errors.log',
        date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . " - INPUT: " . ($input ?? 'No input') . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    
    // Respond with error
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Error processing callback: ' . $e->getMessage()
    ]);
}
?>
