<?php
class TransactionHistory {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Funkcja do logowania transakcji
    public function logTransaction($userId, $amount, $description, $transactionTime) {
        $stmt = $this->pdo->prepare("INSERT INTO transaction_history (user_id, amount, description, created_at) VALUES (:user_id, :amount, :description, :created_at)");
        $stmt->execute([
            'user_id' => $userId,
            'amount' => $amount,
            'description' => $description,
            'created_at' => $transactionTime
        ]);
    }

    // Pobieranie ostatnich transakcji
    public function getRecentTransactions() {
        $stmt = $this->pdo->query("SELECT * FROM transaction_history ORDER BY created_at DESC LIMIT 10");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>