<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Language.php');


$userModel = new User();
$transactionModel = new TransactionHistory($pdo);


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
$balanceMin = isset($_GET['balance_min']) ? (float)$_GET['balance_min'] : '';
$balanceMax = isset($_GET['balance_max']) ? (float)$_GET['balance_max'] : '';
$hasJobs = isset($_GET['has_jobs']) ? $_GET['has_jobs'] : '';
$isVerified = isset($_GET['is_verified']) ? $_GET['is_verified'] : '';

try {

    $total_users = $userModel->getTotalUsersWithFilters($search, $statusFilter, $roleFilter, $dateFrom, $dateTo, $balanceMin, $balanceMax, $hasJobs, $isVerified);
    $users = $userModel->getPaginatedUsersWithFilters($limit, $offset, $sortColumn, $sortOrder, $search, $statusFilter, $roleFilter, $dateFrom, $dateTo, $balanceMin, $balanceMax, $hasJobs, $isVerified);
    $totalPages = ceil($total_users / $limit);


    $executors_count = $userModel->countUsersByRole('executor');
    $clients_count = $userModel->countUsersByRole('user');
    $admin_count = 0;
    foreach (AccessControl::adminRoles() as $adminRole) {
        $admin_count += (int)$userModel->countUsersByRole($adminRole);
    }
    $active_users = $userModel->countUsersByStatus('active');
    $need_attention = $userModel->countUsersNeedingAttention();
    $users_with_jobs = $userModel->countUsersWithJobs();
    $verified_users = $userModel->countVerifiedUsers();

} catch (Exception $e) {
    error_log(__t('admin.logs.fetch_users_error', ['error' => $e->getMessage()]));
    $error = __t('admin.users.fetch_error');
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
                    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= htmlspecialchars(__t('admin.panel')) ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <?php if (isset($_GET['status'])): ?>
                        <div class="alert <?php echo ($_GET['status'] == 'error' || $_GET['status'] == 'error_points') ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                            <?php
                                $messages = [
                                    'deleted' => __t('admin.users.deleted', ['count' => $_GET['jobs_closed'] ?? 0]),
                                    'delete_failed' => __t('admin.users.delete_failed'),
                                    'cannot_delete_self' => __t('admin.users.cannot_delete_self'),
                                    'user_not_found' => __t('admin.users.not_found'),
                                    'system_error' => __t('admin.users.system_error'),
                                    'activated' => __t('admin.users.activated'),
                                    'deactivated' => __t('admin.users.deactivated'),
                                    'error' => __t('admin.users.error'),
                                    'points_added' => __t('admin.users.points_added'),
                                    'error_points' => __t('admin.users.points_error'),
                                    'bulk_success' => __t('admin.users.bulk_success'),
                                    'export_success' => __t('admin.users.export_success'),
                                    'role_changed' => __t('admin.users.role_changed'),
                                    'message_sent' => __t('admin.users.message_sent')

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
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($total_users) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.users.all_users')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($executors_count) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.users.executors')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($clients_count) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.users.clients')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($active_users) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.users.active')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($need_attention) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.users.needs_verification')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($users_with_jobs) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.users.with_jobs')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-people"></i> <?= htmlspecialchars(__t('admin.manage_users')) ?></h5>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                                    <i class="bi bi-funnel"></i> <?= htmlspecialchars(__t('admin.common.filters')) ?>
                                </button>
                                <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'role_filter' => null, 'date_from' => null, 'date_to' => null, 'balance_min' => null, 'balance_max' => null, 'has_jobs' => null, 'is_verified' => null, 'page' => 1]) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> <?= htmlspecialchars(__t('admin.common.clear')) ?>
                                </a>
                            </div>
                        </div>

                        <div class="collapse <?= (!empty($search) || !empty($statusFilter) || !empty($roleFilter) || !empty($dateFrom) || !empty($dateTo) || !empty($balanceMin) || !empty($balanceMax) || !empty($hasJobs) || !empty($isVerified)) ? 'show' : ''; ?>" id="filtersCollapse">
                            <div class="card-body border-bottom">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.search')) ?></label>
                                        <input type="text" name="search" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(__t('admin.users.search_placeholder')) ?>" value="<?= safeEcho($search); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.status')) ?></label>
                                        <select name="status_filter" class="form-select form-select-sm">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.all')) ?></option>
                                            <option value="active" <?= $statusFilter == 'active' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.status.active')) ?></option>
                                            <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.status.inactive')) ?></option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.common.role')) ?></label>
                                        <select name="role_filter" class="form-select form-select-sm">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.all')) ?></option>
                                            <?php foreach (AccessControl::roles() as $roleKey => $roleLabel): ?>
                                                <option value="<?= htmlspecialchars($roleKey) ?>" <?= $roleFilter == $roleKey ? 'selected' : ''; ?>><?= htmlspecialchars($roleLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.balance_from')) ?></label>
                                        <input type="number" name="balance_min" class="form-control form-control-sm" value="<?= safeEcho($balanceMin); ?>" placeholder="Min" step="0.01">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.balance_to')) ?></label>
                                        <input type="number" name="balance_max" class="form-control form-control-sm" value="<?= safeEcho($balanceMax); ?>" placeholder="Max" step="0.01">
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
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.has_jobs')) ?></label>
                                        <select name="has_jobs" class="form-select form-select-sm">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.all_male')) ?></option>
                                            <option value="1" <?= $hasJobs == '1' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.yes')) ?></option>
                                            <option value="0" <?= $hasJobs == '0' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.no')) ?></option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.verified')) ?></label>
                                        <select name="is_verified" class="form-select form-select-sm">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.all_male')) ?></option>
                                            <option value="1" <?= $isVerified == '1' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.yes')) ?></option>
                                            <option value="0" <?= $isVerified == '0' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.no')) ?></option>
                                        </select>
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
                                            <option value="name" <?= $sortColumn == 'name' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.name')) ?></option>
                                            <option value="email" <?= $sortColumn == 'email' ? 'selected' : ''; ?>>Email</option>
                                            <option value="created_at" <?= $sortColumn == 'created_at' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.registration_date')) ?></option>
                                            <option value="account_balance" <?= $sortColumn == 'account_balance' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.common.points')) ?></option>
                                            <option value="registration_ip" <?= $sortColumn == 'registration_ip' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.registration_ip')) ?></option>
                                            <option value="last_login" <?= $sortColumn == 'last_login' ? 'selected' : ''; ?>><?= htmlspecialchars(__t('admin.users.last_login')) ?></option>
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
                            <form method="POST" action="../admin/bulk_users_action.php" id="usersForm">
                                <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <select name="bulk_action" class="form-select form-select-sm me-2" style="width: 200px;">
                                            <option value=""><?= htmlspecialchars(__t('admin.common.bulk_action')) ?></option>
                                            <option value="activate"><?= htmlspecialchars(__t('admin.users.bulk_activate')) ?></option>
                                            <option value="deactivate"><?= htmlspecialchars(__t('admin.users.bulk_deactivate')) ?></option>
                                            <option value="delete"><?= htmlspecialchars(__t('admin.users.bulk_delete')) ?></option>
                                            <option value="export"><?= htmlspecialchars(__t('admin.users.bulk_export')) ?></option>
                                            <option value="message"><?= htmlspecialchars(__t('admin.users.bulk_message')) ?></option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm me-2"><?= htmlspecialchars(__t('admin.common.apply')) ?></button>

                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="bi bi-download"></i> <?= htmlspecialchars(__t('admin.common.export')) ?>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><button type="submit" formaction="export_users.php?format=csv" class="dropdown-item">CSV</button></li>
                                                <li><button type="submit" formaction="export_users.php?format=excel" class="dropdown-item">Excel</button></li>
                                                <li><button type="submit" formaction="export_users.php?format=pdf" class="dropdown-item">PDF</button></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="text-muted">
                                        <?= htmlspecialchars(__t('admin.common.found_users', ['count' => number_format($total_users)])) ?>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 1%">
                                                    <input type="checkbox" id="select-all" title="<?= htmlspecialchars(__t('admin.users.bulk_activate')) ?>">
                                                </th>
                                                <th style="width: 1%">ID</th>
                                                <th style="width: 12%"><?= htmlspecialchars(__t('admin.users.table_user')) ?></th>
                                                <th style="width: 18%">Email</th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.common.role')) ?></th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.common.created')) ?></th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.users.last_login')) ?></th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.users.registration_ip')) ?></th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.common.status')) ?></th>
                                                <th style="width: 8%"><?= htmlspecialchars(__t('admin.common.balance')) ?></th>
                                                <th style="width: 20%"><?= htmlspecialchars(__t('admin.common.actions')) ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($users)): ?>
    <?php foreach ($users as $user) :
        $rowClasses = [];

        if (!empty($user['need_change']) && $user['need_change'] == 1) {
            $rowClasses[] = 'table-warning';
        }

        if ($user['status'] === 'deleted') {
            $rowClasses[] = 'table-secondary';
            $rowClasses[] = 'text-muted';
        }

        $rowClass = implode(' ', $rowClasses);
    ?>
        <tr class="<?= $rowClass ?>">
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
                        <small class="text-muted"><?= safeEcho($user['username'] ?? __t('admin.users.no_username')); ?></small>
                    </div>
                </div>
            </td>
            <td>
                <?= safeEcho($user['email']); ?>
                <?php if (!empty($user['email_verified_at'])): ?>
                    <span class="badge bg-success ms-1" title="<?= htmlspecialchars(__t('admin.users.email_verified')) ?>">✓</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <span class="badge <?= AccessControl::badgeClass($user['role']) ?> me-2">
                        <?= safeEcho(AccessControl::roleLabel($user['role'])); ?>
                    </span>
                    <?php if (!empty($user['need_change']) && $user['need_change'] == 1 && canAdminAccess('roles.manage')): ?>
                        <form action="change_role.php" method="POST" class="d-inline">
                            <input type="hidden" name="user_id" value="<?= safeEcho($user['id']); ?>">
                            <input type="hidden" name="current_role" value="<?= safeEcho($user['role']); ?>">
                            <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars(__t('admin.users.change_role')) ?>">
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
                    <span class="text-muted"><?= htmlspecialchars(__t('admin.common.never')) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <small class="text-muted"><?= safeEcho($user['registration_ip']); ?></small>
            </td>
            <td>
                <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : ($user['status'] == 'deleted' ? 'bg-secondary' : 'bg-warning'); ?>">
                    <?= htmlspecialchars($user['status'] == 'active' ? __t('admin.status.active') : ($user['status'] == 'deleted' ? __t('admin.status.deleted') : __t('admin.status.inactive'))); ?>
                </span>
            </td>
            <td>
                <span class="fw-bold <?= ($user['account_balance'] ?? 0) > 0 ? 'text-success' : 'text-muted'; ?>">
                    <?= number_format($user['account_balance'] ?? 0, 2) ?> pkt
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addPointsModal" data-user-id="<?= safeEcho($user['id']); ?>" data-user-name="<?= safeEcho($user['name']); ?>">
                        <i class="bi bi-plus-circle" title="<?= htmlspecialchars(__t('admin.users.add_points')) ?>"></i>
                    </button>

                    <a href="../admin/edit_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-warning" title="<?= htmlspecialchars(__t('common.edit')) ?>">
                        <i class="bi bi-pencil"></i>
                    </a>

                    <a href="../admin/view_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-info" title="<?= htmlspecialchars(__t('admin.common.preview')) ?>">
                        <i class="bi bi-eye"></i>
                    </a>

                    <?php if ($user['status'] == 'active'): ?>
                        <a href="../admin/deactivate_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-secondary" title="<?= htmlspecialchars(__t('admin.common.deactivate')) ?>" onclick="return confirm('<?= htmlspecialchars(__t('admin.users.deactivate_confirm'), ENT_QUOTES) ?>');">
                            <i class="bi bi-person-x"></i>
                        </a>
                    <?php elseif ($user['status'] == 'inactive'): ?>
                        <a href="../admin/activate_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-success" title="<?= htmlspecialchars(__t('admin.common.activate')) ?>">
                            <i class="bi bi-person-check"></i>
                        </a>
                    <?php endif; ?>

                    <?php if ($user['status'] !== 'deleted'): ?>
                        <a href="../admin/delete_user.php?id=<?= safeEcho($user['id']); ?>" class="btn btn-outline-danger" title="<?= htmlspecialchars(__t('admin.common.delete')) ?>" onclick="return confirm('<?= htmlspecialchars(__t('admin.users.delete_confirm'), ENT_QUOTES) ?>');">
                            <i class="bi bi-trash"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary" title="<?= htmlspecialchars(__t('admin.users.already_deleted')) ?>" disabled>
                            <i class="bi bi-trash"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="11" class="text-center py-5">
            <i class="bi bi-people display-4 text-muted"></i>
            <p class="mt-3"><?= htmlspecialchars(__t('admin.users.empty')) ?></p>
            <a href="<?= buildUrl(['search' => null, 'status_filter' => null, 'role_filter' => null, 'date_from' => null, 'date_to' => null, 'balance_min' => null, 'balance_max' => null, 'has_jobs' => null, 'is_verified' => null]) ?>" class="btn btn-primary btn-sm">
                <?= htmlspecialchars(__t('admin.common.clear')) ?>
            </a>
        </td>
    </tr>
