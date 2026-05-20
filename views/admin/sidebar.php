<?php
require_once __DIR__ . '/_auth.php';
require_once dirname(__DIR__, 2) . '/models/Language.php';

$adminMenuItems = [
    ['permission' => 'admin.dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2', 'label' => __t('admin.dashboard')],
    ['permission' => 'users.view', 'href' => 'manage_users.php', 'icon' => 'bi-people', 'label' => __t('admin.menu.users')],
    ['permission' => 'jobs.view', 'href' => 'manage_jobs.php', 'icon' => 'bi-briefcase', 'label' => __t('admin.menu.jobs')],
    ['permission' => 'messages.moderate', 'href' => 'manage_messages.php', 'icon' => 'bi-chat-dots', 'label' => __t('admin.menu.conversations')],
    ['permission' => 'newsletter.manage', 'href' => 'newsletter_manager.php', 'icon' => 'bi-envelope', 'label' => __t('admin.menu.newsletter')],
    ['permission' => 'pages.manage', 'href' => 'pages.php', 'icon' => 'bi-file-earmark-text', 'label' => __t('admin.menu.pages')],
    ['permission' => 'settings.manage', 'href' => 'site_settings.php', 'icon' => 'bi-gear', 'label' => __t('admin.menu.settings')],
    ['permission' => 'reports.view', 'href' => 'reports.php', 'icon' => 'bi-graph-up', 'label' => __t('admin.menu.reports')],
    ['permission' => 'transactions.view', 'href' => 'transactions.php', 'icon' => 'bi-cash-stack', 'label' => __t('admin.menu.transactions')],
    ['permission' => 'roles.manage', 'href' => 'access_matrix.php', 'icon' => 'bi-shield-lock', 'label' => __t('admin.menu.access')],
];
?>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($adminMenuItems as $item): ?>
                    <?php if (canAdminAccess($item['permission'])): ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" href="<?= htmlspecialchars($item['href']) ?>">
                                <i class="bi <?= htmlspecialchars($item['icon']) ?> me-2"></i>
                                <span><?= htmlspecialchars($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>
