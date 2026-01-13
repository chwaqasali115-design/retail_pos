<?php
require_once 'config/config.php';
require_once 'core/Auth.php';

$db = new Database();
$conn = $db->getConnection();

echo "Checking Sales validity:\n";
$stmt = $conn->prepare("SELECT id, invoice_no, sale_date, status FROM sales ORDER BY id ASC");
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sales as $s) {
    echo "Sale ID: " . $s['id'] . " | " . $s['invoice_no'] . " | " . $s['status'] . "\n";
}
?>