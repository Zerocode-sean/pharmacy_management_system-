<?php
/**
 * SUPPLIER PAYMENTS API
 * =====================
 * Manage payments to suppliers for purchase orders
 * 
 * Actions:
 * - GET ?action=list - List all payments with filters
 * - GET ?action=supplier_balance&supplier_id=X - Get supplier balance
 * - GET ?action=pending_payments - List unpaid purchase orders
 * - POST ?action=create - Create new payment record
 * - GET ?action=payment_details&id=X - Get single payment details
 * - POST ?action=update&id=X - Update payment record
 * - POST ?action=delete&id=X - Delete payment record
 * - GET ?action=stats - Get payment statistics
 * - POST ?action=approve&id=X - Approve a pending payment
 * - GET ?action=report - Generate comprehensive payment report
 */

// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/supplier_payments_errors.log');

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

// Allow admin and pharmacist to manage supplier payments
if (!in_array($_SESSION['role'] ?? '', ['admin', 'pharmacist'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions. Only administrators and pharmacists can manage supplier payments.']);
    exit;
}

// Create database connection
$database = new Database();
$conn = $database->connect();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            listPayments($conn);
            break;
            
        case 'supplier_balance':
            getSupplierBalance($conn);
            break;
            
        case 'pending_payments':
            getPendingPayments($conn);
            break;
            
        case 'create':
            createPayment($conn);
            break;
            
        case 'payment_details':
            getPaymentDetails($conn);
            break;
            
        case 'update':
            updatePayment($conn);
            break;
            
        case 'delete':
            deletePayment($conn);
            break;
            
        case 'stats':
            getPaymentStats($conn);
            break;
            
        case 'approve':
            approvePayment($conn);
            break;
            
        case 'report':
            generatePaymentReport($conn);
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
 * LIST PAYMENTS
 * Get all supplier payments with optional filters
 */
function listPayments($conn) {
    $supplier_id = $_GET['supplier_id'] ?? null;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? null;
    $status = $_GET['status'] ?? null;
    $payment_method = $_GET['payment_method'] ?? null;
    
    $query = "
        SELECT 
            sp.id,
            sp.supplier_id,
            s.company_name AS supplier_name,
            s.contact_person,
            sp.purchase_order_id,
            po.id AS po_number,
            sp.payment_date,
            sp.amount,
            sp.payment_method,
            sp.reference_number,
            sp.bank_name,
            sp.cheque_number,
            sp.status,
            sp.notes,
            u.full_name AS created_by_name,
            sp.created_at
        FROM supplier_payments sp
        LEFT JOIN suppliers s ON sp.supplier_id = s.id
        LEFT JOIN purchase_orders po ON sp.purchase_order_id = po.id
        LEFT JOIN users u ON sp.created_by = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($supplier_id) {
        $query .= " AND sp.supplier_id = ?";
        $params[] = $supplier_id;
    }
    
    if ($from_date) {
        $query .= " AND sp.payment_date >= ?";
        $params[] = $from_date;
    }
    
    if ($to_date) {
        $query .= " AND sp.payment_date <= ?";
        $params[] = $to_date;
    }
    
    if ($status) {
        $query .= " AND sp.status = ?";
        $params[] = $status;
    }
    
    if ($payment_method) {
        $query .= " AND sp.payment_method = ?";
        $params[] = $payment_method;
    }
    
    $query .= " ORDER BY sp.payment_date DESC, sp.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_amount = array_sum(array_column($payments, 'amount'));
    
    echo json_encode([
        'success' => true,
        'payments' => $payments,
        'count' => count($payments),
        'total_amount' => $total_amount
    ]);
}

/**
 * GET SUPPLIER BALANCE
 * Calculate supplier outstanding balance
 */
function getSupplierBalance($conn) {
    $supplier_id = $_GET['supplier_id'] ?? null;
    
    if (!$supplier_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Supplier ID required']);
        return;
    }
    
    $query = "
        SELECT 
            s.id,
            s.company_name,
            s.contact_person,
            s.phone,
            s.email,
            s.outstanding_balance,
            s.credit_limit,
            s.last_payment_date,
            COALESCE(SUM(po.total_amount), 0) AS total_purchases,
            COALESCE(SUM(sp.amount), 0) AS total_payments,
            COUNT(DISTINCT po.id) AS total_orders,
            COUNT(DISTINCT sp.id) AS payment_count
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.id = po.supplier_id
        LEFT JOIN supplier_payments sp ON s.id = sp.supplier_id
        WHERE s.id = ?
        GROUP BY s.id
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$supplier_id]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$balance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Supplier not found']);
        return;
    }
    
    // Get recent transactions
    $stmt = $conn->prepare("
        SELECT 
            'payment' AS type,
            sp.payment_date AS date,
            sp.amount,
            sp.payment_method,
            sp.reference_number,
            po.id AS reference
        FROM supplier_payments sp
        LEFT JOIN purchase_orders po ON sp.purchase_order_id = po.id
        WHERE sp.supplier_id = ?
        
        UNION ALL
        
        SELECT 
            'purchase' AS type,
            po.order_date AS date,
            po.total_amount AS amount,
            NULL AS payment_method,
            po.id AS reference_number,
            NULL AS reference
        FROM purchase_orders po
        WHERE po.supplier_id = ?
        
        ORDER BY date DESC
        LIMIT 20
    ");
    
    $stmt->execute([$supplier_id, $supplier_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'balance' => $balance,
        'transactions' => $transactions
    ]);
}

/**
 * GET PENDING PAYMENTS
 * List purchase orders that haven't been fully paid
 */
function getPendingPayments($conn) {
    $query = "
        SELECT 
            po.id AS purchase_order_id,
            po.id AS order_number,
            po.order_date,
            po.expected_date,
            po.supplier_id,
            s.company_name AS supplier_name,
            s.contact_person,
            s.phone,
            s.outstanding_balance AS supplier_balance,
            po.total_amount AS order_total,
            COALESCE(SUM(sp.amount), 0) AS amount_paid,
            (po.total_amount - COALESCE(SUM(sp.amount), 0)) AS amount_due,
            po.status,
            DATEDIFF(NOW(), po.order_date) AS days_overdue
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN supplier_payments sp ON po.id = sp.purchase_order_id
        WHERE po.status IN ('pending', 'received')
        GROUP BY po.id
        HAVING amount_due > 0
        ORDER BY days_overdue DESC, po.order_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_due = array_sum(array_column($pending, 'amount_due'));
    
    echo json_encode([
        'success' => true,
        'pending_payments' => $pending,
        'count' => count($pending),
        'total_due' => $total_due
    ]);
}

/**
 * CREATE PAYMENT
 * Record new supplier payment
 */
function createPayment($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['supplier_id', 'payment_date', 'amount', 'payment_method'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    $conn->beginTransaction();
    
    try {
        $query = "
            INSERT INTO supplier_payments (
                supplier_id,
                purchase_order_id,
                payment_date,
                amount,
                payment_method,
                reference_number,
                bank_name,
                cheque_number,
                account_number,
                notes,
                status,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $input['supplier_id'],
            $input['purchase_order_id'] ?? null,
            $input['payment_date'],
            $input['amount'],
            $input['payment_method'],
            $input['reference_number'] ?? null,
            $input['bank_name'] ?? null,
            $input['cheque_number'] ?? null,
            $input['account_number'] ?? null,
            $input['notes'] ?? null,
            $input['status'] ?? 'cleared',
            $_SESSION['user_id']
        ]);
        
        $payment_id = $conn->lastInsertId();
        
        // Update supplier outstanding balance (trigger will handle this)
        // But let's also log the activity
        $log_query = "
            INSERT INTO user_activity (
                user_id,
                activity_type,
                description,
                ip_address
            ) VALUES (?, 'supplier_payment', ?, ?)
        ";
        
        $stmt = $conn->prepare($log_query);
        $stmt->execute([
            $_SESSION['user_id'],
            "Created supplier payment #{$payment_id} for KES " . number_format($input['amount'], 2),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'payment_id' => $payment_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * GET PAYMENT DETAILS
 * Get single payment record
 */
function getPaymentDetails($conn) {
    $payment_id = $_GET['id'] ?? null;
    
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment ID required']);
        return;
    }
    
    $query = "
        SELECT 
            sp.*,
            s.company_name AS supplier_name,
            s.contact_person,
            s.phone,
            s.email,
            po.id AS po_number,
            po.order_date,
            po.total_amount AS po_total,
            u.full_name AS created_by_name,
            a.full_name AS approved_by_name
        FROM supplier_payments sp
        LEFT JOIN suppliers s ON sp.supplier_id = s.id
        LEFT JOIN purchase_orders po ON sp.purchase_order_id = po.id
        LEFT JOIN users u ON sp.created_by = u.id
        LEFT JOIN users a ON sp.approved_by = a.id
        WHERE sp.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'payment' => $payment
    ]);
}

/**
 * UPDATE PAYMENT
 * Modify existing payment record
 */
function updatePayment($conn) {
    $payment_id = $_GET['id'] ?? $_POST['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment ID required']);
        return;
    }
    
    $updates = [];
    $params = [];
    
    $allowed_fields = [
        'payment_date', 'amount', 'payment_method', 'reference_number',
        'bank_name', 'cheque_number', 'account_number', 'notes', 'status'
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
    
    $params[] = $payment_id;
    $query = "UPDATE supplier_payments SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment updated successfully'
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
 * DELETE PAYMENT
 * Remove payment record
 */
function deletePayment($conn) {
    $payment_id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment ID required']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM supplier_payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Payment not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment deleted successfully'
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
 * GET PAYMENT STATS
 * Statistics and summary
 */
function getPaymentStats($conn) {
    $period = $_GET['period'] ?? 'month'; // month, quarter, year
    
    $date_filter = match($period) {
        'month' => "DATE_SUB(NOW(), INTERVAL 1 MONTH)",
        'quarter' => "DATE_SUB(NOW(), INTERVAL 3 MONTH)",
        'year' => "DATE_SUB(NOW(), INTERVAL 1 YEAR)",
        default => "DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    };
    
    // Total payments
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS payment_count,
            COALESCE(SUM(amount), 0) AS total_paid,
            AVG(amount) AS avg_payment
        FROM supplier_payments
        WHERE payment_date >= $date_filter
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // By payment method
    $stmt = $conn->prepare("
        SELECT 
            payment_method,
            COUNT(*) AS count,
            SUM(amount) AS total
        FROM supplier_payments
        WHERE payment_date >= $date_filter
        GROUP BY payment_method
        ORDER BY total DESC
    ");
    $stmt->execute();
    $by_method = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Outstanding balance by supplier
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.company_name,
            s.outstanding_balance
        FROM suppliers s
        WHERE s.outstanding_balance > 0
        ORDER BY s.outstanding_balance DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_outstanding = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'period' => $period,
        'stats' => $stats,
        'by_payment_method' => $by_method,
        'top_outstanding_suppliers' => $top_outstanding
    ]);
}

/**
 * APPROVE PAYMENT
 * Approve a pending payment and update supplier balance
 */
function approvePayment($conn) {
    $payment_id = $_POST['id'] ?? null;
    
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Get payment details
        $stmt = $conn->prepare("
            SELECT sp.*, s.outstanding_balance 
            FROM supplier_payments sp
            JOIN suppliers s ON sp.supplier_id = s.id
            WHERE sp.id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        if ($payment['status'] === 'cleared') {
            throw new Exception('Payment is already approved');
        }
        
        // Update payment status
        $stmt = $conn->prepare("
            UPDATE supplier_payments 
            SET status = 'cleared',
                approved_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $payment_id]);
        
        // Update supplier outstanding balance
        $new_balance = $payment['outstanding_balance'] - $payment['amount'];
        $stmt = $conn->prepare("
            UPDATE suppliers 
            SET outstanding_balance = ?
            WHERE id = ?
        ");
        $stmt->execute([$new_balance, $payment['supplier_id']]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment approved successfully',
            'new_balance' => $new_balance
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error approving payment: ' . $e->getMessage()
        ]);
    }
}

/**
 * GENERATE PAYMENT REPORT
 * Generate comprehensive payment report with various filters
 */
function generatePaymentReport($conn) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
    $supplier_id = $_GET['supplier_id'] ?? null;
    $payment_method = $_GET['payment_method'] ?? null;
    $status = $_GET['status'] ?? null;
    
    // Build query
    $query = "
        SELECT 
            sp.id,
            sp.payment_date,
            s.company_name AS supplier_name,
            s.phone AS supplier_phone,
            sp.amount,
            sp.payment_method,
            sp.reference_number,
            sp.status,
            sp.notes,
            po.id AS po_number,
            u.full_name AS created_by,
            a.full_name AS approved_by
        FROM supplier_payments sp
        LEFT JOIN suppliers s ON sp.supplier_id = s.id
        LEFT JOIN purchase_orders po ON sp.purchase_order_id = po.id
        LEFT JOIN users u ON sp.created_by = u.id
        LEFT JOIN users a ON sp.approved_by = a.id
        WHERE sp.payment_date BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];
    
    if ($supplier_id) {
        $query .= " AND sp.supplier_id = ?";
        $params[] = $supplier_id;
    }
    
    if ($payment_method) {
        $query .= " AND sp.payment_method = ?";
        $params[] = $payment_method;
    }
    
    if ($status) {
        $query .= " AND sp.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY sp.payment_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary
    $total_payments = count($payments);
    $total_amount = array_sum(array_column($payments, 'amount'));
    
    // By payment method
    $by_method = [];
    foreach ($payments as $payment) {
        $method = $payment['payment_method'];
        if (!isset($by_method[$method])) {
            $by_method[$method] = ['count' => 0, 'total' => 0];
        }
        $by_method[$method]['count']++;
        $by_method[$method]['total'] += $payment['amount'];
    }
    
    // By status
    $by_status = [];
    foreach ($payments as $payment) {
        $status = $payment['status'];
        if (!isset($by_status[$status])) {
            $by_status[$status] = ['count' => 0, 'total' => 0];
        }
        $by_status[$status]['count']++;
        $by_status[$status]['total'] += $payment['amount'];
    }
    
    // By supplier
    $by_supplier = [];
    foreach ($payments as $payment) {
        $supplier = $payment['supplier_name'];
        if (!isset($by_supplier[$supplier])) {
            $by_supplier[$supplier] = ['count' => 0, 'total' => 0];
        }
        $by_supplier[$supplier]['count']++;
        $by_supplier[$supplier]['total'] += $payment['amount'];
    }
    
    echo json_encode([
        'success' => true,
        'report' => [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'summary' => [
                'total_payments' => $total_payments,
                'total_amount' => $total_amount,
                'average_payment' => $total_payments > 0 ? $total_amount / $total_payments : 0
            ],
            'by_payment_method' => $by_method,
            'by_status' => $by_status,
            'by_supplier' => $by_supplier,
            'payments' => $payments
        ]
    ]);
}
