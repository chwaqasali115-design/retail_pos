<?php
// inventory.php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle Product Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        $conn->beginTransaction();

        // 1. Insert Product
        $stmt = $conn->prepare("
            INSERT INTO products (company_id, name, sku, barcode, category_id, tax_id, cost_price, sell_price, uom) 
            VALUES (:cid, :name, :sku, :barcode, :cat, :tax, :cost, :sell, :uom)
        ");
        $stmt->execute([
            ':cid' => $_SESSION['company_id'],
            ':name' => $_POST['name'],
            ':sku' => $_POST['sku'],
            ':barcode' => $_POST['barcode'],
            ':cat' => $_POST['category_id'] ?: null,
            ':tax' => $_POST['tax_id'] ?: null,
            ':cost' => $_POST['cost_price'],
            ':sell' => $_POST['sell_price'],
            ':uom' => $_POST['uom']
        ]);
        $productId = $conn->lastInsertId();

        // 2. Initialize Stock in Main Warehouse (ID 1 for now, dynamic later)
        if ($_POST['initial_stock'] > 0) {
            $stmt = $conn->prepare("INSERT INTO inventory_stock (product_id, warehouse_id, quantity) VALUES (:pid, 1, :qty)");
            $stmt->execute([':pid' => $productId, ':qty' => $_POST['initial_stock']]);

            // Log Transaction
            $stmt = $conn->prepare("INSERT INTO inventory_transactions (warehouse_id, product_id, type, quantity) VALUES (1, :pid, 'Adjustment', :qty)");
            $stmt->execute([':pid' => $productId, ':qty' => $_POST['initial_stock']]);
        }

        $conn->commit();
        $message = "Product added successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch Products
$query = "
    SELECT p.*, c.name as category_name, t.rate as tax_rate, 
    (SELECT SUM(quantity) FROM inventory_stock WHERE product_id = p.id) as current_stock
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN taxes t ON p.tax_id = t.id
    WHERE p.company_id = :cid
";
$stmt = $conn->prepare($query);
$stmt->execute([':cid' => $_SESSION['company_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories & Taxes
$categories = $conn->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC); // Should filter by company
$taxes = $conn->query("SELECT * FROM taxes")->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Inventory Management</h2>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-box-open"></i> Add Product
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-info"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item Name</th>
                    <th>SKU / Barcode</th>
                    <th>Category</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">Retails</th>
                    <th class="text-center">Tax</th>
                    <th class="text-center">Stock</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($p['name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($p['uom']); ?></small>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($p['sku']); ?></div>
                            <small class="text-monospace text-muted"><?php echo htmlspecialchars($p['barcode']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                        <td class="text-end"><?php echo number_format($p['cost_price'], 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($p['sell_price'], 2); ?></td>
                        <td class="text-center"><?php echo $p['tax_rate'] ? $p['tax_rate'] . '%' : '-'; ?></td>
                        <td class="text-center">
                            <?php
                            $stock = $p['current_stock'] ?? 0;
                            $badge = $stock < 10 ? 'bg-danger' : 'bg-success';
                            echo "<span class='badge $badge'>$stock</span>";
                            ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Product Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="add_product" value="1">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">SKU (Unique)</label>
                        <input type="text" name="sku" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($categories as $c):
                                echo "<option value='{$c['id']}'>{$c['name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tax Rule</label>
                        <select name="tax_id" class="form-select">
                            <option value="">None (0%)</option>
                            <?php foreach ($taxes as $t):
                                echo "<option value='{$t['id']}'>{$t['name']} ({$t['rate']}%)</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit of Measure</label>
                        <select name="uom" class="form-select">
                            <option>Pcs</option>
                            <option>Kg</option>
                            <option>Litr</option>
                            <option>Box</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Cost Price</label>
                        <input type="number" step="0.01" name="cost_price" class="form-control" value="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Selling Price</label>
                        <input type="number" step="0.01" name="sell_price" class="form-control" value="0.00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Initial Stock</label>
                        <input type="number" step="0.01" name="initial_stock" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>