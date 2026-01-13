<?php
// vendor_payment.php - Record Vendor Payment
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

        $vendor_id = $_POST['vendor_id'];
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $method = $_POST['method'];
        $ref = trim($_POST['reference']);

        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }

        // 1. Record Payment
        $stmt = $conn->prepare("INSERT INTO vendor_payments (company_id, vendor_id, amount, payment_date, method, reference) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['company_id'], $vendor_id, $amount, $date, $method, $ref]);
        $paymentId = $conn->lastInsertId();

        // 2. Update Vendor Balance (Decrease Payable)
        $stmt = $conn->prepare("UPDATE vendors SET opening_balance = opening_balance - ? WHERE id = ?");
        $stmt->execute([$amount, $vendor_id]);

        // 3. Post to GL
        // Debit: Accounts Payable (2001)
        // Credit: Cash (1001)
        $apAccountId = $acc->getAccountId(2001);
        $cashAccountId = $acc->getAccountId(1001);

        $glEntries = [
            ['account_id' => $apAccountId, 'debit' => $amount, 'credit' => 0], // Dr AP
            ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => $amount]  // Cr Cash
        ];
        $acc->createJournalEntry($_SESSION['company_id'], $date, $ref, "Payment to Vendor ID: $vendor_id", $glEntries);

        $conn->commit();
        $message = "Payment recorded successfully! Vendor balance updated.";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Vendors with Balance
$stmt = $conn->prepare("SELECT id, name, opening_balance FROM vendors WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$_SESSION['company_id']]);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Record Vendor Payment</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="accounting.php">Accounting</a></li>
                    <li class="breadcrumb-item"><a href="vendors.php">Vendors</a></li>
                    <li class="breadcrumb-item active">Payment</li>
                </ol>
            </nav>
        </div>
        <a href="vendors.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Vendors
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
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Payment Details</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="save_payment" value="1">

                        <div class="mb-3">
                            <label class="form-label">Select Vendor *</label>
                            <select name="vendor_id" class="form-select" required onchange="updateBalance(this)">
                                <option value="">-- Choose Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                    <option value="<?php echo $v['id']; ?>"
                                        data-balance="<?php echo $v['opening_balance']; ?>">
                                        <?php echo htmlspecialchars($v['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-danger fw-bold" id="balanceDisplay">Current Balance: $0.00</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date *</label>
                                <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Used *</label>
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

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check-circle me-2"></i>Record Payment
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
        el.innerText = "Outstanding Payable: " + bal.toFixed(2);
        if (bal > 0) {
            el.className = 'form-text text-danger fw-bold';
        } else {
            el.className = 'form-text text-success fw-bold';
        }
    }
</script>

<?php require_once 'templates/footer.php'; ?>