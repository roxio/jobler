<?php
session_start();
include('../config/config.php');

// Sprawdzenie, czy użytkownik jest już zalogowany
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Obsługa logowania
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Pobranie danych użytkownika z bazy
    $query = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $query->bind_param("s", $username);
    $query->execute();
    $result = $query->get_result();

    // Sprawdzenie, czy użytkownik istnieje
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Weryfikacja hasła
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Przekierowanie do panelu administracyjnego
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Nieprawidłowe hasło.";
        }
    } else {
        $error = "Użytkownik o podanej nazwie nie istnieje.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Jobler</title>
    <link rel="stylesheet" href="../styles/admin.css"> <!-- Plik CSS -->
</head>
<body>

<h2>Logowanie</h2>

<?php if (isset($error)): ?>
    <div class="error-message"><?= $error; ?></div>
<?php endif; ?>

<form method="post" action="login.php">
    <label for="username">Nazwa użytkownika</label>
    <input type="text" name="username" id="username" required>

    <label for="password">Hasło</label>
    <input type="password" name="password" id="password" required>

    <button type="submit">Zaloguj się</button>
</form>

<p>Nie masz konta? <a href="register.php">Zarejestruj się</a></p>

</body>
</html>