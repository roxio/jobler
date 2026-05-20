<?php
session_start();

require_once('../../config/config.php');
require_once('../../models/Database.php');
require_once('../../models/Message.php');
require_once('../../models/User.php');
require_once('../../models/Language.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$conversationId = isset($_GET['conversation_id']) ? trim((string)$_GET['conversation_id']) : null;
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;

if (!$conversationId) {
    die(htmlspecialchars(__t('messages.invalid_conversation_id')));
}

$pdo = Database::getConnection();
$messageModel = new Message($pdo);
$userModel = new User();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$messages = $messageModel->getConversationById($conversationId, $userId);

if ($messages === false) {
    http_response_code(403);
    die('<div class="container mt-5"><div class="alert alert-danger"><i class="bi bi-shield-x"></i> ' . htmlspecialchars(__t('messages.access_denied')) . '</div></div>');
}

if (!$jobId) {
    if (!empty($messages)) {
        $jobId = (int)$messages[0]['job_id'];
    } else {
        $parts = explode('_', $conversationId);
        $jobId = count($parts) === 3 && is_numeric($parts[0]) ? (int)$parts[0] : null;
    }
}

function resolveConversationReceiver($conversationId, $jobId, $userId, $messages) {
    $parts = explode('_', $conversationId);

    if (count($parts) === 3 && is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2])) {
        $conversationJobId = (int)$parts[0];
        $id1 = (int)$parts[1];
        $id2 = (int)$parts[2];

        if ($jobId && $conversationJobId !== (int)$jobId) {
            return null;
        }

        if ($userId !== $id1 && $userId !== $id2) {
            return null;
        }

        return [
            'receiver_id' => $userId === $id1 ? $id2 : $id1,
            'job_id' => $conversationJobId,
        ];
    }

    if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
        $id1 = (int)$parts[0];
        $id2 = (int)$parts[1];

        if ($userId !== $id1 && $userId !== $id2) {
            return null;
        }

        return [
            'receiver_id' => $userId === $id1 ? $id2 : $id1,
            'job_id' => $jobId,
        ];
    }

    if (!empty($messages)) {
        $firstMessage = $messages[0];
        return [
            'receiver_id' => (int)$firstMessage['sender_id'] === $userId
                ? (int)$firstMessage['receiver_id']
                : (int)$firstMessage['sender_id'],
            'job_id' => $jobId ?: (int)$firstMessage['job_id'],
        ];
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $messageContent = trim($_POST['message']);

    if ($messageContent !== '') {
        $conversationTarget = resolveConversationReceiver($conversationId, $jobId, $userId, $messages);

        if ($conversationTarget === null || empty($conversationTarget['receiver_id']) || empty($conversationTarget['job_id'])) {
            http_response_code(400);
            die(htmlspecialchars(__t('messages.invalid_conversation_format')));
        }

        $messageModel->sendMessage($userId, $conversationTarget['receiver_id'], $messageContent, $conversationTarget['job_id']);
        header('Location: conversation.php?conversation_id=' . urlencode($conversationId) . '&job_id=' . urlencode((string)$conversationTarget['job_id']));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: conversation.php?conversation_id=' . urlencode($conversationId) . '&job_id=' . urlencode((string)$jobId) . '&report=csrf');
        exit;
    }

    $reportType = $_POST['report_type'] ?? 'conversation';
    $reason = trim($_POST['reason'] ?? '');
    $messageId = isset($_POST['message_id']) && $_POST['message_id'] !== '' ? (int)$_POST['message_id'] : null;
    $reportedUserId = isset($_POST['reported_user_id']) && $_POST['reported_user_id'] !== '' ? (int)$_POST['reported_user_id'] : null;

    if ($reason === '') {
        header('Location: conversation.php?conversation_id=' . urlencode($conversationId) . '&job_id=' . urlencode((string)$jobId) . '&report=empty');
        exit;
    }

    $saved = $messageModel->createConversationReport($userId, $conversationId, $jobId, $reportType, $reason, $messageId, $reportedUserId);
    header('Location: conversation.php?conversation_id=' . urlencode($conversationId) . '&job_id=' . urlencode((string)$jobId) . '&report=' . ($saved ? 'sent' : 'error'));
    exit;
}

include('../partials/header.php');
?>

