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
$userModel = new User();
$jobModel = new Job();
$settingsModel = new SiteSettings($pdo);
$messageModel = new Message($pdo);
$transactionModel = new TransactionHistory($pdo);
$newsletter = new Newsletter();

$newUsersData = $userModel->getNewUsersPerDay();
$newJobsData = $jobModel->getNewJobsPerDay();
$revenueData = $transactionModel->getDailyRevenue();

$userCount = $userModel->getUserCount();
$jobCount = $jobModel->getJobCount();
$newUsers = $userModel->getNewUsersCount();
$newJobs = $jobModel->getNewJobsCount();
$siteViews = $settingsModel->getSiteViews();
$pendingChanges = $userModel->getPendingAccountChangesCount();
$totalRevenue = $transactionModel->getTotalRevenue();
$activeJobs = $jobModel->getActiveJobsCount();
$newsletterStats = $newsletter->getNewsletterStats();
$recentTransactions = $transactionModel->getRecentTransactionsCount();
$conversationCount = $messageModel->countConversations();

$pendingAlerts = [
    'pending_changes' => $pendingChanges,
    'site_errors' => $settingsModel->getSiteErrors(),
    'unread_reports' => $userModel->getUnreadReportsCount()
];
?>
<?php include '../partials/header.php'; ?>

<div class="admin-topbar">
    <div class="admin-topbar-inner">
        <div class="admin-brand">
            <span class="admin-brand-icon"><i class="bi bi-tools"></i></span>
            <span><?= htmlspecialchars(__t('admin.panel')) ?></span>
        </div>
        <nav class="admin-nav">
            <?php include 'sidebar.php'; ?>
        </nav>
    </div>
</div>

<section class="admin-heading">
    <div>
        <h1><?= htmlspecialchars(__t('admin.dashboard')) ?></h1>
        <p><?= htmlspecialchars(__t('admin.user_job_activity')) ?>, <?= htmlspecialchars(__t('admin.requests')) ?>, <?= htmlspecialchars(__t('admin.recent_activity')) ?></p>
    </div>
    <div class="admin-heading-actions">
        <a href="add_job.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i><?= htmlspecialchars(__t('admin.add_job')) ?></a>
        <a href="site_settings.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i><?= htmlspecialchars(__t('admin.menu.settings')) ?></a>
    </div>
</section>

<?php if ($pendingAlerts['pending_changes'] > 0 || $pendingAlerts['site_errors'] > 0 || $pendingAlerts['unread_reports'] > 0): ?>
    <section class="admin-notices">
        <?php if ($pendingAlerts['pending_changes'] > 0): ?>
            <div class="admin-notice admin-notice-warning">
                <div>
                    <strong><?= htmlspecialchars(__t('admin.attention')) ?></strong>
                    <span><?= htmlspecialchars(__t('admin.pending_account_changes', ['count' => $pendingAlerts['pending_changes']])) ?></span>
                </div>
                <a href="manage_users.php?filter=need_attention" class="btn btn-sm btn-outline-warning"><?= htmlspecialchars(__t('admin.go')) ?></a>
            </div>
        <?php endif; ?>

        <?php if ($pendingAlerts['site_errors'] > 0): ?>
            <div class="admin-notice admin-notice-danger">
                <div>
                    <strong><?= htmlspecialchars(__t('admin.system_error_notice')) ?></strong>
                    <span><?= htmlspecialchars(__t('admin.recent_errors')) ?>: <?= (int)$pendingAlerts['site_errors'] ?></span>
                </div>
                <a href="site_settings.php" class="btn btn-sm btn-outline-danger"><?= htmlspecialchars(__t('admin.go')) ?></a>
            </div>
        <?php endif; ?>

        <?php if ($pendingAlerts['unread_reports'] > 0): ?>
            <div class="admin-notice admin-notice-info">
                <div>
                    <strong><?= htmlspecialchars(__t('admin.unread_reports_notice', ['count' => $pendingAlerts['unread_reports']])) ?></strong>
                    <span><?= htmlspecialchars(__t('admin.requests')) ?></span>
                </div>
                <a href="reports.php" class="btn btn-sm btn-outline-info"><?= htmlspecialchars(__t('admin.go')) ?></a>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="admin-stat-grid">
    <article class="admin-stat-card">
        <span class="admin-stat-icon"><i class="bi bi-people"></i></span>
        <span class="admin-stat-label"><?= htmlspecialchars(__t('admin.users')) ?></span>
        <strong><?= number_format((int)$userCount) ?></strong>
        <small><?= htmlspecialchars(__t('admin.new_users')) ?>: <?= number_format((int)$newUsers) ?></small>
    </article>
    <article class="admin-stat-card admin-stat-green">
        <span class="admin-stat-icon"><i class="bi bi-briefcase"></i></span>
        <span class="admin-stat-label"><?= htmlspecialchars(__t('admin.jobs')) ?></span>
        <strong><?= number_format((int)$jobCount) ?></strong>
        <small><?= htmlspecialchars(__t('admin.jobs.active')) ?>: <?= number_format((int)$activeJobs) ?></small>
    </article>
    <article class="admin-stat-card admin-stat-cyan">
        <span class="admin-stat-icon"><i class="bi bi-envelope"></i></span>
        <span class="admin-stat-label"><?= htmlspecialchars(__t('admin.newsletter_subscribers')) ?></span>
        <strong><?= number_format((int)($newsletterStats['active'] ?? 0)) ?></strong>
        <small><?= htmlspecialchars(__t('admin.site_views')) ?>: <?= number_format((int)$siteViews) ?></small>
    </article>
    <article class="admin-stat-card admin-stat-amber">
        <span class="admin-stat-icon"><i class="bi bi-cash-stack"></i></span>
        <span class="admin-stat-label"><?= htmlspecialchars(__t('admin.total_revenue')) ?></span>
        <strong><?= number_format((float)$totalRevenue, 2) ?> PLN</strong>
        <small><?= htmlspecialchars(__t('admin.new_transactions')) ?>: <?= number_format((int)$recentTransactions) ?></small>
    </article>
