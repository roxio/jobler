<?php
include('../config/auth.php');

// Sprawdzenie, czy id kategorii jest ustawione
if (!isset($_GET['id'])) {
    echo "Brak ID kategorii.";
    exit;
}

$category_id = $_GET['id'];

// Usuwanie kategorii
$stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);

if ($stmt->execute()) {
    echo "<p>Kategoria została usunięta.</p>";
    echo "<p><a href='categories.php'>Powrót do zarządzania kategoriami</a></p>";
} else {
    echo "<p>Błąd podczas usuwania kategorii.</p>";
}
?>