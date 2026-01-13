<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

$stmt = $conn->prepare("SELECT currency FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$companyData = $stmt->fetch(PDO::FETCH_ASSOC);

$currency = 'USD';
if ($companyData && isset($companyData['currency'])) {
    $currency = $companyData['currency'];
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
$symbols['PKR'] = 'Rs';
$symbols['BDT'] = 'Tk';
$symbols['MYR'] = 'RM';
$symbols['SGD'] = 'S$';

$symbol = '$';
if (isset($symbols[$currency])) {
    $symbol = $symbols[$currency];
}

$catStmt = $conn->prepare("SELECT * FROM categories WHERE company_id = ?");
$catStmt->execute(array($_SESSION['company_id']));
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $barcode = trim($_POST['barcode']);
    $category_id = null;
    if (!empty($_POST['category_id'])) {
        $category_id = $_POST['category_id'];
    }
    $cost_price = floatval($_POST['cost_price']);
    $selling_price = floatval($_POST['selling_price']);
    $tax_rate = floatval($_POST['tax_rate']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $reorder_level = intval($_POST['reorder_level']);
    $description = trim($_POST['description']);
    $is_tax_inclusive = isset($_POST['is_tax_inclusive']) ? 1 : 0;

    try {
        $stmt = $conn->prepare("INSERT INTO products (company_id, name, sku, barcode, category_id, cost_price, price, tax_rate, stock_quantity, reorder_level, description, is_tax_inclusive, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute(array($_SESSION['company_id'], $name, $sku, $barcode, $category_id, $cost_price, $selling_price, $tax_rate, $stock_quantity, $reorder_level, $description, $is_tax_inclusive));
        $message = "Product added successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_id = $_POST['product_id'];
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $barcode = trim($_POST['barcode']);
    $category_id = null;
    if (!empty($_POST['category_id'])) {
        $category_id = $_POST['category_id'];
    }
    $cost_price = floatval($_POST['cost_price']);
    $selling_price = floatval($_POST['selling_price']);
    $tax_rate = floatval($_POST['tax_rate']);
    $reorder_level = intval($_POST['reorder_level']);
    $description = trim($_POST['description']);
    $is_active = intval($_POST['is_active']);
    $is_tax_inclusive = isset($_POST['is_tax_inclusive']) ? 1 : 0;

    try {
        $stmt = $conn->prepare("UPDATE products SET name = ?, sku = ?, barcode = ?, category_id = ?, cost_price = ?, price = ?, tax_rate = ?, reorder_level = ?, description = ?, is_active = ?, is_tax_inclusive = ? WHERE id = ? AND company_id = ?");
        $stmt->execute(array($name, $sku, $barcode, $category_id, $cost_price, $selling_price, $tax_rate, $reorder_level, $description, $is_active, $is_tax_inclusive, $product_id, $_SESSION['company_id']));
        $message = "Product updated successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND company_id = ?");
        $stmt->execute(array($_GET['delete'], $_SESSION['company_id']));
        $message = "Product deleted successfully!";
    } catch (PDOException $e) {
        $error = "Cannot delete product. It may be used in orders.";
    }
}

$stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.company_id = ? ORDER BY p.name ASC");
$stmt->execute(array($_SESSION['company_id']));
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Product Management</h2>
        <div>
            <a href="product_import.php" class="btn btn-success me-2">
                <i class="fas fa-file-import me-2"></i>Import
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus me-2"></i>Add Product
            </button>
        </div>
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

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>SKU / Barcode</th>
                            <th>Category</th>
                            <th>Cost</th>
                            <th>Retail Price</th>
                            <th>Tax %</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No products found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $stockClass = 'bg-success';
                                if ($product['stock_quantity'] <= 0) {
                                    $stockClass = 'bg-danger';
                                } elseif ($product['stock_quantity'] <= $product['reorder_level']) {
                                    $stockClass = 'bg-warning';
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['name'] ?? ''); ?></strong></td>
                                    <td>
                                        <span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($product['sku'] ?? ''); ?></span>
                                        <?php if ($product['barcode']): ?>
                                            <br><small
                                                class="text-muted"><?php echo htmlspecialchars($product['barcode'] ?? ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></td>
                                    <td><?php echo $symbol . number_format($product['cost_price'], 2); ?></td>
                                    <td><?php echo $symbol . number_format($product['price'], 2); ?></td>
                                    <td><?php echo number_format($product['tax_rate'], 2); ?>%</td>
                                    <td><span
                                            class="badge <?php echo $stockClass; ?>"><?php echo number_format($product['stock_quantity']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($product['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning edit-product-btn"
                                            data-id="<?php echo $product['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['name'] ?? ''); ?>"
                                            data-sku="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>"
                                            data-barcode="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>"
                                            data-category="<?php echo $product['category_id']; ?>"
                                            data-cost="<?php echo $product['cost_price']; ?>"
                                            data-price="<?php echo $product['price']; ?>"
                                            data-tax="<?php echo $product['tax_rate']; ?>"
                                            data-reorder="<?php echo $product['reorder_level']; ?>"
                                            data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                                            data-active="<?php echo $product['is_active']; ?>"
                                            data-taxinclusive="<?php echo $product['is_tax_inclusive'] ?? 0; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editProductModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this product?')">
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

<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="add_product" value="1">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">SKU *</label>
                        <input type="text" name="sku" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Cost Price *</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo $symbol; ?></span>
                            <input type="number" name="cost_price" class="form-control" step="0.01" required
                                value="0.00">
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Selling Price *</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo $symbol; ?></span>
                            <input type="number" name="selling_price" class="form-control" step="0.01" required
                                value="0.00">
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" class="form-control" step="0.01" value="0.00">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Opening Stock</label>
                        <input type="number" name="stock_quantity" class="form-control" value="0">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" value="10">
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_tax_inclusive"
                                id="add_is_tax_inclusive">
                            <label class="form-check-label" for="add_is_tax_inclusive">
                                <strong>Price includes Sales Tax</strong>
                                <small class="text-muted d-block">Check if selling price already includes tax</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="update_product" value="1">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">SKU *</label>
                        <input type="text" name="sku" id="edit_sku" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="edit_barcode" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="edit_category" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" id="edit_description" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Cost Price</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo $symbol; ?></span>
                            <input type="number" name="cost_price" id="edit_cost" class="form-control" step="0.01"
                                required>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Selling Price</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo $symbol; ?></span>
                            <input type="number" name="selling_price" id="edit_price" class="form-control" step="0.01"
                                required>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" id="edit_tax" class="form-control" step="0.01">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" id="edit_reorder" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status</label>
                        <select name="is_active" id="edit_active" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_tax_inclusive"
                                id="edit_is_tax_inclusive">
                            <label class="form-check-label" for="edit_is_tax_inclusive">
                                <strong>Price includes Sales Tax</strong>
                                <small class="text-muted d-block">Check if selling price already includes tax</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editButtons = document.querySelectorAll('.edit-product-btn');
        for (var i = 0; i < editButtons.length; i++) {
            editButtons[i].addEventListener('click', function () {
                document.getElementById('edit_product_id').value = this.getAttribute('data-id');
                document.getElementById('edit_name').value = this.getAttribute('data-name');
                document.getElementById('edit_sku').value = this.getAttribute('data-sku');
                document.getElementById('edit_barcode').value = this.getAttribute('data-barcode');
                document.getElementById('edit_category').value = this.getAttribute('data-category');
                document.getElementById('edit_cost').value = this.getAttribute('data-cost');
                document.getElementById('edit_price').value = this.getAttribute('data-price');
                document.getElementById('edit_tax').value = this.getAttribute('data-tax');
                document.getElementById('edit_reorder').value = this.getAttribute('data-reorder');
                document.getElementById('edit_description').value = this.getAttribute('data-description');
                document.getElementById('edit_active').value = this.getAttribute('data-active');
                document.getElementById('edit_is_tax_inclusive').checked = this.getAttribute('data-taxinclusive') == '1';
            });
        }
    });
</script>

<?php require_once 'templates/footer.php'; ?>