<?php
include_once('Database.php');

class SiteSettings {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();  // Uzyskanie połączenia z bazą danych
    }

    // Pobieranie ustawień strony
    public function getSettings() {
        $query = $this->pdo->query("SELECT * FROM site_settings LIMIT 1");
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;  
        }

        if (isset($result['categories'])) {
            $result['categories'] = explode(',', $result['categories']); // Konwersja na tablicę
        }
        return $result;
    }

    // Funkcja do monitorowania błędów
    public function getSiteErrors() {
        $query = "SELECT COUNT(*) AS error_count FROM system_logs WHERE log_level = 'ERROR'";
        $stmt = $this->pdo->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['error_count'];
    }

    // Funkcja do logowania transakcji płatniczych
    public function logTransaction($userId, $amount, $description) {
        $stmt = $this->pdo->prepare("INSERT INTO transaction_history (user_id, amount, description, created_at) VALUES (:user_id, :amount, :description, NOW())");
        $stmt->execute([
            'user_id' => $userId,
            'amount' => $amount,
            'description' => $description
        ]);
    }

    // Funkcja do logowania logowań administratorów
    public function logAdminLogin($adminId, $ipAddress) {
        $stmt = $this->pdo->prepare("INSERT INTO admin_login_history (admin_id, ip_address, login_time) VALUES (:admin_id, :ip_address, NOW())");
        $stmt->execute([
            'admin_id' => $adminId,
            'ip_address' => $ipAddress
        ]);
    }

    // Pobieranie liczby odwiedzin strony
    public function getSiteViews() {
        $sql = "SELECT views FROM site_stats LIMIT 1"; 
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['views'] : 0;
    }

    // Aktualizacja liczby odwiedzin strony
    public function updateSiteViews($views) {
        $sql = "UPDATE site_stats SET views = :views WHERE id = 1";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['views' => $views]);
    }

    // Funkcja do dodania kategorii
    public function addCategory($name, $parent_id = null) {
        $stmt = $this->pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (:name, :parent_id)");
        $stmt->execute([
            'name' => $name,
            'parent_id' => $parent_id
        ]);
        return $this->pdo->lastInsertId();
    }

    // Funkcja do pobierania kategorii
    public function getCategories() {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY parent_id IS NULL DESC, parent_id, id");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $structuredCategories = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] === null) {
                $structuredCategories[$category['id']] = [
                    'name' => $category['name'],
                    'subcategories' => []
                ];
            } else {
                $structuredCategories[$category['parent_id']]['subcategories'][] = [
                    'id' => $category['id'],
                    'name' => $category['name']
                ];
            }
        }
        return $structuredCategories;
    }
	// Logi settings
	   public function logSettingsChange($userId, $changeDescription, $timestamp) {
        $query = "INSERT INTO settings_log (user_id, change_description, timestamp) VALUES (:user_id, :change_description, :timestamp)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':change_description', $changeDescription);
        $stmt->bindParam(':timestamp', $timestamp);
        $stmt->execute();
    }
	// SMTP update setting
public function updateSMTPSettings($smtpServer, $smtpPort, $smtpUsername, $smtpPassword) {
    $sql = "UPDATE site_settings 
            SET smtp_server = :smtp_server, 
                smtp_port = :smtp_port, 
                smtp_username = :smtp_username, 
                smtp_password = :smtp_password 
            WHERE id = 1";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':smtp_server', $smtpServer);
    $stmt->bindParam(':smtp_port', $smtpPort);
    $stmt->bindParam(':smtp_username', $smtpUsername);
    $stmt->bindParam(':smtp_password', $smtpPassword);
    
    return $stmt->execute();
}

}
?>
