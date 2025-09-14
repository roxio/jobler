<?php
// Ustawienia bazy danych
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobler');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_URL', 'http://localhost/');


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('USER_SESSION', 'user_id');
define('USER_ROLE', 'user_role');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

define('MAIL_HOST', 'smtp.example.com');
define('MAIL_USERNAME', 'your_email@example.com');
define('MAIL_PASSWORD', 'your_password');
define('MAIL_PORT', 587);

define('DATE_FORMAT', 'Y-m-d H:i:s');
define('TIMEZONE', 'Europe/Warsaw');
?>
