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
    
    // Role-based access control for POS
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, ['admin', 'cashier', 'pharmacist'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. POS access requires cashier, pharmacist, or admin role.']);
        exit();
    }
    
    switch($method) {
        case 'GET':
            handleGetData($db);
            break;
        case 'POST':
            handleCreateSale($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Sales API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleGetData($db) {
    try {
        $action = $_GET['action'] ?? 'pos_data';
        
        switch ($action) {
            case 'pos_data':
                getPosData($db);
                break;
            case 'sales_history':
                getSalesHistory($db);
                break;
            case 'stats':
                getSalesStats($db);
                break;
            case 'getTodayStats':
                getTodayStats($db);
                break;
            case 'getRecentSales':
                getRecentSales($db);
                break;
            default:
                getPosData($db);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getPosData($db) {
    try {
        // Check if reorder_level column exists in medicines table
        $reorderLevelExists = false;
        try {
            $checkReorderLevel = $db->query("SHOW COLUMNS FROM medicines LIKE 'reorder_level'");
            $reorderLevelExists = ($checkReorderLevel->rowCount() > 0);
        } catch (Exception $e) {
            $reorderLevelExists = false;
        }
        
        // Get available medicines for POS
        if ($reorderLevelExists) {
            $medicinesQuery = "SELECT id, name, generic_name, category_id, unit_price, stock_quantity, brand,
                              CASE WHEN stock_quantity <= reorder_level THEN 1 ELSE 0 END as low_stock
                              FROM medicines 
                              WHERE stock_quantity > 0
                              ORDER BY name ASC";
        } else {
            $medicinesQuery = "SELECT id, name, generic_name, category_id, unit_price, stock_quantity, brand,
                              CASE WHEN stock_quantity <= 10 THEN 1 ELSE 0 END as low_stock
                              FROM medicines 
                              WHERE stock_quantity > 0
                              ORDER BY name ASC";
        }
        
        $medicinesStmt = $db->prepare($medicinesQuery);
        $medicinesStmt->execute();
        $medicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if status column exists in customers table
        $statusColumnExists = false;
        try {
            $checkStatus = $db->query("SHOW COLUMNS FROM customers LIKE 'status'");
            $statusColumnExists = ($checkStatus->rowCount() > 0);
        } catch (Exception $e) {
            $statusColumnExists = false;
        }
        
        // Get customers
        if ($statusColumnExists) {
            $customersQuery = "SELECT id, name, email, phone, address FROM customers 
                              WHERE status = 'active' 
                              ORDER BY name ASC";
        } else {
            $customersQuery = "SELECT id, name, email, phone, address FROM customers 
                              ORDER BY name ASC";
        }
        
        $customersStmt = $db->prepare($customersQuery);
        $customersStmt->execute();
        $customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if sales table exists and get today's sales stats
        $salesTableExists = false;
        try {
            $checkSalesTable = $db->query("SHOW TABLES LIKE 'sales'");
            $salesTableExists = ($checkSalesTable->rowCount() > 0);
        } catch (Exception $e) {
            $salesTableExists = false;
        }
        
        if ($salesTableExists) {
            $statsQuery = "SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(final_amount), 0) as total_revenue,
                COALESCE(AVG(final_amount), 0) as avg_sale_amount
                FROM sales 
                WHERE DATE(sale_date) = CURDATE()";
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->execute();
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Default stats if sales table doesn't exist
            $stats = [
                'total_sales' => 0,
                'total_revenue' => 0,
                'avg_sale_amount' => 0
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'medicines' => $medicines,
                'customers' => $customers,
                'stats' => $stats
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get POS data error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch POS data']);
    }
}

function getSalesHistory($db) {
    try {
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
                'data' => []
            ]);
            return;
        }
        
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        $historyQuery = "SELECT s.*, c.name as customer_name, 
                        COUNT(si.id) as item_count
                        FROM sales s
                        LEFT JOIN customers c ON s.customer_id = c.id
                        LEFT JOIN sale_items si ON s.id = si.sale_id
                        GROUP BY s.id
                        ORDER BY s.sale_date DESC 
                        LIMIT :limit OFFSET :offset";
        
        $historyStmt = $db->prepare($historyQuery);
        $historyStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $historyStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $historyStmt->execute();
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $history
        ]);
        
    } catch (Exception $e) {
        error_log("Get sales history error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch sales history']);
    }
}

function getSalesStats($db) {
    try {
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
                    'today' => ['sales_count' => 0, 'revenue' => 0, 'cash_received' => 0],
                    'month' => ['sales_count' => 0, 'revenue' => 0]
                ]
            ]);
            return;
        }
        
        // Today's stats
        $todayQuery = "SELECT 
            COUNT(*) as sales_count,
            COALESCE(SUM(final_amount), 0) as revenue,
            COALESCE(SUM(final_amount), 0) as cash_received
            FROM sales 
            WHERE DATE(sale_date) = CURDATE()";
        $todayStmt = $db->prepare($todayQuery);
        $todayStmt->execute();
        $todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC);
        
        // This month's stats
        $monthQuery = "SELECT 
            COUNT(*) as sales_count,
            COALESCE(SUM(final_amount), 0) as revenue
            FROM sales 
            WHERE YEAR(sale_date) = YEAR(CURDATE()) 
            AND MONTH(sale_date) = MONTH(CURDATE())";
        $monthStmt = $db->prepare($monthQuery);
        $monthStmt->execute();
        $monthStats = $monthStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'today' => $todayStats,
                'month' => $monthStats
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get sales stats error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch sales stats']);
    }
}

