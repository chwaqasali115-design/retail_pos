<?php
// purchase_create.php - Create New Purchase Order
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

// Handle Purchase Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    try {
        $conn->beginTransaction();
        require_once 'core/AccountingHelper.php';
        $acc = new AccountingHelper($conn);

        $vendor_id = $_POST['vendor_id'];
        $grand_total = $_POST['grand_total'];
        $status = isset($_POST['status']) ? $_POST['status'] : 'Received';
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $po_number = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);

        // Create Purchase Record
        $stmt = $conn->prepare("INSERT INTO purchase_orders (company_id, vendor_id, po_number, status, order_date, total_amount, notes, created_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)");
        $stmt->execute(array(
            $_SESSION['company_id'],
            $vendor_id,
            $po_number,
            $status,
            $grand_total,
            $notes,
            $_SESSION['user_id']
        ));
        $purchaseId = $conn->lastInsertId();

        // Insert Items & Update Stock
        $products = $_POST['product_id'];
        $qtys = $_POST['qty'];
        $costs = $_POST['cost'];

        for ($i = 0; $i < count($products); $i++) {
            if ($products[$i] != '' && $qtys[$i] > 0) {
                $pid = $products[$i];
                $qty = $qtys[$i];
                $cost = $costs[$i];
                $total = $qty * $cost;

                // Purchase Item
                $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, total) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute(array($purchaseId, $pid, $qty, $cost, $total));

                // Update Stock only if status is Received
                if ($status == 'Received') {
                    // Update products table (main stock)
                    $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND company_id = ?");
                    $stmt->execute(array($qty, $pid, $_SESSION['company_id']));

                    // Also update inventory_stock for warehouse-based inventory
                    $whStmt = $conn->prepare("SELECT id FROM warehouses WHERE store_id = ? LIMIT 1");
                    $whStmt->execute([$_SESSION['store_id'] ?? 1]);
                    $wh = $whStmt->fetch(PDO::FETCH_ASSOC);
                    $warehouseId = $wh['id'] ?? 1;

                    // Insert or update inventory_stock
                    $invCheck = $conn->prepare("SELECT id FROM inventory_stock WHERE product_id = ? AND warehouse_id = ?");
                    $invCheck->execute([$pid, $warehouseId]);
                    if ($invCheck->fetch()) {
                        $invUpdate = $conn->prepare("UPDATE inventory_stock SET quantity = quantity + ? WHERE product_id = ? AND warehouse_id = ?");
                        $invUpdate->execute([$qty, $pid, $warehouseId]);
                    } else {
                        $invInsert = $conn->prepare("INSERT INTO inventory_stock (product_id, warehouse_id, quantity) VALUES (?, ?, ?)");
                        $invInsert->execute([$pid, $warehouseId, $qty]);
                    }
                }

                // Update Product Cost Price
                $updPrice = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                $updPrice->execute(array($cost, $pid));
            }
        }

        // ACCOUNTING AUTOMATION
        if ($status == 'Received') {
            // 1. Create Vendor Invoice (Bill)
            $billNo = 'BILL-' . $po_number;
            $stmt = $conn->prepare("INSERT INTO vendor_invoices (company_id, vendor_id, purchase_order_id, bill_number, bill_date, total_amount, status) VALUES (?, ?, ?, ?, NOW(), ?, 'Pending')");
            $stmt->execute([$_SESSION['company_id'], $vendor_id, $purchaseId, $billNo, $grand_total]);

            // 2. Update Vendor Opening Balance (Add Payable)
            $stmt = $conn->prepare("UPDATE vendors SET opening_balance = opening_balance + ? WHERE id = ?");
            $stmt->execute([$grand_total, $vendor_id]);

            // 3. Post to GL
            // Debit: Inventory Asset (1002) OR Expense
            // Credit: Accounts Payable (2001)
            $inventoryAccountId = $acc->getAccountId(1002);
            $apAccountId = $acc->getAccountId(2001);

            $glEntries = [
                ['account_id' => $inventoryAccountId, 'debit' => $grand_total, 'credit' => 0], // Dr Inventory
                ['account_id' => $apAccountId, 'debit' => 0, 'credit' => $grand_total]  // Cr Payable
            ];
            $acc->createJournalEntry($_SESSION['company_id'], date('Y-m-d'), $po_number, "Purchase Order $po_number", $glEntries);
        }

        $conn->commit();
        $message = "Purchase order created successfully! PO#: " . $po_number;
        if ($status == 'Received') {
            $message .= " Stock, Vendor Balance & Accounting updated.";
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Failed: " . $e->getMessage();
    }
}

