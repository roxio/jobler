<?php
session_start();
require_once '../../models/Executor.php';

if (!isset($_SESSION['user_id'])) {
    // Jeśli użytkownik nie jest zalogowany
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];

$executor = new Executor();

// Pobranie ofert, na które użytkownik odpowiedział
$respondedOffers = $executor->getRespondedJobs($executorId);

include '../partials/header.php';
?>

<div class="container">
    <h1>Oferty, na które odpowiedziałeś</h1>

    <?php if (empty($respondedOffers)): ?>
        <div class="alert alert-info">
            Nie odpowiedziałeś jeszcze na żadną ofertę.
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($respondedOffers as $offer): ?>
                <div class="list-group-item">
                    <h5 class="mb-1"><?php echo htmlspecialchars($offer['title']); ?></h5>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($offer['description'])); ?></p>
                    <small>Data odpowiedzi: <?php echo date('d-m-Y', strtotime($offer['response_date'])); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
