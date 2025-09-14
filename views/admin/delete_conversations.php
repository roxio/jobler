<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

include_once('../../config/config.php');
include_once('../../models/Message.php');

// Sprawdź token CSRF
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_messages.php?status=error&message=csrf_error');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_messages.php?status=error&message=invalid_id');
    exit();
}

$conversationId = (int)$_GET['id'];
$messageModel = new Message($pdo);

try {
    // Usuń wszystkie wiadomości w konwersacji
    $sql = "DELETE FROM messages WHERE conversation_id = :conversation_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
    $result = $stmt->execute();
    
    if ($result) {
        header('Location: manage_messages.php?status=deleted');
    } else {
        header('Location: manage_messages.php?status=error&message=delete_error');
    }
} catch (Exception $e) {
    error_log("Błąd przy usuwaniu konwersacji: " . $e->getMessage());
    header('Location: manage_messages.php?status=error&message=system_error');
}