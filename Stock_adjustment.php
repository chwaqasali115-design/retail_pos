<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';
$importResults = []; // For bulk import feedback

$stmt = $conn->prepare("SELECT currency FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$companyData = $stmt->fetch(PDO::FETCH_ASSOC);
$currency = isset($companyData['currency']) ? $companyData['currency'] : 'USD';

$symbols = array(
    'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹',
    'AED' => 'AED ', 'PKR' => 'Rs', 'BDT' => 'Tk'
);
$symbol = isset($symbols[$currency]) ? $symbols[$currency] : '$';

// Get Active Warehouse for Synchronization
$warehouse_id = null;
if (isset($_SESSION['store_id'])) {
    $wStmt = $conn->prepare("SELECT id FROM warehouses WHERE store_id = ? LIMIT 1");
    $wStmt->execute([$_SESSION['store_id']]);
    $wh = $wStmt->fetch(PDO::FETCH_ASSOC);
    $warehouse_id = $wh['id'] ?? null;
}

// Handle BULK CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($file); // Skip header row
        
        $successCount = 0;
        $errorCount = 0;
        $adjustment_type = $_POST['bulk_adjustment_type'];
        
        try {
            $conn->beginTransaction();
            
            while (($row = fgetcsv($file)) !== false) {
                if (count($row) >= 2) {
                    $sku = trim($row[0]);
                    $quantity = intval($row[1]);
                    
                    if (empty($sku) || $quantity <= 0) {
                        $errorCount++;
                        $importResults[] = "Skipped: Invalid data - SKU: $sku, Qty: $quantity";
                        continue;
                    }
                    
                    // Find product by SKU
                    $findStmt = $conn->prepare("SELECT id, name, stock_quantity FROM products WHERE sku = ? AND company_id = ?");
                    $findStmt->execute([$sku, $_SESSION['company_id']]);
                    $product = $findStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($product) {
                        if ($adjustment_type == 'add') {
                            $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                        } else {
                            $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                        }
                        $updateStmt->execute([$quantity, $product['id']]);


                        // Sync with Warehouse Inventory
                        if ($warehouse_id) {
                            $checkInv = $conn->prepare("SELECT id, quantity FROM inventory_stock WHERE product_id = ? AND warehouse_id = ?");
                            $checkInv->execute([$product['id'], $warehouse_id]);
                            $invRow = $checkInv->fetch(PDO::FETCH_ASSOC);

                            if ($invRow) {
                                if ($adjustment_type == 'add') {
                                    $updInv = $conn->prepare("UPDATE inventory_stock SET quantity = quantity + ? WHERE id = ?");
                                } else {
                                    $updInv = $conn->prepare("UPDATE inventory_stock SET quantity = quantity - ? WHERE id = ?");
                                }
                                $updInv->execute([$quantity, $invRow['id']]);
                            } else {
                                // If adding, create record. If subtracting, create record with negative?
                                // Usually if we subtract from 0 it becomes negative.
                                $initialQty = ($adjustment_type == 'add') ? $quantity : -$quantity;
                                $insInv = $conn->prepare("INSERT INTO inventory_stock (product_id, warehouse_id, quantity) VALUES (?, ?, ?)");
                                $insInv->execute([$product['id'], $warehouse_id, $initialQty]);
                            }
                            
                            // Log Transaction
                            $transType = ($adjustment_type == 'add') ? 'Adjustment' : 'Adjustment'; // Could distinguish if needed
                            $transQty = ($adjustment_type == 'add') ? $quantity : -$quantity;
                            $logStmt = $conn->prepare("INSERT INTO inventory_transactions (warehouse_id, product_id, type, quantity, reference_id) VALUES (?, ?, ?, ?, NULL)");
                            $logStmt->execute([$warehouse_id, $product['id'], 'Adjustment', $transQty]);
                        }
                        
                        $successCount++;
                        $sign = $adjustment_type == 'add' ? '+' : '-';
                        $importResults[] = "✓ {$product['name']} (SKU: $sku): {$sign}{$quantity}";
                    } else {
                        $errorCount++;
                        $importResults[] = "✗ Product not found: SKU $sku";
                    }
                }
            }
            
            $conn->commit();
            fclose($file);
            $message = "Bulk import completed! Success: $successCount, Errors: $errorCount";
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
    } else {
        $error = "Please select a valid CSV file.";
    }
}

