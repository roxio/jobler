<?php

include_once('Database.php');
include_once('Job.php');
include_once('Message.php');

class Executor {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Pobieranie dostępnych ogłoszeń dla wykonawcy (ogłoszenia, które jeszcze nie mają przypisanego wykonawcy)
    public function getAvailableJobs() {
        $sql = "SELECT * FROM jobs WHERE status = 'open'"; // Można zmienić status na inne zależnie od aplikacji
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Odpowiadanie na ofertę (wysyłanie wiadomości do użytkownika)
	public function respondToJob($executorId, $jobId, $message) {
    $query = "INSERT INTO responses (job_id, executor_id, message) VALUES (:job_id, :executor_id, :message)";
    $stmt = $this->pdo->prepare($query);
    $stmt->bindParam(':job_id', $jobId);
    $stmt->bindParam(':executor_id', $executorId);
    $stmt->bindParam(':message', $message);

    return $stmt->execute();
}

public function getResponsesForUserJobs($userId) {
    $query = "
        SELECT r.message, r.created_at, j.title, e.name AS executor_name
        FROM responses r
        JOIN jobs j ON r.job_id = j.id
        JOIN executors e ON r.executor_id = e.id
        WHERE j.user_id = :user_id
        ORDER BY r.created_at DESC
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    // Pobieranie odpowiedzi na ofertę (wszystkich wiadomości przypisanych do ogłoszenia)
    public function getJobResponses($jobId) {
        $sql = "SELECT * FROM messages WHERE job_id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_id', $jobId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pobranie szczegółów ogłoszenia
    public function getJobDetails($jobId) {
        $sql = "SELECT * FROM jobs WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_id', $jobId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Pobranie wykonawcy na podstawie jego ID
    public function getExecutorById($executorId) {
        $sql = "SELECT * FROM users WHERE id = :executor_id AND role = 'executor'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':executor_id', $executorId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Zaktualizowanie statusu ogłoszenia (np. zamknięcie ogłoszenia, przypisanie wykonawcy)
    public function updateJobStatus($jobId, $status, $executorId = null) {
        $sql = "UPDATE jobs SET status = :status, executor_id = :executor_id WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':job_id', $jobId);
        $stmt->bindParam(':executor_id', $executorId, PDO::PARAM_INT);
        return $stmt->execute();
    }
	// Responded offers obsługa
	public function getRespondedJobs($executorId) {
    $query = "SELECT jobs.id, jobs.title, jobs.description, responses.created_at AS response_date
              FROM jobs
              INNER JOIN responses ON jobs.id = responses.job_id
              WHERE responses.executor_id = :executor_id
              ORDER BY responses.created_at DESC";

    $stmt = $this->pdo->prepare($query);
    $stmt->bindParam(':executor_id', $executorId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function hasRespondedToJob($executorId, $jobId) {
    try {
        // Sprawdzenie, czy istnieje odpowiedź w tabeli responses
        $queryResponses = "
            SELECT r.id AS response_id, r.executor_id, r.job_id
            FROM responses r
            WHERE r.executor_id = :executorId AND r.job_id = :jobId
            LIMIT 1
        ";
        $stmtResponses = $this->pdo->prepare($queryResponses);
        $stmtResponses->bindParam(':executorId', $executorId, PDO::PARAM_INT);
        $stmtResponses->bindParam(':jobId', $jobId, PDO::PARAM_INT);
        $stmtResponses->execute();
        $response = $stmtResponses->fetch(PDO::FETCH_ASSOC);
        error_log("Response from responses table for executor_id {$executorId}, job_id {$jobId}: " . print_r($response, true));

        // Pobranie szczegółów ogłoszenia, aby uzyskać user_id właściciela ogłoszenia (odbiorcy)
        $jobDetails = $this->getJobDetails($jobId);
        if (!$jobDetails) {
            return false;
        }
        $receiverId = $jobDetails['user_id'];

        // Obliczenie conversation_id wg schematu: min(executorId, receiverId)_max(executorId, receiverId)
        $computedConversationId = min($executorId, $receiverId) . "_" . max($executorId, $receiverId);

        // Sprawdzenie, czy istnieje konwersacja w tabeli messages
        $queryMessages = "
            SELECT m.conversation_id 
            FROM messages m
            WHERE m.job_id = :jobId
              AND m.conversation_id = :conversationId
            LIMIT 1
        ";
        $stmtMessages = $this->pdo->prepare($queryMessages);
        $stmtMessages->bindParam(':jobId', $jobId, PDO::PARAM_INT);
        $stmtMessages->bindParam(':conversationId', $computedConversationId, PDO::PARAM_STR);
        $stmtMessages->execute();
        $message = $stmtMessages->fetch(PDO::FETCH_ASSOC);
        error_log("Message from messages table for job_id {$jobId}, computedConversationId {$computedConversationId}: " . print_r($message, true));

        if ($message) {
            // Konwersacja istnieje – zwracamy conversation_id
            return [
                'response_id' => $response ? $response['response_id'] : null,
                'conversation_id' => $message['conversation_id'],
            ];
        }

        if ($response) {
            // Oferta została złożona, ale konwersacja jeszcze nie rozpoczęta
            return [
                'response_id' => $response['response_id'],
                'conversation_id' => null,
            ];
        }

        // Ani odpowiedzi, ani konwersacji nie ma
        return false;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        throw $e;
    }
}
// Pobieranie salda konta wykonawcy
public function getExecutorBalance($executorId) {
    $sql = "SELECT account_balance FROM users WHERE id = :executor_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':executor_id', $executorId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['account_balance'] : 0;
}





}
?>