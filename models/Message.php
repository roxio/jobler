<?php

include_once('Database.php');

class Message {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Pobieranie konwersacji między dwoma użytkownikami w kontekście danego ogłoszenia
    public function getConversation($userId, $executorId, $jobId) {
        $sql = "
            SELECT 
                m.id,
                m.sender_id,
                m.receiver_id,
                m.content AS message,
                m.created_at,
                m.read_status,
                u.username AS sender_name
            FROM 
                messages m
            JOIN 
                users u ON m.sender_id = u.id
            WHERE 
                (m.sender_id = :user_id AND m.receiver_id = :executor_id OR 
                 m.sender_id = :executor_id AND m.receiver_id = :user_id)
                AND m.job_id = :job_id
            ORDER BY 
                m.created_at ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':executor_id', $executorId, PDO::PARAM_INT);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
	
//	public function sendMessage($senderId, $receiverId, $content, $jobId) {
//    $sql = "INSERT INTO messages (sender_id, receiver_id, content, job_id, created_at, read_status)
//            VALUES (:sender_id, :receiver_id, :content, :job_id, NOW(), 0)";
 //   $stmt = $this->pdo->prepare($sql);
 //   $stmt->bindParam(':sender_id', $senderId);
 //   $stmt->bindParam(':receiver_id', $receiverId);
 //   $stmt->bindParam(':content', $content);
 //   $stmt->bindParam(':job_id', $jobId);
  //  return $stmt->execute();
//}

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
