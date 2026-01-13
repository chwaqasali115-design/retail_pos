<?php
// report_trial_balance.php - Trial Balance Report
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');

// Fetch Account Balances
// Logic: Sum(Debit) - Sum(Credit) for Assets/Equity (depends on normal balance but TB usually shows both)
// We will show Total Debit and Total Credit for each account
$query = "
    SELECT 
        c.code, 
        c.name, 
        c.type,
        SUM(CASE WHEN i.debit > 0 THEN i.debit ELSE 0 END) as total_debit,
        SUM(CASE WHEN i.credit > 0 THEN i.credit ELSE 0 END) as total_credit
    FROM chart_of_accounts c
    LEFT JOIN gl_journal_items i ON c.id = i.account_id
    LEFT JOIN gl_journal j ON i.journal_id = j.id
    WHERE c.company_id = :cid
    AND (j.journal_date BETWEEN :start AND :end OR j.journal_date IS NULL)
    GROUP BY c.id
    ORDER BY c.code ASC
";

$stmt = $conn->prepare($query);
$stmt->execute([
    ':cid' => $_SESSION['company_id'],
    ':start' => $startDate,
    ':end' => $endDate
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDebit = 0;
$totalCredit = 0;

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold"><i class="fas fa-balance-scale me-2"></i>Trial Balance</h2>
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
                <h3 class="fw-bold">TRIAL BALANCE</h3>
                <p class="text-muted">Period:
                    <?php echo date('M d, Y', strtotime($startDate)); ?> to
                    <?php echo date('M d, Y', strtotime($endDate)); ?>
                </p>
            </div>

            <table class="table table-striped table-bordered">
                <thead class="table-dark text-center">
                    <tr>
                        <th class="text-start">Account Code</th>
                        <th class="text-start">Account Name</th>
                        <th class="text-start">Type</th>
                        <th width="150">Debit</th>
                        <th width="150">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            // Skip zero balance accounts? Optional. Let's show all for clarity or skip if both 0.
                            if ($row['total_debit'] == 0 && $row['total_credit'] == 0)
                                continue;

                            $totalDebit += $row['total_debit'];
                            $totalCredit += $row['total_credit'];
                            ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($row['code']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </td>
                                <td><span class="badge bg-light text-dark border">
                                        <?php echo $row['type']; ?>
                                    </span></td>
                                <td class="text-end">
                                    <?php echo number_format($row['total_debit'], 2); ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($row['total_credit'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="3" class="text-end">Totals</th>
                        <th class="text-end">
                            <?php echo number_format($totalDebit, 2); ?>
                        </th>
                        <th class="text-end">
                            <?php echo number_format($totalCredit, 2); ?>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <?php if (abs($totalDebit - $totalCredit) > 0.01): ?>
                <div class="alert alert-danger mt-3 text-center fw-bold">
                    <i class="fas fa-exclamation-triangle me-2"></i> Unbalanced! Difference:
                    <?php echo number_format($totalDebit - $totalCredit, 2); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success mt-3 text-center fw-bold">
                    <i class="fas fa-check-circle me-2"></i> Balanced
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>