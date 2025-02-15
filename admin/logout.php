<?php
session_start();

// Usuwanie wszystkich zmiennych sesyjnych
$_SESSION = array();

// Jeśli sesja jest zapisana w pliku cookie, usuwamy także pliki cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Zniszczenie sesji
session_destroy();

// Przekierowanie na stronę logowania po wylogowaniu
header("Location: login.php");
exit();
?>