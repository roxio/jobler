<?php
include_once(__DIR__ . '/Language.php');

class Message {
    private $pdo;
    private $moderationColumnsEnsured = false;

    public function __construct($pdo = null) {
    if ($pdo) {
        $this->pdo = $pdo;
    } else {
        include_once(dirname(__DIR__) . '/models/Database.php');
        $this->pdo = Database::getConnection();
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


    public function getMessageById($id) {
        $this->ensureModerationColumns();

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

        $originalMessage = $this->getMessageById($messageId);

        if (!$originalMessage) {
            return false;
        }


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
public function getConversationById($conversationId, $requestingUserId = null) {
    $this->ensureModerationColumns();

    $parts = explode('_', $conversationId);

    if (count($parts) === 3) {

        $jobId = (int)$parts[0];
        $id1   = (int)$parts[1];
        $id2   = (int)$parts[2];


        if ($requestingUserId !== null) {
            $isParticipant = ($requestingUserId === $id1 || $requestingUserId === $id2);
            $isAdmin       = $this->isAdmin($requestingUserId);
            if (!$isParticipant && !$isAdmin) {
                return false;
            }
        }

        $sql = "SELECT m.*,
                       u1.name AS sender_name,
                       u2.name AS receiver_name
                FROM messages m
                LEFT JOIN users u1 ON m.sender_id = u1.id
                LEFT JOIN users u2 ON m.receiver_id = u2.id
                WHERE m.job_id = :job_id
                  AND (
                      (m.sender_id = :id1 AND m.receiver_id = :id2)
                   OR (m.sender_id = :id2b AND m.receiver_id = :id1b)
                  )
                ORDER BY m.created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':job_id' => $jobId,
            ':id1'    => $id1,
            ':id2'    => $id2,
            ':id2b'   => $id2,
            ':id1b'   => $id1,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif (count($parts) === 2) {
        $id1 = (int)$parts[0];
        $id2 = (int)$parts[1];

        if ($requestingUserId !== null) {
            $isParticipant = ($requestingUserId === $id1 || $requestingUserId === $id2);
            $isAdmin       = $this->isAdmin($requestingUserId);
            if (!$isParticipant && !$isAdmin) {
                return false;
            }
        }

        $sql = "SELECT m.*,
                       u1.name AS sender_name,
                       u2.name AS receiver_name
                FROM messages m
                LEFT JOIN users u1 ON m.sender_id = u1.id
                LEFT JOIN users u2 ON m.receiver_id = u2.id
                WHERE (m.sender_id = :id1  AND m.receiver_id = :id2)
                   OR (m.sender_id = :id2b AND m.receiver_id = :id1b)
                ORDER BY m.created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id1'  => $id1, ':id2'  => $id2,
            ':id2b' => $id2, ':id1b' => $id1,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [];
}


private function isAdmin($userId) {
    $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && in_array($row['role'], ['super_admin', 'admin', 'moderator', 'opiekun', 'reklamodawca'], true);
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
    $this->ensureModerationColumns();

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

public function updateMessageContent($messageId, $content) {
    $this->ensureModerationColumns();

    $sql = "UPDATE messages
            SET content = :content, message = :message
            WHERE id = :id";

    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
        ':content' => $content,
        ':message' => $content,
        ':id' => $messageId,
    ]);
}

public function deleteMessage($messageId) {
    $this->ensureModerationColumns();

    $stmt = $this->pdo->prepare("DELETE FROM messages WHERE id = :id");
    $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
    return $stmt->execute();
}

public function setConversationReadStatus($conversationIds, $readStatus) {
    $this->ensureModerationColumns();

    if (empty($conversationIds)) return false;

    $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
    $query = "UPDATE messages SET read_status = ? WHERE conversation_id IN ($placeholders)";
    $stmt = $this->pdo->prepare($query);

    return $stmt->execute(array_merge([(int)$readStatus], $conversationIds));
}

public function moderateMessage($messageId, $data) {
    $this->ensureModerationColumns();

    $sql = "UPDATE messages
            SET is_hidden = :is_hidden,
                admin_note = :admin_note,
                participant_note = :participant_note,
                moderated_by = :moderated_by,
                moderated_at = NOW()";

    $params = [
        ':is_hidden' => !empty($data['is_hidden']) ? 1 : 0,
        ':admin_note' => $data['admin_note'] ?? null,
        ':participant_note' => $data['participant_note'] ?? null,
        ':moderated_by' => $data['moderated_by'] ?? null,
        ':id' => $messageId,
    ];

    if (array_key_exists('image_path', $data)) {
        $sql .= ", image_path = :image_path";
        $params[':image_path'] = $data['image_path'];
    }

    $sql .= " WHERE id = :id";

    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute($params);
}

public function removeMessageImage($messageId) {
    $this->ensureModerationColumns();

    $stmt = $this->pdo->prepare("UPDATE messages SET image_path = NULL, moderated_at = NOW() WHERE id = :id");
    $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
    return $stmt->execute();
}

public function createConversationReport($reporterId, $conversationId, $jobId, $reportType, $reason, $messageId = null, $reportedUserId = null) {
    $this->ensureModerationColumns();

    $allowedTypes = ['conversation', 'message', 'user'];
    if (!in_array($reportType, $allowedTypes, true)) {
        return false;
    }

    $snapshot = $this->buildConversationSnapshot($conversationId);

    $sql = "INSERT INTO conversation_reports
                (conversation_id, job_id, reporter_id, reported_user_id, message_id, report_type, reason, conversation_snapshot, status, created_at)
            VALUES
                (:conversation_id, :job_id, :reporter_id, :reported_user_id, :message_id, :report_type, :reason, :conversation_snapshot, 'open', NOW())";

    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
        ':conversation_id' => $conversationId,
        ':job_id' => $jobId,
        ':reporter_id' => $reporterId,
        ':reported_user_id' => $reportedUserId ?: null,
        ':message_id' => $messageId ?: null,
        ':report_type' => $reportType,
        ':reason' => $reason,
        ':conversation_snapshot' => $snapshot,
    ]);
}

public function getOpenReportsForConversation($conversationId) {
    $this->ensureModerationColumns();

    $sql = "SELECT cr.*, reporter.name AS reporter_name, reported.name AS reported_user_name
            FROM conversation_reports cr
            LEFT JOIN users reporter ON cr.reporter_id = reporter.id
            LEFT JOIN users reported ON cr.reported_user_id = reported.id
            WHERE cr.conversation_id = :conversation_id AND cr.status = 'open'
            ORDER BY cr.created_at DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

private function buildConversationSnapshot($conversationId) {
    $messages = $this->getConversationMessages($conversationId);
    $lines = [];

    foreach ($messages as $message) {
        $content = $message['content'] ?: $message['message'] ?: __t('messages.no_content');
        $lines[] = '[' . $message['created_at'] . '] ' . ($message['sender_name'] ?? ('ID ' . $message['sender_id'])) . ': ' . $content;
    }

    return implode("\n\n", $lines);
}

private function ensureModerationColumns() {
    if ($this->moderationColumnsEnsured) {
        return;
    }

    $columns = [
        'is_hidden' => "ALTER TABLE messages ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0",
        'image_path' => "ALTER TABLE messages ADD COLUMN image_path VARCHAR(255) DEFAULT NULL",
        'admin_note' => "ALTER TABLE messages ADD COLUMN admin_note TEXT DEFAULT NULL",
        'participant_note' => "ALTER TABLE messages ADD COLUMN participant_note TEXT DEFAULT NULL",
        'moderated_at' => "ALTER TABLE messages ADD COLUMN moderated_at TIMESTAMP NULL DEFAULT NULL",
        'moderated_by' => "ALTER TABLE messages ADD COLUMN moderated_by INT(11) DEFAULT NULL",
    ];

    $stmt = $this->pdo->query("SHOW COLUMNS FROM messages");
    $existingColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

    foreach ($columns as $column => $alterSql) {
        if (!in_array($column, $existingColumns, true)) {
            $this->pdo->exec($alterSql);
        }
    }

    $stmt = $this->pdo->query("SHOW COLUMNS FROM messages");
    $messageColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($messageColumns as $column) {
        if ($column['Field'] === 'conversation_id' && stripos($column['Type'], 'varchar') === false) {
            $this->pdo->exec("ALTER TABLE messages MODIFY conversation_id VARCHAR(100) NOT NULL");
            break;
        }
    }

    $this->pdo->exec("
        CREATE TABLE IF NOT EXISTS conversation_reports (
            id INT(11) NOT NULL AUTO_INCREMENT,
            conversation_id VARCHAR(100) NOT NULL,
            job_id INT(11) DEFAULT NULL,
            reporter_id INT(11) NOT NULL,
            reported_user_id INT(11) DEFAULT NULL,
            message_id INT(11) DEFAULT NULL,
            report_type VARCHAR(30) NOT NULL DEFAULT 'conversation',
            reason TEXT DEFAULT NULL,
            conversation_snapshot MEDIUMTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            resolved_by INT(11) DEFAULT NULL,
            admin_note TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_conversation_reports_conversation (conversation_id),
            KEY idx_conversation_reports_status (status),
            KEY idx_conversation_reports_message (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $this->moderationColumnsEnsured = true;
}





public function getGroupedConversationsWithFilters($limit, $offset, $userId = '') {
    $this->ensureModerationColumns();

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
        (SELECT COUNT(*) FROM conversation_reports cr WHERE cr.conversation_id = m.conversation_id AND cr.status = 'open') as open_report_count,
        (SELECT COALESCE(NULLIF(content, ''), message) FROM messages WHERE conversation_id = m.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_content
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




public function countGroupedConversationsWithFilters($userId = '') {
    $this->ensureModerationColumns();

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
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getGroupedConversationsWithAdvancedFilters($limit, $offset, $filters = []) {
    $this->ensureModerationColumns();

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
        (SELECT COUNT(*) FROM conversation_reports cr WHERE cr.conversation_id = m.conversation_id AND cr.status = 'open') as open_report_count,
        (SELECT COALESCE(NULLIF(content, ''), message) FROM messages WHERE conversation_id = m.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_content
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.receiver_id = u2.id
        LEFT JOIN jobs j ON m.job_id = j.id
        WHERE 1=1";

    $params = [];


    if (!empty($filters['user_id'])) {
        $sql .= " AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
        $params[':user_id'] = $filters['user_id'];
    }

    if (!empty($filters['job_id'])) {
        $sql .= " AND m.job_id = :job_id";
        $params[':job_id'] = $filters['job_id'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (m.content LIKE :search OR m.message LIKE :search OR u1.name LIKE :search OR u2.name LIKE :search OR j.title LIKE :search)";
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

    $allowedSorts = [
        'last_activity_date' => 'last_activity_date',
        'message_count' => 'message_count',
        'sender_name' => 'sender_name',
        'receiver_name' => 'receiver_name',
    ];
    $sortColumn = $allowedSorts[$filters['sort'] ?? 'last_activity_date'] ?? 'last_activity_date';
    $sortOrder = strtoupper($filters['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $sql .= " GROUP BY m.conversation_id
              ORDER BY $sortColumn $sortOrder
              LIMIT :limit OFFSET :offset";

    $stmt = $this->pdo->prepare($sql);


    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function countGroupedConversationsWithAdvancedFilters($filters = []) {
    $this->ensureModerationColumns();

    $sql = "SELECT COUNT(DISTINCT conversation_id) as count
            FROM messages m
            LEFT JOIN users u1 ON m.sender_id = u1.id
            LEFT JOIN users u2 ON m.receiver_id = u2.id
            LEFT JOIN jobs j ON m.job_id = j.id
            WHERE 1=1";

    $params = [];


    if (!empty($filters['user_id'])) {
        $sql .= " AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
        $params[':user_id'] = $filters['user_id'];
    }

    if (!empty($filters['job_id'])) {
        $sql .= " AND m.job_id = :job_id";
        $params[':job_id'] = $filters['job_id'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (m.content LIKE :search OR m.message LIKE :search OR u1.name LIKE :search OR u2.name LIKE :search OR j.title LIKE :search)";
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


    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] ?? 0;
}
public function sendMessage($senderId, $receiverId, $content, $jobId = null) {
    $this->ensureModerationColumns();


    $convId = $jobId . '_' . min($senderId, $receiverId) . '_' . max($senderId, $receiverId);

    $sql = "INSERT INTO messages
                (job_id, sender_id, receiver_id, content, message, conversation_id, created_at, read_status)
            VALUES
                (:job_id, :sender_id, :receiver_id, :content, :message, :conversation_id, NOW(), 0)";

    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
        ':job_id'          => $jobId,
        ':sender_id'       => $senderId,
        ':receiver_id'     => $receiverId,
        ':content'         => $content,
        ':message'         => $content,
        ':conversation_id' => $convId,
    ]);
}
}
?>
