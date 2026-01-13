<?php
require_once 'config/config.php';
require_once 'core/Database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // 1. Create terminals table
    $sql1 = "CREATE TABLE IF NOT EXISTS `terminals` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `store_id` int(11) NOT NULL,
      `name` varchar(255) NOT NULL,
      `device_id` varchar(100) DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $conn->exec($sql1);
    echo "Table 'terminals' created or already exists.<br>";

    // 2. Add terminal_id to users
    // Check if column exists first to avoid error
    $check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'terminal_id'");
    if ($check->rowCount() == 0) {
        $sql2 = "ALTER TABLE `users` ADD COLUMN `terminal_id` int(11) DEFAULT NULL AFTER `store_id`;";
        $conn->exec($sql2);

        $sql3 = "ALTER TABLE `users` ADD CONSTRAINT `fk_users_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminals`(`id`) ON DELETE SET NULL;";
        $conn->exec($sql3);
        echo "Column 'terminal_id' added to 'users'.<br>";
    } else {
        echo "Column 'terminal_id' already exists in 'users'.<br>";
    }

    // 3. Add terminal_id to sales
    $checkSales = $conn->query("SHOW COLUMNS FROM `sales` LIKE 'terminal_id'");
    if ($checkSales->rowCount() == 0) {
        $sql4 = "ALTER TABLE `sales` ADD COLUMN `terminal_id` int(11) DEFAULT NULL AFTER `store_id`;";
        $conn->exec($sql4);

        $sql5 = "ALTER TABLE `sales` ADD CONSTRAINT `fk_sales_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminals`(`id`) ON DELETE SET NULL;";
        $conn->exec($sql5);
        echo "Column 'terminal_id' added to 'sales'.<br>";
    } else {
        echo "Column 'terminal_id' already exists in 'sales'.<br>";
    }

    // 4. Seed a default terminal for the main store if none exists
    // Assuming store_id 1 exists from seed data
    $stmt = $conn->query("SELECT count(*) FROM terminals WHERE store_id = 1");
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO terminals (store_id, name) VALUES (1, 'Main Terminal')");
        echo "Seeded 'Main Terminal' for Store 1.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>