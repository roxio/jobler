<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień do przeglądania tej strony.';
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php?status=error');
    exit();
}

$userId = (int)$_GET['id'];

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Database.php');

$userModel = new User();
$database = Database::getConnection();

// Pobierz dane użytkownika
try {
    $user = $userModel->getUserById($userId);
    if (!$user) {
        header('Location: manage_users.php?status=error&message=user_not_found');
        exit();
    }
} catch (Exception $e) {
    error_log("Błąd przy pobieraniu danych użytkownika: " . $e->getMessage());
    header('Location: manage_users.php?status=error');
    exit();
}

// Inicjalizacja
$formData = [];
$formErrors = [];
$successMessage = '';
$resetMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $formErrors[] = "❌ Błąd bezpieczeństwa: Nieprawidłowy token CSRF.";
    } else {
        $updateData = [];

        // Imię i nazwisko
        if (!empty($_POST['name']) && $_POST['name'] !== $user['name']) {
            $updateData['name'] = trim($_POST['name']);
        }

        // Username
        if (!empty($_POST['username']) && $_POST['username'] !== $user['username']) {
            $updateData['username'] = trim($_POST['username']);
        }

        // Email
        if (!empty($_POST['email'])) {
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if ($email !== $user['email']) {
                    $updateData['email'] = $email;
                }
            } else {
                $formErrors[] = "❌ Podano nieprawidłowy adres email.";
            }
        }

        // Saldo
        if (isset($_POST['account_balance']) && is_numeric($_POST['account_balance'])) {
            $newBalance = (float)$_POST['account_balance'];
            if ($newBalance != $user['account_balance']) {
                $updateData['account_balance'] = $newBalance;
            }
        }

        // Rola
        if (!empty($_POST['role']) && in_array($_POST['role'], ['user', 'executor', 'admin'])) {
            if ($_POST['role'] !== $user['role']) {
                $updateData['role'] = $_POST['role'];
            }
        }

        // Status
        if (isset($_POST['status']) && in_array($_POST['status'], ['active', 'inactive'])) {
            if ($_POST['status'] !== $user['status']) {
                $updateData['status'] = $_POST['status'];
            }
        }

        // Aktualizacja danych
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
                    $successMessage = "✅ Dane użytkownika zostały zaktualizowane.";
                    $user = $userModel->getUserById($userId);
                } else {
                    $formErrors[] = "❌ Nie udało się zaktualizować danych użytkownika.";
                }
            } catch (Exception $e) {
                $formErrors[] = "❌ Błąd podczas aktualizacji danych: " . $e->getMessage();
            }
        }

        // Reset hasła
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
                $resetMessage = "✅ Link do resetowania hasła został wysłany na adres email użytkownika.";
            } catch (Exception $e) {
                $formErrors[] = "❌ Błąd podczas generowania linku resetującego: " . $e->getMessage();
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
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <!-- Nagłówek -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Powrót do listy
                            </a>
                            <span class="ms-2">Edycja użytkownika: <?php echo safeEcho($user['name']); ?> (ID: <?php echo $userId; ?>)</span>
                        </div>
                    </div>

                    <!-- Komunikaty -->
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
                            <h6 class="alert-heading">Wystąpiły błędy:</h6>
                            <ul class="mb-0">
                                <?php foreach ($formErrors as $error): ?>
                                    <li><?php echo safeEcho($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Formularz edycji -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-pencil"></i> Edytuj dane użytkownika</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo safeEcho($_SESSION['csrf_token']); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Imię i nazwisko</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo safeEcho($formData['name'] ?? $user['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nazwa użytkownika</label>
                                        <input type="text" name="username" class="form-control" value="<?php echo safeEcho($formData['username'] ?? $user['username']); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo safeEcho($formData['email'] ?? $user['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Saldo konta</label>
                                        <input type="number" name="account_balance" class="form-control" value="<?php echo safeEcho($formData['account_balance'] ?? $user['account_balance']); ?>" step="0.01" min="0">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Rola</label>
                                        <select name="role" class="form-select">
                                            <option value="user" <?php echo (($formData['role'] ?? $user['role']) === 'user') ? 'selected' : ''; ?>>Użytkownik</option>
                                            <option value="executor" <?php echo (($formData['role'] ?? $user['role']) === 'executor') ? 'selected' : ''; ?>>Wykonawca</option>
                                            <option value="admin" <?php echo (($formData['role'] ?? $user['role']) === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status konta</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?php echo (($formData['status'] ?? $user['status']) === 'active') ? 'selected' : ''; ?>>Aktywny</option>
                                            <option value="inactive" <?php echo (($formData['status'] ?? $user['status']) === 'inactive') ? 'selected' : ''; ?>>Nieaktywny</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Data rejestracji</label>
                                        <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>" disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ostatnie logowanie</label>
                                        <input type="text" class="form-control" value="<?php echo !empty($user['last_login']) ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Nigdy'; ?>" disabled>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Telefon</label>
                                        <input type="text" class="form-control" value="<?php echo safeEcho($user['phone'] ?? 'Brak'); ?>" disabled>
                                        <div class="form-text">Numer telefonu nie może być zmieniony.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Reset hasła</label>
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                                <i class="bi bi-key"></i> Wyślij link resetujący
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="manage_users.php" class="btn btn-secondary">Anuluj</a>
                                    <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
				<div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
			<div class="stupidbottomm"> </div>
        </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal resetowania hasła -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resetowanie hasła</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo safeEcho($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="reset_password" value="1">
                <div class="modal-body">
                    <p>Czy wysłać link resetujący na <strong><?php echo safeEcho($user['email']); ?></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-warning">Wyślij link</button>
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
