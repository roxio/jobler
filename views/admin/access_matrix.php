<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess('roles.manage');
require_once dirname(__DIR__, 2) . '/models/Language.php';

$accessControl = currentAccessControl();
$roles = AccessControl::roles();
$permissions = AccessControl::permissions();
$status = $_GET['status'] ?? '';
$selectedRole = $_GET['role'] ?? 'moderator';

if (!array_key_exists($selectedRole, $roles)) {
    $selectedRole = 'moderator';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_permissions';

    if ($action === 'add_role') {
        $result = $accessControl->addRole($_POST['role_label'] ?? '', $_POST['role_key'] ?? '');
        if (!empty($result['success'])) {
            header('Location: access_matrix.php?role=' . urlencode($result['role']) . '&status=role_added');
            exit;
        }

        header('Location: access_matrix.php?role=' . urlencode($selectedRole) . '&status=role_error');
        exit;
    }

    $role = $_POST['role'] ?? '';
    $selectedPermissions = $_POST['permissions'] ?? [];

    if (!array_key_exists($role, $roles)) {
        header('Location: access_matrix.php?status=invalid_role');
        exit;
    }

    if (in_array($role, ['user', 'executor'], true)) {
        $selectedPermissions = [];
    }

    $saved = $accessControl->setRolePermissions($role, $selectedPermissions);
    header('Location: access_matrix.php?role=' . urlencode($role) . '&status=' . ($saved ? 'saved' : 'error'));
    exit;
}

$rolePermissions = $accessControl->getRolePermissions($selectedRole);
$rolePermissionsMap = array_fill_keys($rolePermissions, true);

$groupedPermissions = [];
foreach ($permissions as $key => $permission) {
    $groupedPermissions[$permission['group']][$key] = $permission;
}

function safeEcho($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

include '../partials/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-shield-lock"></i> <?= htmlspecialchars(__t('admin.access.title')) ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <?php if ($status): ?>
                        <?php
                        $statusMessages = [
                            'saved' => ['alert-success', __t('admin.access.saved')],
                            'role_added' => ['alert-success', __t('admin.access.role_added')],
                            'error' => ['alert-danger', __t('admin.access.error')],
                            'role_error' => ['alert-danger', __t('admin.access.role_error')],
                            'invalid_role' => ['alert-danger', __t('admin.access.invalid_role')],
                        ];
                        [$alertClass, $message] = $statusMessages[$status] ?? ['alert-danger', __t('admin.access.generic_error')];
                        ?>
                        <div class="alert <?= $alertClass ?> alert-dismissible fade show" role="alert">
                            <?= safeEcho($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <div class="col-lg-3">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><?= htmlspecialchars(__t('admin.access.add_role')) ?></h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="vstack gap-2">
                                        <input type="hidden" name="action" value="add_role">
                                        <div>
                                            <label class="form-label small"><?= htmlspecialchars(__t('admin.access.role_name')) ?></label>
                                            <input type="text" name="role_label" class="form-control form-control-sm" maxlength="120" required>
                                        </div>
                                        <div>
                                            <label class="form-label small"><?= htmlspecialchars(__t('admin.access.role_key')) ?></label>
                                            <input type="text" name="role_key" class="form-control form-control-sm" maxlength="40" placeholder="<?= htmlspecialchars(__t('admin.access.role_key_placeholder')) ?>">
                                        </div>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-plus-circle me-1"></i><?= htmlspecialchars(__t('admin.access.add')) ?>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="list-group">
                                <?php foreach ($roles as $roleKey => $roleLabel): ?>
                                    <a href="access_matrix.php?role=<?= urlencode($roleKey) ?>"
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $selectedRole === $roleKey ? 'active' : '' ?>">
                                        <span><?= safeEcho($roleLabel) ?></span>
                                        <span class="badge <?= AccessControl::badgeClass($roleKey) ?>"><?= safeEcho($roleKey) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-lg-9">
                            <form method="POST" class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars(__t('admin.access.permissions_for', ['role' => $roles[$selectedRole]])) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars(__t('admin.access.affects_all')) ?></small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i><?= htmlspecialchars(__t('admin.access.save')) ?>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="action" value="save_permissions">
                                    <input type="hidden" name="role" value="<?= safeEcho($selectedRole) ?>">

                                    <?php if (in_array($selectedRole, ['user', 'executor'], true)): ?>
                                        <div class="alert alert-info">
                                            <?= htmlspecialchars(__t('admin.access.no_admin_access')) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php foreach ($groupedPermissions as $group => $items): ?>
                                        <div class="mb-4">
                                            <h6 class="border-bottom pb-2 mb-3"><?= safeEcho($group) ?></h6>
                                            <div class="row g-3">
                                                <?php foreach ($items as $permissionKey => $permission): ?>
                                                    <div class="col-md-6">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input"
                                                                   type="checkbox"
                                                                   role="switch"
                                                                   id="perm_<?= safeEcho(str_replace('.', '_', $permissionKey)) ?>"
                                                                   name="permissions[]"
                                                                   value="<?= safeEcho($permissionKey) ?>"
                                                                   <?= isset($rolePermissionsMap[$permissionKey]) ? 'checked' : '' ?>
                                                                   <?= in_array($selectedRole, ['user', 'executor'], true) ? 'disabled' : '' ?>>
                                                            <label class="form-check-label" for="perm_<?= safeEcho(str_replace('.', '_', $permissionKey)) ?>">
                                                                <?= safeEcho($permission['label']) ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
