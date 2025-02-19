<?php
session_start();
require_once('../../models/Message.php');
require_once('../../models/User.php');

// Sprawdzenie, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

// Pobieranie danych z URL
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
$executorId = isset($_GET['executor_id']) ? (int)$_GET['executor_id'] : null;
$userId = $_SESSION['user_id'];

// Jeśli nie ma job_id lub executor_id, to zakończ działanie
if (!$jobId || !$executorId) {
    die('Nieprawidłowe parametry.');
}

// Generowanie conversation_id na podstawie user_id i executor_id
$conversationId = min($userId, $executorId) . "_" . max($userId, $executorId);

// Tworzenie instancji modeli
$messageModel = new Message();
$userModel = new User();

// Pobieranie wiadomości z bazy danych na podstawie conversation_id
$messages = $messageModel->getConversation($userId, $executorId, $conversationId);

// Obsługa formularza wysyłania wiadomości
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $messageContent = trim($_POST['message']);

    if (!empty($messageContent)) {
        // Jeśli użytkownik wysyła wiadomość do wykonawcy
        $messageModel->sendMessage($userId, $executorId, $messageContent, $jobId);
        // Przekierowanie z powrotem do strony rozmowy
        header("Location: conversation.php?job_id=$jobId&executor_id=$executorId");
        exit;
    }
}

include('../partials/header.php');
?>

<div class="container">
    <h3>Rozmowa z wykonawcą</h3>

    <div class="messages">
        <?php if (empty($messages)): ?>
            <p>Brak wiadomości w tej konwersacji.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($messages as $msg): ?>
                    <li class="list-group-item <?php echo $msg['sender_id'] == $userId ? 'text-end' : ''; ?>">
                        <p><strong><?php echo htmlspecialchars($msg['sender_name']); ?>:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($msg['message_content'])); ?></p>
                        <small><?php echo date('d-m-Y H:i', strtotime($msg['created_at'])); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <form method="post" class="mt-4">
        <div class="form-group">
            <textarea name="message" class="form-control" placeholder="Wpisz wiadomość..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary mt-2">Wyślij</button>
    </form>
</div>

<?php include('../partials/footer.php'); ?>
