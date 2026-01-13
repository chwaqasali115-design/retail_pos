<?php
require_once 'config/config.php';
require_once 'core/Database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Adding 'notes' column to sales table...\n";
try {
    $conn->exec("ALTER TABLE sales ADD COLUMN notes TEXT NULL AFTER status");
    echo "Column added successfully.\n";
} catch (PDOException $e) {
    echo "Error (might already exist): " . $e->getMessage() . "\n";
}
?>