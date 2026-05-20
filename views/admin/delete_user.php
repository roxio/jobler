<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php?status=error&message=invalid_id');
    exit();
}

$userId = (int)$_GET['id'];


if ($userId === (int)$_SESSION['user_id']) {
    header('Location: manage_users.php?status=error&message=cannot_delete_self');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Message.php');
include_once('../../models/AdminLogger.php');
include_once('../../models/Language.php');

$userModel = new User();
$jobModel = new Job();
$transactionModel = new TransactionHistory($pdo);
$messageModel = new Message();
$adminLogger = new AdminLogger();


function logUserDeletion($adminLogger, $adminId, $userId, $userData, $jobs, $transactions) {
    $description = __t('admin.delete_user.log_description', [
        'id' => $userId,
        'email' => $userData['email'],
        'name' => $userData['name'],
        'jobs' => count($jobs),
        'transactions' => count($transactions),
        'balance' => $userData['account_balance'] ?? 0,
    ]);


    $adminLogger->logAction(
        $adminId,
        'user_delete',
        $description,
        $userId
    );


    error_log("ADMIN DELETE: " . $description);
}

function softDeleteUser($pdo, $userId) {
    $query = "UPDATE users SET
              status = 'deleted',
              email = CONCAT('deleted_', id, '_@ ', UNIX_TIMESTAMP(NOW()), ''),
              name = 'Deleted',
              username = CONCAT('deleted_', id),
              account_balance = 0,
              updated_at = NOW()
              WHERE id = :user_id";

    $stmt = $pdo->prepare($query);
    return $stmt->execute([':user_id' => $userId]);
}

function closeUserJobs($pdo, $userId) {
    $query = "UPDATE jobs SET status = 'deleted', updated_at = NOW() WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    return $stmt->execute();
}

function getJobsCount($pdo, $userId) {
    $query = "SELECT COUNT(*) as count FROM jobs WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

function getClosedJobsCount($pdo, $userId) {
    $query = "SELECT COUNT(*) as count FROM jobs WHERE user_id = :user_id AND status = 'closed'";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

try {

    $user = $userModel->getUserById($userId);
    if (!$user) {

        $adminLogger->logAction(
            $_SESSION['user_id'],
            'user_delete_attempt',
            __t('admin.delete_user.not_found_attempt', ['id' => $userId]),
            $userId
        );

        header('Location: manage_users.php?status=error&message=user_not_found');
        exit();
    }


    $adminId = $_SESSION['user_id'];
    $admin = $userModel->getUserById($adminId);
    $adminName = $admin['name'] ?? $admin['username'] ?? 'Administrator';


    $userJobs = $jobModel->getJobsByUserId($userId, 5);
    $userTransactions = $transactionModel->getUserTransactions($userId, 5);


    logUserDeletion($adminLogger, $adminId, $userId, $user, $userJobs, $userTransactions);


    $pdo->beginTransaction();


    $jobsClosed = closeUserJobs($pdo, $userId);
    $totalJobs = getJobsCount($pdo, $userId);
    $closedJobs = getClosedJobsCount($pdo, $userId);

    if (!$jobsClosed) {
        throw new Exception(__t('admin.delete_user.close_jobs_error'));
    }


    $deleteSuccess = softDeleteUser($pdo, $userId);

    if ($deleteSuccess) {
        $pdo->commit();
        header('Location: manage_users.php?status=deleted&jobs_closed=' . $closedJobs . '&total_jobs=' . $totalJobs);
    } else {
        $pdo->rollBack();


        $adminLogger->logAction(
            $adminId,
            'user_delete_error',
            __t('admin.delete_user.delete_error_log', ['id' => $userId]),
            $userId
        );

        header('Location: manage_users.php?status=error&message=delete_failed');
    }
    exit();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }


    $adminLogger->logAction(
        $_SESSION['user_id'],
        'user_delete_system_error',
        __t('admin.delete_user.system_error_log', ['id' => $userId, 'error' => $e->getMessage()]),
        $userId
    );

    error_log(__t('admin.delete_user.error_log', ['id' => $userId, 'error' => $e->getMessage()]));
    header('Location: manage_users.php?status=error&message=system_error');
    exit();
}
?>
