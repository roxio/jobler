<?php
session_start();
include_once('../../models/Job.php');
include_once('../../models/User.php');
include_once('../../models/Database.php');

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
    'open'     => 'Otwarte',
    'active'   => 'Aktywne',
    'closed'   => 'Zamknięte',
    'inactive' => 'Nieaktywne'
];

// Pobierz nazwę kategorii
$categoryName = 'Brak';
foreach ($categories as $cat) {
    if ($cat['id'] == $job['category_id']) {
        $categoryName = $cat['name'];
        break;
    }
}

// Pobierz nazwę użytkownika
$ownerName = 'Nieznany';
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
                    <h5 class="mb-0"><i class="bi bi-eye"></i> Podgląd ogłoszenia #<?= (int)$jobId ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <!-- Przyciski akcji -->
                    <div class="mb-4">
                        <a href="manage_jobs.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Powrót do listy
                        </a>
                        <a href="edit_job.php?id=<?= (int)$jobId ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edytuj ogłoszenie
                        </a>
                    </div>

                    <!-- Informacje podstawowe -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Informacje podstawowe</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted" style="width: 150px;">ID:</td>
                                            <td><strong>#<?= (int)$job['id'] ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Tytuł:</td>
                                            <td><?= htmlspecialchars($job['title']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Status:</td>
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
                                            <td class="text-muted">Kategoria:</td>
                                            <td><?= htmlspecialchars($categoryName) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Wymagane punkty:</td>
                                            <td><strong class="text-primary"><?= (int)$job['points_required'] ?> pkt</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-person"></i> Informacje o autorze</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted" style="width: 150px;">Autor:</td>
                                            <td>
                                                <a href="view_user.php?id=<?= (int)$job['user_id'] ?>">
                                                    <?= htmlspecialchars($ownerName) ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">ID autora:</td>
                                            <td>#<?= (int)$job['user_id'] ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Data utworzenia:</td>
                                            <td><?= date('Y-m-d H:i:s', strtotime($job['created_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Data aktualizacji:</td>
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
                            <h6 class="mb-0"><i class="bi bi-text-paragraph"></i> Opis</h6>
                        </div>
                        <div class="card-body">
                            <div class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($job['description']) ?></div>
                        </div>
                    </div>

                    <!-- Zdjęcia (jeśli istnieją) -->
                    <?php if (!empty($jobImages)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-images"></i> Załączone zdjęcia</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($jobImages as $image): ?>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="card">
                                        <img src="<?= htmlspecialchars($image['image_path']) ?>" class="card-img-top" alt="Zdjęcie zlecenia">
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
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Historia zmian</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Admin</th>
                                            <th>Zmiana</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($changeHistory as $history): ?>
                                        <tr>
                                            <td><small><?= date('Y-m-d H:i:s', strtotime($history['changed_at'])) ?></small></td>
                                            <td><small><?= htmlspecialchars($history['admin_name'] ?? 'System') ?></small></td>
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
