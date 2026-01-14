<?php
// core/PermissionService.php
require_once 'Database.php';
require_once 'Session.php';

class PermissionService
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Load permissions for a user into the session
     * @param int $userId
     * @param int $roleId
     * @param int $companyId
     */
    public function loadUserPermissions($userId, $roleId, $companyId)
    {
        // SUPER ADMIN CHECK: Role ID 1 is Admin, OR user has is_super_admin flag
        if ($roleId == 1 || (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1)) {
            $_SESSION['permissions'] = ['SUPER_ADMIN'];
            return;
        }

        // 1. Get Role Permissions
        $query = "SELECT p.slug 
                  FROM role_permissions rp
                  JOIN permissions p ON rp.permission_id = p.id
                  WHERE rp.role_id = :roleId";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":roleId", $roleId);
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Apply User Overrides (Scoped to Company)
        // We need to fetch overrides: if is_allowed = 1, add it; if 0, remove it.
        $queryOverride = "SELECT p.slug, up.is_allowed 
                          FROM user_permissions up
                          JOIN permissions p ON up.permission_id = p.id
                          WHERE up.user_id = :userId AND up.company_id = :companyId";

        $stmtOverride = $this->db->prepare($queryOverride);
        $stmtOverride->bindParam(":userId", $userId);
        $stmtOverride->bindParam(":companyId", $companyId);
        $stmtOverride->execute();
        $overrides = $stmtOverride->fetchAll(PDO::FETCH_ASSOC);

        // Convert permissions to an associative array for easier manipulation (slug => true)
        $permMap = array_fill_keys($permissions, true);

        foreach ($overrides as $override) {
            if ($override['is_allowed'] == 1) {
                // Grant
                $permMap[$override['slug']] = true;
            } else {
                // Revoke
                unset($permMap[$override['slug']]);
            }
        }

        // Save to Session
        $_SESSION['permissions'] = array_keys($permMap);
    }

    /**
     * Check if current user has a specific permission
     * @param string $slug
     * @return boolean
     */
    public static function hasPermission($slug)
    {
        // Global Super Admin Bypass
        if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1) {
            return true;
        }

        if (!isset($_SESSION['permissions'])) {
            return false;
        }

        // Intra-Org Super Admin
        if (in_array('SUPER_ADMIN', $_SESSION['permissions'])) {
            return true;
        }

        return in_array($slug, $_SESSION['permissions']);
    }

    /**
     * Middleware helper to block access
     * @param string $slug
     */
    public static function requirePermission($slug)
    {
        if (!self::hasPermission($slug)) {
            // Check if it's an API request or Page request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['status' => 'error', 'message' => 'Permission Denied']);
                exit;
            } else {
                die("<h1>403 Forbidden</h1><p>You do not have permission to access this resource ($slug).</p><a href='index.php'>Go Home</a>");
            }
        }
    }

    /**
     * Get full tree of modules -> resources -> permissions
     * Used for the UI
     */
    public function getPermissionTree()
    {
        // Fetch all modules
        $stmt = $this->db->query("SELECT * FROM modules ORDER BY sort_order ASC");
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tree = [];

        foreach ($modules as $module) {
            $moduleId = $module['id'];

            // Fetch resources for this module
            $stmtRes = $this->db->prepare("SELECT * FROM resources WHERE module_id = ? ORDER BY sort_order ASC");
            $stmtRes->execute([$moduleId]);
            $resources = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

            $modData = [
                'id' => $module['id'],
                'name' => $module['label'],
                'icon' => $module['icon'],
                'resources' => []
            ];

            foreach ($resources as $res) {
                // Fetch permissions for this resource
                $stmtPerm = $this->db->prepare("SELECT * FROM permissions WHERE resource_id = ?");
                $stmtPerm->execute([$res['id']]);
                $perms = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);

                $modData['resources'][] = [
                    'id' => $res['id'],
                    'name' => $res['label'],
                    'permissions' => $perms
                ];
            }

            $tree[] = $modData;
        }

        return $tree;
    }

    /**
     * Get permission overrides for a specific user and company
     */
    public function getUserOverrides($userId, $companyId)
    {
        $stmt = $this->db->prepare("SELECT permission_id, is_allowed FROM user_permissions WHERE user_id = ? AND company_id = ?");
        $stmt->execute([$userId, $companyId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // returns [permission_id => is_allowed]
    }

    /**
     * Get role permissions
     */
    public function getRolePermissions($roleId)
    {
        $stmt = $this->db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Save Role Permissions
     */
    public function saveRolePermissions($roleId, $permissionIds)
    {
        $this->db->beginTransaction();
        try {
            // Clear existing
            $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);

            // Insert new
            $stmtInsert = $this->db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $permId) {
                $stmtInsert->execute([$roleId, $permId]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Save User Overrides
     * @param int $userId
     * @param int $companyId
     * @param array $overrides format: [[permission_id, is_allowed], ...]
     */
    public function saveUserOverrides($userId, $companyId, $overrides)
    {
        $this->db->beginTransaction();
        try {
            // Clear existing for this company
            $stmt = $this->db->prepare("DELETE FROM user_permissions WHERE user_id = ? AND company_id = ?");
            $stmt->execute([$userId, $companyId]);

            // Insert new
            $stmtInsert = $this->db->prepare("INSERT INTO user_permissions (user_id, permission_id, company_id, is_allowed) VALUES (?, ?, ?, ?)");
            foreach ($overrides as $ov) {
                $stmtInsert->execute([$userId, $ov['permission_id'], $companyId, $ov['is_allowed']]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
