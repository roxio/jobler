<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień do przeglądania tej strony.';
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/TransactionHistory.php');

// Inicjalizacja modeli
$userModel = new User();
$transactionModel = new TransactionHistory($pdo);

// Parametry paginacji i sortowania
$limit = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$allowedSortColumns = ['id', 'name', 'email', 'role', 'created_at', 'account_balance', 'registration_ip', 'last_login', 'status'];
$sortColumn = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

// Filtry
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$roleFilter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$balanceMin = isset($_GET['balance_min']) ? (float)$_GET['balance_min'] : '';
$balanceMax = isset($_GET['balance_max']) ? (float)$_GET['balance_max'] : '';
$hasJobs = isset($_GET['has_jobs']) ? $_GET['has_jobs'] : '';
$isVerified = isset($_GET['is_verified']) ? $_GET['is_verified'] : '';

try {
    // Pobieranie danych użytkowników z filtrami
    $total_users = $userModel->getTotalUsersWithFilters($search, $statusFilter, $roleFilter, $dateFrom, $dateTo, $balanceMin, $balanceMax, $hasJobs, $isVerified);
    $users = $userModel->getPaginatedUsersWithFilters($limit, $offset, $sortColumn, $sortOrder, $search, $statusFilter, $roleFilter, $dateFrom, $dateTo, $balanceMin, $balanceMax, $hasJobs, $isVerified);
    $totalPages = ceil($total_users / $limit);
    
    // Statystyki
    $executors_count = $userModel->countUsersByRole('executor');
    $clients_count = $userModel->countUsersByRole('user');
    $admin_count = $userModel->countUsersByRole('admin');
    $active_users = $userModel->countUsersByStatus('active');
    $need_attention = $userModel->countUsersNeedingAttention();
    $users_with_jobs = $userModel->countUsersWithJobs();
    $verified_users = $userModel->countVerifiedUsers();
    
} catch (Exception $e) {
    error_log("Błąd przy pobieraniu użytkowników: " . $e->getMessage());
    $error = "Wystąpił błąd przy pobieraniu danych. Proszę spróbować później.";
    $users = [];
    $totalPages = 1;
    $total_users = $executors_count = $clients_count = $admin_count = $active_users = $need_attention = $users_with_jobs = $verified_users = 0;
}

