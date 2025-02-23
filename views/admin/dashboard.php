<?php
// Rozpocznij sesję
session_start();

include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/SiteSettings.php');
include_once('../../models/Message.php');

$settingsModel = new SiteSettings();

// Utwórz obiekty modelu
$userModel = new User();
$jobModel = new Job();

// Dla charts
$newUsersData = $userModel->getNewUsersPerDay();
$newJobsData = $jobModel->getNewJobsPerDay();

// Pobierz statystyki
$userCount = $userModel->getUserCount();
$jobCount = $jobModel->getJobCount();
$newUsers = $userModel->getNewUsersCount();
$newJobs = $jobModel->getNewJobsCount();
$siteViews = $settingsModel->getSiteViews();
$pendingChanges = $userModel->getPendingAccountChangesCount(); // Nowe zgłoszenia zmiany statusu konta

$pendingAlerts = [
    'pending_changes' => $pendingChanges,
    'site_errors' => $settingsModel->getSiteErrors() // Załóżmy, że masz funkcję do pobierania błędów
];
?>


<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <nav class="nav">
					<?php include 'sidebar.php'; ?>
                    </nav>
					<?php endif; ?>
                </div>

                <div class="card-body">
				
				 <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-1"><i class="bi bi-info-square"></i> Dashboard</h5>
	</div>
                <div class="card-body">
				
				 <!-- Alerty -->
                            <?php if ($pendingAlerts['pending_changes'] > 0): ?>
                                <div class="alert alert-warning">
                                    <strong>Uwaga!</strong> Masz oczekujące zmiany statusu konta: <?= $pendingAlerts['pending_changes']; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($pendingAlerts['site_errors'] > 0): ?>
                                <div class="alert alert-danger">
                                    <strong>Wystąpił błąd!</strong> Skontaktuj się z administratorem.
                                </div>
                            <?php endif; ?>
							
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
                <div class="col-md-4">
                    <h3>Zgłoszenia</h3>
                    <ul class="list-group">
                        <li class="list-group-item">
                            Zmiany statusu konta: <strong><?php echo $pendingChanges; ?></strong>
                        </li>
                    </ul>
                </div>
				
				<div class="col-md-4">
    <h3>Szybkie Działania</h3>
    <ul class="list-group">
        <li class="list-group-item"><a href="add_user.php" class="btn btn-primary btn-sm w-100">Dodaj Użytkownika</a></li>
        <li class="list-group-item"><a href="add_job.php" class="btn btn-success btn-sm w-100">Dodaj Ogłoszenie</a></li>
        <li class="list-group-item"><a href="site_settings.php" class="btn btn-warning btn-sm w-100">Ustawienia</a></li>
        <li class="list-group-item"><a href="manage_users.php" class="btn btn-secondary btn-sm w-100">Zarządzaj Użytkownikami</a></li>
    </ul>
</div>
<div class="col-md-4">
 <h3>Wykres zleceń</h3>

<canvas id="newJobsChart" ></canvas>
<script>

var ctx2 = document.getElementById('newJobsChart').getContext('2d');

var newJobsChart = new Chart(ctx2, {
    type: 'line',
    data: {
        labels: [<?php foreach ($newJobsData as $data) echo '"' . $data['date'] . '",'; ?>],
        datasets: [{
            label: 'Nowe Ogłoszenia',
            data: [<?php foreach ($newJobsData as $data) echo $data['count'] . ','; ?>],
            borderColor: 'rgba(153, 102, 255, 1)',
            fill: false
        }]
    }
});

</script>
				</div>
				<div class="col-md-4">
				 <h3>Wykres użytkowników</h3>
				<canvas id="newUsersChart"></canvas>

<script>
var ctx1 = document.getElementById('newUsersChart').getContext('2d');

var newUsersChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: [<?php foreach ($newUsersData as $data) echo '"' . $data['date'] . '",'; ?>],
        datasets: [{
            label: 'Nowi Użytkownicy',
            data: [<?php foreach ($newUsersData as $data) echo $data['count'] . ','; ?>],
            borderColor: 'rgba(75, 192, 192, 1)',
            fill: false
        }]
    }
});
</script>
				</div>
            </div>

                        </div>
                    </div>
                </div>
				
        <div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
        </div>
  
            </div>	
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>

<?php include '../partials/footer.php'; ?>
