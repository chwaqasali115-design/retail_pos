<?php
// report_customers.php - Customer Performance Report
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';
require_once 'core/Database.php';

Session::checkLogin();
PermissionService::requirePermission('reports.customers');

$db = new Database();
$conn = $db->getConnection();

$limit = $_GET['limit'] ?? 20;

// Query: Top customers by total spend
$query = "
    SELECT 
        c.id, 
        c.name, 
        c.phone, 
        c.email, 
        COUNT(s.id) as total_transactions, 
        SUM(case when s.status != 'Void' and s.status != 'Returned' then s.grand_total else 0 end) as total_spent,
        MAX(s.sale_date) as last_purchase
    FROM customers c
    LEFT JOIN sales s ON c.id = s.customer_id
    GROUP BY c.id
    ORDER BY total_spent DESC
    LIMIT ?
";

$stmt = $conn->prepare($query);
$stmt->bindParam(1, $limit, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold"><i class="fas fa-users me-2"></i>Customer Insights</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print</button>
            <a href="reports.php" class="btn btn-secondary ms-2">Back</a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Top Customers by Revenue</h5>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Customer Name</th>
                            <th>Contact</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Total Spent</th>
                            <th>Last Purchase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No data available.</td>
                            </tr>
                        <?php else: ?>
                            <?php $rank = 1;
                            foreach ($customers as $c): ?>
                                <tr>
                                    <td><span class="badge bg-secondary rounded-pill">
                                            <?php echo $rank++; ?>
                                        </span></td>
                                    <td class="fw-bold">
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </td>
                                    <td>
                                        <div class="small text-muted"><i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($c['phone']); ?>
                                        </div>
                                        <?php if ($c['email']): ?>
                                            <div class="small text-muted"><i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($c['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo number_format($c['total_transactions']); ?>
                                    </td>
                                    <td class="text-end fw-bold text-success">PKR
                                        <?php echo number_format($c['total_spent'], 2); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($c['last_purchase']) {
                                            echo date('M d, Y', strtotime($c['last_purchase']));
                                            $days = floor((time() - strtotime($c['last_purchase'])) / (60 * 60 * 24));
                                            echo " <small class='text-muted'>({$days} days ago)</small>";
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
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