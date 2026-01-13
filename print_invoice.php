<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id'])) {
    header('Location: invoices.php?error=No invoice specified');
    exit;
}

$invoice_id = $_GET['id'];

$stmt = $conn->prepare("SELECT currency, company_name FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$companyData = $stmt->fetch(PDO::FETCH_ASSOC);

$currency = 'USD';
$companyName = 'Your Company';

if ($companyData) {
    if (isset($companyData['currency']) && $companyData['currency'] != '') {
        $currency = $companyData['currency'];
    }
    if (isset($companyData['company_name']) && $companyData['company_name'] != '') {
        $companyName = $companyData['company_name'];
    }
}

$symbols = array();
$symbols['USD'] = '$';
$symbols['EUR'] = '€';
$symbols['GBP'] = '£';
$symbols['INR'] = '₹';
$symbols['AED'] = 'AED ';
$symbols['SAR'] = 'SAR ';
$symbols['CAD'] = 'C$';
$symbols['AUD'] = 'A$';
$symbols['JPY'] = '¥';
$symbols['CNY'] = '¥';
$symbols['PKR'] = 'Rs';
$symbols['BDT'] = 'Tk';
$symbols['MYR'] = 'RM';
$symbols['SGD'] = 'S$';
$symbols['ZAR'] = 'R';
$symbols['NGN'] = 'N';
$symbols['KES'] = 'KSh';
$symbols['GHS'] = 'GH';
$symbols['EGP'] = 'EGP';
$symbols['BRL'] = 'R$';
$symbols['MXN'] = 'MX$';

$symbol = '$';
if (isset($symbols[$currency])) {
    $symbol = $symbols[$currency];
}

$stmt = $conn->prepare("SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, so.order_number FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id LEFT JOIN sales_orders so ON i.sales_order_id = so.id WHERE i.id = ?");
$stmt->execute(array($invoice_id));
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header('Location: invoices.php?error=Invoice not found');
    exit;
}

$stmt = $conn->prepare("SELECT il.*, p.name as item_name, p.sku FROM invoice_lines il LEFT JOIN products p ON il.item_id = p.id WHERE il.invoice_id = ?");
$stmt->execute(array($invoice_id));
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                font-size: 12px;
            }

            .invoice-container {
                box-shadow: none !important;
            }
        }

        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .invoice-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .invoice-header {
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

        .invoice-title {
            font-size: 32px;
            font-weight: 300;
            color: #3498db;
        }

        .invoice-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .table thead th {
            background-color: #3498db;
            color: white;
            border: none;
        }

        .total-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .grand-total {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="text-center my-3 no-print">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="fas fa-print me-1"></i> Print Invoice
            </button>
            <a href="invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Invoices
            </a>
        </div>

        <div class="invoice-container">
            <div class="invoice-header">
                <div class="row">
                    <div class="col-6">
                        <div class="company-name"><?php echo htmlspecialchars($companyName); ?></div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="invoice-title">INVOICE</div>
                        <div class="fs-5 fw-bold"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                        <?php
                        $statusClass = 'status-pending';
                        if ($invoice['status'] == 'Paid') {
                            $statusClass = 'status-paid';
                        }
                        ?>
                        <span
                            class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($invoice['status']); ?></span>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <h6 class="text-uppercase text-muted small fw-bold">Bill To</h6>
                    <div class="fw-bold"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                    <div class="text-muted"><?php echo htmlspecialchars($invoice['customer_email']); ?></div>
                </div>
                <div class="col-6">
                    <div class="invoice-details">
                        <div class="row mb-2">
                            <div class="col-6 text-muted">Invoice Date:</div>
                            <div class="col-6 text-end fw-bold">
                                <?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 text-muted">Sales Order:</div>
                            <div class="col-6 text-end fw-bold">
                                <?php echo htmlspecialchars($invoice['order_number']); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-6 text-muted">Currency:</div>
                            <div class="col-6 text-end fw-bold"><?php echo htmlspecialchars($currency); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <table class="table table-bordered mb-4">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>SKU</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lines)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No items found</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $count = 1;
                        foreach ($lines as $line):
                            ?>
                            <tr>
                                <td><?php echo $count; ?></td>
                                <td><?php echo htmlspecialchars($line['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($line['sku']); ?></td>
                                <td class="text-center"><?php echo number_format($line['quantity'], 0); ?></td>
                                <td class="text-end"><?php echo $symbol . number_format($line['unit_price'], 2); ?></td>
                                <td class="text-end"><?php echo $symbol . number_format($line['line_total'], 2); ?></td>
                            </tr>
                            <?php
                            $count++;
                        endforeach;
                        ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="row">
                <div class="col-6">
                    <div class="text-muted small">
                        <strong>Notes:</strong><br>
                        Thank you for your business!
                    </div>
                </div>
                <div class="col-6">
                    <div class="total-section">
                        <div class="row mb-2">
                            <div class="col-6">Subtotal:</div>
                            <div class="col-6 text-end">
                                <?php echo $symbol . number_format($invoice['total_amount'], 2); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">Tax (0%):</div>
                            <div class="col-6 text-end"><?php echo $symbol . '0.00'; ?></div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6 grand-total">Total:</div>
                            <div class="col-6 text-end grand-total">
                                <?php echo $symbol . number_format($invoice['total_amount'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5 pt-4 border-top">
                <p class="text-muted small mb-0">This is a computer-generated invoice. No signature required.</p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>