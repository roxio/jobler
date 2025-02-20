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

// Pobranie odpowiedzi na oferty wykonawcy
$respondedJobs = $executor->getRespondedJobs($executorId);

include '../partials/header.php';
?>

<div class="container">
    <h1>Witaj, <?php echo htmlspecialchars($executorData['name']); ?>!</h1>
    <p>Twoje konto jest aktywne. Przeglądaj dostępne oferty i reaguj na nie.</p>

    <div class="row">
        <!-- Dostępne oferty -->
        <div class="col-md-6">
            <h3>Dostępne oferty</h3>
            <p>Masz <strong><?php echo $availableOffersCount; ?></strong> dostępnych ofert do rozważenia.</p>
            <a href="../executor/offer_list.php" class="btn btn-primary">Zobacz oferty</a>
        </div>

        <!-- Oferty, na które wykonawca odpowiedział -->
<div class="col-md-6">
    <h3>Odpowiedz na oferty</h3>
    <p>Sprawdź oferty, na które już odpowiedziałeś.</p>
    <?php if (empty($respondedJobs)): ?>
        <p>Nie odpowiedziałeś jeszcze na żadną ofertę.</p>
    <?php else: ?>
        <ul class="list-group">
            <?php foreach ($respondedJobs as $job): ?>
                <li class="list-group-item">
                    <h5><?php echo htmlspecialchars($job['title'] ?? 'Brak tytułu'); ?></h5>
                    <p><?php echo htmlspecialchars($job['description'] ?? 'Brak opisu'); ?></p>
                    <p>
                        <small><strong>Odpowiedziano:</strong> <?php echo date('d-m-Y H:i', strtotime($job['response_date'] ?? 'now')); ?></small>
                    </p>
                    <a href="../messages/conversation.php?job_id=<?php echo isset($job['id']) ? $job['id'] : ''; ?>" class="btn btn-info btn-sm">Przejdź do konwersacji</a>
					
					

                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>


    </div>
</div>

<?php include '../partials/footer.php'; ?>
