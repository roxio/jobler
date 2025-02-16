<?php

include_once('../models/User.php');
include_once('../models/Job.php');
include_once('../models/Message.php');

class AdminController {
    // Wyświetlenie dashboarda administratora
    public function dashboard() {
        // Pobranie wszystkich użytkowników i ogłoszeń
        $users = User::getAllUsers();
        $jobs = Job::getAllJobs();
		$userCount = User::getUserCount();
        $jobCount = Job::getJobCount();
        include('../views/admin/dashboard.php');
    }

    // Zarządzanie użytkownikami (dodawanie, edytowanie, usuwanie)
    public function manageUsers() {
        $users = User::getAllUsers();
        include('../views/admin/manage_users.php');
    }

    // Edytowanie użytkownika
    public function editUser($userId) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Przetwarzanie edycji użytkownika
            $username = $_POST['username'];
            $email = $_POST['email'];
            $role = $_POST['role'];

            // Aktualizacja danych użytkownika
            User::updateUser($userId, $username, $email, $role);
            header("Location: manage_users.php");
        } else {
            // Pobranie danych użytkownika do edycji
            $user = User::getUserById($userId);
            include('../views/admin/edit_user.php');
        }
    }

    // Usuwanie użytkownika
    public function deleteUser($userId) {
        // Usunięcie użytkownika
        User::deleteUser($userId);
        header("Location: manage_users.php");
    }

    // Zarządzanie ogłoszeniami (dodawanie, edytowanie, usuwanie)
    public function manageJobs() {
        $jobs = Job::getAllJobs();
        include('../views/admin/manage_jobs.php');
    }

    // Edytowanie ogłoszenia
    public function editJob($jobId) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Przetwarzanie edycji ogłoszenia
            $title = $_POST['title'];
            $description = $_POST['description'];

            // Aktualizacja ogłoszenia
            Job::updateJob($jobId, $title, $description);
            header("Location: manage_jobs.php");
        } else {
            // Pobranie ogłoszenia do edycji
            $job = Job::getJobById($jobId);
            include('../views/admin/edit_job.php');
        }
    }

    // Usuwanie ogłoszenia
    public function deleteJob($jobId) {
        // Usunięcie ogłoszenia
        Job::deleteJob($jobId);
        header("Location: manage_jobs.php");
    }

    // Przeglądanie odpowiedzi na ogłoszenia
    public function viewResponses($jobId) {
        $responses = Message::getJobResponses($jobId);
        include('../views/admin/job_responses.php');
    }
}
?>