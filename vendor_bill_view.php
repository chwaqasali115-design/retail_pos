<?php
// vendor_bill_view.php - View Vendor Bill Details
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id'])) {
    header('Location: purchases.php?error=No bill specified');
    exit;
}

$bill_id = $_GET['id'];

// Fetch Bill Details
$stmt = $conn->prepare("SELECT vi.*, v.name as vendor_name, v.address as vendor_address, v.phone as vendor_phone, v.email as vendor_email, po.po_number 
                        FROM vendor_invoices vi 
                        JOIN vendors v ON vi.vendor_id = v.id 
                        LEFT JOIN purchase_orders po ON vi.purchase_order_id = po.id 
                        WHERE vi.id = ? AND vi.company_id = ?");
$stmt->execute([$bill_id, $_SESSION['company_id']]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    die("Bill not found.");
}

// Fetch Items (from linked PO)
$items = [];
if ($bill['purchase_order_id']) {
    $stmt = $conn->prepare("SELECT pi.*, p.name as product_name, p.sku 
                            FROM purchase_items pi 
                            JOIN products p ON pi.product_id = p.id 
                            WHERE pi.purchase_id = ?");
    $stmt->execute([$bill['purchase_order_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Payments
$stmt = $conn->prepare("SELECT * FROM vendor_payments WHERE vendor_invoice_id = ? OR (vendor_id = ? AND reference LIKE ?)");
// Note: Simple matching logic for now, ideally payments should link directly to invoice if possible
// For this MVP, we just show payments made to this vendor *after* the bill date, or explicit links
$stmt = $conn->prepare("SELECT * FROM vendor_payments WHERE vendor_id = ? AND payment_date >= ? ORDER BY payment_date DESC");
$stmt->execute([$bill['vendor_id'], $bill['bill_date']]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC); // This is a loose match for context

require_once 'templates/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <div>
            <a href="purchases.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to
                Purchases</a>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print
                Bill</button>
        </div>
    </div>

    <div class="card shadow-lg border-0" id="printArea">
        <div class="card-body p-5">
            <!-- Header -->
            <div class="row mb-5">
                <div class="col-6">
                    <h3 class="fw-bold text-primary">VENDOR BIll</h3>
                    <h5 class="text-muted">
                        <?php echo htmlspecialchars($bill['bill_number']); ?>
                    </h5>
                </div>
                <div class="col-6 text-end">
                    <h4 class="fw-bold">
                        <?php echo htmlspecialchars($bill['vendor_name']); ?>
                    </h4>
                    <p class="mb-0">
                        <?php echo nl2br(htmlspecialchars($bill['vendor_address'])); ?>
                    </p>
                    <p class="mb-0">
                        <?php echo htmlspecialchars($bill['vendor_phone']); ?>
                    </p>
                    <p class="mb-0">
                        <?php echo htmlspecialchars($bill['vendor_email']); ?>
                    </p>
                </div>
            </div>

            <!-- Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted fw-bold" width="100">Bill Date:</td>
                            <td>
                                <?php echo date('F d, Y', strtotime($bill['bill_date'])); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Ref PO:</td>
                            <td>
                                <?php echo $bill['po_number']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Status:</td>
                            <td>
                                <span
                                    class="badge bg-<?php echo ($bill['status'] == 'Paid') ? 'success' : 'warning'; ?>">
                                    <?php echo $bill['status']; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Items -->
            <div class="table-responsive mb-4">
                <table class="table table-striped">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $accTotal = 0;
                        foreach ($items as $i => $item):
                            $accTotal += $item['total']; ?>
                            <tr>
                                <td>
                                    <?php echo $i + 1; ?>
                                </td>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </strong><br>
                                    <small class="text-muted">SKU:
                                        <?php echo $item['sku']; ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <?php echo floatval($item['quantity']); ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($item['unit_cost'], 2); ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($item['total'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Grand Total</td>
                            <td class="text-end fw-bold fs-5">
                                <?php echo number_format($accTotal, 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Footer -->
            <div class="border-top pt-4 mt-5">
                <p class="text-center text-muted small">Generated by Retail POS System</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>