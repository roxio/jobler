<?php
session_start();

include_once('../../models/User.php');
include_once('../../models/Executor.php');
include_once('../../models/Database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userModel = new User();
$executorModel = new Executor();
$pdo = Database::getConnection();
$user = $userModel->getUserById($userId);
$userName = $user['name'] ?? ($_SESSION['user_name'] ?? '');

foreach ([
    'deleted_at' => "ALTER TABLE jobs ADD COLUMN deleted_at DATETIME DEFAULT NULL",
    'archived_at' => "ALTER TABLE jobs ADD COLUMN archived_at DATETIME DEFAULT NULL",
    'archive_reason' => "ALTER TABLE jobs ADD COLUMN archive_reason VARCHAR(80) DEFAULT NULL",
] as $column => $sql) {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM jobs LIKE " . $pdo->quote($column));
    if (!$columnStmt || !$columnStmt->fetch(PDO::FETCH_ASSOC)) {
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'accept_response') {
    $token = $_POST['csrf_token'] ?? '';
    $responseId = isset($_POST['response_id']) ? (int)$_POST['response_id'] : 0;

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: dashboard.php?status=error&message=csrf');
        exit;
    }

    if ($responseId <= 0 || !$executorModel->acceptResponse($userId, $responseId)) {
        header('Location: dashboard.php?status=error&message=accept_failed');
        exit;
    }

    header('Location: dashboard.php?status=accepted');
    exit;
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$responses = $executorModel->getUserJobOffers($userId);
$offersByJob = [];
foreach ($responses as $response) {
    $jobId = (int)$response['job_id'];
    if (!isset($offersByJob[$jobId])) {
        $offersByJob[$jobId] = [
            'title' => $response['title'],
            'job_status' => $response['job_status'],
            'offers' => [],
        ];
    }
    $offersByJob[$jobId]['offers'][] = $response;
}

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
$jobStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$offerStatsStmt = $pdo->prepare("
    SELECT
        COUNT(r.id) AS total_offers,
        SUM(r.status = 'pending') AS pending_offers,
        SUM(r.status = 'accepted') AS accepted_offers
    FROM responses r
    INNER JOIN jobs j ON j.id = r.job_id
    WHERE j.user_id = :user_id AND j.deleted_at IS NULL AND j.archived_at IS NULL
");
$offerStatsStmt->execute(['user_id' => $userId]);
$offerStats = $offerStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND read_status = 0");
$messagesStmt->execute(['user_id' => $userId]);
$unreadMessages = (int)$messagesStmt->fetchColumn();

$recentJobsStmt = $pdo->prepare("
    SELECT j.*, c.name AS category_name, COUNT(r.id) AS offer_count
    FROM jobs j
    LEFT JOIN categories c ON c.id = j.category_id
    LEFT JOIN responses r ON r.job_id = j.id
    WHERE j.user_id = :user_id AND j.deleted_at IS NULL AND j.archived_at IS NULL
    GROUP BY j.id
    ORDER BY j.created_at DESC
    LIMIT 6
");
$recentJobsStmt->execute(['user_id' => $userId]);
$recentJobs = $recentJobsStmt->fetchAll(PDO::FETCH_ASSOC);

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

include('../partials/header.php');
?>

<div class="container">
    <?php if (isset($_GET['status'])): ?>
        <?php
        $isError = $_GET['status'] === 'error';
        $messages = [
            'accepted' => 'Oferta została zaakceptowana. Punkty pozostałych wykonawców zostały zwrócone.',
            'csrf' => 'Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.',
            'accept_failed' => 'Nie udało się zaakceptować oferty.',
            'profile_saved' => 'Dane profilu zostały zapisane.',
            'job_created' => 'Ogłoszenie zostało dodane.',
        ];
        $messageKey = $isError ? ($_GET['message'] ?? '') : $_GET['status'];
        ?>
        <div class="alert alert-<?= $isError ? 'danger' : 'success' ?>">
            <?= htmlspecialchars($messages[$messageKey] ?? 'Operacja zakończona.') ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Panel użytkownika</h1>
            <p class="text-muted mb-0">Witaj, <?= htmlspecialchars($userName) ?>. Tutaj zarządzasz ogłoszeniami i ofertami wykonawców.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="create_job.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> Dodaj ogłoszenie</a>
            <a href="job_list.php" class="btn btn-outline-primary"><i class="bi bi-list-task"></i> Moje ogłoszenia</a>
            <a href="edit_profile.php" class="btn btn-outline-secondary"><i class="bi bi-person-gear"></i> Edytuj profil</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Wszystkie ogłoszenia</div>
                    <div class="h3 mb-0"><?= (int)($jobStats['total_jobs'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Otwarte</div>
                    <div class="h3 mb-0"><?= (int)($jobStats['open_jobs'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Oferty do decyzji</div>
                    <div class="h3 mb-0"><?= (int)($offerStats['pending_offers'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Nieprzeczytane wiadomości</div>
                    <div class="h3 mb-0"><?= $unreadMessages ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Profil</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:1.4rem;">
                            <?= htmlspecialchars(strtoupper(substr($userName, 0, 1))) ?>
                        </div>
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars($userName) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                        </div>
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Nazwa</dt>
                        <dd class="col-7"><?= htmlspecialchars($user['username'] ?? '-') ?></dd>
                        <dt class="col-5">Telefon</dt>
                        <dd class="col-7"><?= htmlspecialchars($user['phone'] ?: '-') ?></dd>
                        <dt class="col-5">Saldo</dt>
                        <dd class="col-7"><?= (int)($user['account_balance'] ?? 0) ?> punktów</dd>
                        <dt class="col-5">Rejestracja</dt>
                        <dd class="col-7"><?= !empty($user['created_at']) ? date('d.m.Y', strtotime($user['created_at'])) : '-' ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0">Szybkie akcje</h2>
                </div>
                <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action" href="create_job.php"><i class="bi bi-plus-circle me-2"></i>Wystaw nowe ogłoszenie</a>
                    <a class="list-group-item list-group-item-action" href="job_list.php"><i class="bi bi-folder2-open me-2"></i>Zarządzaj ogłoszeniami</a>
                    <a class="list-group-item list-group-item-action" href="job_list.php"><i class="bi bi-chat-dots me-2"></i>Sprawdź rozmowy przy ogłoszeniach</a>
                    <a class="list-group-item list-group-item-action" href="edit_profile.php"><i class="bi bi-person-gear me-2"></i>Zmień dane profilu</a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Ostatnie ogłoszenia</h2>
                    <a href="job_list.php" class="btn btn-sm btn-outline-primary">Zobacz wszystkie</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentJobs)): ?>
                        <div class="text-center py-4">
                            <p class="mb-3">Nie masz jeszcze ogłoszeń.</p>
                            <a href="create_job.php" class="btn btn-success">Dodaj pierwsze ogłoszenie</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Ogłoszenie</th>
                                        <th>Status</th>
                                        <th>Oferty</th>
                                        <th>Dodano</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentJobs as $job): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($job['title']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($job['category_name'] ?? 'Bez kategorii') ?></small>
                                            </td>
                                            <td><span class="badge <?= $statusClasses[$job['status']] ?? 'bg-secondary' ?>"><?= $statusLabels[$job['status']] ?? htmlspecialchars($job['status']) ?></span></td>
                                            <td><?= (int)$job['offer_count'] ?></td>
                                            <td><?= date('d.m.Y', strtotime($job['created_at'])) ?></td>
                                            <td class="text-end"><a href="edit_job.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-secondary">Edytuj</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Oferty wykonawców</h2>
                    <span class="badge bg-primary"><?= count($responses) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($offersByJob)): ?>
                        <p class="mb-0">Brak odpowiedzi na Twoje ogłoszenia.</p>
                    <?php else: ?>
                        <div class="accordion" id="jobOffersAccordion">
                            <?php foreach ($offersByJob as $jobId => $job): ?>
                                <div class="accordion-item">
                                    <h3 class="accordion-header" id="jobHeading<?= $jobId ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#jobOffers<?= $jobId ?>">
                                            <?= htmlspecialchars($job['title']) ?>
                                            <span class="badge bg-secondary ms-2"><?= count($job['offers']) ?> ofert</span>
                                            <span class="badge bg-light text-dark ms-2"><?= $statusLabels[$job['job_status']] ?? htmlspecialchars($job['job_status']) ?></span>
                                        </button>
                                    </h3>
                                    <div id="jobOffers<?= $jobId ?>" class="accordion-collapse collapse" data-bs-parent="#jobOffersAccordion">
                                        <div class="accordion-body">
                                            <div class="list-group">
                                                <?php foreach ($job['offers'] as $response): ?>
                                                    <?php
                                                    $conversationId = $response['job_id'] . '_' . min($userId, $response['executor_id']) . '_' . max($userId, $response['executor_id']);
                                                    $isAccepted = $response['response_status'] === 'accepted';
                                                    $isRejected = $response['response_status'] === 'rejected';
                                                    ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                                            <div>
                                                                <h4 class="h6 mb-1"><?= htmlspecialchars($response['executor_name']) ?></h4>
                                                                <p class="mb-1"><strong>Wstępna wycena:</strong> <?= $response['proposed_price'] !== null ? htmlspecialchars(number_format((float)$response['proposed_price'], 2, ',', ' ')) : 'Nie podano' ?></p>
                                                                <p class="mb-1"><strong>Zakres prac:</strong><br><?= nl2br(htmlspecialchars($response['scope'] ?: 'Nie podano')) ?></p>
                                                                <small class="text-muted">Zgłoszono: <?= date('d-m-Y H:i', strtotime($response['created_at'])) ?>, punkty blokady: <?= (int)$response['points_reserved'] ?></small>
                                                            </div>
                                                            <span class="badge <?= $isAccepted ? 'bg-success' : ($isRejected ? 'bg-secondary' : 'bg-warning text-dark') ?>">
                                                                <?= $isAccepted ? 'Zaakceptowana' : ($isRejected ? 'Odrzucona' : 'Oczekuje') ?>
                                                            </span>
                                                        </div>

                                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                                            <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($conversationId) ?>&job_id=<?= (int)$response['job_id'] ?>" class="btn btn-sm btn-outline-primary">Odpowiedz w rozmowie</a>
                                                            <?php if (!$isAccepted && !$isRejected && $job['job_status'] === 'open'): ?>
                                                                <form method="POST" onsubmit="return confirm('Czy na pewno zaakceptować tę ofertę? Pozostałym wykonawcom punkty zostaną zwrócone.');">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <input type="hidden" name="action" value="accept_response">
                                                                    <input type="hidden" name="response_id" value="<?= (int)$response['response_id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-success">Akceptuj ofertę</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
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
    </div>
</div>

<?php include('../partials/footer.php'); ?>
