<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień do wykonania tej operacji.';
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php?status=error&message=invalid_id');
    exit();
}

$userId = (int)$_GET['id'];

// Zabezpieczenie przed przypadkowym usunięciem samego siebie
if ($userId === (int)$_SESSION['user_id']) {
    header('Location: manage_users.php?status=error&message=cannot_delete_self');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Message.php');
include_once('../../models/AdminLogger.php'); // Nowa klasa loggera

$userModel = new User();
$jobModel = new Job();
$transactionModel = new TransactionHistory($pdo);
$messageModel = new Message();
$adminLogger = new AdminLogger(); // Nowy logger

// Funkcje pomocnicze
function logUserDeletion($adminLogger, $adminId, $userId, $userData, $jobs, $transactions) {
    $description = sprintf(
        "Usunięcie użytkownika ID: %d, Email: %s, Nazwa: %s. " .
        "Ilość ogłoszeń: %d, Ilość transakcji: %d, Saldo: %d",
        $userId,
        $userData['email'],
        $userData['name'],
        count($jobs),
        count($transactions),
        $userData['account_balance'] ?? 0
    );
    
    // Zapis do bazy danych
    $adminLogger->logAction(
        $adminId,
        'user_delete',
        $description,
        $userId
    );
    
    // Dodatkowo do error_log dla pewności
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
    // Sprawdź czy użytkownik istnieje
    $user = $userModel->getUserById($userId);
    if (!$user) {
        // Zapisz próbę usunięcia nieistniejącego użytkownika
        $adminLogger->logAction(
            $_SESSION['user_id'],
            'user_delete_attempt',
            "Próba usunięcia nieistniejącego użytkownika ID: $userId",
            $userId
        );
        
        header('Location: manage_users.php?status=error&message=user_not_found');
        exit();
    }
    
    // Pobierz dane administratora
    $adminId = $_SESSION['user_id'];
    $admin = $userModel->getUserById($adminId);
    $adminName = $admin['name'] ?? $admin['username'] ?? 'Administrator';
    
    // Sprawdź zależności przed usunięciem
    $userJobs = $jobModel->getJobsByUserId($userId, 5);
    $userTransactions = $transactionModel->getUserTransactions($userId, 5);
    
    // Zaloguj operację usunięcia
    logUserDeletion($adminLogger, $adminId, $userId, $user, $userJobs, $userTransactions);
    
    // Rozpocznij transakcję
    $pdo->beginTransaction();
    
    // 1. Zamknij ogłoszenia użytkownika
    $jobsClosed = closeUserJobs($pdo, $userId);
    $totalJobs = getJobsCount($pdo, $userId);
    $closedJobs = getClosedJobsCount($pdo, $userId);
    
    if (!$jobsClosed) {
        throw new Exception("Błąd podczas zamykania ogłoszeń użytkownika");
    }
    
    // 2. Wykonaj soft delete użytkownika
    $deleteSuccess = softDeleteUser($pdo, $userId);
    
    if ($deleteSuccess) {
        $pdo->commit();
        header('Location: manage_users.php?status=deleted&jobs_closed=' . $closedJobs . '&total_jobs=' . $totalJobs);
    } else {
        $pdo->rollBack();
        
        // Log błędu
        $adminLogger->logAction(
            $adminId,
            'user_delete_error',
            "Błąd podczas usuwania użytkownika ID: $userId",
            $userId
        );
        
        header('Location: manage_users.php?status=error&message=delete_failed');
    }
    exit();
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log błędu systemowego
    $adminLogger->logAction(
        $_SESSION['user_id'],
        'user_delete_system_error',
        "Błąd systemowy przy usuwaniu użytkownika ID: $userId - " . $e->getMessage(),
        $userId
    );
    
    error_log("Błąd przy usuwaniu użytkownika ID: $userId - " . $e->getMessage());
    header('Location: manage_users.php?status=error&message=system_error');
    exit();
}
?>