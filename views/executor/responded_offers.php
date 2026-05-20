<?php
session_start();
require_once '../../models/Executor.php';
require_once '../../models/Language.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'executor') {
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];

$executor = new Executor();


$respondedOffers = $executor->getRespondedJobs($executorId);

include '../partials/header.php';
?>

<div class="container">
    <h1><?= htmlspecialchars(__t('executor.responded_title')) ?></h1>

    <?php if (empty($respondedOffers)): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars(__t('executor.no_responded')) ?>
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($respondedOffers as $offer): ?>
                <div class="list-group-item">
                    <h5 class="mb-1"><?php echo htmlspecialchars($offer['title']); ?></h5>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($offer['description'])); ?></p>
                    <small><?= htmlspecialchars(__t('executor.response_date')) ?>: <?php echo date('d-m-Y', strtotime($offer['response_date'])); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
