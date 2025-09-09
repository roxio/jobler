<?php
session_start();
require_once 'config/config.php';
require_once 'models/Newsletter.php';
require_once 'models/User.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Podaj poprawny adres email.']);
        exit;
    }

    $newsletter = new Newsletter();
    $userModel = new User();

    // Sprawdź czy użytkownik jest zalogowany
    $userId = null;
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        // Aktualizuj preferencje użytkownika
        $userModel->updateNewsletterPreference($userId, true);
    }

    // Zapisz do newslettera
    $result = $newsletter->subscribe($email, $userId);

    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Nieprawidłowe żądanie.']);
?>