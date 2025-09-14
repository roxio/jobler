<?php
// check_online_status.php
session_start();
require_once('../../config/config.php');
require_once('../../models/User.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Brak uprawnień']);
    exit();
}

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo json_encode(['error' => 'Nieprawidłowe ID użytkownika']);
    exit();
}

$userId = (int)$_GET['user_id'];
$userModel = new User();

try {
    $user = $userModel->getUserById($userId);
    if (!$user) {
        echo json_encode(['error' => 'Użytkownik nie istnieje']);
        exit();
    }

    // Sprawdź czy użytkownik jest online (ostatnie 15 minut)
    $isOnline = false;
    if (!empty($user['last_login'])) {
        $lastLogin = strtotime($user['last_login']);
        $isOnline = (time() - $lastLogin) < 900; // 15 minut
    }

    echo json_encode(['online' => $isOnline]);
    
} catch (Exception $e) {
    error_log("Błąd przy sprawdzaniu statusu online: " . $e->getMessage());
    echo json_encode(['error' => 'Wystąpił błąd']);
}