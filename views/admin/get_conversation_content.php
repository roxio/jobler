<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/Message.php');

if (!isset($_GET['conversation_id']) || !is_numeric($_GET['conversation_id'])) {
    echo '<p class="text-danger">Błędne ID konwersacji.</p>';
    exit();
}

$conversationId = (int)$_GET['conversation_id'];
$messageModel = new Message($pdo);

try {
    $messages = $messageModel->getConversationMessages($conversationId);
    
    if (empty($messages)) {
        echo '<p class="text-muted">Brak wiadomości w tej konwersacji.</p>';
        exit();
    }
    
    foreach ($messages as $message) {
        $messageTime = date('Y-m-d H:i', strtotime($message['created_at']));
        $messageClass = $message['sender_id'] == $_SESSION['user_id'] ? 'message-sent' : 'message-received';
        
        echo '<div class="message ' . $messageClass . ' mb-3">';
        echo '  <div class="d-flex justify-content-between align-items-center mb-1">';
        echo '    <strong>' . htmlspecialchars($message['sender_name']) . '</strong>';
        echo '    <small class="text-muted">' . $messageTime . '</small>';
        echo '  </div>';
        echo '  <div class="message-content p-2 rounded">';
        echo '    <p class="mb-0">' . nl2br(htmlspecialchars($message['content'] ?: $message['message'])) . '</p>';
        echo '  </div>';
        echo '</div>';
    }
} catch (Exception $e) {
    error_log("Błąd przy pobieraniu konwersacji: " . $e->getMessage());
    echo '<p class="text-danger">Wystąpił błąd przy pobieraniu konwersacji.</p>';
}