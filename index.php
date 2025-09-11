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

// Pobierz najnowsze oferty (używając istniejącej metody z limitem)
$latestJobs = $jobModel->getJobsWithPaginationAndSearch(4, 0, '', null);
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

    <div class="container-fluid px-0">
        <div class="container mt-5">
            <!-- Formularz wyszukiwania i filtry -->
            <div class="search-container" id="search-section">
                <h2 class="section-title mb-3">Znajdź idealne zlecenie</h2>
                
                <form method="GET" id="filter-form">
                    <input type="hidden" name="scroll_position" id="scroll_position" value="<?= $scrollPosition ?>">
                    <div class="row search-row align-items-end">
                        <!-- Wyszukiwanie -->
                        <div class="col-lg-4 col-md-6 mb-2">
                            <div class="filter-group">
                                <label class="filter-label">Szukaj</label>
                                <div class="input-group search-input-group">
                                    <span class="input-group-text bg-primary text-white border-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-0" 
                                           placeholder="Tytuł, opis..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Kategoria -->
                        <div class="col-lg-2 col-md-3 mb-2">
                            <div class="filter-group">
                                <label class="filter-label">Kategoria</label>
                                <select name="category" id="category" class="form-select">
                                    <option value="">Wszystkie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($category !== null && $category == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Limit -->
                        <div class="col-lg-2 col-md-3 mb-2">
                            <div class="filter-group">
                                <label class="filter-label">Na stronę</label>
                                <select name="limit" id="limit" class="form-select">
                                    <option value="8" <?= $limit == 8 ? 'selected' : '' ?>>8</option>
                                    <option value="12" <?= $limit == 12 ? 'selected' : '' ?>>12</option>
                                    <option value="16" <?= $limit == 16 ? 'selected' : '' ?>>16</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Widok -->
                        <div class="col-lg-2 col-md-4 mb-2">
                            <div class="filter-group">
                                <label class="filter-label">Widok</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="view" id="view-list" value="list" autocomplete="off" <?= $view == 'list' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="view-list" title="Widok listy">
                                        <i class="bi bi-list-ul"></i>
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="view" id="view-grid" value="grid" autocomplete="off" <?= $view == 'grid' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="view-grid" title="Widok siatki">
                                        <i class="bi bi-grid-3x3"></i>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Przyciski akcji -->
                        <div class="col-lg-2 col-md-8 mb-2">
                            <div class="filter-options">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-search me-1"></i>Szukaj
                                </button>
                                
                                <?php if (!empty($search) || !empty($category)): ?>
                                    <a href="index.php?scroll_position=<?= $scrollPosition ?>" class="btn btn-outline-secondary" title="Wyczyść filtry">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Lista lub grid ogłoszeń -->
            <?php if (!empty($jobs)): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title mb-0">Dostępne zlecenia</h2>
                    <span class="text-muted fw-semibold">Znaleziono: <?= number_format($totalJobs) ?> ogłoszeń</span>
                </div>
                
                <?php if ($view == 'list'): ?>
                    <div class="list-view">
                        <?php foreach ($jobs as $job): ?>
                            <div class="card job-card mb-4">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-2 text-center">
                                            <img src="../images/default-job.jpg" alt="Zdjęcie ogłoszenia" class="img-fluid rounded" style="max-height: 100px;">
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
                                                    <span class="badge bg-secondary category-badge">Brak kategorii</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-muted mb-2"><?= htmlspecialchars(substr($job['description'], 0, 150)) ?>...</p>
                                            <small class="text-muted"><i class="bi bi-calendar me-1"></i> Dodano: <?= date('d.m.Y', strtotime($job['created_at'])) ?></small>
                                        </div>
                                        <div class="col-md-3 text-md-end">
                                            <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-primary">Zobacz szczegóły</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($jobs as $job): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                                <div class="card job-card h-100">
                                    <img src="../images/default-job.jpg" class="card-img-top" alt="Zdjęcie ogłoszenia" style="height: 180px; object-fit: cover;">
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
                                                <span class="badge bg-secondary category-badge">Brak kategorii</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="card-text text-muted flex-grow-1"><?= htmlspecialchars(substr($job['description'], 0, 100)) ?>...</p>
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><i class="bi bi-calendar me-1"></i> <?= date('d.m.Y', strtotime($job['created_at'])) ?></small>
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
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="/register.php" class="btn btn-primary btn-lg mt-3">
                            <i class="bi bi-person-plus me-2"></i>Dołącz i dodaj pierwsze ogłoszenie
                        </a>
                    <?php else: ?>
                        <a href="/views/job/create.php" class="btn btn-primary btn-lg mt-3">
                            <i class="bi bi-plus-circle me-2"></i>Dodaj pierwsze ogłoszenie
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Paginacja -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Paginacja" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&scroll_position=<?= $scrollPosition ?>" aria-label="Poprzednia">
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
                                <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&scroll_position=<?= $scrollPosition ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&scroll_position=<?= $scrollPosition ?>" aria-label="Następna">
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

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Przewiń do zapisanej pozycji po załadowaniu strony
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
            
            // Zapisz pozycję przed wysłaniem formularza
            document.getElementById('filter-form').addEventListener('submit', function() {
                document.getElementById('scroll_position').value = window.pageYOffset || document.documentElement.scrollTop;
            });
            
            // Automatyczne wysyłanie formularza przy zmianie filtra z zapisaniem pozycji
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
            
            // Obsługa czyszczenia filtrów
            document.querySelectorAll('a[href="index.php"]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'index.php?scroll_position=' + (window.pageYOffset || document.documentElement.scrollTop);
                });
            });
            
            // Zapisz pozycję przed kliknięciem paginacji
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