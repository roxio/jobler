<?php
session_start();

include_once('../../models/Database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = Database::getConnection();
$userId = (int)$_SESSION['user_id'];
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($jobId <= 0) {
    header('Location: /views/user/job_list.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function ensureEditJobColumns(PDO $pdo) {
    $columns = [
        'budget_estimate' => "ALTER TABLE jobs ADD COLUMN budget_estimate DECIMAL(10,2) DEFAULT NULL",
        'realization_time' => "ALTER TABLE jobs ADD COLUMN realization_time VARCHAR(120) DEFAULT NULL",
        'validity_days' => "ALTER TABLE jobs ADD COLUMN validity_days INT(11) NOT NULL DEFAULT 7",
        'expires_at' => "ALTER TABLE jobs ADD COLUMN expires_at DATETIME DEFAULT NULL",
        'work_mode' => "ALTER TABLE jobs ADD COLUMN work_mode VARCHAR(20) NOT NULL DEFAULT 'remote'",
        'primary_image' => "ALTER TABLE jobs ADD COLUMN primary_image VARCHAR(255) NOT NULL DEFAULT 'no_image.jpg'",
    ];

    $stmt = $pdo->query("SHOW COLUMNS FROM jobs");
    $existingColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

    foreach ($columns as $column => $sql) {
        if (!in_array($column, $existingColumns, true)) {
            $pdo->exec($sql);
        }
    }
}

function getCategoriesByParent(PDO $pdo, $parentId = null) {
    if ($parentId === null || $parentId === '') {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.parent_id,
                   (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) AS child_count
            FROM categories c
            WHERE c.parent_id IS NULL
            ORDER BY c.name ASC, c.id ASC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.parent_id,
                   (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) AS child_count
            FROM categories c
            WHERE c.parent_id = :parent_id
            ORDER BY c.name ASC, c.id ASC
        ");
        $stmt->bindValue(':parent_id', (int)$parentId, PDO::PARAM_INT);
        $stmt->execute();
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategoryPath(PDO $pdo, $categoryId) {
    $path = [];
    $currentId = (int)$categoryId;

    while ($currentId > 0) {
        $stmt = $pdo->prepare("SELECT id, parent_id FROM categories WHERE id = :id");
        $stmt->execute(['id' => $currentId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            break;
        }

        array_unshift($path, (int)$category['id']);
        $currentId = (int)($category['parent_id'] ?? 0);
    }

    return $path;
}

function uploadEditedJobImage($file) {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nie udało się wgrać zdjęcia.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Zdjęcie może mieć maksymalnie 5 MB.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Dozwolone formaty zdjęć: JPG, PNG, GIF, WEBP.');
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads/jobs';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = 'job_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        throw new RuntimeException('Nie udało się zapisać zdjęcia.');
    }

    return $filename;
}

function jobImageUrl($filename) {
    $filename = trim((string)$filename);
    if ($filename === '') {
        $filename = 'no_image.jpg';
    }

    if (strpos($filename, '/') === 0) {
        return $filename;
    }

    return '/uploads/jobs/' . rawurlencode($filename);
}

ensureEditJobColumns($pdo);

if (isset($_GET['action']) && $_GET['action'] === 'category_children') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['categories' => getCategoriesByParent($pdo, $_GET['parent_id'] ?? null)], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = :job_id AND user_id = :user_id AND deleted_at IS NULL AND archived_at IS NULL LIMIT 1");
$stmt->execute(['job_id' => $jobId, 'user_id' => $userId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: /views/user/job_list.php');
    exit;
}

$error = '';
$allowedValidityDays = [1, 2, 3, 5, 7, 10, 15, 30];
$allowedWorkModes = ['remote', 'onsite', 'hybrid'];
$allowedStatuses = ['open', 'in_progress', 'closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $pointsRequired = (int)($_POST['points_required'] ?? 1);
    $budgetEstimate = trim($_POST['budget_estimate'] ?? '');
    $realizationTime = trim($_POST['realization_time'] ?? '');
    $validityDays = (int)($_POST['validity_days'] ?? 7);
    $workMode = $_POST['work_mode'] ?? 'remote';
    $status = $_POST['status'] ?? 'open';
    $primaryImage = $job['primary_image'] ?: 'no_image.jpg';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.';
    } elseif ($title === '' || $categoryId <= 0 || $description === '' || $budgetEstimate === '' || $realizationTime === '') {
        $error = 'Uzupełnij wszystkie wymagane pola.';
    } elseif ($pointsRequired < 1 || $pointsRequired > 100) {
        $error = 'Liczba punktów musi być w zakresie od 1 do 100.';
    } elseif ((float)$budgetEstimate < 0) {
        $error = 'Budżet nie może być ujemny.';
    } elseif (!in_array($validityDays, $allowedValidityDays, true)) {
        $error = 'Wybierz poprawny czas ważności ogłoszenia.';
    } elseif (!in_array($workMode, $allowedWorkModes, true)) {
        $error = 'Wybierz poprawny tryb pracy.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = 'Wybierz poprawny status.';
    } else {
        try {
            if (!empty($_POST['remove_image'])) {
                if ($primaryImage !== 'no_image.jpg') {
                    $oldPath = dirname(__DIR__, 2) . '/uploads/jobs/' . $primaryImage;
                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $primaryImage = 'no_image.jpg';
                $pdo->prepare("DELETE FROM job_images WHERE job_id = :job_id")->execute(['job_id' => $jobId]);
            }

            $newImage = uploadEditedJobImage($_FILES['job_image'] ?? []);
            if ($newImage !== null) {
                if ($primaryImage !== 'no_image.jpg') {
                    $oldPath = dirname(__DIR__, 2) . '/uploads/jobs/' . $primaryImage;
                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $primaryImage = $newImage;
                $pdo->prepare("INSERT INTO job_images (job_id, filename, created_at) VALUES (:job_id, :filename, NOW())")
                    ->execute(['job_id' => $jobId, 'filename' => $newImage]);
            }

            $update = $pdo->prepare("
                UPDATE jobs
                SET title = :title,
                    description = :description,
                    points_required = :points_required,
                    category_id = :category_id,
                    budget_estimate = :budget_estimate,
                    realization_time = :realization_time,
                    validity_days = :validity_days,
                    expires_at = DATE_ADD(NOW(), INTERVAL :validity_days_for_expire DAY),
                    work_mode = :work_mode,
                    primary_image = :primary_image,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :job_id AND user_id = :user_id
            ");

            $update->execute([
                'title' => $title,
                'description' => $description,
                'points_required' => $pointsRequired,
                'category_id' => $categoryId,
                'budget_estimate' => $budgetEstimate,
                'realization_time' => $realizationTime,
                'validity_days' => $validityDays,
                'validity_days_for_expire' => $validityDays,
                'work_mode' => $workMode,
                'primary_image' => $primaryImage,
                'status' => $status,
                'job_id' => $jobId,
                'user_id' => $userId,
            ]);

            header('Location: /views/user/job_list.php?status=updated');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = :job_id AND user_id = :user_id AND deleted_at IS NULL AND archived_at IS NULL LIMIT 1");
$stmt->execute(['job_id' => $jobId, 'user_id' => $userId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categoryPath = getCategoryPath($pdo, $job['category_id'] ?? 0);
$rootCategories = getCategoriesByParent($pdo, null);
$validityOptions = [1, 2, 3, 5, 7, 10, 15, 30];

include('../partials/header.php');
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edytuj ogłoszenie</h1>
            <p class="text-muted mb-0">Zmień zakres, budżet, kategorię i warunki widoczne dla wykonawców.</p>
        </div>
        <a href="job_list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Wróć do listy</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h2 class="h5 mb-0">Treść ogłoszenia</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Tytuł ogłoszenia</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($job['title']) ?>" maxlength="255" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kategoria</label>
                        <div id="categoryCascade" class="vstack gap-2">
                            <?php
                            $levels = [];
                            $parentId = null;
                            $pathCount = count($categoryPath);
                            for ($level = 0; $level <= max(0, $pathCount - 1); $level++) {
                                $options = getCategoriesByParent($pdo, $parentId);
                                $selectedId = $categoryPath[$level] ?? null;
                                $levels[] = ['level' => $level, 'options' => $options, 'selected' => $selectedId];
                                $parentId = $selectedId;
                                if ($selectedId === null) {
                                    break;
                                }
                            }
                            if (empty($levels)) {
                                $levels[] = ['level' => 0, 'options' => $rootCategories, 'selected' => null];
                            }
                            ?>
                            <?php foreach ($levels as $levelData): ?>
                                <select class="form-select category-level" data-level="<?= (int)$levelData['level'] ?>" <?= (int)$levelData['level'] === 0 ? 'required' : '' ?>>
                                    <option value=""><?= (int)$levelData['level'] === 0 ? 'Wybierz kategorię główną' : 'Wybierz podkategorię' ?></option>
                                    <?php foreach ($levelData['options'] as $category): ?>
                                        <option value="<?= (int)$category['id'] ?>" data-has-children="<?= (int)$category['child_count'] > 0 ? '1' : '0' ?>" <?= (int)$levelData['selected'] === (int)$category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="category_id" name="category_id" value="<?= (int)($job['category_id'] ?? 0) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Treść ogłoszenia</label>
                        <textarea class="form-control" id="description" name="description" rows="8" required><?= htmlspecialchars($job['description']) ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="budget_estimate" class="form-label">Orientacyjny budżet</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="budget_estimate" name="budget_estimate" min="0" step="0.01" value="<?= htmlspecialchars($job['budget_estimate'] ?? '') ?>" required>
                                <span class="input-group-text">PLN</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="realization_time" class="form-label">Czas na realizację zlecenia</label>
                            <input type="text" class="form-control" id="realization_time" name="realization_time" value="<?= htmlspecialchars($job['realization_time'] ?? '') ?>" maxlength="120" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h2 class="h5 mb-0">Warunki i status</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="open" <?= $job['status'] === 'open' ? 'selected' : '' ?>>Otwarte</option>
                            <option value="in_progress" <?= $job['status'] === 'in_progress' ? 'selected' : '' ?>>W realizacji</option>
                            <option value="closed" <?= $job['status'] === 'closed' ? 'selected' : '' ?>>Zamknięte</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="points_required" class="form-label">Wymagane punkty od wykonawcy</label>
                        <input type="number" class="form-control" id="points_required" name="points_required" min="1" max="100" value="<?= (int)$job['points_required'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="validity_days" class="form-label">Czas ważności ogłoszenia</label>
                        <select class="form-select" id="validity_days" name="validity_days" required>
                            <?php foreach ($validityOptions as $days): ?>
                                <option value="<?= $days ?>" <?= (int)($job['validity_days'] ?? 7) === $days ? 'selected' : '' ?>><?= $days ?> <?= $days === 1 ? 'dzień' : 'dni' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tryb pracy</label>
                        <?php $workMode = $job['work_mode'] ?? 'remote'; ?>
                        <div class="list-group">
                            <label class="list-group-item"><input class="form-check-input me-2" type="radio" name="work_mode" value="remote" <?= $workMode === 'remote' ? 'checked' : '' ?>>Praca zdalna</label>
                            <label class="list-group-item"><input class="form-check-input me-2" type="radio" name="work_mode" value="onsite" <?= $workMode === 'onsite' ? 'checked' : '' ?>>Praca stacjonarna</label>
                            <label class="list-group-item"><input class="form-check-input me-2" type="radio" name="work_mode" value="hybrid" <?= $workMode === 'hybrid' ? 'checked' : '' ?>>Hybrydowo</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="h5 mb-0">Zdjęcie</h2></div>
                <div class="card-body">
                    <img src="<?= htmlspecialchars(jobImageUrl($job['primary_image'] ?? 'no_image.jpg')) ?>" alt="Zdjęcie ogłoszenia" class="img-fluid rounded border mb-3" style="aspect-ratio: 4 / 3; object-fit: cover; width: 100%;">
                    <label for="job_image" class="form-label">Podmień zdjęcie</label>
                    <input type="file" class="form-control" id="job_image" name="job_image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if (($job['primary_image'] ?? 'no_image.jpg') !== 'no_image.jpg'): ?>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
                            <label class="form-check-label" for="remove_image">Usuń aktualne zdjęcie i użyj no_image.jpg</label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-4">Zapisz zmiany</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cascade = document.getElementById('categoryCascade');
    const categoryInput = document.getElementById('category_id');

    function removeLowerLevels(level) {
        cascade.querySelectorAll('.category-level').forEach(select => {
            if (Number(select.dataset.level) > level) {
                select.remove();
            }
        });
    }

    async function loadChildren(parentId, level) {
        const response = await fetch(`edit_job.php?id=<?= (int)$jobId ?>&action=category_children&parent_id=${encodeURIComponent(parentId)}`);
        const data = await response.json();
        if (!data.categories || data.categories.length === 0) return;

        const select = document.createElement('select');
        select.className = 'form-select category-level';
        select.dataset.level = String(level);
        select.innerHTML = '<option value="">Wybierz podkategorię</option>';

        data.categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            option.dataset.hasChildren = Number(category.child_count) > 0 ? '1' : '0';
            select.appendChild(option);
        });

        cascade.appendChild(select);
    }

    cascade.addEventListener('change', async function(event) {
        const select = event.target.closest('.category-level');
        if (!select) return;

        const level = Number(select.dataset.level);
        const selected = select.options[select.selectedIndex];
        const categoryId = select.value;
        removeLowerLevels(level);
        categoryInput.value = categoryId;

        if (categoryId && selected.dataset.hasChildren === '1') {
            await loadChildren(categoryId, level + 1);
        }
    });
});
</script>

<?php include('../partials/footer.php'); ?>
