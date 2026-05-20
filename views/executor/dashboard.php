<?php
session_start();
require_once '../../models/Executor.php';
require_once '../../models/Job.php';
require_once '../../models/Language.php';
require_once '../../models/Rating.php';

$currentLocale = Language::current('frontend');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'executor') {
    header('Location: /login.php');
    exit;
}

$executorId = (int)$_SESSION['user_id'];
$executor = new Executor();
$jobModel = new Job();
$ratingModel = new Rating();
$jobModel->processCompletionTimeouts();
$executorData = $executor->getExecutorById($executorId);

if (!$executorData) {
    header('Location: /login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw_response') {
    $token = $_POST['csrf_token'] ?? '';
    $responseId = isset($_POST['response_id']) ? (int)$_POST['response_id'] : 0;

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: dashboard.php?status=withdraw_error');
        exit;
    }

    header('Location: dashboard.php?status=' . ($executor->withdrawResponse($executorId, $responseId) ? 'withdrawn' : 'withdraw_error'));
    exit;
}

$availableJobs = $executor->getAvailableJobs($executorId);
$latestAvailableJobs = array_slice($availableJobs, 0, 5);
$respondedJobs = $executor->getRespondedJobs($executorId);
$executorBalance = (int)$executor->getExecutorBalance($executorId);
$ratingSummary = $ratingModel->getSummaryForUser($executorId);
$averageRating = (float)($ratingSummary['average_rating'] ?? 0);
$ratingCount = (int)($ratingSummary['rating_count'] ?? 0);
$pendingResponses = 0;
$completedJobs = 0;
foreach ($respondedJobs as $job) {
    if (($job['status'] ?? 'pending') === 'pending') {
        $pendingResponses++;
    }
    if (($job['job_status'] ?? '') === 'completed' && ($job['status'] ?? '') === 'accepted') {
        $completedJobs++;
    }
}

include '../partials/header.php';
?>

