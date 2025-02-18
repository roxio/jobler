<?php
include_once('Database.php');

class Report {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo; // Używamy PDO do połączenia z bazą danych
    }

    // Pobranie raportów aktywności użytkowników
    public function getUserActivityReports($userId) {
        $query = "SELECT * FROM user_activity_reports WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Pobieramy wszystkie raporty w formie tablicy
    }

    // Pobranie raportów ogłoszeń
    public function getJobReports($jobId) {
        $query = "SELECT * FROM job_reports WHERE job_id = :job_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Pobieramy wszystkie raporty w formie tablicy
    }

    // Dodanie raportu aktywności użytkownika
public function addUserActivityReport($userId, $activityType, $details) {
    // Sprawdzamy, czy użytkownik istnieje
    $checkQuery = "SELECT COUNT(*) FROM users WHERE id = :user_id";
    $stmt = $this->pdo->prepare($checkQuery);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userExists = $stmt->fetchColumn() > 0;

    // Jeśli użytkownik nie istnieje, nie dodawaj raportu
    if (!$userExists) {
        throw new Exception("Użytkownik o ID $userId nie istnieje.");
    }

    // Jeśli użytkownik istnieje, dodaj raport
    $query = "INSERT INTO user_activity_reports (user_id, activity_type, timestamp, details) 
              VALUES (:user_id, :activity_type, NOW(), :details)";
    $stmt = $this->pdo->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':activity_type', $activityType, PDO::PARAM_STR);
    $stmt->bindParam(':details', $details, PDO::PARAM_STR);
    return $stmt->execute();
}

    // Dodanie raportu aktywności ogłoszenia
    public function addJobReport($jobId, $activityType, $details) {
        $query = "INSERT INTO job_reports (job_id, activity_type, timestamp, details) 
                  VALUES (:job_id, :activity_type, NOW(), :details)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->bindParam(':activity_type', $activityType, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);
        return $stmt->execute();
    }
}
?>
