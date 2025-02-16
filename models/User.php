<?php

include_once('Database.php');

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection(); // Połączenie z bazą danych
    }

    // Rejestracja użytkownika
    public function register($username, $email, $password, $role = 'user') {
        // Sprawdzanie, czy użytkownik już istnieje
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return false; // Użytkownik już istnieje
        }

        // Hashowanie hasła
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Wstawianie nowego użytkownika do bazy
        $sql = "INSERT INTO users (username, email, password, role, created_at, updated_at) 
                VALUES (:username, :email, :password, :role, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    // Logowanie użytkownika
    public function login($email, $password) {
        // Sprawdzanie, czy użytkownik istnieje
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Ustawienie sesji użytkownika
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            return true; // Zalogowany pomyślnie
        }

        return false; // Błędne dane logowania
    }

    // Sprawdzanie, czy użytkownik jest zalogowany
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Pobranie danych użytkownika na podstawie ID
    public function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Aktualizacja danych użytkownika
    public function updateUser($userId, $username, $email) {
        $sql = "UPDATE users SET username = :username, email = :email, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
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

    // Pobranie wszystkich użytkowników (np. do panelu administracyjnego)
    public function getAllUsers() {
        $sql = "SELECT * FROM users";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
	
	  public function getUserCount() {
        $sql = "SELECT COUNT(*) FROM users";  // Zmienna zależna od struktury Twojej tabeli
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();  // Zwraca liczbę użytkowników
    }

}
?>
