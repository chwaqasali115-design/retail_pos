<?php
// purchase_view.php - Purchase Order Report & Print
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

// Set default values for company fields
$companyName = isset($company['name']) && $company['name'] ? $company['name'] : (isset($company['company_name']) ? $company['company_name'] : 'Your Company');
$companyAddress = isset($company['address']) && $company['address'] ? $company['address'] : '';
$companyPhone = isset($company['phone']) && $company['phone'] ? $company['phone'] : '';
$companyEmail = isset($company['email']) && $company['email'] ? $company['email'] : '';

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

// Set default values for purchase fields
$vendorName = isset($purchase['vendor_name']) ? $purchase['vendor_name'] : 'N/A';
$vendorPhone = isset($purchase['vendor_phone']) ? $purchase['vendor_phone'] : '';
$vendorEmail = isset($purchase['vendor_email']) ? $purchase['vendor_email'] : '';
$vendorAddress = isset($purchase['vendor_address']) ? $purchase['vendor_address'] : '';
$vendorContact = isset($purchase['vendor_contact']) ? $purchase['vendor_contact'] : '';
$purchaseNotes = isset($purchase['notes']) ? $purchase['notes'] : '';
$purchaseStatus = isset($purchase['status']) ? $purchase['status'] : 'Pending';

// Fetch Purchase Items
$stmt = $conn->prepare("SELECT pi.*, p.name as product_name, p.sku FROM purchase_items pi LEFT JOIN products p ON pi.product_id = p.id WHERE pi.purchase_id = ?");
$stmt->execute(array($purchase_id));
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$poNumber = isset($purchase['po_number']) && $purchase['po_number'] ? $purchase['po_number'] : 'PO-' . str_pad($purchase['id'], 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - <?php echo $poNumber; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media print {
            @page {
                size: A4;
                margin: 0.5cm;
            }

            body {
                padding: 0;
                margin: 0;
                background: white !important;
                font-size: 12px;
            }

            .no-print {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
            }

            .card-body {
                padding: 10px !important;
            }

            .page-break {
                page-break-after: always;
            }

            /* Ensure colors print */
            .po-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .bg-light {
                background-color: white !important;
            }

            .table-po thead {
                background: #667eea !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
            }

            .badge {
                border: 1px solid #ddd;
                color: black !important;
                background: none !important;
            }

            a {
                text-decoration: none;
                color: inherit;
            }
        }

        .print-only {
            display: none;
        }

        .po-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 20px;
        }

        /* Print layout adjustments for header */
        @media print {
            .po-header {
                border-radius: 0;
                padding: 20px;
                margin-bottom: 15px;
            }
        }

        .company-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #667eea;
        }

        .po-badge {
            font-size: 0.9rem;
            padding: 8px 20px;
            border-radius: 20px;
        }

        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            height: 100%;
        }

        @media print {
            .info-box {
                background: transparent !important;
                border: 1px solid #eee;
                padding: 15px;
            }
        }

        .table-po thead {
            background: #667eea;
            color: white;
        }

        .table-po thead th {
            border: none;
            padding: 12px 15px;
            font-weight: 600;
        }

        .table-po tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        .total-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        @media print {
            .total-section {
                background: transparent !important;
                border-top: 1px solid #333;
                border-radius: 0;
                padding: 10px 0;
            }

            .total-section table td {
                padding: 5px 0;
            }
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 80%;
            margin: 50px auto 10px auto;
            padding-top: 5px;
        }

        /* Ensure table fits on print */
        @media print {
            .table-responsive {
                overflow: visible !important;
            }

            table {
                width: 100% !important;
            }
        }
    </style>
</head>

