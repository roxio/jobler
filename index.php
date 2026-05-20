<?php
session_start();
require_once 'config/config.php';
require_once 'models/Job.php';
require_once 'models/SiteSettings.php';
require_once 'models/Language.php';

$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();
$currentLocale = Language::current('frontend');

$pdo = Database::getConnection();
$categoriesStmt = $pdo->query("SELECT id, name FROM categories");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$defaultLimit = 8;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $defaultLimit;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$view = isset($_GET['view']) ? $_GET['view'] : 'grid';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : null;
$scrollPosition = isset($_GET['scroll_position']) ? (int)$_GET['scroll_position'] : 0;

$offset = ($page - 1) * $limit;

$jobModel = new Job();
$jobs = $jobModel->getJobsWithPaginationAndSearch($limit, $offset, $search, $category);
$totalJobs = $jobModel->getTotalJobsWithSearch($search, $category);

$totalPages = ceil($totalJobs / $limit);

$categoriesMap = [];
foreach ($categories as $cat) {
    $categoriesMap[$cat['id']] = $cat['name'];
}

function jobImageUrl($filename) {
    if (empty($filename)) {
        $filename = 'no_image.jpg';
    }

    $safeFilename = basename($filename);
    $imagePath = __DIR__ . '/uploads/jobs/' . $safeFilename;

    if (!is_file($imagePath)) {
        $safeFilename = 'no_image.jpg';
    }

    return '/uploads/jobs/' . rawurlencode($safeFilename);
}


