<?php
class LoginHistory {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Funkcja logująca logowanie administratora
    public function logLogin($adminId, $ipAddress, $loginTime) {
        $stmt = $this->pdo->prepare("INSERT INTO admin_login_history (admin_id, ip_address, login_time) VALUES (:admin_id, :ip_address, :login_time)");
        $stmt->execute([
            'admin_id' => $adminId,
            'ip_address' => $ipAddress,
            'login_time' => $loginTime
        ]);
    }

    // Pobieranie historii logowań
    public function getLoginHistory() {
        $stmt = $this->pdo->query("SELECT * FROM admin_login_history ORDER BY login_time DESC LIMIT 10");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
