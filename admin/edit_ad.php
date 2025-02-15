<?php
include('../config/auth.php');

// Sprawdzenie, czy przekazano ID ogłoszenia
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Nieprawidłowe ID ogłoszenia.");
}

$ad_id = (int)$_GET['id'];

// Pobranie danych ogłoszenia
$query = "
    SELECT * FROM ads WHERE id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $ad_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ogłoszenie nie istnieje.");
}

$ad = $result->fetch_assoc();

// Pobranie dostępnych kategorii i podkategorii
$categories = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL");
$subcategories = $conn->query("SELECT * FROM categories WHERE parent_id IS NOT NULL");

// Obsługa formularza aktualizacji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $offer_value = $_POST['offer_value'];
    $category_id = $_POST['category'];
    $subcategory_id = $_POST['subcategory'] ?: NULL;
    $preferred_time = $_POST['preferred_time'];

    $update_query = "
        UPDATE ads SET
            title = ?,
            location = ?,
            offer_value = ?,
            category_id = ?,
            subcategory_id = ?,
            preferred_time = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssdiisi", $title, $location, $offer_value, $category_id, $subcategory_id, $preferred_time, $ad_id);

    if ($stmt->execute()) {
        echo "<p>Ogłoszenie zostało zaktualizowane!</p>";
    } else {
        echo "<p>Błąd podczas aktualizacji ogłoszenia.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytuj ogłoszenie</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Edytuj ogłoszenie</h1>
    <form method="POST">
        <label>Tytuł:</label>
        <input type="text" name="title" value="<?= htmlspecialchars($ad['title']) ?>" required>

        <label>Lokalizacja:</label>
        <input type="text" name="location" value="<?= htmlspecialchars($ad['location']) ?>" required>

        <label>Wartość oferty (PLN):</label>
        <input type="number" step="0.01" name="offer_value" value="<?= htmlspecialchars($ad['offer_value']) ?>" required>

        <label>Kategoria:</label>
        <select name="category" required>
            <?php while ($row = $categories->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($ad['category_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Podkategoria:</label>
        <select name="subcategory">
            <option value="">Brak</option>
            <?php while ($row = $subcategories->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($ad['subcategory_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Preferowany czas realizacji:</label>
        <input type="text" name="preferred_time" value="<?= htmlspecialchars($ad['preferred_time']) ?>">

        <button type="submit">Zapisz zmiany</button>
    </form>

    <br>
    <a href="ads.php">Powrót do listy ogłoszeń</a>
</body>
</html>
