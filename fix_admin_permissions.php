<?php
// fix_admin_permissions.php
require_once 'config/config.php';
require_once 'core/Database.php';

echo "Updating Admin Permissions...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get All Permission IDs
    $stmt = $conn->query("SELECT id FROM permissions");
    $allPerms = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Clear existing role_permissions for Admin (Role 1)
    $conn->exec("DELETE FROM role_permissions WHERE role_id = 1");

    // 3. Re-insert ALL
    $stmtInsert = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, ?)");

    $count = 0;
    foreach ($allPerms as $permId) {
        $stmtInsert->execute([$permId]);
        $count++;
    }

    echo "Successfully assigned $count permissions to Admin (Role 1).\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
