<?php

include_once('../models/Job.php');
include_once('../models/Message.php');

class ExecutorController {
    // Wyświetlenie dashboarda wykonawcy
    public function dashboard() {
        // Pobranie dostępnych ogłoszeń
        $availableJobs = Job::getAvailableJobs();
        include('../views/executor/dashboard.php');
    }

    // Odpowiadanie na ofertę (wysyłanie wiadomości do użytkownika)
    public function respondOffer($jobId) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Pobranie treści wiadomości
            $messageContent = $_POST['message'];

            // Wysłanie wiadomości do użytkownika
            $responseId = Message::sendMessage($_SESSION['user_id'], $jobId, $messageContent);
            header("Location: dashboard.php");
        } else {
            // Pobranie szczegółów ogłoszenia
            $job = Job::getJobById($jobId);
            include('../views/executor/respond_offer.php');
        }
    }

    // Wyświetlanie odpowiedzi na ogłoszenie (historia odpowiedzi)
    public function viewResponses($jobId) {
        $responses = Message::getJobResponses($jobId);
        include('../views/executor/offer_list.php');
    }
}
?>