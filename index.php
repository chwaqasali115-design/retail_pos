<?php
require_once 'config/config.php';
require_once 'core/Session.php';
require_once 'core/PermissionService.php';
Session::checkLogin();

// Fetch dashboard statistics
require_once 'core/Database.php';
$db = new Database();
$conn = $db->getConnection();

// Today's Sales
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COALESCE(SUM(grand_total), 0) as total FROM sales WHERE DATE(sale_date) = ? AND status = 'Completed'");
$stmt->execute([$today]);
$todaySales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total Orders Today
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE DATE(sale_date) = ?");
$stmt->execute([$today]);
$orderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Low Stock Items (below reorder level)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND is_active = 1 AND company_id = ?");
$stmt->execute([$_SESSION['company_id']]);
$lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Customer Count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = ?");
$stmt->execute([$_SESSION['company_id']]);
$customerCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent Transactions
$stmt = $conn->prepare("SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, s.status, c.name as customer_name 
                        FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.id 
                        ORDER BY s.sale_date DESC LIMIT 10");
$stmt->execute();
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold">Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo $_SESSION['username']; ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card p-3 stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase mb-1">Today's Sales</h6>
                    <h3 class="fw-bold mb-0">PKR <?php echo number_format($todaySales, 2); ?></h3>
                </div>
                <div class="icon-shape bg-primary text-white rounded-circle p-3">
                    <i class="fas fa-shopping-bag"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 stat-card success">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase mb-1">Total Orders</h6>
                    <h3 class="fw-bold mb-0"><?php echo number_format($orderCount); ?></h3>
                </div>
                <div class="icon-shape bg-success text-white rounded-circle p-3">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 stat-card <?php echo $lowStock > 0 ? '' : 'success'; ?>">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase mb-1">Low Stock</h6>
                    <h3 class="fw-bold mb-0 <?php echo $lowStock > 0 ? 'text-warning' : ''; ?>">
                        <?php echo number_format($lowStock); ?></h3>
                </div>
                <div class="icon-shape bg-warning text-white rounded-circle p-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 stat-card success">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase mb-1">Customers</h6>
                    <h3 class="fw-bold mb-0"><?php echo number_format($customerCount); ?></h3>
                </div>
                <div class="icon-shape bg-info text-white rounded-circle p-3">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Transactions</h5>
                <div class="input-group" style="max-width:300px;">
                    <input type="text" id="dashboardSearchInvoice" class="form-control form-control-sm"
                        placeholder="Search Invoice (e.g. INV-...)">
                    <button class="btn btn-sm btn-primary" onclick="searchAndReprint()">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSales)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No transactions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><span
                                            class="badge bg-primary"><?php echo htmlspecialchars($sale['invoice_no']); ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                    <td class="fw-bold">PKR <?php echo number_format($sale['grand_total'], 2); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'bg-success';
                                        if ($sale['status'] == 'Void')
                                            $statusClass = 'bg-danger';
                                        if ($sale['status'] == 'Returned')
                                            $statusClass = 'bg-warning';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $sale['status']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="reprintInvoice(<?php echo $sale['id']; ?>)" title="Reprint Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="pos.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-cash-register me-2"></i>Open POS Terminal
                    </a>
                    <a href="products.php" class="btn btn-outline-primary">
                        <i class="fas fa-box me-2"></i>Manage Products
                    </a>
                    <a href="purchase_create.php" class="btn btn-outline-secondary">
                        <i class="fas fa-truck me-2"></i>New Purchase Order
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>

<script>
    function reprintInvoice(saleId) {
        if (!saleId) return;
        window.open('print_receipt.php?id=' + saleId, '_blank', 'width=350,height=600');
    }

    function searchAndReprint() {
        const inv = document.getElementById('dashboardSearchInvoice').value.trim();
        if (!inv) {
            alert("Please enter an invoice number");
            return;
        }

        fetch('pos_api.php?action=get_sale&invoice=' + encodeURIComponent(inv))
            .then(res => {
                if (!res.ok) throw new Error('Invoice not found');
                return res.json();
            })
            .then(data => {
                if (data.sale && data.sale.id) {
                    reprintInvoice(data.sale.id);
                } else {
                    alert("Invoice data missing");
                }
            })
            .catch(err => {
                alert(err.message);
            });
    }

    // Bind Enter key
    const searchInput = document.getElementById('dashboardSearchInvoice');
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') searchAndReprint();
        });
    }
</script>