<?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalPages > 1): ?>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div>
                                        <span class="text-muted">
                                            <?= htmlspecialchars(__t('admin.common.displayed_users', ['shown' => count($users), 'total' => number_format($total_users)])) ?>
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


<div class="modal fade" id="addPointsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars(__t('admin.users.add_points_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
            </div>
            <form action="add_points.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="user_id" id="modalUserId">

                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(__t('admin.user')) ?></label>
                        <input type="text" class="form-control" id="modalUserName" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.points_amount')) ?></label>
                        <input type="number" name="points_to_add" class="form-control" min="1" max="10000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(__t('admin.users.reason_optional')) ?></label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="<?= htmlspecialchars(__t('admin.users.reason_placeholder')) ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(__t('admin.users.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= htmlspecialchars(__t('admin.users.add_points')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include '../partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('select-all').addEventListener('change', function() {
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });

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

    document.querySelector('form#usersForm').addEventListener('submit', function(e) {
        const bulkAction = document.querySelector('select[name="bulk_action"]').value;
        const selectedUsers = document.querySelectorAll('.user-checkbox:checked');

        if (bulkAction && selectedUsers.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: <?= json_encode(__t('admin.users.no_selected_title'), JSON_UNESCAPED_UNICODE) ?>,
                text: <?= json_encode(__t('admin.users.no_selected_text'), JSON_UNESCAPED_UNICODE) ?>
            });
        }
    });

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

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
