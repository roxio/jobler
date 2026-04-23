<?php
session_start();
include_once('../../models/Job.php');
include_once('../../models/User.php');
include_once('../../models/Database.php');

// Sprawdź uprawnienia
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit('Brak uprawnień.');
}

$pdo      = Database::getConnection();
$jobModel = new Job();
$userModel = new User();

// Pobierz kategorie i użytkowników
$categories = $jobModel->getCategories();
$allUsers   = $userModel->getAllUsers();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage   = '';

// Wartości formularza (do ponownego wypełnienia po błędzie)
$form = [
    'title'           => '',
    'description'     => '',
    'points_required' => 1,
    'status'          => 'open',
    'category_id'     => '',
    'user_id'         => $_SESSION['user_id'], // domyślnie zalogowany admin
];

// ===== Obsługa POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = 'Błąd bezpieczeństwa CSRF.';
    } else {

        $form['title']           = trim($_POST['title']           ?? '');
        $form['description']     = trim($_POST['description']     ?? '');
        $form['points_required'] = (int)($_POST['points_required'] ?? 1);
        $form['status']          = $_POST['status']               ?? 'open';
        $form['category_id']     = (int)($_POST['category_id']    ?? 0);
        $form['user_id']         = (int)($_POST['user_id']        ?? $_SESSION['user_id']);

        // Walidacja
        if (empty($form['title'])) {
            $errorMessage = 'Tytuł nie może być pusty.';
        } elseif (empty($form['description'])) {
            $errorMessage = 'Opis nie może być pusty.';
        } elseif ($form['points_required'] < 1 || $form['points_required'] > 100) {
            $errorMessage = 'Punkty muszą być w zakresie 1–100.';
        } elseif (!in_array($form['status'], ['open', 'active', 'closed', 'inactive'])) {
            $errorMessage = 'Nieprawidłowy status.';
        } elseif (empty($form['user_id'])) {
            $errorMessage = 'Musisz wybrać właściciela zlecenia.';
        } else {

            // Wstaw zlecenie do bazy
            $sql = "INSERT INTO jobs
                        (user_id, title, description, points_required, category_id, status, created_at, updated_at)
                    VALUES
                        (:user_id, :title, :description, :points, :category_id, :status, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'     => $form['user_id'],
                ':title'       => $form['title'],
                ':description' => $form['description'],
                ':points'      => $form['points_required'],
                ':category_id' => $form['category_id'] ?: null,
                ':status'      => $form['status'],
            ]);
            $newJobId = (int)$pdo->lastInsertId();

            // Obsługa zdjęć
            $uploadedImages = [];
            if (!empty($_FILES['new_images']['name'][0])) {
                $uploadDir = '../../uploads/jobs/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                foreach ($_FILES['new_images']['tmp_name'] as $i => $tmpName) {
                    if ($_FILES['new_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $origName = $_FILES['new_images']['name'][$i];
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;
                    if ($_FILES['new_images']['size'][$i] > 5 * 1024 * 1024) continue;

                    $filename = uniqid('job_') . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        try {
                            $pdo->prepare(
                                "INSERT INTO job_images (job_id, filename, created_at) VALUES (?, ?, NOW())"
                            )->execute([$newJobId, $filename]);
                            $uploadedImages[] = $filename;
                        } catch (PDOException $e) { /* ignoruj */ }
                    }
                }
            }

            // Zapisz do historii zmian — zdarzenie "utworzenie"
            $histDesc = "Zlecenie utworzone przez administratora. Status: {$form['status']}, Punkty: {$form['points_required']}";
            if (!empty($uploadedImages)) {
                $histDesc .= '. Dodano ' . count($uploadedImages) . ' zdjęcie(a).';
            }
            try {
                $pdo->prepare(
                    "INSERT INTO job_change_history (job_id, admin_id, change_description, changed_at)
                     VALUES (?, ?, ?, NOW())"
                )->execute([$newJobId, $_SESSION['user_id'], $histDesc]);
            } catch (PDOException $e) { /* ignoruj */ }

            // Przekieruj do edycji nowo utworzonego zlecenia
            header("Location: edit_job.php?id={$newJobId}&created=1");
            exit;
        }
    }
}

