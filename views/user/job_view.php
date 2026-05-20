<?php
session_start();

include_once('../../models/Database.php');
include_once('../../models/Executor.php');
include_once('../../models/Job.php');
include_once('../../models/Language.php');
include_once('../../models/Rating.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = Database::getConnection();
$executorModel = new Executor();
$jobModel = new Job();
$ratingModel = new Rating();
$userId = (int)$_SESSION['user_id'];
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($jobId <= 0) {
    header('Location: job_list.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function jobDetailImageUrl($filename) {
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
$jobModel->processCompletionTimeouts();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: job_view.php?id=' . $jobId . '&status=error');
        exit;
    }

    if ($action === 'accept_response') {
        $responseId = isset($_POST['response_id']) ? (int)$_POST['response_id'] : 0;
        if ($responseId <= 0 || !$executorModel->acceptResponse($userId, $responseId)) {
            header('Location: job_view.php?id=' . $jobId . '&status=accept_error');
            exit;
        }

        header('Location: job_view.php?id=' . $jobId . '&status=accepted');
        exit;
    }

    if ($action === 'mark_complete') {
        header('Location: job_view.php?id=' . $jobId . '&status=' . ($jobModel->markCompletion($jobId, $userId) ? 'completion_marked' : 'completion_error'));
        exit;
    }

    if ($action === 'dispute_completion') {
        header('Location: job_view.php?id=' . $jobId . '&status=' . ($jobModel->disputeCompletion($jobId, $userId) ? 'completion_disputed' : 'completion_error'));
        exit;
    }

    if ($action === 'submit_rating') {
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = trim($_POST['comment'] ?? '');
        header('Location: job_view.php?id=' . $jobId . '&status=' . ($ratingModel->submitRating($jobId, $userId, $rating, $comment) ? 'rating_saved' : 'rating_error'));
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT j.*, c.name AS category_name
    FROM jobs j
    LEFT JOIN categories c ON c.id = j.category_id
    WHERE j.id = :job_id
      AND j.user_id = :user_id
      AND j.deleted_at IS NULL
      AND j.archived_at IS NULL
    LIMIT 1
");
$stmt->execute(['job_id' => $jobId, 'user_id' => $userId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: job_list.php');
    exit;
}

$sort = $_GET['sort'] ?? 'date';
$allowedSorts = ['price', 'deadline', 'rating', 'date'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'date';
}

$offers = array_values(array_filter(
    $executorModel->getUserJobOffers($userId),
    fn($offer) => (int)$offer['job_id'] === $jobId
));
$canEditJob = count($offers) === 0;
$acceptedOffer = null;
foreach ($offers as $offer) {
    if (($offer['response_status'] ?? '') === 'accepted') {
        $acceptedOffer = $offer;
        break;
    }
}
$completionContext = $jobModel->getCompletionContext($jobId, $userId);
$ownRating = $ratingModel->getRatingForJobByReviewer($jobId, $userId);

usort($offers, function ($a, $b) use ($sort) {
    if ($sort === 'price') {
        $aEmpty = $a['proposed_price'] === null || $a['proposed_price'] === '';
        $bEmpty = $b['proposed_price'] === null || $b['proposed_price'] === '';
        if ($aEmpty !== $bEmpty) {
            return $aEmpty ? 1 : -1;
        }
        return (float)$a['proposed_price'] <=> (float)$b['proposed_price'];
    }

    if ($sort === 'deadline') {
        $aDeadline = trim((string)($a['declared_deadline'] ?? ''));
        $bDeadline = trim((string)($b['declared_deadline'] ?? ''));
        if (($aDeadline === '') !== ($bDeadline === '')) {
            return $aDeadline === '' ? 1 : -1;
        }
        return strnatcasecmp($aDeadline, $bDeadline);
    }

    if ($sort === 'rating') {
        $aRating = (float)($a['executor_rating'] ?? 0);
        $bRating = (float)($b['executor_rating'] ?? 0);
        $ratingCompare = $bRating <=> $aRating;
        if ($ratingCompare !== 0) {
            return $ratingCompare;
        }
    }

    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$statusLabels = [
    'open' => __t('status.open'),
    'in_progress' => __t('status.in_progress'),
    'closed' => __t('status.closed'),
    'completed' => __t('status.completed'),
    'under_review' => __t('status.under_review'),
];

$statusClasses = [
    'open' => 'bg-success',
    'in_progress' => 'bg-warning text-dark',
    'closed' => 'bg-secondary',
    'completed' => 'bg-primary',
    'under_review' => 'bg-danger',
];

$workModeLabels = [
    'remote' => __t('work_mode.remote'),
    'onsite' => __t('work_mode.onsite'),
    'hybrid' => __t('work_mode.hybrid'),
];

$sortLabels = [
    'date' => __t('user.offer_sort_date'),
    'price' => __t('user.offer_sort_price'),
    'deadline' => __t('user.offer_sort_deadline'),
    'rating' => __t('user.offer_sort_rating'),
];

$imageUrl = jobDetailImageUrl($job['primary_image'] ?? 'no_image.jpg');
$expiresAt = !empty($job['expires_at']) ? strtotime($job['expires_at']) : null;

include('../partials/header.php');
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('user.job_offer_details')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('user.job_offer_details_intro')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="job_list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('user.back_list')) ?></a>
            <?php if ($canEditJob): ?>
                <a href="edit_job.php?id=<?= $jobId ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> <?= htmlspecialchars(__t('common.edit')) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($_GET['status'] ?? '') === 'accepted'): ?>
        <div class="alert alert-success"><?= htmlspecialchars(__t('user.offer_accepted_message')) ?></div>
    <?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'accept_error'): ?>
        <div class="alert alert-danger"><?= htmlspecialchars(__t('user.accept_failed')) ?></div>
    <?php endif; ?>
    <?php
    $statusMessages = [
        'completion_marked' => ['success', __t('rating.completion_marked')],
        'completion_disputed' => ['warning', __t('rating.completion_disputed')],
        'completion_error' => ['danger', __t('rating.completion_error')],
        'rating_saved' => ['success', __t('rating.saved')],
        'rating_error' => ['danger', __t('rating.error')],
        'edit_locked' => ['warning', __t('user.edit_locked_message')],
        'error' => ['danger', __t('cms.security_error')],
    ];
    $currentStatus = $_GET['status'] ?? '';
    ?>
    <?php if (isset($statusMessages[$currentStatus])): ?>
        <div class="alert alert-<?= htmlspecialchars($statusMessages[$currentStatus][0]) ?>"><?= htmlspecialchars($statusMessages[$currentStatus][1]) ?></div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <div class="card">
                <img src="<?= htmlspecialchars($imageUrl) ?>" class="card-img-top" alt="<?= htmlspecialchars(__t('home.job_image_alt')) ?>" style="aspect-ratio: 16 / 9; object-fit: cover;">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h2 class="h4 mb-0"><?= htmlspecialchars($job['title']) ?></h2>
                        <span class="badge <?= $statusClasses[$job['status']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($statusLabels[$job['status']] ?? $job['status']) ?></span>
                    </div>
                    <div class="text-muted small mb-3">
                        <?= htmlspecialchars($job['category_name'] ?? __t('home.no_category')) ?> &middot;
                        <?= htmlspecialchars($workModeLabels[$job['work_mode']] ?? $job['work_mode'] ?? '-') ?>
                    </div>

                    <p><?= nl2br(htmlspecialchars($job['description'] ?? '')) ?></p>

                    <dl class="row small mb-0">
                        <dt class="col-5"><?= htmlspecialchars(__t('user.budget')) ?></dt>
                        <dd class="col-7"><?= $job['budget_estimate'] !== null ? htmlspecialchars(number_format((float)$job['budget_estimate'], 2, ',', ' ') . ' PLN') : '-' ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.realization')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($job['realization_time'] ?: '-') ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('user.points')) ?></dt>
                        <dd class="col-7"><?= (int)$job['points_required'] ?></dd>
                        <dt class="col-5"><?= htmlspecialchars(__t('home.added')) ?></dt>
                        <dd class="col-7"><?= date('d.m.Y H:i', strtotime($job['created_at'])) ?></dd>
                        <?php if ($expiresAt): ?>
                            <dt class="col-5"><?= htmlspecialchars(__t('user.valid_until')) ?></dt>
                            <dd class="col-7"><?= date('d.m.Y H:i', $expiresAt) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <?php if ($completionContext && in_array($job['status'], ['in_progress', 'completed', 'under_review'], true)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><?= htmlspecialchars(__t('rating.workflow_title')) ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if ($job['status'] === 'under_review'): ?>
                            <div class="alert alert-warning mb-0"><?= htmlspecialchars(__t('rating.under_review_info')) ?></div>
                        <?php elseif ($job['status'] === 'completed'): ?>
                            <p class="mb-2"><?= htmlspecialchars(__t('rating.completed_info')) ?></p>
                            <?php if (!empty($job['review_deadline'])): ?>
                                <div class="small text-muted mb-3"><?= htmlspecialchars(__t('rating.review_deadline')) ?>: <?= date('d.m.Y H:i', strtotime($job['review_deadline'])) ?></div>
                            <?php endif; ?>
                            <?php if ($ownRating): ?>
                                <div class="alert alert-success mb-0"><?= htmlspecialchars(__t('rating.already_added', ['rating' => (int)$ownRating['rating']])) ?></div>
                            <?php elseif ($completionContext['can_rate'] && $acceptedOffer): ?>
                                <form method="POST" class="vstack gap-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="submit_rating">
                                    <div>
                                        <label for="rating" class="form-label"><?= htmlspecialchars(__t('rating.rate_executor', ['name' => $acceptedOffer['executor_name']])) ?></label>
                                        <select name="rating" id="rating" class="form-select" required>
                                            <option value=""><?= htmlspecialchars(__t('rating.select_rating')) ?></option>
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <option value="<?= $i ?>"><?= $i ?>/5</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="comment" class="form-label"><?= htmlspecialchars(__t('rating.comment')) ?></label>
                                        <textarea name="comment" id="comment" class="form-control" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?= htmlspecialchars(__t('rating.submit')) ?></button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-secondary mb-0"><?= htmlspecialchars(__t('rating.window_closed')) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($completionContext['own_requested_at']): ?>
                                <div class="alert alert-info mb-3"><?= htmlspecialchars(__t('rating.waiting_for_other')) ?></div>
                            <?php elseif ($completionContext['other_requested_at']): ?>
                                <div class="alert alert-info">
                                    <?= htmlspecialchars(__t('rating.other_marked_complete')) ?>
                                    <?php if ($completionContext['auto_confirm_at']): ?>
                                        <?= htmlspecialchars(__t('rating.auto_confirm_at', ['date' => date('d.m.Y H:i', strtotime($completionContext['auto_confirm_at']))])) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($completionContext['can_mark_complete']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="mark_complete">
                                        <button type="submit" class="btn btn-success"><?= htmlspecialchars(__t('rating.mark_complete')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($completionContext['can_dispute']): ?>
                                    <form method="POST" onsubmit="return confirm('<?= htmlspecialchars(__t('rating.dispute_confirm'), ENT_QUOTES) ?>');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="dispute_completion">
                                        <button type="submit" class="btn btn-outline-danger"><?= htmlspecialchars(__t('rating.dispute')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 mb-0"><?= htmlspecialchars(__t('user.contractor_offers')) ?></h2>
                        <div class="small text-muted"><?= htmlspecialchars(__t('user.offer_count', ['count' => count($offers)])) ?></div>
                    </div>
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="id" value="<?= $jobId ?>">
                        <label for="sort" class="small text-muted mb-0"><?= htmlspecialchars(__t('user.sort_by')) ?></label>
                        <select name="sort" id="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($sortLabels as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body">
                    <?php if (empty($offers)): ?>
                        <div class="text-center py-5 text-muted">
                            <?= htmlspecialchars(__t('user.no_executor_offers')) ?>
                        </div>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php foreach ($offers as $offer): ?>
                                <?php
                                $conversationId = $offer['job_id'] . '_' . min($userId, $offer['executor_id']) . '_' . max($userId, $offer['executor_id']);
                                $isAccepted = $offer['response_status'] === 'accepted';
                                $isRejected = $offer['response_status'] === 'rejected';
                                $isWithdrawn = $offer['response_status'] === 'withdrawn';
                                $offerBadgeClass = $isAccepted ? 'bg-success' : (($isRejected || $isWithdrawn) ? 'bg-secondary' : 'bg-warning text-dark');
                                $offerStatusLabel = $isAccepted ? __t('status.accepted') : ($isWithdrawn ? __t('status.withdrawn') : ($isRejected ? __t('status.rejected') : __t('status.pending')));
                                ?>
                                <div class="border rounded p-3">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <h3 class="h6 mb-0"><?= htmlspecialchars($offer['executor_name']) ?></h3>
                                                <span class="badge <?= $offerBadgeClass ?>"><?= htmlspecialchars($offerStatusLabel) ?></span>
                                            </div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars(__t('user.submitted_at')) ?>: <?= date('d.m.Y H:i', strtotime($offer['created_at'])) ?>
                                                &middot; <?= htmlspecialchars(__t('rating.rating')) ?>: <?= (int)$offer['executor_rating_count'] > 0 ? htmlspecialchars(number_format((float)$offer['executor_rating'], 2, ',', ' ') . '/5') : htmlspecialchars(__t('rating.no_ratings')) ?>
                                            </div>
                                        </div>
                                        <div class="text-end small">
                                            <div><strong><?= htmlspecialchars(__t('user.initial_price')) ?>:</strong> <?= $offer['proposed_price'] !== null ? htmlspecialchars(number_format((float)$offer['proposed_price'], 2, ',', ' ') . ' PLN') : htmlspecialchars(__t('user.not_provided')) ?></div>
                                            <div><strong><?= htmlspecialchars(__t('user.declared_deadline')) ?>:</strong> <?= htmlspecialchars($offer['declared_deadline'] ?: __t('user.not_provided')) ?></div>
                                        </div>
                                    </div>

                                    <div class="small mb-2">
                                        <strong><?= htmlspecialchars(__t('user.work_scope')) ?>:</strong>
                                        <div class="text-muted"><?= nl2br(htmlspecialchars($offer['scope'] ?: __t('user.not_provided'))) ?></div>
                                    </div>

                                    <?php if (!empty($offer['message'])): ?>
                                        <div class="small mb-3">
                                            <strong><?= htmlspecialchars(__t('messages.conversation_title')) ?>:</strong>
                                            <div class="text-muted"><?= nl2br(htmlspecialchars(mb_strimwidth($offer['message'], 0, 220, '...'))) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div class="small text-muted">
                                            <?= htmlspecialchars(__t('user.reserved_points')) ?>: <?= (int)$offer['points_reserved'] ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($conversationId) ?>&job_id=<?= (int)$offer['job_id'] ?>" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars(__t('user.reply_conversation')) ?></a>
                                            <?php if (!$isAccepted && !$isRejected && !$isWithdrawn && $job['status'] === 'open'): ?>
                                                <form method="POST" onsubmit="return confirm('<?= htmlspecialchars(__t('user.accept_offer_confirm'), ENT_QUOTES) ?>');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="action" value="accept_response">
                                                    <input type="hidden" name="response_id" value="<?= (int)$offer['response_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success"><?= htmlspecialchars(__t('user.accept_offer')) ?></button>
                                                </form>
                                            <?php endif; ?>
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
