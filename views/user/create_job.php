<?php
session_start();

include_once('../../models/Database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = Database::getConnection();
$userId = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function ensureJobCreateColumns(PDO $pdo) {
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

function uploadJobImage($file) {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'no_image.jpg';
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

ensureJobCreateColumns($pdo);

if (isset($_GET['action']) && $_GET['action'] === 'category_children') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['categories' => getCategoriesByParent($pdo, $_GET['parent_id'] ?? null)], JSON_UNESCAPED_UNICODE);
    exit;
}

$error = '';
$form = [
    'title' => '',
    'category_id' => '',
    'description' => '',
    'points_required' => 1,
    'budget_estimate' => '',
    'realization_time' => '',
    'validity_days' => 7,
    'work_mode' => 'remote',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['title'] = trim($_POST['title'] ?? '');
    $form['category_id'] = (int)($_POST['category_id'] ?? 0);
    $form['description'] = trim($_POST['description'] ?? '');
    $form['points_required'] = (int)($_POST['points_required'] ?? 1);
    $form['budget_estimate'] = trim($_POST['budget_estimate'] ?? '');
    $form['realization_time'] = trim($_POST['realization_time'] ?? '');
    $form['validity_days'] = (int)($_POST['validity_days'] ?? 7);
    $form['work_mode'] = $_POST['work_mode'] ?? 'remote';

    $allowedValidityDays = [1, 2, 3, 5, 7, 10, 15, 30];
    $allowedWorkModes = ['remote', 'onsite', 'hybrid'];

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.';
    } elseif ($form['title'] === '' || $form['category_id'] <= 0 || $form['description'] === '' || $form['budget_estimate'] === '' || $form['realization_time'] === '') {
        $error = 'Uzupełnij wszystkie wymagane pola.';
    } elseif ($form['points_required'] < 1 || $form['points_required'] > 100) {
        $error = 'Liczba punktów musi być w zakresie od 1 do 100.';
    } elseif ((float)$form['budget_estimate'] < 0) {
        $error = 'Budżet nie może być ujemny.';
    } elseif (!in_array($form['validity_days'], $allowedValidityDays, true)) {
        $error = 'Wybierz poprawny czas ważności ogłoszenia.';
    } elseif (!in_array($form['work_mode'], $allowedWorkModes, true)) {
        $error = 'Wybierz poprawny tryb pracy.';
    } else {
        try {
            $imageFilename = uploadJobImage($_FILES['job_image'] ?? []);

            $stmt = $pdo->prepare("
                INSERT INTO jobs
                    (user_id, title, description, points_required, category_id, budget_estimate,
                     realization_time, validity_days, expires_at, work_mode, primary_image, status, created_at, updated_at)
                VALUES
                    (:user_id, :title, :description, :points_required, :category_id, :budget_estimate,
                     :realization_time, :validity_days, DATE_ADD(NOW(), INTERVAL :validity_days_for_expire DAY),
                     :work_mode, :primary_image, 'open', NOW(), NOW())
            ");

            $created = $stmt->execute([
                'user_id' => $userId,
                'title' => $form['title'],
                'description' => $form['description'],
                'points_required' => $form['points_required'],
                'category_id' => $form['category_id'],
                'budget_estimate' => $form['budget_estimate'],
                'realization_time' => $form['realization_time'],
                'validity_days' => $form['validity_days'],
                'validity_days_for_expire' => $form['validity_days'],
                'work_mode' => $form['work_mode'],
                'primary_image' => $imageFilename,
            ]);

            if (!$created) {
                throw new RuntimeException('Wystąpił błąd podczas dodawania ogłoszenia.');
            }

            $jobId = (int)$pdo->lastInsertId();
            if ($imageFilename !== 'no_image.jpg') {
                $imageStmt = $pdo->prepare("INSERT INTO job_images (job_id, filename, created_at) VALUES (:job_id, :filename, NOW())");
                $imageStmt->execute(['job_id' => $jobId, 'filename' => $imageFilename]);
            }

            header('Location: dashboard.php?status=job_created');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$categories = getCategoriesByParent($pdo, null);
$validityOptions = [1, 2, 3, 5, 7, 10, 15, 30];

include('../partials/header.php');
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Dodaj nowe ogłoszenie</h1>
            <p class="text-muted mb-0">Opisz zlecenie tak, żeby wykonawca mógł szybko ocenić zakres, budżet i termin.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Wróć do kokpitu</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="create_job.php" method="POST" enctype="multipart/form-data" class="row g-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0">Treść ogłoszenia</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Tytuł ogłoszenia</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($form['title']) ?>" maxlength="255" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kategoria</label>
                        <div id="categoryCascade" class="vstack gap-2">
                            <select class="form-select category-level" data-level="0" required>
                                <option value="">Wybierz kategorię główną</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int)$category['id'] ?>" data-has-children="<?= (int)$category['child_count'] > 0 ? '1' : '0' ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" id="category_id" name="category_id" value="<?= (int)$form['category_id'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Treść ogłoszenia</label>
                        <textarea class="form-control" id="description" name="description" rows="8" required><?= htmlspecialchars($form['description']) ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="budget_estimate" class="form-label">Orientacyjny budżet</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="budget_estimate" name="budget_estimate" min="0" step="0.01" value="<?= htmlspecialchars($form['budget_estimate']) ?>" required>
                                <span class="input-group-text">PLN</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="realization_time" class="form-label">Czas na realizację zlecenia</label>
                            <input type="text" class="form-control" id="realization_time" name="realization_time" value="<?= htmlspecialchars($form['realization_time']) ?>" placeholder="np. 7 dni, do końca miesiąca" maxlength="120" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Warunki zgłoszenia</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="points_required" class="form-label">Wymagane punkty od wykonawcy</label>
                        <input type="number" class="form-control" id="points_required" name="points_required" min="1" max="100" value="<?= (int)$form['points_required'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="validity_days" class="form-label">Czas ważności ogłoszenia</label>
                        <select class="form-select" id="validity_days" name="validity_days" required>
                            <?php foreach ($validityOptions as $days): ?>
                                <option value="<?= $days ?>" <?= (int)$form['validity_days'] === $days ? 'selected' : '' ?>><?= $days ?> <?= $days === 1 ? 'dzień' : 'dni' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tryb pracy</label>
                        <div class="list-group">
                            <label class="list-group-item">
                                <input class="form-check-input me-2" type="radio" name="work_mode" value="remote" <?= $form['work_mode'] === 'remote' ? 'checked' : '' ?>>
                                Praca zdalna
                            </label>
                            <label class="list-group-item">
                                <input class="form-check-input me-2" type="radio" name="work_mode" value="onsite" <?= $form['work_mode'] === 'onsite' ? 'checked' : '' ?>>
                                Praca stacjonarna
                            </label>
                            <label class="list-group-item">
                                <input class="form-check-input me-2" type="radio" name="work_mode" value="hybrid" <?= $form['work_mode'] === 'hybrid' ? 'checked' : '' ?>>
                                Hybrydowo
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0">Zdjęcie</h2>
                </div>
                <div class="card-body">
                    <label for="job_image" class="form-label">Opcjonalne zdjęcie ogłoszenia</label>
                    <input type="file" class="form-control" id="job_image" name="job_image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">Brak zdjęcia oznacza użycie domyślnego obrazu no_image.jpg.</div>
                    <div id="imagePreview" class="mt-3 d-none">
                        <img src="" alt="Podgląd zdjęcia" class="img-fluid rounded border">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 mt-4">Dodaj ogłoszenie</button>
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
        const response = await fetch(`create_job.php?action=category_children&parent_id=${encodeURIComponent(parentId)}`);
        const data = await response.json();

        if (!data.categories || data.categories.length === 0) {
            return;
        }

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

    document.getElementById('job_image').addEventListener('change', function() {
        const preview = document.getElementById('imagePreview');
        const image = preview.querySelector('img');
        const file = this.files && this.files[0];

        if (!file) {
            preview.classList.add('d-none');
            image.src = '';
            return;
        }

        image.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
    });
});
</script>

<?php include('../partials/footer.php'); ?>
