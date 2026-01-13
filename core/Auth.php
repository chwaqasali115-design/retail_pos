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
                Session::set('role_id', $user['role_id']);
                Session::set('company_id', $user['company_id']);
                Session::set('store_id', $user['store_id']);
                Session::set('terminal_id', $user['terminal_id'] ?? null);

                // Load Permissions
                require_once 'PermissionService.php';
                $permService = new PermissionService();
                $permService->loadUserPermissions($user['id'], $user['role_id']);

                // Log Audit
                $this->logAudit($user['id'], 'Login', 'User Logged in');
                return true;
            }
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
