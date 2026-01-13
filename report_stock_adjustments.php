<?php
// report_stock_adjustments.php - Stock Adjustment History
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';
require_once 'core/Database.php';

Session::checkLogin();
PermissionService::requirePermission('reports.stock');

$db = new Database();
$conn = $db->getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Query: inventory_transactions with type = 'Adjustment'
$query = "
    SELECT 
        it.created_at, 
        p.name as product_name, 
        p.sku,
        it.quantity, 
        it.reference_id,
        it.type
    FROM inventory_transactions it
    JOIN products p ON it.product_id = p.id
    WHERE it.type = 'Adjustment'
    AND DATE(it.created_at) BETWEEN ? AND ?
    ORDER BY it.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute([$startDate, $endDate]);
$adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-adjust me-2"></i>Stock Adjustment Report</h2>
        <a href="reports.php" class="btn btn-secondary no-print">Back</a>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Date & Time</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-center">Adjustment Qty</th>
                        <th>Reason / Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($adjustments)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No adjustments found for this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($adjustments as $row): ?>
                            <tr>
                                <td>
                                    <?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                </td>
                                <td><span class="text-muted small">
                                        <?php echo htmlspecialchars($row['sku']); ?>
                                    </span></td>
                                <td
                                    class="text-center fw-bold <?php echo ($row['quantity'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($row['quantity'] > 0 ? '+' : '') . number_format($row['quantity']); ?>
                                </td>
                                <td>
                                    <?php
                                    // Try to fetch reason or notes if they exist in a related table later
                                    echo $row['type'] . ' #' . $row['reference_id'];
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>