<?php

include_once('Database.php');

class Message {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Pobieranie konwersacji między dwoma użytkownikami w kontekście danego ogłoszenia
public function getConversation($userId, $executorId, $jobId)
{
    // Przygotowanie zapytania SQL z JOIN dla użytkowników oraz treści wiadomości
    $query = $this->pdo->prepare("
        SELECT m.*, 
               u1.name AS sender_name, 
               u2.name AS receiver_name,
               m.content AS message_content
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        WHERE 
            (m.sender_id = :userId AND m.receiver_id = :executorId) 
            OR 
            (m.sender_id = :executorId AND m.receiver_id = :userId)
        AND m.job_id = :jobId
        ORDER BY m.created_at ASC
    ");
    
    // Przypisanie wartości do parametrów zapytania
    $query->bindParam(':userId', $userId);
    $query->bindParam(':executorId', $executorId);
    $query->bindParam(':jobId', $jobId);
    
    // Wykonanie zapytania
    $query->execute();
    
    // Zwrócenie wyników zapytania
    return $query->fetchAll(PDO::FETCH_ASSOC);
}


    // Wysyłanie wiadomości
    public function sendMessage($senderId, $receiverId, $content, $jobId) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, content, job_id, created_at, read_status)
                VALUES (:sender_id, :receiver_id, :content, :job_id, NOW(), 0)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
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
}

?>
