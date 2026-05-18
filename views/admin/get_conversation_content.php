<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/Message.php');

if (!isset($_GET['conversation_id']) || trim((string)$_GET['conversation_id']) === '') {
    echo '<p class="text-danger">Błędne ID konwersacji.</p>';
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
        echo '<p class="text-muted">Brak wiadomości w tej konwersacji.</p>';
        exit();
    }

    if (!empty($conversationReports)) {
        echo '<div class="alert alert-danger">';
        echo '<strong><i class="bi bi-flag-fill"></i> Konwersacja wymaga interwencji:</strong>';
        echo '<ul class="mb-0 mt-2">';
        foreach ($conversationReports as $report) {
            $typeLabels = [
                'conversation' => 'konwersacja',
                'message' => 'wiadomość #' . ($report['message_id'] ?: '-'),
                'user' => 'użytkownik ' . ($report['reported_user_name'] ?: ('ID ' . $report['reported_user_id'])),
            ];
            echo '<li><strong>' . htmlspecialchars($typeLabels[$report['report_type']] ?? $report['report_type'], ENT_QUOTES, 'UTF-8') . '</strong>';
            echo ' zgłoszone przez ' . htmlspecialchars($report['reporter_name'] ?: ('ID ' . $report['reporter_id']), ENT_QUOTES, 'UTF-8');
            echo ' (' . date('Y-m-d H:i', strtotime($report['created_at'])) . '): ';
            echo nl2br(htmlspecialchars($report['reason'] ?: 'Brak opisu', ENT_QUOTES, 'UTF-8')) . '</li>';
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
        echo '      <strong>' . htmlspecialchars($message['sender_name'], ENT_QUOTES, 'UTF-8') . '</strong>';
        echo '      <small class="text-muted ms-2">' . $messageTime . '</small>';
        echo '      <span class="badge ' . ($isRead ? 'bg-success' : 'bg-secondary') . ' ms-2">' . ($isRead ? 'Przeczytana' : 'Nieprzeczytana') . '</span>';
        if (!empty($messageReports)) {
            echo '      <span class="badge bg-danger ms-2"><i class="bi bi-flag-fill"></i> Zgłoszona</span>';
        }
        if ($isHidden) {
            echo '      <span class="badge bg-warning text-dark ms-2">Ukryta</span>';
        }
        if (!empty($imagePath)) {
            echo '      <span class="badge bg-info text-dark ms-2">Obraz</span>';
        }
        echo '    </div>';
        echo '    <div class="btn-group btn-group-sm">';
        echo '      <button type="button" class="btn btn-outline-warning moderate-message-btn" data-bs-toggle="modal" data-bs-target="#moderateMessageModal" data-message-id="' . (int)$message['id'] . '" data-is-hidden="' . ($isHidden ? '1' : '0') . '" data-admin-note="' . htmlspecialchars($adminNote, ENT_QUOTES, 'UTF-8') . '" data-participant-note="' . htmlspecialchars($participantNote, ENT_QUOTES, 'UTF-8') . '" data-image-path="' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') . '" title="Moderuj wiadomość"><i class="bi bi-shield-exclamation"></i></button>';
        echo '      <button type="button" class="btn btn-outline-primary edit-message-btn" data-bs-toggle="modal" data-bs-target="#editMessageModal" data-message-id="' . (int)$message['id'] . '" data-message-content="' . htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8') . '" title="Edytuj wiadomość"><i class="bi bi-pencil"></i></button>';
        echo '      <form method="POST" action="manage_messages.php" onsubmit="return confirm(\'Czy na pewno usunąć tę wiadomość?\');">';
        echo '        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
        echo '        <input type="hidden" name="action" value="delete_message">';
        echo '        <input type="hidden" name="message_id" value="' . (int)$message['id'] . '">';
        echo '        <input type="hidden" name="conversation_id" value="' . htmlspecialchars($conversationId, ENT_QUOTES, 'UTF-8') . '">';
        echo '        <button type="submit" class="btn btn-outline-danger" title="Usuń wiadomość"><i class="bi bi-trash"></i></button>';
        echo '      </form>';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="message-content p-2 rounded border bg-light">';
        echo '    <p class="mb-0">' . nl2br(htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8')) . '</p>';
        if (!empty($imagePath)) {
            echo '    <div class="mt-2"><img src="' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') . '" alt="Obraz wiadomości" class="img-fluid rounded border" style="max-height: 220px;"></div>';
        }
        echo '  </div>';
        if (!empty($adminNote)) {
            echo '  <div class="alert alert-warning py-2 mt-2 mb-0"><strong>Komentarz admin:</strong> ' . nl2br(htmlspecialchars($adminNote, ENT_QUOTES, 'UTF-8')) . '</div>';
        }
        if (!empty($participantNote)) {
            echo '  <div class="alert alert-info py-2 mt-2 mb-0"><strong>Komentarz dla uczestników:</strong> ' . nl2br(htmlspecialchars($participantNote, ENT_QUOTES, 'UTF-8')) . '</div>';
        }
        if (!empty($messageReports)) {
            echo '  <div class="alert alert-danger py-2 mt-2 mb-0">';
            echo '    <strong>Powód zgłoszenia tej wiadomości:</strong>';
            echo '    <ul class="mb-0 mt-1">';
            foreach ($messageReports as $report) {
                $label = $report['report_type'] === 'user'
                    ? 'zgłoszenie użytkownika ' . ($report['reported_user_name'] ?: ('ID ' . $report['reported_user_id']))
                    : 'zgłoszenie wiadomości';
                echo '<li><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>';
                echo ' przez ' . htmlspecialchars($report['reporter_name'] ?: ('ID ' . $report['reporter_id']), ENT_QUOTES, 'UTF-8');
                echo ' (' . date('Y-m-d H:i', strtotime($report['created_at'])) . '): ';
                echo nl2br(htmlspecialchars($report['reason'] ?: 'Brak opisu', ENT_QUOTES, 'UTF-8')) . '</li>';
            }
            echo '    </ul>';
            echo '  </div>';
        }
        echo '</div>';
    }
} catch (Exception $e) {
    error_log("Błąd przy pobieraniu konwersacji: " . $e->getMessage());
    echo '<p class="text-danger">Wystąpił błąd przy pobieraniu konwersacji.</p>';
}
