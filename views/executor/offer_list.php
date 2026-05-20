<?php
session_start();
require_once '../../models/Executor.php';
require_once '../../models/Language.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'executor') {
    header('Location: /login.php');
    exit;
}

$executorId = (int)$_SESSION['user_id'];
$executor = new Executor();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw_response') {
    $token = $_POST['csrf_token'] ?? '';
    $responseId = isset($_POST['response_id']) ? (int)$_POST['response_id'] : 0;

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: offer_list.php?status=withdraw_error');
        exit;
    }

    header('Location: offer_list.php?status=' . ($executor->withdrawResponse($executorId, $responseId) ? 'withdrawn' : 'withdraw_error'));
    exit;
}

$availableJobs = $executor->getAvailableJobs($executorId);
$executorBalance = (int)$executor->getExecutorBalance($executorId);
$categoryFilterEnabled = $executor->isCategoryFilterEnabled($executorId);
$executorCategoryIds = $executor->getExecutorCategoryIds($executorId);
$affordableJobs = array_filter($availableJobs, fn($job) => (int)$job['points_required'] <= $executorBalance);
$unaffordableJobs = array_filter($availableJobs, fn($job) => (int)$job['points_required'] > $executorBalance);

function executorJobImageUrl($filename) {
    $filename = trim((string)$filename);
    if ($filename === '') {
        $filename = 'no_image.jpg';
    }

    $safeFilename = basename($filename);
    $imagePath = dirname(__DIR__, 2) . '/uploads/jobs/' . $safeFilename;
    if (!is_file($imagePath)) {
        $safeFilename = 'no_image.jpg';
    }

    return '/uploads/jobs/' . rawurlencode($safeFilename);
}

