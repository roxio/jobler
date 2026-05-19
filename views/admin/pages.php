<?php
session_start();
require_once '../../config/config.php';
require_once '../../models/Page.php';
require_once '../../models/Language.php';
require_once __DIR__ . '/_auth.php';

requireAdminAccess();

$pageModel = new Page();
$availableLanguages = Language::available();

function safeEcho($value, $default = '') {
    return htmlspecialchars((string)($value ?? $default), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage = '';
$editingPage = null;

if (isset($_GET['edit'])) {
    $editingPage = $pageModel->getById((int)$_GET['edit']);
    if (!$editingPage) {
        $errorMessage = 'Nie znaleziono podstrony do edycji.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errorMessage = 'Blad bezpieczenstwa. Odswiez strone i sprobuj ponownie.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            $deleted = $pageModel->delete((int)($_POST['id'] ?? 0));
            header('Location: pages.php?message=' . ($deleted ? 'deleted' : 'error'));
            exit;
        }

        $result = $pageModel->save([
            'id' => $_POST['id'] ?? 0,
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'content' => $_POST['content'] ?? '',
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'status' => $_POST['status'] ?? 'draft',
            'show_in_menu' => isset($_POST['show_in_menu']),
            'show_in_footer' => isset($_POST['show_in_footer']),
            'sort_order' => $_POST['sort_order'] ?? 100,
            'translations' => $_POST['translations'] ?? [],
        ]);

        if (!empty($result['success'])) {
            header('Location: pages.php?message=saved');
            exit;
        }

        $errorMessage = $result['error'] ?? 'Nie udalo sie zapisac podstrony.';
        $editingPage = $_POST;
    }
}

if (isset($_GET['message'])) {
    if ($_GET['message'] === 'saved') {
        $successMessage = 'Podstrona zostala zapisana.';
    }
    if ($_GET['message'] === 'deleted') {
        $successMessage = 'Podstrona zostala usunieta.';
    }
    if ($_GET['message'] === 'error') {
        $errorMessage = 'Nie udalo sie wykonac operacji.';
    }
}

$pages = $pageModel->getAll();
$isEditing = !empty($editingPage['id']);
$pageTranslations = $isEditing ? $pageModel->getTranslations((int)$editingPage['id']) : [];
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
                    <nav class="nav"><?php include 'sidebar.php'; ?></nav>
                </div>

                <div class="card-body">
                    <?php if ($successMessage): ?><div class="alert alert-success"><?= safeEcho($successMessage) ?></div><?php endif; ?>
                    <?php if ($errorMessage): ?><div class="alert alert-danger"><?= safeEcho($errorMessage) ?></div><?php endif; ?>

                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Podstrony</h5>
                                    <?php if ($isEditing): ?>
                                        <a href="pages.php" class="btn btn-sm btn-outline-primary">Dodaj nowa</a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Tytul</th>
                                                    <th>Status</th>
                                                    <th>Widocznosc</th>
                                                    <th class="text-end">Akcje</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pages as $page): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= safeEcho($page['title']) ?></strong><br>
                                                            <small class="text-muted">/page.php?slug=<?= safeEcho($page['slug']) ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?= ($page['status'] ?? '') === 'published' ? 'bg-success' : 'bg-secondary' ?>">
                                                                <?= ($page['status'] ?? '') === 'published' ? 'Opublikowana' : 'Szkic' ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($page['show_in_menu'])): ?><span class="badge bg-primary">Menu</span><?php endif; ?>
                                                            <?php if (!empty($page['show_in_footer'])): ?><span class="badge bg-info">Stopka</span><?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <a href="pages.php?edit=<?= (int)$page['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edytuj">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="<?= safeEcho($pageModel->publicUrl($page)) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Podglad">
                                                                <i class="bi bi-box-arrow-up-right"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($pages)): ?>
                                                    <tr><td colspan="4" class="text-muted text-center py-4">Brak podstron.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi <?= $isEditing ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i>
                                        <?= $isEditing ? 'Edytuj podstrone' : 'Dodaj podstrone' ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="save">
                                        <input type="hidden" name="id" value="<?= safeEcho($editingPage['id'] ?? '') ?>">

                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <label class="form-label">Tytul</label>
                                                <input name="title" class="form-control" value="<?= safeEcho($editingPage['title'] ?? '') ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Kolejnosc</label>
                                                <input type="number" name="sort_order" class="form-control" value="<?= safeEcho($editingPage['sort_order'] ?? 100) ?>">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Adres URL</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">/page.php?slug=</span>
                                                    <input name="slug" class="form-control" value="<?= safeEcho($editingPage['slug'] ?? '') ?>" placeholder="np. regulamin">
                                                </div>
                                                <div class="form-text">Gdy zostawisz puste, adres utworzy sie z tytulu.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select">
                                                    <option value="published" <?= ($editingPage['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Opublikowana</option>
                                                    <option value="draft" <?= ($editingPage['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Szkic</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Tresc</label>
                                                <textarea name="content" class="form-control" rows="12"><?= safeEcho($editingPage['content'] ?? '') ?></textarea>
                                                <div class="form-text">Tresc jest wyswietlana jako zwykly tekst z zachowaniem nowych linii.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"><?= safeEcho(__t('cms.translations')) ?></label>
                                                <ul class="nav nav-tabs" role="tablist">
                                                    <?php $languageIndex = 0; foreach ($availableLanguages as $language): ?>
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link <?= $languageIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#translation-<?= safeEcho($language['code']) ?>" type="button">
                                                                <?= safeEcho($language['name']) ?>
                                                            </button>
                                                        </li>
                                                    <?php $languageIndex++; endforeach; ?>
                                                </ul>
                                                <div class="tab-content border border-top-0 p-3">
                                                    <?php $languageIndex = 0; foreach ($availableLanguages as $language): ?>
                                                        <?php $translation = $pageTranslations[$language['code']] ?? []; ?>
                                                        <div class="tab-pane fade <?= $languageIndex === 0 ? 'show active' : '' ?>" id="translation-<?= safeEcho($language['code']) ?>">
                                                            <div class="row g-3">
                                                                <div class="col-12">
                                                                    <label class="form-label">Tytuł (<?= safeEcho($language['short']) ?>)</label>
                                                                    <input name="translations[<?= safeEcho($language['code']) ?>][title]" class="form-control" value="<?= safeEcho($translation['title'] ?? '') ?>">
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="form-label">Treść (<?= safeEcho($language['short']) ?>)</label>
                                                                    <textarea name="translations[<?= safeEcho($language['code']) ?>][content]" class="form-control" rows="8"><?= safeEcho($translation['content'] ?? '') ?></textarea>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Meta title (<?= safeEcho($language['short']) ?>)</label>
                                                                    <input name="translations[<?= safeEcho($language['code']) ?>][meta_title]" class="form-control" value="<?= safeEcho($translation['meta_title'] ?? '') ?>">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Meta description (<?= safeEcho($language['short']) ?>)</label>
                                                                    <input name="translations[<?= safeEcho($language['code']) ?>][meta_description]" class="form-control" value="<?= safeEcho($translation['meta_description'] ?? '') ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php $languageIndex++; endforeach; ?>
                                                </div>
                                                <div class="form-text">Puste pola tłumaczenia użyją treści domyślnej.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Meta title</label>
                                                <input name="meta_title" class="form-control" value="<?= safeEcho($editingPage['meta_title'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Meta description</label>
                                                <input name="meta_description" class="form-control" value="<?= safeEcho($editingPage['meta_description'] ?? '') ?>">
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="show_in_menu" id="showInMenu" <?= !empty($editingPage['show_in_menu']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="showInMenu">Pokaz link w menu glownym</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="show_in_footer" id="showInFooter" <?= !empty($editingPage['show_in_footer']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="showInFooter">Pokaz link w stopce</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save me-1"></i>Zapisz podstrone
                                            </button>
                                        </div>
                                    </form>

                                    <?php if ($isEditing): ?>
                                        <form method="POST" class="mt-2" onsubmit="return confirm('Usunac te podstrone?');">
                                            <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= safeEcho($editingPage['id']) ?>">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="bi bi-trash me-1"></i>Usun
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
