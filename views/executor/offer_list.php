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

// Pobranie dostępnych ofert
$availableJobs = $executor->getAvailableJobs();

// Jeśli nie ma żadnych ofert, wyświetl komunikat
if (empty($availableJobs)) {
    $message = "Brak dostępnych ofert do rozważenia.";
}

include '../partials/header.php';
?>

<div class="container">
    <h1>Lista dostępnych ofert</h1>

    <?php if (isset($message)): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($availableJobs as $job): ?>
                <?php
                // Sprawdź, czy użytkownik odpowiedział na ofertę
                $hasResponded = $executor->hasRespondedToJob($executorId, $job['id']);
                ?>

                <!-- Dodajemy klasę CSS "faded" dla wyblakniętych ofert -->
                <div class="list-group-item <?php echo $hasResponded ? 'faded' : ''; ?>">
                    <h5 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                    <small>Data utworzenia: <?php echo date('d-m-Y', strtotime($job['created_at'])); ?></small>
                    <br>
                    <?php if ($hasResponded): ?>
                        <!-- Jeśli użytkownik odpowiedział, pokaż informację -->
                        <span class="badge bg-secondary mt-2">Odpowiedziałeś na tę ofertę</span>
                    <?php else: ?>
                        <!-- Jeśli użytkownik nie odpowiedział, pokaż przycisk -->
                        <a href="respond_offer.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary mt-2">Odpowiedz na ofertę</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>


<?php include '../partials/footer.php'; ?>
