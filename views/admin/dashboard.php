<?php
include_once('../../models/User.php');
include_once('../../models/Job.php');

// Utwórz obiekt klasy User i Job
$userModel = new User();
$jobModel = new Job();

// Pobierz liczbę użytkowników i ogłoszeń
$userCount = $userModel->getUserCount();
$jobCount = $jobModel->getJobCount();
?>

<?php include '../partials/header.php'; ?>

<div class="container">
    <h1>Panel Administratora</h1>
    <p>Witaj w panelu administracyjnym. Zarządzaj użytkownikami, ogłoszeniami i innymi zasobami.</p>

    <div class="row">
        <div class="col-md-6">
            <h3>Użytkownicy</h3>
            <p>Masz <strong><?php echo $userCount; ?></strong> zarejestrowanych użytkowników.</p>
            <a href="../admin/manage_users.php" class="btn btn-primary">Zarządzaj użytkownikami</a>
        </div>
        <div class="col-md-6">
            <h3>Ogłoszenia</h3>
            <p>Masz <strong><?php echo $jobCount; ?></strong> opublikowanych ogłoszeń.</p>
            <a href="../admin/manage_jobs.php" class="btn btn-secondary">Zarządzaj ogłoszeniami</a>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>