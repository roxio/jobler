<?php
session_start();
require_once '../../models/Executor.php';

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
    $error = 'Już wysłałeś ofertę do tego ogłoszenia.';
} elseif ($executorBalance < $pointsRequired) {
    $error = "Nie masz wystarczającej liczby punktów na koncie, aby odpowiedzieć na to ogłoszenie. <a href='payment.php' class='alert-link'>Doładuj konto</a>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $messageContent = trim($_POST['message'] ?? '');
    $proposedPrice = trim($_POST['proposed_price'] ?? '');
    $scope = trim($_POST['scope'] ?? '');

    if ($messageContent === '' || $proposedPrice === '' || $scope === '') {
        $error = 'Podaj wiadomość, wstępną wycenę i zakres prac.';
    } elseif (!is_numeric($proposedPrice) || (float)$proposedPrice < 0) {
        $error = 'Wstępna wycena musi być poprawną kwotą.';
    } else {
        $conversationId = $executor->respondToJob($executorId, $jobId, $messageContent, (float)$proposedPrice, $scope);

        if ($conversationId) {
            $_SESSION['user_account_balance'] = $executor->getExecutorBalance($executorId);
            header('Location: ../messages/conversation.php?conversation_id=' . urlencode($conversationId) . '&job_id=' . $jobId);
            exit;
        }

        $error = 'Nie udało się wysłać oferty. Sprawdź saldo punktów lub spróbuj ponownie.';
    }
}

include '../partials/header.php';
?>

<div class="container">
    <h1>Odpowiedz na ofertę: <?= htmlspecialchars($jobDetails['title']) ?></h1>

    <div class="alert alert-info">
        Do zgłoszenia zostanie pobrane <strong><?= $pointsRequired ?></strong> pkt. Jeśli użytkownik wybierze inną ofertę, punkty wrócą na Twoje konto.
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form action="respond_offer.php?job_id=<?= $jobId ?>" method="POST">
        <div class="mb-3">
            <label for="proposed_price" class="form-label">Wstępna wycena</label>
            <input type="number" name="proposed_price" id="proposed_price" class="form-control" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['proposed_price'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="scope" class="form-label">Zakres prac</label>
            <textarea name="scope" id="scope" class="form-control" rows="5" required><?= htmlspecialchars($_POST['scope'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label for="message" class="form-label">Pierwsza wiadomość do użytkownika</label>
            <textarea name="message" id="message" class="form-control" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary" <?= $error ? 'disabled' : '' ?>>Wyślij ofertę i rozpocznij rozmowę</button>
        <a href="offer_list.php" class="btn btn-outline-secondary">Wróć</a>
    </form>
</div>

<?php include '../partials/footer.php'; ?>
