<?php

include_once('../models/Job.php');
include_once('../models/User.php');

class UserController {
    // Wyświetlenie dashboarda użytkownika
    public function dashboard() {
        // Pobranie ogłoszeń stworzonych przez użytkownika
        $userJobs = Job::getJobsByUser($_SESSION['user_id']);
        include('../views/user/dashboard.php');
    }

    // Dodawanie ogłoszenia
    public function createJob() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Przetwarzanie danych formularza
            $title = $_POST['title'];
            $description = $_POST['description'];

            // Dodanie ogłoszenia do bazy danych
            $jobId = Job::createJob($_SESSION['user_id'], $title, $description);
            header("Location: dashboard.php");
        } else {
            // Wyświetlenie formularza do tworzenia ogłoszenia
            include('../views/user/create_job.php');
        }
    }

    // Edytowanie ogłoszenia
    public function editJob($jobId) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Pobranie danych z formularza
            $title = $_POST['title'];
            $description = $_POST['description'];

            // Aktualizacja ogłoszenia w bazie
            Job::updateJob($jobId, $title, $description);
            header("Location: dashboard.php");
        } else {
            // Pobranie ogłoszenia z bazy do edycji
            $job = Job::getJobById($jobId);
            include('../views/user/create_job.php');
        }
    }

    // Usuwanie ogłoszenia
    public function deleteJob($jobId) {
        // Usunięcie ogłoszenia
        Job::deleteJob($jobId);
        header("Location: dashboard.php");
    }
}
?>