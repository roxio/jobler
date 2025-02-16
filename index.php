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
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    <div class="container">
        <h1>Witaj na naszej stronie!</h1>
        <?php if (isset($_SESSION['user_id'])): ?>
            <h2>Twoje ogłoszenia</h2>
            <ul>
                <?php foreach ($jobs as $job): ?>
                    <li>
                        <a href="/job/view.php?id=<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Jeśli chcesz dodać ogłoszenie lub odpowiedzieć na nie, <a href="/login.php">zaloguj się</a> lub <a href="/register.php">zarejestruj się</a>.</p>
        <?php endif; ?>
    </div>
    <?php include 'templates/footer.php'; ?>
</body>
</html>