function safeEcho($data, $default = '') {
    return isset($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $default;
}

function buildUrl($params = []) {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return '?' . http_build_query($currentParams);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
                    <?php if (isset($_GET['status'])): ?>
                        <div class="alert <?php echo ($_GET['status'] == 'error' || $_GET['status'] == 'error_points') ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                            <?php 
                                $messages = [
                                    'deleted' => '✅ Wybrani użytkownicy zostali pomyślnie usunięci.',
                                    'activated' => '✅ Konto użytkownika zostało aktywowane.',
                                    'deactivated' => '✅ Konto użytkownika zostało dezaktywowane.',
                                    'error' => '❌ Wystąpił błąd. Spróbuj ponownie.',
                                    'points_added' => '✅ Punkty zostały dodane.',
                                    'error_points' => '❌ Wystąpił błąd podczas dodawania punktów.',
                                    'bulk_success' => '✅ Akcja zbiorowa wykonana pomyślnie.',
                                    'export_success' => '✅ Eksport danych zakończony powodzeniem.',
                                    'role_changed' => '✅ Rola użytkownika została zmieniona.',
                                    'message_sent' => '✅ Wiadomość została wysłana.'
                                ];
                                echo safeEcho($messages[$_GET['status']] ?? '');
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= safeEcho($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Kafelek statystyk -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($total_users) ?></h5>
                                    <p class="mb-0 small">Wszyscy użytkownicy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($executors_count) ?></h5>
                                    <p class="mb-0 small">Wykonawcy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($clients_count) ?></h5>
                                    <p class="mb-0 small">Zleceniodawcy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($active_users) ?></h5>
                                    <p class="mb-0 small">Aktywni</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($need_attention) ?></h5>
                                    <p class="mb-0 small">Do weryfikacji</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($users_with_jobs) ?></h5>
                                    <p class="mb-0 small">Z ogłoszeniami</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Główna karta zarządzania -->
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-people"></i> Zarządzaj użytkownikami</h5>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                                    <i class="bi bi-funnel"></i> Filtry
                                </button>
                                <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'role_filter' => null, 'date_from' => null, 'date_to' => null, 'balance_min' => null, 'balance_max' => null, 'has_jobs' => null, 'is_verified' => null, 'page' => 1]) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Wyczyść
                                </a>
                            </div>
                        </div>
                        
                        <!-- Rozwijane filtry -->
                        <div class="collapse <?= (!empty($search) || !empty($statusFilter) || !empty($roleFilter) || !empty($dateFrom) || !empty($dateTo) || !empty($balanceMin) || !empty($balanceMax) || !empty($hasJobs) || !empty($isVerified)) ? 'show' : ''; ?>" id="filtersCollapse">
                            <div class="card-body border-bottom">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Wyszukaj</label>
                                        <input type="text" name="search" class="form-control form-control-sm" placeholder="ID, imię, email..." value="<?= safeEcho($search); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status_filter" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            <option value="active" <?= $statusFilter == 'active' ? 'selected' : ''; ?>>Aktywne</option>
                                            <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : ''; ?>>Nieaktywne</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Rola</label>
                                        <select name="role_filter" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            <option value="user" <?= $roleFilter == 'user' ? 'selected' : ''; ?>>Użytkownik</option>
                                            <option value="executor" <?= $roleFilter == 'executor' ? 'selected' : ''; ?>>Wykonawca</option>
                                            <option value="admin" <?= $roleFilter == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Saldo od</label>
                                        <input type="number" name="balance_min" class="form-control form-control-sm" value="<?= safeEcho($balanceMin); ?>" placeholder="Min" step="0.01">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Saldo do</label>
                                        <input type="number" name="balance_max" class="form-control form-control-sm" value="<?= safeEcho($balanceMax); ?>" placeholder="Max" step="0.01">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Data od</label>
                                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= safeEcho($dateFrom); ?>">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Data do</label>
                                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= safeEcho($dateTo); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Ma ogłoszenia</label>
                                        <select name="has_jobs" class="form-select form-select-sm">
                                            <option value="">Wszyscy</option>
                                            <option value="1" <?= $hasJobs == '1' ? 'selected' : ''; ?>>Tak</option>
                                            <option value="0" <?= $hasJobs == '0' ? 'selected' : ''; ?>>Nie</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Zweryfikowany</label>
                                        <select name="is_verified" class="form-select form-select-sm">
                                            <option value="">Wszyscy</option>
                                            <option value="1" <?= $isVerified == '1' ? 'selected' : ''; ?>>Tak</option>
                                            <option value="0" <?= $isVerified == '0' ? 'selected' : ''; ?>>Nie</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Na stronę</label>
                                        <select name="per_page" class="form-select form-select-sm">
                                            <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                            <option value="25" <?= $limit == 25 ? 'selected' : ''; ?>>25</option>
                                            <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                            <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Sortuj według</label>
                                        <select name="sort" class="form-select form-select-sm">
                                            <option value="id" <?= $sortColumn == 'id' ? 'selected' : ''; ?>>ID</option>
                                            <option value="name" <?= $sortColumn == 'name' ? 'selected' : ''; ?>>Imię</option>
                                            <option value="email" <?= $sortColumn == 'email' ? 'selected' : ''; ?>>Email</option>
                                            <option value="created_at" <?= $sortColumn == 'created_at' ? 'selected' : ''; ?>>Data rejestracji</option>
                                            <option value="account_balance" <?= $sortColumn == 'account_balance' ? 'selected' : ''; ?>>Punkty</option>
                                            <option value="registration_ip" <?= $sortColumn == 'registration_ip' ? 'selected' : ''; ?>>Adres IP</option>
                                            <option value="last_login" <?= $sortColumn == 'last_login' ? 'selected' : ''; ?>>Ostatnie logowanie</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Kolejność</label>
                                        <select name="order" class="form-select form-select-sm">
                                            <option value="ASC" <?= $sortOrder == 'ASC' ? 'selected' : ''; ?>>Rosnąco</option>
                                            <option value="DESC" <?= $sortOrder == 'DESC' ? 'selected' : ''; ?>>Malejąco</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-search"></i> Zastosuj filtry
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card-body">
                            <form method="POST" action="../admin/bulk_users_action.php" id="usersForm">
                                <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                
                                <!-- Akcje zbiorowe -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <select name="bulk_action" class="form-select form-select-sm me-2" style="width: 200px;">
                                            <option value="">Wybierz akcję...</option>
                                            <option value="activate">Aktywuj zaznaczonych</option>
                                            <option value="deactivate">Dezaktywuj zaznaczonych</option>
                                            <option value="delete">Usuń zaznaczonych</option>
                                            <option value="export">Eksportuj zaznaczonych</option>
                                            <option value="message">Wyślij wiadomość</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm me-2">Zastosuj</button>
                                        
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="bi bi-download"></i> Eksportuj
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><button type="submit" formaction="export_users.php?format=csv" class="dropdown-item">CSV</button></li>
                                                <li><button type="submit" formaction="export_users.php?format=excel" class="dropdown-item">Excel</button></li>
                                                <li><button type="submit" formaction="export_users.php?format=pdf" class="dropdown-item">PDF</button></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="text-muted">
                                        Znaleziono: <strong><?= number_format($total_users) ?></strong> użytkowników
                                    </div>
                                </div>

                                <!-- Tabela użytkowników -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 1%">
                                                    <input type="checkbox" id="select-all" title="Zaznacz wszystkich">
                                                </th>
                                                <th style="width: 1%">ID</th>
                                                <th style="width: 12%">Użytkownik</th>
                                                <th style="width: 18%">Email</th>
                                                <th style="width: 8%">Rola</th>
                                                <th style="width: 8%">Utworzone</th>
                                                <th style="width: 8%">Ostatnie logowanie</th>
                                                <th style="width: 8%">IP rejestracji</th>
                                                <th style="width: 8%">Status</th>
                                                <th style="width: 8%">Saldo</th>
                                                <th style="width: 20%">Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($users)): ?>
                                                <?php foreach ($users as $user) : ?>
                                                    <tr class="<?= (!empty($user['need_change']) && $user['need_change'] == 1) ? 'table-warning' : ''; ?>">
                                                        <td>
                                                            <input type="checkbox" name="user_ids[]" value="<?= safeEcho($user['id']); ?>" class="user-checkbox">
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary">#<?= safeEcho($user['id']); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0">
                                                                    <img src="<?= !empty($user['avatar']) ? safeEcho($user['avatar']) : '../../assets/img/default-avatar.png'; ?>" 
                                                                         class="rounded-circle me-2" width="32" height="32" alt="Avatar">
                                                                </div>
                                                                <div class="flex-grow-1 ms-2">
                                                                    <div class="fw-bold"><?= safeEcho($user['name']); ?></div>
                                                                    <small class="text-muted"><?= safeEcho($user['username'] ?? 'Brak nazwy'); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?= safeEcho($user['email']); ?>
                                                            <?php if (!empty($user['email_verified_at'])): ?>
                                                                <span class="badge bg-success ms-1" title="Email zweryfikowany">✓</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <span class="badge <?= $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'executor' ? 'bg-warning' : 'bg-primary'); ?> me-2">
                                                                    <?= safeEcho(ucfirst($user['role'])); ?>
                                                                </span>
                                                                <?php if (!empty($user['need_change']) && $user['need_change'] == 1): ?>
                                                                    <form action="change_role.php" method="POST" class="d-inline">
                                                                        <input type="hidden" name="user_id" value="<?= safeEcho($user['id']); ?>">
                                                                        <input type="hidden" name="current_role" value="<?= safeEcho($user['role']); ?>">
                                                                        <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Zmień rolę">
                                                                            <i class="bi bi-arrow-repeat"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <small><?= date('Y-m-d', strtotime($user['created_at'])) ?></small>
                                                            <br><small class="text-muted"><?= date('H:i', strtotime($user['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($user['last_login'])): ?>
                                                                <small><?= date('Y-m-d', strtotime($user['last_login'])) ?></small>
                                                                <br><small class="text-muted"><?= date('H:i', strtotime($user['last_login'])) ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">Nigdy</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?= safeEcho($user['registration_ip']); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?= $user['status'] == 'active' ? 'Aktywny' : 'Nieaktywny'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="fw-bold <?= ($user['account_balance'] ?? 0) > 0 ? 'text-success' : 'text-muted'; ?>">
                                                                <?= number_format($user['account_balance'] ?? 0, 2) ?> pkt
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <!-- Dodawanie punktów -->
                                                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addPointsModal" data-user-id="<?= safeEcho($user['id']); ?>" data-user-name="<?= safeEcho($user['name']); ?>">
                                                                    <i class="bi bi-plus-circle" title="Dodaj punkty"></i>
                                                                </button>
                                                                
                                                                <!-- Edycja -->
                                                                <a href="../admin/edit_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-warning" title="Edytuj">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                
                                                                <!-- Podgląd -->
                                                                <a href="../admin/view_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-info" title="Podgląd">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                
                                                                <!-- Aktywacja/Deaktywacja -->
                                                                <?php if ($user['status'] == 'active'): ?>
                                                                    <a href="../admin/deactivate_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-secondary" title="Dezaktywuj" onclick="return confirm('Czy na pewno chcesz dezaktywować tego użytkownika?');">
                                                                        <i class="bi bi-person-x"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="../admin/activate_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-success" title="Aktywuj">
                                                                        <i class="bi bi-person-check"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Usuwanie -->
                                                                <a href="../admin/delete_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-danger" title="Usuń" onclick="return confirm('Czy na pewno chcesz usunąć tego użytkownika? Ta operacja jest nieodwracalna.');">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-5">
                                                        <i class="bi bi-people display-4 text-muted"></i>
                                                        <p class="mt-3">Brak użytkowników spełniających kryteria wyszukiwania</p>
                                                        <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'role_filter' => null, 'date_from' => null, 'date_to' => null, 'balance_min' => null, 'balance_max' => null, 'has_jobs' => null, 'is_verified' => null]) ?>" class="btn btn-primary btn-sm">
                                                            Wyczyść filtry
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Paginacja -->
                                <?php if ($totalPages > 1): ?>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div>
                                        <span class="text-muted">
                                            Wyświetlono <?= count($users) ?> z <?= number_format($total_users) ?> użytkowników
                                        </span>
                                    </div>
                                    <div>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination pagination-sm">
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => 1]) ?>" aria-label="Pierwsza">
                                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $page - 1]) ?>" aria-label="Poprzednia">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                                
                                                <?php 
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                    <li class="page-item <?= $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="<?= buildUrl(['page' => $i]) ?>"><?= $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $page + 1]) ?>" aria-label="Następna">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $totalPages]) ?>" aria-label="Ostatnia">
                                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                                <?php endif; ?>
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