include '../partials/header.php';
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('executor.available_list_title')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('executor.available_list_intro')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="../user/edit_profile.php" class="btn btn-outline-primary"><i class="bi bi-sliders"></i> <?= htmlspecialchars(__t('executor.filter_settings')) ?></a>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars(__t('nav.executor_panel')) ?></a>
            <a href="payment.php" class="btn btn-outline-primary"><i class="bi bi-wallet2"></i> <?= htmlspecialchars(__t('executor.top_up_account')) ?></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.available_offers')) ?></div>
                    <div class="h3 mb-0"><?= count($affordableJobs) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.account_balance')) ?></div>
                    <div class="h3 mb-0"><?= $executorBalance ?> pkt</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= htmlspecialchars(__t('executor.category_filter')) ?></div>
                    <div class="h5 mb-0"><?= htmlspecialchars($categoryFilterEnabled ? __t('common.enabled', [], 'Włączony') : __t('common.disabled', [], 'Wyłączony')) ?></div>
                    <small class="text-muted"><?= htmlspecialchars(__t('executor.selected_categories_count', ['count' => count($executorCategoryIds)])) ?></small>
                </div>
            </div>
        </div>
    </div>

    <?php if (($_GET['status'] ?? '') === 'withdrawn'): ?>
        <div class="alert alert-success"><?= htmlspecialchars(__t('executor.withdrawn_success')) ?></div>
    <?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'withdraw_error'): ?>
        <div class="alert alert-danger"><?= htmlspecialchars(__t('executor.withdrawn_error')) ?></div>
    <?php endif; ?>

    <?php if (empty($availableJobs)): ?>
        <div class="alert alert-info"><?= htmlspecialchars(__t('executor.no_available_offers')) ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0"><?= htmlspecialchars(__t('executor.available_offers')) ?></h2>
            </div>
            <?php if ($categoryFilterEnabled && empty($executorCategoryIds)): ?>
                <div class="alert alert-info m-3 mb-0"><?= htmlspecialchars(__t('executor.no_categories_hint')) ?></div>
            <?php endif; ?>
            <div class="list-group list-group-flush executor-offer-list">
                <?php foreach ($availableJobs as $job): ?>
                    <?php
                    $responseStatus = $executor->hasRespondedToJob($executorId, $job['id']);
                    $hasResponded = $responseStatus !== false;
                    $conversationExists = $hasResponded && $responseStatus['conversation_id'] !== null;
                    $responseState = $responseStatus['status'] ?? null;
                    $isWithdrawn = $responseState === 'withdrawn';
                    $hasEnoughPoints = (int)$job['points_required'] <= $executorBalance;
                    $imageUrl = executorJobImageUrl($job['primary_image'] ?? 'no_image.jpg');
                    $budget = $job['budget_estimate'] !== null && $job['budget_estimate'] !== ''
                        ? number_format((float)$job['budget_estimate'], 2, ',', ' ') . ' PLN'
                        : __t('user.not_provided');
                    ?>

                    <div class="list-group-item executor-offer-item <?= !$hasEnoughPoints ? 'executor-offer-item--muted' : ($hasResponded ? 'bg-light' : '') ?>">
                        <div class="d-flex align-items-start gap-3">
                            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($job['title']) ?>" class="executor-offer-thumb">
                            <div class="min-width-0 flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div class="min-width-0">
                                        <h3 class="h6 mb-1"><?= htmlspecialchars($job['title']) ?></h3>
                                        <p class="mb-2 text-muted small"><?= htmlspecialchars(mb_strimwidth($job['description'], 0, 170, '...')) ?></p>
                                    </div>
                                    <div class="executor-offer-meta text-md-end">
                                        <div><strong><?= htmlspecialchars($budget) ?></strong></div>
                                        <small class="text-muted"><?= htmlspecialchars(__t('user.budget')) ?></small>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-3 small">
                                    <span class="text-muted"><i class="bi bi-calendar3"></i> <?= date('d-m-Y', strtotime($job['created_at'])) ?></span>
                                    <span class="text-muted"><i class="bi bi-tag"></i> <?= htmlspecialchars($job['category_name'] ?? __t('home.no_category')) ?></span>
                                    <span class="text-muted"><i class="bi bi-person-check"></i> <?= htmlspecialchars(__t('rating.principal_rating')) ?>:
                                        <strong><?= (int)($job['principal_rating_count'] ?? 0) > 0 ? htmlspecialchars(number_format((float)$job['principal_rating'], 2, ',', ' ') . '/5') : htmlspecialchars(__t('rating.no_ratings')) ?></strong>
                                    </span>
                                    <span><i class="bi bi-coin"></i> <?= htmlspecialchars(__t('executor.required_points')) ?>: <strong><?= (int)$job['points_required'] ?></strong></span>
                                </div>
                                <?php if (!$hasEnoughPoints): ?>
                                    <div class="small text-muted mt-2"><?= htmlspecialchars(__t('executor.not_enough_points_hint')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="executor-offer-actions d-flex flex-column align-items-start align-items-md-end gap-2">
                                <?php if (!$hasEnoughPoints && !$hasResponded): ?>
                                    <span class="badge bg-danger"><?= htmlspecialchars(__t('executor.not_enough_points')) ?></span>
                                <?php elseif ($isWithdrawn): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars(__t('executor.offer_withdrawn')) ?></span>
                                <?php elseif ($hasResponded): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars(__t('executor.offer_sent')) ?></span>
                                <?php endif; ?>
                                <?php if ($conversationExists): ?>
                                    <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($responseStatus['conversation_id']) ?>&job_id=<?= (int)$job['id'] ?>" class="btn btn-outline-primary btn-sm"><?= htmlspecialchars(__t('executor.go_to_conversation')) ?></a>
                                    <?php if ($responseState === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('<?= htmlspecialchars(__t('executor.withdraw_confirm'), ENT_QUOTES) ?>');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="withdraw_response">
                                            <input type="hidden" name="response_id" value="<?= (int)$responseStatus['response_id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><?= htmlspecialchars(__t('executor.withdraw_offer')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($isWithdrawn): ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled><?= htmlspecialchars(__t('executor.offer_withdrawn')) ?></button>
                                <?php elseif ($hasResponded): ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled><?= htmlspecialchars(__t('executor.response_sent')) ?></button>
                                    <?php if ($responseState === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('<?= htmlspecialchars(__t('executor.withdraw_confirm'), ENT_QUOTES) ?>');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="withdraw_response">
                                            <input type="hidden" name="response_id" value="<?= (int)$responseStatus['response_id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><?= htmlspecialchars(__t('executor.withdraw_offer')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($hasEnoughPoints): ?>
                                    <a href="respond_offer.php?job_id=<?= (int)$job['id'] ?>" class="btn btn-success btn-sm"><?= htmlspecialchars(__t('executor.submit_offer')) ?></a>
                                <?php else: ?>
                                    <a href="payment.php" class="btn btn-outline-secondary btn-sm"><?= htmlspecialchars(__t('executor.top_up_account')) ?></a>
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
