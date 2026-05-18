<?php
session_start();
require_once '../../models/Executor.php';

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
            <h1>Lista dostępnych ofert</h1>
            <p class="text-muted mb-0">Wysłanie oferty blokuje punkty wymagane przez ogłoszenie.</p>
        </div>
        <div class="text-end">
            <div class="badge bg-primary fs-6"><?= $executorBalance ?> pkt</div>
            <div class="small text-muted">saldo konta</div>
        </div>
    </div>

    <?php if (empty($availableJobs)): ?>
        <div class="alert alert-info">Brak dostępnych ofert do rozważenia.</div>
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
                            <small class="text-muted">Data utworzenia: <?= date('d-m-Y', strtotime($job['created_at'])) ?></small>
                            <br>
                            <small>Wymagane punkty: <strong><?= (int)$job['points_required'] ?></strong></small>
                        </div>
                        <?php if (!$hasEnoughPoints && !$hasResponded): ?>
                            <span class="badge bg-danger">Za mało punktów</span>
                        <?php elseif ($hasResponded): ?>
                            <span class="badge bg-secondary">Oferta wysłana</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($conversationExists): ?>
                        <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($responseStatus['conversation_id']) ?>&job_id=<?= (int)$job['id'] ?>" class="btn btn-secondary mt-2">Przejdź do konwersacji</a>
                    <?php elseif ($hasResponded): ?>
                        <button class="btn btn-secondary mt-2" disabled>Odpowiedź wysłana</button>
                    <?php elseif ($hasEnoughPoints): ?>
                        <a href="respond_offer.php?job_id=<?= (int)$job['id'] ?>" class="btn btn-primary mt-2">Złóż ofertę</a>
                    <?php else: ?>
                        <a href="payment.php" class="btn btn-outline-primary mt-2">Doładuj konto</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