<!-- Modal do dodawania punktów -->
<div class="modal fade" id="addPointsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dodaj punkty użytkownikowi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="add_points.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="user_id" id="modalUserId">
                    
                    <div class="mb-3">
                        <label class="form-label">Użytkownik</label>
                        <input type="text" class="form-control" id="modalUserName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ilość punktów</label>
                        <input type="number" name="points_to_add" class="form-control" min="1" max="10000" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Powód (opcjonalnie)</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Np. bonus, refundacja, itp."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">Dodaj punkty</button>
                </div>
            </form>
        </div>
    </div>
</div>
       
  
<?php include '../partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Zaznaczanie wszystkich checkboxów
    document.getElementById('select-all').addEventListener('change', function() {
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });

    // Aktualizacja licznika zaznaczonych użytkowników
    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
        const counterElement = document.getElementById('selectedCount');
        if (counterElement) {
            counterElement.textContent = selectedCount;
        }
    }

    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    // Walidacja formularza zbiorowych akcji
    document.querySelector('form#usersForm').addEventListener('submit', function(e) {
        const bulkAction = document.querySelector('select[name="bulk_action"]').value;
        const selectedUsers = document.querySelectorAll('.user-checkbox:checked');
        
        if (bulkAction && selectedUsers.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Brak wybranych użytkowników',
                text: 'Proszę wybrać przynajmniej jednego użytkownika do wykonania akcji zbiorowej'
            });
        }
    });

    // Modal do dodawania punktów
    const addPointsModal = document.getElementById('addPointsModal');
    if (addPointsModal) {
        addPointsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUserName').value = userName;
        });
    }

    // Inicjalizacja tooltipów Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>