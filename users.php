<?php
// users.php - All Users List
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';
$error = '';

// Handle Remove User from Org
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];

    require_once 'core/PermissionService.php';
    // Use the new permission 'org_setup.user_mapping.remove' OR fallback to 'admin.users.delete'
    if (PermissionService::hasPermission('org_setup.user_mapping.remove') || PermissionService::hasPermission('admin.users.delete')) {
        if ($user_id == $_SESSION['user_id']) {
            $error = "You cannot remove yourself from the organization.";
        } else {
            try {
                // Remove from this organization only
                $stmt = $conn->prepare("DELETE FROM organization_users WHERE user_id = ? AND company_id = ?");
                $stmt->execute(array($user_id, $_SESSION['company_id']));
                $message = "User removed from organization successfully!";
            } catch (PDOException $e) {
                $error = "Error removing user.";
            }
        }
    } else {
        $error = "Permission Denied.";
    }
}

// Handle Toggle Status
if (isset($_GET['toggle'])) {
    $user_id = $_GET['toggle'];

    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot deactivate your own account.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE organization_users SET is_active = NOT is_active WHERE user_id = ? AND company_id = ?");
            $stmt->execute(array($user_id, $_SESSION['company_id']));
            $message = "User status updated!";
        } catch (PDOException $e) {
            $error = "Error updating user status.";
        }
    }
}

// Fetch Users for this Organization
$stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.full_name, u.created_at, 
                               ou.role_id, ou.is_active, r.role_name, ou.id as org_user_id
                        FROM users u 
                        JOIN organization_users ou ON u.id = ou.user_id 
                        LEFT JOIN roles r ON ou.role_id = r.id 
                        WHERE ou.company_id = ? 
                        ORDER BY u.created_at DESC");
$stmt->execute(array($_SESSION['company_id']));
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Roles for filter
$stmt = $conn->prepare("SELECT * FROM roles ORDER BY role_name ASC");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-users me-2"></i>User Management</h2>
        <a href="user_create.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Add New User
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

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Users</h6>
                            <h3><?php echo count($users); ?></h3>
                        </div>
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Active Users</h6>
                            <h3><?php
                            $active = 0;
                            foreach ($users as $u) {
                                if (!isset($u['is_active']) || $u['is_active'] == 1)
                                    $active++;
                            }
                            echo $active;
                            ?></h3>
                        </div>
                        <i class="fas fa-user-check fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Inactive Users</h6>
                            <h3><?php echo count($users) - $active; ?></h3>
                        </div>
                        <i class="fas fa-user-times fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Roles</h6>
                            <h3><?php echo count($roles); ?></h3>
                        </div>
                        <i class="fas fa-user-tag fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Users</h5>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchUser" placeholder="Search users...">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-3x mb-3 d-block"></i>
                                    No users found. <a href="user_create.php">Add your first user</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $count = 1;
                            foreach ($users as $user): ?>
                                <?php
                                $isActive = !isset($user['is_active']) || $user['is_active'] == 1;
                                $roleName = isset($user['role_name']) && $user['role_name'] ? $user['role_name'] : 'No Role';
                                $phone = isset($user['phone']) ? $user['phone'] : '';
                                $createdAt = isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A';
                                $isCurrentUser = $user['id'] == $_SESSION['user_id'];
                                ?>
                                <tr>
                                    <td><?php echo $count; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2 bg-primary text-white">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <?php if ($isCurrentUser): ?>
                                                    <span class="badge bg-info ms-1">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($roleName); ?></span></td>
                                    <td><?php echo htmlspecialchars($phone); ?></td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $createdAt; ?></td>
                                    <td>
                                        <?php if (PermissionService::hasPermission('admin.users.edit')): ?>
                                            <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if (!$isCurrentUser): ?>
                                                <a href="?toggle=<?php echo $user['id']; ?>"
                                                    class="btn btn-sm <?php echo $isActive ? 'btn-secondary' : 'btn-success'; ?>"
                                                    title="<?php echo $isActive ? 'Deactivate' : 'Activate'; ?>"
                                                    onclick="return confirm('<?php echo $isActive ? 'Deactivate' : 'Activate'; ?> this user?')">
                                                    <i class="fas fa-<?php echo $isActive ? 'ban' : 'check'; ?>"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (!$isCurrentUser && PermissionService::hasPermission('admin.users.delete')): ?>
                                            <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger"
                                                title="Delete" onclick="return confirm('Delete this user permanently?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
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

<style>
    .avatar-circle {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
    }
</style>

<script>
    document.getElementById('searchUser').addEventListener('keyup', function () {
        var value = this.value.toLowerCase();
        var rows = document.querySelectorAll('#usersTable tbody tr');
        rows.forEach(function (row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.indexOf(value) > -1 ? '' : 'none';
        });
    });
</script>

<?php require_once 'templates/footer.php'; ?>