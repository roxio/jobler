<?php
session_start();
include_once('../../config/config.php');
include_once('../../models/Message.php');
include_once('../../models/User.php');
include_once('../../models/Job.php');

// Sprawdź czy użytkownik jest zalogowany i ma uprawnienia administratora
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

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
        throw new RuntimeException('Nie udało się przesłać obrazu lub plik jest większy niż 5 MB.');
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
        throw new RuntimeException('Dozwolone są tylko obrazy JPG, PNG, GIF i WEBP.');
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads/message_images';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'message_' . bin2hex(random_bytes(12)) . '.' . $allowedMimeTypes[$mimeType];
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Nie udało się zapisać obrazu.');
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
                            'deleted' => 'Konwersacja została usunięta.',
                            'updated' => 'Wiadomość została zaktualizowana.',
                            'message_deleted' => 'Wiadomość została usunięta.',
                            'moderated' => 'Ustawienia moderacji wiadomości zostały zapisane.',
                            'marked_read' => 'Wybrane konwersacje oznaczono jako przeczytane.',
                            'marked_unread' => 'Wybrane konwersacje oznaczono jako nieprzeczytane.',
                        ];
                        $errorMessages = [
                            'csrf_error' => 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.',
                            'invalid_id' => 'Nieprawidłowe ID konwersacji.',
                            'invalid_message' => 'Nieprawidłowa lub pusta treść wiadomości.',
                            'no_selection' => 'Wybierz co najmniej jedną konwersację.',
                            'delete_error' => 'Nie udało się usunąć konwersacji.',
                            'upload_error' => 'Nie udało się zapisać obrazu. Sprawdź format i rozmiar pliku.',
                            'system_error' => 'Wystąpił błąd systemowy.',
                        ];
                        $isError = $_GET['status'] === 'error';
                        $messageKey = $_GET['message'] ?? '';
                        $alertText = $isError ? ($errorMessages[$messageKey] ?? 'Wystąpił błąd.') : ($statusMessages[$_GET['status']] ?? '');
                        ?>
                        <?php if ($alertText): ?>
                            <div class="alert alert-<?= $isError ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                                <?= safeEcho($alertText) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Nagłówek z powrotem -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Powrót do użytkowników
                            </a>
                            <?php if (!empty($filters['user_id']) && $userDetails): ?>
                                <span class="ms-2">Konwersacje użytkownika: <?= safeEcho($userDetails['name']) ?> (ID: <?= $filters['user_id'] ?>)</span>
                            <?php elseif (!empty($filters['job_id']) && $jobDetails): ?>
                                <span class="ms-2">Konwersacje zlecenia: <?= safeEcho($jobDetails['title']) ?> (ID: <?= $filters['job_id'] ?>)</span>
                            <?php else: ?>
                                <span class="ms-2">Wszystkie konwersacje</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                                <i class="bi bi-funnel"></i> Filtry
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
                                <h6 class="mb-0"><i class="bi bi-funnel"></i> Zaawansowane filtry</h6>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Wyszukaj</label>
                                        <input type="text" name="search" class="form-control" 
                                               placeholder="Treść, użytkownik, zlecenie..." 
                                               value="<?= safeEcho($filters['search']); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Użytkownik</label>
                                        <select name="user_id" class="form-select">
                                            <option value="">Wszyscy użytkownicy</option>
                                            <?php foreach ($allUsers as $user): ?>
                                                <option value="<?= $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                                    <?= safeEcho($user['name']) ?> (ID: <?= $user['id'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Zlecenie</label>
                                        <select name="job_id" class="form-select">
                                            <option value="">Wszystkie zlecenia</option>
                                            <?php foreach ($allJobs as $job): ?>
                                                <option value="<?= $job['id'] ?>" <?= $filters['job_id'] == $job['id'] ? 'selected' : ''; ?>>
                                                    #<?= $job['id'] ?>: <?= safeEcho(mb_substr($job['title'], 0, 20)) ?>...
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Data od</label>
                                        <input type="date" name="date_from" class="form-control" 
                                               value="<?= safeEcho($filters['date_from']); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Data do</label>
                                        <input type="date" name="date_to" class="form-control" 
                                               value="<?= safeEcho($filters['date_to']); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Min. wiadomości</label>
                                        <input type="number" name="min_messages" class="form-control" 
                                               min="1" value="<?= safeEcho($filters['min_messages']); ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Max. wiadomości</label>
                                        <input type="number" name="max_messages" class="form-control" 
                                               min="1" value="<?= safeEcho($filters['max_messages']); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Sortuj według</label>
                                        <select name="sort" class="form-select">
                                            <option value="last_activity_date" <?= $filters['sort'] == 'last_activity_date' ? 'selected' : ''; ?>>Ostatnia aktywność</option>
                                            <option value="message_count" <?= $filters['sort'] == 'message_count' ? 'selected' : ''; ?>>Liczba wiadomości</option>
                                            <option value="sender_name" <?= $filters['sort'] == 'sender_name' ? 'selected' : ''; ?>>Nadawca</option>
                                            <option value="receiver_name" <?= $filters['sort'] == 'receiver_name' ? 'selected' : ''; ?>>Odbiorca</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Kolejność</label>
                                        <select name="order" class="form-select">
                                            <option value="DESC" <?= $filters['order'] == 'DESC' ? 'selected' : ''; ?>>Malejąco</option>
                                            <option value="ASC" <?= $filters['order'] == 'ASC' ? 'selected' : ''; ?>>Rosnąco</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Na stronę</label>
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
                                                <i class="bi bi-search"></i> Zastosuj filtry
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
                                                <i class="bi bi-x-circle"></i> Wyczyść filtry
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
                        <strong>Aktywne filtry:</strong>
                        <?php
                        $activeFilters = [];
                        if (!empty($filters['user_id'])) $activeFilters[] = 'Użytkownik: ID ' . $filters['user_id'];
                        if (!empty($filters['job_id'])) $activeFilters[] = 'Zlecenie: ID ' . $filters['job_id'];
                        if (!empty($filters['search'])) $activeFilters[] = 'Szukaj: "' . $filters['search'] . '"';
                        if (!empty($filters['date_from'])) $activeFilters[] = 'Data od: ' . $filters['date_from'];
                        if (!empty($filters['date_to'])) $activeFilters[] = 'Data do: ' . $filters['date_to'];
                        if (!empty($filters['min_messages'])) $activeFilters[] = 'Min. wiadomości: ' . $filters['min_messages'];
                        if (!empty($filters['max_messages'])) $activeFilters[] = 'Max. wiadomości: ' . $filters['max_messages'];
                        
                        echo implode(', ', $activeFilters);
                        ?>
                    </div>
                    <?php endif; ?>

                    <!-- Tabela konwersacji -->
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-chat-dots"></i> Zarządzaj konwersacjami</h5>
                            <div class="text-muted">
                                Znaleziono: <strong><?= number_format($totalConversations) ?></strong> konwersacji
                            </div>
                        </div>

                        <div class="card-body">
                            <form method="POST" action="manage_messages.php" id="bulkConversationsForm">
                                <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Akcje zbiorcze">
                                        <button type="submit" name="action" value="mark_read" class="btn btn-outline-success">
                                            <i class="bi bi-envelope-open"></i> Oznacz jako przeczytane
                                        </button>
                                        <button type="submit" name="action" value="mark_unread" class="btn btn-outline-secondary">
                                            <i class="bi bi-envelope"></i> Oznacz jako nieprzeczytane
                                        </button>
                                        <button type="submit" formaction="delete_conversations.php" class="btn btn-outline-danger" onclick="return confirm('Czy na pewno usunąć wybrane konwersacje?');">
                                            <i class="bi bi-trash"></i> Usuń wybrane
                                        </button>
                                    </div>
                                    <small class="text-muted">Zaznacz konwersacje w tabeli, aby użyć akcji zbiorczych.</small>
                                </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 36px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllConversations" title="Zaznacz wszystkie">
                                            </th>
                                            <th>ID Konwersacji</th>
                                            <th>Nadawca</th>
                                            <th>Odbiorca</th>
                                            <th>Zlecenie</th>
                                            <th>Ostatnia wiadomość</th>
                                            <th>Wiadomości</th>
                                            <th>Interwencja</th>
                                            <th>Ostatnia aktywność</th>
                                            <th>Akcje</th>
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
                                                            <span class="text-muted">Brak zlecenia</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $content = !empty($conversation['last_message_content']) ? $conversation['last_message_content'] : 'Brak treści';
                                                        echo safeEcho(mb_substr($content, 0, 30)) . (mb_strlen($content) > 30 ? '...' : '');
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= safeEcho($conversation['message_count']) ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($conversation['open_report_count'])): ?>
                                                            <span class="badge bg-danger" title="Konwersacja wymaga interwencji">
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
                                                                <i class="bi bi-eye" title="Podgląd konwersacji"></i>
                                                            </button>
                                                            <a href="delete_conversation.php?id=<?= safeEcho($conversation['conversation_id']); ?>&csrf_token=<?= safeEcho($_SESSION['csrf_token']) ?>" 
                                                               class="btn btn-outline-danger" 
                                                               onclick="return confirm('Czy na pewno chcesz usunąć tę konwersację?');">
                                                                <i class="bi bi-trash" title="Usuń konwersację"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-5">
                                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                                    <p class="mt-3">Brak konwersacji spełniających kryteria wyszukiwania</p>
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
                                                            Wyczyść filtry
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
                                        Wyświetlono <?= count($conversations) ?> z <?= number_format($totalConversations) ?> konwersacji
                                    </span>
                                </div>
                                <div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination pagination-sm">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => 1]) ?>" aria-label="Pierwsza">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $page - 1]) ?>" aria-label="Poprzednia">
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
                                                <a class="page-link" href="<?= buildUrl(['page' => $page + 1]) ?>" aria-label="Następna">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $totalPages]) ?>" aria-label="Ostatnia">
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
                <h5 class="modal-title">Podgląd konwersacji <span id="modalConversationId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="conversationContent" class="conversation-messages">
                    <p class="text-center text-muted">Ładowanie wiadomości...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
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
                    <h5 class="modal-title">Moderuj wiadomość</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="moderate_message">
                    <input type="hidden" name="message_id" id="moderateMessageId">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_hidden" id="moderateIsHidden" value="1">
                        <label class="form-check-label" for="moderateIsHidden">Ukryj treść wiadomości przed uczestnikami rozmowy</label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="moderateAdminNote">Komentarz widoczny tylko dla administratorów</label>
                            <textarea name="admin_note" id="moderateAdminNote" class="form-control" rows="5"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="moderateParticipantNote">Komentarz widoczny dla uczestników rozmowy</label>
                            <textarea name="participant_note" id="moderateParticipantNote" class="form-control" rows="5"></textarea>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label" for="moderateMessageImage">Dodaj lub podmień obraz</label>
                        <input type="file" name="message_image" id="moderateMessageImage" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-text">JPG, PNG, GIF lub WEBP, maksymalnie 5 MB.</div>
                    </div>

                    <div id="currentModerationImageWrap" class="d-none">
                        <label class="form-label">Aktualny obraz</label>
                        <div class="d-flex align-items-start gap-3">
                            <img id="currentModerationImage" src="" alt="Aktualny obraz wiadomości" class="img-fluid rounded border" style="max-height: 180px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="moderateRemoveImage">
                                <label class="form-check-label" for="moderateRemoveImage">Usuń obraz</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-shield-check"></i> Zapisz moderację
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
                    <h5 class="modal-title">Edytuj wiadomość</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= safeEcho($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_message">
                    <input type="hidden" name="message_id" id="editMessageId">
                    <label class="form-label" for="editMessageContent">Treść wiadomości</label>
                    <textarea name="content" id="editMessageContent" class="form-control" rows="6" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Zapisz
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
                        '<p class="text-danger">Błąd podczas ładowania konwersacji.</p>';
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
