<?php
session_start();
require_once('../../models/Message.php');
require_once('../../models/User.php');

// Sprawdzenie, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$conversationId = isset($_GET['conversation_id']) ? $_GET['conversation_id'] : null;
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;

if (!$conversationId) {
    die('Nieprawidłowy identyfikator konwersacji.');
}

$messageModel = new Message();
$userModel = new User();

// Pobranie wiadomości na podstawie conversation_id
$messages = $messageModel->getConversationById($conversationId);

// Jeśli job_id nie został przekazany w URL, spróbuj wywnioskować go z pierwszej wiadomości
if (!$jobId) {
    if (!empty($messages)) {
         $jobId = $messages[0]['job_id'];
    } else {
         die('Nieprawidłowy identyfikator ogłoszenia.');
    }
}

// Obsługa formularza wysyłania wiadomości
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $messageContent = trim($_POST['message']);

    if (!empty($messageContent)) {
        // Próba rozbicia conversation_id na dwie części
        $parts = explode('_', $conversationId);
        if (count($parts) == 2) {
            list($id1, $id2) = $parts;
            // Ustalanie, który z identyfikatorów jest odbiorcą
            $receiverId = ($userId == $id1) ? $id2 : $id1;
        } else {
            // Jeśli conversation_id nie ma oczekiwanego formatu, wywnioskuj odbiorcę z pierwszej wiadomości
            if (!empty($messages)) {
                $firstMessage = $messages[0];
                // Jeśli pierwszy komunikat został wysłany przez zalogowanego użytkownika,
                // odbiorcą będzie druga strona, w przeciwnym razie – druga strona to nadawca.
                $receiverId = ($firstMessage['sender_id'] == $userId) 
                              ? $firstMessage['receiver_id'] 
                              : $firstMessage['sender_id'];
            } else {
                die('Nieprawidłowy format conversation_id.');
            }
        }

        $messageModel->sendMessage($userId, $receiverId, $messageContent, $jobId);
        // Przekierowanie z powrotem do konwersacji
        header("Location: conversation.php?conversation_id={$conversationId}&job_id={$jobId}");
        exit;
    }
}

include('../partials/header.php');
?>

<div class="container">
    <h3>Rozmowa</h3>

    <div class="messages">
        <?php if (empty($messages)): ?>
            <p>Brak wiadomości w tej konwersacji.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($messages as $msg): ?>
                    <li class="list-group-item <?php echo $msg['sender_id'] == $userId ? 'text-end' : ''; ?>">
                        <p><strong><?php echo htmlspecialchars($msg['sender_name']); ?>:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($msg['message_content'])); ?></p>
                        <small><?php echo date('d-m-Y H:i', strtotime($msg['created_at'])); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <form method="post" class="mt-4">
        <div class="form-group">
            <textarea name="message" class="form-control" placeholder="Wpisz wiadomość..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary mt-2">Wyślij</button>
    </form>
</div>

<?php include('../partials/footer.php'); ?>
