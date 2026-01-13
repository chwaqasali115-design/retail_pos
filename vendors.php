<?php
// vendors.php - All Vendors List
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

// Handle Update Vendor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vendor'])) {
    $vendor_id = $_POST['vendor_id'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $contact_person = trim($_POST['contact_person']);
    $tax_number = trim($_POST['tax_number']);
    $opening_balance = floatval($_POST['opening_balance']);

    if ($name == '') {
        $error = "Vendor name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE vendors SET name = ?, phone = ?, email = ?, address = ?, contact_person = ?, tax_number = ?, opening_balance = ? WHERE id = ? AND company_id = ?");
        $stmt->execute(array($name, $phone, $email, $address, $contact_person, $tax_number, $opening_balance, $vendor_id, $_SESSION['company_id']));
        $message = "Vendor updated successfully!";
    }
}

// Handle Delete Vendor
if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ? AND company_id = ?");
        $stmt->execute(array($_GET['delete'], $_SESSION['company_id']));
        $message = "Vendor deleted successfully!";
    } catch (PDOException $e) {
        $error = "Cannot delete vendor. It may have associated purchases.";
    }
}

// Fetch Vendors
$stmt = $conn->prepare("SELECT * FROM vendors WHERE company_id = ? ORDER BY name ASC");
$stmt->execute(array($_SESSION['company_id']));
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-store me-2"></i>All Vendors</h2>
        <a href="vendor_create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Vendor
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Card -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Vendors</h6>
                            <h3><?php echo count($vendors); ?></h3>
                        </div>
                        <i class="fas fa-store fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Vendor Name</th>
                            <th>Contact Person</th>
                            <th>Phone / Tax</th>
                            <th>Balance</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vendors)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-store fa-3x mb-3 d-block"></i>
                                    No vendors found. <a href="vendor_create.php">Add your first vendor</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $count = 1;
                            foreach ($vendors as $vendor): ?>
                                <tr>
                                    <td><?php echo $count; ?></td>
                                    <td><strong><?php echo htmlspecialchars($vendor['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($vendor['contact_person']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($vendor['phone']); ?>
                                        <?php if ($vendor['tax_number']): ?>
                                            <div class="small text-muted">Tax:
                                                <?php echo htmlspecialchars($vendor['tax_number']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($vendor['opening_balance'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['address']); ?></td>
                                    <td>
                                        <a href="vendor_payment.php?vendor_id=<?php echo $vendor['id']; ?>"
                                            class="btn btn-sm btn-outline-success me-1">
                                            <i class="fas fa-money-bill-wave"></i> Pay
                                        </a>
                                        <button class="btn btn-sm btn-warning edit-vendor-btn"
                                            data-id="<?php echo $vendor['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($vendor['name']); ?>"
                                            data-phone="<?php echo htmlspecialchars($vendor['phone']); ?>"
                                            data-email="<?php echo htmlspecialchars($vendor['email']); ?>"
                                            data-address="<?php echo htmlspecialchars($vendor['address']); ?>"
                                            data-contact="<?php echo htmlspecialchars($vendor['contact_person']); ?>"
                                            data-tax="<?php echo htmlspecialchars($vendor['tax_number'] ?? ''); ?>"
                                            data-balance="<?php echo htmlspecialchars($vendor['opening_balance'] ?? 0); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editVendorModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $vendor['id']; ?>" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this vendor?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php $count++; endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Vendor Modal -->
<div class="modal fade" id="editVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Vendor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="update_vendor" value="1">
                <input type="hidden" name="vendor_id" id="edit_vendor_id">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vendor Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tax / VAT Number</label>
                        <input type="text" name="tax_number" id="edit_tax" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" id="edit_balance" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Vendor</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editButtons = document.querySelectorAll('.edit-vendor-btn');
        for (var i = 0; i < editButtons.length; i++) {
            editButtons[i].addEventListener('click', function () {
                document.getElementById('edit_vendor_id').value = this.getAttribute('data-id');
                document.getElementById('edit_name').value = this.getAttribute('data-name');
                document.getElementById('edit_phone').value = this.getAttribute('data-phone');
                document.getElementById('edit_email').value = this.getAttribute('data-email');
                document.getElementById('edit_address').value = this.getAttribute('data-address');
                document.getElementById('edit_contact_person').value = this.getAttribute('data-contact');
                document.getElementById('edit_tax').value = this.getAttribute('data-tax');
                document.getElementById('edit_balance').value = this.getAttribute('data-balance');
            });
        }
    });
</script>

<?php require_once 'templates/footer.php'; ?>