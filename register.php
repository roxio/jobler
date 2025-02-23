<?php
session_start();
require_once 'config/config.php';
require_once 'models/User.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $role = $_POST['role']; // Możliwość wyboru roli (user/executor)
    $phone = isset($_POST['phone']) ? $_POST['phone'] : ''; // Pole telefoniczne, opcjonalne

    // Tworzenie instancji klasy User
    $user = new User();

    // Rejestracja użytkownika
    $result = $user->register($email, $password, $name, $username, $role, $phone);  // Zmieniliśmy sposób wywołania metody
    
    if (isset($result['success'])) {
        // Jeśli rejestracja zakończyła się sukcesem, przekieruj użytkownika
        $_SESSION['user_id'] = $result['id']; // Zwracamy id po rejestracji
        $_SESSION['role'] = $result['role'];  // Zwracamy rolę po rejestracji
        header('Location: /');
        exit;
    } else {
        // W przeciwnym razie wyświetlamy błąd
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white text-center">
                        <h2 class="h4 mb-0">Rejestracja</h2>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        <form action="register.php" method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Imię:</label>
                                <input type="text" name="name" id="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Nazwa użytkownika:</label>
                                <input type="text" name="username" id="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" name="email" id="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Hasło:</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Rola:</label>
                                <select name="role" id="role" class="form-select" required>
                                    <option value="user">Zleceniodawca</option>
                                    <option value="executor">Wykonawca</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefon (opcjonalnie):</label>
                                <input type="text" name="phone" id="phone" class="form-control">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Zarejestruj się</button>
                            </div>
                        </form>
                        <p class="text-center mt-3">
                            Masz już konto? <a href="/login.php" class="text-primary">Zaloguj się</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS Bundle -->
    <script src=".https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