<div class="container">
    <?php if (($_GET['status'] ?? '') === 'withdrawn'): ?>
        <div class="alert alert-success"><?= htmlspecialchars(__t('executor.withdrawn_success')) ?></div>
    <?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'withdraw_error'): ?>
        <div class="alert alert-danger"><?= htmlspecialchars(__t('executor.withdrawn_error')) ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('executor.welcome', ['name' => $executorData['name']])) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('executor.dashboard_intro')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="../executor/offer_list.php" class="btn btn-success"><i class="bi bi-search"></i> <?= htmlspecialchars(__t('executor.view_offers')) ?></a>
            <a href="../executor/responded_offers.php" class="btn btn-outline-primary"><i class="bi bi-send-check"></i> <?= htmlspecialchars(__t('executor.your_responses')) ?></a>
            <a href="../executor/payment.php" class="btn btn-outline-secondary"><i class="bi bi-wallet2"></i> <?= htmlspecialchars(__t('executor.top_up_account')) ?></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.available_offers')) ?></div>
                    <div class="h3 mb-0"><?= count($availableJobs) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.your_responses')) ?></div>
                    <div class="h3 mb-0"><?= count($respondedJobs) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('status.pending')) ?></div>
                    <div class="h3 mb-0"><?= $pendingResponses ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.completed_jobs')) ?></div>
                    <div class="h3 mb-0"><?= $completedJobs ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.average_rating')) ?></div>
                    <div class="h3 mb-0"><?= $ratingCount > 0 ? htmlspecialchars(number_format($averageRating, 2, ',', ' ')) : '-' ?></div>
                    <div class="small text-muted"><?= htmlspecialchars(__t('executor.rating_count', ['count' => $ratingCount])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.account_balance')) ?></div>
                    <div class="h3 mb-0"><?= $executorBalance ?> pkt</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('executor.available_offers')) ?></h2>
                    <span class="badge bg-primary"><?= count($availableJobs) ?></span>
                </div>
                <?php if (empty($latestAvailableJobs)): ?>
                    <div class="card-body">
                        <p class="mb-0"><?= htmlspecialchars(__t('executor.no_available_offers')) ?></p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($latestAvailableJobs as $job): ?>
                            <?php $hasEnoughPoints = (int)$job['points_required'] <= $executorBalance; ?>
                            <a href="../executor/offer_list.php" class="list-group-item list-group-item-action <?= $hasEnoughPoints ? '' : 'text-muted bg-light' ?>">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-width-0">
                                        <div class="fw-semibold text-truncate"><?= htmlspecialchars($job['title']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($job['category_name'] ?? __t('home.no_category')) ?></small>
                                    </div>
                                    <span class="badge <?= $hasEnoughPoints ? 'bg-success' : 'bg-secondary' ?>"><?= (int)$job['points_required'] ?> pkt</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="../executor/offer_list.php" class="btn btn-outline-primary btn-sm w-100"><?= htmlspecialchars(__t('executor.view_remaining_offers')) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('executor.your_responses')) ?></h2>
                </div>
                <?php if (empty($respondedJobs)): ?>
                    <div class="card-body">
                        <div class="text-center py-4">
                            <p class="mb-3"><?= htmlspecialchars(__t('executor.no_responses')) ?></p>
                            <a href="../executor/offer_list.php" class="btn btn-success"><?= htmlspecialchars(__t('executor.view_offers')) ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush executor-response-list executor-response-list--compact">
                        <?php foreach ($respondedJobs as $job): ?>
                            <?php
                            $conversationId = $job['id'] . '_' . min($executorId, $job['user_id']) . '_' . max($executorId, $job['user_id']);
                            $status = $job['status'] ?? 'pending';
                            $statusClass = $status === 'accepted' ? 'bg-success' : (in_array($status, ['rejected', 'withdrawn'], true) ? 'bg-secondary' : 'bg-warning text-dark');
                            $statusLabel = $status === 'accepted' ? __t('status.accepted') : ($status === 'withdrawn' ? __t('status.withdrawn') : ($status === 'rejected' ? __t('status.not_selected') : __t('status.pending')));
                            ?>
                            <div class="list-group-item executor-response-item executor-response-item--compact">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div class="min-width-0 flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h3 class="h6 mb-0 text-truncate executor-response-title"><?= htmlspecialchars($job['title'] ?? __t('executor.no_title')) ?></h3>
                                            <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 small text-muted executor-response-meta">
                                            <span><i class="bi bi-calendar3"></i> <?= date('d-m-Y H:i', strtotime($job['response_date'] ?? 'now')) ?></span>
                                            <span><i class="bi bi-coin"></i> <?= (int)$job['points_reserved'] ?> pkt</span>
                                            <span><i class="bi bi-cash"></i> <?= $job['proposed_price'] !== null ? htmlspecialchars(number_format((float)$job['proposed_price'], 2, ',', ' ')) : htmlspecialchars(__t('user.not_provided')) ?></span>
                                            <?php if (!empty($job['scope'])): ?>
                                                <span><?= htmlspecialchars(mb_strimwidth($job['scope'], 0, 70, '...')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="executor-response-actions executor-response-actions--compact d-flex flex-wrap justify-content-start justify-content-md-end gap-2">
                                        <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($conversationId) ?>&job_id=<?= (int)$job['id'] ?>" class="btn btn-outline-primary btn-sm"><?= htmlspecialchars(__t('executor.go_conversation')) ?></a>
                                        <?php if ($status === 'pending' && ($job['job_status'] ?? '') === 'open'): ?>
                                            <form method="POST" onsubmit="return confirm('<?= htmlspecialchars(__t('executor.withdraw_confirm'), ENT_QUOTES) ?>');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="withdraw_response">
                                                <input type="hidden" name="response_id" value="<?= (int)$job['response_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><?= htmlspecialchars(__t('executor.withdraw_offer')) ?></button>
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

<?php include '../partials/footer.php'; ?>
