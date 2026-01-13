<?php
// report_stock.php - Inventory / Stock Report
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';
require_once 'core/Database.php';

Session::checkLogin();
PermissionService::requirePermission('reports.stock');

$db = new Database();
$conn = $db->getConnection();

// Fetch Categories for filter
$catStmt = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryId = $_GET['category_id'] ?? '';
$stockStatus = $_GET['stock_status'] ?? ''; // 'low', 'out', 'all'

$query = "
    SELECT 
        p.id, 
        p.name, 
        p.sku, 
        c.name as category_name, 
        p.stock_quantity, 
        p.reorder_level, 
        p.price, 
        p.cost_price
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
";
$params = [];

if ($categoryId) {
    $query .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if ($stockStatus === 'low') {
    $query .= " AND p.stock_quantity <= p.reorder_level AND p.stock_quantity > 0";
} elseif ($stockStatus === 'out') {
    $query .= " AND p.stock_quantity <= 0";
}

$query .= " ORDER BY p.name ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals
$totalItems = 0;
$totalValue = 0;
$totalCost = 0;

foreach ($products as $p) {
    $qty = $p['stock_quantity'];
    if ($qty < 0)
        $qty = 0; // Don't value negative stock
    $totalItems += $qty;
    $totalValue += ($qty * $p['price']);
    $totalCost += ($qty * $p['cost_price']);
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold"><i class="fas fa-boxes me-2"></i>Stock Report</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print</button>
            <a href="reports.php" class="btn btn-secondary ms-2">Back</a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($categoryId == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Stock Status</label>
                    <select name="stock_status" class="form-select">
                        <option value="">All Items</option>
                        <option value="low" <?php echo ($stockStatus == 'low') ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out" <?php echo ($stockStatus == 'out') ? 'selected' : ''; ?>>Out of Stock
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-info">
                <h6 class="text-muted text-uppercase">Total Items in Stock</h6>
                <h3 class="fw-bold">
                    <?php echo number_format($totalItems); ?>
                </h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-success">
                <h6 class="text-muted text-uppercase">Total Retail Value</h6>
                <h3 class="fw-bold">PKR
                    <?php echo number_format($totalValue, 2); ?>
                </h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-secondary">
                <h6 class="text-muted text-uppercase">Total Cost Value</h6>
                <h3 class="fw-bold">PKR
                    <?php echo number_format($totalCost, 2); ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Stock</th>
                        <th class="text-end">Value (Retail)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">No products found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><small class="text-muted">
                                        <?php echo htmlspecialchars($p['sku']); ?>
                                    </small></td>
                                <td>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </td>
                                <td><span class="badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($p['category_name'] ?? '-'); ?>
                                    </span></td>
                                <td class="text-end">
                                    <?php echo number_format($p['cost_price'], 2); ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($p['price'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $class = 'bg-success';
                                    if ($p['stock_quantity'] <= 0)
                                        $class = 'bg-danger';
                                    elseif ($p['stock_quantity'] <= $p['reorder_level'])
                                        $class = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $class; ?> rounded-pill">
                                        <?php echo number_format($p['stock_quantity']); ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo number_format(max(0, $p['stock_quantity']) * $p['price'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>