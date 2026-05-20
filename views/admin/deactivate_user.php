<?php
require_once __DIR__ . '/_auth.php';
requireAdminAccess();
include_once('../../models/User.php');


$userModel = new User();


$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId) {

    $userModel->deactivateUser($userId);
    header('Location: manage_users.php?status=deactivated');
    exit;
} else {
    header('Location: manage_users.php?status=error');
    exit;
}
