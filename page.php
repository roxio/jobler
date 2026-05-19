<?php
session_start();
require_once 'models/Page.php';
require_once 'models/SiteSettings.php';
require_once 'models/Language.php';

$pageModel = new Page();
$currentLocale = Language::current('frontend');
$slug = $_GET['slug'] ?? '';
$page = $pageModel->getBySlug($slug, true, $currentLocale);

if (!$page) {
    http_response_code(404);
}

$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();
$pageTitle = $page['meta_title'] ?: ($page['title'] ?? 'Nie znaleziono strony');
$metaDescription = $page['meta_description'] ?: ($siteSettings['meta_description'] ?? '');
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(strtolower(substr($currentLocale, 0, 2)), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($siteSettings['title'] ?? 'Jobler', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
    <?php include 'templates/navbar.php'; ?>

    <main class="container my-5">
        <?php if ($page): ?>
            <article class="static-page">
                <div class="static-page__header">
                    <h1><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <div class="static-page__content">
                    <?= nl2br(htmlspecialchars($page['content'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </article>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-file-earmark-x display-1 text-muted"></i>
                <h1 class="mt-3"><?= htmlspecialchars(__t('page.not_found_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-muted"><?= htmlspecialchars(__t('page.not_found_text'), ENT_QUOTES, 'UTF-8') ?></p>
                <a href="/?lang=<?= urlencode($currentLocale) ?>" class="btn btn-primary mt-2"><?= htmlspecialchars(__t('page.back_home'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'templates/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
