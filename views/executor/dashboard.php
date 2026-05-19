<?php
session_start();
require_once '../../models/Executor.php';
require_once '../../models/Language.php';

$currentLocale = Language::current('frontend');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'executor') {
    header('Location: /login.php');
    exit;
}

$executorId = (int)$_SESSION['user_id'];
$executor = new Executor();
$executorData = $executor->getExecutorById($executorId);

if (!$executorData) {
    header('Location: /login.php');
    exit;
}

$availableJobs = $executor->getAvailableJobs();
$respondedJobs = $executor->getRespondedJobs($executorId);
$executorBalance = (int)$executor->getExecutorBalance($executorId);

include '../partials/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1><?= htmlspecialchars(__t('executor.welcome', ['name' => $executorData['name']])) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('executor.dashboard_intro')) ?></p>
        </div>
        <div class="text-end">
            <div class="badge bg-primary fs-6"><?= $executorBalance ?> pkt</div>
            <div class="small text-muted"><?= htmlspecialchars(__t('executor.account_balance')) ?></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0"><?= htmlspecialchars(__t('executor.available_offers')) ?></h3>
                    <span class="badge bg-primary"><?= count($availableJobs) ?></span>
                </div>
                <div class="card-body">
                    <p><?= htmlspecialchars(__t('executor.available_offers_text')) ?></p>
                    <a href="../executor/offer_list.php" class="btn btn-primary"><?= htmlspecialchars(__t('executor.view_offers')) ?></a>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0"><?= htmlspecialchars(__t('executor.your_responses')) ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($respondedJobs)): ?>
                        <p class="mb-0"><?= htmlspecialchars(__t('executor.no_responses')) ?></p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($respondedJobs as $job): ?>
                                <?php
                                $conversationId = $job['id'] . '_' . min($executorId, $job['user_id']) . '_' . max($executorId, $job['user_id']);
                                $status = $job['status'] ?? 'pending';
                                $statusClass = $status === 'accepted' ? 'bg-success' : ($status === 'rejected' ? 'bg-secondary' : 'bg-warning text-dark');
                                $statusLabel = $status === 'accepted' ? __t('status.accepted') : ($status === 'rejected' ? __t('status.not_selected') : __t('status.pending'));
                                ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <h5><?= htmlspecialchars($job['title'] ?? __t('executor.no_title')) ?></h5>
                                            <p class="mb-1"><?= htmlspecialchars($job['description'] ?? __t('executor.no_description')) ?></p>
                                            <p class="mb-1"><strong><?= htmlspecialchars(__t('executor.price')) ?>:</strong> <?= $job['proposed_price'] !== null ? htmlspecialchars(number_format((float)$job['proposed_price'], 2, ',', ' ')) : htmlspecialchars(__t('user.not_provided')) ?></p>
                                            <p class="mb-1"><strong><?= htmlspecialchars(__t('executor.scope')) ?>:</strong> <?= htmlspecialchars($job['scope'] ?: __t('user.not_provided')) ?></p>
                                            <small class="text-muted"><?= htmlspecialchars(__t('user.submitted_at')) ?>: <?= date('d-m-Y H:i', strtotime($job['response_date'] ?? 'now')) ?>, <?= htmlspecialchars(__t('executor.reserved_points')) ?>: <?= (int)$job['points_reserved'] ?></small>
                                        </div>
                                        <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                    </div>
                                    <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($conversationId) ?>&job_id=<?= (int)$job['id'] ?>" class="btn btn-info btn-sm mt-3"><?= htmlspecialchars(__t('executor.go_conversation')) ?></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
