<?php
session_start();
require_once '../../models/Executor.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['job_id'])) {
    // Jeśli użytkownik nie jest zalogowany lub brak ID oferty
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];
$jobId = $_GET['job_id'];

$executor = new Executor();

// Pobranie szczegółów ogłoszenia
$jobDetails = $executor->getJobDetails($jobId);

if (!$jobDetails) {
    // Jeśli ogłoszenie nie istnieje
    header('Location: ../executor/offer_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageContent = $_POST['message'];

    // Odpowiadanie na ofertę
    $response = $executor->respondToJob($executorId, $jobId, $messageContent);

    if ($response) {
        // Jeśli odpowiedź została wysłana, przekieruj do listy ofert
        header('Location: ../executor/offer_list.php');
        exit;
    } else {
        $error = "Wystąpił błąd podczas wysyłania odpowiedzi. Spróbuj ponownie.";
    }
}

include '../partials/header.php';
?>

<div class="container">
    <h1>Odpowiedz na ofertę: <?php echo htmlspecialchars($jobDetails['title']); ?></h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="respond_offer.php?job_id=<?php echo $jobId; ?>" method="POST">
        <div class="mb-3">
            <label for="message" class="form-label">Twoja odpowiedź:</label>
            <textarea name="message" id="message" class="form-control" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Wyślij odpowiedź</button>
    </form>
</div>

<?php include '../partials/footer.php'; ?>
