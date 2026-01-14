<?php
// permissions.php - Manage Role & User Permissions
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/PermissionService.php';

Session::checkLogin();
PermissionService::requirePermission('admin.users.manage_roles'); // Ensure only admins access this

$db = new Database();
$permService = new PermissionService();

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Create Role
    if (isset($_POST['create_role'])) {
        $newRoleName = trim($_POST['role_name']);
        if (!empty($newRoleName)) {
            $checkStmt = $db->getConnection()->prepare("SELECT COUNT(*) FROM roles WHERE role_name = ?");
            $checkStmt->execute([$newRoleName]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = "Role '$newRoleName' already exists.";
            } else {
                $stmt = $db->getConnection()->prepare("INSERT INTO roles (role_name) VALUES (?)");
                if ($stmt->execute([$newRoleName])) {
                    $message = "Role created successfully!";
                    // Refresh roles list
                    $roles = $db->getConnection()->query("SELECT * FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC); // Re-fetch will happen below anyway if we redirect or just let script run, but the query below runs after this block.
                } else {
                    $error = "Error creating role.";
                }
            }
        } else {
            $error = "Role name cannot be empty.";
        }
    }
    
    // Handle Permissions Update
    if (isset($_POST['update_permissions'])) {
    $type = $_POST['type']; // 'role' or 'user'
    $targetId = $_POST['target_id'];
    $selectedPerms = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    if ($type === 'role') {
        // For roles, we save the checked list directly
        if ($permService->saveRolePermissions($targetId, $selectedPerms)) {
            $message = "Role permissions updated successfully!";
        } else {
            $error = "Failed to update role permissions.";
        }
    } elseif ($type === 'user') {
        // For users, it's tricker (overrides).
        // However, the UI might simplify this by just showing "Effective Permissions".
        // But the requirement says "User-wise (override role permissions)".
        // To simplify the UI for now, we will handle User Overrides as:
        // 1. Get User's Role Permissions (Baseline)
        // 2. UI shows ALL checkboxes.
        // 3. User checks/unchecks specific ones.
        // 4. We compare with Role's baseline.
        //    - If Role has it and User UNCHECKS it -> Revoke (is_allowed = 0)
        //    - If Role doesn't have it and User CHECKS it -> Grant (is_allowed = 1)
        //    - If matches Role -> No override entry needed (delete existing)
        
        // Fetch users current role for the active company
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT role_id FROM organization_users WHERE user_id = ? AND company_id = ?");
        $stmt->execute([$targetId, $_SESSION['company_id']]);
        $roleId = $stmt->fetchColumn();
        
        $rolePerms = $permService->getRolePermissions($roleId); // array of perm IDs
        
        $overrides = [];
        
        // Get all possible permission IDs
        $allPermStmt = $conn->query("SELECT id FROM permissions");
        $allPermIds = $allPermStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($allPermIds as $pId) {
            $inRole = in_array($pId, $rolePerms);
            $inPost = in_array($pId, $selectedPerms);
            
            if ($inRole && !$inPost) {
                // Role has it, but User removed it -> Revoke
                $overrides[] = ['permission_id' => $pId, 'is_allowed' => 0];
            } elseif (!$inRole && $inPost) {
                // Role doesn't have it, but User added it -> Grant
                $overrides[] = ['permission_id' => $pId, 'is_allowed' => 1];
            }
        }
        
        if ($permService->saveUserOverrides($targetId, $_SESSION['company_id'], $overrides)) {
            $message = "User permission overrides updated successfully!";
        } else {
            $error = "Failed to update user overrides.";
        }
    }
}
}

