<?php
session_start();
include_once('../../models/Message.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['conversation_ids'])) {
    $messageModel = new Message();
    
    // Pobieramy tablicę zaznaczonych identyfikatorów konwersacji
    $conversationIds = $_POST['conversation_ids'];

    try {
        // Sprawdzamy, czy została zaznaczona przynajmniej jedna konwersacja
        if (empty($conversationIds)) {
            throw new Exception("Nie wybrano żadnej konwersacji do usunięcia.");
        }

        // Usuwamy konwersacje z bazy danych
        $messageModel->deleteConversations($conversationIds);

        // Ustawiamy komunikat o sukcesie
        $_SESSION['message'] = "Konwersacje zostały pomyślnie usunięte.";
        $_SESSION['message_type'] = 'success';

    } catch (Exception $e) {
        // Ustawiamy komunikat o błędzie
        $_SESSION['message'] = "Wystąpił błąd: " . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }

    // Po zakończeniu przekierowujemy użytkownika z powrotem do strony konwersacji
    header("Location: manage_conversations.php");
    exit();
} else {
    // Jeśli brak zaznaczonych konwersacji, przekierowujemy z powrotem
    $_SESSION['message'] = "Nie wybrano żadnej konwersacji.";
    $_SESSION['message_type'] = 'warning';
    header("Location: manage_conversations.php");
    exit();
}
?>