</section>

<section class="admin-content-grid">
    <article class="admin-panel">
        <div class="admin-panel-header">
            <h2><?= htmlspecialchars(__t('admin.user_job_activity')) ?></h2>
            <span class="admin-soft-badge"><?= htmlspecialchars(__t('admin.last_7_days')) ?></span>
        </div>
        <div class="admin-panel-body">
            <canvas id="combinedChart" height="250"></canvas>
        </div>
    </article>

    <aside class="admin-panel">
        <div class="admin-panel-header">
            <h2><?= htmlspecialchars(__t('admin.shortcuts')) ?></h2>
        </div>
        <div class="admin-panel-body">
            <div class="admin-shortcuts">
                <a href="add_job.php"><span><i class="bi bi-briefcase me-2"></i><?= htmlspecialchars(__t('admin.add_job')) ?></span><i class="bi bi-chevron-right"></i></a>
                <a href="site_settings.php"><span><i class="bi bi-gear me-2"></i><?= htmlspecialchars(__t('admin.menu.settings')) ?></span><i class="bi bi-chevron-right"></i></a>
                <a href="manage_users.php"><span><i class="bi bi-people me-2"></i><?= htmlspecialchars(__t('admin.manage_users')) ?></span><i class="bi bi-chevron-right"></i></a>
                <a href="reports.php"><span><i class="bi bi-graph-up me-2"></i><?= htmlspecialchars(__t('admin.generate_report')) ?></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </aside>
</section>

<section class="admin-content-grid admin-content-grid-even">
    <article class="admin-panel">
        <div class="admin-panel-header">
            <h2><?= htmlspecialchars(__t('admin.daily_revenue')) ?></h2>
        </div>
        <div class="admin-panel-body">
            <canvas id="revenueChart" height="250"></canvas>
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-header">
            <h2><?= htmlspecialchars(__t('admin.activity_7_days')) ?></h2>
        </div>
        <div class="admin-panel-body">
            <div class="admin-mini-list">
                <div><span><?= htmlspecialchars(__t('admin.new_users')) ?></span><strong><?= number_format((int)$newUsers) ?></strong></div>
                <div><span><?= htmlspecialchars(__t('admin.new_jobs')) ?></span><strong><?= number_format((int)$newJobs) ?></strong></div>
                <div><span><?= htmlspecialchars(__t('admin.new_transactions')) ?></span><strong><?= number_format((int)$recentTransactions) ?></strong></div>
                <div><span><?= htmlspecialchars(__t('admin.new_conversations')) ?></span><strong><?= number_format((int)$conversationCount) ?></strong></div>
            </div>
        </div>
    </article>
</section>

<section class="admin-panel">
    <div class="admin-panel-header">
        <h2><?= htmlspecialchars(__t('admin.recent_activity')) ?></h2>
        <a href="reports.php" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars(__t('admin.view_all')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover admin-table">
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
                        <td><?= date('Y-m-d H:i', strtotime($activity['timestamp'])) ?></td>
                        <td><?= htmlspecialchars($activity['username']) ?> (ID: <?= (int)$activity['user_id'] ?>)</td>
                        <td><span class="badge bg-<?= $activity['type'] == 'login' ? 'success' : 'info' ?>"><?= htmlspecialchars($activity['type']) ?></span></td>
                        <td><?= htmlspecialchars($activity['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include '../partials/footer.php'; ?>

<script>
var ctxCombined = document.getElementById('combinedChart').getContext('2d');
var combinedChart = new Chart(ctxCombined, {
    type: 'line',
    data: {
        labels: [<?php foreach ($newUsersData as $data) echo '"' . $data['date'] . '",'; ?>],
        datasets: [{
            label: <?= json_encode(__t('admin.chart_new_users'), JSON_UNESCAPED_UNICODE) ?>,
            data: [<?php foreach ($newUsersData as $data) echo $data['count'] . ','; ?>],
            borderColor: 'rgba(37, 99, 235, 1)',
            backgroundColor: 'rgba(37, 99, 235, 0.12)',
            fill: true,
            tension: 0.35
        },
        {
            label: <?= json_encode(__t('admin.chart_new_jobs'), JSON_UNESCAPED_UNICODE) ?>,
            data: [<?php foreach ($newJobsData as $data) echo $data['count'] . ','; ?>],
            borderColor: 'rgba(22, 163, 74, 1)',
            backgroundColor: 'rgba(22, 163, 74, 0.12)',
            fill: true,
            tension: 0.35
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
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

var ctxRevenue = document.getElementById('revenueChart').getContext('2d');
var revenueChart = new Chart(ctxRevenue, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($revenueData as $data) echo '"' . $data['date'] . '",'; ?>],
        datasets: [{
            label: <?= json_encode(__t('admin.chart_revenue'), JSON_UNESCAPED_UNICODE) ?>,
            data: [<?php foreach ($revenueData as $data) echo $data['amount'] . ','; ?>],
            borderColor: 'rgba(22, 163, 74, 1)',
            backgroundColor: 'rgba(22, 163, 74, 0.18)',
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
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
