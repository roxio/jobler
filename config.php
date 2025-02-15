<?php
define('SITE_NAME', 'System Zleceń');
define('BASE_URL', 'http://localhost/project/');

$servername = "localhost"; // lub adres serwera
$username = "root"; // użytkownik bazy danych
$password = ""; // hasło bazy danych
$database = "jobler"; // nazwa bazy danych

// Połączenie z bazą
$conn = new mysqli($servername, $username, $password, $database);

// Sprawdzenie połączenia
if ($conn->connect_error) {
    die("Błąd połączenia z bazą danych: " . $conn->connect_error);
}
?>
