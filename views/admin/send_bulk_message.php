<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnień do przeglądania tej strony.';
    exit();
}

if (empty($_SESSION['bulk_user_ids'])) {
    header('Location: manage_users.php?status=error');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Message.php');

$userModel = new User();
$messageModel = new Message();

$userIds = $_SESSION['bulk_user_ids'];
$users = $userModel->getUsersByIds($userIds);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sprawdzenie tokena CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_users.php?status=error');
        exit();
    }
    
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $messageType = $_POST['message_type'];
    
    if (empty($subject) || empty($message)) {
        $error = "Temat i treść wiadomości są wymagane.";
    } else {
        $successCount = 0;
        $failedCount = 0;
        $adminId = $_SESSION['user_id'];
        
        foreach ($users as $user) {
            if ($messageModel->sendAdminMessage($adminId, $user['id'], $subject, $message, $messageType)) {
                $successCount++;
                
                // Logowanie akcji
                error_log("Admin sent message to user {$user['id']}: $subject");
                
            } else {
                $failedCount++;
            }
        }
        
        // Wyczyść sesję
        unset($_SESSION['bulk_user_ids']);
        
        $status = $failedCount > 0 ? 'error' : 'message_sent';
        header("Location: manage_users.php?status=$status&success=$successCount&failed=$failedCount");
        exit();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-envelope"></i> Wyślij wiadomość do użytkowników</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Wiadomość zostanie wysłana do <strong><?= count($users) ?></strong> wybranych użytkowników.
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Typ wiadomości</label>
                            <select name="message_type" class="form-select" required>
                                <option value="notification">Powiadomienie systemowe</option>
                                <option value="information">Informacja</option>
                                <option value="warning">Ostrzeżenie</option>
                                <option value="promotion">Promocja</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Temat wiadomości *</label>
                            <input type="text" name="subject" class="form-control" required maxlength="255" 
                                   placeholder="Temat wiadomości" value="<?= isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Treść wiadomości *</label>
                            <textarea name="message" class="form-control" rows="8" required 
                                      placeholder="Treść wiadomości..."><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Podgląd odbiorców</label>
                            <div class="border rounded p-3 bg-light">
                                <small>
                                    <?php 
                                    $userNames = array_map(function($user) {
                                        return htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['email']) . ')';
                                    }, array_slice($users, 0, 5));
                                    
                                    echo implode('<br>', $userNames);
                                    
                                    if (count($users) > 5) {
                                        echo '<br>... i ' . (count($users) - 5) . ' więcej';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="manage_users.php" class="btn btn-secondary">Anuluj</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Wyślij do <?= count($users) ?> użytkowników
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>