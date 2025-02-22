<?php

include_once('Database.php');

class Message {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Pobieranie konwersacji między dwoma użytkownikami w kontekście danego ogłoszenia
    public function getConversation($userId, $executorId, $jobId) {
        // Generowanie conversation_id na podstawie id użytkowników
        $conversationId = min($userId, $executorId) . "_" . max($userId, $executorId);

        // Pobranie wszystkich wiadomości dla danego conversation_id
        $query = $this->pdo->prepare("
            SELECT m.*, 
                   u1.name AS sender_name, 
                   u2.name AS receiver_name,
                   m.content AS message_content
            FROM messages m
            LEFT JOIN users u1 ON m.sender_id = u1.id
            LEFT JOIN users u2 ON m.receiver_id = u2.id
            WHERE m.conversation_id = :conversation_id
            ORDER BY m.created_at ASC
        ");

        $query->bindParam(':conversation_id', $conversationId);
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Wysyłanie wiadomości
    public function sendMessage($senderId, $receiverId, $content, $jobId) {
        // Generowanie conversation_id na podstawie id użytkowników
        $conversationId = min($senderId, $receiverId) . "_" . max($senderId, $receiverId);

        $sql = "INSERT INTO messages (sender_id, receiver_id, content, job_id, created_at, read_status, conversation_id)
                VALUES (:sender_id, :receiver_id, :content, :job_id, NOW(), 0, :conversation_id)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->bindParam(':conversation_id', $conversationId, PDO::PARAM_STR);

        return $stmt->execute();
    }

    // Zmiana statusu przeczytania wiadomości
    public function markAsRead($messageId) {
        $sql = "UPDATE messages SET read_status = 1 WHERE id = :message_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Usuwanie wiadomości
    public function deleteMessage($messageId) {
        $sql = "DELETE FROM messages WHERE id = :message_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        return $stmt->execute();
    }
public function getConversationById($conversationId)
{
    $query = $this->pdo->prepare("
        SELECT m.*, 
               u1.name AS sender_name, 
               u2.name AS receiver_name,
               m.content AS message_content
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        WHERE m.conversation_id = :conversationId
        ORDER BY m.created_at ASC
    ");
    $query->bindParam(':conversationId', $conversationId, PDO::PARAM_STR);
    $query->execute();

    return $query->fetchAll(PDO::FETCH_ASSOC);
}
public function getAllConversations() {
    $query = $this->pdo->query("SELECT DISTINCT conversation_id, job_id FROM messages ORDER BY created_at DESC");
    return $query->fetchAll(PDO::FETCH_ASSOC);
}



}

?>
