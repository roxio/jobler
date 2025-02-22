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

// Pobranie salda konta wykonawcy
$executorBalance = $executor->getExecutorBalance($executorId);

// Pobranie liczby punktów wymaganych do odpowiedzi
$pointsRequired = $jobDetails['points_required'];

if ($executorBalance < $pointsRequired) {
    // Jeśli saldo wykonawcy jest mniejsze niż wymagane punkty
    $error = "Nie masz wystarczającej ilości punktów na koncie, aby odpowiedzieć na to ogłoszenie. <a href='payment.php' class='alert-link'>Doładuj konto</a>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $messageContent = $_POST['message'];

    // Odpowiadanie na ofertę
    $response = $executor->respondToJob($executorId, $jobId, $messageContent);

    if ($response) {
        // Przekierowanie do listy odpowiedzianych ofert
        header('Location: ../executor/responded_offers.php');
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
        <?php echo $error; ?>
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
