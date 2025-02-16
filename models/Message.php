<?php

include_once('Database.php');

class Message {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Wysyłanie wiadomości
    public function sendMessage($senderId, $receiverId, $content) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, content, created_at, read_status)
                VALUES (:sender_id, :receiver_id, :content, NOW(), 0)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':sender_id', $senderId);
        $stmt->bindParam(':receiver_id', $receiverId);
        $stmt->bindParam(':content', $content);
        return $stmt->execute();
    }

    // Pobieranie wiadomości otrzymanych przez użytkownika
    public function getReceivedMessages($userId) {
        $sql = "SELECT m.*, u.username AS sender_name FROM messages m 
                JOIN users u ON m.sender_id = u.id
                WHERE m.receiver_id = :user_id
                ORDER BY m.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pobieranie wiadomości wysłanych przez użytkownika
    public function getSentMessages($userId) {
        $sql = "SELECT m.*, u.username AS receiver_name FROM messages m 
                JOIN users u ON m.receiver_id = u.id
                WHERE m.sender_id = :user_id
                ORDER BY m.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pobieranie szczegółów wiadomości
    public function getMessageDetails($messageId) {
        $sql = "SELECT m.*, u1.username AS sender_name, u2.username AS receiver_name 
                FROM messages m
                JOIN users u1 ON m.sender_id = u1.id
                JOIN users u2 ON m.receiver_id = u2.id
                WHERE m.id = :message_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':message_id', $messageId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Zmiana statusu przeczytania wiadomości
    public function markAsRead($messageId) {
        $sql = "UPDATE messages SET read_status = 1 WHERE id = :message_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':message_id', $messageId);
        return $stmt->execute();
    }

    // Usuwanie wiadomości
    public function deleteMessage($messageId) {
        $sql = "DELETE FROM messages WHERE id = :message_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':message_id', $messageId);
        return $stmt->execute();
    }
}
?>
