<?php
// check_online_status.php
session_start();
require_once('../../config/config.php');
require_once('../../models/User.php');
include_once('../../models/Language.php');

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo json_encode(['error' => __t('admin.users.invalid_id')]);
    exit();
}

$userId = (int)$_GET['user_id'];
$userModel = new User();

try {
    $user = $userModel->getUserById($userId);
    if (!$user) {
        echo json_encode(['error' => __t('admin.users.not_found')]);
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
    echo json_encode(['error' => __t('admin.users.error')]);
}