$latestJobs = $jobModel->getJobsWithPaginationAndSearch(4, 0, '', null);
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(strtolower(substr($currentLocale, 0, 2)), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteSettings['title'] ?? 'Jobler') ?> - <?= htmlspecialchars(__t('footer.home'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
    <?php
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/navbar.php';
    ?>

    <div class="container-fluid px-0">
        <div class="container mt-5">
            <section class="homepage-hero">
                <div class="homepage-hero__content">
                    <p class="homepage-hero__eyebrow"><?= htmlspecialchars(__t('home.hero_eyebrow'), ENT_QUOTES, 'UTF-8') ?></p>
                    <h1><?= htmlspecialchars(__t('home.hero_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="homepage-hero__lead">
                        <?= htmlspecialchars(__t('home.hero_lead'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <div class="homepage-hero__actions">
                        <a href="<?= isset($_SESSION['user_id']) ? '/views/user/create_job.php' : '/login.php' ?>" class="btn btn-light btn-lg">
                            <i class="bi bi-plus-circle me-2"></i><?= htmlspecialchars(__t('home.add_job'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <a href="#search-section" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-search me-2"></i><?= htmlspecialchars(__t('home.browse_jobs'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                </div>
                <div class="homepage-hero__panel" aria-hidden="true">
                    <div class="hero-stat">
                        <span><?= number_format($totalJobs) ?></span>
                        <small><?= htmlspecialchars(__t('home.available_jobs_count'), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                    <div class="hero-card-preview">
                        <i class="bi bi-briefcase"></i>
                        <div>
                            <strong><?= htmlspecialchars(__t('home.new_jobs'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars(__t('home.new_jobs_hint'), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                    <div class="hero-card-preview">
                        <i class="bi bi-person-check"></i>
                        <div>
                            <strong><?= htmlspecialchars(__t('home.contractors'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars(__t('home.contractors_hint'), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                </div>
            </section>
            <div class="search-container" id="search-section">
                <h2 class="visually-hidden"><?= htmlspecialchars(__t('home.search_heading'), ENT_QUOTES, 'UTF-8') ?></h2>

                <form method="GET" id="filter-form">
                    <input type="hidden" name="scroll_position" id="scroll_position" value="<?= $scrollPosition ?>">
                    <input type="hidden" name="lang" value="<?= htmlspecialchars($currentLocale, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row search-row align-items-end">
                        <div class="col-lg-5 col-md-6 mb-2 search-field">
                            <div class="filter-group">
                                <label class="filter-label"><?= htmlspecialchars(__t('home.search_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <div class="input-group search-input-group">
                                    <span class="input-group-text bg-primary text-white border-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-0"
                                           placeholder="<?= htmlspecialchars(__t('home.search_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-3 mb-2 category-field">
                            <div class="filter-group">
                                <label class="filter-label"><?= htmlspecialchars(__t('home.category_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <select name="category" id="category" class="form-select">
                                    <option value=""><?= htmlspecialchars(__t('nav.all'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($category !== null && $category == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-1 col-md-3 mb-2 limit-field">
                            <div class="filter-group">
                                <label class="filter-label"><?= htmlspecialchars(__t('home.per_page_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <select name="limit" id="limit" class="form-select">
                                    <option value="8" <?= $limit == 8 ? 'selected' : '' ?>>8</option>
                                    <option value="12" <?= $limit == 12 ? 'selected' : '' ?>>12</option>
                                    <option value="16" <?= $limit == 16 ? 'selected' : '' ?>>16</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-4 mb-2 view-field">
                            <div class="filter-group">
                                <label class="filter-label"><?= htmlspecialchars(__t('home.view_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="view" id="view-list" value="list" autocomplete="off" <?= $view == 'list' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="view-list" title="<?= htmlspecialchars(__t('home.list_view'), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-list-ul"></i>
                                    </label>

                                    <input type="radio" class="btn-check" name="view" id="view-grid" value="grid" autocomplete="off" <?= $view == 'grid' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="view-grid" title="<?= htmlspecialchars(__t('home.grid_view'), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-grid-3x3"></i>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-8 mb-2 action-field">
                            <div class="filter-options">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-search me-1"></i><?= htmlspecialchars(__t('home.search_button'), ENT_QUOTES, 'UTF-8') ?>
                                </button>

                                <?php if (!empty($search) || !empty($category)): ?>
                                    <a href="index.php?scroll_position=<?= $scrollPosition ?>&lang=<?= urlencode($currentLocale) ?>" class="btn btn-outline-secondary" title="<?= htmlspecialchars(__t('home.clear_filters'), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (!empty($jobs)): ?>
                <div class="results-header d-flex justify-content-between align-items-center mb-4" id="jobs-section">
                    <h2 class="section-title mb-0"><?= htmlspecialchars(__t('home.available_jobs'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <span class="text-muted fw-semibold"><?= htmlspecialchars(__t('home.results_found', ['count' => number_format($totalJobs)]), ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <?php if ($view == 'list'): ?>
                    <div class="list-view">
                        <?php foreach ($jobs as $job): ?>
                            <?php $imageUrl = jobImageUrl($job['primary_image'] ?? 'no_image.jpg'); ?>
                            <div class="card job-card mb-4">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-2 text-center">
                                            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars(__t('home.job_image_alt'), ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded border" style="max-height: 100px;">
                                        </div>
                                        <div class="col-md-7">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-1">
                                                    <a href="views/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none text-dark">
                                                        <?= htmlspecialchars($job['title']) ?>
                                                    </a>
                                                </h5>
                                                <?php if (!empty($job['category_id']) && isset($categoriesMap[$job['category_id']])): ?>
                                                    <span class="badge bg-primary category-badge"><?= htmlspecialchars($categoriesMap[$job['category_id']]) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary category-badge"><?= htmlspecialchars(__t('home.no_category'), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-muted mb-2"><?= htmlspecialchars(substr($job['description'], 0, 150)) ?>...</p>
                                            <small class="text-muted"><i class="bi bi-calendar me-1"></i> <?= htmlspecialchars(__t('home.added'), ENT_QUOTES, 'UTF-8') ?>: <?= date('d.m.Y', strtotime($job['created_at'])) ?></small>
                                        </div>
                                        <div class="col-md-3 text-md-end">
                                            <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-primary"><?= htmlspecialchars(__t('home.see_details'), ENT_QUOTES, 'UTF-8') ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($jobs as $job): ?>
                            <?php $imageUrl = jobImageUrl($job['primary_image'] ?? 'no_image.jpg'); ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                                <div class="card job-card h-100">
                                    <img src="<?= htmlspecialchars($imageUrl) ?>" class="card-img-top" alt="<?= htmlspecialchars(__t('home.job_image_alt'), ENT_QUOTES, 'UTF-8') ?>" style="height: 180px; object-fit: cover;">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title">
                                                <a href="views/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none text-dark">
                                                    <?= htmlspecialchars(mb_substr($job['title'], 0, 30)) ?><?= mb_strlen($job['title']) > 30 ? '...' : '' ?>
                                                </a>
                                            </h5>
                                            <?php if (!empty($job['category_id']) && isset($categoriesMap[$job['category_id']])): ?>
                                                <span class="badge bg-primary category-badge"><?= htmlspecialchars($categoriesMap[$job['category_id']]) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary category-badge"><?= htmlspecialchars(__t('home.no_category'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="card-text text-muted flex-grow-1"><?= htmlspecialchars(substr($job['description'], 0, 100)) ?>...</p>
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><i class="bi bi-calendar me-1"></i> <?= date('d.m.Y', strtotime($job['created_at'])) ?></small>
                                                <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars(__t('home.details'), ENT_QUOTES, 'UTF-8') ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted"><?= htmlspecialchars(__t('home.no_jobs_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-muted"><?= htmlspecialchars(__t('home.no_jobs_text'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="/register.php" class="btn btn-primary btn-lg mt-3">
                            <i class="bi bi-person-plus me-2"></i><?= htmlspecialchars(__t('home.join_add_first'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php else: ?>
                        <a href="/views/job/create.php" class="btn btn-primary btn-lg mt-3">
                            <i class="bi bi-plus-circle me-2"></i><?= htmlspecialchars(__t('home.add_first'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Paginacja" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&scroll_position=<?= $scrollPosition ?>&lang=<?= urlencode($currentLocale) ?>" aria-label="<?= htmlspecialchars(__t('home.previous'), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $startPage + 4);

                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }

                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&scroll_position=<?= $scrollPosition ?>&lang=<?= urlencode($currentLocale) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&scroll_position=<?= $scrollPosition ?>&lang=<?= urlencode($currentLocale) ?>" aria-label="<?= htmlspecialchars(__t('home.next'), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const scrollPosition = urlParams.get('scroll_position');

            if (scrollPosition && scrollPosition > 0) {
                setTimeout(function() {
                    window.scrollTo({
                        top: parseInt(scrollPosition),
                        behavior: 'auto'
                    });
                }, 100);
            }

            document.getElementById('filter-form').addEventListener('submit', function() {
                document.getElementById('scroll_position').value = window.pageYOffset || document.documentElement.scrollTop;
            });

            document.getElementById('category').addEventListener('change', function() {
                document.getElementById('scroll_position').value = window.pageYOffset || document.documentElement.scrollTop;
                document.getElementById('filter-form').submit();
            });

            document.getElementById('limit').addEventListener('change', function() {
                document.getElementById('scroll_position').value = window.pageYOffset || document.documentElement.scrollTop;
                document.getElementById('filter-form').submit();
            });

            document.querySelectorAll('input[name="view"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    document.getElementById('scroll_position').value = window.pageYOffset || document.documentElement.scrollTop;
                    document.getElementById('filter-form').submit();
                });
            });

            document.querySelectorAll('a[href="index.php"]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'index.php?scroll_position=' + (window.pageYOffset || document.documentElement.scrollTop);
                });
            });

            document.querySelectorAll('.pagination a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const newUrl = this.href + '&scroll_position=' + (window.pageYOffset || document.documentElement.scrollTop);
                    window.location.href = newUrl;
                });
            });
        });
    </script>
</body>
</html>
