<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT currency FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$companyData = $stmt->fetch(PDO::FETCH_ASSOC);

$currency = 'USD';
if ($companyData && isset($companyData['currency'])) {
    $currency = $companyData['currency'];
}

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

$store_id = $_SESSION['store_id'] ?? null;

if ($store_id) {
    // get warehouse for this store
    $wStmt = $conn->prepare("SELECT id FROM warehouses WHERE store_id = ? LIMIT 1");
    $wStmt->execute([$store_id]);
    $wh = $wStmt->fetch(PDO::FETCH_ASSOC);
    $warehouse_id = $wh['id'] ?? null;

    if ($warehouse_id) {
        $stmt = $conn->prepare("
            SELECT p.*, c.name as category_name, 
                   COALESCE(inv.quantity, 0) as stock_quantity 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN inventory_stock inv ON p.id = inv.product_id AND inv.warehouse_id = ?
            WHERE p.company_id = ? 
            ORDER BY p.name ASC
        ");
        $stmt->execute(array($warehouse_id, $_SESSION['company_id']));
    } else {
        // Fallback or empty if no warehouse found
        $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.company_id = ? ORDER BY p.name ASC");
        $stmt->execute(array($_SESSION['company_id']));
    }
} else {
    // Admin or Head Office view (shows global stock or default)
    $stmt = $conn->prepare("SELECT p.*, p.stock_quantity as stock_quantity, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.company_id = ? ORDER BY p.name ASC");
    $stmt->execute(array($_SESSION['company_id']));
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProducts = count($products);
$totalStockValue = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

foreach ($products as $product) {
    $stockQty = isset($product['stock_quantity']) ? $product['stock_quantity'] : 0;
    $costPrice = isset($product['cost_price']) ? $product['cost_price'] : 0;
    $reorderLevel = isset($product['reorder_level']) ? $product['reorder_level'] : 10;

    $totalStockValue += ($stockQty * $costPrice);

    if ($stockQty <= 0) {
        $outOfStockCount++;
    } elseif ($stockQty <= $reorderLevel) {
        $lowStockCount++;
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-warehouse me-2"></i>Inventory Overview</h2>
        <a href="products.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Product
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Products</h6>
                            <h2 class="mb-0"><?php echo $totalProducts; ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-box fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Stock Value</h6>
                            <h2 class="mb-0"><?php echo $symbol . number_format($totalStockValue, 2); ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Low Stock Items</h6>
                            <h2 class="mb-0"><?php echo $lowStockCount; ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Out of Stock</h6>
                            <h2 class="mb-0"><?php echo $outOfStockCount; ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Stock Levels</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Stock Qty</th>
                            <th>Stock Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
                                    No products found. <a href="products.php">Add your first product</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $stockQty = isset($product['stock_quantity']) ? $product['stock_quantity'] : 0;
                                $reorderLevel = isset($product['reorder_level']) ? $product['reorder_level'] : 10;
                                $costPrice = isset($product['cost_price']) ? $product['cost_price'] : 0;
                                $sellingPrice = isset($product['price']) ? $product['price'] : 0;
                                $categoryName = isset($product['category_name']) ? $product['category_name'] : 'Uncategorized';
                                $stockValue = $stockQty * $costPrice;

                                $stockClass = 'bg-success';
                                $statusText = 'In Stock';
                                if ($stockQty <= 0) {
                                    $stockClass = 'bg-danger';
                                    $statusText = 'Out of Stock';
                                } elseif ($stockQty <= $reorderLevel) {
                                    $stockClass = 'bg-warning';
                                    $statusText = 'Low Stock';
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['sku']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($categoryName); ?></td>
                                    <td><?php echo $symbol . number_format($costPrice, 2); ?></td>
                                    <td><?php echo $symbol . number_format($sellingPrice, 2); ?></td>
                                    <td><span
                                            class="badge <?php echo $stockClass; ?> fs-6"><?php echo number_format($stockQty); ?></span>
                                    </td>
                                    <td><?php echo $symbol . number_format($stockValue, 2); ?></td>
                                    <td><span class="badge <?php echo $stockClass; ?>"><?php echo $statusText; ?></span></td>
                                    <td>
                                        <a href="stock_adjustment.php?product_id=<?php echo $product['id']; ?>"
                                            class="btn btn-sm btn-info" title="Adjust Stock">
                                            <i class="fas fa-sliders-h"></i>
                                        </a>
                                        <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning"
                                            title="Edit Product">
                                            <i class="fas fa-edit"></i>
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