// Fetch Roles and Users for the Selector
$conn = $db->getConnection();
$roles = $conn->query("SELECT * FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Users in this company with their role
$userQuery = "SELECT u.id, u.username, u.full_name, ou.role_id 
              FROM users u 
              JOIN organization_users ou ON u.id = ou.user_id 
              WHERE ou.company_id = ? AND ou.is_active = 1 
              ORDER BY u.username";
$stmtUser = $conn->prepare($userQuery);
$stmtUser->execute([$_SESSION['company_id']]);
$users = $stmtUser->fetchAll(PDO::FETCH_ASSOC);

// Get the Permission Tree for rendering
$permissionTree = $permService->getPermissionTree();

// Current Selection
$selectedType = isset($_GET['type']) ? $_GET['type'] : 'role';
$selectedId = isset($_GET['id']) ? $_GET['id'] : (($selectedType == 'role' && !empty($roles)) ? $roles[0]['id'] : '');

// Fetch Existing Permissions for Selection
$currentPermissions = [];
if ($selectedId) {
    if ($selectedType === 'role') {
        $currentPermissions = $permService->getRolePermissions($selectedId);
    } else {
        // For User, we want to show Effective Permissions (Role + Overrides)
        // 1. Get Role Perms
        $uKey = array_search($selectedId, array_column($users, 'id'));
        if ($uKey !== false) {
             $uRoleId = $users[$uKey]['role_id'];
             $basePerms = $permService->getRolePermissions($uRoleId);
             
             // 2. Get Overrides
             $overrides = $permService->getUserOverrides($selectedId, $_SESSION['company_id']);
             
             // 3. Merge
             // Start with base
             $effMap = array_fill_keys($basePerms, true);
             
             // Apply overrides
             foreach ($overrides as $pId => $isAllowed) {
                 if ($isAllowed) $effMap[$pId] = true;
                 else unset($effMap[$pId]);
             }
             
             $currentPermissions = array_keys($effMap);
        }
    }
}


require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-shield-alt me-2"></i>Permission Management</h2>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRoleModal">
            <i class="fas fa-plus me-2"></i>Create New Role
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar: Selector -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Select Target</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-tabs nav-fill" id="permTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $selectedType == 'role' ? 'active' : ''; ?>" href="permissions.php?type=role">Roles</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $selectedType == 'user' ? 'active' : ''; ?>" href="permissions.php?type=user">Users</a>
                        </li>
                    </ul>
                    <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                        <?php if ($selectedType == 'role'): ?>
                            <?php foreach ($roles as $role): ?>
                                <a href="permissions.php?type=role&id=<?php echo $role['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $selectedId == $role['id'] ? 'active' : ''; ?>">
                                    <i class="fas fa-user-tag me-2"></i><?php echo htmlspecialchars($role['role_name']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <a href="permissions.php?type=user&id=<?php echo $user['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $selectedId == $user['id'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user['username']); ?></span>
                                        <small class="badge bg-secondary rounded-pill"><?php 
                                            // Find role name
                                            $rKey = array_search($user['role_id'], array_column($roles, 'id'));
                                            echo $rKey !== false ? htmlspecialchars($roles[$rKey]['role_name']) : '?';
                                        ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content: Checkboxes -->
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($selectedType == 'role'): ?>
                            Editing Role: <span class="text-primary"><?php echo htmlspecialchars($roles[array_search($selectedId, array_column($roles, 'id'))]['role_name'] ?? ''); ?></span>
                        <?php else: ?>
                            Editing User: <span class="text-primary"><?php echo htmlspecialchars($users[array_search($selectedId, array_column($users, 'id'))]['username'] ?? ''); ?></span>
                        <?php endif; ?>
                    </h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="toggleAll(true)">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Deselect All</button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="type" value="<?php echo $selectedType; ?>">
                        <input type="hidden" name="target_id" value="<?php echo $selectedId; ?>">
                        <input type="hidden" name="update_permissions" value="1">

                        <?php foreach ($permissionTree as $module): ?>
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2 text-secondary">
                                    <i class="<?php echo $module['icon']; ?> me-2"></i><?php echo htmlspecialchars($module['name']); ?>
                                </h5>
                                <div class="row">
                                    <?php foreach ($module['resources'] as $resource): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 border-0 bg-light">
                                                <div class="card-body py-2">
                                                    <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($resource['name']); ?></h6>
                                                    <?php foreach ($resource['permissions'] as $perm): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input perm-checkbox" type="checkbox" 
                                                                   name="permissions[]" 
                                                                   value="<?php echo $perm['id']; ?>" 
                                                                   id="perm_<?php echo $perm['id']; ?>"
                                                                   <?php echo in_array($perm['id'], $currentPermissions) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="perm_<?php echo $perm['id']; ?>">
                                                                <?php echo htmlspecialchars($perm['label']); ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" class="btn btn-primary px-5"><i class="fas fa-save me-2"></i>Save Permissions</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = checked);
}
</script>

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="create_role" value="1">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" name="role_name" class="form-control" required placeholder="e.g. Supervisor">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
