<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Message.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Language.php');


if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_users.php?status=error');
    exit();
}

$userModel = new User();
$messageModel = new Message();
$transactionModel = new TransactionHistory($pdo);

$action = isset($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
$userIds = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];

if (empty($action) || empty($userIds)) {
    header('Location: manage_users.php?status=error');
    exit();
}


$userIds = array_filter($userIds, function($id) {
    return is_numeric($id) && $id > 0;
});

if (empty($userIds)) {
    header('Location: manage_users.php?status=error');
    exit();
}

try {
    switch ($action) {
        case 'activate':
            bulkActivateUsers($userModel, $userIds);
            break;

        case 'deactivate':
            bulkDeactivateUsers($userModel, $userIds);
            break;

        case 'delete':
            bulkDeleteUsers($userModel, $userIds);
            break;

        case 'export':
            bulkExportUsers($userModel, $userIds);
            break;

        case 'message':

            $_SESSION['bulk_user_ids'] = $userIds;
            header('Location: send_bulk_message.php');
            exit();

        default:
            header('Location: manage_users.php?status=error');
            exit();
    }

} catch (Exception $e) {
    error_log(__t('admin.logs.bulk_action_error', ['error' => $e->getMessage()]));
    header('Location: manage_users.php?status=error');
    exit();
}




function bulkActivateUsers($userModel, $userIds) {
    $successCount = 0;
    $failedCount = 0;

    foreach ($userIds as $userId) {
        if ($userModel->activateUser($userId)) {
            $successCount++;


            logUserAction($userId, 'bulk_activation', __t('admin.bulk.log_activation'));

        } else {
            $failedCount++;
        }
    }

    $status = $failedCount > 0 ? 'error' : 'activated';
    header("Location: manage_users.php?status=$status&success=$successCount&failed=$failedCount");
    exit();
}




function bulkDeactivateUsers($userModel, $userIds) {
    $successCount = 0;
    $failedCount = 0;

    foreach ($userIds as $userId) {
        if ($userModel->deactivateUser($userId)) {
            $successCount++;


            logUserAction($userId, 'bulk_deactivation', __t('admin.bulk.log_deactivation'));

        } else {
            $failedCount++;
        }
    }

    $status = $failedCount > 0 ? 'error' : 'deactivated';
    header("Location: manage_users.php?status=$status&success=$successCount&failed=$failedCount");
    exit();
}




function bulkDeleteUsers($userModel, $userIds) {
    $successCount = 0;
    $failedCount = 0;

    foreach ($userIds as $userId) {

        if (canDeleteUser($userModel, $userId)) {
            if ($userModel->deleteUser($userId)) {
                $successCount++;


                logUserAction($userId, 'bulk_deletion', __t('admin.bulk.log_deletion'));

            } else {
                $failedCount++;
            }
        } else {
            $failedCount++;
        }
    }

    $status = $failedCount > 0 ? 'error' : 'deleted';
    header("Location: manage_users.php?status=$status&success=$successCount&failed=$failedCount");
    exit();
}




function bulkExportUsers($userModel, $userIds) {
    $users = $userModel->getUsersByIds($userIds);

    if (empty($users)) {
        header('Location: manage_users.php?status=error');
        exit();
    }


    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i') . '.csv"');

    $output = fopen('php://output', 'w');


    fputcsv($output, [
        __t('admin.reports.col.id'), __t('admin.edit_user.full_name'), __t('admin.reports.col.email'), __t('admin.common.role'), __t('admin.common.status'),
        __t('admin.users.registration_date'), __t('admin.users.last_login'), __t('admin.common.balance'),
        __t('admin.users.registration_ip'), __t('admin.users.verified')
    ]);


    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['role'],
            $user['status'],
            $user['created_at'],
            $user['last_login'] ?? __t('admin.common.never'),
            $user['account_balance'] ?? 0,
            $user['registration_ip'],
            !empty($user['email_verified_at']) ? __t('admin.users.yes') : __t('admin.users.no')
        ]);
    }

    fclose($output);
    exit();
}




function canDeleteUser($userModel, $userId) {

    $hasActiveJobs = $userModel->hasActiveJobs($userId);


    $hasPendingTransactions = $userModel->hasPendingTransactions($userId);


    $hasActiveConversations = $userModel->hasActiveConversations($userId);

    return !$hasActiveJobs && !$hasPendingTransactions && !$hasActiveConversations;
}




function logUserAction($userId, $actionType, $description) {


    error_log("Admin action: $actionType for user $userId - $description");
}


header('Location: manage_users.php?status=error');
exit();
?>
