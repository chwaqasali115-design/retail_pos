<?php
// customers.php - All Customers List
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

// Handle Delete Customer
if (isset($_GET['delete'])) {
    try {
        // Check if used in sales
        $check = $conn->prepare("SELECT COUNT(*) FROM sales_orders WHERE customer_id = ?");
        $check->execute([$_GET['delete']]);
        if ($check->fetchColumn() > 0) {
            $error = "Cannot delete customer. They have existing sales orders.";
        } else {
            $stmt = $conn->prepare("DELETE FROM customers WHERE id = ? AND company_id = ?");
            $stmt->execute(array($_GET['delete'], $_SESSION['company_id']));
            $message = "Customer deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Search
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM customers WHERE company_id = ?";
$params = [$_SESSION['company_id']];

if ($search) {
    $query .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold"><i class="fas fa-users me-2"></i>All Customers</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="accounting.php">Accounting</a></li>
                    <li class="breadcrumb-item active">Customers</li>
                </ol>
            </nav>
        </div>
        <a href="customer_create.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Add Customer
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by name, phone, email..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Customer Name</th>
                            <th>Phone / Email</th>
                            <th>Address</th>
                            <th>Tax Number</th>
                            <th>Balance</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-users fa-3x mb-3 d-block opacity-25"></i>
                                    No customers found. <a href="customer_create.php">Create one now</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $count = 1;
                            foreach ($customers as $c): ?>
                                <tr>
                                    <td class="ps-3">
                                        <?php echo $count++; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </div>
                                        <small class="text-muted d-block">ID:
                                            <?php echo $c['id']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($c['phone']): ?>
                                            <div><i class="fas fa-phone fa-fw text-muted me-1"></i>
                                                <?php echo htmlspecialchars($c['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($c['email']): ?>
                                            <div><i class="fas fa-envelope fa-fw text-muted me-1"></i>
                                                <?php echo htmlspecialchars($c['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><small>
                                            <?php echo htmlspecialchars(substr($c['address'] ?? '', 0, 50)) . (strlen($c['address'] ?? '') > 50 ? '...' : ''); ?>
                                        </small></td>
                                    <td>
                                        <?php echo htmlspecialchars($c['tax_number'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo number_format($c['opening_balance'] ?? 0, 2); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="customer_payment.php?customer_id=<?php echo $c['id']; ?>"
                                            class="btn btn-sm btn-outline-success me-1">
                                            <i class="fas fa-hand-holding-usd"></i> Receive
                                        </a>
                                        <a href="customer_edit.php?id=<?php echo $c['id']; ?>"
                                            class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Delete this customer? This cannot be undone.')">
                                            <i class="fas fa-trash"></i>
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