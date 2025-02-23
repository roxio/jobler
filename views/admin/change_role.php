<?php
include_once('../../models/User.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['current_role'])) {
    $userModel = new User();
    $user_id = intval($_POST['user_id']);
    $current_role = $_POST['current_role'];

    // Określamy nową rolę
    $new_role = ($current_role == 'user') ? 'executor' : 'user';

    // Wykonujemy zmianę w bazie
    if ($userModel->changeUserRole($user_id, $new_role)) {
        header('Location: manage_users.php?status=role_changed');
        exit;
    } else {
        header('Location: manage_users.php?status=error');
        exit;
    }
} else {
    header('Location: manage_users.php');
    exit;
}
