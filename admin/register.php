<?php
session_start();
include('../config/config.php');

// Obsługa rejestracji użytkownika
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    // Sprawdzenie, czy użytkownik już istnieje
    $query = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $query->bind_param("s", $username);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $error = "Użytkownik o podanej nazwie już istnieje.";
    } else {
        // Szyfrowanie hasła
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Dodanie użytkownika do bazy
        $query = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $query->bind_param("ssss", $username, $hashed_password, $email, $role);
        $query->execute();

        // Po udanej rejestracji przekierowanie do logowania
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja - Jobler</title>
    <link rel="stylesheet" href="../styles/admin.css"> <!-- Plik CSS -->
</head>
<body>

<h2>Rejestracja</h2>

<?php if (isset($error)): ?>
    <div class="error-message"><?= $error; ?></div>
<?php endif; ?>

<form method="post" action="register.php">
    <label for="username">Nazwa użytkownika</label>
    <input type="text" name="username" id="username" required>

    <label for="email">Email</label>
    <input type="email" name="email" id="email" required>

    <label for="password">Hasło</label>
    <input type="password" name="password" id="password" required>

    <label for="role">Rola</label>
    <select name="role" id="role" required>
        <option value="user">Użytkownik</option>
        <option value="admin">Administrator</option>
    </select>

    <button type="submit">Zarejestruj się</button>
</form>

<p>Masz już konto? <a href="login.php">Zaloguj się</a></p>

</body>
</html>