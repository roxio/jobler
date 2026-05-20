<?php
session_start();
require_once 'config/config.php';
require_once 'models/User.php';
require_once 'models/SiteSettings.php';
require_once 'models/Language.php';


$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();
$currentLocale = Language::current('frontend');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $role = in_array($_POST['role'] ?? 'user', ['user', 'executor'], true) ? $_POST['role'] : 'user';
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';


    $user = new User();


    $result = $user->register($email, $password, $name, $username, $role, $phone);

    if (isset($result['success'])) {
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['user_role'] = $result['role'];
        header('Location: /?lang=' . urlencode($currentLocale));
        exit;
    } else {
        $error = $result['error'];
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($currentLocale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteSettings['title'] ?? 'Jobler') ?> - <?= htmlspecialchars(__t('auth.register_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/navbar.php';
    ?>

    <main class="flex-grow-1">
        <div class="container mt-4 mb-5">


            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7">
                    <div class="card shadow-lg border-0">
                        <div class="card-header bg-success text-white text-center py-3">
                            <h3 class="mb-0"><i class="bi bi-person-plus me-2"></i><?= htmlspecialchars(__t('auth.register_heading')) ?></h3>
                        </div>
                        <div class="card-body p-4">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
                                </div>
                            <?php endif; ?>

                            <form action="register.php?lang=<?= urlencode($currentLocale) ?>" method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label fw-semibold"><?= htmlspecialchars(__t('auth.name_label')) ?>:</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-person text-muted"></i>
                                            </span>
                                            <input type="text" name="name" id="name" class="form-control border-start-0"
                                                   placeholder="<?= htmlspecialchars(__t('auth.name_placeholder')) ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label fw-semibold"><?= htmlspecialchars(__t('auth.username_label')) ?>:</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-at text-muted"></i>
                                            </span>
                                            <input type="text" name="username" id="username" class="form-control border-start-0"
                                                   placeholder="<?= htmlspecialchars(__t('auth.username_placeholder')) ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label fw-semibold"><?= htmlspecialchars(__t('auth.email_label')) ?>:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-envelope text-muted"></i>
                                        </span>
                                        <input type="email" name="email" id="email" class="form-control border-start-0"
                                               placeholder="<?= htmlspecialchars(__t('auth.email_placeholder')) ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold"><?= htmlspecialchars(__t('auth.password_label')) ?>:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-lock text-muted"></i>
                                        </span>
                                        <input type="password" name="password" id="password" class="form-control border-start-0"
                                               placeholder="<?= htmlspecialchars(__t('auth.password_placeholder')) ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label fw-semibold"><?= htmlspecialchars(__t('auth.role_label')) ?>:</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-person-badge text-muted"></i>
                                            </span>
                                            <select name="role" id="role" class="form-select border-start-0" required>
                                                <option value="user"><?= htmlspecialchars(__t('auth.role_user')) ?></option>
                                                <option value="executor"><?= htmlspecialchars(__t('auth.role_executor')) ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <label for="phone" class="form-label fw-semibold"><?= htmlspecialchars(__t('auth.phone_label')) ?>:</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-telephone text-muted"></i>
                                            </span>
                                            <input type="text" name="phone" id="phone" class="form-control border-start-0"
                                                   placeholder="<?= htmlspecialchars(__t('auth.phone_placeholder')) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-person-plus me-2"></i><?= htmlspecialchars(__t('auth.register_button')) ?>
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4 pt-3 border-top">
                                <p class="mb-0">
                                    <?= htmlspecialchars(__t('auth.has_account')) ?>
                                    <a href="/login.php?lang=<?= urlencode($currentLocale) ?>" class="text-success fw-semibold text-decoration-none">
                                        <?= htmlspecialchars(__t('auth.login_link')) ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
