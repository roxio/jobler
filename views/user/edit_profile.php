<?php
session_start();

include_once('../../models/User.php');
include_once('../../models/Database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userModel = new User();
$pdo = Database::getConnection();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage = '';
$user = $userModel->getUserById($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errorMessage = 'Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.';
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

            $errorMessage = $result['error'] ?? 'Nie udało się zapisać profilu.';
        }

        if ($action === 'change_password') {
            $password = (string)($_POST['password'] ?? '');
            $passwordRepeat = (string)($_POST['password_repeat'] ?? '');

            if (strlen($password) < 8) {
                $errorMessage = 'Hasło musi mieć co najmniej 8 znaków.';
            } elseif ($password !== $passwordRepeat) {
                $errorMessage = 'Hasła nie są takie same.';
            } elseif ($userModel->changePassword($userId, $password)) {
                $successMessage = 'Hasło zostało zmienione.';
            } else {
                $errorMessage = 'Nie udało się zmienić hasła.';
            }
        }

        $user = $userModel->getUserById($userId);
    }
}

include('../partials/header.php');
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edycja profilu</h1>
            <p class="text-muted mb-0">Zmień bieżące dane kontaktowe. Dane z pierwszej rejestracji pozostają zachowane dla administracji.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Wróć do kokpitu</a>
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
                    <h2 class="h5 mb-0">Dane bieżące</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Imię / nazwa</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nazwa użytkownika</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefon</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="15">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="newsletter_subscription" value="1" id="newsletter" <?= !empty($user['newsletter_subscription']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="newsletter">Chcę otrzymywać powiadomienia i newsletter</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-4">Zapisz dane</button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Zmiana hasła</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nowe hasło</label>
                                <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Powtórz nowe hasło</label>
                                <input type="password" name="password_repeat" class="form-control" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-primary mt-4">Zmień hasło</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0">Dane z rejestracji</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Te dane są zachowane jako pierwotny zapis konta i nie są zmieniane przez formularz profilu.</p>
                    <dl class="row small mb-0">
                        <dt class="col-5">Imię / nazwa</dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_name'] ?? '-') ?></dd>
                        <dt class="col-5">Login</dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_username'] ?? '-') ?></dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_email'] ?? '-') ?></dd>
                        <dt class="col-5">Telefon</dt>
                        <dd class="col-7"><?= htmlspecialchars($user['original_phone'] ?: '-') ?></dd>
                        <dt class="col-5">IP rejestracji</dt>
                        <dd class="col-7"><code><?= htmlspecialchars($user['registration_ip'] ?? '-') ?></code></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../partials/footer.php'); ?>
