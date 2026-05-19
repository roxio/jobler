<?php
session_start();
include_once('../../models/Job.php');
include_once('../../models/User.php');
include_once('../../models/Database.php');
include_once('../../models/Language.php');

// Sprawdź uprawnienia
require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_jobs.php?status=error');
    exit;
}

$jobId = (int)$_GET['id'];
$pdo   = Database::getConnection();

$jobModel  = new Job();
$userModel = new User();

// Pobierz zlecenie
$job = $jobModel->getJobDetails($jobId);
if (!$job) {
    header('Location: manage_jobs.php?status=error&message=not_found');
    exit;
}

// Pobierz kategorie i użytkowników
$categories = $jobModel->getCategories();
$allUsers   = $userModel->getAllUsers();

// Pobierz zdjęcia zlecenia (jeśli tabela istnieje)
$jobImages = [];
try {
    $imgStmt = $pdo->prepare("SELECT * FROM job_images WHERE job_id = ? ORDER BY created_at ASC");
    $imgStmt->execute([$jobId]);
    $jobImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela może nie istnieć — ignorujemy
}

// Pobierz historię zmian
$changeHistory = [];
try {
    $histStmt = $pdo->prepare(
        "SELECT jch.*, u.name AS admin_name
         FROM job_change_history jch
         LEFT JOIN users u ON jch.admin_id = u.id
         WHERE jch.job_id = ?
         ORDER BY jch.changed_at DESC
         LIMIT 20"
    );
    $histStmt->execute([$jobId]);
    $changeHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela może nie istnieć
}

// Mapowanie statusów na polskie nazwy
$statusLabels = [
    'open'     => __t('admin.jobs.open'),
    'active'   => __t('admin.jobs.active'),
    'closed'   => __t('admin.jobs.closed'),
    'inactive' => __t('admin.jobs.inactive')
];

// Pobierz nazwę kategorii
$categoryName = __t('admin.jobs.none_category');
foreach ($categories as $cat) {
    if ($cat['id'] == $job['category_id']) {
        $categoryName = $cat['name'];
        break;
    }
}

// Pobierz nazwę użytkownika
$ownerName = __t('admin.jobs.unknown_user');
foreach ($allUsers as $user) {
    if ($user['id'] == $job['user_id']) {
        $ownerName = $user['name'];
        break;
    }
}
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-eye"></i> <?= htmlspecialchars(__t('admin.view_job.title', ['id' => (int)$jobId])) ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <!-- Przyciski akcji -->
                    <div class="mb-4">
                        <a href="manage_jobs.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('admin.back_to_list')) ?>
                        </a>
                        <a href="edit_job.php?id=<?= (int)$jobId ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> <?= htmlspecialchars(__t('admin.view_job.edit')) ?>
                        </a>
                    </div>

                    <!-- Informacje podstawowe -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> <?= htmlspecialchars(__t('admin.basic_info')) ?></h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted" style="width: 150px;">ID:</td>
                                            <td><strong>#<?= (int)$job['id'] ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(__t('admin.common.title')) ?>:</td>
                                            <td><?= htmlspecialchars($job['title']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(__t('admin.common.status')) ?>:</td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $job['status'] == 'active' ? 'success' : 
                                                    ($job['status'] == 'open' ? 'primary' : 
                                                    ($job['status'] == 'closed' ? 'secondary' : 'warning'))
                                                ?>">
                                                    <?= $statusLabels[$job['status']] ?? $job['status'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(__t('admin.common.category')) ?>:</td>
                                            <td><?= htmlspecialchars($categoryName) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(__t('admin.view_job.required_points')) ?>:</td>
                                            <td><strong class="text-primary"><?= (int)$job['points_required'] ?> pkt</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-person"></i> <?= htmlspecialchars(__t('admin.view_job.author_info')) ?></h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted" style="width: 150px;"><?= htmlspecialchars(__t('admin.view_job.author')) ?>:</td>
                                            <td>
                                                <a href="view_user.php?id=<?= (int)$job['user_id'] ?>">
                                                    <?= htmlspecialchars($ownerName) ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(__t('admin.view_job.author_id')) ?>:</td>
                                            <td>#<?= (int)$job['user_id'] ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(__t('admin.view_job.created_date')) ?>:</td>
                                            <td><?= date('Y-m-d H:i:s', strtotime($job['created_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(__t('admin.view_job.updated_date')) ?>:</td>
                                            <td><?= date('Y-m-d H:i:s', strtotime($job['updated_at'])) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Opis -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-text-paragraph"></i> <?= htmlspecialchars(__t('admin.description')) ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($job['description']) ?></div>
                        </div>
                    </div>

                    <!-- Zdjęcia (jeśli istnieją) -->
                    <?php if (!empty($jobImages)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-images"></i> <?= htmlspecialchars(__t('admin.view_job.attached_images')) ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($jobImages as $image): ?>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="card">
                                        <img src="<?= htmlspecialchars($image['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars(__t('admin.edit_job.image_alt')) ?>">
                                        <div class="card-footer text-muted small">
                                            <?= date('Y-m-d H:i', strtotime($image['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Historia zmian -->
                    <?php if (!empty($changeHistory)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> <?= htmlspecialchars(__t('admin.change_history')) ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th><?= htmlspecialchars(__t('admin.date')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.admin')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.change')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($changeHistory as $history): ?>
                                        <tr>
                                            <td><small><?= date('Y-m-d H:i:s', strtotime($history['changed_at'])) ?></small></td>
                                            <td><small><?= htmlspecialchars($history['admin_name'] ?? __t('admin.system')) ?></small></td>
                                            <td><small><?= htmlspecialchars($history['change_description']) ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
