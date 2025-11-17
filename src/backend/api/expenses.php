<?php
/**
 * EXPENSES API
 * =============
 * Manage general business operating expenses
 * 
 * Actions:
 * - GET ?action=list - List all expenses with filters
 * - GET ?action=details&id=X - Get single expense details
 * - POST ?action=create - Create new expense
 * - POST ?action=update&id=X - Update expense
 * - POST ?action=delete&id=X - Delete expense
 * - GET ?action=stats - Get expense statistics
 * - GET ?action=categories - List expense categories
 * - GET ?action=monthly_summary - Monthly breakdown
 */

// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/expenses_errors.log');

require_once '../config/session_fix.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';

// Start session using SessionCookieFixer to match login session
SessionCookieFixer::startSession();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Allow admin and pharmacist to manage expenses
if (!in_array($_SESSION['role'] ?? '', ['admin', 'pharmacist'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions. Only administrators and pharmacists can manage expenses.']);
    exit;
}

// Create database connection
$database = new Database();
$conn = $database->connect();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            listExpenses($conn);
            break;
            
        case 'details':
            getExpenseDetails($conn);
            break;
            
        case 'create':
            createExpense($conn);
            break;
            
        case 'update':
            updateExpense($conn);
            break;
            
        case 'delete':
            deleteExpense($conn);
            break;
            
        case 'stats':
            getExpenseStats($conn);
            break;
            
        case 'categories':
            getCategories($conn);
            break;
            
        case 'monthly_summary':
            getMonthlySummary($conn);
            break;
            
        case 'recurring':
            getRecurringExpenses($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * LIST EXPENSES
 * Get all expenses with optional filters
 */
function listExpenses($conn) {
    $category = $_GET['category'] ?? null;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? null;
    $payment_method = $_GET['payment_method'] ?? null;
    $is_recurring = $_GET['is_recurring'] ?? null;
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $offset = ($page - 1) * $limit;
    
    $query = "
        SELECT 
            e.id,
            e.expense_date,
            e.category,
            e.subcategory,
            e.description,
            e.amount,
            e.payment_method,
            e.reference_number,
            e.receipt_number,
            e.vendor_name,
            e.is_recurring,
            e.recurrence_period,
            u.full_name AS created_by_name,
            e.created_at
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($category) {
        $query .= " AND e.category = ?";
        $params[] = $category;
    }
    
    if ($from_date) {
        $query .= " AND e.expense_date >= ?";
        $params[] = $from_date;
    }
    
    if ($to_date) {
        $query .= " AND e.expense_date <= ?";
        $params[] = $to_date;
    }
    
    if ($payment_method) {
        $query .= " AND e.payment_method = ?";
        $params[] = $payment_method;
    }
    
    if ($is_recurring !== null) {
        $query .= " AND e.is_recurring = ?";
        $params[] = $is_recurring;
    }
    
    $query .= " ORDER BY e.expense_date DESC, e.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    
    // Bind regular parameters
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    
    // Bind LIMIT and OFFSET as integers
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count (build a simple COUNT(*) query without ORDER BY / LIMIT to avoid syntax issues
    // when wrapping the original query in a subquery)
    $count_query = "SELECT COUNT(*) as total FROM expenses e WHERE 1=1";
    if ($category) {
        $count_query .= " AND e.category = ?";
    }
    if ($from_date) {
        $count_query .= " AND e.expense_date >= ?";
    }
    if ($to_date) {
        $count_query .= " AND e.expense_date <= ?";
    }
    if ($payment_method) {
        $count_query .= " AND e.payment_method = ?";
    }
    if ($is_recurring !== null) {
        $count_query .= " AND e.is_recurring = ?";
    }

    $stmt = $conn->prepare($count_query);
    // Bind the same filter parameters (without limit/offset)
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->execute();
    $total = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? count($expenses));
    
    // Calculate totals for current filter
    $sum_query = str_replace(
        "SELECT e.id, e.expense_date, e.category, e.subcategory, e.description, e.amount, e.payment_method, e.reference_number, e.receipt_number, e.vendor_name, e.is_recurring, e.recurrence_period, u.full_name AS created_by_name, e.created_at",
        "SELECT SUM(e.amount) as total_amount",
        $query
    );
    $sum_query = str_replace(" LIMIT :limit OFFSET :offset", "", $sum_query);
    
    $stmt = $conn->prepare($sum_query);
    
    // Bind the same filter parameters (without limit/offset)
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    
    $stmt->execute();
    $total_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'expenses' => $expenses,
        'pagination' => [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ],
        'total_amount' => (float)$total_amount
    ]);
}

/**
 * GET EXPENSE DETAILS
 * Get single expense record
 */
function getExpenseDetails($conn) {
    $expense_id = $_GET['id'] ?? null;
    
    if (!$expense_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Expense ID required']);
        return;
    }
    
    $query = "
        SELECT 
            e.*,
            u.full_name AS created_by_name,
            u.email AS created_by_email,
            a.full_name AS approved_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN users a ON e.approved_by = a.id
        WHERE e.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'expense' => $expense
    ]);
}