// Handle Single Product Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $product_id = $_POST['product_id'];
    $adjustment_type = $_POST['adjustment_type'];
    $quantity = intval($_POST['quantity']);
    $reason = trim($_POST['reason']);
    
    if ($quantity <= 0) {
        $error = "Quantity must be greater than 0.";
    } else {
        try {
            if ($adjustment_type == 'add') {
                $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND company_id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND company_id = ?");
            }
            $stmt->execute(array($quantity, $product_id, $_SESSION['company_id']));

            // Sync with Warehouse Inventory
            if ($warehouse_id) {
                $checkInv = $conn->prepare("SELECT id, quantity FROM inventory_stock WHERE product_id = ? AND warehouse_id = ?");
                $checkInv->execute([$product_id, $warehouse_id]);
                $invRow = $checkInv->fetch(PDO::FETCH_ASSOC);

                if ($invRow) {
                    if ($adjustment_type == 'add') {
                        $updInv = $conn->prepare("UPDATE inventory_stock SET quantity = quantity + ? WHERE id = ?");
                    } else {
                        $updInv = $conn->prepare("UPDATE inventory_stock SET quantity = quantity - ? WHERE id = ?");
                    }
                    $updInv->execute([$quantity, $invRow['id']]);
                } else {
                    $initialQty = ($adjustment_type == 'add') ? $quantity : -$quantity;
                    $insInv = $conn->prepare("INSERT INTO inventory_stock (product_id, warehouse_id, quantity) VALUES (?, ?, ?)");
                    $insInv->execute([$product_id, $warehouse_id, $initialQty]);
                }

                // Log Transaction
                $transQty = ($adjustment_type == 'add') ? $quantity : -$quantity;
                 // Ideally we should log the user/reason too, but table schema focuses on qty/ref
                $logStmt = $conn->prepare("INSERT INTO inventory_transactions (warehouse_id, product_id, type, quantity, reference_id) VALUES (?, ?, 'Adjustment', ?, NULL)");
                $logStmt->execute([$warehouse_id, $product_id, $transQty]);
            }
            $message = "Stock adjusted successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM products WHERE company_id = ? ORDER BY name ASC");
$stmt->execute(array($_SESSION['company_id']));
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedProductId = isset($_GET['product_id']) ? $_GET['product_id'] : '';

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-sliders-h me-2"></i>Stock Adjustment</h2>
        <a href="inventory.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Inventory
        </a>
    </div>

    <?php if ($message != ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error != ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Adjust Stock</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="adjust_stock" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Select Product *</label>
                            <select name="product_id" id="product_select" class="form-select" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $product): ?>
                                <?php
                                $stockQty = isset($product['stock_quantity']) ? $product['stock_quantity'] : 0;
                                $selected = ($selectedProductId == $product['id']) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $product['id']; ?>" 
                                    data-stock="<?php echo $stockQty; ?>"
                                    data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?> (Stock: <?php echo $stockQty; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="text" id="current_stock" class="form-control bg-light" readonly value="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type *</label>
                            <select name="adjustment_type" class="form-select" required>
                                <option value="add">Add Stock (+)</option>
                                <option value="subtract">Remove Stock (-)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantity *</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Reason for adjustment (optional)"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-info w-100">
                            <i class="fas fa-check me-2"></i>Apply Adjustment
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Bulk Import Card -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-file-import me-2"></i>Bulk Stock Import (CSV)</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="bulk_import" value="1">
                        
                        <div class="alert alert-info mb-3">
                            <strong>CSV Format:</strong> SKU, Quantity<br>
                            <small class="text-muted">First row should be header (will be skipped). Example:</small>
                            <pre class="mb-0 mt-2 bg-light p-2 rounded">sku,quantity
SKU001,50
SKU002,100
SKU003,25</pre>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type *</label>
                            <select name="bulk_adjustment_type" class="form-select" required>
                                <option value="add">Add Stock (+)</option>
                                <option value="subtract">Remove Stock (-)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CSV File *</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-upload me-2"></i>Import Stock
                        </button>
                    </form>
                    
                    <?php if (!empty($importResults)): ?>
                    <div class="mt-3">
                        <h6>Import Results:</h6>
                        <div class="bg-light p-2 rounded" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($importResults as $result): ?>
                                <div class="small"><?php echo htmlspecialchars($result); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Reference</h5>
                </div>
                <div class="card-body">
                    <h6>Low Stock Items</h6>
                    <ul class="list-group mb-3">
                        <?php 
                        $lowStockItems = array();
                        foreach ($products as $product) {
                            $stockQty = isset($product['stock_quantity']) ? $product['stock_quantity'] : 0;
                            $reorderLevel = isset($product['reorder_level']) ? $product['reorder_level'] : 10;
                            if ($stockQty <= $reorderLevel && $stockQty > 0) {
                                $lowStockItems[] = $product;
                            }
                        }
                        
                        if (empty($lowStockItems)):
                        ?>
                            <li class="list-group-item text-muted">No low stock items</li>
                        <?php else: ?>
                            <?php foreach ($lowStockItems as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <span class="badge bg-warning"><?php echo $item['stock_quantity']; ?></span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    
                    <h6>Out of Stock Items</h6>
                    <ul class="list-group">
                        <?php 
                        $outOfStockItems = array();
                        foreach ($products as $product) {
                            $stockQty = isset($product['stock_quantity']) ? $product['stock_quantity'] : 0;
                            if ($stockQty <= 0) {
                                $outOfStockItems[] = $product;
                            }
                        }
                        
                        if (empty($outOfStockItems)):
                        ?>
                            <li class="list-group-item text-muted">No out of stock items</li>
                        <?php else: ?>
                            <?php foreach ($outOfStockItems as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <span class="badge bg-danger">0</span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var productSelect = document.getElementById('product_select');
    var currentStock = document.getElementById('current_stock');
    
    productSelect.addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        var stock = selected.getAttribute('data-stock');
        currentStock.value = stock ? stock : '0';
    });
    
    // Trigger change on page load if product is pre-selected
    if (productSelect.value) {
        productSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>