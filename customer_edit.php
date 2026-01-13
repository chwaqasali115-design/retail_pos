<?php
// customer_edit.php - Edit Customer
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

if (!isset($_GET['id'])) {
    header("Location: customers.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';
$id = $_GET['id'];

// Fetch Existing
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND company_id = ?");
$stmt->execute([$id, $_SESSION['company_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die("Customer not found or access denied.");
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $tax_number = trim($_POST['tax_number']);
    $opening_balance = floatval($_POST['opening_balance']);

    if (empty($name)) {
        $error = "Customer Name is required.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, tax_number = ?, opening_balance = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([
                $name,
                $phone,
                $email,
                $address,
                $tax_number,
                $opening_balance,
                $id,
                $_SESSION['company_id']
            ]);
            // Re-fetch
            $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            $message = "Customer updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold"><i class="fas fa-user-edit me-2"></i>Edit Customer</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="accounting.php">Accounting</a></li>
                    <li class="breadcrumb-item"><a href="customers.php">Customers</a></li>
                    <li class="breadcrumb-item active">
                        <?php echo htmlspecialchars($customer['name']); ?>
                    </li>
                </ol>
            </nav>
        </div>
        <a href="customers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to List
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

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark py-3">
                    <h5 class="mb-0"><i class="fas fa-pen me-2"></i>Edit Information</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="update_customer" value="1">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                    value="<?php echo htmlspecialchars($customer['name']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control"
                                    value="<?php echo htmlspecialchars($customer['phone']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($customer['email']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax / VAT Number</label>
                                <input type="text" name="tax_number" class="form-control"
                                    value="<?php echo htmlspecialchars($customer['tax_number']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control"
                                rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                        </div>

                        <div class="row border-top pt-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted">Opening Balance</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">$</span>
                                    <input type="number" step="0.01" name="opening_balance" class="form-control"
                                        value="<?php echo htmlspecialchars($customer['opening_balance']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="customers.php" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-check me-2"></i>Update Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>