<?php
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/SiteSettings.php');

// Utwórz obiekty modelu
$userModel = new User();
$jobModel = new Job();
$settingsModel = new SiteSettings();

// Pobierz liczbę użytkowników i ogłoszeń
$userCount = $userModel->getUserCount();
$jobCount = $jobModel->getJobCount();
$newUsers = $userModel->getNewUsersCount(); // Nowi użytkownicy
$newJobs = $jobModel->getNewJobsCount();   // Nowe ogłoszenia
$siteViews = $settingsModel->getSiteViews(); // Wyświetlenia strony

// Pobierz bieżące ustawienia strony
$currentSettings = $settingsModel->getSettings();

// Sprawdzenie, czy ustawienia zostały poprawnie pobrane
if ($currentSettings === null) {
    // Jeśli brak ustawień, przypisujemy domyślne wartości
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

<div class="container">
    <h1>Panel Administratora</h1>
    <p>Witaj w panelu administracyjnym. Zarządzaj użytkownikami, ogłoszeniami, ustawieniami strony i innymi zasobami.</p>

    <div class="row">
        <!-- Sekcja użytkowników -->
        <div class="col-md-6">
            <h3>Użytkownicy</h3>
            <p>Masz <strong><?php echo $userCount; ?></strong> zarejestrowanych użytkowników.</p>
            <p>Nowi użytkownicy w tym tygodniu: <strong><?php echo $newUsers; ?></strong>.</p>
            <a href="../admin/manage_users.php" class="btn btn-primary">Zarządzaj użytkownikami</a>
            <a href="export_users.php" class="btn btn-success">Eksportuj do CSV</a>
        </div>

        <!-- Sekcja ogłoszeń -->
        <div class="col-md-6">
            <h3>Ogłoszenia</h3>
            <p>Masz <strong><?php echo $jobCount; ?></strong> opublikowanych ogłoszeń.</p>
            <p>Nowe ogłoszenia w tym tygodniu: <strong><?php echo $newJobs; ?></strong>.</p>
            <a href="../admin/manage_jobs.php" class="btn btn-secondary">Zarządzaj ogłoszeniami</a>
        </div>
    </div>

    <hr>

    <!-- Statystyki strony -->
    <div class="row mt-4">
        <div class="col-md-4">
            <h4>Wyświetlenia strony</h4>
            <p><strong><?php echo $siteViews; ?></strong></p>
        </div>
        <div class="col-md-4">
            <h4>Nowi użytkownicy</h4>
            <p><strong><?php echo $newUsers; ?></strong></p>
        </div>
        <div class="col-md-4">
            <h4>Nowe ogłoszenia</h4>
            <p><strong><?php echo $newJobs; ?></strong></p>
        </div>
    </div>

    <hr>

    <!-- Sekcja ustawień strony -->
    <div class="mt-4">
        <h3>Ustawienia strony</h3>
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <!-- Tytuł strony -->
            <div class="mb-3">
                <label for="site_title" class="form-label">Tytuł strony</label>
                <input type="text" name="site_title" id="site_title" class="form-control" value="<?php echo htmlspecialchars($currentSettings['title']); ?>" required>
            </div>

            <!-- Logo strony -->
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

            <!-- Kategorie -->
            <div class="mb-3">
                <label for="categories" class="form-label">Kategorie i podkategorie</label>
                <textarea name="categories" id="categories" class="form-control" rows="6"><?php echo htmlspecialchars(implode("\n", $currentSettings['categories'])); ?></textarea>
                <small class="text-muted">Wpisz kategorie oddzielone nową linią. Przykład:</small>
                <pre class="bg-light p-2 mt-2">Budowa domu
- Elektryk
- Hydraulik
Meble i zabudowa</pre>
            </div>

            <!-- Zapisz ustawienia -->
            <button type="submit" class="btn btn-success">Zapisz zmiany</button>
        </form>
    </div>
</div>

<?php include '../partials/footer.php'; ?>