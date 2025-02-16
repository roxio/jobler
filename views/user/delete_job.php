<?php
// Rozpocznij sesję
session_start();

// Załaduj modele
include_once('../../models/Job.php');

// Utwórz instancję klasy Job
$jobModel = new Job();

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

// Sprawdź, czy przesłano ID ogłoszenia
if (!isset($_GET['id'])) {
    header('Location: /views/user/dashboard.php');
    exit;
}

$jobId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Pobierz szczegóły ogłoszenia
$job = $jobModel->getJobDetails($jobId);

// Sprawdź, czy ogłoszenie należy do użytkownika
if ($job['user_id'] != $userId) {
    header('Location: /views/user/dashboard.php');
    exit;
}

// Usuń ogłoszenie
$jobModel->deleteJob($jobId);

// Przekieruj na dashboard
header('Location: /views/user/dashboard.php');
exit;
?>