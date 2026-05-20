<?php
session_start();

include_once('../../models/User.php');
include_once('../../models/Executor.php');
include_once('../../models/Database.php');
include_once('../../models/Language.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userModel = new User();
$executorModel = new Executor();
$pdo = Database::getConnection();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage = '';
$user = $userModel->getUserById($userId);
$isExecutor = ($user['role'] ?? '') === 'executor';
$executorCategoryIds = $isExecutor ? $executorModel->getExecutorCategoryIds($userId) : [];
$executorCategoryFilterEnabled = $isExecutor ? $executorModel->isCategoryFilterEnabled($userId) : true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errorMessage = __t('user.job_form.csrf_error');
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $result = $userModel->updateProfile($userId, [
                'name' => $_POST['name'] ?? '',
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'newsletter_subscription' => isset($_POST['newsletter_subscription']) ? 1 : 0,
            ]);

            if (!empty($result['success'])) {
                header('Location: dashboard.php?status=profile_saved');
                exit;
            }

            $errorMessage = $result['error'] ?? __t('user.profile_edit.save_error');
        }

        if ($action === 'change_password') {
            $password = (string)($_POST['password'] ?? '');
            $passwordRepeat = (string)($_POST['password_repeat'] ?? '');

            if (strlen($password) < 8) {
                $errorMessage = __t('user.profile_edit.password_min');
            } elseif ($password !== $passwordRepeat) {
                $errorMessage = __t('user.profile_edit.password_mismatch');
            } elseif ($userModel->changePassword($userId, $password)) {
                $successMessage = __t('user.profile_edit.password_changed');
            } else {
                $errorMessage = __t('user.profile_edit.password_error');
            }
        }

        if ($action === 'executor_settings' && $isExecutor) {
            $selectedCategories = array_values(array_unique(array_map('intval', $_POST['executor_categories'] ?? [])));
            $selectedCategories = array_filter($selectedCategories, fn($id) => $id > 0);

            if (count($selectedCategories) > 10) {
                $errorMessage = __t('executor.categories_limit');
            } else {
                $savedCategories = $executorModel->saveExecutorCategories($userId, $selectedCategories);
                $savedFilter = $executorModel->setCategoryFilterEnabled($userId, isset($_POST['executor_category_filter_enabled']));

                if ($savedCategories && $savedFilter) {
                    $successMessage = __t('executor.settings_saved');
                    $executorCategoryIds = $executorModel->getExecutorCategoryIds($userId);
                    $executorCategoryFilterEnabled = $executorModel->isCategoryFilterEnabled($userId);
                } else {
                    $errorMessage = __t('executor.settings_error');
                }
            }
        }

        $user = $userModel->getUserById($userId);
        $isExecutor = ($user['role'] ?? '') === 'executor';
    }
}

include('../partials/header.php');
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('user.profile_edit.title')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('user.profile_edit.intro')) ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('user.back_dashboard')) ?></a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.profile_edit.current_data')) ?></h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?= htmlspecialchars(__t('user.profile_edit.name')) ?></label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= htmlspecialchars(__t('user.username')) ?></label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= htmlspecialchars(__t('user.phone')) ?></label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="15">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="newsletter_subscription" value="1" id="newsletter" <?= !empty($user['newsletter_subscription']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="newsletter"><?= htmlspecialchars(__t('user.profile_edit.newsletter')) ?></label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-4"><?= htmlspecialchars(__t('user.profile_edit.save_data')) ?></button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.profile_edit.password_change')) ?></h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?= htmlspecialchars(__t('user.profile_edit.new_password')) ?></label>
                                <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= htmlspecialchars(__t('user.profile_edit.repeat_password')) ?></label>
                                <input type="password" name="password_repeat" class="form-control" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-primary mt-4"><?= htmlspecialchars(__t('user.profile_edit.change_password')) ?></button>
                    </form>
                </div>
            </div>

            <?php if ($isExecutor): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><?= htmlspecialchars(__t('executor.settings_title')) ?></h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="executor_settings">

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="executor_category_filter_enabled" value="1" id="executorCategoryFilter" <?= $executorCategoryFilterEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="executorCategoryFilter"><?= htmlspecialchars(__t('executor.category_filter_enabled')) ?></label>
                            </div>

                            <label class="form-label"><?= htmlspecialchars(__t('executor.categories_label')) ?></label>
                            <div class="executor-category-picker">
                                <?php foreach ($categories as $category): ?>
                                    <label class="executor-category-chip">
                                        <input type="checkbox" name="executor_categories[]" value="<?= (int)$category['id'] ?>" <?= in_array((int)$category['id'], $executorCategoryIds, true) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($category['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text"><?= htmlspecialchars(__t('executor.categories_help')) ?></div>

                            <button type="submit" class="btn btn-primary mt-3"><?= htmlspecialchars(__t('user.profile_edit.save_data')) ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.profile_edit.registration_data')) ?></h2>
                </div>
                <div class="card-body">
                    <p class="text-muted small"><?= htmlspecialchars(__t('user.profile_edit.registration_hint')) ?></p>
                    <dl class="row small mb-0">
                        <dt class="col-5"><?= htmlspecialchars(__t('user.profile_edit.name')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_name'] ?? '-') ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.profile_edit.login')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_username'] ?? '-') ?></dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_email'] ?? '-') ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.phone')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_phone'] ?: '-') ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.profile_edit.registration_ip')) ?></dt>
                        <dd class="col-7"><code><?= htmlspecialchars($user['registration_ip'] ?? '-') ?></code></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const picker = document.querySelector('.executor-category-picker');
    if (!picker) return;

    const checkboxes = Array.from(picker.querySelectorAll('input[type="checkbox"]'));
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selected = checkboxes.filter(item => item.checked);
            if (selected.length > 10) {
                this.checked = false;
                alert(<?= json_encode(__t('executor.categories_limit'), JSON_UNESCAPED_UNICODE) ?>);
            }
        });
    });
});
</script>

<?php include('../partials/footer.php'); ?>
