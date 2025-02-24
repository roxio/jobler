<?php
include_once('../../models/Message.php');

if (isset($_GET['conversation_id'])) {
    $messageModel = new Message();
    $conversation_id = intval($_GET['conversation_id']);
    $messages = $messageModel->getConversationById($conversation_id);

    if ($messages) {
        foreach ($messages as $msg) {
            echo '<div class="message-box p-3 border rounded mb-2">';
            echo '<p><strong>Od: ' . htmlspecialchars($msg['sender_id']) . ' → ' . htmlspecialchars($msg['receiver_id']) . '</strong></p>';
            echo '<p>' . htmlspecialchars($msg['content']) . '</p>';
            echo '<small class="text-muted"><i class="bi bi-clock"></i> ' . htmlspecialchars($msg['created_at']) . '</small>';
            echo '</div>';
        }
    } else {
        echo '<p class="text-muted">Brak wiadomości w tej konwersacji.</p>';
    }
} else {
    echo '<p class="text-danger">Nieprawidłowy identyfikator konwersacji.</p>';
}
?>
