<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/auth_middleware.php';
require_once '../config/cors.php';

// Set headers for API response
header('Content-Type: application/json');

// Development-friendly authentication for localhost
session_start();

// For development: Check if we're in local environment and allow easier access
$isLocalhost = (
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
    strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0
);

if ($isLocalhost && !isset($_SESSION['user_id'])) {
    // Auto-create a test session for development
    $_SESSION['user_id'] = 2;
    $_SESSION['username'] = 'dev_cashier';
    $_SESSION['role'] = 'cashier';
    $_SESSION['last_activity'] = time();

    error_log("Development: Auto-created cashier session for localhost");
}

// Apply security middleware only if not in development mode
if (!$isLocalhost) {
    AuthMiddleware::checkAuth(false);
} else {
    // For development, ensure session is set
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit();
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $database = new Database();
    $db = $database->getConnection();

    // Role-based access control
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, ['admin', 'cashier', 'pharmacist'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Analytics access requires admin, cashier, or pharmacist role.']);
        exit();
    }

    switch($method) {
        case 'GET':
            handleAnalyticsRequest($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleAnalyticsRequest($db) {
    try {
        $date = $_GET['date'] ?? date('Y-m-d');

        // Check if sales table exists
        $salesTableExists = false;
        try {
            $checkSalesTable = $db->query("SHOW TABLES LIKE 'sales'");
            $salesTableExists = ($checkSalesTable->rowCount() > 0);
        } catch (Exception $e) {
            $salesTableExists = false;
        }

        if (!$salesTableExists) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'totalSales' => 0,
                    'totalTransactions' => 0,
                    'itemsSold' => 0,
                    'avgTransaction' => 0,
                    'topItems' => []
                ]
            ]);
            return;
        }

        // Get today's sales data
        $todayQuery = "SELECT
            COUNT(DISTINCT s.id) as totalTransactions,
            COALESCE(SUM(s.final_amount), 0) as totalSales,
            COALESCE(AVG(s.final_amount), 0) as avgTransaction
            FROM sales s
            WHERE DATE(s.sale_date) = :date";

        $todayStmt = $db->prepare($todayQuery);
        $todayStmt->bindParam(':date', $date);
        $todayStmt->execute();
        $todayData = $todayStmt->fetch(PDO::FETCH_ASSOC);

        // Get total items sold today
        $itemsQuery = "SELECT
            COALESCE(SUM(si.quantity), 0) as itemsSold
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            WHERE DATE(s.sale_date) = :date";

        $itemsStmt = $db->prepare($itemsQuery);
        $itemsStmt->bindParam(':date', $date);
        $itemsStmt->execute();
        $itemsData = $itemsStmt->fetch(PDO::FETCH_ASSOC);

        // Get top selling items today
        $topItemsQuery = "SELECT
            m.name,
            SUM(si.quantity) as quantity
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            INNER JOIN medicines m ON si.medicine_id = m.id
            WHERE DATE(s.sale_date) = :date
            GROUP BY m.id, m.name
            ORDER BY SUM(si.quantity) DESC
            LIMIT 5";

        $topItemsStmt = $db->prepare($topItemsQuery);
        $topItemsStmt->bindParam(':date', $date);
        $topItemsStmt->execute();
        $topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the response
        $analyticsData = [
            'totalSales' => (float) $todayData['totalSales'],
            'totalTransactions' => (int) $todayData['totalTransactions'],
            'itemsSold' => (int) $itemsData['itemsSold'],
            'avgTransaction' => (float) $todayData['avgTransaction'],
            'topItems' => $topItems
        ];

        echo json_encode([
            'success' => true,
            'data' => $analyticsData
        ]);

    } catch (Exception $e) {
        error_log("Analytics request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch analytics data']);
    }
}
?>