<body class="bg-light">

    <!-- Action Buttons -->
    <div class="container mt-4 no-print">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="purchases.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Purchases
            </a>
            <div>
                <button class="btn btn-primary me-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print PO
                </button>
                <a href="purchase_invoice.php?id=<?php echo $purchase_id; ?>" class="btn btn-success">
                    <i class="fas fa-file-invoice me-2"></i>View Invoice
                </a>
            </div>
        </div>
    </div>

    <!-- Purchase Order Document -->
    <div class="container mb-5">
        <div class="card shadow-lg border-0">

            <!-- Header -->
            <div class="po-header">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <div class="company-logo">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-1 fw-bold"><?php echo htmlspecialchars($companyName); ?></h2>
                        <p class="mb-0 opacity-75">
                            <?php if ($companyAddress): ?>
                                <?php echo htmlspecialchars($companyAddress); ?><br>
                            <?php endif; ?>
                            <?php if ($companyPhone): ?>
                                Phone: <?php echo htmlspecialchars($companyPhone); ?>
                            <?php endif; ?>
                            <?php if ($companyEmail): ?>
                                | Email: <?php echo htmlspecialchars($companyEmail); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h3 class="fw-bold mb-2">PURCHASE ORDER</h3>
                        <span class="po-badge bg-white text-dark fw-bold"><?php echo $poNumber; ?></span>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                <!-- Order Info & Vendor Info -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="info-box">
                            <h6 class="text-muted mb-3"><i class="fas fa-store me-2"></i>VENDOR INFORMATION</h6>
                            <h5 class="fw-bold"><?php echo htmlspecialchars($vendorName); ?></h5>
                            <?php if ($vendorContact): ?>
                                <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($vendorContact); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($vendorPhone): ?>
                                <p class="mb-1"><i
                                        class="fas fa-phone me-2 text-muted"></i><?php echo htmlspecialchars($vendorPhone); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($vendorEmail): ?>
                                <p class="mb-1"><i
                                        class="fas fa-envelope me-2 text-muted"></i><?php echo htmlspecialchars($vendorEmail); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($vendorAddress): ?>
                                <p class="mb-0"><i
                                        class="fas fa-map-marker-alt me-2 text-muted"></i><?php echo htmlspecialchars($vendorAddress); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-box">
                            <h6 class="text-muted mb-3"><i class="fas fa-file-alt me-2"></i>ORDER DETAILS</h6>
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td class="text-muted">PO Number:</td>
                                    <td class="fw-bold"><?php echo $poNumber; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Order Date:</td>
                                    <td class="fw-bold">
                                        <?php echo date('F d, Y', strtotime($purchase['order_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status:</td>
                                    <td>
                                        <?php
                                        $statusClass = 'bg-secondary';
                                        if ($purchaseStatus == 'Pending')
                                            $statusClass = 'bg-warning';
                                        if ($purchaseStatus == 'Ordered')
                                            $statusClass = 'bg-info';
                                        if ($purchaseStatus == 'Received')
                                            $statusClass = 'bg-success';
                                        ?>
                                        <span
                                            class="badge <?php echo $statusClass; ?>"><?php echo $purchaseStatus; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payment Terms:</td>
                                    <td class="fw-bold">Net 30 Days</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <h6 class="text-muted mb-3"><i class="fas fa-list me-2"></i>ORDER ITEMS</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-po table-bordered">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Product Description</th>
                                <th width="100">SKU</th>
                                <th width="100" class="text-center">Quantity</th>
                                <th width="120" class="text-end">Unit Price</th>
                                <th width="130" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 1;
                            $subtotal = 0;
                            if (!empty($items)):
                                foreach ($items as $item):
                                    $itemTotal = $item['quantity'] * $item['unit_cost'];
                                    $subtotal += $itemTotal;
                                    $productName = isset($item['product_name']) ? $item['product_name'] : 'Unknown Product';
                                    $productSku = isset($item['sku']) ? $item['sku'] : 'N/A';
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $count; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($productName); ?></strong>
                                        </td>
                                        <td><span
                                                class="badge bg-light text-dark"><?php echo htmlspecialchars($productSku); ?></span>
                                        </td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end"><?php echo $symbol . number_format($item['unit_cost'], 2); ?></td>
                                        <td class="text-end fw-bold"><?php echo $symbol . number_format($itemTotal, 2); ?></td>
                                    </tr>
                                    <?php $count++; endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No items found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals & Notes -->
                <div class="row">
                    <div class="col-md-6">
                        <?php if ($purchaseNotes): ?>
                            <div class="info-box">
                                <h6 class="text-muted mb-2"><i class="fas fa-sticky-note me-2"></i>NOTES</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($purchaseNotes)); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="total-section">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Subtotal:</td>
                                    <td class="text-end fw-bold"><?php echo $symbol . number_format($subtotal, 2); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tax (0%):</td>
                                    <td class="text-end"><?php echo $symbol . '0.00'; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Shipping:</td>
                                    <td class="text-end"><?php echo $symbol . '0.00'; ?></td>
                                </tr>
                                <tr class="border-top">
                                    <td class="fs-5 fw-bold">Grand Total:</td>
                                    <td class="text-end fs-4 fw-bold text-primary">
                                        <?php echo $symbol . number_format($purchase['total_amount'], 2); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="row mt-5">
                    <div class="col-md-4 text-center">
                        <div class="signature-line mx-auto">
                            <small class="text-muted">Prepared By</small>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="signature-line mx-auto">
                            <small class="text-muted">Approved By</small>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="signature-line mx-auto">
                            <small class="text-muted">Received By</small>
                        </div>
                    </div>
                </div>

                <!-- Terms -->
                <div class="mt-5 pt-4 border-top">
                    <h6 class="text-muted mb-2">TERMS & CONDITIONS</h6>
                    <ol class="small text-muted mb-0">
                        <li>Please supply the above items as per specifications mentioned.</li>
                        <li>Delivery must be made within the agreed timeframe.</li>
                        <li>All goods must be properly packed and labeled.</li>
                        <li>Invoice must reference this PO number for payment processing.</li>
                        <li>Goods received are subject to inspection and approval.</li>
                    </ol>
                </div>
            </div>

            <!-- Footer -->
            <div class="card-footer bg-light text-center py-3">
                <small class="text-muted">
                    This is a computer-generated document. Generated on <?php echo date('F d, Y h:i A'); ?>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>