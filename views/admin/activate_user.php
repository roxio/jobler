<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();
include_once('../../models/User.php');

if (isset($_GET['id'])) {
    $userId = $_GET['id'];
    $userModel = new User();


    if ($userModel->activateUser($userId)) {
        header('Location: manage_users.php?status=activated');
    } else {
        header('Location: manage_users.php?status=error');
    }
} else {
    header('Location: manage_users.php?status=error');
}
?>
