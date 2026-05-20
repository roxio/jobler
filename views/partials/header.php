<?php
require_once dirname(__DIR__, 2) . '/models/AccessControl.php';
require_once dirname(__DIR__, 2) . '/models/Language.php';
require_once dirname(__DIR__, 2) . '/models/Job.php';
$languageContext = strpos($_SERVER['REQUEST_URI'] ?? '', '/views/admin/') !== false ? 'admin' : 'frontend';
$isAdminContext = $languageContext === 'admin';
$currentLocale = Language::current($languageContext);
if (!$isAdminContext) {
    (new Job())->processCompletionTimeouts();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($currentLocale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(__t('site.meta_description')) ?>">
    <title><?= htmlspecialchars(__t('site.title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" >
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/style.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php
$navbarLanguageContext = $languageContext;
include dirname(__DIR__, 2) . '/templates/navbar.php';
?>
<?php if ($isAdminContext): ?>
<div class="admin-shell">
    <div class="admin-page">
<?php else: ?>
<div class="container mt-4">
<?php endif; ?>

