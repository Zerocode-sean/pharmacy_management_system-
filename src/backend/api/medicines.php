<?php
// Temporarily enable errors to debug 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS and content headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Include config.php first to define constants
    require_once '../config/config.php';
    require_once '../config/database.php';
    
    // Only include security if it exists
    if (file_exists('../config/security.php')) {
        require_once '../config/security.php';
    }
    
    // Only include auth middleware if it exists
    if (file_exists('../config/auth_middleware.php')) {
        require_once '../config/auth_middleware.php';
    }
    
    // CORS headers
    if (file_exists('../config/cors.php')) {
        require_once '../config/cors.php';
    }
} catch (Exception $e) {
    error_log("Failed to include required files: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server configuration error']);
    exit();
}

// Development-friendly authentication for localhost
session_start();

// For development: Check if we're in local environment and allow easier access
$isLocalhost = (
    $_SERVER['HTTP_HOST'] === 'localhost' || 
    $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
    strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0
);

if ($isLocalhost && !isset($_SESSION['user_id'])) {
    // Check if there's a user in localStorage and create appropriate session
    $user_data = $_POST['user_data'] ?? $_GET['user_data'] ?? null;
    
    // Auto-create a pharmacist session for development
    $_SESSION['user_id'] = 3;
    $_SESSION['username'] = 'pharmacist1';
    $_SESSION['role'] = 'pharmacist';
    $_SESSION['last_activity'] = time();
    
    error_log("Development: Auto-created pharmacist session for localhost");
}

// Now check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Role-based access control
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, ['admin', 'cashier', 'pharmacist'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Role: ' . $userRole]);
        exit();
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception('Database connection failed');
        }
    } catch (Exception $e) {
        error_log("Database error in medicines API: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }
    
    switch($method) {
        case 'GET':
            // Allow admin, cashier, and pharmacist to view medicines
            handleGetMedicines($db);
            break;
        case 'POST':
            // Allow admin and pharmacist to create medicines
            if (!in_array($userRole, ['admin', 'pharmacist'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin or Pharmacist access required to create medicines']);
                exit();
            }
            handleCreateMedicine($db);
            break;
        case 'PUT':
            // Allow admin, cashier, and pharmacist to update medicine stock
            handleUpdateMedicine($db);
            break;
        case 'DELETE':
            // Only admin can delete medicines
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required to delete medicines']);
                exit();
            }
            handleDeleteMedicine($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Medicines API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleGetMedicines($db) {
    try {
        // Check if this is a stats-only request
        if (isset($_GET['action']) && $_GET['action'] === 'stats') {
            // Get statistics only
            $statsQuery = "SELECT 
                COUNT(*) as total_medicines,
                SUM(CASE WHEN stock_quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN expiry_date <= CURDATE() THEN 1 ELSE 0 END) as expired
                FROM medicines";
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->execute();
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            return;
        }
        
        // Regular medicines request
        // Only return active medicines for POS system
        $query = "SELECT * FROM medicines WHERE is_active = 1 ORDER BY name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $medicines
        ]);
        
    } catch (Exception $e) {
        error_log("Get medicines error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch medicines']);
    }
}

function handleCreateMedicine($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            return;
        }
        
        // Check if manufacturer column exists
        $structQuery = $db->query("DESCRIBE medicines");
        $columns = $structQuery->fetchAll(PDO::FETCH_COLUMN);
        $hasManufacturer = in_array('manufacturer', $columns);
        
        // Check actual table structure - we have category_id not category
        $hasCategory = in_array('category', $columns);
        $hasCategoryId = in_array('category_id', $columns);
        $hasSupplierId = in_array('supplier_id', $columns);
        
        // Validate required fields based on actual table structure
        $required = ['name', 'unit_price', 'stock_quantity'];
        
        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                return;
            }
        }
        
        // Set selling_price to unit_price if not provided (for backward compatibility)
        if (!isset($input['selling_price']) || $input['selling_price'] === '') {
            $input['selling_price'] = $input['unit_price'];
        }
        
        // Handle category mapping - convert string category to category_id
        $category_id = 1; // Default category ID
        if (isset($input['category']) && !empty($input['category'])) {
            // Try to find existing category or create it
            $categoryName = trim($input['category']);
            $stmt = $db->prepare("SELECT id FROM categories WHERE name = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$categoryName]);
            $existingCategory = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingCategory) {
                $category_id = $existingCategory['id'];
            } else {
                // Create new category
                $stmt = $db->prepare("INSERT INTO categories (name, is_active, created_at, updated_at) VALUES (?, 1, NOW(), NOW())");
                $result = $stmt->execute([$categoryName]);
                if ($result) {
                    $category_id = $db->lastInsertId();
                } else {
                    error_log("Failed to create category: " . $categoryName);
                    $category_id = 1; // Fallback to default
                }
            }
        }
        
        // Handle supplier mapping - convert manufacturer string to supplier_id
        $supplier_id = 1; // Default supplier ID
        if (isset($input['manufacturer']) && !empty($input['manufacturer'])) {
            // Try to find existing supplier or create it
            $supplierName = trim($input['manufacturer']);
            $stmt = $db->prepare("SELECT id FROM suppliers WHERE company_name = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$supplierName]);
            $existingSupplier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingSupplier) {
                $supplier_id = $existingSupplier['id'];
            } else {
                // Create new supplier
                // Generate dummy email to avoid unique constraint violation
                $dummyEmail = 'supplier_' . time() . '_' . rand(1000, 9999) . '@placeholder.com';
                $stmt = $db->prepare("INSERT INTO suppliers (company_name, email, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
                $stmt->execute([$supplierName, $dummyEmail]);
                $supplier_id = $db->lastInsertId();
            }
        }
        
        // Build query based on actual table structure
        $fields = ['name', 'generic_name', 'unit_price', 'selling_price', 'stock_quantity', 'reorder_level', 'expiry_date', 'description', 'category_id', 'supplier_id'];
        $values = [':name', ':generic_name', ':unit_price', ':selling_price', ':stock_quantity', ':reorder_level', ':expiry_date', ':description', ':category_id', ':supplier_id'];
        
        // Always include timestamps
        $fields[] = 'created_at';
        $fields[] = 'updated_at';
        $values[] = 'NOW()';
        $values[] = 'NOW()';
        
        // Add optional fields if they exist in table and input
        $optionalFields = ['brand', 'barcode', 'min_stock_level', 'manufacture_date', 'batch_number', 'cost_price', 'minimum_stock'];
        foreach ($optionalFields as $field) {
            if (in_array($field, $columns) && isset($input[$field]) && $input[$field] !== '') {
                $fields[] = $field;
                $values[] = ':' . $field;
            }
        }
        
        $query = "INSERT INTO medicines (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        
        $stmt = $db->prepare($query);
        
        // Prepare variables for binding (bindParam requires variable references)
        $name = $input['name'];
        $generic_name = $input['generic_name'];
        $unit_price = $input['unit_price'];
        $selling_price = $input['selling_price'];
        $stock_quantity = $input['stock_quantity'];
        $reorder_level = $input['reorder_level'] ?? 10;
        $expiry_date = $input['expiry_date'] ?? null;
        $description = $input['description'] ?? '';
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':generic_name', $generic_name);
        $stmt->bindParam(':unit_price', $unit_price);
        $stmt->bindParam(':selling_price', $selling_price);
        $stmt->bindParam(':stock_quantity', $stock_quantity);
        $stmt->bindParam(':reorder_level', $reorder_level);
        $stmt->bindParam(':expiry_date', $expiry_date);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':supplier_id', $supplier_id);
        
        // Bind optional fields
        if (isset($input['brand']) && $input['brand'] !== '') {
            $brand = $input['brand'];
            $stmt->bindParam(':brand', $brand);
        }
        if (isset($input['barcode']) && $input['barcode'] !== '') {
            $barcode = $input['barcode'];
            $stmt->bindParam(':barcode', $barcode);
        }
        if (isset($input['selling_price']) && $input['selling_price'] !== '') {
            $selling_price = $input['selling_price'];
            $stmt->bindParam(':selling_price', $selling_price);
        }
        if (isset($input['min_stock_level']) && $input['min_stock_level'] !== '') {
            $min_stock_level = $input['min_stock_level'];
            $stmt->bindParam(':min_stock_level', $min_stock_level);
        }
        if (isset($input['manufacture_date']) && $input['manufacture_date'] !== '') {
            $manufacture_date = $input['manufacture_date'];
            $stmt->bindParam(':manufacture_date', $manufacture_date);
        }
        if (isset($input['batch_number']) && $input['batch_number'] !== '') {
            $batch_number = $input['batch_number'];
            $stmt->bindParam(':batch_number', $batch_number);
        }
        if (isset($input['cost_price']) && $input['cost_price'] !== '') {
            $cost_price = $input['cost_price'];
            $stmt->bindParam(':cost_price', $cost_price);
        }
        if (isset($input['minimum_stock']) && $input['minimum_stock'] !== '') {
            $minimum_stock = $input['minimum_stock'];
            $stmt->bindParam(':minimum_stock', $minimum_stock);
        }
        
        if ($stmt->execute()) {
            $medicineId = $db->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => 'Medicine created successfully',
                'medicine_id' => $medicineId
            ]);
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Database error creating medicine: " . print_r($errorInfo, true));
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to create medicine',
                'debug_error' => $errorInfo,
                'debug_query' => $query,
                'debug_data' => [
                    'name' => $name,
                    'generic_name' => $generic_name,
                    'category' => $category,
                    'unit_price' => $unit_price,
                    'stock_quantity' => $stock_quantity
                ]
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Create medicine error: " . $e->getMessage());
        http_response_code(500);
        // Return specific error for debugging
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to create medicine: ' . $e->getMessage(),
            'debug_info' => $e->getTraceAsString()
        ]);
    }
}

