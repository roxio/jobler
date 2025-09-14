<?php
session_start();
include_once('../../models/Job.php');
include_once('../../models/User.php');

// Sprawdź czy użytkownik jest zalogowany i ma uprawnienia administratora
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Utwórz instancje klas
$jobModel = new Job();
$userModel = new User();

// Parametry paginacji
$limit = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Sortowanie
$allowedSortColumns = ['id', 'title', 'points_required', 'created_at', 'status'];
$sortColumn = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

// Parametry wyszukiwania i filtrowania
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$categoryFilter = isset($_GET['category_filter']) ? (int)$_GET['category_filter'] : '';
$userFilter = isset($_GET['user_filter']) ? (int)$_GET['user_filter'] : '';
$userIdFilter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
if (!empty($userIdFilter)) {
    $userFilter = $userIdFilter;
}
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$pointsMin = isset($_GET['points_min']) ? (float)$_GET['points_min'] : '';
$pointsMax = isset($_GET['points_max']) ? (float)$_GET['points_max'] : '';

// Pobierz kategorie dla filtrowania
$categories = $jobModel->getCategories();

// Pobierz listę użytkowników dla filtrowania
$users = $userModel->getAllUsers();

// Pobierz ogłoszenia z uwzględnieniem filtrów i paginacji
$jobs = $jobModel->getJobsWithFilters($limit, $offset, $sortColumn, $sortOrder, $searchTerm, $statusFilter, $categoryFilter, $userFilter, $dateFrom, $dateTo, $pointsMin, $pointsMax);

// Pobierz całkowitą liczbę ogłoszeń dla paginacji
$totalJobs = $jobModel->countJobsWithFilters($searchTerm, $statusFilter, $categoryFilter, $userFilter, $dateFrom, $dateTo, $pointsMin, $pointsMax);
$totalPages = ceil($totalJobs / $limit);

// Statystyki
$open_jobs = $jobModel->countJobsByStatus('open');
$active_jobs = $jobModel->countJobsByStatus('active');
$closed_jobs = $jobModel->countJobsByStatus('closed');
$inactive_jobs = $jobModel->countJobsByStatus('inactive');

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

// Obsługa akcji masowych
if (isset($_POST['bulk_action']) && isset($_POST['selected_jobs'])) {
    $selectedJobs = $_POST['selected_jobs'];
    $bulkAction = $_POST['bulk_action'];
    
    switch ($bulkAction) {
        case 'delete':
            foreach ($selectedJobs as $jobId) {
                $jobModel->deleteJob($jobId);
            }
            $_SESSION['message'] = 'Wybrane ogłoszenia zostały usunięte.';
            break;
            
        case 'activate':
            foreach ($selectedJobs as $jobId) {
                $jobModel->updateJobStatus($jobId, 'active');
            }
            $_SESSION['message'] = 'Wybrane ogłoszenia zostały aktywowane.';
            break;
            
        case 'deactivate':
            foreach ($selectedJobs as $jobId) {
                $jobModel->updateJobStatus($jobId, 'inactive');
            }
            $_SESSION['message'] = 'Wybrane ogłoszenia zostały dezaktywowane.';
            break;
            
        case 'close':
            foreach ($selectedJobs as $jobId) {
                $jobModel->updateJobStatus($jobId, 'closed');
            }
            $_SESSION['message'] = 'Wybrane ogłoszenia zostały zamknięte.';
            break;
    }
    
    header("Location: manage_jobs.php");
    exit;
}

