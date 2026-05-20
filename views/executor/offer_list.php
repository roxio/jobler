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
$availableJobs = $executor->getAvailableJobs();
$executorBalance = (int)$executor->getExecutorBalance($executorId);

include '../partials/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1><?= htmlspecialchars(__t('executor.available_list_title')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('executor.available_list_intro')) ?></p>
        </div>
        <div class="text-end">
            <div class="badge bg-primary fs-6"><?= $executorBalance ?> pkt</div>
            <div class="small text-muted"><?= htmlspecialchars(__t('executor.account_balance')) ?></div>
        </div>
    </div>

    <?php if (empty($availableJobs)): ?>
        <div class="alert alert-info"><?= htmlspecialchars(__t('executor.no_available_offers')) ?></div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($availableJobs as $job): ?>
                <?php
                $responseStatus = $executor->hasRespondedToJob($executorId, $job['id']);
                $hasResponded = $responseStatus !== false;
                $conversationExists = $hasResponded && $responseStatus['conversation_id'] !== null;
                $hasEnoughPoints = $executorBalance >= (int)$job['points_required'];
                ?>

                <div class="list-group-item <?= $hasResponded ? 'bg-light' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($job['title']) ?></h5>
                            <p class="mb-1"><?= nl2br(htmlspecialchars($job['description'])) ?></p>
                            <small class="text-muted"><?= htmlspecialchars(__t('executor.created_date')) ?>: <?= date('d-m-Y', strtotime($job['created_at'])) ?></small>
                            <br>
                            <small><?= htmlspecialchars(__t('executor.required_points')) ?>: <strong><?= (int)$job['points_required'] ?></strong></small>
                        </div>
                        <?php if (!$hasEnoughPoints && !$hasResponded): ?>
                            <span class="badge bg-danger"><?= htmlspecialchars(__t('executor.not_enough_points')) ?></span>
                        <?php elseif ($hasResponded): ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars(__t('executor.offer_sent')) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($conversationExists): ?>
                        <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($responseStatus['conversation_id']) ?>&job_id=<?= (int)$job['id'] ?>" class="btn btn-secondary mt-2"><?= htmlspecialchars(__t('executor.go_to_conversation')) ?></a>
                    <?php elseif ($hasResponded): ?>
                        <button class="btn btn-secondary mt-2" disabled><?= htmlspecialchars(__t('executor.response_sent')) ?></button>
                    <?php elseif ($hasEnoughPoints): ?>
                        <a href="respond_offer.php?job_id=<?= (int)$job['id'] ?>" class="btn btn-primary mt-2"><?= htmlspecialchars(__t('executor.submit_offer')) ?></a>
                    <?php else: ?>
                        <a href="payment.php" class="btn btn-outline-primary mt-2"><?= htmlspecialchars(__t('executor.top_up_account')) ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
