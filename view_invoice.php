<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Handle both ?id= and ?so_id= parameters
$invoice_id = $_GET['id'] ?? null;
$so_id = $_GET['so_id'] ?? null;

if ($so_id && !$invoice_id) {
    // Find invoice by sales order ID
    $stmt = $conn->prepare("SELECT id FROM invoices WHERE sales_order_id = ?");
    $stmt->execute([$so_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $invoice_id = $result['id'];
    }
}

if (!$invoice_id) {
    header('Location: invoices.php?error=No invoice specified');
    exit;
}

// Redirect to print view
header('Location: print_invoice.php?id=' . $invoice_id);
exit;
?>