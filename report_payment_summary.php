<?php
// report_payment_summary.php - Payment Method Wise Sales
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';
require_once 'core/Database.php';

Session::checkLogin();
PermissionService::requirePermission('reports.sales');

$db = new Database();
$conn = $db->getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Query: Sum by payment method
$query = "
    SELECT 
        payment_method, 
        COUNT(id) as transaction_count, 
        SUM(grand_total) as total_amount
    FROM sales 
    WHERE status = 'Completed' 
    AND DATE(sale_date) BETWEEN ? AND ?
    GROUP BY payment_method
";

$stmt = $conn->prepare($query);
$stmt->execute([$startDate, $endDate]);
$methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-credit-card me-2"></i>Payment Method Report</h2>
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

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">Revenue Breakdown</h5>
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-center">Count</th>
                                <th class="text-end">Total Collected</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalRev = 0;
                            foreach ($methods as $m):
                                $totalRev += $m['total_amount'];
                                ?>
                                <tr>
                                    <td class="fw-bold">
                                        <?php echo htmlspecialchars($m['payment_method']); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $m['transaction_count']; ?>
                                    </td>
                                    <td class="text-end fw-bold">PKR
                                        <?php echo number_format($m['total_amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="2">GRAND TOTAL</th>
                                <th class="text-end">PKR
                                    <?php echo number_format($totalRev, 2); ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add a visual chart placeholder or simple text bars -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">Method Popularity</h5>
                    <?php if (empty($methods)): ?>
                        <p class="text-muted text-center pt-5">No data for chart</p>
                    <?php else: ?>
                        <?php foreach ($methods as $m):
                            $percent = ($totalRev > 0) ? ($m['total_amount'] / $totalRev) * 100 : 0;
                            ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>
                                        <?php echo $m['payment_method']; ?>
                                    </span>
                                    <span class="fw-bold">
                                        <?php echo round($percent, 1); ?>%
                                    </span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-primary" role="progressbar"
                                        style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>