<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Brak dostępu");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Panel Administratora</h1>
    <nav>
        <a href="admin.php">Dashboard</a> |
        <a href="admin_users.php">Zarządzanie użytkownikami</a> |
        <a href="admin_jobs.php">Zarządzanie zleceniami</a>
    </nav>
    <h2>Podsumowanie:</h2>
    <ul>
        <?php
        $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $job_count = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
        $open_jobs = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'open'")->fetchColumn();
        $total_budget = $pdo->query("SELECT SUM(budget) FROM jobs")->fetchColumn();

        echo "<li>Łączna liczba użytkowników: $user_count</li>";
        echo "<li>Łączna liczba zleceń: $job_count</li>";
        echo "<li>Otwarte zlecenia: $open_jobs</li>";
        echo "<li>Łączny budżet zleceń: $total_budget PLN</li>";
        ?>
    </ul>
</body>
</html>