// Fetch Vendors & Products
$stmt = $conn->prepare("SELECT * FROM vendors WHERE company_id = ? ORDER BY name ASC");
$stmt->execute(array($_SESSION['company_id']));
$vendorList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM products WHERE company_id = ? ORDER BY name ASC");
$stmt->execute(array($_SESSION['company_id']));
$productList = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-plus me-2"></i>New Purchase Order</h2>
        <a href="purchases.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Purchases
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

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Purchase Order Details</h5>
        </div>
        <div class="card-body">
            <?php if (empty($vendorList)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No vendors found. <a href="vendor_create.php">Add a vendor first</a> before creating a purchase order.
                </div>
            <?php elseif (empty($productList)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No products found. <a href="products.php">Add products first</a> before creating a purchase order.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="save_purchase" value="1">

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Select Vendor *</label>
                            <select name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendorList as $v): ?>
                                    <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><a href="vendor_create.php">+ Add new vendor</a></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="Pending">Pending</option>
                                <option value="Ordered">Ordered</option>
                                <option value="Received" selected>Received (Add to Stock)</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="mb-3 border-bottom pb-2">Purchase Items</h6>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="poTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th width="120">Quantity</th>
                                    <th width="150">Unit Cost (<?php echo $symbol; ?>)</th>
                                    <th width="150">Total (<?php echo $symbol; ?>)</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select name="product_id[]" class="form-select prod-select" required
                                            onchange="updateCost(this)">
                                            <option value="">-- Select Product --</option>
                                            <?php foreach ($productList as $p): ?>
                                                <option value="<?php echo $p['id']; ?>"
                                                    data-cost="<?php echo isset($p['cost_price']) ? $p['cost_price'] : 0; ?>">
                                                    <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['sku']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control qty" min="1" value="1"
                                            oninput="calcRow(this)"></td>
                                    <td><input type="number" name="cost[]" class="form-control cost" step="0.01" min="0"
                                            oninput="calcRow(this)"></td>
                                    <td><input type="number" class="form-control total" readonly></td>
                                    <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i
                                                class="fas fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-sm btn-secondary mb-3" onclick="addRow()">
                        <i class="fas fa-plus me-1"></i>Add Item
                    </button>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2"
                                placeholder="Any additional notes..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Grand Total</label>
                            <input type="number" name="grand_total" id="grandTotal"
                                class="form-control fw-bold fs-4 bg-light" readonly>
                            <button type="submit" class="btn btn-success w-100 mt-3">
                                <i class="fas fa-save me-2"></i>Create Purchase Order
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function calcRow(el) {
        let row = el.closest('tr');
        let qty = parseFloat(row.querySelector('.qty').value) || 0;
        let cost = parseFloat(row.querySelector('.cost').value) || 0;
        row.querySelector('.total').value = (qty * cost).toFixed(2);
        calcGrand();
    }

    function updateCost(select) {
        let opt = select.options[select.selectedIndex];
        let row = select.closest('tr');
        let cost = opt.getAttribute('data-cost');
        row.querySelector('.cost').value = cost ? cost : '';
        calcRow(select);
    }

    function calcGrand() {
        let sum = 0;
        document.querySelectorAll('.total').forEach(e => sum += parseFloat(e.value) || 0);
        document.getElementById('grandTotal').value = sum.toFixed(2);
    }

    function addRow() {
        let table = document.getElementById('poTable').getElementsByTagName('tbody')[0];
        let newRow = table.rows[0].cloneNode(true);
        newRow.querySelectorAll('input').forEach(i => i.value = '');
        newRow.querySelector('.qty').value = 1;
        newRow.querySelector('.prod-select').value = '';
        table.appendChild(newRow);
    }

    function removeRow(btn) {
        if (document.querySelectorAll('#poTable tbody tr').length > 1) {
            btn.closest('tr').remove();
            calcGrand();
        }
    }
</script>

<?php require_once 'templates/footer.php'; ?>