function safeEcho($v, $d = '') { return htmlspecialchars($v ?? $d, ENT_QUOTES, 'UTF-8'); }
function sel($a, $b) { return (string)$a === (string)$b ? 'selected' : ''; }

$statusLabels = [
    'open'     => 'Otwarte',
    'active'   => 'Aktywne',
    'closed'   => 'Zamknięte',
    'inactive' => 'Nieaktywne',
];
?>
<?php include '../partials/header.php'; ?>

<style>
/* ===== Add Job — Admin Panel Styles (wspólne z edit_job) ===== */
.edit-job-wrap { max-width: 1100px; margin: 0 auto; }

.section-card {
    background: #fff;
    border: 1px solid #e3e6f0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(78,115,223,.07);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.section-card .section-head {
    background: linear-gradient(90deg, #4e73df 0%, #36b9cc 100%);
    color: #fff;
    padding: .75rem 1.25rem;
    font-weight: 600;
    font-size: .95rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.section-card .section-body { padding: 1.25rem; }

.upload-zone {
    border: 2px dashed #c5cbe4;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
}
.upload-zone:hover { border-color: #4e73df; background: #f0f4ff; }

.img-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: .75rem;
}
.img-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #4e73df;
}
.img-item img { width: 100%; height: 110px; object-fit: cover; display: block; }
.img-item .img-name {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: rgba(78,115,223,.8);
    color: #fff; font-size: .7rem;
    padding: 2px 4px; text-align: center;
    overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
}

.sticky-bar {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 1px solid #e3e6f0;
    padding: .75rem 1.25rem;
    z-index: 100;
    box-shadow: 0 -4px 12px rgba(0,0,0,.07);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 0 0 12px 12px;
    margin-top: 1.5rem;
}

/* Wskazówki */
.tip-box {
    background: #eef2ff;
    border-left: 3px solid #4e73df;
    border-radius: 0 8px 8px 0;
    padding: .65rem 1rem;
    font-size: .82rem;
    color: #3d4e8a;
    margin-top: .5rem;
}
</style>

<div class="container-fluid">
<div class="row">
<div class="col-12 main-content">

<div class="card shadow">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
    <nav class="nav"><?php include 'sidebar.php'; ?></nav>
  </div>

  <div class="card-body">

    <!-- Nagłówek -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <a href="manage_jobs.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Powrót do listy
        </a>
        <span class="ms-2 text-muted fw-semibold">
          <i class="bi bi-plus-circle text-success"></i> Nowe zlecenie
        </span>
      </div>
    </div>

    <!-- Alerty -->
    <?php if ($errorMessage): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= safeEcho($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- ===== FORMULARZ ===== -->
    <form method="POST" enctype="multipart/form-data" id="addJobForm">
      <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">

      <div class="edit-job-wrap">
      <div class="row g-4">

        <!-- LEWA KOLUMNA -->
        <div class="col-lg-8">

          <!-- 1. Podstawowe informacje -->
          <div class="section-card">
            <div class="section-head"><i class="bi bi-file-text"></i> Podstawowe informacje</div>
            <div class="section-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Tytuł zlecenia <span class="text-danger">*</span>
                </label>
                <input type="text" name="title" class="form-control form-control-lg"
                       value="<?= safeEcho($form['title']) ?>"
                       placeholder="Np. Potrzebuję pomocy przy remoncie mieszkania"
                       required maxlength="255" autofocus>
                <div class="tip-box mt-2">
                  <i class="bi bi-lightbulb"></i>
                  Dobry tytuł jasno opisuje czego szuka zleceniodawca. Maks. 255 znaków.
                </div>
              </div>
              <div class="mb-0">
                <label class="form-label fw-semibold">
                  Opis zlecenia <span class="text-danger">*</span>
                </label>
                <textarea name="description" class="form-control" rows="10"
                          required style="resize:vertical"
                          placeholder="Opisz szczegółowo zakres pracy, wymagania, termin realizacji..."><?= safeEcho($form['description']) ?></textarea>
                <div class="d-flex justify-content-end mt-1">
                  <small class="text-muted" id="descCounter">0 znaków</small>
                </div>
              </div>
            </div>
          </div>

          <!-- 2. Zdjęcia -->
          <div class="section-card">
            <div class="section-head"><i class="bi bi-images"></i> Zdjęcia zlecenia</div>
            <div class="section-body">
              <label class="upload-zone d-block" for="new_images">
                <i class="bi bi-cloud-upload fs-2 text-primary"></i>
                <div class="mt-1 fw-semibold">Kliknij lub przeciągnij pliki tutaj</div>
                <div class="text-muted small">JPG, PNG, WEBP, GIF · max 5 MB · wiele plików jednocześnie</div>
                <input type="file" id="new_images" name="new_images[]"
                       class="d-none" multiple accept="image/*"
                       onchange="previewNewImages(this)">
              </label>
              <!-- Podgląd przed uploadem -->
              <div id="newImgPreview" class="img-grid mt-3"></div>
              <div id="imgCount" class="text-muted small mt-2" style="display:none"></div>
            </div>
          </div>

        </div><!-- /lewa -->

        <!-- PRAWA KOLUMNA -->
        <div class="col-lg-4">

          <!-- 3. Parametry -->
          <div class="section-card">
            <div class="section-head"><i class="bi bi-sliders"></i> Parametry zlecenia</div>
            <div class="section-body">

              <div class="mb-3">
                <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select">
                  <?php foreach ($statusLabels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= sel($form['status'], $val) ?>>
                      <?= $label ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="tip-box">
                  <i class="bi bi-info-circle"></i>
                  <strong>Otwarte</strong> — widoczne dla wykonawców;<br>
                  <strong>Nieaktywne</strong> — ukryte, do późniejszej publikacji.
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Punkty wymagane od wykonawcy <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <input type="number" name="points_required" class="form-control"
                         value="<?= (int)$form['points_required'] ?>"
                         min="1" max="100" required>
                  <span class="input-group-text">pkt</span>
                </div>
                <div class="form-text">Zakres: 1–100</div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Kategoria</label>
                <select name="category_id" class="form-select">
                  <option value="">— Brak kategorii —</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"
                            <?= sel($form['category_id'], $cat['id']) ?>>
                      <?= safeEcho($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-0">
                <label class="form-label fw-semibold">
                  Właściciel zlecenia <span class="text-danger">*</span>
                </label>
                <select name="user_id" class="form-select">
                  <option value="">— Wybierz użytkownika —</option>
                  <?php foreach ($allUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"
                            <?= sel($form['user_id'], $u['id']) ?>>
                      #<?= $u['id'] ?> — <?= safeEcho($u['name']) ?>
                      (<?= safeEcho($u['role']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="tip-box mt-2">
                  <i class="bi bi-person-check"></i>
                  Zlecenie zostanie przypisane do wybranego użytkownika. Domyślnie wybrany jest zalogowany administrator.
                </div>
              </div>

            </div>
          </div>

          <!-- 4. Podsumowanie (dynamiczne) -->
          <div class="section-card">
            <div class="section-head"><i class="bi bi-check2-square"></i> Podsumowanie</div>
            <div class="section-body p-0">
              <table class="table table-sm mb-0">
                <tr>
                  <td class="text-muted ps-3">Status</td>
                  <td class="pe-3 fw-bold" id="summStatus">Otwarte</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Punkty</td>
                  <td class="pe-3 fw-bold" id="summPoints">1 pkt</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Kategoria</td>
                  <td class="pe-3" id="summCategory">— brak —</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Właściciel</td>
                  <td class="pe-3" id="summOwner">—</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Zdjęcia</td>
                  <td class="pe-3" id="summImages">0</td>
                </tr>
              </table>
            </div>
          </div>

        </div><!-- /prawa -->

      </div><!-- /row -->

      <!-- Sticky bar -->
      <div class="sticky-bar">
        <a href="manage_jobs.php" class="btn btn-outline-secondary">
          <i class="bi bi-x-circle"></i> Anuluj
        </a>
        <button type="submit" class="btn btn-success px-4" form="addJobForm">
          <i class="bi bi-plus-circle-fill me-1"></i> Utwórz zlecenie
        </button>
      </div>

      </div><!-- /edit-job-wrap -->
    </form>

  </div><!-- /card-body -->
  <div class="container mb-3">
    <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
  </div>
</div><!-- /card -->
</div>
</div>
</div>

<script>
// ===== Podgląd zdjęć =====
let selectedFilesCount = 0;

function previewNewImages(input) {
    const container = document.getElementById('newImgPreview');
    const countEl   = document.getElementById('imgCount');
    container.innerHTML = '';
    selectedFilesCount = input.files.length;

    if (!input.files.length) {
        countEl.style.display = 'none';
        updateSummary();
        return;
    }

    countEl.textContent = `Wybrano ${input.files.length} plik(ów) do przesłania`;
    countEl.style.display = 'block';

    Array.from(input.files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'img-item';
            div.innerHTML = `
                <img src="${e.target.result}" alt="${file.name}">
                <div class="img-name">${file.name}</div>`;
            container.appendChild(div);
        };
        reader.readAsDataURL(file);
    });

    updateSummary();
}

// ===== Licznik znaków opisu =====
const descArea = document.querySelector('textarea[name="description"]');
const descCounter = document.getElementById('descCounter');
if (descArea && descCounter) {
    const update = () => { descCounter.textContent = descArea.value.length + ' znaków'; };
    descArea.addEventListener('input', update);
    update();
}

// ===== Dynamiczne podsumowanie =====
const statusLabels = {
    open: 'Otwarte', active: 'Aktywne', closed: 'Zamknięte', inactive: 'Nieaktywne'
};

function updateSummary() {
    const status   = document.querySelector('select[name="status"]');
    const points   = document.querySelector('input[name="points_required"]');
    const category = document.querySelector('select[name="category_id"]');
    const owner    = document.querySelector('select[name="user_id"]');

    if (status)   document.getElementById('summStatus').textContent =
        statusLabels[status.value] || status.value;

    if (points)   document.getElementById('summPoints').textContent =
        (points.value || 1) + ' pkt';

    if (category) {
        const opt = category.options[category.selectedIndex];
        document.getElementById('summCategory').textContent =
            (category.value ? opt.text : '— brak —');
    }

    if (owner) {
        const opt = owner.options[owner.selectedIndex];
        document.getElementById('summOwner').textContent =
            (owner.value ? opt.text : '—');
    }

    document.getElementById('summImages').textContent = selectedFilesCount;
}

document.querySelector('select[name="status"]')?.addEventListener('change', updateSummary);
document.querySelector('input[name="points_required"]')?.addEventListener('input', updateSummary);
document.querySelector('select[name="category_id"]')?.addEventListener('change', updateSummary);
document.querySelector('select[name="user_id"]')?.addEventListener('change', updateSummary);

// Inicjalne podsumowanie
updateSummary();

// ===== Drag & Drop =====
const uploadZone = document.querySelector('.upload-zone');
if (uploadZone) {
    uploadZone.addEventListener('dragover', e => {
        e.preventDefault();
        uploadZone.style.borderColor = '#4e73df';
        uploadZone.style.background  = '#f0f4ff';
    });
    uploadZone.addEventListener('dragleave', () => {
        uploadZone.style.borderColor = '';
        uploadZone.style.background  = '';
    });
    uploadZone.addEventListener('drop', e => {
        e.preventDefault();
        uploadZone.style.borderColor = '';
        uploadZone.style.background  = '';
        const input = document.getElementById('new_images');
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
        input.files = dt.files;
        previewNewImages(input);
    });
}

// ===== Auto-zamknięcie alertów =====
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        try { new bootstrap.Alert(a).close(); } catch(e) {}
    });
}, 5000);
</script>

<?php include '../partials/footer.php'; ?>
