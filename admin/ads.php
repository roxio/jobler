<?php
include('../config/auth.php');
// Pobranie dostępnych użytkowników, kategorii i podkategorii
$users = $conn->query("SELECT id, username FROM users");
$categories = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL");
$subcategories = $conn->query("SELECT * FROM categories WHERE parent_id IS NOT NULL");

// Obsługa dodawania nowego ogłoszenia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ad'])) {
    $user_id = $_POST['user_id'];
    $title = $_POST['title'];
    $location = $_POST['location'];
    $offer_value = $_POST['offer_value'];
    $category_id = $_POST['category'];
    $subcategory_id = !empty($_POST['subcategory']) ? $_POST['subcategory'] : NULL;
    $preferred_time = $_POST['preferred_time'];

    $insert_query = "
        INSERT INTO ads (user_id, title, location, offer_value, category_id, subcategory_id, preferred_time, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ";

    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("issdiis", $user_id, $title, $location, $offer_value, $category_id, $subcategory_id, $preferred_time);

    if ($stmt->execute()) {
        echo "<p>Ogłoszenie zostało dodane!</p>";
    } else {
        echo "<p>Błąd podczas dodawania ogłoszenia.</p>";
    }
}

// Pobranie listy ogłoszeń
$query = "
    SELECT 
        ads.id, ads.title, ads.location, ads.offer_value, ads.preferred_time,
        users.username, users.verified, users.rating,
        categories.name AS category, subcategories.name AS subcategory,
        (SELECT COUNT(*) FROM ads WHERE user_id = users.id AND status = 'completed') AS completed_ads,
        (SELECT COUNT(*) FROM ads WHERE user_id = users.id AND status = 'active') AS active_ads
    FROM ads
    JOIN users ON ads.user_id = users.id
    JOIN categories ON ads.category_id = categories.id
    LEFT JOIN categories AS subcategories ON ads.subcategory_id = subcategories.id
    ORDER BY ads.created_at DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista ogłoszeń</title>
    <link rel="stylesheet" href="../styles/admin.css"> <!-- Użyj odpowiedniej ścieżki do pliku CSS -->
</head>
<body>
<?php include('../includes/header.php'); ?> <!-- Pasek nawigacji -->
    <h1>Lista ogłoszeń</h1>

    <h2>Dodaj nowe ogłoszenie</h2>
    <form method="POST">
        <label>Użytkownik:</label>
        <select name="user_id" required>
            <?php while ($row = $users->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['username']) ?></option>
            <?php endwhile; ?>
        </select>

        <label>Tytuł:</label>
        <input type="text" name="title" required>

        <label>Lokalizacja:</label>
        <input type="text" name="location" required>

        <label>Wartość oferty (PLN):</label>
        <input type="number" step="0.01" name="offer_value" required>

        <label>Kategoria:</label>
        <select name="category" required>
            <?php while ($row = $categories->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
            <?php endwhile; ?>
        </select>

        <label>Podkategoria:</label>
        <select name="subcategory">
            <option value="">Brak</option>
            <?php while ($row = $subcategories->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
            <?php endwhile; ?>
        </select>

        <label>Preferowany czas realizacji:</label>
        <input type="text" name="preferred_time">

        <button type="submit" name="add_ad">Dodaj ogłoszenie</button>
    </form>

    <h2>Lista ogłoszeń</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Tytuł</th>
                <th>Lokalizacja</th>
                <th>Wartość oferty</th>
                <th>Kategoria</th>
                <th>Podkategoria</th>
                <th>Preferowany czas realizacji</th>
                <th>Użytkownik</th>
                <th>Zakończone ogłoszenia</th>
                <th>Aktywne ogłoszenia</th>
                <th>Zweryfikowany</th>
                <th>Ocena</th>
                <th>Akcja</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['location']) ?></td>
                    <td><?= number_format($row['offer_value'], 2) ?> PLN</td>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><?= htmlspecialchars($row['subcategory'] ?? 'Brak') ?></td>
                    <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= $row['completed_ads'] ?></td>
                    <td><?= $row['active_ads'] ?></td>
                    <td><?= $row['verified'] ? 'Tak' : 'Nie' ?></td>
                    <td><?= number_format($row['rating'], 1) ?>/5</td>
                    <td>
                        <a href="edit_ad.php?id=<?= $row['id'] ?>">Edytuj</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
