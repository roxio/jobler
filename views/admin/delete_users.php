<?php
include_once('../../models/User.php');

// Utwórz instancję klasy User
$userModel = new User();

// Sprawdzenie, czy zostały przekazane dane do usunięcia
if (isset($_POST['user_ids']) && !empty($_POST['user_ids'])) {
    // Pobierz tablicę ID użytkowników do usunięcia
    $userIds = $_POST['user_ids'];

    // Usuwanie użytkowników z bazy danych
    foreach ($userIds as $userId) {
        $userModel->deleteUser($userId);
    }

    // Przekierowanie do strony zarządzania użytkownikami z komunikatem o sukcesie
    header('Location: ../admin/manage_users.php?status=deleted');
    exit();
} else {
    // Jeśli nie zaznaczymy żadnego użytkownika
    header('Location: ../admin/manage_users.php?status=error');
    exit();
}
?>