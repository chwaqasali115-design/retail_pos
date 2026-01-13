<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id'])) {
    die("No sale ID specified");
}

$sale_id = $_GET['id'];

// Fetch Sale
$stmt = $conn->prepare("SELECT s.*, u.username FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale)
    die("Sale not found");

// Fetch Items
$stmt = $conn->prepare("SELECT si.*, p.name, p.sku FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Company Info
$stmt = $conn->prepare("SELECT company_name, phone, address FROM companies WHERE id = ?");
$stmt->execute([$_SESSION['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Customer Info (if exists)
$customer = null;
if (!empty($sale['customer_id'])) {
    $stmt = $conn->prepare("SELECT name, phone, loyalty_points FROM customers WHERE id = ?");
    $stmt->execute([$sale['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Receipt #
        <?php echo $sale['invoice_no']; ?>
    </title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 300px;
            margin: 0 auto;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .line {
            border-bottom: 1px dashed #000;
            margin: 5px 0;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">
    <div class="text-center">
        <h3 style="margin:0;">
            <?php echo $company['company_name']; ?>
        </h3>
        <p>
            <?php echo $company['address']; ?><br>Tel:
            <?php echo $company['phone']; ?>
        </p>
        <div class="line"></div>
        <p>Inv:
            <?php echo $sale['invoice_no']; ?><br>
            Date:
            <?php echo $sale['sale_date']; ?><br>
            Cashier:
            <?php echo $sale['username']; ?>
        </p>
    </div>

    <!-- Customer Information -->
    <div class="line"></div>
    <div style="padding: 5px 0;">
        <?php if ($customer): ?>
            <div class="item-row">
                <span class="bold">Customer:</span>
                <span><?php echo htmlspecialchars($customer['name']); ?></span>
            </div>
            <?php if (!empty($customer['phone'])): ?>
                <div class="item-row">
                    <span>Phone:</span>
                    <span><?php echo htmlspecialchars($customer['phone']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($customer['loyalty_points'] > 0): ?>
                <div class="item-row">
                    <span>Loyalty Points:</span>
                    <span><?php echo number_format($customer['loyalty_points']); ?> pts</span>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="item-row">
                <span class="bold">Customer:</span>
                <span>Walk-in Customer</span>
            </div>
        <?php endif; ?>
    </div>
    <div class="line"></div>

    <div>
        <?php foreach ($items as $item): ?>
            <div>
                <?php echo $item['name']; ?>
            </div>
            <div class="item-row">
                <span>
                    <?php echo $item['quantity']; ?> x
                    <?php echo number_format($item['unit_price'], 2); ?>
                </span>
                <span>
                    <?php echo number_format($item['total'], 2); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="line"></div>

    <div class="item-row"><span>Subtotal:</span> <span>
            <?php echo number_format($sale['subtotal'], 2); ?>
        </span></div>
    <div class="item-row"><span>Tax:</span> <span>
            <?php echo number_format($sale['tax_total'], 2); ?>
        </span></div>
    <div class="item-row bold" style="font-size: 14px; margin-top:5px;">
        <span>TOTAL:</span> <span>
            <?php echo number_format($sale['grand_total'], 2); ?>
        </span>
    </div>

    <?php
    // Fetch Payments
    $payStmt = $conn->prepare("SELECT * FROM sale_payments WHERE sale_id = ?");
    $payStmt->execute([$sale_id]);
    $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="item-row" style="margin-top:5px;">
        <span class="bold">Payments:</span>
    </div>
    <?php foreach ($payments as $pay): ?>
        <div class="item-row">
            <span><?php echo htmlspecialchars($pay['method']); ?>:</span>
            <span><?php echo number_format($pay['amount'], 2); ?></span>
        </div>
    <?php endforeach; ?>

    <div class="item-row bold" style="border-top: 1px dashed #000; margin-top:5px; padding-top:2px;">
        <span>Change:</span>
        <span>0.00</span>
    </div>

    <?php if ($sale['notes']): ?>
        <div class="line"></div>
        <p class="text-center">Note:
            <?php echo $sale['notes']; ?>
        </p>
    <?php endif; ?>

    <!-- FBR Invoice Section -->
    <?php if (!empty($sale['fbr_invoice_no'])): ?>
        <div class="line"></div>
        <div class="text-center">
            <p style="margin:5px 0; font-weight:bold;">FBR Invoice ID:
                <?php echo htmlspecialchars($sale['fbr_invoice_no']); ?></p>
            <?php if (!empty($sale['fbr_qr_code'])): ?>
                <!-- Note: fbr_qr_code usually is a string to generate QR, or image URL. 
                     If it is a long string, we might need a JS lib to render it. 
                     For now assuming it's a value to print or the raw string. -->
                <!-- <div style="margin: 5px auto; width: 100px; height: 100px; background: #ccc;">QR HERE</div> -->
                <!-- If FBR provides exact image URL or we use a library: -->
            <?php endif; ?>
            <p style="font-size: 10px;">Verify at fbr.gov.pk</p>
        </div>
    <?php elseif (isset($sale['fbr_status']) && $sale['fbr_status'] != 'SYNCED' && $sale['fbr_status'] !== null): ?>
        <div class="line"></div>
        <div class="text-center">
            <p style="margin:5px 0;">FBR Status: <strong>PENDING</strong></p>
        </div>
    <?php endif; ?>

    <div class="line"></div>
    <div class="text-center">
        <p>Thank you for shopping!</p>
        <div class="no-print" style="margin-top: 20px;">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
        </div>
    </div>
</body>

</html>