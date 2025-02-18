<?php
include_once('../../models/User.php');

// Uzyskanie połączenia z bazą danych
$userModel = new User();

// Pobranie ID użytkownika z GET
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId) {
    // Dezaktywowanie konta użytkownika
    $userModel->deactivateUser($userId);
    header('Location: manage_users.php?status=deactivated');
    exit;
} else {
    header('Location: manage_users.php?status=error');
    exit;
}
