<?php
require_once __DIR__ . '/_auth.php';
requireAdminAccess();
include_once('../../models/User.php');


$userModel = new User();


if (isset($_POST['user_ids']) && !empty($_POST['user_ids'])) {

    $userIds = $_POST['user_ids'];


    foreach ($userIds as $userId) {
        $userModel->deleteUser($userId);
    }


    header('Location: ../admin/manage_users.php?status=deleted');
    exit();
} else {

    header('Location: ../admin/manage_users.php?status=error');
    exit();
}
?>