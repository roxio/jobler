<?php
include_once(__DIR__ . '/Database.php');

class AccessControl {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->ensureSchema();
        $this->seedDefaultPermissions();
    }

    public static function defaultRoles() {
        return [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'moderator' => 'Moderator',
            'opiekun' => 'Opiekun',
            'reklamodawca' => 'Reklamodawca',
            'executor' => 'Wykonawca',
            'user' => 'Uzytkownik',
        ];
    }

    public static function roles() {
        $roles = self::defaultRoles();

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'admin_roles'");
            if (!$stmt || !$stmt->fetchColumn()) {
                return $roles;
            }

            $stmt = $pdo->query("SELECT role_key, label FROM admin_roles ORDER BY sort_order ASC, label ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $role) {
                $roles[$role['role_key']] = $role['label'];
            }
        } catch (Throwable $e) {
            error_log('Roles read warning: ' . $e->getMessage());
        }

        return $roles;
    }

    public static function permissions() {
        return [
            'admin.dashboard' => ['label' => 'Panel administracyjny', 'group' => 'Panel'],
            'users.view' => ['label' => 'Podglad uzytkownikow', 'group' => 'Uzytkownicy'],
            'users.edit' => ['label' => 'Edycja uzytkownikow i sald', 'group' => 'Uzytkownicy'],
            'users.delete' => ['label' => 'Usuwanie uzytkownikow', 'group' => 'Uzytkownicy'],
            'roles.manage' => ['label' => 'Role i uprawnienia', 'group' => 'Dostepy'],
            'jobs.view' => ['label' => 'Podglad ogloszen', 'group' => 'Ogloszenia'],
            'jobs.create' => ['label' => 'Dodawanie ogloszen z panelu', 'group' => 'Ogloszenia'],
            'jobs.edit' => ['label' => 'Edycja ogloszen', 'group' => 'Ogloszenia'],
            'jobs.delete' => ['label' => 'Usuwanie ogloszen', 'group' => 'Ogloszenia'],
            'messages.moderate' => ['label' => 'Moderacja konwersacji', 'group' => 'Komunikacja'],
            'newsletter.manage' => ['label' => 'Newsletter i wiadomosci masowe', 'group' => 'Marketing'],
            'pages.manage' => ['label' => 'Podstrony statyczne', 'group' => 'CMS'],
            'settings.manage' => ['label' => 'Ustawienia serwisu', 'group' => 'System'],
            'reports.view' => ['label' => 'Raporty', 'group' => 'Raporty'],
            'transactions.view' => ['label' => 'Transakcje', 'group' => 'Finanse'],
        ];
    }

    public static function adminRoles() {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'admin_roles'");
            if ($stmt && $stmt->fetchColumn()) {
                $stmt = $pdo->query("SELECT role_key FROM admin_roles WHERE is_panel_role = 1");
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Throwable $e) {
            error_log('Admin roles read warning: ' . $e->getMessage());
        }

        return ['super_admin', 'admin', 'moderator', 'opiekun', 'reklamodawca'];
    }

    public static function roleLabel($role) {
        $roles = self::roles();
        return $roles[$role] ?? ucfirst((string)$role);
    }

    public static function badgeClass($role) {
        $classes = [
            'super_admin' => 'bg-dark',
            'admin' => 'bg-danger',
            'moderator' => 'bg-info',
            'opiekun' => 'bg-success',
            'reklamodawca' => 'bg-secondary',
            'executor' => 'bg-warning',
            'user' => 'bg-primary',
        ];

        return $classes[$role] ?? 'bg-secondary';
    }

    public function getRolePermissions($role) {
        $stmt = $this->pdo->prepare("SELECT permission_key FROM admin_role_permissions WHERE role = :role AND allowed = 1");
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function addRole($label, $roleKey = '') {
        $label = trim((string)$label);
        $roleKey = trim((string)$roleKey);

        if ($label === '') {
            return ['error' => 'Podaj nazwe roli.'];
        }

        if ($roleKey === '') {
            $roleKey = $this->slugifyRole($label);
        } else {
            $roleKey = $this->slugifyRole($roleKey);
        }

        if ($roleKey === '') {
            return ['error' => 'Nie udalo sie utworzyc klucza roli.'];
        }

        if (array_key_exists($roleKey, self::roles())) {
            return ['error' => 'Rola o takim kluczu juz istnieje.'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO admin_roles (role_key, label, is_system, is_panel_role, permissions_initialized, sort_order, created_at, updated_at)
            VALUES (:role_key, :label, 0, 1, 1, 100, NOW(), NOW())
        ");

        if (!$stmt->execute(['role_key' => $roleKey, 'label' => $label])) {
            return ['error' => 'Nie udalo sie dodac roli.'];
        }

        return ['success' => true, 'role' => $roleKey];
    }

    public function setRolePermissions($role, array $permissions) {
        if (!array_key_exists($role, self::roles())) {
            return false;
        }

        $allowedKeys = array_keys(self::permissions());
        $permissions = array_values(array_intersect($permissions, $allowedKeys));

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM admin_role_permissions WHERE role = :role")->execute(['role' => $role]);

            if (!empty($permissions)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO admin_role_permissions (role, permission_key, allowed, created_at, updated_at)
                    VALUES (:role, :permission_key, 1, NOW(), NOW())
                ");

                foreach ($permissions as $permission) {
                    $stmt->execute(['role' => $role, 'permission_key' => $permission]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Role permissions save error: ' . $e->getMessage());
            return false;
        }
    }

    public function hasPermission($userId, $permissionKey) {
        if (!$userId || !$permissionKey) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $role = $stmt->fetchColumn();

        if (!$role) {
            return false;
        }

        $override = $this->getUserPermissionOverride($userId, $permissionKey);
        if ($override !== null) {
            return $override;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM admin_role_permissions
            WHERE role = :role AND permission_key = :permission_key AND allowed = 1
            LIMIT 1
        ");
        $stmt->execute(['role' => $role, 'permission_key' => $permissionKey]);
        return (bool)$stmt->fetchColumn();
    }

    public function hasAnyAdminAccess($userId) {
        if (!$userId) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $role = $stmt->fetchColumn();

        if (in_array($role, self::adminRoles(), true)) {
            return true;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM admin_role_permissions WHERE role = :role AND allowed = 1 LIMIT 1");
        $stmt->execute(['role' => $role]);
        if ($stmt->fetchColumn()) {
            return true;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM admin_user_permissions WHERE user_id = :user_id AND allowed = 1 LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function permissionForAdminFile($fileName) {
        $map = [
            'dashboard.php' => 'admin.dashboard',
            'access_matrix.php' => 'roles.manage',
            'manage_users.php' => 'users.view',
            'view_user.php' => 'users.view',
            'edit_user.php' => 'users.edit',
            'activate_user.php' => 'users.edit',
            'deactivate_user.php' => 'users.edit',
            'add_points.php' => 'users.edit',
            'bulk_users_action.php' => 'users.edit',
            'export_users.php' => 'users.view',
            'delete_user.php' => 'users.delete',
            'delete_users.php' => 'users.delete',
            'change_role.php' => 'roles.manage',
            'manage_jobs.php' => 'jobs.view',
            'view_job.php' => 'jobs.view',
            'add_job.php' => 'jobs.create',
            'edit_job.php' => 'jobs.edit',
            'delete_conversation.php' => 'messages.moderate',
            'delete_conversations.php' => 'messages.moderate',
            'manage_messages.php' => 'messages.moderate',
            'get_conversation_content.php' => 'messages.moderate',
            'load_conversation.php' => 'messages.moderate',
            'newsletter_manager.php' => 'newsletter.manage',
            'send_bulk_message.php' => 'newsletter.manage',
            'pages.php' => 'pages.manage',
            'site_settings.php' => 'settings.manage',
            'reports.php' => 'reports.view',
            'export_report.php' => 'reports.view',
            'export_pdf.php' => 'reports.view',
            'transactions.php' => 'transactions.view',
            'check_online_status.php' => 'users.view',
        ];

        return $map[$fileName] ?? 'admin.dashboard';
    }

    private function getUserPermissionOverride($userId, $permissionKey) {
        $stmt = $this->pdo->prepare("
            SELECT allowed
            FROM admin_user_permissions
            WHERE user_id = :user_id AND permission_key = :permission_key
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId, 'permission_key' => $permissionKey]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return null;
        }

        return (int)$value === 1;
    }

    private function ensureSchema() {
        try {
            $this->pdo->exec("ALTER TABLE users MODIFY role VARCHAR(40) NOT NULL DEFAULT 'user'");
        } catch (Throwable $e) {
            error_log('Role column migration warning: ' . $e->getMessage());
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_key VARCHAR(40) NOT NULL,
                label VARCHAR(120) NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                is_panel_role TINYINT(1) NOT NULL DEFAULT 1,
                permissions_initialized TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY uniq_admin_roles_key (role_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(40) NOT NULL,
                permission_key VARCHAR(80) NOT NULL,
                allowed TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY uniq_role_permission (role, permission_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_user_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                permission_key VARCHAR(80) NOT NULL,
                allowed TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY uniq_user_permission (user_id, permission_key),
                KEY idx_admin_user_permissions_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function seedDefaultPermissions() {
        $roleStmt = $this->pdo->prepare("
            INSERT IGNORE INTO admin_roles
                (role_key, label, is_system, is_panel_role, permissions_initialized, sort_order, created_at, updated_at)
            VALUES
                (:role_key, :label, 1, :is_panel_role, 0, :sort_order, NOW(), NOW())
        ");

        $sortOrder = 10;
        foreach (self::defaultRoles() as $role => $label) {
            $roleStmt->execute([
                'role_key' => $role,
                'label' => $label,
                'is_panel_role' => in_array($role, ['user', 'executor'], true) ? 0 : 1,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        $defaults = $this->defaultRolePermissions();
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO admin_role_permissions (role, permission_key, allowed, created_at, updated_at)
            VALUES (:role, :permission_key, 1, NOW(), NOW())
        ");

        foreach ($defaults as $role => $permissions) {
            if ($this->isRolePermissionsInitialized($role)) {
                continue;
            }

            foreach ($permissions as $permission) {
                $stmt->execute(['role' => $role, 'permission_key' => $permission]);
            }

            $this->pdo->prepare("UPDATE admin_roles SET permissions_initialized = 1, updated_at = NOW() WHERE role_key = :role")
                ->execute(['role' => $role]);
        }

        foreach (['super_admin', 'admin'] as $role) {
            foreach (array_keys(self::permissions()) as $permission) {
                $stmt->execute(['role' => $role, 'permission_key' => $permission]);
            }
        }
    }

    private function isRolePermissionsInitialized($role) {
        $stmt = $this->pdo->prepare("SELECT permissions_initialized FROM admin_roles WHERE role_key = :role LIMIT 1");
        $stmt->execute(['role' => $role]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function slugifyRole($value) {
        $value = strtolower(trim((string)$value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim($value, '_');
        return substr($value, 0, 40);
    }

    private function defaultRolePermissions() {
        $all = array_keys(self::permissions());

        return [
            'super_admin' => $all,
            'admin' => $all,
            'moderator' => [
                'admin.dashboard',
                'users.view',
                'jobs.view',
                'messages.moderate',
                'reports.view',
            ],
            'opiekun' => [
                'admin.dashboard',
                'users.view',
                'users.edit',
                'jobs.view',
                'jobs.edit',
                'messages.moderate',
            ],
            'reklamodawca' => [
                'admin.dashboard',
                'newsletter.manage',
                'reports.view',
            ],
            'executor' => [],
            'user' => [],
        ];
    }
}
?>
