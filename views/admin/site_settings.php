<?php 
session_start();
include_once('../../models/Database.php'); // Połączenie z bazą danych
include_once('../../models/SiteSettings.php');
include_once('../../models/ErrorLogs.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/LoginHistory.php');

// Uzyskanie połączenia z bazą danych
$pdo = Database::getConnection();

// Tworzenie instancji modeli z połączeniem PDO
$settingsModel = new SiteSettings($pdo);
$errorLogsModel = new ErrorLogs($pdo); // Przekazanie PDO do klasy ErrorLogs
$transactionHistoryModel = new TransactionHistory($pdo); // Przekazanie PDO do klasy TransactionHistory
$loginHistoryModel = new LoginHistory($pdo); // Przekazanie PDO do klasy LoginHistory

// Pobierz bieżące ustawienia strony
$currentSettings = $settingsModel->getSettings();

if ($currentSettings === null) {
    $currentSettings = [
        'title' => 'Domyślny tytuł',
        'logo' => 'default-logo.png',
        'categories' => [],
        'meta_description' => '',
        'meta_keywords' => '',
        'smtp_server' => '',
        'smtp_port' => '',
        'smtp_username' => '',
        'smtp_password' => ''
    ];
}

/// Obsługa formularza aktualizacji ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    if ($_POST['form_type'] === 'update_settings') {
        // Aktualizacja podstawowych ustawień
        $siteTitle = $_POST['site_title'] ?? '';
        $metaDescription = $_POST['meta_description'] ?? '';
        $metaKeywords = $_POST['meta_keywords'] ?? '';
        $maxAds = $_POST['max_ads'] ?? 10;
        $promotionFee = $_POST['promotion_fee'] ?? 10;
        $smtpServer = $_POST['smtp_server'] ?? '';
        $smtpPort = $_POST['smtp_port'] ?? '';
        $smtpUsername = $_POST['smtp_username'] ?? '';
        $smtpPassword = $_POST['smtp_password'] ?? '';
		$facebookUrl = $_POST['facebook_url'] ?? '';
		$twitterUrl = $_POST['twitter_url'] ?? '';
		$instagramUrl = $_POST['instagram_url'] ?? '';
		$linkedinUrl = $_POST['linkedin_url'] ?? '';
		$contactEmail = $_POST['contact_email'] ?? '';
		$contactPhone = $_POST['contact_phone'] ?? '';
		$contactAddress = $_POST['contact_address'] ?? '';
		$businessHours = $_POST['business_hours'] ?? '';
        
        // Obsługa uploadu logo
        $logoName = $currentSettings['logo']; // Zachowaj stare logo domyślnie
        
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../img/';
            $fileExtension = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            
            if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                // Usuń stare logo (jeśli nie jest domyślne)
                if ($currentSettings['logo'] !== 'default-logo.png' && file_exists($uploadDir . $currentSettings['logo'])) {
                    unlink($uploadDir . $currentSettings['logo']);
                }
                
                // Generuj unikalną nazwę pliku
                $logoName = uniqid() . '.' . $fileExtension;
                $uploadFile = $uploadDir . $logoName;
                
                if (!move_uploaded_file($_FILES['site_logo']['tmp_name'], $uploadFile)) {
                    $errorMessage = "Błąd podczas uploadu pliku!";
                    $logoName = $currentSettings['logo']; // Przywróć stare logo
                }
            } else {
                $errorMessage = "Nieprawidłowy format pliku! Dozwolone formaty: " . implode(', ', $allowedExtensions);
            }
        }
        
        // Przygotuj dane do aktualizacji
        $settingsData = [
    'title' => $siteTitle,
    'logo' => $logoName,
    'meta_description' => $metaDescription,
    'meta_keywords' => $metaKeywords,
    'max_ads' => $maxAds,
    'promotion_fee' => $promotionFee,
    'smtp_server' => $smtpServer,
    'smtp_port' => $smtpPort,
    'smtp_username' => $smtpUsername,
    'smtp_password' => $smtpPassword,
    'facebook_url' => $facebookUrl,
    'twitter_url' => $twitterUrl,
    'instagram_url' => $instagramUrl,
    'linkedin_url' => $linkedinUrl,
    'contact_email' => $contactEmail,
    'contact_phone' => $contactPhone,
    'contact_address' => $contactAddress,
    'business_hours' => $businessHours
];
        
        // Aktualizuj ustawienia w bazie danych
        if ($settingsModel->updateSettings($settingsData)) {
            $successMessage = "Ustawienia strony zostały zaktualizowane!";
            // Odśwież bieżące ustawienia
            $currentSettings = $settingsModel->getSettings();
        } else {
            $errorMessage = "Błąd podczas aktualizacji ustawień!";
        }

        // Rejestrowanie zmian ustawień
        $settingsModel->logSettingsChange($_SESSION['user_id'], 'Zaktualizowane ustawienia strony i SMTP', date('Y-m-d H:i:s'));
    }

    // Obsługa błędów - Monitorowanie błędów PHP i MySQL
    if ($_POST['form_type'] === 'monitor_errors') {
        $errors = $errorLogsModel->getRecentErrors();
    }

    // Obsługa transakcji płatniczych
    if ($_POST['form_type'] === 'log_transaction') {
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
        $amount = isset($_POST['amount']) ? $_POST['amount'] : 0.0;
        $description = isset($_POST['description']) ? $_POST['description'] : '';

        if ($user_id && $amount > 0) {
            $transactionHistoryModel->logTransaction($user_id, $amount, $description, date('Y-m-d H:i:s'));
            $successMessage = "Transakcja została zapisana!";
        }
    }
}

