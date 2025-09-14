<?php
session_start();

include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/SiteSettings.php');
include_once('../../models/Message.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Newsletter.php');
include_once('../../config/config.php');

$settingsModel = new SiteSettings();

// Utwórz obiekty modelu
$userModel = new User();
$jobModel = new Job();
$settingsModel = new SiteSettings($pdo);
$messageModel = new Message($pdo);
$transactionModel = new TransactionHistory($pdo);
$newsletter = new Newsletter();

// Dla charts
$newUsersData = $userModel->getNewUsersPerDay();
$newJobsData = $jobModel->getNewJobsPerDay();
$revenueData = $transactionModel->getDailyRevenue();

// Pobierz statystyki
$userCount = $userModel->getUserCount();
$jobCount = $jobModel->getJobCount();
$newUsers = $userModel->getNewUsersCount();
$newJobs = $jobModel->getNewJobsCount();
$siteViews = $settingsModel->getSiteViews();
$pendingChanges = $userModel->getPendingAccountChangesCount();
$totalRevenue = $transactionModel->getTotalRevenue();
$activeJobs = $jobModel->getActiveJobsCount();
// Pobierz statystyki newslettera - TERAZ ZMIENNA $newsletter JEST DOSTĘPNA
$newsletterStats = $newsletter->getNewsletterStats();

$pendingAlerts = [
    'pending_changes' => $pendingChanges,
    'site_errors' => $settingsModel->getSiteErrors(),
    'unread_reports' => $userModel->getUnreadReportsCount() // Nowe alerty
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
            
                            <?php if ($pendingAlerts['pending_changes'] > 0): ?>
                                <div class="alert alert-warning">
                                    <strong>Uwaga!</strong> Masz oczekujące zmiany statusu konta: <?= $pendingAlerts['pending_changes']; ?>
                                    <a href="manage_users.php?filter=need_attention" class="btn btn-sm btn-outline-warning ms-2">Przejdź</a>
                                </div>
                            <?php endif; ?>

                            <?php if ($pendingAlerts['site_errors'] > 0): ?>
                                <div class="alert alert-danger">
                                    <strong>Wystąpił błąd!</strong> Skontaktuj się z sys admistratorem.
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($pendingAlerts['unread_reports'] > 0): ?>
                                <div class="alert alert-info">
                                    <strong>Masz nieprzeczytane zgłoszenia:</strong> <?= $pendingAlerts['unread_reports']; ?>
                                    <a href="reports.php" class="btn btn-sm btn-outline-info ms-2">Przejdź</a>
                                </div>
                            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-3">
                    <h3>Statystyki</h3>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Użytkownicy: 
                            <span class="badge bg-primary rounded-pill"><?php echo $userCount; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Ogłoszenia: 
                            <span class="badge bg-primary rounded-pill"><?php echo $jobCount; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
    Subskrybenci newslettera: 
    <span class="badge bg-info rounded-pill"><?php echo $newsletterStats['active'] ?? 0; ?></span>
</li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Wyświetlenia strony: 
                            <span class="badge bg-info rounded-pill"><?php echo $siteViews; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Przychód całkowity: 
                            <span class="badge bg-success rounded-pill"><?php echo number_format($totalRevenue, 2); ?> PLN</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3>Aktywność <sup>(7 dni)</sup></h3>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nowi użytkownicy: 
                            <span class="badge bg-success rounded-pill"><?php echo $newUsers; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nowe ogłoszenia: 
                            <span class="badge bg-success rounded-pill"><?php echo $newJobs; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nowe transakcje: 
                            <span class="badge bg-success rounded-pill"><?php echo $transactionModel->getRecentTransactionsCount(); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nowe konwersacje: 
                            <span class="badge bg-success rounded-pill"><?php echo $messageModel->countConversations(); ?></span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3>Zgłoszenia</h3>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Zmiany statusu konta: 
                            <span class="badge bg-warning rounded-pill"><?php echo $pendingChanges; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nieprzeczytane raporty: 
                            <span class="badge bg-info rounded-pill"><?php echo $pendingAlerts['unread_reports']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Ostatnie błędy: 
                            <span class="badge bg-danger rounded-pill"><?php echo $pendingAlerts['site_errors']; ?></span>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-3">
    <h3>Skróty</h3>
    <div class="list-group">
        <a href="add_user.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            Dodaj Użytkownika
            <i class="bi bi-person-plus"></i>
        </a>
        <a href="add_job.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            Dodaj Ogłoszenie
            <i class="bi bi-briefcase"></i>
        </a>
        <a href="site_settings.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            Ustawienia
            <i class="bi bi-gear"></i>
        </a>
        <a href="manage_users.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            Zarządzaj Użytkownikami
            <i class="bi bi-people"></i>
        </a>
        <a href="reports.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            Generuj Raport
            <i class="bi bi-graph-up"></i>
        </a>
    </div>
</div>

            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Aktywność użytkowników i ogłoszeń</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="combinedChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Przychody dzienne</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Ostatnie aktywności</h5>
                            <a href="reports.php" class="btn btn-sm btn-primary">Zobacz wszystkie</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Użytkownik</th>
                                            <th>Akcja</th>
                                            <th>Szczegóły</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recentActivities = $userModel->getRecentActivities(5);
                                        foreach ($recentActivities as $activity): 
                                        ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i', strtotime($activity['timestamp'])); ?></td>
                                                <td><?php echo htmlspecialchars($activity['username']); ?> (ID: <?php echo $activity['user_id']; ?>)</td>
                                                <td><span class="badge bg-<?php echo $activity['type'] == 'login' ? 'success' : 'info'; ?>"><?php echo $activity['type']; ?></span></td>
                                                <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                        </div>
                    </div>
                </div>
                
        <div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
			<div class="stupidbottomm"> </div>
        </div>
  
            </div>    
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>

<script>
// Nowy wykres łączący użytkowników i ogłoszenia
var ctxCombined = document.getElementById('combinedChart').getContext('2d');
var combinedChart = new Chart(ctxCombined, {
    type: 'line',
    data: {
        labels: [<?php foreach ($newUsersData as $data) echo '"' . $data['date'] . '",'; ?>],
        datasets: [{
            label: 'Nowi Użytkownicy',
            data: [<?php foreach ($newUsersData as $data) echo $data['count'] . ','; ?>],
            borderColor: 'rgba(75, 192, 192, 1)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            fill: true
        },
        {
            label: 'Nowe Ogłoszenia',
            data: [<?php foreach ($newJobsData as $data) echo $data['count'] . ','; ?>],
            borderColor: 'rgba(153, 102, 255, 1)',
            backgroundColor: 'rgba(153, 102, 255, 0.2)',
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Aktywność na stronie'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Wykres przychodów
var ctxRevenue = document.getElementById('revenueChart').getContext('2d');
var revenueChart = new Chart(ctxRevenue, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($revenueData as $data) echo '"' . $data['date'] . '",'; ?>],
        datasets: [{
            label: 'Przychód (PLN)',
            data: [<?php foreach ($revenueData as $data) echo $data['amount'] . ','; ?>],
            borderColor: 'rgba(40, 167, 69, 1)',
            backgroundColor: 'rgba(40, 167, 69, 0.2)',
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Dzienne przychody'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value + ' PLN';
                    }
                }
            }
        }
    }
});
</script>