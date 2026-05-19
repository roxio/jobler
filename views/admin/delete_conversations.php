<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

include_once('../../config/config.php');
include_once('../../models/Message.php');

$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    header('Location: manage_messages.php?status=error&message=csrf_error');
    exit();
}

$conversationIds = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conversationIds = $_POST['conversation_ids'] ?? [];
} elseif (isset($_GET['id'])) {
    $conversationIds = [$_GET['id']];
}

$conversationIds = array_values(array_filter(array_map('trim', (array)$conversationIds), static function ($id) {
    return $id !== '';
}));

if (empty($conversationIds)) {
    header('Location: manage_messages.php?status=error&message=invalid_id');
    exit();
}

$messageModel = new Message($pdo);

try {
    if ($messageModel->deleteConversations($conversationIds)) {
        header('Location: manage_messages.php?status=deleted&count=' . count($conversationIds));
    } else {
        header('Location: manage_messages.php?status=error&message=delete_error');
    }
} catch (Exception $e) {
    error_log("Błąd przy usuwaniu konwersacji: " . $e->getMessage());
    header('Location: manage_messages.php?status=error&message=system_error');
}
exit();
