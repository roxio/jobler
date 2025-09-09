<?php
session_start();
require_once 'config/config.php';
require_once 'models/Job.php';
require_once 'models/SiteSettings.php';

$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();

$pdo = Database::getConnection();
$categoriesStmt = $pdo->query("SELECT id, name FROM categories");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$defaultLimit = 5;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $defaultLimit;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$view = isset($_GET['view']) ? $_GET['view'] : 'list';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : null;

$offset = ($page - 1) * $limit;

$jobModel = new Job();
$jobs = $jobModel->getJobsWithPaginationAndSearch($limit, $offset, $search, $category);
$totalJobs = $jobModel->getTotalJobsWithSearch($search, $category);

$totalPages = ceil($totalJobs / $limit);

$categoriesMap = [];
foreach ($categories as $cat) {
    $categoriesMap[$cat['id']] = $cat['name'];
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteSettings['title'] ?? 'Znajdź zlecenie') ?> - Strona główna</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php 
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/navbar.php'; 
    ?>

    <div class="container mt-4">
        <div class="hero text-center">
            <h1><?= htmlspecialchars($siteSettings['title'] ?? 'Znajdź najlepsze zlecenia lub wykonawców!') ?></h1>
            <p class="lead">Przeglądaj ogłoszenia, wyszukuj wykonawców i dołącz do społeczności profesjonalistów.</p>
            <a href="#search-section" class="btn btn-light btn-lg mt-3">Rozpocznij wyszukiwanie</a>
        </div>

        <!-- Liczniki statystyk -->
        <div class="row">
            <div class="col-md-4">
                <div class="stats-counter">
                    <h3><?= $totalJobs ?></h3>
                    <p class="text-muted">Dostępnych zleceń</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-counter">
                    <h3><?= count($categories) ?></h3>
                    <p class="text-muted">Kategorii</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-counter">
                    <h3><?= $totalPages ?></h3>
                    <p class="text-muted">Stron z ogłoszeniami</p>
                </div>
            </div>
        </div>

        <!-- Formularz wyszukiwania i filtry -->
        <div class="search-container" id="search-section">
            <form method="GET" id="filter-form">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Szukaj ogłoszeń po tytule, opisie..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit">Szukaj</button>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <select name="category" id="category" class="form-select">
                            <option value="">Wszystkie kategorie</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($category !== null && $category == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-controls mt-3">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-2">
                            <label for="limit" class="form-label mb-0">Wyświetlaj po:</label>
                            <select name="limit" id="limit" class="form-select">
                                <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="view" class="form-label mb-0">Widok:</label>
                            <div class="btn-group view-options" role="group">
                                <input type="radio" class="btn-check" name="view" id="view-list" value="list" autocomplete="off" <?= $view == 'list' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="view-list"><i class="bi bi-list-ul"></i> Lista</label>
                                
                                <input type="radio" class="btn-check" name="view" id="view-grid" value="grid" autocomplete="off" <?= $view == 'grid' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="view-grid"><i class="bi bi-grid-3x3"></i> Siatka</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2 text-md-end">
                            <button type="submit" class="btn btn-primary">Zastosuj filtry</button>
                            <?php if (!empty($search) || !empty($category)): ?>
                                <a href="index.php" class="btn btn-outline-secondary ms-2">Wyczyść filtry</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Lista lub grid ogłoszeń -->
        <?php if (!empty($jobs)): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">Dostępne zlecenia</h3>
                <span class="text-muted">Znaleziono: <?= $totalJobs ?> ogłoszeń</span>
            </div>
            
            <?php if ($view == 'list'): ?>
                <div class="list-view">
                    <?php foreach ($jobs as $job): ?>
                        <div class="list-group-item d-md-flex align-items-center">
                            <img src="../images/default-job.jpg" alt="Zdjęcie ogłoszenia" class="me-md-4 mb-3 mb-md-0">
                            <div class="flex-grow-1 me-md-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h5 class="mb-1">
                                        <a href="views/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </a>
                                    </h5>
                                    <?php if (!empty($job['category_id']) && isset($categoriesMap[$job['category_id']])): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($categoriesMap[$job['category_id']]) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Brak kategorii</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted mb-2"><?= htmlspecialchars(substr($job['description'], 0, 150)) ?>...</p>
                                <small class="text-muted"><i class="bi bi-calendar me-1"></i> Dodano: <?= htmlspecialchars($job['created_at']) ?></small>
                            </div>
                            <div class="mt-3 mt-md-0">
                                <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary">Zobacz szczegóły</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($jobs as $job): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card job-card h-100">
                                <img src="../images/default-job.jpg" class="card-img-top" alt="Zdjęcie ogłoszenia">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title">
                                            <a href="views/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($job['title']) ?>
                                            </a>
                                        </h5>
                                        <?php if (!empty($job['category_id']) && isset($categoriesMap[$job['category_id']])): ?>
                                            <span class="badge bg-primary"><?= htmlspecialchars($categoriesMap[$job['category_id']]) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Brak kategorii</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="card-text text-muted flex-grow-1"><?= htmlspecialchars(substr($job['description'], 0, 100)) ?>...</p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted"><i class="bi bi-calendar me-1"></i> <?= htmlspecialchars($job['created_at']) ?></small>
                                            <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-primary btn-sm">Szczegóły</a>
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
                <h3 class="mt-3 text-muted">Brak ogłoszeń do wyświetlenia</h3>
                <p class="text-muted">Spróbuj zmienić kryteria wyszukiwania lub sprawdź później.</p>
            </div>
        <?php endif; ?>

        <!-- Paginacja -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginacja" class="mt-5">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>" aria-label="Poprzednia">
                            <span aria-hidden="true">&laquo;</span>
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
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>" aria-label="Następna">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <?php 
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/footer.php'; 
    ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('category').addEventListener('change', function() {
            const form = document.getElementById('filter-form');
            const pageInput = document.createElement('input');
            pageInput.type = 'hidden';
            pageInput.name = 'page';
            pageInput.value = '1';
            form.appendChild(pageInput);
            
            form.submit();
        });
        
        document.getElementById('limit').addEventListener('change', function() {
            const form = document.getElementById('filter-form');
            const pageInput = document.createElement('input');
            pageInput.type = 'hidden';
            pageInput.name = 'page';
            pageInput.value = '1';
            form.appendChild(pageInput);
            
            form.submit();
        });
        
        document.querySelectorAll('input[name="view"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.getElementById('filter-form').submit();
            });
        });

        document.querySelectorAll('a[href="index.php"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'index.php';
            });
        });
    </script>
</body>
</html>