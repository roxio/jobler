<?php
session_start();
require_once 'config/config.php';
require_once 'models/User.php';
require_once 'models/SiteSettings.php';
require_once 'models/Language.php';

// Pobieranie ustawień strony
$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();
$currentLocale = Language::current('frontend');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $user        = new User();
    $loginResult = $user->login($email, $password);

    if ($loginResult !== false && !isset($loginResult['error'])) {
        // Sukces
        $_SESSION['user_id']              = $loginResult['id'];
        $_SESSION['user_role']            = $loginResult['role'];
        $_SESSION['user_email']           = $loginResult['email'];
        $_SESSION['user_name']            = $loginResult['name'];
        $_SESSION['user_account_balance'] = $loginResult['account_balance'];

        $user->updateLastLogin($loginResult['id']);
        $user->logLoginAttempt($loginResult['id'], $_SERVER['REMOTE_ADDR'], true, $_SERVER['HTTP_USER_AGENT']);

        header('Location: /?lang=' . urlencode($currentLocale));
        exit;
    }

    // Nieudane logowanie — userId może być null jeśli email nie istnieje
    if (isset($loginResult['error'])) {
        $error = $loginResult['error'];
    } else {
        $error = __t('auth.invalid_login');
    }

    // Szukamy user_id tylko jeśli email istnieje w bazie — metoda zwróci null jeśli nie
    $userId = $user->getUserIdByEmail($email); // null gdy brak — to jest OK po poprawce
    $user->logLoginAttempt($userId, $_SERVER['REMOTE_ADDR'], false, $_SERVER['HTTP_USER_AGENT']);
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($currentLocale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteSettings['title'] ?? 'Jobler') ?> - <?= htmlspecialchars(__t('auth.login_title')) ?></title>
    <!-- Bootstrap CSS -->
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
            <!-- Hero section -->
       

            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow-lg border-0">
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h3 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i><?= htmlspecialchars(__t('auth.login_heading')) ?></h3>
                        </div>
                        <div class="card-body p-4">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form action="login.php?lang=<?= urlencode($currentLocale) ?>" method="POST">
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
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label fw-semibold"><?= htmlspecialchars(__t('auth.password_label')) ?>:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-lock text-muted"></i>
                                        </span>
                                        <input type="password" name="password" id="password" class="form-control border-start-0" 
                                               placeholder="<?= htmlspecialchars(__t('auth.password_placeholder')) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-box-arrow-in-right me-2"></i><?= htmlspecialchars(__t('auth.login_button')) ?>
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4 pt-3 border-top">
                                <p class="mb-0">
                                    <?= htmlspecialchars(__t('auth.no_account')) ?>
                                    <a href="/register.php?lang=<?= urlencode($currentLocale) ?>" class="text-primary fw-semibold text-decoration-none">
                                        <?= htmlspecialchars(__t('auth.register_link')) ?>
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
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
