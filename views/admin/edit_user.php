<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php?status=error');
    exit();
}

$userId = (int)$_GET['id'];

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Database.php');
include_once('../../models/Language.php');

$userModel = new User();
$database = Database::getConnection();


try {
    $user = $userModel->getUserById($userId);
    if (!$user) {
        header('Location: manage_users.php?status=error&message=user_not_found');
        exit();
    }
} catch (Exception $e) {
    error_log(__t('admin.logs.fetch_user_error', ['error' => $e->getMessage()]));
    header('Location: manage_users.php?status=error');
    exit();
}


$formData = [];
$formErrors = [];
$successMessage = '';
$resetMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $formErrors[] = __t('admin.edit_user.csrf_error');
    } else {
        $updateData = [];


        if (!empty($_POST['name']) && $_POST['name'] !== $user['name']) {
            $updateData['name'] = trim($_POST['name']);
        }


        if (!empty($_POST['username']) && $_POST['username'] !== $user['username']) {
            $updateData['username'] = trim($_POST['username']);
        }


        if (!empty($_POST['email'])) {
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if ($email !== $user['email']) {
                    $updateData['email'] = $email;
                }
            } else {
                $formErrors[] = __t('admin.edit_user.invalid_email');
            }
        }


        if (isset($_POST['account_balance']) && is_numeric($_POST['account_balance'])) {
            $newBalance = (float)$_POST['account_balance'];
            if ($newBalance != $user['account_balance']) {
                $updateData['account_balance'] = $newBalance;
            }
        }


        if (canAdminAccess('roles.manage') && !empty($_POST['role']) && array_key_exists($_POST['role'], AccessControl::roles())) {
            if ($_POST['role'] !== $user['role']) {
                $updateData['role'] = $_POST['role'];
            }
        }


        if (isset($_POST['status']) && in_array($_POST['status'], ['active', 'inactive'])) {
            if ($_POST['status'] !== $user['status']) {
                $updateData['status'] = $_POST['status'];
            }
        }


        if (empty($formErrors) && !empty($updateData)) {
            try {
                $setParts = [];
                $params = [];
                foreach ($updateData as $field => $value) {
                    $setParts[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
                $sql = "UPDATE users SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :id";
                $params[':id'] = $userId;

                $stmt = $database->prepare($sql);
                if ($stmt->execute($params)) {
                    $successMessage = __t('admin.edit_user.updated');
                    $user = $userModel->getUserById($userId);
                } else {
                    $formErrors[] = __t('admin.edit_user.update_error');
                }
            } catch (Exception $e) {
                $formErrors[] = __t('admin.edit_user.update_exception', ['error' => $e->getMessage()]);
            }
        }


        if (isset($_POST['reset_password']) && $_POST['reset_password'] === '1' && empty($formErrors)) {
            try {
                $resetToken = bin2hex(random_bytes(32));
                $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $stmt = $database->prepare("UPDATE users SET reset_token = :token, reset_expiry = :expiry WHERE id = :id");
                $stmt->execute([
                    ':token' => $resetToken,
                    ':expiry' => $resetExpiry,
                    ':id' => $userId
                ]);
                $resetMessage = __t('admin.edit_user.reset_sent');
            } catch (Exception $e) {
                $formErrors[] = __t('admin.edit_user.reset_error', ['error' => $e->getMessage()]);
            }
        }

        $formData = $_POST;
    }
}

function safeEcho($data, $default = '') {
    return isset($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $default;
}
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= htmlspecialchars(__t('admin.panel')) ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('admin.back_to_list')) ?>
                            </a>
                            <span class="ms-2"><?= htmlspecialchars(__t('admin.edit_user.title', ['name' => $user['name'], 'id' => $userId])) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <?php echo $successMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($resetMessage)): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <?php echo $resetMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($formErrors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <h6 class="alert-heading"><?= htmlspecialchars(__t('admin.edit_user.errors_heading')) ?></h6>
                            <ul class="mb-0">
                                <?php foreach ($formErrors as $error): ?>
                                    <li><?php echo safeEcho($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-pencil"></i> <?= htmlspecialchars(__t('admin.edit_user.form_title')) ?></h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo safeEcho($_SESSION['csrf_token']); ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.edit_user.full_name')) ?></label>
                                        <input type="text" name="name" class="form-control" value="<?php echo safeEcho($formData['name'] ?? $user['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.edit_user.username')) ?></label>
                                        <input type="text" name="username" class="form-control" value="<?php echo safeEcho($formData['username'] ?? $user['username']); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo safeEcho($formData['email'] ?? $user['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.edit_user.account_balance')) ?></label>
                                        <input type="number" name="account_balance" class="form-control" value="<?php echo safeEcho($formData['account_balance'] ?? $user['account_balance']); ?>" step="0.01" min="0">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.role')) ?></label>
                                        <?php if (canAdminAccess('roles.manage')): ?>
                                            <select name="role" class="form-select">
                                                <?php foreach (AccessControl::roles() as $roleKey => $roleLabel): ?>
                                                    <option value="<?= htmlspecialchars($roleKey) ?>" <?php echo (($formData['role'] ?? $user['role']) === $roleKey) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($roleLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars(AccessControl::roleLabel($user['role'])) ?>" readonly>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.edit_user.account_status')) ?></label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?php echo (($formData['status'] ?? $user['status']) === 'active') ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.status.active')) ?></option>
                                            <option value="inactive" <?php echo (($formData['status'] ?? $user['status']) === 'inactive') ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.status.inactive')) ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.registration_date')) ?></label>
                                        <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>" disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.last_login')) ?></label>
                                        <input type="text" class="form-control" value="<?php echo !empty($user['last_login']) ? date('Y-m-d H:i', strtotime($user['last_login'])) : htmlspecialchars(__t('admin.common.never')); ?>" disabled>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Telefon</label>
                                        <input type="text" class="form-control" value="<?php echo safeEcho($user['phone'] ?? 'Brak'); ?>" disabled>
                                        <div class="form-text"><?= htmlspecialchars(__t('admin.edit_user.phone_readonly')) ?></div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.edit_user.reset_password')) ?></label>
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                                <i class="bi bi-key"></i> <?= htmlspecialchars(__t('admin.edit_user.send_reset_link')) ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="manage_users.php" class="btn btn-secondary"><?= htmlspecialchars(__t('admin.users.cancel')) ?></a>
                                    <button type="submit" class="btn btn-primary"><?= htmlspecialchars(__t('admin.save_changes')) ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars(__t('admin.edit_user.reset_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo safeEcho($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="reset_password" value="1">
                <div class="modal-body">
                    <p><?= htmlspecialchars(__t('admin.edit_user.reset_confirm', ['email' => $user['email']])) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(__t('admin.users.cancel')) ?></button>
                    <button type="submit" class="btn btn-warning"><?= htmlspecialchars(__t('admin.edit_user.send_link')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>
