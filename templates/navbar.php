<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/AccessControl.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../models/Language.php';
require_once __DIR__ . '/../models/Page.php';
require_once __DIR__ . '/../models/SiteSettings.php';

$navbarLanguageContext = $navbarLanguageContext ?? 'frontend';
$navbarAccessControl = function_exists('currentAccessControl') ? currentAccessControl() : new AccessControl();
$navbarPageModel = new Page();
$siteSettingsModel = new SiteSettings();
$siteSettings = $GLOBALS['siteSettings'] ?? $siteSettingsModel->getSettings();
$currentLocale = Language::current($navbarLanguageContext);
$availableLanguages = Language::available();
$menuPages = $navbarPageModel->getVisibleInMenu($currentLocale);
$logo = $siteSettings['logo'] ?? 'logo.png';
$siteTitle = $siteSettings['title'] ?? 'Jobler';
$isLoggedIn = isset($_SESSION['user_id']);
$isExecutor = ($_SESSION['user_role'] ?? '') === 'executor';
$hasAdminAccess = $isLoggedIn && $navbarAccessControl->hasAnyAdminAccess((int)$_SESSION['user_id']);

try {
    $pdo = Database::getConnection();
    $categoriesStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $categoriesStmt ? $categoriesStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $categories = [];
}
?>

<nav class="site-navbar navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand site-navbar__brand" href="/?lang=<?= urlencode($currentLocale) ?>">
            <img src="/img/<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?>">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNavbar" aria-controls="siteNavbar" aria-expanded="false" aria-label="<?= htmlspecialchars(__t('nav.menu', [], 'Menu'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse site-navbar__collapse" id="siteNavbar">
            <div class="site-navbar__row site-navbar__row--main">
                <ul class="navbar-nav site-navbar__main-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="categoryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= htmlspecialchars(__t('nav.categories'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="categoryDropdown">
                            <li><a class="dropdown-item" href="/index.php?lang=<?= urlencode($currentLocale) ?>"><?= htmlspecialchars(__t('nav.all'), ENT_QUOTES, 'UTF-8') ?></a></li>
                            <?php foreach ($categories as $navCategory): ?>
                                <li>
                                    <a class="dropdown-item" href="/index.php?category=<?= (int)$navCategory['id'] ?>&lang=<?= urlencode($currentLocale) ?>">
                                        <?= htmlspecialchars($navCategory['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/index.php?lang=<?= urlencode($currentLocale) ?>"><?= htmlspecialchars(__t('nav.browse_jobs', [], 'Ogłoszenia'), ENT_QUOTES, 'UTF-8') ?></a>
                    </li>

                    <?php foreach ($menuPages as $menuPage): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= htmlspecialchars($navbarPageModel->publicUrl($menuPage, $currentLocale), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($menuPage['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <ul class="navbar-nav site-navbar__utility-nav">
                    <?php if (!empty($availableLanguages)): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-translate"></i>
                                <?= htmlspecialchars($availableLanguages[$currentLocale]['short'] ?? __t('nav.language'), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                                <?php foreach ($availableLanguages as $language): ?>
                                    <li>
                                        <a class="dropdown-item <?= $language['code'] === $currentLocale ? 'active' : '' ?>" href="<?= htmlspecialchars(Language::urlWithLocale($language['code']), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_account_balance'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/views/executor/payment.php">
                                <i class="bi bi-wallet2"></i>
                                <?= htmlspecialchars(__t('nav.balance', ['points' => $_SESSION['user_account_balance']]), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php"><?= htmlspecialchars(__t('nav.logout'), ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php"><?= htmlspecialchars(__t('nav.login'), ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-success btn-sm site-navbar__auth-btn" href="/register.php"><?= htmlspecialchars(__t('nav.register'), ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if ($isLoggedIn): ?>
                <div class="site-navbar__row site-navbar__row--account">
                    <span class="site-navbar__section-label"><?= htmlspecialchars(__t('nav.account_zone', [], 'Strefa użytkownika'), ENT_QUOTES, 'UTF-8') ?></span>
                    <ul class="navbar-nav site-navbar__account-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="/views/user/dashboard.php"><i class="bi bi-speedometer2"></i><?= htmlspecialchars(__t('nav.user_panel'), ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/views/user/job_list.php"><i class="bi bi-list-task"></i><?= htmlspecialchars(__t('nav.my_jobs'), ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/views/user/create_job.php"><i class="bi bi-plus-circle"></i><?= htmlspecialchars(__t('nav.add_job', [], 'Dodaj ogłoszenie'), ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                        <?php if ($isExecutor): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/executor/dashboard.php"><i class="bi bi-briefcase"></i><?= htmlspecialchars(__t('nav.executor_panel'), ENT_QUOTES, 'UTF-8') ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if ($hasAdminAccess): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/admin/dashboard.php"><i class="bi bi-shield-lock"></i><?= htmlspecialchars(__t('nav.admin_panel'), ENT_QUOTES, 'UTF-8') ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
