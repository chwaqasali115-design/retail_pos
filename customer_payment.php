<?php
// customer_payment.php - Receive Customer Payment
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/AccountingHelper.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

// Get currency symbol from company settings
$stmt = $conn->prepare("SELECT currency FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$companyData = $stmt->fetch(PDO::FETCH_ASSOC);
$currency = isset($companyData['currency']) ? $companyData['currency'] : 'USD';

$symbols = array(
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'INR' => '₹',
    'AED' => 'AED ',
    'PKR' => 'Rs',
    'BDT' => 'Tk'
);
$symbol = isset($symbols[$currency]) ? $symbols[$currency] : '$';

// Handle Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    try {
        $conn->beginTransaction();
        $acc = new AccountingHelper($conn);

        $customer_id = $_POST['customer_id'];
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $method = $_POST['method'];
        $ref = trim($_POST['reference']);

        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }

        // 1. Record Payment
        $stmt = $conn->prepare("INSERT INTO customer_payments (company_id, customer_id, amount, payment_date, method, reference) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['company_id'], $customer_id, $amount, $date, $method, $ref]);
        $paymentId = $conn->lastInsertId();

        // 2. Update Customer Balance (Decrease Receivable)
        $stmt = $conn->prepare("UPDATE customers SET opening_balance = opening_balance - ? WHERE id = ?");
        $stmt->execute([$amount, $customer_id]);

        // 3. Post to GL
        // Debit: Cash (1001)
        // Credit: Accounts Receivable (1003)
        $cashAccountId = $acc->getAccountId(1001);
        $arAccountId = $acc->getAccountId(1003);

        $glEntries = [
            ['account_id' => $cashAccountId, 'debit' => $amount, 'credit' => 0], // Dr Cash
            ['account_id' => $arAccountId, 'debit' => 0, 'credit' => $amount]  // Cr AR
        ];
        $acc->createJournalEntry($_SESSION['company_id'], $date, $ref, "Payment from Customer ID: $customer_id", $glEntries);

        $conn->commit();
        $message = "Payment received successfully! Customer balance updated.";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Customers with Balance (Positive = They owe us)
// Optimization: Only show customers who owe money
$stmt = $conn->prepare("SELECT id, name, opening_balance FROM customers WHERE company_id = ? AND opening_balance > 0 ORDER BY name ASC");
$stmt->execute([$_SESSION['company_id']]);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If list is empty, maybe show all?
if (empty($customers)) {
    $stmt = $conn->prepare("SELECT id, name, opening_balance FROM customers WHERE company_id = ? ORDER BY name ASC");
    $stmt->execute([$_SESSION['company_id']]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold"><i class="fas fa-hand-holding-usd me-2"></i>Receive Customer Payment</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="accounting.php">Accounting</a></li>
                    <li class="breadcrumb-item"><a href="customers.php">Customers</a></li>
                    <li class="breadcrumb-item active">Receive Payment</li>
                </ol>
            </nav>
        </div>
        <a href="customers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Customers
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

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Payment Details</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="save_payment" value="1">

                        <div class="mb-3">
                            <label class="form-label">Select Customer *</label>
                            <select name="customer_id" class="form-select" required onchange="updateBalance(this)">
                                <option value="">-- Choose Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        data-balance="<?php echo $c['opening_balance']; ?>">
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted fw-bold" id="balanceDisplay">Current Balance (Receivable):
                                $0.00</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date *</label>
                                <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Received *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo $symbol; ?></span>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reference / Note</label>
                            <input type="text" name="reference" class="form-control"
                                placeholder="e.g. Check #1234 or Inv #99">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-check-circle me-2"></i>Record Receipt
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function updateBalance(select) {
        let opt = select.options[select.selectedIndex];
        let bal = parseFloat(opt.getAttribute('data-balance')) || 0;
        let el = document.getElementById('balanceDisplay');
        el.innerText = "Current Balance (Receivable): " + bal.toFixed(2);
        if (bal > 0) {
            el.className = 'form-text text-success fw-bold';
        } else {
            el.className = 'form-text text-muted';
        }
    }
</script>

<?php require_once 'templates/footer.php'; ?>