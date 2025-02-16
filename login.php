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

    // Debugowanie: sprawdźmy, co zwraca metoda login
    var_dump($loginResult); // To pozwoli zobaczyć, co dokładnie jest zwracane

    if ($loginResult !== false) {
        // Jeśli logowanie się powiodło, zapisz dane użytkownika w sesji
        $_SESSION['user_id'] = $loginResult['id']; // ID użytkownika
        $_SESSION['user_role'] = $loginResult['role']; // Rola użytkownika
        $_SESSION['user_email'] = $loginResult['email']; // Email użytkownika
		$_SESSION['user_name'] = $loginResult['name']; // Zapisz nazwę użytkownika w sesji
        
        header('Location: /'); // Przekierowanie na stronę główną po zalogowaniu
        exit;
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
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    <div class="container">
        <h1>Logowanie</h1>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?= $error ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>
            <label for="password">Hasło:</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">Zaloguj się</button>
        </form>
        <p>Nie masz konta? <a href="/register.php">Zarejestruj się</a>.</p>
    </div>
    <?php include 'templates/footer.php'; ?>
</body>
</html>
