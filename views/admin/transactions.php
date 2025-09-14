<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień do przeglądania tej strony.';
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Message.php');

// Sprawdź czy użytkownik jest zalogowany i ma uprawnienia administratora
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Utwórz instancje klas z przekazanym połączeniem PDO
$transactionModel = new TransactionHistory($pdo); // Przekazujemy $pdo do konstruktora
$userModel = new User();

// Pobierz ID użytkownika do filtrowania
$userIdFilter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';

// Parametry paginacji
$limit = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Sortowanie
$allowedSortColumns = ['id', 'user_id', 'amount', 'type', 'status', 'created_at'];
$sortColumn = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

// Pobierz transakcje
$transactions = $transactionModel->getTransactionsWithFilters($limit, $offset, $sortColumn, $sortOrder, $userIdFilter);
$totalTransactions = $transactionModel->countTransactionsWithFilters($userIdFilter);
$totalPages = ceil($totalTransactions / $limit);

// Pobierz użytkownika jeśli filtrujemy
$userDetails = null;
if (!empty($userIdFilter)) {
    $userDetails = $userModel->getUserById($userIdFilter);
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
                    <!-- Nagłówek z powrotem -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="view_user.php?id=<?= $userIdFilter ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Powrót do użytkowników
                            </a>
                            <?php if (!empty($userIdFilter) && $userDetails): ?>
                                <span class="ms-2">Transakcje użytkownika: <?= safeEcho($userDetails['name']) ?> (ID: <?= $userIdFilter ?>)</span>
                            <?php else: ?>
                                <span class="ms-2">Wszystkie transakcje</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statystyki -->
                    <?php if (!empty($userIdFilter)): ?>
                    <div class="row mb-4">
                        <?php
                        $stats = $transactionModel->getUserTransactionStats($userIdFilter);
                        ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['total_count']) ?></h5>
                                    <p class="mb-0 small">Wszystkie transakcje</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['income_total'], 2) ?> PLN</h5>
                                    <p class="mb-0 small">Przychody</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['expense_total'], 2) ?> PLN</h5>
                                    <p class="mb-0 small">Wydatki</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['completed_count']) ?></h5>
                                    <p class="mb-0 small">Zakończone</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Tabela transakcji -->
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-currency-exchange"></i> Zarządzaj transakcjami</h5>
                            <div class="d-flex align-items-center">
                                <a href="<?= buildUrl(['user_id' => null, 'page' => 1]) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Wyczyść filtry
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Użytkownik</th>
                                            <th>Kwota</th>
                                            <th>Typ</th>
                                            <th>Status</th>
                                            <th>Opis</th>
                                            <th>Data</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($transactions)): ?>
                                            <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary">#<?= safeEcho($transaction['id']); ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="view_user.php?id=<?= safeEcho($transaction['user_id']); ?>" class="text-decoration-none">
                                                            <?= safeEcho($transaction['user_name'] ?? 'Użytkownik #' . $transaction['user_id']) ?>
                                                        </a>
                                                    </td>
                                                    <td class="fw-bold <?= $transaction['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                                        <?= number_format($transaction['amount'], 2) ?> PLN
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $transaction['type'] == 'income' ? 'success' : 'danger'; ?>">
                                                            <?= $transaction['type'] == 'income' ? 'Przychód' : 'Wydatek' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $transaction['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                            <?= $transaction['status'] == 'completed' ? 'Zakończona' : 'Oczekująca' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= safeEcho(mb_substr($transaction['description'], 0, 50)) ?>
                                                        <?= mb_strlen($transaction['description']) > 50 ? '...' : '' ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('Y-m-d', strtotime($transaction['created_at'])) ?></small>
                                                        <br><small class="text-muted"><?= date('H:i', strtotime($transaction['created_at'])) ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                                    <p class="mt-3">Brak transakcji</p>
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
                                        Wyświetlono <?= count($transactions) ?> z <?= number_format($totalTransactions) ?> transakcji
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>