<?php
require_once 'config/config.php';
require_once 'core/Auth.php';

$db = new Database();
$conn = $db->getConnection();

$sku = 'US000DAY001';

// 1. Get Product ID
$stmt = $conn->prepare("SELECT id, name, stock_quantity FROM products WHERE sku = ?");
$stmt->execute([$sku]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "Product not found.\n";
    exit;
}

echo "Product ID: " . $product['id'] . "\n";
echo "Name: " . $product['name'] . "\n";
echo "Product Table Stock: " . $product['stock_quantity'] . "\n";

// 2. Get Inventory Stock
$stmt = $conn->prepare("SELECT * FROM inventory_stock WHERE product_id = ?");
$stmt->execute([$product['id']]);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nInventory Stock Table:\n";
foreach ($stocks as $s) {
    echo "Warehouse " . $s['warehouse_id'] . ": " . $s['quantity'] . "\n";
}

// 3. Get Transactions
$stmt = $conn->prepare("SELECT * FROM inventory_transactions WHERE product_id = ? ORDER BY created_at DESC");
$stmt->execute([$product['id']]);
$txns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nInventory Transactions:\n";
foreach ($txns as $t) {
    echo $t['created_at'] . " | " . $t['type'] . " | Qty: " . $t['quantity'] . " | Ref: " . $t['reference_id'] . "\n";
}
?>