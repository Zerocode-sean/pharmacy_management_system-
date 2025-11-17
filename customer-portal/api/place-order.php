<?php
// Customer Order Placement API
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);
ini_set('error_log', '../../src/backend/logs/place_order_errors.log');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Include configuration files
    require_once '../../src/backend/config/config.php';
    require_once '../../src/backend/config/database.php';
    
    // Create database connection
    $database = new Database();
    $pdo = $database->connect();
    
    // Get JSON input
    $input = file_get_contents('php://input');
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
    
    // Validate customer data
    $customer = $data['customer'];
    $customer_required = ['name', 'email', 'phone', 'address'];
    foreach ($customer_required as $field) {
        if (empty($customer[$field])) {
            throw new Exception("Missing customer field: $field");
        }
    }
    
    // Validate items
    if (!is_array($data['items']) || empty($data['items'])) {
        throw new Exception('Order must contain at least one item');
    }
    
    // Create tables if they don't exist (DDL statements auto-commit, so do this BEFORE transaction)
    $create_customers_table = "
        CREATE TABLE IF NOT EXISTS customers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100),
            zip VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    $pdo->exec($create_customers_table);
    
    $create_orders_table = "
        CREATE TABLE IF NOT EXISTS customer_orders (
            id INT PRIMARY KEY AUTO_INCREMENT,
            customer_id INT,
            order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'confirmed', 'processing', 'delivered', 'cancelled') DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(customer_id),
            INDEX(order_date),
            INDEX(status)
        )";
    $pdo->exec($create_orders_table);
    
    $create_order_items_table = "
        CREATE TABLE IF NOT EXISTS customer_order_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            medicine_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            INDEX(order_id),
            INDEX(medicine_id)
        )";
    $pdo->exec($create_order_items_table);
    
    $create_payments_table = "
        CREATE TABLE IF NOT EXISTS payment_transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(20) DEFAULT 'mpesa',
            checkout_request_id VARCHAR(255),
            merchant_request_id VARCHAR(255),
            mpesa_receipt VARCHAR(100),
            transaction_date VARCHAR(20),
            mpesa_response TEXT,
            callback_response TEXT,
            failure_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX(order_id),
            INDEX(checkout_request_id),
            INDEX(status)
        )";
    $pdo->exec($create_payments_table);
    
    // NOW start transaction for actual data operations
    $pdo->beginTransaction();
    
    try {
        // Check if customer exists, if not create new customer
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$customer['email']]);
        $existing_customer = $stmt->fetch();
        
        if ($existing_customer) {
            $customer_id = $existing_customer['id'];
            
            // Update customer info if provided
            $update_stmt = $pdo->prepare("
                UPDATE customers 
                SET name = ?, phone = ?, address = ?, city = ?, zip_code = ? 
                WHERE id = ?
            ");
            $update_stmt->execute([
                $customer['name'],
                $customer['phone'], 
                $customer['address'],
                $customer['city'] ?? '',
                $customer['zip'] ?? '',
                $customer_id
            ]);
        } else {
            // Create new customer
            $insert_stmt = $pdo->prepare("
                INSERT INTO customers (name, email, phone, address, city, zip_code) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->execute([
                $customer['name'],
                $customer['email'],
                $customer['phone'],
                $customer['address'],
                $customer['city'] ?? '',
                $customer['zip'] ?? ''
            ]);
            $customer_id = $pdo->lastInsertId();
        }
        
        // Generate order reference
        $order_reference = 'ORD' . date('Ymd') . rand(100000, 999999);
        
        // Get payment method from data or default to 'cash'
        $payment_method = $data['payment_method'] ?? 'cash';
        
        // Create the order with order_reference and payment_method
        $order_stmt = $pdo->prepare("
            INSERT INTO customer_orders (
                customer_id, order_reference, total_amount, 
                final_amount, payment_method, payment_status, 
                status, notes, order_date
            ) 
            VALUES (?, ?, ?, ?, ?, 'pending', 'payment_pending', 'Customer portal order', NOW())
        ");
        $order_stmt->execute([
            $customer_id, 
            $order_reference, 
            $data['total'],
            $data['total'],
            $payment_method
        ]);
        $order_id = $pdo->lastInsertId();
        
        // Process each item
        $items_processed = [];
        $total_calculated = 0;
        
        foreach ($data['items'] as $item) {
            // Validate item data
            if (!isset($item['id']) || !isset($item['quantity']) || !isset($item['price'])) {
                throw new Exception('Invalid item data');
            }
            
            $medicine_id = (int)$item['id'];
            $quantity = (int)$item['quantity'];
            $unit_price = (float)$item['price'];
            $total_price = $unit_price * $quantity;
            
            // Check if medicine exists and has sufficient stock
            $medicine_stmt = $pdo->prepare("
                SELECT id, name, stock_quantity, unit_price 
                FROM medicines 
                WHERE id = ?
            ");
            $medicine_stmt->execute([$medicine_id]);
            $medicine = $medicine_stmt->fetch();
            
            if (!$medicine) {
                throw new Exception("Medicine with ID {$medicine_id} not found");
            }
            
            if ($medicine['stock_quantity'] < $quantity) {
                throw new Exception("Insufficient stock for {$medicine['name']}. Available: {$medicine['stock_quantity']}, Requested: {$quantity}");
            }
            
            // Insert order item
            $item_stmt = $pdo->prepare("
                INSERT INTO customer_order_items (order_id, medicine_id, quantity, unit_price, total_price) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $item_stmt->execute([$order_id, $medicine_id, $quantity, $unit_price, $total_price]);
            
            // Update medicine stock
            $update_stock_stmt = $pdo->prepare("
                UPDATE medicines 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ?
            ");
            $update_stock_stmt->execute([$quantity, $medicine_id]);
            
            $items_processed[] = [
                'medicine_id' => $medicine_id,
                'name' => $medicine['name'],
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ];
            
            $total_calculated += $total_price;
        }
        
        // Verify total matches (allow small floating point differences)
        if (abs($total_calculated - $data['total']) > 0.01) {
            throw new Exception("Total amount mismatch. Calculated: {$total_calculated}, Provided: {$data['total']}");
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response with payment info
        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully. Please proceed with payment.',
            'order_id' => $order_id,
            'order_reference' => $order_reference,
            'customer_id' => $customer_id,
            'total_amount' => $total_calculated,
            'items_count' => count($items_processed),
            'items' => $items_processed,
            'requires_payment' => true,
            'payment_amount' => $total_calculated
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error only if transaction is active
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
}
?>
