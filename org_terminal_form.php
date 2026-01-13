<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';
$terminal = null;
$isEdit = false;

// Fetch stores for dropdown
$stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE company_id = ? AND is_active = 1");
$stmt->execute([$_SESSION['company_id']]);
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['id'])) {
    $isEdit = true;
    $stmt = $conn->prepare("SELECT * FROM terminals WHERE id = ?"); // Needs company check ideally via store
    $stmt->execute([$_GET['id']]);
    $terminal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$terminal) {
        header("Location: org_terminals.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $store_id = $_POST['store_id'];
    $device_id = $_POST['device_id'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($isEdit) {
        try {
            $stmt = $conn->prepare("UPDATE terminals SET name = ?, store_id = ?, device_id = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $store_id, $device_id, $isActive, $_GET['id']]);
            header("Location: org_terminals.php?success=Terminal updated successfully");
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO terminals (store_id, name, device_id, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$store_id, $name, $device_id, $isActive]);
            header("Location: org_terminals.php?success=Terminal created successfully");
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
                        <h5 class="mb-0 fw-bold"><?php echo $isEdit ? 'Edit Terminal' : 'Add New Terminal'; ?></h5>
                        <a href="org_terminals.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Terminal Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Main Register, Counter 1"
                                   value="<?php echo htmlspecialchars($terminal['name'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assign to Store *</label>
                            <select name="store_id" class="form-select" required>
                                <option value="">-- Select Store --</option>
                                <?php foreach($stores as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo (isset($terminal['store_id']) && $terminal['store_id'] == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['store_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Device ID / MAC Address (Optional)</label>
                            <input type="text" name="device_id" class="form-control" placeholder="For hardware identification"
                                   value="<?php echo htmlspecialchars($terminal['device_id'] ?? ''); ?>">
                            <div class="form-text">Used to lock this terminal configuration to specific hardware if needed.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                       <?php echo ($terminal['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="org_terminals.php" class="btn btn-light me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update Terminal' : 'Create Terminal'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