// Obsługa pojedynczych akcji
if (isset($_GET['action']) && isset($_GET['id'])) {
    $jobId = (int)$_GET['id'];
    $action = $_GET['action'];
    
    switch ($action) {
        case 'delete':
            $jobModel->deleteJob($jobId);
            $_SESSION['message'] = 'Ogłoszenie zostało usunięte.';
            break;
            
        case 'activate':
            $jobModel->updateJobStatus($jobId, 'active');
            $_SESSION['message'] = 'Ogłoszenie zostało aktywowane.';
            break;
            
        case 'deactivate':
            $jobModel->updateJobStatus($jobId, 'inactive');
            $_SESSION['message'] = 'Ogłoszenie zostało dezaktywowane.';
            break;
            
        case 'close':
            $jobModel->updateJobStatus($jobId, 'closed');
            $_SESSION['message'] = 'Ogłoszenie zostało zamknięte.';
            break;
    }
    
    header("Location: manage_jobs.php");
    exit;
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
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Kafelek statystyk -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($totalJobs) ?></h5>
                                    <p class="mb-0 small">Wszystkie ogłoszenia</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($active_jobs) ?></h5>
                                    <p class="mb-0 small">Aktywne</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($open_jobs) ?></h5>
                                    <p class="mb-0 small">Otwarte</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($closed_jobs) ?></h5>
                                    <p class="mb-0 small">Zamknięte</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Główna karta zarządzania -->
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-briefcase"></i> Zarządzaj ogłoszeniami</h5>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                                    <i class="bi bi-funnel"></i> Filtry
                                </button>
                                <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'category_filter' => null, 'user_filter' => null, 'date_from' => null, 'date_to' => null, 'points_min' => null, 'points_max' => null, 'page' => 1]) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Wyczyść
                                </a>
                                <a href="add_job.php" class="btn btn-success btn-sm ms-2"><i class="bi bi-plus-circle"></i> Dodaj ogłoszenie</a>
                            </div>
                        </div>
                        
                        <!-- Rozwijane filtry -->
                        <div class="collapse <?= (!empty($searchTerm) || !empty($statusFilter) || !empty($categoryFilter) || !empty($userFilter) || !empty($dateFrom) || !empty($dateTo) || !empty($pointsMin) || !empty($pointsMax)) ? 'show' : ''; ?>" id="filtersCollapse">
                            <div class="card-body border-bottom">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Wyszukaj</label>
                                        <input type="text" name="search" class="form-control form-control-sm" placeholder="ID, tytuł..." value="<?= safeEcho($searchTerm); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status_filter" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            <option value="open" <?= $statusFilter == 'open' ? 'selected' : ''; ?>>Otwarte</option>
                                            <option value="active" <?= $statusFilter == 'active' ? 'selected' : ''; ?>>Aktywne</option>
                                            <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : ''; ?>>Nieaktywne</option>
                                            <option value="closed" <?= $statusFilter == 'closed' ? 'selected' : ''; ?>>Zamknięte</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Kategoria</label>
                                        <select name="category_filter" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" <?= $categoryFilter == $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Użytkownik</label>
                                        <select name="user_filter" class="form-select form-select-sm">
                                            <option value="">Wszyscy</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>" <?= $userFilter == $user['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($user['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Punkty od</label>
                                        <input type="number" name="points_min" class="form-control form-control-sm" value="<?= safeEcho($pointsMin); ?>" placeholder="Min" step="0.01">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Punkty do</label>
                                        <input type="number" name="points_max" class="form-control form-control-sm" value="<?= safeEcho($pointsMax); ?>" placeholder="Max" step="0.01">
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
                                            <option value="title" <?= $sortColumn == 'title' ? 'selected' : ''; ?>>Tytuł</option>
                                            <option value="points_required" <?= $sortColumn == 'points_required' ? 'selected' : ''; ?>>Punkty</option>
                                            <option value="created_at" <?= $sortColumn == 'created_at' ? 'selected' : ''; ?>>Data utworzenia</option>
                                            <option value="status" <?= $sortColumn == 'status' ? 'selected' : ''; ?>>Status</option>
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
                            <form method="POST" action="manage_jobs.php" id="jobsForm">
                                <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                
                                <!-- Akcje zbiorowe -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <select name="bulk_action" class="form-select form-select-sm me-2" style="width: 200px;">
                                            <option value="">Wybierz akcję...</option>
                                            <option value="activate">Aktywuj zaznaczone</option>
                                            <option value="deactivate">Dezaktywuj zaznaczone</option>
                                            <option value="close">Zamknij zaznaczone</option>
                                            <option value="delete">Usuń zaznaczone</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm me-2">Zastosuj</button>
                                    </div>
                                    
                                    <div class="text-muted">
                                        Znaleziono: <strong><?= number_format($totalJobs) ?></strong> ogłoszeń
                                    </div>
                                </div>

                                <!-- Tabela ogłoszeń -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 1%">
                                                    <input type="checkbox" id="select-all" title="Zaznacz wszystkie">
                                                </th>
                                                <th style="width: 5%">ID</th>
                                                <th style="width: 25%">Tytuł</th>
                                                <th style="width: 12%">Kategoria</th>
                                                <th style="width: 15%">Użytkownik</th>
                                                <th style="width: 8%">Punkty</th>
                                                <th style="width: 10%">Utworzone</th>
                                                <th style="width: 8%">Status</th>
                                                <th style="width: 16%">Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($jobs)): ?>
                                                <?php foreach ($jobs as $job): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" name="selected_jobs[]" value="<?= safeEcho($job['id']); ?>" class="job-checkbox">
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary">#<?= safeEcho($job['id']); ?></span>
                                                        </td>
                                                        <td>
                                                            <a href="../jobs/view_job.php?id=<?= $job['id'] ?>" target="_blank" class="text-decoration-none">
                                                                <?= htmlspecialchars(mb_substr($job['title'], 0, 40)) . (mb_strlen($job['title']) > 40 ? '...' : '') ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $categoryName = 'Brak';
                                                            foreach ($categories as $category) {
                                                                if ($category['id'] == $job['category_id']) {
                                                                    $categoryName = htmlspecialchars($category['name']);
                                                                    break;
                                                                }
                                                            }
                                                            echo $categoryName;
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $userName = 'Nieznany';
                                                            foreach ($users as $user) {
                                                                if ($user['id'] == $job['user_id']) {
                                                                    $userName = htmlspecialchars($user['name']);
                                                                    break;
                                                                }
                                                            }
                                                            echo $userName;
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <span class="fw-bold text-primary"><?= safeEcho($job['points_required']) ?> pkt</span>
                                                        </td>
                                                        <td>
                                                            <small><?= date('Y-m-d', strtotime($job['created_at'])) ?></small>
                                                            <br><small class="text-muted"><?= date('H:i', strtotime($job['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $job['status'] == 'active' ? 'success' : 
                                                                ($job['status'] == 'open' ? 'primary' : 
                                                                ($job['status'] == 'closed' ? 'secondary' : 'warning'))
                                                            ?>">
                                                                <?= $job['status'] == 'active' ? 'Aktywny' : 
                                                                   ($job['status'] == 'open' ? 'Otwarty' : 
                                                                   ($job['status'] == 'closed' ? 'Zamknięty' : 'Nieaktywny')) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <!-- Edycja -->
                                                                <a href="edit_job.php?id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-warning" title="Edytuj">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                
                                                                <!-- Podgląd -->
                                                                <a href="../jobs/view_job.php?id=<?= safeEcho($job['id']); ?>" target="_blank" class="btn btn-outline-info" title="Podgląd">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                
                                                                <!-- Aktywacja/Deaktywacja -->
                                                                <?php if ($job['status'] == 'active' || $job['status'] == 'open'): ?>
                                                                    <a href="manage_jobs.php?action=deactivate&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-secondary" title="Dezaktywuj" onclick="return confirm('Czy na pewno chcesz dezaktywować to ogłoszenie?');">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="manage_jobs.php?action=activate&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-success" title="Aktywuj">
                                                                        <i class="bi bi-check-circle"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Zamknięcie -->
                                                                <?php if ($job['status'] != 'closed'): ?>
                                                                    <a href="manage_jobs.php?action=close&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-info" title="Zamknij" onclick="return confirm('Czy na pewno chcesz zamknąć to ogłoszenie?');">
                                                                        <i class="bi bi-lock"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Usuwanie -->
                                                                <a href="manage_jobs.php?action=delete&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-danger" title="Usuń" onclick="return confirm('Czy na pewno chcesz usunąć to ogłoszenie? Ta operacja jest nieodwracalna.');">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-5">
                                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                                        <p class="mt-3">Brak ogłoszeń spełniających kryteria wyszukiwania</p>
                                                        <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'category_filter' => null, 'user_filter' => null, 'date_from' => null, 'date_to' => null, 'points_min' => null, 'points_max' => null]) ?>" class="btn btn-primary btn-sm">
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
                                            Wyświetlono <?= count($jobs) ?> z <?= number_format($totalJobs) ?> ogłoszeń
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Zaznaczanie wszystkich checkboxów
    document.getElementById('select-all').addEventListener('change', function() {
        document.querySelectorAll('.job-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Walidacja formularza zbiorowych akcji
    document.querySelector('form#jobsForm').addEventListener('submit', function(e) {
        const bulkAction = document.querySelector('select[name="bulk_action"]').value;
        const selectedJobs = document.querySelectorAll('.job-checkbox:checked');
        
        if (bulkAction && selectedJobs.length === 0) {
            e.preventDefault();
            alert('Proszę wybrać przynajmniej jedno ogłoszenie do wykonania akcji zbiorowej');
        }
        
        if (bulkAction === 'delete' && !confirm('Czy na pewno chcesz usunąć wybrane ogłoszenia? Tej operacji nie można cofnąć.')) {
            e.preventDefault();
        }
    });

    // Inicjalizacja tooltipów Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../partials/footer.php'; ?>