<?php
include_once('Database.php');

class Report {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Pobranie raportów aktywności użytkowników
    public function getUserActivityReports($userId, $activityType = '', $searchTerm = '', $startDate = '', $endDate = '', $sortBy = 'timestamp', $limit = 10, $offset = 0) {
        // Walidacja sortowania
        $allowedSortColumns = ['timestamp', 'activity_type', 'user_id'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'timestamp';
        }

        $query = "SELECT * FROM user_activity_reports WHERE user_id = :user_id";

        if ($activityType) {
            $query .= " AND activity_type LIKE :activityType";
        }

        if ($searchTerm) {
            $query .= " AND details LIKE :searchTerm";
        }

        if ($startDate && $endDate) {
            $query .= " AND timestamp BETWEEN :startDate AND :endDate";
        }

        $query .= " ORDER BY $sortBy DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($activityType) {
            $stmt->bindValue(':activityType', "%$activityType%", PDO::PARAM_STR);
        }

        if ($searchTerm) {
            $stmt->bindValue(':searchTerm', "%$searchTerm%", PDO::PARAM_STR);
        }

        if ($startDate && $endDate) {
            $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Zliczanie raportów aktywności użytkowników
    public function countUserReports($userId, $searchTerm = '', $startDate = '', $endDate = '') {
        $query = "SELECT COUNT(*) FROM user_activity_reports WHERE user_id = :user_id";

        if ($searchTerm) {
            $query .= " AND details LIKE :searchTerm";
        }

        if ($startDate && $endDate) {
            $query .= " AND timestamp BETWEEN :startDate AND :endDate";
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($searchTerm) {
            $stmt->bindValue(':searchTerm', "%$searchTerm%", PDO::PARAM_STR);
        }

        if ($startDate && $endDate) {
            $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Dodanie raportu aktywności użytkownika
    public function addUserActivityReport($userId, $activityType, $details) {
        // Sprawdzenie, czy użytkownik istnieje
        $checkQuery = "SELECT COUNT(*) FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($checkQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Użytkownik o ID $userId nie istnieje.");
        }

        $query = "INSERT INTO user_activity_reports (user_id, activity_type, timestamp, details) VALUES (:user_id, :activity_type, NOW(), :details)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':activity_type', $activityType, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);

        return $stmt->execute();
    }

    // Dodanie raportu ogłoszenia
    public function addJobReport($jobId, $activityType, $details) {
        // Sprawdzenie, czy ogłoszenie istnieje
        $checkQuery = "SELECT COUNT(*) FROM jobs WHERE id = :job_id";
        $stmt = $this->pdo->prepare($checkQuery);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Ogłoszenie o ID $jobId nie istnieje.");
        }

        $query = "INSERT INTO job_reports (job_id, activity_type, details, timestamp) VALUES (:job_id, :activity_type, :details, NOW())";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->bindParam(':activity_type', $activityType, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);

        return $stmt->execute();
    }

    // Dodanie raportu płatności
    public function addPaymentReport($userId, $details) {
        // Sprawdzenie, czy użytkownik istnieje
        $checkQuery = "SELECT COUNT(*) FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($checkQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Użytkownik o ID $userId nie istnieje.");
        }

        $query = "INSERT INTO payment_reports (user_id, details, timestamp) VALUES (:user_id, :details, NOW())";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);

        return $stmt->execute();
    }

    // Pobranie raportów płatności użytkowników
    public function getPaymentReports($userId, $searchTerm = '', $startDate = '', $endDate = '', $limit = 10, $offset = 0) {
        $query = "SELECT * FROM payment_reports WHERE user_id = :user_id";

        if ($searchTerm) {
            $query .= " AND details LIKE :searchTerm";
        }

        if ($startDate && $endDate) {
            $query .= " AND timestamp BETWEEN :startDate AND :endDate";
        }

        $query .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        if ($searchTerm) {
            $stmt->bindValue(':searchTerm', "%$searchTerm%", PDO::PARAM_STR);
        }
        if ($startDate && $endDate) {
            $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