<div class="container">
    <?php if (isset($_GET['report'])): ?>
        <?php
        $reportMessages = [
            'sent' => __t('messages.report_sent'),
            'empty' => __t('messages.report_empty'),
            'csrf' => __t('messages.report_csrf'),
            'error' => __t('messages.report_error'),
        ];
        $isReportSuccess = $_GET['report'] === 'sent';
        ?>
        <div class="alert alert-<?= $isReportSuccess ? 'success' : 'danger' ?>">
            <?= htmlspecialchars($reportMessages[$_GET['report']] ?? __t('messages.report_processed')) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><?= htmlspecialchars(__t('messages.conversation_title')) ?></h3>
        <button type="button" class="btn btn-outline-danger btn-sm report-trigger"
                data-bs-toggle="modal" data-bs-target="#reportModal"
                data-report-type="conversation" data-message-id="" data-reported-user-id="">
            <i class="bi bi-flag"></i> <?= htmlspecialchars(__t('messages.report_conversation')) ?>
        </button>
    </div>

    <div class="messages">
        <?php if (empty($messages)): ?>
            <p><?= htmlspecialchars(__t('messages.no_messages')) ?></p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($messages as $msg): ?>
                    <li class="list-group-item <?php echo (int)$msg['sender_id'] === $userId ? 'text-end' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <p class="mb-2"><strong><?php echo htmlspecialchars($msg['sender_name']); ?>:</strong></p>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-warning report-trigger"
                                        data-bs-toggle="modal" data-bs-target="#reportModal"
                                        data-report-type="message"
                                        data-message-id="<?= (int)$msg['id'] ?>"
                                        data-reported-user-id="<?= (int)$msg['sender_id'] ?>"
                                        title="<?= htmlspecialchars(__t('messages.report_message')) ?>">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </button>
                                <?php if ((int)$msg['sender_id'] !== $userId): ?>
                                    <button type="button" class="btn btn-outline-danger report-trigger"
                                            data-bs-toggle="modal" data-bs-target="#reportModal"
                                            data-report-type="user"
                                            data-message-id="<?= (int)$msg['id'] ?>"
                                            data-reported-user-id="<?= (int)$msg['sender_id'] ?>"
                                            title="<?= htmlspecialchars(__t('messages.report_user')) ?>">
                                        <i class="bi bi-person-exclamation"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($msg['is_hidden'])): ?>
                            <div class="alert alert-warning mb-2">
                                <?= htmlspecialchars(__t('messages.hidden_by_admin')) ?>
                            </div>
                        <?php else: ?>
                            <p><?php echo nl2br(htmlspecialchars($msg['content'] ?: $msg['message'] ?: __t('messages.no_content'))); ?></p>
                            <?php if (!empty($msg['image_path'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($msg['image_path']); ?>" alt="<?= htmlspecialchars(__t('messages.image_alt')) ?>" class="img-fluid rounded border" style="max-height: 260px;">
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($msg['participant_note'])): ?>
                            <div class="alert alert-info py-2 mb-2">
                                <?php echo nl2br(htmlspecialchars($msg['participant_note'])); ?>
                            </div>
                        <?php endif; ?>
                        <small><?php echo date('d-m-Y H:i', strtotime($msg['created_at'])); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <form method="post" class="mt-4">
        <div class="form-group">
            <textarea name="message" class="form-control" placeholder="<?= htmlspecialchars(__t('messages.placeholder')) ?>"></textarea>
        </div>
        <button type="submit" class="btn btn-primary mt-2"><?= htmlspecialchars(__t('messages.send')) ?></button>
    </form>
</div>

<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><?= htmlspecialchars(__t('messages.report_modal_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="report">
                    <input type="hidden" name="report_type" id="reportType" value="conversation">
                    <input type="hidden" name="message_id" id="reportMessageId">
                    <input type="hidden" name="reported_user_id" id="reportedUserId">
                    <label for="reportReason" class="form-label"><?= htmlspecialchars(__t('messages.describe_problem')) ?></label>
                    <textarea name="reason" id="reportReason" class="form-control" rows="5" required></textarea>
                    <div class="form-text"><?= htmlspecialchars(__t('messages.report_hint')) ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(__t('admin.users.cancel')) ?></button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-flag"></i> <?= htmlspecialchars(__t('messages.send_report')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.report-trigger').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('reportType').value = this.dataset.reportType || 'conversation';
            document.getElementById('reportMessageId').value = this.dataset.messageId || '';
            document.getElementById('reportedUserId').value = this.dataset.reportedUserId || '';
            document.getElementById('reportReason').value = '';
        });
    });
});
</script>

<?php include('../partials/footer.php'); ?>
