<?php
// report_profit_loss.php - Profit & Loss (Income Statement)
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');

// Fetch Income and Expense Accounts with Balances
// Revenue: Credit is positive
// Expense: Debit is positive
$query = "
    SELECT 
        c.code, 
        c.name, 
        c.type,
        SUM(CASE 
            WHEN c.type = 'Revenue' THEN (i.credit - i.debit) 
            WHEN c.type = 'Expense' THEN (i.debit - i.credit)
            ELSE 0 
        END) as net_balance
    FROM chart_of_accounts c
    LEFT JOIN gl_journal_items i ON c.id = i.account_id
    LEFT JOIN gl_journal j ON i.journal_id = j.id
    WHERE c.company_id = :cid
    AND c.type IN ('Revenue', 'Expense')
    AND (j.journal_date BETWEEN :start AND :end OR j.journal_date IS NULL)
    GROUP BY c.id
    HAVING net_balance != 0 OR net_balance IS NULL
    ORDER BY c.type DESC, c.code ASC
";

$stmt = $conn->prepare($query);
$stmt->execute([
    ':cid' => $_SESSION['company_id'],
    ':start' => $startDate,
    ':end' => $endDate
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRevenue = 0;
$totalExpense = 0;
$revenues = [];
$expenses = [];

foreach ($rows as $row) {
    if ($row['type'] == 'Revenue') {
        $revenues[] = $row;
        $totalRevenue += $row['net_balance'];
    } else {
        $expenses[] = $row;
        $totalExpense += $row['net_balance'];
    }
}

$netProfit = $totalRevenue - $totalExpense;

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold"><i class="fas fa-chart-line me-2"></i>Profit & Loss Statement</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print</button>
            <a href="accounting.php" class="btn btn-secondary ms-2">Back</a>
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
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-5">
            <div class="text-center mb-5">
                <h3 class="fw-bold text-uppercase">Income Statement</h3>
                <p class="text-muted">Period: <?php echo date('M d, Y', strtotime($startDate)); ?> to
                    <?php echo date('M d, Y', strtotime($endDate)); ?></p>
            </div>

            <!-- Revenue Section -->
            <h5 class="fw-bold text-success border-bottom pb-2 mb-3">REVENUE (Income)</h5>
            <table class="table table-borderless table-sm mb-4">
                <tbody>
                    <?php if (empty($revenues)): ?>
                        <tr>
                            <td class="text-muted">No revenue recorded.</td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($revenues as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['name']); ?> <small
                                        class="text-muted">(<?php echo $r['code']; ?>)</small></td>
                                <td class="text-end" width="200"><?php echo number_format($r['net_balance'] ?? 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="border-top">
                    <tr>
                        <th class="pt-2">Total Revenue</th>
                        <th class="text-end pt-2"><?php echo number_format($totalRevenue, 2); ?></th>
                    </tr>
                </tfoot>
            </table>

            <!-- Expense Section -->
            <h5 class="fw-bold text-danger border-bottom pb-2 mb-3 mt-5">EXPENSES</h5>
            <table class="table table-borderless table-sm mb-4">
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td class="text-muted">No expenses recorded.</td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($e['name']); ?> <small
                                        class="text-muted">(<?php echo $e['code']; ?>)</small></td>
                                <td class="text-end" width="200"><?php echo number_format($e['net_balance'] ?? 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="border-top">
                    <tr>
                        <th class="pt-2">Total Expenses</th>
                        <th class="text-end pt-2"><?php echo number_format($totalExpense, 2); ?></th>
                    </tr>
                </tfoot>
            </table>

            <!-- Net Profit -->
            <div class="row mt-5 pt-3 border-top border-3">
                <div class="col-6">
                    <h3 class="fw-bold">NET PROFIT / (LOSS)</h3>
                </div>
                <div class="col-6 text-end">
                    <h3 class="fw-bold <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format($netProfit, 2); ?>
                    </h3>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>