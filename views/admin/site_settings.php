<?php include_once('../../models/SiteSettings.php');
$settingsModel = new SiteSettings();

// Pobierz bieżące ustawienia strony
$currentSettings = $settingsModel->getSettings();

// Sprawdzenie, czy ustawienia zostały poprawnie pobrane
if ($currentSettings === null) {
    $currentSettings = [
        'title' => 'Domyślny tytuł',
        'logo' => 'default-logo.png',
        'categories' => []
    ];
}

// Obsługa formularza aktualizacji ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newTitle = $_POST['site_title'];
    $newLogo = $_FILES['site_logo']['name'];
    $categories = $_POST['categories'];

    // Aktualizacja tytułu strony
    $settingsModel->updateTitle($newTitle);

    // Aktualizacja logo (jeśli zostało przesłane)
    if (!empty($newLogo)) {
        $targetDir = "../../img/";
        $targetFile = $targetDir . basename($newLogo);
        move_uploaded_file($_FILES['site_logo']['tmp_name'], $targetFile);
        $settingsModel->updateLogo($newLogo);
    }

    // Aktualizacja kategorii
    $settingsModel->updateCategories($categories);

    // Odśwież dane
    $currentSettings = $settingsModel->getSettings();
    $successMessage = "Ustawienia zostały zaktualizowane!";
}
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Menu boczne -->
        <div class="col-md-2 col-lg-2 sidebar">
            <div class="sidebar-header">
                <h2>Panel Admina</h2>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/manage_users.php">
                        <i class="fas fa-users"></i> Użytkownicy
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/manage_jobs.php">
                        <i class="fas fa-briefcase"></i> Ogłoszenia
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_settings.php">
                        <i class="fas fa-cogs"></i> Ustawienia
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-line"></i> Raporty
                    </a>
                </li>
            </ul>
        </div>

        <!-- Główna zawartość -->
        <div class="col-md-10 col-lg-10 main-content">
            <!-- Ustawienia strony -->
            <div class="mt-4">
                <h3>Ustawienia strony</h3>
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success">
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="site_title" class="form-label">Tytuł strony</label>
                        <input type="text" name="site_title" id="site_title" class="form-control" value="<?php echo htmlspecialchars($currentSettings['title']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="site_logo" class="form-label">Logo strony</label>
                        <input type="file" name="site_logo" id="site_logo" class="form-control">
                        <small class="text-muted">Bieżące logo:</small>
                        <?php if (file_exists("../../img/" . $currentSettings['logo'])): ?>
                            <img src="/img/<?php echo htmlspecialchars($currentSettings['logo']); ?>" alt="Logo" style="height: 50px;">
                        <?php else: ?>
                            <p>Brak logo</p>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="categories" class="form-label">Kategorie i podkategorie</label>
                        <textarea name="categories" id="categories" class="form-control" rows="6"><?php echo htmlspecialchars(implode("\n", $currentSettings['categories'])); ?></textarea>
                        <small class="text-muted">Wpisz kategorie oddzielone nową linią. Przykład:</small>
                        <pre class="bg-light p-2 mt-2">Budowa domu
- Elektryk
- Hydraulik
Meble i zabudowa</pre>
                    </div>

                    <button type="submit" class="btn btn-success">Zapisz zmiany</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
