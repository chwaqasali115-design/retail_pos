<?php
// fbr_transactions.php
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/FBRService.php';

Session::checkLogin();
$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

// Manual Sync Action
if (isset($_POST['sync_now'])) {
    $saleId = $_POST['sale_id'];
    try {
        $fbr = new FBRService($conn, $_SESSION['company_id']);
        $result = $fbr->syncSale($saleId);

        if ($result['status'] == 'SYNCED') {
            $msg = "Sale synced successfully. FBR Invoice: " . $result['fbr_invoice'];
            header("Location: fbr_transactions.php?success=" . urlencode($msg));
            exit;
        } else {
            $err = "Sync Failed. Check logs.";
            header("Location: fbr_transactions.php?error=" . urlencode($err));
            exit;
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
        header("Location: fbr_transactions.php?error=" . urlencode($err));
        exit;
    }
}

// Handle Flash Messages
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Fetch Filter
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, s.fbr_invoice_no, s.fbr_status,
        (SELECT request_payload FROM fbr_logs WHERE sale_id = s.id ORDER BY id DESC LIMIT 1) as request_payload, 
        (SELECT response_payload FROM fbr_logs WHERE sale_id = s.id ORDER BY id DESC LIMIT 1) as response_payload
        FROM sales s 
        JOIN stores st ON s.store_id = st.id
        WHERE st.company_id = ? ";
$params = [$_SESSION['company_id']];

if ($statusFilter) {
    if ($statusFilter == 'PENDING') {
        $sql .= " AND (s.fbr_status = 'PENDING' OR s.fbr_status IS NULL) ";
    } else {
        $sql .= " AND s.fbr_status = ? ";
        $params[] = $statusFilter;
    }
}
$sql .= " ORDER BY s.sale_date DESC LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2 class="fw-bold">FBR Transaction History</h2>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card mt-3 shadow-sm">
        <div class="card-header bg-white py-3">
            <form class="row g-3 align-items-center" method="GET">
                <div class="col-auto">
                    <label class="col-form-label fw-bold">Filter Status:</label>
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="SYNCED" <?php echo $statusFilter == 'SYNCED' ? 'selected' : ''; ?>>Synced</option>
                        <option value="PENDING" <?php echo $statusFilter == 'PENDING' ? 'selected' : ''; ?>>Pending /
                            Failed</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="fbr_sync_all.php" class="btn btn-warning ms-2" target="_blank">
                        <i class="fas fa-sync me-1"></i> Run Batch Sync
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>FBR Info</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td>
                                <?php echo $sale['invoice_no']; ?>
                            </td>
                            <td>
                                <?php echo date('Y-m-d H:i', strtotime($sale['sale_date'])); ?>
                            </td>
                            <td>
                                <?php echo number_format($sale['grand_total'], 2); ?>
                            </td>
                            <td>
                                <?php if ($sale['fbr_invoice_no']): ?>
                                    <small class="d-block text-success fw-bold">
                                        <?php echo $sale['fbr_invoice_no']; ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status = $sale['fbr_status'] ?? 'PENDING';
                                $badge = match ($status) {
                                    'SYNCED' => 'success',
                                    'FAILED' => 'danger',
                                    'PENDING' => 'warning',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($status != 'SYNCED'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                                        <input type="hidden" name="sync_now" value="1">
                                        <button class="btn btn-sm btn-outline-primary" title="Retry Sync"><i
                                                class="fas fa-redo"></i></button>
                                    </form>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-info view-log-btn text-white"
                                    data-invoice="<?php echo $sale['invoice_no']; ?>"
                                    data-request="<?php echo htmlspecialchars($sale['request_payload'] ?? ''); ?>"
                                    data-response="<?php echo htmlspecialchars($sale['response_payload'] ?? ''); ?>"
                                    data-bs-toggle="modal" data-bs-target="#logModal">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Log View Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">FBR Sync Log - <span id="modalInvoice"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fw-bold">Request Payload:</label>
                    <pre class="bg-light p-3 border rounded" id="modalRequest"
                        style="max-height: 200px; overflow: auto;"></pre>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Response Payload:</label>
                    <pre class="bg-light p-3 border rounded" id="modalResponse"
                        style="max-height: 200px; overflow: auto;"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var logButtons = document.querySelectorAll('.view-log-btn');
        logButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var invoice = this.getAttribute('data-invoice');
                var req = this.getAttribute('data-request');
                var res = this.getAttribute('data-response');

                document.getElementById('modalInvoice').textContent = invoice;

                try {
                    // Try to format JSON if valid
                    if (req) req = JSON.stringify(JSON.parse(req), null, 2);
                    if (res) res = JSON.stringify(JSON.parse(res), null, 2);
                } catch (e) {
                    console.log('Not valid JSON');
                }

                document.getElementById('modalRequest').textContent = req || 'No Request Logged';
                document.getElementById('modalResponse').textContent = res || 'No Response Logged';
            });
        });
    });
</script>

<?php require_once 'templates/footer.php'; ?>