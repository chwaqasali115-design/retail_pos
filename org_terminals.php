<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Delete action if needed
if (isset($_GET['action']) && $_GET['action'] == 'toggle' && isset($_GET['id'])) {
    $stmt = $conn->prepare("UPDATE terminals SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header("Location: org_terminals.php?success=Status updated");
    exit;
}

// Fetch terminals with store info
$query = "SELECT t.*, s.store_name 
          FROM terminals t 
          JOIN stores s ON t.store_id = s.id 
          WHERE s.company_id = ? 
          ORDER BY s.store_name ASC, t.name ASC";
$stmt = $conn->prepare($query);
$stmt->execute(array($_SESSION['company_id']));
$terminals = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="fw-bold">Terminals / Registers</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Organization</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Terminals</li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="org_terminal_form.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add New Terminal
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
                            <th class="ps-3">Terminal Name</th>
                            <th>Assigned Store</th>
                            <th>Device ID / MAC</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($terminals)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-desktop fa-2x mb-3 opacity-25"></i>
                                    <p class="mb-0">No terminals found. Click "Add New Terminal" to setup one.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($terminals as $t): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        <?php echo htmlspecialchars($t['name']); ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-store text-muted me-1"></i>
                                        <?php echo htmlspecialchars($t['store_name']); ?>
                                    </td>
                                    <td class="text-secondary small">
                                        <?php echo htmlspecialchars($t['device_id'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php if ($t['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($t['created_at'])); ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="org_terminal_form.php?id=<?php echo $t['id']; ?>"
                                            class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="org_terminals.php?action=toggle&id=<?php echo $t['id']; ?>"
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