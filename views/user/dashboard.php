<?php
session_start();

include_once('../../models/User.php');
include_once('../../models/Executor.php');
include_once('../../models/Job.php');
include_once('../../models/Database.php');
include_once('../../models/Language.php');
include_once('../../models/Rating.php');

$currentLocale = Language::current('frontend');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userModel = new User();
$executorModel = new Executor();
$jobModel = new Job();
$ratingModel = new Rating();
$pdo = Database::getConnection();
$user = $userModel->getUserById($userId);
$userName = $user['name'] ?? ($_SESSION['user_name'] ?? '');
$ratingSummary = $ratingModel->getSummaryForUser($userId);
$averageRating = (float)($ratingSummary['average_rating'] ?? 0);
$ratingCount = (int)($ratingSummary['rating_count'] ?? 0);

$jobModel->archiveExpiredJobs();
$jobModel->processCompletionTimeouts();

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
        SUM(status = 'completed') AS completed_jobs,
        SUM(status = 'under_review') AS under_review_jobs,
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

include('../partials/header.php');
?>

<div class="container">
    <?php if (isset($_GET['status'])): ?>
        <?php
        $isError = $_GET['status'] === 'error';
        $messages = [
            'accepted' => __t('user.offer_accepted_message'),
            'csrf' => __t('cms.security_error'),
            'accept_failed' => __t('user.accept_failed'),
            'profile_saved' => __t('auth.profile_saved'),
            'job_created' => __t('user.job_created'),
        ];
        $messageKey = $isError ? ($_GET['message'] ?? '') : $_GET['status'];
        ?>
        <div class="alert alert-<?= $isError ? 'danger' : 'success' ?>">
            <?= htmlspecialchars($messages[$messageKey] ?? __t('user.operation_done')) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('user.dashboard_title')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('user.dashboard_intro', ['name' => $userName])) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="create_job.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> <?= htmlspecialchars(__t('user.add_job')) ?></a>
            <a href="job_list.php" class="btn btn-outline-primary"><i class="bi bi-list-task"></i> <?= htmlspecialchars(__t('user.my_jobs')) ?></a>
            <a href="edit_profile.php" class="btn btn-outline-secondary"><i class="bi bi-person-gear"></i> <?= htmlspecialchars(__t('user.edit_profile')) ?></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('user.all_jobs')) ?></div>
                    <div class="h3 mb-0"><?= (int)($jobStats['total_jobs'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('user.open_jobs')) ?></div>
                    <div class="h3 mb-0"><?= (int)($jobStats['open_jobs'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('user.offers_to_decide')) ?></div>
                    <div class="h3 mb-0"><?= (int)($offerStats['pending_offers'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('user.average_rating')) ?></div>
                    <div class="h3 mb-0"><?= $ratingCount > 0 ? htmlspecialchars(number_format($averageRating, 2, ',', ' ')) : '-' ?></div>
                    <div class="small text-muted"><?= htmlspecialchars(__t('user.rating_count', ['count' => $ratingCount])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('user.unread_messages')) ?></div>
                    <div class="h3 mb-0"><?= $unreadMessages ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.profile')) ?></h2>
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
                        <dt class="col-5"><?= htmlspecialchars(__t('user.username')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($user['username'] ?? '-') ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.phone')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($user['phone'] ?: '-') ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.balance')) ?></dt>
                        <dd class="col-7"><?= (int)($user['account_balance'] ?? 0) ?> <?= htmlspecialchars(__t('user.points')) ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.registration')) ?></dt>
                        <dd class="col-7"><?= !empty($user['created_at']) ? date('d.m.Y', strtotime($user['created_at'])) : '-' ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.quick_actions')) ?></h2>
                </div>
                <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action" href="create_job.php"><i class="bi bi-plus-circle me-2"></i><?= htmlspecialchars(__t('user.create_new_job')) ?></a>
                    <a class="list-group-item list-group-item-action" href="job_list.php"><i class="bi bi-folder2-open me-2"></i><?= htmlspecialchars(__t('user.manage_jobs')) ?></a>
                    <a class="list-group-item list-group-item-action" href="job_list.php"><i class="bi bi-chat-dots me-2"></i><?= htmlspecialchars(__t('user.check_conversations')) ?></a>
                    <a class="list-group-item list-group-item-action" href="edit_profile.php"><i class="bi bi-person-gear me-2"></i><?= htmlspecialchars(__t('user.change_profile')) ?></a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.recent_jobs')) ?></h2>
                    <a href="job_list.php" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars(__t('user.see_all')) ?></a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentJobs)): ?>
                        <div class="text-center py-4">
                            <p class="mb-3"><?= htmlspecialchars(__t('user.no_jobs')) ?></p>
                            <a href="create_job.php" class="btn btn-success"><?= htmlspecialchars(__t('user.add_first_job')) ?></a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th><?= htmlspecialchars(__t('user.job_column')) ?></th>
                                        <th><?= htmlspecialchars(__t('job.status')) ?></th>
                                        <th><?= htmlspecialchars(__t('user.offers')) ?></th>
                                        <th><?= htmlspecialchars(__t('home.added')) ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentJobs as $job): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($job['title']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($job['category_name'] ?? __t('home.no_category')) ?></small>
                                            </td>
                                            <td><span class="badge <?= $statusClasses[$job['status']] ?? 'bg-secondary' ?>"><?= $statusLabels[$job['status']] ?? htmlspecialchars($job['status']) ?></span></td>
                                            <td><?= (int)$job['offer_count'] ?></td>
                                            <td><?= date('d.m.Y', strtotime($job['created_at'])) ?></td>
                                            <td class="text-end">
                                                <?php if ((int)$job['offer_count'] === 0): ?>
                                                    <a href="edit_job.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars(__t('job.edit')) ?></a>
                                                <?php else: ?>
                                                    <a href="job_view.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars(__t('user.details_and_offers')) ?></a>
                                                <?php endif; ?>
                                            </td>
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
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.executor_offers')) ?></h2>
                    <span class="badge bg-primary"><?= count($responses) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($offersByJob)): ?>
                        <p class="mb-0"><?= htmlspecialchars(__t('user.no_offers')) ?></p>
                    <?php else: ?>
                        <div class="accordion" id="jobOffersAccordion">
                            <?php foreach ($offersByJob as $jobId => $job): ?>
                                <div class="accordion-item">
                                    <h3 class="accordion-header" id="jobHeading<?= $jobId ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#jobOffers<?= $jobId ?>">
                                            <?= htmlspecialchars($job['title']) ?>
                                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars(__t('user.offer_count', ['count' => count($job['offers'])])) ?></span>
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
                                                    $isWithdrawn = $response['response_status'] === 'withdrawn';
                                                    ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                                            <div>
                                                                <h4 class="h6 mb-1"><?= htmlspecialchars($response['executor_name']) ?></h4>
                                                                <p class="mb-1"><strong><?= htmlspecialchars(__t('user.initial_price')) ?>:</strong> <?= $response['proposed_price'] !== null ? htmlspecialchars(number_format((float)$response['proposed_price'], 2, ',', ' ')) : htmlspecialchars(__t('user.not_provided')) ?></p>
                                                                <p class="mb-1"><strong><?= htmlspecialchars(__t('user.work_scope')) ?>:</strong><br><?= nl2br(htmlspecialchars($response['scope'] ?: __t('user.not_provided'))) ?></p>
                                                                <small class="text-muted"><?= htmlspecialchars(__t('user.submitted_at')) ?>: <?= date('d-m-Y H:i', strtotime($response['created_at'])) ?>, <?= htmlspecialchars(__t('user.reserved_points')) ?>: <?= (int)$response['points_reserved'] ?></small>
                                                            </div>
                                                            <span class="badge <?= $isAccepted ? 'bg-success' : (($isRejected || $isWithdrawn) ? 'bg-secondary' : 'bg-warning text-dark') ?>">
                                                                <?= htmlspecialchars($isAccepted ? __t('status.accepted') : ($isWithdrawn ? __t('status.withdrawn') : ($isRejected ? __t('status.rejected') : __t('status.pending')))) ?>
                                                            </span>
                                                        </div>

                                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                                            <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($conversationId) ?>&job_id=<?= (int)$response['job_id'] ?>" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars(__t('user.reply_conversation')) ?></a>
                                                            <?php if (!$isAccepted && !$isRejected && !$isWithdrawn && $job['job_status'] === 'open'): ?>
                                                                <form method="POST" onsubmit="return confirm('<?= htmlspecialchars(__t('user.accept_offer_confirm'), ENT_QUOTES) ?>');">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <input type="hidden" name="action" value="accept_response">
                                                                    <input type="hidden" name="response_id" value="<?= (int)$response['response_id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-success"><?= htmlspecialchars(__t('user.accept_offer')) ?></button>
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
