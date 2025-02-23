<?php
session_start();
require_once 'config/config.php';
require_once 'models/Job.php';

// Pobieranie liczby wyświetlanych elementów na stronie
$defaultLimit = 5;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $defaultLimit;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$view = isset($_GET['view']) ? $_GET['view'] : 'list'; // Widok: list lub grid
$search = isset($_GET['search']) ? trim($_GET['search']) : ''; // Wyszukiwanie

// Obliczanie offsetu
$offset = ($page - 1) * $limit;

// Pobranie ogłoszeń z limitem, offsetem i opcjonalnym wyszukiwaniem
$jobs = Job::getJobsWithPaginationAndSearch($limit, $offset, $search);
$totalJobs = Job::getTotalJobsWithSearch($search); // Pobiera całkowitą liczbę ogłoszeń pasujących do wyszukiwania

// Obliczanie liczby stron
$totalPages = ceil($totalJobs / $limit);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Znajdź zlecenie - Strona główna</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="hero">
            <h1>Znajdź najlepsze zlecenia lub wykonawców!</h1>
            <p>Przeglądaj ogłoszenia, wyszukuj wykonawców i dołącz do społeczności profesjonalistów.</p>
        </div>

        <!-- Formularz wyszukiwania i filtry -->
        <form method="GET" class="d-flex justify-content-between align-items-center mb-4">
            <div class="input-group w-50">
                <input type="text" name="search" class="form-control" placeholder="Szukaj ogłoszeń..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary" type="submit">Szukaj</button>
            </div>

            <div class="d-flex align-items-center">
                <label for="limit" class="form-label me-2 mb-0">Wyświetlaj:</label>
                <select name="limit" id="limit" class="form-select w-auto me-3" onchange="this.form.submit()">
                    <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                </select>

                <label for="view" class="form-label me-2 mb-0">Widok:</label>
                <select name="view" id="view" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="list" <?= $view == 'list' ? 'selected' : '' ?>>Lista</option>
                    <option value="grid" <?= $view == 'grid' ? 'selected' : '' ?>>Grid</option>
                </select>
            </div>
        </form>

        <!-- Lista lub grid ogłoszeń -->
        <?php if (!empty($jobs)): ?>
            <?php if ($view == 'list'): ?>
                <div class="list-group list-view">
                    <div class="row">
                        <?php foreach ($jobs as $job): ?>
                            <div class="col-md-6">
                                <div class="list-group-item">
                                    <img src="../images/default-job.jpg" alt="Zdjęcie ogłoszenia">
                                    <div>
                                        <h5 class="mt-2">
                                            <a href="views/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none text-primary">
                                                <?= htmlspecialchars($job['title']) ?>
                                            </a>
                                        </h5>
                                        <small class="text-muted">Dodano: <?= htmlspecialchars($job['created_at']) ?></small>
                                    </div>
                                    <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary btn-sm mt-2">Zobacz szczegóły</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($jobs as $job): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card job-card">
                                <img src="../images/default-job.jpg" class="card-img-top" alt="Zdjęcie ogłoszenia">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="views/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none text-primary">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </a>
                                    </h5>
                                    <p class="card-text text-muted">Dodano: <?= htmlspecialchars($job['created_at']) ?></p>
                                    <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-primary btn-sm">Zobacz szczegóły</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted text-center">Brak ogłoszeń do wyświetlenia.</p>
        <?php endif; ?>

        <!-- Paginacja -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginacja" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <?php include 'templates/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src=".https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>