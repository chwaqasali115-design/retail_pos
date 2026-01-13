<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';
$store = null;
$isEdit = false;

if (isset($_GET['id'])) {
    $isEdit = true;
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ? AND company_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['company_id']]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        header("Location: org_stores.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['store_name'];
    $code = $_POST['store_code'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($isEdit) {
        try {
            $stmt = $conn->prepare("UPDATE stores SET store_name = ?, store_code = ?, phone = ?, address = ?, is_active = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$name, $code, $phone, $address, $isActive, $_GET['id'], $_SESSION['company_id']]);
            header("Location: org_stores.php?success=Store updated successfully");
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO stores (company_id, store_name, store_code, phone, address, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['company_id'], $name, $code, $phone, $address, $isActive]);
            header("Location: org_stores.php?success=Store created successfully");
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><?php echo $isEdit ? 'Edit Store' : 'Add New Store'; ?></h5>
                        <a href="org_stores.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Store Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-store"></i></span>
                                    <input type="text" name="store_name" class="form-control" required 
                                           value="<?php echo htmlspecialchars($store['store_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Store Code *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                    <input type="text" name="store_code" class="form-control" required placeholder="STR-001"
                                           value="<?php echo htmlspecialchars($store['store_code'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($store['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                           <?php echo ($store['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($store['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="org_stores.php" class="btn btn-light me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update Store' : 'Create Store'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
