<?php
// Diagnostic script to test POS search
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== POS API DIAGNOSTIC ===\n";

// Test 1: Config
require_once 'config/config.php';
echo "1. Config loaded. DB_HOST=" . DB_HOST . ", DB_NAME=" . DB_NAME . "\n";

// Test 2: Database
require_once 'core/Database.php';
$db = new Database();
$conn = $db->getConnection();
if ($conn) {
    echo "2. Database connected OK\n";
} else {
    echo "2. Database FAILED\n";
    exit;
}

// Test 3: Session
require_once 'core/Session.php';
echo "3. Session started. user_id=" . ($_SESSION['user_id'] ?? 'NULL') . ", company_id=" . ($_SESSION['company_id'] ?? 'NULL') . "\n";

// Test 4: Auth include
try {
    require_once 'core/Auth.php';
    echo "4. Auth.php loaded\n";
} catch (Exception $e) {
    echo "4. Auth.php FAILED: " . $e->getMessage() . "\n";
}

// Test 5: AccountingHelper include
try {
    require_once 'core/AccountingHelper.php';
    echo "5. AccountingHelper.php loaded\n";
} catch (Exception $e) {
    echo "5. AccountingHelper.php FAILED: " . $e->getMessage() . "\n";
}

// Test 6: FBRService include
try {
    require_once 'core/FBRService.php';
    echo "6. FBRService.php loaded\n";
} catch (Exception $e) {
    echo "6. FBRService.php FAILED: " . $e->getMessage() . "\n";
}

// Test 7: Product search query
try {
    $q = 'stitch';
    $cid = $_SESSION['company_id'] ?? 1;
    $stmt = $conn->prepare("SELECT id, name, price FROM products WHERE (name LIKE :q OR sku LIKE :q OR barcode LIKE :q) AND company_id = :cid LIMIT 5");
    $stmt->execute([':q' => "%$q%", ':cid' => $cid]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "7. Product search OK. Found " . count($results) . " results\n";
    print_r($results);
} catch (PDOException $e) {
    echo "7. Product search FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
