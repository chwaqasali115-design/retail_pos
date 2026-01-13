<?php
require_once 'config/config.php';
require_once 'core/Auth.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Migrating Database: Customer Opening Balance...</h2>";

try {
    // Add opening_balance column
    try {
        $conn->exec("ALTER TABLE customers ADD COLUMN opening_balance decimal(15,2) DEFAULT 0.00 AFTER tax_number");
        echo "Column 'opening_balance' added successfully.<br>";
    } catch (PDOException $e) {
        echo "Column 'opening_balance' might already exist or error: " . $e->getMessage() . "<br>";
    }

    echo "<h3 style='color:green'>Migration Completed!</h3>";
    echo "<a href='customers.php'>Go to Customers</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Migration Failed: " . $e->getMessage() . "</h3>";
}
?>