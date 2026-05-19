<?php
require_once __DIR__ . '/_auth.php';

$adminMenuItems = [
    ['permission' => 'admin.dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    ['permission' => 'users.view', 'href' => 'manage_users.php', 'icon' => 'bi-people', 'label' => 'Uzytkownicy'],
    ['permission' => 'jobs.view', 'href' => 'manage_jobs.php', 'icon' => 'bi-briefcase', 'label' => 'Ogloszenia'],
    ['permission' => 'messages.moderate', 'href' => 'manage_messages.php', 'icon' => 'bi-chat-dots', 'label' => 'Konwersacje'],
    ['permission' => 'newsletter.manage', 'href' => 'newsletter_manager.php', 'icon' => 'bi-envelope', 'label' => 'Newsletter'],
    ['permission' => 'pages.manage', 'href' => 'pages.php', 'icon' => 'bi-file-earmark-text', 'label' => 'Podstrony'],
    ['permission' => 'settings.manage', 'href' => 'site_settings.php', 'icon' => 'bi-gear', 'label' => 'Ustawienia'],
    ['permission' => 'reports.view', 'href' => 'reports.php', 'icon' => 'bi-graph-up', 'label' => 'Raporty'],
    ['permission' => 'transactions.view', 'href' => 'transactions.php', 'icon' => 'bi-cash-stack', 'label' => 'Transakcje'],
    ['permission' => 'roles.manage', 'href' => 'access_matrix.php', 'icon' => 'bi-shield-lock', 'label' => 'Dostepy'],
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
