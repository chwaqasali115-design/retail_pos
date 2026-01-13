<?php
require_once 'config/config.php';
require_once 'core/Database.php';

$db = new Database();
$conn = $db->getConnection();

echo "--- Products Table Schema ---\n";
$stmt = $conn->query("DESCRIBE products");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
?>