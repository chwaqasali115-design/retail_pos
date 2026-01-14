<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'Retail POS'; ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="public/assets/style.css" rel="stylesheet">
    <style>
        /* Submenu Styling */
        .nav-link[data-bs-toggle="collapse"] {
            position: relative;
        }

        .nav-link[data-bs-toggle="collapse"]::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 10px;
            position: absolute;
            right: 15px;
            transition: transform 0.3s ease;
        }

        .nav-link[data-bs-toggle="collapse"][aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        .submenu {
            background-color: rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            margin: 5px 0;
        }

        .submenu .nav-link {
            padding-left: 40px !important;
            font-size: 0.9rem;
        }

        .submenu .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="d-flex" id="wrapper">
            <!-- Sidebar -->
            <div class="sidebar p-3" id="sidebar-wrapper" style="width: 250px;">
                <div class="sidebar-heading text-center py-4 fs-4 fw-bold text-uppercase border-bottom">
                    <i class="fas fa-cash-register me-2"></i> Retail Flow
                </div>
                <div class="list-group list-group-flush my-3">
                    <a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>

                    <!--<small class="text-uppercase text-muted mt-3 mb-1 px-3">Operations</small>-->
                    <?php if (PermissionService::hasPermission('sales.pos.access')): ?>
                        <a href="pos.php" class="nav-link"><i class="fas fa-shopping-cart me-2"></i> POS Terminal</a>
                    <?php endif; ?>

                    <!-- Inventory Module with Submenu -->
                    <?php if (PermissionService::hasPermission('inventory.products.view')): ?>
                        <a class="nav-link" data-bs-toggle="collapse" href="#inventoryMenu" role="button" aria-expanded="false"
                            aria-controls="inventoryMenu">
                            <i class="fas fa-boxes me-2"></i> Inventory
                        </a>
                        <div class="collapse submenu" id="inventoryMenu">
                            <a href="inventory.php" class="nav-link"><i class="fas fa-warehouse me-2"></i> Stock Overview</a>
                            <a href="products.php" class="nav-link"><i class="fas fa-box me-2"></i> Products</a>
                            <a href="categories.php" class="nav-link"><i class="fas fa-tags me-2"></i> Categories</a>
                            <?php if (PermissionService::hasPermission('inventory.products.edit')): ?>
                                <a href="stock_adjustment.php" class="nav-link"><i class="fas fa-sliders-h me-2"></i> Stock
                                    Adjustment</a>
                                <a href="barcode_generator.php" class="nav-link"><i class="fas fa-barcode me-2"></i> Barcode
                                    Generator</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Procurement Module with Submenu -->
                    <?php if (PermissionService::hasPermission('purchases.order.view')): ?>
                        <a class="nav-link" data-bs-toggle="collapse" href="#procurementMenu" role="button"
                            aria-expanded="false" aria-controls="procurementMenu">
                            <i class="fas fa-truck-loading me-2"></i> Procurement
                        </a>
                        <div class="collapse submenu" id="procurementMenu">
                            <a href="purchases.php" class="nav-link"><i class="fas fa-tachometer-alt me-2"></i> Purchase
                                Overview</a>
                            <?php if (PermissionService::hasPermission('purchases.order.create')): ?>
                                <a href="purchase_create.php" class="nav-link"><i class="fas fa-plus me-2"></i> New Purchase
                                    Order</a>
                            <?php endif; ?>
                            <a href="vendors.php" class="nav-link"><i class="fas fa-store me-2"></i> All Vendors</a>
                            <?php if (PermissionService::hasPermission('purchases.vendor_invoice.create')): ?>
                                <a href="vendor_create.php" class="nav-link"><i class="fas fa-user-plus me-2"></i> Add Vendor</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!--<small class="text-uppercase text-muted mt-3 mb-1 px-3">Sales</small>-->
                    <!-- Sales and Marketing with Submenu -->
                    <?php if (PermissionService::hasPermission('sales.invoices.view')): ?>
                        <a class="nav-link" data-bs-toggle="collapse" href="#salesMarketingMenu" role="button"
                            aria-expanded="false" aria-controls="salesMarketingMenu">
                            <i class="fas fa-chart-line me-2"></i> Sales and Marketing
                        </a>
                        <div class="collapse submenu" id="salesMarketingMenu">
                            <a href="sales_orders.php" class="nav-link"><i class="fas fa-list me-2"></i> All Sales Orders</a>
                            <?php if (PermissionService::hasPermission('sales.invoices.create')): ?>
                                <a href="Sale_Order_Creation.php" class="nav-link"><i class="fas fa-plus me-2"></i> New Sales
                                    Order</a>
                            <?php endif; ?>
                            <a href="invoices.php" class="nav-link"><i class="fas fa-file-invoice me-2"></i> All Invoices</a>
                        </div>
                    <?php endif; ?>

                    <!--<small class="text-uppercase text-muted mt-3 mb-1 px-3">Finance</small>-->
                    <!-- Accounting Module with Submenu -->
                    <?php if (PermissionService::hasPermission('accounting.transactions.view')): ?>
                        <a class="nav-link" data-bs-toggle="collapse" href="#accountingMenu" role="button" aria-expanded="false"
                            aria-controls="accountingMenu">
                            <i class="fas fa-calculator me-2"></i> Accounting
                        </a>
                        <div class="collapse submenu" id="accountingMenu">
                            <a href="accounting.php" class="nav-link"><i class="fas fa-chart-pie me-2"></i> Overview</a>
                            <a href="customers.php" class="nav-link"><i class="fas fa-users me-2"></i> Customers</a>
                            <a href="customer_create.php" class="nav-link"><i class="fas fa-user-plus me-2"></i> Add
                                Customer</a>
                            <a href="vendors.php" class="nav-link"><i class="fas fa-store me-2"></i> Vendors</a>
                            <a href="vendor_create.php" class="nav-link"><i class="fas fa-user-tie me-2"></i> Add Vendor</a>
                        </div>
                    <?php endif; ?>

                    <!-- Reports Module -->
                    <?php if (PermissionService::hasPermission('reports.view')): ?>
                        <a href="reports.php" class="nav-link"><i class="fas fa-chart-bar me-2"></i> Reports</a>
                    <?php endif; ?>

                    <!--<small class="text-uppercase text-muted mt-3 mb-1 px-3">Admin</small>-->
                    <!-- Organization Module with Submenu -->
                    <?php if (PermissionService::hasPermission('admin.settings.view')): ?>
                        <a class="nav-link" data-bs-toggle="collapse" href="#orgMenu" role="button" aria-expanded="false"
                            aria-controls="orgMenu">
                            <i class="fas fa-sitemap me-2"></i> Organization
                        </a>
                        <div class="collapse submenu" id="orgMenu">
                            <a href="org_profile.php" class="nav-link"><i class="fas fa-building me-2"></i> Company Profile</a>
                            <a href="fbr_transactions.php" class="nav-link"><i class="fas fa-file-invoice-dollar me-2"></i> FBR
                                History</a>
                            <a href="org_stores.php" class="nav-link"><i class="fas fa-store me-2"></i> Stores</a>
                            <a href="org_terminals.php" class="nav-link"><i class="fas fa-desktop me-2"></i> Terminals</a>
                        </div>
                    <?php endif; ?>

                    <?php if (PermissionService::hasPermission('admin.users.view')): ?>
                        <a href="users.php" class="nav-link"><i class="fas fa-users-cog me-2"></i> Users</a>
                    <?php endif; ?>

                    <?php if (PermissionService::hasPermission('admin.users.manage_roles')): ?>
                        <a href="permissions.php" class="nav-link"><i class="fas fa-shield-alt me-2"></i> Permissions</a>
                    <?php endif; ?>

                    <a href="logout.php" class="nav-link text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i>
                        Logout</a>
                </div>
            </div>
            <!-- /#sidebar-wrapper -->

            <!-- Page Content -->
            <div id="page-content-wrapper" class="w-100">
                <nav class="navbar navbar-expand-lg navbar-light bg-light px-4 py-3 border-bottom">
                    <button class="btn btn-primary" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle fw-bold text-primary" href="#" id="orgDropdown"
                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($_SESSION['company_name'] ?? 'Organization'); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="orgDropdown">
                                    <li><a class="dropdown-item" href="select_organization.php"><i
                                                class="fas fa-exchange-alt me-2"></i> Switch Organization</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <span
                                    class="nav-link fw-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                            </li>
                        </ul>
                    </div>
                </nav>
                <div class="container-fluid p-4">
                <?php endif; ?>