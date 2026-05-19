<?php
session_start();
include_once('../../models/Database.php');
include_once('../../models/Language.php');

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

$pdo = Database::getConnection();

function safeEcho($value, $default = '') {
    return htmlspecialchars((string)($value ?? $default), ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
    $stmt->execute([':table_name' => $tableName]);
    return (bool)$stmt->fetchColumn();
}

function columnExists(PDO $pdo, $tableName, $columnName) {
    if (!tableExists($pdo, $tableName)) {
        return false;
    }

    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tableName` LIKE :column_name");
    $stmt->execute([':column_name' => $columnName]);
    return (bool)$stmt->fetchColumn();
}

function dateFilterSql($column, &$params, $dateFrom, $dateTo) {
    $sql = '';
    if ($dateFrom !== '') {
        $sql .= " AND DATE($column) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql .= " AND DATE($column) <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    return $sql;
}

function fetchReport(PDO $pdo, $reportKey, $dateFrom, $dateTo, $search, $limit) {
    $limit = max(1, min(1000, (int)$limit));
    $params = [];
    $title = '';
    $columns = [];
    $sql = '';

    switch ($reportKey) {
        case 'new_users_time':
            $title = __t('admin.reports.new_users_time');
            $columns = [__t('admin.reports.col.date'), __t('admin.reports.col.new_users')];
            $sql = "SELECT DATE(created_at) AS report_date, COUNT(*) AS total FROM users WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY DATE(created_at) ORDER BY report_date DESC LIMIT :limit";
            break;

        case 'users_by_role':
            $title = __t('admin.reports.users_by_role');
            $columns = [__t('admin.reports.col.role'), __t('admin.reports.col.users_count')];
            $sql = "SELECT role, COUNT(*) AS total FROM users WHERE 1=1 GROUP BY role ORDER BY total DESC LIMIT :limit";
            break;

        case 'jobs_by_status':
            $title = __t('admin.reports.jobs_by_status');
            $columns = [__t('admin.reports.col.status'), __t('admin.reports.col.jobs_count')];
            $sql = "SELECT status, COUNT(*) AS total FROM jobs WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY status ORDER BY total DESC LIMIT :limit";
            break;

        case 'jobs_by_category':
        case 'categories_job_count':
            $title = $reportKey === 'jobs_by_category' ? __t('admin.reports.jobs_by_category') : __t('admin.reports.categories_job_count');
            $columns = [__t('admin.reports.col.category_id'), __t('admin.reports.col.category'), __t('admin.reports.col.jobs_count')];
            $sql = "SELECT c.id, c.name, COUNT(j.id) AS total
                    FROM categories c
                    LEFT JOIN jobs j ON j.category_id = c.id";
            $where = " WHERE 1=1";
            $where .= dateFilterSql('j.created_at', $params, $dateFrom, $dateTo);
            if ($search !== '') {
                $where .= " AND c.name LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= $where . " GROUP BY c.id, c.name ORDER BY total DESC, c.name ASC LIMIT :limit";
            break;

        case 'jobs_without_offers':
            $title = __t('admin.reports.jobs_without_offers');
            $columns = [__t('admin.reports.col.id'), __t('admin.reports.col.title'), __t('admin.reports.col.status'), __t('admin.reports.col.user'), __t('admin.reports.col.created_at')];
            $sql = "SELECT j.id, j.title, j.status, u.name AS user_name, j.created_at
                    FROM jobs j
                    LEFT JOIN users u ON u.id = j.user_id
                    LEFT JOIN responses r ON r.job_id = j.id
                    WHERE r.id IS NULL";
            $sql .= dateFilterSql('j.created_at', $params, $dateFrom, $dateTo);
            if ($search !== '') {
                $sql .= " AND (j.title LIKE :search OR u.name LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= " ORDER BY j.created_at DESC LIMIT :limit";
            break;

        case 'offers_by_status':
            $title = __t('admin.reports.offers_by_status');
            $columns = [__t('admin.reports.col.status'), __t('admin.reports.col.offers_count')];
            $statusColumn = columnExists($pdo, 'responses', 'status') ? 'status' : "'pending'";
            $sql = "SELECT $statusColumn AS status, COUNT(*) AS total FROM responses WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY $statusColumn ORDER BY total DESC LIMIT :limit";
            break;

        case 'executor_success':
            $title = __t('admin.reports.executor_success');
            $columns = [__t('admin.reports.col.executor_id'), __t('admin.reports.col.executor'), __t('admin.reports.col.offers'), __t('admin.reports.col.accepted'), __t('admin.reports.col.success_rate')];
            $statusColumn = columnExists($pdo, 'responses', 'status') ? 'r.status' : "'pending'";
            $sql = "SELECT u.id, u.name,
                           COUNT(r.id) AS total_offers,
                           SUM(CASE WHEN $statusColumn = 'accepted' THEN 1 ELSE 0 END) AS accepted_offers,
                           ROUND((SUM(CASE WHEN $statusColumn = 'accepted' THEN 1 ELSE 0 END) / NULLIF(COUNT(r.id), 0)) * 100, 2) AS success_rate
                    FROM responses r
                    INNER JOIN users u ON u.id = r.executor_id
                    WHERE 1=1";
            $sql .= dateFilterSql('r.created_at', $params, $dateFrom, $dateTo);
            if ($search !== '') {
                $sql .= " AND u.name LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= " GROUP BY u.id, u.name ORDER BY success_rate DESC, total_offers DESC LIMIT :limit";
            break;

        case 'conversations_intervention':
            $title = __t('admin.reports.conversations_intervention');
            $columns = [__t('admin.reports.col.conversation'), __t('admin.reports.col.job'), __t('admin.reports.col.open_reports'), __t('admin.reports.col.last_report')];
            if (!tableExists($pdo, 'conversation_reports')) {
                return compact('title', 'columns') + ['rows' => []];
            }
            $sql = "SELECT conversation_id, job_id, COUNT(*) AS open_reports, MAX(created_at) AS last_report
                    FROM conversation_reports
                    WHERE status = 'open'";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY conversation_id, job_id ORDER BY last_report DESC LIMIT :limit";
            break;

        case 'reports_by_type':
            $title = __t('admin.reports.reports_by_type');
            $columns = [__t('admin.reports.col.report_type'), __t('admin.reports.col.reports_count')];
            if (!tableExists($pdo, 'conversation_reports')) {
                return compact('title', 'columns') + ['rows' => []];
            }
            $sql = "SELECT report_type, COUNT(*) AS total FROM conversation_reports WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY report_type ORDER BY total DESC LIMIT :limit";
            break;

        case 'most_reported_users':
            $title = __t('admin.reports.most_reported_users');
            $columns = [__t('admin.reports.col.user_id'), __t('admin.reports.col.user'), __t('admin.reports.col.reports_count')];
            if (!tableExists($pdo, 'conversation_reports')) {
                return compact('title', 'columns') + ['rows' => []];
            }
            $sql = "SELECT u.id, u.name, COUNT(cr.id) AS total
                    FROM conversation_reports cr
                    LEFT JOIN users u ON u.id = cr.reported_user_id
                    WHERE cr.reported_user_id IS NOT NULL";
            $sql .= dateFilterSql('cr.created_at', $params, $dateFrom, $dateTo);
            if ($search !== '') {
                $sql .= " AND u.name LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= " GROUP BY u.id, u.name ORDER BY total DESC LIMIT :limit";
            break;

        case 'hidden_messages':
            $title = __t('admin.reports.hidden_messages');
            $columns = [__t('admin.reports.col.id'), __t('admin.reports.col.conversation'), __t('admin.reports.col.sender'), __t('admin.reports.col.content'), __t('admin.reports.col.date')];
            if (!columnExists($pdo, 'messages', 'is_hidden')) {
                return compact('title', 'columns') + ['rows' => []];
            }
            $sql = "SELECT m.id, m.conversation_id, u.name AS sender_name, COALESCE(NULLIF(m.content, ''), m.message) AS content, m.created_at
                    FROM messages m
                    LEFT JOIN users u ON u.id = m.sender_id
                    WHERE m.is_hidden = 1";
            $sql .= dateFilterSql('m.created_at', $params, $dateFrom, $dateTo);
            if ($search !== '') {
                $sql .= " AND (m.content LIKE :search OR m.message LIKE :search OR u.name LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= " ORDER BY m.created_at DESC LIMIT :limit";
            break;

        case 'transactions_time':
            $title = __t('admin.reports.transactions_time');
            $columns = [__t('admin.reports.col.date'), __t('admin.reports.col.transactions_count'), __t('admin.reports.col.total')];
            $sql = "SELECT DATE(created_at) AS report_date, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
                    FROM transactions
                    WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY DATE(created_at) ORDER BY report_date DESC LIMIT :limit";
            break;

        case 'system_errors_time':
            $title = __t('admin.reports.system_errors_time');
            $columns = [__t('admin.reports.col.date'), __t('admin.reports.col.errors_count')];
            $sql = "SELECT DATE(error_time) AS report_date, COUNT(*) AS total
                    FROM system_logs
                    WHERE log_level = 'ERROR'";
            $sql .= dateFilterSql('error_time', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY DATE(error_time) ORDER BY report_date DESC LIMIT :limit";
            break;

        case 'admin_logins':
            $title = __t('admin.reports.admin_logins');
            $columns = [__t('admin.reports.col.id'), __t('admin.reports.col.admin'), __t('admin.reports.col.ip'), __t('admin.reports.col.login_date')];
            $sql = "SELECT alh.id, u.name AS admin_name, alh.ip_address, alh.login_time
                    FROM admin_login_history alh
                    LEFT JOIN users u ON u.id = alh.admin_id
                    WHERE 1=1";
            $sql .= dateFilterSql('alh.login_time', $params, $dateFrom, $dateTo);
            if ($search !== '') {
                $sql .= " AND (u.name LIKE :search OR alh.ip_address LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= " ORDER BY alh.login_time DESC LIMIT :limit";
            break;

        case 'user_logins':
            $title = __t('admin.reports.user_logins');
            $columns = [__t('admin.reports.col.id'), __t('admin.reports.col.user'), __t('admin.reports.col.email'), __t('admin.reports.col.ip'), __t('admin.reports.col.success'), __t('admin.reports.col.login_date')];
            $sql = "SELECT ulh.id, u.name AS user_name, u.email, ulh.ip_address, ulh.success, ulh.login_time
                    FROM user_login_history ulh
                    LEFT JOIN users u ON u.id = ulh.user_id
                    WHERE 1=1";
            $sql .= dateFilterSql('ulh.login_time', $params, $dateFrom, $dateTo);
            if ($search !== '') {
                $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR ulh.ip_address LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= " ORDER BY ulh.login_time DESC LIMIT :limit";
            break;

        default:
            return fetchReport($pdo, 'new_users_time', $dateFrom, $dateTo, $search, $limit);
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'title' => $title,
        'columns' => $columns,
        'rows' => $stmt->fetchAll(PDO::FETCH_NUM),
    ];
}

$reports = [
    'new_users_time' => __t('admin.reports.new_users_time'),
    'users_by_role' => __t('admin.reports.users_by_role'),
    'jobs_by_status' => __t('admin.reports.jobs_by_status'),
    'jobs_by_category' => __t('admin.reports.jobs_by_category'),
    'jobs_without_offers' => __t('admin.reports.jobs_without_offers'),
    'offers_by_status' => __t('admin.reports.offers_by_status'),
    'executor_success' => __t('admin.reports.executor_success'),
    'conversations_intervention' => __t('admin.reports.conversations_intervention'),
    'reports_by_type' => __t('admin.reports.reports_by_type'),
    'most_reported_users' => __t('admin.reports.most_reported_users'),
    'hidden_messages' => __t('admin.reports.hidden_messages'),
    'categories_job_count' => __t('admin.reports.categories_job_count'),
    'transactions_time' => __t('admin.reports.transactions_time'),
    'system_errors_time' => __t('admin.reports.system_errors_time'),
    'admin_logins' => __t('admin.reports.admin_logins'),
    'user_logins' => __t('admin.reports.user_logins'),
];

$reportKey = $_GET['report'] ?? 'new_users_time';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$reportData = fetchReport($pdo, $reportKey, $dateFrom, $dateTo, $search, $limit);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = preg_replace('/[^a-z0-9_-]+/i', '_', $reportKey) . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, $reportData['columns'], ';');
    foreach ($reportData['rows'] as $row) {
        fputcsv($output, $row, ';');
    }
    fclose($output);
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
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> <?= htmlspecialchars(__t('admin.reports.title')) ?></h5>
                            <a class="btn btn-sm btn-outline-success" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">
                                <i class="bi bi-filetype-csv"></i> <?= htmlspecialchars(__t('admin.reports.export_csv')) ?>
                            </a>
                        </div>

                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end mb-4">
                                <div class="col-md-4">
                                    <label class="form-label"><?= htmlspecialchars(__t('admin.reports.report')) ?></label>
                                    <select name="report" class="form-select">
                                        <?php foreach ($reports as $key => $label): ?>
                                            <option value="<?= safeEcho($key) ?>" <?= $reportKey === $key ? 'selected' : '' ?>>
                                                <?= safeEcho($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= htmlspecialchars(__t('admin.common.from_date')) ?></label>
                                    <input type="date" name="date_from" class="form-control" value="<?= safeEcho($dateFrom) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= htmlspecialchars(__t('admin.common.to_date')) ?></label>
                                    <input type="date" name="date_to" class="form-control" value="<?= safeEcho($dateTo) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= htmlspecialchars(__t('admin.common.search')) ?></label>
                                    <input type="text" name="search" class="form-control" value="<?= safeEcho($search) ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><?= htmlspecialchars(__t('admin.reports.limit')) ?></label>
                                    <input type="number" min="1" max="1000" name="limit" class="form-control" value="<?= safeEcho($limit) ?>">
                                </div>
                                <div class="col-md-1">
                                    <button class="btn btn-primary w-100" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </form>

                            <h5><?= safeEcho($reportData['title']) ?></h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <?php foreach ($reportData['columns'] as $column): ?>
                                                <th><?= safeEcho($column) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reportData['rows'])): ?>
                                            <tr>
                                                <td colspan="<?= count($reportData['columns']) ?>" class="text-center text-muted py-4">
                                                    <?= htmlspecialchars(__t('admin.reports.no_data')) ?>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reportData['rows'] as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?= safeEcho($value) ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted mb-0"><?= htmlspecialchars(__t('admin.reports.results', ['count' => count($reportData['rows'])])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
