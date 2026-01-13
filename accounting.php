<?php
// accounting.php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Fetch COA
$coa = $conn->prepare("SELECT * FROM chart_of_accounts WHERE company_id = :cid ORDER BY code ASC");
$coa->execute([':cid' => $_SESSION['company_id']]);
$accounts = $coa->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold">Accounting & Finance</h2>
    </div>
</div>

<div class="row g-4">
    <!-- COA -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between">
                <h5 class="mb-0">Chart of Accounts</h5>
                <button class="btn btn-sm btn-light">Manage</button>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y:auto;">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <tr class="<?php echo $a['is_group'] ? 'fw-bold table-active' : ''; ?>">
                                <td><?php echo $a['code']; ?></td>
                                <td><?php echo $a['name']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo $a['type']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reports & Tools -->
    <div class="col-md-6">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card p-3 text-center h-100">
                    <i class="fas fa-balance-scale fa-3x text-primary mb-3"></i>
                    <h5>Trial Balance</h5>
                    <p class="small text-muted">View balances of all accounts.</p>
                    <a href="report_trial_balance.php" class="btn btn-outline-primary">View Report</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-3 text-center h-100">
                    <i class="fas fa-book-open fa-3x text-info mb-3"></i>
                    <h5>General Ledger</h5>
                    <p class="small text-muted">View account transaction details.</p>
                    <a href="report_ledger.php" class="btn btn-outline-info">View Ledger</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-3 text-center h-100">
                    <i class="fas fa-file-invoice-dollar fa-3x text-success mb-3"></i>
                    <h5>Profit & Loss</h5>
                    <p class="small text-muted">Income vs Expenses Analysis.</p>
                    <a href="report_profit_loss.php" class="btn btn-outline-success">View Report</a>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="col-md-12">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Recent Journal Entries</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Ref</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $journal = $conn->prepare("
                                    SELECT j.*, SUM(i.debit) as total 
                                    FROM gl_journal j 
                                    JOIN gl_journal_items i ON j.id = i.journal_id 
                                    WHERE j.company_id = :cid 
                                    GROUP BY j.id 
                                    ORDER BY j.journal_date DESC, j.id DESC 
                                    LIMIT 10
                                ");
                                $journal->execute([':cid' => $_SESSION['company_id']]);
                                while ($row = $journal->fetch(PDO::FETCH_ASSOC)):
                                    ?>
                                    <tr>
                                        <td><?php echo $row['journal_date']; ?></td>
                                        <td><span
                                                class="badge bg-light text-dark border"><?php echo $row['reference']; ?></span>
                                        </td>
                                        <td><?php echo substr($row['description'], 0, 40); ?>...</td>
                                        <td class="text-end fw-bold"><?php echo number_format($row['total'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>