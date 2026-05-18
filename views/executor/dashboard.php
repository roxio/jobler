<?php
session_start();
require_once '../../models/Executor.php';

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
            <h1>Witaj, <?= htmlspecialchars($executorData['name']) ?>!</h1>
            <p class="text-muted mb-0">Przeglądaj zlecenia, składaj oferty i pilnuj statusu rozmów.</p>
        </div>
        <div class="text-end">
            <div class="badge bg-primary fs-6"><?= $executorBalance ?> pkt</div>
            <div class="small text-muted">saldo konta</div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Dostępne oferty</h3>
                    <span class="badge bg-primary"><?= count($availableJobs) ?></span>
                </div>
                <div class="card-body">
                    <p>Możesz odpowiedzieć na ogłoszenia, dla których masz wystarczającą liczbę punktów.</p>
                    <a href="../executor/offer_list.php" class="btn btn-primary">Zobacz oferty</a>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0">Twoje zgłoszenia</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($respondedJobs)): ?>
                        <p class="mb-0">Nie odpowiedziałeś jeszcze na żadną ofertę.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($respondedJobs as $job): ?>
                                <?php
                                $conversationId = $job['id'] . '_' . min($executorId, $job['user_id']) . '_' . max($executorId, $job['user_id']);
                                $status = $job['status'] ?? 'pending';
                                $statusClass = $status === 'accepted' ? 'bg-success' : ($status === 'rejected' ? 'bg-secondary' : 'bg-warning text-dark');
                                $statusLabel = $status === 'accepted' ? 'Zaakceptowana' : ($status === 'rejected' ? 'Niewybrana' : 'Oczekuje');
                                ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <h5><?= htmlspecialchars($job['title'] ?? 'Brak tytułu') ?></h5>
                                            <p class="mb-1"><?= htmlspecialchars($job['description'] ?? 'Brak opisu') ?></p>
                                            <p class="mb-1"><strong>Wycena:</strong> <?= $job['proposed_price'] !== null ? htmlspecialchars(number_format((float)$job['proposed_price'], 2, ',', ' ')) : 'Nie podano' ?></p>
                                            <p class="mb-1"><strong>Zakres:</strong> <?= htmlspecialchars($job['scope'] ?: 'Nie podano') ?></p>
                                            <small class="text-muted">Zgłoszono: <?= date('d-m-Y H:i', strtotime($job['response_date'] ?? 'now')) ?>, zablokowane punkty: <?= (int)$job['points_reserved'] ?></small>
                                        </div>
                                        <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                    </div>
                                    <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($conversationId) ?>&job_id=<?= (int)$job['id'] ?>" class="btn btn-info btn-sm mt-3">Przejdź do konwersacji</a>
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
