<?php
session_start();
require_once 'config/config.php';
require_once 'models/Job.php';

// Pobieranie liczby wyświetlanych elementów na stronie
$defaultLimit = 5;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $defaultLimit;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Obliczanie offsetu
$offset = ($page - 1) * $limit;

// Pobranie ogłoszeń z limitem i offsetem
$jobs = Job::getJobsWithPagination($limit, $offset);
$totalJobs = Job::getTotalJobs(); // Pobiera całkowitą liczbę ogłoszeń

// Obliczanie liczby stron
$totalPages = ceil($totalJobs / $limit);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strona Główna</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Witaj na naszej stronie!</h1>
            <p class="lead">Znajdź najlepsze zlecenia lub wykonawców w swojej okolicy.</p>
        </div>

        <!-- Selektor liczby wyświetlanych elementów -->
        <form method="GET" class="mb-3">
            <label for="limit" class="form-label">Wyświetlaj:</label>
            <select name="limit" id="limit" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
                <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
            </select> ogłoszeń na stronę.
        </form>

        <!-- Lista ogłoszeń -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h2 class="h4 mb-0">Lista ogłoszeń</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($jobs)): ?>
                    <ul class="list-group">
                        <?php foreach ($jobs as $job): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">
                                        <a href="views/job/view.php?id=<?= $job['id'] ?>" class="text-decoration-none text-primary">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </a>
                                    </h5>
                                    <small class="text-muted">Dodano: <?= htmlspecialchars($job['created_at']) ?></small>
                                </div>
                                <a href="views/job/view.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary btn-sm">Zobacz szczegóły</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">Brak ogłoszeń do wyświetlenia.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Paginacja -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginacja" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <?php include 'templates/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>