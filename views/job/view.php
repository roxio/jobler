<?php
session_start();
require_once '../../config/config.php';
include_once('../../models/Job.php');
include_once('../../models/Language.php');

$currentLocale = Language::current('frontend');


$jobModel = new Job();


$jobId = isset($_GET['id']) ? intval($_GET['id']) : 0;


$job = $jobModel->getJobDetails($jobId);

if (!$job) {

    http_response_code(404);
    echo "<h1>" . htmlspecialchars(__t('job.not_found')) . "</h1>";
    echo "<p><a href='/?lang=" . urlencode($currentLocale) . "'>" . htmlspecialchars(__t('page.back_home')) . "</a></p>";
    exit;
}


$isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] === $job['user_id'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($currentLocale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__t('job.details_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h2 class="h4 mb-0"><?= htmlspecialchars($job['title']) ?></h2>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong><?= htmlspecialchars(__t('job.description')) ?>:</strong> <?= nl2br(htmlspecialchars($job['description'])) ?></p>
                <p class="mb-2"><strong><?= htmlspecialchars(__t('home.added')) ?>:</strong> <?= htmlspecialchars($job['created_at']) ?></p>
                <p class="mb-2"><strong><?= htmlspecialchars(__t('job.status')) ?>:</strong> <?= htmlspecialchars($job['status']) ?></p>
                <p class="mb-2"><strong><?= htmlspecialchars(__t('job.author')) ?>:</strong> <?= htmlspecialchars($job['user_name'] ?? __t('job.unknown_author')) ?></p>
            </div>
        </div>

        <?php if ($isOwner): ?>
            <div class="mt-4">
                <a href="/views/user/edit_job.php?id=<?= $job['id'] ?>" class="btn btn-warning"><?= htmlspecialchars(__t('job.edit')) ?></a>
                <a href="/views/user/delete_job.php?id=<?= $job['id'] ?>" class="btn btn-danger" onclick="return confirm('<?= htmlspecialchars(__t('job.delete_confirm'), ENT_QUOTES) ?>')"><?= htmlspecialchars(__t('job.delete')) ?></a>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="/?lang=<?= urlencode($currentLocale) ?>" class="btn btn-secondary"><?= htmlspecialchars(__t('job.back_home')) ?></a>
        </div>
    </div>
    <?php include '../../templates/footer.php'; ?>
    <script src=".https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
