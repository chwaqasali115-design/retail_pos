<?php
// report_daily_summary.php - Daily Sales Summary
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

// Query: Grouped by date
$query = "
    SELECT 
        DATE(sale_date) as day, 
        COUNT(id) as transaction_count, 
        SUM(grand_total) as total_amount,
        SUM(CASE WHEN status != 'Completed' THEN grand_total ELSE 0 END) as voided_amount
    FROM sales 
    WHERE DATE(sale_date) BETWEEN ? AND ?
    GROUP BY DATE(sale_date)
    ORDER BY day DESC
";

$stmt = $conn->prepare($query);
$stmt->execute([$startDate, $endDate]);
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-calendar-day me-2"></i>Daily Sales Summary</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary no-print"><i
                    class="fas fa-print me-2"></i>Print</button>
            <a href="reports.php" class="btn btn-secondary ms-2 no-print">Back</a>
        </div>
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
                    <button type="submit" class="btn btn-primary w-100">View</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th class="text-center">Transactions</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end text-warning">Voided/Returned</th>
                        <th class="text-end fw-bold">Net Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summary)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No data recorded for this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $totalTrans = 0;
                        $totalGross = 0;
                        $totalVoid = 0;
                        foreach ($summary as $row):
                            $net = $row['total_amount'] - $row['voided_amount'];
                            $totalTrans += $row['transaction_count'];
                            $totalGross += $row['total_amount'];
                            $totalVoid += $row['voided_amount'];
                            ?>
                            <tr>
                                <td>
                                    <?php echo date('D, M d, Y', strtotime($row['day'])); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo $row['transaction_count']; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                                <td class="text-end text-danger">
                                    <?php echo number_format($row['voided_amount'], 2); ?>
                                </td>
                                <td class="text-end fw-bold">PKR
                                    <?php echo number_format($net, 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($summary)): ?>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td>TOTALS</td>
                            <td class="text-center">
                                <?php echo $totalTrans; ?>
                            </td>
                            <td class="text-end">
                                <?php echo number_format($totalGross, 2); ?>
                            </td>
                            <td class="text-end text-danger">
                                <?php echo number_format($totalVoid, 2); ?>
                            </td>
                            <td class="text-end">PKR
                                <?php echo number_format($totalGross - $totalVoid, 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>