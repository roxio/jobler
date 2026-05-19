<?php
session_start();
include_once('../../models/Job.php');
include_once('../../models/User.php');
include_once('../../models/Language.php');

// Sprawdź czy użytkownik jest zalogowany i ma uprawnienia administratora
require_once __DIR__ . '/_auth.php';
requireAdminAccess();

// Utwórz instancje klas
$jobModel = new Job();
$userModel = new User();

function ensureAdminJobArchiveColumns() {
    $pdo = Database::getConnection();
    $columns = [
        'deleted_at' => "ALTER TABLE jobs ADD COLUMN deleted_at DATETIME DEFAULT NULL",
        'archived_at' => "ALTER TABLE jobs ADD COLUMN archived_at DATETIME DEFAULT NULL",
        'archive_reason' => "ALTER TABLE jobs ADD COLUMN archive_reason VARCHAR(80) DEFAULT NULL",
    ];

    $stmt = $pdo->query("SHOW COLUMNS FROM jobs");
    $existingColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

    foreach ($columns as $column => $sql) {
        if (!in_array($column, $existingColumns, true)) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec("
        UPDATE jobs
        SET archived_at = COALESCE(archived_at, NOW()),
            archive_reason = CASE
                WHEN deleted_at IS NOT NULL THEN 'auto_year_after_delete'
                ELSE 'auto_year_after_publish'
            END,
            updated_at = NOW()
        WHERE archived_at IS NULL
          AND (
              created_at <= DATE_SUB(NOW(), INTERVAL 1 YEAR)
              OR (deleted_at IS NOT NULL AND deleted_at <= DATE_SUB(NOW(), INTERVAL 1 YEAR))
          )
    ");
}

ensureAdminJobArchiveColumns();

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
$archived_jobs = $jobModel->countJobsWithFilters('', 'archived');

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
            $_SESSION['message'] = __t('admin.jobs.archived_message');
            break;

        case 'restore':
            foreach ($selectedJobs as $jobId) {
                $jobModel->restoreJob($jobId);
            }
            $_SESSION['message'] = __t('admin.jobs.restored_message');
            break;

        case 'permanent_delete':
            foreach ($selectedJobs as $jobId) {
                $jobModel->permanentlyDeleteJob($jobId);
            }
            $_SESSION['message'] = __t('admin.jobs.permanently_deleted_message');
            break;

        case 'activate':
            foreach ($selectedJobs as $jobId) {
                $jobModel->updateJobStatus($jobId, 'active');
            }
            $_SESSION['message'] = __t('admin.jobs.activated_message');
            break;
            
        case 'deactivate':
            foreach ($selectedJobs as $jobId) {
                $jobModel->updateJobStatus($jobId, 'inactive');
            }
            $_SESSION['message'] = __t('admin.jobs.deactivated_message');
            break;
            
        case 'close':
            foreach ($selectedJobs as $jobId) {
                $jobModel->updateJobStatus($jobId, 'closed');
            }
            $_SESSION['message'] = __t('admin.jobs.closed_message');
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
            $_SESSION['message'] = __t('admin.jobs.single_archived_message');
            break;

        case 'restore':
            $jobModel->restoreJob($jobId);
            $_SESSION['message'] = __t('admin.jobs.single_restored_message');
            break;

        case 'permanent_delete':
            $jobModel->permanentlyDeleteJob($jobId);
            $_SESSION['message'] = __t('admin.jobs.single_deleted_message');
            break;
            
        case 'activate':
            $jobModel->updateJobStatus($jobId, 'active');
            $_SESSION['message'] = __t('admin.jobs.single_activated_message');
            break;
            
        case 'deactivate':
            $jobModel->updateJobStatus($jobId, 'inactive');
            $_SESSION['message'] = __t('admin.jobs.single_deactivated_message');
            break;
            
        case 'close':
            $jobModel->updateJobStatus($jobId, 'closed');
            $_SESSION['message'] = __t('admin.jobs.single_closed_message');
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
                    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= htmlspecialchars(__t('admin.panel')) ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Kafelek statystyk -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($totalJobs) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.jobs.all_jobs')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($active_jobs) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.jobs.active')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($open_jobs) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.jobs.open')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($closed_jobs) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.jobs.closed')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($archived_jobs) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.jobs.archived')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Główna karta zarządzania -->
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-briefcase"></i> <?= htmlspecialchars(__t('admin.jobs.manage')) ?></h5>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                                    <i class="bi bi-funnel"></i> <?= htmlspecialchars(__t('admin.common.filters')) ?>
                                </button>
                                <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'category_filter' => null, 'user_filter' => null, 'date_from' => null, 'date_to' => null, 'points_min' => null, 'points_max' => null, 'page' => 1]) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> <?= htmlspecialchars(__t('admin.common.clear')) ?>
                                </a>
                                <a href="add_job.php" class="btn btn-success btn-sm ms-2"><i class="bi bi-plus-circle"></i> <?= htmlspecialchars(__t('admin.add_job')) ?></a>
                            </div>
                        </div>
                        
                        <!-- Rozwijane filtry -->
                        <div class="collapse <?= (!empty($searchTerm) || !empty($statusFilter) || !empty($categoryFilter) || !empty($userFilter) || !empty($dateFrom) || !empty($dateTo) || !empty($pointsMin) || !empty($pointsMax)) ? 'show' : ''; ?>" id="filtersCollapse">
                            <div class="card-body border-bottom">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.search')) ?></label>
                                        <input type="text" name="search" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(__t('admin.jobs.search_placeholder')) ?>" value="<?= safeEcho($searchTerm); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.status')) ?></label>
                                        <select name="status_filter" class="form-select form-select-sm">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.all')) ?></option>
                                            <option value="open" <?= $statusFilter == 'open' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.jobs.open')) ?></option>
                                            <option value="active" <?= $statusFilter == 'active' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.jobs.active')) ?></option>
                                            <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.jobs.inactive')) ?></option>
                                            <option value="closed" <?= $statusFilter == 'closed' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.jobs.closed')) ?></option>
                                            <option value="archived" <?= $statusFilter == 'archived' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.jobs.archived')) ?></option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.category')) ?></label>
                                        <select name="category_filter" class="form-select form-select-sm">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.all')) ?></option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" <?= $categoryFilter == $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.jobs.user')) ?></label>
                                        <select name="user_filter" class="form-select form-select-sm">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.all_male')) ?></option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>" <?= $userFilter == $user['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($user['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.jobs.points_from')) ?></label>
                                        <input type="number" name="points_min" class="form-control form-control-sm" value="<?= safeEcho($pointsMin); ?>" placeholder="Min" step="0.01">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.jobs.points_to')) ?></label>
                                        <input type="number" name="points_max" class="form-control form-control-sm" value="<?= safeEcho($pointsMax); ?>" placeholder="Max" step="0.01">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.from_date')) ?></label>
                                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= safeEcho($dateFrom); ?>">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.to_date')) ?></label>
                                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= safeEcho($dateTo); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.per_page')) ?></label>
                                        <select name="per_page" class="form-select form-select-sm">
                                            <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                            <option value="25" <?= $limit == 25 ? 'selected' : ''; ?>>25</option>
                                            <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                            <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.sort_by')) ?></label>
                                        <select name="sort" class="form-select form-select-sm">
                                            <option value="id" <?= $sortColumn == 'id' ? 'selected' : ''; ?>>ID</option>
                                            <option value="title" <?= $sortColumn == 'title' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.common.title')) ?></option>
                                            <option value="points_required" <?= $sortColumn == 'points_required' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.common.points')) ?></option>
                                            <option value="created_at" <?= $sortColumn == 'created_at' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.common.created')) ?></option>
                                            <option value="status" <?= $sortColumn == 'status' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.common.status')) ?></option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.order')) ?></label>
                                        <select name="order" class="form-select form-select-sm">
                                            <option value="ASC" <?= $sortOrder == 'ASC' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.common.asc')) ?></option>
                                            <option value="DESC" <?= $sortOrder == 'DESC' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.common.desc')) ?></option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-search"></i> <?= htmlspecialchars(__t('admin.common.apply_filters')) ?>
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
                                            <option value=""><?= htmlspecialchars(__t('admin.common.bulk_action')) ?></option>
                                            <option value="activate"><?= htmlspecialchars(__t('admin.jobs.bulk_activate')) ?></option>
                                            <option value="deactivate"><?= htmlspecialchars(__t('admin.jobs.bulk_deactivate')) ?></option>
                                            <option value="close"><?= htmlspecialchars(__t('admin.jobs.bulk_close')) ?></option>
                                            <option value="delete"><?= htmlspecialchars(__t('admin.jobs.bulk_archive')) ?></option>
                                            <option value="restore"><?= htmlspecialchars(__t('admin.jobs.bulk_restore')) ?></option>
                                            <option value="permanent_delete"><?= htmlspecialchars(__t('admin.jobs.bulk_delete_permanent')) ?></option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm me-2"><?= htmlspecialchars(__t('admin.common.apply')) ?></button>
                                    </div>
                                    
                                    <div class="text-muted">
                                        <?= htmlspecialchars(__t('admin.common.found_jobs', ['count' => number_format($totalJobs)])) ?>
                                    </div>
                                </div>

                                <!-- Tabela ogłoszeń -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 1%">
                                                    <input type="checkbox" id="select-all" title="<?= htmlspecialchars(__t('admin.common.bulk_action')) ?>">
                                                </th>
                                                <th style="width: 5%">ID</th>
                                                <th style="width: 25%"><?= htmlspecialchars(__t('admin.common.title')) ?></th>
                                                <th style="width: 12%"><?= htmlspecialchars(__t('admin.common.category')) ?></th>
                                                <th style="width: 15%"><?= htmlspecialchars(__t('admin.jobs.user')) ?></th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.common.points')) ?></th>
                                                <th style="width: 10%"><?= htmlspecialchars(__t('admin.common.created')) ?></th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.common.status')) ?></th>
                                                <th style="width: 16%"><?= htmlspecialchars(__t('admin.common.actions')) ?></th>
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
                                                            <a href="view_job.php?id=<?= $job['id'] ?>" target="_blank" class="text-decoration-none">
                                                                <?= htmlspecialchars(mb_substr($job['title'], 0, 40)) . (mb_strlen($job['title']) > 40 ? '...' : '') ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $categoryName = __t('admin.jobs.none_category');
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
                                                            $userName = __t('admin.jobs.unknown_user');
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
                                                                <?= htmlspecialchars($job['status'] == 'active' ? __t('admin.status.active') : 
                                                                   ($job['status'] == 'open' ? __t('admin.jobs.open_single') : 
                                                                   ($job['status'] == 'closed' ? __t('admin.jobs.closed') : __t('admin.jobs.inactive')))) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <!-- Edycja -->
                                                                <a href="edit_job.php?id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-warning" title="<?= htmlspecialchars(__t('common.edit')) ?>">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                
                                                                <!-- Podgląd -->
                                                                <a href="view_job.php?id=<?= safeEcho($job['id']); ?>" target="_blank" class="btn btn-outline-info" title="<?= htmlspecialchars(__t('admin.common.preview')) ?>">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                
                                                                <!-- Aktywacja/Deaktywacja -->
                                                                <?php if ($job['status'] == 'active' || $job['status'] == 'open'): ?>
                                                                    <a href="manage_jobs.php?action=deactivate&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-secondary" title="<?= htmlspecialchars(__t('admin.common.deactivate')) ?>" onclick="return confirm('<?= htmlspecialchars(__t('admin.jobs.deactivate_confirm'), ENT_QUOTES) ?>');">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="manage_jobs.php?action=activate&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-success" title="<?= htmlspecialchars(__t('admin.common.activate')) ?>">
                                                                        <i class="bi bi-check-circle"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Zamknięcie -->
                                                                <?php if ($job['status'] != 'closed'): ?>
                                                                    <a href="manage_jobs.php?action=close&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-info" title="<?= htmlspecialchars(__t('admin.common.close_job')) ?>" onclick="return confirm('<?= htmlspecialchars(__t('admin.jobs.close_confirm'), ENT_QUOTES) ?>');">
                                                                        <i class="bi bi-lock"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Usuwanie -->
                                                                <a href="manage_jobs.php?action=delete&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-danger" title="<?= htmlspecialchars(__t('admin.common.archive')) ?>" onclick="return confirm('<?= htmlspecialchars(__t('admin.jobs.archive_confirm'), ENT_QUOTES) ?>');">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                                <?php if (!empty($job['archived_at'])): ?>
                                                                    <a href="manage_jobs.php?action=restore&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-success" title="<?= htmlspecialchars(__t('admin.common.restore')) ?>">
                                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                                    </a>
                                                                    <a href="manage_jobs.php?action=permanent_delete&id=<?= safeEcho($job['id']); ?>" class="btn btn-outline-dark" title="<?= htmlspecialchars(__t('admin.jobs.bulk_delete_permanent')) ?>" onclick="return confirm('<?= htmlspecialchars(__t('admin.jobs.permanent_delete_confirm'), ENT_QUOTES) ?>');">
                                                                        <i class="bi bi-trash3"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-5">
                                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                                        <p class="mt-3"><?= htmlspecialchars(__t('admin.jobs.empty')) ?></p>
                                                        <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'category_filter' => null, 'user_filter' => null, 'date_from' => null, 'date_to' => null, 'points_min' => null, 'points_max' => null]) ?>" class="btn btn-primary btn-sm">
                                                            <?= htmlspecialchars(__t('admin.common.clear')) ?>
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
                                            <?= htmlspecialchars(__t('admin.common.displayed_jobs', ['shown' => count($jobs), 'total' => number_format($totalJobs)])) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination pagination-sm">
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => 1]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.first')) ?>">
                                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $page - 1]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.previous')) ?>">
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
                                                    <a class="page-link" href="<?= buildUrl(['page' => $page + 1]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.next')) ?>">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $totalPages]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.last')) ?>">
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
            alert(<?= json_encode(__t('admin.jobs.no_selected'), JSON_UNESCAPED_UNICODE) ?>);
        }
        
        if (bulkAction === 'delete' && !confirm(<?= json_encode(__t('admin.jobs.bulk_archive_confirm'), JSON_UNESCAPED_UNICODE) ?>)) {
            e.preventDefault();
        }

        if (bulkAction === 'permanent_delete' && !confirm(<?= json_encode(__t('admin.jobs.bulk_permanent_delete_confirm'), JSON_UNESCAPED_UNICODE) ?>)) {
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
