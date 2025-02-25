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

// Obsługa formularza aktualizacji ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    if ($_POST['form_type'] === 'update_settings') {
        // Aktualizacja ustawień SMTP
        $smtpServer = isset($_POST['smtp_server']) ? $_POST['smtp_server'] : '';
        $smtpPort = isset($_POST['smtp_port']) ? $_POST['smtp_port'] : '';
        $smtpUsername = isset($_POST['smtp_username']) ? $_POST['smtp_username'] : '';
        $smtpPassword = isset($_POST['smtp_password']) ? $_POST['smtp_password'] : '';

        if ($smtpServer && $smtpPort && $smtpUsername && $smtpPassword) {
            $settingsModel->updateSMTPSettings($smtpServer, $smtpPort, $smtpUsername, $smtpPassword);
            $successMessage = "Ustawienia SMTP zostały zaktualizowane!";
        } else {
            $errorMessage = "Wszystkie pola SMTP muszą być wypełnione!";
        }

        // Rejestrowanie zmian ustawień
        $settingsModel->logSettingsChange($_SESSION['user_id'], 'Zaktualizowane ustawienia SMTP', date('Y-m-d H:i:s'));

        // Inne zmiany w ustawieniach strony
        // ...
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
                                            <input type="file" name="site_logo" id="site_logo" class="form-control">
                                            <small class="text-muted">Bieżące logo:</small>
                                            <?php if (file_exists("../../img/" . $currentSettings['logo'])): ?>
                                                <img src="/img/<?php echo htmlspecialchars($currentSettings['logo']); ?>" alt="Logo" style="height: 50px;">
                                            <?php else: ?>
                                                <p>Brak logo</p>
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
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
