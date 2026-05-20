<?php
session_start();
include_once('../../models/Job.php');
include_once('../../models/User.php');
include_once('../../models/Database.php');
include_once('../../models/Language.php');


require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_jobs.php?status=error');
    exit;
}

$jobId = (int)$_GET['id'];
$pdo   = Database::getConnection();

$jobModel  = new Job();
$userModel = new User();


$job = $jobModel->getJobDetails($jobId);
if (!$job) {
    header('Location: manage_jobs.php?status=error&message=not_found');
    exit;
}


$categories = $jobModel->getCategories();
$allUsers   = $userModel->getAllUsers();


$jobImages = [];
try {
    $imgStmt = $pdo->prepare("SELECT * FROM job_images WHERE job_id = ? ORDER BY created_at ASC");
    $imgStmt->execute([$jobId]);
    $jobImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

}


$changeHistory = [];
try {
    $histStmt = $pdo->prepare(
        "SELECT jch.*, u.name AS admin_name
         FROM job_change_history jch
         LEFT JOIN users u ON jch.admin_id = u.id
         WHERE jch.job_id = ?
         ORDER BY jch.changed_at DESC
         LIMIT 20"
    );
    $histStmt->execute([$jobId]);
    $changeHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage   = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = __t('admin.edit_job.csrf_error');
    } else {
        $changes = [];

        $newTitle    = trim($_POST['title']          ?? '');
        $newDesc     = trim($_POST['description']    ?? '');
        $newPoints   = (int)($_POST['points_required'] ?? 1);
        $newStatus   = $_POST['status']              ?? $job['status'];
        $newCategory = (int)($_POST['category_id']  ?? 0);
        $newOwner    = (int)($_POST['user_id']       ?? $job['user_id']);


        if (empty($newTitle)) {
            $errorMessage = __t('admin.edit_job.title_required');
        } elseif ($newPoints < 1 || $newPoints > 100) {
            $errorMessage = __t('admin.edit_job.points_invalid');
        } elseif (!in_array($newStatus, ['open','active','closed','inactive'])) {
            $errorMessage = __t('admin.edit_job.invalid_status');
        } else {


            if ($newTitle    !== $job['title'])         $changes[] = __t('admin.edit_job.history_title_changed', ['old' => $job['title'], 'new' => $newTitle]);
            if ($newDesc     !== $job['description'])   $changes[] = __t('admin.edit_job.history_description_changed');
            if ($newPoints   !== (int)$job['points_required'])
                                                        $changes[] = __t('admin.edit_job.history_points_changed', ['old' => $job['points_required'], 'new' => $newPoints]);
            if ($newStatus   !== $job['status'])        $changes[] = __t('admin.edit_job.history_status_changed', ['old' => $job['status'], 'new' => $newStatus]);
            if ($newCategory !== (int)($job['category_id'] ?? 0))
                                                        $changes[] = __t('admin.edit_job.history_category_changed');
            if ($newOwner    !== (int)$job['user_id'])  $changes[] = __t('admin.edit_job.history_owner_changed', ['old' => $job['user_id'], 'new' => $newOwner]);


            $sql = "UPDATE jobs
                    SET title = :title,
                        description = :description,
                        points_required = :points,
                        status = :status,
                        category_id = :category_id,
                        user_id = :user_id,
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title'       => $newTitle,
                ':description' => $newDesc,
                ':points'      => $newPoints,
                ':status'      => $newStatus,
                ':category_id' => $newCategory ?: null,
                ':user_id'     => $newOwner,
                ':id'          => $jobId,
            ]);


            if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $imgId) {
                    $imgId = (int)$imgId;
                    try {
                        $imgRow = $pdo->prepare("SELECT filename FROM job_images WHERE id = ? AND job_id = ?");
                        $imgRow->execute([$imgId, $jobId]);
                        $img = $imgRow->fetch();
                        if ($img) {
                            $filePath = '../../uploads/jobs/' . $img['filename'];
                            if (file_exists($filePath)) @unlink($filePath);
                            $pdo->prepare("DELETE FROM job_images WHERE id = ?")->execute([$imgId]);
                            $changes[] = __t('admin.edit_job.history_image_removed', ['filename' => $img['filename']]);
                        }
                    } catch (PDOException $e) {  }
                }
            }


            if (!empty($_FILES['new_images']['name'][0])) {
                $uploadDir = '../../uploads/jobs/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                $allowed = ['jpg','jpeg','png','webp','gif'];
                foreach ($_FILES['new_images']['tmp_name'] as $i => $tmpName) {
                    if ($_FILES['new_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $origName = $_FILES['new_images']['name'][$i];
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;
                    if ($_FILES['new_images']['size'][$i] > 5 * 1024 * 1024) continue;

                    $filename = uniqid('job_') . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        try {
                            $pdo->prepare("INSERT INTO job_images (job_id, filename, created_at) VALUES (?, ?, NOW())")
                                ->execute([$jobId, $filename]);
                            $changes[] = __t('admin.edit_job.history_image_added', ['filename' => $filename]);
                        } catch (PDOException $e) {  }
                    }
                }
            }


            if (!empty($changes)) {
                try {
                    $changeDesc = implode('; ', $changes);
                    $pdo->prepare(
                        "INSERT INTO job_change_history (job_id, admin_id, change_description, changed_at)
                         VALUES (?, ?, ?, NOW())"
                    )->execute([$jobId, $_SESSION['user_id'], $changeDesc]);
                } catch (PDOException $e) {  }
            }

            $successMessage = __t('admin.edit_job.updated');

            $job = $jobModel->getJobDetails($jobId);


            try {
                $imgStmt->execute([$jobId]);
                $jobImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) { }


            try {
                $histStmt->execute([$jobId]);
                $changeHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) { }
        }
    }
}

