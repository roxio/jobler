<?php
session_start();

include_once('../../config/config.php');
include_once('../../models/Database.php');
include_once('../../models/SiteSettings.php');
include_once('../../models/Language.php');
include_once('../../models/ErrorLogs.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/LoginHistory.php');

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

$pdo = Database::getConnection();
$settingsModel = new SiteSettings($pdo);
$errorLogsModel = new ErrorLogs($pdo);
$transactionHistoryModel = new TransactionHistory($pdo);
$loginHistoryModel = new LoginHistory($pdo);
$availableLanguages = Language::available();

function safeEcho($value, $default = '') {
    return htmlspecialchars((string)($value ?? $default), ENT_QUOTES, 'UTF-8');
}

function decodeListSetting($value) {
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? array_values(array_filter(array_map('trim', $decoded))) : [];
}

function encodeListSetting($values) {
    return json_encode(array_values(array_filter(array_map('trim', (array)$values))), JSON_UNESCAPED_UNICODE);
}

function decodeTemplateSetting($value) {
    $defaults = [
        'offer_received' => __t('admin.settings.default_offer_received'),
        'offer_accepted' => __t('admin.settings.default_offer_accepted'),
        'conversation_reported' => __t('admin.settings.default_conversation_reported'),
    ];
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
}

function uploadSettingsAsset($file, $prefix, $currentFile) {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $currentFile;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__t('admin.settings.file_upload_error'));
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException(__t('admin.settings.invalid_file_format'));
    }

    $uploadDir = '../../img/';
    if ($currentFile && !in_array($currentFile, ['default-logo.png', 'favicon.ico'], true) && file_exists($uploadDir . $currentFile)) {
        unlink($uploadDir . $currentFile);
    }

    $fileName = uniqid($prefix . '_', true) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        throw new RuntimeException(__t('admin.settings.file_save_error'));
    }

    return $fileName;
}

function backupDir() {
    $dir = dirname(__DIR__, 2) . '/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function createDatabaseBackup(PDO $pdo) {
    $fileName = 'database_' . date('Ymd_His') . '.sql';
    $path = backupDir() . '/' . $fileName;
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $output = "-- Database backup " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $output .= "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns = array_map(fn($column) => "`$column`", array_keys($row));
            $values = array_map(fn($value) => $value === null ? 'NULL' : $pdo->quote((string)$value), array_values($row));
            $output .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $output .= "\n";
    }

    file_put_contents($path, $output);
    return $fileName;
}

function createSystemBackup() {
    $fileName = 'system_' . date('Ymd_His') . '.zip';
    $path = backupDir() . '/' . $fileName;

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(__t('admin.settings.zip_missing'));
    }

    $root = dirname(__DIR__, 2);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException(__t('admin.settings.system_backup_error'));
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $fullPath = $file->getPathname();
        if (str_contains($fullPath, DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $relativePath = substr($fullPath, strlen($root) + 1);
        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($fullPath, $relativePath);
        }
    }

    $zip->close();
    return $fileName;
}

