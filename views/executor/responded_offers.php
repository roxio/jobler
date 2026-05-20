<?php
session_start();
require_once '../../models/Executor.php';
require_once '../../models/Job.php';
require_once '../../models/Language.php';
require_once '../../models/Rating.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'executor') {
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];

$executor = new Executor();
$jobModel = new Job();
$ratingModel = new Rating();

$jobModel->processCompletionTimeouts();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: responded_offers.php?status=error');
        exit;
    }

    if ($action === 'mark_complete') {
        header('Location: responded_offers.php?status=' . ($jobModel->markCompletion($jobId, $executorId) ? 'completion_marked' : 'completion_error'));
        exit;
    }

    if ($action === 'withdraw_response') {
        $responseId = isset($_POST['response_id']) ? (int)$_POST['response_id'] : 0;
        header('Location: responded_offers.php?status=' . ($executor->withdrawResponse($executorId, $responseId) ? 'withdrawn' : 'withdraw_error'));
        exit;
    }

    if ($action === 'dispute_completion') {
        header('Location: responded_offers.php?status=' . ($jobModel->disputeCompletion($jobId, $executorId) ? 'completion_disputed' : 'completion_error'));
        exit;
    }

    if ($action === 'submit_rating') {
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = trim($_POST['comment'] ?? '');
        header('Location: responded_offers.php?status=' . ($ratingModel->submitRating($jobId, $executorId, $rating, $comment) ? 'rating_saved' : 'rating_error'));
        exit;
    }
}

$respondedOffers = $executor->getRespondedJobs($executorId);

include '../partials/header.php';
?>