function handleUpdateMedicine($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid input or missing ID']);
            return;
        }
        
        // For cashiers, we mainly update stock quantity
        if (isset($input['stock_quantity'])) {
            $query = "UPDATE medicines SET 
                        stock_quantity = :stock_quantity,
                        updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $input['id']);
            $stmt->bindParam(':stock_quantity', $input['stock_quantity']);
        } else {
            // Full update for admin users
            $query = "UPDATE medicines SET 
                        name = :name,
                        generic_name = :generic_name,
                        manufacturer = :manufacturer,
                        category = :category,
                        unit_price = :unit_price,
                        stock_quantity = :stock_quantity,
                        reorder_level = :reorder_level,
                        expiry_date = :expiry_date,
                        description = :description,
                        updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $input['id']);
            $stmt->bindParam(':name', $input['name']);
            $stmt->bindParam(':generic_name', $input['generic_name']);
            $stmt->bindParam(':manufacturer', $input['manufacturer']);
            $stmt->bindParam(':category', $input['category']);
            $stmt->bindParam(':unit_price', $input['unit_price']);
            $stmt->bindParam(':stock_quantity', $input['stock_quantity']);
            $stmt->bindParam(':reorder_level', $input['reorder_level']);
            $stmt->bindParam(':expiry_date', $input['expiry_date']);
            $stmt->bindParam(':description', $input['description']);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Medicine updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update medicine']);
        }
        
    } catch (Exception $e) {
        error_log("Update medicine error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update medicine']);
    }
}

function handleDeleteMedicine($db) {
    try {
        // Get ID from query parameter
        $medicineId = $_GET['id'] ?? null;
        
        if (!$medicineId || !is_numeric($medicineId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
            return;
        }
        
        $query = "DELETE FROM medicines WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $medicineId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Medicine deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete medicine']);
        }
        
    } catch (Exception $e) {
        error_log("Delete medicine error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete medicine']);
    }
}
?>
