<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';

Session::checkLogin();
PermissionService::requirePermission('org_setup.org_management.create');

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_org'])) {
    $company_name = trim($_POST['company_name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $currency = $_POST['currency'];
    $fiscal_year_start = $_POST['fiscal_year_start'];

    if (empty($company_name)) {
        $error = "Company Name is required.";
    } else {
        try {
            $conn->beginTransaction();

            // 1. Create Company
            // Schema: companies (company_name, tax_number, currency, currency_symbol, address, phone, email, fiscal_year_start, is_active)
            $stmt = $conn->prepare("INSERT INTO companies (company_name, address, phone, email, currency, fiscal_year_start, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$company_name, $address, $phone, $email, $currency, $fiscal_year_start]);
            $newOrgId = $conn->lastInsertId();

            // 2. Assign Creator to this Org as Admin (Role ID 1)
            // organization_users (user_id, company_id, role_id, is_active)
            $stmtUser = $conn->prepare("INSERT INTO organization_users (user_id, company_id, role_id, is_active) VALUES (?, ?, 1, 1)");
            $stmtUser->execute([$_SESSION['user_id'], $newOrgId]);

            // 3. (Optional) Create Default Store?
            // "Main Store"
            // Generate a default Store Code
            $storeCode = 'STR-' . str_pad($newOrgId, 3, '0', STR_PAD_LEFT) . '-001';

            $stmtStore = $conn->prepare("INSERT INTO stores (company_id, store_name, store_code, address, phone, is_active) VALUES (?, 'Main Store', ?, ?, ?, 1)");
            $stmtStore->execute([$newOrgId, $storeCode, $address, $phone]);
            $newStoreId = $conn->lastInsertId();

            // Update the user's default store in this org? 
            // Update organization_users set store_id...
            $stmtUpdateUser = $conn->prepare("UPDATE organization_users SET store_id = ? WHERE user_id = ? AND company_id = ?");
            $stmtUpdateUser->execute([$newStoreId, $_SESSION['user_id'], $newOrgId]);

            $conn->commit();
            $message = "Organization created successfully! You are now an Admin of this new organization.";

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error creating organization: " . $e->getMessage();
        }
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="fas fa-building me-2"></i>Create New Organization</h2>
        <a href="org_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to List</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $message; ?>
            <div class="mt-2">
                <a href="select_organization.php" class="btn btn-sm btn-success">Switch to New Org</a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="create_org" value="1">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Company Name *</label>
                            <input type="text" name="company_name" class="form-control" required
                                placeholder="e.g. Acme Corp Retail">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="+123456789">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" placeholder="contact@company.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                placeholder="Street Address, City, Country"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="PKR">PKR (Pakistani Rupee)</option>
                                    <option value="USD">USD (US Dollar)</option>
                                    <option value="EUR">EUR (Euro)</option>
                                    <option value="GBP">GBP (British Pound)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fiscal Year Start</label>
                                <input type="date" name="fiscal_year_start" class="form-control"
                                    value="<?php echo date('Y-01-01'); ?>">
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i>Create Organization
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>