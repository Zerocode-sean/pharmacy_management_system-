<?php
/**
 * Dashboard API Endpoint
 * Provides statistics and data for dashboard
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/cors.php';

// Initialize CORS with proper headers
CORSHelper::init();

// Start session and check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'stats';
    
    switch ($action) {
        case 'stats':
            getDashboardStats($conn);
            break;
        case 'recent_sales':
            getRecentSales($conn);
            break;
        case 'low_stock':
            getLowStockMedicines($conn);
            break;
        case 'expiring_medicines':
            getExpiringMedicines($conn);
            break;
        default:
            getDashboardStats($conn);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getDashboardStats($conn) {
    try {
        $stats = [];
        
        // Total medicines count
        $query = "SELECT COUNT(*) as total FROM medicines WHERE is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['total_medicines'] = $stmt->fetch()['total'];
        
        // Low stock count
        $query = "SELECT COUNT(*) as low_stock FROM medicines WHERE stock_quantity <= minimum_stock AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['low_stock_count'] = $stmt->fetch()['low_stock'];
        
        // Medicines expiring in 30 days
        $query = "SELECT COUNT(*) as expiring FROM medicines WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['expiring_soon'] = $stmt->fetch()['expiring'];
        
        // Today's sales total
        $query = "SELECT COALESCE(SUM(final_amount), 0) as today_sales FROM sales WHERE DATE(sale_date) = CURDATE()";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['today_sales'] = $stmt->fetch()['today_sales'];
        
        // This month's sales
        $query = "SELECT COALESCE(SUM(final_amount), 0) as month_sales FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['month_sales'] = $stmt->fetch()['month_sales'];
        
        // Total customers
        $query = "SELECT COUNT(*) as total_customers FROM customers";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['total_customers'] = $stmt->fetch()['total_customers'];
        
        // Total suppliers
        $query = "SELECT COUNT(*) as total_suppliers FROM suppliers WHERE is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['total_suppliers'] = $stmt->fetch()['total_suppliers'];
        
        echo json_encode(['success' => true, 'data' => $stats]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get dashboard statistics: ' . $e->getMessage());
    }
}

function getRecentSales($conn) {
    try {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        $query = "SELECT s.id, s.final_amount, s.sale_date, s.payment_method,
                         c.name as customer_name,
                         u.full_name as cashier_name
                  FROM sales s
                  LEFT JOIN customers c ON s.customer_id = c.id
                  LEFT JOIN users u ON s.user_id = u.id
                  ORDER BY s.sale_date DESC
                  LIMIT :limit";
        
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $sales = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $sales]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get recent sales: ' . $e->getMessage());
    }
}

function getLowStockMedicines($conn) {
    try {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        
        $query = "SELECT m.id, m.name, m.brand, m.stock_quantity, m.min_stock_level,
                         c.name as category_name
                  FROM medicines m
                  LEFT JOIN categories c ON m.category_id = c.id
                  WHERE m.stock_quantity <= m.minimum_stock AND m.is_active = 1
                  ORDER BY (m.stock_quantity - m.min_stock_level) ASC
                  LIMIT :limit";
        
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $medicines = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $medicines]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get low stock medicines: ' . $e->getMessage());
    }
}

function getExpiringMedicines($conn) {
    try {
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        
        $query = "SELECT m.id, m.name, m.brand, m.expiry_date, m.stock_quantity, m.batch_number,
                         c.name as category_name,
                         DATEDIFF(m.expiry_date, CURDATE()) as days_to_expire
                  FROM medicines m
                  LEFT JOIN categories c ON m.category_id = c.id
                  WHERE m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY) 
                        AND m.expiry_date >= CURDATE() 
                        AND m.is_active = 1
                  ORDER BY m.expiry_date ASC
                  LIMIT :limit";
        
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $medicines = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $medicines]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get expiring medicines: ' . $e->getMessage());
    }
}
?>
