<?php
class ErrorLogs {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Funkcja pobierająca ostatnie błędy
    public function getRecentErrors() {
        $stmt = $this->pdo->query("SELECT * FROM system_logs WHERE log_level = 'ERROR' ORDER BY error_time DESC LIMIT 10");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Funkcja logująca błąd
    public function logError($message, $errorTime) {
        $stmt = $this->pdo->prepare("INSERT INTO system_logs (log_level, error_message, error_time) VALUES ('ERROR', :message, :error_time)");
        $stmt->execute([
            'message' => $message,
            'error_time' => $errorTime
        ]);
    }
}
?>
