<?php
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="System zleceń - zarządzaj swoimi usługami i zleceniami.">
    <title>System Zleceń</title>
    <!-- Link do Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" >
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Możesz dodać tutaj własne style CSS -->
    <link rel="stylesheet" href="../../css/style.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Nawigacja -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
   <div class="container">
    <!-- Logo -->
    <a class="navbar-brand" href="/">
      <img src="/img/logo.png" alt="Logo" style="height: 40px;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <!-- Dropdown menu "Usługi" -->
 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="categoryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Kategorie
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="categoryDropdown">
                        <li><a class="dropdown-item" href="index.php">Wszystkie</a></li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a class="dropdown-item" href="index.php?category=<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
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
        <?php if (isset($_SESSION['user_account_balance'])): ?>
            <li class="nav-item"><a class="nav-link" href="../../views/executor/payment.php">Stan konta: <?= $_SESSION['user_account_balance'] ?> punktów</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <button class="btn btn-light d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
    <i class="fas fa-bars"></i> Menu
</button>
</nav>
<script src=".https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="container mt-4">