function safeEcho($v, $d = '') { return htmlspecialchars($v ?? $d, ENT_QUOTES, 'UTF-8'); }
function sel($a, $b) { return $a == $b ? 'selected' : ''; }

$statusLabels = [
    'open'     => __t('admin.jobs.open'),
    'active'   => __t('admin.jobs.active'),
    'closed'   => __t('admin.jobs.closed'),
    'inactive' => __t('admin.jobs.inactive'),
];
$statusColors = [
    'open'     => 'primary',
    'active'   => 'success',
    'closed'   => 'secondary',
    'inactive' => 'warning',
];
?>
<?php include '../partials/header.php'; ?>

<style>
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

.img-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: .75rem;
}
.img-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #e3e6f0;
    transition: border-color .2s;
}
.img-item:hover { border-color: #e74a3b; }
.img-item img { width: 100%; height: 110px; object-fit: cover; display: block; }
.img-delete-label {
    position: absolute; inset: 0;
    background: rgba(231,74,59,.0);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s;
}
.img-delete-label:hover { background: rgba(231,74,59,.55); }
.img-delete-label:hover .del-icon { opacity: 1; }
.del-icon {
    opacity: 0;
    color: #fff;
    font-size: 1.6rem;
    transition: opacity .2s;
}
.img-item input[type=checkbox]:checked ~ label.img-delete-label,
.img-checked { border-color: #e74a3b !important; background: rgba(231,74,59,.1); }
.img-checked .del-icon { opacity: 1 !important; color: #e74a3b; }

.history-badge {
    font-size: .72rem;
    padding: .25em .55em;
    border-radius: 6px;
}

.upload-zone {
    border: 2px dashed #c5cbe4;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
}
.upload-zone:hover { border-color: #4e73df; background: #f0f4ff; }

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
</style>

<div class="container-fluid">
<div class="row">
<div class="col-12 main-content">

<div class="card shadow">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= htmlspecialchars(__t('admin.panel')) ?></h5>
    <nav class="nav"><?php include 'sidebar.php'; ?></nav>
  </div>

  <div class="card-body">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <a href="manage_jobs.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('admin.back_to_list')) ?>
        </a>
        <span class="ms-2 text-muted">
          <?= htmlspecialchars(__t('admin.edit_job.title', ['id' => $jobId])) ?>
        </span>
      </div>
      <div>
        <span class="badge bg-<?= $statusColors[$job['status']] ?? 'secondary' ?> fs-6">
          <?= $statusLabels[$job['status']] ?? $job['status'] ?>
        </span>
      </div>
    </div>

    <?php if ($successMessage): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-1"></i> <?= safeEcho($successMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= safeEcho($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="editJobForm">
      <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">

      <div class="edit-job-wrap">
      <div class="row g-4">

        <div class="col-lg-8">

          <div class="section-card">
            <div class="section-head"><i class="bi bi-file-text"></i> <?= htmlspecialchars(__t('admin.edit_job.basic_info')) ?></div>
            <div class="section-body">
              <div class="mb-3">
                <label class="form-label fw-semibold"><?= htmlspecialchars(__t('admin.edit_job.job_title')) ?> <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control form-control-lg"
                       value="<?= safeEcho($job['title']) ?>" required maxlength="255">
              </div>
              <div class="mb-0">
                <label class="form-label fw-semibold"><?= htmlspecialchars(__t('admin.edit_job.job_description')) ?> <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control" rows="8" required
                          style="resize:vertical"><?= safeEcho($job['description']) ?></textarea>
              </div>
            </div>
          </div>

          <div class="section-card">
            <div class="section-head"><i class="bi bi-images"></i> <?= htmlspecialchars(__t('admin.edit_job.job_images')) ?></div>
            <div class="section-body">

              <?php if (!empty($jobImages)): ?>
                <p class="text-muted small mb-2">
                  <?= htmlspecialchars(__t('admin.edit_job.delete_image_hint')) ?>
                </p>
                <div class="img-grid mb-3">
                  <?php foreach ($jobImages as $img): ?>
                    <div class="img-item" id="wrap-<?= $img['id'] ?>">
                      <input type="checkbox" name="delete_images[]"
                             value="<?= $img['id'] ?>"
                             id="del-<?= $img['id'] ?>"
                             class="d-none img-del-chk">
                      <label for="del-<?= $img['id'] ?>" class="img-delete-label" title="<?= htmlspecialchars(__t('admin.edit_job.delete_image_title')) ?>">
                        <span class="del-icon"><i class="bi bi-trash-fill"></i></span>
                      </label>
                      <img src="/uploads/jobs/<?= safeEcho($img['filename']) ?>"
                           alt="<?= htmlspecialchars(__t('admin.edit_job.image_alt')) ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted small"><?= htmlspecialchars(__t('admin.edit_job.no_images')) ?></p>
              <?php endif; ?>

              <label class="upload-zone d-block" for="new_images">
                <i class="bi bi-cloud-upload fs-2 text-primary"></i>
                <div class="mt-1 fw-semibold"><?= htmlspecialchars(__t('admin.edit_job.upload_click')) ?></div>
                <div class="text-muted small"><?= htmlspecialchars(__t('admin.edit_job.upload_hint')) ?></div>
                <input type="file" id="new_images" name="new_images[]"
                       class="d-none" multiple accept="image/*"
                       onchange="previewNewImages(this)">
              </label>

              <div id="newImgPreview" class="img-grid mt-3"></div>
            </div>
          </div>

        </div>

        <div class="col-lg-4">

          <div class="section-card">
            <div class="section-head"><i class="bi bi-sliders"></i> <?= htmlspecialchars(__t('admin.edit_job.parameters')) ?></div>
            <div class="section-body">

              <div class="mb-3">
                <label class="form-label fw-semibold"><?= htmlspecialchars(__t('admin.common.status')) ?></label>
                <select name="status" class="form-select">
                  <?php foreach ($statusLabels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= sel($job['status'], $val) ?>>
                      <?= $label ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold"><?= htmlspecialchars(__t('admin.edit_job.points_required')) ?></label>
                <div class="input-group">
                  <input type="number" name="points_required" class="form-control"
                         value="<?= (int)$job['points_required'] ?>"
                         min="1" max="100" required>
                  <span class="input-group-text">pkt</span>
                </div>
                <div class="form-text"><?= htmlspecialchars(__t('admin.edit_job.range_1_100')) ?></div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold"><?= htmlspecialchars(__t('admin.common.category')) ?></label>
                <select name="category_id" class="form-select">
                  <option value=""><?= htmlspecialchars(__t('admin.edit_job.no_category')) ?></option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"
                            <?= sel($job['category_id'] ?? '', $cat['id']) ?>>
                      <?= safeEcho($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-0">
                <label class="form-label fw-semibold"><?= htmlspecialchars(__t('admin.edit_job.owner')) ?></label>
                <select name="user_id" class="form-select">
                  <?php foreach ($allUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"
                            <?= sel($job['user_id'], $u['id']) ?>>
                      #<?= $u['id'] ?> — <?= safeEcho($u['name']) ?>
                      (<?= safeEcho($u['role']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text text-danger">
                  <i class="bi bi-exclamation-triangle"></i>
                  <?= htmlspecialchars(__t('admin.edit_job.owner_warning')) ?>
                </div>
              </div>

            </div>
          </div>

          <div class="section-card">
            <div class="section-head"><i class="bi bi-info-circle"></i> <?= htmlspecialchars(__t('admin.system_info')) ?></div>
            <div class="section-body p-0">
              <table class="table table-sm mb-0">
                <tr>
                  <td class="text-muted ps-3">ID</td>
                  <td class="fw-bold pe-3">#<?= $jobId ?></td>
                </tr>
                <tr>
                  <td class="text-muted ps-3"><?= htmlspecialchars(__t('admin.edit_job.created_at')) ?></td>
                  <td class="pe-3"><?= date('d.m.Y H:i', strtotime($job['created_at'])) ?></td>
                </tr>
                <tr>
                  <td class="text-muted ps-3"><?= htmlspecialchars(__t('admin.updated_at')) ?></td>
                  <td class="pe-3"><?= date('d.m.Y H:i', strtotime($job['updated_at'])) ?></td>
                </tr>
                <tr>
                  <td class="text-muted ps-3"><?= htmlspecialchars(__t('admin.owner')) ?></td>
                  <td class="pe-3"><?= safeEcho($job['user_name'] ?? '—') ?></td>
                </tr>
                <tr>
                  <td class="text-muted ps-3"><?= htmlspecialchars(__t('admin.images')) ?></td>
                  <td class="pe-3"><?= count($jobImages) ?></td>
                </tr>
              </table>
            </div>
          </div>

        </div>

      </div>

      <div class="section-card">
        <div class="section-head">
          <i class="bi bi-clock-history"></i> <?= htmlspecialchars(__t('admin.change_history')) ?>
          <span class="badge bg-white text-dark ms-auto"><?= count($changeHistory) ?></span>
        </div>
        <div class="section-body p-0">
          <?php if (!empty($changeHistory)): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3" style="width:160px"><?= htmlspecialchars(__t('admin.date')) ?></th>
                    <th style="width:160px"><?= htmlspecialchars(__t('admin.admin')) ?></th>
                    <th><?= htmlspecialchars(__t('admin.edit_job.change_description')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($changeHistory as $h): ?>
                    <tr>
                      <td class="ps-3 text-muted small">
                        <?= date('d.m.Y H:i', strtotime($h['changed_at'])) ?>
                      </td>
                      <td>
                        <span class="badge bg-primary history-badge">
                          <?= safeEcho($h['admin_name'] ?? 'Admin #' . $h['admin_id']) ?>
                        </span>
                      </td>
                      <td class="small"><?= safeEcho($h['change_description']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted text-center py-3 mb-0">
              <i class="bi bi-clock"></i> <?= htmlspecialchars(__t('admin.no_changes')) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="sticky-bar">
        <a href="manage_jobs.php" class="btn btn-outline-secondary">
          <i class="bi bi-x-circle"></i> <?= htmlspecialchars(__t('admin.users.cancel')) ?>
        </a>
        <div class="d-flex align-items-center gap-3">
          <small class="text-muted d-none d-md-block">
            <?= htmlspecialchars(__t('admin.last_edit')) ?>: <?= date('d.m.Y H:i', strtotime($job['updated_at'])) ?>
          </small>
          <button type="submit" class="btn btn-primary px-4" form="editJobForm">
            <i class="bi bi-floppy-fill me-1"></i> <?= htmlspecialchars(__t('admin.save_changes')) ?>
          </button>
        </div>
      </div>

      </div>
    </form>

  </div>
</div>
</div>
</div>
</div>

<script>
function previewNewImages(input) {
    const container = document.getElementById('newImgPreview');
    container.innerHTML = '';
    if (!input.files.length) return;
    Array.from(input.files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'img-item';
            div.style.border = '2px solid #4e73df';
            div.innerHTML = `<img src="${e.target.result}" style="width:100%;height:110px;object-fit:cover;">
                             <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(78,115,223,.8);color:#fff;font-size:.7rem;padding:2px 4px;text-align:center;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">${file.name}</div>`;
            container.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

document.querySelectorAll('.img-del-chk').forEach(chk => {
    chk.addEventListener('change', function () {
        const wrap = document.getElementById('wrap-' + this.value);
        if (this.checked) {
            wrap.classList.add('img-checked');
        } else {
            wrap.classList.remove('img-checked');
        }
    });
});

const uploadZone = document.querySelector('.upload-zone');
if (uploadZone) {
    uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.style.borderColor = '#4e73df'; });
    uploadZone.addEventListener('dragleave', () => { uploadZone.style.borderColor = ''; });
    uploadZone.addEventListener('drop', e => {
        e.preventDefault();
        uploadZone.style.borderColor = '';
        const input = document.getElementById('new_images');
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
        input.files = dt.files;
        previewNewImages(input);
    });
}

setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        try { new bootstrap.Alert(a).close(); } catch(e) {}
    });
}, 5000);
</script>

<?php include '../partials/footer.php'; ?>
