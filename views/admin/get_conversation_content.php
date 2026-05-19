<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

include_once('../../config/config.php');
include_once('../../models/Message.php');
include_once('../../models/Language.php');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ht($key, array $replace = []) {
    return h(__t($key, $replace));
}

if (!isset($_GET['conversation_id']) || trim((string)$_GET['conversation_id']) === '') {
    echo '<p class="text-danger">' . ht('admin.conversation.invalid_id') . '</p>';
    exit();
}

$conversationId = trim((string)$_GET['conversation_id']);
$messageModel = new Message($pdo);

try {
    $messages = $messageModel->getConversationMessages($conversationId);
    $reports = $messageModel->getOpenReportsForConversation($conversationId);
    $reportsByMessageId = [];
    $conversationReports = [];

    foreach ($reports as $report) {
        if (!empty($report['message_id'])) {
            $reportsByMessageId[(int)$report['message_id']][] = $report;
        } else {
            $conversationReports[] = $report;
        }
    }

    if (empty($messages)) {
        echo '<p class="text-muted">' . ht('admin.conversation.no_messages') . '</p>';
        exit();
    }

    if (!empty($conversationReports)) {
        echo '<div class="alert alert-danger">';
        echo '<strong><i class="bi bi-flag-fill"></i> ' . ht('admin.conversation.needs_intervention') . '</strong>';
        echo '<ul class="mb-0 mt-2">';
        foreach ($conversationReports as $report) {
            $reportedUserName = $report['reported_user_name'] ?: ('ID ' . $report['reported_user_id']);
            $typeLabels = [
                'conversation' => __t('admin.conversation.type_conversation'),
                'message' => __t('admin.conversation.type_message', ['id' => ($report['message_id'] ?: '-')]),
                'user' => __t('admin.conversation.type_user', ['name' => $reportedUserName]),
            ];
            echo '<li><strong>' . h($typeLabels[$report['report_type']] ?? $report['report_type']) . '</strong>';
            echo ' ' . ht('admin.conversation.reported_by') . ' ' . h($report['reporter_name'] ?: ('ID ' . $report['reporter_id']));
            echo ' (' . date('Y-m-d H:i', strtotime($report['created_at'])) . '): ';
            echo nl2br(h($report['reason'] ?: __t('admin.conversation.no_reason'))) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    foreach ($messages as $message) {
        $messageTime = date('Y-m-d H:i', strtotime($message['created_at']));
        $messageClass = $message['sender_id'] == $_SESSION['user_id'] ? 'message-sent' : 'message-received';
        $messageText = $message['content'] ?: $message['message'];
        $isRead = (int)$message['read_status'] === 1;
        $isHidden = !empty($message['is_hidden']);
        $imagePath = $message['image_path'] ?? '';
        $adminNote = $message['admin_note'] ?? '';
        $participantNote = $message['participant_note'] ?? '';
        $messageReports = $reportsByMessageId[(int)$message['id']] ?? [];

        echo '<div class="message ' . $messageClass . ' mb-3' . (!empty($messageReports) ? ' border border-danger rounded p-2 bg-danger-subtle' : '') . '">';
        echo '  <div class="d-flex justify-content-between align-items-center gap-2 mb-1">';
        echo '    <div>';
        echo '      <strong>' . h($message['sender_name']) . '</strong>';
        echo '      <small class="text-muted ms-2">' . $messageTime . '</small>';
        echo '      <span class="badge ' . ($isRead ? 'bg-success' : 'bg-secondary') . ' ms-2">' . ht($isRead ? 'admin.conversation.read' : 'admin.conversation.unread') . '</span>';
        if (!empty($messageReports)) {
            echo '      <span class="badge bg-danger ms-2"><i class="bi bi-flag-fill"></i> ' . ht('admin.conversation.reported') . '</span>';
        }
        if ($isHidden) {
            echo '      <span class="badge bg-warning text-dark ms-2">' . ht('admin.conversation.hidden') . '</span>';
        }
        if (!empty($imagePath)) {
            echo '      <span class="badge bg-info text-dark ms-2">' . ht('admin.conversation.image') . '</span>';
        }
        echo '    </div>';
        echo '    <div class="btn-group btn-group-sm">';
        echo '      <button type="button" class="btn btn-outline-warning moderate-message-btn" data-bs-toggle="modal" data-bs-target="#moderateMessageModal" data-message-id="' . (int)$message['id'] . '" data-is-hidden="' . ($isHidden ? '1' : '0') . '" data-admin-note="' . h($adminNote) . '" data-participant-note="' . h($participantNote) . '" data-image-path="' . h($imagePath) . '" title="' . ht('admin.conversation.moderate_message') . '"><i class="bi bi-shield-exclamation"></i></button>';
        echo '      <button type="button" class="btn btn-outline-primary edit-message-btn" data-bs-toggle="modal" data-bs-target="#editMessageModal" data-message-id="' . (int)$message['id'] . '" data-message-content="' . h($messageText) . '" title="' . ht('admin.conversation.edit_message') . '"><i class="bi bi-pencil"></i></button>';
        echo '      <form method="POST" action="manage_messages.php" onsubmit="return confirm(\'' . ht('admin.conversation.delete_confirm') . '\');">';
        echo '        <input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
        echo '        <input type="hidden" name="action" value="delete_message">';
        echo '        <input type="hidden" name="message_id" value="' . (int)$message['id'] . '">';
        echo '        <input type="hidden" name="conversation_id" value="' . h($conversationId) . '">';
        echo '        <button type="submit" class="btn btn-outline-danger" title="' . ht('admin.conversation.delete_message') . '"><i class="bi bi-trash"></i></button>';
        echo '      </form>';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="message-content p-2 rounded border bg-light">';
        echo '    <p class="mb-0">' . nl2br(h($messageText)) . '</p>';
        if (!empty($imagePath)) {
            echo '    <div class="mt-2"><img src="' . h($imagePath) . '" alt="' . ht('admin.conversation.message_image_alt') . '" class="img-fluid rounded border" style="max-height: 220px;"></div>';
        }
        echo '  </div>';
        if (!empty($adminNote)) {
            echo '  <div class="alert alert-warning py-2 mt-2 mb-0"><strong>' . ht('admin.conversation.admin_note') . '</strong> ' . nl2br(h($adminNote)) . '</div>';
        }
        if (!empty($participantNote)) {
            echo '  <div class="alert alert-info py-2 mt-2 mb-0"><strong>' . ht('admin.conversation.participant_note') . '</strong> ' . nl2br(h($participantNote)) . '</div>';
        }
        if (!empty($messageReports)) {
            echo '  <div class="alert alert-danger py-2 mt-2 mb-0">';
            echo '    <strong>' . ht('admin.conversation.report_reason') . '</strong>';
            echo '    <ul class="mb-0 mt-1">';
            foreach ($messageReports as $report) {
                $label = $report['report_type'] === 'user'
                    ? __t('admin.conversation.user_report', ['name' => ($report['reported_user_name'] ?: ('ID ' . $report['reported_user_id']))])
                    : __t('admin.conversation.message_report');
                echo '<li><strong>' . h($label) . '</strong>';
                echo ' ' . ht('admin.conversation.by') . ' ' . h($report['reporter_name'] ?: ('ID ' . $report['reporter_id']));
                echo ' (' . date('Y-m-d H:i', strtotime($report['created_at'])) . '): ';
                echo nl2br(h($report['reason'] ?: __t('admin.conversation.no_reason'))) . '</li>';
            }
            echo '    </ul>';
            echo '  </div>';
        }
        echo '</div>';
    }
} catch (Exception $e) {
    error_log("Błąd przy pobieraniu konwersacji: " . $e->getMessage());
    echo '<p class="text-danger">' . ht('admin.conversation.fetch_error') . '</p>';
}
