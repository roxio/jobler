<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess('roles.manage');
include_once('../../models/User.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['user_id'])) {
    header('Location: manage_users.php');
    exit;
}

$userModel = new User();
$userId = (int)$_POST['user_id'];
$currentRole = $_POST['current_role'] ?? 'user';
$newRole = $_POST['new_role'] ?? '';

if ($newRole === '') {
    $newRole = $currentRole === 'user' ? 'executor' : 'user';
}

if (!array_key_exists($newRole, AccessControl::roles())) {
    header('Location: manage_users.php?status=error');
    exit;
}

if ($userModel->changeUserRole($userId, $newRole)) {
    header('Location: manage_users.php?status=role_changed');
    exit;
}

header('Location: manage_users.php?status=error');
exit;
?>
