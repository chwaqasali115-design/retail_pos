<?php
// seed_more_permissions.php
require_once 'config/config.php';
require_once 'core/Database.php';

echo "Seeding additional permissions...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Helper to insert if not exists
    function addResource($conn, $moduleName, $resName, $label)
    {
        $stmt = $conn->prepare("SELECT id FROM modules WHERE module_name = ?");
        $stmt->execute([$moduleName]);
        $modId = $stmt->fetchColumn();

        if (!$modId) {
            echo "Module $moduleName not found!\n";
            return 0;
        }

        $stmt = $conn->prepare("SELECT id FROM resources WHERE module_id = ? AND resource_name = ?");
        $stmt->execute([$modId, $resName]);
        $resId = $stmt->fetchColumn();

        if (!$resId) {
            $stmt = $conn->prepare("INSERT INTO resources (module_id, resource_name, label) VALUES (?, ?, ?)");
            $stmt->execute([$modId, $resName, $label]);
            $resId = $conn->lastInsertId();
            echo "Created Resource: $label\n";
        }
        return $resId;
    }

    function addPerm($conn, $resId, $action, $label, $slug)
    {
        if (!$resId)
            return;

        $stmt = $conn->prepare("SELECT id FROM permissions WHERE slug = ?");
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            $stmt = $conn->prepare("INSERT INTO permissions (resource_id, action, label, slug) VALUES (?, ?, ?, ?)");
            $stmt->execute([$resId, $action, $label, $slug]);
            echo "  + Permission: $slug\n";

            // Auto grant to Admin (Role 1)
            $permId = $conn->lastInsertId();
            $conn->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, $permId)");
        }
    }

    // --- PURCHASES ---
    $resId = addResource($conn, 'purchases', 'orders', 'Purchase Orders');
    addPerm($conn, $resId, 'view', 'View Orders', 'purchases.orders.view');
    addPerm($conn, $resId, 'create', 'Create Order', 'purchases.orders.create');
    addPerm($conn, $resId, 'edit', 'Edit Order', 'purchases.orders.edit');

    $resId = addResource($conn, 'purchases', 'vendors', 'Vendors');
    addPerm($conn, $resId, 'view', 'View Vendors', 'purchases.vendors.view');
    addPerm($conn, $resId, 'create', 'Create Vendor', 'purchases.vendors.create');

    // --- ACCOUNTING ---
    $resId = addResource($conn, 'accounting', 'accounts', 'Chart of Accounts');
    addPerm($conn, $resId, 'view', 'View Accounts', 'accounting.accounts.view');

    $resId = addResource($conn, 'accounting', 'transactions', 'Transactions');
    addPerm($conn, $resId, 'view', 'View Transactions', 'accounting.transactions.view');

    // --- REPORTS ---
    $resId = addResource($conn, 'reports', 'reports', 'Reports');
    addPerm($conn, $resId, 'view', 'View Reports', 'reports.view');
    addPerm($conn, $resId, 'sales', 'View Sales Reports', 'reports.sales');
    addPerm($conn, $resId, 'stock', 'View Stock Reports', 'reports.stock');
    addPerm($conn, $resId, 'customers', 'View Customer Reports', 'reports.customers');

    echo "Done!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
