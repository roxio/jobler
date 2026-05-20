<?php
session_start();
require_once '../../models/Executor.php';
require_once '../../models/Language.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'executor' || !isset($_GET['job_id'])) {
    header('Location: /login.php');
    exit;
}

$executorId = (int)$_SESSION['user_id'];
$jobId = (int)$_GET['job_id'];
$executor = new Executor();
$jobDetails = $executor->getJobDetails($jobId);

if (!$jobDetails) {
    header('Location: ../executor/offer_list.php');
    exit;
}

$executorBalance = (int)$executor->getExecutorBalance($executorId);
$pointsRequired = (int)$jobDetails['points_required'];
$responseStatus = $executor->hasRespondedToJob($executorId, $jobId);
$error = '';

if ($responseStatus !== false) {
    $error = htmlspecialchars(__t('executor.already_responded'));
} elseif ($executorBalance < $pointsRequired) {
    $error = __t('executor.not_enough_points_html');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $messageContent = trim($_POST['message'] ?? '');
    $proposedPrice = trim($_POST['proposed_price'] ?? '');
    $scope = trim($_POST['scope'] ?? '');
    $declaredDeadline = trim($_POST['declared_deadline'] ?? '');

    if ($messageContent === '' || $proposedPrice === '' || $scope === '' || $declaredDeadline === '') {
        $error = htmlspecialchars(__t('executor.respond_required'));
    } elseif (!is_numeric($proposedPrice) || (float)$proposedPrice < 0) {
        $error = htmlspecialchars(__t('executor.price_invalid'));
    } else {
        $conversationId = $executor->respondToJob($executorId, $jobId, $messageContent, (float)$proposedPrice, $scope, $declaredDeadline);

        if ($conversationId) {
            $_SESSION['user_account_balance'] = $executor->getExecutorBalance($executorId);
            header('Location: ../messages/conversation.php?conversation_id=' . urlencode($conversationId) . '&job_id=' . $jobId);
            exit;
        }

        $error = htmlspecialchars(__t('executor.send_error'));
    }
}

include '../partials/header.php';
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('executor.respond_title', ['title' => $jobDetails['title']])) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('executor.available_list_intro')) ?></p>
        </div>
        <a href="offer_list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('executor.back')) ?></a>
    </div>

    <div class="alert alert-info">
        <?= htmlspecialchars(__t('executor.points_notice', ['points' => $pointsRequired])) ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars($jobDetails['title']) ?></h2>
                </div>
                <div class="card-body">
                    <p class="text-muted"><?= nl2br(htmlspecialchars($jobDetails['description'] ?? '')) ?></p>
                    <dl class="row small mb-0">
                        <dt class="col-6"><?= htmlspecialchars(__t('executor.required_points')) ?></dt>
                        <dd class="col-6"><?= $pointsRequired ?> pkt</dd>
                        <dt class="col-6"><?= htmlspecialchars(__t('executor.account_balance')) ?></dt>
                        <dd class="col-6"><?= $executorBalance ?> pkt</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?= htmlspecialchars(__t('executor.submit_offer')) ?></h2>
                </div>
                <div class="card-body">
                    <form action="respond_offer.php?job_id=<?= $jobId ?>" method="POST">
                        <div class="mb-3">
                            <label for="proposed_price" class="form-label"><?= htmlspecialchars(__t('executor.initial_price')) ?></label>
                            <input type="number" name="proposed_price" id="proposed_price" class="form-control" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['proposed_price'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="scope" class="form-label"><?= htmlspecialchars(__t('executor.scope')) ?></label>
                            <textarea name="scope" id="scope" class="form-control" rows="5" required><?= htmlspecialchars($_POST['scope'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="declared_deadline" class="form-label"><?= htmlspecialchars(__t('executor.declared_deadline')) ?></label>
                            <input type="text" name="declared_deadline" id="declared_deadline" class="form-control" required value="<?= htmlspecialchars($_POST['declared_deadline'] ?? '') ?>" placeholder="<?= htmlspecialchars(__t('executor.declared_deadline_placeholder')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label"><?= htmlspecialchars(__t('executor.first_message')) ?></label>
                            <textarea name="message" id="message" class="form-control" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" <?= $error ? 'disabled' : '' ?>><?= htmlspecialchars(__t('executor.send_offer_start')) ?></button>
                            <a href="offer_list.php" class="btn btn-outline-secondary"><?= htmlspecialchars(__t('executor.back')) ?></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
