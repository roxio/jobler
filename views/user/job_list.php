<?php
session_start();

include_once('../../models/Database.php');
include_once('../../models/Job.php');
include_once('../../models/Language.php');

$currentLocale = Language::current('frontend');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = Database::getConnection();
$jobModel = new Job();
$userId = (int)$_SESSION['user_id'];

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

$jobModel->archiveExpiredJobs();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['open', 'in_progress', 'completed', 'under_review', 'closed'];

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
        SUM(status = 'completed') AS completed_jobs,
        SUM(status = 'under_review') AS under_review_jobs,
        SUM(status = 'closed') AS closed_jobs
    FROM jobs
    WHERE user_id = :user_id AND deleted_at IS NULL AND archived_at IS NULL
");
$statsStmt->execute(['user_id' => $userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$statusLabels = [
    'open' => __t('status.open'),
    'in_progress' => __t('status.in_progress'),
    'completed' => __t('status.completed'),
    'under_review' => __t('status.under_review'),
    'closed' => __t('status.closed'),
];

$statusClasses = [
    'open' => 'bg-success',
    'in_progress' => 'bg-warning text-dark',
    'completed' => 'bg-primary',
    'under_review' => 'bg-danger',
    'closed' => 'bg-secondary',
];

$workModeLabels = [
    'remote' => __t('work_mode.remote'),
    'onsite' => __t('work_mode.onsite'),
    'hybrid' => __t('work_mode.hybrid'),
];

include('../partials/header.php');
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('user.my_jobs')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('user.my_jobs_intro')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars(__t('user.dashboard_title')) ?></a>
            <a href="create_job.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> <?= htmlspecialchars(__t('user.add_job')) ?></a>
        </div>
    </div>

    <?php if (($_GET['status'] ?? '') === 'updated'): ?>
        <div class="alert alert-success"><?= htmlspecialchars(__t('user.job_updated')) ?></div>
    <?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'archived'): ?>
        <div class="alert alert-success"><?= htmlspecialchars(__t('user.job_archived')) ?></div>
    <?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'archive_error'): ?>
        <div class="alert alert-danger"><?= htmlspecialchars(__t('user.job_archive_error')) ?></div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small"><?= htmlspecialchars(__t('nav.all')) ?></div><div class="h3 mb-0"><?= (int)($stats['total_jobs'] ?? 0) ?></div></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small"><?= htmlspecialchars(__t('status.open')) ?></div><div class="h3 mb-0"><?= (int)($stats['open_jobs'] ?? 0) ?></div></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small"><?= htmlspecialchars(__t('status.in_progress')) ?></div><div class="h3 mb-0"><?= (int)($stats['in_progress_jobs'] ?? 0) ?></div></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small"><?= htmlspecialchars(__t('status.closed')) ?></div><div class="h3 mb-0"><?= (int)($stats['closed_jobs'] ?? 0) ?></div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.jobs_list')) ?></h2>
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value=""><?= htmlspecialchars(__t('user.all_statuses')) ?></option>
                    <?php foreach ($statusLabels as $status => $label): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($statusFilter !== ''): ?>
                    <a href="job_list.php" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars(__t('home.clear_filters')) ?></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($jobs)): ?>
                <div class="text-center py-5">
                    <p class="mb-3"><?= htmlspecialchars(__t('user.no_jobs_in_view')) ?></p>
                    <a href="create_job.php" class="btn btn-success"><?= htmlspecialchars(__t('user.add_new_job')) ?></a>
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
                                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars(__t('home.job_image_alt')) ?>" class="img-fluid rounded border" style="aspect-ratio: 4 / 3; object-fit: cover; width: 100%;">
                                </div>
                                <div class="col-md-7">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h3 class="h5 mb-0"><?= htmlspecialchars($job['title']) ?></h3>
                                        <span class="badge <?= $statusClasses[$job['status']] ?? 'bg-secondary' ?>"><?= $statusLabels[$job['status']] ?? htmlspecialchars($job['status']) ?></span>
                                        <?php if ($isExpired): ?><span class="badge bg-danger"><?= htmlspecialchars(__t('user.expired')) ?></span><?php endif; ?>
                                    </div>
                                    <div class="text-muted small mb-2">
                                        <?= htmlspecialchars($job['category_name'] ?? __t('home.no_category')) ?> · <?= htmlspecialchars($workModeLabels[$job['work_mode']] ?? $job['work_mode'] ?? '-') ?>
                                    </div>
                                    <?php $shortDescription = strlen($job['description']) > 180 ? substr($job['description'], 0, 180) . '...' : $job['description']; ?>
                                    <p class="mb-2"><?= htmlspecialchars($shortDescription) ?></p>
                                    <div class="d-flex flex-wrap gap-3 small">
                                        <span><strong><?= htmlspecialchars(__t('user.budget')) ?>:</strong> <?= $job['budget_estimate'] !== null ? number_format((float)$job['budget_estimate'], 2, ',', ' ') . ' PLN' : '-' ?></span>
                                        <span><strong><?= htmlspecialchars(__t('user.realization')) ?>:</strong> <?= htmlspecialchars($job['realization_time'] ?: '-') ?></span>
                                        <span><strong><?= htmlspecialchars(__t('user.points')) ?>:</strong> <?= (int)$job['points_required'] ?></span>
                                        <span><strong><?= htmlspecialchars(__t('user.offers')) ?>:</strong> <?= (int)$job['offer_count'] ?></span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <?= htmlspecialchars(__t('home.added')) ?> <?= date('d.m.Y H:i', strtotime($job['created_at'])) ?>
                                        <?php if ($expiresAt): ?> · <?= htmlspecialchars(__t('user.valid_until')) ?> <?= date('d.m.Y H:i', $expiresAt) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-grid gap-2">
                                        <a href="job_view.php?id=<?= (int)$job['id'] ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars(__t('user.details_and_offers')) ?></a>
                                        <?php if ((int)$job['offer_count'] === 0): ?>
                                            <a href="edit_job.php?id=<?= (int)$job['id'] ?>" class="btn btn-outline-primary btn-sm"><?= htmlspecialchars(__t('common.edit')) ?></a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled><?= htmlspecialchars(__t('user.edit_locked_short')) ?></button>
                                        <?php endif; ?>
                                        <a href="delete_job.php?id=<?= (int)$job['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('<?= htmlspecialchars(__t('user.delete_archive_confirm'), ENT_QUOTES) ?>')"><?= htmlspecialchars(__t('common.delete')) ?></a>
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
