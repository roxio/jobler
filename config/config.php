<?php

// Ustawienia bazy danych
define('DB_HOST', 'localhost');        // Host bazy danych
define('DB_NAME', 'jobler');       // Nazwa bazy danych
define('DB_USER', 'root');             // Użytkownik bazy danych
define('DB_PASS', '');                 // Hasło bazy danych

// Ustawienia aplikacji
define('APP_URL', 'http://localhost/'); // Główna URL aplikacji
define('APP_NAME', 'Ogłoszenia Online');          // Nazwa aplikacji

// Ustawienia sesji
//session_start(); // Rozpoczyna sesję (powinna być wywoływana na początku każdej strony)

if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Rozpoczyna sesję, jeśli nie została jeszcze rozpoczęta
}

// Sprawdzanie, czy użytkownik jest zalogowany
define('USER_SESSION', 'user_id');
define('USER_ROLE', 'user_role');

// Połączenie z bazą danych
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    // Ustawienie trybu błędów na wyjątki
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // W przypadku błędu połączenia z bazą danych
    die("Connection failed: " . $e->getMessage());
}

// Ustawienia dla maili (jeśli wymagane w aplikacji)
define('MAIL_HOST', 'smtp.example.com');    // Serwer SMTP
define('MAIL_USERNAME', 'your_email@example.com'); // Adres email do wysyłania
define('MAIL_PASSWORD', 'your_password');    // Hasło do konta email
define('MAIL_PORT', 587);                   // Port SMTP

// Inne globalne ustawienia
define('DATE_FORMAT', 'Y-m-d H:i:s');  // Format daty
define('TIMEZONE', 'Europe/Warsaw');   // Strefa czasowa

?>
