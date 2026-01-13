<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['so_id'])) {
    header('Location: sales_orders.php?error=No sales order specified');
    exit;
}

$so_id = $_GET['so_id'];

// Fetch the sales order
$stmt = $conn->prepare("SELECT * FROM sales_orders WHERE id = ?");
$stmt->execute([$so_id]);
$salesOrder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salesOrder) {
    header('Location: sales_orders.php?error=Sales order not found');
    exit;
}

if ($salesOrder['status'] !== 'Confirmed') {
    header('Location: sales_orders.php?error=Only confirmed orders can be invoiced');
    exit;
}

// Check if invoice already exists for this sales order
$stmt = $conn->prepare("SELECT id FROM invoices WHERE sales_order_id = ?");
$stmt->execute([$so_id]);
if ($stmt->fetch()) {
    header('Location: sales_orders.php?error=Invoice already exists for this order');
    exit;
}

try {
    $conn->beginTransaction();

    // Generate invoice number
    $invoiceNumber = 'INV-' . date('Y') . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);

    // Create the invoice
    $stmt = $conn->prepare("
        INSERT INTO invoices (invoice_number, sales_order_id, customer_id, invoice_date, total_amount, currency, status, created_at)
        VALUES (?, ?, ?, NOW(), ?, ?, 'Pending', NOW())
    ");
    $stmt->execute([
        $invoiceNumber,
        $so_id,
        $salesOrder['customer_id'],
        $salesOrder['total_amount'],
        $salesOrder['currency'] ?? 'USD'
    ]);

    $invoiceId = $conn->lastInsertId();

    // Copy sales order lines to invoice lines
    // Changed 'sales_order_id' to 'order_id' - adjust if your column name is different
    $stmt = $conn->prepare("SELECT * FROM sales_order_lines WHERE order_id = ?");
    $stmt->execute([$so_id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($lines)) {
        $insertLine = $conn->prepare("
            INSERT INTO invoice_lines (invoice_id, item_id, quantity, unit_price, line_total)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($lines as $line) {
            $insertLine->execute([
                $invoiceId,
                $line['item_id'] ?? $line['product_id'] ?? null,
                $line['quantity'] ?? $line['qty'] ?? 0,
                $line['unit_price'] ?? $line['price'] ?? 0,
                $line['net_amount'] ?? $line['total'] ?? $line['line_total'] ?? 0
            ]);
        }
    }

    // Update sales order status
    $stmt = $conn->prepare("UPDATE sales_orders SET status = 'Invoiced' WHERE id = ?");
    $stmt->execute([$so_id]);

    // ACCOUNTING AUTOMATION
    require_once 'core/AccountingHelper.php';
    $acc = new AccountingHelper($conn);

    // 1. Update Customer Balance (Add Receivable)
    $stmt = $conn->prepare("UPDATE customers SET opening_balance = opening_balance + ? WHERE id = ?");
    $stmt->execute([$salesOrder['total_amount'], $salesOrder['customer_id']]);

    // 2. Post to GL
    // Debit: Accounts Receivable (1003)
    // Credit: Sales Revenue (4001)
    $arAccountId = $acc->getAccountId('1003');
    $revenueAccountId = $acc->getAccountId('4001');

    $glEntries = [
        ['account_id' => $arAccountId, 'debit' => $salesOrder['total_amount'], 'credit' => 0], // Dr AR
        ['account_id' => $revenueAccountId, 'debit' => 0, 'credit' => $salesOrder['total_amount']]  // Cr Revenue
    ];
    $acc->createJournalEntry($_SESSION['company_id'], date('Y-m-d'), $invoiceNumber, "Invoice generated for SO #$so_id", $glEntries);

    $conn->commit();

    header('Location: invoices.php?success=Invoice ' . $invoiceNumber . ' created successfully');
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    header('Location: sales_orders.php?error=Failed to create invoice: ' . $e->getMessage());
    exit;
}
?>