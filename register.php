<?php
session_start();
require_once 'config/config.php';
require_once 'models/User.php';
require_once 'models/SiteSettings.php';

// Pobieranie ustawień strony
$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';

    // Tworzenie instancji klasy User
    $user = new User();

    // Rejestracja użytkownika
    $result = $user->register($email, $password, $name, $username, $role, $phone);
    
    if (isset($result['success'])) {
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['role'] = $result['role'];
        header('Location: /');
        exit;
    } else {
        $error = $result['error'];
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteSettings['title'] ?? 'Jobler') ?> - Rejestracja</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php 
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/navbar.php'; 
    ?>

    <main class="flex-grow-1">
        <div class="container mt-4 mb-5">
            <!-- Hero section -->
           

            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7">
                    <div class="card shadow-lg border-0">
                        <div class="card-header bg-success text-white text-center py-3">
                            <h3 class="mb-0"><i class="bi bi-person-plus me-2"></i>Rejestracja</h3>
                        </div>
                        <div class="card-body p-4">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form action="register.php" method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label fw-semibold">Imię:</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-person text-muted"></i>
                                            </span>
                                            <input type="text" name="name" id="name" class="form-control border-start-0" 
                                                   placeholder="Twoje imię" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label fw-semibold">Nazwa użytkownika:</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-at text-muted"></i>
                                            </span>
                                            <input type="text" name="username" id="username" class="form-control border-start-0" 
                                                   placeholder="Twój nick" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-semibold">Email:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-envelope text-muted"></i>
                                        </span>
                                        <input type="email" name="email" id="email" class="form-control border-start-0" 
                                               placeholder="Twój adres email" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold">Hasło:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-lock text-muted"></i>
                                        </span>
                                        <input type="password" name="password" id="password" class="form-control border-start-0" 
                                               placeholder="Twoje hasło" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label fw-semibold">Rola:</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-person-badge text-muted"></i>
                                            </span>
                                            <select name="role" id="role" class="form-select border-start-0" required>
                                                <option value="user">Zleceniodawca</option>
                                                <option value="executor">Wykonawca</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label for="phone" class="form-label fw-semibold">Telefon (opcjonalnie):</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-telephone text-muted"></i>
                                            </span>
                                            <input type="text" name="phone" id="phone" class="form-control border-start-0" 
                                                   placeholder="Twój numer telefonu">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-person-plus me-2"></i>Zarejestruj się
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4 pt-3 border-top">
                                <p class="mb-0">
                                    Masz już konto? 
                                    <a href="/login.php" class="text-success fw-semibold text-decoration-none">
                                        Zaloguj się
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php 
    $GLOBALS['siteSettings'] = $siteSettings;
    include 'templates/footer.php'; 
    ?>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>