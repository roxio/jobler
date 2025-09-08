<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień do przeglądania tej strony.';
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Message.php');
include_once('../../models/TransactionHistory.php');

// Sprawdzenie tokena CSRF
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

// Walidacja ID użytkowników
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
            // Przekierowanie do formularza wiadomości
            $_SESSION['bulk_user_ids'] = $userIds;
            header('Location: send_bulk_message.php');
            exit();
            
        default:
            header('Location: manage_users.php?status=error');
            exit();
    }
    
} catch (Exception $e) {
    error_log("Błąd podczas wykonywania akcji zbiorowej: " . $e->getMessage());
    header('Location: manage_users.php?status=error');
    exit();
}

/**
 * Aktywuj zaznaczonych użytkowników
 */
function bulkActivateUsers($userModel, $userIds) {
    $successCount = 0;
    $failedCount = 0;
    
    foreach ($userIds as $userId) {
        if ($userModel->activateUser($userId)) {
            $successCount++;
            
            // Logowanie akcji
            logUserAction($userId, 'bulk_activation', 'Aktywacja konta przez administratora');
            
        } else {
            $failedCount++;
        }
    }
    
    $status = $failedCount > 0 ? 'error' : 'activated';
    header("Location: manage_users.php?status=$status&success=$successCount&failed=$failedCount");
    exit();
}

/**
 * Dezaktywuj zaznaczonych użytkowników
 */
function bulkDeactivateUsers($userModel, $userIds) {
    $successCount = 0;
    $failedCount = 0;
    
    foreach ($userIds as $userId) {
        if ($userModel->deactivateUser($userId)) {
            $successCount++;
            
            // Logowanie akcji
            logUserAction($userId, 'bulk_deactivation', 'Dezaktywacja konta przez administratora');
            
        } else {
            $failedCount++;
        }
    }
    
    $status = $failedCount > 0 ? 'error' : 'deactivated';
    header("Location: manage_users.php?status=$status&success=$successCount&failed=$failedCount");
    exit();
}

/**
 * Usuń zaznaczonych użytkowników
 */
function bulkDeleteUsers($userModel, $userIds) {
    $successCount = 0;
    $failedCount = 0;
    
    foreach ($userIds as $userId) {
        // Sprawdź czy użytkownik ma powiązane dane przed usunięciem
        if (canDeleteUser($userModel, $userId)) {
            if ($userModel->deleteUser($userId)) {
                $successCount++;
                
                // Logowanie akcji
                logUserAction($userId, 'bulk_deletion', 'Usunięcie konta przez administratora');
                
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

/**
 * Eksportuj zaznaczonych użytkowników
 */
function bulkExportUsers($userModel, $userIds) {
    $users = $userModel->getUsersByIds($userIds);
    
    if (empty($users)) {
        header('Location: manage_users.php?status=error');
        exit();
    }
    
    // Ustaw nagłówki dla pobierania pliku
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Nagłówki CSV
    fputcsv($output, [
        'ID', 'Imię', 'Email', 'Rola', 'Status', 
        'Data rejestracji', 'Ostatnie logowanie', 'Saldo', 
        'IP rejestracji', 'Zweryfikowany'
    ]);
    
    // Dane użytkowników
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['role'],
            $user['status'],
            $user['created_at'],
            $user['last_login'] ?? 'Nigdy',
            $user['account_balance'] ?? 0,
            $user['registration_ip'],
            !empty($user['email_verified_at']) ? 'Tak' : 'Nie'
        ]);
    }
    
    fclose($output);
    exit();
}

/**
 * Sprawdź czy użytkownik może być usunięty
 */
function canDeleteUser($userModel, $userId) {
    // Sprawdź czy użytkownik ma aktywne ogłoszenia
    $hasActiveJobs = $userModel->hasActiveJobs($userId);
    
    // Sprawdź czy użytkownik ma oczekujące transakcje
    $hasPendingTransactions = $userModel->hasPendingTransactions($userId);
    
    // Sprawdź czy użytkownik ma niezałatwione konwersacje
    $hasActiveConversations = $userModel->hasActiveConversations($userId);
    
    return !$hasActiveJobs && !$hasPendingTransactions && !$hasActiveConversations;
}

/**
 * Logowanie akcji administratora
 */
function logUserAction($userId, $actionType, $description) {
    // Tutaj możesz dodać logikę logowania akcji do bazy danych
    // np. zapis do tabeli admin_actions_log
    error_log("Admin action: $actionType for user $userId - $description");
}

// Przekierowanie w przypadku błędu
header('Location: manage_users.php?status=error');
exit();
?>