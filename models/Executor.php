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
    public function respondToJob($executorId, $jobId, $messageContent) {
        $sql = "INSERT INTO messages (executor_id, job_id, content, sent_at) VALUES (:executor_id, :job_id, :content, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':executor_id', $executorId);
        $stmt->bindParam(':job_id', $jobId);
        $stmt->bindParam(':content', $messageContent);
        return $stmt->execute();
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
}
?>