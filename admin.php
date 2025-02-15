<?php
include 'includes/db.php';
include('config.php'); // Załaduj plik konfiguracyjny
session_start();

//if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
//    die("Brak dostępu");
//}
if (!isset($conn)) {
    die("Błąd: Połączenie z bazą nie istnieje.");
}
?>
	<?php
// Dodawanie nowego użytkownika
if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $role = $_POST['role'];

    $query = "INSERT INTO users (username, password, email, role) VALUES ('$username', '$password', '$email', '$role')";
    mysqli_query($conn, $query);
}

// Usuwanie użytkownika
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    $query = "DELETE FROM users WHERE id = $user_id";
    mysqli_query($conn, $query);
}

// Pobieranie listy użytkowników
$query = "SELECT * FROM users";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Panel Administratora</h1>
    <nav>
        <a href="admin.php">Dashboard</a> |
        <a href="admin_users.php">Zarządzanie użytkownikami</a> |
        <a href="admin_jobs.php">Zarządzanie zleceniami</a>
    </nav>
    <h2>Podsumowanie:</h2>
    <ul>
        <?php
        $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $job_count = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
        $open_jobs = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'open'")->fetchColumn();
        $total_budget = $pdo->query("SELECT SUM(budget) FROM jobs")->fetchColumn();

        echo "<li>Łączna liczba użytkowników: $user_count</li>";
        echo "<li>Łączna liczba zleceń: $job_count</li>";
        echo "<li>Otwarte zlecenia: $open_jobs</li>";
        echo "<li>Łączny budżet zleceń: $total_budget PLN</li>";
        ?>
    </ul>
	


<h2>Zarządzanie użytkownikami</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Nazwa użytkownika</th>
        <th>Email</th>
        <th>Rola</th>
        <th>Akcje</th>
    </tr>
    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo $row['username']; ?></td>
        <td><?php echo $row['email']; ?></td>
        <td><?php echo $row['role']; ?></td>
        <td>
            <a href="admin.php?edit_user=<?php echo $row['id']; ?>">Edytuj</a>
            <a href="admin.php?delete_user=<?php echo $row['id']; ?>">Usuń</a>
        </td>
    </tr>
    <?php } ?>
</table>

<h3>Dodaj nowego użytkownika</h3>
<form method="post" action="admin.php">
    <input type="text" name="username" placeholder="Nazwa użytkownika" required>
    <input type="password" name="password" placeholder="Hasło" required>
    <input type="email" name="email" placeholder="Email" required>
    <select name="role">
        <option value="admin">Administrator</option>
        <option value="user">Użytkownik</option>
    </select>
    <button type="submit" name="add_user">Dodaj</button>
</form>

</body>
</html>
