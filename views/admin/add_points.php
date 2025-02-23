<?php
include_once('../../models/User.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['points_to_add'])) {
    $userModel = new User();

    $userId = $_POST['user_id'];
    $pointsToAdd = (float)$_POST['points_to_add']; // Używamy typu float dla punktów

    // Zaktualizowanie salda konta użytkownika
    $result = $userModel->addPointsToUser($userId, $pointsToAdd);

    // Sprawdzamy wynik operacji
    if ($result) {
        header('Location: manage_users.php?status=points_added');
    } else {
        header('Location: manage_users.php?status=error');
    }
    exit;
}
?>
