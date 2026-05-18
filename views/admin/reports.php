<?php
session_start();
include_once('../../models/Database.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

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
            $title = 'Nowi użytkownicy w czasie';
            $columns = ['Data', 'Nowi użytkownicy'];
            $sql = "SELECT DATE(created_at) AS report_date, COUNT(*) AS total FROM users WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY DATE(created_at) ORDER BY report_date DESC LIMIT :limit";
            break;

        case 'users_by_role':
            $title = 'Użytkownicy według roli';
            $columns = ['Rola', 'Liczba użytkowników'];
            $sql = "SELECT role, COUNT(*) AS total FROM users WHERE 1=1 GROUP BY role ORDER BY total DESC LIMIT :limit";
            break;

        case 'jobs_by_status':
            $title = 'Ogłoszenia według statusu';
            $columns = ['Status', 'Liczba ogłoszeń'];
            $sql = "SELECT status, COUNT(*) AS total FROM jobs WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY status ORDER BY total DESC LIMIT :limit";
            break;

        case 'jobs_by_category':
        case 'categories_job_count':
            $title = $reportKey === 'jobs_by_category' ? 'Ogłoszenia według kategorii' : 'Kategorie według liczby ogłoszeń';
            $columns = ['ID kategorii', 'Kategoria', 'Liczba ogłoszeń'];
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
            $title = 'Ogłoszenia bez ofert';
            $columns = ['ID', 'Tytuł', 'Status', 'Użytkownik', 'Data utworzenia'];
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
            $title = 'Oferty według statusu';
            $columns = ['Status', 'Liczba ofert'];
            $statusColumn = columnExists($pdo, 'responses', 'status') ? 'status' : "'pending'";
            $sql = "SELECT $statusColumn AS status, COUNT(*) AS total FROM responses WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY $statusColumn ORDER BY total DESC LIMIT :limit";
            break;

        case 'executor_success':
            $title = 'Skuteczność wykonawców';
            $columns = ['ID wykonawcy', 'Wykonawca', 'Oferty', 'Zaakceptowane', 'Skuteczność %'];
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
            $title = 'Konwersacje wymagające interwencji';
            $columns = ['Konwersacja', 'Zlecenie', 'Otwarte zgłoszenia', 'Ostatnie zgłoszenie'];
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
            $title = 'Zgłoszenia według typu';
            $columns = ['Typ zgłoszenia', 'Liczba zgłoszeń'];
            if (!tableExists($pdo, 'conversation_reports')) {
                return compact('title', 'columns') + ['rows' => []];
            }
            $sql = "SELECT report_type, COUNT(*) AS total FROM conversation_reports WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY report_type ORDER BY total DESC LIMIT :limit";
            break;

        case 'most_reported_users':
            $title = 'Najczęściej zgłaszani użytkownicy';
            $columns = ['ID użytkownika', 'Użytkownik', 'Liczba zgłoszeń'];
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
            $title = 'Wiadomości ukryte przez administrację';
            $columns = ['ID', 'Konwersacja', 'Nadawca', 'Treść', 'Data'];
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
            $title = 'Transakcje w czasie';
            $columns = ['Data', 'Liczba transakcji', 'Suma'];
            $sql = "SELECT DATE(created_at) AS report_date, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
                    FROM transactions
                    WHERE 1=1";
            $sql .= dateFilterSql('created_at', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY DATE(created_at) ORDER BY report_date DESC LIMIT :limit";
            break;

        case 'system_errors_time':
            $title = 'Błędy systemowe w czasie';
            $columns = ['Data', 'Liczba błędów'];
            $sql = "SELECT DATE(error_time) AS report_date, COUNT(*) AS total
                    FROM system_logs
                    WHERE log_level = 'ERROR'";
            $sql .= dateFilterSql('error_time', $params, $dateFrom, $dateTo);
            $sql .= " GROUP BY DATE(error_time) ORDER BY report_date DESC LIMIT :limit";
            break;

        case 'admin_logins':
            $title = 'Logowania administratorów';
            $columns = ['ID', 'Administrator', 'IP', 'Data logowania'];
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
            $title = 'Logowania użytkowników';
            $columns = ['ID', 'Użytkownik', 'Email', 'IP', 'Sukces', 'Data logowania'];
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
    'new_users_time' => 'Nowi użytkownicy w czasie',
    'users_by_role' => 'Użytkownicy według roli',
    'jobs_by_status' => 'Ogłoszenia według statusu',
    'jobs_by_category' => 'Ogłoszenia według kategorii',
    'jobs_without_offers' => 'Ogłoszenia bez ofert',
    'offers_by_status' => 'Oferty według statusu',
    'executor_success' => 'Skuteczność wykonawców',
    'conversations_intervention' => 'Konwersacje wymagające interwencji',
    'reports_by_type' => 'Zgłoszenia według typu',
    'most_reported_users' => 'Najczęściej zgłaszani użytkownicy',
    'hidden_messages' => 'Wiadomości ukryte przez administrację',
    'categories_job_count' => 'Kategorie według liczby ogłoszeń',
    'transactions_time' => 'Transakcje w czasie',
    'system_errors_time' => 'Błędy systemowe w czasie',
    'admin_logins' => 'Logowania administratorów',
    'user_logins' => 'Logowania użytkowników',
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
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Raporty</h5>
                            <a class="btn btn-sm btn-outline-success" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">
                                <i class="bi bi-filetype-csv"></i> Eksport CSV
                            </a>
                        </div>

                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Raport</label>
                                    <select name="report" class="form-select">
                                        <?php foreach ($reports as $key => $label): ?>
                                            <option value="<?= safeEcho($key) ?>" <?= $reportKey === $key ? 'selected' : '' ?>>
                                                <?= safeEcho($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Data od</label>
                                    <input type="date" name="date_from" class="form-control" value="<?= safeEcho($dateFrom) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Data do</label>
                                    <input type="date" name="date_to" class="form-control" value="<?= safeEcho($dateTo) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Szukaj</label>
                                    <input type="text" name="search" class="form-control" value="<?= safeEcho($search) ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Limit</label>
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
                                                    Brak danych dla wybranych filtrów.
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
                            <p class="text-muted mb-0">Wyniki: <?= count($reportData['rows']) ?>. Eksport CSV używa tych samych filtrów.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
