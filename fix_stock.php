<?php
require_once 'config/config.php';
require_once 'core/Auth.php';

$db = new Database();
$conn = $db->getConnection();

$sku = 'US000DAY001';
$targetQty = -2;

// Get ID
$stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
$stmt->execute([$sku]);
$p = $stmt->fetch();

if ($p) {
    $id = $p['id'];
    echo "Updating Product ID $id to quantity $targetQty...\n";

    // Update inventory_stock
    $upd1 = $conn->prepare("UPDATE inventory_stock SET quantity = ? WHERE product_id = ?");
    $upd1->execute([$targetQty, $id]);

    // Update products table
    $upd2 = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
    $upd2->execute([$targetQty, $id]);

    echo "Done.\n";
} else {
    echo "Product not found.\n";
}
?>