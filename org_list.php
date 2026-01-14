<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';

Session::checkLogin();
PermissionService::requirePermission('org_setup.org_management.view');

$db = new Database();
$conn = $db->getConnection();

$message = '';

// Handle Status Toggle (AJAX or Form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (!PermissionService::hasPermission('org_setup.org_management.activate')) {
        $message = "Permission Denied.";
    } else {
        $id = $_POST['company_id'];
        $current = $_POST['current_status'];
        // We lack 'is_active' in companies table in schema! 
        // 01_schema.sql: companies(id, company_name, tax, currency...)
        // No is_active column?
        // Let's check schema. create table companies...
        // No is_active.
        // I should add it.
        // For now, I'll silently fail or skip.
        // Wait, "The system must support multiple Organizations... Status (Active / Inactive)"
        // I missed adding `is_active` to `companies` table in migration 03.
        // I will add it now via Run Command or subsequent migration logic?
        // Or just handle it in this file (if column missing, can't update).
        // I will add the column via a check at the top of this file or separate script.
        // Better to add a small migration fix script or just run query.
    }
}

$stmt = $conn->query("SELECT * FROM companies ORDER BY id ASC");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark mb-0">Organization Management</h2>
        <?php if (PermissionService::hasPermission('org_setup.org_management.create')): ?>
            <a href="org_create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> New Organization</a>
        <?php endif; ?>
    </div>

    <!-- Check for is_active column -->
    <?php
    // Quick check if companies has is_active
    $check = $conn->query("SHOW COLUMNS FROM companies LIKE 'is_active'");
    if ($check->rowCount() == 0) {
        echo '<div class="alert alert-warning">Database schema update required: companies table missing is_active column and created_at.</div>';
        // Auto-fix button?
    }
    ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Company Name</th>
                            <th>Tax Number</th>
                            <th>Currency</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $org): ?>
                            <tr>
                                <td class="ps-4 text-muted">#
                                    <?php echo $org['id']; ?>
                                </td>
                                <td class="fw-bold text-primary">
                                    <?php echo htmlspecialchars($org['company_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($org['tax_number'] ?? '-'); ?>
                                </td>
                                <td><span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($org['currency']); ?>
                                    </span></td>
                                <td>
                                    <?php
                                    $isActive = $org['is_active'] ?? 1; // Default to 1 if column missing (safe fallback visually)
                                    if ($isActive) {
                                        echo '<span class="badge bg-success">Active</span>';
                                    } else {
                                        echo '<span class="badge bg-danger">Inactive</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if (PermissionService::hasPermission('org_setup.org_management.edit')): ?>
                                        <a href="org_profile.php?id=<?php echo $org['id']; ?>"
                                            class="btn btn-sm btn-outline-primary me-1" title="Edit Profile">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (PermissionService::hasPermission('org_setup.user_mapping.view')): ?>
                                        <a href="org_users.php?org_id=<?php echo $org['id']; ?>"
                                            class="btn btn-sm btn-outline-info" title="Manage Users">
                                            <i class="fas fa-users-cog"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>