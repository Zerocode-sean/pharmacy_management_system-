<?php
/**
 * Quick Fix: Add Sample Medicines to Database
 * Run this script to populate the database with sample medicines if empty
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../src/backend/config/config.php';
    require_once '../src/backend/config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check current medicine count
    $countStmt = $db->query("SELECT COUNT(*) as count FROM medicines");
    $currentCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'step' => 'check',
        'current_count' => $currentCount,
        'message' => "Found $currentCount medicines in database"
    ]) . "\n";
    
    // Get categories
    $categoriesStmt = $db->query("SELECT id, name FROM medicine_categories LIMIT 5");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categories)) {
        // Create sample categories
        $sampleCategories = [
            'Pain Relief',
            'Antibiotics',
            'Vitamins',
            'Cold & Flu',
            'Digestive Health'
        ];
        
        foreach ($sampleCategories as $catName) {
            $db->exec("INSERT INTO medicine_categories (name, description, created_at) 
                      VALUES ('$catName', 'Sample category', NOW())");
        }
        
        $categoriesStmt = $db->query("SELECT id, name FROM medicine_categories LIMIT 5");
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'step' => 'categories',
            'created' => count($categories),
            'message' => 'Created sample categories'
        ]) . "\n";
    }
    
    // Get suppliers
    $suppliersStmt = $db->query("SELECT id, name FROM suppliers LIMIT 5");
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($suppliers)) {
        // Create sample suppliers
        $sampleSuppliers = [
            'Generic Pharmaceuticals Ltd',
            'MedSupply Kenya',
            'PharmaCo International',
            'HealthCare Distributors',
            'MediLink Suppliers'
        ];
        
        foreach ($sampleSuppliers as $supName) {
            $db->exec("INSERT INTO suppliers (name, contact_person, email, phone, created_at) 
                      VALUES ('$supName', 'Contact Person', 'contact@example.com', '0700000000', NOW())");
        }
        
        $suppliersStmt = $db->query("SELECT id, name FROM suppliers LIMIT 5");
        $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'step' => 'suppliers',
            'created' => count($suppliers),
            'message' => 'Created sample suppliers'
        ]) . "\n";
    }
    
    // Sample medicines with good stock and future expiry dates
    $sampleMedicines = [
        [
            'name' => 'Paracetamol 500mg Tablets',
            'generic_name' => 'Paracetamol',
            'description' => 'Pain relief and fever reducer',
            'dosage' => '500mg',
            'form' => 'Tablet',
            'unit_price' => 50.00,
            'stock_quantity' => 500,
            'reorder_level' => 100,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ],
        [
            'name' => 'Amoxicillin 250mg Capsules',
            'generic_name' => 'Amoxicillin',
            'description' => 'Antibiotic for bacterial infections',
            'dosage' => '250mg',
            'form' => 'Capsule',
            'unit_price' => 150.00,
            'stock_quantity' => 300,
            'reorder_level' => 80,
            'expiry_date' => date('Y-m-d', strtotime('+18 months'))
        ],
        [
            'name' => 'Ibuprofen 400mg Tablets',
            'generic_name' => 'Ibuprofen',
            'description' => 'Anti-inflammatory and pain relief',
            'dosage' => '400mg',
            'form' => 'Tablet',
            'unit_price' => 80.00,
            'stock_quantity' => 400,
            'reorder_level' => 100,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ],
        [
            'name' => 'Vitamin C 1000mg',
            'generic_name' => 'Ascorbic Acid',
            'description' => 'Immune system support',
            'dosage' => '1000mg',
            'form' => 'Tablet',
            'unit_price' => 120.00,
            'stock_quantity' => 250,
            'reorder_level' => 60,
            'expiry_date' => date('Y-m-d', strtotime('+3 years'))
        ],
        [
            'name' => 'Cough Syrup 100ml',
            'generic_name' => 'Dextromethorphan',
            'description' => 'Cough suppressant',
            'dosage' => '100ml',
            'form' => 'Syrup',
            'unit_price' => 250.00,
            'stock_quantity' => 150,
            'reorder_level' => 40,
            'expiry_date' => date('Y-m-d', strtotime('+1 year'))
        ],
        [
            'name' => 'Aspirin 75mg Tablets',
            'generic_name' => 'Acetylsalicylic Acid',
            'description' => 'Blood thinner and pain relief',
            'dosage' => '75mg',
            'form' => 'Tablet',
            'unit_price' => 60.00,
            'stock_quantity' => 350,
            'reorder_level' => 90,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ],
        [
            'name' => 'Cetirizine 10mg Tablets',
            'generic_name' => 'Cetirizine',
            'description' => 'Antihistamine for allergies',
            'dosage' => '10mg',
            'form' => 'Tablet',
            'unit_price' => 100.00,
            'stock_quantity' => 200,
            'reorder_level' => 50,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ],
        [
            'name' => 'Omeprazole 20mg Capsules',
            'generic_name' => 'Omeprazole',
            'description' => 'Reduces stomach acid',
            'dosage' => '20mg',
            'form' => 'Capsule',
            'unit_price' => 180.00,
            'stock_quantity' => 180,
            'reorder_level' => 45,
            'expiry_date' => date('Y-m-d', strtotime('+18 months'))
        ],
        [
            'name' => 'Multivitamin Tablets',
            'generic_name' => 'Multivitamin Complex',
            'description' => 'Daily vitamin supplement',
            'dosage' => '1 tablet',
            'form' => 'Tablet',
            'unit_price' => 200.00,
            'stock_quantity' => 300,
            'reorder_level' => 70,
            'expiry_date' => date('Y-m-d', strtotime('+3 years'))
        ],
        [
            'name' => 'Diclofenac 50mg Tablets',
            'generic_name' => 'Diclofenac',
            'description' => 'Anti-inflammatory pain relief',
            'dosage' => '50mg',
            'form' => 'Tablet',
            'unit_price' => 90.00,
            'stock_quantity' => 280,
            'reorder_level' => 75,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ],
        [
            'name' => 'Metformin 500mg Tablets',
            'generic_name' => 'Metformin',
            'description' => 'Diabetes medication',
            'dosage' => '500mg',
            'form' => 'Tablet',
            'unit_price' => 120.00,
            'stock_quantity' => 400,
            'reorder_level' => 100,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ],
        [
            'name' => 'Salbutamol Inhaler',
            'generic_name' => 'Salbutamol',
            'description' => 'Asthma relief inhaler',
            'dosage' => '100mcg',
            'form' => 'Inhaler',
            'unit_price' => 450.00,
            'stock_quantity' => 80,
            'reorder_level' => 20,
            'expiry_date' => date('Y-m-d', strtotime('+18 months'))
        ],
        [
            'name' => 'Loratadine 10mg Tablets',
            'generic_name' => 'Loratadine',
            'description' => 'Allergy relief',
            'dosage' => '10mg',
            'form' => 'Tablet',
            'unit_price' => 110.00,
            'stock_quantity' => 220,
            'reorder_level' => 55,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ],
        [
            'name' => 'Azithromycin 500mg Tablets',
            'generic_name' => 'Azithromycin',
            'description' => 'Antibiotic',
            'dosage' => '500mg',
            'form' => 'Tablet',
            'unit_price' => 280.00,
            'stock_quantity' => 150,
            'reorder_level' => 40,
            'expiry_date' => date('Y-m-d', strtotime('+18 months'))
        ],
        [
            'name' => 'Ranitidine 150mg Tablets',
            'generic_name' => 'Ranitidine',
            'description' => 'Heartburn relief',
            'dosage' => '150mg',
            'form' => 'Tablet',
            'unit_price' => 130.00,
            'stock_quantity' => 190,
            'reorder_level' => 50,
            'expiry_date' => date('Y-m-d', strtotime('+2 years'))
        ]
    ];
    
    $addedCount = 0;
    
    foreach ($sampleMedicines as $index => $medicine) {
        // Assign random category and supplier
        $category = $categories[array_rand($categories)];
        $supplier = $suppliers[array_rand($suppliers)];
        
        try {
            $stmt = $db->prepare("
                INSERT INTO medicines (
                    name, generic_name, description, dosage, form,
                    unit_price, stock_quantity, reorder_level,
                    expiry_date, category_id, supplier_id,
                    created_at, updated_at
                ) VALUES (
                    :name, :generic_name, :description, :dosage, :form,
                    :unit_price, :stock_quantity, :reorder_level,
                    :expiry_date, :category_id, :supplier_id,
                    NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                ':name' => $medicine['name'],
                ':generic_name' => $medicine['generic_name'],
                ':description' => $medicine['description'],
                ':dosage' => $medicine['dosage'],
                ':form' => $medicine['form'],
                ':unit_price' => $medicine['unit_price'],
                ':stock_quantity' => $medicine['stock_quantity'],
                ':reorder_level' => $medicine['reorder_level'],
                ':expiry_date' => $medicine['expiry_date'],
                ':category_id' => $category['id'],
                ':supplier_id' => $supplier['id']
            ]);
            
            $addedCount++;
            
        } catch (PDOException $e) {
            // Skip if medicine already exists
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                throw $e;
            }
        }
    }
    
    // Final count
    $finalCountStmt = $db->query("SELECT COUNT(*) as count FROM medicines");
    $finalCount = $finalCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'step' => 'complete',
        'added' => $addedCount,
        'total_before' => $currentCount,
        'total_after' => $finalCount,
        'message' => "Successfully added $addedCount sample medicines. Total medicines: $finalCount",
        'categories' => count($categories),
        'suppliers' => count($suppliers)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
