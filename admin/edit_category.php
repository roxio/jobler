<?php
include('../config/auth.php');

// Sprawdzenie, czy id kategorii jest ustawione
if (!isset($_GET['id'])) {
    echo "Brak ID kategorii.";
    exit;
}

$category_id = $_GET['id'];

// Pobranie danych kategorii
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();

// Edytowanie kategorii
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $new_name = $_POST['category_name'];

    if (!empty($new_name)) {
        $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $category_id);

        if ($stmt->execute()) {
            echo "<p>Kategoria została zaktualizowana!</p>";
        } else {
            echo "<p>Błąd podczas aktualizacji kategorii.</p>";
        }
    } else {
        echo "<p>Proszę podać nową nazwę kategorii.</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytuj kategorię</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Edytuj kategorię</h1>

    <form method="POST">
        <label for="category_name">Nazwa kategorii:</label>
        <input type="text" name="category_name" id="category_name" value="<?= htmlspecialchars($category['name']) ?>" required>
        <button type="submit" name="edit_category">Zaktualizuj kategorię</button>
    </form>
</body>
</html>