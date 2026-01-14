<?php
// user_edit.php - Edit User
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

require_once 'core/PermissionService.php';
PermissionService::requirePermission('admin.users.edit');

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = $_GET['id'];

// Fetch User
$query = "SELECT u.id, u.username, u.email, u.phone,
                 ou.role_id, ou.company_id, ou.store_id, ou.terminal_id, ou.is_active
          FROM users u
          JOIN organization_users ou ON u.id = ou.user_id
          WHERE u.id = ? AND ou.company_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute(array($user_id, $_SESSION['company_id']));
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}

// Fetch Roles
$stmt = $conn->prepare("SELECT * FROM roles ORDER BY role_name ASC");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stores
$stmt = $conn->prepare("SELECT * FROM stores WHERE company_id = ? ORDER BY store_name ASC");
$stmt->execute([$_SESSION['company_id']]);
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role_id = $_POST['role_id'] ? $_POST['role_id'] : null;
    $store_id = !empty($_POST['store_id']) ? $_POST['store_id'] : null;
    $terminal_id = !empty($_POST['terminal_id']) ? $_POST['terminal_id'] : null;
    $phone = trim($_POST['phone']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Prevent user from changing their own role
    if ($user_id == $_SESSION['user_id'] && $role_id != $user['role_id']) {
        $error = "You cannot change your own role.";
    } elseif ($username == '' || $email == '') {
        $error = "Username and email are required.";
    } else {
        // Check Global Uniqueness for Username/Email (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute(array($username, $email, $user_id));
        
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
        } else {
            try {
                $conn->beginTransaction();

                // 1. Update Core User Data
                if ($password != '') {
                    if (strlen($password) < 6) {
                        throw new Exception("Password must be at least 6 characters.");
                    }
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, phone = ? WHERE id = ?");
                    $stmt->execute(array($username, $email, $hashedPassword, $phone, $user_id));
                    $message = "User updated successfully with new password!";
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute(array($username, $email, $phone, $user_id));
                    $message = "User updated successfully!";
                }

                // 2. Update Organization Mapping
                $stmtOrg = $conn->prepare("UPDATE organization_users SET role_id = ?, store_id = ?, terminal_id = ?, is_active = ? WHERE user_id = ? AND company_id = ?");
                $stmtOrg->execute(array($role_id, $store_id, $terminal_id, $is_active, $user_id, $_SESSION['company_id']));

                $conn->commit();
                
                // Refresh user data
                $stmt = $conn->prepare($query);
                $stmt->execute(array($user_id, $_SESSION['company_id']));
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Error updating user: " . $e->getMessage();
            }
        }
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-user-edit me-2"></i>Edit User</h2>
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
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_user" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" required 
                                           value="<?php echo htmlspecialchars($user['username']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                                </div>
                                <small class="text-muted">Leave blank to keep current password</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Store Assignment</label>
                                <select name="store_id" id="store_id" class="form-select">
                                    <option value="">-- No Specific Store --</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['id']; ?>" <?php echo ($user['store_id'] == $store['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($store['store_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Terminal Assignment</label>
                                <select name="terminal_id" id="terminal_id" class="form-select" disabled>
                                    <option value="">-- Select Store First --</option>
                                </select>
                                <input type="hidden" id="current_terminal_id" value="<?php echo $user['terminal_id']; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select name="role_id" class="form-select" required 
                                    <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" 
                                        <?php echo ($user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($user_id == $_SESSION['user_id']): ?>
                                    <input type="hidden" name="role_id" value="<?php echo $user['role_id']; ?>">
                                    <small class="text-muted">You cannot change your own role</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                        <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>
                                        <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Active User</label>
                                </div>
                                <?php if ($user_id == $_SESSION['user_id']): ?>
                                    <input type="hidden" name="is_active" value="1">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update User
                            </button>
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const storeSelect = document.getElementById('store_id');
    const terminalSelect = document.getElementById('terminal_id');
    const currentTerminalId = document.getElementById('current_terminal_id').value;

    function loadTerminals(storeId, selectedId = null) {
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
                        if (selectedId && term.id == selectedId) {
                            option.selected = true;
                        }
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
    }

    storeSelect.addEventListener('change', function() {
        loadTerminals(this.value);
    });

    // Initial load
    if (storeSelect.value) {
        loadTerminals(storeSelect.value, currentTerminalId);
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>