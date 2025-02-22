<?php
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/SiteSettings.php');
include_once('../../models/Message.php');

$settingsModel = new SiteSettings();

// Utwórz obiekty modelu
$userModel = new User();
$jobModel = new Job();
//$settingsModel = new SiteSettings();

// Pobierz liczbę użytkowników i ogłoszeń
$userCount = $userModel->getUserCount();
$jobCount = $jobModel->getJobCount();
$newUsers = $userModel->getNewUsersCount(); // Nowi użytkownicy
$newJobs = $jobModel->getNewJobsCount();   // Nowe ogłoszenia
$siteViews = $settingsModel->getSiteViews(); // Wyświetlenia strony

?>

<?php include '../partials/header.php'; ?>


<div class="container-fluid">
    <div class="row">
        <!-- Menu boczne -->
        <?php include 'sidebar.php'; ?>

        <!-- Główna zawartość -->
        <div class="col-md-10 col-lg-10 main-content">
            <h1>Witaj w Panelu Administracyjnym</h1>
            <p>Wybierz sekcję z menu po lewej stronie, aby rozpocząć zarządzanie systemem.</p>

            <div class="row">
                <div class="col-md-4">
                    <h3>Statystyki</h3>
                    <ul class="list-group">
                        <li class="list-group-item">Użytkownicy: <strong><?php echo $userCount; ?></strong></li>
                        <li class="list-group-item">Ogłoszenia: <strong><?php echo $jobCount; ?></strong></li>
                        <li class="list-group-item">Wyświetlenia strony: <strong><?php echo $siteViews; ?></strong></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h3>Nowości</h3>
                    <ul class="list-group">
                        <li class="list-group-item">Nowi użytkownicy: <strong><?php echo $newUsers; ?></strong></li>
                        <li class="list-group-item">Nowe ogłoszenia: <strong><?php echo $newJobs; ?></strong></li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>