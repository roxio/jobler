<?php
session_start();
require_once 'config/config.php';
require_once 'models/User.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = 'user'; // Domyślnie użytkownik

    // Tworzenie instancji klasy User
    $user = new User();

    // Rejestracja użytkownika
    $result = $user->register($email, $password, $role);  // Zmieniliśmy sposób wywołania metody
    
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
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    <div class="container">
        <h1>Rejestracja</h1>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?= $error ?></p>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>
            <label for="password">Hasło:</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">Zarejestruj się</button>
        </form>
        <p>Masz już konto? <a href="/login.php">Zaloguj się</a>.</p>
    </div>
    <?php include 'templates/footer.php'; ?>
</body>
</html>