<?php
class TransactionHistory {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Sprawdzanie połączenia dla PDO jest inne niż dla mysqli
        // PDO rzuca wyjątki, więc nie ma connect_error
    }
    
    // Pobierz dzienne przychody
    public function getDailyRevenue($days = 7) {
        $query = "SELECT DATE(created_at) as date, SUM(amount) as amount 
                  FROM transactions 
                  WHERE status = 'completed' 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY DATE(created_at) 
                  ORDER BY date DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Pobierz całkowity przychód
    public function getTotalRevenue() {
        $query = "SELECT SUM(amount) as total 
                  FROM transactions 
                  WHERE status = 'completed'";
        
        $stmt = $this->pdo->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ?? 0;
    }
    
    // Pobierz liczbę ostatnich transakcji
    public function getRecentTransactionsCount($days = 7) {
        $query = "SELECT COUNT(*) as count 
                  FROM transactions 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    }
	
	 public function getRecentTransactions($limit = 10) {
        $query = "SELECT t.*, u.username 
                  FROM transactions t
                  LEFT JOIN users u ON t.user_id = u.id
                  ORDER BY t.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Pobierz historię transakcji z paginacją
    public function getTransactionHistory($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        $whereClauses = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $whereClauses[] = "t.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['status'])) {
            $whereClauses[] = "t.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $whereClauses[] = "t.type = :type";
            $params[':type'] = $filters['type'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereClauses[] = "t.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClauses[] = "t.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $whereSql = "";
        if (!empty($whereClauses)) {
            $whereSql = "WHERE " . implode(" AND ", $whereClauses);
        }
        
        $query = "SELECT t.*, u.username 
                  FROM transactions t 
                  LEFT JOIN users u ON t.user_id = u.id 
                  $whereSql 
                  ORDER BY t.created_at DESC 
                  LIMIT :offset, :perPage";
        
        $stmt = $this->pdo->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pobierz całkowitą liczbę dla paginacji
        $countQuery = "SELECT COUNT(*) as total 
                       FROM transactions t 
                       LEFT JOIN users u ON t.user_id = u.id 
                       $whereSql";
        
        $countStmt = $this->pdo->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'transactions' => $transactions,
            'total' => $total,
            'pages' => ceil($total / $perPage)
        ];
    }
    
    // Aktualizuj status transakcji
    public function updateTransactionStatus($transactionId, $status) {
        $query = "UPDATE transactions 
                  SET status = :status, updated_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':id', $transactionId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    // Dodaj nową transakcję
    public function addTransaction($userId, $amount, $type, $description, $status = 'pending') {
        $query = "INSERT INTO transactions (user_id, amount, type, description, status, created_at) 
                  VALUES (:user_id, :amount, :type, :description, :status, NOW())";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

public function getUserTransactions($userId, $limit = 10) {
    $query = "SELECT * FROM transactions 
              WHERE user_id = ? 
              ORDER BY created_at DESC 
              LIMIT ?";
    $stmt = $this->pdo->prepare($query);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}
?>