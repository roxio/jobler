<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień do przeglądania tej strony.';
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');

$userModel = new User();

$limit = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$allowedSortColumns = ['id', 'name', 'email', 'role', 'created_at', 'account_balance', 'registration_ip', 'last_login', 'status'];
$sortColumn = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$roleFilter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

try {
    $total_users = $userModel->getTotalUsersWithFilters($search, $statusFilter, $roleFilter, $dateFrom, $dateTo);
    $users = $userModel->getPaginatedUsersWithFilters($limit, $offset, $sortColumn, $sortOrder, $search, $statusFilter, $roleFilter, $dateFrom, $dateTo);
    $totalPages = ceil($total_users / $limit);
    
    $executors_count = $userModel->countUsersByRole('executor');
    $clients_count = $userModel->countUsersByRole('user');
    $admin_count = $userModel->countUsersByRole('admin');
    $active_users = $userModel->countUsersByStatus('active');
    $need_attention = $userModel->countUsersNeedingAttention();
    
} catch (Exception $e) {
    error_log("Błąd przy pobieraniu użytkowników: " . $e->getMessage());
    $error = "Wystąpił błąd przy pobieraniu danych. Proszę spróbować później.";
    $users = [];
    $totalPages = 1;
    $total_users = $executors_count = $clients_count = $admin_count = $active_users = $need_attention = 0;
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
                                    'error' => '❌ Wystąpił błąd. Spróbuj ponownie.',
                                    'points_added' => '✅ Punkty zostały dodane.',
                                    'error_points' => '❌ Wystąpił błąd podczas dodawania punktów.',
                                    'bulk_success' => '✅ Akcja zbiorowa wykonana pomyślnie.',
                                    'export_success' => '✅ Eksport danych zakończony powodzeniem.'
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

                    <div class="row mb-4">
                        <div class="col-md-2 col-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5><?= number_format($total_users) ?></h5>
                                    <p class="mb-0 small">Wszyscy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5><?= number_format($executors_count) ?></h5>
                                    <p class="mb-0 small">Wykonawcy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5><?= number_format($clients_count) ?></h5>
                                    <p class="mb-0 small">Zleceniodawcy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5><?= number_format($admin_count) ?></h5>
                                    <p class="mb-0 small">Administratorzy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h5><?= number_format($active_users) ?></h5>
                                    <p class="mb-0 small">Aktywni</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5><?= number_format($need_attention) ?></h5>
                                    <p class="mb-0 small">Do weryfikacji</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-people"></i> Zarządzaj użytkownikami</h5>
                            <nav class="nav">
                                <form method="GET" class="d-flex align-items-center flex-wrap">
                                    <div class="me-2 mb-2">
                                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Szukaj..." value="<?= safeEcho($search); ?>" style="width: 200px;">
                                    </div>
                                    
                                    <select name="per_page" class="form-select form-select-sm me-2 mb-2" onchange="this.form.submit()">
                                        <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?= $limit == 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>

                                    <select name="status_filter" class="form-select form-select-sm me-2 mb-2">
                                        <option value="">Wszystkie statusy</option>
                                        <option value="active" <?= $statusFilter == 'active' ? 'selected' : ''; ?>>Aktywne</option>
                                        <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : ''; ?>>Nieaktywne</option>
                                    </select>

                                    <select name="role_filter" class="form-select form-select-sm me-2 mb-2">
                                        <option value="">Wszystkie role</option>
                                        <option value="user" <?= $roleFilter == 'user' ? 'selected' : ''; ?>>Użytkownik</option>
                                        <option value="executor" <?= $roleFilter == 'executor' ? 'selected' : ''; ?>>Wykonawca</option>
                                        <option value="admin" <?= $roleFilter == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                    </select>

                                    <div class="me-2 mb-2">
                                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= safeEcho($dateFrom) ?>" placeholder="Od daty" style="width: 120px;">
                                    </div>
                                    <div class="me-2 mb-2">
                                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= safeEcho($dateTo) ?>" placeholder="Do daty" style="width: 120px;">
                                    </div>

                                    <select name="sort" class="form-select form-select-sm me-2 mb-2">
                                        <option value="id" <?= $sortColumn == 'id' ? 'selected' : ''; ?>>ID</option>
                                        <option value="name" <?= $sortColumn == 'name' ? 'selected' : ''; ?>>Imię</option>
                                        <option value="email" <?= $sortColumn == 'email' ? 'selected' : ''; ?>>Email</option>
                                        <option value="created_at" <?= $sortColumn == 'created_at' ? 'selected' : ''; ?>>Data rejestracji</option>
                                        <option value="account_balance" <?= $sortColumn == 'account_balance' ? 'selected' : ''; ?>>Punkty</option>
                                        <option value="registration_ip" <?= $sortColumn == 'registration_ip' ? 'selected' : ''; ?>>Adres IP</option>
                                    </select>

                                    <select name="order" class="form-select form-select-sm me-2 mb-2">
                                        <option value="ASC" <?= $sortOrder == 'ASC' ? 'selected' : ''; ?>>Rosnąco</option>
                                        <option value="DESC" <?= $sortOrder == 'DESC' ? 'selected' : ''; ?>>Malejąco</option>
                                    </select>

                                    <button type="submit" class="btn btn-primary btn-sm me-2 mb-2">🔍</button>
                                    <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'role_filter' => null, 'date_from' => null, 'date_to' => null, 'page' => 1]) ?>" class="btn btn-secondary btn-sm mb-2">Wyczyść filtry</a>
                                </form>
                            </nav>
                        </div>
                        
                        <div class="card-body">
                            <form method="POST" action="../admin/bulk_users_action.php" id="usersForm">
                                <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="btn-group">
                                        <select name="bulk_action" class="form-select form-select-sm me-2" style="width: 180px;">
                                            <option value="">Zbiorowe akcje...</option>
                                            <option value="activate">Aktywuj zaznaczonych</option>
                                            <option value="deactivate">Dezaktywuj zaznaczonych</option>
                                            <option value="delete">Usuń zaznaczonych</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Zastosuj</button>
                                    </div>
                                    
                                    <div class="btn-group">
                                        <button type="submit" formaction="export_users.php" class="btn btn-success btn-sm me-2">
                                            <i class="bi bi-download"></i> Eksportuj CSV
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 1%"><input type="checkbox" id="select-all"></th>
                                                <th style="width: 1%">ID</th>
                                                <th style="width: 10%">Imię</th>
                                                <th style="width: 20%">Email</th>
                                                <th style="width: 8%">Rola</th>
                                                <th style="width: 8%">Utworzone</th>
                                                <th style="width: 10%">First IP</th>
                                                <th style="width: 10%">Last IP</th>
                                                <th style="width: 5%">Status</th>
                                                <th style="width: 8%">Saldo</th>
                                                <th style="width: 15%">Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($users)): ?>
                                                <?php foreach ($users as $user) : ?>
                                                    <tr class="<?= (!empty($user['need_change']) && $user['need_change'] == 1) ? 'table-info' : ''; ?>">
                                                        <td><input type="checkbox" name="user_ids[]" value="<?= safeEcho($user['id']); ?>"></td>
                                                        <td><?= safeEcho($user['id']); ?></td>
                                                        <td><?= safeEcho($user['name']); ?></td>
                                                        <td><?= safeEcho($user['email']); ?></td>
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
                                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                                            <i class="bi bi-arrow-repeat"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td><?= safeEcho($user['created_at']); ?></td>
                                                        <td><?= safeEcho($user['registration_ip']); ?></td>
                                                        <td><?= safeEcho($user['last_login_ip']); ?></td>
                                                        <td>
                                                            <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?= $user['status'] == 'active' ? 'Aktywne' : 'Nieaktywne'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?= isset($user['account_balance']) ? safeEcho($user['account_balance']) . ' pkt' : 'Brak'; ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <form action="add_points.php" method="POST" class="input-group me-2">
                                                                    <input type="hidden" name="user_id" value="<?= safeEcho($user['id']); ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                                                    <input type="number" name="points_to_add" min="1" max="1000" class="form-control form-control-sm" placeholder="Punkty" required style="width: 70px;">
                                                                    <button type="submit" class="btn btn-success btn-sm">
                                                                        <i class="bi bi-database-add"></i>
                                                                    </button>
                                                                </form>
                                                                <a href="../admin/edit_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-warning btn-sm me-1">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                <a href="../admin/delete_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-danger btn-sm me-1" onclick="return confirm('Na pewno chcesz usunąć?');">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                                <?php if ($user['status'] == 'active'): ?>
                                                                    <a href="../admin/deactivate_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-secondary btn-sm">
                                                                        <i class="bi bi-person-fill-slash"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="../admin/activate_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-success btn-sm">
                                                                        <i class="bi bi-person-fill-check"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-4">
                                                        <i class="bi bi-people display-4 text-muted"></i>
                                                        <p class="mt-2">Brak użytkowników spełniających kryteria wyszukiwania</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if ($totalPages > 0): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <span class="text-muted">
                                            Wyświetlanie <?= min($limit, count($users)) ?> z <?= number_format($total_users) ?> użytkowników
                                        </span>
                                    </div>
                                    <div>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination">
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $page - 1]) ?>">Poprzednia</a>
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
                                                    <a class="page-link" href="<?= buildUrl(['page' => $page + 1]) ?>">Następna</a>
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
                
                <footer class="mt-4">
                    <div class="container">
                        <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
                    </div>
                </footer>
            </div>	
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

document.querySelector('form#usersForm').addEventListener('submit', function(e) {
    const bulkAction = document.querySelector('select[name="bulk_action"]').value;
    const selectedUsers = document.querySelectorAll('input[name="user_ids[]"]:checked');
    
    if (bulkAction && selectedUsers.length === 0) {
        e.preventDefault();
        alert('Proszę wybrać przynajmniej jednego użytkownika do wykonania akcji zbiorowej');
    }
});
</script>