/**
 * CREATE EXPENSE
 * Record new expense
 */
function createExpense($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['expense_date', 'category', 'description', 'amount', 'payment_method'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    try {
        $query = "
            INSERT INTO expenses (
                expense_date,
                category,
                subcategory,
                description,
                amount,
                payment_method,
                reference_number,
                receipt_number,
                vendor_name,
                vendor_contact,
                is_recurring,
                recurrence_period,
                notes,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $input['expense_date'],
            $input['category'],
            $input['subcategory'] ?? null,
            $input['description'],
            $input['amount'],
            $input['payment_method'],
            $input['reference_number'] ?? null,
            $input['receipt_number'] ?? null,
            $input['vendor_name'] ?? null,
            $input['vendor_contact'] ?? null,
            $input['is_recurring'] ?? 0,
            $input['recurrence_period'] ?? null,
            $input['notes'] ?? null,
            $_SESSION['user_id']
        ]);
        
        $expense_id = $conn->lastInsertId();
        
        // Log activity
        $log_query = "
            INSERT INTO user_activity (
                user_id,
                activity_type,
                description,
                ip_address
            ) VALUES (?, 'expense_created', ?, ?)
        ";
        
        $stmt = $conn->prepare($log_query);
        $stmt->execute([
            $_SESSION['user_id'],
            "Created expense #{$expense_id}: {$input['description']} - KES " . number_format($input['amount'], 2),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Expense recorded successfully',
            'expense_id' => $expense_id
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * UPDATE EXPENSE
 * Modify existing expense
 */
function updateExpense($conn) {
    $expense_id = $_GET['id'] ?? $_POST['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$expense_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Expense ID required']);
        return;
    }
    
    $updates = [];
    $params = [];
    
    $allowed_fields = [
        'expense_date', 'category', 'subcategory', 'description', 'amount',
        'payment_method', 'reference_number', 'receipt_number', 'vendor_name',
        'vendor_contact', 'is_recurring', 'recurrence_period', 'notes'
    ];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $expense_id;
    $query = "UPDATE expenses SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Expense updated successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * DELETE EXPENSE
 * Remove expense record
 */
function deleteExpense($conn) {
    $expense_id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$expense_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Expense ID required']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Expense not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * GET EXPENSE STATS
 * Statistics and summary
 */
function getExpenseStats($conn) {
    $period = $_GET['period'] ?? 'month';
    
    $date_filter = match($period) {
        'week' => "DATE_SUB(NOW(), INTERVAL 1 WEEK)",
        'month' => "DATE_SUB(NOW(), INTERVAL 1 MONTH)",
        'quarter' => "DATE_SUB(NOW(), INTERVAL 3 MONTH)",
        'year' => "DATE_SUB(NOW(), INTERVAL 1 YEAR)",
        default => "DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    };
    
    // Total expenses
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS expense_count,
            COALESCE(SUM(amount), 0) AS total_expenses,
            AVG(amount) AS avg_expense,
            MAX(amount) AS max_expense,
            MIN(amount) AS min_expense
        FROM expenses
        WHERE expense_date >= $date_filter
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // By category
    $stmt = $conn->prepare("
        SELECT 
            category,
            COUNT(*) AS count,
            SUM(amount) AS total,
            AVG(amount) AS average
        FROM expenses
        WHERE expense_date >= $date_filter
        GROUP BY category
        ORDER BY total DESC
    ");
    $stmt->execute();
    $by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By payment method
    $stmt = $conn->prepare("
        SELECT 
            payment_method,
            COUNT(*) AS count,
            SUM(amount) AS total
        FROM expenses
        WHERE expense_date >= $date_filter
        GROUP BY payment_method
        ORDER BY total DESC
    ");
    $stmt->execute();
    $by_method = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top vendors
    $stmt = $conn->prepare("
        SELECT 
            vendor_name,
            COUNT(*) AS transaction_count,
            SUM(amount) AS total_spent
        FROM expenses
        WHERE expense_date >= $date_filter AND vendor_name IS NOT NULL
        GROUP BY vendor_name
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'period' => $period,
        'stats' => $stats,
        'by_category' => $by_category,
        'by_payment_method' => $by_method,
        'top_vendors' => $top_vendors
    ]);
}

/**
 * GET CATEGORIES
 * List all available expense categories
 */
function getCategories($conn) {
    $categories = [
        'rent' => 'Rent',
        'utilities' => 'Utilities (Electricity, Water, Internet)',
        'salaries' => 'Staff Salaries & Wages',
        'maintenance' => 'Maintenance & Repairs',
        'marketing' => 'Marketing & Advertising',
        'transport' => 'Transport & Fuel',
        'insurance' => 'Insurance',
        'taxes' => 'Taxes & Government Fees',
        'licenses' => 'Licenses & Permits',
        'office_supplies' => 'Office Supplies & Stationery',
        'professional_fees' => 'Professional Fees (Legal, Accounting)',
        'repairs' => 'Equipment Repairs',
        'fuel' => 'Fuel & Vehicle Costs',
        'security' => 'Security Services',
        'cleaning' => 'Cleaning Services',
        'communication' => 'Phone & Communication',
        'training' => 'Staff Training & Development',
        'other' => 'Other Expenses'
    ];
    
    // Get usage count for each category
    $stmt = $conn->prepare("
        SELECT 
            category,
            COUNT(*) AS usage_count,
            SUM(amount) AS total_spent
        FROM expenses
        GROUP BY category
    ");
    $stmt->execute();
    $usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $usage_map = [];
    foreach ($usage as $row) {
        $usage_map[$row['category']] = [
            'count' => $row['usage_count'],
            'total' => $row['total_spent']
        ];
    }
    
    $result = [];
    foreach ($categories as $key => $label) {
        $result[] = [
            'value' => $key,
            'label' => $label,
            'usage_count' => $usage_map[$key]['count'] ?? 0,
            'total_spent' => $usage_map[$key]['total'] ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $result
    ]);
}

/**
 * GET MONTHLY SUMMARY
 * Breakdown by month and category
 */
function getMonthlySummary($conn) {
    $months = $_GET['months'] ?? 12;
    
    $query = "
        SELECT 
            DATE_FORMAT(expense_date, '%Y-%m') AS month,
            category,
            COUNT(*) AS transaction_count,
            SUM(amount) AS total_amount
        FROM expenses
        WHERE expense_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        GROUP BY DATE_FORMAT(expense_date, '%Y-%m'), category
        ORDER BY month DESC, total_amount DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$months]);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get monthly totals
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(expense_date, '%Y-%m') AS month,
            COUNT(*) AS transaction_count,
            SUM(amount) AS total_amount,
            AVG(amount) AS avg_amount
        FROM expenses
        WHERE expense_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute([$months]);
    $monthly_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'monthly_summary' => $summary,
        'monthly_totals' => $monthly_totals
    ]);
}

/**
 * GET RECURRING EXPENSES
 * List recurring expenses
 */
function getRecurringExpenses($conn) {
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            u.full_name AS created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.is_recurring = 1
        ORDER BY e.recurrence_period, e.expense_date DESC
    ");
    $stmt->execute();
    $recurring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate projected monthly cost
    $monthly_projection = 0;
    foreach ($recurring as $expense) {
        $factor = match($expense['recurrence_period']) {
            'daily' => 30,
            'weekly' => 4.33,
            'monthly' => 1,
            'quarterly' => 0.33,
            'yearly' => 0.083,
            default => 0
        };
        $monthly_projection += $expense['amount'] * $factor;
    }
    
    echo json_encode([
        'success' => true,
        'recurring_expenses' => $recurring,
        'monthly_projection' => $monthly_projection,
        'count' => count($recurring)
    ]);
}
