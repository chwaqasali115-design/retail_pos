<?php
// reports.php - Reports Dashboard
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';
Session::checkLogin();
PermissionService::requirePermission('reports.view');

require_once 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="fas fa-chart-bar me-2"></i>Reports Dashboard</h2>
        <p class="text-muted">Generate and view business reports.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Analysis Reports -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="icon-shape bg-primary text-white rounded-circle p-3 mb-3 d-inline-block">
                    <i class="fas fa-chart-line fa-2x"></i>
                </div>
                <h5 class="fw-bold">Sales Report</h5>
                <p class="text-muted small">View specific sales transactions, daily totals, and trends.</p>
                <?php if (PermissionService::hasPermission('reports.sales')): ?>
                    <a href="report_sales.php" class="btn btn-primary w-100">View Sales</a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Restricted</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="icon-shape bg-success text-white rounded-circle p-3 mb-3 d-inline-block">
                    <i class="fas fa-boxes fa-2x"></i>
                </div>
                <h5 class="fw-bold">Stock Report</h5>
                <p class="text-muted small">Current inventory levels and stock valuation.</p>
                <?php if (PermissionService::hasPermission('reports.stock')): ?>
                    <a href="report_stock.php" class="btn btn-success w-100">View Stock</a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Restricted</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="icon-shape bg-warning text-dark rounded-circle p-3 mb-3 d-inline-block">
                    <i class="fas fa-list-alt fa-2x"></i>
                </div>
                <h5 class="fw-bold">Detailed Sales</h5>
                <p class="text-muted small">Item-by-item breakdown of every sale transaction.</p>
                <?php if (PermissionService::hasPermission('reports.sales')): ?>
                    <a href="report_sales_detail.php" class="btn btn-warning w-100">View Detailed</a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Restricted</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="icon-shape bg-dark text-white rounded-circle p-3 mb-3 d-inline-block">
                    <i class="fas fa-calendar-day fa-2x"></i>
                </div>
                <h5 class="fw-bold">Daily Summary</h5>
                <p class="text-muted small">Daily sales totals and performance trends.</p>
                <?php if (PermissionService::hasPermission('reports.sales')): ?>
                    <a href="report_daily_summary.php" class="btn btn-dark w-100">View Daily</a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Restricted</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="icon-shape bg-secondary text-white rounded-circle p-3 mb-3 d-inline-block">
                    <i class="fas fa-credit-card fa-2x"></i>
                </div>
                <h5 class="fw-bold">Payment Methods</h5>
                <p class="text-muted small">Sales breakdown by Cash, Card, and other methods.</p>
                <?php if (PermissionService::hasPermission('reports.sales')): ?>
                    <a href="report_payment_summary.php" class="btn btn-secondary w-100">View Payments</a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Restricted</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="icon-shape bg-danger text-white rounded-circle p-3 mb-3 d-inline-block">
                    <i class="fas fa-truck fa-2x"></i>
                </div>
                <h5 class="fw-bold">Purchase Report</h5>
                <p class="text-muted small">Summary of inventory purchases and vendor costs.</p>
                <?php if (PermissionService::hasPermission('reports.view')): ?>
                    <a href="report_purchases.php" class="btn btn-danger w-100">View Purchases</a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Restricted</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="icon-shape bg-info text-white rounded-circle p-3 mb-3 d-inline-block">
                    <i class="fas fa-adjust fa-2x"></i>
                </div>
                <h5 class="fw-bold">Stock Adjustments</h5>
                <p class="text-muted small">Record of manual stock corrections and reasons.</p>
                <?php if (PermissionService::hasPermission('reports.stock')): ?>
                    <a href="report_stock_adjustments.php" class="btn btn-info w-100">View Adjustments</a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Restricted</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Accounting Shortcuts -->
    <div class="col-md-12 mt-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-bold">Financial Reports</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <a href="report_profit_loss.php"
                            class="d-flex align-items-center text-decoration-none text-dark p-3 border rounded hover-shadow">
                            <i class="fas fa-file-invoice-dollar text-success fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Profit & Loss</h6>
                                <small class="text-muted">Income Statement</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <a href="report_trial_balance.php"
                            class="d-flex align-items-center text-decoration-none text-dark p-3 border rounded hover-shadow">
                            <i class="fas fa-balance-scale text-primary fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Trial Balance</h6>
                                <small class="text-muted">Account Balances</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="report_ledger.php"
                            class="d-flex align-items-center text-decoration-none text-dark p-3 border rounded hover-shadow">
                            <i class="fas fa-book-open text-info fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">General Ledger</h6>
                                <small class="text-muted">Transaction Details</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>