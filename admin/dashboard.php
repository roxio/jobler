<?php
include('../config/auth.php');
require_login();

// Pobranie liczby użytkowników
$result_users = $conn->query("SELECT COUNT(*) AS total_users FROM users");
$total_users = $result_users->fetch_assoc()['total_users'];

// Pobranie liczby ogłoszeń
$result_ads = $conn->query("SELECT COUNT(*) AS total_ads FROM ads");
$total_ads = $result_ads->fetch_assoc()['total_ads'];

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administracyjny</title>
    <link rel="stylesheet" href="../styles/admin.css"> <!-- Plik CSS -->
</head>
<body>

<?php include('../includes/header.php'); ?> <!-- Pasek nawigacji -->

<main>
    <h1>Panel Administracyjny</h1>

    <section class="stats">
        <div class="stat-box">
            <h2>Użytkownicy</h2>
            <p>Łącznie: <strong><?= $total_users; ?></strong></p>
            <a href="users.php" class="btn">Zarządzaj użytkownikami</a>
        </div>

        <div class="stat-box">
            <h2>Ogłoszenia</h2>
            <p>Łącznie: <strong><?= $total_ads; ?></strong></p>
            <a href="ads.php" class="btn">Zarządzaj ogłoszeniami</a>
        </div>
    </section>
</main>

<?php include('../includes/footer.php'); ?> <!-- Stopka -->

</body>
</html>