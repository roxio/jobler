<?php
class Message {
    private $pdo;
    
    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            include_once('../config/config.php');
            $this->pdo = $pdo;
        }
    }

    public function getUserConversations($userId, $limit = 5) {
    $sql = "SELECT 
        m.id as message_id,
        m.job_id,
        m.content,
        m.message,
        m.created_at,
        m.created_at as last_activity_date,
        1 as message_count,
        CASE 
            WHEN m.sender_id = :user_id THEN m.receiver_id 
            ELSE m.sender_id 
        END as other_user_id,
        CASE 
            WHEN m.sender_id = :user_id THEN u2.name 
            ELSE u1.name 
        END as other_user_name,
        j.title as job_title,
        'message' as type
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        LEFT JOIN jobs j ON m.job_id = j.id
        WHERE m.sender_id = :user_id OR m.receiver_id = :user_id
        ORDER BY m.created_at DESC
        LIMIT :limit";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

 public function getUserConversationStats($userId) {
    $sql = "SELECT 
        COUNT(*) as total_conversations,
        COUNT(*) as total_messages
        FROM messages
        WHERE sender_id = :user_id OR receiver_id = :user_id";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

  public function getConversationsWithFilters($limit, $offset, $userId = '') {
    $sql = "SELECT 
        m.id as message_id,
        m.job_id,
        m.content,
        m.message,
        m.created_at,
        m.created_at as last_activity_date,
        1 as message_count,
        m.sender_id,
        m.receiver_id,
        u1.name as sender_name,
        u2.name as receiver_name,
        j.title as job_title,
        'message' as type
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        LEFT JOIN jobs j ON m.job_id = j.id
        WHERE 1=1";
    
    if (!empty($userId)) {
        $sql .= " AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
    }
    
    $sql .= " ORDER BY m.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $this->pdo->prepare($sql);
    
    if (!empty($userId)) {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function countConversationsWithFilters($userId = '') {
        $sql = "SELECT COUNT(*) as count
                FROM messages
                WHERE 1=1";
        
        if (!empty($userId)) {
            $sql .= " AND (sender_id = :user_id OR receiver_id = :user_id)";
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($userId)) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    }

    // Inne metody
    public function getMessageById($id) {
        $sql = "SELECT m.*, u1.name as sender_name, u2.name as receiver_name, j.title as job_title
                FROM messages m 
                LEFT JOIN users u1 ON m.sender_id = u1.id 
                LEFT JOIN users u2 ON m.receiver_id = u2.id
                LEFT JOIN jobs j ON m.job_id = j.id
                WHERE m.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

public function getResponsesByMessageId($messageId) {
    $originalMessage = $this->getMessageById($messageId);
    
    if (!$originalMessage) {
        return [];
    }
    
    $sql = "SELECT r.*, u.name as executor_name 
            FROM responses r 
            LEFT JOIN users u ON r.executor_id = u.id 
            WHERE r.job_id = :job_id 
            ORDER BY r.created_at ASC";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':job_id', $originalMessage['job_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function createMessage($senderId, $receiverId, $jobId, $content, $messageText) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, job_id, content, message, created_at) 
                VALUES (:sender_id, :receiver_id, :job_id, :content, :message, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindValue(':receiver_id', $receiverId, PDO::PARAM_INT);
        $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':message', $messageText);
        return $stmt->execute();
    }

    public function createResponse($messageId, $senderId, $content, $messageText) {
        // Najpierw pobierz oryginalną wiadomość, aby uzyskać job_id i odbiorcę
        $originalMessage = $this->getMessageById($messageId);
        
        if (!$originalMessage) {
            return false;
        }
        
        // Określ odbiorcę odpowiedzi (przeciwny użytkownik niż nadawca)
        $receiverId = ($senderId == $originalMessage['sender_id']) 
            ? $originalMessage['receiver_id'] 
            : $originalMessage['sender_id'];
        
        $sql = "INSERT INTO responses (sender_id, receiver_id, job_id, content, message, created_at) 
                VALUES (:sender_id, :receiver_id, :job_id, :content, :message, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindValue(':receiver_id', $receiverId, PDO::PARAM_INT);
        $stmt->bindValue(':job_id', $originalMessage['job_id'], PDO::PARAM_INT);
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':message', $messageText);
        return $stmt->execute();
    }

    // Pobierz odpowiedzi dla danego job_id i użytkowników
public function getResponsesForJob($jobId, $user1Id, $user2Id) {
    $sql = "SELECT r.*, u.name as executor_name 
            FROM responses r 
            LEFT JOIN users u ON r.executor_id = u.id 
            WHERE r.job_id = :job_id 
            ORDER BY r.created_at ASC";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getConversationById($conversationId) {
    $sql = "SELECT * FROM messages WHERE conversation_id = :conversation_id 
            ORDER BY created_at DESC";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':conversation_id', $conversationId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function countConversations($search = '') {
    $sql = "SELECT COUNT(DISTINCT conversation_id) as count FROM messages";
    if ($search) {
        $sql .= " WHERE content LIKE :search OR message LIKE :search";
    }
    
    $stmt = $this->pdo->prepare($sql);
    if ($search) {
        $searchTerm = "%$search%";
        $stmt->bindParam(':search', $searchTerm);
    }
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}
public function getAllConversations($limit, $offset, $sortColumn, $sortOrder, $search) {
    $searchQuery = '';
    $params = [];
    
    if ($search) {
        $searchQuery = "WHERE (m.content LIKE :search OR m.message LIKE :search OR u1.name LIKE :search OR u2.name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    $query = "SELECT 
                m.id as message_id,
                m.job_id,
                m.conversation_id,
                m.content,
                m.message,
                m.created_at,
                u1.name as sender_name,
                u2.name as receiver_name,
                j.title as job_title
              FROM messages m
              LEFT JOIN users u1 ON m.sender_id = u1.id
              LEFT JOIN users u2 ON m.receiver_id = u2.id
              LEFT JOIN jobs j ON m.job_id = j.id
              $searchQuery
              ORDER BY $sortColumn $sortOrder
              LIMIT :limit OFFSET :offset";
    
    $stmt = $this->pdo->prepare($query);
    
    if ($search) {
        $stmt->bindValue(':search', $params[':search']);
    }
    
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getConversationMessages($conversationId) {
    $sql = "SELECT m.*, 
                   u1.name as sender_name, 
                   u2.name as receiver_name
            FROM messages m
            LEFT JOIN users u1 ON m.sender_id = u1.id
            LEFT JOIN users u2 ON m.receiver_id = u2.id
            WHERE m.conversation_id = :conversation_id
            ORDER BY m.created_at ASC";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':conversation_id', $conversationId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function deleteConversations($conversationIds) {
    if (empty($conversationIds)) return false;
    
    $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
    $query = "DELETE FROM messages WHERE conversation_id IN ($placeholders)";
    $stmt = $this->pdo->prepare($query);
    return $stmt->execute($conversationIds);
}
// Dodaj te metody do klasy Message w Message.php

/**
 * Pobiera zgrupowane konwersacje z filtrami
 */
public function getGroupedConversationsWithFilters($limit, $offset, $userId = '') {
    $sql = "SELECT 
        m.conversation_id,
        m.job_id,
        MAX(m.created_at) as last_activity_date,
        COUNT(DISTINCT m.id) as message_count,
        m.sender_id,
        m.receiver_id,
        u1.name as sender_name,
        u2.name as receiver_name,
        j.title as job_title,
        (SELECT content FROM messages WHERE conversation_id = m.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_content
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        LEFT JOIN jobs j ON m.job_id = j.id
        WHERE 1=1";
    
    if (!empty($userId)) {
        $sql .= " AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
    }
    
    $sql .= " GROUP BY m.conversation_id
              ORDER BY last_activity_date DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $this->pdo->prepare($sql);
    
    if (!empty($userId)) {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Zlicza zgrupowane konwersacje
 */
public function countGroupedConversationsWithFilters($userId = '') {
    $sql = "SELECT COUNT(DISTINCT conversation_id) as count
            FROM messages
            WHERE 1=1";
    
    if (!empty($userId)) {
        $sql .= " AND (sender_id = :user_id OR receiver_id = :user_id)";
    }
    
    $stmt = $this->pdo->prepare($sql);
    
    if (!empty($userId)) {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] ?? 0;
}
public function getFullConversation($conversationId) {
    $sql = "(
        SELECT m.*, u1.name as sender_name, u2.name as receiver_name, 'message' as type
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        WHERE m.conversation_id = :conversation_id
    )
    UNION ALL
    (
        SELECT r.id, r.job_id, r.executor_id as sender_id, 
               (SELECT receiver_id FROM messages WHERE conversation_id = :conversation_id LIMIT 1) as receiver_id,
               NULL as content, r.message, r.created_at, NULL as read_status, :conversation_id as conversation_id,
               u.name as sender_name, NULL as receiver_name, 'response' as type
        FROM responses r
        LEFT JOIN users u ON r.executor_id = u.id
        WHERE r.job_id = (SELECT job_id FROM messages WHERE conversation_id = :conversation_id LIMIT 1)
    )
    ORDER BY created_at ASC";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getGroupedConversationsWithAdvancedFilters($limit, $offset, $filters = []) {
    $sql = "SELECT 
        m.conversation_id,
        m.job_id,
        MAX(m.created_at) as last_activity_date,
        COUNT(DISTINCT m.id) as message_count,
        m.sender_id,
        m.receiver_id,
        u1.name as sender_name,
        u2.name as receiver_name,
        j.title as job_title,
        (SELECT content FROM messages WHERE conversation_id = m.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_content
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        LEFT JOIN jobs j ON m.job_id = j.id
        WHERE 1=1";
    
    $params = [];
    
    // Filtry
    if (!empty($filters['user_id'])) {
        $sql .= " AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
        $params[':user_id'] = $filters['user_id'];
    }
    
    if (!empty($filters['job_id'])) {
        $sql .= " AND m.job_id = :job_id";
        $params[':job_id'] = $filters['job_id'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (m.content LIKE :search OR u1.name LIKE :search OR u2.name LIKE :search OR j.title LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(m.created_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(m.created_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    if (!empty($filters['min_messages'])) {
        $sql .= " AND (SELECT COUNT(*) FROM messages WHERE conversation_id = m.conversation_id) >= :min_messages";
        $params[':min_messages'] = $filters['min_messages'];
    }
    
    if (!empty($filters['max_messages'])) {
        $sql .= " AND (SELECT COUNT(*) FROM messages WHERE conversation_id = m.conversation_id) <= :max_messages";
        $params[':max_messages'] = $filters['max_messages'];
    }
    
    $sql .= " GROUP BY m.conversation_id
              ORDER BY last_activity_date DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $this->pdo->prepare($sql);
    
    // Bindowanie parametrów
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function countGroupedConversationsWithAdvancedFilters($filters = []) {
    $sql = "SELECT COUNT(DISTINCT conversation_id) as count
            FROM messages m
            LEFT JOIN users u1 ON m.sender_id = u1.id
            LEFT JOIN users u2 ON m.receiver_id = u2.id
            LEFT JOIN jobs j ON m.job_id = j.id
            WHERE 1=1";
    
    $params = [];
    
    // Filtry
    if (!empty($filters['user_id'])) {
        $sql .= " AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
        $params[':user_id'] = $filters['user_id'];
    }
    
    if (!empty($filters['job_id'])) {
        $sql .= " AND m.job_id = :job_id";
        $params[':job_id'] = $filters['job_id'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (m.content LIKE :search OR u1.name LIKE :search OR u2.name LIKE :search OR j.title LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(m.created_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(m.created_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    if (!empty($filters['min_messages'])) {
        $sql .= " AND (SELECT COUNT(*) FROM messages WHERE conversation_id = m.conversation_id) >= :min_messages";
        $params[':min_messages'] = $filters['min_messages'];
    }
    
    if (!empty($filters['max_messages'])) {
        $sql .= " AND (SELECT COUNT(*) FROM messages WHERE conversation_id = m.conversation_id) <= :max_messages";
        $params[':max_messages'] = $filters['max_messages'];
    }
    
    $stmt = $this->pdo->prepare($sql);
    
    // Bindowanie parametrów
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] ?? 0;
}
}
?>