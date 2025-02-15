<?php
include('../config/auth.php');
require_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_ad'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $author_id = $_SESSION['user_id'];

        $query = $conn->prepare("INSERT INTO ads (title, description, author_id) VALUES (?, ?, ?)");
        $query->bind_param("ssi", $title, $description, $author_id);
        $query->execute();
    }

    if (isset($_POST['delete_ad'])) {
        $ad_id = $_POST['delete_ad'];
        $query = $conn->prepare("DELETE FROM ads WHERE id = ?");
        $query->bind_param("i", $ad_id);
        $query->execute();
    }
}

$result = $conn->query("SELECT ads.*, users.username FROM ads JOIN users ON ads.author_id = users.id");
?>

<h2>Zarządzanie ogłoszeniami</h2>
<table>
    <tr><th>ID</th><th>Tytuł</th><th>Opis</th><th>Autor</th><th>Akcje</th></tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= $row['title']; ?></td>
            <td><?= $row['description']; ?></td>
            <td><?= $row['username']; ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="delete_ad" value="<?= $row['id']; ?>">
                    <button type="submit">Usuń</button>
                </form>
            </td>
        </tr>
    <?php } ?>
</table>

<h3>Dodaj ogłoszenie</h3>
<form method="post">
    <input type="text" name="title" placeholder="Tytuł" required>
    <textarea name="description" placeholder="Opis" required></textarea>
    <button type="submit" name="add_ad">Dodaj</button>
</form>