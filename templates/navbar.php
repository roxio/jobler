<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/SiteSettings.php';
require_once __DIR__ . '/../models/AccessControl.php';
require_once __DIR__ . '/../models/Page.php';
require_once __DIR__ . '/../models/Language.php';

$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();
$navbarAccessControl = new AccessControl();
$navbarPageModel = new Page();
$currentLocale = Language::current('frontend');
$availableLanguages = Language::available();
$menuPages = $navbarPageModel->getVisibleInMenu($currentLocale);
$logo = $siteSettings['logo'] ?? 'logo.png';
$siteTitle = $siteSettings['title'] ?? 'Jobler';

$pdo = Database::getConnection();
$categoriesStmt = $pdo->query("SELECT id, name FROM categories");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container">
    <a class="navbar-brand" href="/?lang=<?= urlencode($currentLocale) ?>">
      <img src="/img/<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?>" style="height: 40px;">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
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

        <?php foreach ($menuPages as $menuPage): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= htmlspecialchars($navbarPageModel->publicUrl($menuPage, $currentLocale), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($menuPage['title'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </li>
        <?php endforeach; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="/views/user/dashboard.php"><?= htmlspecialchars(__t('nav.user_panel'), ENT_QUOTES, 'UTF-8') ?></a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="/views/user/job_list.php"><?= htmlspecialchars(__t('nav.my_jobs'), ENT_QUOTES, 'UTF-8') ?></a>
          </li>

          <?php if ($navbarAccessControl->hasAnyAdminAccess((int)$_SESSION['user_id'])): ?>
            <li class="nav-item">
              <a class="nav-link" href="/views/admin/dashboard.php"><?= htmlspecialchars(__t('nav.admin_panel'), ENT_QUOTES, 'UTF-8') ?></a>
            </li>
          <?php endif; ?>

          <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'executor'): ?>
            <li class="nav-item">
              <a class="nav-link" href="/views/executor/dashboard.php"><?= htmlspecialchars(__t('nav.executor_panel'), ENT_QUOTES, 'UTF-8') ?></a>
            </li>
          <?php endif; ?>

          <li class="nav-item">
            <a class="nav-link" href="/logout.php"><?= htmlspecialchars(__t('nav.logout'), ENT_QUOTES, 'UTF-8') ?></a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="/login.php"><?= htmlspecialchars(__t('nav.login'), ENT_QUOTES, 'UTF-8') ?></a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="/register.php"><?= htmlspecialchars(__t('nav.register'), ENT_QUOTES, 'UTF-8') ?></a>
          </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_account_balance'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="/views/executor/payment.php">
              <?= htmlspecialchars(__t('nav.balance', ['points' => $_SESSION['user_account_balance']]), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </li>
        <?php endif; ?>

        <?php if (!empty($availableLanguages)): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
      </ul>
    </div>
  </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
