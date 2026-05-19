<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php?status=error');
    exit();
}

$userId = (int)$_GET['id'];

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Message.php');
include_once('../../models/Language.php');

$userModel = new User();
$jobModel = new Job();
$transactionModel = new TransactionHistory($pdo);
$messageModel = new Message($pdo);


try {
    $user = $userModel->getUserById($userId);
    
     if (!$user) {
         header('Location: manage_users.php?status=error&message=user_not_found');
         exit();
    }
	
    
    //Pobierz dodatkowe informacje
    $userJobs = $jobModel->getJobsByUserId($userId, 5);
     $userTransactions = $transactionModel->getUserTransactions($userId, 5);
     $userStats = $userModel->getUserStatistics($userId);
     $loginHistory = $userModel->getLoginHistory($userId, 5);
	//$userConversations = $messageModel->getUserConversations($userId, 5);
	//$conversationStats = $messageModel->getUserConversationStats($userId);
    
} catch (Exception $e) {
     error_log("Błąd przy pobieraniu danych użytkownika: " . $e->getMessage());
     header('Location: manage_users.php?status=error');
     exit();
}

function safeEcho($data, $default = '') {
    return isset($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $default;
}

function formatDate($date) {
    if (!$date) return __t('admin.common.never');
    return date('Y-m-d H:i', strtotime($date));
}

function formatBalance($balance) {
    return number_format(floatval($balance), 2) . ' pkt';
}

// Sprawdź czy użytkownik jest online (ostatnie 15 minut)
$isOnline = false;
if (!empty($user['last_login'])) {
    $lastLogin = strtotime($user['last_login']);
    $isOnline = (time() - $lastLogin) < 900; // 15 minut
}
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= htmlspecialchars(__t('admin.panel')) ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <!-- Nagłówek z powrotem i akcjami -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('admin.back_to_list')) ?>
                            </a>
                            <span class="ms-2"><?= htmlspecialchars(__t('admin.view_user.title')) ?></span>
                        </div>
                        <div class="btn-group">
                            <a href="edit_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-pencil"></i> <?= htmlspecialchars(__t('common.edit')) ?>
                            </a>
                            <?php if ($user['status'] == 'active'): ?>
                                <a href="deactivate_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-secondary" 
                                   onclick="return confirm('<?= htmlspecialchars(__t('admin.users.deactivate_confirm'), ENT_QUOTES) ?>');">
                                    <i class="bi bi-person-x"></i> <?= htmlspecialchars(__t('admin.common.deactivate')) ?>
                                </a>
                            <?php else: ?>
                                <a href="activate_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-person-check"></i> <?= htmlspecialchars(__t('admin.common.activate')) ?>
                                </a>
                            <?php endif; ?>
                            <a href="delete_user.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('<?= htmlspecialchars(__t('admin.users.delete_confirm'), ENT_QUOTES) ?>');">
                                <i class="bi bi-trash"></i> <?= htmlspecialchars(__t('admin.common.delete')) ?>
                            </a>
                        </div>
                    </div>

                    <!-- Alerty -->
                    <?php if (isset($_GET['status'])): ?>
                        <div class="alert <?= $_GET['status'] == 'error' ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show mb-4" role="alert">
                            <?php
                            $messages = [
                                'points_added' => __t('admin.users.points_added'),
                                'role_changed' => __t('admin.users.role_changed'),
                                'activated' => __t('admin.users.activated'),
                                'deactivated' => __t('admin.users.deactivated'),
                                'error' => __t('admin.users.error')
                            ];
                            echo safeEcho($messages[$_GET['status']] ?? '');
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Lewa kolumna - informacje podstawowe -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-person"></i> <?= htmlspecialchars(__t('admin.basic_info')) ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <img src="<?= !empty($user['avatar']) ? safeEcho($user['avatar']) : '../../assets/img/default-avatar.png'; ?>" 
                                             class="rounded-circle mb-2" width="100" height="100" alt="Avatar">
                                        <h5><?= safeEcho($user['name']) ?></h5>
                                        <span class="badge bg-<?= $isOnline ? 'success' : 'secondary'; ?>">
                                            <?= $isOnline ? 'Online' : 'Offline'; ?>
                                        </span>
                                    </div>

                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>ID:</strong></td>
                                            <td>#<?= $userId ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td>
                                                <?= safeEcho($user['email']) ?>
                                                <?php if (!empty($user['email_verified_at'])): ?>
                                                    <span class="badge bg-success ms-1" title="<?= htmlspecialchars(__t('admin.users.email_verified')) ?>">✓</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.common.role')) ?>:</strong></td>
                                            <td>
                                                <span class="badge <?= AccessControl::badgeClass($user['role']) ?>">
                                                    <?= safeEcho(AccessControl::roleLabel($user['role'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.common.status')) ?>:</strong></td>
                                            <td>
                                                <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?= htmlspecialchars($user['status'] == 'active' ? __t('admin.status.active') : __t('admin.status.inactive')); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.users.registration_date')) ?>:</strong></td>
                                            <td><?= formatDate($user['created_at']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.users.last_login')) ?>:</strong></td>
                                            <td><?= formatDate($user['last_login'] ?? null) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.common.balance')) ?>:</strong></td>
                                            <td class="fw-bold <?= ($user['account_balance'] ?? 0) > 0 ? 'text-success' : 'text-muted'; ?>">
                                                <?= formatBalance($user['account_balance'] ?? 0) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.view_user.original_data')) ?>:</strong></td>
                                            <td>
                                                <small>
                                                    <?= safeEcho($user['original_name'] ?? __t('admin.newsletter.none')) ?>,
                                                    <?= safeEcho($user['original_username'] ?? __t('admin.newsletter.none')) ?>,
                                                    <?= safeEcho($user['original_email'] ?? __t('admin.newsletter.none')) ?>,
                                                    tel. <?= safeEcho($user['original_phone'] ?? __t('admin.newsletter.none')) ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.view_user.profile_changed')) ?>:</strong></td>
                                            <td><?= !empty($user['profile_updated_at']) ? formatDate($user['profile_updated_at']) : htmlspecialchars(__t('admin.view_user.no_changes')); ?></td>
                                        </tr>
                                    </table>

                                    <!-- Szybkie akcje -->
                                    <div class="mt-3">
                                        <form action="add_points.php" method="POST" class="mb-2">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $userId ?>">
    <div class="input-group input-group-sm">
        <input type="number" name="points_to_add" class="form-control" placeholder="<?= htmlspecialchars(__t('admin.common.points')) ?>" min="1" max="1000" required>
        <input type="text" name="reason" class="form-control" placeholder="<?= htmlspecialchars(__t('admin.users.reason_optional')) ?>" maxlength="100">
        <button type="submit" class="btn btn-success"><?= htmlspecialchars(__t('admin.users.add_points')) ?></button>
    </div>
</form>

                                        <?php if (!empty($user['need_change']) && canAdminAccess('roles.manage')): ?>
                                            <form action="change_role.php" method="POST" class="mb-2">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $userId ?>">
                                                <input type="hidden" name="current_role" value="<?= safeEcho($user['role']) ?>">
                                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                                    <i class="bi bi-arrow-repeat"></i> <?= htmlspecialchars(__t('admin.users.change_role')) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <button class="btn btn-info btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                                            <i class="bi bi-envelope"></i> <?= htmlspecialchars(__t('admin.view_user.send_message')) ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Informacje techniczne -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> <?= htmlspecialchars(__t('admin.technical_info')) ?></h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.users.registration_ip')) ?>:</strong></td>
                                            <td><code><?= safeEcho($user['registration_ip'] ?? __t('admin.newsletter.none')) ?></code></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.view_user.last_ip')) ?>:</strong></td>
                                            <td><code><?= safeEcho($user['last_login_ip'] ?? __t('admin.newsletter.none')) ?></code></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= htmlspecialchars(__t('admin.view_user.browser')) ?>:</strong></td>
                                            <td><small><?= safeEcho($user['user_agent'] ?? __t('admin.newsletter.none')) ?></small></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Prawa kolumna - statystyki i aktywność -->
                        <div class="col-md-8">
                            <!-- Statystyki -->
                            <div class="row mb-4">
                                <div class="col-md-3 col-6">
                                    <div class="card bg-primary text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['total_jobs'] ?? 0 ?></h5>
                                            <small><?= htmlspecialchars(__t('admin.view_user.total_jobs')) ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card bg-success text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['active_jobs'] ?? 0 ?></h5>
                                            <small><?= htmlspecialchars(__t('admin.users.active')) ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card bg-info text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['total_transactions'] ?? 0 ?></h5>
                                            <small><?= htmlspecialchars(__t('admin.view_user.transactions')) ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card bg-warning text-white text-center">
                                        <div class="card-body py-2">
                                            <h5 class="mb-0"><?= $userStats['total_messages'] ?? 0 ?></h5>
                                            <small><?= htmlspecialchars(__t('admin.view_user.messages')) ?></small>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Ostatnie ogłoszenia -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-briefcase"></i> <?= htmlspecialchars(__t('admin.view_user.recent_jobs')) ?></h6>
                                    <a href="../admin/manage_jobs.php?user_id=<?= $userId ?>" class="btn btn-sm btn-outline-primary">
                                        <?= htmlspecialchars(__t('admin.view_all')) ?>
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($userJobs)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th><?= htmlspecialchars(__t('admin.common.title')) ?></th>
                                                        <th><?= htmlspecialchars(__t('admin.common.status')) ?></th>
                                                        <th><?= htmlspecialchars(__t('admin.date')) ?></th>
                                                        <th><?= htmlspecialchars(__t('admin.view_user.offer_count')) ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($userJobs as $job): ?>
                                                        <tr>
                                                            <td>
                                                                <a href="../jobs/view.php?id=<?= $job['id'] ?>" target="_blank" class="text-decoration-none">
                                                                    <?= safeEcho(mb_substr($job['title'], 0, 30)) ?><?= mb_strlen($job['title']) > 30 ? '...' : '' ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $job['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?= safeEcho($job['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= formatDate($job['created_at']) ?></td>
                                                            <td><?= $job['offer_count'] ?? 0 ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0"><?= htmlspecialchars(__t('admin.view_user.no_jobs')) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Ostatnie transakcje -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-currency-exchange"></i> <?= htmlspecialchars(__t('admin.view_user.recent_transactions')) ?></h6>
                                    <a href="../admin/transactions.php?user_id=<?= $userId ?>" class="btn btn-sm btn-outline-primary">
                                        <?= htmlspecialchars(__t('admin.view_all')) ?>
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($userTransactions)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th><?= htmlspecialchars(__t('admin.view_user.type')) ?></th>
                                                        <th><?= htmlspecialchars(__t('admin.view_user.amount')) ?></th>
                                                        <th><?= htmlspecialchars(__t('admin.common.status')) ?></th>
                                                        <th><?= htmlspecialchars(__t('admin.date')) ?></th>
                                                        <th><?= htmlspecialchars(__t('admin.description')) ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($userTransactions as $transaction): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-<?= $transaction['type'] == 'income' ? 'success' : 'danger'; ?>">
                                                                    <?= safeEcho($transaction['type']) ?>
                                                                </span>
                                                            </td>
                                                            <td class="fw-bold <?= $transaction['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                                                <?= number_format($transaction['amount'], 2) ?> PLN
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $transaction['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                                    <?= safeEcho($transaction['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= formatDate($transaction['created_at']) ?></td>
                                                            <td><?= safeEcho(mb_substr($transaction['description'], 0, 20)) ?><?= mb_strlen($transaction['description']) > 20 ? '...' : '' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0"><?= htmlspecialchars(__t('admin.view_user.no_transactions')) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

<!-- Ostatnie konwersacje -->
<!-- Ostatnie konwersacje -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-chat-dots"></i> <?= htmlspecialchars(__t('admin.view_user.recent_conversations')) ?></h6>
        <a href="../admin/manage_messages.php?user_id=<?= $userId ?>" class="btn btn-sm btn-outline-primary">
            <?= htmlspecialchars(__t('admin.view_all')) ?>
        </a>
    </div>
    <div class="card-body">
        <?php 
        // Pobierz ostatnie 5 zgrupowanych konwersacji użytkownika
        $userConversations = $messageModel->getGroupedConversationsWithAdvancedFilters(5, 0, ['user_id' => $userId]);
        ?>
        
        <?php if (!empty($userConversations)): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars(__t('admin.view_user.conversation_id')) ?></th>
                            <th><?= htmlspecialchars(__t('admin.view_user.participant')) ?></th>
                            <th><?= htmlspecialchars(__t('admin.view_user.job')) ?></th>
                            <th><?= htmlspecialchars(__t('admin.view_user.last_message')) ?></th>
                            <th><?= htmlspecialchars(__t('admin.view_user.messages')) ?></th>
                            <th><?= htmlspecialchars(__t('admin.view_user.last_activity')) ?></th>
                            <th><?= htmlspecialchars(__t('admin.common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userConversations as $conversation): ?>
                            <?php
                            // Określ drugiego uczestnika konwersacji
                            $otherUserId = ($conversation['sender_id'] == $userId) ? $conversation['receiver_id'] : $conversation['sender_id'];
                            $otherUserName = ($conversation['sender_id'] == $userId) ? $conversation['receiver_name'] : $conversation['sender_name'];
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">#<?= safeEcho($conversation['conversation_id']); ?></span>
                                </td>
                                <td>
                                    <a href="view_user.php?id=<?= safeEcho($otherUserId) ?>" class="text-decoration-none">
                                        <?= safeEcho($otherUserName) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($conversation['job_id'])): ?>
                                        <a href="../jobs/view.php?id=<?= safeEcho($conversation['job_id']) ?>" class="text-decoration-none" target="_blank">
                                            #<?= safeEcho($conversation['job_id']) ?>: <?= safeEcho(mb_substr($conversation['job_title'], 0, 15)) ?><?= mb_strlen($conversation['job_title']) > 15 ? '...' : '' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><?= htmlspecialchars(__t('admin.view_user.no_job')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $content = !empty($conversation['last_message_content']) ? $conversation['last_message_content'] : __t('admin.view_user.no_content');
                                    echo safeEcho(mb_substr($content, 0, 30)) . (mb_strlen($content) > 30 ? '...' : '');
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= safeEcho($conversation['message_count']) ?></span>
                                </td>
                                <td>
                                    <small><?= date('Y-m-d H:i', strtotime($conversation['last_activity_date'])) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-info view-conversation" 
                                                data-bs-toggle="modal" data-bs-target="#conversationModal" 
                                                data-conversation-id="<?= safeEcho($conversation['conversation_id']); ?>">
                                            <i class="bi bi-eye" title="<?= htmlspecialchars(__t('admin.view_user.preview_conversation')) ?>"></i>
                                        </button>
                                        <a href="../messages/view.php?conversation_id=<?= safeEcho($conversation['conversation_id']) ?>" 
                                           class="btn btn-outline-primary" target="_blank">
                                            <i class="bi bi-chat" title="<?= htmlspecialchars(__t('admin.view_user.open_conversation')) ?>"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center mb-0"><?= htmlspecialchars(__t('admin.view_user.no_conversations')) ?></p>
        <?php endif; ?>
    </div>
</div>

                            <!-- Historia logowań -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-clock-history"></i> <?= htmlspecialchars(__t('admin.view_user.recent_logins')) ?></h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($loginHistory)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th><?= htmlspecialchars(__t('admin.date')) ?></th>
                                                        <th>IP</th>
                                                        <th><?= htmlspecialchars(__t('admin.common.status')) ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($loginHistory as $login): ?>
                                                        <tr>
                                                            <td><?= formatDate($login['login_time']) ?></td>
                                                            <td><code><?= safeEcho($login['ip_address']) ?></code></td>
                                                            <td>
                                                                <span class="badge bg-<?= $login['success'] ? 'success' : 'danger'; ?>">
                                                                    <?= htmlspecialchars($login['success'] ? __t('admin.view_user.success') : __t('admin.view_user.error')) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0"><?= htmlspecialchars(__t('admin.view_user.no_login_history')) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
												
							
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal do wysyłania wiadomości -->
<div class="modal fade" id="sendMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars(__t('admin.view_user.send_message_to', ['name' => $user['name']])) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
            </div>
            <form action="../admin/send_message.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(__t('admin.view_user.subject')) ?></label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(__t('admin.view_user.message')) ?></label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(__t('admin.view_user.message_type')) ?></label>
                        <select name="message_type" class="form-select">
                            <option value="notification"><?= htmlspecialchars(__t('admin.view_user.notification')) ?></option>
                            <option value="information"><?= htmlspecialchars(__t('admin.view_user.information')) ?></option>
                            <option value="warning"><?= htmlspecialchars(__t('admin.view_user.warning')) ?></option>
                            <option value="promotion"><?= htmlspecialchars(__t('admin.view_user.promotion')) ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(__t('admin.users.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= htmlspecialchars(__t('admin.view_user.send_message')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal do podglądu konwersacji -->
<div class="modal fade" id="conversationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars(__t('admin.view_user.preview_conversation')) ?> <span id="modalConversationId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars(__t('common.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div id="conversationContent" class="conversation-messages">
                    <p class="text-center text-muted"><?= htmlspecialchars(__t('admin.view_user.loading_messages')) ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(__t('common.close')) ?></button>
                <a href="#" id="fullConversationLink" class="btn btn-primary" target="_blank"><?= htmlspecialchars(__t('admin.view_user.full_conversation')) ?></a>
            </div>
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>

<script>
// Inicjalizacja tooltipów
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function checkUserOnlineStatus(userId) {
    fetch(`check_online_status.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.online) {
                document.querySelector(`#user-status-${userId}`).className = 'badge bg-success';
                document.querySelector(`#user-status-${userId}`).textContent = 'Online';
            } else {
                document.querySelector(`#user-status-${userId}`).className = 'badge bg-secondary';
                document.querySelector(`#user-status-${userId}`).textContent = 'Offline';
            }
        });
}

// Wywołanie co 30 sekund
setInterval(() => {
    checkUserOnlineStatus(<?= $userId ?>);
}, 30000);
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal do podglądu konwersacji
    const conversationModal = document.getElementById('conversationModal');
    if (conversationModal) {
        conversationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const conversationId = button.getAttribute('data-conversation-id');
            
            document.getElementById('modalConversationId').textContent = '#' + conversationId;
            document.getElementById('fullConversationLink').href = '../messages/view.php?conversation_id=' + conversationId;
            
            // Pobierz zawartość konwersacji przez AJAX
            fetch('../admin/get_conversation_content.php?conversation_id=' + conversationId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('conversationContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('conversationContent').innerHTML = 
                        '<p class="text-danger"><?= htmlspecialchars(__t('admin.view_user.loading_error'), ENT_QUOTES) ?></p>';
                });
        });
    }
});
</script>
