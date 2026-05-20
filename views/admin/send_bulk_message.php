<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if (empty($_SESSION['bulk_user_ids'])) {
    header('Location: manage_users.php?status=error');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Message.php');
include_once('../../models/Language.php');

$userModel = new User();
$messageModel = new Message();

function safeEcho($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

$userIds = $_SESSION['bulk_user_ids'];
$users = $userModel->getUsersByIds($userIds);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_users.php?status=error');
        exit();
    }

    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $messageType = $_POST['message_type'];

    if (empty($subject) || empty($message)) {
        $error = __t('admin.bulk.subject_message_required');
    } else {
        $successCount = 0;
        $failedCount = 0;
        $adminId = $_SESSION['user_id'];

        foreach ($users as $user) {
            if ($messageModel->sendAdminMessage($adminId, $user['id'], $subject, $message, $messageType)) {
                $successCount++;


                error_log("Admin sent message to user {$user['id']}: $subject");

            } else {
                $failedCount++;
            }
        }


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
                    <h5 class="mb-0"><i class="bi bi-envelope"></i> <?= safeEcho(__t('admin.bulk.send_title')) ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <?= safeEcho(__t('admin.bulk.send_notice', ['count' => count($users)])) ?>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="mb-3">
                            <label class="form-label"><?= safeEcho(__t('admin.bulk.message_type')) ?></label>
                            <select name="message_type" class="form-select" required>
                                <option value="notification"><?= safeEcho(__t('admin.bulk.type_system')) ?></option>
                                <option value="information"><?= safeEcho(__t('admin.bulk.type_info')) ?></option>
                                <option value="warning"><?= safeEcho(__t('admin.bulk.type_warning')) ?></option>
                                <option value="promotion"><?= safeEcho(__t('admin.bulk.type_promo')) ?></option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= safeEcho(__t('admin.bulk.subject_label')) ?></label>
                            <input type="text" name="subject" class="form-control" required maxlength="255"
                                   placeholder="<?= safeEcho(__t('admin.bulk.subject_placeholder')) ?>" value="<?= isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '' ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= safeEcho(__t('admin.bulk.message_label')) ?></label>
                            <textarea name="message" class="form-control" rows="8" required
                                      placeholder="<?= safeEcho(__t('admin.bulk.message_placeholder')) ?>"><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= safeEcho(__t('admin.bulk.recipients_preview')) ?></label>
                            <div class="border rounded p-3 bg-light">
                                <small>
                                    <?php
                                    $userNames = array_map(function($user) {
                                        return htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['email']) . ')';
                                    }, array_slice($users, 0, 5));

                                    echo implode('<br>', $userNames);

                                    if (count($users) > 5) {
                                        echo '<br>' . safeEcho(__t('admin.bulk.more_recipients', ['count' => count($users) - 5]));
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="manage_users.php" class="btn btn-secondary"><?= safeEcho(__t('admin.users.cancel')) ?></a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> <?= safeEcho(__t('admin.bulk.send_to_users', ['count' => count($users)])) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
