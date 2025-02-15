<?php
include('../config/auth.php');

// Dodanie nowej kategorii
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = $_POST['category_name'];

    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (name, parent_id) VALUES (?, NULL)");
        $stmt->bind_param("s", $category_name);

        if ($stmt->execute()) {
            echo "<p>Kategoria została dodana!</p>";
        } else {
            echo "<p>Błąd podczas dodawania kategorii.</p>";
        }
    } else {
        echo "<p>Proszę podać nazwę kategorii.</p>";
    }
}

// Pobranie listy kategorii
$query = "SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC";
$categories = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzaj kategoriami</title>
    <link rel="stylesheet" href="../styles/admin.css"> <!-- Użyj odpowiedniej ścieżki do pliku CSS -->
</head>
<body>
<?php include('../includes/header.php'); ?> <!-- Pasek nawigacji -->
    <h1>Zarządzaj kategoriami</h1>

    <!-- Formularz do dodawania kategorii -->
    <form method="POST">
        <label for="category_name">Nazwa kategorii:</label>
        <input type="text" name="category_name" id="category_name" required>
        <button type="submit" name="add_category">Dodaj kategorię</button>
    </form>

    <h2>Istniejące kategorie</h2>
    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nazwa kategorii</th>
                <th>Akcja</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $categories->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td>
                        <a href="edit_category.php?id=<?= $row['id'] ?>">Edytuj</a> | 
                        <a href="delete_category.php?id=<?= $row['id'] ?>">Usuń</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>