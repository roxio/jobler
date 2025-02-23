<?php

include_once('Database.php');

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Rejestracja użytkownika
    public function register($email, $password, $name, $username, $role = 'user', $phone = '') {
        // Pobranie adresu IP użytkownika
        $registrationIp = $_SERVER['REMOTE_ADDR'];

        // Sprawdzanie, czy użytkownik już istnieje
        $sql = "SELECT * FROM users WHERE email = :email OR username = :username";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['error' => 'Użytkownik o tym adresie email lub nazwie użytkownika już istnieje.'];
        }

        // Hashowanie hasła
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Wstawianie nowego użytkownika do bazy
        $sql = "INSERT INTO users (email, password, name, username, role, phone, registration_ip, created_at, updated_at) 
                VALUES (:email, :password, :name, :username, :role, :phone, :registration_ip, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':registration_ip', $registrationIp);

        if ($stmt->execute()) {
            // Pobierz ID nowo zarejestrowanego użytkownika
            $userId = $this->pdo->lastInsertId();
            return ['success' => 'Rejestracja zakończona pomyślnie.', 'id' => $userId, 'role' => $role];
        }

        return ['error' => 'Wystąpił błąd podczas rejestracji. Spróbuj ponownie.'];
    }

    // Logowanie użytkownika
    public function login($email, $password) {
        // Sprawdzanie, czy użytkownik istnieje
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);

        if (!$stmt->execute()) {
            // Jeśli wystąpił błąd w zapytaniu SQL
            return false;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sprawdzanie, czy konto jest aktywne
        if ($user && $user['status'] !== 'active') {
            return ['error' => 'Konto zostało zablokowane przez administratora.'];
        }

        if ($user && password_verify($password, $user['password'])) {
            // Zaktualizowanie IP przy logowaniu
            $lastLoginIp = $_SERVER['REMOTE_ADDR'];
            $this->updateLastLoginIp($user['id'], $lastLoginIp);

            // Zwrócenie danych użytkownika, jeśli logowanie się powiodło
            return $user;
        }

        return false; // Zwrócenie false, jeśli logowanie nie powiodło się
    }

    // Sprawdzanie, czy użytkownik jest zalogowany
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Pobranie danych użytkownika na podstawie ID
    public function getUserById($userId) {
        $sql = "SELECT id, email, role, created_at, updated_at, registration_ip, last_login_ip, status, account_balance FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Aktualizacja danych użytkownika
    public function updateUser($userId, $email) {
        $sql = "UPDATE users SET email = :email, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }

    // Zmiana hasła użytkownika
    public function changePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }

public function addPointsToUser($userId, $points) {
    // Dodanie punktów do konta użytkownika
    $sql = "UPDATE users SET account_balance = account_balance + :points WHERE id = :user_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':points', $points, PDO::PARAM_STR);  // Zmieniamy na PDO::PARAM_STR, aby obsługiwać float
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

    return $stmt->execute();  // Zwróci true, jeśli operacja zakończyła się sukcesem
}

    // Pobranie wszystkich użytkowników (np. do panelu administracyjnego)
public function getAllUsers() {
    $sql = "SELECT id, name, email, role, status, created_at, updated_at, registration_ip, last_login_ip, account_balance, need_change FROM users";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

 // Metoda do pobierania użytkowników z paginacją, sortowaniem i wyszukiwaniem
    public function getPaginatedUsers($limit, $offset, $sortColumn, $sortOrder, $search) {
        $db = Database::getConnection();

        $searchQuery = '';
        if ($search) {
            $searchQuery = "WHERE name LIKE :search OR email LIKE :search";
        }

        $query = "SELECT * FROM users $searchQuery ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        if ($search) {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metoda do pobierania całkowitej liczby użytkowników
    public function getTotalUsers($search) {
        $db = Database::getConnection();

        $searchQuery = '';
        if ($search) {
            $searchQuery = "WHERE name LIKE :search OR email LIKE :search";
        }

        $query = "SELECT COUNT(*) FROM users $searchQuery";
        $stmt = $db->prepare($query);
        if ($search) {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Sprawdzanie roli użytkownika
    public function getUserRole($userId) {
        $sql = "SELECT role FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['role'] : null;
    }

    // Pobranie liczby użytkowników
    public function getUserCount() {
        $sql = "SELECT COUNT(*) FROM users";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();
    }

    // Pobranie liczby nowych użytkowników w ostatnich 30 dniach
    public function getNewUsersCount() {
        $sql = "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Usuwanie użytkownika
    public function deleteUser($userId) {
        $sql = "DELETE FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }

    // Usuwanie wielu użytkowników
    public function deleteUsers($userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $query = "DELETE FROM users WHERE id IN ($placeholders)";
        $stmt = $this->db->prepare($query);
        $stmt->execute($userIds);
    }

    // Dezaktywuj użytkownika
    public function deactivateUser($userId) {
        $query = "UPDATE users SET status = 'inactive' WHERE id = :user_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Aktywuj użytkownika
    public function activateUser($userId) {
        $query = "UPDATE users SET status = 'active' WHERE id = :user_id AND status = 'inactive'";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Aktualizowanie adresu IP ostatniego logowania
    public function updateLastLoginIp($userId, $lastLoginIp) {
        $sql = "UPDATE users SET last_login_ip = :last_login_ip WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':last_login_ip', $lastLoginIp);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getResponsesForUserJobs($userId) {
        $query = "
            SELECT 
                j.title AS title,
                r.message AS message,
                r.created_at AS created_at,
                u.name AS executor_name,
                r.executor_id AS executor_id, -- Dodane pole executor_id
                j.id AS job_id -- Dla poprawnego przekierowania do konwersacji
            FROM 
                jobs j
            INNER JOIN 
                responses r ON j.id = r.job_id
            INNER JOIN 
                users u ON r.executor_id = u.id
            WHERE 
                j.user_id = :user_id
            ORDER BY 
                r.created_at DESC
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
	
	public function addBalanceToUser($userId, $balanceToAdd) {
    $sql = "UPDATE users SET account_balance = account_balance + :balance_to_add, updated_at = NOW() WHERE id = :user_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':balance_to_add', $balanceToAdd, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

    return $stmt->execute();
}
// Czy zmienićrodzaj konta
public function getPendingAccountChangesCount() {
    $query = "SELECT COUNT(*) FROM users WHERE need_change = 1";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchColumn();
}
// Zmiana roli user executor user
public function changeUserRole($user_id, $new_role) {
    $query = "UPDATE users SET role = :new_role, need_change = 0 WHERE id = :user_id";
    $stmt = $this->pdo->prepare($query);
    return $stmt->execute([':new_role' => $new_role, ':user_id' => $user_id]);
}
// Dla charts
public function getNewUsersPerDay() {
    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at > NOW() - INTERVAL 7 DAY GROUP BY DATE(created_at)";
    return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

}
?>
