<?php
session_start();

include_once('../../models/User.php');
include_once('../../models/Executor.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';
$userModel = new User();
$executorModel = new Executor();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'accept_response') {
    $token = $_POST['csrf_token'] ?? '';
    $responseId = isset($_POST['response_id']) ? (int)$_POST['response_id'] : 0;

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: dashboard.php?status=error&message=csrf');
        exit;
    }

    if ($responseId <= 0 || !$executorModel->acceptResponse($userId, $responseId)) {
        header('Location: dashboard.php?status=error&message=accept_failed');
        exit;
    }

    header('Location: dashboard.php?status=accepted');
    exit;
}

$responses = $executorModel->getUserJobOffers($userId);
$offersByJob = [];

foreach ($responses as $response) {
    $jobId = (int)$response['job_id'];
    if (!isset($offersByJob[$jobId])) {
        $offersByJob[$jobId] = [
            'title' => $response['title'],
            'job_status' => $response['job_status'],
            'points_required' => $response['points_required'],
            'offers' => [],
        ];
    }
    $offersByJob[$jobId]['offers'][] = $response;
}

include('../partials/header.php');
?>

<div class="container">
    <?php if (isset($_GET['status'])): ?>
        <?php
        $isError = $_GET['status'] === 'error';
        $messages = [
            'accepted' => 'Oferta została zaakceptowana. Punkty pozostałych wykonawców zostały zwrócone.',
            'csrf' => 'Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.',
            'accept_failed' => 'Nie udało się zaakceptować oferty.',
        ];
        $messageKey = $isError ? ($_GET['message'] ?? '') : $_GET['status'];
        ?>
        <div class="alert alert-<?= $isError ? 'danger' : 'success' ?>">
            <?= htmlspecialchars($messages[$messageKey] ?? 'Operacja zakończona.') ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Dane użytkownika</h3>
                </div>
                <div class="card-body">
                    <p><strong>ID:</strong> <?= htmlspecialchars($userId) ?></p>
                    <p><strong>Imię:</strong> <?= htmlspecialchars($userName) ?></p>
                    <a href="edit_profile.php" class="btn btn-primary w-100">Edytuj dane</a>
                </div>
            </div>

            <div class="mt-4 d-grid gap-2">
                <a href="create_job.php" class="btn btn-success">Dodaj nowe ogłoszenie</a>
                <a href="logout.php" class="btn btn-danger">Wyloguj się</a>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mt-4 mt-md-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Oferty do Twoich ogłoszeń</h3>
                    <span class="badge bg-primary"><?= count($responses) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($offersByJob)): ?>
                        <p class="mb-0">Brak odpowiedzi na Twoje ogłoszenia.</p>
                    <?php else: ?>
                        <div class="accordion" id="jobOffersAccordion">
                            <?php foreach ($offersByJob as $jobId => $job): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="jobHeading<?= $jobId ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#jobOffers<?= $jobId ?>">
                                            <?= htmlspecialchars($job['title']) ?>
                                            <span class="badge bg-secondary ms-2"><?= count($job['offers']) ?> ofert</span>
                                            <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars($job['job_status']) ?></span>
                                        </button>
                                    </h2>
                                    <div id="jobOffers<?= $jobId ?>" class="accordion-collapse collapse" data-bs-parent="#jobOffersAccordion">
                                        <div class="accordion-body">
                                            <div class="list-group">
                                                <?php foreach ($job['offers'] as $response): ?>
                                                    <?php
                                                    $conversationId = $response['job_id'] . '_' . min($userId, $response['executor_id']) . '_' . max($userId, $response['executor_id']);
                                                    $isAccepted = $response['response_status'] === 'accepted';
                                                    $isRejected = $response['response_status'] === 'rejected';
                                                    ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                                            <div>
                                                                <h5 class="mb-1"><?= htmlspecialchars($response['executor_name']) ?></h5>
                                                                <p class="mb-1"><strong>Wstępna wycena:</strong> <?= $response['proposed_price'] !== null ? htmlspecialchars(number_format((float)$response['proposed_price'], 2, ',', ' ')) : 'Nie podano' ?></p>
                                                                <p class="mb-1"><strong>Zakres prac:</strong><br><?= nl2br(htmlspecialchars($response['scope'] ?: 'Nie podano')) ?></p>
                                                                <p class="mb-1"><strong>Pierwsza wiadomość:</strong><br><?= nl2br(htmlspecialchars($response['message'] ?: 'Brak treści')) ?></p>
                                                                <small class="text-muted">Zgłoszono: <?= date('d-m-Y H:i', strtotime($response['created_at'])) ?>, punkty blokady: <?= (int)$response['points_reserved'] ?></small>
                                                            </div>
                                                            <span class="badge <?= $isAccepted ? 'bg-success' : ($isRejected ? 'bg-secondary' : 'bg-warning text-dark') ?>">
                                                                <?= $isAccepted ? 'Zaakceptowana' : ($isRejected ? 'Odrzucona' : 'Oczekuje') ?>
                                                            </span>
                                                        </div>

                                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                                            <a href="../messages/conversation.php?conversation_id=<?= htmlspecialchars($conversationId) ?>&job_id=<?= (int)$response['job_id'] ?>" class="btn btn-sm btn-outline-primary">Odpowiedz w rozmowie</a>
                                                            <?php if (!$isAccepted && !$isRejected && $job['job_status'] === 'open'): ?>
                                                                <form method="POST" onsubmit="return confirm('Czy na pewno zaakceptować tę ofertę? Pozostałym wykonawcom punkty zostaną zwrócone.');">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <input type="hidden" name="action" value="accept_response">
                                                                    <input type="hidden" name="response_id" value="<?= (int)$response['response_id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-success">Akceptuj ofertę</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
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