function generateSitemap(PDO $pdo) {
    $baseUrl = rtrim(defined('APP_URL') ? APP_URL : '/', '/');
    $urls = [
        $baseUrl . '/',
        $baseUrl . '/login.php',
        $baseUrl . '/register.php',
    ];

    $jobs = $pdo->query("SELECT id FROM jobs WHERE status IN ('open', 'in_progress') ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($jobs as $jobId) {
        $urls[] = $baseUrl . '/views/job/view.php?id=' . (int)$jobId;
    }

    try {
        $pages = $pdo->query("SELECT slug FROM pages WHERE status = 'published' ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($pages as $slug) {
            $urls[] = $baseUrl . '/page.php?slug=' . rawurlencode($slug);
        }
    } catch (Throwable $e) {
        error_log('Sitemap pages warning: ' . $e->getMessage());
    }

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($urls as $url) {
        $xml .= "  <url><loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc></url>\n";
    }
    $xml .= "</urlset>\n";

    file_put_contents(dirname(__DIR__, 2) . '/sitemap.xml', $xml);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['action']) && $_GET['action'] === 'category_children') {
    header('Content-Type: application/json; charset=utf-8');
    $parentId = isset($_GET['parent_id']) && $_GET['parent_id'] !== '' ? (int)$_GET['parent_id'] : null;
    echo json_encode(['success' => true, 'categories' => $settingsModel->getCategoriesByParent($parentId)]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $file = basename($_GET['file'] ?? '');
    $path = backupDir() . '/' . $file;
    if ($file === '' || !is_file($path)) {
        http_response_code(404);
        exit(__t('admin.settings.backup_not_found'));
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$successMessage = '';
$errorMessage = '';
$currentSettings = $settingsModel->getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errorMessage = __t('cms.security_error');
    } else {
        $formType = $_POST['form_type'];

        try {
            if ($formType === 'update_settings') {
                $settingsData = [
                    'title' => trim($_POST['site_title'] ?? ''),
                    'logo' => uploadSettingsAsset($_FILES['site_logo'] ?? [], 'logo', $currentSettings['logo'] ?? 'default-logo.png'),
                    'favicon' => uploadSettingsAsset($_FILES['site_favicon'] ?? [], 'favicon', $currentSettings['favicon'] ?? ''),
                    'copyright_text' => trim($_POST['copyright_text'] ?? ''),
                    'meta_title' => trim($_POST['meta_title'] ?? ''),
                    'meta_description' => trim($_POST['meta_description'] ?? ''),
                    'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
                    'max_ads' => max(1, (int)($_POST['max_ads'] ?? 10)),
                    'promotion_fee' => max(0, (float)($_POST['promotion_fee'] ?? 10)),
                    'smtp_server' => trim($_POST['smtp_server'] ?? ''),
                    'smtp_port' => trim($_POST['smtp_port'] ?? ''),
                    'smtp_username' => trim($_POST['smtp_username'] ?? ''),
                    'smtp_password' => trim($_POST['smtp_password'] ?? ''),
                    'facebook_url' => trim($_POST['facebook_url'] ?? ''),
                    'twitter_url' => trim($_POST['twitter_url'] ?? ''),
                    'instagram_url' => trim($_POST['instagram_url'] ?? ''),
                    'linkedin_url' => trim($_POST['linkedin_url'] ?? ''),
                    'contact_email' => trim($_POST['contact_email'] ?? ''),
                    'contact_phone' => trim($_POST['contact_phone'] ?? ''),
                    'contact_address' => trim($_POST['contact_address'] ?? ''),
                    'business_hours' => trim($_POST['business_hours'] ?? ''),
                    'default_language' => $_POST['frontend_default_language'] ?? 'PL_pl',
                    'frontend_default_language' => $_POST['frontend_default_language'] ?? 'PL_pl',
                    'admin_default_language' => $_POST['admin_default_language'] ?? 'PL_pl',
                    'layout_variant' => $_POST['layout_variant'] ?? 'classic',
                    'company_name' => trim($_POST['company_name'] ?? ''),
                    'company_tax_id' => trim($_POST['company_tax_id'] ?? ''),
                    'company_addresses' => encodeListSetting($_POST['company_addresses'] ?? []),
                    'company_emails' => encodeListSetting($_POST['company_emails'] ?? []),
                    'company_phones' => encodeListSetting($_POST['company_phones'] ?? []),
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                    'maintenance_message' => trim($_POST['maintenance_message'] ?? ''),
                    'email_templates' => json_encode($_POST['email_templates'] ?? [], JSON_UNESCAPED_UNICODE),
                    'sitemap_enabled' => isset($_POST['sitemap_enabled']) ? 1 : 0,
                ];

                if ($settingsModel->updateSettings($settingsData)) {
                    $successMessage = __t('admin.settings.saved');
                    $currentSettings = $settingsModel->getSettings();
                    $settingsModel->logSettingsChange($_SESSION['user_id'], 'Zaktualizowane ustawienia strony', date('Y-m-d H:i:s'));
                } else {
                    $errorMessage = __t('admin.settings.save_error');
                }
            }

            if ($formType === 'add_category') {
                $name = trim($_POST['category_name'] ?? '');
                $parentId = $_POST['parent_category'] !== '' ? (int)$_POST['parent_category'] : null;
                if ($name === '') {
                    $errorMessage = __t('admin.settings.category_name_required');
                } else {
                    $settingsModel->addCategory($name, $parentId);
                    $successMessage = __t('admin.settings.category_added');
                }
            }

            if ($formType === 'update_category') {
                $settingsModel->updateCategory((int)$_POST['category_id'], trim($_POST['category_name'] ?? ''), $_POST['parent_category'] !== '' ? (int)$_POST['parent_category'] : null);
                $successMessage = __t('admin.settings.category_updated');
            }

            if ($formType === 'delete_category') {
                $successMessage = $settingsModel->deleteCategory((int)$_POST['category_id'])
                    ? __t('admin.settings.category_deleted')
                    : __t('admin.settings.category_delete_blocked');
            }

            if ($formType === 'generate_sitemap') {
                generateSitemap($pdo);
                $settingsModel->updateSettingValue('sitemap_last_generated', date('Y-m-d H:i:s'));
                $currentSettings = $settingsModel->getSettings();
                $successMessage = __t('admin.settings.sitemap_generated');
            }

            if ($formType === 'backup_database') {
                $fileName = createDatabaseBackup($pdo);
                $settingsModel->updateSettingValue('last_database_backup', $fileName);
                $currentSettings = $settingsModel->getSettings();
                $successMessage = __t('admin.settings.database_backup_created');
            }

            if ($formType === 'backup_system') {
                $fileName = createSystemBackup();
                $settingsModel->updateSettingValue('last_system_backup', $fileName);
                $currentSettings = $settingsModel->getSettings();
                $successMessage = __t('admin.settings.system_backup_created');
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$companyAddresses = decodeListSetting($currentSettings['company_addresses'] ?? '[]');
$companyEmails = decodeListSetting($currentSettings['company_emails'] ?? '[]');
$companyPhones = decodeListSetting($currentSettings['company_phones'] ?? '[]');
$emailTemplates = decodeTemplateSetting($currentSettings['email_templates'] ?? '{}');
$rootCategories = $settingsModel->getCategoriesByParent(null);
$errors = $errorLogsModel->getRecentErrors();
$transactions = $transactionHistoryModel->getRecentTransactions();
$loginHistoryModel->logLogin($_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], date('Y-m-d H:i:s'));
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= safeEcho(__t('admin.panel')) ?></h5>
                    <nav class="nav"><?php include 'sidebar.php'; ?></nav>
                </div>

                <div class="card-body">
                    <?php if ($successMessage): ?><div class="alert alert-success"><?= safeEcho($successMessage) ?></div><?php endif; ?>
                    <?php if ($errorMessage): ?><div class="alert alert-danger"><?= safeEcho($errorMessage) ?></div><?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="settingsForm">
                        <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_type" value="update_settings">

                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#companyTab" type="button"><?= safeEcho(__t('admin.settings.tab_company')) ?></button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#brandingTab" type="button"><?= safeEcho(__t('admin.settings.tab_branding')) ?></button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#seoTab" type="button">SEO</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#systemTab" type="button"><?= safeEcho(__t('admin.settings.tab_system')) ?></button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#emailTab" type="button"><?= safeEcho(__t('admin.settings.tab_email')) ?></button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#categoriesTab" type="button"><?= safeEcho(__t('admin.settings.tab_categories')) ?></button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#maintenanceTab" type="button"><?= safeEcho(__t('admin.settings.tab_maintenance')) ?></button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#logsTab" type="button"><?= safeEcho(__t('admin.settings.tab_logs')) ?></button></li>
                        </ul>

                        <div class="tab-content border border-top-0 p-3">
                            <div class="tab-pane fade show active" id="companyTab">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label"><?= safeEcho(__t('admin.settings.company_name')) ?></label><input name="company_name" class="form-control" value="<?= safeEcho($currentSettings['company_name'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label"><?= safeEcho(__t('admin.settings.company_tax_id')) ?></label><input name="company_tax_id" class="form-control" value="<?= safeEcho($currentSettings['company_tax_id'] ?? '') ?>"></div>
                                    <div class="col-md-4"><label class="form-label"><?= safeEcho(__t('admin.settings.company_addresses')) ?></label><div class="multi-field" data-name="company_addresses"><?php foreach ($companyAddresses ?: [''] as $value): ?><div class="input-group mb-2"><input name="company_addresses[]" class="form-control" value="<?= safeEcho($value) ?>"><button type="button" class="btn btn-outline-danger remove-field"><i class="bi bi-x"></i></button></div><?php endforeach; ?></div><button type="button" class="btn btn-sm btn-outline-primary add-field"><?= safeEcho(__t('admin.settings.add_address')) ?></button></div>
                                    <div class="col-md-4"><label class="form-label"><?= safeEcho(__t('admin.settings.company_emails')) ?></label><div class="multi-field" data-name="company_emails"><?php foreach ($companyEmails ?: [''] as $value): ?><div class="input-group mb-2"><input type="email" name="company_emails[]" class="form-control" value="<?= safeEcho($value) ?>"><button type="button" class="btn btn-outline-danger remove-field"><i class="bi bi-x"></i></button></div><?php endforeach; ?></div><button type="button" class="btn btn-sm btn-outline-primary add-field"><?= safeEcho(__t('admin.settings.add_email')) ?></button></div>
                                    <div class="col-md-4"><label class="form-label"><?= safeEcho(__t('admin.settings.company_phones')) ?></label><div class="multi-field" data-name="company_phones"><?php foreach ($companyPhones ?: [''] as $value): ?><div class="input-group mb-2"><input name="company_phones[]" class="form-control" value="<?= safeEcho($value) ?>"><button type="button" class="btn btn-outline-danger remove-field"><i class="bi bi-x"></i></button></div><?php endforeach; ?></div><button type="button" class="btn btn-sm btn-outline-primary add-field"><?= safeEcho(__t('admin.settings.add_phone')) ?></button></div>
                                    <div class="col-md-6"><label class="form-label"><?= safeEcho(__t('admin.settings.contact_email')) ?></label><input type="email" name="contact_email" class="form-control" value="<?= safeEcho($currentSettings['contact_email'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label"><?= safeEcho(__t('admin.settings.business_hours')) ?></label><input name="business_hours" class="form-control" value="<?= safeEcho($currentSettings['business_hours'] ?? '') ?>"></div>
                                    <div class="col-12"><label class="form-label"><?= safeEcho(__t('admin.settings.footer_address')) ?></label><textarea name="contact_address" class="form-control" rows="2"><?= safeEcho($currentSettings['contact_address'] ?? '') ?></textarea></div>
                                    <input type="hidden" name="contact_phone" value="<?= safeEcho($currentSettings['contact_phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="tab-pane fade" id="brandingTab">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label"><?= safeEcho(__t('admin.settings.site_title')) ?></label><input name="site_title" class="form-control" value="<?= safeEcho($currentSettings['title'] ?? '') ?>" required></div>
                                    <div class="col-md-6"><label class="form-label"><?= safeEcho(__t('admin.settings.layout')) ?></label><select name="layout_variant" class="form-select"><option value="classic" <?= ($currentSettings['layout_variant'] ?? '') === 'classic' ? 'selected' : '' ?>><?= safeEcho(__t('admin.settings.layout_classic')) ?></option><option value="compact" <?= ($currentSettings['layout_variant'] ?? '') === 'compact' ? 'selected' : '' ?>><?= safeEcho(__t('admin.settings.layout_compact')) ?></option><option value="modern" <?= ($currentSettings['layout_variant'] ?? '') === 'modern' ? 'selected' : '' ?>><?= safeEcho(__t('admin.settings.layout_modern')) ?></option></select></div>
                                    <div class="col-md-6"><label class="form-label">Logo</label><input type="file" name="site_logo" class="form-control" accept="image/*"><?php if (!empty($currentSettings['logo']) && file_exists('../../img/' . $currentSettings['logo'])): ?><img src="/img/<?= safeEcho($currentSettings['logo']) ?>" class="mt-2" style="height:50px;max-width:220px;" alt="Logo"><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label">Favicon</label><input type="file" name="site_favicon" class="form-control" accept="image/*,.ico"><?php if (!empty($currentSettings['favicon']) && file_exists('../../img/' . $currentSettings['favicon'])): ?><img src="/img/<?= safeEcho($currentSettings['favicon']) ?>" class="mt-2" style="height:32px;width:32px;" alt="Favicon"><?php endif; ?></div>
                                    <div class="col-12"><label class="form-label"><?= safeEcho(__t('admin.settings.copyright_text')) ?></label><input name="copyright_text" class="form-control" value="<?= safeEcho($currentSettings['copyright_text'] ?? __t('admin.settings.default_copyright')) ?>"><div class="form-text"><?= safeEcho(__t('admin.settings.copyright_hint')) ?></div></div>
                                    <div class="col-md-3"><label class="form-label">Facebook</label><input type="url" name="facebook_url" class="form-control" value="<?= safeEcho($currentSettings['facebook_url'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">Twitter</label><input type="url" name="twitter_url" class="form-control" value="<?= safeEcho($currentSettings['twitter_url'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">Instagram</label><input type="url" name="instagram_url" class="form-control" value="<?= safeEcho($currentSettings['instagram_url'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">LinkedIn</label><input type="url" name="linkedin_url" class="form-control" value="<?= safeEcho($currentSettings['linkedin_url'] ?? '') ?>"></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="seoTab">
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label">Meta Title</label><input name="meta_title" class="form-control" value="<?= safeEcho($currentSettings['meta_title'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Meta Description</label><textarea name="meta_description" class="form-control" rows="5"><?= safeEcho($currentSettings['meta_description'] ?? '') ?></textarea></div>
                                    <div class="col-md-6"><label class="form-label"><?= safeEcho(__t('admin.settings.meta_keywords')) ?></label><textarea name="meta_keywords" class="form-control" rows="5"><?= safeEcho($currentSettings['meta_keywords'] ?? '') ?></textarea></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="systemTab">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label"><?= safeEcho(__t('admin.settings.language_frontend')) ?></label>
                                        <select name="frontend_default_language" class="form-select">
                                            <?php foreach ($availableLanguages as $language): ?>
                                                <option value="<?= safeEcho($language['code']) ?>" <?= Language::normalize($currentSettings['frontend_default_language'] ?? $currentSettings['default_language'] ?? 'PL_pl') === $language['code'] ? 'selected' : '' ?>>
                                                    <?= safeEcho($language['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label"><?= safeEcho(__t('admin.settings.language_admin')) ?></label>
                                        <select name="admin_default_language" class="form-select">
                                            <?php foreach ($availableLanguages as $language): ?>
                                                <option value="<?= safeEcho($language['code']) ?>" <?= Language::normalize($currentSettings['admin_default_language'] ?? $currentSettings['default_language'] ?? 'PL_pl') === $language['code'] ? 'selected' : '' ?>>
                                                    <?= safeEcho($language['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label class="form-label"><?= safeEcho(__t('admin.settings.max_ads')) ?></label><input type="number" min="1" name="max_ads" class="form-control" value="<?= safeEcho($currentSettings['max_ads'] ?? 10) ?>"></div>
                                    <div class="col-md-3"><label class="form-label"><?= safeEcho(__t('admin.settings.promotion_fee')) ?></label><input type="number" min="0" step="0.01" name="promotion_fee" class="form-control" value="<?= safeEcho($currentSettings['promotion_fee'] ?? 10) ?>"></div>
                                    <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" <?= !empty($currentSettings['maintenance_mode']) ? 'checked' : '' ?>><label class="form-check-label"><?= safeEcho(__t('admin.settings.maintenance_mode')) ?></label></div></div>
                                    <div class="col-12"><label class="form-label"><?= safeEcho(__t('admin.settings.maintenance_message')) ?></label><textarea name="maintenance_message" class="form-control" rows="3"><?= safeEcho($currentSettings['maintenance_message'] ?? '') ?></textarea></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="emailTab">
                                <div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">SMTP server</label><input name="smtp_server" class="form-control" value="<?= safeEcho($currentSettings['smtp_server'] ?? '') ?>"></div>
                                    <div class="col-md-2"><label class="form-label">Port</label><input name="smtp_port" class="form-control" value="<?= safeEcho($currentSettings['smtp_port'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label"><?= safeEcho(__t('admin.settings.smtp_user')) ?></label><input name="smtp_username" class="form-control" value="<?= safeEcho($currentSettings['smtp_username'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label"><?= safeEcho(__t('admin.settings.smtp_password')) ?></label><input type="password" name="smtp_password" class="form-control" value="<?= safeEcho($currentSettings['smtp_password'] ?? '') ?>"></div>
                                    <?php foreach ($emailTemplates as $key => $template): ?><div class="col-12"><label class="form-label"><?= safeEcho(__t('admin.settings.email_template', ['key' => $key])) ?></label><textarea name="email_templates[<?= safeEcho($key) ?>]" class="form-control" rows="3"><?= safeEcho($template) ?></textarea></div><?php endforeach; ?>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="categoriesTab">
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <div class="border rounded p-3">
                                            <h6><?= safeEcho(__t('admin.settings.category')) ?></h6>
                                            <div id="categoryFormExternal">
                                                <input type="hidden" id="categoryFormType" value="add_category">
                                                <input type="hidden" id="categoryId">
                                                <input type="hidden" id="parentCategory">
                                                <label class="form-label"><?= safeEcho(__t('admin.settings.name')) ?></label>
                                                <input type="text" id="categoryName" class="form-control">
                                                <div class="form-text" id="categoryParentHint"><?= safeEcho(__t('admin.settings.adding_root_category')) ?></div>
                                                <div class="d-flex gap-2 mt-2">
                                                    <button type="button" class="btn btn-primary btn-sm" id="submitCategoryForm"><?= safeEcho(__t('admin.settings.add')) ?></button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="resetCategoryForm"><?= safeEcho(__t('admin.common.clear')) ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div id="categoryTree">
                                            <?php foreach ($rootCategories as $category): ?>
                                                <div class="category-node border rounded p-2 mb-2" data-category-id="<?= (int)$category['id'] ?>" data-parent-id="" data-category-name="<?= safeEcho($category['name']) ?>">
                                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary category-toggle" <?= (int)$category['child_count'] > 0 ? '' : 'disabled' ?>><i class="bi <?= (int)$category['child_count'] > 0 ? 'bi-chevron-right' : 'bi-dot' ?>"></i></button>
                                                        <span class="flex-grow-1"><?= safeEcho($category['name']) ?></span>
                                                        <button type="button" class="btn btn-sm btn-outline-primary add-child-category"><i class="bi bi-plus"></i></button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary edit-category"><i class="bi bi-pencil"></i></button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-category" <?= (int)$category['child_count'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button>
                                                    </div>
                                                    <div class="category-children ms-4 mt-2 d-none"></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="maintenanceTab">
                                <div class="row g-3">
                                    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sitemap_enabled" value="1" <?= !isset($currentSettings['sitemap_enabled']) || (int)$currentSettings['sitemap_enabled'] === 1 ? 'checked' : '' ?>><label class="form-check-label"><?= safeEcho(__t('admin.settings.enable_sitemap')) ?></label></div><p class="text-muted mt-2"><?= safeEcho(__t('admin.settings.last')) ?>: <?= safeEcho($currentSettings['sitemap_last_generated'] ?? __t('admin.settings.none')) ?></p></div>
                                    <div class="col-md-6"><button type="submit" name="form_type" value="generate_sitemap" class="btn btn-outline-primary"><?= safeEcho(__t('admin.settings.generate_sitemap')) ?></button></div>
                                    <div class="col-md-6"><button type="submit" name="form_type" value="backup_system" class="btn btn-outline-secondary"><?= safeEcho(__t('admin.settings.system_backup')) ?></button><p class="text-muted mt-2"><?= safeEcho(__t('admin.settings.last')) ?>: <?= safeEcho($currentSettings['last_system_backup'] ?? __t('admin.settings.none')) ?></p><?php if (!empty($currentSettings['last_system_backup'])): ?><a href="?action=download_backup&file=<?= urlencode($currentSettings['last_system_backup']) ?>"><?= safeEcho(__t('admin.settings.download')) ?></a><?php endif; ?></div>
                                    <div class="col-md-6"><button type="submit" name="form_type" value="backup_database" class="btn btn-outline-secondary"><?= safeEcho(__t('admin.settings.database_backup')) ?></button><p class="text-muted mt-2"><?= safeEcho(__t('admin.settings.last')) ?>: <?= safeEcho($currentSettings['last_database_backup'] ?? __t('admin.settings.none')) ?></p><?php if (!empty($currentSettings['last_database_backup'])): ?><a href="?action=download_backup&file=<?= urlencode($currentSettings['last_database_backup']) ?>"><?= safeEcho(__t('admin.settings.download')) ?></a><?php endif; ?></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="logsTab">
                                <div class="row g-3">
                                    <div class="col-md-6"><h6><?= safeEcho(__t('admin.settings.recent_errors')) ?></h6><ul><?php foreach (array_slice($errors, 0, 10) as $error): ?><li><?= safeEcho($error['error_message'] ?? '') ?> - <?= safeEcho($error['timestamp'] ?? '') ?></li><?php endforeach; ?></ul></div>
                                    <div class="col-md-6"><h6><?= safeEcho(__t('admin.settings.recent_transactions')) ?></h6><ul><?php foreach (array_slice($transactions, 0, 10) as $transaction): ?><li><?= safeEcho($transaction['description'] ?? '') ?> - <?= safeEcho($transaction['amount'] ?? '') ?> PLN</li><?php endforeach; ?></ul></div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success mt-3"><?= safeEcho(__t('admin.settings.save_settings')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="categoryActionForm" class="d-none">
    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="form_type" id="categoryActionType">
    <input type="hidden" name="category_id" id="categoryActionId">
    <input type="hidden" name="parent_category" id="categoryActionParent">
    <input type="hidden" name="category_name" id="categoryActionName">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryTree = document.getElementById('categoryTree');
    const actionForm = document.getElementById('categoryActionForm');
    const actionType = document.getElementById('categoryActionType');
    const actionId = document.getElementById('categoryActionId');
    const actionParent = document.getElementById('categoryActionParent');
    const actionName = document.getElementById('categoryActionName');
    const formType = document.getElementById('categoryFormType');
    const categoryId = document.getElementById('categoryId');
    const parentCategory = document.getElementById('parentCategory');
    const categoryName = document.getElementById('categoryName');
    const parentHint = document.getElementById('categoryParentHint');

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    }

    function renderCategory(category) {
        const hasChildren = Number(category.child_count) > 0;
        return `<div class="category-node border rounded p-2 mb-2" data-category-id="${category.id}" data-parent-id="${category.parent_id || ''}" data-category-name="${esc(category.name)}">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary category-toggle" ${hasChildren ? '' : 'disabled'}><i class="bi ${hasChildren ? 'bi-chevron-right' : 'bi-dot'}"></i></button>
                <span class="flex-grow-1">${esc(category.name)}</span>
                <button type="button" class="btn btn-sm btn-outline-primary add-child-category"><i class="bi bi-plus"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary edit-category"><i class="bi bi-pencil"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger delete-category" ${hasChildren ? 'disabled' : ''}><i class="bi bi-trash"></i></button>
            </div>
            <div class="category-children ms-4 mt-2 d-none"></div>
        </div>`;
    }

    async function loadChildren(node) {
        const children = node.querySelector(':scope > .category-children');
        if (children.dataset.loaded === '1') return;
        children.innerHTML = '<div class="text-muted small"><?= safeEcho(__t('admin.settings.loading')) ?></div>';
        const response = await fetch(`site_settings.php?action=category_children&parent_id=${encodeURIComponent(node.dataset.categoryId)}`);
        const data = await response.json();
        children.innerHTML = data.categories.map(renderCategory).join('') || '<div class="text-muted small"><?= safeEcho(__t('admin.settings.no_subcategories')) ?></div>';
        children.dataset.loaded = '1';
    }

    function resetCategoryForm() {
        formType.value = 'add_category';
        categoryId.value = '';
        parentCategory.value = '';
        categoryName.value = '';
        parentHint.textContent = <?= json_encode(__t('admin.settings.adding_root_category'), JSON_UNESCAPED_UNICODE) ?>;
    }

    document.querySelectorAll('.add-field').forEach(button => {
        button.addEventListener('click', function() {
            const box = this.previousElementSibling;
            const name = box.dataset.name;
            box.insertAdjacentHTML('beforeend', `<div class="input-group mb-2"><input name="${name}[]" class="form-control"><button type="button" class="btn btn-outline-danger remove-field"><i class="bi bi-x"></i></button></div>`);
        });
    });

    document.addEventListener('click', function(event) {
        if (event.target.closest('.remove-field')) {
            event.target.closest('.input-group').remove();
        }
    });

    categoryTree.addEventListener('click', async function(event) {
        const node = event.target.closest('.category-node');
        if (!node) return;

        if (event.target.closest('.category-toggle')) {
            const children = node.querySelector(':scope > .category-children');
            await loadChildren(node);
            children.classList.toggle('d-none');
        }

        if (event.target.closest('.add-child-category')) {
            formType.value = 'add_category';
            categoryId.value = '';
            parentCategory.value = node.dataset.categoryId;
            categoryName.value = '';
            parentHint.textContent = <?= json_encode(__t('admin.settings.adding_subcategory', ['name' => '__NAME__']), JSON_UNESCAPED_UNICODE) ?>.replace('__NAME__', node.dataset.categoryName);
            categoryName.focus();
        }

        if (event.target.closest('.edit-category')) {
            formType.value = 'update_category';
            categoryId.value = node.dataset.categoryId;
            parentCategory.value = node.dataset.parentId || '';
            categoryName.value = node.dataset.categoryName;
            parentHint.textContent = <?= json_encode(__t('admin.settings.editing_category'), JSON_UNESCAPED_UNICODE) ?>;
            categoryName.focus();
        }

        if (event.target.closest('.delete-category') && confirm(<?= json_encode(__t('admin.settings.delete_category_confirm'), JSON_UNESCAPED_UNICODE) ?>)) {
            actionType.value = 'delete_category';
            actionId.value = node.dataset.categoryId;
            actionForm.submit();
        }
    });

    document.getElementById('submitCategoryForm').addEventListener('click', function() {
        if (!categoryName.value.trim()) return;
        actionType.value = formType.value;
        actionId.value = categoryId.value;
        actionParent.value = parentCategory.value;
        actionName.value = categoryName.value;
        actionForm.submit();
    });

    document.getElementById('resetCategoryForm').addEventListener('click', resetCategoryForm);
});
</script>

<?php include '../partials/footer.php'; ?>
