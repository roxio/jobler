<?php
session_start();
require_once '../../models/Executor.php';

if (!isset($_SESSION['user_id'])) {
    // Jeśli użytkownik nie jest zalogowany, przekieruj go do strony logowania
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];

// Tworzenie instancji klasy Executor
$executor = new Executor();

// Pobranie danych wykonawcy na podstawie jego ID
$executorData = $executor->getExecutorById($executorId);

// Jeśli nie znaleziono wykonawcy, przekieruj do strony logowania
if (!$executorData) {
    header('Location: /login.php');
    exit;
}

// Pobranie dostępnych ofert
$availableJobs = $executor->getAvailableJobs();
$availableOffersCount = count($availableJobs);

include '../partials/header.php';
?>

<div class="container">
    <h1>Witaj, <?php echo htmlspecialchars($executorData['name']); ?>!</h1>
    <p>Twoje konto jest aktywne. Przeglądaj dostępne oferty i reaguj na nie.</p>

    <div class="row">
        <div class="col-md-6">
            <h3>Dostępne oferty</h3>
            <p>Masz <strong><?php echo $availableOffersCount; ?></strong> dostępnych ofert do rozważenia.</p>
            <a href="../executor/offer_list.php" class="btn btn-primary">Zobacz oferty</a>
        </div>
        <div class="col-md-6">
            <h3>Odpowiedz na oferty</h3>
            <p>Sprawdź oferty, na które jeszcze nie odpowiedziałeś.</p>
            <a href="../executor/responded_offers.php" class="btn btn-secondary">Odpowiedz na oferty</a>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
