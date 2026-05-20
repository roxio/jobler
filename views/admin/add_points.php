<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_users.php?status=error');
    exit();
}


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
include_once('../../models/Language.php');

$userModel = new User();

try {

    $user = $userModel->getUserById($userId);
    if (!$user) {
        header('Location: manage_users.php?status=error&message=user_not_found');
        exit();
    }


    $adminId = $_SESSION['user_id'];
    $admin = $userModel->getUserById($adminId);
    $adminName = $admin['name'] ?? 'Administrator';


    $description = '';
    if (!empty($reason)) {
        $description = $adminName . " - " . $reason;
    }
	else {
        $description = __t('admin.points.added_by', ['name' => $adminName]);
    }

    $success = $userModel->addPointsWithTransaction($userId, $pointsToAdd, $description);

    if ($success) {
        header('Location: manage_users.php?status=points_added');
    } else {
        header('Location: manage_users.php?status=error_points');
    }
    exit();

} catch (Exception $e) {
    error_log(__t('admin.points.add_error_log', ['error' => $e->getMessage()]));
    header('Location: manage_users.php?status=error_points');
    exit();
}
?>
