<?php
// vendor_create.php - Add New Vendor
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

// Get currency symbol from company settings
$stmt = $conn->prepare("SELECT currency FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$companyData = $stmt->fetch(PDO::FETCH_ASSOC);
$currency = isset($companyData['currency']) ? $companyData['currency'] : 'USD';

$symbols = array(
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'INR' => '₹',
    'AED' => 'AED ',
    'PKR' => 'Rs',
    'BDT' => 'Tk'
);
$symbol = isset($symbols[$currency]) ? $symbols[$currency] : '$';

// Handle New Vendor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vendor'])) {
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
        try {
            $stmt = $conn->prepare("INSERT INTO vendors (company_id, name, phone, email, address, contact_person, tax_number, opening_balance, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute(array($_SESSION['company_id'], $name, $phone, $email, $address, $contact_person, $tax_number, $opening_balance));
            $message = "Vendor added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-user-plus me-2"></i>Add New Vendor</h2>
        <a href="vendors.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Vendors
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

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-store me-2"></i>Vendor Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="add_vendor" value="1">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vendor/Company Name *</label>
                                <input type="text" name="name" class="form-control" required
                                    placeholder="Enter vendor name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control"
                                    placeholder="Primary contact name">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="Phone number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="Email address">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax / VAT Number</label>
                                <input type="text" name="tax_number" class="form-control" placeholder="Tax ID">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Opening Balance (Payable)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo $symbol; ?></span>
                                    <input type="number" step="0.01" name="opening_balance" class="form-control"
                                        value="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"
                                placeholder="Full address"></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Vendor
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Vendor name is required</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Add contact details for easy
                            communication</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Complete address helps with
                            deliveries</li>
                        <li><i class="fas fa-check text-success me-2"></i>You can edit vendor info later</li>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
                </div>
                <div class="card-body">
                    <a href="vendors.php" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-list me-2"></i>View All Vendors
                    </a>
                    <a href="purchase_create.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-plus me-2"></i>Create Purchase Order
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>