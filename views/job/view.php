<?php
session_start();
require_once '../../config/config.php';
include_once('../../models/Job.php');

// Utwórz instancję klasy Job
$jobModel = new Job();

// Pobranie ID ogłoszenia z parametru GET
$jobId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Pobranie danych ogłoszenia
$job = $jobModel->getJobDetails($jobId);

if (!$job) {
    // Jeśli ogłoszenie nie istnieje, wyświetlamy komunikat o błędzie
    http_response_code(404);
    echo "<h1>Ogłoszenie nie istnieje</h1>";
    echo "<p><a href='/'>Wróć na stronę główną</a></p>";
    exit;
}

// Sprawdzenie, czy użytkownik jest właścicielem ogłoszenia
$isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] === $job['user_id'];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szczegóły ogłoszenia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h2 class="h4 mb-0"><?= htmlspecialchars($job['title']) ?></h2>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Opis:</strong> <?= nl2br(htmlspecialchars($job['description'])) ?></p>
                <p class="mb-2"><strong>Dodano:</strong> <?= htmlspecialchars($job['created_at']) ?></p>
                <p class="mb-2"><strong>Status:</strong> <?= htmlspecialchars($job['status']) ?></p>
                <p class="mb-2"><strong>Autor:</strong> <?= htmlspecialchars($job['user_name'] ?? 'Nieznany') ?></p>
            </div>
        </div>

        <?php if ($isOwner): ?>
            <div class="mt-4">
                <a href="/views/user/edit_job.php?id=<?= $job['id'] ?>" class="btn btn-warning">Edytuj ogłoszenie</a>
                <a href="/views/user/delete_job.php?id=<?= $job['id'] ?>" class="btn btn-danger" onclick="return confirm('Czy na pewno chcesz usunąć to ogłoszenie?')">Usuń ogłoszenie</a>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="/" class="btn btn-secondary">Powrót na stronę główną</a>
        </div>
    </div>
    <?php include '../../templates/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
