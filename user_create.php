<?php
// user_create.php - Add New User
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

require_once 'core/PermissionService.php';
PermissionService::requirePermission('admin.users.create');

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role_id = $_POST['role_id'] ? $_POST['role_id'] : null;
    $store_id = !empty($_POST['store_id']) ? $_POST['store_id'] : null;
    $terminal_id = !empty($_POST['terminal_id']) ? $_POST['terminal_id'] : null;
    $phone = trim($_POST['phone']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if ($username == '' || $email == '' || $password == '') {
        $error = "Username, email and password are required.";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if username or email exists globally
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute(array($username, $email));
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $userId = null;

        try {
            $conn->beginTransaction();

            if ($existingUser) {
                // User exists. Check if they are already in this company
                $userId = $existingUser['id'];

                $stmtCheckOrg = $conn->prepare("SELECT id FROM organization_users WHERE user_id = ? AND company_id = ?");
                $stmtCheckOrg->execute([$userId, $_SESSION['company_id']]);

                if ($stmtCheckOrg->fetch()) {
                    throw new Exception("User already exists in this organization.");
                }

                // If not in this org, we will just map them (ignoring password/email update for now)
                // Optionally update full name or phone if provided? Let's keep it simple.
            } else {
                // New User
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Schema: users (id, username, password_hash, full_name, email, phone, created_at)
                // removed company_id, role_id, store_id, terminal_id, is_active (moved to org_users)
                $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, phone, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute(array(
                    $username,
                    $email,
                    $hashedPassword,
                    $phone
                ));
                $userId = $conn->lastInsertId();
            }

            // Map to Organization
            // organization_users (user_id, company_id, role_id, store_id, terminal_id, is_active)
            $stmtOrg = $conn->prepare("INSERT INTO organization_users (user_id, company_id, role_id, store_id, terminal_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtOrg->execute(array(
                $userId,
                $_SESSION['company_id'],
                $role_id,
                $store_id,
                $terminal_id,
                $is_active
            ));

            $conn->commit();
            $message = "User created/added successfully!";

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error creating user: " . $e->getMessage();
        }
    }
}

// Fetch Roles
$stmt = $conn->prepare("SELECT * FROM roles ORDER BY role_name ASC");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stores
$stmt = $conn->prepare("SELECT * FROM stores WHERE company_id = ? ORDER BY store_name ASC");
$stmt->execute([$_SESSION['company_id']]);
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-user-plus me-2"></i>Add New User</h2>
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Users
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
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>User Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="create_user" value="1">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" required
                                        placeholder="Enter username">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required
                                        placeholder="Enter email">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" required
                                        placeholder="Enter password" minlength="6">
                                </div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" required
                                        placeholder="Confirm password">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select name="role_id" class="form-select" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="phone" class="form-control"
                                        placeholder="Enter phone number">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Store Assignment</label>
                                <select name="store_id" id="store_id" class="form-select">
                                    <option value="">-- No Specific Store --</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['id']; ?>">
                                            <?php echo htmlspecialchars($store['store_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">If selecting a store, user sees only that store's
                                    data.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Terminal Assignment</label>
                                <select name="terminal_id" id="terminal_id" class="form-select" disabled>
                                    <option value="">-- Select Store First --</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">Active User</label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create User
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
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Username must be unique</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Email should be valid</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Password minimum 6 chars</li>
                        <li><i class="fas fa-check text-success me-2"></i>Assign appropriate role</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('store_id').addEventListener('change', function () {
        const storeId = this.value;
        const terminalSelect = document.getElementById('terminal_id');

        terminalSelect.innerHTML = '<option value="">-- Loading... --</option>';
        terminalSelect.disabled = true;

        if (storeId) {
            fetch('pos_api.php?action=get_terminals&store_id=' + storeId)
                .then(response => response.json())
                .then(data => {
                    terminalSelect.innerHTML = '<option value="">-- Select Terminal (Optional) --</option>';
                    data.forEach(term => {
                        const option = document.createElement('option');
                        option.value = term.id;
                        option.textContent = term.name;
                        terminalSelect.appendChild(option);
                    });
                    terminalSelect.disabled = false;
                })
                .catch(err => {
                    terminalSelect.innerHTML = '<option value="">Error loading terminals</option>';
                });
        } else {
            terminalSelect.innerHTML = '<option value="">-- Select Store First --</option>';
            terminalSelect.disabled = true;
        }
    });
</script>

<?php require_once 'templates/footer.php'; ?>