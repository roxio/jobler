<?php
require_once dirname(__DIR__, 2) . '/models/AccessControl.php';
require_once dirname(__DIR__, 2) . '/models/Language.php';
$languageContext = strpos($_SERVER['REQUEST_URI'] ?? '', '/views/admin/') !== false ? 'admin' : 'frontend';
Language::setCurrent(Language::defaultLocale($languageContext));
$currentLocale = Language::current($languageContext);
$headerAccessControl = new AccessControl();
if (!isset($categories) || !is_array($categories)) {
    try {
        require_once dirname(__DIR__, 2) . '/models/Database.php';
        $headerPdo = Database::getConnection();
        $categories = $headerPdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $categories = [];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($currentLocale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(__t('site.meta_description')) ?>">
    <title><?= htmlspecialchars(__t('site.title')) ?></title>
    <!-- Link do Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" >
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Możesz dodać tutaj własne style CSS -->
    <link rel="stylesheet" href="../../css/style.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Nawigacja -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
   <div class="container">
    <!-- Logo -->
    <a class="navbar-brand" href="/">
      <img src="/img/logo.png" alt="Logo" style="height: 40px;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <!-- Dropdown menu "Usługi" -->
 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="categoryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars(__t('nav.categories')) ?>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="categoryDropdown">
                        <li><a class="dropdown-item" href="/index.php?lang=<?= urlencode($currentLocale) ?>"><?= htmlspecialchars(__t('nav.all')) ?></a></li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a class="dropdown-item" href="/index.php?category=<?= $category['id'] ?>&lang=<?= urlencode($currentLocale) ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
        <!-- Jeżeli użytkownik jest administratorem -->
        <?php if (isset($_SESSION['user_id']) && $headerAccessControl->hasAnyAdminAccess((int)$_SESSION['user_id'])): ?>
            <li class="nav-item">
              <a class="nav-link" href="../admin/dashboard.php"><?= htmlspecialchars(__t('nav.admin_panel')) ?></a>
            </li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'executor'): ?>
            <li class="nav-item">
              <a class="nav-link" href="../executor/dashboard.php"><?= htmlspecialchars(__t('nav.executor_panel')) ?></a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link" href="/views/user/dashboard.php"><?= htmlspecialchars(__t('nav.user_panel')) ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/views/user/job_list.php"><?= htmlspecialchars(__t('nav.my_jobs')) ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/logout.php"><?= htmlspecialchars(__t('nav.logout')) ?></a>
        </li>
        <?php if (isset($_SESSION['user_account_balance'])): ?>
            <li class="nav-item"><a class="nav-link" href="/views/executor/payment.php"><?= htmlspecialchars(__t('nav.balance', ['points' => $_SESSION['user_account_balance']])) ?></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <button class="btn btn-light d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
    <i class="fas fa-bars"></i> Menu
</button>
</nav>
<script src=".https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="container mt-4">

