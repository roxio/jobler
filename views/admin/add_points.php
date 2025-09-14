<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień.';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_users.php?status=error');
    exit();
}

// Walidacja tokena CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_users.php?status=error&message=csrf_invalid');
    exit();
}

if (!isset($_POST['user_id']) || !isset($_POST['points_to_add'])) {
    header('Location: manage_users.php?status=error');
    exit();
}

$userId = (int)$_POST['user_id'];
$pointsToAdd = (float)$_POST['points_to_add'];
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if ($pointsToAdd <= 0) {
    header('Location: manage_users.php?status=error_points&message=invalid_amount');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');

$userModel = new User();

try {
    // Sprawdź czy użytkownik istnieje
    $user = $userModel->getUserById($userId);
    if (!$user) {
        header('Location: manage_users.php?status=error&message=user_not_found');
        exit();
    }
    
    // Pobierz dane administratora
    $adminId = $_SESSION['user_id'];
    $admin = $userModel->getUserById($adminId);
    $adminName = $admin['name'] ?? 'Administrator';
    
    // Przygotuj opis transakcji z nazwą administratora
    if (!empty($reason)) {
        $description .= $adminName . " - " . $reason;
    }
	else {
    $description .= " dodał: " . $adminName;
    }
    // Dodaj punkty z zapisem do historii transakcji
    $success = $userModel->addPointsWithTransaction($userId, $pointsToAdd, $description);
    
    if ($success) {
        header('Location: manage_users.php?status=points_added');
    } else {
        header('Location: manage_users.php?status=error_points');
    }
    exit();
    
} catch (Exception $e) {
    error_log("Błąd przy dodawaniu punktów: " . $e->getMessage());
    header('Location: manage_users.php?status=error_points');
    exit();
}
?>