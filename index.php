<?php
require_once 'includes/db.php';

try {
    // Testowe zapytanie do bazy danych
    $stmt = $pdo->query("SELECT 1");
} catch (PDOException $e) {
    // Jeśli wystąpi problem z połączeniem z bazą, przekieruj do instalatora
    header("Location: install.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Zleceń</title>
</head>
<body>
    <h1>Witaj w systemie zleceń!</h1>
    <p>Strona główna projektu.</p>
</body>
</html>
