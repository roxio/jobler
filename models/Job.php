<?php

include_once('Database.php');

class Job {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Dodawanie nowego ogłoszenia
    public function createJob($userId, $title, $description) {
        $sql = "INSERT INTO jobs (user_id, title, description, status, created_at, updated_at) 
                VALUES (:user_id, :title, :description, 'open', NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        return $stmt->execute();
    }

    // Pobieranie wszystkich ogłoszeń użytkownika
    public function getUserJobs($userId) {
        $sql = "SELECT * FROM jobs WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pobieranie szczegółów ogłoszenia na podstawie jego ID
    public function getJobDetails($jobId) {
        $sql = "SELECT jobs.*, users.name as user_name 
                FROM jobs 
                LEFT JOIN users ON jobs.user_id = users.id 
                WHERE jobs.id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Edytowanie ogłoszenia
    public function updateJob($jobId, $title, $description, $status) {
        $sql = "UPDATE jobs SET title = :title, description = :description, status = :status, updated_at = NOW() WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':job_id', $jobId);
        return $stmt->execute();
    }

    // Zmiana statusu ogłoszenia (np. zamknięcie oferty)
    public function updateJobStatus($jobId, $status) {
        $sql = "UPDATE jobs SET status = :status, updated_at = NOW() WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':job_id', $jobId);
        return $stmt->execute();
    }

    // Usuwanie ogłoszenia
    public function deleteJob($jobId) {
        $sql = "DELETE FROM jobs WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_id', $jobId);
        return $stmt->execute();
    }

    // Pobieranie dostępnych ogłoszeń (np. ogłoszenia bez przypisanego wykonawcy)
    public function getAvailableJobs() {
        $sql = "SELECT * FROM jobs WHERE status = 'open' AND executor_id IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pobieranie ogłoszeń z przypisanym wykonawcą
    public function getJobsWithExecutor() {
        $sql = "SELECT * FROM jobs WHERE executor_id IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Dodanie metody do pobierania wszystkich ogłoszeń
    public static function getAllJobs() {
        $pdo = Database::getConnection();  // Uzyskanie połączenia z bazą danych
        $sql = "SELECT * FROM jobs";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJobCount() {
        $sql = "SELECT COUNT(*) FROM jobs";  // Zmienna zależna od struktury Twojej tabeli
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();  // Zwraca liczbę ogłoszeń
    }

    public static function getJobsWithPagination($limit, $offset) {
        $pdo = Database::getConnection();  // Uzyskanie połączenia z bazą danych
        $stmt = $pdo->prepare("SELECT * FROM jobs ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTotalJobs() {
        $pdo = Database::getConnection();  // Uzyskanie połączenia z bazą danych
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM jobs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    // Nowe metody do paginacji z wyszukiwaniem
    public static function getJobsWithPaginationAndSearch($limit, $offset, $search) {
        $pdo = Database::getConnection();
        $sql = "SELECT * FROM jobs WHERE title LIKE :search ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTotalJobsWithSearch($search) {
        $pdo = Database::getConnection();
        $sql = "SELECT COUNT(*) as total FROM jobs WHERE title LIKE :search";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    // Nowa metoda: Pobieranie liczby nowych ogłoszeń w ciągu ostatnich 30 dni
    public function getNewJobsCount() {
        $sql = "SELECT COUNT(*) FROM jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
?>
