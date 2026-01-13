<?php
// purchases.php - Purchase Overview
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

// Get currency
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

// Handle Receive Purchase
if (isset($_GET['receive'])) {
    $purchase_id = $_GET['receive'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT * FROM purchase_orders WHERE id = ? AND company_id = ?");
        $stmt->execute(array($purchase_id, $_SESSION['company_id']));
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($purchase && $purchase['status'] != 'Received') {
            // 1. Update Stock
            $stmt = $conn->prepare("SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?");
            $stmt->execute(array($purchase_id));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                // Update products table (main stock)
                $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND company_id = ?");
                $stmt->execute(array($item['quantity'], $item['product_id'], $_SESSION['company_id']));

                // Also update inventory_stock for warehouse-based inventory
                // First, get the default warehouse for this store
                $whStmt = $conn->prepare("SELECT id FROM warehouses WHERE store_id = ? LIMIT 1");
                $whStmt->execute([$_SESSION['store_id'] ?? 1]);
                $wh = $whStmt->fetch(PDO::FETCH_ASSOC);
                $warehouseId = $wh['id'] ?? 1;

                // Insert or update inventory_stock
                $invCheck = $conn->prepare("SELECT id FROM inventory_stock WHERE product_id = ? AND warehouse_id = ?");
                $invCheck->execute([$item['product_id'], $warehouseId]);
                if ($invCheck->fetch()) {
                    $invUpdate = $conn->prepare("UPDATE inventory_stock SET quantity = quantity + ? WHERE product_id = ? AND warehouse_id = ?");
                    $invUpdate->execute([$item['quantity'], $item['product_id'], $warehouseId]);
                } else {
                    $invInsert = $conn->prepare("INSERT INTO inventory_stock (product_id, warehouse_id, quantity) VALUES (?, ?, ?)");
                    $invInsert->execute([$item['product_id'], $warehouseId, $item['quantity']]);
                }
            }

            // 2. Update Status
            $stmt = $conn->prepare("UPDATE purchase_orders SET status = 'Received' WHERE id = ? AND company_id = ?");
            $stmt->execute(array($purchase_id, $_SESSION['company_id']));

            // 3. ACCOUNTING AUTOMATION
            require_once 'core/AccountingHelper.php';
            $acc = new AccountingHelper($conn);

            // A. Create Vendor Invoice (Bill)
            $billNo = 'BILL-' . ($purchase['po_number'] ?? $purchase['id']);
            $stmt = $conn->prepare("INSERT INTO vendor_invoices (company_id, vendor_id, purchase_order_id, bill_number, bill_date, total_amount, status) VALUES (?, ?, ?, ?, NOW(), ?, 'Pending')");
            $stmt->execute([$_SESSION['company_id'], $purchase['vendor_id'], $purchase_id, $billNo, $purchase['total_amount']]);
            $billId = $conn->lastInsertId();

            // B. Update Vendor Balance
            $stmt = $conn->prepare("UPDATE vendors SET opening_balance = opening_balance + ? WHERE id = ?");
            $stmt->execute([$purchase['total_amount'], $purchase['vendor_id']]);

            // C. Post to GL
            $glEntries = [
                ['account_id' => 1002, 'debit' => $purchase['total_amount'], 'credit' => 0], // Dr Inventory
                ['account_id' => 2001, 'debit' => 0, 'credit' => $purchase['total_amount']]  // Cr Payable
            ];
            $acc->createJournalEntry($_SESSION['company_id'], date('Y-m-d'), $purchase['po_number'], "Purchase Order " . $purchase['po_number'], $glEntries);

            $conn->commit();
            $message = "Purchase received! Stock, Vendor Balance & Accounting updated.";
        } else {
            $error = "Purchase already received or not found.";
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Purchase
if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
        $stmt->execute(array($_GET['delete']));

        $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE id = ? AND company_id = ?");
        $stmt->execute(array($_GET['delete'], $_SESSION['company_id']));
        $message = "Purchase deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting purchase.";
    }
}

// Fetch Purchase History with Bill Info
$stmt = $conn->prepare("SELECT po.*, v.name as vendor_name, vi.id as bill_id 
                        FROM purchase_orders po 
                        LEFT JOIN vendors v ON po.vendor_id = v.id 
                        LEFT JOIN vendor_invoices vi ON po.id = vi.purchase_order_id
                        WHERE po.company_id = ? 
                        ORDER BY po.order_date DESC");
$stmt->execute(array($_SESSION['company_id']));
$purchaseList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalPurchases = count($purchaseList);
$totalAmount = 0;
$pendingCount = 0;
$receivedCount = 0;

foreach ($purchaseList as $p) {
    $totalAmount += $p['total_amount'];
    if ($p['status'] == 'Pending' || $p['status'] == 'Ordered')
        $pendingCount++;
    if ($p['status'] == 'Received')
        $receivedCount++;
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-truck-loading me-2"></i>Purchase Overview</h2>
        <a href="purchase_create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Purchase Order
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

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Purchase Orders</h6>
                            <h3><?php echo $totalPurchases; ?></h3>
                        </div>
                        <i class="fas fa-file-invoice fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Purchase Value</h6>
                            <h3><?php echo $symbol . number_format($totalAmount, 2); ?></h3>
                        </div>
                        <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Pending Orders</h6>
                            <h3><?php echo $pendingCount; ?></h3>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Received Orders</h6>
                            <h3><?php echo $receivedCount; ?></h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase History Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>All Purchase Orders</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>PO Number</th>
                            <th>Vendor</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchaseList)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-file-invoice fa-3x mb-3 d-block"></i>
                                    No purchase orders found. <a href="purchase_create.php">Create your first purchase</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($purchaseList as $purchase): ?>
                                <?php
                                $statusClass = 'bg-secondary';
                                if ($purchase['status'] == 'Pending')
                                    $statusClass = 'bg-warning';
                                if ($purchase['status'] == 'Ordered')
                                    $statusClass = 'bg-info';
                                if ($purchase['status'] == 'Received')
                                    $statusClass = 'bg-success';
                                if ($purchase['status'] == 'Cancelled')
                                    $statusClass = 'bg-danger';
                                $poNumber = isset($purchase['po_number']) && $purchase['po_number'] ? $purchase['po_number'] : 'PO-' . $purchase['id'];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($poNumber); ?></strong></td>
                                    <td><?php echo htmlspecialchars($purchase['vendor_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($purchase['order_date'])); ?></td>
                                    <td><?php echo $symbol . number_format($purchase['total_amount'], 2); ?></td>
                                    <td><span
                                            class="badge <?php echo $statusClass; ?>"><?php echo $purchase['status']; ?></span>
                                    </td>
                                    <td>
                                        <a href="purchase_view.php?id=<?php echo $purchase['id']; ?>"
                                            class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($purchase['status'] != 'Received'): ?>
                                            <a href="?receive=<?php echo $purchase['id']; ?>" class="btn btn-sm btn-success"
                                                title="Mark as Received"
                                                onclick="return confirm('Mark as received? This will add items to inventory and create accounting entries.')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php elseif (isset($purchase['bill_id'])): ?>
                                            <a href="vendor_bill_view.php?id=<?php echo $purchase['bill_id']; ?>"
                                                class="btn btn-sm btn-secondary" title="View Bill">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $purchase['id']; ?>" class="btn btn-sm btn-danger"
                                            title="Delete" onclick="return confirm('Delete this purchase?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>