<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/auth_middleware.php';

header('Content-Type: application/json');

// Apply security middleware
AuthMiddleware::checkAuth(true);
AuthMiddleware::checkRole('admin'); // Reports typically need admin access

$action = $_GET['action'] ?? '';
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-d');

try {
    $database = new Database();
    $db = $database->connect();
    
    // Validate dates
    if (!validateDate($startDate) || !validateDate($endDate)) {
        throw new Exception('Invalid date format', 400);
    }
    
    switch ($action) {
        case 'summary':
            handleGetSummary($db, $startDate, $endDate);
            break;
            
        case 'sales':
            handleGetSalesAnalytics($db, $startDate, $endDate);
            break;
            
        case 'inventory':
            handleGetInventoryReports($db, $startDate, $endDate);
            break;
            
        case 'customers':
            handleGetCustomerAnalytics($db, $startDate, $endDate);
            break;
            
        case 'financial':
            handleGetFinancialReports($db, $startDate, $endDate);
            break;
            
        case 'export':
            handleExportReports($db, $startDate, $endDate);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('reports_api_error', 
        'Error: ' . $e->getMessage() . ' | User ID: ' . ($_SESSION['user_id'] ?? 'null')
    );
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetSummary($db, $startDate, $endDate) {
    // Get current period summary
    $currentSummary = getSummaryData($db, $startDate, $endDate);
    
    // Get previous period for comparison
    $daysDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    $prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
    $prevStartDate = date('Y-m-d', strtotime($prevEndDate . ' -' . $daysDiff . ' days'));
    
    $prevSummary = getSummaryData($db, $prevStartDate, $prevEndDate);
    
    // Calculate changes
    $data = $currentSummary;
    $data['revenue_change'] = calculatePercentChange($prevSummary['total_revenue'], $currentSummary['total_revenue']);
    $data['sales_change'] = calculatePercentChange($prevSummary['total_sales'], $currentSummary['total_sales']);
    $data['customers_change'] = calculatePercentChange($prevSummary['new_customers'], $currentSummary['new_customers']);
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
}

function getSummaryData($db, $startDate, $endDate) {
    // Total revenue
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_revenue,
               COUNT(*) as total_sales
        FROM sales 
        WHERE sale_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $salesData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // New customers
    $stmt = $db->prepare("
        SELECT COUNT(*) as new_customers 
        FROM customers 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Low stock items
    $stmt = $db->prepare("
        SELECT COUNT(*) as low_stock_items 
        FROM medicines 
        WHERE stock_quantity <= minimum_stock
    ");
    $stmt->execute();
    $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total_revenue' => floatval($salesData['total_revenue']),
        'total_sales' => intval($salesData['total_sales']),
        'new_customers' => intval($customerData['new_customers']),
        'low_stock_items' => intval($stockData['low_stock_items'])
    ];
}

function handleGetSalesAnalytics($db, $startDate, $endDate) {
    // Daily sales trends
    $stmt = $db->prepare("
        SELECT 
            DATE(sale_date) as date,
            COUNT(*) as transaction_count,
            SUM(quantity) as items_sold,
            SUM(total_amount) as revenue,
            AVG(total_amount) as average_sale
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY DATE(s.sale_date)
        ORDER BY date ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format trends data
    foreach ($trends as &$trend) {
        $trend['revenue'] = floatval($trend['revenue']);
        $trend['average_sale'] = floatval($trend['average_sale']);
        $trend['transaction_count'] = intval($trend['transaction_count']);
        $trend['items_sold'] = intval($trend['items_sold']);
    }
    
    // Top selling medicines
    $stmt = $db->prepare("
        SELECT 
            m.name as medicine_name,
            SUM(si.quantity) as quantity_sold,
            SUM(si.quantity * si.unit_price) as revenue
        FROM sale_items si
        LEFT JOIN medicines m ON si.medicine_id = m.id
        LEFT JOIN sales s ON si.sale_id = s.id
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY si.medicine_id, m.name
        ORDER BY quantity_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $topMedicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format top medicines data
    foreach ($topMedicines as &$medicine) {
        $medicine['quantity_sold'] = intval($medicine['quantity_sold']);
        $medicine['revenue'] = floatval($medicine['revenue']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'trends' => $trends,
            'top_medicines' => $topMedicines,
            'daily_summary' => $trends
        ]
    ]);
}

function handleGetInventoryReports($db, $startDate, $endDate) {
    // Stock levels for all medicines
    $stmt = $db->prepare("
        SELECT 
            m.id,
            m.name as medicine_name,
            c.name as category_name,
            m.stock_quantity as current_stock,
            m.minimum_stock,
            (m.stock_quantity * m.unit_price) as stock_value
        FROM medicines m
        LEFT JOIN categories c ON m.category_id = c.id
        ORDER BY m.name ASC
    ");
    $stmt->execute();
    $stockLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format stock data
    foreach ($stockLevels as &$item) {
        $item['current_stock'] = intval($item['current_stock']);
        $item['minimum_stock'] = intval($item['minimum_stock']);
        $item['stock_value'] = floatval($item['stock_value']);
    }
    
    // Category distribution
    $stmt = $db->prepare("
        SELECT 
            c.name as category_name,
            COUNT(m.id) as item_count,
            SUM(m.stock_quantity) as total_stock
        FROM categories c
        LEFT JOIN medicines m ON c.id = m.category_id
        GROUP BY c.id, c.name
        ORDER BY item_count DESC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low stock alerts
    $stmt = $db->prepare("
        SELECT 
            m.name as medicine_name,
            m.stock_quantity as current_stock,
            m.minimum_stock
        FROM medicines m
        WHERE m.stock_quantity <= m.minimum_stock
        ORDER BY (m.stock_quantity - m.minimum_stock) ASC
    ");
    $stmt->execute();
    $lowStockAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Expiry alerts (medicines expiring within 30 days)
    $stmt = $db->prepare("
        SELECT 
            m.name as medicine_name,
            m.expiry_date
        FROM medicines m
        WHERE m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND m.expiry_date >= CURDATE()
        ORDER BY m.expiry_date ASC
    ");
    $stmt->execute();
    $expiryAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stock_levels' => $stockLevels,
            'categories' => $categories,
            'items' => $stockLevels,
            'alerts' => [
                'low_stock' => $lowStockAlerts,
                'expiry' => $expiryAlerts
            ]
        ]
    ]);
}

function handleGetCustomerAnalytics($db, $startDate, $endDate) {
    // Customer registration trends
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_customers
        FROM customers 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Customer purchase patterns by hour
    $stmt = $db->prepare("
        SELECT 
            HOUR(s.sale_date) as hour,
            COUNT(*) as purchase_count
        FROM sales s
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY HOUR(s.sale_date)
        ORDER BY hour ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top customers
    $stmt = $db->prepare("
        SELECT 
            c.name as customer_name,
            COUNT(s.id) as total_purchases,
            SUM(s.total_amount) as total_spent,
            MAX(s.sale_date) as last_purchase,
            CASE 
                WHEN SUM(s.total_amount) >= 1000 THEN 'Platinum'
                WHEN SUM(s.total_amount) >= 500 THEN 'Gold'
                WHEN SUM(s.total_amount) >= 250 THEN 'Silver'
                ELSE 'Regular'
            END as loyalty_status
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY c.id, c.name
        ORDER BY total_spent DESC
        LIMIT 20
    ");
    $stmt->execute([$startDate, $endDate]);
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format customer data
    foreach ($topCustomers as &$customer) {
        $customer['total_purchases'] = intval($customer['total_purchases']);
        $customer['total_spent'] = floatval($customer['total_spent']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'trends' => $trends,
            'patterns' => $patterns,
            'top_customers' => $topCustomers
        ]
    ]);
}

function handleGetFinancialReports($db, $startDate, $endDate) {
    // Revenue and profit analysis
    $stmt = $db->prepare("
        SELECT 
            SUM(s.total_amount) as gross_revenue,
            SUM(si.quantity * m.cost_price) as cost_of_goods,
            SUM(s.total_amount) - SUM(si.quantity * m.cost_price) as gross_profit
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN medicines m ON si.medicine_id = m.id
        WHERE s.sale_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $financial = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $grossRevenue = floatval($financial['gross_revenue']) ?: 0;
    $costOfGoods = floatval($financial['cost_of_goods']) ?: 0;
    $grossProfit = $grossRevenue - $costOfGoods;
    $profitMargin = $grossRevenue > 0 ? ($grossProfit / $grossRevenue) * 100 : 0;
    
    // Daily revenue vs profit
    $stmt = $db->prepare("
        SELECT 
            DATE(s.sale_date) as date,
            SUM(s.total_amount) as revenue,
            SUM(s.total_amount) - SUM(si.quantity * m.cost_price) as profit
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN medicines m ON si.medicine_id = m.id
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY DATE(s.sale_date)
        ORDER BY date ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $revenueProfit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format revenue profit data
    foreach ($revenueProfit as &$item) {
        $item['revenue'] = floatval($item['revenue']);
        $item['profit'] = floatval($item['profit']);
    }
    
    // Monthly overview (last 12 months)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(s.sale_date, '%Y-%m') as month,
            SUM(s.total_amount) as revenue,
            SUM(si.quantity * m.cost_price) as expenses
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN medicines m ON si.medicine_id = m.id
        WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlyOverview = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format monthly data
    foreach ($monthlyOverview as &$item) {
        $item['revenue'] = floatval($item['revenue']);
        $item['expenses'] = floatval($item['expenses']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'gross_revenue' => $grossRevenue,
            'cost_of_goods' => $costOfGoods,
            'gross_profit' => $grossProfit,
            'profit_margin' => $profitMargin,
            'revenue_profit' => $revenueProfit,
            'monthly_overview' => $monthlyOverview
        ]
    ]);
}

function handleExportReports($db, $startDate, $endDate) {
    // This would typically generate a PDF report
    // For now, we'll return a mock response
    $reportId = uniqid();
    $filename = "pharmacy_report_{$startDate}_to_{$endDate}_{$reportId}.pdf";
    
    // In a real implementation, you would:
    // 1. Generate PDF using libraries like TCPDF or FPDF
    // 2. Save to a temporary directory
    // 3. Return download URL
    
    echo json_encode([
        'success' => true,
        'data' => [
            'download_url' => "/reports/downloads/{$filename}",
            'filename' => $filename,
            'message' => 'Report generated successfully'
        ]
    ]);
}

function calculatePercentChange($oldValue, $newValue) {
    if ($oldValue == 0) {
        return $newValue > 0 ? 100 : 0;
    }
    
    return (($newValue - $oldValue) / $oldValue) * 100;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
