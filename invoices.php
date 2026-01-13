<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Fetch all Invoices
$query = "SELECT i.*, c.name as customer_name, so.order_number 
          FROM invoices i 
          LEFT JOIN customers c ON i.customer_id = c.id 
          LEFT JOIN sales_orders so ON i.sales_order_id = so.id
          ORDER BY i.created_at DESC";
$invoices = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">All Invoices</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Sales and Marketing</a></li>
                    <li class="breadcrumb-item active">Invoices</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
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
                            <th class="ps-3">Invoice Number</th>
                            <th>Sales Order</th>
                            <th>Customer Name</th>
                            <th>Invoice Date</th>
                            <th>Status</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No invoices found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($invoices as $inv): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <a href="view_invoice.php?id=<?php echo $inv['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="view_sales_order.php?id=<?php echo $inv['sales_order_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($inv['order_number']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($inv['customer_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('m/d/Y', strtotime($inv['invoice_date'])); ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'bg-warning';
                                        if($inv['status'] == 'Paid') $badgeClass = 'bg-success';
                                        if($inv['status'] == 'Cancelled') $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($inv['status']); ?></span>
                                </td>
                                <td class="text-end fw-bold">
                                    $<?php echo number_format($inv['total_amount'], 2); ?>
                                </td>
                                <td class="text-center pe-3">
                                    <a href="view_invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <a href="print_invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                                        <i class="fas fa-print me-1"></i> Print
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