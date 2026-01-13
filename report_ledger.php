<?php
// report_ledger.php - Account Ledger / Transaction Detail View
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');
$accountId = $_GET['account_id'] ?? '';

// Fetch Accounts for dropdown
$accStmt = $conn->query("SELECT id, code, name, type FROM chart_of_accounts ORDER BY code ASC");
$accounts = $accStmt->fetchAll(PDO::FETCH_ASSOC);

$ledgerRows = [];
$openingBalance = 0;
$runningBalance = 0;

if ($accountId) {
    // 1. Calculate Opening Balance (Sum prior to start date)
    // Asset/Expense: Debit - Credit
    // Liability/Equity/Revenue: Credit - Debit
    // For simplicity, we calculate net debit (Debit - Credit) and interpret sign based on type

    $opQuery = "
        SELECT SUM(i.debit) as debits, SUM(i.credit) as credits 
        FROM gl_journal_items i 
        JOIN gl_journal j ON i.journal_id = j.id
        WHERE i.account_id = ? AND j.journal_date < ?
    ";
    $opStmt = $conn->prepare($opQuery);
    $opStmt->execute([$accountId, $startDate]);
    $opRow = $opStmt->fetch(PDO::FETCH_ASSOC);
    $openingBalance = ($opRow['debits'] ?? 0) - ($opRow['credits'] ?? 0);

    $runningBalance = $openingBalance;

    // 2. Fetch Transactions
    $query = "
        SELECT 
            j.journal_date, 
            j.reference, 
            j.description, 
            i.debit, 
            i.credit
        FROM gl_journal_items i 
        JOIN gl_journal j ON i.journal_id = j.id
        WHERE i.account_id = ? 
        AND j.journal_date BETWEEN ? AND ?
        ORDER BY j.journal_date ASC, j.id ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$accountId, $startDate, $endDate]);
    $ledgerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold"><i class="fas fa-book-open me-2"></i>Account Ledger</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print</button>
            <a href="accounting.php" class="btn btn-secondary ms-2">Back</a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Select Account</label>
                    <select name="account_id" class="form-select select2" required> // select2 if available
                        <option value="">-- Choose Account --</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>" <?php echo ($accountId == $acc['id']) ? 'selected' : ''; ?>>
                                <?php echo $acc['code'] . ' - ' . $acc['name']; ?> (
                                <?php echo $acc['type']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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

    <!-- Report -->
    <?php if ($accountId): ?>
        <div class="card shadow-lg border-0">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h4 class="fw-bold">GENERAL LEDGER</h4>
                    <?php
                    // Get account Name
                    $accName = "";
                    foreach ($accounts as $a) {
                        if ($a['id'] == $accountId)
                            $accName = $a['name'];
                    }
                    ?>
                    <h5 class="text-primary">
                        <?php echo $accName; ?>
                    </h5>
                    <p class="text-muted">
                        Period:
                        <?php echo date('M d, Y', strtotime($startDate)); ?> to
                        <?php echo date('M d, Y', strtotime($endDate)); ?>
                    </p>
                </div>

                <table class="table table-striped table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-warning fw-bold">
                            <td colspan="3">Opening Balance</td>
                            <td class="text-end">
                                <?php echo ($openingBalance > 0) ? number_format($openingBalance, 2) : '-'; ?>
                            </td>
                            <td class="text-end">
                                <?php echo ($openingBalance < 0) ? number_format(abs($openingBalance), 2) : '-'; ?>
                            </td>
                            <td class="text-end">
                                <?php echo number_format($openingBalance, 2); ?>
                            </td>
                        </tr>
                        <?php
                        $totalDebit = 0;
                        $totalCredit = 0;
                        ?>
                        <?php if (empty($ledgerRows)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No transactions found in this period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ledgerRows as $row):
                                $runningBalance += ($row['debit'] - $row['credit']);
                                $totalDebit += $row['debit'];
                                $totalCredit += $row['credit'];
                                ?>
                                <tr>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($row['journal_date'])); ?>
                                    </td>
                                    <td><span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($row['reference']); ?>
                                        </span></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['description']); ?>
                                    </td>
                                    <td class="text-end">
                                        <?php echo ($row['debit'] > 0) ? number_format($row['debit'], 2) : '-'; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php echo ($row['credit'] > 0) ? number_format($row['credit'], 2) : '-'; ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php echo number_format($runningBalance, 2); ?>
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
                            <th class="text-end">
                                <?php echo number_format($runningBalance, 2); ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'templates/footer.php'; ?>