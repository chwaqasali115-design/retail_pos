<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Delete action if needed (soft delete or status toggle)
if (isset($_GET['action']) && $_GET['action'] == 'toggle' && isset($_GET['id'])) {
    $stmt = $conn->prepare("UPDATE stores SET is_active = NOT is_active WHERE id = ? AND company_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['company_id']]);
    header("Location: org_stores.php?success=Status updated");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM stores WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute(array($_SESSION['company_id']));
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="fw-bold">Stores / Branches</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Organization</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Stores</li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="org_store_form.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add New Store
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Store Name</th>
                            <th>Code</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stores)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-store fa-2x mb-3 opacity-25"></i>
                                    <p class="mb-0">No stores found. Click "Add New Store" to create one.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stores as $store): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        <?php echo htmlspecialchars($store['store_name']); ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">
                                            <?php echo htmlspecialchars($store['store_code']); ?>
                                        </span></td>
                                    <td>
                                        <?php echo htmlspecialchars($store['phone'] ?? ''); ?>
                                    </td>
                                    <td class="small text-muted" style="max-width: 200px;">
                                        <?php echo htmlspecialchars($store['address'] ?? ''); ?>
                                    </td>
                                    <td>
                                        <?php if ($store['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="org_store_form.php?id=<?php echo $store['id']; ?>"
                                            class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="org_stores.php?action=toggle&id=<?php echo $store['id']; ?>"
                                            class="btn btn-sm btn-outline-secondary"
                                            onclick="return confirm('Are you sure you want to toggle status?');">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>