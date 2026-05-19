<?php
session_start();


require_once __DIR__ . '/_auth.php';
requireAdminAccess();
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/SiteSettings.php');
include_once('../../models/Message.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Newsletter.php');
include_once('../../config/config.php');
include_once('../../models/Language.php');

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
                    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= htmlspecialchars(__t('admin.panel')) ?></h5>
                    <nav class="nav">
                    <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                
                 <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-1"><i class="bi bi-info-square"></i> <?= htmlspecialchars(__t('admin.dashboard')) ?></h5>
    </div>
                <div class="card-body">
            
                            <?php if ($pendingAlerts['pending_changes'] > 0): ?>
                                <div class="alert alert-warning">
                                    <strong><?= htmlspecialchars(__t('admin.attention')) ?></strong> <?= htmlspecialchars(__t('admin.pending_account_changes', ['count' => $pendingAlerts['pending_changes']])) ?>
                                    <a href="manage_users.php?filter=need_attention" class="btn btn-sm btn-outline-warning ms-2"><?= htmlspecialchars(__t('admin.go')) ?></a>
                                </div>
                            <?php endif; ?>

                            <?php if ($pendingAlerts['site_errors'] > 0): ?>
                                <div class="alert alert-danger">
                                    <strong><?= htmlspecialchars(__t('admin.system_error_notice')) ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($pendingAlerts['unread_reports'] > 0): ?>
                                <div class="alert alert-info">
                                    <strong><?= htmlspecialchars(__t('admin.unread_reports_notice', ['count' => $pendingAlerts['unread_reports']])) ?></strong>
                                    <a href="reports.php" class="btn btn-sm btn-outline-info ms-2"><?= htmlspecialchars(__t('admin.go')) ?></a>
                                </div>
                            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-3">
                    <h3><?= htmlspecialchars(__t('admin.statistics')) ?></h3>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.users')) ?>:
                            <span class="badge bg-primary rounded-pill"><?php echo $userCount; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.jobs')) ?>:
                            <span class="badge bg-primary rounded-pill"><?php echo $jobCount; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
    <?= htmlspecialchars(__t('admin.newsletter_subscribers')) ?>:
    <span class="badge bg-info rounded-pill"><?php echo $newsletterStats['active'] ?? 0; ?></span>
</li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.site_views')) ?>:
                            <span class="badge bg-info rounded-pill"><?php echo $siteViews; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.total_revenue')) ?>:
                            <span class="badge bg-success rounded-pill"><?php echo number_format($totalRevenue, 2); ?> PLN</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3><?= htmlspecialchars(__t('admin.activity_7_days')) ?> <sup>(<?= htmlspecialchars(__t('admin.last_7_days')) ?>)</sup></h3>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.new_users')) ?>:
                            <span class="badge bg-success rounded-pill"><?php echo $newUsers; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.new_jobs')) ?>:
                            <span class="badge bg-success rounded-pill"><?php echo $newJobs; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.new_transactions')) ?>:
                            <span class="badge bg-success rounded-pill"><?php echo $transactionModel->getRecentTransactionsCount(); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.new_conversations')) ?>:
                            <span class="badge bg-success rounded-pill"><?php echo $messageModel->countConversations(); ?></span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3><?= htmlspecialchars(__t('admin.requests')) ?></h3>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.account_status_changes')) ?>:
                            <span class="badge bg-warning rounded-pill"><?php echo $pendingChanges; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.unread_reports')) ?>:
                            <span class="badge bg-info rounded-pill"><?php echo $pendingAlerts['unread_reports']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(__t('admin.recent_errors')) ?>:
                            <span class="badge bg-danger rounded-pill"><?php echo $pendingAlerts['site_errors']; ?></span>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-3">
    <h3><?= htmlspecialchars(__t('admin.shortcuts')) ?></h3>
    <div class="list-group">
        <a href="add_user.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <?= htmlspecialchars(__t('admin.add_user')) ?>
            <i class="bi bi-person-plus"></i>
        </a>
        <a href="add_job.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <?= htmlspecialchars(__t('admin.add_job')) ?>
            <i class="bi bi-briefcase"></i>
        </a>
        <a href="site_settings.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <?= htmlspecialchars(__t('admin.menu.settings')) ?>
            <i class="bi bi-gear"></i>
        </a>
        <a href="manage_users.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <?= htmlspecialchars(__t('admin.manage_users')) ?>
            <i class="bi bi-people"></i>
        </a>
        <a href="reports.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <?= htmlspecialchars(__t('admin.generate_report')) ?>
            <i class="bi bi-graph-up"></i>
        </a>
    </div>
</div>

            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><?= htmlspecialchars(__t('admin.user_job_activity')) ?></h5>
                        </div>
                        <div class="card-body">
                            <canvas id="combinedChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><?= htmlspecialchars(__t('admin.daily_revenue')) ?></h5>
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
                            <h5 class="card-title"><?= htmlspecialchars(__t('admin.recent_activity')) ?></h5>
                            <a href="reports.php" class="btn btn-sm btn-primary"><?= htmlspecialchars(__t('admin.view_all')) ?></a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?= htmlspecialchars(__t('admin.date')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.user')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.action')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.details')) ?></th>
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
            label: <?= json_encode(__t('admin.chart_new_users'), JSON_UNESCAPED_UNICODE) ?>,
            data: [<?php foreach ($newUsersData as $data) echo $data['count'] . ','; ?>],
            borderColor: 'rgba(75, 192, 192, 1)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            fill: true
        },
        {
            label: <?= json_encode(__t('admin.chart_new_jobs'), JSON_UNESCAPED_UNICODE) ?>,
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
                text: <?= json_encode(__t('admin.chart_activity'), JSON_UNESCAPED_UNICODE) ?>
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
            label: <?= json_encode(__t('admin.chart_revenue'), JSON_UNESCAPED_UNICODE) ?>,
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
                text: <?= json_encode(__t('admin.chart_daily_revenue'), JSON_UNESCAPED_UNICODE) ?>
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
