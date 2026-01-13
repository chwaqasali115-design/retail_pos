<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter
$date = isset($_GET['date']) ? $_GET['date'] : '';

$where = "WHERE s.store_id = ?";
$params = [$_SESSION['store_id'] ?? 1];

if (!empty($_SESSION['terminal_id'])) {
    $where .= " AND s.terminal_id = ?";
    $params[] = $_SESSION['terminal_id'];
}

if ($date) {
    $where .= " AND DATE(s.sale_date) = ?";
    $params[] = $date;
}

// Count
$stmt = $conn->prepare("SELECT COUNT(*) FROM sales s $where");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pages = ceil($total / $limit);

// Fetch
$query = "SELECT s.*, u.username FROM sales s LEFT JOIN users u ON s.user_id = u.id $where ORDER BY s.sale_date DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-history me-2"></i>POS Journal</h2>
        <a href="pos.php" class="btn btn-primary"><i class="fas fa-cash-register me-2"></i>Back to POS</a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control" value="<?php echo $date; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Cashier</th>
                        <th>Method</th>
                        <th class="text-end">Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No sales found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td class="fw-bold">
                                    <?php echo $s['invoice_no']; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y H:i', strtotime($s['sale_date'])); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($s['username']); ?>
                                </td>
                                <td><span class="badge bg-info text-dark">
                                        <?php echo $s['payment_method']; ?>
                                    </span></td>
                                <td class="text-end fw-bold">
                                    <?php echo number_format($s['grand_total'], 2); ?>
                                </td>
                                <td>
                                    <button
                                        onclick="window.open('print_receipt.php?id=<?php echo $s['id']; ?>', '_blank', 'width=350,height=600')"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-print me-1"></i> Reprint
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&date=<?php echo $date; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>