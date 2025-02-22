<?php
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Zleceń</title>
    <!-- Link do Bootstrap (lub innego frameworka CSS, jeśli używasz) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <!-- Możesz dodać tutaj własne style CSS -->
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>

<!-- Nawigacja -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="/">System Zleceń</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
			<!-- Jeżeli użytkownik jest administratorem -->
          <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="../admin/dashboard.php">Panel Administracyjny</a>
            </li>
          <?php endif; ?>
		  <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'executor'): ?>
            <li class="nav-item">
              <a class="nav-link" href="../executor/dashboard.php">Panel Wykonawcy</a>
            </li>
          <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="/views/user/dashboard.php">Panel użytkownika</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/views/user/job_list.php">Moje ogłoszenia</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/logout.php">Wyloguj</a>
                </li>
				
				

            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">