<?php 
session_start();
include_once('../../models/SiteSettings.php');

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
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
    $fileType = mime_content_type($_FILES['site_logo']['tmp_name']);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (in_array($fileType, $allowedTypes)) {
        move_uploaded_file($_FILES['site_logo']['tmp_name'], $targetFile);
        $settingsModel->updateLogo($newLogo);
    } else {
        echo "Nieprawidłowy format pliku!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    if ($_POST['form_type'] === 'update_settings') {
        $newTitle = isset($_POST['site_title']) ? $_POST['site_title'] : null;
        $newLogo = isset($_FILES['site_logo']['name']) ? $_FILES['site_logo']['name'] : null;
        
        if ($newTitle) {
            $settingsModel->updateTitle($newTitle);
        }

        if (!empty($newLogo) && isset($_FILES['site_logo']['tmp_name']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../../img/";
            $targetFile = $targetDir . basename($newLogo);
            
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $targetFile)) {
                $settingsModel->updateLogo($newLogo);
            } else {
                $errorMessage = "Nie udało się przesłać pliku.";
            }
        }

        $successMessage = "Ustawienia zostały zaktualizowane!";
    }

    if ($_POST['form_type'] === 'add_category') {
        $name = isset($_POST['category_name']) ? $_POST['category_name'] : '';
        $parent_id = isset($_POST['parent_category']) ? (int) $_POST['parent_category'] : null;

        if (!empty($name)) {
            $settingsModel->addCategory($name, $parent_id);
            $successMessage = "Dodano nową kategorię!";
        } else {
            $errorMessage = "Nazwa kategorii nie może być pusta!";
        }
    }
}

?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <nav class="nav">
					<?php include 'sidebar.php'; ?>
                    </nav>
					<?php endif; ?>
                </div>

                <div class="card-body">
				
				 <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-1"><i class="bi bi-info-square"></i> Ustawienia strony</h3></h5>
	</div>
                <div class="card-body">
			
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success">
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
				
				<div class="row">
                <div class="col-md-4">
				
                <form method="POST" enctype="multipart/form-data">
				<input type="hidden" name="form_type" value="update_settings">
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
 <button type="submit" class="btn btn-success">Zapisz zmiany</button>
                </form>
				</div>
				
				
                <div class="col-md-4">
                 <?php $categories = $settingsModel->getCategories(); ?>
<div class="mb-3">
    <label class="form-label">Kategorie</label>
    <ul>
        <?php foreach ($categories as $catId => $category): ?>
            <li><?php echo htmlspecialchars($category['name']); ?>
                <?php if (!empty($category['subcategories'])): ?>
                    <ul>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <li><?php echo htmlspecialchars($sub['name']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
</div>
<div class="col-md-4">
<form method="POST">
<input type="hidden" name="form_type" value="add_category">
    <div class="mb-3">
        <label for="category_name" class="form-label">Nazwa kategorii/podkategorii</label>
        <input type="text" name="category_name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label for="parent_category" class="form-label">Kategoria nadrzędna</label>
        <select name="parent_category" class="form-control">
            <option value="">Brak (główna kategoria)</option>
            <?php foreach ($categories as $catId => $category): ?>
                <option value="<?php echo $catId; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" name="add_category" class="btn btn-primary">Dodaj kategorię</button>
</form>

            </div>
        </div>
    </div>

                        </div>
                    </div>

				
        <div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
        </div>
  
            </div>	
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>