function handleCreateSale($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            return;
        }
        
        // Validate required fields
        if (!isset($input['items']) || empty($input['items'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No items in cart']);
            return;
        }
        
        if (!isset($input['total_amount']) || $input['total_amount'] <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid total amount']);
            return;
        }
        
        $db->beginTransaction();
        
        try {
            // Get current user ID from session
            $userId = $_SESSION['user_id'];
            
            // Prepare sale data
            $customerId = !empty($input['customer_id']) ? $input['customer_id'] : null;
            $subtotal = $input['subtotal'] ?? 0;
            $discountAmount = $input['discount_amount'] ?? 0;
            $discountType = $input['discount_type'] ?? 'amount';
            $taxAmount = $input['tax_amount'] ?? 0;
            $totalAmount = $input['total_amount'];
            $amountPaid = $input['amount_paid'] ?? $totalAmount;
            $changeAmount = $input['change_amount'] ?? 0;
            $paymentMethod = $input['payment_method'] ?? 'cash';
            $notes = $input['notes'] ?? '';
            
            // Create sale record (matching actual table structure)
            $saleQuery = "INSERT INTO sales (customer_id, user_id, total_amount, discount_amount, 
                         tax_amount, final_amount, payment_method, notes, sale_date) 
                         VALUES (:customer_id, :user_id, :total_amount, :discount_amount, 
                         :tax_amount, :final_amount, :payment_method, :notes, NOW())";
            
            $finalAmount = $totalAmount - $discountAmount + $taxAmount;
            
            $saleStmt = $db->prepare($saleQuery);
            $saleStmt->bindParam(':customer_id', $customerId);
            $saleStmt->bindParam(':user_id', $userId);
            $saleStmt->bindParam(':total_amount', $totalAmount);
            $saleStmt->bindParam(':discount_amount', $discountAmount);
            $saleStmt->bindParam(':tax_amount', $taxAmount);
            $saleStmt->bindParam(':final_amount', $finalAmount);
            $saleStmt->bindParam(':payment_method', $paymentMethod);
            $saleStmt->bindParam(':notes', $notes);
            
            if (!$saleStmt->execute()) {
                throw new Exception('Failed to create sale record');
            }
            
            $saleId = $db->lastInsertId();
            
            // Create sale items and update stock
            foreach ($input['items'] as $item) {
                // Validate item data
                if (!isset($item['medicine_id']) || !isset($item['quantity']) || !isset($item['unit_price'])) {
                    throw new Exception('Invalid item data');
                }
                
                $medicineId = $item['medicine_id'];
                $quantity = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $totalPrice = $quantity * $unitPrice;
                
                // Check if sufficient stock is available
                $stockCheckQuery = "SELECT stock_quantity FROM medicines WHERE id = :medicine_id";
                $stockCheckStmt = $db->prepare($stockCheckQuery);
                $stockCheckStmt->bindParam(':medicine_id', $medicineId);
                $stockCheckStmt->execute();
                $currentStock = $stockCheckStmt->fetchColumn();
                
                if ($currentStock < $quantity) {
                    throw new Exception('Insufficient stock for medicine ID: ' . $medicineId . '. Available: ' . $currentStock . ', Required: ' . $quantity);
                }
                
                // Insert sale item
                $itemQuery = "INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, total_price) 
                             VALUES (:sale_id, :medicine_id, :quantity, :unit_price, :total_price)";
                
                $itemStmt = $db->prepare($itemQuery);
                $itemStmt->bindParam(':sale_id', $saleId);
                $itemStmt->bindParam(':medicine_id', $medicineId);
                $itemStmt->bindParam(':quantity', $quantity);
                $itemStmt->bindParam(':unit_price', $unitPrice);
                $itemStmt->bindParam(':total_price', $totalPrice);
                
                if (!$itemStmt->execute()) {
                    throw new Exception('Failed to create sale item');
                }
                
                // Update medicine stock
                $updateStockQuery = "UPDATE medicines SET stock_quantity = stock_quantity - :quantity WHERE id = :medicine_id";
                $updateStockStmt = $db->prepare($updateStockQuery);
                $updateStockStmt->bindParam(':quantity', $quantity);
                $updateStockStmt->bindParam(':medicine_id', $medicineId);
                
                if (!$updateStockStmt->execute()) {
                    throw new Exception('Failed to update medicine stock');
                }
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Sale completed successfully',
                'data' => [
                    'sale_id' => $saleId,
                    'total_amount' => $finalAmount,
                    'items_processed' => count($input['items'])
                ]
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Create sale error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function generateSaleNumber($db) {
    try {
        // Simple sale number generation based on date and time
        $dateStr = date('Ymd');
        $timeStr = date('His');
        $randomNum = rand(100, 999);
        
        return 'POS' . $dateStr . $timeStr . $randomNum;
        
    } catch (Exception $e) {
        return 'POS' . time() . rand(100, 999);
    }
}

// Get today's statistics for cashier dashboard
function getTodayStats($db) {
    try {
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
                'stats' => [
                    'total_sales' => 0,
                    'transaction_count' => 0,
                    'sales_change' => 0
                ]
            ]);
            return;
        }
        
        // Today's stats
        $todayQuery = "SELECT 
            COUNT(*) as transaction_count,
            COALESCE(SUM(final_amount), 0) as total_sales
            FROM sales 
            WHERE DATE(sale_date) = CURDATE()";
        $todayStmt = $db->prepare($todayQuery);
        $todayStmt->execute();
        $today = $todayStmt->fetch(PDO::FETCH_ASSOC);
        
        // Yesterday's sales for comparison
        $yesterdayQuery = "SELECT COALESCE(SUM(final_amount), 0) as total_sales
            FROM sales 
            WHERE DATE(sale_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $yesterdayStmt = $db->prepare($yesterdayQuery);
        $yesterdayStmt->execute();
        $yesterday = $yesterdayStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate percentage change
        $salesChange = 0;
        if ($yesterday['total_sales'] > 0) {
            $salesChange = (($today['total_sales'] - $yesterday['total_sales']) / $yesterday['total_sales']) * 100;
        } elseif ($today['total_sales'] > 0) {
            $salesChange = 100;
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_sales' => $today['total_sales'],
                'transaction_count' => $today['transaction_count'],
                'sales_change' => round($salesChange, 1)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get today stats error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch today stats']);
    }
}

// Get recent sales for cashier dashboard
function getRecentSales($db) {
    try {
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
                'sales' => []
            ]);
            return;
        }
        
        $limit = $_GET['limit'] ?? 10;
        
        // Try to get recent sales - simplified query to avoid column issues
        try {
            // First check what columns exist in sales table
            $columnsQuery = "SHOW COLUMNS FROM sales";
            $columnsStmt = $db->query($columnsQuery);
            $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $hasCreatedAt = in_array('created_at', $columns);
            $hasFinalAmount = in_array('final_amount', $columns);
            
            // Build query based on available columns
            $selectCols = "s.id, s.total_amount";
            if ($hasFinalAmount) {
                $selectCols .= ", s.final_amount";
            }
            $selectCols .= ", s.sale_date";
            if ($hasCreatedAt) {
                $selectCols .= ", s.created_at";
            }
            
            $orderBy = $hasCreatedAt ? "s.created_at DESC" : "s.sale_date DESC";
            
            // Simple query without joins first
            $salesQuery = "SELECT $selectCols, 'Medicine Sale' as medicine_name, 1 as quantity
                          FROM sales s
                          WHERE DATE(s.sale_date) = CURDATE()
                          ORDER BY $orderBy
                          LIMIT :limit";
            
            $salesStmt = $db->prepare($salesQuery);
            $salesStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $salesStmt->execute();
            $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'sales' => $sales
            ]);
            
        } catch (PDOException $e) {
            error_log("SQL Error in getRecentSales: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Get recent sales error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch recent sales: ' . $e->getMessage()]);
    }
}
?>
