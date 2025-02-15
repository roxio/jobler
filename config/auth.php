<?php
session_start();
include('../config/config.php');

// Sprawdzenie, czy użytkownik jest zalogowany
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Pobranie roli użytkownika
function get_user_role($user_id) {
    global $conn;
    $query = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    return $row['role'] ?? null;
}

// Wymuszenie logowania
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}
?>