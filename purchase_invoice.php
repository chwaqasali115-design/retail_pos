<?php
// purchase_invoice.php - Purchase Invoice
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id'])) {
    header('Location: purchases.php');
    exit;
}

$purchase_id = $_GET['id'];

// Get Company Info
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$company = $stmt->fetch(PDO::FETCH_ASSOC);

$currency = isset($company['currency']) ? $company['currency'] : 'USD';
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

// Fetch Purchase
$stmt = $conn->prepare("SELECT po.*, v.name as vendor_name, v.phone as vendor_phone, v.email as vendor_email, v.address as vendor_address, v.contact_person as vendor_contact FROM purchase_orders po LEFT JOIN vendors v ON po.vendor_id = v.id WHERE po.id = ? AND po.company_id = ?");
$stmt->execute(array($purchase_id, $_SESSION['company_id']));
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    header('Location: purchases.php');
    exit;
}

// Fetch Purchase Items
$stmt = $conn->prepare("SELECT pi.*, p.name as product_name, p.sku FROM purchase_items pi LEFT JOIN products p ON pi.product_id = p.id WHERE pi.purchase_id = ?");
$stmt->execute(array($purchase_id));
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$poNumber = isset($purchase['po_number']) && $purchase['po_number'] ? $purchase['po_number'] : 'PO-' . str_pad($purchase['id'], 6, '0', STR_PAD_LEFT);
$invoiceNumber = 'INV-' . str_pad($purchase['id'], 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice - <?php echo $invoiceNumber; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                padding: 0;
                margin: 0;
            }

            .invoice-container {
                box-shadow: none !important;
            }
        }

        body {
            background-color: #f0f0f0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .invoice-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        }

        .invoice-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .invoice-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
        }

        .invoice-title {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 3px;
        }

        .invoice-number {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 25px;
            border-radius: 30px;
            display: inline-block;
        }

        .company-details h4 {
            font-weight: 700;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #1e3c72;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1e3c72;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            height: 100%;
        }

        .table-invoice {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-invoice thead th {
            background: #1e3c72;
            color: white;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .table-invoice thead th:first-child {
            border-radius: 10px 0 0 0;
        }

        .table-invoice thead th:last-child {
            border-radius: 0 10px 0 0;
        }

        .table-invoice tbody td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .table-invoice tbody tr:hover {
            background: #f8f9fa;
        }

        .totals-table {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
        }

        .totals-table .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ccc;
        }

        .totals-table .total-row:last-child {
            border-bottom: none;
            padding-top: 15px;
            margin-top: 10px;
            border-top: 2px solid #1e3c72;
        }

        .totals-table .grand-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3c72;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-received {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-ordered {
            background: #cce5ff;
            color: #004085;
        }

        .payment-info {
            background: #e8f4fd;
            border-left: 4px solid #1e3c72;
            padding: 20px;
            border-radius: 0 10px 10px 0;
        }

        .footer-note {
            background: #f8f9fa;
            padding: 20px 40px;
            font-size: 0.85rem;
            color: #666;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 8rem;
            color: rgba(0, 0, 0, 0.03);
            font-weight: 900;
            pointer-events: none;
            z-index: 0;
        }

        @media print {
            .invoice-header {
                background: #1e3c72 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .table-invoice thead th {
                background: #1e3c72 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <!-- Action Buttons -->
    <div class="container no-print" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center py-3">
            <a href="purchases.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <div>
                <a href="purchase_view.php?id=<?php echo $purchase_id; ?>" class="btn btn-outline-primary me-2">
                    <i class="fas fa-file-alt me-2"></i>View PO
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Invoice
                </button>
            </div>
        </div>
    </div>

    <!-- Invoice Document -->
    <div class="invoice-container">

        <!-- Watermark -->
        <div style="position: relative;">
            <?php if ($purchase['status'] == 'Received'): ?>
                <div class="watermark">PAID</div>
            <?php endif; ?>
        </div>

        <!-- Header -->
        <div class="invoice-header">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="company-details">
                        <h4><?php echo htmlspecialchars($company['company_name']); ?></h4>
                        <p class="mb-1 opacity-75">
                            <?php if (isset($company['address']) && $company['address']): ?>
                                <?php echo htmlspecialchars($company['address']); ?><br>
                            <?php endif; ?>
                            <?php if (isset($company['phone']) && $company['phone']): ?>
                                Tel: <?php echo htmlspecialchars($company['phone']); ?><br>
                            <?php endif; ?>
                            <?php if (isset($company['email']) && $company['email']): ?>
                                Email: <?php echo htmlspecialchars($company['email']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-5 text-md-end">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number mt-2"><?php echo $invoiceNumber; ?></div>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="p-4">

            <!-- Info Cards -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="info-card">
                        <div class="section-title">Bill From (Vendor)</div>
                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($purchase['vendor_name']); ?></h5>
                        <?php if ($purchase['vendor_contact']): ?>
                            <p class="mb-1"><small class="text-muted">Contact:</small>
                                <?php echo htmlspecialchars($purchase['vendor_contact']); ?></p>
                        <?php endif; ?>
                        <?php if ($purchase['vendor_phone']): ?>
                            <p class="mb-1"><i
                                    class="fas fa-phone fa-sm me-2 text-muted"></i><?php echo htmlspecialchars($purchase['vendor_phone']); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($purchase['vendor_email']): ?>
                            <p class="mb-1"><i
                                    class="fas fa-envelope fa-sm me-2 text-muted"></i><?php echo htmlspecialchars($purchase['vendor_email']); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($purchase['vendor_address']): ?>
                            <p class="mb-0"><i
                                    class="fas fa-map-marker-alt fa-sm me-2 text-muted"></i><?php echo htmlspecialchars($purchase['vendor_address']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="info-card">
                        <div class="section-title">Invoice Details</div>
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td class="text-muted" width="40%">Invoice No:</td>
                                <td class="fw-bold"><?php echo $invoiceNumber; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">PO Reference:</td>
                                <td class="fw-bold"><?php echo $poNumber; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Invoice Date:</td>
                                <td><?php echo date('F d, Y', strtotime($purchase['order_date'])); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Due Date:</td>
                                <td><?php echo date('F d, Y', strtotime($purchase['order_date'] . ' +30 days')); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td>
                                    <?php
                                    $statusClass = 'status-pending';
                                    if ($purchase['status'] == 'Received')
                                        $statusClass = 'status-received';
                                    if ($purchase['status'] == 'Ordered')
                                        $statusClass = 'status-ordered';
                                    ?>
                                    <span
                                        class="status-badge <?php echo $statusClass; ?>"><?php echo $purchase['status']; ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="section-title">Items Purchased</div>
            <div class="table-responsive mb-4">
                <table class="table table-invoice">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Description</th>
                            <th width="90">SKU</th>
                            <th width="80" class="text-center">Qty</th>
                            <th width="110" class="text-end">Unit Price</th>
                            <th width="120" class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 1;
                        $subtotal = 0;
                        foreach ($items as $item):
                            $itemTotal = $item['quantity'] * $item['unit_cost'];
                            $subtotal += $itemTotal;
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $count; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                </td>
                                <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end"><?php echo $symbol . number_format($item['unit_cost'], 2); ?></td>
                                <td class="text-end fw-bold"><?php echo $symbol . number_format($itemTotal, 2); ?></td>
                            </tr>
                            <?php $count++; endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals & Payment -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="payment-info">
                        <div class="section-title" style="border-color: #1e3c72;">Payment Information</div>
                        <p class="mb-2"><strong>Payment Terms:</strong> Net 30 Days</p>
                        <p class="mb-2"><strong>Payment Method:</strong> Bank Transfer / Check</p>
                        <p class="mb-0"><strong>Reference:</strong> <?php echo $poNumber; ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="totals-table">
                        <div class="total-row">
                            <span class="text-muted">Subtotal</span>
                            <span><?php echo $symbol . number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span class="text-muted">Tax (0%)</span>
                            <span><?php echo $symbol . '0.00'; ?></span>
                        </div>
                        <div class="total-row">
                            <span class="text-muted">Shipping</span>
                            <span><?php echo $symbol . '0.00'; ?></span>
                        </div>
                        <div class="total-row">
                            <span class="grand-total">Total Due</span>
                            <span
                                class="grand-total"><?php echo $symbol . number_format($purchase['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <?php if (isset($purchase['notes']) && $purchase['notes']): ?>
                <div class="mt-4">
                    <div class="section-title">Notes</div>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($purchase['notes'])); ?></p>
                </div>
            <?php endif; ?>

        </div>

        <!-- Footer -->
        <div class="footer-note text-center">
            <p class="mb-2"><strong>Thank you for your business!</strong></p>
            <p class="mb-0">This invoice was generated on <?php echo date('F d, Y \a\t h:i A'); ?>. For any queries,
                please contact us.</p>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>