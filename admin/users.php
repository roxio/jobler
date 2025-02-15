<?php
include('../config/auth.php');
require_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_POST['email'];
        $role = $_POST['role'];

        $query = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $query->bind_param("ssss", $username, $password, $email, $role);
        $query->execute();
    }

    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['delete_user'];
        $query = $conn->prepare("DELETE FROM users WHERE id = ?");
        $query->bind_param("i", $user_id);
        $query->execute();
    }
}

$result = $conn->query("SELECT * FROM users");
?>

<h2>Zarządzanie użytkownikami</h2>
<table>
    <tr><th>ID</th><th>Nazwa</th><th>Email</th><th>Rola</th><th>Akcje</th></tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= $row['username']; ?></td>
            <td><?= $row['email']; ?></td>
            <td><?= $row['role']; ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="delete_user" value="<?= $row['id']; ?>">
                    <button type="submit">Usuń</button>
                </form>
            </td>
        </tr>
    <?php } ?>
</table>

<h3>Dodaj użytkownika</h3>
<form method="post">
    <input type="text" name="username" placeholder="Nazwa użytkownika" required>
    <input type="password" name="password" placeholder="Hasło" required>
    <input type="email" name="email" placeholder="Email" required>
    <select name="role">
        <option value="admin">Administrator</option>
        <option value="user">Użytkownik</option>
    </select>
    <button type="submit" name="add_user">Dodaj</button>
</form>