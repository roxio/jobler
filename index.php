<?php
session_start();
require_once 'config/config.php';
require_once 'models/Job.php';

// Pobranie ogłoszeń
$jobs = Job::getAllJobs();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strona Główna</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Witaj na naszej stronie!</h1>
            <p class="lead">Znajdź najlepsze zlecenia lub wykonawców w swojej okolicy.</p>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="h4 mb-0">Twoje ogłoszenia</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($jobs)): ?>
                        <ul class="list-group">
                            <?php foreach ($jobs as $job): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0">
                                            <a href="/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none text-primary">
                                                <?= htmlspecialchars($job['title']) ?>
                                            </a>
                                        </h5>
                                        <small class="text-muted">Dodano: <?= htmlspecialchars($job['created_at']) ?></small>
                                    </div>
                                    <a href="/job/view.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary btn-sm">Zobacz szczegóły</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Nie masz jeszcze żadnych ogłoszeń. <a href="/views/user/create_job.php" class="text-primary">Dodaj pierwsze ogłoszenie</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-4">
                <p>Jeśli chcesz dodać ogłoszenie lub odpowiedzieć na nie, <a href="/login.php" class="alert-link">zaloguj się</a> lub <a href="/register.php" class="alert-link">zarejestruj się</a>.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'templates/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
