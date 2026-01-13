<?php
// report_sales.php - Sales Report
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';
require_once 'core/Database.php';

Session::checkLogin();
PermissionService::requirePermission('reports.sales');

$db = new Database();
$conn = $db->getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$customerId = $_GET['customer_id'] ?? '';

// Fetch Customers
$custStmt = $conn->query("SELECT id, name FROM customers ORDER BY name ASC");
$customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Sales Data
$query = "
    SELECT 
        s.id, 
        s.invoice_no, 
        s.sale_date, 
        c.name as customer_name, 
        s.grand_total, 
        s.payment_method,
        s.status
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if ($customerId) {
    $query .= " AND s.customer_id = ?";
    $params[] = $customerId;
}

$query .= " ORDER BY s.sale_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSales = 0;
foreach ($sales as $sale) {
    if ($sale['status'] !== 'Void' && $sale['status'] !== 'Returned') {
        $totalSales += $sale['grand_total'];
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold"><i class="fas fa-chart-line me-2"></i>Sales Report</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print</button>
            <a href="reports.php" class="btn btn-secondary ms-2">Back</a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($customerId == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white p-3">
                <h5>Total Sales Period</h5>
                <h3>PKR <?php echo number_format($totalSales, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white p-3">
                <h5>Transaction Count</h5>
                <h3><?php echo count($sales); ?></h3>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Transactions</h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No sales found for the selected period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($sale['invoice_no']); ?></span></td>
                                    <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                    <td><?php echo ucfirst($sale['payment_method']); ?></td>
                                    <td>
                                        <?php 
                                        $badge = 'bg-success';
                                        if ($sale['status'] === 'Void') $badge = 'bg-danger';
                                        if ($sale['status'] === 'Returned') $badge = 'bg-warning';
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo $sale['status']; ?></span>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php 
                                            // Strike through if void
                                            if ($sale['status'] === 'Void') echo '<span class="text-decoration-line-through text-muted">';
                                            echo number_format($sale['grand_total'], 2); 
                                            if ($sale['status'] === 'Void') echo '</span>';
                                        ?>
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
