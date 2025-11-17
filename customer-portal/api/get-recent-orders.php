<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "pharmacy_db";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Get recent orders (last 10)
    $sql = "SELECT o.*, p.status as payment_status, p.mpesa_receipt_number 
            FROM orders o 
            LEFT JOIN payments p ON o.payment_id = p.id 
            ORDER BY o.created_at DESC 
            LIMIT 10";
    
    $result = $conn->query($sql);
    $orders = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $order_id = $order['id'];
        $items_sql = "SELECT oi.*, m.name as medicine_name 
                      FROM order_items oi 
                      LEFT JOIN medicines m ON oi.medicine_id = m.id 
                      WHERE oi.order_id = ?";
        
        $stmt = $conn->prepare($items_sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        
        $order['items'] = [];
        while ($item = $items_result->fetch_assoc()) {
            $order['items'][] = $item;
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
