<?php
include('../config/auth.php');


// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

// Sprawdzenie roli użytkownika
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Dodawanie lub edytowanie użytkownika
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pobieranie danych z formularza
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    $phone = isset($_POST['phone']) ? $_POST['phone'] : null;
    $address = isset($_POST['address']) ? $_POST['address'] : null;

    // Haszowanie hasła
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Przygotowanie zapytania SQL
    $query = $conn->prepare("INSERT INTO users (username, email, password, role, status, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $query->bind_param("sssssss", $username, $email, $password, $role, $status, $phone, $address);

    // Wykonanie zapytania
    if ($query->execute()) {
        echo "Użytkownik został dodany!";
    } else {
        echo "Błąd zapytania: " . $query->error;
    }

    // Przekierowanie do strony użytkowników po dodaniu
    header("Location: users.php");
    exit();
}

// Pobranie danych użytkowników z bazy
$query = "SELECT * FROM users";
$result = $conn->query($query);

if ($conn->errno) {
    echo "Błąd bazy danych: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Użytkownikami</title>
    <link rel="stylesheet" href="../styles/admin.css">
</head>
<body>
    <div class="container">
        <h2>Dodaj nowego użytkownika</h2>

        <!-- Formularz dodawania użytkownika -->
        <form method="POST" action="users.php">
            <label for="username">Nazwa użytkownika:</label>
            <input type="text" id="username" name="username" required><br><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required><br><br>

            <label for="password">Hasło:</label>
            <input type="password" id="password" name="password" required><br><br>

            <label for="role">Rola:</label>
            <select id="role" name="role" required>
                <option value="admin">Administrator</option>
                <option value="superuser">Super User</option>
                <option value="moderator">Moderator</option>
                <option value="zleceniodawca">Zleceniodawca</option>
                <option value="zleceniobiorca">Zleceniobiorca</option>
            </select><br><br>

            <label for="status">Status:</label>
            <select id="status" name="status" required>
                <option value="active">Aktywny</option>
                <option value="inactive">Nieaktywny</option>
            </select><br><br>

            <label for="phone">Telefon (opcjonalnie):</label>
            <input type="text" id="phone" name="phone"><br><br>

            <label for="address">Adres (opcjonalnie):</label>
            <input type="text" id="address" name="address"><br><br>

            <input type="submit" value="Dodaj użytkownika">
        </form>

        <h2>Lista użytkowników</h2>

        <table>
            <tr>
                <th>ID</th>
                <th>Użytkownik</th>
                <th>Email</th>
                <th>Rola</th>
                <th>Status</th>
                <th>Telefon</th>
                <th>Adres</th>
                <th>Akcja</th>
            </tr>

            <?php
            // Wyświetlanie listy użytkowników
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['username']}</td>
                        <td>{$row['email']}</td>
                        <td>{$row['role']}</td>
                        <td>{$row['status']}</td>
                        <td>{$row['phone']}</td>
                        <td>{$row['address']}</td>
                        <td><a href='edit_user.php?id={$row['id']}'>Edytuj</a> | <a href='delete_user.php?id={$row['id']}'>Usuń</a></td>
                    </tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>
