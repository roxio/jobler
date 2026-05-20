<?php
include_once __DIR__ . '/../../models/AccessControl.php';
$footerAccessControl = new AccessControl();
?>
<?php if (isset($_SESSION['user_id']) && $footerAccessControl->hasAnyAdminAccess((int)$_SESSION['user_id'])): ?>
    <?php
    include_once __DIR__ . '/../../models/SiteSettings.php';
    $footerSettingsModel = new SiteSettings();
    $footerSettings = $footerSettingsModel->getSettings();
    $copyrightText = $footerSettingsModel->formatCopyrightText($footerSettings);
    ?>
    <br>
    <div class="container">
        <span class="text-muted"><?= htmlspecialchars($copyrightText, ENT_QUOTES, 'UTF-8') ?></span>
        <div class="stupidbottomm"> </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="stupidbottom"> </div>
</body>
</html>
