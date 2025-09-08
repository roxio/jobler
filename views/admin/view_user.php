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
include_once('../../models/Job.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Message.php');

$userModel = new User();
$jobModel = new Job();
$transactionModel = new TransactionHistory($pdo);
$messageModel = new Message();

try {
    //Pobierz szczegóły użytkownika
    $user = $userModel->getUserById($userId);
    
     if (!$user) {
         header('Location: manage_users.php?status=error&message=user_not_found');
         exit();
    }
	
    
    //Pobierz dodatkowe informacje
    // $userJobs = $jobModel->getJobsByUserId($userId, 5); // Ostatnie 5 ogłoszeń
     $userTransactions = $transactionModel->getUserTransactions($userId, 10); // Ostatnie 10 transakcji
     $userStats = $userModel->getUserStatistics($userId);
     $loginHistory = $userModel->getLoginHistory($userId, 10); // Ostatnie 10 logowań
    
} catch (Exception $e) {
     error_log("Błąd przy pobieraniu danych użytkownika: " . $e->getMessage());
     header('Location: manage_users.php?status=error');
     exit();
}

function safeEcho($data, $default = '') {
    return isset($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $default;
}

function formatDate($date) {
    if (!$date) return 'Nigdy';
    return date('Y-m-d H:i', strtotime($date));
}

function formatBalance($balance) {
    return number_format(floatval($balance), 2) . ' pkt';
}

// Sprawdź czy użytkownik jest online (ostatnie 15 minut)
$isOnline = false;
if (!empty($user['last_login'])) {
    $lastLogin = strtotime($user['last_login']);
    $isOnline = (time() - $lastLogin) < 900; // 15 minut
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
                    <!-- Nagłówek z powrotem i akcjami -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Powrót do listy
                            </a>
                            <span class="ms-2">Podgląd użytkownika</span>
                        </div>
                        <div class="btn-group">
                            <a href="edit_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-pencil"></i> Edytuj
                            </a>
                            <?php if ($user['status'] == 'active'): ?>
                                <a href="deactivate_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-secondary" 
                                   onclick="return confirm('Czy na pewno chcesz dezaktywować tego użytkownika?');">
                                    <i class="bi bi-person-x"></i> Dezaktywuj
                                </a>
                            <?php else: ?>
                                <a href="activate_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-person-check"></i> Aktywuj
                                </a>
                            <?php endif; ?>
                            <a href="delete_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Czy na pewno chcesz usunąć tego użytkownika? Ta operacja jest nieodwracalna.');">
                                <i class="bi bi-trash"></i> Usuń
                            </a>
                        </div>
                    </div>

                    <!-- Alerty -->
                    <?php if (isset($_GET['status'])): ?>
                        <div class="alert <?= $_GET['status'] == 'error' ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show mb-4" role="alert">
                            <?php
                            $messages = [
                                'points_added' => '✅ Punkty zostały dodane.',
                                'role_changed' => '✅ Rola użytkownika została zmieniona.',
                                'activated' => '✅ Konto zostało aktywowane.',
                                'deactivated' => '✅ Konto zostało dezaktywowane.',
                                'error' => '❌ Wystąpił błąd.'
                            ];
                            echo safeEcho($messages[$_GET['status']] ?? '');
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Lewa kolumna - informacje podstawowe -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-person"></i> Informacje podstawowe</h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <img src="<?= !empty($user['avatar']) ? safeEcho($user['avatar']) : '../../assets/img/default-avatar.png'; ?>" 
                                             class="rounded-circle mb-2" width="100" height="100" alt="Avatar">
                                        <h5><?= safeEcho($user['name']) ?></h5>
                                        <span class="badge bg-<?= $isOnline ? 'success' : 'secondary'; ?>">
                                            <?= $isOnline ? 'Online' : 'Offline'; ?>
                                        </span>
                                    </div>

                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>ID:</strong></td>
                                            <td>#<?= $userId ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td>
                                                <?= safeEcho($user['email']) ?>
                                                <?php if (!empty($user['email_verified_at'])): ?>
                                                    <span class="badge bg-success ms-1" title="Email zweryfikowany">✓</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Rola:</strong></td>
                                            <td>
                                                <span class="badge <?= $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'executor' ? 'bg-warning' : 'bg-primary'); ?>">
                                                    <?= safeEcho(ucfirst($user['role'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?= $user['status'] == 'active' ? 'Aktywny' : 'Nieaktywny'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Data rejestracji:</strong></td>
                                            <td><?= formatDate($user['created_at']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ostatnie logowanie:</strong></td>
                                            <td><?= formatDate($user['last_login'] ?? null) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Saldo:</strong></td>
                                            <td class="fw-bold <?= ($user['account_balance'] ?? 0) > 0 ? 'text-success' : 'text-muted'; ?>">
                                                <?= formatBalance($user['account_balance'] ?? 0) ?>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Szybkie akcje -->
                                    <div class="mt-3">
                                        <form action="add_points.php" method="POST" class="mb-2">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="points_to_add" class="form-control" placeholder="Punkty" min="1" max="1000" required>
                                                <button type="submit" class="btn btn-success">Dodaj</button>
                                            </div>
                                        </form>

                                        <?php if (!empty($user['need_change'])): ?>
                                            <form action="change_role.php" method="POST" class="mb-2">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $userId ?>">
                                                <input type="hidden" name="current_role" value="<?= safeEcho($user['role']) ?>">
                                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                                    <i class="bi bi-arrow-repeat"></i> Zmień rolę
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <button class="btn btn-info btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                                            <i class="bi bi-envelope"></i> Wyślij wiadomość
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Informacje techniczne -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Informacje techniczne</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>IP rejestracji:</strong></td>
                                            <td><code><?= safeEcho($user['registration_ip'] ?? 'Brak') ?></code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ostatnie IP:</strong></td>
                                            <td><code><?= safeEcho($user['last_login_ip'] ?? 'Brak') ?></code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Przeglądarka:</strong></td>
                                            <td><small><?= safeEcho($user['user_agent'] ?? 'Brak') ?></small></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Prawa kolumna - statystyki i aktywność -->
                        <div class="col-md-8">
                            <!-- Statystyki -->
                            <div class="row mb-4">
                                <div class="col-md-3 col-6">
                                    <div class="card bg-primary text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['total_jobs'] ?? 0 ?></h5>
                                            <small>Ogłoszenia</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card bg-success text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['active_jobs'] ?? 0 ?></h5>
                                            <small>Aktywne</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card bg-info text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['total_transactions'] ?? 0 ?></h5>
                                            <small>Transakcje</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card bg-warning text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['total_messages'] ?? 0 ?></h5>
                                            <small>Wiadomości</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ostatnie ogłoszenia -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-briefcase"></i> Ostatnie ogłoszenia</h6>
                                    <a href="../admin/manage_jobs.php?user_id=<?= $userId ?>" class="btn btn-sm btn-outline-primary">
                                        Zobacz wszystkie
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($userJobs)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Tytuł</th>
                                                        <th>Status</th>
                                                        <th>Data</th>
                                                        <th>Ofert</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($userJobs as $job): ?>
                                                        <tr>
                                                            <td>
                                                                <a href="../jobs/view.php?id=<?= $job['id'] ?>" target="_blank" class="text-decoration-none">
                                                                    <?= safeEcho(mb_substr($job['title'], 0, 30)) ?><?= mb_strlen($job['title']) > 30 ? '...' : '' ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $job['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?= safeEcho($job['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= formatDate($job['created_at']) ?></td>
                                                            <td><?= $job['offer_count'] ?? 0 ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0">Brak ogłoszeń</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Ostatnie transakcje -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-currency-exchange"></i> Ostatnie transakcje</h6>
                                    <a href="../admin/transactions.php?user_id=<?= $userId ?>" class="btn btn-sm btn-outline-primary">
                                        Zobacz wszystkie
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($userTransactions)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Typ</th>
                                                        <th>Kwota</th>
                                                        <th>Status</th>
                                                        <th>Data</th>
                                                        <th>Opis</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($userTransactions as $transaction): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-<?= $transaction['type'] == 'income' ? 'success' : 'danger'; ?>">
                                                                    <?= safeEcho($transaction['type']) ?>
                                                                </span>
                                                            </td>
                                                            <td class="fw-bold <?= $transaction['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                                                <?= number_format($transaction['amount'], 2) ?> PLN
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $transaction['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                                    <?= safeEcho($transaction['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= formatDate($transaction['created_at']) ?></td>
                                                            <td><?= safeEcho(mb_substr($transaction['description'], 0, 20)) ?><?= mb_strlen($transaction['description']) > 20 ? '...' : '' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0">Brak transakcji</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Historia logowań -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-clock-history"></i> Ostatnie logowania</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($loginHistory)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Data</th>
                                                        <th>IP</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($loginHistory as $login): ?>
                                                        <tr>
                                                            <td><?= formatDate($login['login_time']) ?></td>
                                                            <td><code><?= safeEcho($login['ip_address']) ?></code></td>
                                                            <td>
                                                                <span class="badge bg-<?= $login['success'] ? 'success' : 'danger'; ?>">
                                                                    <?= $login['success'] ? 'Sukces' : 'Błąd' ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0">Brak historii logowań</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal do wysyłania wiadomości -->
<div class="modal fade" id="sendMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Wyślij wiadomość do <?= safeEcho($user['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../admin/send_message.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Temat</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Wiadomość</label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Typ wiadomości</label>
                        <select name="message_type" class="form-select">
                            <option value="notification">Powiadomienie</option>
                            <option value="information">Informacja</option>
                            <option value="warning">Ostrzeżenie</option>
                            <option value="promotion">Promocja</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">Wyślij wiadomość</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>

<script>
// Inicjalizacja tooltipów
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>