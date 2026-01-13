<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Fetch all Sales Orders (D365 Style Grid)
// This query joins the customer table to show the name instead of just an ID
$query = "SELECT so.*, c.name as customer_name 
          FROM sales_orders so 
          LEFT JOIN customers c ON so.customer_id = c.id 
          ORDER BY so.created_at DESC";
$orders = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">All Sales Orders</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Sales and marketing</a></li>
                    <li class="breadcrumb-item active">Sales orders</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            <a href="Sale_Order_Creation.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Sales Order
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr class="text-uppercase small fw-bold text-muted">
                            <th class="ps-3">Sales Order</th>
                            <th>Customer Name</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No sales orders found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary">
                                        <a href="#" class="text-decoration-none"><?php echo $o['order_number']; ?></a>
                                    </td>
                                    <td><?php echo htmlspecialchars($o['customer_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('m/d/Y', strtotime($o['order_date'])); ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = 'bg-secondary';
                                        if ($o['status'] == 'Confirmed')
                                            $badgeClass = 'bg-info';
                                        if ($o['status'] == 'Invoiced')
                                            $badgeClass = 'bg-success';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $o['status']; ?></span>
                                    </td>
                                    <td class="text-end pe-3 fw-bold">
                                        $<?php echo number_format($o['total_amount'], 2); ?>
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