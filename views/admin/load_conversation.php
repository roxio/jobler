<?php
require_once __DIR__ . '/_auth.php';
requireAdminAccess();
include_once('../../models/Message.php');
include_once('../../models/Language.php');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['conversation_id'])) {
    $messageModel = new Message();
    $conversation_id = intval($_GET['conversation_id']);
    $messages = $messageModel->getConversationById($conversation_id);

    if ($messages) {
        foreach ($messages as $msg) {
            echo '<div class="message-box p-3 border rounded mb-2">';
            echo '<p><strong>' . h(__t('admin.conversation.from')) . ': ' . h($msg['sender_id']) . ' → ' . h($msg['receiver_id']) . '</strong></p>';
            echo '<p>' . h($msg['content']) . '</p>';
            echo '<small class="text-muted"><i class="bi bi-clock"></i> ' . h($msg['created_at']) . '</small>';
            echo '</div>';
        }
    } else {
        echo '<p class="text-muted">' . h(__t('admin.conversation.no_messages')) . '</p>';
    }
} else {
    echo '<p class="text-danger">' . h(__t('admin.conversation.invalid_identifier')) . '</p>';
}
?>
