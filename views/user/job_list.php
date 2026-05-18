<?php
session_start();

include_once('../../models/Database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = Database::getConnection();
$userId = (int)$_SESSION['user_id'];

function ensureUserJobColumns(PDO $pdo) {
    $columns = [
        'budget_estimate' => "ALTER TABLE jobs ADD COLUMN budget_estimate DECIMAL(10,2) DEFAULT NULL",
        'realization_time' => "ALTER TABLE jobs ADD COLUMN realization_time VARCHAR(120) DEFAULT NULL",
        'validity_days' => "ALTER TABLE jobs ADD COLUMN validity_days INT(11) NOT NULL DEFAULT 7",
        'expires_at' => "ALTER TABLE jobs ADD COLUMN expires_at DATETIME DEFAULT NULL",
        'work_mode' => "ALTER TABLE jobs ADD COLUMN work_mode VARCHAR(20) NOT NULL DEFAULT 'remote'",
        'primary_image' => "ALTER TABLE jobs ADD COLUMN primary_image VARCHAR(255) NOT NULL DEFAULT 'no_image.jpg'",
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
}

function jobImageUrl($filename) {
    $filename = trim((string)$filename);
    if ($filename === '') {
        $filename = 'no_image.jpg';
    }

    if (strpos($filename, '/') === 0) {
        return $filename;
    }

    return '/uploads/jobs/' . rawurlencode($filename);
}

ensureUserJobColumns($pdo);

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

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['open', 'in_progress', 'closed'];

$where = ['j.user_id = :user_id', 'j.deleted_at IS NULL', 'j.archived_at IS NULL'];
$params = ['user_id' => $userId];

if (in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = 'j.status = :status';
    $params['status'] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT j.*, c.name AS category_name, COUNT(r.id) AS offer_count
    FROM jobs j
    LEFT JOIN categories c ON c.id = j.category_id
    LEFT JOIN responses r ON r.job_id = j.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY j.id
    ORDER BY j.created_at DESC
");
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_jobs,
        SUM(status = 'open') AS open_jobs,
        SUM(status = 'in_progress') AS in_progress_jobs,
        SUM(status = 'closed') AS closed_jobs
    FROM jobs
    WHERE user_id = :user_id AND deleted_at IS NULL AND archived_at IS NULL
");
$statsStmt->execute(['user_id' => $userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$statusLabels = [
    'open' => 'Otwarte',
    'in_progress' => 'W realizacji',
    'closed' => 'Zamknięte',
];

$statusClasses = [
    'open' => 'bg-success',
    'in_progress' => 'bg-warning text-dark',
    'closed' => 'bg-secondary',
];

$workModeLabels = [
    'remote' => 'Zdalnie',
    'onsite' => 'Stacjonarnie',
    'hybrid' => 'Hybrydowo',
];

include('../partials/header.php');
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Moje ogłoszenia</h1>
            <p class="text-muted mb-0">Przeglądaj, edytuj i kontroluj status swoich zleceń.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> Kokpit</a>
            <a href="create_job.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> Dodaj ogłoszenie</a>
        </div>
    </div>

    <?php if (($_GET['status'] ?? '') === 'updated'): ?>
        <div class="alert alert-success">Ogłoszenie zostało zaktualizowane.</div>
    <?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'archived'): ?>
        <div class="alert alert-success">Ogłoszenie zostało przeniesione do archiwum. Zablokowane punkty wykonawców zostały zwrócone.</div>
    <?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'archive_error'): ?>
        <div class="alert alert-danger">Nie udało się zarchiwizować ogłoszenia.</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">Wszystkie</div><div class="h3 mb-0"><?= (int)($stats['total_jobs'] ?? 0) ?></div></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">Otwarte</div><div class="h3 mb-0"><?= (int)($stats['open_jobs'] ?? 0) ?></div></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">W realizacji</div><div class="h3 mb-0"><?= (int)($stats['in_progress_jobs'] ?? 0) ?></div></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">Zamknięte</div><div class="h3 mb-0"><?= (int)($stats['closed_jobs'] ?? 0) ?></div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0">Lista ogłoszeń</h2>
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Wszystkie statusy</option>
                    <?php foreach ($statusLabels as $status => $label): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($statusFilter !== ''): ?>
                    <a href="job_list.php" class="btn btn-sm btn-outline-secondary">Wyczyść</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($jobs)): ?>
                <div class="text-center py-5">
                    <p class="mb-3">Nie masz jeszcze ogłoszeń w tym widoku.</p>
                    <a href="create_job.php" class="btn btn-success">Dodaj nowe ogłoszenie</a>
                </div>
            <?php else: ?>
                <div class="vstack gap-3">
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $imageUrl = jobImageUrl($job['primary_image'] ?? 'no_image.jpg');
                        $expiresAt = !empty($job['expires_at']) ? strtotime($job['expires_at']) : null;
                        $isExpired = $expiresAt && $expiresAt < time();
                        ?>
                        <div class="border rounded p-3">
                            <div class="row g-3 align-items-start">
                                <div class="col-md-2">
                                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Zdjęcie ogłoszenia" class="img-fluid rounded border" style="aspect-ratio: 4 / 3; object-fit: cover; width: 100%;">
                                </div>
                                <div class="col-md-7">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h3 class="h5 mb-0"><?= htmlspecialchars($job['title']) ?></h3>
                                        <span class="badge <?= $statusClasses[$job['status']] ?? 'bg-secondary' ?>"><?= $statusLabels[$job['status']] ?? htmlspecialchars($job['status']) ?></span>
                                        <?php if ($isExpired): ?><span class="badge bg-danger">Po terminie</span><?php endif; ?>
                                    </div>
                                    <div class="text-muted small mb-2">
                                        <?= htmlspecialchars($job['category_name'] ?? 'Bez kategorii') ?> · <?= htmlspecialchars($workModeLabels[$job['work_mode']] ?? $job['work_mode'] ?? '-') ?>
                                    </div>
                                    <?php $shortDescription = strlen($job['description']) > 180 ? substr($job['description'], 0, 180) . '...' : $job['description']; ?>
                                    <p class="mb-2"><?= htmlspecialchars($shortDescription) ?></p>
                                    <div class="d-flex flex-wrap gap-3 small">
                                        <span><strong>Budżet:</strong> <?= $job['budget_estimate'] !== null ? number_format((float)$job['budget_estimate'], 2, ',', ' ') . ' PLN' : '-' ?></span>
                                        <span><strong>Realizacja:</strong> <?= htmlspecialchars($job['realization_time'] ?: '-') ?></span>
                                        <span><strong>Punkty:</strong> <?= (int)$job['points_required'] ?></span>
                                        <span><strong>Oferty:</strong> <?= (int)$job['offer_count'] ?></span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Dodano <?= date('d.m.Y H:i', strtotime($job['created_at'])) ?>
                                        <?php if ($expiresAt): ?> · Ważne do <?= date('d.m.Y H:i', $expiresAt) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-grid gap-2">
                                        <a href="edit_job.php?id=<?= (int)$job['id'] ?>" class="btn btn-outline-primary btn-sm">Edytuj</a>
                                        <a href="delete_job.php?id=<?= (int)$job['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Ogłoszenie zniknie z Twojej listy i trafi do archiwum administratora. Zablokowane punkty wykonawców zostaną zwrócone. Kontynuować?')">Usuń</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include('../partials/footer.php'); ?>