// Dodawanie logów logowań administratorów
$loginHistoryModel->logLogin($_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], date('Y-m-d H:i:s'));
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
                            <h5 class="mb-1"><i class="bi bi-info-square"></i> Ustawienia strony</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($successMessage)): ?>
                                <div class="alert alert-success">
                                    <?php echo $successMessage; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($errorMessage)): ?>
                                <div class="alert alert-danger">
                                    <?php echo $errorMessage; ?>
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
                                            <input type="file" name="site_logo" id="site_logo" class="form-control" accept="image/*">
                                            <small class="text-muted">Dozwolone formaty: JPG, JPEG, PNG, GIF, SVG</small>
                                            <br>
                                            <small class="text-muted">Bieżące logo:</small>
                                            <?php if (file_exists("../../img/" . $currentSettings['logo'])): ?>
                                                <img src="/img/<?php echo htmlspecialchars($currentSettings['logo']); ?>" alt="Logo" style="height: 50px; max-width: 200px;" class="mt-2">
                                            <?php else: ?>
                                                <p class="text-muted mt-2">Brak logo</p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mb-3">
                                            <label for="meta_description" class="form-label">Opis strony (Meta Description)</label>
                                            <textarea name="meta_description" class="form-control"><?php echo htmlspecialchars($currentSettings['meta_description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="meta_keywords" class="form-label">Słowa kluczowe (Meta Keywords)</label>
                                            <textarea name="meta_keywords" class="form-control"><?php echo htmlspecialchars($currentSettings['meta_keywords'] ?? ''); ?></textarea>
                                        </div>

                                       <div class="mb-3">
										<label for="max_ads" class="form-label">Maksymalna liczba ogłoszeń na użytkownika</label>
										<input type="number" name="max_ads" class="form-control" value="<?php echo $currentSettings['max_ads'] ?? 10; ?>"> <!-- Domyślna wartość 10 -->
										</div>

                                        <div class="mb-3">
                                            <label for="promotion_fee" class="form-label">Opłata za promowanie ogłoszeń (w PLN)</label>
                                            <input type="number" step="0.01" name="promotion_fee" class="form-control" value="<?php echo $currentSettings['promotion_fee'] ?? 10; ?>">
                                        </div>
										
                                        <div class="mb-3">
                                            <label for="smtp_server" class="form-label">Serwer SMTP</label>
                                            <input type="text" name="smtp_server" id="smtp_server" class="form-control" value="<?php echo htmlspecialchars($currentSettings['smtp_server'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="smtp_port" class="form-label">Port SMTP</label>
                                            <input type="text" name="smtp_port" id="smtp_port" class="form-control" value="<?php echo htmlspecialchars($currentSettings['smtp_port'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="smtp_username" class="form-label">Nazwa użytkownika SMTP</label>
                                            <input type="text" name="smtp_username" id="smtp_username" class="form-control" value="<?php echo htmlspecialchars($currentSettings['smtp_username'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label">Hasło SMTP</label>
                                            <input type="password" name="smtp_password" id="smtp_password" class="form-control" value="<?php echo htmlspecialchars($currentSettings['smtp_password'] ?? ''); ?>" required>
                                        </div>
										
										<h6 class="mt-4 mb-3 border-bottom pb-2">Social Media</h6>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="facebook_url" class="form-label">Facebook URL</label>
        <input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars($currentSettings['facebook_url'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label for="twitter_url" class="form-label">Twitter URL</label>
        <input type="url" name="twitter_url" class="form-control" value="<?php echo htmlspecialchars($currentSettings['twitter_url'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label for="instagram_url" class="form-label">Instagram URL</label>
        <input type="url" name="instagram_url" class="form-control" value="<?php echo htmlspecialchars($currentSettings['instagram_url'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label for="linkedin_url" class="form-label">LinkedIn URL</label>
        <input type="url" name="linkedin_url" class="form-control" value="<?php echo htmlspecialchars($currentSettings['linkedin_url'] ?? ''); ?>">
    </div>
</div>

<!-- Dane kontaktowe -->
<h6 class="mt-4 mb-3 border-bottom pb-2">Dane kontaktowe</h6>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="contact_email" class="form-label">Email kontaktowy</label>
        <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($currentSettings['contact_email'] ?? 'info@jobler.pl'); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label for="contact_phone" class="form-label">Telefon</label>
        <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($currentSettings['contact_phone'] ?? '+48 123 456 789'); ?>">
    </div>
    <div class="col-12 mb-3">
        <label for="contact_address" class="form-label">Adres</label>
        <textarea name="contact_address" class="form-control"><?php echo htmlspecialchars($currentSettings['contact_address'] ?? 'ul. Przykładowa 123, 00-000 Warszawa'); ?></textarea>
    </div>
    <div class="col-12 mb-3">
        <label for="business_hours" class="form-label">Godziny otwarcia</label>
        <input type="text" name="business_hours" class="form-control" value="<?php echo htmlspecialchars($currentSettings['business_hours'] ?? 'Pon-Pt: 8:00-18:00'); ?>">
    </div>
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

                    <div class="card shadow mt-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-bug"></i> Monitorowanie błędów</h5>
                        </div>
                        <div class="card-body">
                            <h6>Ostatnie błędy:</h6>
                            <ul>
                                <?php 
                                $errors = $errorLogsModel->getRecentErrors(); 
                                foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error['error_message']); ?> - <?php echo $error['timestamp']; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="card shadow mt-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-credit-card"></i> Historia transakcji</h5>
                        </div>
                        <div class="card-body">
                            <h6>Ostatnie transakcje:</h6>
                            <ul>
                                <?php 
                                $transactions = $transactionHistoryModel->getRecentTransactions(); 
                                foreach ($transactions as $transaction): ?>
                                    <li><?php echo htmlspecialchars($transaction['description']); ?> - <?php echo $transaction['amount']; ?> PLN - <?php echo $transaction['timestamp']; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
				<div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
			<div class="stupidbottomm"> </div>
        </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>