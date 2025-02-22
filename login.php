<?php
session_start();
require_once 'config/config.php';
require_once 'models/User.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Tworzenie obiektu klasy User
    $user = new User();

    // Wywołanie metody login
    $loginResult = $user->login($email, $password);

    if ($loginResult !== false) {
        // Sprawdzanie, czy konto jest aktywne
        if (isset($loginResult['error'])) {
            // Jeśli konto zostało zablokowane
            $error = $loginResult['error'];
        } else {
            // Jeśli logowanie się powiodło, zapisz dane użytkownika w sesji
            $_SESSION['user_id'] = $loginResult['id']; // ID użytkownika
            $_SESSION['user_role'] = $loginResult['role']; // Rola użytkownika
            $_SESSION['user_email'] = $loginResult['email']; // Email użytkownika
            $_SESSION['user_name'] = $loginResult['name']; // Zapisz nazwę użytkownika w sesji
			$_SESSION['user_account_balance'] = $loginResult['account_balance'];  //Stan konta zapisany w sesji
            
            header('Location: /'); // Przekierowanie na stronę główną po zalogowaniu
            exit;
        }
    } else {
        // Jeśli logowanie nie powiodło się
        $error = "Nieprawidłowy login lub hasło.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white text-center">
                        <h2 class="h4 mb-0">Logowanie</h2>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" name="email" id="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Hasło:</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Zaloguj się</button>
                            </div>
                        </form>
                        <p class="text-center mt-3">
                            Nie masz konta? <a href="/register.php" class="text-primary">Zarejestruj się</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