<div class="container">
    <?php
    $statusMessages = [
        'completion_marked' => ['success', __t('rating.completion_marked')],
        'completion_disputed' => ['warning', __t('rating.completion_disputed')],
        'completion_error' => ['danger', __t('rating.completion_error')],
        'rating_saved' => ['success', __t('rating.saved')],
        'rating_error' => ['danger', __t('rating.error')],
        'withdrawn' => ['success', __t('executor.withdrawn_success')],
        'withdraw_error' => ['danger', __t('executor.withdrawn_error')],
        'error' => ['danger', __t('cms.security_error')],
    ];
    $currentStatus = $_GET['status'] ?? '';
    ?>
    <?php if (isset($statusMessages[$currentStatus])): ?>
        <div class="alert alert-<?= htmlspecialchars($statusMessages[$currentStatus][0]) ?>"><?= htmlspecialchars($statusMessages[$currentStatus][1]) ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('executor.responded_title')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('executor.dashboard_intro')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars(__t('nav.executor_panel')) ?></a>
            <a href="offer_list.php" class="btn btn-success"><i class="bi bi-search"></i> <?= htmlspecialchars(__t('executor.view_offers')) ?></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.your_responses')) ?></div>
                    <div class="h3 mb-0"><?= count($respondedOffers) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($respondedOffers)): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars(__t('executor.no_responded')) ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0"><?= htmlspecialchars(__t('executor.your_responses')) ?></h2>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($respondedOffers as $offer): ?>
                    <?php
                    $completionContext = $jobModel->getCompletionContext((int)$offer['id'], $executorId);
                    $ownRating = $ratingModel->getRatingForJobByReviewer((int)$offer['id'], $executorId);
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div>
                                <h3 class="h5 mb-1"><?php echo htmlspecialchars($offer['title']); ?></h3>
                                <p class="mb-1 text-muted"><?php echo nl2br(htmlspecialchars(mb_strimwidth($offer['description'], 0, 220, '...'))); ?></p>
                                <small class="text-muted">
                                    <?= htmlspecialchars(__t('executor.response_date')) ?>: <?php echo date('d-m-Y', strtotime($offer['response_date'])); ?>
                                    &middot; <?= htmlspecialchars(__t('rating.principal_rating')) ?>: <?= (int)$offer['principal_rating_count'] > 0 ? htmlspecialchars(number_format((float)$offer['principal_rating'], 2, ',', ' ') . '/5') : htmlspecialchars(__t('rating.no_ratings')) ?>
                                    <?php if ($offer['status'] === 'withdrawn'): ?>
                                        &middot; <?= htmlspecialchars(__t('status.withdrawn')) ?>
                                    <?php endif; ?>
                                </small>
                                <?php if (($offer['status'] ?? '') === 'pending' && ($offer['job_status'] ?? '') === 'open'): ?>
                                    <form method="POST" class="mt-3" onsubmit="return confirm('<?= htmlspecialchars(__t('executor.withdraw_confirm'), ENT_QUOTES) ?>');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="withdraw_response">
                                        <input type="hidden" name="job_id" value="<?= (int)$offer['id'] ?>">
                                        <input type="hidden" name="response_id" value="<?= (int)$offer['response_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?= htmlspecialchars(__t('executor.withdraw_offer')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($completionContext && in_array($offer['job_status'] ?? '', ['in_progress', 'completed', 'under_review'], true) && ($offer['status'] ?? '') === 'accepted'): ?>
                                    <div class="border rounded p-3 mt-3">
                                        <div class="fw-semibold mb-2"><?= htmlspecialchars(__t('rating.workflow_title')) ?></div>
                                        <?php if (($offer['job_status'] ?? '') === 'under_review'): ?>
                                            <div class="alert alert-warning mb-0"><?= htmlspecialchars(__t('rating.under_review_info')) ?></div>
                                        <?php elseif (($offer['job_status'] ?? '') === 'completed'): ?>
                                            <?php if ($ownRating): ?>
                                                <div class="alert alert-success mb-0"><?= htmlspecialchars(__t('rating.already_added', ['rating' => (int)$ownRating['rating']])) ?></div>
                                            <?php elseif ($completionContext['can_rate']): ?>
                                                <form method="POST" class="vstack gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="action" value="submit_rating">
                                                    <input type="hidden" name="job_id" value="<?= (int)$offer['id'] ?>">
                                                    <label class="form-label mb-0" for="rating<?= (int)$offer['id'] ?>"><?= htmlspecialchars(__t('rating.rate_principal', ['name' => $offer['principal_name']])) ?></label>
                                                    <select name="rating" id="rating<?= (int)$offer['id'] ?>" class="form-select form-select-sm" required>
                                                        <option value=""><?= htmlspecialchars(__t('rating.select_rating')) ?></option>
                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                            <option value="<?= $i ?>"><?= $i ?>/5</option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="<?= htmlspecialchars(__t('rating.comment')) ?>"></textarea>
                                                    <button type="submit" class="btn btn-primary btn-sm align-self-start"><?= htmlspecialchars(__t('rating.submit')) ?></button>
                                                </form>
                                            <?php else: ?>
                                                <div class="alert alert-secondary mb-0"><?= htmlspecialchars(__t('rating.window_closed')) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($completionContext['own_requested_at']): ?>
                                                <div class="alert alert-info py-2"><?= htmlspecialchars(__t('rating.waiting_for_other')) ?></div>
                                            <?php elseif ($completionContext['other_requested_at']): ?>
                                                <div class="alert alert-info py-2"><?= htmlspecialchars(__t('rating.other_marked_complete')) ?></div>
                                            <?php endif; ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php if ($completionContext['can_mark_complete']): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="mark_complete">
                                                        <input type="hidden" name="job_id" value="<?= (int)$offer['id'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm"><?= htmlspecialchars(__t('rating.mark_complete')) ?></button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($completionContext['can_dispute']): ?>
                                                    <form method="POST" onsubmit="return confirm('<?= htmlspecialchars(__t('rating.dispute_confirm'), ENT_QUOTES) ?>');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="dispute_completion">
                                                        <input type="hidden" name="job_id" value="<?= (int)$offer['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?= htmlspecialchars(__t('rating.dispute')) ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
