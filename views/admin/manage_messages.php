<?php
session_start();
include_once('../../config/config.php');
include_once('../../models/Message.php');
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/Language.php');

// Sprawdź czy użytkownik jest zalogowany i ma uprawnienia administratora
require_once __DIR__ . '/_auth.php';
requireAdminAccess();

// Utwórz instancje klas
$messageModel = new Message($pdo);
$userModel = new User();
$jobModel = new Job();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function handleModerationImageUpload($file) {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException(__t('admin.messages.upload_too_large'));
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException(__t('admin.messages.invalid_image_type'));
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads/message_images';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'message_' . bin2hex(random_bytes(12)) . '.' . $allowedMimeTypes[$mimeType];
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException(__t('admin.messages.image_save_error'));
    }

    return '/uploads/message_images/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: manage_messages.php?status=error&message=csrf_error');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_message') {
            $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
            $content = trim($_POST['content'] ?? '');

            if ($messageId <= 0 || $content === '') {
                header('Location: manage_messages.php?status=error&message=invalid_message');
                exit;
            }

            $messageModel->updateMessageContent($messageId, $content);
            header('Location: manage_messages.php?status=updated');
            exit;
        }

        if ($action === 'delete_message') {
            $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

            if ($messageId <= 0) {
                header('Location: manage_messages.php?status=error&message=invalid_message');
                exit;
            }

            $messageModel->deleteMessage($messageId);
            header('Location: manage_messages.php?status=message_deleted');
            exit;
        }

        if (in_array($action, ['mark_read', 'mark_unread'], true)) {
            $conversationIds = array_values(array_filter(array_map('trim', (array)($_POST['conversation_ids'] ?? []))));

            if (empty($conversationIds)) {
                header('Location: manage_messages.php?status=error&message=no_selection');
                exit;
            }

            $messageModel->setConversationReadStatus($conversationIds, $action === 'mark_read' ? 1 : 0);
            header('Location: manage_messages.php?status=' . ($action === 'mark_read' ? 'marked_read' : 'marked_unread'));
            exit;
        }

        if ($action === 'moderate_message') {
            $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
            if ($messageId <= 0) {
                header('Location: manage_messages.php?status=error&message=invalid_message');
                exit;
            }

            $message = $messageModel->getMessageById($messageId);
            if (!$message) {
                header('Location: manage_messages.php?status=error&message=invalid_message');
                exit;
            }

            if (!empty($_POST['remove_image']) && !empty($message['image_path'])) {
                $imagePath = dirname(__DIR__, 2) . $message['image_path'];
                if (is_file($imagePath)) {
                    unlink($imagePath);
                }
                $messageModel->removeMessageImage($messageId);
            }

            $uploadedImagePath = handleModerationImageUpload($_FILES['message_image'] ?? []);

            $moderationData = [
                'is_hidden' => isset($_POST['is_hidden']) ? 1 : 0,
                'admin_note' => trim($_POST['admin_note'] ?? ''),
                'participant_note' => trim($_POST['participant_note'] ?? ''),
                'moderated_by' => $_SESSION['user_id'],
            ];

            if ($uploadedImagePath !== null) {
                if (!empty($message['image_path'])) {
                    $oldImagePath = dirname(__DIR__, 2) . $message['image_path'];
                    if (is_file($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $moderationData['image_path'] = $uploadedImagePath;
            }

            $messageModel->moderateMessage($messageId, $moderationData);
            header('Location: manage_messages.php?status=moderated');
            exit;
        }
    } catch (RuntimeException $e) {
        error_log("Błąd uploadu obrazu wiadomości: " . $e->getMessage());
        header('Location: manage_messages.php?status=error&message=upload_error');
        exit;
    } catch (Exception $e) {
        error_log("Błąd akcji administratora na wiadomościach: " . $e->getMessage());
        header('Location: manage_messages.php?status=error&message=system_error');
        exit;
    }
}

// Pobierz wszystkie filtry
$filters = [
    'user_id' => isset($_GET['user_id']) ? (int)$_GET['user_id'] : '',
    'job_id' => isset($_GET['job_id']) ? (int)$_GET['job_id'] : '',
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'min_messages' => isset($_GET['min_messages']) ? (int)$_GET['min_messages'] : '',
    'max_messages' => isset($_GET['max_messages']) ? (int)$_GET['max_messages'] : '',
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'last_activity_date',
    'order' => isset($_GET['order']) ? $_GET['order'] : 'DESC'
];

// Parametry paginacji
$limit = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Pobierz listę użytkowników i zleceń dla filtrów
$allUsers = $userModel->getAllUsers();
$allJobs = $jobModel->getAllJobs();

// Pobierz zgrupowane konwersacje z filtrami
$conversations = $messageModel->getGroupedConversationsWithAdvancedFilters($limit, $offset, $filters);
$totalConversations = $messageModel->countGroupedConversationsWithAdvancedFilters($filters);
$totalPages = ceil($totalConversations / $limit);

// Pobierz użytkownika jeśli filtrujemy
$userDetails = null;
if (!empty($filters['user_id'])) {
    $userDetails = $userModel->getUserById($filters['user_id']);
}

// Pobierz zlecenie jeśli filtrujemy
$jobDetails = null;
if (!empty($filters['job_id'])) {
    $jobDetails = $jobModel->getJobById($filters['job_id']);
}

function safeEcho($data, $default = '') {
    return isset($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $default;
}

function buildUrl($params = []) {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return '?' . http_build_query($currentParams);
}

function isFilterActive($filters) {
    foreach ($filters as $key => $value) {
        if (!empty($value) && $key !== 'sort' && $key !== 'order' && $key !== 'per_page' && $key !== 'page') {
            return true;
        }
    }
    return false;
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <?php if (isset($_GET['status'])): ?>
                        <?php
                        $statusMessages = [
                            'deleted' => __t('admin.messages.deleted'),
                            'updated' => __t('admin.messages.updated'),
                            'message_deleted' => __t('admin.messages.message_deleted'),
                            'moderated' => __t('admin.messages.moderated'),
                            'marked_read' => __t('admin.messages.marked_read'),
                            'marked_unread' => __t('admin.messages.marked_unread'),
                        ];
                        $errorMessages = [
                            'csrf_error' => __t('admin.messages.csrf_error'),
                            'invalid_id' => __t('admin.messages.invalid_conversation_id'),
                            'invalid_message' => __t('admin.messages.invalid_message'),
                            'no_selection' => __t('admin.messages.no_selection'),
                            'delete_error' => __t('admin.messages.delete_error'),
                            'upload_error' => __t('admin.messages.upload_error'),
                            'system_error' => __t('admin.messages.system_error'),
                        ];
                        $isError = $_GET['status'] === 'error';
                        $messageKey = $_GET['message'] ?? '';
                        $alertText = $isError ? ($errorMessages[$messageKey] ?? __t('admin.users.error')) : ($statusMessages[$_GET['status']] ?? '');
                        ?>
                        <?php if ($alertText): ?>
                            <div class="alert alert-<?= $isError ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                                <?= safeEcho($alertText) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= safeEcho(__t('admin.messages.close')) ?>"></button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Nagłówek z powrotem -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> <?= safeEcho(__t('admin.messages.back_to_users')) ?>
                            </a>
                            <?php if (!empty($filters['user_id']) && $userDetails): ?>
                                <span class="ms-2"><?= safeEcho(__t('admin.messages.user_conversations', ['name' => $userDetails['name'], 'id' => $filters['user_id']])) ?></span>
                            <?php elseif (!empty($filters['job_id']) && $jobDetails): ?>
                                <span class="ms-2"><?= safeEcho(__t('admin.messages.job_conversations', ['title' => $jobDetails['title'], 'id' => $filters['job_id']])) ?></span>
                            <?php else: ?>
                                <span class="ms-2"><?= safeEcho(__t('admin.messages.all_conversations')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                                <i class="bi bi-funnel"></i> <?= safeEcho(__t('admin.messages.filters')) ?>
                                <?php if (isFilterActive($filters)): ?>
                                    <span class="badge bg-danger ms-1">!</span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>

                    <!-- Rozwijane filtry -->
                    <div class="collapse <?= isFilterActive($filters) ? 'show' : ''; ?>" id="filtersCollapse">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-funnel"></i> <?= safeEcho(__t('admin.messages.advanced_filters')) ?></h6>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label"><?= safeEcho(__t('admin.common.search')) ?></label>
                                        <input type="text" name="search" class="form-control" 
                                               placeholder="<?= safeEcho(__t('admin.messages.search_placeholder')) ?>"
                                               value="<?= safeEcho($filters['search']); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.user')) ?></label>
                                        <select name="user_id" class="form-select">
                                            <option value=""><?= safeEcho(__t('admin.messages.all_users')) ?></option>
                                            <?php foreach ($allUsers as $user): ?>
                                                <option value="<?= $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                                    <?= safeEcho($user['name']) ?> (ID: <?= $user['id'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.messages.job')) ?></label>
                                        <select name="job_id" class="form-select">
                                            <option value=""><?= safeEcho(__t('admin.messages.all_jobs')) ?></option>
                                            <?php foreach ($allJobs as $job): ?>
                                                <option value="<?= $job['id'] ?>" <?= $filters['job_id'] == $job['id'] ? 'selected' : ''; ?>>
                                                    #<?= $job['id'] ?>: <?= safeEcho(mb_substr($job['title'], 0, 20)) ?>...
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.reports.date_from')) ?></label>
                                        <input type="date" name="date_from" class="form-control" 
                                               value="<?= safeEcho($filters['date_from']); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.reports.date_to')) ?></label>
                                        <input type="date" name="date_to" class="form-control" 
                                               value="<?= safeEcho($filters['date_to']); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.messages.min_messages')) ?></label>
                                        <input type="number" name="min_messages" class="form-control" 
                                               min="1" value="<?= safeEcho($filters['min_messages']); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.messages.max_messages')) ?></label>
                                        <input type="number" name="max_messages" class="form-control" 
                                               min="1" value="<?= safeEcho($filters['max_messages']); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.messages.sort_by')) ?></label>
                                        <select name="sort" class="form-select">
                                            <option value="last_activity_date" <?= $filters['sort'] == 'last_activity_date' ? 'selected' : ''; ?>><?= safeEcho(__t('admin.messages.last_activity')) ?></option>
                                            <option value="message_count" <?= $filters['sort'] == 'message_count' ? 'selected' : ''; ?>><?= safeEcho(__t('admin.messages.message_count')) ?></option>
                                            <option value="sender_name" <?= $filters['sort'] == 'sender_name' ? 'selected' : ''; ?>><?= safeEcho(__t('admin.messages.sender')) ?></option>
                                            <option value="receiver_name" <?= $filters['sort'] == 'receiver_name' ? 'selected' : ''; ?>><?= safeEcho(__t('admin.messages.receiver')) ?></option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.messages.order')) ?></label>
                                        <select name="order" class="form-select">
                                            <option value="DESC" <?= $filters['order'] == 'DESC' ? 'selected' : ''; ?>><?= safeEcho(__t('admin.messages.desc')) ?></option>
                                            <option value="ASC" <?= $filters['order'] == 'ASC' ? 'selected' : ''; ?>><?= safeEcho(__t('admin.messages.asc')) ?></option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label"><?= safeEcho(__t('admin.messages.per_page')) ?></label>
                                        <select name="per_page" class="form-select">
                                            <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                            <option value="25" <?= $limit == 25 ? 'selected' : ''; ?>>25</option>
                                            <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                            <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-search"></i> <?= safeEcho(__t('admin.messages.apply_filters')) ?>
                                            </button>
                                            <a href="<?= buildUrl([
                                                'user_id' => null, 
                                                'job_id' => null, 
                                                'search' => null, 
                                                'date_from' => null, 
                                                'date_to' => null, 
                                                'min_messages' => null, 
                                                'max_messages' => null,
                                                'page' => 1
                                            ]) ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-circle"></i> <?= safeEcho(__t('admin.messages.clear_filters')) ?>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Statystyki filtrów -->
                    <?php if (isFilterActive($filters)): ?>
                    <div class="alert alert-info mb-4">
                        <strong><?= safeEcho(__t('admin.messages.active_filters')) ?></strong>
                        <?php
                        $activeFilters = [];
                        if (!empty($filters['user_id'])) $activeFilters[] = __t('admin.user') . ': ID ' . $filters['user_id'];
                        if (!empty($filters['job_id'])) $activeFilters[] = __t('admin.messages.job') . ': ID ' . $filters['job_id'];
                        if (!empty($filters['search'])) $activeFilters[] = __t('admin.common.search') . ': "' . $filters['search'] . '"';
                        if (!empty($filters['date_from'])) $activeFilters[] = __t('admin.reports.date_from') . ': ' . $filters['date_from'];
                        if (!empty($filters['date_to'])) $activeFilters[] = __t('admin.reports.date_to') . ': ' . $filters['date_to'];
                        if (!empty($filters['min_messages'])) $activeFilters[] = __t('admin.messages.min_messages') . ': ' . $filters['min_messages'];
                        if (!empty($filters['max_messages'])) $activeFilters[] = __t('admin.messages.max_messages') . ': ' . $filters['max_messages'];
                        
                        echo implode(', ', $activeFilters);
                        ?>
                    </div>
                    <?php endif; ?>

                    <!-- Tabela konwersacji -->
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-chat-dots"></i> <?= safeEcho(__t('admin.messages.manage_conversations')) ?></h5>
                            <div class="text-muted">
                                <?= safeEcho(__t('admin.messages.found_count', ['count' => number_format($totalConversations)])) ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <form method="POST" action="manage_messages.php" id="bulkConversationsForm">
                                <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="<?= safeEcho(__t('admin.messages.bulk_actions')) ?>">
                                        <button type="submit" name="action" value="mark_read" class="btn btn-outline-success">
                                            <i class="bi bi-envelope-open"></i> <?= safeEcho(__t('admin.messages.mark_read')) ?>
                                        </button>
                                        <button type="submit" name="action" value="mark_unread" class="btn btn-outline-secondary">
                                            <i class="bi bi-envelope"></i> <?= safeEcho(__t('admin.messages.mark_unread')) ?>
                                        </button>
                                        <button type="submit" formaction="delete_conversations.php" class="btn btn-outline-danger" onclick="return confirm('<?= safeEcho(__t('admin.messages.delete_selected_confirm')) ?>');">
                                            <i class="bi bi-trash"></i> <?= safeEcho(__t('admin.messages.delete_selected')) ?>
                                        </button>
                                    </div>
                                    <small class="text-muted"><?= safeEcho(__t('admin.messages.bulk_hint')) ?></small>
                                </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 36px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllConversations" title="<?= safeEcho(__t('admin.messages.select_all')) ?>">
                                            </th>
                                            <th><?= safeEcho(__t('admin.messages.conversation_id')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.sender')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.receiver')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.job')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.last_message')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.messages')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.intervention')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.last_activity')) ?></th>
                                            <th><?= safeEcho(__t('admin.messages.actions')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($conversations)): ?>
                                            <?php foreach ($conversations as $conversation): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="form-check-input conversation-checkbox" name="conversation_ids[]" value="<?= safeEcho($conversation['conversation_id']); ?>">
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">#<?= safeEcho($conversation['conversation_id']); ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="view_user.php?id=<?= safeEcho($conversation['sender_id']) ?>" class="text-decoration-none">
                                                            <?= safeEcho($conversation['sender_name']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <a href="view_user.php?id=<?= safeEcho($conversation['receiver_id']) ?>" class="text-decoration-none">
                                                            <?= safeEcho($conversation['receiver_name']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($conversation['job_id'])): ?>
                                                            <a href="../jobs/view.php?id=<?= safeEcho($conversation['job_id']) ?>" class="text-decoration-none" target="_blank">
                                                                #<?= safeEcho($conversation['job_id']) ?>: <?= safeEcho(mb_substr($conversation['job_title'], 0, 15)) ?><?= mb_strlen($conversation['job_title']) > 15 ? '...' : '' ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted"><?= safeEcho(__t('admin.messages.no_job')) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $content = !empty($conversation['last_message_content']) ? $conversation['last_message_content'] : __t('admin.messages.no_content');
                                                        echo safeEcho(mb_substr($content, 0, 30)) . (mb_strlen($content) > 30 ? '...' : '');
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= safeEcho($conversation['message_count']) ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($conversation['open_report_count'])): ?>
                                                            <span class="badge bg-danger" title="<?= safeEcho(__t('admin.messages.needs_intervention')) ?>">
                                                                <i class="bi bi-flag-fill"></i> <?= (int)$conversation['open_report_count'] ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('Y-m-d', strtotime($conversation['last_activity_date'])) ?></small>
                                                        <br><small class="text-muted"><?= date('H:i', strtotime($conversation['last_activity_date'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-info view-conversation" 
                                                                    data-bs-toggle="modal" data-bs-target="#conversationModal" 
                                                                    data-conversation-id="<?= safeEcho($conversation['conversation_id']); ?>">
                                                                <i class="bi bi-eye" title="<?= safeEcho(__t('admin.messages.preview_conversation')) ?>"></i>
                                                            </button>
                                                            <a href="delete_conversation.php?id=<?= safeEcho($conversation['conversation_id']); ?>&csrf_token=<?= safeEcho($_SESSION['csrf_token']) ?>" 
                                                               class="btn btn-outline-danger" 
                                                               onclick="return confirm('<?= safeEcho(__t('admin.messages.delete_conversation_confirm')) ?>');">
                                                                <i class="bi bi-trash" title="<?= safeEcho(__t('admin.messages.delete_conversation')) ?>"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-5">
                                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                                    <p class="mt-3"><?= safeEcho(__t('admin.messages.no_results')) ?></p>
                                                    <?php if (isFilterActive($filters)): ?>
                                                        <a href="<?= buildUrl([
                                                            'user_id' => null, 
                                                            'job_id' => null, 
                                                            'search' => null, 
                                                            'date_from' => null, 
                                                            'date_to' => null, 
                                                            'min_messages' => null, 
                                                            'max_messages' => null,
                                                            'page' => 1
                                                        ]) ?>" class="btn btn-primary btn-sm">
                                                            <?= safeEcho(__t('admin.messages.clear_filters')) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            </form>
                            
                            <!-- Paginacja -->
                            <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    <span class="text-muted">
                                        <?= safeEcho(__t('admin.messages.displayed_count', ['shown' => count($conversations), 'total' => number_format($totalConversations)])) ?>
                                    </span>
                                </div>
                                <div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination pagination-sm">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => 1]) ?>" aria-label="<?= safeEcho(__t('admin.messages.first_page')) ?>">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $page - 1]) ?>" aria-label="<?= safeEcho(__t('admin.messages.previous_page')) ?>">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <?php 
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <li class="page-item <?= $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $i]) ?>"><?= $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $page + 1]) ?>" aria-label="<?= safeEcho(__t('admin.messages.next_page')) ?>">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $totalPages]) ?>" aria-label="<?= safeEcho(__t('admin.messages.last_page')) ?>">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal do podglądu konwersacji -->
<div class="modal fade" id="conversationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= safeEcho(__t('admin.messages.preview_conversation')) ?> <span id="modalConversationId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= safeEcho(__t('admin.messages.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div id="conversationContent" class="conversation-messages">
                    <p class="text-center text-muted"><?= safeEcho(__t('admin.messages.loading_messages')) ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= safeEcho(__t('admin.messages.close')) ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal do moderacji wiadomości -->
<div class="modal fade" id="moderateMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="manage_messages.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><?= safeEcho(__t('admin.conversation.moderate_message')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= safeEcho(__t('admin.messages.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="moderate_message">
                    <input type="hidden" name="message_id" id="moderateMessageId">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_hidden" id="moderateIsHidden" value="1">
                        <label class="form-check-label" for="moderateIsHidden"><?= safeEcho(__t('admin.messages.hide_content')) ?></label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="moderateAdminNote"><?= safeEcho(__t('admin.messages.admin_note_label')) ?></label>
                            <textarea name="admin_note" id="moderateAdminNote" class="form-control" rows="5"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="moderateParticipantNote"><?= safeEcho(__t('admin.messages.participant_note_label')) ?></label>
                            <textarea name="participant_note" id="moderateParticipantNote" class="form-control" rows="5"></textarea>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label" for="moderateMessageImage"><?= safeEcho(__t('admin.messages.replace_image')) ?></label>
                        <input type="file" name="message_image" id="moderateMessageImage" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-text"><?= safeEcho(__t('admin.messages.image_help')) ?></div>
                    </div>

                    <div id="currentModerationImageWrap" class="d-none">
                        <label class="form-label"><?= safeEcho(__t('admin.messages.current_image')) ?></label>
                        <div class="d-flex align-items-start gap-3">
                            <img id="currentModerationImage" src="" alt="<?= safeEcho(__t('admin.messages.current_image_alt')) ?>" class="img-fluid rounded border" style="max-height: 180px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="moderateRemoveImage">
                                <label class="form-check-label" for="moderateRemoveImage"><?= safeEcho(__t('admin.messages.remove_image')) ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= safeEcho(__t('admin.users.cancel')) ?></button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-shield-check"></i> <?= safeEcho(__t('admin.messages.save_moderation')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal do edycji wiadomości -->
<div class="modal fade" id="editMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_messages.php">
                <div class="modal-header">
                    <h5 class="modal-title"><?= safeEcho(__t('admin.messages.edit_message')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= safeEcho(__t('admin.messages.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_message">
                    <input type="hidden" name="message_id" id="editMessageId">
                    <label class="form-label" for="editMessageContent"><?= safeEcho(__t('admin.messages.message_content')) ?></label>
                    <textarea name="content" id="editMessageContent" class="form-control" rows="6" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= safeEcho(__t('admin.users.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?= safeEcho(__t('admin.common.save')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadingErrorMessage = <?= json_encode(__t('admin.messages.loading_error'), JSON_UNESCAPED_UNICODE) ?>;
    const selectAll = document.getElementById('selectAllConversations');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.conversation-checkbox').forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    document.addEventListener('click', function(event) {
        const editButton = event.target.closest('.edit-message-btn');
        if (editButton) {
            document.getElementById('editMessageId').value = editButton.getAttribute('data-message-id');
            document.getElementById('editMessageContent').value = editButton.getAttribute('data-message-content') || '';
        }

        const moderateButton = event.target.closest('.moderate-message-btn');
        if (moderateButton) {
            const imagePath = moderateButton.getAttribute('data-image-path') || '';

            document.getElementById('moderateMessageId').value = moderateButton.getAttribute('data-message-id');
            document.getElementById('moderateIsHidden').checked = moderateButton.getAttribute('data-is-hidden') === '1';
            document.getElementById('moderateAdminNote').value = moderateButton.getAttribute('data-admin-note') || '';
            document.getElementById('moderateParticipantNote').value = moderateButton.getAttribute('data-participant-note') || '';
            document.getElementById('moderateMessageImage').value = '';
            document.getElementById('moderateRemoveImage').checked = false;

            const imageWrap = document.getElementById('currentModerationImageWrap');
            const imagePreview = document.getElementById('currentModerationImage');
            if (imagePath) {
                imagePreview.src = imagePath;
                imageWrap.classList.remove('d-none');
            } else {
                imagePreview.src = '';
                imageWrap.classList.add('d-none');
            }
        }
    });

    // Modal do podglądu konwersacji
    const conversationModal = document.getElementById('conversationModal');
    if (conversationModal) {
        conversationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const conversationId = button.getAttribute('data-conversation-id');
            
            document.getElementById('modalConversationId').textContent = '#' + conversationId;
            
            // Pobierz zawartość konwersacji przez AJAX
            fetch('get_conversation_content.php?conversation_id=' + conversationId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('conversationContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('conversationContent').innerHTML = 
                        '<p class="text-danger">' + loadingErrorMessage + '</p>';
                });
        });
    }

    // Automatyczne zamykanie alertów po 5 sekundach
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>
