<?php
// report_purchases.php - Purchase Report
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';
require_once 'core/Database.php';

Session::checkLogin();
PermissionService::requirePermission('reports.view');

$db = new Database();
$conn = $db->getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Query: purchase_orders
$query = "
    SELECT 
        po.id, 
        po.order_date, 
        v.name as vendor_name, 
        po.total_amount, 
        po.status,
        u.full_name as created_by_name
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    JOIN users u ON po.created_by = u.id
    WHERE po.order_date BETWEEN ? AND ?
    ORDER BY po.order_date DESC
";

$stmt = $conn->prepare($query);
$stmt->execute([$startDate, $endDate]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-truck me-2"></i>Purchase Report</h2>
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
                        <th>Date</th>
                        <th>PO #</th>
                        <th>Vendor</th>
                        <th>Ordered By</th>
                        <th>Status</th>
                        <th class="text-end">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">No purchases found.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $grandTotal = 0;
                        foreach ($purchases as $row):
                            $grandTotal += $row['total_amount'];
                            ?>
                            <tr>
                                <td>
                                    <?php echo date('M d, Y', strtotime($row['order_date'])); ?>
                                </td>
                                <td><span class="badge bg-secondary">
                                        <?php echo 'PO-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </span></td>
                                <td class="fw-bold">
                                    <?php echo htmlspecialchars($row['vendor_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['created_by_name']); ?>
                                </td>
                                <td>
                                    <?php
                                    $badge = 'bg-info';
                                    if ($row['status'] == 'Received')
                                        $badge = 'bg-success';
                                    if ($row['status'] == 'Cancelled')
                                        $badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold">PKR
                                    <?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($purchases)): ?>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <th colspan="5">GRAND TOTAL</th>
                            <th class="text-end">PKR
                                <?php echo number_format($grandTotal, 2); ?>
                            </th>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>