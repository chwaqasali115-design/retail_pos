<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';

Session::checkLogin();
PermissionService::requirePermission('org_setup.user_mapping.view');

if (!isset($_GET['org_id'])) {
    header("Location: org_list.php");
    exit;
}

$orgId = $_GET['org_id'];
$db = new Database();
$conn = $db->getConnection();

// Fetch Organization Details
$stmtOrg = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmtOrg->execute([$orgId]);
$organization = $stmtOrg->fetch(PDO::FETCH_ASSOC);

if (!$organization) {
    die("Organization not found.");
}

$message = '';
$error = '';

// Handle Add User to Org (Map existing user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user_to_org'])) {
    PermissionService::requirePermission('org_setup.user_mapping.create');
    
    $usernameOrEmail = trim($_POST['username_search']);
    $roleId = $_POST['role_id'];
    $storeId = !empty($_POST['store_id']) ? $_POST['store_id'] : null;
    
    // Find User
    $stmtFind = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmtFind->execute([$usernameOrEmail, $usernameOrEmail]);
    $userFound = $stmtFind->fetch(PDO::FETCH_ASSOC);
    
    if ($userFound) {
        // Check if already in org
        $stmtCheck = $conn->prepare("SELECT id FROM organization_users WHERE user_id = ? AND company_id = ?");
        $stmtCheck->execute([$userFound['id'], $orgId]);
        if ($stmtCheck->rowCount() > 0) {
            $error = "User is already assigned to this organization.";
        } else {
            // Assign
            try {
                $stmtAdd = $conn->prepare("INSERT INTO organization_users (user_id, company_id, role_id, store_id, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmtAdd->execute([$userFound['id'], $orgId, $roleId, $storeId]);
                $message = "User added to organization successfully.";
            } catch (Exception $e) {
                $error = "Error adding user: " . $e->getMessage();
            }
        }
    } else {
        $error = "User not found with that username or email.";
    }
}

// Handle Remove User from Org
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_user'])) {
    PermissionService::requirePermission('org_setup.user_mapping.remove'); // Ensure this permission exists or use similar
    // Fallback if permission slug doesn't exist yet: default to admin check or update seeding
    
    $orgUserId = $_POST['org_user_id'];
    // Delete from organization_users
    $stmtDel = $conn->prepare("DELETE FROM organization_users WHERE id = ? AND company_id = ?");
    $stmtDel->execute([$orgUserId, $orgId]);
    $message = "User removed from organization.";
}

// Fetch Users in this Org
$queryUsers = "SELECT ou.id as link_id, u.username, u.full_name, u.email, r.role_name, s.store_name, ou.is_active 
               FROM organization_users ou
               JOIN users u ON ou.user_id = u.id
               JOIN roles r ON ou.role_id = r.id
               LEFT JOIN stores s ON ou.store_id = s.id
               WHERE ou.company_id = ?
               ORDER BY u.username ASC";
$stmtUsers = $conn->prepare($queryUsers);
$stmtUsers->execute([$orgId]);
$orgUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Fetch Roles for Dropdown
$roles = $conn->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stores for Dropdown
$stmtStores = $conn->prepare("SELECT * FROM stores WHERE company_id = ?");
$stmtStores->execute([$orgId]);
$stores = $stmtStores->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-users-cog me-2"></i>User Management</h2>
            <h5 class="text-secondary"><?php echo htmlspecialchars($organization['company_name']); ?></h5>
        </div>
        <a href="org_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Org List</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <!-- Add Existing User Form -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add User to Organization</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="add_user_to_org" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Search User</label>
                            <input type="text" name="username_search" class="form-control" required placeholder="Username or Email">
                            <small class="text-muted">User must already exist in the system.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role in Organization</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Default Store (Optional)</label>
                            <select name="store_id" class="form-select">
                                <option value="">-- All Stores / None --</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['store_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Add User</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 bg-light">
                 <div class="card-body">
                     <small class="text-muted"><i class="fas fa-info-circle me-1"></i> To create a brand new user account, go to the <a href="user_create.php">New User</a> page first.</small>
                 </div>
            </div>
        </div>

        <!-- User List -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Assigned Users</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">User</th>
                                    <th>Role</th>
                                    <th>Store</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orgUsers)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No users assigned yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($orgUsers as $u): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                            </td>
                                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($u['role_name']); ?></span></td>
                                            <td><?php echo htmlspecialchars($u['store_name'] ?? 'All Stores'); ?></td>
                                            <td>
                                                <?php echo $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this user from the organization?');">
                                                    <input type="hidden" name="remove_user" value="1">
                                                    <input type="hidden" name="org_user_id" value="<?php echo $u['link_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i> Remove</button>
                                                </form>
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
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
