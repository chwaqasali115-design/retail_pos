<?php
// core/Auth.php
require_once 'Database.php';
require_once 'Session.php';
require_once 'PermissionService.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function login($username, $password)
    {
        $query = "SELECT * FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password_hash'])) {

                Session::set('user_id', $user['id']);
                Session::set('username', $user['username']);
                Session::set('is_super_admin', $user['is_super_admin']);

                // Fetch Organizations
                $orgQuery = "SELECT ou.*, c.company_name 
                             FROM organization_users ou
                             JOIN companies c ON ou.company_id = c.id
                             WHERE ou.user_id = :userId AND ou.is_active = 1";
                $stmtOrg = $this->db->prepare($orgQuery);
                $stmtOrg->execute([':userId' => $user['id']]);
                $orgs = $stmtOrg->fetchAll(PDO::FETCH_ASSOC);

                $count = count($orgs);

                // If Super Admin, allow login even with 0 orgs
                if ($user['is_super_admin'] == 1) {
                    if ($count === 0) {
                        // Super Admin with NO Orgs -> Send to Org Create?
                        // But standard flow is MULTI_ORG -> Select Org Page.
                        // Select Org Page for Super Admin updates needed.
                        return 'MULTI_ORG';
                    }
                    // If has orgs, still return MULTI_ORG so they can choose (or auto login if 1? Super Admin might want to choose)
                    // Let's stick to standard behavior: 1 org -> auto, >1 -> select.
                    // But if they want to access "Global" features, they might need a way out.
                    // For now, let's just properly set session and continue standard flow.
                    // Or always force selection for Super Admin?
                    // Let's force selection if count > 0, else MULTI_ORG (redirects to select_org which will handle "No Org" case for Super Admin specially)
                }

                if ($count === 0) {
                    return 'NO_ORG';
                }

                if ($count === 1) {
                    // Auto Login
                    $this->setOrganizationContext($orgs[0]);
                    return 'SUCCESS';
                } else {
                    // Multi Org -> User needs to select
                    return 'MULTI_ORG';
                }
            }
        }
        return false;
    }

    public function setOrganizationContext($orgUserRow)
    {
        Session::set('company_id', $orgUserRow['company_id']);
        Session::set('company_name', $orgUserRow['company_name']);
        Session::set('role_id', $orgUserRow['role_id']);
        Session::set('store_id', $orgUserRow['store_id']);
        Session::set('terminal_id', $orgUserRow['terminal_id']);


        // Load Permissions
        $permService = new PermissionService();
        $permService->loadUserPermissions($orgUserRow['user_id'], $orgUserRow['role_id'], $orgUserRow['company_id']);

        // Audit
        $this->logAudit($orgUserRow['user_id'], 'Login', 'User Logged in to Org ' . $orgUserRow['company_id']);
    }

    public function getAvailableOrganizations($userId)
    {
        // Global Super Admin sees all Organizations
        if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1) {
            $query = "SELECT id, company_name, 1 as role_id FROM companies WHERE is_active = 1";
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        }

        $orgQuery = "SELECT c.id, c.company_name, ou.role_id 
                     FROM organization_users ou
                     JOIN companies c ON ou.company_id = c.id
                     WHERE ou.user_id = :userId AND ou.is_active = 1";
        $stmt = $this->db->prepare($orgQuery);
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function switchOrganization($userId, $companyId)
    {
        // Global Super Admin Switch Logic
        // They can switch to ANY company.
        if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1) {
            $stmt = $this->db->prepare("SELECT * FROM companies WHERE id = :companyId AND is_active = 1");
            $stmt->execute([':companyId' => $companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($company) {
                // Mock OrgUser Row for Context
                // We use role_id = 1 (Admin) as effective role
                $orgUserRow = [
                    'user_id' => $userId,
                    'company_id' => $company['id'],
                    'company_name' => $company['company_name'],
                    'role_id' => 1,
                    'store_id' => null, // Super Admin might need to select store globally or default to none
                    'terminal_id' => null
                ];
                $this->setOrganizationContext($orgUserRow);
                return true;
            }
            return false;
        }

        $query = "SELECT ou.*, c.company_name 
                 FROM organization_users ou
                 JOIN companies c ON ou.company_id = c.id
                 WHERE ou.user_id = :userId AND ou.company_id = :companyId AND ou.is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':userId' => $userId, ':companyId' => $companyId]);

        if ($stmt->rowCount() > 0) {
            $orgRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->setOrganizationContext($orgRow);
            return true;
        }
        return false;
    }

    private function logAudit($user_id, $action, $details)
    {
        $query = "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (:uid, :act, :det, :ip)";
        $stmt = $this->db->prepare($query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bindParam(":uid", $user_id);
        $stmt->bindParam(":act", $action);
        $stmt->bindParam(":det", $details);
        $stmt->bindParam(":ip", $ip);
        $stmt->execute();
    }
}
