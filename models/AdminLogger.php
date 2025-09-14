<?php
include_once('Database.php');

class AdminLogger {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    /**
     * Zapis loga administracyjnego do bazy danych
     */
    public function logAction($adminId, $actionType, $description, $userId = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $query = "INSERT INTO admin_log (admin_id, user_id, action_type, description, ip_address, user_agent) 
                  VALUES (:admin_id, :user_id, :action_type, :description, :ip_address, :user_agent)";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':action_type', $actionType, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Pobierz logi z paginacją
     */
    public function getLogs($page = 1, $perPage = 50, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['admin_id'])) {
            $whereConditions[] = "al.admin_id = :admin_id";
            $params[':admin_id'] = $filters['admin_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $whereConditions[] = "al.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['action_type'])) {
            $whereConditions[] = "al.action_type = :action_type";
            $params[':action_type'] = $filters['action_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "al.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "al.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        
        $whereSql = "";
        if (!empty($whereConditions)) {
            $whereSql = "WHERE " . implode(" AND ", $whereConditions);
        }
        
        $query = "SELECT al.*, a.username as admin_username, a.name as admin_name,
                         u.username as user_username, u.name as user_name
                  FROM admin_log al
                  LEFT JOIN users a ON al.admin_id = a.id
                  LEFT JOIN users u ON al.user_id = u.id
                  $whereSql
                  ORDER BY al.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liczba logów z filtrami
     */
    public function countLogs($filters = []) {
        $whereConditions = [];
        $params = [];
        
        // Takie same filtry jak w getLogs...
        if (!empty($filters['admin_id'])) {
            $whereConditions[] = "admin_id = :admin_id";
            $params[':admin_id'] = $filters['admin_id'];
        }
        
        // ... pozostałe filtry
        
        $whereSql = "";
        if (!empty($whereConditions)) {
            $whereSql = "WHERE " . implode(" AND ", $whereConditions);
        }
        
        $query = "SELECT COUNT(*) as total FROM admin_log $whereSql";
        $stmt = $this->pdo->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ?? 0;
    }

    /**
     * Usuń stare logi (automatyczne czyszczenie)
     */
    public function cleanupOldLogs($days = 365) {
        $query = "DELETE FROM admin_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
}
?>