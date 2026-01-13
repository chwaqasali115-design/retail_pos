<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$message = '';

$currencies = array(
    'USD' => 'US Dollar ($)',
    'EUR' => 'Euro (€)',
    'GBP' => 'British Pound (£)',
    'INR' => 'Indian Rupee (₹)',
    'AED' => 'UAE Dirham (د.إ)',
    'SAR' => 'Saudi Riyal (﷼)',
    'CAD' => 'Canadian Dollar (C$)',
    'AUD' => 'Australian Dollar (A$)',
    'JPY' => 'Japanese Yen (¥)',
    'CNY' => 'Chinese Yuan (¥)',
    'PKR' => 'Pakistani Rupee (₨)',
    'BDT' => 'Bangladeshi Taka (৳)',
    'MYR' => 'Malaysian Ringgit (RM)',
    'SGD' => 'Singapore Dollar (S$)',
    'ZAR' => 'South African Rand (R)',
    'NGN' => 'Nigerian Naira (₦)',
    'KES' => 'Kenyan Shilling (KSh)',
    'GHS' => 'Ghanaian Cedi (₵)',
    'EGP' => 'Egyptian Pound (E£)',
    'BRL' => 'Brazilian Real (R$)',
    'MXN' => 'Mexican Peso (MX$)'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company'])) {
    try {
        $stmt = $conn->prepare("UPDATE companies SET company_name = ?, tax_number = ?, address = ?, phone = ?, email = ?, currency = ? WHERE id = ?");
        $stmt->execute(array(
            $_POST['company_name'],
            $_POST['tax_number'],
            $_POST['address'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['currency'],
            $_SESSION['company_id']
        ));
        $_SESSION['company_currency'] = $_POST['currency'];
        $message = "Company profile updated successfully!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_store'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO stores (company_id, store_name, store_code, address, phone, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute(array(
            $_SESSION['company_id'],
            $_POST['store_name'],
            $_POST['store_code'],
            $_POST['address'],
            $_POST['phone']
        ));
        $message = "Store added successfully!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_store'])) {
    try {
        $stmt = $conn->prepare("UPDATE stores SET store_name = ?, store_code = ?, phone = ?, address = ?, is_active = ? WHERE id = ? AND company_id = ?");
        $stmt->execute(array(
            $_POST['store_name'],
            $_POST['store_code'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['is_active'],
            $_POST['store_id'],
            $_SESSION['company_id']
        ));
        $message = "Store updated successfully!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$company = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM stores WHERE company_id = ?");
$stmt->execute(array($_SESSION['company_id']));
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2 class="fw-bold">Organization Setup</h2>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0">
            <h5 class="card-title fw-bold border-bottom pb-2">Company Profile</h5>
            <div class="mb-3">
                <label class="form-label text-muted small">Company Name</label>
                <input type="text" class="form-control bg-light"
                    value="<?php echo htmlspecialchars($company['company_name']); ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Tax Number</label>
                <input type="text" class="form-control bg-light"
                    value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Phone</label>
                <input type="text" class="form-control bg-light"
                    value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Email</label>
                <input type="email" class="form-control bg-light"
                    value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Address</label>
                <textarea class="form-control bg-light" disabled
                    rows="2"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Currency</label>
                <?php
                $currentCurrency = isset($company['currency']) ? $company['currency'] : 'USD';
                $currencyDisplay = isset($currencies[$currentCurrency]) ? $currencies[$currentCurrency] : 'US Dollar ($)';
                ?>
                <input type="text" class="form-control bg-light"
                    value="<?php echo htmlspecialchars($currencyDisplay); ?>" disabled>
            </div>
            <button class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#editCompanyModal">
                <i class="fas fa-edit"></i> Edit Profile (Admin Only)
            </button>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card p-3 shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title fw-bold mb-0">Stores / Branches</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStoreModal">
                    <i class="fas fa-plus"></i> Add Store
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Store Name</th>
                            <th>Code</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stores)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No stores found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stores as $store): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($store['store_name']); ?></td>
                                    <td><span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($store['store_code']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($store['phone']); ?></td>
                                    <td>
                                        <?php if ($store['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-warning edit-store-btn"
                                            data-id="<?php echo $store['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($store['store_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($store['store_code']); ?>"
                                            data-phone="<?php echo htmlspecialchars($store['phone']); ?>"
                                            data-address="<?php echo htmlspecialchars($store['address']); ?>"
                                            data-active="<?php echo $store['is_active']; ?>" data-bs-toggle="modal"
                                            data-bs-target="#editStoreModal">
                                            <i class="fas fa-edit"></i> Edit
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
</div>

<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Company Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="update_company" value="1">
                <div class="mb-3">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control"
                        value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tax Number</label>
                    <input type="text" name="tax_number" class="form-control"
                        value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                        value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                        value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"
                        rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Currency</label>
                    <select name="currency" class="form-select" required>
                        <?php foreach ($currencies as $code => $name): ?>
                            <?php $selected = ($currentCurrency == $code) ? 'selected' : ''; ?>
                            <option value="<?php echo $code; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">This currency will be used across all transactions.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="addStoreModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Store</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="add_store" value="1">
                <div class="mb-3">
                    <label class="form-label">Store Name</label>
                    <input type="text" name="store_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Store Code</label>
                    <input type="text" name="store_code" class="form-control" required placeholder="STR-00X">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Store</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editStoreModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Store Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="update_store" value="1">
                <input type="hidden" name="store_id" id="edit_store_id">

                <div class="mb-3">
                    <label class="form-label">Store Name</label>
                    <input type="text" name="store_name" id="edit_store_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Store Code</label>
                    <input type="text" name="store_code" id="edit_store_code" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" id="edit_is_active" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Update Store</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editStoreBtns = document.querySelectorAll('.edit-store-btn');
        editStoreBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_store_id').value = this.dataset.id;
                document.getElementById('edit_store_name').value = this.dataset.name;
                document.getElementById('edit_store_code').value = this.dataset.code;
                document.getElementById('edit_phone').value = this.dataset.phone;
                document.getElementById('edit_address').value = this.dataset.address;
                document.getElementById('edit_is_active').value = this.dataset.active;
            });
        });
    });
</script>

<?php require_once 'templates/footer.php'; ?>