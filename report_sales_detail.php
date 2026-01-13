<?php
// report_sales_detail.php - Detailed Sales Report (Item Level)
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

// Fetch Detailed Sales Data
$query = "
    SELECT 
        s.invoice_no, 
        s.sale_date, 
        c.name as customer_name, 
        p.name as product_name,
        si.quantity,
        si.unit_price,
        si.total as line_total,
        s.status
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    ORDER BY s.sale_date DESC, s.id DESC
";

$stmt = $conn->prepare($query);
$stmt->execute([$startDate, $endDate]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold"><i class="fas fa-list-alt me-2"></i>Detailed Sales Report</h2>
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
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($details)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">No data found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($details as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M d, Y H:i', strtotime($row['sale_date'])); ?>
                                    </td>
                                    <td><span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($row['invoice_no']); ?>
                                        </span></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo number_format($row['quantity']); ?>
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($row['unit_price'], 2); ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php echo number_format($row['line_total'], 2); ?>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php echo ($row['status'] == 'Completed') ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
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